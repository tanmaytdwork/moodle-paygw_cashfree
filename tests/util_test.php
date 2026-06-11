<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for the Cashfree verify-and-deliver trust boundary.
 *
 * @package    paygw_cashfree
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_cashfree;

use core_payment\helper;
use GuzzleHttp\Psr7\Response;

/**
 * Tests for util::verify_and_deliver — the server-side payment verification + delivery.
 *
 * @covers \paygw_cashfree\util
 */
final class util_test extends \advanced_testcase {

    /**
     * Create an enrol_fee payable backed by a Cashfree-enabled payment account.
     *
     * @param \stdClass $user The paying user.
     * @param float $cost The fee.
     * @param string $currency The currency code.
     * @return \stdClass component, paymentarea, itemid, userid, course, cost, currency, amount
     */
    private function create_fee_payable(\stdClass $user, float $cost = 250, string $currency = 'INR'): \stdClass {
        global $DB;

        $generator = $this->getDataGenerator();
        $account = $generator->get_plugin_generator('core_payment')->create_payment_account(['gateways' => 'cashfree']);

        // Store a real config so cashfree_helper receives strings (the API is mocked anyway).
        helper::save_payment_gateway((object) [
            'accountid' => $account->get('id'),
            'gateway' => 'cashfree',
            'enabled' => 1,
            'config' => json_encode([
                'clientid' => 'test_client_id',
                'secret' => 'test_secret',
                'environment' => 'sandbox',
            ]),
        ]);

        $course = $generator->create_course();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $feeplugin = enrol_get_plugin('fee');
        $itemid = $feeplugin->add_instance($course, [
            'courseid' => $course->id,
            'customint1' => $account->get('id'),
            'cost' => $cost,
            'currency' => $currency,
            'roleid' => $studentrole->id,
        ]);

        $payable = helper::get_payable('enrol_fee', 'fee', (int) $itemid);
        $amount = helper::get_rounded_cost($payable->get_amount(), $currency, helper::get_gateway_surcharge('cashfree'));

        return (object) [
            'component' => 'enrol_fee',
            'paymentarea' => 'fee',
            'itemid' => (int) $itemid,
            'userid' => (int) $user->id,
            'course' => $course,
            'cost' => $cost,
            'currency' => $currency,
            'amount' => $amount,
        ];
    }

    /**
     * Build a Cashfree order payload echoing the given tags/amount/status.
     *
     * @param \stdClass $p The payable descriptor.
     * @param array $overrides Keys to override (order_status, order_amount, order_currency, order_tags).
     * @return array
     */
    private function order_payload(\stdClass $p, array $overrides = []): array {
        return array_merge([
            'cf_order_id' => 1234567,
            'order_id' => 'order_xyz',
            'order_status' => cashfree_helper::ORDER_STATUS_PAID,
            'order_amount' => $p->amount,
            'order_currency' => $p->currency,
            'order_tags' => [
                'component' => $p->component,
                'paymentarea' => $p->paymentarea,
                'itemid' => (string) $p->itemid,
                'userid' => (string) $p->userid,
            ],
        ], $overrides);
    }

    /**
     * Happy path: a PAID order with matching tags/amount is delivered exactly once.
     */
    public function test_paid_order_is_delivered(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $p = $this->create_fee_payable($user);

        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(200, [], json_encode($this->order_payload($p))));

        $sink = $this->redirectEvents();
        $result = util::verify_and_deliver($p->component, $p->paymentarea, $p->itemid, 'order_xyz', $p->userid);
        $events = $sink->get_events();
        $sink->close();

        $this->assertTrue($result['success']);
        $this->assertTrue($DB->record_exists('paygw_cashfree', ['cf_orderid' => 'order_xyz']));
        $this->assertEquals(1, $DB->count_records('payments', ['userid' => $user->id]));

        // The payment_completed event fired.
        $names = array_map(fn($e) => $e->eventname, $events);
        $this->assertContains('\\paygw_cashfree\\event\\payment_completed', $names);

