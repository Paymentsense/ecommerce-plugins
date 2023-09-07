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
 * Admin controller for Paymentsense Remote Payments
 */
class ControllerExtensionPaymentPaymentsenserp extends Controller {

	/**
	 * Module version
	 */
	const MODULE_VERSION = '1.0.3';

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
	 * OpenCart admin routes
	 */
	const ROUTE_DASHBOARD          = 'common/dashboard';
	const ROUTE_PAYMENT_EXTENSION  = 'extension/payment/paymentsenserp';
	const ROUTE_PAYMENT_EXTENSIONS = 'marketplace/extension';

	/**
	 * Payment method code
	 *
	 * @var string
	 */
	protected $payment_code = 'paymentsenserp';

	/**
	 * Index action handler for showing and updating the extension configuration settings
	 */
	public function index() {
		$this->load->language('extension/payment/paymentsenserp');
		$this->document->setTitle($this->language->get('heading_title'));
		if ($this->request->server['REQUEST_METHOD'] === 'POST') {
			try {
				if (!$this->userHasPermission()) {
					$response = array(
						'type' => 'danger',
						'text' => $this->language->get('error_no_modify_permission')
					);
				} elseif (!$this->isMethodConfigured()) {
					$response = array(
						'type' => 'danger',
						'text' => $this->language->get('error_required_fields')
					);
				} else {
					$this->updateSettings();
					$response = array(
						'type' => 'success',
						'text' => $this->language->get('text_success')
					);
				}
			} catch (Exception $exception) {
				$response = array(
					'type' => 'danger',
					'text' => $this->language->get('error_save_failed')
				);
			}
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($response));
		} else {
			$template_data = $this->prepareConfigTemplateVars();
			$this->response->setOutput(
				$this->load->view("extension/payment/paymentsenserp", $template_data)
			);
		}
	}

	/**
	 * Determines whether a secure connection is required for this payment method
	 *
	 * @return bool
	 */
	public function isSecureConnectionRequired() {
		return true;
	}

	/**
	 * Gets the names and default values of the extension configuration fields
	 *
	 * @return array
	 */
	public function getConfigFields() {
		return array(
			'paymentsenserp_status'                     => null,
			'paymentsenserp_title'                      => 'Paymentsense',
			'paymentsenserp_description'                => 'Pay securely by credit or debit card through Paymentsense.',
			'paymentsenserp_order_prefix'               => 'OC-',
			'paymentsenserp_gateway_username'           => null,
			'paymentsenserp_gateway_jwt'                => null,
			'paymentsenserp_transaction_type'           => null,
			'paymentsenserp_gateway_environment'        => null,
			'paymentsenserp_successful_order_status_id' => self::OC_ORD_STATUS_PROCESSING,
			'paymentsenserp_failed_order_status_id'     => self::OC_ORD_STATUS_FAILED,
			'paymentsenserp_geo_zone_id'                => null,
			'paymentsenserp_sort_order'                 => null
		);
	}

	/**
	 * Checks whether the user is permitted to modify the extension and validates the input
	 *
	 * @return bool
	 */
	public function userHasPermission() {
		return $this->user->hasPermission('modify', 'extension/payment/paymentsenserp');
	}

	/**
	 * Checks whether the required configuration fields are filled in
	 *
	 * @return bool
	 */
	public function isMethodConfigured() {
		return !empty($this->request->post['paymentsenserp_title'])
			&& !empty($this->request->post['paymentsenserp_gateway_username'])
			&& !empty($this->request->post['paymentsenserp_gateway_jwt']);
	}

	/**
	 * Prepares the template variables for the extension configuration page
	 *
	 * @return array
	 */
	protected function prepareConfigTemplateVars() {
		$this->load->model('localisation/order_status');
		$this->load->model('localisation/geo_zone');
		$result = array(
			'extension_version'           => self::MODULE_VERSION,
			'order_statuses'              => $this->model_localisation_order_status->getOrderStatuses(),
			'geo_zones'                   => $this->model_localisation_geo_zone->getGeoZones(),
			'breadcrumbs'                 => $this->getBreadcrumbs(),
			'action'                      => $this->getPaymentExtensionLink(),
			'cancel'                      => $this->getPaymentExtensionsLink(),
			'header'                      => $this->load->controller('common/header'),
			'column_left'                 => $this->load->controller('common/column_left'),
			'footer'                      => $this->load->controller('common/footer'),
			'warning_insecure_connection' => $this->isSecureConnectionRequired() && !$this->isConnectionSecure()
				? $this->language->get('warning_insecure_connection')
				: ''
		);

		$fields = $this->getConfigFields();
		foreach ($fields as $key => $value) {
			if (isset($this->request->post[$key])) {
				$result[$key] = $this->request->post[$key];
			} else {
				$result[$key] = $this->getConfigValue($key, $value);
			}
		}

		return $result;
	}

	/**
	 * Gets token name
	 *
	 * @return string
	 */
	protected function getTokenName() {
		return 'user_token';
	}

	/**
	 * Gets token value
	 *
	 * @return string
	 */
	protected function getTokenValue() {
		return $this->session->data[$this->getTokenName()];
	}

	/**
	 * Builds token arguments string
	 *
	 * @return string
	 */
	protected function buildTokenArgumentString() {
		return $this->getTokenName() . '=' . $this->getTokenValue();
	}

	/**
	 * Gets a link to the dashboard
	 *
	 * @return string
	 */
	protected function getDashboardLink() {
		return $this->url->link(self::ROUTE_DASHBOARD, $this->buildTokenArgumentString(), 'SSL');
	}

	/**
	 * Gets a link to the Paymentsense Remote Payments payment extension
	 *
	 * @return string
	 */
	protected function getPaymentExtensionLink() {
		return $this->url->link(self::ROUTE_PAYMENT_EXTENSION, $this->buildTokenArgumentString(), 'SSL');
	}

	/**
	 * Gets a link to the payment extensions list
	 *
	 * @return string
	 */
	protected function getPaymentExtensionsLink() {
		return $this->url->link(self::ROUTE_PAYMENT_EXTENSIONS, 'type=payment&' . $this->buildTokenArgumentString(), 'SSL');
	}

	/**
	 * Gets the breadcrumbs
	 *
	 * @return array
	 */
	protected function getBreadcrumbs() {
		return array(
			array(
				'text' => $this->language->get('text_home'),
				'href' => $this->getDashboardLink()
			),
			array(
				'text' => $this->language->get('text_extension'),
				'href' => $this->getPaymentExtensionsLink()
			),
			array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->getPaymentExtensionLink()
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
	 * Checks whether the current connection is secure
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
	 * Updates extension configuration settings
	 */
	protected function updateSettings() {
		$this->load->model('setting/setting');

		$code = $this->payment_code;
		$data = $this->request->post;
		$code = "payment_$code";
		$data = array_combine(
			array_map(
				function($key) {
					return "payment_$key";
				},
				array_keys($data)
			),
			$data
		);

		$this->model_setting_setting->editSetting($code, $data);
	}
}
