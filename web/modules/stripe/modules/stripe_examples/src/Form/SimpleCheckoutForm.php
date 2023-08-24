<?php

namespace Drupal\stripe_examples\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Stripe\StripeClient;

/**
 * Class SimpleCheckout.
 *
 * @package Drupal\stripe_examples\Form
 */
class SimpleCheckoutForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stripe_examples_simple_checkout';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $link_generator = \Drupal::service('link_generator');

    $form['#theme'] = 'stripe-examples-simple-checkout';

    $form['button'] = [
      '#type' => 'stripe_paymentrequest',
      '#title' => $this->t('Pay with a button'),
      '#description' => $this->t('You can use test card numbers and tokens from @link.', [
        '@link' => $link_generator->generate('stripe docs', Url::fromUri('https://stripe.com/docs/testing')),
      ]),
      '#stripe_paymentintent_unique' => TRUE,
    ];

    $form['first'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First name'),
      '#description' => $this->t('Anything other than "John" would give a validation error to test different scenarios.')
    ];
    $form['last'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last name'),
    ];

    $form['card'] = [
      '#type' => 'stripe',
      '#title' => $this->t('Credit card'),
      '#description' => $this->t('You can use test card numbers and tokens from @link.', [
        '@link' => $link_generator->generate('stripe docs', Url::fromUri('https://stripe.com/docs/testing')),
      ]),
      '#stripe_paymentintent_unique' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    $form['submit']['#value'] = $this->t('Pay $25');

    $form['#attached']['library'][] = 'stripe_examples/stripe_examples';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('first') != 'John') {
      $form_state->setError($form['first'], $this->t('"John" is the only valid first name on this example.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Display result.
    foreach ($form_state->getValues() as $key => $value) {
      if ($key == 'card') {
        $this->messenger()->addStatus('card/payment_intent: ' . $value['payment_intent'] ?? '');
        continue;
      }
      if ($key == 'button') {
        $this->messenger()->addStatus('button/payment_intent: ' . $value['payment_intent'] ?? '');
        continue;
      }
      $this->messenger()->addStatus($key . ': ' . $value);
    }

    $config = \Drupal::config('stripe.settings');
    $apikeySecret = $config->get('apikey.' . $config->get('environment') . '.secret');
    // Quick test of subscription creation
    $stripe = new StripeClient($apikeySecret);

    // $customer = $stripe->customer->create([
    //   'customer' => 'cus_J4sMTZH5VcpNxu',
    //   'items' => [
    //     ['price' => 'price_1ISibg2Jih6Bdv92IyZkqVu7'],
    //   ],
    // ]);

    // $stripe->subscriptions->create([
    //   'customer' => 'cus_J4sMTZH5VcpNxu',
    //   'items' => [
    //     ['price' => 'price_1ISibg2Jih6Bdv92IyZkqVu7'],
    //   ],
    // ]);

  }
}
