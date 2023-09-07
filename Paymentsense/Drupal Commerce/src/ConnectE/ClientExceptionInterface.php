<?php

namespace Drupal\commerce_paymentsense_remotepayments\ConnectE;

/**
 * Interface to client exceptions.
 */
interface ClientExceptionInterface {

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
  public function setCurlStatus(?int $curl_error_number, ?string $curl_error_message, ?array $curl_info, ?string $curl_response): ClientExceptionInterface;

  /**
   * Returns curl info.
   */
  public function getCurlInfo(): ?array;

  /**
   * Returns curl response.
   */
  public function getCurlResponse(): ?string;

  /**
   * Returns curl error message.
   */
  public function getCurlErrorMessage(): ?string;

  /**
   * Returns curl error number.
   */
  public function getCurlErrorNumber(): ?int;

  /**
   * Returns error message.
   *
   * @return string
   *   Error message.
   */
  public function getMessage();

  /**
   * Returns the error code.
   *
   * @return int
   *   Error code.
   */
  public function getCode();

}
