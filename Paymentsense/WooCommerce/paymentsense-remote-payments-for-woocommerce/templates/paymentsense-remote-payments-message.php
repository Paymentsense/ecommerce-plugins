<?php
/**
 * Paymentsense Remote Payments for WooCommerce Template
 *
 * @package    Paymentsense_Remote_Payments_For_WooCommerce
 * @subpackage Paymentsense_Remote_Payments_For_WooCommerce/templates
 * @author     Paymentsense
 * @link       http://www.paymentsense.co.uk/
 */

/**
 * Exit if accessed directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
?>

<ul class="woocommerce-error"><li><?php echo esc_html( $message ); ?></li></ul>
<?php if ( isset( $cancel_url ) ) { ?>
	<p>
		<a class="button cancel" href="<?php echo esc_url( $cancel_url ); ?>"><?php esc_html_e( 'Cancel order & restore cart', 'woocommerce-paymentsense-remote-payments' ); ?></a>
	</p>
	<?php
}
