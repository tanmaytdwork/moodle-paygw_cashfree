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
 * Shared verification and delivery logic for the Cashfree payment gateway.
 *
 * @package    paygw_cashfree
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_cashfree;

use core_payment\helper;
use paygw_cashfree\event\payment_completed;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared helpers used by both the AJAX return path and the webhook.
 *
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class util {

    /**
     * Verify a Cashfree order server-side and, if it is paid, deliver it.
     *
     * This is the trust boundary: the order is re-fetched from Cashfree and the
     * status, amount and currency are checked before the order is delivered. The
     * delivery is idempotent, so it is safe to call this from both the browser
     * return path and the webhook for the same order.
     *
     * @param string $component The component the item belongs to.
     * @param string $paymentarea The payment area within the component.
     * @param int $itemid The item id within the component area.
     * @param string $orderid The Cashfree order id to verify.
     * @param int $userid The id of the user the order belongs to.
     * @return array [success => bool, message => string]
     */
    public static function verify_and_deliver(string $component, string $paymentarea, int $itemid,
            string $orderid, int $userid): array {
        global $DB;

        $config = (object) helper::get_gateway_configuration($component, $paymentarea, $itemid, 'cashfree');
        $sandbox = ($config->environment ?? 'sandbox') === 'sandbox';

        $payable = helper::get_payable($component, $paymentarea, $itemid);
        $currency = $payable->get_currency();
        $surcharge = helper::get_gateway_surcharge('cashfree');
        $amount = helper::get_rounded_cost($payable->get_amount(), $currency, $surcharge);

        // Idempotency guard: if this order was already delivered, do not deliver again.
        if ($DB->record_exists('paygw_cashfree', ['cf_orderid' => $orderid])) {
            return ['success' => true, 'message' => get_string('repeatedorder', 'paygw_cashfree')];
        }

        $cashfreehelper = new cashfree_helper($config->clientid, $config->secret, $sandbox);
        $orderdetails = $cashfreehelper->get_order($orderid);

        if (empty($orderdetails) || empty($orderdetails['order_status'])) {
            return ['success' => false, 'message' => get_string('cannotfetchorderdetails', 'paygw_cashfree')];
        }

        if ($orderdetails['order_status'] !== cashfree_helper::ORDER_STATUS_PAID) {
            return ['success' => false, 'message' => get_string('paymentnotcleared', 'paygw_cashfree')];
        }

        // Bind the order to the item and user being delivered. Cashfree echoes back the
        // order_tags we set at creation time, so this is the authoritative record of what
        // the order was created for. Without this, a PAID order for one item or user could
        // be replayed against a different (same-priced) item or user. This also hardens the
        // webhook path: the component/paymentarea/itemid/userid passed in there come from the
        // request body, and checking them against Cashfree's own tags stops a request body
        // from dictating what gets delivered.
        $tags = $orderdetails['order_tags'] ?? [];
        if (($tags['component'] ?? '') !== $component
                || ($tags['paymentarea'] ?? '') !== $paymentarea
                || (string) ($tags['itemid'] ?? '') !== (string) $itemid
                || (string) ($tags['userid'] ?? '') !== (string) $userid) {
            return ['success' => false, 'message' => get_string('ordermismatch', 'paygw_cashfree')];
        }

        // Confirm the amount and currency match what Moodle expects.
        $paidamount = (float) ($orderdetails['order_amount'] ?? 0);
        $paidcurrency = (string) ($orderdetails['order_currency'] ?? '');
        if (abs($paidamount - $amount) >= 0.01 || $paidcurrency !== $currency) {
            return ['success' => false, 'message' => get_string('amountmismatch', 'paygw_cashfree')];
        }

        try {
            $paymentid = helper::save_payment($payable->get_account_id(), $component, $paymentarea,
                $itemid, $userid, $amount, $currency, 'cashfree');

            $record = new \stdClass();
            $record->paymentid = $paymentid;
            $record->cf_orderid = $orderid;
            // The order endpoint returns Cashfree's internal order reference, not a payment id;
            // store it for reconciliation. The per-payment id would require a separate API call.
            $record->cf_paymentid = isset($orderdetails['cf_order_id']) ? (string) $orderdetails['cf_order_id'] : null;
            $record->status = $orderdetails['order_status'];
            $DB->insert_record('paygw_cashfree', $record);

            payment_completed::create([
                'context' => \context_system::instance(),
                'objectid' => $paymentid,
                'userid' => $userid,
                'other' => [
                    'component' => $component,
                    'paymentarea' => $paymentarea,
                    'itemid' => $itemid,
                    'amount' => $amount,
                    'currency' => $currency,
                    'orderid' => $orderid,
                ],
            ])->trigger();

            helper::deliver_order($component, $paymentarea, $itemid, $paymentid, $userid);
        } catch (\Exception $e) {
            debugging('Exception while processing Cashfree payment: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return ['success' => false, 'message' => get_string('internalerror', 'paygw_cashfree')];
        }

        return ['success' => true, 'message' => ''];
    }
}
