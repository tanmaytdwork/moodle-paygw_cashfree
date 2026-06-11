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
 * Tests for the Cashfree REST API helper.
 *
 * @package    paygw_cashfree
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_cashfree;

/**
 * Tests for cashfree_helper, focused on webhook signature verification.
 *
 * @covers \paygw_cashfree\cashfree_helper
 */
final class cashfree_helper_test extends \advanced_testcase {

    /** @var string A sample secret key used across the signature tests. */
    private const SECRET = 'test_secret_key';

    /**
     * Build a helper with a known secret. The clientid and environment are irrelevant
     * to signature verification, which only uses the secret.
     *
     * @param string $secret
     * @return cashfree_helper
     */
    private function make_helper(string $secret = self::SECRET): cashfree_helper {
        return new cashfree_helper('test_client_id', $secret, true);
    }

    /**
     * Compute the signature exactly as Cashfree does: base64(HMAC-SHA256(ts + body, secret)).
     *
     * @param string $timestamp
     * @param string $body
     * @param string $secret
     * @return string
     */
    private function sign(string $timestamp, string $body, string $secret = self::SECRET): string {
        return base64_encode(hash_hmac('sha256', $timestamp . $body, $secret, true));
    }

    /**
     * A correctly computed signature is accepted.
     */
    public function test_verify_webhook_signature_valid(): void {
        $helper = $this->make_helper();
        $timestamp = '1700000000';
        $body = '{"type":"PAYMENT_SUCCESS_WEBHOOK"}';

        $signature = $this->sign($timestamp, $body);

        $this->assertTrue($helper->verify_webhook_signature($body, $timestamp, $signature));
    }

    /**
     * A signature produced with a different secret is rejected.
     */
    public function test_verify_webhook_signature_wrong_secret(): void {
        $helper = $this->make_helper();
        $timestamp = '1700000000';
        $body = '{"type":"PAYMENT_SUCCESS_WEBHOOK"}';

        $signature = $this->sign($timestamp, $body, 'a_different_secret');

        $this->assertFalse($helper->verify_webhook_signature($body, $timestamp, $signature));
    }

    /**
     * Tampering with a single byte of the body invalidates the signature.
     */
    public function test_verify_webhook_signature_tampered_body(): void {
        $helper = $this->make_helper();
        $timestamp = '1700000000';
        $body = '{"order_amount":100.00}';

        $signature = $this->sign($timestamp, $body);
        $tampered = '{"order_amount":900.00}';

        $this->assertFalse($helper->verify_webhook_signature($tampered, $timestamp, $signature));
    }

    /**
     * An empty signature or timestamp is rejected without computing anything.
     */
    public function test_verify_webhook_signature_empty(): void {
        $helper = $this->make_helper();
        $body = '{"type":"PAYMENT_SUCCESS_WEBHOOK"}';

        $this->assertFalse($helper->verify_webhook_signature($body, '1700000000', ''));
        $this->assertFalse($helper->verify_webhook_signature($body, '', 'someSignature'));
    }
}
