<?php
/**
 * Copyright (C) 2021 Paymentsense Ltd.
 *
 * This program is free software: you can redistribute it and/or modify it under the terms
 * of the AFL Academic Free License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the AFL Academic Free License for more details. You should have received a copy of the
 * AFL Academic Free License along with this program. If not, see <http://opensource.org/licenses/AFL-3.0/>.
 *
 *  @author     Paymentsense <devsupport@paymentsense.com>
 *  @copyright  2021 Paymentsense Ltd.
 *  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/**
 * Message Abstract Controller
 */
abstract class PaymentsenserpMessageAbstractController extends ModuleFrontController
{
    /**
     * Response title messages
     */
    const MSG_TITLE_FAIL  = 'Payment Failed';
    const MSG_TITLE_ERROR = 'Payment Processing Error';

    /**
     * Response error messages
     */
    const MSG_ORDER_FAILED         = 'Payment failed with message "%s". Please check your card details and try again.';
    const MSG_NOT_CONFIGURED       = 'The plugin is not configured. Please contact customer support.';
    const MSG_RESP_RETRIEVE_ERROR  = 'Payment status is unknown. Please contact customer support quoting your order #%1$s'
    . ' and the message "%2$s" and do not retry the payment for this order unless you are instructed to do so.';
    const MSG_ORDER_CREATION_ERROR = 'Payment processed successfully. An error occurred during order creation.'
    . ' Please contact customer support.';
    const MSG_EXCEPTION            = 'An exception with message "%s" has been thrown. Please contact customer support.';
    const MSG_EMPTY_TOKEN          = 'Access token is empty.';
    const MSG_SESSION_EXPIRED      = 'Your session has expired.';
    const MSG_SECURE_KEY_MISMATCH  = 'An error occurred. Please contact customer support.';
    const MSG_INVALID_CART_ID      = 'Invalid Cart ID %d.';
    const MSG_ORDER_ALREADY_EXISTS = 'An order for Cart ID %d already exists.';

    /**
     * Shows a fail message
     *
     * @param string $message The fail message
     *
     * @throws PrestaShopException
     */
    protected function showFailMessage($message)
    {
        $templateVars = [
            'title'        => self::MSG_TITLE_FAIL,
            'message'      => $message,
            'checkout_url' => $this->getOrderCheckoutUrl(),
        ];
        $this->showMessage($templateVars);
    }

    /**
     * Shows an error message
     *
     * @param string $message The error message
     *
     * @throws PrestaShopException
     */
    protected function showErrorMessage($message)
    {
        $templateVars = [
            'title'        => self::MSG_TITLE_ERROR,
            'message'      => $message,
            'checkout_url' => $this->getOrderCheckoutUrl(),
        ];
        $this->showMessage($templateVars);
    }

    /**
     * Shows the message
     *
     * @param array $templateVars
     *
     * @throws PrestaShopException
     */
    protected function showMessage($templateVars)
    {
        $this->context->smarty->assign('paymentsenserp', $templateVars, true);
        $this->setTemplate('module:paymentsenserp/views/templates/front/payment_message.tpl');
    }

    /**
     * Gets the URL of the order checkout page
     *
     * @return string A string containing a URL
     */
    protected function getOrderCheckoutUrl()
    {
        return $this->context->link->getPageLink('order', true);
    }
}
