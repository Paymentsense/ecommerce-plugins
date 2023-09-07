Dojo Module for Magento 2 Open Source
=====================================

Known issues
-------
* Debug is not working. This work is still in progress
* Magento keys are hardcoded in the Dockerfile. Not a security concern just need to update these if they are reset
* Folder permissions issue. After the initial setup and every time a cache is cleared need to fix it manually
 

Configuration of the Dojo payment method
----------------------------------------
* Run the dev container in VsCode and it should setup your local environment.
(In order to run the dev container in VsCode it is helpful to install DevContainers extension. Open the Magento folder with VsCode and it should start to run the devcontainer. In case it doesn't start try the VsCode => Open a Remote wWndow => Open folder in container => select Payentsense/Magento 2 folder.)
* Make a host entry in your local env 127.0.0.1 local.domain.com
* Navigate to https://local.domain.com/admin (and ingore the SSL error)
* Login with username 'admin' password 'SomePassword123'
* Follow below steps to configure Dojo Payments module

The above steps should run successful. In case there are any errors try doing the following:

Shell into the docker container
  ```sh
    $ docker exec -it magento2_devcontainer_web_1 bash
```
Grant permissions 
```sh
    $ chmod 777 -R /app/var && chmod 777 -R /app/generated && chmod 777 -R /app/app && chmod 777 -R /app/pub
```

1. Login to the Magento admin panel and go to **Stores** -> **Configuration** -> **Sales** -> **Payment Methods**.

2. If the Paymentsense payment method does not appear in the list of the payment methods, go to
   **System** -> **Cache Management** and clear the Magento cache by clicking on the **Flush Magento Cache** button.

3. Go to **Payment Methods** and click the **Configure** button next to the payment method **Paymentsense - RemotePayments** to expand the configuration settings.

4. Set **Enabled** to **Yes**.

5. Set your "Gateway Username/URL" and "Gateway JWT".

6. Optionally, set the rest of the settings as per your needs.

7. Click the **Save Config** button.

Support
-------

[devsupport@dojo.tech](mailto:devsupport@dojo.tech)
