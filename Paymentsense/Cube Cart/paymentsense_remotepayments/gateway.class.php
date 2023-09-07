<?php

/**
 * Paymentsense - Remote Payments Gateway
 *
 * Web:   http://www.paymentsense.com
 * Email:  devsupport@paymentsense.com
 * License:  GPL-3.0 http://opensource.org/licenses/GPL-3.0
 */

/**
 * Gateway class.
 */
class Gateway {
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
	 * Shopping cart platform.
	 */
	public const SHOPPING_CART_PLATFORM = 'CubeCart';
	/**
	 * Shopping cart gateway.
	 */
	public const SHOPPING_CART_GATEWAY = 'Paymentsense - Remote Payments';
	/**
	 * Plugin extension.
	 */
	public const MODULE_VERSION = '1.0.0';
	/**
	 * When access token is empty.
	 */
	public const ERROR_CODE_ACCESS_TOKEN_EMPTY = 3;
	/**
	 * Code for payment failure.
	 */
	public const ERROR_CODE_PAYMENT_FAILED = 4;
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
	 * When status code can not be determined.
	 */
	public const ERROR_CODE_UNKNOWN_STATUS_CODE = 11;
	/**
	 * When TLS is not enabled.
	 */
	public const ERROR_CODE_SSL_NOT_ENABLED = 12;
	/**
	 * Url where the request will be sent.
	 *
	 * @var string|null
	 */
	private $_url;
	/**
	 * Curl handle.
	 *
	 * @var resource|null
	 */
	private $_curlHandle;
	/**
	 * Curl error number.
	 *
	 * @var int|null
	 */
	private $_curlErrorNumber;
	/**
	 * Curl info.
	 *
	 * @var string[]|null
	 */
	private $_curlInfo;
	/**
	 * Curl error message.
	 *
	 * @var string|null
	 */
	private $_curlErrorMessage;
	/**
	 * Response from curl.
	 *
	 * @var bool|string|null
	 */
	private $_curlResponse;
	/**
	 * Status code from gateway.
	 *
	 * @var int|null
	 */
	private $_statusCode;
	/**
	 * Status message from gateway.
	 *
	 * @var string|null
	 */
	private $_statusMessage;
	/**
	 * Access token for a transaction.
	 *
	 * @var string|null
	 */
	private $_accessToken;
	/**
	 * Amount for transaction.
	 *
	 * @var string
	 */
	private $_transactionAmount;
	/**
	 * Curl request data.
	 *
	 * @var array
	 */
	private $_requestData;
	/**
	 * Module information passed in constructor.
	 *
	 * @var false|mixed
	 */
	private $_module;
	/**
	 * Basket passed in constructor.
	 *
	 * @var false|mixed
	 */
	private $_basket;

	public function __construct($module = false, $basket = false) {
		$this->_module = $module;
		$this->_basket = $basket;
		$this->_loadLanguageDefinitions();
	}

	private function _loadLanguageDefinitions() {
		$GLOBALS['language']->loadDefinitions('paymentsense_remotepayments', CC_ROOT_DIR.CC_DS.'modules'.CC_DS.'plugins'.CC_DS.'paymentsense_remotepayments'.CC_DS.'language','module.definitions.xml');
		$GLOBALS['language']->loadLanguageXML('paymentsense_remotepayments', '', CC_ROOT_DIR.CC_DS.'modules'.CC_DS.'plugins'.CC_DS.'paymentsense_remotepayments'.CC_DS.'language');
	}

	public function transfer() {}

	public function repeatVariables() {}

	public function fixedVariables() {}

	public function call() {
		require_once __DIR__ . DIRECTORY_SEPARATOR . 'diagnostics.class.php';
		$diagnostics = new Diagnostics();
		$diagnostics->executeAction();
	}

