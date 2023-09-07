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

define(
    [
        'jquery',
        'mage/storage',
        'mage/url',
        'Magento_Customer/js/model/customer',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function (
        $,
        storage,
        url,
        customer,
        customerData,
        quote,
        urlBuilder,
        Component,
        placeOrderAction,
        selectPaymentMethodAction,
        checkoutData,
        additionalValidators,
        fullScreenLoader
    ) {
        'use strict';

        const dataProviderUrl = url.build('paymentsense/remotepayments/dataprovider');

        let connectE                = null,
            remotePaymentsFormValid = false,
            orderProcessing         = false,
            paymentDetails          = {
                amount: null,
                currencyCode: null,
                paymentToken: null
            },
            connectEconfig  = {
                paymentDetails: {},
                containerId: "remotepayments-payment-div",
                fontCss: ['https://fonts.googleapis.com/css?family=Roboto'],
                styles: {
                    base: {
                        default: {
                            padding: "12px 16px 12px 16px",
                            width: "400px",
                            height: "48px",
                            borderRadius: "4px",
                            border: "solid 1px rgba(0, 0, 0, 0.15)",
                            fontFamily: "Roboto",
                            fontSize: "16px",
                            fontWeight: "normal",
                            fontStretch: "normal",
                            fontStyle: "normal",
                            lineHeight: "1.5",
                            letterSpacing: "0.15px",
                            color: "rgba(0, 0, 0, 0.87)",
                            marginBottom: "15px",
                            boxShadow: 'none',
                            backgroundColor: '#FFF'
                        },
                        focus: {
                            outline: "none",
                            borderWidth: "2px",
                        },
                        error: {
                            border: "solid 1px #b00020"
                        },
                        valid: {
                            border: "solid 1px #00857d"
                        },
                        label: {
                            display: "block",
                            width: "400px",
                            height: "24px",
                            fontFamily: "Roboto",
                            fontSize: "16px",
                            fontWeight: "normal",
                            fontStretch: "normal",
                            fontStyle: "normal",
                            lineHeight: "1.5",
                            letterSpacing: "0.15px",
                            color: "rgba(0, 0, 0, 0.87)",
                        }
                    },
                    cardIcon: {
                        visibility: "hidden"
                    }
                }
            };

        function initRemotePaymentsForm()
        {
            fullScreenLoader.startLoader();

            let coPaymentForm = document.getElementById("remotepayments-payment-form");
            if (coPaymentForm) {
                while (coPaymentForm.firstChild) {
                    coPaymentForm.removeChild(coPaymentForm.lastChild);
                }
            }

            let btn = document.getElementById("remotepayments-place-order-btn");
            if (btn) {
                btn.style.visibility = "hidden";
            }

            $.ajax(
                {
                    url: dataProviderUrl,
                    type: "POST",
                    data: {is_order: 0},
                    success: loadRemotePaymentsForm,
                    fail: ajaxErrorHandler,
                    error: ajaxErrorHandler
                }
            );
        }

        function loadRemotePaymentsForm(data)
        {
            if (data.accessToken !== undefined) {
                paymentDetails = {
                    amount: data.amount,
                    currencyCode: data.currencyCode,
                    paymentToken: data.accessToken
                };

                let textDiv = document.createElement("div");
                let textNode = document.createTextNode(data.message);
                textDiv.id = "before-remotepayments-payment-form-div";
                textDiv.className = "before-remotepayments-payment-form-msg";
                textDiv.style.visibility = "hidden";
                textDiv.appendChild(textNode);

                let paymentDiv = document.createElement("div");
                paymentDiv.id = "remotepayments-payment-div";

                let errorsDiv = document.createElement("div");
                errorsDiv.id = "remotepayments-errors-div";
                errorsDiv.classList.add("messages");

                let coPaymentForm = document.getElementById("remotepayments-payment-form");
                coPaymentForm.appendChild(textDiv);
                coPaymentForm.appendChild(paymentDiv);
                coPaymentForm.appendChild(errorsDiv);

                let connectEscript = document.createElement('script');
                connectEscript.onload = initConnectE;
                connectEscript.src = data.clientJsUrl;
                coPaymentForm.appendChild(connectEscript);
            } else {
                showErrorMsg();
            }
        }

        function initConnectE()
        {
            connectEconfig.paymentDetails = paymentDetails;
            connectE = new Connect.ConnectE(connectEconfig, displayConnectEerrors);
            $("body").on('DOMSubtreeModified', "#remotepayments-payment-div", afterRemotePaymentsFormLoaded);
        }

        function ajaxErrorHandler(jqXHR, textStatus, errorThrown)
        {
            fullScreenLoader.stopLoader();
            if ((jqXHR.status === 400) || (jqXHR.status === 401)) {
                showMessage(`An error has occurred. Please contact customer support.`);
            } else {
                showMessage('An unexpected error has occurred. Please try again later.');
            }
        }

        function showErrorMsg(errNo=0, errMsg='')
        {
            fullScreenLoader.stopLoader();
            if (errNo === 401) {
                alert(`An authentication error has occurred. The response from the gateway was: "${errMsg}". Please contact customer support.`);
            } else {
                alert('An unexpected error has occurred. Please try again later.');
            }
        }

        function showMessage(message)
        {
            let textDiv = document.createElement("div");
            let textNode = document.createTextNode(message);
            textDiv.id = "before-remotepayments-payment-form-div";
            textDiv.className = "before-remotepayments-payment-form-msg";
            textDiv.style.visibility = "visible";
            textDiv.classList.add("message");
            textDiv.classList.add("message-error");
            textDiv.classList.add("error");
            textDiv.appendChild(textNode);
            let coPaymentForm = document.getElementById("remotepayments-payment-form");
            coPaymentForm.appendChild(textDiv);
        }

        function afterRemotePaymentsFormLoaded()
        {
            let textDiv = document.getElementById("before-remotepayments-payment-form-div");
            textDiv.style.visibility = "visible";

            let btn = document.getElementById("remotepayments-place-order-btn");
            btn.style.visibility = "visible";
            fullScreenLoader.stopLoader();
        }

        function displayConnectEerrors(errors)
        {
            let errorsDiv = document.getElementById("remotepayments-errors-div");
            errorsDiv.innerHTML = '';
            remotePaymentsFormValid = ! (errors && errors.length);
            if (! remotePaymentsFormValid) {
                let list = document.createElement("div");
                list.classList.add("message");
                list.classList.add("message-error");
                list.classList.add("error");
                errors.forEach(function (error) {
                    let item = document.createElement("div");
                    item.classList.add("checkout-cart-validationmessages-message-error");
                    item.innerText = error.message;
                    list.appendChild(item);
                });
                errorsDiv.appendChild(list);
            }
        }

        function executePayment(data)
        {
            if (data.accessToken !== undefined) {
                let returnUrl = data.returnUrl;
                paymentDetails = {
                    amount: data.amount,
                    currencyCode: data.currencyCode,
                    paymentToken: data.accessToken
                };

                connectE.updateAccessToken(paymentDetails);
                fullScreenLoader.stopLoader();
                connectE.executePayment()
                .then(function (data) {
                    redirectToReturnUrl(returnUrl, data);
                }).catch(function (data) {
                    if (data.hasOwnProperty('statusCode')) {
                        if (data.hasOwnProperty('message')) {
                            showErrorMsg(data.statusCode, data.message);
                        } else {
                            showErrorMsg(data.statusCode);
                        }
                    } else {
                        showErrorMsg();
                    }
                });
            } else {
                showErrorMsg();
            }
        }

        function redirectToReturnUrl(returnUrl, data)
        {
            setTimeout(
                function () {
                    performRedirectToReturnUrl(returnUrl, data);
                },
                2000
            );
        }

        function performRedirectToReturnUrl(returnUrl, data)
        {
            let form = document.createElement("form");
            form.action = returnUrl;
            form.method = "POST";
            data.paymentToken = paymentDetails.paymentToken;
            for (let prop in data) {
                if (!data.hasOwnProperty(prop)) {
                    continue;
                }
                let element = document.createElement("input");
                element.name = prop;
                element.value = data[prop];
                element.type = "hidden";
                form.appendChild(element);
            }
            document.body.appendChild(form);
            form.submit();
        }

        function isRemotePaymentsFormValid()
        {
            connectE.validate()
            .then(() => {})
            .catch(errs => {});
            return remotePaymentsFormValid;
        }

        return Component.extend({
            defaults: {
                template: 'Paymentsense_RemotePayments/payment/method/remotepayments/form'
            },

            initialize: function () {
                let self = this;
                self._super();
                initRemotePaymentsForm();
                return self;
            },

            placeOrder: function (data, event) {
                let result = false;
                if (orderProcessing === false) {
                    orderProcessing = true;
                    if (event) {
                        event.preventDefault();
                    }
                    let self = this,
                        placeOrder,
                        emailValidationResult = customer.isLoggedIn(),
                        loginFormSelector = 'form[data-role=email-with-possible-login]';
                    if (!customer.isLoggedIn()) {
                        $(loginFormSelector).validation();
                        emailValidationResult = Boolean($(loginFormSelector + ' input[name=username]').valid());
                    }
                    if (emailValidationResult && this.validate() && additionalValidators.validate() && isRemotePaymentsFormValid()) {
                        this.isPlaceOrderActionAllowed(false);
                        fullScreenLoader.startLoader();
                        placeOrder = placeOrderAction(this.getData(), false, this.messageContainer);
                        $.when(placeOrder).fail(function () {
                            self.isPlaceOrderActionAllowed(true);
                            fullScreenLoader.stopLoader();
                        }).done(this.afterPlaceOrder.bind(this));
                        result = true;
                    }
                    orderProcessing = false;
                }
                return result;
            },

            selectPaymentMethod: function () {
                initRemotePaymentsForm();
                selectPaymentMethodAction(this.getData());
                checkoutData.setSelectedPaymentMethod(this.item.method);
                return true;
            },

            afterPlaceOrder: function () {
                $.ajax(
                    {
                        url: dataProviderUrl,
                        type: "POST",
                        data: {is_order: 1},
                        success: executePayment,
                        fail: ajaxErrorHandler,
                        error: ajaxErrorHandler
                    }
                );
            }
        });
    }
);
