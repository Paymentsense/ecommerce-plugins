Paymentsense - Remote Payments for CubeCart
===========================================

Paymentsense - Remote Payments for CubeCart is a CubeCart module,
allowing you to take payments via Paymentsense - Remote Payments.

In order to use this plugin, you must have a Gateway Username/URL and
Gateway JWT provided by us. If you do not have them, please
email <a href="mailto:gatewaysupport@paymentsense.com">gatewaysupport@paymentsense.com</a>, and we will be happy to help.

Support
-------
For any support queries, email <a href="mailto:devsupport@paymentsense.com">devsupport@paymentsense.com</a>

Minimum Requirements
--------------------

* PHP version 7.3.0 or greater. An actively supported version is recommended.
* CubeCart 6.0.0 or greater (tested up to 6.4.4)
* jQuery 1.12.4 or greater
* PCI-certified server using SSL/TLS

Installation
------------

1. Unzip module zip file into modules/plugins directory of your CubeCart site.
2. Login to admin backend, on the left side-bar, under "Extensions", click on "Manage Extensions".
3. Under "Available extensions", click on "Paymentsense - Remote Payments" name, which will take you to the configuration page.
4. Under "Module Settings"
   1. Status - Check the checkbox to activate the module.
   2. Priority - Enter a number to specify the order in which the gateway will be displayed on the checkout page.
   3. Description - Enter the text to be shown to the customer during checkout.
5. Under - "Paymentsense - Remote Payments Gateway Settings"
   1. Gateway username/URL - Enter the gateway username/URL provided by us.
   2. Gateway JWT - Enter your gateway JWT.
   3. Gateway environment - Set to "Test" or "Production" based on whether you want to use the
      Test or the Production gateway environment.
   4. Order prefix - Optionally set an order prefix.
6. Make sure status is enabled  and click the "Save" button. 

