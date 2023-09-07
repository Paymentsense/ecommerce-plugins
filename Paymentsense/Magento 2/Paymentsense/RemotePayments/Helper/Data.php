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

namespace Paymentsense\RemotePayments\Helper;

use Magento\Sales\Model\Order;
use Paymentsense\RemotePayments\Model\Connect\PaymentStatus;

/**
 * Helper containing common functionalities
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * Shopping cart platform name
     */
    private const PLATFORM_NAME = 'Magento';

    /**
     * Payment gateway name
     */
    private const GATEWAY_NAME = 'Paymentsense';

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;
    /**
     * @var \Magento\Payment\Helper\Data
     */
    protected $_paymentData;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;
    /**
     * @var \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress
     */
    protected $_configFactory;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @param \Paymentsense\RemotePayments\Model\ConfigFactory $configFactory
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Paymentsense\RemotePayments\Model\ConfigFactory $configFactory,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->_configFactory = $configFactory;
        $this->_objectManager = $objectManager;
        $this->_paymentData = $paymentData;
        $this->_storeManager = $storeManager;
        $this->_scopeConfig = $context->getScopeConfig();
        parent::__construct($context);
    }

    /**
     * Gets an instance of the Magento Object Manager
     *
     * @return \Magento\Framework\ObjectManagerInterface
     */
    public function getObjectManager()
    {
        return $this->_objectManager;
    }

    /**
     * Gets an instance of the Magento Store Manager
     *
     * @return \Magento\Store\Model\StoreManagerInterface
     */
    protected function getStoreManager()
    {
        return $this->_storeManager;
    }

    /**
     * Gets an instance of the Config Factory Class
     *
     * @return \Paymentsense\RemotePayments\Model\ConfigFactory
     */
    protected function getConfigFactory()
    {
        return $this->_configFactory;
    }

    /**
     * Gets an instance of the Magento UrlBuilder
     *
     * @return \Magento\Framework\UrlInterface
     */
    public function getUrlBuilder()
    {
        return $this->_urlBuilder;
    }

    /**
     * Gets an instance of the Magento Scope Config
     *
     * @return \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected function getScopeConfig()
    {
        return $this->_scopeConfig;
    }

    /**
     * Gets the URL receiving the payment notifications from the payment gateway
     *
     * @return string|false
     */
    public function getPaymentNotificationUrl()
    {
        try {
            $store = $this->getStoreManager()->getStore();
            $params = [
                '_store'  => $store,
                '_secure' => $this->isStoreSecure()
            ];
            $result = $this->getUrlBuilder()->getUrl('paymentsense/remotepayments/notification', $params);
        } catch (\Exception $e) {
            $result = false;
        }
        return $result;
    }

    /**
     * Gets the return URL where the customer will be redirected from the payment form
     *
     * @return string|false
     */
    public function getReturnUrl()
    {
        try {
            $store = $this->getStoreManager()->getStore();
            $params = [
                '_store'  => $store,
                '_secure' => $this->isStoreSecure()
            ];
            $result = $this->getUrlBuilder()->getUrl('paymentsense/remotepayments/index', $params);
        } catch (\Exception $e) {
            $result = false;
        }
        return $result;
    }

    /**
     * Gets shopping cart platform URL
     *
     * @return string
     */
    public function getCartUrl()
    {
        try {
            $result = $this->getStoreManager()->getStore()->getBaseUrl();
        } catch (\Exception $e) {
            $result = false;
        }
        return $result;
    }

    /**
     * Gets shopping cart platform name
     *
     * @return string
     */
    public function getCartPlatformName()
    {
        return self::PLATFORM_NAME;
    }

    /**
     * Gets gateway name
     *
     * @return string
     */
    public function getGatewayName()
    {
        return self::GATEWAY_NAME;
    }

    /**
     * Gets an instance of a Method object
     *
     * @param string $methodCode
     *
     * @return \Paymentsense\RemotePayments\Model\Config
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getMethodConfig($methodCode)
    {
        $parameters = [
            'params' => [
                $methodCode,
                $this->getStoreManager()->getStore()->getId()
            ]
        ];
        $config = $this->getConfigFactory()->create($parameters);
        $config->setMethodCode($methodCode);
        return $config;
    }

    /**
     * Creates Webapi Exception
     *
     * @param \Magento\Framework\Phrase|string $phrase
     *
     * @return \Magento\Framework\Webapi\Exception
     */
    public function createWebapiException($phrase)
    {
        if (!($phrase instanceof \Magento\Framework\Phrase)) {
            $phrase = new \Magento\Framework\Phrase($phrase);
        }
        return new \Magento\Framework\Webapi\Exception($phrase);
    }

    /**
     * Throws Webapi Exception
     *
     * @param \Magento\Framework\Phrase|string $phrase
     *
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function throwWebapiException($phrase)
    {
        // @codingStandardsIgnoreLine
        throw $this->createWebapiException($phrase);
    }

    /**
     * Checks whether the store is secure
     *
     * @param $storeId
     *
     * @return bool
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isStoreSecure($storeId = null)
    {
        $store = $this->getStoreManager()->getStore($storeId);
        return $store->isCurrentlySecure();
    }

    /**
     * Updates the order status and state
     *
     * @param Order  $order Order object
     * @param string $state Order state
     */
    public function setOrderStatusByState($order, $state)
    {
        $order
            ->setState($state)
            ->setStatus($order->getConfig()->getStateDefaultStatus($state));
    }

    /**
     * Sets the order state based on the payment status
     *
     * @param Order  $order         Order object
     * @param string $paymentStatus Payment status
     * @param string $message       Response message
     *
     * @throws \Exception
     */
    public function setOrderState($order, $paymentStatus, $message)
    {
        switch ($paymentStatus) {
            case PaymentStatus::SUCCESS:
                $this->setOrderStatusByState($order, Order::STATE_PROCESSING);
                $order->save();
                break;
            case PaymentStatus::FAIL:
                foreach ($order->getInvoiceCollection() as $invoice) {
                    $invoice->cancel();
                }
                $order
                    ->registerCancellation($message)
                    ->setCustomerNoteNotify(true)
                    ->save();
                break;
            case PaymentStatus::UNKNOWN:
                $this->setOrderStatusByState($order, Order::STATE_PAYMENT_REVIEW);
                $order->save();
                break;
            default:
                $order->save();
                break;
        }
    }

    /**
     * Gets an array of all globally allowed currency codes
     *
     * @return array
     */
    private function getGloballyAllowedCurrencyCodes()
    {
        $allowedCurrencyCodes = $this->getScopeConfig()->getValue(
            \Magento\Directory\Model\Currency::XML_PATH_CURRENCY_ALLOW
        );
        return array_map(
            function ($item) {
                return trim($item);
            },
            explode(',', $allowedCurrencyCodes)
        );
    }

    /**
     * Builds the Select Options for the Allowed Currencies in the admin panel
     *
     * @param array $availableCurrenciesOptions
     *
     * @return array
     */
    public function getGloballyAllowedCurrenciesOptions($availableCurrenciesOptions)
    {
        $allowedCurrenciesOptions = [];
        $allowedGlobalCurrencyCodes = $this->getGloballyAllowedCurrencyCodes();
        foreach ($availableCurrenciesOptions as $availableCurrencyOptions) {
            if (in_array($availableCurrencyOptions['value'], $allowedGlobalCurrencyCodes)) {
                $allowedCurrenciesOptions[] = $availableCurrencyOptions;
            }
        }
        return $allowedCurrenciesOptions;
    }

    /**
     * Gets the filtered currencies
     *
     * @param array $allowedLocalCurrencies
     *
     * @return array
     */
    private function getFilteredCurrencies($allowedLocalCurrencies)
    {
        $result = [];
        $allowedGlobalCurrencyCodes = $this->getGloballyAllowedCurrencyCodes();
        foreach ($allowedLocalCurrencies as $allowedLocalCurrency) {
            if (in_array($allowedLocalCurrency, $allowedGlobalCurrencyCodes)) {
                $result[] = $allowedLocalCurrency;
            }
        }
        return $result;
    }

    /**
     * Checks whether the payment method is available for a given currency
     *
     * @param string $methodCode
     * @param string $currencyCode
     *
     * @return bool
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isCurrencyAllowed($methodCode, $currencyCode)
    {
        $methodConfig = $this->getMethodConfig($methodCode);
        if ($methodConfig->areSpecificCurrenciesEnabled()) {
            $allowedMethodCurrencies = $this->getFilteredCurrencies(
                $methodConfig->getAllowedCurrencies()
            );
        } else {
            $allowedMethodCurrencies = $this->getGloballyAllowedCurrencyCodes();
        }
        return in_array($currencyCode, $allowedMethodCurrencies);
    }

    /**
     * Calculates an order/display amount due
     *
     * @param Order $order  Order object
     * @param float $amount Amount
     *
     * @return float|false
     *
     */
    public function calculateOrderAmountDue($order, $amount)
    {
        $result = false;
        if ($order->getBaseTotalDue()) {
            $orderAmount = $order->getTotalDue();
            $baseAmount = $order->getBaseTotalDue();
            if (is_numeric($orderAmount) && is_numeric($baseAmount) && ($baseAmount > 0)) {
                $rate = $orderAmount / $baseAmount;
                $result = round($amount * $rate, 2);
            }
        }
        return $result;
    }

    /**
     * Calculates an order/display amount paid
     *
     * @param Order $order  Order object
     * @param float $amount Amount
     *
     * @return float|false
     *
     */
    public function calculateOrderAmountPaid($order, $amount)
    {
        $result = false;
        if ($order->getBaseTotalPaid()) {
            $orderAmount = $order->getTotalPaid();
            $baseAmount = $order->getBaseTotalPaid();
            if (is_numeric($orderAmount) && is_numeric($baseAmount) && ($baseAmount > 0)) {
                $rate = $orderAmount / $baseAmount;
                $result = round($amount * $rate, 2);
            }
        }
        return $result;
    }

    /**
     * Calculates a base amount
     *
     * @param Order $order  Order object
     * @param float $amount Amount
     *
     * @return float|false
     *
     */
    public function calculateBaseAmount($order, $amount)
    {
        $result = false;
        if ($order->getBaseTotalDue()) {
            $orderAmount = $order->getTotalDue();
            $baseAmount = $order->getBaseTotalDue();
            if (is_numeric($orderAmount) && is_numeric($baseAmount) && ($orderAmount > 0)) {
                $rate = $baseAmount / $orderAmount;
                $result = round($amount * $rate, 2);
            }
        }
        return $result;
    }
}
