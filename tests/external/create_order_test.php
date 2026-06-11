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
 * Tests for the create_order web service.
 *
 * @package    paygw_cashfree
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_cashfree\external;

use core_payment\helper;
use GuzzleHttp\Psr7\Response;

/**
 * Tests for \paygw_cashfree\external\create_order.
 *
 * @covers \paygw_cashfree\external\create_order
 */
final class create_order_test extends \advanced_testcase {

    /**
     * Create an enrol_fee payable on a Cashfree account.
     *
     * @return \stdClass component, paymentarea, itemid
     */
    private function create_payable(): \stdClass {
        global $DB;

        $generator = $this->getDataGenerator();
        $account = $generator->get_plugin_generator('core_payment')->create_payment_account(['gateways' => 'cashfree']);
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
        $itemid = enrol_get_plugin('fee')->add_instance($course, [
            'courseid' => $course->id,
            'customint1' => $account->get('id'),
            'cost' => 250,
            'currency' => 'INR',
            'roleid' => $studentrole->id,
        ]);

        return (object) [
            'component' => 'enrol_fee',
            'paymentarea' => 'fee',
            'itemid' => (int) $itemid,
        ];
    }

    /**
     * A successful POST /pg/orders returns the order id and payment session id.
     */
    public function test_creates_order_and_returns_session(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user(['phone1' => '9876543210']));
        $p = $this->create_payable();

        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(200, [], json_encode([
            'order_id' => 'order_abc',
            'payment_session_id' => 'session_xyz',
            'order_status' => 'ACTIVE',
        ])));

        $result = create_order::execute($p->component, $p->paymentarea, $p->itemid);

        $this->assertEquals('order_abc', $result['orderid']);
        $this->assertEquals('session_xyz', $result['paymentsessionid']);
    }

    /**
     * The request body carries the order tags (for webhook resolution), amount and currency.
     */
    public function test_request_body_carries_tags_and_amount(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['phone1' => '9876543210']);
        $this->setUser($user);
        $p = $this->create_payable();

        $history = [];
        ['mock' => $mock] = $this->get_mocked_http_client($history);
        $mock->append(new Response(200, [], json_encode([
            'order_id' => 'order_abc',
            'payment_session_id' => 'session_xyz',
        ])));

        create_order::execute($p->component, $p->paymentarea, $p->itemid);

        $body = json_decode((string) $history[0]['request']->getBody(), true);

        $this->assertEquals('enrol_fee', $body['order_tags']['component']);
        $this->assertEquals('fee', $body['order_tags']['paymentarea']);
        $this->assertEquals((string) $p->itemid, $body['order_tags']['itemid']);
        $this->assertEquals((string) $user->id, $body['order_tags']['userid']);
        $this->assertEquals('INR', $body['order_currency']);
        $this->assertEquals(250, $body['order_amount']);
        $this->assertArrayHasKey('return_url', $body['order_meta']);
    }

    /**
     * A response without a payment session id surfaces an error.
     */
    public function test_missing_session_throws(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user(['phone1' => '9876543210']));
        $p = $this->create_payable();

        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(400, [], json_encode(['message' => 'bad request'])));

        $this->expectException(\moodle_exception::class);
        create_order::execute($p->component, $p->paymentarea, $p->itemid);
    }

    /**
     * A user with no phone falls back to the placeholder number Cashfree requires.
     */
    public function test_phone_fallback(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user(['phone1' => '']));
        $p = $this->create_payable();

        $history = [];
        ['mock' => $mock] = $this->get_mocked_http_client($history);
        $mock->append(new Response(200, [], json_encode([
            'order_id' => 'order_abc',
            'payment_session_id' => 'session_xyz',
        ])));

        create_order::execute($p->component, $p->paymentarea, $p->itemid);

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertEquals('9999999999', $body['customer_details']['customer_phone']);
    }
}
