<?php
/**
 * Paymentsense Remote Payments Plugin for VirtueMart 3
 * Version: 1.0.0
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @version     1.0.0
 * @author      Paymentsense
 * @copyright   2021 Paymentsense Ltd.
 * @license     https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('_JEXEC') or die('Restricted access');

vmJsApi::css( 'paymentsense_rp','plugins/vmpayment/paymentsense_rp/paymentsense_rp/assets/css/');
?>
<p>Payment failed with message '<b><?php echo $viewData['message'] ?></b>'. Please check your card details and try again.<p>
<p><a href="<?php echo $viewData['checkout_url'] ?>">Click here to return to the checkout</a>.</p>
