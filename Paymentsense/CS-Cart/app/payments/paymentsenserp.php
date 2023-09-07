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

require_once __DIR__ . '/paymentsenserp_files/PaymentsenseRpPhrases.php';
require_once __DIR__ . '/paymentsenserp_files/PaymentsenseRpCurrencyCode.php';
require_once __DIR__ . '/paymentsenserp_files/PaymentsenseRemotePayments.php';

function ___($key)
{
    return PaymentsenseRpPhrases::__($key);
}

if (defined('PAYMENT_NOTIFICATION')) {
    $error_message = '';
    /** @var string $mode */
    switch ($mode) {
        case PaymentsenseRemotePayments::NOTIFICATION_MODE_PROCESS:
            $order_id = null;
            if (isset($_GET['order_id'])) {
                $order_id = strpos($_GET['order_id'], '_')
                    ? substr($_GET['order_id'], 0, strpos($_GET['order_id'], '_'))
                    : $_GET['order_id'];
            }
            if ($order_id) {
                $order_info = fn_get_order_info($order_id);
                if (is_array($order_info) && isset($order_info['order_id']) && ($order_info['order_id'] === $order_id)) {
                    $payment_method = new PaymentsenseRemotePayments($order_info['payment_method']);
                    if (fn_check_payment_script(basename(__FILE__), $order_id)) {
                        $payment_method->processGatewayResponse($order_id);
                    } else {
                        $error_message = ___('invalid_payment_script');
                    }
                } else {
                    $error_message = ___('invalid_order_id');
                }
            } else {
                $error_message = ___('empty_order_id');
            }

            break;
        case PaymentsenseRemotePayments::NOTIFICATION_MODE_MODULE_INFO:
            $payment_method = new PaymentsenseRemotePayments();
            $payment_method->processModuleInfoRequest();
            break;
        default:
            break;
    }
    $tpl_vars = [
        'error_title'   => ___('bad_request_title'),
        'error_message' => ___("bad_request_message") . ' '
            . $error_message . ' '
            . ___("bad_request_message_additional")
    ];
    $tpl_name = 'error';
} else {
    /** @var array $processor_data */
    /** @var array $order_info */
    $payment_method = new PaymentsenseRemotePayments($processor_data);
    list($form_data, $error_message) = $payment_method->getPaymentFormData($order_info);
    if ($error_message) {
        $order_id = $payment_method->getOrderId($order_info);
        if ($order_id) {
            $order_info = fn_get_order_info($order_id);
            if (is_array($order_info) && isset($order_info['order_id']) && ($order_info['order_id'] === $order_id)) {
                $payment_method = new PaymentsenseRemotePayments($order_info['payment_method']);
                if (fn_check_payment_script(basename(__FILE__), $order_id)) {
                    $payment_details = [
                        'reason_text'    => $error_message,
                        'order_status'   => PaymentsenseRemotePayments::CSCART_ORDER_STATUS_INCOMPLETE,
                        'transaction_id' => ''
                    ];
                    fn_finish_payment($order_id, $payment_details);
                }
            }
        }
        $tpl_vars = [
            'title'                         => $payment_method->getTitle($order_info),
            'total'                         => $order_info['total'],
            'message'                       => ___('error_unexpected_frontend'),
            'return_url'                    => $payment_method->getCustomerRedirectUrl($order_info),
            'client_js_url'                 => '',
            'button_pay'                    => '',
            'button_processing'             => '',
            'payment_of'                    => ___('payment_of'),
            'confirm_order_cancellation'    => ___('confirm_order_cancellation'),
            'payment_details_amount'        => '',
            'payment_details_currency_code' => '',
            'payment_details_payment_token' => ''
        ];
    } else {
        $tpl_vars = [
            'total'                         => $order_info['total'],
            'title'                         => $payment_method->getTitle($order_info),
            'message'                       => sprintf(___('order_created'), $order_info['order_id']),
            'return_url'                    => $payment_method->getCustomerRedirectUrl($order_info),
            'client_js_url'                 => $payment_method->getClientJsUrl(),
            'button_pay'                    => ___('button_pay'),
            'button_processing'             => ___('button_processing'),
            'payment_of'                    => ___('payment_of'),
            'confirm_order_cancellation'    => ___('confirm_order_cancellation'),
            'payment_details_amount'        => $form_data['amount'],
            'payment_details_currency_code' => $form_data['currency_code'],
            'payment_details_payment_token' => $form_data['access_token'],
        ];
    }
    $tpl_name = 'payment_form';
}
$view = Tygh::$app['view'];
$view->assign($tpl_vars, null, true);
$view->display("addons/paymentsenserp/{$tpl_name}.tpl");
exit;
