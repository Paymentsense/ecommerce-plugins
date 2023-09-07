<?php

namespace Drupal\commerce_paymentsense_remotepayments\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentStorageInterface;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\commerce_paymentsense_remotepayments\ConnectE\Client;
use Drupal\commerce_paymentsense_remotepayments\ConnectE\ClientException;
use Drupal\commerce_paymentsense_remotepayments\ConnectE\ClientInterface;
use Drupal\commerce_paymentsense_remotepayments\ConnectE\ErrorMessageHandlerInterface;
use Drupal\commerce_price\Price;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the Paymentsense remote payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "commerce_paymentsense_remotepayments",
 *   label = @Translation("Paymentsense - Remote Payments"),
 *   display_label = @Translation("Paymentsense"),
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_paymentsense_remotepayments\PluginForm\RemotePayments\PaymentOffsiteForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "mastercard", "visa",
 *   },
 *   requires_billing_information = FALSE,
 * )
 */
class RemotePayments extends OffsitePaymentGatewayBase implements SupportsRefundsInterface {


  /**
   * Client used to communicate with connect e gateway.
   *
   * @var \Drupal\commerce_paymentsense_remotepayments\ConnectE\ClientInterface
   */
  private $client;
  /**
   * Handles error messages.
   *
   * @var \Drupal\commerce_paymentsense_remotepayments\ConnectE\ErrorMessageHandlerInterface
   */
  private $errorMessageHandler;

  /**
   * {@inheritdoc}
   *
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, TimeInterface $time, ClientInterface $client, ErrorMessageHandlerInterface $error_message_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time);
    $this->client = $client;
    $this->client->setLogger(\Drupal::logger(Client::EXTENSION_COMMERCE_PAYMENT_GATEWAY));
    $this->errorMessageHandler = $error_message_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_payment_type'),
      $container->get('plugin.manager.commerce_payment_method_type'),
      $container->get('datetime.time'),
      $container->get(ClientInterface::class),
      $container->get(ErrorMessageHandlerInterface::class)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'gateway_username' => '',
      'gateway_jwt' => '',
      'order_prefix' => 'DC-',
      'transaction_type' => '',

    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {

    $form = parent::buildConfigurationForm($form, $form_state);

    $form['gateway_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Gateway Username/URL:'),
      '#description' => $this->t('This is the gateway username or URL.'),
      '#default_value' => $this->configuration['gateway_username'] ?? '',
      '#required' => TRUE,
    ];

    $form['gateway_jwt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Gateway JWT:'),
      '#description' => $this->t('This is the gateway JWT.'),
      '#default_value' => $this->configuration['gateway_jwt'] ?? '',
      '#required' => TRUE,
    ];

    $form['order_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Order Prefix:'),
      '#description' => $this->t('This is the order prefix that you will see in the Merchant Portal.'),
      '#default_value' => $this->configuration['order_prefix'] ?? '',
      '#required' => TRUE,
    ];

    $form['transaction_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Transaction Type:'),
      '#options' => [
        'SALE' => $this->t('Sale'),
      ],
      '#description' => $this->t('If you wish to obtain authorisation for the payment only, as you intend to manually collect the payment via the Merchant Portal, choose Pre-auth.'),
      '#default_value' => $this->configuration['transaction_type'] ?? '',
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $values = $form_state->getValue($form['#parents']);

    $this->configuration['gateway_username'] = $values['gateway_username'];
    $this->configuration['gateway_jwt'] = $values['gateway_jwt'];
    $this->configuration['transaction_type'] = $values['transaction_type'];
    $this->configuration['order_prefix'] = $values['order_prefix'];
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    try {
      /** @var \Drupal\commerce_payment\PaymentStorageInterface $payment_storage */
      $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');

      $this->client->setGatewayJwt($this->configuration['gateway_jwt']);
      $this->client->setEnvironment($this->configuration['mode']);
      $access_token = $this->readAccessTokenFromRequest();
      $this->client->verifyPayment($access_token);
      $this->writePayment($this->parentEntity->id(), $access_token, $payment_storage, $order);
    }
    catch (ClientException $exception) {
      $this->errorMessageHandler->handleException($exception, $order);
    }
  }

  /**
   * Creates access token from gateway response.
   *
   * @throws \Drupal\commerce_payment\Exception\PaymentGatewayException
   *   When access token is empty in payment gateway response.
   */
  private function readAccessTokenFromRequest(): string {

    $keys = ['paymentToken', 'accessToken'];

    foreach ($keys as $key) {
      $access_token = \Drupal::request()->request->get($key);
      if (NULL !== $access_token) {
        return $access_token;
      }
    }

    $message = (string) $this->t('Access token is empty.');
    $this->messenger->addError($message);
    throw new PaymentGatewayException($message);
  }

  /**
   * Writes the payment after a successful payment.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   When payment can not be stored.
   */
  public function writePayment(string $payment_gateway, string $access_token, PaymentStorageInterface $payment_storage, OrderInterface $order): void {
    $plugin_payment = $payment_storage->create([
      'state' => 'completed',
      'amount' => $order->getTotalPrice(),
      'payment_gateway' => $payment_gateway,
      'order_id' => $order->id(),
      'remote_id' => $access_token,
      'remote_state' => Client::PAYMENT_STATUS_SUCCESS,
    ]);

    $plugin_payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, Price $amount = NULL) {

    try {
      $this->client->setGatewayJwt($this->configuration['gateway_jwt']);
      $this->client->setGatewayUsername($this->configuration['gateway_username']);
      $this->client->setEnvironment($this->configuration['mode']);
      $this->client->setMinorUnitsConverter($this->minorUnitsConverter);
      $this->client->refund($this->configuration['order_prefix'], $payment, $amount);

      $this->writeRefundedPayment($payment, $amount);

    }
    catch (ClientException $exception) {
      $this->errorMessageHandler->handleException($exception, $payment->getOrder());
    }
  }

  /**
   * Writes a payment after refund.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   When saving payment fails.
   */
  public function writeRefundedPayment(PaymentInterface $payment, ?Price $partial_refunded_amount = NULL): void {

    $old_refunded_amount = $payment->getRefundedAmount();
    $total_payment_amount = $payment->getAmount();
    if (NULL === $old_refunded_amount || NULL === $total_payment_amount) {
      throw new \UnexpectedValueException('Invalid payment object given for refund');
    }

    $new_refunded_amount = $total_payment_amount;
    if (NULL !== $partial_refunded_amount) {
      $new_refunded_amount = $old_refunded_amount->add($partial_refunded_amount);
    }

    if ($new_refunded_amount->lessThan($total_payment_amount)) {
      $payment->setState('partially_refunded');
    }
    else {
      $payment->setState('refunded');
    }

    $payment->setRefundedAmount($new_refunded_amount);
    $payment->save();
  }

}
