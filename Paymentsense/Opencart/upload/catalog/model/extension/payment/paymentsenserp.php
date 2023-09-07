<?php
/*
 * Copyright (C) 2022 Paymentsense Ltd.
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
 * @author      Paymentsense
 * @copyright   2022 Paymentsense Ltd.
 * @license     https://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Front Model for Paymentsense Remote Payments
 */
class ModelExtensionPaymentPaymentsenserp extends Model
{
	/**
	 * Module name. Used in the module information reporting
	 */
	const MODULE_NAME = 'Paymentsense Remote Payments Extension for OpenCart';

	/**
	 * Module version
	 */
	const MODULE_VERSION = '1.0.3';

	/**
	 * Shopping cart platform name
	 */
	const PLATFORM_NAME = 'OpenCart';

	/**
	 * Payment gateway name
	 */
	const GATEWAY_NAME = 'Paymentsense';

	/**
	 * Gateway environments configuration
	 */
	const GW_ENVIRONMENTS = array(
		'TEST' => array(
			'name'            => 'Test',
			'entry_point_url' => 'https://e.test.connect.paymentsense.cloud',
			'client_js_url'   => 'https://web.e.test.connect.paymentsense.cloud/assets/js/client.js'
		),
		'PROD' => array(
			'name'            => 'Production',
			'entry_point_url' => 'https://e.connect.paymentsense.cloud',
			'client_js_url'   => 'https://web.e.connect.paymentsense.cloud/assets/js/client.js'
		)
	);

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
	 * OpenCart order status constants
	 * Reference: database table oc_order_status
	 */
	const OC_ORD_STATUS_PENDING           =  1;
	const OC_ORD_STATUS_PROCESSING        =  2;
	const OC_ORD_STATUS_SHIPPED           =  3;
	const OC_ORD_STATUS_COMPLETE          =  5;
	const OC_ORD_STATUS_CANCELED          =  7;
	const OC_ORD_STATUS_DENIED            =  8;
	const OC_ORD_STATUS_CANCELED_REVERSAL =  9;
	const OC_ORD_STATUS_FAILED            = 10;
	const OC_ORD_STATUS_REFUNDED          = 11;
	const OC_ORD_STATUS_REVERSED          = 12;
	const OC_ORD_STATUS_CHARGEBACK        = 13;
	const OC_ORD_STATUS_EXPIRED           = 14;
	const OC_ORD_STATUS_PROCESSED         = 15;
	const OC_ORD_STATUS_VOIDED            = 16;

	/**
	 * OpenCart front routes
	 */
	const ROUTE_PAYMENT_NOTIFICATION = 'extension/payment/paymentsenserp/notification';

	/**
	 * Payment method code
	 *
	 * @var string
	 */
	protected $payment_code = 'paymentsenserp';

	/**
	 * Gets the payment method data for showing the payment options at the checkout
	 *
	 * @param array $address Order address data
	 * @param float $total   Order total
	 *
	 * @return array
	 */
	public function getMethod($address, $total) {
		$result = array();
		if ($this->isAvailable($address, $total)) {
			$this->load->language('extension/payment/paymentsenserp');
			$result = array(
				'code'       => $this->payment_code,
				'title'      => $this->getConfigValue('paymentsenserp_title'),
				'terms'      => '',
				'sort_order' => $this->getConfigValue('paymentsenserp_sort_order')
			);
		}
		return $result;
	}

	/**
	 * Gets data for showing the payment form
	 *
	 * @return array An associative array containing an error message (if any), data for showing the payment form and the order ID
	 */
	public function getPaymentFormData() {
		$error_message = '';
		$form_data     = array();
		$order_id      = '';

		$response      = '';
		$info          = array();
		$access_token  = '';
		$http_code     = 'N/A';

		$request       = array(
			'url'         => $this->getApiEndpointUrl(self::API_REQUEST_ACCESS_TOKENS),
			'method'      => self::API_METHOD_POST,
			'headers'     => $this->buildRequestHeaders(),
			'post_fields' => $this->buildRequestParams($this->buildRequestAccessTokensPayment())
		);

		$curl_err_no = $this->performCurl($request, $response, $info, $curl_err_msg);
		if (0 === $curl_err_no) {
			$http_code = $info['http_code'];
			if (200 === $http_code) {
				$response     = json_decode($response, true);
				$access_token = $this->getArrayElement($response, 'id', null);
			}
		}

		if ($access_token) {
			$form_data = array(
				'amount'        => $request['post_fields']['amount'],
				'currency_code' => $request['post_fields']['currencyCode'],
				'access_token'  => $access_token
			);
			$order_id = $request['post_fields']['orderId'];
		} else {
			$diagnostic_message = (0 === $curl_err_no)
				? sprintf(
					'HTTP Status Code: %1$s; Payment Gateway Message: %2$s;',
					$http_code,
					print_r($response, true)
				)
				: sprintf(
					'cURL Error No: %1$s; cURL Error Message: %2$s;',
					$curl_err_no,
					$curl_err_msg
				);
			$error_message = sprintf($this->language->get('error_unexpected_detailed'), $diagnostic_message);
		}

		return array(
			$error_message,
			$form_data,
			$order_id
		);
	}

