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
 * File Checksums Front-End Controller
 *
 * Handles the request for file checksums
 */
class PaymentsenserpChecksumsModuleFrontController extends PaymentsenserpReportAbstractController
{
    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();
        $this->processChecksumsRequest();
    }

    /**
     * Processes the request for file checksums
     */
    protected function processChecksumsRequest()
    {
        $info = [
            'Checksums' => $this->getFileChecksums(),
        ];
        $this->outputInfo($info);
    }

    /**
     * Gets the file checksums
     *
     * @return array
     */
    protected function getFileChecksums()
    {
        $result = [];
        $rootPath = _PS_ROOT_DIR_;
        $fileList = Tools::getValue('data');
        if (is_array($fileList)) {
            foreach ($fileList as $key => $file) {
                $filename = $rootPath . '/' . $file;
                $result[$key] = is_file($filename)
                    ? sha1_file($filename)
                    : null;
            }
        }
        return $result;
    }
}
