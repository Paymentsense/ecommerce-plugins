<?php
/**
 * Dojo Logger
 *
 * @package	 Paymentsense_Remote_Payments_For_WooCommerce
 * @subpackage Paymentsense_Remote_Payments_For_WooCommerce/includes
 * @author	  Paymentsense
 * @link		 http://www.paymentsense.co.uk/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'WC_Paymentsense_Logger' ) ) {
	require_once __DIR__ . '/../vendor/autoload.php';

	/**
	 * Dojo logger
	 *
	 */
	class WC_Paymentsense_Logger {
		private $enabled;
		private $logger;
		private $custom_properties;

		public function __construct($plugin_version, $jwt_username, $instrumentation_key) {
			try {
				$this->enabled = !self::IsNullOrEmptyString($instrumentation_key);

				if ( !$this->enabled ) {
					return;
				}
	
				// \GuzzleHttp\Client needs to be deleted manually, otherwise due to bug in the code
				// will be used no matter what. This client is very slow.
				$this->logger = new \ApplicationInsights\Telemetry_Client();
	
				$context = $this->logger->getContext();
				$context->setInstrumentationKey($instrumentation_key);
	
				$wp_session_token = wp_get_session_token();
				$wp_session_id = session_id();
				$user_id = get_current_user_id();
	
				$context->getSessionContext()->setId($wp_session_token);
				$context->getUserContext()->setId($user_id);
				$context->getApplicationContext()->setVer($plugin_version);
				
				global $wp;
				$current_url = add_query_arg( $wp->query_vars, home_url( $wp->request ) );
				
				$this->custom_properties = ['jwt_Username' => $jwt_username, 'url' => $current_url ];
			} catch ( Exception $exception ) { }
		}

		private static function IsNullOrEmptyString ( $str ) {
			return ( $str === null || trim( $str ) === '' );
		}

		public function EmitEvent( $event_name ) {
			try {
				if ( !$this->enabled ) {
					return;
				}

				$this->logger->trackEvent( $event_name, $this->custom_properties );
			} catch ( Exception $exception ) { }
		}
		
		public function LogMessage( $message, $severity = 'Information' ) {
			try {
				if ( !$this->enabled ) {
					return;
				}
	
				$this->logger->trackMessage( $message, $severity, $this->custom_properties );
			} catch ( Exception $exception ) { }
		}

		public function LogException( $exception ) {
			try {
				if ( !$this->enabled ) {
					return;
				}
	
				$this->logger->trackException( $exception, $this->custom_properties);
           		$this->logger->flush();
			} catch ( Exception $exception ) { }
		}

		function __destruct() {
			try {
				if ( !$this->enabled ) {
					return;
				}
	
				$this->logger->flush();
			} catch ( Exception $exception ) { }
		}
	}
}