        // deliver_order enrolled the user.
        $this->assertTrue(is_enrolled(\context_course::instance($p->course->id), $user));
    }

    /**
     * Regression test for the order-binding security fix: an order whose tags name a
     * different item/user is rejected and nothing is delivered.
     */
    public function test_order_tags_mismatch_is_rejected(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $p = $this->create_fee_payable($user);

        // Cashfree returns an order whose tags point at a different item and user.
        $payload = $this->order_payload($p, [
            'order_tags' => [
                'component' => $p->component,
                'paymentarea' => $p->paymentarea,
                'itemid' => (string) ($p->itemid + 999),
                'userid' => (string) ($p->userid + 999),
            ],
        ]);

        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(200, [], json_encode($payload)));

        $result = util::verify_and_deliver($p->component, $p->paymentarea, $p->itemid, 'order_xyz', $p->userid);

        $this->assertFalse($result['success']);
        $this->assertEquals(get_string('ordermismatch', 'paygw_cashfree'), $result['message']);
        $this->assertFalse($DB->record_exists('paygw_cashfree', ['cf_orderid' => 'order_xyz']));
        $this->assertFalse(is_enrolled(\context_course::instance($p->course->id), $user));
    }

    /**
     * An order that is not PAID is rejected.
     */
    public function test_unpaid_order_is_rejected(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $p = $this->create_fee_payable($user);

        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(200, [], json_encode($this->order_payload($p, ['order_status' => 'ACTIVE']))));

        $result = util::verify_and_deliver($p->component, $p->paymentarea, $p->itemid, 'order_xyz', $p->userid);

        $this->assertFalse($result['success']);
        $this->assertEquals(get_string('paymentnotcleared', 'paygw_cashfree'), $result['message']);
        $this->assertFalse($DB->record_exists('paygw_cashfree', ['cf_orderid' => 'order_xyz']));
    }

    /**
     * A PAID order whose amount differs from the expected fee is rejected.
     */
    public function test_amount_mismatch_is_rejected(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $p = $this->create_fee_payable($user);

        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(200, [], json_encode($this->order_payload($p, ['order_amount' => $p->amount + 50]))));

        $result = util::verify_and_deliver($p->component, $p->paymentarea, $p->itemid, 'order_xyz', $p->userid);

        $this->assertFalse($result['success']);
        $this->assertEquals(get_string('amountmismatch', 'paygw_cashfree'), $result['message']);
        $this->assertFalse($DB->record_exists('paygw_cashfree', ['cf_orderid' => 'order_xyz']));
    }

    /**
     * A PAID order in the wrong currency is rejected.
     */
    public function test_currency_mismatch_is_rejected(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $p = $this->create_fee_payable($user);

        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(200, [], json_encode($this->order_payload($p, ['order_currency' => 'USD']))));

        $result = util::verify_and_deliver($p->component, $p->paymentarea, $p->itemid, 'order_xyz', $p->userid);

        $this->assertFalse($result['success']);
        $this->assertEquals(get_string('amountmismatch', 'paygw_cashfree'), $result['message']);
        $this->assertFalse($DB->record_exists('paygw_cashfree', ['cf_orderid' => 'order_xyz']));
    }

    /**
     * If the order cannot be fetched from Cashfree, nothing is delivered.
     */
    public function test_fetch_failure_is_handled(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $p = $this->create_fee_payable($user);

        // A 500 with a non-JSON body decodes to null in cashfree_helper.
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(500, [], 'Internal Server Error'));

        $result = util::verify_and_deliver($p->component, $p->paymentarea, $p->itemid, 'order_xyz', $p->userid);

        $this->assertFalse($result['success']);
        $this->assertEquals(get_string('cannotfetchorderdetails', 'paygw_cashfree'), $result['message']);
        $this->assertFalse($DB->record_exists('paygw_cashfree', ['cf_orderid' => 'order_xyz']));
    }

    /**
     * Delivery is idempotent: a second call for an already-recorded order does not
     * create a second payment, and never calls the API (the mock queue stays untouched).
     */
    public function test_delivery_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $p = $this->create_fee_payable($user);

        // Pretend this order was already delivered.
        $DB->insert_record('paygw_cashfree', (object) [
            'paymentid' => 999999,
            'cf_orderid' => 'order_xyz',
            'cf_paymentid' => '1234567',
            'status' => cashfree_helper::ORDER_STATUS_PAID,
        ]);

        // No response queued: if the code reaches the API the empty mock will error.
        $this->get_mocked_http_client();

        $before = $DB->count_records('payments', ['userid' => $user->id]);
        $result = util::verify_and_deliver($p->component, $p->paymentarea, $p->itemid, 'order_xyz', $p->userid);
        $after = $DB->count_records('payments', ['userid' => $user->id]);

        $this->assertTrue($result['success']);
        $this->assertEquals(get_string('repeatedorder', 'paygw_cashfree'), $result['message']);
        $this->assertEquals($before, $after);
    }
}
