<?php

namespace Drupal\commerce_paymentsense_remotepayments\Controller;

use Drupal\commerce_paymentsense_remotepayments\ModuleDiagnostics\ModuleDiagnostics;
use Drupal\commerce_paymentsense_remotepayments\ModuleDiagnostics\ModuleDiagnosticsInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides plugin support for MIE tool for diagnostics.
 */
class DiagnosticsController extends ControllerBase implements ContainerInjectionInterface {
  /**
   * Diagnostics service.
   *
   * @var \Drupal\commerce_paymentsense_remotepayments\ModuleDiagnostics\ModuleDiagnosticsInterface
   */
  private $moduleDiagnostics;

  /**
   * Constructor.
   *
   * @param \Drupal\commerce_paymentsense_remotepayments\ModuleDiagnostics\ModuleDiagnosticsInterface $module_diagnostics
   *   Service that provides diagnostics functions.
   */
  public function __construct(ModuleDiagnosticsInterface $module_diagnostics) {
    $this->moduleDiagnostics = $module_diagnostics;
  }

  /**
   * Used to inject dependencies into the controller.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Dependency injection container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get(ModuleDiagnostics::class)
    );
  }

  /**
   * Action that will receive Mie requests.
   */
  public function diagnostics(Request $request): Response {

    return $this->moduleDiagnostics->setFrameworkRequest($request)
      ->setExtensionList(\Drupal::service('extension.list.module'))
      ->executeAction()
      ->getFrameworkResponse() ?? new Response('No response.');
  }

}
