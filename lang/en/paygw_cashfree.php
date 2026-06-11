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
 * Strings for component 'paygw_cashfree', language 'en'.
 *
 * @package    paygw_cashfree
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['amountmismatch'] = 'The amount you attempted to pay does not match the required fee. Your account has not been debited.';
$string['authorising'] = 'Authorising the payment. Please wait...';
$string['cannotfetchorderdetails'] = 'Could not fetch payment details from Cashfree. Your account has not been debited.';
$string['clientid'] = 'App ID';
$string['clientid_help'] = 'The App ID (client ID) generated for your application in the Cashfree merchant dashboard.';
$string['environment'] = 'Environment';
$string['environment_help'] = 'Set this to Sandbox to use Cashfree test credentials (for testing only). Use Live for real payments.';
$string['eventpayment_completed'] = 'Cashfree payment completed';
$string['gatewaydescription'] = 'Pay via Cashfree using UPI, cards, netbanking or wallets.';
$string['gatewayname'] = 'Cashfree';
$string['internalerror'] = 'An internal error has occurred. Please contact us.';
$string['live'] = 'Live';
$string['ordercreatefailed'] = 'Could not create a Cashfree order. Please try again or contact us.';
$string['ordermismatch'] = 'This payment does not match the item being purchased. Your account has not been debited.';
$string['paymentnotcleared'] = 'Your payment was not cleared by Cashfree.';
$string['pluginname'] = 'Cashfree';
$string['pluginname_desc'] = 'The Cashfree plugin allows you to receive payments via Cashfree.';
$string['privacy:metadata'] = 'The Cashfree plugin does not store any personal data. It stores the Cashfree order identifier associated with a payment.';
$string['repeatedorder'] = 'This order has already been processed earlier.';
$string['sandbox'] = 'Sandbox';
$string['secret'] = 'Secret key';
$string['secret_help'] = 'The Secret Key generated for your application in the Cashfree merchant dashboard. Keep this confidential.';
$string['webhookurl'] = 'Webhook URL';
$string['webhookurl_help'] = 'Copy this URL into the Cashfree merchant dashboard (Developers &rarr; Webhooks). Cashfree sends payment notifications here so that orders are still delivered if the learner closes the browser before returning. The URL must be reachable from the internet.';
