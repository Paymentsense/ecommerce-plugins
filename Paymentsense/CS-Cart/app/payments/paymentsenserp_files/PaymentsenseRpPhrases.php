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

/**
 * Phrases class
 */
class PaymentsenseRpPhrases
{
    /**
     * Phrases
     */
    const PHRASES = [
        'payment_of' =>
            'Payment of',
        'order_created' =>
            'Your order #%s is created. Please enter your payment information to pay for your order.',
        'button_pay' =>
            'Pay with Paymentsense',
        'button_processing' =>
            'Processing...',
        'confirm_order_cancellation' =>
            'Your order will be cancelled. Are you sure you want to processed with the order cancellation?',
        'order_cancelled' =>
            'The order was cancelled by customer\'s request.',
        'bad_request_title' =>
            '400 Bad Request',
        'bad_request_message' =>
            'The attempted request to this URL is malformed or illegal.',
        'bad_request_message_additional' =>
            'If you believe you are receiving this message in error, please contact customer support.',
        'empty_order_id' =>
            'Order ID is empty.',
        'invalid_order_id' =>
            'Order ID is invalid.',
        'invalid_payment_script' =>
            'Invalid payment script.',
        'curl_error' =>
            'An error has occurred. (cURL Error No: %1$s, cURL Error Message: %2$s).',
        'http_error' =>
            'An error has occurred. (HTTP Status Code: %1$s, Payment Gateway Message: %2$s).',
        'error_unexpected_frontend' =>
            'An unexpected error has occurred. Please contact customer support.',
        'payment_status_unknown_title' =>
            'Payment status is unknown',
        'payment_status_unknown_backend' =>
            'Payment status is unknown because of a communication error or unknown/unsupported payment status.',
        'payment_status_unknown_frontend' =>
            'Payment status is unknown. Please contact customer support quoting your order #%s and do not retry the payment for this order unless you are instructed to do so.',
        'insecure_connection' =>
            'SSL/TLS not configured. Please enable SSL/TLS.'
    ];

    /**
     * Gets a phrase
     *
     * @param string $key
     *
     * @return string
     */
    public static function __($key)
    {
        return array_key_exists($key, self::PHRASES)
            ? self::PHRASES[$key]
            : __($key);
    }
}
