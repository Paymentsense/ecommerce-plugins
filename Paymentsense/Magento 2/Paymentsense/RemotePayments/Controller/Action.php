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

/**
 * Abstract action class containing base class methods
 */
abstract class Action extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\App\Action\Context
     */
    protected $_context;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->_context = $context;
        $this->_logger = $logger;
    }

    /**
     * Gets an instance of the Magento Controller Action
     *
     * @return \Magento\Framework\App\Action\Context
     */
    protected function getContext()
    {
        return $this->_context;
    }

    /**
     * Gets an instance of the Magento Object Manager
     *
     * @return \Magento\Framework\ObjectManagerInterface
     */
    protected function getObjectManager()
    {
        return $this->_objectManager;
    }

    /**
     * Gets an instance of the Magento global Message Manager
     *
     * @return \Magento\Framework\Message\ManagerInterface
     */
    protected function getMessageManager()
    {
        return $this->getContext()->getMessageManager();
    }

    /**
     * Gets an instance of the Magento global Logger
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function getLogger()
    {
        return $this->_logger;
    }

    /**
     * Gets the POST variables
     *
     * @return array
     */
    protected function getPostData()
    {
        return $this->getRequest()->getPostValue();
    }

    /**
     * Checks whether the current request is a payment notification
     *
     * @return bool
     */
    protected function isPaymentNotificationRequest()
    {
        return $this->getRequest()->isPost() && $this->isJsonRequest();
    }

    /**
     * Checks whether the current request is a JSON request
     *
     * @return bool
     */
    protected function isJsonRequest()
    {
        $contentType = $this->getRequest()->getHeader('CONTENT_TYPE');
        return isset($contentType) &&
            preg_match('/(^|\s|,)application\/json($|\s|;|,)/i', $contentType);
    }
}
