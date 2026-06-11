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
 * Tests for the Cashfree gateway class.
 *
 * @package    paygw_cashfree
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_cashfree;

/**
 * Tests for the gateway configuration class.
 *
 * @covers \paygw_cashfree\gateway
 */
final class gateway_test extends \advanced_testcase {
    /**
     * The plugin supports INR.
     */
    public function test_get_supported_currencies(): void {
        $currencies = gateway::get_supported_currencies();

        $this->assertEquals(['INR'], $currencies);
    }

    /**
     * Enabling the gateway with no App ID / Secret must be rejected.
     */
    public function test_validate_gateway_form_rejects_empty_keys(): void {
        // The form argument is not used by validate_gateway_form(), so a stub is fine.
        $form = $this->createStub(\core_payment\form\account_gateway::class);

        $data = (object) [
            'enabled' => 1,
            'clientid' => '',
            'secret' => '',
        ];
        $errors = [];

        gateway::validate_gateway_form($form, $data, [], $errors);

        $this->assertArrayHasKey('enabled', $errors);
    }

    /**
     * A complete configuration produces no validation errors.
     */
    public function test_validate_gateway_form_accepts_complete(): void {
        $form = $this->createStub(\core_payment\form\account_gateway::class);

        $data = (object) [
            'enabled' => 1,
            'clientid' => 'app_id_123',
            'secret' => 'secret_456',
        ];
        $errors = [];

        gateway::validate_gateway_form($form, $data, [], $errors);

        $this->assertEmpty($errors);
    }

    /**
     * A disabled gateway is not required to have keys.
     */
    public function test_validate_gateway_form_disabled_skips_key_check(): void {
        $form = $this->createStub(\core_payment\form\account_gateway::class);
        $data = (object) [
            'enabled' => 0,
            'clientid' => '',
            'secret' => '',
        ];
        $errors = [];

        gateway::validate_gateway_form($form, $data, [], $errors);

        $this->assertEmpty($errors);
    }
}
