<?php

namespace Drupal\commerce_paymentsense_remotepayments\ConnectE;

/**
 * Resolves numeric country iso code.
 */
interface CountryNumericIsoCodeResolverInterface {

  /**
   * Resolves numeric iso code for the given string iso code.
   */
  public function resolveIsoCode(?string $countryCode): ?string;

}
