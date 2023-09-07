<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html lang="en">
<head>
    <title>{$title}</title>
    <link href='https://fonts.googleapis.com/css?family=Roboto' rel='stylesheet' type='text/css'>
    <style>
        #paymentsenserp_form {
            position: relative;
            width: 600px;
            background-color: #ffffff;
            font-family: 'Roboto', sans-serif;
            border: solid 1px rgba(0, 0, 0, 0.15);
            margin: 2% auto;
        }
        #paymentsenserp_header {
            background-color: #f5f5f5;
            padding: 20px 0 20px 0;
            border-bottom: solid 1px rgba(0, 0, 0, 0.15);
            font-size: 20px;
            font-weight: normal;
            font-stretch: normal;
            font-style: normal;
            line-height: normal;
            letter-spacing: normal;
            text-align: center;
            color: rgba(0, 0, 0, 0.87);
        }
        #paymentsenserp_content {
            font-family: 'Roboto', sans-serif;
            padding: 20px;
        }
        #paymentsenserp-payment-div iframe { width: 100%; }
        #paymentsenserp-payment-div { padding: 0; }
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
        #paymentsenserp-errors-div {
            color: #b00020;
        }
        #paymentsenserp-button-div {
            padding: 5px 0 0 0;
            text-align: left;
        }
        #paymentsenserp-submit-payment-btn {
            visibility: hidden;
            min-width: 188px;
            height: 48px;
            padding: 12px 24px;
            background-color: #00857d;
            font-family: 'Roboto', sans-serif;
            font-size: 16px;
            font-weight: normal;
            font-stretch: normal;
            font-style: normal;
            line-height: 1.5;
            letter-spacing: 1px;
            text-align: center;
            color: #ffffff;
            border-radius: 24px;
            border: solid 1px #00857d;
        }
        #paymentsenserp-close-btn {
            visibility: hidden;
            width: 1rem;
            height: 1rem;
            padding: 0;
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            cursor: pointer;
            border: 0;
            background: transparent;
        }
        #paymentsenserp-span-btn {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0,0,0,0);
            border: 0;
        }
        #paymentsenserp-close-btn::before,
        #paymentsenserp-close-btn::after {
            content: '';
            width: 2px;
            height: 100%;
            background: rgba(0, 0, 0, 0.87);
            display: block;
            transform: rotate(45deg) translateX(0px);
            position: absolute;
            left: 50%;
            top: 0;
        }
        #paymentsenserp-close-btn::after {
            transform: rotate(-45deg) translateX(0px);
        }
    </style>
</head>
<body class="clear-body">
<div id="paymentsenserp_form">
    <div id="paymentsenserp_header">
        {$payment_of} {include file="common/price.tpl" value=$total}
        <button id="paymentsenserp-close-btn"><span id="paymentsenserp-span-btn"></span></button>
    </div>
    <div id="paymentsenserp_content">
        <p>{$message}</p>
        <div id="paymentsenserp-payment-div"></div>
        <div id="paymentsenserp-errors-div"></div>
        <div id="paymentsenserp-button-div">
            <button id="paymentsenserp-submit-payment-btn"></button>
        </div>
    </div>
</div>
{script src="js/lib/jquery/jquery.min.js"}
{scripts}{/scripts}
<script type="text/javascript" src="{$client_js_url}"></script>
<script type="text/javascript">
    const connect_e_config = {
        paymentDetails: {
            amount:       '{$payment_details_amount}',
            currencyCode: '{$payment_details_currency_code}',
            paymentToken: '{$payment_details_payment_token}',
        },
        containerId: "paymentsenserp-payment-div",
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
{if $payment_details_payment_token != ""}
    let connect_e;
    let btn_submit;
    let btn_cancel;
    let connect_e_script = document.createElement('script');
    connect_e_script.onload = initConnectE;
    connect_e_script.src = '{$client_js_url}';
    document.body.appendChild(connect_e_script);
{/if}
    function initConnectE() {
        connect_e = new Connect.ConnectE(connect_e_config, displayConnectEerrors);
        btn_submit = document.getElementById("paymentsenserp-submit-payment-btn");
        btn_submit.onclick = executePaymentsenseRpPayment;
        btn_cancel = document.getElementById("paymentsenserp-close-btn");
        btn_cancel.onclick = executePaymentsenseRpCancel;
        if (typeof jQuery !== 'undefined') {
            jQuery("body").on('DOMSubtreeModified', "#paymentsenserp-payment-div", function() {
                showButtons();
            });
        } else {
            showButtons();
        }
    }
    function executePaymentsenseRpPayment() {
        disableSubmitBtn();
        disableCancelBtn();
        connect_e.executePayment()
        .then(function(data) {
            redirectToReturnUrl(data);
        }).catch(function(data) {
            if (data.hasOwnProperty('statusCode')) {
                if (data.hasOwnProperty('message')) {
                    showErrorMsg(data.statusCode, data.message);
                } else {
                    showErrorMsg(data.statusCode);
                }
            }
            enableSubmitBtn();
            enableCancelBtn();
        });
    }
    function executePaymentsenseRpCancel() {
        if (confirm("{$confirm_order_cancellation}") === true) {
            cancelOrder();
        } else {
            btn_cancel.blur();
        }
    }
    function showButtons() {
        showSubmitBtn();
        showCancelBtn();
    }
    function showSubmitBtn() {
        enableSubmitBtn();
        btn_submit.style.visibility = "visible";
    }
    function showCancelBtn() {
        enableCancelBtn();
        btn_cancel.style.visibility = "visible";
    }
    function disableSubmitBtn() {
        btn_submit.innerHTML = "{$button_processing}";
        btn_submit.disabled = true;
    }
    function enableSubmitBtn() {
        btn_submit.innerHTML = "{$button_pay}";
        btn_submit.disabled = false;
    }
    function disableCancelBtn() {
        btn_cancel.disabled = true;
    }
    function enableCancelBtn() {
        btn_cancel.disabled = false;
    }
    function cancelOrder() {
        redirectToReturnUrl({ action: 'cancel' });
    }
    function redirectToReturnUrl(data) {
        let form = document.createElement("form");
        let form_action = "{$return_url}";
        form.action = form_action.replace(/&amp;/g, "&");
        form.method = "POST";
        data.paymentToken = "{$payment_details_payment_token}";
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
        errors_div.innerHTML = '';
        if (errors && errors.length) {
            let list = document.createElement("ul");
            errors.forEach(function(error) {
                let item = document.createElement("li");
                item.innerText = error.message;
                list.appendChild(item);
            });
            errors_div.appendChild(list);
        }
    }
    function showErrorMsg(err_no=0, err_msg='') {
        if (err_no === 401) {
            alert('An authentication error has occurred. The response from the gateway was: "' + err_msg + '". Please contact customer support.');
        } else {
            alert('An unexpected error has occurred. Please try again later.');
        }
    }
</script>
</body>
</html>
