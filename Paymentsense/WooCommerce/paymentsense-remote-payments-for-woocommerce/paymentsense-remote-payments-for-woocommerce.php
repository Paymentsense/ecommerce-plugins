<?php
/**
 * Paymentsense Remote Payments for WooCommerce.
 *
 * Plugin Name:          Paymentsense Remote Payments for WooCommerce
 * Description:          Extends WooCommerce by taking payments via Paymentsense.
 * Version:              1.0.14
 * Author:               Paymentsense
 * Author URI:           http://www.paymentsense.co.uk/
 * License:              GNU General Public License v3.0
 * License URI:          http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:          woocommerce-paymentsense-remote-payments
 * Requires at least:    5.0
 * Tested up to:         5.9
 * WC requires at least: 4.0
 * WC tested up to:      6.3.1
 *
 * @package Paymentsense_Remote_Payments_For_WooCommerce
 * @author  Paymentsense
 * @link    http://www.paymentsense.co.uk/
 */

/**
 * Exit if accessed directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

load_plugin_textdomain(
	'woocommerce-paymentsense-remote-payments',
	false,
	basename( __DIR__ ) . DIRECTORY_SEPARATOR . 'languages'
);

/**
 * Hooks Paymentsense Remote Payments on the plugins_loaded action if WooCommerce is active
 */
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {

	if ( ! function_exists( 'woocommerce_paymentsense_remote_payments_init' ) ) {
		/**
		 * Paymentsense Remote Payments Init function
		 */
		function woocommerce_paymentsense_remote_payments_init() {
			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				return;
			}

			require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-paymentsense-remote-payments.php';

			if ( ! function_exists( 'woocommerce_add_paymentsense_remote_payments' ) ) {
				/**
				 * Adds Paymentsense Remote Payments payment into the WooCommerce payment gateways
				 *
				 * @param array $methods WooCommerce payment gateways.
				 *
				 * @return array WooCommerce payment gateways
				 */
				function woocommerce_add_paymentsense_remote_payments( $methods ) {
					array_push( $methods, 'WC_Paymentsense_Remote_Payments' );
					return $methods;
				}
				add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_paymentsense_remote_payments' );
			}
		}
	}
	add_action( 'plugins_loaded', 'woocommerce_paymentsense_remote_payments_init', 0 );
}
