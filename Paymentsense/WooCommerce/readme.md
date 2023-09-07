# General info

## Into

There is no automatic build or deployment for this plugin, since it is legacy PS one. This plugin has support for AppInsight telemetry, which requires
some manual steps in order to be packaged.

## How to build?

1. Restore dependencies with composer:

    ``` bash
    composer install --no-dev
    ```

2. There are bugs in the `app-insights-php` package, thus some manual fixes required:
    * Inside `guzzlehttp` package delete the client file. This is required due to strange code over here (Telemetry_Channel.php):

    ``` Php
    public function __construct($endpointUrl = 'https://dc.services.visualstudio.com/v2/track', $client = null)
    {
        $this->_endpointUrl = $endpointUrl;
        $this->_queue = [];
        $this->_client = $client;
        $this->_sendGzipped = false;

        if ($client === null && \class_exists('\GuzzleHttp\Client') == true) {
            // Standard case if properly pulled in composer dependencies
            $this->_client = new \GuzzleHttp\Client();
        }
    }
    ```

    If not deleted, will use this `\GuzzleHttp\Client()` instead of WordPress Api calls client which is much slower.
    * Inside `app-insights-php/application-insights` package in `utils.php` replace the original method with this one:

    ``` Php
    /**
        * Returns the proper ISO string for Application Insights service to accept.
        *
        * @param mixed $time
        *
        * @return string
        */
    public static function returnISOStringForTime($time = null)
    {
        if ($time == null) {
            // return \gmdate('c') . 'Z';
            return \gmdate('Y-m-d\TH:i:s') . substr(microtime(), 1, 8) . date('P') . 'Z';
        }

        // return \gmdate('c', $time) . 'Z';
        return \gmdate('Y-m-d\TH:i:s', $time) . substr(microtime(), 1, 8) . date('P') . 'Z';
    }
    ```

    This is required to get milliseconds in the AppInsights output, otherwise the trace is messed up.
3. Update required version of the plugin
4. Package as zip archive (usual stuff)

## How to deploy?

Also, there is no deployment for this plugin into K8S. However, it can be done by using WooCommerce plugin for Dojo. Just run deployment without 
deploying the plugin, and deploy this one manually.