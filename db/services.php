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
 * External functions and service definitions for the Cashfree payment gateway plugin.
 *
 * @package    paygw_cashfree
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'paygw_cashfree_get_sdk_mode' => [
        'classname'   => 'paygw_cashfree\external\get_sdk_mode',
        'classpath'   => '',
        'description' => 'Returns the mode (sandbox/production) for the Cashfree JS SDK.',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'paygw_cashfree_create_order' => [
        'classname'   => 'paygw_cashfree\external\create_order',
        'classpath'   => '',
        'description' => 'Creates a Cashfree order on the server and returns the payment session id.',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'paygw_cashfree_create_transaction_complete' => [
        'classname'   => 'paygw_cashfree\external\transaction_complete',
        'classpath'   => '',
        'description' => 'Verifies a Cashfree order and delivers it when the payment is complete.',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];
