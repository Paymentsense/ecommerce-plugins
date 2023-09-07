<?php

/**
 * Translations for strings used in Paymentsense - Remote Payments module.
 *
 * @license GNU Public License V2.0
 */

define('MODULE_PAYMENT_PAYMENTSENSE_TEXT_TITLE', 'Paymentsense');
define('MODULE_PAYMENT_PAYMENTSENSE_TEXT_DESCRIPTION', 'Process Payments via the Paymentsense - Remote Payments Secure Gateway');
define('MODULE_PAYMENT_PAYMENTSENSE_TEXT_VERSION', '3.0');
define('MODULE_PAYMENT_PAYMENTSENSE_TEXT_CODE', 'paymentsense');
define('MODULE_PAYMENT_PAYMENTSENSE_BUTTON_PAYMENT_LABEL', 'Pay with Paymentsense');
define('MODULE_PAYMENT_PAYMENTSENSE_TEXT_CATALOG_LOGO', '<img src="'.HTTPS_SERVER . DIR_WS_HTTPS_CATALOG.'images/paymentsense/logo.png" style="vertical-align:middle" alt="' . MODULE_PAYMENT_PAYMENTSENSE_TEXT_TITLE . '" title="' . MODULE_PAYMENT_PAYMENTSENSE_TEXT_TITLE . '" />' .
    '<span>'.MODULE_PAYMENT_PAYMENTSENSE_TEXT_TITLE . '</span>');
define('MODULE_PAYMENT_PAYMENTSENSE_TEXT_SSL_NOT_ENABLED', 'Please enable SSL/TLS.');
define('MODULE_PAYMENT_PAYMENTSENSE_TEXT_UNKNOWN_STATUS_CODE', 'Payment status is unknown because of a communication error or unknown/unsupported payment status. Status Code @statusCode; Message: @gatewayMessage');
define('MODULE_PAYMENT_PAYMENTSENSE_TEXT_EMPTY_ACCESS_TOKEN', 'Access token is empty. Please contact customer support.');
define('MODULE_PAYMENT_PAYMENTSENSE_TEXT_CURL_ERROR', 'An error has occurred. (cURL Error No: @curlErrorNumber, cURL Error Message: @curlErrorMessage, HTTP Code: @httpCode).');
define('MODULE_PAYMENT_PAYMENTSENSE_TEXT_PAYMENT_FAILED', 'Payment failed due to: @gatewayMessage');
define('MODULE_PAYMENT_PAYMENTSENSE_TEXT_CURL_NOT_ENABLED', 'Curl not enabled. Please enable curl.');
define('MODULE_PAYMENT_PAYMENTSENSE_TEXT_UNEXPECTED_ERROR', 'An unexpected error has occurred. Please contact customer support.');
define('MODULE_PAYMENT_PAYMENTSENSE_TEXT_PAYMENT_METHOD_NOT_CONFIGURED', 'The Paymentsense - Remote Payments payment method is not configured.');
define('MODULE_PAYMENT_PAYMENTSENSE_TEXT_REFUND_FAILED', 'Refund was declined. (Status Code: @statusCode, Payment Gateway Message: @gatewayMessage).');
define('MODULE_PAYMENT_PAYMENTSENSE_TEXT_REFUND_DUPLICATED', 'Refund cannot be performed at this time, please try again after 60 seconds.');
define('MODULE_PAYMENT_PAYMENTSENSE_TEXT_ERROR_OCCURRED', 'An error occurred.');
define('MODULE_PAYMENT_PAYMENTSENSE_TEXT_NO_RETRY', 'Payment status is unknown. Please contact customer support and do not retry the payment for this order unless you are instructed to do so.');
define('MODULE_PAYMENT_PAYMENTSENSE_URL_NOT_BUILT', 'Url not built');
define('MODULE_PAYMENT_PAYMENTSENSE_TEXT_MODULE_ALREADY_INSTALLED', 'Paymentsense - Remote Payments module already installed.');

define('MODULE_PAYMENT_PAYMENTSENSE_ENTRY_REFUND_TITLE', '<strong>Refund Transaction</strong>');
define('MODULE_PAYMENT_PAYMENTSENSE_ENTRY_REFUND', 'You may refund money to the customer here:');
define('MODULE_PAYMENT_PAYMENTSENSE_TEXT_REFUND_CONFIRM_CHECK', 'Check this box to confirm your intent: ');
define('MODULE_PAYMENT_PAYMENTSENSE_ENTRY_REFUND_AMOUNT_TEXT', 'Enter the amount you wish to refund');
define('MODULE_PAYMENT_PAYMENTSENSE_ENTRY_REFUND_TEXT_COMMENTS', 'Notes (will show on Order History):');
define('MODULE_PAYMENT_PAYMENTSENSE_ENTRY_REFUND_DEFAULT_MESSAGE', 'Refund Issued');
define('MODULE_PAYMENT_PAYMENTSENSE_ENTRY_REFUND_BUTTON_TEXT', 'Refund');
define('MODULE_PAYMENT_PAYMENTSENSE_TEXT_REFUND_CONFIRM_ERROR', 'Error: You requested to do a refund but did not check the Confirmation box.');
define('MODULE_PAYMENT_PAYMENTSENSE_TEXT_INVALID_REFUND_AMOUNT', 'Error: You requested a refund but entered an invalid amount.');
define('MODULE_PAYMENT_PAYMENTSENSE_TEXT_REFUND_INITIATED', 'Refunded ');
