<?php
/**
 * Paymentsense Remote Payments for WooCommerce Template
 *
 * @package    Paymentsense_Remote_Payments_For_WooCommerce
 * @subpackage Paymentsense_Remote_Payments_For_WooCommerce/templates
 * @author     Paymentsense
 * @link       http://www.paymentsense.co.uk/
 */

/**
 * Exit if accessed directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

?>
<p>
	<?php echo wp_kses_post( wpautop( $description ) ); ?>
</p>
<script type="text/javascript" src="<?php echo esc_url( $client_js_url ); ?>"></script>
<link rel="stylesheet" href="<?php echo esc_url( $css_url ); ?>" media="all" />

<div id="paymentsense-rp-payment-div"></div>
<div id="paymentsense-rp-errors-div"></div>
<div id="paymentsense-rp-button-div"><button id="paymentsense-rp-submit-payment-btn" class="button alt"></button></div>

<script type="text/javascript">
	const connectEconfig = {
		paymentDetails: {
			amount: "<?php echo esc_attr( $amount ); ?>",
			currencyCode: "<?php echo esc_attr( $currency_code ); ?>",
			paymentToken: "<?php echo esc_attr( $access_token ); ?>"
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
	let connectE = new Connect.ConnectE(connectEconfig, displayConnectEerrors);
	let btn = document.getElementById("paymentsense-rp-submit-payment-btn");
	btn.onclick = executePaymentsenseRpPayment;

	if ( typeof jQuery !== 'undefined' ) {
		jQuery("body").on('DOMSubtreeModified', "#paymentsense-rp-payment-div", function() {
			enableSubmitBtn();
		});
	} else {
		enableSubmitBtn();
	}

	function executePaymentsenseRpPayment() {
		disableSubmitBtn();
		connectE.executePayment()
		.then(function(data) {
			redirectToReturnUrl(data);
		}).catch(function(data) {
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
		btn.innerHTML = "<?php esc_html_e( 'Pay with Paymentsense', 'woocommerce-paymentsense-remote-payments' ); ?>";
		btn.disabled = false;
	}

	function redirectToReturnUrl(data) {
		setTimeout(function() { performRedirectToReturnUrl(data); }, 2000);
	}

	function performRedirectToReturnUrl(data) {
		let form = document.createElement("form");
		form.action = "<?php echo esc_url( $return_url ); ?>";
		form.method = "POST";
		data.paymentToken = "<?php echo esc_attr( $access_token ); ?>";
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
			list.classList.add("woocommerce-error");
			errors.forEach(function(error) {
				let item = document.createElement("li");
				item.innerText = error.message;
				list.appendChild(item);
			});
			errorsDiv.appendChild(list);
		}
	}

	function showErrorMsg(errNo=0, errMsg='') {
		if (errNo === 401) {
			alert('An authentication error has occurred. The response from the gateway was: "' + errMsg + '". Please contact customer support.');
		} else {
			alert('An unexpected error has occurred. Please try again later.');
		}
	}
</script>
