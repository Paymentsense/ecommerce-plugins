=== Paymentsense Remote Payments for Drupal Commerce ===

Paymentsense Remote Payments for Drupal commerce is a Drupal module,
allowing you to take payments via Paymentsense Remote Payments.

In order to use this plugin, you must have a Gateway Username/URL and
Gateway JWT provided by us. If you do not have them, please
email gatewaysupport@paymentsense.com and we will be happy to help.

= Support =

<a href="mailto:devsupport@paymentsense.com">devsupport@paymentsense.com</a>

== Minimum Requirements ==

* PHP version 7.3.0 or greater. An actively supported version is recommended.
* Drupal Commerce 2.0 or greater (tested up to 2.25)
* Drupal 9.0.0 or greater (tested up to 9.2.1)
* jQuery 1.12.4 or greater (part of Drupal core)
* PCI-certified server using SSL/TLS


== Installation ==

1. Install the plugin using composer or un-archive the module zip file into
   modules/contrib directory of your drupal commerce site.

2. Login to admin backend and go to Extend.

3. Check the box next to "Commerce Paymentsense Remote Payments" module,
   and click install

== Activation and configuration of the Paymentsense Remote Payments payment method ==

1. Go to "Commerce" -> "Configuration" -> "Payment Gateways".

2. Click "Add payment gateway"

3. Give a "Name" and Display name, and under plugin select
   "Paymentsense Remote Payments"

4. Set your "Gateway Username/URL" and "Gateway JWT".

5. Set the "Mode" to "Test" or "Production" based on whether you want to use the
   Test or the Production gateway environment.

6. Optionally, set the rest of the settings as per your needs.

7. Make sure status is enabled  and click the "Save" button.
