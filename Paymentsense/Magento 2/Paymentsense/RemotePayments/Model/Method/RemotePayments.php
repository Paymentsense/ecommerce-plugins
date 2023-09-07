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

namespace Paymentsense\RemotePayments\Model\Method;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Checkout\Model\Session;
use Paymentsense\RemotePayments\Model\Traits\BaseInfoMethod;

/**
 * RemotePayments payment method model
 */
class RemotePayments extends \Magento\Payment\Model\Method\AbstractMethod
{
    use BaseInfoMethod;

    public const CODE = 'paymentsense_remotepayments';

    protected $_code                    = self::CODE;
    protected $_canOrder                = true;
    protected $_isGateway               = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = true;
    protected $_canCancelInvoice        = true;
    protected $_canVoid                 = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canAuthorize            = true;
    protected $_isInitializeNeeded      = false;
    protected $_canUseCheckout          = true;
    protected $_canUseInternal          = false;

    /**
     * @var OrderSender
     */
    protected $_orderSender;

    /**
     * @var \Magento\Framework\Module\Dir\Reader
     */
    protected $moduleReader;

    /**
     * @param \Paymentsense\RemotePayments\Model\Connect\Client $connectClient
     * @param \Paymentsense\RemotePayments\Helper\Data $moduleHelper
     * @param \Paymentsense\RemotePayments\Helper\Transactions $transactionsHelper
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\App\Action\Context $actionContext
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     * @param \Magento\Framework\App\ProductMetadataInterface $productMetadataInterface
     * @param \Magento\Framework\Module\Dir\Reader $moduleReader
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct(
        \Paymentsense\RemotePayments\Model\Connect\Client $connectClient,
        \Paymentsense\RemotePayments\Helper\Data $moduleHelper,
        \Paymentsense\RemotePayments\Helper\Transactions $transactionsHelper,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\App\Action\Context $actionContext,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        Session $checkoutSession,
        OrderSender $orderSender,
        \Magento\Framework\App\ProductMetadataInterface $productMetadataInterface,
        \Magento\Framework\Module\Dir\Reader $moduleReader,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $logger = $this->createLogger();
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
        $this->_moduleHelper       = $moduleHelper;
        $this->_transactionsHelper = $transactionsHelper;
        $this->_connectClient      = $connectClient;
        $this->_actionContext      = $actionContext;
        $this->_storeManager       = $storeManager;
        $this->_checkoutSession    = $checkoutSession;
        $this->_orderSender        = $orderSender;
        $this->_configHelper       = $this->getModuleHelper()->getMethodConfig($this->getCode());
        $this->productMetadata     = $productMetadataInterface;
        $this->moduleReader        = $moduleReader;
        $this->_canCapture         = true;
        $this->_canRefund          = true;
        $this->_canVoid            = true;
    }

    /**
     * Gets the logger
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger->getLogger();
    }

    /**
     * Gets the payment action on payment complete
     *
     * @return string
     */
    public function getConfigPaymentAction()
    {
        return self::ACTION_ORDER;
    }

    /**
     * Determines the payment method availability
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     *
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return parent::isAvailable($quote) && $this->getConfigHelper()->isMethodAvailable($this->getCode());
    }

    /**
     * Order handler
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     */
    // @codingStandardsIgnoreLine
    public function order(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->getLogger()->info('ACTION_ORDER has been triggered.');
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);
        $order->setState(Order::STATE_NEW);
        $this->getLogger()->info(
            sprintf(
                'New order #%s with amount %.2f %s (%.2f %s) has been created.',
                $order->getRealOrderId(),
                $order->getBaseTotalDue(),
                $order->getBaseCurrencyCode(),
                $order->getTotalDue(),
                $order->getOrderCurrencyCode()
            )
        );
        return $this;
    }

    /**
     * Refund handler
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     *
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $errorMessage = '';
        $order        = $payment->getOrder();
        $orderId      = $order->getIncrementId();
        $this->getLogger()->info(
            sprintf('Preparing REFUND transaction for order #%s', $orderId)
        );
        $captureTransaction = $this->getTransactionsHelper()->lookUpCaptureTransaction($payment);
        if (isset($captureTransaction)) {
            try {
                $this->performRefund($payment, $amount, $captureTransaction);
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
            }
        } else {
            $errorMessage = 'REFUND transaction for order #' . $orderId .
                ' cannot be finished (No Capture Transaction exists)';
        }

        if ($errorMessage !== '') {
            $this->getLogger()->warning($errorMessage);
            $this->getModuleHelper()->throwWebapiException($errorMessage);
        }

        return $this;
    }

    /**
     * Capture handler
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     *
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $errorMessage = '';
        $order        = $payment->getOrder();
        $orderId      = $order->getIncrementId();
        $this->getLogger()->info(
            sprintf('Preparing COLLECTION transaction for order #%s', $orderId)
        );

        $authTransaction = $this->getTransactionsHelper()->lookUpAuthorisationTransaction($payment);
        if (isset($authTransaction)) {
            try {
                $this->performCollection($payment, $amount, $authTransaction);
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
            }
        } else {
            $errorMessage = 'COLLECTION transaction for order #' . $orderId .
                ' cannot be finished (No Authorize Transaction exists)';
        }

        if ($errorMessage !== '') {
            $this->getLogger()->warning($errorMessage);
            $this->getModuleHelper()->throwWebapiException($errorMessage);
        }

        return $this;
    }

    /**
     * Void handler
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     *
     * @return $this
     *
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function void(\Magento\Payment\Model\InfoInterface $payment)
    {
        $errorMessage = '';
        $order        = $payment->getOrder();
        $orderId      = $order->getIncrementId();

        $this->getLogger()->info(
            sprintf('Preparing VOID transaction for order #%s', $orderId)
        );

        $authTransaction = $this->getTransactionsHelper()->lookUpAuthorisationTransaction($payment);
        if (isset($authTransaction)) {
            try {
                $this->performVoid($payment, $authTransaction);
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
            }
        } else {
            $errorMessage = 'VOID transaction for order #' . $orderId .
                ' cannot be finished (No Authorize Transaction exists)';
        }

        if ($errorMessage !== '') {
            $this->getLogger()->warning($errorMessage);
            $this->getModuleHelper()->throwWebapiException($errorMessage);
        }

        return $this;
    }

    /**
     * Cancel handler
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     *
     * @return $this
     *
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function cancel(\Magento\Payment\Model\InfoInterface $payment)
    {
        return $this->void($payment);
    }
}
