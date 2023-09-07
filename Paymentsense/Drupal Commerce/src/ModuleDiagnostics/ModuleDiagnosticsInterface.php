<?php

namespace Drupal\commerce_paymentsense_remotepayments\ModuleDiagnostics;

use Drupal\Core\Extension\ExtensionList;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interface to module diagnostics operations.
 */
interface ModuleDiagnosticsInterface {

  /**
   * Sets framework request.
   */
  public function setFrameworkRequest(Request $request): self;

  /**
   * Returns the prepared symfony response to be returned from the controller.
   */
  public function getFrameworkResponse(): ?Response;

  /**
   * Executes action requested in the request.
   */
  public function executeAction(): self;

  /**
   * Sets extension list to get module information from drupal.
   *
   * Extension list is a drupal service registered in the container.
   */
  public function setExtensionList(ExtensionList $extension_list): self;

}
