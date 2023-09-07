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

use Symfony\Component\HttpFoundation\Response;

require_once _PS_MODULE_DIR_ . 'paymentsenserp/controllers/front/base/message.php';

/**
 * Customer Redirect Front-End Controller
 *
 * Handles the processing of the payment result and shows the payment status
 */
class PaymentsenserpCustomerRedirectModuleFrontController extends PaymentsenserpMessageAbstractController
{
    public $php_self = 'paymentsenserp-customer-redirect';

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
        $this->processPaymentResult();
    }

    /**
     * Registers CSS
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
        return true;
    }

    /**
     * Processes the payment result
     *
     * @throws PrestaShopException
     */
    private function processPaymentResult()
    {
        try {
            if (!$this->module->isConfigured()) {
                $this->showErrorMessage(self::MSG_NOT_CONFIGURED);
                return;
            }

            $cartId = $this->module->retrieveCartId();
            if ($cartId !== $this->context->cart->id) {
                $this->showErrorMessage(self::MSG_SESSION_EXPIRED);
                return;
            }

            if ($this->context->cart->secure_key !== $this->context->customer->secure_key) {
                $this->showErrorMessage(self::MSG_SECURE_KEY_MISMATCH);
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

            $httpCode   = null;
            $statusCode = Paymentsenserp::TRX_NOT_AVAILABLE;
            $message    = 'An error occurred while communicating with the payment gateway';

            $request = [
                'url'     => $this->module->getApiEndpointUrl(Paymentsenserp::API_REQUEST_PAYMENTS, $accessToken),
                'method'  => Paymentsenserp::API_METHOD_GET,
                'headers' => $this->module->buildRequestHeaders(),
            ];

            $response = '';
            $info     = [];

            $curlErrNo = $this->module->performCurl($request, $response, $info, $curlErrMsg);
            if (0 === $curlErrNo) {
                $httpCode = $info['http_code'];
                if (Response::HTTP_OK === $httpCode) {
                    $response   = json_decode($response, true);
                    $statusCode = $this->module->getArrayElement($response, 'statusCode', '');
                    $message    = $this->module->getArrayElement($response, 'message', '');
                }
            }

            $cart = new Cart($cartId);

            if (!Validate::isLoadedObject($cart)) {
                $this->showErrorMessage(sprintf(self::MSG_INVALID_CART_ID, $cartId));
                return;
            }
            if ($cart->orderExists()) {
                $this->showErrorMessage(sprintf(self::MSG_ORDER_ALREADY_EXISTS, $cart->id));
                return;
            }

            $amount = number_format($cart->getOrderTotal(true, Cart::BOTH), 2, '.', '');

            $orderState = $this->module->getOrderState($statusCode);

            $paymentStatus = $this->module->getPaymentStatus($statusCode);

            switch ($paymentStatus) {
                case Paymentsenserp::PAYMENT_STATUS_CODE_SUCCESS:
                    if (!$this->module->createOrder($cart, $orderState, $message, $amount)) {
                        $this->showErrorMessage(self::MSG_ORDER_CREATION_ERROR);
                        return;
                    }
                    $this->showOrderConfirmation($cart->id);
                    break;
                case Paymentsenserp::PAYMENT_STATUS_CODE_FAIL:
                    if (!$this->module->createOrder($cart, $orderState, $message, $amount)) {
                        $this->showErrorMessage(self::MSG_ORDER_CREATION_ERROR);
                        return;
                    }

                    $this->restoreCart($cart);

                    $this->showFailMessage(sprintf(self::MSG_ORDER_FAILED, $message));
                    break;
                case Paymentsenserp::PAYMENT_STATUS_CODE_UNKNOWN:
                default:
                    $diagnosticMessage = ( 0 === $curlErrNo )
                        ? sprintf(
                            'HTTP Code: %1$s; Status Code %2$s; Message: %3$s;',
                            $httpCode,
                            $statusCode,
                            $response
                        )
                        : sprintf(
                            'cURL Error No: %1$s; cURL Error Message: %2$s;',
                            $curlErrNo,
                            $curlErrMsg
                        );
                    $this->showErrorMessage(sprintf(self::MSG_RESP_RETRIEVE_ERROR, $cartId, $diagnosticMessage));
                    break;
            }
        } catch (Exception $exception) {
            $this->showErrorMessage(sprintf(self::MSG_EXCEPTION, $exception->getMessage()));
        }
    }

    /**
     * Restores the cart
     *
     * @param Cart $cart
     *
     * @return bool
     */
    private function restoreCart($cart)
    {
        $result = false;
        $duplication = $cart->duplicate();
        if (is_array($duplication) && $duplication['success'] && Validate::isLoadedObject($duplication['cart'])) {
            $newCart = $duplication['cart'];
            $this->context->cart            = $newCart;
            $this->context->cookie->id_cart = $newCart->id;
            $this->context->cookie->write();
            $result = true;
        }
        return $result;
    }

    /**
     * Redirects the customer to the order confirmation page
     *
     * @param string $cartId Cart ID
     */
    private function showOrderConfirmation($cartId)
    {
        Tools::redirect(
            'index.php?controller=order-confirmation' .
            '&id_cart=' . (int) $cartId .
            '&id_module=' . (int) $this->module->id .
            '&id_order=' . $this->module->currentOrder .
            '&key=' . $this->context->customer->secure_key
        );
    }
}
