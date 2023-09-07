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

use Magento\Framework\Webapi\Exception;
use Magento\Sales\Model\Order;
use Zend\Http\Response;
use Paymentsense\RemotePayments\Model\Connect\PaymentStatus;

/**
 * Handles the payment notifications from the payment gateway
 */
class Notification extends \Paymentsense\RemotePayments\Controller\ApiAction
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $_resultPageFactory;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Paymentsense\RemotePayments\Model\Method\RemotePayments
     */
    // @codingStandardsIgnoreStart
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Paymentsense\RemotePayments\Model\Method\RemotePayments $method
    ) {
        $this->_resultPageFactory = $resultPageFactory;
        parent::__construct($context, $logger, $orderFactory, $method);
    }
    // @codingStandardsIgnoreEnd

    /**
     * Processes the payment notification callback requests made by the payment gateway
     *
     * @throws \Exception
     */
    public function execute()
    {
        if (! $this->isPaymentNotificationRequest()) {
            $this->_method->getLogger()->warning('Non-JSON notification request triggering HTTP_BAD_REQUEST (HTTP status code 400).');
            $this->getResponse()->setHttpResponseCode(
                Exception::HTTP_BAD_REQUEST
            );
            return;
        }

        $this->_method->getLogger()->info(
            'Payment notification has been received.'
        );

        $requestBodyValid = false;
        // @codingStandardsIgnoreLine
        $requestBody = file_get_contents('php://input');
        if (! empty($requestBody)) {
            $params = json_decode($requestBody, true);
            if (is_array($params)) {
                $accessToken      = $this->_method->getArrayElement($params, 'id', '');
                $orderId          = $this->_method->getArrayElement($params, 'orderId', '');
                $requestBodyValid = ! empty($accessToken) && ! empty($orderId);
            }
        }

        if (! $requestBodyValid) {
            $this->_method->getLogger()->warning('The body of the notification request is invalid. Triggering HTTP_BAD_REQUEST (HTTP status code 400).');
            $this->getResponse()->setHttpResponseCode(
                Exception::HTTP_BAD_REQUEST
            );
            return;
        }

        $order = $this->getOrderByOrderId($orderId);
        if (empty($order)) {
            $this->_method->getLogger()->warning('No order found for the Magento checkout session.');
            $this->getResponse()->setHttpResponseCode(
                Exception::HTTP_BAD_REQUEST
            );
            return;
        }

        $response = $this->_method->getTransactionPaymentStatus($order, $accessToken);

        if (!$response['OrderIdValid']) {
            $this->_method->getLogger()->error('Order ID does not match its meta data value.');
            $this->getResponse()->setHttpResponseCode(
                Exception::HTTP_BAD_REQUEST
            );
            return;
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

        $this->_method->getLogger()->info('Responding with HTTP Status Code 200.');
        $this->getResponse()->setHttpResponseCode(
            Response::STATUS_CODE_200
        );
    }
}
