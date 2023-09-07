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
 * Front controller for Paymentsense Remote Payments
 */
class ControllerExtensionPaymentPaymentsenserp extends Controller
{
	/**
	 * Content types of the output of the module information
	 */
	const TYPE_APPLICATION_JSON = 'application/json';
	const TYPE_TEXT_PLAIN       = 'text/plain';

	/**
	 * OpenCart front routes
	 */
	const ROUTE_CHECKOUT_CART     = 'checkout/cart';
	const ROUTE_CHECKOUT_SUCCESS  = 'checkout/success';
	const ROUTE_CUSTOMER_REDIRECT = 'extension/payment/paymentsenserp/customerredirect';

	/**
	 * Supported content types of the output of the module information
	 *
	 * @var array
	 */
	protected $content_types = array(
		'json' => self::TYPE_APPLICATION_JSON,
		'text' => self::TYPE_TEXT_PLAIN
	);

	/**
	 * Index action handler showing the payment form
	 *
	 * @return string
	 */
	public function index() {
		$this->load->language('extension/payment/paymentsenserp');
		$this->load->model('checkout/order');
		$this->load->model('extension/payment/paymentsenserp');
		list($error_message, $form_data, $order_id) = $this->model_extension_payment_paymentsenserp->getPaymentFormData();
		if ($error_message) {
			$data = array(
				'error_message'                 => json_encode($error_message),
				'title'                         => $this->getConfigValue('paymentsenserp_title'),
				'message'                       => '',
				'payment_details_amount'        => '',
				'payment_details_currency_code' => '',
				'payment_details_payment_token' => '',
				'return_url'                    => '',
				'client_js_url'                 => '',
				'button_confirm'                => $this->language->get('button_confirm')
			);
		} else {
			$data = array(
				'error_message'                 => '',
				'title'                         => $this->getConfigValue('paymentsenserp_title'),
				'message'                       => $this->getConfigValue('paymentsenserp_description'),
				'payment_details_amount'        => $form_data['amount'],
				'payment_details_currency_code' => $form_data['currency_code'],
				'payment_details_payment_token' => $form_data['access_token'],
				'return_url'                    => $this->getCustomerRedirectUrl($order_id),
				'client_js_url'                 => $this->model_extension_payment_paymentsenserp->getClientJsUrl(),
				'button_confirm'                => $this->language->get('button_confirm')
			);
		}
		return $this->load->view("extension/payment/paymentsenserp", $data);
	}

	/**
	 * Processes the payment notification callback requests made by the payment gateway
	 */
	public function notification() {
		$this->language->load('extension/payment/paymentsenserp');
		$this->load->model('checkout/order');
		$this->load->model('extension/payment/paymentsenserp');

		if ($this->model_extension_payment_paymentsenserp->isMethodConfigured()) {
			if ($this->isPaymentNotificationRequest()) {
				$request_body = file_get_contents('php://input');
				if (! empty($request_body)) {
					$params = json_decode( $request_body, true );
					if (is_array($params)) {
						$access_token = $this->model_extension_payment_paymentsenserp->getArrayElement($params, 'id', '');
						$order_id = $this->model_extension_payment_paymentsenserp->getArrayElement($params, 'orderId', '');
						if (!empty($access_token) && !empty($order_id)) {
							$order = $this->model_checkout_order->getOrder($order_id);
							if ( $order_id === $this->model_extension_payment_paymentsenserp->getArrayElement($order, 'order_id', '')) {
								$order_status_id = $this->model_extension_payment_paymentsenserp->getArrayElement($order, 'order_status_id', '');
								if ( $order_status_id !== '' ) {
									$response = $this->model_extension_payment_paymentsenserp->getTransactionPaymentStatus($order_id, $access_token);
									if ($response[ 'OrderIdValid' ]) {
										$order_status_id = $this->model_extension_payment_paymentsenserp->getArrayElement($order, 'order_status_id', '');
										if (empty($order_status_id)) {
											$response['Message'] = $this->language->get( 'info_notification_received' ) . $response['Message'];
											$this->model_extension_payment_paymentsenserp->updatePayment($order_id, $response);
										}
										$this->send_ok_header();
									}
								}
							}
						}
					}
				}
			}
		}
		$this->send_bad_request_header();
	}

