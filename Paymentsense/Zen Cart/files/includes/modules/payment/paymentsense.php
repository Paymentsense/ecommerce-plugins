<?php

/**
 * Paymentsense - Remote Payments module
 *
 * REQUIRES PHP 7.1 or newer
 *
 * @license GNU Public License V2.0
 * @version 1.0.0
 */
class paymentsense
{
    /**
     * Test connect e entry point.
     */
    public const ENTRY_POINT_URL_TEST = 'https://e.test.connect.paymentsense.cloud';
    /**
     * Production connect e entry point.
     */
    public const ENTRY_POINT_URL_PRODUCTION = 'https://e.connect.paymentsense.cloud';
    /**
     * Endpoint for access tokens
     */
    public const ENDPOINT_ACCESS_TOKENS = 'v1/access-tokens';
    /**
     * Endpoint for cross-reference payments.
     */
    public const ENDPOINT_CROSS_REFERENCE_PAYMENTS = 'v1/cross-reference-payments';
    /**
     * Endpoint for payments.
     */
    public const ENDPOINT_PAYMENTS = 'v1/payments';
    /**
     * Successful payment status.
     */
    public const PAYMENT_STATUS_SUCCESS = 0;
    /**
     * Referred payment status.
     */
    public const PAYMENT_STATUS_REFERRED = 4;
    /**
     * Declined payment status.
     */
    public const PAYMENT_STATUS_DECLINED = 5;
    /**
     * Duplicated payment status.
     */
    public const PAYMENT_STATUS_DUPLICATED = 20;
    /**
     * Failed payment status.
     */
    public const PAYMENT_STATUS_FAILED = 30;
    /**
     * Shopping cart gateway.
     */
    public const SHOPPING_CART_GATEWAY = 'Paymentsense - Remote Payments';
    /**
     * Plugin extension.
     */
    public const MODULE_VERSION = '1.0.1';
    /**
     * Code for a refund failure.
     */
    public const ERROR_CODE_REFUND_FAILED = 1;
    /**
     * When access token is empty.
     */
    public const ERROR_CODE_ACCESS_TOKEN_EMPTY = 3;
    /**
     * Code for payment failure.
     */
    public const ERROR_CODE_PAYMENT_FAILED = 4;
    /**
     * Refund duplicated code.
     */
    public const ERROR_CODE_REFUND_DUPLICATED = 5;
    /**
     * When response is empty from the gateway.
     */
    public const ERROR_CODE_RESPONSE_EMPTY = 6;
    /**
     * When curl is not enabled.
     */
    public const ERROR_CODE_CURL_NOT_ENABLED = 7;
    /**
     * When gateway is not properly configured.
     */
    public const ERROR_CODE_GATEWAY_NOT_CONFIGURED = 8;
    /**
     * When curl request fails.
     */
    public const ERROR_CODE_CURL_REQUEST_FAILED = 9;
    /**
     * When cross-reference can not be read.
     */
    public const ERROR_CODE_COULD_NOT_READ_CROSS_REFERENCE = 10;
    /**
     * When status code can not be determined.
     */
    public const ERROR_CODE_UNKNOWN_STATUS_CODE = 11;
    /**
     * When TLS is not enabled.
     */
    public const ERROR_CODE_SSL_NOT_ENABLED = 12;
    /**
     * Determines the internal 'code' name used to designate the payment module
     */
    public $code;
    /**
     * The displayed name for this payment method in configuration
     */
    public $title;
    /**
     * A soft name for this payment method
     *
     * @var string
     */
    public $description;
    /**
     * Determines if the module shows or not in catalog.
     *
     * @var bool
     */
    public $enabled;
    /**
     * Order in which payment module will appear on front end amount other modules.
     *
     * @var integer
     */
    public $sort_order;
    /**
     * Order status id for successful orders.
     *
     * @var integer
     */
    public $order_status;
    /**
     * Url where the request will be sent.
     *
     * @var string|null
     */
    private $url;
    /**
     * Curl handle.
     *
     * @var resource|null
     */
    private $curlHandle;
    /**
     * Curl error number.
     *
     * @var int|null
     */
    private $curlErrorNumber;
    /**
     * Curl info.
     *
     * @var string[]|null
     */
    private $curlInfo;
    /**
     * Curl error message.
     *
     * @var string|null
     */
    private $curlErrorMessage;
    /**
     * Response from curl.
     *
     * @var bool|string|null
     */
    private $curlResponse;
    /**
     * Status code from gateway.
     *
     * @var int|null
     */
    private $statusCode;
    /**
     * Status message from gateway.
     *
     * @var string|null
     */
    private $statusMessage;
    /**
     * Auth code from gateway.
     *
     * @var mixed|null
     */
    private $authCode;
    /**
     * Cross-reference for a refund request.
     *
     * @var string|null
     */
    private $crossReference;
    /**
     * Access token for a transaction.
     *
     * @var string|null
     */
    private $accessToken;
    /**
     * Amount for transaction.
     *
     * @var string
     */
    private $transactionAmount;
    /**
     * Is it a refund operation.
     *
     * @var bool
     */
    private $isRefundOperation = false;
    /**
     * Order id being processed.
     *
     * @var string
     */
    private $refundOrderId;
    /**
     * Curl request data.
     *
     * @var array
     */
    private $requestData;
    /**
     * Refund amount.
     *
     * @var string
     */
    private $refundAmount;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->code = MODULE_PAYMENT_PAYMENTSENSE_TEXT_CODE;
        $this->title = MODULE_PAYMENT_PAYMENTSENSE_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_PAYMENTSENSE_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_PAYMENT_PAYMENTSENSE_SORT_ORDER;
        $this->enabled = MODULE_PAYMENT_PAYMENTSENSE_STATUS == 'True';
        $this->order_status = MODULE_PAYMENT_PAYMENTSENSE_ORDER_STATUS_ID;
        $this->tableCheckup();
    }

    /**
     * Displays payment method name on the Checkout Payment Page.
     *
     * @return string[]
     */
    public function selection()
    {
        return ['id' => $this->code, 'module' => MODULE_PAYMENT_PAYMENTSENSE_TEXT_CATALOG_LOGO];
    }

    /**
     * Return any javascript validation code. Called by core code.
     *
     * @return string
     */
    public function javascript_validation()
    {
        return '';
    }

    /**
     * Pre-confirmation check. Called by core code.
     *
     * @return false
     */
    public function pre_confirmation_check()
    {
        return false;
    }

    /**
     * Returns the process button string that will be rendered along with the button.
     */
    public function process_button()
    {
        $this->calculateTransactionAmount();
        $this->createAccessTokenForPayment();
        return $this->createProcessButtonString();
    }

    /**
     * Calculates the transaction amount.
     */
    private function calculateTransactionAmount()
    {
        global $order;
        $this->transactionAmount = zen_round($order->info['total'] * 100, 0);
    }

    /**
     * Creates the process button string.
     *
     * Contains the div blocks where the iframe will be inserted by
     * connect e javascript sdk. Code for this is in
     * @see modules/pages/checkout_confirmation/jscript_paymentsense.php
     *
     * @return string
     */
    private function createProcessButtonString()
    {
        $returnUrl = zen_catalog_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL');
        $buttonLabel = MODULE_PAYMENT_PAYMENTSENSE_BUTTON_PAYMENT_LABEL;
        $currencyCode = $this->resolveCurrencyCode();
        return <<<TAG
<h2>Payment Information</h2>
<div id="paymentsense-rp-payment-div" data-amount="$this->transactionAmount" data-currency-code="$currencyCode" 
    data-access-code="$this->accessToken" data-return-url="$returnUrl"></div>
<div id="paymentsense-rp-errors-div"></div>

<script type="text/javascript">
    $("document").ready(function () {
        $("input.button_confirm_order").val("$buttonLabel")
    })
</script>
TAG;
    }

    /**
     * User is redirected to main_page=checkout_process after successful
     * payment with gateway. The redirection code is in
     * javascript file. @return bool
     * @see modules/pages/checkout_confirmation/jscript_connect_e.php
     *
     */
    public function after_process()
    {
        $this->retrieveAccessTokenFromRequest();
        $this->verifyPayment();
        $this->updateSuccessfulPaymentOrderHistory();
        $this->storeConnectEPayment();
        return true;
    }

    /**
     * Retrieves access token from request.
     */
    private function retrieveAccessTokenFromRequest()
    {
        if (!isset($_POST['paymentToken'])) {
            $this->raiseError(self::ERROR_CODE_ACCESS_TOKEN_EMPTY);
        }

        $accessToken = trim($_POST['paymentToken']);
        if ('' === $accessToken) {
            $this->raiseError(self::ERROR_CODE_ACCESS_TOKEN_EMPTY);
        }

        $this->accessToken = $accessToken;
    }

    /**
     * Reports appropriate error message to the front end.
     *
     * @param int $code
     */
    public function raiseError($code)
    {
        switch ($code) {
            case self::ERROR_CODE_PAYMENT_FAILED:
                $message = $logMessage = $this->formatErrorMessage(
                    MODULE_PAYMENT_PAYMENTSENSE_TEXT_PAYMENT_FAILED,
                    [
                        '@gatewayMessage' => $this->statusMessage,
                    ]
                );
                break;

            case self::ERROR_CODE_CURL_NOT_ENABLED:
                $message = $logMessage = MODULE_PAYMENT_PAYMENTSENSE_TEXT_CURL_NOT_ENABLED;
                break;

            case self::ERROR_CODE_COULD_NOT_READ_CROSS_REFERENCE:
                $message = $logMessage = MODULE_PAYMENT_PAYMENTSENSE_TEXT_UNEXPECTED_ERROR;
                break;

            case self::ERROR_CODE_GATEWAY_NOT_CONFIGURED:
                $message = $logMessage = MODULE_PAYMENT_PAYMENTSENSE_TEXT_PAYMENT_METHOD_NOT_CONFIGURED;
                break;

            case self::ERROR_CODE_ACCESS_TOKEN_EMPTY:
                $message = $logMessage = MODULE_PAYMENT_PAYMENTSENSE_TEXT_EMPTY_ACCESS_TOKEN;
                break;

            case self::ERROR_CODE_SSL_NOT_ENABLED:
                $message = $logMessage = MODULE_PAYMENT_PAYMENTSENSE_TEXT_SSL_NOT_ENABLED;
                break;

            case self::ERROR_CODE_REFUND_FAILED:
                $message = $logMessage = $this->formatErrorMessage(
                    MODULE_PAYMENT_PAYMENTSENSE_TEXT_REFUND_FAILED,
                    [
                        '@statusCode' => $this->statusCode,
                        '@gatewayMessage' => $this->statusMessage,
                    ]
                );
                break;

            case self::ERROR_CODE_REFUND_DUPLICATED:
                $message = $logMessage = MODULE_PAYMENT_PAYMENTSENSE_TEXT_REFUND_DUPLICATED;
                break;

            case self::ERROR_CODE_UNKNOWN_STATUS_CODE:
                $message = $logMessage = $this->formatErrorMessage(
                    MODULE_PAYMENT_PAYMENTSENSE_TEXT_UNKNOWN_STATUS_CODE,
                    [
                        '@statusCode' => $this->statusCode,
                        '@gatewayMessage' => $this->statusMessage,
                    ]
                );
                break;

            case self::ERROR_CODE_CURL_REQUEST_FAILED:
            case self::ERROR_CODE_RESPONSE_EMPTY:
                $logMessage = $this->formatErrorMessage(
                    MODULE_PAYMENT_PAYMENTSENSE_TEXT_CURL_ERROR,
                    [
                        '@curlErrorNumber' => $this->curlErrorNumber,
                        '@curlErrorMessage' => $this->curlErrorMessage,
                        '@httpCode' => $this->curlInfo['http_code'] ?? '',
                    ]
                );
                $message = MODULE_PAYMENT_PAYMENTSENSE_TEXT_NO_RETRY;
                break;

            default:
                $message = $logMessage = MODULE_PAYMENT_PAYMENTSENSE_TEXT_ERROR_OCCURRED;
                break;
        }

        $this->logError($logMessage);
        $this->showErrorMessageAndRedirect($message);
    }

    /**
     * Logs an error to error log as notice.
     *
     * @param string $message
     */
    private function logError($message)
    {
        trigger_error($message);
    }

    /**
     * Format an error message, replacing placeholders with parameters given.
     *
     * @param string $message
     * @param array $parameters
     * @return array|string|string[]
     */
    private function formatErrorMessage($message, $parameters)
    {
        return str_replace(array_keys($parameters), array_values($parameters), $message);
    }

    /**
     * Adds error to the front end and redirects to check out confirmation page.
     *
     * @param string $message
     */
    private function showErrorMessageAndRedirect($message)
    {
        global $messageStack;

        if ($this->isRefundOperation) {
            $messageStack->add_session($message, 'error');
            zen_redirect(zen_href_link(
                FILENAME_ORDERS,
                zen_get_all_get_params(['action']) . 'action=edit',
                'SSL',
                true,
                false
            ));
        }

        $messageStack->add_session(FILENAME_CHECKOUT_CONFIRMATION, $message, 'error');
        zen_redirect(zen_href_link(FILENAME_CHECKOUT_CONFIRMATION, '', 'SSL', true, false));
    }

    /**
     * Updates order history after a successful payment.
     */
    private function updateSuccessfulPaymentOrderHistory()
    {
        global $insert_id;
        $comments = 'Payment successful (access token: ' . $this->accessToken . ")\n";
        zen_update_orders_history($insert_id, $comments, null, MODULE_PAYMENT_PAYMENTSENSE_ORDER_STATUS_ID, -1);
    }

    /**
     * Stores payment in payments table.
     */
    private function storeConnectEPayment()
    {
        global $insert_id;
        $sql_data_array = [
            'order_id' => $insert_id,
            'payment_id' => zen_db_input($this->accessToken),
            'payment_status' => zen_db_input($this->statusCode),
            'created_at' => 'now()',
        ];

        zen_db_perform(TABLE_PAYMENTSENSE_PAYMENTS, $sql_data_array);
    }

    /**
     * Create payments table when appropriate.
     */
    private function tableCheckup()
    {
        if (!defined('TABLE_PAYMENTSENSE_PAYMENTS')) {
            define('TABLE_PAYMENTSENSE_PAYMENTS', DB_PREFIX . 'paymentsense_payments');
        }

        global $db, $sniffer;
        if (!$sniffer->table_exists(TABLE_PAYMENTSENSE_PAYMENTS)) {
            $sql = sprintf(
                'CREATE TABLE `%s` (
                  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `order_id` int(11) UNSIGNED NOT NULL,
                  `payment_id` varchar(255) DEFAULT NULL,
                  `payment_status` char(1) DEFAULT NULL,
                  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
                )',
                TABLE_PAYMENTSENSE_PAYMENTS
            );
            $db->Execute($sql);
        }
    }

    /**
     * Gives the extra html needed for refunds.
     *
     * Called by zen cart run time.
     *
     * @return string
     */
    public function admin_notification()
    {
        $output = '';
        require(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paymentsense/paymentsense_admin_notification.php');
        return $output;
    }

    /**
     * Checks if the module should be enabled.
     *
     * @return int
     */
    public function check()
    {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute(sprintf(
                "select configuration_value from %s where configuration_key = 'MODULE_PAYMENT_PAYMENTSENSE_STATUS'",
                TABLE_CONFIGURATION
            ));
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }

    /**
     * Called during installation.
     */
    public function install()
    {
        global $db, $messageStack;
        if (defined('MODULE_PAYMENT_PAYMENTSENSE_STATUS')) {
            $messageStack->add_session(MODULE_PAYMENT_PAYMENTSENSE_TEXT_MODULE_ALREADY_INSTALLED, 'error');
            zen_redirect(zen_href_link(FILENAME_MODULES, 'set=payment&module=paymentsense', 'NONSSL'));
            exit;
        }
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Paymentsense - Remote Payments Module', 'MODULE_PAYMENT_PAYMENTSENSE_STATUS', 'True', 'Do you want to accept payments via Paymentsense - Remote Payments?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Gateway Username/URL.', 'MODULE_PAYMENT_PAYMENTSENSE_GATEWAY_USERNAME', 'Gateway Username/URL', 'Enter Your Gateway Username or URL.', '6', '1', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Gateway JWT.', 'MODULE_PAYMENT_PAYMENTSENSE_GATEWAY_JWT', 'Gateway JWT', 'Enter Your Gateway JWT.', '6', '2', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Gateway Environment.', 'MODULE_PAYMENT_PAYMENTSENSE_GATEWAY_ENVIRONMENT', 'Test', 'Select gateway environment.', '6', '3', 'zen_cfg_select_option(array(\'Test\', \'Production\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_PAYMENTSENSE_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '4', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_PAYMENTSENSE_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value.', '6', '6', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Refunded Order Status', 'MODULE_PAYMENT_PAYMENTSENSE_REFUNDED_ORDER_STATUS_ID', '0', 'Set the status of refunded orders to this value.', '6', '7', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_PAYMENTSENSE_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '8', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Order Prefix.', 'MODULE_PAYMENT_PAYMENTSENSE_ORDER_PREFIX', 'ZC-', 'This is the order prefix that you will see in the merchant portal.', '6', '9', now())");

        $this->tableCheckup();
    }

    /**
     * Called when uninstalling the module.
     */
    public function remove()
    {
        global $db;
        $db->Execute(sprintf(
            "DELETE FROM %s where configuration_key in ('%s')",
            TABLE_CONFIGURATION,
            implode("', '", $this->keys())
        ));
    }

    /**
     * Keys that will appear in the configuration after install.
     *
     * @return string[]
     */
    public function keys()
    {
        return [
            'MODULE_PAYMENT_PAYMENTSENSE_STATUS',
            'MODULE_PAYMENT_PAYMENTSENSE_GATEWAY_USERNAME',
            'MODULE_PAYMENT_PAYMENTSENSE_GATEWAY_JWT',
            'MODULE_PAYMENT_PAYMENTSENSE_GATEWAY_ENVIRONMENT',
            'MODULE_PAYMENT_PAYMENTSENSE_ORDER_PREFIX',
            'MODULE_PAYMENT_PAYMENTSENSE_ORDER_STATUS_ID',
            'MODULE_PAYMENT_PAYMENTSENSE_REFUNDED_ORDER_STATUS_ID',
            'MODULE_PAYMENT_PAYMENTSENSE_SORT_ORDER',
            'MODULE_PAYMENT_PAYMENTSENSE_ZONE',
        ];
    }

    /**
     * Performs a full or partial refund.
     *
     * @param string $orderId
     * @return bool
     */
    public function _doRefund($orderId)
    {
        $this->isRefundOperation = true;
        $this->refundOrderId = $orderId;

        $this->validateRefundRequest();
        $this->createRefundAccessToken();
        $this->performRefund();
        $this->verifyRefundStatus();
        $this->updateOrderHistoryWithRefund();
        return true;
    }

    /**
     * Validates refund request.
     */
    private function validateRefundRequest()
    {
        if (!isset($_POST['refconfirm']) || $_POST['refconfirm'] != 'on' || !isset($_POST['buttonrefund'])) {
            $this->showErrorMessageAndRedirect(MODULE_PAYMENT_PAYMENTSENSE_TEXT_REFUND_CONFIRM_ERROR);
        }
    }

    /**
     * Reads refund amount from request to be refunded.
     *
     * @return void
     */
    private function readRefundAmount()
    {
        $amount = null;
        if ($_POST['buttonrefund'] == MODULE_PAYMENT_PAYMENTSENSE_ENTRY_REFUND_BUTTON_TEXT) {
            $amount = preg_replace('/[^0-9.,]/', '', $_POST['refamt']);
        }

        if (empty($amount)) {
            $this->showErrorMessageAndRedirect(MODULE_PAYMENT_PAYMENTSENSE_TEXT_INVALID_REFUND_AMOUNT);
            return;
        }

        $this->refundAmount = $amount;
    }

    /**
     * Sets transaction amount.
     */
    private function setRefundTransactionAmount()
    {
        $this->transactionAmount = zen_round($this->refundAmount * 100, 0);
    }

    /**
     * Reads access token for order
     */
    private function readAccessTokenForOrder(): void
    {
        global $db;
        $payment_record = $db->Execute(sprintf(
            "SELECT payment_id FROM %s WHERE order_id = '%s'",
            TABLE_PAYMENTSENSE_PAYMENTS,
            zen_db_input($this->refundOrderId)
        ));

        $this->accessToken = $payment_record->fields['payment_id'];
    }

    /**
     * Prepares refund access token data.
     */
    private function prepareRefundAccessTokenData()
    {
        $this->requestData = [
            'gatewayUsername' => MODULE_PAYMENT_PAYMENTSENSE_GATEWAY_USERNAME,
            'currencyCode' => $this->resolveCurrencyCode(),
            'amount' => $this->transactionAmount,
            'transactionType' => 'REFUND',
            'orderId' => $this->refundOrderId,
            'orderDescription' => MODULE_PAYMENT_PAYMENTSENSE_ORDER_PREFIX . $this->refundOrderId,
            'crossReference' => $this->crossReference,
            'metaData' => $this->buildMetaData(),
        ];
    }

    /**
     * Creates refund access token.
     */
    private function createRefundAccessToken()
    {
        $this->readRefundAmount();
        $this->readAccessTokenForOrder();
        $this->readCrossReference();
        $this->setRefundTransactionAmount();
        $this->prepareRefundAccessTokenData();
        $this->setUrlToAccessTokenUrl();
        $this->initCurl();
        $this->setPostData();
        $this->makeHttpRequest();
        $this->readAccessTokenFromResponse();
    }

    /**
     * Performs refund.
     */
    private function performRefund()
    {
        $this->prepareRefundData();
        $this->setUrlToRefundUrl();
        $this->initCurl();
        $this->setPostData();
        $this->makeHttpRequest();
        $this->readRefundStatusFromResponse();
    }

    /**
     * Prepares refund data.
     */
    private function prepareRefundData()
    {
        $this->requestData = ['crossReference' => $this->crossReference];
    }

    /**
     * Makes an HTTP request.
     */
    private function makeHttpRequest()
    {
        $this->ensureSslEnabled();
        $this->performCurlRequest();
        if ($this->isCurlError()) {
            $this->raiseError(self::ERROR_CODE_CURL_REQUEST_FAILED);
        }
    }

    /**
     * Makes sure ssl is enabled.
     */
    private function ensureSslEnabled()
    {
        $sslConfigurationEnabled = defined('ENABLE_SSL') && ENABLE_SSL === 'true';
        $serverHasSslConfiguration =
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || $_SERVER['SERVER_PORT'] == 443;
        $httpsServerUrlDefined = defined('HTTP_SERVER')
            && (strpos(HTTPS_SERVER, 'https:') !== false);
        if ($serverHasSslConfiguration && ($sslConfigurationEnabled || $httpsServerUrlDefined)) {
            return;
        }

        $this->raiseError(self::ERROR_CODE_SSL_NOT_ENABLED);
    }

    /**
     * Performs curl request.
     */
    private function performCurlRequest()
    {
        $this->curlResponse = curl_exec($this->curlHandle);
        $this->curlErrorNumber = curl_errno($this->curlHandle);
        $this->curlErrorMessage = curl_error($this->curlHandle);
        $this->curlInfo = curl_getinfo($this->curlHandle);
        curl_close($this->curlHandle);
    }

    /**
     * Marks to be sent a GET request.
     *
     * @return $this
     */
    private function setAsGetRequest(): self
    {
        curl_setopt($this->curlHandle, CURLOPT_POST, false);
        return $this;
    }

    /**
     * Sets the post fields for a POST request.
     *
     * @return $this
     */
    private function setPostData(): self
    {
        curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $this->encodeData($this->requestData));
        return $this;
    }

    /**
     * Checks if the Curl result contains error information.
     *
     * @return bool
     *   Error when the result is an error result.
     */
    private function isCurlError(): bool
    {
        if ($this->curlErrorNumber !== 0) {
            return true;
        }
        if (!isset($this->curlInfo['http_code'])) {
            return true;
        }
        if (200 != $this->curlInfo['http_code']) {
            return true;
        }
        return false;
    }

    /**
     * Creates and sets and access token url to be hit.
     */
    private function setUrlToAccessTokenUrl()
    {
        $this->url = $this->combineUrlParts($this->resolveEntryPointUrl(), static::ENDPOINT_ACCESS_TOKENS);
    }

    /**
     * Inits curl request.
     */
    private function initCurl()
    {
        if (!function_exists('curl_version')) {
            $this->raiseError(self::ERROR_CODE_CURL_NOT_ENABLED);
        }

        $ch = curl_init();

        if (false === $ch) {
            $this->raiseError(self::ERROR_CODE_CURL_NOT_ENABLED);
        }

        if (!defined('MODULE_PAYMENT_PAYMENTSENSE_GATEWAY_JWT') ||
            '' === trim(MODULE_PAYMENT_PAYMENTSENSE_GATEWAY_JWT)) {
            $this->raiseError(self::ERROR_CODE_GATEWAY_NOT_CONFIGURED);
        }

        $headers = [
            'Cache-Control: no-cache',
            'Authorization: Bearer ' . MODULE_PAYMENT_PAYMENTSENSE_GATEWAY_JWT,
            'Content-Type: application/json',
        ];

        if (null === $this->url) {
            $this->logError(MODULE_PAYMENT_PAYMENTSENSE_URL_NOT_BUILT);
            exit;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $this->curlHandle = $ch;
    }

    /**
     * Combines url parts with a separator.
     *
     * @return string
     */
    private function combineUrlParts(string ...$parts)
    {
        return implode('/', $parts);
    }

    /**
     * Resolves entry point url for the environment.
     *
     * @return string
     */
    private function resolveEntryPointUrl()
    {
        if (!defined('MODULE_PAYMENT_PAYMENTSENSE_GATEWAY_ENVIRONMENT')
            || null === MODULE_PAYMENT_PAYMENTSENSE_GATEWAY_ENVIRONMENT) {
            $this->raiseError(self::ERROR_CODE_GATEWAY_NOT_CONFIGURED);
        }

        if (MODULE_PAYMENT_PAYMENTSENSE_GATEWAY_ENVIRONMENT === 'Test') {
            return static::ENTRY_POINT_URL_TEST;
        }

        if (MODULE_PAYMENT_PAYMENTSENSE_GATEWAY_ENVIRONMENT === 'Production') {
            return static::ENTRY_POINT_URL_PRODUCTION;
        }

        $this->raiseError(self::ERROR_CODE_GATEWAY_NOT_CONFIGURED);
    }

    /**
     * Creates access token for payment.
     */
    public function createAccessTokenForPayment()
    {
        $this->preparePaymentAccessTokenData();
        $this->setUrlToAccessTokenUrl();
        $this->initCurl();
        $this->setPostData();
        $this->makeHttpRequest();
        $this->readAccessTokenFromResponse();
    }

    /**
     * Prepare request data for access token for payment.
     */
    private function preparePaymentAccessTokenData()
    {
        global $order;
        $orderId = $this->calculateNextOrderId();
        $this->requestData = [
            'gatewayUsername' => MODULE_PAYMENT_PAYMENTSENSE_GATEWAY_USERNAME,
            'currencyCode' => $this->resolveCurrencyCode(),
            'amount' => $this->transactionAmount,
            'transactionType' => 'SALE',
            'orderId' => $orderId,
            'orderDescription' => MODULE_PAYMENT_PAYMENTSENSE_ORDER_PREFIX . $orderId,
            'userEmailAddress' => $order->customer['email_address'] ?? '',
            'userPhoneNumber' => $order->customer['telephone'],
            'userIpAddress' => $order->info['ip_address'] ?? '',
            'userAddress1' => $order->billing['street_address'],
            'userAddress2' => $order->billing['suburb'],
            'userCity' => $order->billing['city'],
            'userState' => $order->billing['state'],
            'userPostcode' => $order->billing['postcode'],
            'userCountryCode' => $this->getNumericCountryIsoCode($order->customer['country']['iso_code_2'] ?? ''),
            'metaData' => $this->buildMetaData(),
        ];
    }

    /**
     * Returns numeric iso code for the country iso code provided.
     *
     * @param string $iso_code
     * @return string
     */
    private function getNumericCountryIsoCode($iso_code)
    {
        $numericCodes = [
            'AL' => '8',
            'DZ' => '12',
            'AS' => '16',
            'AD' => '20',
            'AO' => '24',
            'AI' => '660',
            'AG' => '28',
            'AR' => '32',
            'AM' => '51',
            'AW' => '533',
            'AU' => '36',
            'AT' => '40',
            'AZ' => '31',
            'BS' => '44',
            'BH' => '48',
            'BD' => '50',
            'BB' => '52',
            'BY' => '112',
            'BE' => '56',
            'BZ' => '84',
            'BJ' => '204',
            'BM' => '60',
            'BT' => '64',
            'BO' => '68',
            'BA' => '70',
            'BW' => '72',
            'BR' => '76',
            'BN' => '96',
            'BG' => '100',
            'BF' => '854',
            'BI' => '108',
            'KH' => '116',
            'CM' => '120',
            'CA' => '124',
            'CV' => '132',
            'KY' => '136',
            'CF' => '140',
            'TD' => '148',
            'CL' => '152',
            'CN' => '156',
            'CO' => '170',
            'KM' => '174',
            'CG' => '178',
            'CD' => '180',
            'CK' => '184',
            'CR' => '188',
            'CI' => '384',
            'HR' => '191',
            'CU' => '192',
            'CY' => '196',
            'CZ' => '203',
            'DK' => '208',
            'DJ' => '262',
            'DM' => '212',
            'DO' => '214',
            'EC' => '218',
            'EG' => '818',
            'SV' => '222',
            'GQ' => '226',
            'ER' => '232',
            'EE' => '233',
            'ET' => '231',
            'FK' => '238',
            'FO' => '234',
            'FJ' => '242',
            'FI' => '246',
            'FR' => '250',
            'GF' => '254',
            'PF' => '258',
            'GA' => '266',
            'GM' => '270',
            'GE' => '268',
            'DE' => '276',
            'GH' => '288',
            'GI' => '292',
            'GR' => '300',
            'GL' => '304',
            'GD' => '308',
            'GP' => '312',
            'GU' => '316',
            'GT' => '320',
            'GN' => '324',
            'GW' => '624',
            'GY' => '328',
            'HT' => '332',
            'VA' => '336',
            'HN' => '340',
            'HK' => '344',
            'HU' => '348',
            'IS' => '352',
            'IN' => '356',
            'ID' => '360',
            'IR' => '364',
            'IQ' => '368',
            'IE' => '372',
            'IL' => '376',
            'IT' => '380',
            'JM' => '388',
            'JP' => '392',
            'JO' => '400',
            'KZ' => '398',
            'KE' => '404',
            'KI' => '296',
            'KP' => '408',
            'KR' => '410',
            'KW' => '414',
            'KG' => '417',
            'LA' => '418',
            'LV' => '428',
            'LB' => '422',
            'LS' => '426',
            'LR' => '430',
            'LY' => '434',
            'LI' => '438',
            'LT' => '440',
            'LU' => '442',
            'MO' => '446',
            'MK' => '807',
            'MG' => '450',
            'MW' => '454',
            'MY' => '458',
            'MV' => '462',
            'ML' => '466',
            'MT' => '470',
            'MH' => '584',
            'MQ' => '474',
            'MR' => '478',
            'MU' => '480',
            'MX' => '484',
            'FM' => '583',
            'MD' => '498',
            'MC' => '492',
            'MN' => '496',
            'MS' => '500',
            'MA' => '504',
            'MZ' => '508',
            'MM' => '104',
            'NA' => '516',
            'NR' => '520',
            'NP' => '524',
            'NL' => '528',
            'AN' => '530',
            'NC' => '540',
            'NZ' => '554',
            'NI' => '558',
            'NE' => '562',
            'NG' => '566',
            'NU' => '570',
            'NF' => '574',
            'MP' => '580',
            'NO' => '578',
            'OM' => '512',
            'PK' => '586',
            'PW' => '585',
            'PA' => '591',
            'PG' => '598',
            'PY' => '600',
            'PE' => '604',
            'PH' => '608',
            'PN' => '612',
            'PL' => '616',
            'PT' => '620',
            'PR' => '630',
            'QA' => '634',
            'RE' => '638',
            'RO' => '642',
            'RU' => '643',
            'RW' => '646',
            'SH' => '654',
            'KN' => '659',
            'LC' => '662',
            'PM' => '666',
            'VC' => '670',
            'WS' => '882',
            'SM' => '674',
            'ST' => '678',
            'SA' => '682',
            'SN' => '686',
            'SC' => '690',
            'SL' => '694',
            'SG' => '702',
            'SK' => '703',
            'SI' => '705',
            'SB' => '90',
            'SO' => '706',
            'ZA' => '710',
            'ES' => '724',
            'LK' => '144',
            'SD' => '736',
            'SR' => '740',
            'SJ' => '744',
            'SZ' => '748',
            'SE' => '752',
            'CH' => '756',
            'SY' => '760',
            'TW' => '158',
            'TJ' => '762',
            'TZ' => '834',
            'TH' => '764',
            'TG' => '768',
            'TK' => '772',
            'TO' => '776',
            'TT' => '780',
            'TN' => '788',
            'TR' => '792',
            'TM' => '795',
            'TC' => '796',
            'TV' => '798',
            'UG' => '800',
            'UA' => '804',
            'AE' => '784',
            'GB' => '826',
            'US' => '840',
            'UY' => '858',
            'UZ' => '860',
            'VU' => '548',
            'VE' => '862',
            'VN' => '704',
            'VG' => '92',
            'VI' => '850',
            'WF' => '876',
            'EH' => '732',
            'YE' => '887',
            'ZM' => '894',
            'ZW' => '716',
        ];

        return $numericCodes[$iso_code] ?? '';
    }

    /**
     * Decodes curl response json into array.
     *
     * @return array
     */
    private function decodeResponseIntoArray()
    {
        if (!$this->curlResponse) {
            $this->raiseError(self::ERROR_CODE_RESPONSE_EMPTY);
        }

        $decoded = json_decode($this->curlResponse, true);
        if (!is_array($decoded)) {
            $this->raiseError(self::ERROR_CODE_RESPONSE_EMPTY);
        }

        return $decoded;
    }

    /**
     * Reads access token from gateway response.
     */
    private function readAccessTokenFromResponse()
    {
        $response = $this->decodeResponseIntoArray();
        $this->accessToken = $response['id'] ?? null;
    }

    /**
     * Encodes data given to be sent with requests.
     */
    private function encodeData(array $data): string
    {
        return json_encode($data);
    }

    /**
     * Builds meta data information
     */
    private function buildMetaData()
    {
        return [
            'shoppingCartUrl' => HTTPS_SERVER . DIR_WS_HTTPS_CATALOG,
            'shoppingCartPlatform' => PROJECT_VERSION_NAME,
            'shoppingCartVersion' => PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR,
            'shoppingCartGateway' => self::SHOPPING_CART_GATEWAY,
            'pluginVersion' => self::MODULE_VERSION,
        ];
    }

    /**
     * Sets url to retrieve payment.
     */
    private function setUrlToRetrievePayment()
    {
        $this->url = $this->combineUrlParts(
            $this->resolveEntryPointUrl(),
            static::ENDPOINT_PAYMENTS,
            $this->accessToken
        );
    }

    /**
     * Verifies payment after control returns from gateway.
     */
    public function verifyPayment()
    {
        $this->retrievePayment();
        $this->verifyPaymentStatus();
    }

    /**
     * Retrieves payment from Connect E.
     */
    private function retrievePayment()
    {
        $this->setUrlToRetrievePayment();
        $this->initCurl();
        $this->setAsGetRequest();
        $this->makeHttpRequest();
        $this->readPaymentStatusFromGatewayResponse();
    }

    /**
     * Reads payment status from gateway response.
     */
    private function readPaymentStatusFromGatewayResponse()
    {
        $response = $this->decodeResponseIntoArray();
        $this->statusCode = $response['statusCode'] ?? '';
        $this->statusMessage = $response['message'] ?? '';
        $this->crossReference = $response['crossReference'] ?? '';
    }

    /**
     * Verifies payment status after a payment request.
     */
    private function verifyPaymentStatus()
    {
        if (!$this->isStatusCodeKnown()) {
            $this->raiseError(self::ERROR_CODE_UNKNOWN_STATUS_CODE);
        }

        if (!$this->isStatusSuccessful()) {
            $this->raiseError(self::ERROR_CODE_PAYMENT_FAILED);
        }
    }

    /**
     * Read refund status from gateway response.
     */
    private function readRefundStatusFromResponse()
    {
        $data = $this->decodeResponseIntoArray();
        $this->statusCode = $data['statusCode'] ?? null;
        $this->statusMessage = $data['message'] ?? '';
        $this->authCode = $data['authCode'] ?? null;
    }

    /**
     * Checks if the status code is a known status code.
     */
    private function isStatusCodeKnown(): bool
    {
        if (null === $this->statusCode) {
            return false;
        }

        if (!in_array($this->statusCode, [
            self::PAYMENT_STATUS_SUCCESS,
            self::PAYMENT_STATUS_DECLINED,
            self::PAYMENT_STATUS_DUPLICATED,
            self::PAYMENT_STATUS_FAILED,
            self::PAYMENT_STATUS_REFERRED,
        ])) {
            return false;
        }

        return true;
    }

    /**
     * Checks if status code is successful.
     */
    private function isStatusSuccessful(): bool
    {
        return intval($this->statusCode) === self::PAYMENT_STATUS_SUCCESS;
    }

    /**
     * Checks if the status is duplicated.
     */
    private function isStatusDuplicated(): bool
    {
        return intval($this->statusCode) === self::PAYMENT_STATUS_DUPLICATED;
    }

    /**
     * Verifies refund status.
     */
    private function verifyRefundStatus()
    {
        if ($this->isStatusDuplicated()) {
            $this->raiseError(self::ERROR_CODE_REFUND_DUPLICATED);
        }
        if (!$this->isStatusSuccessful()) {
            $this->raiseError(self::ERROR_CODE_REFUND_FAILED);
        }
    }

    /**
     * Adds the refund information to order history after successful refund.
     */
    private function updateOrderHistoryWithRefund()
    {
        global $messageStack;
        require_once(DIR_WS_CLASSES . 'currencies.php');
        $currencies = new currencies();
        $new_order_status = MODULE_PAYMENT_PAYMENTSENSE_REFUNDED_ORDER_STATUS_ID;
        $refundNote = strip_tags(zen_db_input($_POST['refnote']));
        $comments = 'REFUNDED: ' . $currencies->format($this->refundAmount) . "\n" . $refundNote;
        zen_update_orders_history($this->refundOrderId, $comments, null, $new_order_status, 0);
        $messageStack->add_session(MODULE_PAYMENT_PAYMENTSENSE_TEXT_REFUND_INITIATED . $currencies->format($this->refundAmount), 'success');
    }

    /**
     * Reads cross-reference for a refund.
     */
    private function readCrossReference()
    {
        $this->retrievePayment();
        if (null === $this->crossReference) {
            $this->raiseError(self::ERROR_CODE_COULD_NOT_READ_CROSS_REFERENCE);
        }
    }

    /**
     * Sets the url where a refund request will be sent.
     */
    private function setUrlToRefundUrl()
    {
        if (null === $this->accessToken) {
            $this->raiseError(self::ERROR_CODE_ACCESS_TOKEN_EMPTY);
        }
        $this->url = $this->combineUrlParts(
            $this->resolveEntryPointUrl(),
            static::ENDPOINT_CROSS_REFERENCE_PAYMENTS,
            $this->accessToken
        );
        return $this;
    }

    /**
     * Calculates next order id.
     */
    private function calculateNextOrderId()
    {
        global $db;
        $last_order_id = $db->Execute("SELECT orders_id FROM " . TABLE_ORDERS . " ORDER BY orders_id desc LIMIT 1");
        $new_order_id = $last_order_id->fields['orders_id'];
        $new_order_id = ($new_order_id + 1);
        $new_order_id = $new_order_id . '-' . zen_create_random_value(6, 'chars');
        return $new_order_id;
    }

    /**
     * Gets currency ISO 4217 code
     */
    private function resolveCurrencyCode()
    {
        global $order;

        $currencyCodes = [
            'AED' => '784',
            'AFN' => '971',
            'ALL' => '8',
            'AMD' => '51',
            'ANG' => '532',
            'AOA' => '973',
            'ARS' => '32',
            'AUD' => '36',
            'AWG' => '533',
            'AZN' => '944',
            'BAM' => '977',
            'BBD' => '52',
            'BDT' => '50',
            'BGN' => '975',
            'BHD' => '48',
            'BIF' => '108',
            'BMD' => '60',
            'BND' => '96',
            'BOB' => '68',
            'BOV' => '984',
            'BRL' => '986',
            'BSD' => '44',
            'BTN' => '64',
            'BWP' => '72',
            'BYN' => '933',
            'BZD' => '84',
            'CAD' => '124',
            'CDF' => '976',
            'CHE' => '947',
            'CHF' => '756',
            'CHW' => '948',
            'CLF' => '990',
            'CLP' => '152',
            'CNY' => '156',
            'COP' => '170',
            'COU' => '970',
            'CRC' => '188',
            'CUC' => '931',
            'CUP' => '192',
            'CVE' => '132',
            'CZK' => '203',
            'DJF' => '262',
            'DKK' => '208',
            'DOP' => '214',
            'DZD' => '12',
            'EGP' => '818',
            'ERN' => '232',
            'ETB' => '230',
            'EUR' => '978',
            'FJD' => '242',
            'FKP' => '238',
            'GBP' => '826',
            'GEL' => '981',
            'GHS' => '936',
            'GIP' => '292',
            'GMD' => '270',
            'GNF' => '324',
            'GTQ' => '320',
            'GYD' => '328',
            'HKD' => '344',
            'HNL' => '340',
            'HRK' => '191',
            'HTG' => '332',
            'HUF' => '348',
            'IDR' => '360',
            'ILS' => '376',
            'INR' => '356',
            'IQD' => '368',
            'IRR' => '364',
            'ISK' => '352',
            'JMD' => '388',
            'JOD' => '400',
            'JPY' => '392',
            'KES' => '404',
            'KGS' => '417',
            'KHR' => '116',
            'KMF' => '174',
            'KPW' => '408',
            'KRW' => '410',
            'KWD' => '414',
            'KYD' => '136',
            'KZT' => '398',
            'LAK' => '418',
            'LBP' => '422',
            'LKR' => '144',
            'LRD' => '430',
            'LSL' => '426',
            'LYD' => '434',
            'MAD' => '504',
            'MDL' => '498',
            'MGA' => '969',
            'MKD' => '807',
            'MMK' => '104',
            'MNT' => '496',
            'MOP' => '446',
            'MRU' => '929',
            'MUR' => '480',
            'MVR' => '462',
            'MWK' => '454',
            'MXN' => '484',
            'MXV' => '979',
            'MYR' => '458',
            'MZN' => '943',
            'NAD' => '516',
            'NGN' => '566',
            'NIO' => '558',
            'NOK' => '578',
            'NPR' => '524',
            'NZD' => '554',
            'OMR' => '512',
            'PAB' => '590',
            'PEN' => '604',
            'PGK' => '598',
            'PHP' => '608',
            'PKR' => '586',
            'PLN' => '985',
            'PYG' => '600',
            'QAR' => '634',
            'RON' => '946',
            'RSD' => '941',
            'RUB' => '643',
            'RWF' => '646',
            'SAR' => '682',
            'SBD' => '90',
            'SCR' => '690',
            'SDG' => '938',
            'SEK' => '752',
            'SGD' => '702',
            'SHP' => '654',
            'SLL' => '694',
            'SOS' => '706',
            'SRD' => '968',
            'SSP' => '728',
            'STN' => '930',
            'SVC' => '222',
            'SYP' => '760',
            'SZL' => '748',
            'THB' => '764',
            'TJS' => '972',
            'TMT' => '934',
            'TND' => '788',
            'TOP' => '776',
            'TRY' => '949',
            'TTD' => '780',
            'TWD' => '901',
            'TZS' => '834',
            'UAH' => '980',
            'UGX' => '800',
            'USD' => '840',
            'USN' => '997',
            'UYI' => '940',
            'UYU' => '858',
            'UYW' => '927',
            'UZS' => '860',
            'VES' => '928',
            'VND' => '704',
            'VUV' => '548',
            'WST' => '882',
            'XAF' => '950',
            'XAG' => '961',
            'XAU' => '959',
            'XBA' => '955',
            'XBB' => '956',
            'XBC' => '957',
            'XBD' => '958',
            'XCD' => '951',
            'XDR' => '960',
            'XOF' => '952',
            'XPD' => '964',
            'XPF' => '953',
            'XPT' => '962',
            'XSU' => '994',
            'XTS' => '963',
            'XUA' => '965',
            'XXX' => '999',
            'YER' => '886',
            'ZAR' => '710',
            'ZMW' => '967',
            'ZWL' => '932',
        ];

        return $currencyCodes[$order->info['currency']] ?? '826';
    }
}
