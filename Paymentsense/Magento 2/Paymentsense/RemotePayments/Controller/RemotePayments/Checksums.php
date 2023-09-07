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

/**
 * Handles the file checksums request
 */
class Checksums extends \Paymentsense\RemotePayments\Controller\ChecksumsAction
{
    /**
     * @var \Paymentsense\RemotePayments\Model\Method\RemotePayments
     */
    protected $method;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Paymentsense\RemotePayments\Model\Method\RemotePayments $method
     */
    // @codingStandardsIgnoreStart
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Paymentsense\RemotePayments\Model\Method\RemotePayments $method
    ) {
        parent::__construct($context, $logger, $method);
    }
    // @codingStandardsIgnoreEnd
}
