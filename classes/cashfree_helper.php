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
 * Helper class to work with the Cashfree PG REST API (v3).
 *
 * Uses Moodle's \core\http_client (the Guzzle-based wrapper) so that proxy, SSL and
 * cURL security (SSRF) settings are respected. No third-party SDK is bundled.
 *
 * @package    paygw_cashfree
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_cashfree;

use core\di;
use core\http_client;
use GuzzleHttp\Exception\GuzzleException;

defined('MOODLE_INTERNAL') || die();

/**
 * Cashfree PG REST API helper.
 *
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cashfree_helper {

    /** @var string The Cashfree API version sent in the x-api-version header. */
    public const API_VERSION = '2023-08-01';

    /** @var string The order has been fully paid. */
    public const ORDER_STATUS_PAID = 'PAID';

    /** @var string Cashfree App ID (client id). */
    private $clientid;

    /** @var string Cashfree Secret Key. */
    private $secret;

    /** @var string The base API URL for the selected environment. */
    private $baseurl;

    /**
     * Constructor.
     *
     * @param string $clientid The Cashfree App ID.
     * @param string $secret The Cashfree Secret Key.
     * @param bool $sandbox Whether to use the sandbox environment.
     */
    public function __construct(string $clientid, string $secret, bool $sandbox) {
        $this->clientid = $clientid;
        $this->secret = $secret;
        $this->baseurl = $sandbox ? 'https://sandbox.cashfree.com' : 'https://api.cashfree.com';
    }

    /**
     * Perform a JSON request against the Cashfree API and decode the response.
     *
     * Uses \core\http_client so the request passes through Moodle's proxy and cURL
     * security (SSRF) middleware. HTTP errors are not thrown: Cashfree returns a JSON
     * error body on 4xx/5xx, which we decode and return for the caller to inspect.
     *
     * @param string $method The HTTP method (GET, POST).
     * @param string $path The API path, relative to the base URL (e.g. /pg/orders).
     * @param array|null $payload The JSON body to send, or null for no body.
     * @return array|null The decoded response, or null on transport/decoding failure.
     */
    private function request(string $method, string $path, ?array $payload = null): ?array {
        $options = [
            'headers' => [
                'x-api-version' => self::API_VERSION,
                'x-client-id' => $this->clientid,
                'x-client-secret' => $this->secret,
            ],
            'http_errors' => false,
            'timeout' => 30,
        ];
        if ($payload !== null) {
            $options['json'] = $payload;
        }

        try {
            $client = di::get(http_client::class);
            $response = $client->request($method, $this->baseurl . $path, $options);
        } catch (GuzzleException $e) {
            debugging('Cashfree API request failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }

        $decoded = json_decode((string) $response->getBody(), true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Create a Cashfree order.
     *
     * @param float $amount The order amount.
     * @param string $currency The 3-letter currency code.
     * @param array $customerdetails Cashfree customer_details payload.
     * @param string $returnurl The return URL Cashfree redirects to after payment.
     * @param array $tags Key-value order tags (used to resolve config in the webhook).
     * @return array|null Decoded API response, or null on failure.
     */
    public function create_order(float $amount, string $currency, array $customerdetails,
            string $returnurl, array $tags): ?array {
        return $this->request('POST', '/pg/orders', [
            'order_amount' => round($amount, 2),
            'order_currency' => $currency,
            'customer_details' => $customerdetails,
            'order_meta' => [
                'return_url' => $returnurl,
            ],
            'order_tags' => $tags,
        ]);
    }

    /**
     * Fetch the details of a Cashfree order.
     *
     * @param string $orderid The Cashfree order id.
     * @return array|null Decoded API response, or null on failure.
     */
    public function get_order(string $orderid): ?array {
        return $this->request('GET', '/pg/orders/' . rawurlencode($orderid));
    }

    /**
     * Verify the signature of an incoming Cashfree webhook.
     *
     * Cashfree signs webhooks as base64(HMAC-SHA256(timestamp + rawBody, secretKey)).
     *
     * @param string $rawbody The raw request body.
     * @param string $timestamp The x-webhook-timestamp header value.
     * @param string $signature The x-webhook-signature header value.
     * @return bool True if the signature is valid.
     */
    public function verify_webhook_signature(string $rawbody, string $timestamp, string $signature): bool {
        if ($signature === '' || $timestamp === '') {
            return false;
        }
        $expected = base64_encode(hash_hmac('sha256', $timestamp . $rawbody, $this->secret, true));
        return hash_equals($expected, $signature);
    }
}
