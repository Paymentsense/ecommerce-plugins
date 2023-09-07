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

namespace Paymentsense\RemotePayments\Model\Traits;

use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Paymentsense\RemotePayments\Model\Connect\GatewayEnvironment;
use Paymentsense\RemotePayments\Model\Connect\ApiRequest;
use Paymentsense\RemotePayments\Model\Connect\CountryCode;
use Paymentsense\RemotePayments\Model\Connect\CurrencyCode;
use Paymentsense\RemotePayments\Model\Connect\PaymentStatus;
use Paymentsense\RemotePayments\Model\Connect\TransactionType;
use Paymentsense\RemotePayments\Model\Connect\TransactionStatusCode;

/**
 * Trait for processing transactions
 */
trait Transactions
{
    /**
     * @var \Paymentsense\RemotePayments\Model\Config
     */
    protected $_configHelper;

    /**
     * @var \Paymentsense\RemotePayments\Helper\Data
     */
    protected $_moduleHelper;

    /**
     * @var \Paymentsense\RemotePayments\Helper\Transactions
     */
    protected $_transactionsHelper;

    /**
     * @var \Paymentsense\RemotePayments\Model\Connect\Client
     */
    protected $_connectClient;

    /**
     * @var \Magento\Framework\App\Action\Context
     */
    protected $_actionContext;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;
    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;
    /**
     * @var \Magento\Sales\Model\Order\Payment\Transaction\ManagerInterface
     */
    protected $_transactionManager;

    /**
     * Gets an instance of the Config Helper
     *
     * @return \Paymentsense\RemotePayments\Model\Config
     */
    public function getConfigHelper()
    {
        return $this->_configHelper;
    }

    /**
     * Gets an instance of the Module Helper
     *
     * @return \Paymentsense\RemotePayments\Helper\Data
     */
    public function getModuleHelper()
    {
        return $this->_moduleHelper;
    }

    /**
     * Gets an instance of the Transactions Helper
     *
     * @return \Paymentsense\RemotePayments\Helper\Transactions
     */
    public function getTransactionsHelper()
    {
        return $this->_transactionsHelper;
    }

    /**
     * Gets an instance of the Magento Action Context
     *
     * @return \Magento\Framework\App\Action\Context
     */
    public function getActionContext()
    {
        return $this->_actionContext;
    }

    /**
     * Gets an instance of the Magento Core Message Manager
     *
     * @return \Magento\Framework\Message\ManagerInterface
     */
    public function getMessageManager()
    {
        return $this->getActionContext()->getMessageManager();
    }

    /**
     * Gets an instance of the Magento Core Store Manager object
     *
     * @return \Magento\Store\Model\StoreManagerInterface
     */
    public function getStoreManager()
    {
        return$this->_storeManager;
    }

    /**
     * Gets an instance of a URL
     *
     * @return \Magento\Framework\UrlInterface
     */
    public function getUrlBuilder()
    {
        return $this->_urlBuilder;
    }

    /**
     * Gets an instance of the Magento Core Checkout Session
     *
     * @return \Magento\Checkout\Model\Session
     */
    public function getCheckoutSession()
    {
        return $this->_checkoutSession;
    }

    /**
     * Gets an instance of the Magento Transaction Manager
     *
     * @return \Magento\Sales\Model\Order\Payment\Transaction\ManagerInterface
     */
    public function getTransactionManager()
    {
        return $this->_transactionManager;
    }

