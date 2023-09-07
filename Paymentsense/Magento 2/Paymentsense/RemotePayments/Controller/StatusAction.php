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

use Magento\Backend\Model\Session;

/**
 * Abstract action class implementing redirect actions
 */
abstract class StatusAction extends CsrfAwareAction
{
    /**
     * @var \Paymentsense\RemotePayments\Helper\DiagnosticMessage
     */
    protected $_messageHelper;

    /**
     * @var \Magento\Backend\Model\Session
     */
    protected $_backendSession;

    /**
     * @var \Paymentsense\RemotePayments\Model\Method\RemotePayments
     */
    protected $_method;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Paymentsense\RemotePayments\Helper\DiagnosticMessage $messageHelper
     * @param \Magento\Backend\Model\Session $backendSession
     * @param \Magento\Payment\Model\Method\AbstractMethod $method
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Paymentsense\RemotePayments\Helper\DiagnosticMessage $messageHelper,
        Session $backendSession,
        $method
    ) {
        parent::__construct($context, $logger);
        $this->_messageHelper  = $messageHelper;
        $this->_backendSession = $backendSession;
        $this->_method         = $method;
    }

    /**
     * Gets an instance of the Magento Checkout Session
     *
     * @return \Magento\Backend\Model\Session
     */
    protected function getBackendSession()
    {
        return $this->_backendSession;
    }
}
