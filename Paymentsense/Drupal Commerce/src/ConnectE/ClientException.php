<?php

namespace Drupal\commerce_paymentsense_remotepayments\ConnectE;

/**
 * Thrown when an operation fails.
 */
class ClientException extends \Exception implements ClientExceptionInterface {
  /**
   * Code for a refund failure.
   */
  public const CODE_REFUND_FAILED = 1;
  /**
   * When amount can not be resolved for a refund or a payment.
   */
  public const CODE_AMOUNT_COULD_NOT_BE_RESOLVED = 2;
  /**
   * When access token is empty.
   */
  public const CODE_ACCESS_TOKEN_EMPTY = 3;
  /**
   * Code for payment failure.
   */
  public const CODE_PAYMENT_FAILED = 4;
  /**
   * Refund duplicated code.
   */
  public const CODE_REFUND_DUPLICATED = 5;
  /**
   * When response is empty from the gateway.
   */
  public const CODE_RESPONSE_EMPTY = 6;
  /**
   * When curl is not enabled.
   */
  public const CODE_CURL_NOT_ENABLED = 7;
  /**
   * When gateway is not properly configured.
   */
  public const CODE_GATEWAY_NOT_CONFIGURED = 8;
  /**
   * When curl request fails.
   */
  public const CODE_CURL_REQUEST_FAILED = 9;
  /**
   * When cross reference can not be read.
   */
  public const CODE_COULD_NOT_READ_CROSS_REFERENCE = 10;
  /**
   * When status code can not be determined.
   */
  public const CODE_UNKNOWN_STATUS_CODE = 11;
  /**
   * When TLS is not enabled.
   */
  public const CODE_SSL_NOT_ENABLED = 12;
  /**
   * When url can not be determined.
   */
  public const CODE_URL_NOT_BUILT = 13;
  /**
   * Curl error number.
   *
   * @var int|null
   */
  private $curlErrorNumber;
  /**
   * Curl error message.
   *
   * @var string|null
   */
  private $curlErrorMessage;
  /**
   * Curl info.
   *
   * @var array|null
   */
  private $curlInfo;
  /**
   * Curl response.
   *
   * @var string|null
   */
  private $curlResponse;

  /**
   * Sets the curl status from the parameters provided.
   *
   * @param int|null $curl_error_number
   *   Curl error number following a curl call.
   * @param string|null $curl_error_message
   *   Curl error message following a curl call.
   * @param array|null $curl_info
   *   Curl info from a curl_info call.
   * @param string|null $curl_response
   *   Response from curl_exec call.
   *
   * @return $this
   */
  public function setCurlStatus(?int $curl_error_number, ?string $curl_error_message, ?array $curl_info, ?string $curl_response): ClientExceptionInterface {
    $this->curlErrorNumber = $curl_error_number;
    $this->curlErrorMessage = $curl_error_message;
    $this->curlInfo = $curl_info;
    $this->curlResponse = $curl_response;

    return $this;
  }

  /**
   * Returns curl error number.
   */
  public function getCurlErrorNumber(): ?int {
    return $this->curlErrorNumber;
  }

  /**
   * Returns curl error message.
   */
  public function getCurlErrorMessage(): ?string {
    return $this->curlErrorMessage;
  }

  /**
   * Returns curl info.
   */
  public function getCurlInfo(): ?array {
    return $this->curlInfo;
  }

  /**
   * Returns curl response.
   */
  public function getCurlResponse(): ?string {
    return $this->curlResponse;
  }

}
