<?php

/**
 * Provides extra functionality to manage orders (Refund) from admin backend.
 *
 * @license GNU Public License V2.0
 */

$outputStartBlock = '';
$outputRefund = '';
$outputEndBlock = '';
$output = '';

$outputStartBlock .= '<table class="noprint">'."\n";
$outputStartBlock .= '<tr style="background-color : #bbbbbb; border: 1px solid black;">' . "\n";
$outputEndBlock .= '</tr>'."\n";
$outputEndBlock .='</table>'."\n";


if (method_exists($this, '_doRefund')) {
    $outputRefund .= '<td><table class="noprint">' . "\n";
    $outputRefund .= '<tr style="background-color : #dddddd; border: 1px solid black;">' . "\n";
    $outputRefund .= '<td class="main">' . MODULE_PAYMENT_PAYMENTSENSE_ENTRY_REFUND_TITLE . '<br />' . "\n";
    $outputRefund .= zen_draw_form('paymentsenserefund', FILENAME_ORDERS, zen_get_all_get_params(array('action')) . 'action=doRefund', 'post', '', true) . zen_hide_session_id();
    $outputRefund .= MODULE_PAYMENT_PAYMENTSENSE_ENTRY_REFUND . '<br />';
    $outputRefund .= MODULE_PAYMENT_PAYMENTSENSE_ENTRY_REFUND_AMOUNT_TEXT . ' ' . zen_draw_input_field('refamt', '', 'length="10" placeholder="amount"') . '<br />';
    $outputRefund .= MODULE_PAYMENT_PAYMENTSENSE_TEXT_REFUND_CONFIRM_CHECK . zen_draw_checkbox_field('refconfirm', '', false) . '<br />';
    $outputRefund .= '<br />' . MODULE_PAYMENT_PAYMENTSENSE_ENTRY_REFUND_TEXT_COMMENTS . '<br />' . zen_draw_textarea_field('refnote', 'soft', '50', '3', MODULE_PAYMENT_PAYMENTSENSE_ENTRY_REFUND_DEFAULT_MESSAGE);
    $outputRefund .= '<br /><input type="submit" name="buttonrefund" value="' . MODULE_PAYMENT_PAYMENTSENSE_ENTRY_REFUND_BUTTON_TEXT . '" title="' . MODULE_PAYMENT_PAYMENTSENSE_ENTRY_REFUND_BUTTON_TEXT . '" />';
    $outputRefund .= '</form>';
    $outputRefund .= '</td></tr></table></td>' . "\n";
}

if (defined('MODULE_PAYMENT_PAYMENTSENSE_STATUS') && MODULE_PAYMENT_PAYMENTSENSE_STATUS != '') {
    $output .= $outputStartBlock;
    if (method_exists($this, '_doRefund')) {
        $output .= $outputRefund;
    }
    $output .= $outputEndBlock;
}
