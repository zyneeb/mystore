<?php

namespace Drupal\stripe\Event;

use Drupal\Core\Form\FormStateInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Wraps a stripe event for webhook.
 */
class StripePaymentEvent extends Event {

  /**
   * The form.
   *
   * @var array
   */
  private $form;

  /**
   * The form state.
   *
   * @var \Drupal\Core\Form\FormStateInterface
   */
  private $formState;

  /**
   * Stripe element name
   *
   * @var array
   */
  private $element;

  /**
   * The description
   *
   * @var string
   */
  private $description = '';

  /**
   * Total label and amounts
   *
   * @var array
   */
  private $total = [];

  /**
   * Total label and amounts
   *
   * @var array
   */
  private $billing = [];

  /**
   * Metadata key-value array
   *
   * @var array
   */
  private $metadata = [];

  /**
   * Constructor.
   *
   * @param array $form
   *   Nested array of form elements that comprise the form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form. The arguments that
   *   \Drupal::formBuilder()->getForm() was originally called with are
   *   available in the array $form_state->getBuildInfo()['args'].
   */
  public function __construct(array &$form, FormStateInterface $formState, $element) {
    $this->form = &$form;
    $this->formState = $formState;
    $this->element = $element;
  }

  /**
   * Get the form.
   *
   * @return array
   *   The form.
   */
  public function &getForm(): array {
    return $this->form;
  }

  /**
   * Get the form state.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   The form state.
   */
  public function getFormState(): FormStateInterface {
    return $this->formState;
  }

  public function getFormElement(): string {
    return $this->element;
  }

  /**
   * Get the form id.
   *
   * @return string
   *   The form id.
   */
  public function getFormId(): string {
    return $this->formId;
  }

  public function setTotal(int $amount, string $label) {
    $this->total['amount'] = $amount;
    $this->total['label'] = $label;
  }

  public function setBillingCity(string $city) {
    $this->billing['address']['city'] = $city;
  }

  public function setBillingCountry(string $country) {
    $this->billing['address']['country'] = $country;
  }

  public function setBillingAddress1(string $address1) {
    $this->billing['address']['line1'] = $address1;
  }

  public function setBillingAddress2(string $address2) {
    $this->billing['address']['line2'] = $address2;
  }

  public function setBillingPostalCode(string $postal_code) {
    $this->billing['address']['postal_code'] = $postal_code;
  }

  public function setBillingState(string $state) {
    $this->billing['address']['state'] = $state;
  }

  public function setBillingEmail(string $email) {
    $this->billing['email'] = $email;
  }

  public function setBillingName(string $name) {
    $this->billing['name'] = $name;
  }

  public function setBillingPhone(string $phone) {
    $this->billing['phone'] = $phone;
  }

  public function setDescription(string $description) {
    $this->description = $description;
  }

  public function setMetadata(string $key, $value) {
    $this->metadata[$key] = $value;
  }

  public function getMetadata(): array {
    return $this->metadata;
  }

  public function getBillingDetails(): array {
    return $this->billing;
  }

  public function getDescription(): string {
    return $this->description;
  }

  public function getTotal(): array {
    return $this->total;
  }
}