    /**
     * Gets the data for showing the payment form
     *
     * @param Order|Quote $order Order or quote object
     *
     * @return array An associative array containing data for showing the payment form
     *
     * @throws \Exception
     */
    public function getPaymentFormData($order)
    {
        $moduleHelper = $this->getModuleHelper();
        $config       = $this->getConfigHelper();

        $accessToken  = null;

        $request = [
            'url'     => $this->getApiEndPointUrl(ApiRequest::ACCESS_TOKENS),
            'headers' => $this->buildRequestHeaders(),
            'data'    => $this->buildRequestParams($this->buildRequestAccessTokensPayment($order)),
        ];

        try {
            $response = $this->_connectClient->post($request);
            if (!empty($response['Data'])) {
                $accessToken = $this->getArrayElement($response['Data'], 'id', false);
            }
        } catch (\Exception $e) {
            // Swallows the exceptions thrown by Zend_Http_Client. No action is required.
            unset($e);
        }

        if ($order instanceof Order) {
            $orderId = $order->getRealOrderId();
        } else {
            $orderId = 'QUOTE' . $order->getEntityId();
        }

        if (!empty($accessToken)) {
            $this->getLogger()->info(
                'Requesting access token for the Payment Form with ' . $config->getTransactionType() .
                ' transaction for order #' . $orderId
            );

            if ($order instanceof Order) {
                $this->getModuleHelper()->setOrderStatusByState($order, Order::STATE_PENDING_PAYMENT);
                $order->save();
            }

            $result = [
                'message'      => 'Please enter your card details and press the button below to pay with Paymentsense.',
                'amount'       => (string) $request['data']['amount'],
                'currencyCode' => $request['data']['currencyCode'],
                'accessToken'  => $accessToken,
                'clientJsUrl'  => $this->getClientJsUrl(),
                'returnUrl'    => $moduleHelper->getReturnUrl()
            ];
        } else {
            if (!empty($response['HttpStatusCode'])) {
                $error_info = 'HTTP Status Code: ' . $response['HttpStatusCode'] .
                    '; Response Body: ' . $response['ResponseBody'];
                $result = [
                    'HttpStatusCode' => $response['HttpStatusCode'],
                    'ResponseBody'   => $response['ResponseBody']
                ];
            } else {
                $error_info = 'HTTP Status Code was not set';
                $result = [];
            }

            $this->getLogger()->error(
                'An error has occurred while requesting access token for ' .
                $config->getTransactionType() . ' transaction for order #' . $orderId . ' (' . $error_info . ')'
            );
        }

        return $result;
    }

    /**
     * Gets the payment status based on the transaction status code
     *
     * @param int $statusCode Transaction status code
     *
     * @return int
     */
    public function getPaymentStatus($statusCode)
    {
        switch ($statusCode) {
            case TransactionStatusCode::SUCCESS:
                $result = PaymentStatus::SUCCESS;
                break;
            case TransactionStatusCode::REFERRED:
            case TransactionStatusCode::DECLINED:
            case TransactionStatusCode::FAILED:
                $result = PaymentStatus::FAIL;
                break;
            default:
                $result = PaymentStatus::UNKNOWN;
                break;
        }
        return $result;
    }

    /**
     * Gets the transaction payment status
     *
     * @param Order  $order       Order object
     * @param string $accessToken Access token
     *
     * @return array
     */
    public function getTransactionPaymentStatus($order, $accessToken)
    {
        $result = [
            'OrderIdValid'   => false,
            'StatusCode'     => TransactionStatusCode::NOT_AVAILABLE,
            'Message'        => 'An error occurred while communicating with the payment gateway. ',
            'CrossReference' => '',
            'CurrencyCode'   => '',
            'Amount'         => 0
        ];

        $request = [
            'url'     => $this->getApiEndPointUrl(ApiRequest::PAYMENTS, $accessToken),
            'headers' => $this->buildRequestHeaders()
        ];

        try {
            $response = $this->_connectClient->get($request);
            if (!empty($response['Data'])) {
                $metaData = $this->getArrayElement($response['Data'], 'metaData', '');
                $orderIdValid = is_array($metaData) &&
                    array_key_exists('orderId', $metaData) &&
                    ! empty($metaData['orderId']) &&
                    ($order->getRealOrderId() === $metaData['orderId']);
                $result = [
                    'OrderIdValid'   => $orderIdValid,
                    'StatusCode'     => $this->getArrayElement($response['Data'], 'statusCode', TransactionStatusCode::NOT_AVAILABLE),
                    'Message'        => $this->getArrayElement($response['Data'], 'message', ''),
                    'CrossReference' => $this->getArrayElement($response['Data'], 'crossReference', ''),
                    'Amount'         => $this->getPaymentTotalDue($order) * 100,
                    'CurrencyCode'   => $this->getCurrencyCode($this->getPaymentCurrencyCode($order))
                ];
            } else {
                if (!empty($response['HttpStatusCode'])) {
                    $result['Message'] .= 'HTTP Status Code: ' . $response['HttpStatusCode'] .
                        '; Response Body: ' . $response['ResponseBody'];
                } else {
                    $result['Message'] .= 'HTTP Status Code was not set';
                }
            }
        } catch (\Exception $e) {
            // Swallows the exceptions thrown by Zend_Http_Client. No action is required.
            unset($e);
        }
        return $result;
    }

