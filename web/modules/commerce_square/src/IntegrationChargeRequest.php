<?php

namespace Drupal\commerce_square;

use Square\Models\ChargeRequest;

/**
 * Adds integration_id support to the ChargeRequest API object.
 *
 * @see https://github.com/square/connect-php-sdk/issues/94
 */
final class IntegrationChargeRequest extends ChargeRequest {

  /**
   * The swagger types.
   *
   * @var string[]
   */
  public static $swaggerTypes = [
    'idempotency_key' => 'string',
    'amount_money' => '\Square\Models\Money',
    'card_nonce' => 'string',
    'customer_card_id' => 'string',
    'delay_capture' => 'bool',
    'reference_id' => 'string',
    'note' => 'string',
    'customer_id' => 'string',
    'billing_address' => '\Square\Models\Address',
    'shipping_address' => '\Square\Models\Address',
    'buyer_email_address' => 'string',
    'order_id' => 'string',
    'additional_recipients' => '\Square\Models\AdditionalRecipient[]',
    'verification_token' => 'string',
    // Override addition.
    'integration_id' => 'string',
  ];

  /**
   * The attributes map.
   *
   * @var string[]
   */
  public static $attributeMap = [
    'idempotency_key' => 'idempotency_key',
    'amount_money' => 'amount_money',
    'card_nonce' => 'card_nonce',
    'customer_card_id' => 'customer_card_id',
    'delay_capture' => 'delay_capture',
    'reference_id' => 'reference_id',
    'note' => 'note',
    'customer_id' => 'customer_id',
    'billing_address' => 'billing_address',
    'shipping_address' => 'shipping_address',
    'buyer_email_address' => 'buyer_email_address',
    'order_id' => 'order_id',
    'additional_recipients' => 'additional_recipients',
    'verification_token' => 'verification_token',
    // Override addition.
    'integration_id' => 'integration_id',
  ];

  /**
   * An array of setters.
   *
   * @var string[]
   */
  public static $setters = [
    'idempotency_key' => 'setIdempotencyKey',
    'amount_money' => 'setAmountMoney',
    'card_nonce' => 'setCardNonce',
    'customer_card_id' => 'setCustomerCardId',
    'delay_capture' => 'setDelayCapture',
    'reference_id' => 'setReferenceId',
    'note' => 'setNote',
    'customer_id' => 'setCustomerId',
    'billing_address' => 'setBillingAddress',
    'shipping_address' => 'setShippingAddress',
    'buyer_email_address' => 'setBuyerEmailAddress',
    'order_id' => 'setOrderId',
    'additional_recipients' => 'setAdditionalRecipients',
    'verification_token' => 'setVerificationToken',
    // Override addition.
    'integration_id' => 'setIntegrationId',
  ];

  /**
   * An array of getters.
   *
   * @var string[]
   */
  public static $getters = [
    'idempotency_key' => 'getIdempotencyKey',
    'amount_money' => 'getAmountMoney',
    'card_nonce' => 'getCardNonce',
    'customer_card_id' => 'getCustomerCardId',
    'delay_capture' => 'getDelayCapture',
    'reference_id' => 'getReferenceId',
    'note' => 'getNote',
    'customer_id' => 'getCustomerId',
    'billing_address' => 'getBillingAddress',
    'shipping_address' => 'getShippingAddress',
    'buyer_email_address' => 'getBuyerEmailAddress',
    'order_id' => 'getOrderId',
    'additional_recipients' => 'getAdditionalRecipients',
    'verification_token' => 'getVerificationToken',
    // Override addition.
    'integration_id' => 'getIntegrationId',
  ];

  /**
   * The integration ID.
   *
   * @var string
   */
  protected $integrationId;

  /**
   * Set the integration ID.
   *
   * @param string $integration_id
   *   The integration ID.
   */
  public function setIntegrationId($integration_id) {
    $this->integrationId = $integration_id;
  }

  /**
   * Get the integration ID.
   *
   * @return string
   *   The integration ID.
   */
  public function getIntegrationId() {
    return $this->integrationId;
  }

}
