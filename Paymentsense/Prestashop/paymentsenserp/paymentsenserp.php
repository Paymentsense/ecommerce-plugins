<?php
/**
 * Copyright (C) 2021 Paymentsense Ltd.
 *
 * This program is free software: you can redistribute it and/or modify it under the terms
 * of the AFL Academic Free License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the AFL Academic Free License for more details. You should have received a copy of the
 * AFL Academic Free License along with this program. If not, see <http://opensource.org/licenses/AFL-3.0/>.
 *
 *  @author     Paymentsense <devsupport@paymentsense.com>
 *  @copyright  2021 Paymentsense Ltd.
 *  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

use Symfony\Component\HttpFoundation\Response;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Paymentsenserp extends PaymentModule
{
    /**
     * Module name. Used in the module information reporting.
     */
    const MODULE_NAME = 'Paymentsense Remote Payments Module for PrestaShop';

    /**
     * Shopping cart platform name
     */
    const PLATFORM_NAME = 'PrestaShop';

    /**
     * Payment gateway name
     */
    const GATEWAY_NAME = 'Paymentsense';

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

    public function __construct()
    {
        $this->name                   = 'paymentsenserp';
        $this->version                = '1.0.2';
        $this->tab                    = 'payments_gateways';
        $this->author                 = 'Paymentsense Ltd.';
        $this->module_key             = '';
        $this->currencies             = true;
        $this->currencies_mode        = 'radio';
        $this->bootstrap              = true;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => '1.7.9.99',
        ];

        parent::__construct();

        $this->displayName      = $this->l('Paymentsense Remote Payments');
        $this->description      = $this->l('Accept credit/debit cards through Paymentsense.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');
    }

    /**
     * Installer
     *
     * @return bool
     */
    public function install()
    {
        return parent::install()
            && Configuration::updateValue(
                'PAYMENTSENSERP_MODULE_TITLE',
                'Pay by Paymentsense Remote Payments'
            )
            && Configuration::updateValue(
                'PAYMENTSENSERP_MODULE_DESCRIPTION',
                'Pay securely by credit or debit card through Paymentsense Remote Payments.'
            )
            && Configuration::updateValue(
                'PAYMENTSENSERP_ORDER_PREFIX',
                'PS-'
            )
            && Configuration::updateValue(
                'PAYMENTSENSERP_GATEWAY_USERNAME',
                ''
            )
            && Configuration::updateValue(
                'PAYMENTSENSERP_GATEWAY_JWT',
                ''
            )
            && Configuration::updateValue(
                'PAYMENTSENSERP_GATEWAY_ENVIRONMENT',
                ''
            )
            && Configuration::updateValue(
                'PAYMENTSENSERP_TRANSACTION_TYPE',
                ''
            )
            && $this->registerHook('paymentOptions');
    }

    /**
     * Uninstaller
     *
     * @return bool
     */
    public function uninstall()
    {
        return Configuration::deleteByName('PAYMENTSENSERP_MODULE_TITLE')
            && Configuration::deleteByName('PAYMENTSENSERP_MODULE_DESCRIPTION')
            && Configuration::deleteByName('PAYMENTSENSERP_ORDER_PREFIX')
            && Configuration::deleteByName('PAYMENTSENSERP_GATEWAY_USERNAME')
            && Configuration::deleteByName('PAYMENTSENSERP_GATEWAY_JWT')
            && Configuration::deleteByName('PAYMENTSENSERP_GATEWAY_ENVIRONMENT')
            && Configuration::deleteByName('PAYMENTSENSERP_TRANSACTION_TYPE')
            && parent::uninstall();
    }

    /**
     * Gets the content of the configuration settings page
     *
     * @return string
     */
    public function getContent()
    {
        $params = [
            'module_path'                  => $this->getPathUri(),
            'notice_type'                  => '',
            'notice_text'                  => '',
            'form_action'                  => $_SERVER['REQUEST_URI'],
            'form_var_module_title'        => $this->getSetting('PAYMENTSENSERP_MODULE_TITLE'),
            'form_var_module_description'  => $this->getSetting('PAYMENTSENSERP_MODULE_DESCRIPTION'),
            'form_var_order_prefix'        => $this->getSetting('PAYMENTSENSERP_ORDER_PREFIX'),
            'form_var_gateway_username'    => $this->getSetting('PAYMENTSENSERP_GATEWAY_USERNAME'),
            'form_var_gateway_jwt'         => $this->getSetting('PAYMENTSENSERP_GATEWAY_JWT'),
            'form_var_gateway_environment' => $this->getSetting('PAYMENTSENSERP_GATEWAY_ENVIRONMENT'),
            'form_var_trx_type'            => $this->getSetting('PAYMENTSENSERP_TRANSACTION_TYPE')
        ];
        if (Tools::isSubmit('btnSubmit')) {
            if ($this->updateConfigSettings()) {
                $params['notice_type'] = $this->l('success');
                $params['notice_text'] = $this->l('Settings updated');
            } else {
                $params['notice_type'] = $this->l('danger');
                $params['notice_text'] = $this->l('Settings update error');
            }
        }
        $this->context->smarty->assign($params);
        return $this->display(__FILE__, 'views/templates/admin/config_settings.tpl');
    }

    /**
     * Gets the module title. Used for the payment option and the payment form.
     *
     * @return string
     */
    public function getModuleTitle()
    {
        return Configuration::get('PAYMENTSENSERP_MODULE_TITLE');
    }

    /**
     * Gets the module description. Used for the payment option and the payment form.
     *
     * @return string
     */
    public function getModuleDescription()
    {
        return Configuration::get('PAYMENTSENSERP_MODULE_DESCRIPTION');
    }

    /**
     * Gets module name. Used in the module information reporting.
     *
     * @return string
     */
    public function getModuleInternalName()
    {
        return self::MODULE_NAME;
    }

    /**
     * Gets module installed version
     *
     * @return string
     */
    public function getModuleInstalledVersion()
    {
        return $this->version;
    }

    /**
     * Gets the PrestaShop version
     *
     * @return string
     */
    public function getPsVersion()
    {
        return _PS_VERSION_;
    }

    /**
     * Gets shopping cart platform URL
     *
     * @return string
     */
    public function getCartUrl()
    {
        return Context::getContext()->shop->getBaseURL(true);
    }

    /**
     * Gets shopping cart platform name
     *
     * @return string
     */
    public function getCartPlatformName()
    {
        return self::PLATFORM_NAME;
    }

    /**
     * Gets gateway name
     *
     * @return string
     */
    public function getGatewayName()
    {
        return self::GATEWAY_NAME;
    }

    /**
     * Gets order state based on the transaction status code
     *
     * @param string $statusCode Transaction status code
     *
     * @return string
     */
    public function getOrderState($statusCode)
    {
        return ($statusCode === self::TRX_STATUS_CODE_SUCCESS)
            ? Configuration::get('PS_OS_PAYMENT')
            : Configuration::get('PS_OS_ERROR');
    }

    /**
     * Gets the payment status based on the transaction status code
     *
     * @param string $statusCode status code
     *
     * @return int
     */
    public function getPaymentStatus($statusCode)
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
     * Gets the URL of the page where the payment form will be shown
     *
     * @return string A string containing a URL
     */
    public function getPaymentFormUrl()
    {
        return $this->context->link->getModuleLink($this->name, 'paymentform', [], true);
    }

    /**
     * Gets the URL of the page where the customer will be redirected after completing the payment
     *
     * @return string A string containing a URL
     */
    public function getCustomerRedirectUrl()
    {
        return $this->context->link->getModuleLink($this->name, 'customerredirect', [], true);
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
    public function getArrayElement($arr, $element, $default)
    {
        return (is_array($arr) && array_key_exists($element, $arr))
            ? $arr[$element]
            : $default;
    }

    /**
     * Gets the API endpoint URL
     *
     * @param string $request API request
     * @param string $param   Parameter of the API request
     *
     * @return string
     */
    public function getApiEndpointUrl($request, $param = null)
    {
        $gwEnv = Configuration::get('PAYMENTSENSERP_GATEWAY_ENVIRONMENT');
        $baseUrl = array_key_exists($gwEnv, self::GW_ENVIRONMENTS)
            ? self::GW_ENVIRONMENTS[$gwEnv]['entry_point_url']
            : self::GW_ENVIRONMENTS['TEST']['entry_point_url'];
        $param    = (null !== $param) ? "/$param" : '';
        return $baseUrl . $request . $param;
    }

    /**
     * Gets the URL of the client.js library
     *
     * @return string
     */
    public function getClientJsUrl()
    {
        $gwEnv = Configuration::get('PAYMENTSENSERP_GATEWAY_ENVIRONMENT');
        return array_key_exists($gwEnv, self::GW_ENVIRONMENTS)
            ? self::GW_ENVIRONMENTS[$gwEnv]['client_js_url']
            : self::GW_ENVIRONMENTS['TEST']['client_js_url'];
    }

    /**
     * Hooks Payment Options to show Paymentsense on the checkout page
     *
     * @param array $params
     *
     * @return array|false
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return false;
        }

        try {
            $formData = [];

            if (!$this->isConnectionSecure()) {
                $errorMessage = $this->l(
                    'This payment method requires an encrypted connection. Please enable SSL/TLS.'
                );
            } elseif (!$this->isConfigured()) {
                $errorMessage = $this->l(
                    'This payment method is not configured. Please configure module gateway settings.'
                );
            } else {
                list($errorMessage, $formData) = $this->getPaymentFormData($params);
            }

            $paymentOptionAvailable = empty($errorMessage);

            $templateVars = [
                'module_name' => $this->name,
                'module_description' => $this->getPaymentOptionDescription($paymentOptionAvailable),
                'error_message' => $errorMessage,
                'paymentsenserp_payment_option_js' => $this->getPathUri() . 'views/js/paymentsenserp-payment-option.js',
            ];

            $templateVars = array_merge($templateVars, $formData);

            $this->context->smarty->assign('paymentsenserp', $templateVars, true);

            $paymentOption = new PaymentOption;
            $paymentOption->setModuleName($this->name)
                ->setCallToActionText($this->getPaymentOptionTitle($paymentOptionAvailable))
                ->setForm(
                    $this->context->smarty->fetch('module:paymentsenserp/views/templates/front/payment_option_form.tpl')
                )
                ->setAdditionalInformation(
                    $this->context->smarty->fetch('module:paymentsenserp/views/templates/front/payment_option.tpl')
                );
            return [
                $paymentOption
            ];
        } catch (Exception $exception) {
            return false;
        }
    }

    /**
     * Determines whether the store is configured to use a secure connection
     *
     * @return bool
     */
    public function isConnectionSecure()
    {
        return Configuration::get('PS_SSL_ENABLED') == 1;
    }

    /**
     * Checks whether the payment method is configured
     *
     * @return bool
     */
    public function isConfigured()
    {
        return (trim(Configuration::get('PAYMENTSENSERP_GATEWAY_USERNAME')) != '')
            && (trim(Configuration::get('PAYMENTSENSERP_GATEWAY_JWT')) != '')
            && (trim(Configuration::get('PAYMENTSENSERP_GATEWAY_ENVIRONMENT')) != '')
            && (trim(Configuration::get('PAYMENTSENSERP_TRANSACTION_TYPE')) != '');
    }

    /**
     * Retrieves the Cart ID from the cartId POST variable
     *
     * @return int|false
     */
    public function retrieveCartId()
    {
        $result = false;
        $cartId = Tools::getValue('cartId');
        if (is_string($cartId) && ($cartId != '')) {
            $result = (int) $cartId;
        }
        return $result;
    }

    /**
     * Creates the order
     *
     * @param Cart   $cart
     * @param string $orderState
     * @param string $message
     * @param float  $amount
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function createOrder($cart, $orderState, $message, $amount)
    {
        $customer = new Customer((int) $cart->id_customer);
        return $this->validateOrder(
            $cart->id,
            $orderState,
            $amount,
            $this->displayName,
            $message,
            [],
            null,
            true,
            $customer->secure_key
        );
    }

    /**
     * Builds the HTTP headers for the API requests
     *
     * @return array An associative array containing the HTTP headers
     */
    public function buildRequestHeaders()
    {
        return [
            'Cache-Control: no-cache',
            'Authorization: Bearer ' . Configuration::get('PAYMENTSENSERP_GATEWAY_JWT'),
            'Content-type: application/json',
        ];
    }

    /**
     * Builds the fields for the access tokens payment request
     *
     * @param array $params
     *
     * @return array An associative array containing the fields for the request
     */
    public function buildRequestAccessTokensPayment($params)
    {
        $address           = new Address((int) ($params['cart']->id_address_invoice));
        $customer          = new Customer((int) ($params['cart']->id_customer));
        $currencyAlphaCode = $this->getsCurrencyAlphaCode((int) $params['cart']->id_currency);
        $orderTotal        = number_format($params['cart']->getOrderTotal(true, Cart::BOTH), 2, '.', '');
        $amount            = (string) ($orderTotal * 100);
        $orderId           = date('Ymd-His') . '~' . $params['cart']->id;

        $orderPrefix     = Configuration::get('PAYMENTSENSERP_ORDER_PREFIX');
        $gatewayUsername = Configuration::get('PAYMENTSENSERP_GATEWAY_USERNAME');
        $transactionType = Configuration::get('PAYMENTSENSERP_TRANSACTION_TYPE');

        return [
            'gatewayUsername'  => $gatewayUsername,
            'currencyCode'     => $this->getCurrencyCode($currencyAlphaCode),
            'amount'           => $amount,
            'transactionType'  => $transactionType,
            'orderId'          => $orderId,
            'orderDescription' => $orderPrefix . $orderId,
            'userEmailAddress' => $customer->email,
            'userPhoneNumber'  => ($address->phone != '') ? $address->phone : $address->phone_mobile,
            'userIpAddress'    => $_SERVER['REMOTE_ADDR'],
            'userAddress1'     => $address->address1,
            'userAddress2'     => $address->address2,
            'userCity'         => $address->city,
            'userState'        => '',
            'userPostcode'     => $address->postcode,
            'userCountryCode'  => $this->getCountryCode($this->context->country->iso_code),
            'metaData'         => $this->buildMetaData(),
        ];
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
    public function performCurl($request, &$response, &$info = [], &$errMsg = '')
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
     * Builds the meta data
     *
     * @return array An associative array containing the meta data
     */
    protected function buildMetaData()
    {
        return [
            'shoppingCartUrl'      => $this->getCartUrl(),
            'shoppingCartPlatform' => $this->getCartPlatformName(),
            'shoppingCartVersion'  => $this->getPsVersion(),
            'shoppingCartGateway'  => $this->getGatewayName(),
            'pluginVersion'        => $this->getModuleInstalledVersion(),
        ];
    }

    /**
     * Gets a setting from the HTTP POST variables or the configuration
     *
     * @param string $name Setting name
     *
     * @return string
     */
    protected function getSetting($name)
    {
        switch (true) {
            case array_key_exists($name, $_POST):
                $result = Tools::getValue($name);
                break;
            case Configuration::get($name) !== false:
                $result = Configuration::get($name);
                break;
            default:
                $result = '';
                break;
        }
        return $result;
    }

    /**
     * Gets the alphabetic ISO 4217 code of a currency
     *
     * @param int $id_currency PrestaShop currency ID
     *
     * @return string
     */
    protected function getsCurrencyAlphaCode($id_currency)
    {
        $sql = new DbQuery();
        $sql->select('iso_code');
        $sql->from('currency');
        $sql->where('id_currency = ' . (int) $id_currency);
        return Db::getInstance()->getValue($sql);
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
     * Gets the numeric currency ISO 4217 code
     *
     * @param string $currencyCode Alphabetic currency code
     * @param string $defaultCode  Default numeric currency code
     *
     * @return string
     */
    protected function getCurrencyCode($currencyCode, $defaultCode = '826')
    {
        $result   = $defaultCode;
        $isoCodes = [
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
        if (array_key_exists($currencyCode, $isoCodes)) {
            $result = $isoCodes[$currencyCode];
        }
        return $result;
    }

    /**
     * Gets the data for showing the payment form
     *
     * @param array $params
     *
     * @return array An associative array containing data for showing the payment form
     */
    protected function getPaymentFormData($params)
    {
        $errorMessage = '';
        $formData     = [];

        $request = [
            'url'         => $this->getApiEndpointUrl(self::API_REQUEST_ACCESS_TOKENS),
            'method'      => self::API_METHOD_POST,
            'headers'     => $this->buildRequestHeaders(),
            'post_fields' => $this->buildRequestParams($this->buildRequestAccessTokensPayment($params)),
        ];

        $response    = '';
        $info        = [];
        $accessToken = '';

        $httpCode = 'N/A';
        $curlErrNo = $this->performCurl($request, $response, $info, $curlErrMsg);
        if (0 === $curlErrNo) {
            $httpCode = $info['http_code'];
            if (Response::HTTP_OK === $httpCode) {
                $response    = json_decode($response, true);
                $accessToken = $this->getArrayElement($response, 'id', null);
            }
        }

        if ($accessToken) {
            $formData = [
                'form_action'              => $this->getPaymentFormUrl(),
                'form_param_cart_id'       => $params['cart']->id,
                'form_param_amount'        => $request['post_fields']['amount'],
                'form_param_currency_code' => $request['post_fields']['currencyCode'],
                'form_param_payment_token' => $accessToken,
            ];
        } else {
            $errorMessage = ( 0 === $curlErrNo )
                ? sprintf(
                    $this->l(
                        'An unexpected error has occurred. (HTTP Status Code: %1$s, Payment Gateway Message: %2$s). '
                        . 'Please contact customer support.'
                    ),
                    $httpCode,
                    $response
                )
                : sprintf(
                    $this->l(
                        'An unexpected error has occurred. (cURL Error No: %1$s, cURL Error Message: %2$s). '
                        . 'Please contact customer support.'
                    ),
                    $curlErrNo,
                    $curlErrMsg
                );
        }

        return [
            $errorMessage,
            $formData,
        ];
    }

    /**
     * Gets the payment option title
     *
     * @param bool $paymentOptionAvailable
     *
     * @return string
     */
    protected function getPaymentOptionTitle($paymentOptionAvailable)
    {
        $title = $this->getModuleTitle();
        if (!$paymentOptionAvailable) {
            $title .= ' ' . $this->l('(unavailable)');
        }
        return $title;
    }

    /**
     * Gets the payment option description
     *
     * @param bool $paymentOptionAvailable
     *
     * @return string
     */
    protected function getPaymentOptionDescription($paymentOptionAvailable)
    {
        return $paymentOptionAvailable
            ? $this->getModuleDescription()
            : $this->l('This payment method is currently unavailable.');
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
     * Updates the configuration settings
     *
     * @return bool
     */
    protected function updateConfigSettings()
    {
        $result   = true;
        $settings = [
            'PAYMENTSENSERP_MODULE_TITLE',
            'PAYMENTSENSERP_MODULE_DESCRIPTION',
            'PAYMENTSENSERP_ORDER_PREFIX',
            'PAYMENTSENSERP_GATEWAY_USERNAME',
            'PAYMENTSENSERP_GATEWAY_JWT',
            'PAYMENTSENSERP_GATEWAY_ENVIRONMENT',
            'PAYMENTSENSERP_TRANSACTION_TYPE',
        ];
        foreach ($settings as $setting) {
            $value = Tools::getValue($setting);
            if ($value != '') {
                Configuration::updateValue($setting, $value);
            } else {
                $result = false;
                break;
            }
        }
        return $result;
    }
}