	public function form(): string {

		if (isset($_POST['paymentToken'])) {
			$this->_retrieveAccessTokenFromRequest();
			$this->_verifyPayment();
			$this->_updateOrder();
			$this->_redirectToPaymentSuccessfulPage();
			return '';
		}

		$this->_calculateTransactionAmount();
		$this->_createAccessTokenForPayment();
		return $this->_renderForm();
	}

	/**
	 * Renders form where iframe will be injected.
	 * @see form.tpl
	 */
	private function _renderForm(): string {
		$GLOBALS['smarty']->assign('ACCESS_CODE', $this->_accessToken);
		$GLOBALS['smarty']->assign('CURRENCY_CODE', $this->_resolveCurrencyCode());
		$GLOBALS['smarty']->assign('RETURN_URL', 'url');
		$GLOBALS['smarty']->assign('MODULE', $this->_module);
		$GLOBALS['smarty']->assign('AMOUNT', $this->_basket['total'] * 100);
		$GLOBALS['smarty']->assign('FORMATTED_AMOUNT', $GLOBALS['tax']->priceFormat($this->_basket['total']));

		//Check for custom template for module in skin folder
		$file_name = 'form.tpl';
		$form_file = $GLOBALS['gui']->getCustomModuleSkin('gateway', dirname(__FILE__), $file_name);
		$GLOBALS['gui']->changeTemplateDir($form_file);
		$ret = $GLOBALS['smarty']->fetch($file_name);
		$GLOBALS['gui']->changeTemplateDir();
		return $ret;
	}

	/**
	 * Calculates the transaction amount.
	 */
	private function _calculateTransactionAmount() {
		$this->_transactionAmount = (string)($this->_basket['total'] * 100);
	}

	/**
	 * Creates access token for payment.
	 */
	private function _createAccessTokenForPayment() {
		$this->_preparePaymentAccessTokenData();
		$this->_setUrlToAccessTokenUrl();
		$this->_initCurl();
		$this->_setPostData();
		$this->_makeHttpRequest();
		$this->_readAccessTokenFromResponse();
	}

	/**
	 * Prepare request data for access token for payment.
	 */
	private function _preparePaymentAccessTokenData() {
		$orderId = $this->_basket['cart_order_id'];
		$this->_requestData = [
			'gatewayUsername' => $this->_module['gatewayUsername'],
			'currencyCode' => $this->_resolveCurrencyCode(),
			'amount' => $this->_transactionAmount,
			'transactionType' => 'SALE',
			'orderId' => $orderId,
			'orderDescription' => $this->_module['orderPrefix'] . $orderId,
			'userEmailAddress' => $this->_basket['billing_address']['email'] ?? '',
			'userPhoneNumber' => $this->_basket['billing_address']['phone'] ?? '',
			'userIpAddress' => $_SERVER['REMOTE_ADDR'] ?? '',
			'userAddress1' => $this->_basket['billing_address']['line1'],
			'userAddress2' => $this->_basket['billing_address']['line2'],
			'userCity' => $this->_basket['billing_address']['town'],
			'userState' => $this->_basket['billing_address']['state'],
			'userPostcode' => $this->_basket['billing_address']['postcode'],
			'userCountryCode' => $this->_getNumericCountryIsoCode($this->_basket['billing_address']['country_iso'] ?? ''),
			'metaData' => $this->_buildMetaData(),
		];
	}

	/**
	 * Gets currency ISO 4217 code.
	 *
	 * @return string currency code.
	 */
	private function _resolveCurrencyCode(): string {
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
		return $currencyCodes[$GLOBALS['config']->get('config', 'default_currency')] ?? '826';
	}