    /**
     * Gets an element from array
     *
     * @param array  $arr     The array
     * @param string $element The element
     * @param mixed  $default The default value if the element does not exist
     *
     * @return mixed
     */
    public function getArrayElement($arr, $element, $default)
    {
        return (is_array($arr) && array_key_exists($element, $arr))
            ? $arr[$element]
            : $default;
    }

    /**
     * Performs COLLECTION
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @param \Magento\Sales\Model\Order\Payment\Transaction $authTransaction
     *
     * @return $this
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function performCollection(\Magento\Payment\Model\InfoInterface $payment, $amount, $authTransaction)
    {
        $order = $payment->getOrder();

        if (!$this->isTransactionInBaseCurrency($authTransaction)) {
            $amount = $this->getModuleHelper()->calculateOrderAmountDue($order, $amount);
        }

        $request = [
            'url'     => $this->getApiEndPointUrl(ApiRequest::ACCESS_TOKENS),
            'headers' => $this->buildRequestHeaders(),
            'data'    => $this->buildRequestParams(
                $this->buildRequestAccessTokensCrossRefTxn(
                    $order,
                    TransactionType::COLLECTION,
                    $authTransaction->getTxnId(),
                    $amount,
                    'Collection'
                )
            )
        ];

        $response = $this->processCrossRefTransaction($payment, $request);

        if ($response['StatusCode'] === TransactionStatusCode::SUCCESS) {
            $this->getMessageManager()->addSuccessMessage($response['Message']);
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                new \Magento\Framework\Phrase(
                    __('COLLECTION transaction failed. ') .
                    $response['Message']
                )
            );
        }

        return $this;
    }

    /**
     * Performs REFUND
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @param \Magento\Sales\Model\Order\Payment\Transaction $captureTransaction
     *
     * @return $this
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function performRefund(\Magento\Payment\Model\InfoInterface $payment, $amount, $captureTransaction)
    {
        $order = $payment->getOrder();

        if (!$this->isTransactionInBaseCurrency($captureTransaction)) {
            $amount = $this->getModuleHelper()->calculateOrderAmountPaid($order, $amount);
        }

        $request = [
            'url'         => $this->getApiEndPointUrl(ApiRequest::ACCESS_TOKENS),
            'headers'     => $this->buildRequestHeaders(),
            'data' => $this->buildRequestParams(
                $this->buildRequestAccessTokensCrossRefTxn(
                    $order,
                    TransactionType::REFUND,
                    $captureTransaction->getTxnId(),
                    $amount,
                    'Refund'
                )
            )
        ];

        $response = $this->processCrossRefTransaction($payment, $request);

        if ($response['StatusCode'] === TransactionStatusCode::SUCCESS) {
            $this->getMessageManager()->addSuccessMessage($response['Message']);
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                new \Magento\Framework\Phrase(
                    __('REFUND transaction failed. ') .
                    $response['Message']
                )
            );
        }

        return $this;
    }

    /**
     * Performs VOID
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param \Magento\Sales\Model\Order\Payment\Transaction $referenceTransaction
     *
     * @return $this
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function performVoid(\Magento\Payment\Model\InfoInterface $payment, $referenceTransaction)
    {
        $order = $payment->getOrder();

        $request = [
            'url'         => $this->getApiEndPointUrl(ApiRequest::ACCESS_TOKENS),
            'headers'     => $this->buildRequestHeaders(),
            'data' => $this->buildRequestParams(
                $this->buildRequestAccessTokensCrossRefTxn(
                    $order,
                    TransactionType::VOID,
                    $referenceTransaction->getTxnId(),
                    0,
                    'Void'
                )
            )
        ];

        $response = $this->processCrossRefTransaction($payment, $request);

        if ($response['StatusCode'] === TransactionStatusCode::SUCCESS) {
            $this->getMessageManager()->addSuccessMessage($response['Message']);
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                new \Magento\Framework\Phrase(
                    __('VOID transaction failed. ') .
                    $response['Message']
                )
            );
        }

        return $this;
    }

    /**
     * Performs a cross-reference transaction (COLLECTION, REFUND, VOID)
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param array $request Transaction request data
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    // phpcs:ignore Generic.Metrics.CyclomaticComplexity, Generic.Metrics.NestingLevel
    public function processCrossRefTransaction(\Magento\Payment\Model\InfoInterface $payment, $request)
    {
        $result = [
            'StatusCode'     => TransactionStatusCode::NOT_AVAILABLE,
            'Message'        => 'An error occurred while communicating with the payment gateway.',
            'CrossReference' => ''
        ];
        try {
            $response = $this->_connectClient->post($request);
            if (!empty($response['Data'])) {
                $accessToken = $this->getArrayElement($response['Data'], 'id', false);
                if (!empty($accessToken)) {
                    $cross_ref_request = [
                        'url'     => $this->getApiEndPointUrl(ApiRequest::CROSS_REF_PAYMENTS, $accessToken),
                        'headers' => $this->buildRequestHeaders(),
                        'data'    => $this->buildRequestParams(
                            $this->buildRequestCrossRefTxn(
                                $request['data']['crossReference']
                            )
                        )
                    ];
                    $response = $this->_connectClient->post($cross_ref_request);
                    if (!empty($response['Data'])) {
                        $result = [
                            'StatusCode'     => $this->getArrayElement($response['Data'], 'statusCode', TransactionStatusCode::NOT_AVAILABLE),
                            'Message'        => $this->getArrayElement($response['Data'], 'message', ''),
                            'CrossReference' => $this->getArrayElement($response['Data'], 'md', '')
                        ];
                    } else {
                        if (!empty($response['HttpStatusCode'])) {
                            $error_info = ' HTTP Status Code: ' . $response['HttpStatusCode'] .
                                '; Response Body: ' . $response['ResponseBody'];
                        } else {
                            $error_info = ' HTTP Status Code was not set';
                        }
                        $this->getLogger()->error(
                            sprintf(
                                'An error has occurred while performing reference %1$s transaction for order #%2$s (%3$s)',
                                $request['data']['transactionType'],
                                $request['data']['orderId'],
                                $error_info
                            )
                        );
                    }

                    if (empty($result['CrossReference'])) {
                        $payment_status_request = [
                            'url'     => $this->getApiEndPointUrl(ApiRequest::PAYMENTS, $accessToken),
                            'headers' => $this->buildRequestHeaders(),
                        ];
                        $response = $this->_connectClient->get($payment_status_request);
                        if (!empty($response['Data'])) {
                            $result = [
                                'StatusCode'     => $this->getArrayElement($response['Data'], 'statusCode', TransactionStatusCode::NOT_AVAILABLE),
                                'Message'        => $this->getArrayElement($response['Data'], 'message', ''),
                                'CrossReference' => $this->getArrayElement($response['Data'], 'crossReference', '')
                            ];
                        } else {
                            if (!empty($response['HttpStatusCode'])) {
                                $error_info = ' HTTP Status Code: ' . $response['HttpStatusCode'] .
                                    '; Response Body: ' . $response['ResponseBody'];
                            } else {
                                $error_info = ' HTTP Status Code was not set';
                            }
                            $this->getLogger()->error(
                                sprintf(
                                    'An error has occurred while retrieving the status of a reference %1$s transaction for order #%2$s (%3$s)',
                                    $request['data']['transactionType'],
                                    $request['data']['orderId'],
                                    $error_info
                                )
                            );
                        }
                    }
                }
            } else {
                if (!empty($response['HttpStatusCode'])) {
                    $error_info = ' HTTP Status Code: ' . $response['HttpStatusCode'] .
                        '; Response Body: ' . $response['ResponseBody'];
                } else {
                    $error_info = ' HTTP Status Code was not set';
                }
                $this->getLogger()->error(
                    sprintf(
                        'An error has occurred while requesting access token for reference %1$s transaction for order #%2$s (%3$s)',
                        $request['data']['transactionType'],
                        $request['data']['orderId'],
                        $error_info
                    )
                );
            }
        } catch (\Exception $e) {
            // Swallows the exceptions thrown by Zend_Http_Client. No action is required.
            unset($e);
        }

        $this->getLogger()->info(
            'Reference transaction ' . $result['CrossReference'] .
            ' has been performed with status code "' . $result['StatusCode'] . '".'
        );

        if ($result['StatusCode'] !== TransactionStatusCode::NOT_AVAILABLE) {
            $payment
                ->setTransactionId($result['CrossReference'])
                ->setParentTransactionId($request['data']['crossReference'])
                ->setShouldCloseParentTransaction(true)
                ->setIsTransactionPending(false)
                ->setIsTransactionClosed(true);
            $this->setPaymentTransactionAdditionalInfo(
                $payment,
                array_merge(
                    [
                        'Amount'       => $request['data']['amount'],
                        'CurrencyCode' => $request['data']['currencyCode']
                    ],
                    $result
                )
            );
            $payment->save();
        }
        return $result;
    }

    /**
     * Updates payment info and registers a card details transaction
     *
     * @param Order $order    Order object
     * @param array $response Transaction response data received from the gateway
     *
     * @throws \Exception
     */
    public function updatePayment($order, $response)
    {
        $config            = $this->getConfigHelper();
        $transactionID     = $response['CrossReference'];
        $payment           = $order->getPayment();
        $lastTransactionId = $payment->getLastTransId();
        $payment->setMethod($this->getCode());
        $payment->setLastTransId($transactionID);
        $payment->setTransactionId($transactionID);
        $payment->setParentTransactionId($lastTransactionId);
        $payment->setShouldCloseParentTransaction(true);
        $payment->setIsTransactionPending($response['StatusCode'] !== TransactionStatusCode::SUCCESS);
        $payment->setIsTransactionClosed($config->getTransactionType() === TransactionType::SALE);
        if ($response['StatusCode'] === TransactionStatusCode::SUCCESS) {
            $this->setPaymentTransactionAdditionalInfo(
                $payment,
                [
                    'Message'        => $response['Message'],
                    'CrossReference' => $response['CrossReference'],
                    'Amount'         => $response['Amount'],
                    'CurrencyCode'   => $response['CurrencyCode']
                ]
            );
            $amount = $response['Amount'] / 100;
            $this->registerTransaction($payment, $amount);
        }
        $order->save();
    }

