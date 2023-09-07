<?php

namespace Drupal\commerce_paymentsense_remotepayments\ConnectE;

use Drupal\address\AddressInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface as FrameworkPaymentInterface;
use Drupal\commerce_price\MinorUnitsConverterInterface;
use Drupal\commerce_price\Price;

/**
 * Facilitates Sending of HTTP requests.
 */
class Client implements ClientInterface {

  use LoggableTrait;

  /**
   * Test connect e entry point.
   */
  public const ENTRY_POINT_URL_TEST = 'https://e.test.connect.paymentsense.cloud';
  /**
   * Live connect e entry point.
   */
  public const ENTRY_POINT_URL_LIVE = 'https://e.connect.paymentsense.cloud';
  /**
   * Access token end point.
   */
  public const ENDPOINT_ACCESS_TOKENS = 'v1/access-tokens';
  /**
   * Endpoint for cross-reference payments.
   */
  public const ENDPOINT_CROSS_REFERENCE_PAYMENTS = 'v1/cross-reference-payments';
  /**
   * Endpoint for payments.
   */
  public const ENDPOINT_PAYMENTS = 'v1/payments';
  /**
   * Successful payment status.
   */
  public const PAYMENT_STATUS_SUCCESS = 0;
  /**
   * Referred payment status.
   */
  public const PAYMENT_STATUS_REFERRED = 4;
  /**
   * Declined payment status.
   */
  public const PAYMENT_STATUS_DECLINED = 5;
  /**
   * Duplicated payment status.
   */
  public const PAYMENT_STATUS_DUPLICATED = 20;
  /**
   * Failed payment status.
   */
  public const PAYMENT_STATUS_FAILED = 30;
  /**
   * Shopping cart platform.
   */
  public const SHOPPING_CART_PLATFORM = 'Drupal Commerce';
  /**
   * Shopping cart gateway.
   */
  public const SHOPPING_CART_GATEWAY = 'Paymentsense - Remote Payments';
  /**
   * Plugin extension.
   */
  public const EXTENSION_COMMERCE_PAYMENT_GATEWAY = 'commerce_paymentsense_remotepayments';
  /**
   * Commerce extension.
   */
  public const EXTENSION_COMMERCE = 'commerce';
  /**
   * Url where the request will be sent.
   *
   * @var string|null
   */
  private $url;
  /**
   * Curl handle.
   *
   * @var resource|null
   */
  private $curlHandle;
  /**
   * Curl error number.
   *
   * @var int|null
   */
  private $curlErrorNumber;
  /**
   * Curl info.
   *
   * @var string[]|null
   */
  private $curlInfo;
  /**
   * Curl error message.
   *
   * @var string|null
   */
  private $curlErrorMessage;
  /**
   * Response from curl.
   *
   * @var bool|string|null
   */
  private $curlResponse;
  /**
   * Gateway jwt.
   *
   * @var string|null
   */
  private $gatewayJwt;
  /**
   * Gateway username.
   *
   * @var string|null
   */
  private $gatewayUsername;
  /**
   * Environment.
   *
   * @var string|null
   */
  private $environment;

  /**
   * Curl data to be sent.
   *
   * @var string[]|null
   */
  private $data;
  /**
   * Resolves country iso code.
   *
   * @var \Drupal\commerce_paymentsense_remotepayments\ConnectE\CountryNumericIsoCodeResolver|null
   */
  private $countryNumericIsoCodeResolver;
  /**
   * Minor units converter.
   *
   * @var \Drupal\commerce_price\MinorUnitsConverterInterface|null
   */
  private $minorUnitsConverter;
  /**
   * Status code from gateway.
   *
   * @var int|null
   */
  private $statusCode;
  /**
   * Status message from gateway.
   *
   * @var string|null
   */
  private $statusMessage;
  /**
   * Auth code from gateway.
   *
   * @var mixed|null
   */
  private $authCode;
  /**
   * Cross-reference for a refund request.
   *
   * @var string|null
   */
  private $crossReference;
  /**
   * Access token for a transaction.
   *
   * @var string|null
   */
  private $accessToken;

