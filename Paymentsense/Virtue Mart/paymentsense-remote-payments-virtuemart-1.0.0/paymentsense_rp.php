<?php
/**
 * Paymentsense Remote Payments Plugin for VirtueMart 3
 * Version: 1.0.0
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @version     1.0.0
 * @author      Paymentsense
 * @copyright   2021 Paymentsense Ltd.
 * @license     https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\Input\Input;

if (!class_exists('vmPSPlugin')) {
    require_once VMPATH_PLUGINLIBS . DS . 'vmpsplugin.php';
}

class plgVmPaymentPaymentsense_rp extends vmPSPlugin
{
    /**
     * Shopping cart platform name
     */
    const PLATFORM_NAME = 'VirtueMart';

    /**
     * Payment gateway name
     */
    const GATEWAY_NAME = 'Paymentsense';

    /**
     * Module name. Used in the module information reporting.
     */
    const MODULE_NAME = 'Paymentsense Remote Payments Module for VirtueMart';

    /**
     * Module version
     */
    const MODULE_VERSION = '1.0.0';

    /**
     * VirtueMart Debug Flag
     */
    const DEBUG = true;

    /**
     * Gateway environments configuration
     */
    const GW_ENVIRONMENTS = [
        'TEST' => [
            'name'            => 'Test',
            'entry_point_url' => 'https://e.test.connect.paymentsense.cloud',
            'client_js_url'   => 'https://web.e.test.connect.paymentsense.cloud/assets/js/client.js',
        ],
        'PROD' => [
            'name'            => 'Production',
            'entry_point_url' => 'https://e.connect.paymentsense.cloud',
            'client_js_url'   => 'https://web.e.connect.paymentsense.cloud/assets/js/client.js',
        ],
    ];

    /**
     * API HTTP methods
     */
    const API_METHOD_POST = 'POST';
    const API_METHOD_GET  = 'GET';

    /**
     * API requests
     */
    const API_REQUEST_ACCESS_TOKENS = '/v1/access-tokens';
    const API_REQUEST_PAYMENTS      = '/v1/payments';

    /**
     * Transaction status codes
     */
    const TRX_NOT_AVAILABLE        = -1;
    const TRX_STATUS_CODE_SUCCESS  = 0;
    const TRX_STATUS_CODE_REFERRED = 4;
    const TRX_STATUS_CODE_DECLINED = 5;
    const TRX_STATUS_CODE_FAILED   = 30;

    /**
     * Payment status codes
     */
    const PAYMENT_STATUS_CODE_UNKNOWN = 0;
    const PAYMENT_STATUS_CODE_SUCCESS = 1;
    const PAYMENT_STATUS_CODE_FAIL    = 2;

    /**
     * VirtueMart Order Statuses
     */
    const VM_ORDER_STATUS_CREATED   = 'P';
    const VM_ORDER_STATUS_CONFIRMED = 'C';
    const VM_ORDER_STATUS_CANCELLED = 'X';

    /**
     * Layouts
     */
    const LAYOUT_PAYMENT_FORM       = 'payment_form';
    const LAYOUT_PAYMENT_SUCCESSFUL = 'payment_successful';
    const LAYOUT_PAYMENT_FAILED     = 'payment_failed';
    const LAYOUT_PAYMENT_ERROR      = 'payment_error';

    /**
     * Content types of the output of the module information
     */
    const TYPE_APPLICATION_JSON = 'application/json';
    const TYPE_TEXT_PLAIN       = 'text/plain';

    /**
     * @var object
     * VirtueMart Plugin Method
     */
    protected $method;

    /**
     * @var array
     * VirtueMart Order
     */
    protected $order;

    /**
     * @var object
     * VirtueMart Order Info
     */
    protected $orderInfo;

    /**
     * @var Input
     * The Application Input Object
     */
    protected $input;

    /**
     * Supported content types of the output of the module information
     *
     * @var array
     */
    protected $contentTypes = [
        'json' => self::TYPE_APPLICATION_JSON,
        'text' => self::TYPE_TEXT_PLAIN
    ];

    public function __construct(&$subject, $config)
    {
		parent::__construct($subject, $config);
        $this->tableFields = array_keys($this->getTableSQLFields());
		$this->setConfigParameterable($this->_configTableFieldName, $this->getVarsToPush());
        $this->input = Factory::getApplication()->input;
        $this->_debug = self::DEBUG;
	}

    /**
     * Creates the table for this plugin if it does not yet exist
     *
     * @return string
     */
    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment Standard Table');
    }

    /**
     * Gets the fields to create the payment table
     *
     * @return array
     */
    public function getTableSQLFields()
    {
        return [
            'id'                          => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id'         => 'int(1) UNSIGNED',
            'order_number'                => 'char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name'                => 'varchar(5000)',
            'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
            'payment_currency'            => 'char(3)',
            'email_currency'              => 'char(3)',
            'cost_per_transaction'        => 'decimal(10,2)',
            'cost_percent_total'          => 'decimal(10,2)',
            'tax_id'                      => 'smallint(1)'
        ];
    }

	/**
	 * Prepares and submits the payment form layout to the processConfirmedOrderPaymentResponse function
	 *
	 * @param object $cart  The cart
	 * @param array  $order The order
     *
     * @return bool|null
	 */
	public function plgVmConfirmedOrder($cart, $order)
	{
	    $result = true;
	    $this->setOrder($order);
        $method = $this->getMethodByOrderInfo();
        if ($this->isMethodSelected($method)) {
            vRequest::setVar('display_title', false);
            $this->setMethod($method);
            $html = $this->renderByLayout (
                self::LAYOUT_PAYMENT_FORM,
                $this->getPaymentFormData()
            );
            $this->processConfirmedOrderPaymentResponse(2, $cart, $order, $html, $this->_name);
        } else {
            $result = $method;
        }
        $this->logInfo(__METHOD__ . ': ' . var_export($result, true), 'message');
		return $result;
	}

    /**
     * Handler of the module information request
     */
    public function plgVmOnPaymentNotification()
    {
        if ($this->_name === $this->input->get('moduleidentifier', null, 'string')) {
            switch ($this->getModuleInfoRequest()) {
                case 'info':
                    $this->processInfoRequest();
                    break;
                case 'checksums':
                    $this->processChecksumsRequest();
                    break;
                default:
                    $this->showModuleInformationRequestError();
                    break;
            }
        }
    }

    /**
     * Handler of the callback request sent to the CallbackURL
     *
     * @param string $html            Payment response HTML content
     * @param string $paymentResponse Payment response title
     *
     * @return bool|null
     */
    public function plgVmOnPaymentResponseReceived(&$html, &$paymentResponse)
    {
        $result = $this->validateRequest();
        if ($result) {
            $orderNumber = $this->input->get('order_number', null, 'string');
            $order = $this->getOrder();
            if (!$this->isConfigured()) {
                $paymentResponse = 'Payment Processing Error';
                $html = $this->renderByLayout (
                    self::LAYOUT_PAYMENT_ERROR,
                    [
                        'order_number' => $orderNumber
                    ]
                );
                $this->logInfo(__METHOD__ . ': ' . 'The plugin is not configured.', 'error', true);
                return false;
            }

            $accessToken = $this->input->get('accessToken', null, 'string');
            if (empty($accessToken)) {
                $accessToken = $this->input->get('paymentToken', null, 'string');
            }
            if (empty($accessToken)) {
                $paymentResponse = 'Payment Processing Error';
                $html = $this->renderByLayout (
                    self::LAYOUT_PAYMENT_ERROR,
                    [
                        'order_number' => $orderNumber
                    ]
                );
                $this->logInfo(__METHOD__ . ': ' . 'Access token is empty.', 'error', true);
                return false;
            }

            $httpCode   = null;
            $statusCode = self::TRX_NOT_AVAILABLE;
            $message    = 'An error occurred while communicating with the payment gateway';

            $request    = [
                'url'     => $this->getApiEndpointUrl(self::API_REQUEST_PAYMENTS, $accessToken),
                'method'  => self::API_METHOD_GET,
                'headers' => $this->buildRequestHeaders(),
            ];

            $response = '';
            $info     = [];

            $curlErrNo = $this->performCurl($request, $response, $info, $curlErrMsg);
            if (0 === $curlErrNo) {
                $httpCode = $info['http_code'];
                if (200 === $httpCode) {
                    $response   = json_decode($response, true);
                    $statusCode = $this->getArrayElement($response, 'statusCode', '');
                    $message    = $this->getArrayElement($response, 'message', '');
                }
            }

            $payment_status = $this->getPaymentStatus($statusCode);
            switch ($payment_status) {
                case self::PAYMENT_STATUS_CODE_SUCCESS:
                    $orderStatusCode = self::VM_ORDER_STATUS_CONFIRMED;
                    $this->updateOrderStatus($order, $orderNumber, $orderStatusCode, $statusCode, $message);
                    $cart = VirtueMartCart::getCart();
                    $cart->emptyCart();
                    $html = $this->renderByLayout (
                        self::LAYOUT_PAYMENT_SUCCESSFUL,
                        [
                            'order_number' => $orderNumber
                        ]
                    );
                    break;
                case self::PAYMENT_STATUS_CODE_FAIL:
                    $orderStatusCode = self::VM_ORDER_STATUS_CANCELLED;
                    $this->updateOrderStatus($order, $orderNumber, $orderStatusCode, $statusCode, $message);
                    $paymentResponse = 'Payment Failed';
                    $html = $this->renderByLayout (
                        self::LAYOUT_PAYMENT_FAILED,
                        [
                            'message'      => $message,
                            'checkout_url' => $this->getCheckoutUrl(),
                        ]
                    );
                    break;
                case self::PAYMENT_STATUS_CODE_UNKNOWN:
                default:
                    $paymentResponse = 'Payment Processing Error';
                    $diagnosticMessage = (0 === $curlErrNo)
                        ? sprintf(
                            'HTTP Status Code: %1$s; Status Code %2$s; Payment Gateway Message: %3$s;',
                            $httpCode,
                            $statusCode,
                            print_r($response, true)
                        )
                        : sprintf(
                            'cURL Error No: %1$s; cURL Error Message: %2$s;',
                            $curlErrNo,
                            $curlErrMsg
                        );
                    $html = $this->renderByLayout (
                        self::LAYOUT_PAYMENT_ERROR,
                        [
                            'order_number' => $orderNumber
                        ]
                    );
                    $this->logInfo(
                        __METHOD__ . ':' .
                        ' Order: ' . $orderNumber .
                        ', Diagnostic Message: ' . $diagnosticMessage,
                        'error',
                        true
                    );
            }
        }
        return $result;
    }

    /**
     * @see plgVmPaymentStandard::plgVmOnShowOrderBEPayment
     */
    public function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id)
    {
        if (!$this->selectedThisByMethodId($virtuemart_payment_id)) {
            return NULL; // Another method was selected, do nothing
        }

        if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            return NULL;
        }
        vmLanguage::loadJLang('com_virtuemart');

        $html = '<table class="adminlist table">' . "\n";
        $html .= $this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
        $html .= $this->getHtmlRowBE('STANDARD_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
        if ($paymentTable->email_currency) {
            $html .= $this->getHtmlRowBE('STANDARD_EMAIL_CURRENCY', $paymentTable->email_currency );
        }
        $html .= '</table>' . "\n";
        return $html;
    }

    /**
     * @see plgVmPaymentStandard::plgVmOnStoreInstallPaymentPluginTable
     */
    public function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
    {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    /**
     * @see plgVmPaymentStandard::plgVmOnSelectCheckPayment
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg)
    {
        return $this->OnSelectCheck($cart);
    }

    /**
     * @see plgVmPaymentStandard::plgVmDisplayListFEPayment
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    /**
     * @see plgVmPaymentStandard::plgVmonSelectedCalculatePricePayment
     */
    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * @see plgVmPaymentStandard::plgVmgetPaymentCurrency
     */
    public function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
    {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return FALSE;
        }
        $this->getPaymentCurrency($method);

        $paymentCurrencyId = $method->payment_currency;
        return;
    }

    /**
     * @see plgVmPaymentStandard::plgVmOnCheckAutomaticSelectedPayment
     */
    public function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = [], &$paymentCounter)
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
    }

    /**
     * @see plgVmPaymentStandard::plgVmOnShowOrderFEPayment
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * @see plgVmPaymentStandard::plgVmOnUserInvoice
     */
    public function plgVmOnUserInvoice($orderDetails, &$data)
    {
        if (!($method = $this->getVmPluginMethod($orderDetails['virtuemart_paymentmethod_id']))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return NULL;
        }

        if (!isset($method->send_invoice_on_order_null) or $method->send_invoice_on_order_null==1 or $orderDetails['order_total'] > 0.00) {
            return NULL;
        }

        if ($orderDetails['order_salesPrice']==0.00) {
            $data['invoice_number'] = 'reservedByPayment_' . $orderDetails['order_number']; // Never send the invoice via email
        }
    }

    /**
     * @see plgVmPaymentStandard::plgVmgetEmailCurrency
     */
    public function plgVmgetEmailCurrency($virtuemart_paymentmethod_id, $virtuemart_order_id, &$emailCurrencyId)
    {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return FALSE;
        }

        if (empty($method->email_currency)) {

        } else if ($method->email_currency == 'vendor') {
            $vendor_model = VmModel::getModel('vendor');
            $vendor = $vendor_model->getVendor($method->virtuemart_vendor_id);
            $emailCurrencyId = $vendor->vendor_currency;
        } else if ($method->email_currency == 'payment') {
            $emailCurrencyId = $this->getPaymentCurrency($method);
        }
    }

    /**
     * @see plgVmPaymentStandard::plgVmonShowOrderPrintPayment
     */
    public function plgVmonShowOrderPrintPayment($order_number, $method_id)
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    /**
     * @see plgVmPaymentStandard::plgVmDeclarePluginParamsPaymentVM3
     */
    public function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

    /**
     * @see plgVmPaymentStandard::plgVmSetOnTablePluginParamsPayment
     */
    public function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    /**
     * Checks the conditions of using the payment method
     *
     * @param VirtueMartCart $cart
     * @param object         $method
     * @param array          $cart_prices
     *
     * @return bool
     */
    protected function checkConditions($cart, $method, $cart_prices)
    {
        $this->setMethod($method);
        $result = $this->isConfigured();
        $this->logInfo(__METHOD__ . ': ' . var_export($result, true), 'message');
        return $result;
    }

    /**
     * Gets module name
     *
     * @return string
     */
    protected function getModuleInternalName()
    {
        return self::MODULE_NAME;
    }

    /**
     * Gets module installed version
     *
     * @return string
     */
    protected function getModuleInstalledVersion()
    {
        return self::MODULE_VERSION;
    }

    /**
     * Gets Joomla version
     *
     * @return string
     */
    protected function getJoomlaVersion()
    {
        return JVERSION;
    }

    /**
     * Gets VirtueMart version
     *
     * @return string
     */
    protected function getVmVersion()
    {
        return class_exists('vmVersion') ? vmVersion::$RELEASE : 'N/A';
    }

    /**
     * Gets PHP version
     *
     * @return string
     */
    protected function getPhpVersion()
    {
        return phpversion();
    }

    /**
     * Gets the order
     *
     * @return array $order
     */
    protected function getOrder()
    {
        return $this->order;
    }

    /**
     * Gets the order info
     *
     * @return object
     */
    protected function getOrderInfo()
    {
        return $this->orderInfo;
    }

    /**
     * Gets the payment status based on the transaction status code
     *
     * @param mixed $statusCode Transaction status code
     *
     * @return int
     */
    protected function getPaymentStatus($statusCode)
    {
        switch ($statusCode) {
            case self::TRX_STATUS_CODE_SUCCESS:
                $result = self::PAYMENT_STATUS_CODE_SUCCESS;
                break;
            case self::TRX_STATUS_CODE_REFERRED:
            case self::TRX_STATUS_CODE_DECLINED:
            case self::TRX_STATUS_CODE_FAILED:
                $result = self::PAYMENT_STATUS_CODE_FAIL;
                break;
            default:
                $result = self::PAYMENT_STATUS_CODE_UNKNOWN;
                break;
        }
        return $result;
    }

    /**
     * Gets the VirtueMart Plugin Method by the order info
     *
     * @return object|bool
     */
    protected function getMethodByOrderInfo()
    {
        $result = false;
        if (is_object($this->getOrderInfo())) {
            $result = $this->getVmPluginMethod(($this->getOrderInfo()->virtuemart_paymentmethod_id));
        }
        return $result;
    }

    /**
     * Gets the username/URL
     *
     * @return string
     */
    protected function getUsername()
    {
        return trim($this->method->gateway_username);
    }

    /**
     * Gets the JWT
     *
     * @return string
     */
    protected function getGatewayJwt()
    {
        return trim($this->method->gateway_jwt);
    }

    /**
     * Gets the order prefix
     *
     * @return string
     */
    protected function getOrderPrefix()
    {
        return trim($this->method->order_prefix);
    }

    /**
     * Gets the transaction type
     *
     * @return string
     */
    protected function getTransactionType()
    {
        return $this->method->gateway_transaction_type;
    }

    /**
     * Gets the API endpoint URL
     *
     * @param string $request API request
     * @param string $param   Parameter of the API request
     *
     * @return string
     */
    protected function getApiEndpointUrl($request, $param = null)
    {
        $gwEnv = $this->method->gateway_environment;
        $baseUrl = array_key_exists($gwEnv, self::GW_ENVIRONMENTS)
            ? self::GW_ENVIRONMENTS[$gwEnv]['entry_point_url']
            : self::GW_ENVIRONMENTS['TEST']['entry_point_url'];
        $param    = (null !== $param) ? "/$param" : '';
        return $baseUrl . $request . $param;
    }

    /**
     * Gets shopping cart platform URL
     *
     * @return string
     */
    protected function getCartUrl()
    {
        return JROUTE::_(JURI::root());

    }

    /**
     * Gets shopping cart platform name
     *
     * @return string
     */
    protected function getCartPlatformName()
    {
        return self::PLATFORM_NAME;
    }

    /**
     * Gets gateway name
     *
     * @return string
     */
    protected function getGatewayName()
    {
        return self::GATEWAY_NAME;
    }

    /**
     * Gets the checkout URL
     *
     * @return string
     */
    protected function getCheckoutUrl()
    {
        return JROUTE::_(
            JURI::root() .
            'index.php?option=com_virtuemart'.
            '&view=cart'
        );
    }

    /**
     * Gets the URL of the client.js library
     *
     * @return string
     */
    protected function getClientJsUrl()
    {
        $gwEnv = $this->method->gateway_environment;
        return array_key_exists($gwEnv, self::GW_ENVIRONMENTS)
            ? self::GW_ENVIRONMENTS[$gwEnv]['client_js_url']
            : self::GW_ENVIRONMENTS['TEST']['client_js_url'];
    }

    /**
     *  Gets the URL of the page where the customer will be redirected after completing the payment
     *
     * @return string
     */
    protected function getCustomerRedirectUrl()
    {
        return JROUTE::_(
            JURI::root() .
            'index.php?option=com_virtuemart' .
            '&view=pluginresponse' .
            '&task=pluginresponsereceived' .
            '&pm=' . $this->orderInfo->virtuemart_paymentmethod_id .
            '&order_number=' . $this->orderInfo->order_number
        );
    }

    /**
     * Gets the module information request
     *
     * @return string|bool
     */
    protected function getModuleInfoRequest()
    {
        $paymentMethodId   = $this->input->get('pm', null, 'string');
        $moduleInfoRequest = $this->input->get('moduleinforequest', null, 'string');
        return (isset($moduleInfoRequest) && ($paymentMethodId === '-1'))
            ? $moduleInfoRequest
            : false;
    }

    /**
     * Gets the data for showing the payment form
     *
     * @return array An associative array containing data for showing the payment form
     */
    protected function getPaymentFormData()
    {
        $errorMessage = '';
        $accessToken  = '';
        if (!$this->isConnectionSecure()) {
            $errorMessage = sprintf(
                'The %s payment method requires an encrypted connection. Please enable SSL/TLS.',
                $this->method->payment_name
            );
            $this->logInfo(
                __METHOD__ . ': ' .
                'SSL/TLS not enabled',
                'error',
                true
            );
        } else {
            $request = [
                'url'         => $this->getApiEndpointUrl(self::API_REQUEST_ACCESS_TOKENS),
                'method'      => self::API_METHOD_POST,
                'headers'     => $this->buildRequestHeaders(),
                'post_fields' => $this->buildRequestParams($this->buildRequestAccessTokensPayment()),
            ];

            $response    = '';
            $info        = [];
            $accessToken = '';

            $httpCode = 'N/A';
            $curlErrNo = $this->performCurl($request, $response, $info, $curlErrMsg);
            if (0 === $curlErrNo) {
                $httpCode = $info['http_code'];
                if (200 === $httpCode) {
                    $response    = json_decode($response, true);
                    $accessToken = $this->getArrayElement($response, 'id', null);
                }
            }

            if (empty($accessToken)) {
                $errorMessage = ( 0 === $curlErrNo )
                    ? sprintf(
                        'An unexpected error has occurred. (HTTP Status Code: %1$s, Payment Gateway Message: %2$s). '
                        . 'Please contact customer support.',
                        $httpCode,
                        json_encode($response)
                    )
                    : sprintf(
                        'An unexpected error has occurred. (cURL Error No: %1$s, cURL Error Message: %2$s). '
                        . 'Please contact customer support.',
                        $curlErrNo,
                        $curlErrMsg
                    );
            }
        }

        if (empty($errorMessage)) {
            $data = [
                'error_message'     => '',
                'title'             => 'Payment Information',
                'message'           => sprintf(
                    'Your order number %s is created. Please enter your payment information to pay for your order.',
                    $request['post_fields']['orderId']
                ),
                'amount'            => $request['post_fields']['amount'],
                'currency_code'     => $request['post_fields']['currencyCode'],
                'payment_token'     => $accessToken,
                'client_js_url'     => $this->getClientJsUrl(),
                'return_url'        => $this->getCustomerRedirectUrl(),
                'button_pay'        => 'Pay with Paymentsense',
                'button_processing' => 'Processing...',
            ];
        } else {
            $data = [
                'error_message'     => $errorMessage,
                'title'             => 'An unexpected error has occurred',
                'message'           => '',
                'amount'            => '',
                'currency_code'     => '',
                'payment_token'     => '',
                'client_js_url'     => '',
                'return_url'        => '',
                'button_pay'        => '',
                'button_processing' => '',
            ];
        }
        return $data;
    }

    /**
     * Gets an element from array
     *
     * @param array  $arr     The array
     * @param string $element The element
     * @param mixed  $default The default value if the element does not exist
     *
     * @return mixed
     */
    protected function getArrayElement($arr, $element, $default)
    {
        return (is_array($arr) && array_key_exists($element, $arr))
            ? $arr[$element]
            : $default;
    }

    /**
     * Gets the numeric country ISO 3166-1 code
     *
     * @param string $countryCode Alpha-2 country code
     *
     * @return string
     */
    protected function getCountryCode($countryCode)
    {
        $result   = '';
        $isoCodes = [
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
        if (array_key_exists($countryCode, $isoCodes)) {
            $result = $isoCodes[$countryCode];
        }
        return $result;
    }

    /**
     * Gets file checksums
     *
     * @return array
     */
    protected function getFileChecksums()
    {
        $result = [];
        $root_path = JPATH_ROOT;
        $fileList  = $this->input->get('data', [], 'array');
        if (!empty($fileList)) {
            foreach ($fileList as $key => $file) {
                $filename = $root_path . '/' . $file;
                $result[$key] = is_file($filename)
                    ? sha1_file($filename)
                    : null;
            }
        }
        return $result;
    }

    /**
     * Checks whether the payment method is configured
     *
     * @return bool
     */
    protected function isConfigured()
    {
        return !empty($this->getUsername())
            && !empty($this->getGatewayJwt())
            && !empty($this->getOrderPrefix())
            && !empty($this->getTransactionType());
    }

    /**
     * Checks whether the Paymentsense Remote Payments Plugin Method is selected
     *
     * @param object $method VirtueMart Plugin Method
     *
     * @return bool|null
     */
    protected function isMethodSelected($method)
    {
        $result = null;
        if (is_object($method)) {
            $result = $this->selectedThisElement($method->payment_element);
        }
        return $result;
    }

    /**
     * Checks whether the current connection is secure
     *
     * @return bool
     */
    protected function isConnectionSecure()
    {
        $https = array_key_exists('HTTPS', $_SERVER)
            ? $_SERVER['HTTPS']
            : '';
        $forwardedProto = array_key_exists('HTTP_X_FORWARDED_PROTO', $_SERVER)
            ? $_SERVER['HTTP_X_FORWARDED_PROTO']
            : '';
        return (!empty($https) && strtolower($https) !== 'off')
            || (!empty($forwardedProto) && $forwardedProto === 'https');
    }

    /**
     * Determines whether the module information request is for extended info
     *
     * @return bool
     */
    protected function isModuleExtendedInfoRequest()
    {
        $extendedInfo = $this->input->get('extended_info', null, 'string');
        return $extendedInfo === 'true';
    }

    /**
     * Builds the HTTP headers for the API requests
     *
     * @return array An associative array containing the HTTP headers
     */
    protected function buildRequestHeaders()
    {
        return [
            'Cache-Control: no-cache',
            'Authorization: Bearer ' . $this->getGatewayJwt(),
            'Content-type: application/json',
        ];
    }

    /**
     * Builds the fields for the access tokens payment request
     *
     * @return array An associative array containing the fields for the request
     */
    protected function buildRequestAccessTokensPayment()
    {
        $orderInfo           = $this->getOrderInfo();
        $amount              = (string) (round($orderInfo->order_total, 2) * 100);
        $currencyNumericCode = ShopFunctions::getCurrencyByID($orderInfo->order_currency,'currency_numeric_code');
        $state               = ShopFunctions::getStateByID($orderInfo->virtuemart_state_id);
        $countryAlpha2Code   = ShopFunctions::getCountryByID($orderInfo->virtuemart_country_id, 'country_2_code');

        return [
            'gatewayUsername'  => $this->getUsername(),
            'currencyCode'     => $currencyNumericCode,
            'amount'           => $amount,
            'transactionType'  => $this->getTransactionType(),
            'orderId'          => $orderInfo->order_number,
            'orderDescription' => $this->getOrderPrefix() . $orderInfo->order_number,
            'userEmailAddress' => $orderInfo->email,
            'userPhoneNumber'  => $orderInfo->phone_1,
            'userIpAddress'    => $_SERVER['REMOTE_ADDR'],
            'userAddress1'     => $orderInfo->address_1,
            'userAddress2'     => $orderInfo->address_2,
            'userCity'         => $orderInfo->city,
            'userState'        => $state,
            'userPostcode'     => $orderInfo->zip,
            'userCountryCode'  => $this->getCountryCode($countryAlpha2Code),
            'metaData'         => $this->buildMetaData()
        ];
    }

    /**
     * Builds the meta data
     *
     * @return array An associative array containing the meta data
     */
    protected function buildMetaData()
    {
        return [
            'shoppingCartUrl'      => $this->getCartUrl(),
            'shoppingCartPlatform' => $this->getCartPlatformName(),
            'shoppingCartVersion'  => $this->getVmVersion(),
            'shoppingCartGateway'  => $this->getGatewayName(),
            'pluginVersion'        => $this->getModuleInstalledVersion()
        ];
    }

    /**
     * Builds the fields for the API requests by replacing the null values with empty strings
     *
     * @param array $fields An array containing the fields for the API request
     *
     * @return array An array containing the fields for the API request
     */
    protected function buildRequestParams($fields)
    {
        return array_map(
            function ($value) {
                return null === $value ? '' : $value;
            },
            $fields
        );
    }

    /**
     * Sets the VirtueMart Plugin Method
     *
     * @param object $method
     */
    protected function setMethod($method)
    {
        $this->method = $method;
    }

    /**
     * Sets the order
     * @param array $order
     */
    protected function setOrder($order)
    {
        $this->order = $order;
        if (isset($order['details']['BT'])) {
            $this->orderInfo = $order['details']['BT'];
        }
    }

    /**
     * Validates the request received from the gateway
     *
     * @return object|bool|null
     */
    protected function validateRequest()
    {
        $result = null;

        $paymentMethodId = $this->input->get('pm', null, 'string');
        $orderNumber     = $this->input->get('order_number', null, 'string');

        if (isset($paymentMethodId, $orderNumber))
        {
            $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($orderNumber);
            if ($virtuemart_order_id)
            {
                $orderModel = VmModel::getModel('orders');
                $order = $orderModel->getOrder($virtuemart_order_id);
                $this->setOrder($order);
                $orderInfo = $this->getOrderInfo();
                if ($orderInfo->virtuemart_paymentmethod_id === $paymentMethodId)
                {
                    $method = $this->getMethodByOrderInfo();
                    if ($this->isMethodSelected($method)) {
                        $this->setMethod($method);
                        $result = true;
                    } else {
                        $result = $method;
                    }
                }
            } else {
                $this->logInfo(
                    __METHOD__ . ': ' .
                    sprintf('Cannot get order ID by order number (order_number=%1$s, virtuemart_order_id=%2$s).',
                        $orderNumber,
                        $virtuemart_order_id
                    ),
                    'error',
                    true
                );
            }
        } else {
            $this->logInfo(
                __METHOD__ . ': ' .
                sprintf('One or more GET parameters are not set (pm=%1$s, order_number=%2$s).',
                    $paymentMethodId,
                    $orderNumber
                ),
                'error',
                true
            );
        }
        return $result;
    }

    /**
     * Updates the order status
     *
     * @param array  $order           The order
     * @param string $orderNumber     Order number
     * @param string $orderStatusCode Order status code
     * @param int    $trxStatusCode   Transaction status code
     * @param string $message         Gateway message
     */
    protected function updateOrderStatus($order, $orderNumber, $orderStatusCode, $trxStatusCode, $message)
    {
        if (is_array($order) && array_key_exists('history', $order)) {
            $orderHistory = array_pop($order['history']);
            if ($orderHistory->order_status_code === self::VM_ORDER_STATUS_CREATED) {
                $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($orderNumber);
                if ($virtuemart_order_id) {
                    $order['order_status']        = $orderStatusCode;
                    $order['virtuemart_order_id'] = $virtuemart_order_id;
                    $order['comments']            = $message;
                    $order['customer_notified']   = 1;
                    $modelOrder = new VirtueMartModelOrders();
                    $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
                }
                $this->logInfo(
                    __METHOD__ . ':' .
                    ' Order: ' . $orderNumber .
                    ', Order Status Code: ' . $orderStatusCode .
                    ', Transaction Status Code: ' . $trxStatusCode .
                    ', Gateway Message: ' . $message,
                    'message'
                );
            }
        }
    }

    /**
     * Converts an array to string
     *
     * @param array  $arr    An associative array
     * @param string $indent Indentation
     *
     * @return string
     */
    protected function convertArrayToString($arr, $indent = '')
    {
        $result        = '';
        $indentPattern = '  ';
        foreach ($arr as $key => $value) {
            if ('' !== $result) {
                $result .= PHP_EOL;
            }

            if (is_array($value)) {
                $value = PHP_EOL . $this->convertArrayToString($value, $indent . $indentPattern);
            }

            $result .= $indent . $key . ': ' . $value;
        }
        return $result;
    }

    /**
     * Performs cURL requests
     *
     * @param array  $request  Request data
     * @param mixed  $response The result or false on failure
     * @param mixed  $info     Last transfer information
     * @param string $errMsg   Last transfer error message
     *
     * @return int cURL error number or 0 if no error occurred
     */
    protected function performCurl($request, &$response, &$info = [], &$errMsg = '')
    {
        if (!function_exists('curl_version')) {
            $errNo    = 2; // CURLE_FAILED_INIT
            $errMsg   = 'cURL is not enabled';
            $info     = [];
            $response = '';
        } else {
            $ch = curl_init();
            if (isset($request['headers'])) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $request['headers']);
            }
            curl_setopt($ch, CURLOPT_URL, $request['url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            if (self::API_METHOD_POST === $request['method']) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request['post_fields']));
            } else {
                curl_setopt($ch, CURLOPT_POST, false);
            }
            $response = curl_exec($ch);
            $errNo    = curl_errno($ch);
            $errMsg   = curl_error($ch);
            $info     = curl_getinfo($ch);
            curl_close($ch);
        }
        return $errNo;
    }

    /**
     * Processes the request for plugin information
     */
    protected function processInfoRequest()
    {
        $info = [
            'Module Name'              => $this->getModuleInternalName(),
            'Module Installed Version' => $this->getModuleInstalledVersion()
        ];
        if (($this->isModuleExtendedInfoRequest())) {
            $extendedInfo = array_merge(
                [
                    'Joomla Version'     => $this->getJoomlaVersion(),
                    'VirtueMart Version' => $this->getVmVersion(),
                    'PHP Version'        => $this->getPhpVersion()
                ]
            );
            $info = array_merge($info, $extendedInfo);
        }
        $this->outputInfo($info);
    }

    /**
     * Processes the request for file checksums
     */
    protected function processChecksumsRequest()
    {
        $info = [
            'Checksums' => $this->getFileChecksums(),
        ];
        $this->outputInfo($info);
    }

    /**
     * Shows a module information request error
     */
    protected function showModuleInformationRequestError()
    {
        $protocol = $_SERVER['SERVER_PROTOCOL'];
        if ( ! in_array( $protocol, [ 'HTTP/1.1', 'HTTP/2', 'HTTP/2.0' ], true ) ) {
            $protocol = 'HTTP/1.0';
        }
        header( "$protocol 400 Bad Request" );
        echo 'Invalid module information request.';
        exit;
    }

    /**
     * Outputs module information
     *
     * @param array $info Module information
     */
    protected function outputInfo($info)
    {
        $outputFormat = $this->input->get('output', 'text', 'string');
        $contentType = array_key_exists($outputFormat, $this->contentTypes)
            ? $this->contentTypes[$outputFormat]
            : self::TYPE_TEXT_PLAIN;
        switch ($contentType) {
            case self::TYPE_APPLICATION_JSON:
                $body = json_encode($info);
                break;
            case self::TYPE_TEXT_PLAIN:
            default:
                $body = $this->convertArrayToString($info);
                break;
        }
        header('Cache-Control: max-age=0, must-revalidate, no-cache, no-store');
        header('Pragma: no-cache');
        header('Content-Type:', $contentType);
        echo $body;
        exit;
    }
}
