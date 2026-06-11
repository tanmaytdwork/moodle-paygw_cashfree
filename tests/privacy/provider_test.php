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
 * Tests for the paygw_cashfree privacy provider.
 *
 * @package    paygw_cashfree
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_cashfree\privacy;

use core_privacy\local\request\writer;

/**
 * Tests for \paygw_cashfree\privacy\provider.
 *
 * @covers \paygw_cashfree\privacy\provider
 */
final class provider_test extends \advanced_testcase {

    /**
     * Insert a Cashfree payment row and return its (fake) payment id.
     *
     * @param string $orderid
     * @param int $paymentid
     * @return int
     */
    private function insert_row(string $orderid, int $paymentid): int {
        global $DB;
        $DB->insert_record('paygw_cashfree', (object) [
            'paymentid' => $paymentid,
            'cf_orderid' => $orderid,
            'cf_paymentid' => 'cf_' . $orderid,
            'status' => 'PAID',
        ]);
        return $paymentid;
    }

    /**
     * Exported data contains the Cashfree order id, payment id and status.
     */
    public function test_export_payment_data(): void {
        $this->resetAfterTest();

        $paymentid = $this->insert_row('order_xyz', 4242);
        $context = \context_system::instance();
        $subcontext = ['payments'];

        provider::export_payment_data($context, $subcontext, (object) ['id' => $paymentid]);

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        // export_payment_data appends the gateway name to the subcontext.
        $exported = $writer->get_data(array_merge($subcontext, [get_string('gatewayname', 'paygw_cashfree')]));
        $this->assertEquals('order_xyz', $exported->orderid);
        $this->assertEquals('cf_order_xyz', $exported->paymentid);
        $this->assertEquals('PAID', $exported->status);
    }

    /**
     * Exporting with no matching row writes nothing.
     */
    public function test_export_with_no_row_is_noop(): void {
        $this->resetAfterTest();

        // Start from a clean writer so we only observe this export's effect.
        writer::reset();

        $context = \context_system::instance();
        provider::export_payment_data($context, ['payments'], (object) ['id' => 9999]);

        $this->assertFalse(writer::with_context($context)->has_any_data());
    }

    /**
     * delete_data_for_payment_sql removes the matching Cashfree rows.
     */
    public function test_delete_data_for_payment_sql(): void {
        global $DB;
        $this->resetAfterTest();

        $keep = $this->insert_row('order_keep', 1);
        $drop = $this->insert_row('order_drop', 2);

        // "paymentid IN (:pid)" — delete only the dropped payment's row.
        provider::delete_data_for_payment_sql(':pid', ['pid' => $drop]);

        $this->assertFalse($DB->record_exists('paygw_cashfree', ['cf_orderid' => 'order_drop']));
        $this->assertTrue($DB->record_exists('paygw_cashfree', ['cf_orderid' => 'order_keep']));
    }
}
