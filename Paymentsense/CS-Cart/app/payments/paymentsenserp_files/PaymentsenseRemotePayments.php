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
 * CS-Cart Paymentsense Remote Payments class
 */
class PaymentsenseRemotePayments
{
    /**
     * Module name. Used in the module information reporting.
     */
    const MODULE_NAME = 'Paymentsense Remote Payments Add-on for CS-Cart';

    /**
     * Shopping cart platform name
     */
    const PLATFORM_NAME = 'CS-Cart';

    /**
     * Payment gateway name
     */
    const GATEWAY_NAME = 'Paymentsense';

    /**
     * Module version. Used in the module information reporting.
     */
    const MODULE_VERSION = '1.1';

    /**
     * Gateway environments configuration
     */
    const GW_ENVIRONMENTS = [
        'TEST' => [
            'name'            => 'Test',
            'entry_point_url' => 'https://e.test.connect.paymentsense.cloud',
            'client_js_url'   => 'https://web.e.test.connect.paymentsense.cloud/assets/js/client.js'
        ],
        'PROD' => [
            'name'            => 'Production',
            'entry_point_url' => 'https://e.connect.paymentsense.cloud',
            'client_js_url'   => 'https://web.e.connect.paymentsense.cloud/assets/js/client.js'
        ]
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
    const TRX_STATUS_CODE_SUCCESS  = 0;
    const TRX_STATUS_CODE_REFERRED = 4;
    const TRX_STATUS_CODE_DECLINED = 5;
    const TRX_STATUS_CODE_FAILED   = 30;

    /**
     * CS-Cart Order Statuses
     */
    const CSCART_ORDER_STATUS_INCOMPLETE = 'N';
    const CSCART_ORDER_STATUS_PROCESSED  = 'P';
    const CSCART_ORDER_STATUS_FAILED     = 'F';
    const CSCART_ORDER_STATUS_CANCELLED  = 'I';

    /**
     * Notifications
     */
    const NOTIFICATION_MODE_PROCESS     = 'process';
    const NOTIFICATION_MODE_MODULE_INFO = 'module_info';

    /**
     * Content types for module information
     */
    const TYPE_APPLICATION_JSON = 'application/json';
    const TYPE_TEXT_PLAIN       = 'text/plain';

    /**
     * @var array
     *
     * Stores the superglobal variable $_REQUEST
     */
    protected $request_data = [];

    /**
     * @var array
     *
     * Stores the payment processor data
     */
    protected $processor_data = [];

    /**
     * Supported content types of the output of the module information
     *
     * @var array
     */
    protected $content_types = [
        'json' => self::TYPE_APPLICATION_JSON,
        'text' => self::TYPE_TEXT_PLAIN
    ];

    /**
     * PaymentsenseRemotePayments Class Constructor
     *
     * @param array $processor_data Payment processor data
     */
    public function __construct($processor_data = [])
    {
        $this->request_data   = $_REQUEST;
        $this->processor_data = $processor_data;
    }

    /**
     * Gets data for showing the payment form
     *
     * @param array $order_info Order information
     *
     * @return array An array containing data for showing the payment form and an error message (if any)
     */
    public function getPaymentFormData($order_info)
    {
        $form_data     = [];
        $error_message = '';

        if (defined('HTTPS')) {
            $response     = '';
            $info         = [];
            $access_token = '';
            $http_code    = 'N/A';

            $request = [
                'url' => $this->getApiEndpointUrl(self::API_REQUEST_ACCESS_TOKENS),
                'method' => self::API_METHOD_POST,
                'headers' => $this->buildRequestHeaders(),
                'post_fields' => $this->buildRequestParams($this->buildRequestAccessTokensPayment($order_info))
            ];

            $curl_err_no = $this->performCurl($request, $response, $info, $curl_err_msg);
            if (0 === $curl_err_no) {
                $http_code = $info['http_code'];
                if (200 === $http_code) {
                    $response = json_decode($response, true);
                    $access_token = $this->getArrayElement($response, 'id', null);
                }
            }

            if ($access_token) {
                $form_data = [
                    'amount' => $request['post_fields']['amount'],
                    'currency_code' => $request['post_fields']['currencyCode'],
                    'access_token' => $access_token
                ];
            } else {
                $error_message = (0 === $curl_err_no)
                    ? sprintf(___('http_error'), $http_code, $response)
                    : sprintf(___('curl_error'), $curl_err_no, $curl_err_msg);
            }
        } else {
            $error_message = ___('insecure_connection');
        }

        return [
            $form_data,
            $error_message
        ];
    }

    /**
     * Gets the order ID
     *
     * @param array $order_info Order information
     *
     * @return string
     */
    public function getOrderId($order_info)
    {
        $result = '';
        if (is_array($order_info) && isset($order_info['order_id'])) {
            $result = (string)(($order_info['repaid'])
                ? ($order_info['order_id'] . '_' . $order_info['repaid'])
                : $order_info['order_id']);
        }
        return $result;
    }

    /**
     * Gets the URL of the page where the customer will be redirected after completing the payment
     *
     * @param array $order_info Order information
     *
     * @return string
     */
    public function getCustomerRedirectUrl($order_info)
    {
        $notification_mode = self::NOTIFICATION_MODE_PROCESS;
        return fn_url(
            "payment_notification.{$notification_mode}?payment=paymentsenserp&order_id=&order_id={$order_info['order_id']}",
            AREA,
            'current'
        );
    }

    /**
     * Gets the URL of the client.js library
     *
     * @return string
     */
    public function getClientJsUrl()
    {
        return array_key_exists($this->getGatewayEnvironment(), self::GW_ENVIRONMENTS)
            ? self::GW_ENVIRONMENTS[$this->getGatewayEnvironment()]['client_js_url']
            : self::GW_ENVIRONMENTS['TEST']['client_js_url'];
    }

    /**
     * Gets the configured module title
     *
     * @param array $order_info Order information
     *
     * @return string
     */
    public function getTitle($order_info)
    {
        return $order_info['payment_method']['payment'];
    }

    /**
     * Processes the payment gateway response
     *
     * @param string $order_id Order ID
     */
    public function processGatewayResponse($order_id)
    {
        $cancel_action = isset($this->request_data['action']) && ($this->request_data['action'] === 'cancel');
        if (!$cancel_action) {
            $payment_details = $this->getTransactionPaymentDetails();
        } else {
            $payment_details = [
                'reason_text'    => ___('order_cancelled'),
                'order_status'   => self::CSCART_ORDER_STATUS_CANCELLED,
                'transaction_id' => ''
            ];
        }

        if ($payment_details['order_status'] === self::CSCART_ORDER_STATUS_INCOMPLETE) {
            $payment_details['reason_text'] = ___('payment_status_unknown_backend') . $payment_details['reason_text'];
            fn_finish_payment($order_id, $payment_details);
            $view = Tygh::$app['view'];
            $tpl_vars = [
                'error_title'   => ___('payment_status_unknown_title'),
                'error_message' => sprintf(___('payment_status_unknown_frontend'), $order_id)
            ];
            $view->assign($tpl_vars, null, true);
            $view->display('addons/paymentsenserp/error.tpl');
            exit;
        }

        fn_finish_payment($order_id, $payment_details);
        fn_order_placement_routines('route', $order_id, false);
    }

    /**
     * Processes module information requests
     */
    public function processModuleInfoRequest()
    {
        if (isset($this->request_data['action'])) {
            switch ($this->request_data['action']) {
                case 'info':
                    $this->outputInfo($this->getInfo());
                    break;
                case 'checksums':
                    $this->outputInfo($this->getFileChecksums());
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * Gets the username/URL
     *
     * @return string
     */
    protected function getUsername()
    {
        return $this->processor_data['processor_params']['gateway_username'];
    }

    /**
     * Gets the JWT
     *
     * @return string
     */
    protected function getGatewayJwt()
    {
        return $this->processor_data['processor_params']['gateway_jwt'];
    }

    /**
     * Gets the environment
     *
     * @return string
     */
    protected function getGatewayEnvironment()
    {
        return $this->processor_data['processor_params']['gateway_environment'];
    }

    /**
     * Gets the order prefix
     *
     * @return string
     */
    protected function getOrderPrefix()
    {
        return $this->processor_data['processor_params']['order_prefix'];
    }

    /**
     * Gets the transaction type
     *
     * @return string
     */
    protected function getTransactionType()
    {
        return $this->processor_data['processor_params']['transaction_type'];
    }

    /**
     * Gets the API endpoint URL
     *
     * @param string      $request API request
     * @param string|null $param   Parameter of the API request
     *
     * @return string
     */
    protected function getApiEndpointUrl($request, $param = null)
    {
        $base_url = array_key_exists($this->getGatewayEnvironment(), self::GW_ENVIRONMENTS)
            ? self::GW_ENVIRONMENTS[$this->getGatewayEnvironment()]['entry_point_url']
            : self::GW_ENVIRONMENTS['TEST']['entry_point_url'];

        $param   = (null !== $param) ? "/$param" : '';
        return $base_url . $request . $param;
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
     * Gets module name
     *
     * @return string
     */
    protected function getModuleName()
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
     * Gets the transaction payment details
     *
     * @return array
     */
    protected function getTransactionPaymentDetails()
    {
        $reason_text    = '';
        $transaction_id = '';

        $status_code = -1;

        $access_token = isset($this->request_data['accessToken'])
            ? $this->request_data['accessToken']
            : (isset($this->request_data['paymentToken']) ? $this->request_data['paymentToken'] : '');

        if (!empty($access_token)) {
            $request = [
                'url'     => $this->getApiEndpointUrl(self::API_REQUEST_PAYMENTS, $access_token),
                'method'  => self::API_METHOD_GET,
                'headers' => $this->buildRequestHeaders(),
            ];

            $response  = '';
            $info      = [];

            $curl_err_no = $this->performCurl($request, $response, $info, $curl_err_msg);
            if (0 === $curl_err_no) {
                $http_code = $info['http_code'];
                if (200 === $http_code) {
                    $response       = json_decode($response, true);
                    $status_code    = $this->getArrayElement($response, 'statusCode', '');
                    $reason_text    = $this->getArrayElement($response, 'message', '');
                    $transaction_id = $this->getArrayElement($response, 'crossReference', '');
                } else {
                    $reason_text = sprintf(___('http_error'), $http_code, $response);
                }
            } else {
                $reason_text = sprintf(___('curl_error'), $curl_err_no, $curl_err_msg);
            }
        }

        switch ($status_code) {
            case self::TRX_STATUS_CODE_SUCCESS:
                $order_status = self::CSCART_ORDER_STATUS_PROCESSED;
                break;
            case self::TRX_STATUS_CODE_REFERRED:
            case self::TRX_STATUS_CODE_DECLINED:
            case self::TRX_STATUS_CODE_FAILED:
                $order_status = self::CSCART_ORDER_STATUS_FAILED;
                break;
            default:
                $order_status = self::CSCART_ORDER_STATUS_INCOMPLETE;
                break;
        }

        return [
            'reason_text'    => $reason_text,
            'order_status'   => $order_status,
            'transaction_id' => $transaction_id
        ];
    }

    /**
     * Gets the numeric country ISO 3166-1 code
     *
     * @param string $country_code Alpha-2 country code
     *
     * @return string
     */
    protected function getCountryCode($country_code)
    {
        return db_get_field('SELECT code_N3 FROM ?:countries WHERE code=?s', $country_code);
    }

    /**
     * Gets module information
     */
    protected function getInfo()
    {
        $info = [
            'Module Name'              => $this->getModuleName(),
            'Module Installed Version' => $this->getModuleInstalledVersion(),
        ];
        if ((isset($this->request_data['extended_info'])) && ('true' === $this->request_data['extended_info'])) {
            $extended_info = [
                'CS-Cart Version' => $this->getCsCartVersion(),
                'PHP Version'     => $this->getPhpVersion(),
            ];
            $info = array_merge($info, $extended_info);
        }
        return $info;
    }

    /**
     * Gets file checksums
     *
     * @return array
     */
    protected function getFileChecksums()
    {
        $result = [];
        $root_path = DIR_ROOT;
        $file_list = $this->request_data['data'];
        if (is_array($file_list)) {
            foreach ($file_list as $key => $file) {
                $filename = $root_path . '/' . $file;
                $result[$key] = is_file($filename)
                    ? sha1_file($filename)
                    : null;
            }
        }
        return [
            'Checksums' => $result
        ];
    }

    /**
     * Gets CS-Cart version
     *
     * @return string
     */
    protected function getCsCartVersion()
    {
        return PRODUCT_VERSION;
    }

    /**
     * Gets shopping cart platform URL
     *
     * @return string
     */
    protected function getCartUrl()
    {
        return fn_url();
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
     * Gets PHP version
     *
     * @return string
     */
    protected function getPhpVersion()
    {
        return PHP_VERSION;
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
            'Content-Type: application/json'
        ];
    }

    /**
     * Builds the fields for the access tokens payment request
     *
     * @param array $order_info Order information
     *
     * @return array An associative array containing the fields for the request
     */
    protected function buildRequestAccessTokensPayment($order_info)
    {
        $amount       = (string)round($order_info['total']*100);
        $order_id     = $this->getOrderId($order_info);
        $order_prefix = $this->getOrderPrefix();

        return [
            'gatewayUsername'  => $this->getUsername(),
            'currencyCode'     => PaymentsenseRpCurrencyCode::getCurrencyCode($order_info['secondary_currency']),
            'amount'           => $amount,
            'transactionType'  => $this->getTransactionType(),
            'orderId'          => $order_id,
            'orderDescription' => $order_prefix . $order_id,
            'userEmailAddress' => $order_info['email'],
            'userPhoneNumber'  => $order_info['phone'],
            'userIpAddress'    => $_SERVER['REMOTE_ADDR'],
            'userAddress1'     => $order_info['b_address'],
            'userAddress2'     => $order_info['b_address_2'],
            'userCity'         => $order_info['b_city'],
            'userState'        => $order_info['b_state_descr'],
            'userPostcode'     => $order_info['b_zipcode'],
            'userCountryCode'  => $this->getCountryCode($order_info['b_country']),
            'metaData'         => $this->buildMetaData(),
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
            'shoppingCartVersion'  => $this->getCsCartVersion(),
            'shoppingCartGateway'  => $this->getGatewayName(),
            'pluginVersion'        => $this->getModuleInstalledVersion(),
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
     * Performs cURL requests
     *
     * @param array  $request  Request data
     * @param mixed  $response The result or false on failure
     * @param mixed  $info     Last transfer information
     * @param string $err_msg  Last transfer error message
     *
     * @return int cURL error number or 0 if no error occurred
     */
    protected function performCurl($request, &$response, &$info = [], &$err_msg = '')
    {
        if (!function_exists('curl_version')) {
            $err_no   = 2; // CURLE_FAILED_INIT
            $err_msg  = 'cURL is not enabled';
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
            $err_no   = curl_errno($ch);
            $err_msg  = curl_error($ch);
            $info     = curl_getinfo($ch);
            curl_close($ch);
        }
        return $err_no;
    }

    /**
     * Outputs module information
     *
     * @param array $info Module information
     */
    protected function outputInfo($info)
    {
        $output_format = $this->request_data['output'];
        $content_type  = is_string($output_format) && array_key_exists($output_format, $this->content_types)
            ? $this->content_types[$output_format]
            : self::TYPE_TEXT_PLAIN;
        switch ($content_type) {
            case self::TYPE_APPLICATION_JSON:
                $body = json_encode($info);
                break;
            case self::TYPE_TEXT_PLAIN:
            default:
                $body = $this->convertArrayToString($info);
                break;
        }
        header('Cache-Control: no-store');
        header('Pragma: no-cache');
        header('Content-Type:', $content_type);
        echo $body;
        exit;
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
        $result         = '';
        $indent_pattern = '  ';
        foreach ($arr as $key => $value) {
            if ('' !== $result) {
                $result .= PHP_EOL;
            }

            if (is_array($value)) {
                $value = PHP_EOL . $this->convertArrayToString($value, $indent . $indent_pattern);
            }

            $result .= $indent . $key . ': ' . $value;
        }
        return $result;
    }
}
