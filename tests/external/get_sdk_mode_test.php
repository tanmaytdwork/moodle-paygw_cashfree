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
 * Tests for the get_sdk_mode web service.
 *
 * @package    paygw_cashfree
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_cashfree\external;

use core_payment\helper;

/**
 * Tests for \paygw_cashfree\external\get_sdk_mode.
 *
 * @covers \paygw_cashfree\external\get_sdk_mode
 */
final class get_sdk_mode_test extends \advanced_testcase {

    /**
     * Create an enrol_fee payable on a Cashfree account with the given environment.
     *
     * @param string $environment 'sandbox' or 'live'
     * @return \stdClass component, paymentarea, itemid
     */
    private function create_payable(string $environment = 'sandbox'): \stdClass {
        global $DB;

        $generator = $this->getDataGenerator();
        $account = $generator->get_plugin_generator('core_payment')->create_payment_account(['gateways' => 'cashfree']);
        helper::save_payment_gateway((object) [
            'accountid' => $account->get('id'),
            'gateway' => 'cashfree',
            'enabled' => 1,
            'config' => json_encode([
                'clientid' => 'public_app_id',
                'secret' => 'super_secret',
                'environment' => $environment,
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
     * The sandbox environment maps to the SDK 'sandbox' mode.
     */
    public function test_returns_sandbox_mode(): void {
        $this->resetAfterTest();
        $p = $this->create_payable('sandbox');

        $result = get_sdk_mode::execute($p->component, $p->paymentarea, $p->itemid);

        $this->assertEquals('sandbox', $result['mode']);
    }

    /**
     * The 'live' environment maps to the SDK 'production' mode.
     */
    public function test_live_environment_maps_to_production(): void {
        $this->resetAfterTest();
        $p = $this->create_payable('live');

        $result = get_sdk_mode::execute($p->component, $p->paymentarea, $p->itemid);

        $this->assertEquals('production', $result['mode']);
    }

    /**
     * Only the SDK mode is exposed; credentials must never reach the browser.
     */
    public function test_credentials_are_never_returned(): void {
        $this->resetAfterTest();
        $p = $this->create_payable('sandbox');

        $result = get_sdk_mode::execute($p->component, $p->paymentarea, $p->itemid);

        $this->assertSame(['mode'], array_keys($result));
        $this->assertArrayNotHasKey('secret', $result);
        $this->assertArrayNotHasKey('clientid', $result);
        $this->assertNotContains('super_secret', $result);
        $this->assertNotContains('public_app_id', $result);
    }
}