	/**
	 * Gets the URL of the client.js library
	 *
	 * @return string
	 */
	public function getClientJsUrl() {
		return array_key_exists($this->getConfigValue('paymentsenserp_gateway_environment'), self::GW_ENVIRONMENTS)
			? self::GW_ENVIRONMENTS[$this->getConfigValue('paymentsenserp_gateway_environment')]['client_js_url']
			: self::GW_ENVIRONMENTS['TEST']['client_js_url'];
	}

	/**
	 * Gets the module name
	 *
	 * @return string
	 */
	public function getModuleName() {
		return self::MODULE_NAME;
	}

	/**
	 * Gets the module installed version
	 *
	 * @return string
	 */
	public function getModuleInstalledVersion() {
		return self::MODULE_VERSION;
	}

	/**
	 * Gets the OpenCart version
	 *
	 * @return string
	 */
	public function getOpenCartVersion() {
		return VERSION;
	}

	/**
	 * Gets the PHP version
	 *
	 * @return string
	 */
	public function getPhpVersion() {
		return phpversion();
	}

	/**
	 * Gets the transaction payment status
	 *
	 * @param string $order_id     Order ID
	 * @param string $access_token Access token
	 *
	 * @return array
	 */
	public function getTransactionPaymentStatus($order_id, $access_token) {
		$result = [
			'OrderIdValid' => false,
			'StatusCode'   => self::TRX_NOT_AVAILABLE,
			'Message'      => 'An error occurred while communicating with the payment gateway. ' // TODO: or ''
		];

		$request = array(
			'url'     => $this->getApiEndpointUrl(self::API_REQUEST_PAYMENTS, $access_token),
			'method'  => self::API_METHOD_GET,
			'headers' => $this->buildRequestHeaders()
		);

		$response = '';
		$info     = array();

		$curl_err_no = $this->performCurl($request, $response, $info, $curl_err_msg);
		if (0 === $curl_err_no) {
			$http_code = $info['http_code'];
			if (200 === $http_code) {
				$response     = json_decode($response, true);
				$metaData     = $this->getArrayElement($response, 'metaData', '');
				$orderIdValid = is_array($metaData) &&
					array_key_exists('orderId', $metaData) &&
					! empty($metaData['orderId']) &&
					($order_id === $metaData['orderId']);
				$result = [
					'OrderIdValid' => $orderIdValid,
					'StatusCode'   => $this->getArrayElement($response, 'statusCode', ''),
					'Message'      => $this->getArrayElement($response, 'message', ''),
				];
			} else {
				$diagnostic_message = sprintf(
					'HTTP Status Code: %1$s; Payment Gateway Message: %2$s;',
					$http_code,
					print_r($response, true)
				);
				$result['Message'] = sprintf($this->language->get('error_unexpected_detailed'), $diagnostic_message);
			}
		} else {
			$diagnostic_message = sprintf(
				'cURL Error No: %1$s; cURL Error Message: %2$s;',
				$curl_err_no,
				$curl_err_msg
			);
			$result['Message'] = sprintf($this->language->get('error_unexpected_detailed'), $diagnostic_message);
		}

		return $result;
	}

