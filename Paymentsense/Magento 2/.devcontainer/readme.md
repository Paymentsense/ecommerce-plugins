# Run DevContainer locally

* Install [ngrok](https://ngrok.com/download)
* Install VSCode "Remote Development" extensions
* Run command
    ```
    ngrok http 8090
    ```
    Copy url like https://3746-77-99-71-143.eu.ngrok.io
* Open mage-setup.sh file and update base-url variable with URL above.
* Open docker-compose.yml and update WEB_ALIAS_DOMAIN with 3746-77-99-71-143.eu.ngrok.io.
* Make a host entry in your local env 127.0.0.1 3746-77-99-71-143.eu.ngrok.io.
* Go to https://3746-77-99-71-143.eu.ngrok.io/admin if you want to change Magento settings.
* Go to https://3746-77-99-71-143.eu.ngrok.io if you want to place an order.
* To go to production open ngrok url above.
