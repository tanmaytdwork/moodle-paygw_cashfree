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
 * Cashfree repository module to encapsulate the AJAX requests.
 *
 * @module     paygw_cashfree/repository
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Fetch the mode (sandbox/production) the Cashfree JavaScript SDK should run in.
 *
 * @param {string} component Name of the component that the itemId belongs to
 * @param {string} paymentArea The area of the component that the itemId belongs to
 * @param {number} itemId An internal identifier that is used by the component
 * @returns {Promise<{mode: string}>}
 */
export const getSdkMode = (component, paymentArea, itemId) => {
    const request = {
        methodname: 'paygw_cashfree_get_sdk_mode',
        args: {
            component,
            paymentarea: paymentArea,
            itemid: itemId,
        },
    };

    return Ajax.call([request])[0];
};

/**
 * Ask the server to create a Cashfree order and return its payment session id.
 *
 * @param {string} component Name of the component that the itemId belongs to
 * @param {string} paymentArea The area of the component that the itemId belongs to
 * @param {number} itemId An internal identifier that is used by the component
 * @returns {Promise<{orderid: string, paymentsessionid: string}>}
 */
export const createOrder = (component, paymentArea, itemId) => {
    const request = {
        methodname: 'paygw_cashfree_create_order',
        args: {
            component,
            paymentarea: paymentArea,
            itemid: itemId,
        },
    };

    return Ajax.call([request])[0];
};

/**
 * Call the server to validate the order and deliver it.
 *
 * @param {string} component Name of the component that the itemId belongs to
 * @param {string} paymentArea The area of the component that the itemId belongs to
 * @param {number} itemId An internal identifier that is used by the component
 * @param {string} orderId The order id coming back from Cashfree
 * @returns {Promise<{success: boolean, message: string}>}
 */
export const markTransactionComplete = (component, paymentArea, itemId, orderId) => {
    const request = {
        methodname: 'paygw_cashfree_create_transaction_complete',
        args: {
            component,
            paymentarea: paymentArea,
            itemid: itemId,
            orderid: orderId,
        },
    };

    return Ajax.call([request])[0];
};
