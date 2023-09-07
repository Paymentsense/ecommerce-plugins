<?php
/**
 * Copyright (C) 2021 Paymentsense Ltd.
 *
 * This program is free software: you can redistribute it and/or modify it under the terms
 * of the AFL Academic Free License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the AFL Academic Free License for more details. You should have received a copy of the
 * AFL Academic Free License along with this program. If not, see <http://opensource.org/licenses/AFL-3.0/>.
 *
 *  @author     Paymentsense <devsupport@paymentsense.com>
 *  @copyright  2021 Paymentsense Ltd.
 *  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

require_once _PS_MODULE_DIR_ . 'paymentsenserp/controllers/front/base/report.php';

/**
 * Module Information Front-End Controller
 *
 * Handles the request for plugin information
 */
class PaymentsenserpInfoModuleFrontController extends PaymentsenserpReportAbstractController
{
    /**
     * @var Paymentsenserp
     */
    public $module;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();
        $this->processInfoRequest();
    }

    /**
     * Processes the request for plugin information
     */
    protected function processInfoRequest()
    {
        $info = [
            'Module Name'              => $this->module->getModuleInternalName(),
            'Module Installed Version' => $this->module->getModuleInstalledVersion(),
        ];
        if ('true' === Tools::getValue('extended_info')) {
            $extendedInfo = [
                'PrestaShop Version' => $this->module->getPsVersion(),
                'PHP Version'        => $this->getPhpVersion(),
            ];
            $info = array_merge($info, $extendedInfo);
        }
        $this->outputInfo($info);
    }

    /**
     * Gets the PHP version
     *
     * @return string
     */
    protected function getPhpVersion()
    {
        return phpversion();
    }
}
