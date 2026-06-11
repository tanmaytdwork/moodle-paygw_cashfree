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
 * Creates a Cashfree order on the server and returns the payment session id.
 *
 * @package    paygw_cashfree
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace paygw_cashfree\external;

use core_payment\helper;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use paygw_cashfree\cashfree_helper;

/**
 * Web service that creates a Cashfree order and returns its payment session id.
 *
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_order extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Component'),
            'paymentarea' => new external_value(PARAM_AREA, 'Payment area in the component'),
            'itemid' => new external_value(PARAM_INT, 'An identifier for payment area in the component'),
        ]);
    }

    /**
     * Creates the Cashfree order.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @return array
     */
    public static function execute(string $component, string $paymentarea, int $itemid): array {
        global $USER, $DB;

        self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
        ]);

        $config = (object) helper::get_gateway_configuration($component, $paymentarea, $itemid, 'cashfree');
        $sandbox = ($config->environment ?? 'sandbox') === 'sandbox';

        $payable = helper::get_payable($component, $paymentarea, $itemid);
        $currency = $payable->get_currency();
        $surcharge = helper::get_gateway_surcharge('cashfree');
        $amount = helper::get_rounded_cost($payable->get_amount(), $currency, $surcharge);

        // Cashfree requires a customer phone. Fall back to a placeholder if the user has none.
        $phone = $DB->get_field('user', 'phone1', ['id' => $USER->id]);
        $phone = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($phone) < 10) {
            $phone = '9999999999';
        }

        $customerdetails = [
            'customer_id' => (string) $USER->id,
            'customer_name' => fullname($USER),
            'customer_email' => $USER->email,
            'customer_phone' => $phone,
        ];

        // Order tags let the (unauthenticated) webhook resolve the right config and user.
        $tags = [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => (string) $itemid,
            'userid' => (string) $USER->id,
        ];

        $returnurl = helper::get_success_url($component, $paymentarea, $itemid)->out(false);

        $cashfreehelper = new cashfree_helper($config->clientid, $config->secret, $sandbox);
        $response = $cashfreehelper->create_order($amount, $currency, $customerdetails, $returnurl, $tags);

        if (empty($response['payment_session_id']) || empty($response['order_id'])) {
            throw new \moodle_exception('ordercreatefailed', 'paygw_cashfree');
        }

        return [
            'orderid' => $response['order_id'],
            'paymentsessionid' => $response['payment_session_id'],
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'orderid' => new external_value(PARAM_TEXT, 'The Cashfree order id'),
            'paymentsessionid' => new external_value(PARAM_TEXT, 'The Cashfree payment session id'),
        ]);
    }
}
