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
 * Contains the gateway class for the Cashfree payment gateway.
 *
 * @package    paygw_cashfree
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_cashfree;

/**
 * The gateway class for the Cashfree payment gateway.
 *
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gateway extends \core_payment\gateway {

    /**
     * The currencies supported by Cashfree.
     *
     * INR is the primary (domestic) currency. The remaining currencies are supported
     * for Cashfree international/cross-border payments where enabled on the account.
     *
     * @return string[]
     */
    public static function get_supported_currencies(): array {
        return [
            'INR', 'USD', 'EUR', 'GBP', 'AED', 'CAD', 'AUD', 'SGD',
        ];
    }

    /**
     * Configuration form for the gateway instance.
     *
     * Use $form->get_mform() to access the \MoodleQuickForm instance.
     *
     * @param \core_payment\form\account_gateway $form
     */
    public static function add_configuration_to_gateway_form(\core_payment\form\account_gateway $form): void {
        $mform = $form->get_mform();

        $mform->addElement('text', 'clientid', get_string('clientid', 'paygw_cashfree'));
        $mform->setType('clientid', PARAM_TEXT);
        $mform->addHelpButton('clientid', 'clientid', 'paygw_cashfree');

        $mform->addElement('passwordunmask', 'secret', get_string('secret', 'paygw_cashfree'));
        $mform->setType('secret', PARAM_TEXT);
        $mform->addHelpButton('secret', 'secret', 'paygw_cashfree');

        $options = [
            'live'    => get_string('live', 'paygw_cashfree'),
            'sandbox' => get_string('sandbox', 'paygw_cashfree'),
        ];

        $mform->addElement('select', 'environment', get_string('environment', 'paygw_cashfree'), $options);
        $mform->addHelpButton('environment', 'environment', 'paygw_cashfree');

        // Read-only webhook endpoint for the admin to copy into the Cashfree dashboard.
        // The URL is the same for every account; it is shown here for convenience.
        $webhookurl = (new \moodle_url('/payment/gateway/cashfree/webhook.php'))->out(false);
        $webhookfield = \html_writer::empty_tag('input', [
            'type' => 'text',
            'readonly' => 'readonly',
            'value' => $webhookurl,
            'class' => 'form-control',
            'size' => 70,
        ]);
        $mform->addElement('static', 'webhookurl', get_string('webhookurl', 'paygw_cashfree'), $webhookfield);
        $mform->addHelpButton('webhookurl', 'webhookurl', 'paygw_cashfree');
    }

    /**
     * Validates the gateway configuration form.
     *
     * @param \core_payment\form\account_gateway $form
     * @param \stdClass $data
     * @param array $files
     * @param array $errors form errors (passed by reference)
     */
    public static function validate_gateway_form(\core_payment\form\account_gateway $form,
            \stdClass $data, array $files, array &$errors): void {
        if ($data->enabled && (empty($data->clientid) || empty($data->secret))) {
            $errors['enabled'] = get_string('gatewaycannotbeenabled', 'payment');
        }
    }
}
