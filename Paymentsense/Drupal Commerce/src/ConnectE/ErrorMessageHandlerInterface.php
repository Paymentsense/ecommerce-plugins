<?php

namespace Drupal\commerce_paymentsense_remotepayments\ConnectE;

use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Handles error messages.
 */
interface ErrorMessageHandlerInterface {

  /**
   * Handles exceptions thrown during the payment process or refund.
   *
   * Parameters are passed to message in strict order to be translated.
   *
   * @throws \Drupal\commerce_payment\Exception\PaymentGatewayException
   *   Thrown to halt the payment process.
   */
  public function handleException(ClientExceptionInterface $exception, OrderInterface $order): string;

}
