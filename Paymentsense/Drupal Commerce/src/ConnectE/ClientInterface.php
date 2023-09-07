<?php

namespace Drupal\commerce_paymentsense_remotepayments\ConnectE;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface as FrameworkPaymentInterface;
use Drupal\commerce_price\MinorUnitsConverterInterface;
use Drupal\commerce_price\Price;
use Psr\Log\LoggerInterface;

/**
 * Client that is responsible for sending HTTP requests to gateway.
 */
interface ClientInterface {

  /**
   * Sets the environment that will be used to connect to the gateway.
   */
  public function setEnvironment(string $environment);

  /**
   * Sets the minor units converter.
   *
   * Used to convert order amount without decimals.
   */
  public function setMinorUnitsConverter(MinorUnitsConverterInterface $minorUnitsConverter);

  /**
   * Verifies if payment attempted was successful.
   *
   * @throws ClientException
   *   When verification fails.
   */
  public function verifyPayment(string $access_token): ClientInterface;

  /**
   * Performs a refund.
   *
   * @return $this
   *
   * @throws ClientException
   *   When the refund is not successful.
   */
  public function refund(string $order_prefix, FrameworkPaymentInterface $framework_payment, ?Price $amount = NULL): ClientInterface;

  /**
   * Creates access token for payment.
   *
   * @throws ClientException
   *   When access token can not be created.
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   *   When address can not be resolved.
   */
  public function createAccessTokenForPayment(string $order_prefix, OrderInterface $order, FrameworkPaymentInterface $payment, string $transaction_type): string;

  /**
   * Enables logging.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger to enable logging.
   */
  public function setLogger(LoggerInterface $logger);

}