    /**
     * Registers a card details transaction
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount Transaction amount
     */
    public function registerTransaction($payment, $amount)
    {
        $config = $this->getConfigHelper();
        $order = $payment->getOrder();
        $transactionID = $payment->getLastTransId();
        $transactionType = $config->getTransactionType();
        $commentMessage = sprintf(
            'A %s transaction in the %s currency of the amount of %.2f %s is performed.',
            $transactionType,
            $config->getPaymentCurrency(),
            $amount,
            $this->getPaymentCurrencyCode($order)
        );
        $this->getLogger()->info($commentMessage);
        $payment->addTransactionCommentsToOrder($transactionID, $commentMessage);
        if (!$config->isBaseCurrency()) {
            $amount = $this->getModuleHelper()->calculateBaseAmount($order, $amount);
        }

        if ($transactionType === TransactionType::SALE) {
            $payment->registerCaptureNotification($amount);
        } else {
            $payment->registerAuthorizationNotification($amount);
        }
    }

    /**
     * Gets the API endpoint URL
     *
     * @param string $request API request
     * @param string $param   Parameter of the API request
     *
     * @return string
     */
    private function getApiEndpointUrl($request, $param = null)
    {
        $config  = $this->getConfigHelper();
        $baseUrl = array_key_exists($config->getGatewayEnvironment(), GatewayEnvironment::ENVIRONMENTS)
            ? GatewayEnvironment::ENVIRONMENTS[$config->getGatewayEnvironment()]['entry_point_url']
            : GatewayEnvironment::ENVIRONMENTS['TEST']['entry_point_url'];

        $param   = (null !== $param) ? "/$param" : '';
        return $baseUrl . $request . $param;
    }

