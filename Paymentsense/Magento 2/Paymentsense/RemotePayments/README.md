Paymentsense Remote Payments Module for Magento 2 Open Source
=============================================================

Payment module for Magento 2 Open Source, allowing you to take payments via Paymentsense Remote Payments.

In order to use this module, you must have a Gateway Username/URL and Gateway JWT provided by us. If you do not have them, please email [gatewaysupport@paymentsense.com](mailto:gatewaysupport@paymentsense.com) and we will be happy to help.

Requirements
------------

* Magento Open Source version 2.3.x or 2.4.x (tested up to 2.4.5)

Installation of the Paymentsense - Remote Payments module
---------------------------------------------------------

1. Upload the contents of the folder to ```app/code/Paymentsense/RemotePayments/``` in the Magento root folder.

2. Enable the Paymentsense Remote Payments module.

    ```sh
    $ php bin/magento module:enable Paymentsense_RemotePayments --clear-static-content
    ```

3. Update Magento.

    ```sh
    $ php bin/magento setup:upgrade
    ```

4. Deploy the static view files (if needed).

    ```sh
    $ php bin/magento setup:static-content:deploy
    ```

Configuration of the Paymentsense - Remote Payments payment method
------------------------------------------------------------------

1. Login to the Magento admin panel and go to **Stores** -> **Configuration** -> **Sales** -> **Payment Methods**.

2. If the Paymentsense Remote Payments payment method does not appear in the list of the payment methods, go to 
  **System** -> **Cache Management** and clear the Magento cache by clicking on the **Flush Magento Cache** button.

3. Go to **Payment Methods** and click the **Configure** button next to the payment method **Paymentsense - Remote Payments** to expand the configuration settings.

4. Set **Enabled** to **Yes**.

5. Set your "Gateway Username/URL" and "Gateway JWT".

6. Optionally, set the rest of the settings as per your needs.

7. Click the **Save Config** button.

Changelog
---------

## [1.0.7] - 2022-11-18
### Changed
- Orders grid group id updated (Magento 2.4.5 compatibility)

## [1.0.6] - 2022-07-20
### Added
- Support of the CSP module (Magento 2.4.4 compatibility)

### Changed
- Paymentsense logger (Magento 2.4.4 compatibility)


## [1.0.5] - 2022-04-06
### Added
- "Environment" field to the module information
- z-index property to the CSS of the 3DS authentication box


## [1.0.4] - 2022-02-16
### Added
- Payment notification callback support

### Changed
- Payment method name shown on the frontend to "Paymentsense"


## [1.0.3] - 2021-07-14
### Added
- metaData parameter for API requests

### Removed
- User-Agent header and userAgent parameter for API requests


## [1.0.2] - 2021-06-04
### Changed
- README.md file


## [1.0.1] - 2021-06-03
### Changed
- Module name


## [1.0.0] - 2021-05-13
### Initial Release

Support
-------

[devsupport@paymentsense.com](mailto:devsupport@paymentsense.com)
