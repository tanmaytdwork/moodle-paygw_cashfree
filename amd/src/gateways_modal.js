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
 * This module is responsible for Cashfree content in the gateways modal.
 *
 * @module     paygw_cashfree/gateways_modal
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Repository from './repository';
import Templates from 'core/templates';
import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import {getString} from 'core/str';

/**
 * Creates and shows a modal that contains a loading placeholder.
 *
 * @returns {Promise<Modal>}
 */
const showModalWithPlaceholder = async() => await Modal.create({
    body: await Templates.render('paygw_cashfree/cashfree_button_placeholder', {}),
    show: true,
    removeOnClose: true,
});

/**
 * Loads the Cashfree v3 JavaScript SDK once.
 *
 * @returns {Promise}
 */
const loadSdk = () => {
    const sdkUrl = 'https://sdk.cashfree.com/js/v3/cashfree.js';

    if (loadSdk.loaded) {
        return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = sdkUrl;
        script.onload = () => {
            loadSdk.loaded = true;
            resolve();
        };
        script.onerror = () => reject(new Error('Failed to load the Cashfree SDK.'));
        document.head.appendChild(script);
    });
};
loadSdk.loaded = false;

/**
 * Process the payment.
 *
 * @param {string} component Name of the component that the itemId belongs to
 * @param {string} paymentArea The area of the component that the itemId belongs to
 * @param {number} itemId An internal identifier that is used by the component
 * @returns {Promise<string>}
 */
export const process = async(component, paymentArea, itemId) => {
    const [modal, sdkConfig] = await Promise.all([
        showModalWithPlaceholder(),
        Repository.getSdkMode(component, paymentArea, itemId),
    ]);

    modal.getRoot().on(ModalEvents.hidden, () => {
        modal.destroy();
    });

    const [, order] = await Promise.all([
        loadSdk(),
        Repository.createOrder(component, paymentArea, itemId),
    ]);

    const cashfree = window.Cashfree({mode: sdkConfig.mode});

    let result;
    try {
        result = await cashfree.checkout({
            paymentSessionId: order.paymentsessionid,
            redirectTarget: '_modal',
        });
    } catch (e) {
        modal.hide();
        // Reject with a string so the caller's notification shows readable text.
        throw e.message || e;
    }

    // The user closed the overlay or the SDK reported an error.
    if (result && result.error) {
        modal.hide();
        throw result.error.message;
    }

    // Payment flow finished. Verify and deliver on the server.
    modal.setBody(getString('authorising', 'paygw_cashfree'));

    const res = await Repository.markTransactionComplete(component, paymentArea, itemId, order.orderid);
    modal.hide();
    if (!res.success) {
        throw res.message;
    }

    return res.message;
};
