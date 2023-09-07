<?php

/**
 * Handler class for callback requests to the module.
 *
 * @license GNU Public License V2.0
 */
require_once 'includes/modules/payment/paymentsense/diagnostics.php';
$diagnostics = new diagnostics;
$diagnostics->executeAction();
