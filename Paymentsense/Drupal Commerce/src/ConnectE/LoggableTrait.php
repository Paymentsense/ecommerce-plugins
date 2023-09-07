<?php

namespace Drupal\commerce_paymentsense_remotepayments\ConnectE;

use Psr\Log\LoggerInterface;

/**
 * Trait to be used to enable logging in an object.
 *
 * Logger need to be set using the setLogger method.
 */
trait LoggableTrait {
  /**
   * Logger instance.
   *
   * @var \Psr\Log\LoggerInterface|null
   */
  private $logger;

  /**
   * Sets the logger.
   */
  public function setLogger(LoggerInterface $logger): self {
    $this->logger = $logger;
    return $this;
  }

  /**
   * Logs debug information.
   *
   * If logger is not set simply skips the operation.
   *
   * @see LoggerInterface::debug()
   */
  private function logDebug(string $message, array $context = []): void {
    if (NULL === $this->logger) {
      return;
    }
    $this->logger->debug($message . ' ' . json_encode($context));
  }

}