	/**
	 * Customer Redirect action handler handling redirection of the customer after completing the payment
	 */
	public function customerredirect() {
		$this->language->load('extension/payment/paymentsenserp');
		$this->load->model('checkout/order');
		$this->load->model('extension/payment/paymentsenserp');

		if (!$this->model_extension_payment_paymentsenserp->isMethodConfigured()) {
			$error_message = $this->language->get('error_payment_method_not_configured');
		} else {
			$session_data = $this->session->data;
			$order_id     = isset($session_data['order_id'])
				? (string)$session_data['order_id']
				: $this->getHttpVar('order_id', ModelExtensionPaymentPaymentsenserp::API_METHOD_GET);
			if (empty($order_id)) {
				$error_message = $this->language->get('error_empty_order_id');
			} else {
				$access_token = $this->getHttpVar('accessToken');
				if (empty($access_token)) {
					$access_token = $this->getHttpVar('paymentToken');
				}
				try {
					if (empty($access_token)) {
						$error_message = $this->language->get('error_empty_access_token');
					} else {
						$order = $this->model_checkout_order->getOrder($order_id);
						if ($order_id !== $this->model_extension_payment_paymentsenserp->getArrayElement($order, 'order_id', '')) {
							$error_message = $this->language->get('error_retrieving_order');
						} else {
							$response = $this->model_extension_payment_paymentsenserp->getTransactionPaymentStatus($order_id, $access_token);
							if (!$response['OrderIdValid']) {
								$error_message = $this->language->get('error_access_token_does_not_match');
							} else {
								$order_status_id = $this->model_extension_payment_paymentsenserp->getArrayElement($order, 'order_status_id', '');
								if (empty($order_status_id)) {
									$error_message = $this->model_extension_payment_paymentsenserp->updatePayment($order_id, $response);
								} else {
									if (ModelExtensionPaymentPaymentsenserp::PAYMENT_STATUS_CODE_SUCCESS === $this->model_extension_payment_paymentsenserp->getPaymentStatus($response['StatusCode'])) {
										$error_message = '';
									} else {
										$error_message = $response['Message'];
									}
								}
							}
						}
					}
				} catch (Exception $exception) {
					$error_message = sprintf($this->language->get('error_exception'), $exception->getMessage());
				}
			}
		}

		$this->session->data['error'] = $error_message ? $error_message : '';
		$route = $error_message ? self::ROUTE_CHECKOUT_CART : self::ROUTE_CHECKOUT_SUCCESS;
		$this->response->redirect($this->url->link($route, '', 'SSL'));
	}

	/**
	 * Module Information action handler
	 */
	public function info() {
		$this->load->model('extension/payment/paymentsenserp');
		$info = array(
			'Module Name'              => $this->model_extension_payment_paymentsenserp->getModuleName(),
			'Module Installed Version' => $this->model_extension_payment_paymentsenserp->getModuleInstalledVersion()
		);
		if ($this->getRequestParameter('extended_info', '') === 'true') {
			$extended_info = array(
				'OpenCart Version' => $this->model_extension_payment_paymentsenserp->getOpenCartVersion(),
				'PHP Version'      => $this->model_extension_payment_paymentsenserp->getPhpVersion()
			);
			$info = array_merge($info, $extended_info);
		}
		$this->outputInfo($info);
	}

	/**
	 * Checksums action handler
	 */
	public function checksums() {
		$info = array(
			'Checksums' => $this->getFileChecksums()
		);
		$this->outputInfo($info);
	}

