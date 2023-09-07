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

require_once _PS_MODULE_DIR_ . 'paymentsenserp/controllers/front/base/message.php';

/**
 * Payment Form Front-End Controller
 *
 * Shows the payment form
 */
class PaymentsenserpPaymentFormModuleFrontController extends PaymentsenserpMessageAbstractController
{
    public $php_self = 'paymentsenserp-payment-form';

    public $ssl = true;

    /**
     * @var Paymentsenserp
     */
    public $module;

    /**
     * @see FrontController::initContent()
     *
     * @throws PrestaShopException
     */
    public function initContent()
    {
        parent::initContent();
        $this->processPaymentFormRequest();
    }

    /**
     * Registers CSS and JS
     *
     * @return bool
     */
    public function setMedia()
    {
        parent::setMedia();
        $this->registerStylesheet(
            'module-paymentsenserp-style',
            'modules/'.$this->module->name.'/views/css/default.css',
            [
                'media'    => 'all',
                'priority' => 100,
            ]
        );
        $this->registerJavascript(
            'connect-e-client-lib',
            $this->module->getClientJsUrl(),
            [
                'priority'  => 100,
                'server'    => 'remote',
                'position'  => 'bottom',
                'attribute' => 'async',
            ]
        );
        $this->registerJavascript(
            'module-paymentsenserp-lib',
            'modules/'.$this->module->name.'/views/js/paymentsenserp-payment-form.js',
            [
                'priority'  => 101,
                'attribute' => 'async',
            ]
        );
        return true;
    }

    /**
     * Processes the request for the payment form
     *
     * @throws PrestaShopException
     */
    private function processPaymentFormRequest()
    {
        try {
            if (!$this->module->isConfigured()) {
                $this->showErrorMessage(self::MSG_NOT_CONFIGURED);
                return;
            }

            $cartId = $this->module->retrieveCartId();
            if ($cartId !== $this->context->cart->id) {
                Tools::redirect('index.php');
                return;
            }

            if ($this->context->cart->secure_key !== $this->context->customer->secure_key) {
                Tools::redirect('index.php');
                return;
            }

            $accessToken = Tools::getValue('accessToken');
            if (empty($accessToken)) {
                $accessToken = Tools::getValue('paymentToken');
            }

            if (empty($accessToken)) {
                $this->showErrorMessage(self::MSG_EMPTY_TOKEN);
                return;
            }

            $this->outputPaymentForm();
        } catch (Exception $exception) {
            $this->showErrorMessage(sprintf(self::MSG_EXCEPTION, $exception->getMessage()));
        }
    }

    /**
     * Outputs the payment form
     *
     * @throws PrestaShopException
     */
    private function outputPaymentForm()
    {
        $templateVars = [
            'title'                         => $this->module->getModuleTitle(),
            'message'                       => $this->module->getModuleDescription(),
            'checkout_url'                  => $this->context->link->getPageLink('order', true),
            'payment_details_amount'        => Tools::getValue('amount'),
            'payment_details_currency_code' => Tools::getValue('currencyCode'),
            'payment_details_payment_token' => Tools::getValue('paymentToken'),
            'cart_id'                       => Tools::getValue('cartId'),
            'return_url'                    => $this->module->getCustomerRedirectUrl(),
        ];
        $this->context->smarty->assign('paymentsenserp', $templateVars, true);
        $this->setTemplate('module:paymentsenserp/views/templates/front/payment_form.tpl');
    }
}