	/**
	 * Gets the payment status based on the transaction status code
	 *
	 * @param mixed $status_code status code
	 *
	 * @return int
	 */
	public function getPaymentStatus($status_code)
	{
		switch ($status_code) {
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
	 * Gets an element from array
	 *
	 * @param array  $arr     The array
	 * @param string $element The element
	 * @param mixed  $default The default value if the element does not exist
	 *
	 * @return mixed
	 */
	public function getArrayElement($arr, $element, $default) {
		return (is_array($arr) && array_key_exists($element, $arr))
			? $arr[$element]
			: $default;
	}

	/**
	 * Checks whether the required configuration fields are filled in
	 *
	 * @return bool
	 */
	public function isMethodConfigured() {
		return ((trim($this->getConfigValue('paymentsenserp_title')) != '')
			&& (trim($this->getConfigValue('paymentsenserp_gateway_username')) != '')
			&& (trim($this->getConfigValue('paymentsenserp_gateway_jwt')) != '')
		);
	}

	/**
	 * Adds order comment
	 *
	 * @param string $order_id        Order ID
	 * @param string $comment         Comment
	 * @param int    $order_status_id Order status ID
	 */
	public function addOrderComment($order_id, $comment, $order_status_id) {
		$this->load->model('checkout/order');
		$this->model_checkout_order->addOrderHistory(
			$order_id,
			$order_status_id,
			$comment,
			false
		);
	}

	/**
	 * Updates payment info
	 *
	 * @param string $order_id Order ID
	 * @param array  $response Transaction response data received from the gateway
	 *
	 * @return string Error message or empty string if the payment was successful
	 */
	public function updatePayment($order_id, $response) {
		$payment_status = $this->getPaymentStatus($response['StatusCode']);
		switch ($payment_status) {
			case self::PAYMENT_STATUS_CODE_SUCCESS:
				$order_status_id = $this->getConfigValue('paymentsenserp_successful_order_status_id');
				$result          = '';
				break;
			case self::PAYMENT_STATUS_CODE_FAIL:
				$order_status_id = $this->getConfigValue('paymentsenserp_failed_order_status_id');
				$result          = sprintf($this->language->get('text_payment_failed'), $response['Message']);
				break;
			case self::PAYMENT_STATUS_CODE_UNKNOWN:
			default:
				$order_status_id = self::OC_ORD_STATUS_PENDING;
				$result          = $this->language->get('error_unexpected_brief');
				break;
		}
		$this->addOrderComment($order_id, $response['Message'], $order_status_id);
		return $result;
	}

	/**
	 * Gets shopping cart platform URL
	 *
	 * @return string
	 */
	protected function getCartUrl()
	{
		return $this->config->get('config_ssl');
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
	 * Gets the API endpoint URL
	 *
	 * @param string      $request API request
	 * @param string|null $param   Parameter of the API request
	 *
	 * @return string
	 */
	protected function getApiEndpointUrl($request, $param = null) {
		$baseUrl = array_key_exists($this->getConfigValue('paymentsenserp_gateway_environment'), self::GW_ENVIRONMENTS)
			? self::GW_ENVIRONMENTS[$this->getConfigValue('paymentsenserp_gateway_environment')]['entry_point_url']
			: self::GW_ENVIRONMENTS['TEST']['entry_point_url'];

		$param   = (null !== $param) ? "/$param" : '';
		return $baseUrl . $request . $param;
	}

	/**
	 * Gets the numeric country ISO 3166-1 code by country name
	 *
	 * @param  string $country_name OpenCart country name
	 *
	 * @return string
	 */
	protected function getCountryCode($country_name) {
		$result        = '';
		$country_codes = array(
			'Afghanistan'=>'4',
			'Albania'=>'8',
			'Algeria'=>'12',
			'American Samoa'=>'16',
			'Andorra'=>'20',
			'Angola'=>'24',
			'Anguilla'=>'660',
			'Antarctica'=>'',
			'Antigua and Barbuda'=>'28',
			'Argentina'=>'32',
			'Armenia'=>'51',
			'Aruba'=>'533',
			'Australia'=>'36',
			'Austria'=>'40',
			'Azerbaijan'=>'31',
			'Bahamas'=>'44',
			'Bahrain'=>'48',
			'Bangladesh'=>'50',
			'Barbados'=>'52',
			'Belarus'=>'112',
			'Belgium'=>'56',
			'Belize'=>'84',
			'Benin'=>'204',
			'Bermuda'=>'60',
			'Bhutan'=>'64',
			'Bolivia'=>'68',
			'Bosnia and Herzegowina'=>'70',
			'Botswana'=>'72',
			'Brazil'=>'76',
			'Brunei Darussalam'=>'96',
			'Bulgaria'=>'100',
			'Burkina Faso'=>'854',
			'Burundi'=>'108',
			'Cambodia'=>'116',
			'Cameroon'=>'120',
			'Canada'=>'124',
			'Cape Verde'=>'132',
			'Cayman Islands'=>'136',
			'Central African Republic'=>'140',
			'Chad'=>'148',
			'Chile'=>'152',
			'China'=>'156',
			'Colombia'=>'170',
			'Comoros'=>'174',
			'Congo'=>'178',
			'Cook Islands'=>'180',
			'Costa Rica'=>'184',
			'Cote D\'Ivoire'=>'188',
			'Croatia'=>'384',
			'Cuba'=>'191',
			'Cyprus'=>'192',
			'Czech Republic'=>'196',
			'Democratic Republic of Congo'=>'203',
			'Denmark'=>'208',
			'Djibouti'=>'262',
			'Dominica'=>'212',
			'Dominican Republic'=>'214',
			'Ecuador'=>'218',
			'Egypt'=>'818',
			'El Salvador'=>'222',
			'Equatorial Guinea'=>'226',
			'Eritrea'=>'232',
			'Estonia'=>'233',
			'Ethiopia'=>'231',
			'Falkland Islands (Malvinas)'=>'238',
			'Faroe Islands'=>'234',
			'Fiji'=>'242',
			'Finland'=>'246',
			'France'=>'250',
			'French Guiana'=>'254',
			'French Polynesia'=>'258',
			'French Southern Territories'=>'',
			'Gabon'=>'266',
			'Gambia'=>'270',
			'Georgia'=>'268',
			'Germany'=>'276',
			'Ghana'=>'288',
			'Gibraltar'=>'292',
			'Greece'=>'300',
			'Greenland'=>'304',
			'Grenada'=>'308',
			'Guadeloupe'=>'312',
			'Guam'=>'316',
			'Guatemala'=>'320',
			'Guinea'=>'324',
			'Guinea-bissau'=>'624',
			'Guyana'=>'328',
			'Haiti'=>'332',
			'Honduras'=>'340',
			'Hong Kong'=>'344',
			'Hungary'=>'348',
			'Iceland'=>'352',
			'India'=>'356',
			'Indonesia'=>'360',
			'Iran (Islamic Republic of)'=>'364',
			'Iraq'=>'368',
			'Ireland'=>'372',
			'Israel'=>'376',
			'Italy'=>'380',
			'Jamaica'=>'388',
			'Japan'=>'392',
			'Jordan'=>'400',
			'Kazakhstan'=>'398',
			'Kenya'=>'404',
			'Kiribati'=>'296',
			'Korea, Republic of'=>'410',
			'Kuwait'=>'414',
			'Kyrgyzstan'=>'417',
			'Lao People\'s Democratic Republic'=>'418',
			'Latvia'=>'428',
			'Lebanon'=>'422',
			'Lesotho'=>'426',
			'Liberia'=>'430',
			'Libyan Arab Jamahiriya'=>'434',
			'Liechtenstein'=>'438',
			'Lithuania'=>'440',
			'Luxembourg'=>'442',
			'Macau'=>'446',
			'Macedonia'=>'807',
			'Madagascar'=>'450',
			'Malawi'=>'454',
			'Malaysia'=>'458',
			'Maldives'=>'462',
			'Mali'=>'466',
			'Malta'=>'470',
			'Marshall Islands'=>'584',
			'Martinique'=>'474',
			'Mauritania'=>'478',
			'Mauritius'=>'480',
			'Mexico'=>'484',
			'Micronesia, Federated States of'=>'583',
			'Moldova, Republic of'=>'498',
			'Monaco'=>'492',
			'Mongolia'=>'496',
			'Montserrat'=>'500',
			'Morocco'=>'504',
			'Mozambique'=>'508',
			'Myanmar'=>'104',
			'Namibia'=>'516',
			'Nauru'=>'520',
			'Nepal'=>'524',
			'Netherlands'=>'528',
			'Netherlands Antilles'=>'530',
			'New Caledonia'=>'540',
			'New Zealand'=>'554',
			'Nicaragua'=>'558',
			'Niger'=>'562',
			'Nigeria'=>'566',
			'Niue'=>'570',
			'Norfolk Island'=>'574',
			'Northern Mariana Islands'=>'580',
			'Norway'=>'578',
			'Oman'=>'512',
			'Pakistan'=>'586',
			'Palau'=>'585',
			'Panama'=>'591',
			'Papua New Guinea'=>'598',
			'Paraguay'=>'600',
			'Peru'=>'604',
			'Philippines'=>'608',
			'Pitcairn'=>'612',
			'Poland'=>'616',
			'Portugal'=>'620',
			'Puerto Rico'=>'630',
			'Qatar'=>'634',
			'Reunion'=>'638',
			'Romania'=>'642',
			'Russian Federation'=>'643',
			'Rwanda'=>'646',
			'Saint Kitts and Nevis'=>'659',
			'Saint Lucia'=>'662',
			'Saint Vincent and the Grenadines'=>'670',
			'Samoa'=>'882',
			'San Marino'=>'674',
			'Sao Tome and Principe'=>'678',
			'Saudi Arabia'=>'682',
			'Senegal'=>'686',
			'Seychelles'=>'690',
			'Sierra Leone'=>'694',
			'Singapore'=>'702',
			'Slovak Republic'=>'703',
			'Slovenia'=>'705',
			'Solomon Islands'=>'90',
			'Somalia'=>'706',
			'South Africa'=>'710',
			'Spain'=>'724',
			'Sri Lanka'=>'144',
			'Sudan'=>'736',
			'Suriname'=>'740',
			'Svalbard and Jan Mayen Islands'=>'744',
			'Swaziland'=>'748',
			'Sweden'=>'752',
			'Switzerland'=>'756',
			'Syrian Arab Republic'=>'760',
			'Taiwan'=>'158',
			'Tajikistan'=>'762',
			'Tanzania, United Republic of'=>'834',
			'Thailand'=>'764',
			'Togo'=>'768',
			'Tokelau'=>'772',
			'Tonga'=>'776',
			'Trinidad and Tobago'=>'780',
			'Tunisia'=>'788',
			'Turkey'=>'792',
			'Turkmenistan'=>'795',
			'Turks and Caicos Islands'=>'796',
			'Tuvalu'=>'798',
			'Uganda'=>'800',
			'Ukraine'=>'804',
			'United Arab Emirates'=>'784',
			'United Kingdom'=>'826',
			'United States'=>'840',
			'Uruguay'=>'858',
			'Uzbekistan'=>'860',
			'Vanuatu'=>'548',
			'Vatican City State (Holy See)'=>'336',
			'Venezuela'=>'862',
			'Viet Nam'=>'704',
			'Virgin Islands (British)'=>'92',
			'Virgin Islands (U.S.)'=>'850',
			'Wallis and Futuna Islands'=>'876',
			'Western Sahara'=>'732',
			'Yemen'=>'887',
			'Zambia'=>'894',
			'Zimbabwe'=>'716'
		);
		if (array_key_exists($country_name, $country_codes)) {
			$result = $country_codes[$country_name];
		}
		return $result;
	}

	/**
	 * Gets the numeric currency ISO 4217 code
	 *
	 * @param string $currency_code Alphabetic currency code
	 * @param string $default_code  Default numeric currency code
	 *
	 * @return string
	 */
	protected function getCurrencyCode($currency_code, $default_code = '826') {
		$result = $default_code;
		$iso_codes = array(
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
			'ZWL' => '932'
		);
		if (array_key_exists($currency_code, $iso_codes)) {
			$result = $iso_codes[$currency_code];
		}
		return $result;
	}

	/**
	 * Gets the value of a parameter from the extension configuration settings
	 *
	 * @param string      $key     Configuration key
	 * @param string|null $default Default value
	 *
	 * @return string|null
	 */
	protected function getConfigValue($key, $default = null) {
		$key = "payment_$key";
		$value = $this->config->get($key);
		if (is_null($value) && !is_null($default)) {
			$value = $default;
		}
		return $value;
	}

	/**
	 * Builds the HTTP headers for the API requests
	 *
	 * @return array An associative array containing the HTTP headers
	 */
	protected function buildRequestHeaders() {
		return array(
			'Cache-Control: no-cache',
			'Authorization: Bearer ' . $this->getConfigValue('paymentsenserp_gateway_jwt'),
			'Content-type: application/json'
		);
	}

	/**
	 * Builds the fields for the access tokens payment request
	 *
	 * @return array An associative array containing the fields for the request
	 */
	protected function buildRequestAccessTokensPayment() {
		$this->load->model('checkout/order');

		$order_info  = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		$order_total = round($this->currency->format($order_info['total'], $order_info['currency_code'], false, false)*100);
		$amount      = (string)$order_total;
		$orderId     = (string)$this->session->data['order_id'];

		$paymentsenserp_order_prefix     = $this->getConfigValue('paymentsenserp_order_prefix');
		$paymentsenserp_gateway_username = $this->getConfigValue('paymentsenserp_gateway_username');
		$paymentsenserp_transaction_type = $this->getConfigValue('paymentsenserp_transaction_type');

		return array(
			'gatewayUsername'  => $paymentsenserp_gateway_username,
			'currencyCode'     => $this->getCurrencyCode($order_info['currency_code']),
			'amount'           => $amount,
			'transactionType'  => $paymentsenserp_transaction_type,
			'orderId'          => $orderId,
			'orderDescription' => $paymentsenserp_order_prefix . $orderId,
			'userEmailAddress' => $order_info['email'],
			'userPhoneNumber'  => $order_info['telephone'],
			'userIpAddress'    => $this->request->server['REMOTE_ADDR'],
			'userAddress1'     => $order_info['payment_address_1'],
			'userAddress2'     => $order_info['payment_address_2'],
			'userCity'         => $order_info['payment_city'],
			'userState'        => $order_info['payment_zone'],
			'userPostcode'     => $order_info['payment_postcode'],
			'userCountryCode'  => $this->getCountryCode($order_info['payment_country']),
			'webHookUrl'       => $this->getPaymentNotification(),
			'metaData'         => $this->buildMetaData(['orderId' => $orderId])
		);
	}

	/**
	 * Builds the meta data
	 *
	 * @param array $additionalData Additional meta data
	 *
	 * @return array An associative array containing the meta data
	 */
	protected function buildMetaData($additionalData = [])
	{
		$metaData = [
			'shoppingCartUrl'      => $this->getCartUrl(),
			'shoppingCartPlatform' => $this->getCartPlatformName(),
			'shoppingCartVersion'  => $this->getOpenCartVersion(),
			'shoppingCartGateway'  => $this->getGatewayName(),
			'pluginVersion'        => $this->getModuleInstalledVersion()
		];
		return array_merge($metaData, $additionalData);
	}

	/**
	 * Builds the fields for the API requests by replacing the null values with empty strings
	 *
	 * @param array $fields An array containing the fields for the API request
	 *
	 * @return array An array containing the fields for the API request
	 */
	protected function buildRequestParams($fields) {
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
	protected function performCurl($request, &$response, &$info = array(), &$err_msg = '') {
		if (!function_exists('curl_version')) {
			$err_no   = 2; // CURLE_FAILED_INIT
			$err_msg  = 'cURL is not enabled';
			$info     = array();
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
	 * Checks whether the payment method is available for checkout
	 *
	 * @param array $address Order Address
	 * @param float $total   Order Total
	 *
	 * @return bool
	 */
	protected function isAvailable($address, $total) {
		if ($this->isSecureConnectionRequired() && !$this->isConnectionSecure()) {
			return false;
		}

		if ($total == 0) {
			return false;
		}

		if ($this->getConfigValue('paymentsenserp_geo_zone_id')) {
			$query = $this->db->query(
				"SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone " .
				"WHERE geo_zone_id = '" . (int)$this->getConfigValue('paymentsenserp_geo_zone_id') .
				"' AND country_id = '" . (int)$address['country_id'] .
				"' AND (zone_id = '" . (int)$address['zone_id'] .
				"' OR zone_id = '0')"
			);

			if (!$query->num_rows) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Determines whether a secure connection is required for this payment method
	 *
	 * @return bool
	 */
	protected function isSecureConnectionRequired() {
		return true;
	}

	/**
	 * Checks whether the connection is secure
	 *
	 * @return bool
	 */
	protected function isConnectionSecure() {
		$https = array_key_exists('HTTPS', $this->request->server)
			? $this->request->server['HTTPS']
			: '';
		$forwarded_proto = array_key_exists('HTTP_X_FORWARDED_PROTO', $this->request->server)
			? $this->request->server['HTTP_X_FORWARDED_PROTO']
			: '';
		return (!empty($https) && strtolower($https) != 'off')
			|| (!empty($forwarded_proto) && $forwarded_proto == 'https');
	}

	/**
	 * Gets the URL receiving the payment notifications from the payment gateway
	 *
	 * @return string A string containing URL
	 */
	protected function getPaymentNotification() {
		return str_replace(
			'&amp;',
			'&',
			$this->url->link(self::ROUTE_PAYMENT_NOTIFICATION, array(), 'SSL'
			)
		);
	}
}