  /**
   * {@inheritdoc}
   */
  public function setEnvironment(string $environment): self {
    $this->environment = $environment;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setMinorUnitsConverter(MinorUnitsConverterInterface $minorUnitsConverter): ClientInterface {
    $this->minorUnitsConverter = $minorUnitsConverter;
    return $this;
  }

  /**
   * Sets resolves class to resolve country iso code.
   *
   * @return $this
   */
  public function setCountryIsoCodeResolver(CountryNumericIsoCodeResolver $country_numeric_iso_code_resolver): ClientInterface {
    $this->countryNumericIsoCodeResolver = $country_numeric_iso_code_resolver;
    return $this;
  }

  /**
   * Sets gateway jwt to be sent in requests.
   *
   * @return $this
   */
  public function setGatewayJwt(?string $gateway_jwt): self {
    $this->gatewayJwt = $gateway_jwt;
    return $this;
  }

  /**
   * Sets gateway username.
   *
   * @return $this
   */
  public function setGatewayUsername(?string $gatewayUsername): self {
    $this->gatewayUsername = $gatewayUsername;
    return $this;
  }

  /**
   * Makes an HTTP request.
   *
   * @throws ClientException
   *   When request fails.
   */
  private function makeHttpRequest(): void {
    $this->ensureSslEnabled();

    $this->curlResponse = curl_exec($this->curlHandle);

    $this->curlErrorNumber = curl_errno($this->curlHandle);
    $this->curlErrorMessage = curl_error($this->curlHandle);
    $this->curlInfo = curl_getinfo($this->curlHandle);

    $this->logDebug('Curl result', [
      'error number' => $this->curlErrorNumber,
      'error' => $this->curlErrorMessage,
      'info' => $this->curlInfo,
      'response' => $this->curlResponse,
    ]);

    curl_close($this->curlHandle);

    $this->assertNotError();
  }

  /**
   * Ensure SSL is enabled.
   *
   * @throws ClientException
   *   If SSL is not enabled.
   */
  private function ensureSslEnabled(): void {
    if (!\Drupal::request()->isSecure()) {
      throw $this->createClientException(ClientException::CODE_SSL_NOT_ENABLED);
    }
  }

  /**
   * Marks to be sent a GET request.
   *
   * @return $this
   */
  private function setAsGetRequest(): self {
    curl_setopt($this->curlHandle, CURLOPT_POST, FALSE);
    return $this;
  }

  /**
   * Sets the post fields for a POST request.
   *
   * @return $this
   */
  private function setPostData(string $encodeData): self {
    $this->logDebug('Post data', [$encodeData]);
    curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $encodeData);
    return $this;
  }

  /**
   * Checks if the result is an error.
   *
   * @throws ClientException
   *   When curl request fails.
   */
  public function assertNotError(): void {
    if ($this->isCurlError()) {
      throw $this->createClientException(ClientException::CODE_CURL_REQUEST_FAILED);
    }
  }

