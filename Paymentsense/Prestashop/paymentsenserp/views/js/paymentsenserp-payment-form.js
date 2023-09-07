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

const connectEconfig = {
    paymentDetails: paymentsenserpConfig.paymentDetails,
    containerId: 'paymentsenserp-payment-div',
    fontCss: ['https://fonts.googleapis.com/css?family=Roboto'],
    styles: {
        base: {
            default: {
                padding: '12px 16px 12px 16px',
                width: '400px',
                height: '48px',
                borderRadius: '4px',
                border: 'solid 1px rgba(0, 0, 0, 0.15)',
                fontFamily: 'Roboto',
                fontSize: '16px',
                fontWeight: 'normal',
                fontStretch: 'normal',
                fontStyle: 'normal',
                lineHeight: '1.5',
                letterSpacing: '0.15px',
                color: 'rgba(0, 0, 0, 0.87)',
                marginBottom: '15px',
                boxShadow: 'none',
                backgroundColor: '#FFF',
            },
            focus: {
                outline: 'none',
                borderWidth: '2px',
            },
            error: {
                border: 'solid 1px #b00020',
            },
            valid: {
                border: 'solid 1px #00857d',
            },
            label: {
                display: 'block',
                width: '400px',
                height: '24px',
                fontFamily: 'Roboto',
                fontSize: '16px',
                fontWeight: 'normal',
                fontStretch: 'normal',
                fontStyle: 'normal',
                lineHeight: '1.5',
                letterSpacing: '0.15px',
                color: 'rgba(0, 0, 0, 0.87)',
            }
        },
        cardIcon: {
            visibility: "hidden"
        }
    }
};
let connectE = new Connect.ConnectE(connectEconfig, displayConnectEerrors);
let paymentsenserpBtn = document.getElementById('paymentsenserp-submit-payment-btn');
paymentsenserpBtn.onclick = executePaymentsenseRpPayment;
let paymentsenserpLinks = document.getElementById('paymentsenserp-links');

if (typeof $ !== 'undefined') {
    $('body').on('DOMSubtreeModified', '#paymentsenserp-payment-div', function () {
        enableSubmitBtn();
    });
} else {
    enableSubmitBtn();
}

function executePaymentsenseRpPayment()
{
    disableSubmitBtn();
    connectE.executePayment()
    .then(function (data) {
        redirectToReturnUrl(data);
    }).catch(function (data) {
        if (Object.prototype.hasOwnProperty.call(data, 'statusCode')) {
            if (Object.prototype.hasOwnProperty.call(data, 'message')) {
                showErrorMsg(data.statusCode, data.message);
            } else {
                showErrorMsg(data.statusCode);
            }
        }
        enableSubmitBtn();
    });
}

function disableSubmitBtn()
{
    paymentsenserpBtn.style.visibility = 'visible';
    paymentsenserpBtn.innerHTML = 'Processing...';
    paymentsenserpBtn.disabled = true;
    paymentsenserpLinks.style.visibility = 'hidden';
}

function enableSubmitBtn()
{
    paymentsenserpBtn.style.visibility = 'visible';
    paymentsenserpBtn.innerHTML = 'Pay with Paymentsense';
    paymentsenserpBtn.disabled = false;
    paymentsenserpLinks.style.visibility = 'visible';
}

function redirectToReturnUrl(data)
{
    let form = document.createElement('form');
    form.action = decodeUrl(paymentsenserpConfig.returnUrl);
    form.method = 'POST';
    data.cartId = paymentsenserpConfig.cartId;
    data.paymentToken = paymentsenserpConfig.paymentDetails.paymentToken;
    for (let prop in data) {
        if (!Object.prototype.hasOwnProperty.call(data, prop)) {
            continue;
        }

        let element = document.createElement('input');
        element.name = prop;
        element.value = data[prop];
        element.type = 'hidden';
        form.appendChild(element);
    }
    document.body.appendChild(form);
    form.submit();
}

function displayConnectEerrors(errors)
{
    let errorsDiv = document.getElementById('paymentsenserp-errors-div');
    errorsDiv.innerHTML = '';
    if (errors && errors.length) {
        let list = document.createElement('ul');
        list.classList.add('alert');
        list.classList.add('alert-danger');
        errors.forEach(function (error) {
            let item = document.createElement('li');
            item.innerText = error.message;
            list.appendChild(item);
        });
        errorsDiv.appendChild(list);
    }
}

function showErrorMsg(errNo, errMsg='')
{
    if (errNo === 401) {
        alert(`An authentication error has occurred. The response from the gateway was: ${errMsg}. Please contact customer support.`);
    } else {
        alert(`An unexpected error has occurred (Error Number: ${errNo}, Error Message: "${errMsg}"). Please try again later.`);
    }
}

function decodeUrl(url)
{
    let textarea = document.createElement('textarea');
    textarea.innerHTML = url;
    return textarea.value;
}