	/**
	 * Gets the URL of the page where the customer will be redirected after completing the payment
	 *
	 * @param string $order_id Order ID
	 *
	 * @return string A string containing URL
	 */
	protected function getCustomerRedirectUrl($order_id) {
		return str_replace(
			'&amp;',
			'&',
			$this->url->link(self::ROUTE_CUSTOMER_REDIRECT, array('order_id' => $order_id), 'SSL'
			)
		);
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
	 * Gets the value of an HTTP GET/POST parameter
	 *
	 * @param string      $parameter HTTP GET/POST parameter
	 * @param string|null $method    HTTP method
	 *
	 * @return mixed
	 */
	protected function getHttpVar($parameter, $method = null) {
		if (empty($method)) {
			$method = $this->request->server['REQUEST_METHOD'];
		}
		switch ($method) {
			case 'GET':
				return array_key_exists($parameter, $this->request->get)
					? $this->request->get[$parameter]
					: '';
			case 'POST':
				return array_key_exists($parameter, $this->request->post)
					? $this->request->post[$parameter]
					: '';
			default:
				return '';
		}
	}

	/**
	 * Gets the value of a parameter from the route
	 *
	 * @param string $parameter Parameter
	 * @param string $default   Default value
	 *
	 * @return string
	 */
	protected function getRequestParameter($parameter, $default = '') {
		$result = $default;
		if (isset($this->request->get['route'])) {
			$route = $this->request->get['route'];
			if (preg_match('#/' . $parameter .'/([a-z0-9]+)#i', $route, $matches)) {
				$result = $matches[1];
			}
		}
		return $result;
	}

	/**
	 * Converts an array to string
	 *
	 * @param array  $arr   An associative array
	 * @param string $ident Indentation
	 *
	 * @return string
	 */
	protected function convertArrayToString($arr, $ident = '') {
		$result         = '';
		$indent_pattern = '  ';
		foreach ($arr as $key => $value) {
			if ('' !== $result) {
				$result .= PHP_EOL;
			}
			if (is_array($value)) {
				$value = PHP_EOL . $this->convertArrayToString($value, $ident . $indent_pattern);
			}
			$result .= $ident . $key . ': ' . $value;
		}
		return $result;
	}

	/**
	 * Outputs plugin information
	 *
	 * @param array $info Module information
	 */
	protected function outputInfo($info) {
		$output       = $this->getRequestParameter('output', 'text');
		$content_type = array_key_exists($output, $this->content_types)
			? $this->content_types[ $output ]
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

		@header('Cache-Control: max-age=0, must-revalidate, no-cache, no-store', true);
		@header('Pragma: no-cache', true);
		@header('Content-Type: ' . $content_type, true);
		echo $body;
		exit;
	}

	/**
	 * Gets file checksums
	 *
	 * @return array
	 */
	protected function getFileChecksums() {
		$result = array();
		$root_path = realpath(__DIR__ . '/../../../..');
		$file_list = $this->getHttpVar('data');
		if (is_array($file_list)) {
			foreach ($file_list as $key => $file) {
				$filename = $root_path . '/' . $file;
				$result[$key] = is_file($filename)
					? sha1_file($filename)
					: null;
			}
		}
		return $result;
	}

	/**
	 * Checks whether the current request is a payment notification
	 *
	 * @return bool
	 */
	protected function isPaymentNotificationRequest()
	{
		return $this->isPostMethod() && $this->isJsonRequest();
	}

	/**
	 * Checks whether the current request method is POST
	 *
	 * @return bool
	 */
	protected function isPostMethod() {
		return 'POST' === $this->model_extension_payment_paymentsenserp->getArrayElement($this->request->server, 'REQUEST_METHOD', '');
	}

	/**
	 * Checks whether the current request is a JSON request
	 *
	 * @return bool
	 */
	protected function isJsonRequest() {
		$contentType = $this->model_extension_payment_paymentsenserp->getArrayElement($this->request->server, 'CONTENT_TYPE', '');
		return isset($contentType) &&
			preg_match('/(^|\s|,)application\/json($|\s|;|,)/i', $contentType);
	}

	/**
	 * Sends a 200 OK HTTP header and exits
	 */
	protected function send_ok_header() {
		@header( $this->request->server[ 'SERVER_PROTOCOL' ] . ' 200 OK', true );
		exit;
	}

	/**
	 * Sends a 400 Bad Request HTTP header and exits
	 */
	protected function send_bad_request_header() {
		@header($this->request->server['SERVER_PROTOCOL'] . ' 400 Bad Request', true);
		exit;
	}
}
