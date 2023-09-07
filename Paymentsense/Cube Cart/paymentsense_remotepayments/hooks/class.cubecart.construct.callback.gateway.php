<?php
/**
 * To set $plugin to true for module diagnostics.
 *
 * Web:   http://www.paymentsense.com
 * Email:  devsupport@paymentsense.com
 * License:  GPL-3.0 http://opensource.org/licenses/GPL-3.0
 */

if (isset($_GET['module']) && $_GET['module'] == 'paymentsense_remotepayments') $plugin = true;