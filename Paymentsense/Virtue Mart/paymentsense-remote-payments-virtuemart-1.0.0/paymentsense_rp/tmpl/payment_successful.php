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
<p>Your payment was successful. Your order number is <b><?php echo $viewData['order_number'] ?></b>.</p>
