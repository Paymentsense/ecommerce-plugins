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
 * Diagnostic Message helper
 */
class DiagnosticMessage extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * CSS class names
     */
    private const SUCCESS_CLASS_NAME = 'success-text';
    private const WARNING_CLASS_NAME = 'warning-text';
    private const ERROR_CLASS_NAME   = 'error-text';

    /**
     * Message types
     */
    private const MESSAGE_TYPE_STATUS = 'status';

    /**
     * Gets the payment method status
     *
     * @param bool $configured Specifies whether the payment method is configured
     * @param bool $secure     Specifies whether the payment method is secure
     *
     * @return array
     */
    public function getStatusMessage($configured, $secure)
    {
        switch (true) {
            case ! $configured:
                $result = $this->buildErrorStatusMessage(
                    __('Unavailable (Payment method not configured)')
                );
                break;
            case ! $secure:
                $result = $this->buildErrorStatusMessage(
                    __('Unavailable (SSL/TLS not configured)')
                );
                break;
            default:
                $result = $this->buildSuccessStatusMessage(
                    __('Enabled')
                );
                break;
        }
        return $result;
    }

    /**
     * Builds a localised success status message
     *
     * @param string $text
     *
     * @return array
     */
    public function buildSuccessStatusMessage($text)
    {
        return $this->buildMessage($text, self::SUCCESS_CLASS_NAME, self::MESSAGE_TYPE_STATUS);
    }

    /**
     * Builds a localised warning status message
     *
     * @param string $text
     *
     * @return array
     */
    public function buildWarningStatusMessage($text)
    {
        return $this->buildMessage($text, self::WARNING_CLASS_NAME, self::MESSAGE_TYPE_STATUS);
    }

    /**
     * Builds a localised error status message
     *
     * @param string $text
     *
     * @return array
     */
    public function buildErrorStatusMessage($text)
    {
        return $this->buildMessage($text, self::ERROR_CLASS_NAME, self::MESSAGE_TYPE_STATUS);
    }

    /**
     * Builds a localised message
     *
     * @param string $text
     * @param string $className
     * @param string $messageType
     *
     * @return array
     */
    private function buildMessage($text, $className, $messageType)
    {
        return [
            $messageType . 'Text'      => $text,
            $messageType . 'ClassName' => $className
        ];
    }
}
