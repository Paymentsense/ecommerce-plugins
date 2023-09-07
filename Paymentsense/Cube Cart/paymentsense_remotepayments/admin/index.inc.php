<?php
/**
 * Paymentsense - Remote Payments Gateway
 *
 * Web:   http://www.paymentsense.com
 * Email:  devsupport@paymentsense.com
 * License:  GPL-3.0 http://opensource.org/licenses/GPL-3.0
 */
if (!defined('CC_INI_SET')) die('Access Denied');
$module = new Module(__FILE__, $_GET['module'], 'admin/index.tpl', true);
$page_content = $module->display();
