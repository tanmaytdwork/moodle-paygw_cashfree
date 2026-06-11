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
 * The paygw_cashfree payment completed event.
 *
 * @package    paygw_cashfree
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_cashfree\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event fired when a Cashfree payment has been completed and the order delivered.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *      - string component: the component the payment belongs to.
 *      - string paymentarea: the payment area within the component.
 *      - int itemid: the item id within the component area.
 *      - float amount: the amount paid.
 *      - string currency: the currency.
 *      - string orderid: the Cashfree order id.
 * }
 *
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment_completed extends \core\event\base {

    /**
     * Initialise the event data.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'payments';
    }

    /**
     * Returns the localised name of the event.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventpayment_completed', 'paygw_cashfree');
    }

    /**
     * Returns a human readable description of the event.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' completed a Cashfree payment with id '{$this->objectid}' " .
            "for '{$this->other['component']}' (order '{$this->other['orderid']}').";
    }

    /**
     * Validates the custom data.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();

        foreach (['component', 'paymentarea', 'itemid', 'amount', 'currency', 'orderid'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception("The '$key' value must be set in other.");
            }
        }
    }
}
