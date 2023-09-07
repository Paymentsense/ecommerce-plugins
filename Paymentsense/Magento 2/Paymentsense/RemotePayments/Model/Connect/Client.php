<?php
/*
 * Copyright (C) 2022 Paymentsense Ltd.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 3
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author      Paymentsense
 * @copyright   2022 Paymentsense Ltd.
 * @license     https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Paymentsense\RemotePayments\Model\Connect;

use Zend\Http\Response;
use Zend_Http_Client;

/**
 * Connect-E client class
 */
class Client
{
    /**
     * @var \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory
     */
    private $httpClientFactory;

    /**
     * @param \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory
     */
    public function __construct(
        \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory
    ) {
        $this->httpClientFactory = $httpClientFactory;
    }

    /**
     * Performs HTTP GET requests
     *
     * @param array $request Request data
     *
     * @return array
     */
    public function get($request)
    {
        return $this->request(Zend_Http_Client::GET, $request);
    }

    /**
     * Performs HTTP POST requests
     *
     * @param array $request Request data
     *
     * @return array
     */
    public function post($request)
    {
        return $this->request(Zend_Http_Client::POST, $request);
    }

    /**
     * Performs HTTP requests
     *
     * @param string $method  HTTP method
     * @param array  $request Request data
     *
     * @return array
     */
    private function request($method, $request)
    {
        $config = [
            'adapter'       => 'Zend_Http_Client_Adapter_Curl',
            'ssltransport'  => 'tls',
            'sslverifypeer' => true,
            'strict'        => false,
            'persistent'    => true,
            'timeout'       => 30
        ];
        $result = [
            'HttpStatusCode' => null,
            'ResponseBody'   => null,
            'Data'           => null
        ];
        try {
            if (array_key_exists('sslallowselfsigned', $request)) {
                $config['sslallowselfsigned'] = $request['sslallowselfsigned'];
            }
            $client = $this->httpClientFactory->create();
            $client->setConfig($config);
            $client->setMethod($method);
            $client->setUri($request['url']);
            if (array_key_exists('headers', $request)) {
                $client->setHeaders($request['headers']);
            }
            if (array_key_exists('data', $request)) {
                $client->setRawData(json_encode($request['data']), 'application/json');
            }
            $httpResponse = $client->request();
            if ($httpResponse instanceof \Zend_Http_Response) {
                $result['HttpStatusCode'] = $httpResponse->getStatus();
                $result['ResponseBody']   = $httpResponse->getBody();
                if ($result['HttpStatusCode'] === Response::STATUS_CODE_200) {
                    if (!empty($result['ResponseBody'])) {
                        $result['Data'] = json_decode($result['ResponseBody'], true);
                    }
                }
            }
        } catch (\Exception $e) {
            // Swallows the exceptions thrown by Zend_Http_Client. No action is required.
            unset($e);
        }
        return $result;
    }
}
