Disclaimer: Paymentsense provides this code as an example of a working integration module. Responsibility for the final
implementation, functionality and testing of the module resides with the merchant/merchant's website developer.

NOTE: Although this module has been tested and rolled out with basic functionality if you have any feedback/requests
then please email devsupport@paymentsense.com and we will look into the feature.

Paymentsense - Remote Payments for Zen cart
----------------
_Contributors:_ paymentsense, hashinpanakkaparambil

_License URI:_ https://www.gnu.org/licenses/gpl-3.0.html

Provides payment module integration to take payments through paymentsense.

In order to use this plugin, you must have a Gateway Username/URL and Gateway JWT provided by us. If you do not have
them, please email [devsupport@paymentsense.com](mailto:devsupport@paymentsense.com) and we will be happy to help.

Minimum requirements
--------------------

- Zen Cart 1.5.7 (tested up to 1.5.7c)
- PHP version 7.1 or greater. An actively supported version is recommended. (tested up to 7.3.28)
- jQuery 1.12.4 or greater (Shipped with zen cart)
- Gateway Username/URL
- Gateway JWT
- PCI-certified server using SSL/TLS

Support
-------
Any support queries, email [devsupport@paymentsense.com](mailto:devsupport@paymentsense.com)

Integration
------------

- Upload the contents of the files directory into the root of your Zen Cart 1.5.1 environment via your FTP Client e.g.
  FileZilla
- Login to the Admin side of your Zen Cart environment and navigate to the "Modules" > "Payment".
- Install "Paymentsense - Remote Payments" and click "edit" to allow you to configure the Payment Module settings
- Enable Paymentsense - Remote Payments Module (Set to True).
- Enter the gateway Username/URL, and JWT that should have been provided to you.
- Set the gateway environment (Test for testing purposes and Production for production).
- Set the order status that will be set for the order after a successful payment.
- Set the refund order status that will be set for the order after a successful refund for refunds.
- So that Paymentsense appears as the first payment method for the customers leave "Sort Order of Display" set as 0.
- If you want Paymentsense - Remote Payments to be available to specific zones then you should select these here. By default set as
  --none--
- Click the update button.

Testing
--------
Using the Test Gateway please use the test card details included in this module.

Making the site Live
---------------------
You will need to use the Production Gateway Username/URL and JWT within Zen Cart admin backend. see "Integration" steps.
Test the Production account using a Real Card.
