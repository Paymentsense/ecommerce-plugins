Paymentsense Remote Payments Extension for OpenCart
=====================================================

Payment extension for OpenCart 3, allowing you to take payments via Paymentsense Remote Payments.

Description
-----------

Payment extension for OpenCart 3 (tested up to 3.0.3.6), allowing you to take payments via Paymentsense Remote Payments.

In order to use this extension, you must have a Gateway Username/URL and Gateway JWT provided by us. If you do not have them, please email [gatewaysupport@paymentsense.com](mailto:gatewaysupport@paymentsense.com) and we will be happy to help.

Installation using Extension Installer
--------------------------------------

1. Login to the OpenCart Admin Panel
2. Go to Extensions -> Extensions Installer -> Upload to upload the extension's zip file
3. Go to Extensions -> Extensions -> Payments and click the "Install" button next to "Paymentsense Remote Payments"
4. Click the "Edit" button next to "Paymentsense Remote Payments" to configure the extension
5. Set "Extension Status" to "Enabled"
6. Set your "Gateway Username/URL" and "Gateway JWT"
7. Set the "Gateway Environment" to "Test" or "Production" based on whether you want to use the Test or the Production gateway environment
8. Optionally, set the rest of the settings as per your needs
9. Click the "Save" button

Manual installation
-------------------

1. Unzip the extension's file and upload the content of the upload folder to the root folder of your OpenCart
2. Login to the OpenCart Admin Panel
3. Go to Extensions -> Extensions -> Payments and click the "Install" button next to "Paymentsense Remote Payments"
4. Click the "Edit" button next to "Paymentsense Remote Payments" to configure the extension
5. Set "Extension Status" to "Enabled"
6. Set your "Gateway Username/URL" and "Gateway JWT"
7. Set the "Gateway Environment" to "Test" or "Production" based on whether you want to use the Test or the Production gateway environment
8. Optionally, set the rest of the settings as per your needs
9. Click the "Save" button

Changelog
---------

## [1.0.3] - 2022-03-04
### Added
- Payment notification callback support

### Changed
- Payment method name shown on the frontend to "Paymentsense"


## [1.0.2] - 2021-07-27
### Added
- metaData parameter for API requests

### Removed
- User-Agent header and userAgent parameter for API requests


## [1.0.1] - 2021-06-04
### Changed
- README.md file


## [1.0.0] - 2021-05-31
### Initial Release

Support
-------

[devsupport@paymentsense.com](mailto:devsupport@paymentsense.com)
