<?php
/*
 * Copyright (C) 2022 Paymentsense Ltd.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 3
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author      Paymentsense
 * @copyright   2022 Paymentsense Ltd.
 * @license     https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Paymentsense\RemotePayments\Model\Traits;

use Paymentsense\RemotePayments\Model\Connect\GatewayEnvironment;

/**
 * Base method with module information
 */
trait BaseInfoMethod
{
    use BaseMethod;

    /**
     * @var \Magento\Framework\App\ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * Gets module information
     *
     * @param boolean $extendedInfoRequest
     *
     * @return array
     */
    public function getInfo($extendedInfoRequest)
    {
        $info = [
            'Module Name'              => $this->getModuleName(),
            'Module Installed Version' => $this->getModuleInstalledVersion(),
        ];
        if ($extendedInfoRequest) {
            $extendedInfo = [
                'Magento Version' => $this->getMagentoVersion(),
                'PHP Version'     => $this->getPHPVersion(),
                'Environment'     => $this->getEnvironmentName()
            ];
            $info = array_merge($info, $extendedInfo);
        }
        return $info;
    }

    /**
     * Gets file checksums
     *
     * @param array  $fileList
     * @param string $scope
     *
     * @return array
     */
    public function getChecksums($fileList, $scope)
    {
        return [
            'Checksums' => $this->getFileChecksums($fileList, $scope)
        ];
    }

    /**
     * Converts an array to string
     *
     * @param array  $arr    An associative array
     * @param string $indent Indentation
     *
     * @return string
     */
    public function convertArrayToString($arr, $indent = '')
    {
        $result       = '';
        $identPattern = '  ';
        foreach ($arr as $key => $value) {
            if ('' !== $result) {
                $result .= PHP_EOL;
            }

            if (is_array($value)) {
                $value = PHP_EOL . $this->convertArrayToString($value, $indent . $identPattern);
            }

            $result .= $indent . $key . ': ' . $value;
        }
        return $result;
    }

    /**
     * Gets module name
     *
     * @return string
     */
    private function getModuleName()
    {
        return $this->getConfigHelper()->getModuleName();
    }

    /**
     * Gets module installed version
     *
     * @return string
     */
    private function getModuleInstalledVersion()
    {
        return $this->getConfigHelper()->getModuleInstalledVersion();
    }

    /**
     * Gets Magento version
     *
     * @return string
     */
    private function getMagentoVersion()
    {
        return $this->productMetadata->getVersion();
    }

    /**
     * Gets PHP version
     *
     * @return string
     */
    private function getPHPVersion()
    {
        return phpversion();
    }

    /**
     * Gets the environment name
     *
     * @return string
     */
    private function getEnvironmentName()
    {
        $config  = $this->getConfigHelper();
        return array_key_exists($config->getGatewayEnvironment(), GatewayEnvironment::ENVIRONMENTS)
            ? GatewayEnvironment::ENVIRONMENTS[$config->getGatewayEnvironment()]['name']
            : '';
    }

    /**
     * Gets file checksums
     *
     * @param array  $fileList
     * @param string $scope
     *
     * @return array
     */
    private function getFileChecksums($fileList, $scope)
    {
        $result = false;
        if (is_array($fileList)) {
            switch ($scope) {
                case 'module':
                    $directory = $this->getModuleDirectory();
                    break;
                case 'platform':
                    $directory = $this->getPlatformDirectory();
                    break;
                default:
                    $directory = false;
                    break;
            }
            if (!empty($directory)) {
                $result = [];
                foreach ($fileList as $key => $filename) {
                    $file = $directory . '/' . $filename;
                    // @codingStandardsIgnoreLine
                    $result[$key] = is_file($file)
                        ? sha1_file($file)
                        : null;
                }
            }
        }
        return $result;
    }

    /**
     * Gets module directory
     *
     * @return string
     */
    private function getModuleDirectory()
    {
        return $this->moduleReader->getModuleDir('', 'Paymentsense_RemotePayments');
    }

    /**
     * Gets platform directory
     *
     * @return string
     */
    private function getPlatformDirectory()
    {
        $objectManager = $this->getModuleHelper()->getObjectManager();
        $directoryList = $objectManager->get('\Magento\Framework\App\Filesystem\DirectoryList');
        return $directoryList->getPath('base');
    }
}