    /**
     * Gets the URL of the client.js library
     *
     * @return string
     */
    private function getClientJsUrl()
    {
        $config = $this->getConfigHelper();
        return array_key_exists($config->getGatewayEnvironment(), GatewayEnvironment::ENVIRONMENTS)
            ? GatewayEnvironment::ENVIRONMENTS[$config->getGatewayEnvironment()]['client_js_url']
            : GatewayEnvironment::ENVIRONMENTS['TEST']['client_js_url'];
    }

    /**
     * Gets the numeric country ISO 3166-1 code
     *
     * @param string $countryCode Alpha-2 country code
     *
     * @return string
     */
    private function getCountryCode($countryCode)
    {
        $result   = '';
        $isoCodes = CountryCode::ISO_3166_1;
        if (array_key_exists($countryCode, $isoCodes)) {
            $result = $isoCodes[$countryCode];
        }
        return $result;
    }

    /**
     * Gets the numeric currency ISO 4217 code
     *
     * @param string $currencyCode Alphabetic currency code
     * @param string $defaultCode  Default numeric currency code
     *
     * @return string
     */
    private function getCurrencyCode($currencyCode, $defaultCode = '826')
    {
        $result   = $defaultCode;
        $isoCodes = CurrencyCode::ISO_4217;
        if (array_key_exists($currencyCode, $isoCodes)) {
            $result = $isoCodes[$currencyCode];
        }
        return $result;
    }

