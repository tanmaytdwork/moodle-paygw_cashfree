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
 * Webhook endpoint for receiving asynchronous payment events from Cashfree.
 *
 * Acts as a reliability backup to the browser return path: if the user closes the
 * browser after paying, Cashfree still notifies us here and the order is delivered.
 * Delivery is idempotent, so it is safe even when both paths run.
 *
 * @package    paygw_cashfree
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);

use core_payment\helper;
use paygw_cashfree\cashfree_helper;
use paygw_cashfree\util;

require_once(__DIR__ . '/../../../config.php');

// Read the raw body and signature headers.
$rawbody = file_get_contents('php://input');
$timestamp = $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '';
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';

$payload = json_decode($rawbody, true);
if (empty($payload) || !is_array($payload)) {
    http_response_code(400);
    exit();
}

// Resolve the Moodle payment context from the order tags we set at create time.
$tags = $payload['data']['order']['order_tags'] ?? null;
if (
    empty($tags['component']) || empty($tags['paymentarea']) ||
        !isset($tags['itemid']) || !isset($tags['userid'])
) {
    // Not a payload we can act on (e.g. a test ping). Accept it so Cashfree stops retrying.
    http_response_code(202);
    exit();
}

$component = (string) $tags['component'];
$paymentarea = (string) $tags['paymentarea'];
$itemid = (int) $tags['itemid'];
$userid = (int) $tags['userid'];

$config = (object) helper::get_gateway_configuration($component, $paymentarea, $itemid, 'cashfree');
$sandbox = ($config->environment ?? 'sandbox') === 'sandbox';

// Verify the webhook signature using this account's secret key.
$cashfreehelper = new cashfree_helper($config->clientid, $config->secret, $sandbox);
if (!$cashfreehelper->verify_webhook_signature($rawbody, $timestamp, $signature)) {
    http_response_code(401);
    exit();
}

$orderid = $payload['data']['order']['order_id'] ?? '';
if ($orderid === '') {
    http_response_code(202);
    exit();
}

// The verify_and_deliver() call re-fetches the order from Cashfree and only delivers when
// PAID, so non-success events are safely ignored. Delivery is idempotent.
util::verify_and_deliver($component, $paymentarea, $itemid, (string) $orderid, $userid);

http_response_code(200);
