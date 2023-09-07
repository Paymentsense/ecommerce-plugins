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
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html lang="en">
<head>
	<title><?php echo esc_html( $title ); ?></title>
</head>
<body>
<h1><?php echo esc_html( $title ); ?></h1>
<p><?php echo esc_html( $message ); ?></p>
</body>
</html>
