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

namespace Paymentsense\RemotePayments\Controller\RemotePayments;

use Magento\Sales\Model\Order;
use Paymentsense\RemotePayments\Model\Connect\PaymentStatus;

/**
 * Handles the response from the payment gateway
 */
class Index extends \Paymentsense\RemotePayments\Controller\CheckoutAction
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $_resultPageFactory;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Paymentsense\RemotePayments\Model\Method\RemotePayments
     */
    // @codingStandardsIgnoreStart
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Paymentsense\RemotePayments\Model\Method\RemotePayments $method
    ) {
        $this->_resultPageFactory = $resultPageFactory;
        parent::__construct($context, $logger, $checkoutSession, $orderFactory, $method);
    }
    // @codingStandardsIgnoreEnd

    /**
     * Processes the customer redirect from the payment form
     *
     * @return \Magento\Framework\View\Result\Page|null
     *
     * @throws \Exception
     */
    public function execute()
    {
        if (! $this->getRequest()->isPost()) {
            $this->_method->getLogger()->warning('Non-POST customer redirect request triggering HTTP_BAD_REQUEST (HTTP status code 400).');
            return $this->getBadRequestPage();
        }

        $this->_method->getLogger()->info(
            'Customer redirect from the payment form has been received.'
        );

        $accessToken = $this->getRequest()->getPostValue('accessToken');
        if (empty($accessToken)) {
            $accessToken = $this->getRequest()->getPostValue('paymentToken');
        }

        if (empty($accessToken)) {
            $this->_method->getLogger()->warning('Access token is empty.');
            return $this->getBadRequestPage();
        }

        $order = $this->getOrder();
        if (empty($order)) {
            $this->_method->getLogger()->warning('No order found for the Magento checkout session.');
            $this->redirectToCheckoutCart();
            return;
        }
        $orderId = $order->getRealOrderId();

        $response = $this->_method->getTransactionPaymentStatus($order, $accessToken);

        if (!$response['OrderIdValid']) {
            $this->_method->getLogger()->error('Order ID does not match its meta data value.');
            return $this->getBadRequestPage();
        }

        $this->_method->getLogger()->info(
            'Card details transaction ' . $response['CrossReference'] .
            ' for order #' . $orderId .
            ' has been performed with status code "' . $response['StatusCode'] . '"' .
            ' and message "' . $response['Message'] . '".'
        );

        $paymentStatus = $this->_method->getPaymentStatus($response['StatusCode']);

        if ($order->getState() === Order::STATE_PENDING_PAYMENT) {
            $this->_method->getModuleHelper()->setOrderState($order, $paymentStatus, $response['Message']);
            if ($paymentStatus !== PaymentStatus::UNKNOWN) {
                $this->_method->updatePayment($order, $response);
                if ($paymentStatus === PaymentStatus::SUCCESS) {
                    $this->_method->sendNewOrderEmail($order);
                }
            }
        }

        $this->processActions($orderId, $paymentStatus, $response['Message']);
        $this->_method->getLogger()->info(
            'Customer redirect from the payment form has been processed.'
        );
    }

    /**
     * Processes actions based on the transaction status
     *
     * @param string $orderId       Order ID
     * @param int    $paymentStatus Payment status
     * @param string $message       Response message
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function processActions($orderId, $paymentStatus, $message)
    {
        switch ($paymentStatus) {
            case PaymentStatus::SUCCESS:
                $this->executeSuccessAction();
                break;
            case PaymentStatus::FAIL:
                $this->executeFailureAction($message);
                break;
            default:
                $this->executeUnknownPaymentResultAction($orderId);
                break;
        }
    }
}
