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

namespace Paymentsense\RemotePayments\Model;

use Magento\Store\Model\ScopeInterface;

/**
 * Implementation of Payment Model Method ConfigInterface
 * Used for retrieving configuration data
 */
class Config implements \Magento\Payment\Model\Method\ConfigInterface
{
    private const MODULE_NAME = 'Paymentsense Remote Payments Module for Magento 2 Open Source';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_methodCode;
    /**
     * @var \Magento\Framework\Module\ModuleListInterface
     */
    protected $moduleList;
    /**
     * Current store id
     *
     * @var int
     */
    protected $_storeId;
    /**
     * @var string
     */
    protected $pathPattern;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Module\ModuleListInterface
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Module\ModuleListInterface $moduleListInterface
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->moduleList   = $moduleListInterface;
    }

    /**
     * Gets an instance of the ScopeConfig
     *
     * @return \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected function getScopeConfig()
    {
        return $this->_scopeConfig;
    }

    /**
     * Gets the payment method code
     *
     * @return string
     */
    public function getMethodCode()
    {
        return $this->_methodCode;
    }

    /**
     * Gets the module name
     *
     * @return string
     */
    public function getModuleName()
    {
        return self::MODULE_NAME;
    }

    /**
     * Gets the module installed version
     *
     * @return string
     */
    public function getModuleInstalledVersion()
    {
        return $this->moduleList->getOne('Paymentsense_RemotePayments')['setup_version'];
    }

    /**
     * Gets a configuration value
     *
     * @param string $key
     * @param null   $storeId
     *
     * @return null|string
     */
    public function getValue($key, $storeId = null)
    {
        $value = '';
        $underscored = strtolower(preg_replace('/(.)([A-Z])/', "$1_$2", $key));
        $path = $this->getConfigPath($underscored);
        if ($path !== null) {
            $value = $this->getScopeConfig()->getValue(
                $path,
                ScopeInterface::SCOPE_STORE,
                $storeId ?: $this->_storeId
            );
        }
        return $value;
    }

    /**
     * Sets the payment method code
     *
     * @param string $methodCode
     *
     * @return void
     */
    public function setMethodCode($methodCode)
    {
        $this->_methodCode = $methodCode;
    }

    /**
     * Sets path pattern
     *
     * @param string $pathPattern
     *
     * @return void
     */
    public function setPathPattern($pathPattern)
    {
        $this->pathPattern = $pathPattern;
    }

    /**
     * Gets config path by field name
     *
     * @param string $fieldName
     *
     * @return string
     */
    private function getConfigPath($fieldName)
    {
        if ($this->pathPattern) {
            return sprintf($this->pathPattern, $this->_methodCode, $fieldName);
        }

        return "payment/{$this->_methodCode}/{$fieldName}";
    }

    /**
     * Checks whether the payment gateway credentials are configured
     *
     * @return bool
     */
    public function isMethodConfigured()
    {
        return !empty($this->getUsername()) &&
            !empty($this->getGatewayJwt()) &&
            !empty($this->getTransactionType()) &&
            !empty($this->getGatewayEnvironment());
    }

    /**
     * Checks whether the payment method is available for checkout
     *
     * @param string $methodCode
     *
     * @return bool
     */
    public function isMethodAvailable($methodCode)
    {
        return $this->isMethodActive($methodCode) && $this->isMethodConfigured();
    }

    /**
     * Checks whether the payment method is active
     *
     * @param string $methodCode
     *
     * @return bool
     */
    public function isMethodActive($methodCode)
    {
        return $this->isChecked($methodCode, 'active');
    }

    /**
     * Checks whether configuration checkbox is checked
     *
     * @param string $methodCode
     * @param string $name
     *
     * @return bool
     */
    public function isChecked($methodCode, $name)
    {
        $methodCode = $methodCode ?: $this->_methodCode;
        return $this->getScopeConfig()->isSetFlag(
            "payment/{$methodCode}/{$name}",
            ScopeInterface::SCOPE_STORE,
            $this->_storeId
        );
    }

    /**
     * Gets the username
     *
     * @return null|string
     */
    public function getUsername()
    {
        return $this->getValue('gateway_username');
    }

    /**
     * Gets the JWT
     *
     * @return string
     */
    public function getGatewayJwt()
    {
        return $this->getValue('gateway_jwt');
    }

    /**
     * Gets the environment
     *
     * @return string
     */
    public function getGatewayEnvironment()
    {
        return $this->getValue('gateway_environment');
    }

    /**
     * Gets the checkout page title
     *
     * @return null|string
     */
    public function getCheckoutTitle()
    {
        return $this->getValue('title');
    }

    /**
     * Gets the transaction type
     *
     * @return string
     */
    public function getTransactionType()
    {
        return $this->getValue('transaction_type');
    }

    /**
     * Gets the new order status
     *
     * @return null|string
     */
    public function getOrderStatusNew()
    {
        return $this->getValue('order_status');
    }

    /**
     * Gets the payment currency
     *
     * @return string
     */
    public function getPaymentCurrency()
    {
        return $this->getValue('payment_currency');
    }

    /**
     * Determines whether the payment currency is the base currency
     *
     * @return bool
     */
    public function isBaseCurrency()
    {
        return 'BASE' === $this->getPaymentCurrency();
    }

    /**
     * Determines whether specific currencies are enabled
     *
     * @return bool
     */
    public function areSpecificCurrenciesEnabled()
    {
        return $this->isChecked($this->_methodCode, 'allow_specific_currency');
    }

    /**
     * Gets the allowed currencies
     *
     * @return array
     */
    public function getAllowedCurrencies()
    {
        return array_map(
            'trim',
            explode(
                ',',
                $this->getValue('specific_currencies')
            )
        );
    }

    /**
     * Gets the log level
     *
     * @return int
     */
    public function getLogLevel()
    {
        return (int) $this->getValue('log_level');
    }
}
