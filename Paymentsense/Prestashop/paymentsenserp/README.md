Paymentsense Remote Payments Module for PrestaShop
==================================================

Payment module for PrestaShop, allowing you to take payments via Paymentsense Remote Payments.

In order to use this module, you must have a Gateway Username/URL and Gateway JWT provided by us. If you do not have them, please email [gatewaysupport@paymentsense.com](mailto:gatewaysupport@paymentsense.com) and we will be happy to help.

Requirements
------------

* PrestaShop 1.7 (tested up to 1.7.7.4)
* PHP 7.0 or higher (a version with security support is highly recommended)
* PHP CURL extension
* PHP JSON extension
* PCI-certified server using SSL/TLS

Installation
------------

1. Login into the admin area of your PrestaShop website.

2. Go to "Modules" -> "Module Manager".

3. Click the "Upload a module" button at the top of the page.

4. A Pop-up window will appear for uploading the module.

5. Click the "select file" link and choose to the module's zip file or drop the module's zip file into the designated area.

6. The module will be installed automatically and a message about the successful installation should appear.

7. Click the "Configure" button to configure the module and go to step 4. of the Configuration section below in these instructions.

Configuration
-------------

1. Login into the admin area of your PrestaShop website.

2. Go to "Modules" -> "Module Manager".

3. Find the "Paymentsense Remote Payments" module and click the "Configure" button.

4. Set your "Gateway Username/URL" and "Gateway JWT".

5. Set the "Gateway Environment" to "Test" or "Production" based on whether you want to use the Test or the Production gateway environment.

6. Optionally, set the rest of the settings as per your needs.

7. Click the "Save" button at the bottom of the page.

8. Go to "Advanced Parameters" -> "Performance" and click the "Clear cache" button.

Secure Checkout
---------------

1. Make sure SSL/TLS is configured on your PCI-DSS certified server.

2. Login into the admin area of your PrestaShop website.

3. Go to "Shop Parameters" -> "General".

4. Click the "Please click here to check if your shop supports HTTPS" link.

5. Set "Enable SSL" to "YES".

6. Click the "Save" button at the bottom of the page.

7. Set "Enable SSL on all pages" to "YES".

8. Click the "Save" button again.

Changelog
---------

## [1.0.2] - 2021-07-21
### Added
- metaData parameter for API requests

### Removed
- User-Agent header and userAgent parameter for API requests

Support
-------

[devsupport@paymentsense.com](mailto:devsupport@paymentsense.com)