  /**
   * Checks if the Curl result contains error information.
   *
   * @return bool
   *   Error when the result is an error result.
   */
  private function isCurlError(): bool {
    if ($this->curlErrorNumber !== 0) {
      return TRUE;
    }
    if (!isset($this->curlInfo['http_code'])) {
      return TRUE;
    }
    if (200 != $this->curlInfo['http_code']) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Creates and sets and access token url to be hit.
   *
   * @throws ClientException
   *    When url can not be successfully determined.
   */
  private function createAccessTokenUrl(): void {
    $this->url = $this->combineUrlParts($this->createEntryPointUrl(), static::ENDPOINT_ACCESS_TOKENS);
  }

  /**
   * Creates an Executor that will send the HTTP request.
   *
   * @throws ClientException
   *   If curl is not enabled.
   */
  private function initCurl(): void {

    if (!function_exists('curl_version')) {
      throw $this->createClientException(ClientException::CODE_CURL_NOT_ENABLED);
    }

    $ch = curl_init();

    if (FALSE === $ch) {
      throw $this->createClientException(ClientException::CODE_CURL_NOT_ENABLED);
    }

    if (NULL === $this->gatewayJwt) {
      throw $this->createClientException(ClientException::CODE_GATEWAY_NOT_CONFIGURED);
    }

    $headers = [
      'Cache-Control: no-cache',
      'Authorization: Bearer ' . $this->gatewayJwt,
      'Content-Type: application/json',
    ];

    $this->logDebug('Headers', $headers);

    if (NULL === $this->url) {
      throw $this->createClientException(ClientException::CODE_URL_NOT_BUILT, 'Url not built');
    }

    $this->logDebug('Url', [$this->url]);

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $this->curlHandle = $ch;
  }

  /**
   * Combines url parts with a separator.
   */
  private function combineUrlParts(string ...$parts): string {
    return implode('/', $parts);
  }

  /**
   * Creates entry point for environment.
   *
   * @throws ClientException
   *   When environment is not correctly configured for gateway.
   */
  private function createEntryPointUrl(): string {

    if (NULL === $this->environment) {
      throw $this->createClientException(ClientException::CODE_GATEWAY_NOT_CONFIGURED);
    }

    if ($this->environment === 'test') {
      return static::ENTRY_POINT_URL_TEST;
    }

    return static::ENTRY_POINT_URL_LIVE;
  }

  /**
   * Creates a client exception.
   *
   * @param int $code
   *   Error code.
   * @param string|null $message
   *   Optional message.
   *
   * @return ClientException
   *   Returns the created exception.
   */
  private function createClientException(int $code, string $message = NULL): ClientException {
    return (new ClientException($message ?? 'Request failed', $code))
      ->setCurlStatus($this->curlErrorNumber, $this->curlErrorMessage, $this->curlInfo, $this->curlResponse);
  }

  /**
   * {@inheritdoc}
   */
  public function createAccessTokenForPayment(string $order_prefix, OrderInterface $order, FrameworkPaymentInterface $payment, string $transaction_type): string {

    $address = $this->resolveAddress($order);

    if (NULL === $this->gatewayUsername) {
      throw $this->createClientException(ClientException::CODE_GATEWAY_NOT_CONFIGURED);
    }

    $data = [
      'gatewayUsername' => $this->gatewayUsername,
      'currencyCode' => $this->resolveCurrencyCode($order),
      'amount' => $this->resolveAmountFromPayment($payment),
      'transactionType' => $transaction_type,
      'orderId' => $order->id(),
      'orderDescription' => $this->resolveOrderDescription($order, $order_prefix),
      'userEmailAddress' => $order->getEmail() ?? '',
      'userIpAddress' => $order->getIpAddress() ?? '',
      'userAddress1' => $address->getAddressLine1() ?? '',
      'userAddress2' => $address->getAddressLine2() ?? '',
      'userCity' => $address->getLocality() ?? '',
      'userState' => $address->getAdministrativeArea() ?? '',
      'userPostcode' => $address->getPostalCode() ?? '',
      'userCountryCode' => $this->countryNumericIsoCodeResolver->resolveIsoCode($address->getCountryCode()),
    ];

    $this->createAccessTokenUrl();
    $this->initCurl();
    $this->setPostData($this->encodeWithMetaData($data));
    $this->makeHttpRequest();

    $this->readAccessTokenFromResponse();

    return $this->accessToken;
  }

  /**
   * Decodes curl response json into array.
   *
   * @throws ClientException
   *   When response is empty.
   */
  private function decodeResponseIntoArray(): array {
    if (!$this->curlResponse) {
      throw $this->createClientException(ClientException::CODE_RESPONSE_EMPTY);
    }

    $decoded = json_decode($this->curlResponse, TRUE);
    if (!is_array($decoded)) {
      throw $this->createClientException(ClientException::CODE_RESPONSE_EMPTY);
    }

    return $decoded;
  }

  /**
   * Reads access token from gateway response.
   *
   * @throws ClientException
   */
  private function readAccessTokenFromResponse(): void {

    $response = $this->decodeResponseIntoArray();

    $access_token = $response['id'] ?? NULL;

    $this->accessToken = $access_token;
  }

  /**
   * Composes order description to be sent with access token requests.
   */
  private function resolveOrderDescription(OrderInterface $order, string $order_prefix): string {
    return $order_prefix . $order->id();
  }

  /**
   * Resolves amount from payment.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   Commerce payment object.
   *
   * @return int
   *   Minor integer units - amount 3.45 becomes 345, for example.
   *
   * @throws ClientException
   */
  private function resolveAmountFromPayment(FrameworkPaymentInterface $payment): int {
    $amount = $payment->getAmount();
    if (NULL === $amount) {
      throw $this->createClientException(ClientException::CODE_AMOUNT_COULD_NOT_BE_RESOLVED);
    }
    return $this->minorUnitsConverter->toMinorUnits($amount);
  }

  /**
   * Resolves currency code from a commerce order.
   */
  private function resolveCurrencyCode(OrderInterface $order): string {
    return $order->getStore()->getDefaultCurrency()->getNumericCode();
  }

  /**
   * Encodes data given to be sent with requests.
   */
  private function encodeData(array $data): string {
    return json_encode($data);
  }

  /**
   * Attaches meta data information.
   *
   * Attaches meta data to given $data and encodes it into a json object.
   */
  private function encodeWithMetaData(array $data): string {
    $extension_list = \Drupal::service('extension.list.module');
    $shopping_cart_info = $extension_list->getExtensionInfo(Client::EXTENSION_COMMERCE);
    $plugin_info = $extension_list->getExtensionInfo(Client::EXTENSION_COMMERCE_PAYMENT_GATEWAY);

    $data['metaData'] = [
      'shoppingCartUrl'      => \Drupal::service('router.request_context')->getCompleteBaseUrl(),
      'shoppingCartPlatform' => self::SHOPPING_CART_PLATFORM,
      'shoppingCartVersion'  => $this->stripDrupalVersionPrefix((string) $shopping_cart_info['version'] ?? ''),
      'shoppingCartGateway'  => self::SHOPPING_CART_GATEWAY,
      'pluginVersion'        => (string) $plugin_info['version'] ?? '',
    ];

    return $this->encodeData($data);
  }

  /**
   * Strips of drupal version from commerce version string.
   *
   * For example, converts 8.x-2.25 to 2.25
   */
  private function stripDrupalVersionPrefix(string $commerceFullVersion): string {
    $parts = explode('-', $commerceFullVersion);
    return $parts[1] ?? $commerceFullVersion;
  }

  /**
   * Resolves address form order.
   *
   * @return \Drupal\address\AddressInterface
   *   Returns address.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  private function resolveAddress(OrderInterface $order): AddressInterface {
    return $order->getBillingProfile()->address->first();
  }

  /**
   * Sets url to retrieve payment.
   *
   * @throws ClientException
   *   When url can not be created.
   */
  private function createRetrievePaymentUrl(string $access_token): void {
    $this->url = $this->combineUrlParts(
      $this->createEntryPointUrl(),
      static::ENDPOINT_PAYMENTS,
      $access_token
    );
  }

  /**
   * {@inheritdoc}
   */
  public function verifyPayment(string $access_token): ClientInterface {
    $this->retrievePayment($access_token);
    $this->verifyPaymentStatus();
    return $this;
  }

  /**
   * Retrieves payment from Connect E.
   *
   * @throws ClientException
   *   When payment retrieval fails.
   */
  private function retrievePayment(string $access_token): void {
    $this->createRetrievePaymentUrl($access_token);
    $this->initCurl();
    $this->setAsGetRequest();
    $this->makeHttpRequest();
    $this->readPaymentStatusFromGatewayResponse();
  }

  /**
   * Reads payment status from gateway response.
   *
   * @throws ClientException
   *    When payment status can not be read.
   */
  private function readPaymentStatusFromGatewayResponse() {
    $response = $this->decodeResponseIntoArray();
    $this->statusCode = $response['statusCode'] ?? '';
    $this->statusMessage = $response['message'] ?? '';
    $this->crossReference = $response['crossReference'] ?? '';
  }

  /**
   * Verifies payment status after a payment request.
   *
   * @throws ClientException
   *   When payment has an unknown status or it is declined.
   */
  private function verifyPaymentStatus(): void {
    if (!$this->isStatusCodeKnown()) {
      throw $this->createClientException(ClientException::CODE_UNKNOWN_STATUS_CODE);
    }

    if (!$this->isStatusSuccessful()) {
      throw $this->createClientException(ClientException::CODE_PAYMENT_FAILED);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function refund(string $order_prefix, FrameworkPaymentInterface $framework_payment, ?Price $amount = NULL): ClientInterface {

    $this->readCrossReference($framework_payment->getRemoteId());
    $order = $framework_payment->getOrder();

    if (NULL == $this->gatewayUsername) {
      throw $this->createClientException(ClientException::CODE_GATEWAY_NOT_CONFIGURED);
    }

    $data = [
      'gatewayUsername' => $this->gatewayUsername,
      'currencyCode' => $this->resolveCurrencyCode($order),
      'amount' => (string) $this->resolveRefundAmount($framework_payment, $amount),
      'transactionType' => 'REFUND',
      'orderId' => $order->id(),
      'orderDescription' => $this->resolveOrderDescription($order, $order_prefix),
      'crossReference' => $this->crossReference,
    ];

    $this->createAccessTokenUrl();
    $this->initCurl();
    $this->setPostData($this->encodeWithMetaData($data));
    $this->makeHttpRequest();
    $this->readAccessTokenFromResponse();

    $this->createRefundUrl();
    $this->initCurl();
    $data = ['crossReference' => $this->crossReference];
    $this->setPostData($this->encodeData($data));
    $this->makeHttpRequest();
    $this->readRefundStatusFromGatewayResponse();
    $this->verifyRefundStatus();

    return $this;
  }

  /**
   * Resolves refund amount.
   *
   * @throws ClientException
   *   When amount can not be resolved.
   */
  private function resolveRefundAmount(FrameworkPaymentInterface $framework_payment, ?Price $partial_amount = NULL): int {
    if (NULL !== $partial_amount) {
      return $this->minorUnitsConverter->toMinorUnits($partial_amount);
    }

    return $this->resolveAmountFromPayment($framework_payment);
  }

  /**
   * Read refund status from gateway response.
   *
   * @throws ClientException
   *   When refund status can not be read.
   */
  private function readRefundStatusFromGatewayResponse(): void {
    $data = $this->decodeResponseIntoArray();
    $this->statusCode = $data['statusCode'] ?? NULL;
    $this->statusMessage = $data['message'] ?? '';
    $this->authCode = $data['authCode'] ?? NULL;
  }

  /**
   * Checks if the status code is a known status code.
   */
  private function isStatusCodeKnown(): bool {
    if (NULL === $this->statusCode) {
      return FALSE;
    }

    if (!in_array($this->statusCode, [
      self::PAYMENT_STATUS_SUCCESS,
      self::PAYMENT_STATUS_DECLINED,
      self::PAYMENT_STATUS_DUPLICATED,
      self::PAYMENT_STATUS_FAILED,
      self::PAYMENT_STATUS_REFERRED,
    ])) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Checks if status code is successful.
   */
  private function isStatusSuccessful(): bool {
    return intval($this->statusCode) === self::PAYMENT_STATUS_SUCCESS;
  }

  /**
   * Checks if the status is duplicated.
   */
  private function isStatusDuplicated(): bool {
    return intval($this->statusCode) === self::PAYMENT_STATUS_DUPLICATED;
  }

  /**
   * Verifies refund status.
   *
   * @throws ClientException
   *    When verification fails.
   */
  private function verifyRefundStatus(): void {

    if ($this->isStatusDuplicated()) {
      throw $this->createClientException(ClientException::CODE_REFUND_DUPLICATED);
    }
    if (!$this->isStatusSuccessful()) {
      throw $this->createClientException(ClientException::CODE_REFUND_FAILED);
    }
  }

  /**
   * Reads cross-reference for a refund.
   *
   * @throws ClientException
   *    When cross-reference can not be read for refund.
   */
  private function readCrossReference(string $access_token): void {
    $this->retrievePayment($access_token);
    if (NULL === $this->crossReference) {
      throw $this->createClientException(ClientException::CODE_COULD_NOT_READ_CROSS_REFERENCE);
    }
  }

  /**
   * Sets the url where a refund request will be sent.
   *
   * @throws ClientException
   *   When access token is empty.
   */
  private function createRefundUrl(): ClientInterface {
    if (NULL === $this->accessToken) {
      throw $this->createClientException(ClientException::CODE_ACCESS_TOKEN_EMPTY);
    }
    $this->url = $this->combineUrlParts($this->createEntryPointUrl(), static::ENDPOINT_CROSS_REFERENCE_PAYMENTS, $this->accessToken);
    return $this;
  }

}
