

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.initPaymentSenseRemotePaymentsCheckout = {
    attach: function (context) {

      $('body', context).once('#paymentsense-rp-payment-div').each(function () {
        const connectEconfig = {
          paymentDetails: {
            amount: JSON.stringify(drupalSettings.commerce_remotepayments.amount),
            currencyCode:  drupalSettings.commerce_remotepayments.currencyCode,
            paymentToken: drupalSettings.commerce_remotepayments.accessCode
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

        if(jQuery('#paymentsense-rp-payment-div iframe').length !== 0) {
          return;
        }
        let connectE = new Connect.ConnectE(connectEconfig, displayConnectEerrors);
        let btn = document.getElementById("paymentsense-rp-submit-payment-btn");
        btn.onclick = executePaymentsensePayment;

        if (typeof jQuery !== 'undefined') {
          jQuery("body").on('DOMSubtreeModified', "#paymentsense-rp-payment-div", function () {
            enableSubmitBtn();
          });
        } else {
          enableSubmitBtn();
        }

        function executePaymentsensePayment() {
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
          form.action = drupalSettings.commerce_remotepayments.returnUrl;
          form.method = "POST";
          data.paymentToken = drupalSettings.commerce_remotepayments.accessCode;
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
            alert('An authentication error has occurred. The response from the gateway was: "' + errMsg + '". Please contact customer support.');
          } else {
            alert('An unexpected error has occurred. Please try again later.');
          }
        }
      })
    }
  };

})(jQuery, Drupal, drupalSettings);
