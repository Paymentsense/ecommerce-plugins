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

use Magento\Payment\Model\Method\AbstractMethod;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;

/**
 * Paymentsense Logger
 *
 * Log events of the Paymentsense module. Log files reside in the var/log/paymentsense directory.
 */
class PaymentsenseLogger extends Logger
{
    /**
     * @var AbstractMethod $method Payment method
     */
    protected $method;

    /**
     * @param string             $name       The logging channel
     * @param HandlerInterface[] $handlers   Optional stack of handlers
     * @param callable[]         $processors Optional array of processors
     * @param mixed              $method     Payment method
     */
    public function __construct(string $name, array $handlers = [], array $processors = [], $method = null)
    {
        parent::__construct($name, $handlers, $processors);
        $this->method = $method;
    }

    /**
     * Gets Log level
     */
    public function getLogLevel()
    {
        return $this->method->getConfigHelper()->getLogLevel();
    }

    /**
     * Logs error messages to the Paymentsense log
     *
     * Requires Log Level 1 or higher
     *
     * @param  string $message The log message
     * @param  array  $context The log context
     */
    public function error($message, array $context = []) : void
    {
        if ($this->getLogLevel()>=1) {
            $this->addRecord(parent::ERROR, $message, $context);
        }
    }

    /**
     * Logs warning messages to the Paymentsense log
     *
     * Requires Log Level 2 or higher
     *
     * @param  string $message The log message
     * @param  array  $context The log context
     */
    public function warning($message, array $context = []) : void
    {
        if ($this->getLogLevel()>=2) {
            $this->addRecord(parent::WARNING, $message, $context);
        }
    }

    /**
     * Logs info messages to the Paymentsense log
     *
     * Requires Log Level 3 or higher
     *
     * @param  string $message The log message
     * @param  array  $context The log context
     */
    public function info($message, array $context = []) : void
    {
        if ($this->getLogLevel()>=3) {
            $this->addRecord(parent::INFO, $message, $context);
        }
    }

    /**
     * Logs debug messages to the Paymentsense log
     *
     * Does not depend on the Log Level configuration.
     * For debugging only. Do not use in production.
     *
     * @param  string $message The log message
     * @param  array  $context The log context
     */
    public function debug($message, array $context = []) : void
    {
        $this->addRecord(parent::DEBUG, $message, $context);
    }
}