    /**
     * Gets the payment currency code
     *
     * @param Order $order Order object
     *
     * @return string|null
     */
    private function getPaymentCurrencyCode($order)
    {
        $config = $this->getConfigHelper();
        return $config->isBaseCurrency() ? $order->getBaseCurrencyCode() : $order->getOrderCurrencyCode();
    }

    /**
     * Gets the payment total due
     *
     * @param Order|Quote $order Order or quote object
     *
     * @return float|null
     */
    private function getPaymentTotalDue($order)
    {
        $config = $this->getConfigHelper();
        return ($order instanceof Order)
            ? ($config->isBaseCurrency() ? $order->getBaseTotalDue() : $order->getTotalDue())
            : ($config->isBaseCurrency() ? $order->getBaseGrandTotal() : $order->getGrandTotal());
    }

    /**
     * Sets an additional info to the payment transaction
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param array|string $info
     */
    private function setPaymentTransactionAdditionalInfo($payment, $info)
    {
        $payment->setTransactionAdditionalInfo(Transaction::RAW_DETAILS, $info);
    }

    /**
     * Gets an additional info to the payment transaction
     *
     * @param \Magento\Sales\Model\Order\Payment\Transaction $transaction
     * @param string $element Info element
     *
     * @return array|string|null
     */
    private function getPaymentTransactionAdditionalInfo($transaction, $element = null)
    {
        $result = null;
        $info = $transaction->getAdditionalInformation(Transaction::RAW_DETAILS);
        if (is_array($info)) {
            if (isset($element)) {
                if (array_key_exists($element, $info)) {
                    $result = $info[$element];
                }
            } else {
                $result = $info;
            }
        }
        return $result;
    }

    /**
     * Determines whether the payment transaction is in the base currency
     *
     * @param \Magento\Sales\Model\Order\Payment\Transaction $transaction Payment transaction
     *
     * @return bool
     */
    private function isTransactionInBaseCurrency($transaction)
    {
        $trxCurrencyCode = $this->getPaymentTransactionAdditionalInfo(
            $transaction,
            'CurrencyCode'
        );
        $baseCurrencyCode = $this->getCurrencyCode(
            $transaction->getOrder()->getBaseCurrencyCode()
        );
        return $trxCurrencyCode == $baseCurrencyCode;
    }

