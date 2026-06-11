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
 * Tests for the transaction_complete web service.
 *
 * @package    paygw_cashfree
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_cashfree\external;

use core_payment\helper;
use paygw_cashfree\cashfree_helper;
use GuzzleHttp\Psr7\Response;

/**
 * Tests for \paygw_cashfree\external\transaction_complete.
 *
 * The branch logic lives in util::verify_and_deliver (covered by util_test); here we
 * confirm the web service wires the current user through to a successful delivery.
 *
 * @covers \paygw_cashfree\external\transaction_complete
 */
final class transaction_complete_test extends \advanced_testcase {
    /**
     * A PAID order for the current user delivers and reports success.
     */
    public function test_paid_transaction_delivers(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

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
        $itemid = (int) enrol_get_plugin('fee')->add_instance($course, [
            'courseid' => $course->id,
            'customint1' => $account->get('id'),
            'cost' => 250,
            'currency' => 'INR',
            'roleid' => $studentrole->id,
        ]);

        $amount = helper::get_rounded_cost(250, 'INR', helper::get_gateway_surcharge('cashfree'));

        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(200, [], json_encode([
            'cf_order_id' => 999,
            'order_id' => 'order_xyz',
            'order_status' => cashfree_helper::ORDER_STATUS_PAID,
            'order_amount' => $amount,
            'order_currency' => 'INR',
            'order_tags' => [
                'component' => 'enrol_fee',
                'paymentarea' => 'fee',
                'itemid' => (string) $itemid,
                'userid' => (string) $user->id,
            ],
        ])));

        $result = transaction_complete::execute('enrol_fee', 'fee', $itemid, 'order_xyz');

        $this->assertTrue($result['success']);
        $this->assertTrue($DB->record_exists('paygw_cashfree', ['cf_orderid' => 'order_xyz']));
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $user));
    }
}
