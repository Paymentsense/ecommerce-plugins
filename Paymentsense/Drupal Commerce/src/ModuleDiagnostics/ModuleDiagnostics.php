<?php

namespace Drupal\commerce_paymentsense_remotepayments\ModuleDiagnostics;

use Drupal\Core\Extension\ExtensionList;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Module diagnostics.
 */
class ModuleDiagnostics implements ModuleDiagnosticsInterface {

  const ACTION_INFO = 'info';
  const ACTION_CHECKSUMS = 'checksums';

  const PARAM_ACTION = 'action';
  const PARAM_EXTENDED_INFO = 'extended_info';
  const PARAM_FILE_LIST_KEY = 'data';
  const PARAM_OUTPUT_FORMAT_KEY = 'output';

  const EXTENDED_INFO_TRUE_VALUE = 'true';

  const MODULE_NAME_PLUGIN = 'commerce_paymentsense_remotepayments';
  const MODULE_NAME_SHOPPING_CART = 'commerce';

  public const OUTPUT_JSON = 'json';
  public const OUTPUT_TEXT = 'text';

  public const CONTENT_TYPE = 'Content-Type';
  public const CONTENT_TYPE_TEXT_PLAIN = 'text/plain';
  public const CONTENT_TYPE_APPLICATION_JSON = 'application/json';

  /**
   * A symfony request that will contain the data required to fulfil the action.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  private $frameworkRequest;
  /**
   * Action requested.
   *
   * @var string|null
   */
  private $action;
  /**
   * Extension list service to get module information.
   *
   * @var \Drupal\Core\Extension\ExtensionList
   */
  private $extensionList;
  /**
   * Data that will be sent in the output.
   *
   * @var array
   */
  private $outputData = [];
  /**
   * Format to send the output in. text, json and so on.
   *
   * @var string|null
   */
  private $outputFormat;
  /**
   * Output that will be sent to the client for presentation.
   *
   * @var string
   */
  private $output = '';
  /**
   * Response that contains the result of a diagnostics request.
   *
   * @var \Symfony\Component\HttpFoundation\Response|null
   */
  private $frameworkResponse;
  /**
   * List of files for which the checksums are requested.
   *
   * @var array
   */
  private $checksumsFileList = [];

  /**
   * Sets framework request.
   *
   * @return $this
   */
  public function setFrameworkRequest(Request $request): ModuleDiagnosticsInterface {
    $this->frameworkRequest = $request;
    return $this;
  }

  /**
   * Sets extension list to get module information from drupal.
   *
   * Extension list is a drupal service registered in the container.
   */
  public function setExtensionList(ExtensionList $extension_list): ModuleDiagnosticsInterface {
    $this->extensionList = $extension_list;
    return $this;
  }

  /**
   * Returns the prepared symfony response to be returned from the controller.
   */
  public function getFrameworkResponse(): ?Response {
    return $this->frameworkResponse;
  }

  /**
   * Executes action.
   *
   * @return $this
   *
   * @throws \Drupal\commerce_paymentsense_remotepayments\ModuleDiagnostics\DiagnosticsException
   *   When action fails.
   */
  public function executeAction(): ModuleDiagnosticsInterface {

    if (NULL === $this->frameworkRequest) {
      throw new DiagnosticsException('Request not provided.');
    }

    $this->resolveAction();
    if ($this->isActionInfo()) {
      $this->executeInfo();
    }

    if ($this->isActionChecksums()) {
      $this->executeChecksums();
    }

    $this->encodeOutput();
    $this->createResponse();

    return $this;
  }

  /**
   * Resolves action.
   *
   * @throws \Drupal\commerce_paymentsense_remotepayments\ModuleDiagnostics\DiagnosticsException
   *   When an action is not recognised.
   */
  private function resolveAction(): void {
    $action = $this->frameworkRequest->query->get(static::PARAM_ACTION);

    $available_actions = [static::ACTION_INFO, static::ACTION_CHECKSUMS];
    if (NULL === $action || !in_array($action, $available_actions)) {
      throw new DiagnosticsException('Action unknown');
    }

    $this->action = $action;
  }

  /**
   * Checks if action is info.
   */
  private function isActionInfo(): bool {
    return static::ACTION_INFO === $this->action;
  }

  /**
   * Checks if action is for checksums.
   */
  private function isActionChecksums(): bool {
    return static::ACTION_CHECKSUMS === $this->action;
  }

  /**
   * Executes info action.
   *
   * @throws DiagnosticsException
   *   When info action fails.
   */
  private function executeInfo(): void {
    if (NULL === $this->extensionList) {
      throw new DiagnosticsException('Extension list module not set.');
    }

    if ($this->isRequestingExtendedInfo()) {
      $this->createExtendedInfoOutputData();
      return;
    }

    $this->createInfoOutputData();
  }