	/**
	 * Returns numeric iso code for the country iso code provided.
	 *
	 * @param string $iso_code
	 * @return string
	 */
	private function _getNumericCountryIsoCode($iso_code): string {
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
	private function _decodeResponseIntoArray(): array {
		if (!$this->_curlResponse) {
			$this->_raiseError(self::ERROR_CODE_RESPONSE_EMPTY);
		}

		$decoded = json_decode($this->_curlResponse, true);
		if (!is_array($decoded)) {
			$this->_raiseError(self::ERROR_CODE_RESPONSE_EMPTY);
		}

		return $decoded;
	}

	/**
	 * Reads access token from gateway response.
	 */
	private function _readAccessTokenFromResponse() {
		$response = $this->_decodeResponseIntoArray();
		$this->_accessToken = $response['id'] ?? null;
	}

	/**
	 * Encodes data given to be sent with requests.
	 */
	private function _encodeData(array $data): string {
		return json_encode($data);
	}

	/**
	 * Builds meta data information
	 */
	private function _buildMetaData(): array {
		return [
			'shoppingCartUrl' => CC_STORE_URL,
			'shoppingCartPlatform' => self::SHOPPING_CART_PLATFORM,
			'shoppingCartVersion' => CC_VERSION,
			'shoppingCartGateway' => self::SHOPPING_CART_GATEWAY,
			'pluginVersion' => self::MODULE_VERSION,
		];
	}

	/**
	 * Retrieves access token from request.
	 */
	private function _retrieveAccessTokenFromRequest() {
		if (!isset($_POST['paymentToken'])) {
			$this->_raiseError(self::ERROR_CODE_ACCESS_TOKEN_EMPTY);
		}

		$accessToken = trim($_POST['paymentToken']);
		if ('' === $accessToken) {
			$this->_raiseError(self::ERROR_CODE_ACCESS_TOKEN_EMPTY);
		}

		$this->_accessToken = $accessToken;
	}

	/**
	 * Sets url to retrieve payment.
	 */
	private function _setUrlToRetrievePayment() {
		$this->_url = $this->_combineUrlParts(
			$this->_resolveEntryPointUrl(),
			static::ENDPOINT_PAYMENTS,
			$this->_accessToken
		);
	}

	/**
	 * Verifies payment after control returns from gateway.
	 */
	private function _verifyPayment() {
		$this->_retrievePayment();
		$this->_verifyPaymentStatus();
	}

	/**
	 * Retrieves payment from Connect E.
	 */
	private function _retrievePayment() {
		$this->_setUrlToRetrievePayment();
		$this->_initCurl();
		$this->_setAsGetRequest();
		$this->_makeHttpRequest();
		$this->_readPaymentStatusFromGatewayResponse();
	}

	/**
	 * Makes an HTTP request.
	 */
	private function _makeHttpRequest() {
		$this->_ensureSslEnabled();
		$this->_performCurlRequest();
		if ($this->_isCurlError()) {
			$this->_raiseError(self::ERROR_CODE_CURL_REQUEST_FAILED);
		}
	}

	/**
	 * Makes sure ssl is enabled.
	 */
	private function _ensureSslEnabled() {
		$serverHasSslConfiguration =
			(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
			|| $_SERVER['SERVER_PORT'] == 443;
		if ($serverHasSslConfiguration) {
			return;
		}
		$this->_raiseError(self::ERROR_CODE_SSL_NOT_ENABLED);
	}

	/**
	 * Performs curl request.
	 */
	private function _performCurlRequest() {
		if (is_array($this->_requestData)) {
			$this->_logNotice('Request: ' . json_encode($this->_requestData));
		}
		$this->_curlResponse = curl_exec($this->_curlHandle);
		$this->_curlErrorNumber = curl_errno($this->_curlHandle);
		$this->_curlErrorMessage = curl_error($this->_curlHandle);
		$this->_curlInfo = curl_getinfo($this->_curlHandle);
		curl_close($this->_curlHandle);
	}

	/**
	 * Marks to be sent a GET request.
	 */
	private function _setAsGetRequest() {
		curl_setopt($this->_curlHandle, CURLOPT_POST, false);
	}

	/**
	 * Sets the post fields for a POST request.
	 */
	private function _setPostData() {
		curl_setopt($this->_curlHandle, CURLOPT_POSTFIELDS, $this->_encodeData($this->_requestData));
	}

	/**
	 * Checks if the Curl result contains error information.
	 *
	 * @return bool
	 *   Error when the result is an error result.
	 */
	private function _isCurlError(): bool {
		if ($this->_curlErrorNumber !== 0) {
			return true;
		}
		if (!isset($this->_curlInfo['http_code'])) {
			return true;
		}
		if (200 != $this->_curlInfo['http_code']) {
			return true;
		}
		return false;
	}

	/**
	 * Reports appropriate error message to the front end.
	 *
	 * @param int $code
	 */
	private function _raiseError($code) {
		switch ($code) {
			case self::ERROR_CODE_PAYMENT_FAILED:
				$message = $logMessage = $this->_formatErrorMessage(
					$this->_translateKeyToMessage('error_PAYMENT_FAILED'),
					[
						'@gatewayMessage' => $this->_statusMessage,
					]
				);
				break;

			case self::ERROR_CODE_CURL_NOT_ENABLED:
				$message = $logMessage = $this->_translateKeyToMessage('error_CURL_NOT_ENABLED');
				break;

			case self::ERROR_CODE_GATEWAY_NOT_CONFIGURED:
				$message = $logMessage = $this->_translateKeyToMessage('error_PAYMENT_METHOD_NOT_CONFIGURED');
				break;

			case self::ERROR_CODE_ACCESS_TOKEN_EMPTY:
				$message = $logMessage = $this->_translateKeyToMessage('error_EMPTY_ACCESS_TOKEN');
				break;

			case self::ERROR_CODE_SSL_NOT_ENABLED:
				$message = $logMessage = $this->_translateKeyToMessage('error_SSL_NOT_ENABLED');
				break;

			case self::ERROR_CODE_UNKNOWN_STATUS_CODE:
				$message = $logMessage = $this->_formatErrorMessage(
					$this->_translateKeyToMessage('error_UNKNOWN_STATUS_CODE'),
					[
						'@statusCode' => $this->_statusCode,
						'@gatewayMessage' => $this->_statusMessage,
					]
				);
				break;

			case self::ERROR_CODE_CURL_REQUEST_FAILED:
			case self::ERROR_CODE_RESPONSE_EMPTY:
				$logMessage = $this->_formatErrorMessage(
					$this->_translateKeyToMessage('error_CURL_ERROR'),
					[
						'@curlErrorNumber' => $this->_curlErrorNumber,
						'@curlErrorMessage' => $this->_curlErrorMessage,
						'@httpCode' => $this->_curlInfo['http_code'] ?? '',
					]
				);
				$message = $this->_translateKeyToMessage('error_NO_RETRY');
				break;

			default:
				$message = $logMessage = $this->_translateKeyToMessage('error_ERROR_OCCURRED');
				break;
		}

		$this->_logNotice($logMessage);
		$this->_showErrorMessageAndRedirect($message);
	}

	/**
	 * Logs an error to error log as notice.
	 *
	 * @param string $message
	 */
	private function _logNotice($message) {
		trigger_error($message);
	}

	/**
	 * Looks up key in language files and returns mapped translated string.
	 *
	 * @param $translationKey
	 * @return string translated message
	 */
	private function _translateKeyToMessage($translationKey) {
		return $GLOBALS['language']->getStrings('paymentsense_remotepayments')[$translationKey] ?? $translationKey;
	}

	/**
	 * Format an error message, replacing placeholders with parameters given.
	 *
	 * @param string $message
	 * @param array $parameters
	 * @return string
	 */
	private function _formatErrorMessage($message, $parameters) {
		return str_replace(array_keys($parameters), array_values($parameters), $message);
	}

	/**
	 * Adds error to the front end and redirects to the gateway page.
	 *
	 * @param string $message
	 */
	private function _showErrorMessageAndRedirect($message) {
		$GLOBALS['gui']->setError($message);
		httpredir('?_a=checkout');
		exit;
	}

	/**
	 * Creates and sets and access token url to be hit.
	 */
	private function _setUrlToAccessTokenUrl() {
		$this->_url = $this->_combineUrlParts($this->_resolveEntryPointUrl(), static::ENDPOINT_ACCESS_TOKENS);
	}

	/**
	 * Inits curl request.
	 */
	private function _initCurl() {
		if (!function_exists('curl_version')) {
			$this->_raiseError(self::ERROR_CODE_CURL_NOT_ENABLED);
		}

		$ch = curl_init();

		if (false === $ch) {
			$this->_raiseError(self::ERROR_CODE_CURL_NOT_ENABLED);
		}

		if (!isset($this->_module['gatewayJwt'])) {
			$this->_raiseError(self::ERROR_CODE_GATEWAY_NOT_CONFIGURED);
		}

		$headers = [
			'Cache-Control: no-cache',
			'Authorization: Bearer ' . $this->_module['gatewayJwt'],
			'Content-Type: application/json',
		];

		if (null === $this->_url) {
			$this->_logNotice($this->_translateKeyToMessage('error_URL_NOT_BUILT'));
			exit;
		}

		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_URL, $this->_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);

		$this->_curlHandle = $ch;
	}

	/**
	 * Combines url parts with a separator.
	 */
	private function _combineUrlParts(string ...$parts): string {
		return implode('/', $parts);
	}

	/**
	 * Resolves entry point url for the environment.
	 */
	private function _resolveEntryPointUrl(): ?string {
		$environment = $this->_module['environment'] ?? '';

		if ($environment === 'Test') {
			return static::ENTRY_POINT_URL_TEST;
		}

		if ($environment === 'Production') {
			return static::ENTRY_POINT_URL_PRODUCTION;
		}

		$this->_raiseError(self::ERROR_CODE_GATEWAY_NOT_CONFIGURED);
		return '';
	}

	/**
	 * Reads payment status from gateway response.
	 */
	private function _readPaymentStatusFromGatewayResponse() {
		$response = $this->_decodeResponseIntoArray();
		$this->_statusCode = $response['statusCode'] ?? '';
		$this->_statusMessage = $response['message'] ?? '';
	}

	/**
	 * Verifies payment status after a payment request.
	 */
	private function _verifyPaymentStatus() {
		if (!$this->_isStatusCodeKnown()) {
			$this->_raiseError(self::ERROR_CODE_UNKNOWN_STATUS_CODE);
		}

		if (!$this->_isStatusSuccessful()) {
			$this->_raiseError(self::ERROR_CODE_PAYMENT_FAILED);
		}
	}

	private function _updateOrder() {
		$order = Order::getInstance();
		$cart_order_id = $this->_basket['cart_order_id'];
		$order_summary = $order->getSummary($cart_order_id);
		$order->orderStatus(Order::ORDER_PROCESS, $cart_order_id);
		$order->paymentStatus(Order::PAYMENT_SUCCESS, $cart_order_id);
		$transData['notes'] = '';
		$transData['order_id'] = $cart_order_id;
		$transData['trans_id'] = $this->_accessToken;
		$transData['amount'] = $this->_basket['total'];
		$transData['status'] = $this->_statusCode;
		$transData['customer_id'] = $order_summary['customer_id'];
		$transData['gateway'] = 'paymentsense_remotepayments';
		$order->logTransaction($transData);
	}

	private function _redirectToPaymentSuccessfulPage() {
		httpredir(currentPage(['_g', 'type', 'cmd', 'module'], ['_a' => 'complete']));
	}

	/**
	 * Checks if the status code is a known status code.
	 */
	private function _isStatusCodeKnown(): bool {
		if (null === $this->_statusCode) {
			return false;
		}

		if (!in_array($this->_statusCode, [
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
	private function _isStatusSuccessful(): bool {
		return intval($this->_statusCode) === self::PAYMENT_STATUS_SUCCESS;
	}

}
