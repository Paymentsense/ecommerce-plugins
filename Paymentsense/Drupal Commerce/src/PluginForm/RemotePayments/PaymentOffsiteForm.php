<?php

namespace Drupal\commerce_paymentsense_remotepayments\PluginForm\RemotePayments;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\commerce_paymentsense_remotepayments\ConnectE\Client;
use Drupal\commerce_paymentsense_remotepayments\ConnectE\ClientException;
use Drupal\commerce_paymentsense_remotepayments\ConnectE\ClientInterface;
use Drupal\commerce_paymentsense_remotepayments\ConnectE\CountryNumericIsoCodeResolver;
use Drupal\commerce_paymentsense_remotepayments\ConnectE\ErrorMessageHandlerInterface;
use Drupal\commerce_price\MinorUnitsConverterInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders markup needed to render the iframe.
 *
 * Uses theme attaches with the form to render the markup.
 * Access token is created in buildConfigurationForm and passed to the front end
 * in JS settings.
 */
class PaymentOffsiteForm extends BasePaymentOffsiteForm implements ContainerInjectionInterface {

  /**
   * Minor units converter.
   *
   * @var \Drupal\commerce_price\MinorUnitsConverterInterface
   */
  private $minorUnitsConverter;
  /**
   * Handles error messages during form configuration.
   *
   * @var \Drupal\commerce_paymentsense_remotepayments\ConnectE\ErrorMessageHandlerInterface
   */
  private $errorMessageHandler;
  /**
   * Connect e client.
   *
   * @var \Drupal\commerce_paymentsense_remotepayments\ConnectE\ClientInterface
   */
  private $client;

  /**
   * Constructs a PaymentOffsiteForm.
   *
   * @param \Drupal\commerce_price\MinorUnitsConverterInterface $minor_units_converter
   *   Minor units converter.
   * @param \Drupal\commerce_paymentsense_remotepayments\ConnectE\ClientInterface $client
   *   Connect e client.
   * @param \Drupal\commerce_paymentsense_remotepayments\ConnectE\ErrorMessageHandlerInterface $error_message_handler
   *   Used to handle errors during form configuration.
   */
  public function __construct(MinorUnitsConverterInterface $minor_units_converter, ClientInterface $client, ErrorMessageHandlerInterface $error_message_handler) {
    $this->minorUnitsConverter = $minor_units_converter;
    $this->errorMessageHandler = $error_message_handler;
    $this->client = $client;
    $this->client->setLogger(\Drupal::logger(Client::EXTENSION_COMMERCE_PAYMENT_GATEWAY));
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('commerce_price.minor_units_converter'),
      $container->get(ClientInterface::class),
      $container->get(ErrorMessageHandlerInterface::class)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    $order = $payment->getOrder();

    $configuration = $this->entity->getPaymentGateway()->getPluginConfiguration();

    $gateway_jwt = $configuration['gateway_jwt'] ?? '';
    $gateway_username = $configuration['gateway_username'] ?? '';
    $mode = $configuration['mode'] ?? '';
    $order_prefix = $configuration['order_prefix'] ?? '';
    $transaction_type = $configuration['transaction_type'] ?? '';

    try {

      $this->client
        ->setGatewayJwt($gateway_jwt)
        ->setGatewayUsername($gateway_username)
        ->setCountryIsoCodeResolver(new CountryNumericIsoCodeResolver())
        ->setMinorUnitsConverter($this->minorUnitsConverter)
        ->setEnvironment($mode);
      $payment_access_token = $this->client->createAccessTokenForPayment($order_prefix, $order, $payment, $transaction_type);

      $js_settings = [
        'amount' => $this->minorUnitsConverter->toMinorUnits($payment->getAmount()),
        'currencyCode' => $order->getStore()->getDefaultCurrency()->getNumericCode(),
        'accessCode' => Html::escape($payment_access_token),
        'returnUrl' => UrlHelper::stripDangerousProtocols($form['#return_url']),
      ];

      $form['#theme'] = 'paymentsense_offsite_form';
      $form['#attached']['drupalSettings']['commerce_remotepayments'] = $js_settings;
      $form['#attached']['library'][] = 'commerce_paymentsense_remotepayments/paymentsense_checkout_' . $mode;

      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#attributes' => ['id' => 'paymentsense-submit-payment-btn'],
        '#value' => $this->t('Pay with Paymentsense'),
      ];

      $form['actions']['cancel'] = [
        '#type' => 'link',
        '#title' => $this->t('Cancel'),
        '#url' => Url::fromUri($form['#cancel_url']),
      ];

    }
    catch (ClientException $exception) {
      $this->errorMessageHandler->handleException($exception, $order);
    }

    return $form;
  }

  /**
   * Validates configuration form.
   *
   * As payment is through a javascript form submit, if the request reaches
   * here that means the javascript didn't load for some reason.
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    throw new PaymentGatewayException('Please fill in your card details.');
  }

}
