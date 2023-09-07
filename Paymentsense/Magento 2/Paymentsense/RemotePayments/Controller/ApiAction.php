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

/**
 * Abstract action class containing class methods for common API-related functionalities
 */
abstract class ApiAction extends CsrfAwareAction
{
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
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Payment\Model\Method\AbstractMethod $method
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        $method
    ) {
        parent::__construct($context, $logger);
        $this->_orderFactory = $orderFactory;
        $this->_method       = $method;
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
     * Gets an instance of an Order object
     *
     * @param string $orderId
     *
     * @return Order
     */
    public function getOrderByOrderId($orderId)
    {
        $result = null;
        $order = $this->getOrderFactory()->create()->loadByIncrementId($orderId);
        if ($order->getId()) {
            $result = $order;
        }
        return $result;
    }
}
