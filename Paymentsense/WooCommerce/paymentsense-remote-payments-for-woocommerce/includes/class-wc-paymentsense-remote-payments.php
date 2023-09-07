<?php
/**
 * Paymentsense Remote Payments Payment Method
 *
 * @package	 Paymentsense_Remote_Payments_For_WooCommerce
 * @subpackage Paymentsense_Remote_Payments_For_WooCommerce/includes
 * @author	  Paymentsense
 * @link		 http://www.paymentsense.co.uk/
 */

/**
 * Exit if accessed directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

if ( ! class_exists( 'WC_Paymentsense_Remote_Payments' ) ) {
	
	require_once __DIR__ . '/class-wc-paymentsense-logger.php';

	/**
	 * WC_Paymentsense_Remote_Payments class.
	 */
	class WC_Paymentsense_Remote_Payments extends WC_Payment_Gateway {
		/**
		 * Shopping cart platform name
		 */
		const PLATFORM_NAME = 'WooCommerce';

		/**
		 * Payment gateway name
		 */
		const GATEWAY_NAME = 'Paymentsense';

		/**
		 * Payment method logo
		 */
		const LOGO = 'assets/images/logo.png';

		/**
		 * Standard CSS file
		 */
		const STANDARD_CSS = 'assets/css/standard.css';

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
		const API_REQUEST_ACCESS_TOKENS      = '/v1/access-tokens';
		const API_REQUEST_PAYMENTS           = '/v1/payments';
		const API_REQUEST_CROSS_REF_PAYMENTS = '/v1/cross-reference-payments';

		/**
		 * Transaction status codes
		 */
		const TRX_STATUS_CODE_SUCCESS             = 0;
		const TRX_STATUS_CODE_NOT_AVAILABLE 	  = -1;
		const TRX_STATUS_CODE_AUTHORIZING   	  = 3;
		const TRX_STATUS_CODE_REFERRED      	  = 4;
		const TRX_STATUS_CODE_DECLINED      	  = 5;
		const TRX_STATUS_CODE_DUPLICATED    	  = 20;
		const TRX_STATUS_CODE_FAILED        	  = 30;
		const TRX_STATUS_CODE_PROCESSING    	  = 40;
		const TRX_STATUS_CODE_REVOKED       	  = 90;
		const TRX_STATUS_CODE_WAITING_PRE_EXECUTE = 99;

		/**
		 * Payment status codes
		 */
		const PAYMENT_STATUS_CODE_UNKNOWN = 0;
		const PAYMENT_STATUS_CODE_SUCCESS = 1;
		const PAYMENT_STATUS_CODE_FAIL    = 2;

		/**
		 * Content types for module information
		 */
		const MINFO_TYPE_APPLICATION_JSON = 'application/json';
		const MINFO_TYPE_TEXT_PLAIN       = 'text/plain';

		/**
		 * PS Logger
		 */
		private $logger;
		
		/**
		 * Payment method ID
		 *
		 * @var string
		 */
		public $id = 'paymentsense_remote_payments';

		/**
		 * Payment method title
		 *
		 * @var string
		 */
		public $method_title = 'Paymentsense - Remote Payments';

		/**
		 * Payment method title for the frontend
		 *
		 * @var string
		 */
		public $method_title_frontend = 'Paymentsense';

		/**
		 * Payment method description
		 *
		 * @var string
		 */
		public $method_description = 'Accept payments from credit/debit cards through Paymentsense - Remote Payments';

		/**
		 * Specifies whether the payment method shows fields on the checkout
		 *
		 * @var bool
		 */
		public $has_fields = false;

		/**
		 * Payment gateway username
		 *
		 * @var string
		 */
		protected $gateway_username;

		/**
		 * Payment gateway JWT
		 *
		 * @var string
		 */
		protected $gateway_jwt;

		/**
		 * Payment gateway transaction type
		 *
		 * @var string
		 */
		protected $gateway_transaction_type;

		/**
		 * Payment gateway environment
		 *
		 * @var string
		 */
		protected $gateway_environment;

		/**
		 * Order prefix
		 *
		 * @var string
		 */
		protected $order_prefix;

		/**
		 * Telemetry instrumentation key
		 *
		 * @var string
		 */
		protected $telemetry_instrumentation_key;

		/**
		 * A delay to wait until redirect to order processed page.
		 * This is required, because if redirected too fast, the order 
		 * might not be processed yet (a mitigation).
		 * @var string
		 */
		protected $delay_before_redirect_after_order_processed;

		/**
		 * Plugin data
		 *
		 * @var array
		 */
		protected $plugin_data = [];

		/**
		 * Supported content types of the output of the module information
		 *
		 * @var array
		 */
		protected $content_types = [
			'json' => self::MINFO_TYPE_APPLICATION_JSON,
			'text' => self::MINFO_TYPE_TEXT_PLAIN,
		];

		/**
		 * Paymentsense Remote Payments class constructor
		 */
		public function __construct() {
			$this->init_form_fields();
			$this->load_configuration();
			$this->set_logo();
			$this->add_refund_support();
			$this->add_hooks();
			$this->retrieve_plugin_data();

			$this->logger = new WC_Paymentsense_Logger(
				$this->get_module_installed_version(), 
				$this->gateway_username,
				$this->telemetry_instrumentation_key
			 );

			$this->logger->EmitEvent("Plugin created");

			set_exception_handler(array($this, 'exceptionHandler'));
		}

		public function exceptionHandler($exception) {
			if ($exception != NULL)	{
				$this->logger->LogMessage( "$exception", 'Error' );
			}
		}

		/**
		 * Initialises settings form fields
		 *
		 * Overrides wc settings api class method
		 */
		public function init_form_fields() {
			$this->form_fields = [
				'enabled'	 	 	 	   => [
					'title'   => __( 'Enable/Disable:', 'woocommerce-paymentsense-remote-payments' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable ', 'woocommerce-paymentsense-remote-payments' ) . $this->method_title,
					'default' => 'yes',
				],

				'module_options'	 		 => [
					'title'       => __( 'Module Options', 'woocommerce-paymentsense-remote-payments' ),
					'type'        => 'title',
					'description' => sprintf(
					// Translators: %s - Payment method title.
						__( 'The following options affect how the %s Module is displayed on the frontend.', 'woocommerce-paymentsense-remote-payments' ),
						$this->method_title
					),
				],

				'title'	 	 	 	 	 => [
					'title'		 => __( 'Title:', 'woocommerce-paymentsense-remote-payments' ),
					'type'	 	 => 'text',
					'description' => __( 'This controls the title which the customer sees during checkout.', 'woocommerce-paymentsense-remote-payments' ),
					'default'	  => $this->method_title_frontend,
					'desc_tip'	 => true,
				],

				'description'	 	 	   => [
					'title'		 => __( 'Description:', 'woocommerce-paymentsense-remote-payments' ),
					'type'	 	 => 'textarea',
					'description' => __( 'This controls the description which the customer sees during checkout.', 'woocommerce-paymentsense-remote-payments' ),
					'default'	  => __( 'Pay securely by credit or debit card through Paymentsense.', 'woocommerce-paymentsense-remote-payments' ),
					'desc_tip'	 => true,
				],

				'order_prefix'	 	 	  => [
					'title'		 => __( 'Order Prefix:', 'woocommerce-paymentsense-remote-payments' ),
					'type'	 	 => 'text',
					'description' => __( 'This is the order prefix that you will see in the Merchant Portal.', 'woocommerce-paymentsense-remote-payments' ),
					'default'	  => 'WC-',
					'desc_tip'	 => true,
				],

				'gateway_settings'	 	  => [
					'title'		 => __( 'Gateway Settings', 'woocommerce-paymentsense-remote-payments' ),
					'type'	 	 => 'title',
					'description' => __( 'These are the gateway settings to allow you to connect with the Paymentsense gateway.', 'woocommerce-paymentsense-remote-payments' ),
				],

				'gateway_username'	 	  => [
					'title'		 => __( 'Gateway Username/URL:', 'woocommerce-paymentsense-remote-payments' ),
					'type'	 	 => 'text',
					'description' => __( 'This is the gateway username or URL.', 'woocommerce-paymentsense-remote-payments' ),
					'default'	  => '',
					'desc_tip'	 => true,
				],

				'gateway_jwt'	 	 	   => [
					'title'		 => __( 'Gateway JWT:', 'woocommerce-paymentsense-remote-payments' ),
					'type'	 	 => 'text',
					'description' => __( 'This is the gateway JWT.', 'woocommerce-paymentsense-remote-payments' ),
					'default'	  => '',
					'desc_tip'	 => true,
				],

				'gateway_transaction_type' => [
					'title'		 => __( 'Transaction Type:', 'woocommerce-paymentsense-remote-payments' ),
					'type'	 	 => 'select',
					'description' => __( 'If you wish to obtain authorisation for the payment only, as you intend to manually collect the payment via the Merchant Portal, choose Pre-auth.', 'woocommerce-paymentsense-remote-payments' ),
					'default'	  => 'SALE',
					'desc_tip'	 => true,
					'options'	  => [
						'SALE' => __( 'Sale', 'woocommerce-paymentsense-remote-payments' ),
					],
				],

				'gateway_environment'	   => [
					'title'       => __( 'Gateway Environment:', 'woocommerce-paymentsense-remote-payments' ),
					'type'        => 'select',
					'description' => __( 'Gateway environment for performing transactions.', 'woocommerce-paymentsense-remote-payments' ),
					'default'     => 'TEST',
					'desc_tip'    => true,
					'options'     => $this->get_gw_environment_names(),
				],

				'telemetry_instrumentation_key' => [
					'title'		 => __( 'Telemetry:', 'woocommerce-paymentsense-remote-payments' ),
					'type'	 	 => 'text',
					'description' => __( 'This is the cloud telemetry instrumentation key. If not provided, no telemetry is being emitted.', 'woocommerce-paymentsense-remote-payments' ),
					'default'	  => '',
					'desc_tip'	 => true,
				],

				'delay_before_redirect_after_order_processed' => [
					'title'		 => __( 'Order processed redirect delay:', 'woocommerce-paymentsense-remote-payments' ),
					'type'	 	 => 'text',
					'description' => __( 'Amount of seconds to wait, before redirecting a user to order handled (or failure page), after processing a payment', 'woocommerce-paymentsense-remote-payments' ),
					'default'	  => '1',
					'desc_tip'	 => true,
				],
			];
		}

		/**
		 * Receipt page
		 *
		 * @param int $order_id Order ID.
		 */
		public function receipt_page( $order_id ) {
			$this->logger->EmitEvent("Receipt page called");

			if ( $this->is_valid_for_use() ) {
				$this->show_payment_form( $order_id );
			} elseif ( ! $this->is_connection_secure() ) {
				$this->show_message(
					__( 'The Paymentsense payment method requires an encrypted connection.', 'woocommerce-paymentsense-remote-payments' ) . ' ' .
					__( 'Please contact customer support.', 'woocommerce-paymentsense-remote-payments' )
				);
			} else {
				$this->show_message(
					__( 'The Paymentsense payment method is not configured.', 'woocommerce-paymentsense-remote-payments' ) . ' ' .
					__( 'Please contact customer support.', 'woocommerce-paymentsense-remote-payments' )
				);
			}
		}

		/**
		 * Processes the payment
		 *
		 * Overrides wc payment gateway class method
		 *
		 * @param int $order_id WooCommerce Order ID.
		 *
		 * @return array
		 */
		public function process_payment( $order_id ) {
			$this->logger->LogMessage("Process payment start. Order id: {$order_id}.");

			$order = new WC_Order( $order_id );
			$status = $order->get_status();
			$redirectUrl = $order->get_checkout_payment_url( true );
			$result = [
				'result'   => 'success',
				'redirect' => $redirectUrl,
			];
			
			$this->logger->LogMessage("Process payment end. Order status: {$status}. Redirecting to: '{$redirectUrl}'.");
			return $result;
		}

		/**
		 * Processes the requests sent to the plugin through the WooCommerce API Callback
		 */
		public function process_requests() {
			$this->logger->LogMessage("Process requests start...");
			
			switch ( true ) {
				case $this->is_payment_notification_request():
					$this->process_payment_notification_request();
					break;
				case $this->is_info_request():
					$this->process_info_request();
					break;
				case $this->is_checksums_request():
					$this->process_checksums_request();
					break;
				case $this->is_post_request():
					$this->process_customer_redirect();
					break;
				default:
					$this->show_bad_request_page();
			}

			$this->logger->LogMessage("Process request end");
		}

		/**
		 * Outputs the payment method settings in the admin panel
		 *
		 * Overrides wc payment gateway class method
		 */
		public function admin_options() {
			$this->show_output(
				'paymentsense-remote-payments-settings.php',
				[
					'this_'         => $this,
					'title'         => $this->get_method_title(),
					'description'   => $this->get_method_description(),
					'error_message' => $this->is_connection_secure()
						? ''
						: __( 'This payment method requires an encrypted connection. ', 'woocommerce-paymentsense-remote-payments' ) .
						__( 'Please enable SSL/TLS.', 'woocommerce-paymentsense-remote-payments' ),
				]
			);
		}

		/**
		 * Processes refunds.
		 *
		 * @param int    $order_id Order ID.
		 * @param float  $amount   Refund amount.
		 * @param string $reason   Refund Reason.
		 *
		 * @return bool|WP_Error
		 */
		public function process_refund( $order_id, $amount = null, $reason = '' ) {
			$this->logger->LogMessage( "Process refund. Order id: {$order_id}, amount: {$amount}..." );

			$access_token = null;
			$message      = 'N/A';

			$data = [
				'url'         => $this->get_api_endpoint_url( self::API_REQUEST_ACCESS_TOKENS ),
				'method'      => self::API_METHOD_POST,
				'headers'     => $this->build_request_headers(),
				'post_fields' => $this->build_request_params(
					$this->build_request_access_tokens_refund(
						$order_id,
						$amount,
						get_post_meta( $order_id, 'cross_ref', true )
					)
				),
			];

			$curl_err_no = $this->perform_transaction( $data, $response, $info, $curl_err_msg );
			if ( 0 === $curl_err_no ) {
				$http_code = $info['http_code'];
				if ( WP_Http::OK === $http_code ) {
					$response     = json_decode( $response, true );
					$access_token = $this->get_array_element( $response, 'id', null );
				}
			}

			if ( $access_token ) {
				$status_code = 'N/A';
				$data        = [
					'url'         => $this->get_api_endpoint_url( self::API_REQUEST_CROSS_REF_PAYMENTS, $access_token ),
					'method'      => self::API_METHOD_POST,
					'headers'     => $this->build_request_headers(),
					'post_fields' => $this->build_request_params(
						$this->build_request_refund(
							get_post_meta( $order_id, 'cross_ref', true )
						)
					),
				];

				$curl_err_no = $this->perform_transaction( $data, $response, $info, $curl_err_msg );
				if ( 0 === $curl_err_no ) {
					$http_code = $info['http_code'];
					if ( WP_Http::OK === $http_code ) {
						$response    = json_decode( $response, true );
						$status_code = $this->get_array_element( $response, 'statusCode', '' );
						$message     = $this->get_array_element( $response, 'message', '' );

						$this->logger->LogMessage( "Transaction success. Status code: {$status_code}, message: {$message}" );
					}
				}

				if ( self::TRX_STATUS_CODE_SUCCESS !== $status_code ) {
					if ( 0 !== $curl_err_no ) {
						$message = sprintf(
						// Translators: %1$s - cURL error number, %2$s - cURL error message.
							__(
								'An error has occurred. (cURL Error No: %1$s, cURL Error Message: %2$s). ',
								'woocommerce-paymentsense-remote-payments'
							),
							$curl_err_no,
							$curl_err_msg
						);
					} elseif ( WP_Http::OK !== $http_code ) {
						$message = sprintf(
						// Translators: %1$s - HTTP status code, %2$s - payment gateway message.
							__(
								'An error has occurred. (HTTP Status Code: %1$s, Payment Gateway Message: %2$s). ',
								'woocommerce-paymentsense-remote-payments'
							),
							$http_code,
							$response
						);
					} elseif ( self::TRX_STATUS_CODE_DUPLICATED === $status_code ) {
						$message = __(
							'Refund cannot be performed at this time. Please try again after 60 seconds. ',
							'woocommerce-paymentsense-remote-payments'
						);
					} else {
						$message = sprintf(
						// Translators: %1$s - status code, %2$s - payment gateway message.
							__(
								'Refund was declined. (Status Code: %1$s, Payment Gateway Message: %2$s). ',
								'woocommerce-paymentsense-remote-payments'
							),
							$status_code,
							$message
						);
					}
					$this->logger->LogMessage( "Refund error: {$message}", 'Error' );
					return new WP_Error( 'refund_error', $message );
				}
			} else {
				$this->logger->LogMessage( "Access token not received!", 'Error' );

				$message = __( 'An unexpected error has occurred. ', 'woocommerce-paymentsense-remote-payments' ) . ' ' .
					__( 'Please contact customer support.', 'woocommerce-paymentsense-remote-payments' );
				return new WP_Error( 'refund_error', $message );
			}

			$order = new WC_Order( $order_id );
			if ( $order->get_id() ) {
				$order_note = sprintf(
				// Translators: %1$s - order amount, %2$s - currency.
					__( 'Refund for %1$.2f %2$s processed successfully.', 'woocommerce-paymentsense-remote-payments' ),
					$amount,
					get_woocommerce_currency()
				);
				$order->add_order_note( $order_note );

				$this->logger->LogMessage( "Order with {$order_id} updated with note: {$order_note}" );
			}

			return true;
		}

		/**
		 * Validates payment fields on the frontend.
		 *
		 * Overrides parent wc payment gateway class method
		 *
		 * @return bool
		 */
		public function validate_fields() {
			if ( ! $this->is_connection_secure() ) {
				wc_add_notice(
					__( 'This payment method requires an encrypted connection. ', 'woocommerce-paymentsense-remote-payments' )
					. __( 'Please enable SSL/TLS.', 'woocommerce-paymentsense-remote-payments' ),
					'error'
				);
				$this->logger->LogMessage( "Connection is not secure!", 'Error' );
				return false;
			}
			return true;
		}

		/**
		 * Determines if the payment method is available
		 *
		 * Checks whether the gateway username and JWT are set
		 *
		 * @return bool
		 */
		public function is_valid_for_use() {
			return (
				$this->is_connection_secure() &&
				! empty( $this->gateway_username ) &&
				! empty( $this->gateway_jwt )
			);
		}

		/**
		 * Processes the payment notification callback requests made by the payment gateway
		 */
		protected function process_payment_notification_request() {
			$this->logger->LogMessage( 'Process payment notification request start...' );

			if ( ! $this->is_valid_for_use() ) {
				$this->logger->LogMessage( "Not valid for use", 'Error' );
				$this->show_bad_request_page();
			}

			$request_body_valid = false;

			$request_body = $this->get_request_body();
			if ( ! empty( $request_body ) ) {
				$params = json_decode( $request_body, true );
				if ( is_array( $params ) ) {
					$access_token = $this->get_array_element( $params, 'id', '' );
					$order_id     = $this->get_array_element( $params, 'orderId', '' );

					$this->logger->LogMessage( "Access token from request body: {$access_token}, Order id: {$order_id}" );

					if ( ( ! empty( $access_token ) ) && ( ! empty( $order_id ) ) ) {
						if ( $order_id === $this->get_order_by_access_token( $access_token ) ) {
							$request_body_valid = true;
						}
					}
				}
			}

			if ( ! $request_body_valid ) {
				$this->show_bad_request_page();
			}

			try {
				$order = new WC_Order( $order_id );
			} catch (Exception $exception) { // @codingStandardsIgnoreLine
				$this->logger->LogException( $exception );
			}

			if ( ! ( isset( $order ) && ( $order instanceof WC_Order ) && ( ! empty( $order->get_id() ) ) ) ) {
				$this->show_bad_request_page();
			}

			$order_status = $order->get_status();

			$this->logger->LogMessage( "Order with id {$order_id} current status: {$order_status}" );
			
			if ( 'pending' === $order_status ) {
				try {
					$order->add_order_note(
						__( 'Payment notification received.', 'woocommerce-paymentsense-remote-payments' )
					);

					$http_code   = null;
					$status_code = self::TRX_STATUS_CODE_NOT_AVAILABLE;
					$message     = '';

					$data = [
						'url'     => $this->get_api_endpoint_url( self::API_REQUEST_PAYMENTS, $access_token ),
						'method'  => self::API_METHOD_GET,
						'headers' => $this->build_request_headers(),
					];

					$curl_err_no = $this->perform_transaction( $data, $response, $info, $curl_err_msg );
					if ( 0 === $curl_err_no ) {
						$http_code = $info['http_code'];
						if ( WP_Http::OK === $http_code ) {
							$response    = json_decode( $response, true );
							$status_code = $this->get_array_element( $response, 'statusCode', '' );
							$message     = $this->get_array_element( $response, 'message', '' );
							$cross_ref   = $this->get_array_element( $response, 'crossReference', '' );
							update_post_meta( (int) $order->get_id(), 'cross_ref', $cross_ref );
						}
					}

					$payment_status = $this->get_payment_status( $status_code );

					switch ( $payment_status ) {
						case self::PAYMENT_STATUS_CODE_SUCCESS:
							$order->payment_complete();
							$order->add_order_note(
								__( 'Payment processed successfully. ', 'woocommerce-paymentsense-remote-payments' ) . $message
							);
							$this->logger->LogMessage( "Payment for order '{$order_id}' processed successfully." );
							break;
						case self::PAYMENT_STATUS_CODE_FAIL:
							$order->update_status(
								'failed',
								__( 'Payment failed due to: ', 'woocommerce-paymentsense-remote-payments' ) . $message
							);
							break;
						case self::PAYMENT_STATUS_CODE_UNKNOWN:
						default:
							$info = ( 0 === $curl_err_no )
								? sprintf(
								// Translators: %s - HTTP code, Status Code, Message.
									__(
										'HTTP Code: %1$s; Status Code %2$s; Message: %3$s;',
										'woocommerce-paymentsense-remote-payments'
									),
									$http_code,
									$status_code,
									$response
								)
								: sprintf(
								// Translators: %s - cURL error number and message.
									__(
										'cURL Error No: %1$s; cURL Error Message: %2$s;',
										'woocommerce-paymentsense-remote-payments'
									),
									$curl_err_no,
									$curl_err_msg
								);
							$order->add_order_note(
								__( 'Payment status is unknown because of a communication error or unknown/unsupported payment status.', 'woocommerce-paymentsense-remote-payments' ) .
								$info
							);
							break;
					}
				} catch ( Exception $exception ) {
					$this->logger->LogException( $exception );
					$this->show_bad_request_page();
				}
			}

			$this->logger->LogMessage( 'Process payment notification end' );
			$this->send_ok_header();
		}

		/**
		 * Processes the customer redirect from the payment form
		 */
		protected function process_customer_redirect() {
			$this->logger->LogMessage( 'Process customer redirect start...' );

			if ( ! $this->is_valid_for_use() ) {
				$this->show_error(
					__( 'The Paymentsense payment method is not configured.', 'woocommerce-paymentsense-remote-payments' ) . ' ' .
					__( 'Please contact customer support.', 'woocommerce-paymentsense-remote-payments' )
				);
			}

			$access_token = $this->get_http_var( 'accessToken', '', 'POST' );

			if ( empty( $access_token ) ) {
				$access_token = $this->get_http_var( 'paymentToken', '', 'POST' );
			}

			if ( empty( $access_token ) ) {
				$this->show_error(
					__( 'Access token is empty.', 'woocommerce-paymentsense-remote-payments' ) . ' ' .
					__( 'Please contact customer support.', 'woocommerce-paymentsense-remote-payments' )
				);
			}

			$order_id = $this->get_order_by_access_token( $access_token );

			if ( empty( $order_id ) ) {
				$this->show_error(
					__( 'Order ID is empty.', 'woocommerce-paymentsense-remote-payments' ) . ' ' .
					__( 'Please contact customer support.', 'woocommerce-paymentsense-remote-payments' )
				);
			}

			try {
				$order = new WC_Order( $order_id );
			} catch ( Exception $exception ) { // @codingStandardsIgnoreLine
				$this->logger->LogException( $exception );
			}

			if ( ! ( isset( $order ) && ( $order instanceof WC_Order ) && ( ! empty( $order->get_id() ) ) ) ) {
				$this->show_error(
					__( 'Order ID is invalid.', 'woocommerce-paymentsense-remote-payments' ) . ' ' .
					__( 'Please contact customer support.', 'woocommerce-paymentsense-remote-payments' )
				);
			}

			$order_status = $order->get_status();
			$this->logger->LogMessage( "Order with id {$order_id} current status: {$order_status}" );

			switch ( $order_status ) {
				case 'pending':
					try {
						$http_code   = null;
						$status_code = self::TRX_STATUS_CODE_NOT_AVAILABLE;
						$message     = '';

						$data = [
							'url'     => $this->get_api_endpoint_url( self::API_REQUEST_PAYMENTS, $access_token ),
							'method'  => self::API_METHOD_GET,
							'headers' => $this->build_request_headers(),
						];

						$curl_err_no = $this->perform_transaction( $data, $response, $info, $curl_err_msg );
						if ( 0 === $curl_err_no ) {
							$http_code = $info['http_code'];
							if ( WP_Http::OK === $http_code ) {
								$response    = json_decode( $response, true );
								$status_code = $this->get_array_element( $response, 'statusCode', '' );
								$message     = $this->get_array_element( $response, 'message', '' );
								$cross_ref   = $this->get_array_element( $response, 'crossReference', '' );
								update_post_meta( (int) $order->get_id(), 'cross_ref', $cross_ref );
							}
						}

						$payment_status = $this->get_payment_status( $status_code );

						switch ( $payment_status ) {
							case self::PAYMENT_STATUS_CODE_SUCCESS:
								$order->payment_complete();
								$order->add_order_note( __( 'Payment processed successfully. ', 'woocommerce-paymentsense-remote-payments' ) . $message );
								$location = $order->get_checkout_order_received_url();
								$this->logger->LogMessage( "Payment for order '{$order_id}' processed successfully." );
								break;
							case self::PAYMENT_STATUS_CODE_FAIL:
								$order->update_status( 'failed', __( 'Payment failed due to: ', 'woocommerce-paymentsense-remote-payments' ) . $message );
								wc_add_notice(
									__( 'Payment failed due to: ', 'woocommerce-paymentsense-remote-payments' ) . $message . '<br />' .
									__( 'Please check your card details and try again.', 'woocommerce-paymentsense-remote-payments' ),
									'error'
								);
								$location = $order->get_checkout_payment_url();
								$this->logger->LogMessage( 'Payment failed. '. 'Error' );
								break;
							case self::PAYMENT_STATUS_CODE_UNKNOWN:
							default:
								$info = ( 0 === $curl_err_no )
									? sprintf(
									// Translators: %s - HTTP code, Status Code, Message.
										__(
											'HTTP Code: %1$s; Status Code %2$s; Message: %3$s;',
											'woocommerce-paymentsense-remote-payments'
										),
										$http_code,
										$status_code,
										$response
									)
									: sprintf(
									// Translators: %s - cURL error number and message.
										__(
											'cURL Error No: %1$s; cURL Error Message: %2$s;',
											'woocommerce-paymentsense-remote-payments'
										),
										$curl_err_no,
										$curl_err_msg
									);
								$order->add_order_note(
									__( 'Payment status is unknown because of a communication error or unknown/unsupported payment status.', 'woocommerce-paymentsense-remote-payments' ) .
									$info
								);
								wc_add_notice(
									sprintf(
									// Translators: %s - order ID.
										__( 'Payment status is unknown. Please contact customer support quoting your order #%s and do not retry the payment for this order unless you are instructed to do so.', 'woocommerce-paymentsense-remote-payments' ),
										$order_id
									),
									'error'
								);
								$location = $order->get_checkout_payment_url();
								break;
						}
					} catch ( Exception $exception ) {
						$this->logger->LogException( $exception );
						wc_add_notice(
							sprintf(
							// Translators: %1$s - order number, %2$s - error message.
								__( 'An error occurred while processing order#%1$s. Error message: %2$s', 'woocommerce-paymentsense-remote-payments' ),
								$order_id,
								$exception->getMessage()
							),
							'error'
						);
						$location = $order->get_checkout_payment_url();
					}
					break;
				case 'processing':
				case 'completed':
					$location = $order->get_checkout_order_received_url();
					break;
				case 'failed':
					$message = $this->get_order_note( $order_id );
					wc_add_notice(
						$message . '<br />' .
						__( 'Please check your card details and try again.', 'woocommerce-paymentsense-remote-payments' ),
						'error'
					);
					$location = $order->get_checkout_payment_url();
					break;
				default:
					wc_add_notice(
						sprintf(
						// Translators: %s - order ID.
							__( 'Payment status is unknown. Please contact customer support quoting your order #%s and do not retry the payment for this order unless you are instructed to do so.', 'woocommerce-paymentsense-remote-payments' ),
							$order_id
						),
						'error'
					);
					$location = $order->get_checkout_payment_url();
			}

			$this->logger->LogMessage( "Redirecting to '{$location }' after {$this->delay_before_redirect_after_order_processed} seconds delay..." );
			sleep(intval($this->delay_before_redirect_after_order_processed));
			wp_safe_redirect( $location );
			$this->logger->LogMessage( 'Process customer redirect end' );
			exit;
		}

		/**
		 * Gets the latest order note
		 *
		 * @param int $order_id WooCommerce Order ID.
		 *
		 * @return string
		 */
		protected function get_order_note( $order_id ) {
			$result      = '';
			$order_notes = wc_get_order_notes(
				[
					'order_id' => $order_id,
					'limit'    => 1,
					'orderby'  => 'date_created_gmt',
				]
			);

			$latest_note = current( $order_notes );

			if ( isset( $latest_note->content ) ) {
				$result = $latest_note->content;
			}

			return $result;
		}

		/**
		 * Checks whether the current request is a payment notification
		 *
		 * @return bool
		 */
		protected function is_payment_notification_request() {
			return $this->is_post_request() && wp_is_json_request();
		}

		/**
		 * Checks whether the request is for plugin information
		 *
		 * @return bool
		 */
		protected function is_info_request() {
			return 'info' === $this->get_http_var( 'action', '' );
		}

		/**
		 * Checks whether the request is for file checksums
		 *
		 * @return bool
		 */
		protected function is_checksums_request() {
			return 'checksums' === $this->get_http_var( 'action', '', 'GET' );
		}

		/**
		 * Checks whether the HTTP request method is POST
		 *
		 * @return bool
		 */
		protected function is_post_request() {
			// @codingStandardsIgnoreLine
			return 'POST' === $_SERVER['REQUEST_METHOD'];
		}

		/**
		 * Determines whether the store is configured to use a secure connection
		 *
		 * @return bool
		 */
		protected function is_connection_secure() {
			return is_ssl();
		}

		/**
		 * Loads the configuration
		 */
		protected function load_configuration() {
			$options = [
				'enabled',
				'title',
				'description',
				'order_prefix',
				'gateway_username',
				'gateway_jwt',
				'gateway_transaction_type',
				'gateway_environment',
				'telemetry_instrumentation_key',
				'delay_before_redirect_after_order_processed'
			];
			foreach ( $options as $option ) {
				$this->$option = $this->get_option( $option );
			}
		}

		/**
		 * Sets the payment method logo
		 */
		protected function set_logo() {
			if ( ! empty( self::LOGO ) ) {
				$this->icon = apply_filters(
					'woocommerce_' . $this->id . '_icon',
					plugins_url( self::LOGO, __DIR__ )
				);
			}
		}

		/**
		 * Adds support for refunds
		 */
		protected function add_refund_support() {
			array_push(
				$this->supports,
				'refunds'
			);
		}

		/**
		 * Adds hooks
		 */
		protected function add_hooks() {
			add_action(
				'woocommerce_update_options_payment_gateways_' . $this->id,
				[ $this, 'process_admin_options' ]
			);
			add_action(
				'woocommerce_receipt_' . $this->id,
				[ $this, 'receipt_page' ]
			);
			add_action(
				'woocommerce_api_wc_' . $this->id,
				[ $this, 'process_requests' ]
			);
		}

		/**
		 * Gets gateway environment names
		 *
		 * @return array
		 */
		protected function get_gw_environment_names() {
			$result = [];
			foreach ( self::GW_ENVIRONMENTS as $gw_env_id => $gw_env_config ) {
				$result[ $gw_env_id ] = $gw_env_config['name'];
			}
			return $result;
		}

		/**
		 * Gets the API endpoint URL
		 *
		 * @param string $request API request.
		 * @param string $param Parameter of the API request.
		 *
		 * @return string
		 */
		protected function get_api_endpoint_url( $request, $param = null ) {
			$base_url = array_key_exists( $this->gateway_environment, self::GW_ENVIRONMENTS )
				? self::GW_ENVIRONMENTS[ $this->gateway_environment ]['entry_point_url']
				: self::GW_ENVIRONMENTS['TEST']['entry_point_url'];
			$param    = ( null !== $param ) ? "/$param" : '';
			return $base_url . $request . $param;
		}

		/**
		 * Gets the URL of the client.js library
		 *
		 * @return string
		 */
		protected function get_client_js_url() {
			return array_key_exists( $this->gateway_environment, self::GW_ENVIRONMENTS )
				? self::GW_ENVIRONMENTS[ $this->gateway_environment ]['client_js_url']
				: self::GW_ENVIRONMENTS['TEST']['client_js_url'];
		}

		/**
		 * Gets the value of an HTTP variable based on the requested method or the default value if the variable does not exist
		 *
		 * @param string $field HTTP POST/GET variable.
		 * @param string $default Default value.
		 * @param string $method Request method.
		 *
		 * @return string
		 */
		protected function get_http_var( $field, $default = '', $method = '' ) {
			// @codingStandardsIgnoreStart
			if ( empty( $method ) ) {
				$method = $_SERVER['REQUEST_METHOD'];
			}
			switch ( $method ) {
				case 'GET':
					return array_key_exists( $field, $_GET )
						? $_GET[ $field ]
						: $default;
				case 'POST':
					return array_key_exists( $field, $_POST )
						? $_POST[ $field ]
						: $default;
				default:
					return $default;
			}
			// @codingStandardsIgnoreEnd
		}

		/**
		 * Gets the body of the HTTP request
		 *
		 * @return string|false
		 */
		protected function get_request_body() {
			return file_get_contents('php://input');
		}

		/**
		 * Gets currency ISO 4217 code
		 *
		 * @param string $currency_code Currency 4217 code.
		 * @param string $default_code Default currency code.
		 *
		 * @return string
		 */
		protected function get_currency_iso_code( $currency_code, $default_code = '826' ) {
			$result    = $default_code;
			$iso_codes = [
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
			if ( array_key_exists( $currency_code, $iso_codes ) ) {
				$result = $iso_codes[ $currency_code ];
			}
			return $result;
		}

		/**
		 * Gets country ISO 3166-1 code
		 *
		 * @param string $country_code Country 3166-1 code.
		 *
		 * @return string
		 */
		protected function get_country_iso_code( $country_code ) {
			$result    = '';
			$iso_codes = [
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
			if ( array_key_exists( $country_code, $iso_codes ) ) {
				$result = $iso_codes[ $country_code ];
			}
			return $result;
		}

		/**
		 * Gets an element from array
		 *
		 * @param array  $arr The array.
		 * @param string $element The element.
		 * @param mixed  $default The default value if the element does not exist.
		 *
		 * @return mixed
		 */
		protected function get_array_element( $arr, $element, $default ) {
			return ( is_array( $arr ) && array_key_exists( $element, $arr ) )
				? $arr[ $element ]
				: $default;
		}

		/**
		 * Gets order ID by access token.
		 *
		 * @param string $access_token access token.
		 *
		 * @return string
		 */
		protected function get_order_by_access_token( $access_token ) {
			$this->logger->LogMessage( "Retrieving order id by access token: '{$access_token}'..." );

			global $wpdb;
			// @codingStandardsIgnoreLine
			$result = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='access_token' AND meta_value=%s",
					$access_token
				)
			);

			$this->logger->LogMessage( "Order id by access token retrieved: {$result}" );

			return $result;
		}

		/**
		 * Gets the payment status based on the transaction status code
		 *
		 * @param int $status_code status code.
		 *
		 * @return int
		 */
		protected function get_payment_status( $status_code ) {
			switch ( $status_code ) {
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

			$this->logger->LogMessage("Payment status from status code. Status code: {$status_code}. Payment status: {$result}");
			return $result;
		}

		/**
		 * Gets the module name
		 *
		 * @return string
		 */
		protected function get_module_name() {
			return array_key_exists( 'Name', $this->plugin_data )
				? $this->plugin_data['Name']
				: '';
		}

		/**
		 * Gets the module installed version
		 *
		 * @return string
		 */
		protected function get_module_installed_version() {
			return array_key_exists( 'Version', $this->plugin_data )
				? $this->plugin_data['Version']
				: '';
		}

		/**
		 * Gets the WordPress version
		 *
		 * @return string
		 */
		protected function get_wp_version() {
			return get_bloginfo( 'version' );
		}

		/**
		 * Gets WooCommerce version
		 *
		 * @return string
		 */
		protected function get_wc_version() {
			return WC()->version;
		}

		/**
		 * Gets shopping cart platform URL
		 *
		 * @return string
		 */
		protected function get_cart_url() {
			return site_url();
		}

		/**
		 * Gets shopping cart platform name
		 *
		 * @return string
		 */
		protected function get_cart_platform_name() {
			return self::PLATFORM_NAME;
		}

		/**
		 * Gets gateway name
		 *
		 * @return string
		 */
		protected function get_gateway_name() {
			return self::GATEWAY_NAME;
		}

		/**
		 * Gets the environment name
		 *
		 * @return string
		 */
		protected function get_environment_name() {
			$gwEnv = $this->gateway_environment;
			return array_key_exists( $gwEnv, self::GW_ENVIRONMENTS )
				? self::GW_ENVIRONMENTS[ $gwEnv ]['name']
				: '';
		}

		/**
		 * Gets the PHP version
		 *
		 * @return string
		 */
		protected function get_php_version() {
			return phpversion();
		}

		/**
		 * Gets the file checksums
		 *
		 * @return array
		 */
		protected function get_file_checksums() {
			$result    = [];
			$root_path = realpath( __DIR__ . '/../../../..' );
			$file_list = $this->get_http_var( 'data', '', 'POST' );
			if ( is_array( $file_list ) ) {
				foreach ( $file_list as $key => $file ) {
					$filename       = $root_path . '/' . $file;
					$result[ $key ] = is_file( $filename )
						? sha1_file( $filename )
						: null;
				}
			}
			return $result;
		}

		/**
		 * Builds the HTTP headers for the API requests
		 *
		 * @return array An associative array containing the HTTP headers
		 */
		protected function build_request_headers() {
			return [
				'Cache-Control: no-cache',
				'Authorization: Bearer ' . $this->gateway_jwt,
				'Content-Type: application/json',
			];
		}

		/**
		 * Builds the fields for the API requests by replacing the null values with empty strings
		 *
		 * @param array $fields An array containing the fields for the API request.
		 *
		 * @return array An array containing the fields for the API request
		 */
		protected function build_request_params( $fields ) {
			return array_map(
				function ( $value ) {
					return null === $value ? '' : $value;
				},
				$fields
			);
		}

		/**
		 * Builds the fields for the access tokens payment request
		 *
		 * @param WC_Order $order WooCommerce order object.
		 *
		 * @return array An associative array containing the fields for the request
		 */
		protected function build_request_access_tokens_payment( $order ) {
			return [
				'gatewayUsername'  => $this->gateway_username,
				'currencyCode'     => $this->get_currency_iso_code( get_woocommerce_currency() ),
				'amount'           => (string) ( $order->get_total() * 100 ),
				'transactionType'  => $this->gateway_transaction_type,
				'orderId'          => (string) $order->get_id(),
				'orderDescription' => $this->order_prefix . $order->get_id(),
				'userEmailAddress' => $order->get_billing_email(),
				'userPhoneNumber'  => $order->get_billing_phone(),
				// @codingStandardsIgnoreLine
				'userIpAddress'   => $_SERVER['REMOTE_ADDR'],
				'userAddress1'    => $order->get_billing_address_1(),
				'userAddress2'    => $order->get_billing_address_2(),
				'userCity'        => $order->get_billing_city(),
				'userState'       => $order->get_billing_state(),
				'userPostcode'    => $order->get_billing_postcode(),
				'userCountryCode' => $this->get_country_iso_code( $order->get_billing_country() ),
				'webHookUrl'      => WC()->api_request_url( get_class( $this ), is_ssl() ),
				'metaData'        => $this->build_meta_data(),
			];
		}

		/**
		 * Builds the fields for the access tokens refund request
		 *
		 * @param float  $order_id WooCommerce order object.
		 * @param float  $amount Amount of the refund.
		 * @param string $cross_ref Cross-reference ID of the parent transaction.
		 *
		 * @return array An associative array containing the fields for the request
		 */
		protected function build_request_access_tokens_refund( $order_id, $amount, $cross_ref ) {
			return [
				'gatewayUsername'  => $this->gateway_username,
				'currencyCode'     => $this->get_currency_iso_code( get_woocommerce_currency() ),
				'amount'           => (string) ( $amount * 100 ),
				'transactionType'  => 'REFUND',
				'orderId'          => (string) $order_id,
				'orderDescription' => $this->order_prefix . $order_id,
				'newTransaction'   => false,
				'crossReference'   => $cross_ref,
				'metaData'         => $this->build_meta_data(),
			];
		}

		/**
		 * Builds the meta data
		 *
		 * @return array An associative array containing the meta data
		 */
		protected function build_meta_data() {
			return [
				'shoppingCartUrl'      => $this->get_cart_url(),
				'shoppingCartPlatform' => $this->get_cart_platform_name(),
				'shoppingCartVersion'  => $this->get_wc_version(),
				'shoppingCartGateway'  => $this->get_gateway_name(),
				'pluginVersion'        => $this->get_module_installed_version(),
			];
		}

		/**
		 * Builds the fields for the refund request
		 *
		 * @param string $cross_ref Cross-reference ID of the parent transaction.
		 *
		 * @return array An associative array containing the fields for the request
		 */
		protected function build_request_refund( $cross_ref ) {
			return [
				'crossReference' => $cross_ref,
			];
		}

		/**
		 * Performs cURL requests
		 *
		 * @param array  $data cURL data.
		 * @param mixed  $response the result or false on failure.
		 * @param mixed  $info last transfer information.
		 * @param string $err_msg last transfer error message.
		 *
		 * @return int cURL error number or 0 if no error occurred
		 */
		protected function perform_transaction( $data, &$response, &$info = [], &$err_msg = '' ) {

			$url = $data['url'];
			$callId = mt_rand(0, 10000);
			$method = $data['method'];
			$this->logger->LogMessage("Perform transaction start ({$callId}). Url: '{$method} {$url}'...");

			if ( ! function_exists( 'curl_version' ) ) {
				$err_no   = 2; // CURLE_FAILED_INIT
				$err_msg  = 'cURL is not enabled';
				$info     = [];
				$response = '';

				$this->logger->LogMessage("cURL is not enabled", 'Error');
			} else {
				$ch = curl_init();
				curl_setopt( $ch, CURLOPT_HTTPHEADER, $data['headers'] );
				curl_setopt( $ch, CURLOPT_URL, $data['url'] );
				curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
				curl_setopt( $ch, CURLOPT_ENCODING, 'UTF-8' );
				curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
				curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 30 );
				curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
				if ( self::API_METHOD_POST === $method ) {
					curl_setopt( $ch, CURLOPT_POST, true );
					curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $data['post_fields'] ) );
				} else {
					curl_setopt( $ch, CURLOPT_POST, false );
				}
				$response = curl_exec( $ch );
				$err_no   = curl_errno( $ch );
				$err_msg  = curl_error( $ch );
				$info     = curl_getinfo( $ch );
				curl_close( $ch );
				
				$http_code = $info['http_code'];
				$this->logger->LogMessage("Perform transaction end ({$callId}). cURL Error no: {$err_no}. Status: {$http_code}. Response: {$response}");
			}
			return $err_no;
		}

		/**
		 * Processes the request for plugin information
		 */
		protected function process_info_request() {
			$this->logger->EmitEvent("Info request");

			$info = [
				'Module Name'              => $this->get_module_name(),
				'Module Installed Version' => $this->get_module_installed_version(),
			];

			if ( 'true' === $this->get_http_var( 'extended_info', '' ) ) {
				$extended_info = [
					'WordPress Version'   => $this->get_wp_version(),
					'WooCommerce Version' => $this->get_wc_version(),
					'PHP Version'         => $this->get_php_version(),
					'Environment'         => $this->get_environment_name(),
				];

				$info = array_merge( $info, $extended_info );
			}

			$this->output_info( $info );
		}

		/**
		 * Processes the request for file checksums
		 */
		protected function process_checksums_request() {
			$this->logger->EmitEvent("Checksum request");
			$info = [
				'Checksums' => $this->get_file_checksums(),
			];

			$this->output_info( $info );
		}

		/**
		 * Retrieves the plugin data
		 */
		protected function retrieve_plugin_data() {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				if ( is_file( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
			}

			if ( function_exists( 'get_plugin_data' ) ) {
				$ps_plugin_file    = '/paymentsense-remote-payments-for-woocommerce/paymentsense-remote-payments-for-woocommerce.php';
				$this->plugin_data = get_plugin_data( WP_PLUGIN_DIR . $ps_plugin_file );
			}
		}

		/**
		 * Outputs plugin information
		 *
		 * @param array $info Module information.
		 */
		protected function output_info( $info ) {
			$this->logger->LogMessage("Outputting info...");

			$output       = $this->get_http_var( 'output', 'text', 'GET' );
			$content_type = array_key_exists( $output, $this->content_types )
				? $this->content_types[ $output ]
				: self::MINFO_TYPE_TEXT_PLAIN;

			switch ( $content_type ) {
				case self::MINFO_TYPE_APPLICATION_JSON:
					$body = wp_json_encode( $info );
					break;
				case self::MINFO_TYPE_TEXT_PLAIN:
				default:
					$body = $this->convert_array_to_string( $info );
					break;
			}

			// @codingStandardsIgnoreStart
			@header( 'Cache-Control: max-age=0, must-revalidate, no-cache, no-store', true );
			@header( 'Pragma: no-cache', true );
			@header( 'Content-Type: ' . $content_type, true );
			echo $body;
			// @codingStandardsIgnoreEnd

			$this->logger->LogMessage("Info output complete. Body: {$body}");
			exit;
		}

		/**
		 * Shows the payment form
		 *
		 * @param int $order_id WooCommerce Order ID.
		 */
		protected function show_payment_form( $order_id ) {
			$this->logger->LogMessage( "Show payment form start. Order id: {$order_id} ..." );

			$order = new WC_Order( $order_id );
			$order_old_status = $order->get_status();

			$this->logger->LogMessage( "Order with id {$order_id} current status: '{$order_old_status}'." );

			// Preventive measure just in case
			if ( $order_old_status === "processing" || $order_old_status === "completed" ) {
				$this->logger->LogMessage("Order with id {$order_id} already payed!", 'Warning');
				$location = $order->get_checkout_order_received_url();
				$this->logger->LogMessage( "Redirecting to: {$location }..." );
				wp_safe_redirect( $location );
				exit;
			}

			
			// Yet another safety mechanism, for cases where webhooks are slow (and the guard above did not work)
			// or in rare cases where we hit this concurency window
			$use_old_token = false;
			$old_access_token = get_post_meta( $order_id, 'access_token', true );
			if ( !empty( $old_access_token ) ) {

				$this->logger->LogMessage( "Order with id {$order_id} has token already $old_access_token}'." );
				// Why do we poll? We want to minimize the risk of hitting the window where the order is not yet payed,
				// but pay button has been pressed already.
				$old_access_token_status = $this->poll_connecte_payment_status_until_in_final_state( $old_access_token, 5 );

				// Already paid, just redirect
				if ( $old_access_token_status == self::TRX_STATUS_CODE_SUCCESS ) {
					$location = $order->get_checkout_order_received_url();
					wp_safe_redirect( $location );
					exit;
				}
				else if ( $old_access_token_status == self::TRX_STATUS_CODE_DECLINED ) {
					// Old token declined, we need a new one
					$this->logger->LogMessage( "Token {$old_access_token} status is declined. New token will be requested..." );
				}
				else if ( $old_access_token_status == self::TRX_STATUS_CODE_AUTHORIZING ) {
					// Old token in authorizing mode, means payment page was re-loaded while 3DS was in progress
					$this->logger->LogMessage( "Token {$old_access_token} status is authorizing. New token will be requested, old one will be revoked..." );
					$this->revoke_connecte_payment_status ($old_access_token );
				}
				else {
					// Old token looks good, just page reload, still usuable (in theory)
					$use_old_token = true;
					$this->logger->LogMessage( "Token {$old_access_token} status is OK. We will re-use it." );
				}
			}
			// end

			$order->update_status(
				'pending',
				__( 'Pending payment', 'woocommerce-paymentsense-remote-payments' )
			);

			$this->logger->LogMessage( "Order with id {$order_id} status updated to 'pending'." );

			if ( $use_old_token ) {
				$access_token = $old_access_token;
			}
			else {
				$http_code    = -1;
				$access_token = null;

				$data = [
					'url'         => $this->get_api_endpoint_url( self::API_REQUEST_ACCESS_TOKENS ),
					'method'      => self::API_METHOD_POST,
					'headers'     => $this->build_request_headers(),
					'post_fields' => $this->build_request_params( $this->build_request_access_tokens_payment( $order ) ),
				];

				$curl_err_no = $this->perform_transaction( $data, $response_json, $info, $curl_err_msg );
				if ( 0 === $curl_err_no ) {
					$http_code = $info['http_code'];
					if ( WP_Http::OK === $http_code ) {
						$response     = json_decode( $response_json, true );
						$access_token = $this->get_array_element( $response, 'id', null );
					}
				}
			}

			if ( $access_token ) {
				$order_id_new = (int) $order->get_id();
				update_post_meta( $order_id_new , 'access_token', $access_token );
				$this->logger->LogMessage("Post meta updated. Order id: {$order_id_new}. Token: '{$access_token}'");

				$this->show_output(
					'paymentsense-remote-payments-payment-form.php',
					[
						'description'   => $this->description,
						'amount'        => $data['post_fields']['amount'],
						'currency_code' => $data['post_fields']['currencyCode'],
						'access_token'  => $access_token,
						'return_url'    => WC()->api_request_url( get_class( $this ), is_ssl() ),
						'client_js_url' => $this->get_client_js_url(),
						'css_url'       => plugins_url( self::STANDARD_CSS, __DIR__ ),
					]
				);
			} else {
				$this->logger->LogMessage("Access token could not be received!", 'Error');

				$order_note = ( 0 === $curl_err_no )
					? sprintf(
					// Translators: %1$s - HTTP status code, %2$s - payment gateway message.
						__( 'An error has occurred. (HTTP Status Code: %1$s, Payment Gateway Message: %2$s). ', 'woocommerce-paymentsense-remote-payments' ),
						$http_code,
						$response
					)
					: sprintf(
					// Translators: %1$s - cURL error number, %2$s - cURL error message.
						__( 'An error has occurred. (cURL Error No: %1$s, cURL Error Message: %2$s). ', 'woocommerce-paymentsense-remote-payments' ),
						$curl_err_no,
						$curl_err_msg
					);

				$order->add_order_note( $order_note );
				$this->logger->LogMessage("Order note updated with note: {$order_note}", 'Error');

				$message = __( 'An unexpected error has occurred. ', 'woocommerce-paymentsense-remote-payments' ) . ' ' .
					sprintf(
					// Translators: #%s - order number.
						__( 'Please contact customer support quoting your order #%s.', 'woocommerce-paymentsense-remote-payments' ),
						$order_id
					);

				$extra_params = [ 'cancel_url' => $order->get_cancel_order_url() ];

				$this->show_message( $message, $extra_params );
			}

			$this->logger->LogMessage("Show payment form end");
		}

		/**
		 * Shows the bad request page
		 */
		protected function show_bad_request_page() {
			$this->logger->LogMessage( "Show bad request page..." );

			$protocol = $this->get_server_protocol();
			header( "$protocol 400 Bad Request" );
			header( 'Content-Type: text/html; charset=UTF-8' );
			$this->show_output(
				'paymentsense-remote-payments-error.php',
				[
					'title'   => __(
						'400 Bad Request',
						'woocommerce-paymentsense-remote-payments'
					),
					'message' => __(
						'The attempted request to this URL is malformed or illegal. If you believe you are receiving this message in error, please contact customer support.',
						'woocommerce-paymentsense-remote-payments'
					),
				]
			);
			
			$this->logger->LogMessage( "Show bad request page." );
			exit;
		}

		/**
		 * Sends an HTTP header with HTTP status code 200 (OK)
		 */
		protected function send_ok_header() {
			$this->logger->LogMessage("Sending OK headers...");

			$protocol = $this->get_server_protocol();
			header( "$protocol 200 OK" );

			$this->logger->LogMessage( "Sending OK headers complete" );

			exit;
		}

		/**
		 * Gets the server protocol
		 *
		 * @return string
		 */
		protected function get_server_protocol() {
			// @codingStandardsIgnoreLine
			$result = $_SERVER['SERVER_PROTOCOL'];
			if ( ! in_array( $result, [ 'HTTP/1.1', 'HTTP/2', 'HTTP/2.0' ], true ) ) {
				$result = 'HTTP/1.0';
			}

			$this->logger->LogMessage( "Server protocol: {$result}" );
			return $result;
		}

		/**
		 * Outputs an inline message
		 *
		 * @param string $message The message.
		 * @param array  $extra_params Extra parameters.
		 */
		protected function show_message( $message, $extra_params = [] ) {
			$this->logger->LogMessage("Show message. Message: {$message}..." );

			$params = [
				'message' => $message,
			];
			if ( ! empty( $extra_params ) ) {
				$params = array_merge( $params, $extra_params );
			}
			$this->show_output(
				'paymentsense-remote-payments-message.php',
				$params
			);

			$this->logger->LogMessage("Show message complete." );
		}

		/**
		 * Outputs an error message on a dedicated error page.
		 *
		 * @param string $message The message.
		 */
		protected function show_error( $message ) {
			$this->logger->LogMessage( "Showing error: {$message}", 'Error' );

			$this->show_output(
				'paymentsense-remote-payments-error.php',
				[
					'title'   => __( 'An unexpected error has occurred. ', 'woocommerce-paymentsense-remote-payments' ),
					'message' => $message,
				]
			);
			exit;
		}

		/**
		 * Generates output using a template
		 *
		 * @param string $template_name Template filename.
		 * @param array  $args Template arguments.
		 */
		protected function show_output( $template_name, $args = [] ) {
			$this->logger->LogMessage( "Showing output. Template name: {$template_name}..." );

			$templates_path = dirname( plugin_dir_path( __FILE__ ) ) . '/templates/';
			wc_get_template( $template_name, $args, '', $templates_path );

			$this->logger->LogMessage( "Show output complete." );
		}

		/**
		 * Converts an array to string
		 *
		 * @param array  $arr An associative array.
		 * @param string $indent Indentation.
		 *
		 * @return string
		 */
		protected function convert_array_to_string( $arr, $indent = '' ) {
			$result         = '';
			$indent_pattern = '  ';
			foreach ( $arr as $key => $value ) {
				if ( '' !== $result ) {
					$result .= PHP_EOL;
				}
				if ( is_array( $value ) ) {
					$value = PHP_EOL . $this->convert_array_to_string( $value, $indent . $indent_pattern );
				}
				$result .= $indent . $key . ': ' . $value;
			}
			return $result;
		}

		private function get_connecte_payment_status($access_token, &$error_message = '') {
			$data = [
				'url'     => $this->get_api_endpoint_url( self::API_REQUEST_PAYMENTS, $access_token ),
				'method'  => self::API_METHOD_GET,
				'headers' => $this->build_request_headers(),
			];

			$curl_err_no = $this->perform_transaction( $data, $response, $info, $error_message );
			if ( 0 === $curl_err_no ) {
				$http_code = $info['http_code'];
				if ( WP_Http::OK === $http_code ) {
					$response_object  = json_decode( $response, true );
					return $response_object;
				}
			}

			return null;
		}

		private function poll_connecte_payment_status_until_in_final_state( $access_token, $max_duration_seconds ) {

			$duration = 0;
			
			$this->logger->LogMessage( "Start polling for success status..." );

			while ($duration < $max_duration_seconds) {
				$payment = $this->get_connecte_payment_status( $access_token );

				if ($payment !== null) {
					$status = $this->get_array_element( $payment, 'statusCode', null );

					// Already payed, just redirect
					if ( $status == self::TRX_STATUS_CODE_SUCCESS || 
						 $status == self::TRX_STATUS_CODE_AUTHORIZING ||     
					     $status == self::TRX_STATUS_CODE_DECLINED ) {
						$this->logger->LogMessage( "Status is final: {$status}!" );
						return $status;
					}
				}

				sleep( 1 );
				$duration += 1;
			}
			
			$this->logger->LogMessage( "Timeout of {$max_duration_seconds} reached while waiting for success!", "Error" );
			return self::TRX_STATUS_CODE_NOT_AVAILABLE;
		}

		private function revoke_connecte_payment_status($access_token, &$error_message = '') {
			$data = [
				'url'     => $this->get_api_endpoint_url( self::API_REQUEST_ACCESS_TOKENS, $access_token ) . "/revoke",
				'method'  => self::API_METHOD_POST,
				'headers' => $this->build_request_headers(),
			];

			$curl_err_no = $this->perform_transaction( $data, $response, $info, $error_message );
			if ( 0 === $curl_err_no ) {
				$http_code = $info['http_code'];
				return WP_Http::OK === $http_code;
			}

			return false;
		}
	}
}
