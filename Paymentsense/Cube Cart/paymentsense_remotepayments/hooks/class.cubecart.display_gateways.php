<?php
/**
 * To add logo when displaying gateway.
 *
 * Web:   http://www.paymentsense.com
 * Email:  devsupport@paymentsense.com
 * License:  GPL-3.0 http://opensource.org/licenses/GPL-3.0
 */
$module_config = $GLOBALS['config']->get('paymentsense_remotepayments');
if ($module_config['status']) {
    $logo = CC_ROOT_REL.'modules/plugins/paymentsense_remotepayments/admin/logo.png';
	$desc = "<img alt='paymentsense' src='$logo'/>" . $module_config['desc'] ?? '';
	if (isset($_POST['gateway']) || (isset($name) && !empty($name))) {
		$base_folder = isset($_POST['gateway']) ? $_POST['gateway'] : $name;
		if($base_folder=='paymentsense_remotepayments') {
		    $gateways = [];
			$gateways[0]	= array(
				'plugin'	=> true,
				'base_folder' => 'paymentsense_remotepayments',
				'folder'	=> 'paymentsense_remotepayments',
				'desc'		=> $desc,
			);
		}
	} else {
		$gateways[(int) $module_config['position']]	= array(
			'plugin'	=> true,
			'base_folder' => 'paymentsense_remotepayments',
			'folder'	=> 'paymentsense_remotepayments',
			'desc'		=> $desc,
			'default'	=> true
		);
	}
}