    /**
     * Builds the fields for the access token payment request
     *
     * @param Order $order Order object
     *
     * @return array
     */
    private function buildRequestAccessTokensPayment($order)
    {
        $config = $this->getConfigHelper();
        $billingAddress = $order->getBillingAddress();
        if ($order instanceof Order) {
            $moduleHelper = $this->getModuleHelper();
            $orderId = $order->getRealOrderId();
            $result = [
                'gatewayUsername'  => $config->getUsername(),
                'currencyCode'     => $this->getCurrencyCode($this->getPaymentCurrencyCode($order)),
                'amount'           => $this->getPaymentTotalDue($order) * 100,
                'transactionType'  => $config->getTransactionType(),
                'orderId'          => $orderId,
                'orderDescription' => $orderId . ': New order',
                'userEmailAddress' => $order->getCustomerEmail(),
                'userPhoneNumber'  => $billingAddress->getTelephone(),
                // @codingStandardsIgnoreLine
                'userIpAddress'    => $_SERVER['REMOTE_ADDR'],
                'userAddress1'     => $billingAddress->getStreetLine(1),
                'userAddress2'     => $billingAddress->getStreetLine(2),
                'userAddress3'     => $billingAddress->getStreetLine(3),
                'userAddress4'     => $billingAddress->getStreetLine(4),
                'userCity'         => $billingAddress->getCity(),
                'userState'        => $billingAddress->getRegionCode(),
                'userPostcode'     => $billingAddress->getPostcode(),
                'userCountryCode'  => $this->getCountryCode($billingAddress->getCountryId()),
                'webHookUrl'       => $moduleHelper->getPaymentNotificationUrl(),
                'metaData'         => $this->buildMetaData(['orderId' => $orderId]),
            ];
        } else {
            $orderId = $order->getEntityId();
            $result = [
                'gatewayUsername'  => $config->getUsername(),
                'currencyCode'     => $this->getCurrencyCode($this->getPaymentCurrencyCode($order)),
                'amount'           => $this->getPaymentTotalDue($order) * 100,
                'transactionType'  => $config->getTransactionType(),
                'orderId'          => $orderId,
                'orderDescription' => $orderId . ': Quote',
                'userEmailAddress' => '',
                'userPhoneNumber'  => '',
                // @codingStandardsIgnoreLine
                'userIpAddress'    => '',
                'userAddress1'     => '',
                'userAddress2'     => '',
                'userAddress3'     => '',
                'userAddress4'     => '',
                'userCity'         => '',
                'userState'        => '',
                'userPostcode'     => '',
                'userCountryCode'  => '',
                'webHookUrl'       => '',
                'metaData'         => $this->buildMetaData(['orderId' => $orderId]),
            ];
        }
        return $result;
    }

    /**
     * Builds the fields for the access token cross-reference transaction request
     *
     * @param Order  $order           Order object
     * @param string $transactionType Transaction type
     * @param string $crossRef        Cross-reference ID of the parent transaction
     * @param float  $amount          Amount
     * @param string $reason          Reason message
     *
     * @return array
     */
    private function buildRequestAccessTokensCrossRefTxn($order, $transactionType, $crossRef, $amount, $reason)
    {
        $config = $this->getConfigHelper();
        $orderId = $order->getRealOrderId();
        return [
            'gatewayUsername'  => $config->getUsername(),
            'currencyCode'     => $this->getCurrencyCode($this->getPaymentCurrencyCode($order)),
            'amount'           => $amount * 100,
            'transactionType'  => $transactionType,
            'orderId'          => $orderId,
            'orderDescription' => $orderId . ': ' . $reason,
            'newTransaction'   => false,
            'crossReference'   => $crossRef,
            'metaData'         => $this->buildMetaData(),
        ];
    }

    /**
     * Builds the meta data
     *
     * @param array $additionalData Additional meta data
     *
     * @return array An associative array containing the meta data
     */
    private function buildMetaData($additionalData = [])
    {
        $metaData = [
            'shoppingCartUrl'      => $this->getModuleHelper()->getCartUrl(),
            'shoppingCartPlatform' => $this->getModuleHelper()->getCartPlatformName(),
            'shoppingCartVersion'  => $this->getMagentoVersion(),
            'shoppingCartGateway'  => $this->getModuleHelper()->getGatewayName(),
            'pluginVersion'        => $this->getModuleInstalledVersion(),
        ];
        return array_merge($metaData, $additionalData);
    }

    /**
     * Builds the fields for a cross-reference transaction request
     *
     * @param string $crossRef Cross-reference ID of the parent transaction.
     *
     * @return array
     */
    private function buildRequestCrossRefTxn($crossRef)
    {
        return [
            'crossReference' => $crossRef
        ];
    }

    /**
     * Builds the HTTP headers for the API requests
     *
     * @return array
     */
    private function buildRequestHeaders()
    {
        $config = $this->getConfigHelper();
        return [
            'Accept: application/connect.v1+json',
            'Cache-Control: no-cache',
            'Authorization: Bearer ' . $config->getGatewayJwt(),
            'Content-Type: application/json'
        ];
    }

    /**
     * Builds the fields for the API requests by replacing the null values with empty strings
     *
     * @param array $fields An array containing the fields for the API request
     *
     * @return array
     */
    private function buildRequestParams($fields)
    {
        return array_map(
            function ($value) {
                return $value ?? '';
            },
            $fields
        );
    }
}
