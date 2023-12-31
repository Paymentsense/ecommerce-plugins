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

/**
 * Logger
 *
 * Uses the PaymentsenseLogger class writing to the log files in the /var/log/paymentsense directory.
 * Failback to the Monolog Logger class writing to the system log.
 */
class Logger extends \Magento\Payment\Model\Method\Logger
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param mixed $method
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Exception
     */
    public function __construct($method)
    {
        if ($method instanceof \Magento\Payment\Model\Method\AbstractMethod) {
            $name = $method->getCode();
            $handler = new \Magento\Framework\Logger\Handler\Base(
                new \Magento\Framework\Filesystem\Driver\File(),
                BP . '/var/log/paymentsense/',
                $name . '.log'
            );
            $logger = new PaymentsenseLogger($name, [$handler], [], $method);
        } else {
            // Failback
            $name = 'paymentsense_remotepayments';
            $handler = new \Magento\Framework\Logger\Handler\Base(
                new \Magento\Framework\Filesystem\Driver\File(),
                BP . '/var/log/system.log'
            );
            $logger = new \Monolog\Logger($name, [$handler]);
            $logger->error(
                'An error occurred while trying to initialise logger helper: Invalid payment method.'
            );
        }
        parent::__construct($logger);
        $this->logger = $logger;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }
}
