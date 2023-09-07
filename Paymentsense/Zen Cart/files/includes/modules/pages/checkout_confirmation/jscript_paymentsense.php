<?php

/**
 * Javascript to prep functionality for Paymentsense - Remote Payments module.
 *
 * @license GNU Public License V2.0
 * @version 1.0.0
 */
if (!defined('MODULE_PAYMENT_PAYMENTSENSE_STATUS') || MODULE_PAYMENT_PAYMENTSENSE_STATUS != 'True') {
    return false;
}

if (empty($paymentsense)) {
    return false;
}

$jsurl = 'https://web.e.test.connect.paymentsense.cloud/assets/js/client.js';

if (MODULE_PAYMENT_PAYMENTSENSE_GATEWAY_ENVIRONMENT === 'Production') {
    $jsurl = 'https://web.e.connect.paymentsense.cloud/assets/js/client.js';
}
?>
<script type="text/javascript" src="<?php echo $jsurl; ?>"></script>
<script type="text/javascript">
    $('#paymentsense-rp-payment-div').ready(function () {
        const settings = $('#paymentsense-rp-payment-div').data();
        const connectEConfig = {
            paymentDetails: {
                amount: JSON.stringify(settings.amount),
                currencyCode: JSON.stringify(settings.currencyCode),
                paymentToken: settings.accessCode
            },
            containerId: "paymentsense-rp-payment-div",
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

        if (jQuery('#paymentsense-rp-payment-div iframe').length !== 0) {
            return;
        }
        let connectE = new Connect.ConnectE(connectEConfig, displayConnectEErrors);
        let btn = document.querySelector("input.button_confirm_order");
        btn.onclick = executePaymentsenseRpPayment;

        if (typeof jQuery !== 'undefined') {
            jQuery("body").on('DOMSubtreeModified', "#paymentsense-rp-payment-div", function () {
                enableSubmitBtn();
            });
        } else {
            enableSubmitBtn();
        }

        function executePaymentsenseRpPayment() {
            disableSubmitBtn();
            connectE.executePayment()
                .then(function (data) {
                    redirectToReturnUrl(data);
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
                enableSubmitBtn();
            });
        }

        function disableSubmitBtn() {
            btn.style.visibility = "visible";
            btn.innerHTML = "Processing...";
            btn.disabled = true;
        }

        function enableSubmitBtn() {
            btn.style.visibility = "visible";
            btn.innerHTML = 'Pay with Paymentsense';
            btn.disabled = false;
        }

        function redirectToReturnUrl(data) {
            let form = document.createElement("form");
            form.action = settings.returnUrl;
            form.method = "POST";
            data.paymentToken = settings.accessCode;
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

        function displayConnectEErrors(errors) {
            let errorsDiv = document.getElementById("paymentsense-rp-errors-div");
            errorsDiv.innerHTML = '';
            if (errors && errors.length) {
                let list = document.createElement("ul");
                errors.forEach(function (error) {
                    let item = document.createElement("li");
                    item.innerText = error.message;
                    list.appendChild(item);
                });
                errorsDiv.appendChild(list);
            }
        }

        function showErrorMsg(errNo = 0, errMsg = '') {
            if (errNo === 401) {
                alert('An authentication error has occurred. The response from the gateway was: "'
                    + errMsg + '". Please contact customer support.');
            } else {
                alert('An unexpected error has occurred. Please try again later.');
            }
        }
    })
</script>
<style>
    #paymentsense-rp-payment-div iframe { width: 100%;}
    #paymentsense-rp-payment-div {
        padding: 0;
    }
    iframe.threeDs {
        width: 370px;
        height: 366px;
        margin: 100px 0 0 -175px;
        position: fixed;
        top: 0;
        left: 50%;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.6);
        background-color: #FFF;
    }
</style>
