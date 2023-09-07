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

/**
 * Provides data for the payment form
 */
class DataProvider extends \Paymentsense\RemotePayments\Controller\CheckoutAction
{
    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Paymentsense\RemotePayments\Model\Method\RemotePayments
     */
    // @codingStandardsIgnoreStart
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Paymentsense\RemotePayments\Model\Method\RemotePayments $method
    ) {
        parent::__construct($context, $logger, $checkoutSession, $orderFactory, $method);
    }
    // @codingStandardsIgnoreEnd

    /**
     * Handles ajax requests and provides data for the payment form
     * Generates application/json response containing the form data in JSON format
     *
     * @throws \Exception
     */
    public function execute()
    {
        $is_order = (bool) $this->getRequest()->getPostValue('is_order');
        $order = $is_order ? $this->getOrder() : $this->getCheckoutSession()->getQuote();
        $data = isset($order) ? $this->_method->getPaymentFormData($order) : [];

        if (isset($data['HttpStatusCode']) &&
            (($data['HttpStatusCode'] === Exception::HTTP_BAD_REQUEST) || ($data['HttpStatusCode'] === Exception::HTTP_UNAUTHORIZED))
        ) {
            $this->getResponse()
                ->setHttpResponseCode($data['HttpStatusCode'])
                ->setBody($data['ResponseBody']);
        } else {
            $this->getResponse()
                ->setHeader('Content-Type', 'application/json')
                ->setBody(json_encode($data));
        }
    }
}