  /**
   * Checks if the request contains extended info param set to true.
   */
  private function isRequestingExtendedInfo(): bool {
    return static::EXTENDED_INFO_TRUE_VALUE === $this->frameworkRequest->query->get(self::PARAM_EXTENDED_INFO, 'false');
  }

  /**
   * Creates extended info data.
   */
  private function createExtendedInfoOutputData(): void {
    $plugin_info = $this->extensionList->getExtensionInfo(static::MODULE_NAME_PLUGIN);
    $shopping_cart_info = $this->extensionList->getExtensionInfo(static::MODULE_NAME_SHOPPING_CART);
    $this->outputData = [
      'Module Name' => $plugin_info['name'] ?? '',
      'Module Installed Version' => (string) $plugin_info['version'] ?? '',
      'Drupal Version'   => \Drupal::VERSION,
      'Drupal Commerce Version' => $this->stripDrupalVersionPrefix((string) $shopping_cart_info['version'] ?? ''),
      'PHP Version'         => phpversion(),
    ];
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
   * Creates simple info output.
   */
  private function createInfoOutputData(): void {
    $plugin_info = $this->extensionList->getExtensionInfo(static::MODULE_NAME_PLUGIN);
    $this->outputData = [
      'Module Name' => $plugin_info['name'] ?? '',
      'Module Installed Version' => (string) $plugin_info['version'] ?? '',
    ];
  }

  /**
   * Executes a checksum request.
   */
  private function executeChecksums(): void {
    $this->resolveFileList();
    $this->createChecksums();
  }

  /**
   * Resolves file list.
   *
   * File list is calculated from http request object.
   */
  private function resolveFileList(): void {
    $data = $this->frameworkRequest->request->get(static::PARAM_FILE_LIST_KEY);
    if (!is_array($data)) {
      return;
    }

    $this->checksumsFileList = $data;
  }

  /**
   * Calculates and add checksums for the given files.
   *
   * @return $this
   */
  public function createChecksums(): self {
    foreach ($this->checksumsFileList as $key => $file) {
      $filename                            = \DRUPAL_ROOT . DIRECTORY_SEPARATOR . $file;
      $this->outputData['Checksums'][$key] = $this->calculateFileChecksum($filename);
    }

    return $this;
  }

  /**
   * Calculates checksum for given file.
   */
  private function calculateFileChecksum(string $file_path): ?string {
    return is_file($file_path)
      ? sha1_file($file_path)
      : NULL;
  }

  /**
   * Encodes the output based on the value of output param in url.
   *
   * @throws DiagnosticsException
   *   When output requested is not available.
   */
  private function encodeOutput(): void {
    $this->resolveOutputFormat();
    if (static::OUTPUT_JSON === $this->outputFormat) {
      $this->encodeOutputAsJson();
      return;
    }

    if (static::OUTPUT_TEXT === $this->outputFormat) {
      $this->encodeOutputAsText();
      return;
    }

    throw new DiagnosticsException('Unknown output requested');
  }

  /**
   * Resolves the output format from the parameter output.
   */
  private function resolveOutputFormat(): void {
    $this->outputFormat = $this->frameworkRequest->query->get(self::PARAM_OUTPUT_FORMAT_KEY, static::OUTPUT_TEXT);
  }

  /**
   * Encodes output as json.
   */
  public function encodeOutputAsJson(): void {
    $this->output = json_encode($this->outputData);
  }

  /**
   * Encodes output as text.
   */
  public function encodeOutputAsText(): void {
    $this->output = $this->convertArrayToString($this->outputData);
  }

  /**
   * Converts array to string.
   */
  private function convertArrayToString($arr, $indent = ''): string {
    $result         = '';
    $indent_pattern = '  ';
    foreach ($arr as $key => $value) {
      if ('' !== $result) {
        $result .= PHP_EOL;
      }
      if (is_array($value)) {
        $value = PHP_EOL . $this->convertArrayToString($value, $indent . $indent_pattern);
      }
      $result .= $indent . $key . ': ' . $value;
    }
    return $result;
  }

  /**
   * Prepares response to be presented.
   *
   * Builds response from the data provided and encodes it using an encoder.
   * This data is passed by the use case.
   */
  private function createResponse(): void {

    $response = new Response($this->output, Response::HTTP_OK);
    $response->headers->set('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store');
    $response->headers->set('Pragma', 'no-cache');

    if (static::OUTPUT_JSON === $this->outputFormat) {
      $response->headers->set(static::CONTENT_TYPE, static::CONTENT_TYPE_APPLICATION_JSON);
    }

    if (static::OUTPUT_TEXT === $this->outputFormat) {
      $response->headers->set(static::CONTENT_TYPE, static::CONTENT_TYPE_TEXT_PLAIN);
    }

    $this->frameworkResponse = $response;
  }

}
