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

namespace Paymentsense\RemotePayments\Controller;

use Magento\Sales\Model\Order;
use Magento\Checkout\Model\Session;

/**
 * Abstract action class implementing redirect actions
 */
abstract class CheckoutAction extends CsrfAwareAction
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Paymentsense\RemotePayments\Model\Method\RemotePayments
     */
    protected $_method;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Payment\Model\Method\AbstractMethod $method
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        $method
    ) {
        parent::__construct($context, $logger);
        $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory    = $orderFactory;
        $this->_method          = $method;
    }

    /**
     * Gets an instance of the Magento Checkout Session
     *
     * @return \Magento\Checkout\Model\Session
     */
    public function getCheckoutSession()
    {
        return $this->_checkoutSession;
    }

    /**
     * Gets an instance of the Magento Order Factory
     *
     * @return \Magento\Sales\Model\OrderFactory
     */
    public function getOrderFactory()
    {
        return $this->_orderFactory;
    }

    /**
     * Gets an instance of the current Checkout Order object
     *
     * @return Order
     */
    public function getOrder()
    {
        $result = null;
        $orderId = $this->getCheckoutSession()->getLastRealOrderId();
        if (isset($orderId)) {
            $order = $this->getOrderFactory()->create()->loadByIncrementId($orderId);
            if ($order->getId()) {
                $result = $order;
            }
        }
        return $result;
    }

    /**
     * Cancels the current order and restores the quote
     *
     * @param string $comment
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Exception
     */
    public function cancelOrderAndRestoreQuote($comment)
    {
        $order = $this->getCheckoutSession()->getLastRealOrder();
        if ($order->getId() && $order->getState() != Order::STATE_CANCELED) {
            $order->registerCancellation($comment)->save();
        }
        $this->getCheckoutSession()->restoreQuote();
    }

    /**
     * Handles Success action
     */
    public function executeSuccessAction()
    {
        $this->_method->getLogger()->info('Success Action has been triggered.');
        $this->redirectToCheckoutOnePageSuccess();
        $this->_method->getLogger()->info('A redirect to the Checkout Success Page has been set.');
    }

    /**
     * Handles Failure action
     *
     * @param string $message
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function executeFailureAction($message)
    {
        $this->_method->getLogger()->info('Failure Action with message "' . $message . '" has been triggered.');
        $customerMessage = sprintf(
            __("Payment failed with message '%s'. Please check your card details and try again."),
            $message
        );
        $orderMessage = sprintf(
            __("Payment failed with message '%s'."),
            $message
        );
        $this->getMessageManager()->addErrorMessage($customerMessage);
        $this->cancelOrderAndRestoreQuote($orderMessage);
        $this->redirectToCheckoutCart();
        $this->_method->getLogger()->info('A redirect to the Checkout Cart has been set.');
    }

    /**
     * Handles Unknown Payment Result action
     *
     * @param string $orderId Order ID
     */
    public function executeUnknownPaymentResultAction($orderId)
    {
        $this->_method->getLogger()->info('Unknown Payment Result Action has been triggered.');
        $customerMessage = sprintf(
            __("Payment status is unknown. Please contact customer support quoting your order #%s and do not retry the payment for this order unless you are instructed to do so."),
            $orderId
        );
        $this->getMessageManager()->addErrorMessage($customerMessage);
        $this->redirectToCheckoutCart();
        $this->_method->getLogger()->info('A redirect to the Checkout Cart has been set.');
    }

    /**
     * Redirects to the Checkout Success page
     *
     * @return void
     */
    public function redirectToCheckoutOnePageSuccess()
    {
        $this->_redirect('checkout/onepage/success');
    }

    /**
     * Redirects to the Checkout Cart
     *
     * @return void
     */
    public function redirectToCheckoutCart()
    {
        $this->_redirect('checkout/cart');
    }

    /**
     * Redirects to the Checkout Payment page
     *
     * @return void
     */
    public function redirectToCheckoutFragmentPayment()
    {
        $this->_redirect('checkout', ['_fragment' => 'payment']);
    }

    /**
     * Gets the bad request page
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function getBadRequestPage()
    {
        $resultPage = $this->_resultPageFactory->create();
        $resultPage->addHandle('bad_request');
        return $resultPage;
    }
}
