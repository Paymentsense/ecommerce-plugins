<?php

namespace Drupal\commerce_paymentsense_remotepayments\ConnectE;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Handles error messages.
 */
class ErrorMessageHandler implements ErrorMessageHandlerInterface {

  use StringTranslationTrait;
  use MessengerTrait;

  /**
   * Handles exceptions thrown during the payment process or refund.
   *
   * Parameters are passed to message in strict order to be translated.
   *
   * @throws \Drupal\commerce_payment\Exception\PaymentGatewayException
   *   Thrown to halt the payment process.
   */
  public function handleException(ClientExceptionInterface $exception, OrderInterface $order): string {
    $decoded = json_decode($exception->getCurlResponse(), TRUE);
    $status_code = $decoded['statusCode'] ?? NULL;
    $gateway_message = $decoded['message'] ?? NULL;

    switch ($exception->getCode()) {
      case ClientException::CODE_PAYMENT_FAILED:
        $message = $this->t('Payment failed due to: @gatewayMessage', ['@gatewayMessage' => $gateway_message]);
        $this->messenger()->addError($message);
        break;

      case ClientException::CODE_CURL_NOT_ENABLED:
        $message = $this->t('Curl not enabled. Please enable curl.');
        $this->messenger()->addError($message);
        break;

      case ClientException::CODE_COULD_NOT_READ_CROSS_REFERENCE:
        $message = $this->t('An unexpected error has occurred. Please contact customer support.');
        $this->messenger()->addError($message);
        break;

      case ClientException::CODE_GATEWAY_NOT_CONFIGURED:
        $message = $this->t('The Paymentsense Remote Payments payment method is not configured.');
        $this->messenger()->addError($message);
        break;

      case ClientException::CODE_ACCESS_TOKEN_EMPTY:
        $message = $this->t('Access token is empty. Please contact customer support.');
        $this->messenger()->addError($message);
        break;

      case ClientException::CODE_SSL_NOT_ENABLED:
        $message = $this->t('Please enable SSL/TLS.');
        $this->messenger()->addError($message);
        break;

      case ClientException::CODE_REFUND_FAILED:

        $message = $this->t(
          'Refund was declined. (Status Code: @statusCode, Payment Gateway Message: @gatewayMessage).',
          [
            '@statusCode' => $status_code,
            '@gatewayMessage' => $gateway_message,
          ]
        );
        $this->messenger()->addError($message);
        break;

      case ClientException::CODE_AMOUNT_COULD_NOT_BE_RESOLVED:
        $message = $this->t('Amount could not be resolved.');
        break;

      case ClientException::CODE_REFUND_DUPLICATED:
        $message = $this->t('Refund cannot be performed at this time, please try again after 60 seconds.');
        $this->messenger()->addError($message);
        break;

      case ClientException::CODE_UNKNOWN_STATUS_CODE:
        $message = $this->t('Payment status is unknown because of a communication error or unknown/unsupported payment status.');
        $this->messenger()->addError($this->t(
          'Payment status is unknown because of a communication error or unknown/unsupported payment status. Status Code @statusCode; Message: @gatewayMessage',
          [
            '@statusCode' => $status_code,
            '@gatewayMessage' => $gateway_message,
          ]
        ));
        break;

      case ClientException::CODE_CURL_REQUEST_FAILED:
      case ClientException::CODE_RESPONSE_EMPTY:
        $message = $this->t(
          'An error has occurred. (cURL Error No: @curlErrorNumber, cURL Error Message: @curlErrorMessage, HTTP Code: @httpCode).',
          [
            '@curlErrorNumber' => $exception->getCurlErrorNumber(),
            '@curlErrorMessage' => $exception->getCurlErrorMessage(),
            '@httpCode' => $exception->getCurlInfo()['http_code'] ?? '',
          ]
        );

        $this->messenger()->addError($this->t(
          'Payment status is unknown. Please contact customer support quoting your order @orderNumber and do not retry the payment for this order unless you are instructed to do so.',
          [
            '@orderNumber' => $order->id(),
          ]
        ));
        break;

      default:
        $message = $this->t('An error occurred @message.', ['@message' => $exception->getMessage()]);
        break;
    }

    throw new PaymentGatewayException($message);
  }

}
