<?php
/**
 * Paymentsense Remote Payments Plugin for VirtueMart 3
 * Version: 1.0.0
 *
 * This program is free software; you cancan redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @version     1.0.0
 * @author      Paymentsense
 * @copyright   2021 Paymentsense Ltd.
 * @license     https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('_JEXEC') or die('Restricted access');

vmJsApi::css( 'paymentsense_rp','plugins/vmpayment/paymentsense_rp/paymentsense_rp/assets/css/');

$js = '
    const connect_e_config = {
        paymentDetails: {
            amount:       \'' . $viewData['amount'] . '\',
            currencyCode: \'' . $viewData['currency_code'] . '\',
            paymentToken: \'' . $viewData['payment_token'] . '\',
        },
        containerId: "paymentsenserp-payment-div",
        fontCss: [\'https://fonts.googleapis.com/css?family=Roboto\'],
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
                    boxShadow: \'none\',
                    backgroundColor: \'#FFF\'
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
    window.onload = hideBreadcrumb;
    setTitle(\'' . $viewData['title'] . '\');
    setDescription(\'' . $viewData['message'] . '\');
    
    if (\'' . ($viewData['error_message'] != '') . '\') {
        let error_message_encoded = \'' . $viewData['error_message'] . '\';
        let init_errors = [{ message: error_message_encoded}];
        if (init_errors && init_errors.length) {
            displayConnectEerrors(init_errors)
        }
    }
    if (connect_e_config.paymentDetails.paymentToken != "") {
        let connect_e;
        let btn_submit;
        let connect_e_script = document.createElement(\'script\');
        connect_e_script.onload = initConnectE;
        connect_e_script.src = \'' . $viewData['client_js_url'] . '\';
        document.body.appendChild(connect_e_script);
    }
    function initConnectE() {
        connect_e = new Connect.ConnectE(connect_e_config, displayConnectEerrors);
        btn_submit = document.getElementById("paymentsenserp-submit-payment-btn");
        btn_submit.onclick = executePaymentsenseRpPayment;
        if (typeof jQuery !== \'undefined\') {
            jQuery("body").on(\'DOMSubtreeModified\', "#paymentsenserp-payment-div", function() {
                showButtons();
            });
        } else {
            showButtons();
        }
    }
    function hideBreadcrumb() {
        var elements = document.getElementsByClassName(\'breadcrumb\')
        for (var i = 0; i < elements.length; i++){
            elements[i].style.display = \'none\';
        }
    }
    function executePaymentsenseRpPayment() {
        disableSubmitBtn();
        connect_e.executePayment()
        .then(function(data) {
            redirectToReturnUrl(data);
        }).catch(function(data) {
            if (data.hasOwnProperty(\'statusCode\')) {
                if (data.hasOwnProperty(\'message\')) {
                    showErrorMsg(data.statusCode, data.message);
                } else {
                    showErrorMsg(data.statusCode);
                }
            }
            enableSubmitBtn();
        });
    }
    function showButtons() {
        showSubmitBtn();
    }
    function showSubmitBtn() {
        enableSubmitBtn();
        btn_submit.style.visibility = "visible";
    }
    function disableSubmitBtn() {
        btn_submit.innerHTML = "' . $viewData['button_processing'] . '";
        btn_submit.disabled = true;
    }
    function enableSubmitBtn() {
        btn_submit.innerHTML = "' . $viewData['button_pay'] . '";
        btn_submit.disabled = false;
    }
    function redirectToReturnUrl(data) {
        let form = document.createElement("form");
        let form_action = "' . $viewData['return_url'] . '";
        form.action = form_action.replace(/&amp;/g, "&");
        form.method = "POST";
        data.paymentToken = "' . $viewData['payment_token'] . '";
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
    function displayConnectEerrors(errors) {
        let errors_div = document.getElementById("paymentsenserp-errors-div");
        errors_div.innerHTML = \'\';
        if (errors && errors.length) {
            errors_div.classList.add("alert");
            errors_div.classList.add("alert-danger");
            let list = document.createElement("ul");
            errors.forEach(function(error) {
                let item = document.createElement("li");
                item.innerText = error.message;
                list.appendChild(item);
            });
            errors_div.appendChild(list);
        } else {
            errors_div.classList.remove("alert");
            errors_div.classList.remove("alert-danger");
        }
    }
    function setTitle(text) {
         document.title = text;
    }
    function setDescription(text) {
        let description_div = document.getElementById("paymentsenserp_description");
        description_div.innerHTML = text;
    }
    function showErrorMsg(err_no=0, err_msg=\'\') {
        if (err_no === 401) {
            alert(\'An authentication error has occurred. The response from the gateway was: "\' + err_msg + \'". Please contact customer support.\');
        } else {
            alert(\'An unexpected error has occurred. Please try again later.\');
        }
    }
';
vmJsApi::addJScript('vm.paymentsenserpForm', $js);
?>
<h4 id="paymentsenserp_description"></h4>
<div id="paymentsenserp-payment-div"></div>
<div id="paymentsenserp-errors-div"></div>
<div id="paymentsenserp-buttons-div">
<div class="checkout-button-top">
<div id="paymentsenserp-button-div"><button id="paymentsenserp-submit-payment-btn" class="vm-button-correct"></button></div>
</div>
</div>
