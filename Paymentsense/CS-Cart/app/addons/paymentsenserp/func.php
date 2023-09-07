<?php
/**
 * Copyright (C) 2021 Paymentsense Ltd.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 3
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @package     CS-Cart Paymentsense Remote Payments add-on
 * @version     1.1
 * @author      Paymentsense
 * @copyright   2021 Paymentsense Ltd.
 * @license     https://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('BOOTSTRAP')) { die('Access denied'); }

if (!function_exists('fn_paymentsenserp_uninstall')) {
    /**
     * Paymentsense Remote Payments uninstaller
     */
    function fn_paymentsenserp_uninstall()
    {
        $files = [
            '/app/addons/paymentsenserp',
            '/app/payments/paymentsenserp.php',
            '/app/payments/paymentsenserp_files/PaymentsenseRemotePayments.php',
            '/app/payments/paymentsenserp_files/PaymentsenseRpCurrencyCode.php',
            '/app/payments/paymentsenserp_files/PaymentsenseRpPhrases.php',
            '/var/langs/en/addons/paymentsenserp.po',
            '/design/backend/templates/views/payments/components/cc_processors/paymentsenserp.tpl',
            '/design/themes/responsive/templates/addons/paymentsenserp/error.tpl',
            '/design/themes/responsive/templates/addons/paymentsenserp/payment_form.tpl'
        ];
        $root_dir = DIR_ROOT;
        foreach ($files as $file) {
            if (!empty($file)) {
                fn_rm($root_dir . $file);
            }
        }
    }
}
