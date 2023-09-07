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

if ( ! empty( $error_message ) ) {
	echo '<div class="notice notice-error"><p>' . esc_html( $error_message ) . '</p></div>';
}

echo '<h2>' . esc_html( $title ) . '</h2>';
echo wp_kses_post( wpautop( $description ) );
?>
<table class="form-table">
	<?php $this_->generate_settings_html(); ?>
</table>
