<?php

namespace Drupal\stripe\Element;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Url;
use Drupal\stripe\Event\StripeEvents;
use Drupal\stripe\Event\StripePaymentEvent;
use Stripe\StripeClient;

/**
 * Provides the base for our Stripe elements
 */
abstract class StripeBase extends FormElement {

  protected static $type = '';

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    $info = [
      '#process' => [
        [$class, 'processStripe'],
      ],
      '#theme_wrappers' => [
        'form_element' => [],
      ],
      '#stripe_submit_selector' => [],
      '#stripe_amount' => 100,
      '#stripe_label' => 'Total',
      '#stripe_currency' => 'usd',
      '#stripe_country' => 'US',
      '#stripe_paymentintent' => [],
      '#stripe_shared' => TRUE,
    ];

    return $info;
  }

  /**
   * Flatten the sub-elements.
   *
   * This is so that the "#tree" can be used but this elements is presented
   * as a regular 'composite element' to FAPI.
   *
   * @see https://www.drupal.org/project/drupal/issues/784874#comment-11420347
   */
  public static function processActions(&$element, FormStateInterface $form_state, &$complete_form) {
    array_pop($element['#parents']);
    array_pop($element['#parents']);

    return $element;
  }

  /**
   * Processes a stripe element.
   */
  public static function processStripe(&$element, FormStateInterface $form_state, &$complete_form) {
    if (!$form_state->isProcessingInput() && !$form_state->isExecuted() && $form_state->get('drupal_stripe_' . static::$type)) {
      $error = t('Only one stripe element of type @type is allowed per form.', ['@type' => static::$type]);
      // \Drupal::messenger()->addError($error);
      $form_state->setError($element, $error);
      $element['error'] = [
        '#markup' => "<div class=\"messages messages--error\">$error</div>",
        '#attributes' => [
          'class' => [
            'messages',
            'messages-error'
          ]
        ],
      ];
      return $element;
    }
    $form_state->set('drupal_stripe_' . static::$type, TRUE);

    $config = \Drupal::config('stripe.settings');
    $apikey = $config->get('apikey.' . $config->get('environment') . '.public');
    $apikeySecret = $config->get('apikey.' . $config->get('environment') . '.secret');

    if (empty($apikey) || empty($apikeySecret)) {
      $settings_uri = Url::fromRoute('stripe.settings')->toString();
      $error = t('You must <a href="@settings_uri">configure</a> the <b>API Keys</b> on <b>%environment</b> for the stripe element to work.', ['%environment' => $config->get('environment'), '@settings_uri' => $settings_uri]);
      \Drupal::messenger()->addError($error);
      $element['error'] = [
        '#markup' => $error,
      ];
      return $element;
    }

    $stripe = new StripeClient($apikeySecret);

    $id = $element['#id'];
    $wrapper_id = 'stripe-' . implode('-', $element['#parents']) . '-wrapper';
    $button_name = 'stripe-' . implode('-', $element['#parents']) . '-button';

    $element['#tree'] = TRUE;


    // $element['card'] = [
    //   '#markup' => "<div id=\"$id-stripe-card\"></div>",
    // ];

    // $element['paymentrequest'] = [
    //   '#markup' => "<div id=\"$id-stripe-paymentrequest\"></div>",
    //   '#access' => $element['#stripe_paymentrequest'],
    // ];

    $element['stripe'] = [
      '#type' => 'container',
      '#id' => $id,
    ];

    $element['stripe']['element'] = [
      '#markup' => "<div class=\"drupal-stripe-element\"></div>",
    ];

    $element['stripe']['errors'] = [
      '#markup' => "<div class=\"drupal-stripe-errors\" role=\"alert\"></div>",
    ];
    $element['stripe']['actions'] = [
      '#process' => [
        [static::class, 'processActions'],
      ],
    ];
    $element['stripe']['actions']['update'] = [
      '#type' => 'submit',
      '#value' => t('Update'),
      // We specifically don't us a validate ajax callback as we want to act
      // only when the whole form validation succeddes, not otherwise
      // '#validate' => [[get_called_class(), 'validateStripeElementCallback']],
      '#submit' => [[get_called_class(), 'submitStripeElementCallback']],
      '#ajax' => [
        'callback' => [get_called_class(), 'ajaxStripeElementCallback'],
        'progress' => ['type' => 'none'],
      ],
      // Disable validation, hide button, add submit button trigger class.
      '#attributes' => [
        'formnovalidate' => 'formnovalidate',
        'class' => [
          'js-hide',
          'drupal-stripe-update',
        ],
      ],
      // Issue #1342066 Document that buttons with the same #value need a unique
      // #name for the Form API to distinguish them, or change the Form API to
      // assign unique #names automatically.
      '#name' => $button_name,
    ];

    $element['stripe']['actions']['#attached']['library'][] = 'stripe/stripe';
    $element['stripe']['actions']['#attached']['drupalSettings']['stripe']['apiKey'] = $apikey;

    $settings = [];

    $payment_intent_id = $element['#value']['payment_intent'] ?? NULL;

    // If the element is configured to share the same payment intent for all
    // stripe elements in the form, attempt to find it.
    if (!$payment_intent_id && $element['#stripe_shared']) {
      $payment_intent_id = $form_state->get('stripe_paymentintent');
    }

    if (!$payment_intent_id) {
      $payment_intent_create = $element['#stripe_paymentintent'] ?? [];
      $payment_intent_create += [
        'amount' => is_numeric($element['#stripe_amount']) ? $element['#stripe_amount'] : 100,
        'currency' => $element['#stripe_currency'],
      ];
      $payment_intent = $stripe->paymentIntents->create($payment_intent_create);
    }
    else {
      $payment_intent = $stripe->paymentIntents->retrieve($payment_intent_id);
    }

    // If the element is configured to share the same payment intent for all
    // stripe elements in the form, store it
    if ($element['#stripe_shared']) {
      $form_state->set('stripe_paymentintent', $payment_intent->id);
    }

    $element['stripe']['actions']['payment_intent'] = [
      '#type' => 'hidden',
      '#value' => $payment_intent->id,
      '#attributes' => [
        'id' => "$id-stripe-payment-intent",
        'data-payment-intent-status' => $payment_intent->status,
        'class' => [
          'drupal-stripe-payment-intent',
        ],
      ],
    ];
    $element['stripe']['actions']['client_secret'] = [
      '#type' => 'hidden',
      '#value' => $payment_intent->client_secret,
      '#attributes' => [
        'class' => [
          'drupal-stripe-client-secret',
        ],
      ]
    ];
    $element['stripe']['actions']['trigger'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'class' => [
          'drupal-stripe-trigger',
        ],
      ]
    ];

    $settings['country'] = $element['#stripe_country'];
    $settings['currency'] = $element['#stripe_currency'];
    $settings['amount'] = $element['#stripe_amount'];
    $settings['label'] = $element['#stripe_label'];
    $settings['submit_selector'] = $element['#stripe_submit_selector'];
    $settings['type'] = static::$type;

    $element['stripe']['actions']['#attached']['drupalSettings']['stripe']['elements'][$element['#id']] = $settings;

    // Add validate callback.
    $element['stripe'] += ['#element_validate' => []];
    array_unshift($element['stripe']['#element_validate'], [get_called_class(), 'validateStripeElement']);

    if (!empty($element['#value']['trigger'])) {
      $element['stripe']['actions']['processed'] = [
        '#type' => 'value',
        '#value' => TRUE,
      ];
    }

    return $element;
  }

  /**
   * Validates an other element.
   */
  public static function validateStripeElement(&$element, FormStateInterface $form_state, &$form) {
  }

  /**
   * Webform computed element submit callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function submitStripeElementCallback(array $form, FormStateInterface $form_state) {
    // Do nothing, but it's important to prevent other submission handlers
    // from being run
  }

  /**
   * Webform computed element Ajax callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The computed element element.
   */
  public static function ajaxStripeElementCallback(array $form, FormStateInterface $form_state) {
    $config = \Drupal::config('stripe.settings');
    $apikeySecret = $config->get('apikey.' . $config->get('environment') . '.secret');

    // Stripe form element from trigger
    $trigger = $form_state->getTriggeringElement();
    $key = $trigger['#parents'][0];

    $payment_intent = $form_state->getValue([$key, 'payment_intent']);

    $response = new AjaxResponse();

    // Instantiate our event.
    $event = new StripePaymentEvent($form, $form_state, $key);

    // Dispatch our event to allow modules to update client-side elements of
    // the stripe elements
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $event_dispatcher->dispatch($event, StripeEvents::PAYMENT);

    $settings = [
      'trigger' => $form_state->getValue([$key, 'trigger']),
      'error' => $form_state->hasAnyErrors(),
    ];
    $params = [];
    $total = $event->getTotal();
    if (!empty($total)) {
      $settings['total'] = $total;
      $params['amount'] = $total['amount'];
    }
    $description = $event->getDescription();
    if (!empty($description)) {
      $params['description'] = $description;
    }
    $metadata = $event->getMetadata();
    if (!empty($metadata)) {
      $params['metadata'] = $metadata;
    }
    if (count($params) > 0 && $payment_intent) {
      $stripe = new \Stripe\StripeClient($apikeySecret);
      $stripe->paymentIntents->update($payment_intent, $params);
    }
    $billing_details = $event->getBillingDetails();
    if (!empty($billing_details)) {
      $settings['billing_details'] = $billing_details;
    }
    $response->addCommand(new InvokeCommand(NULL, 'stripeUpdatePaymentIntent', [$settings]));
    return $response;
  }

}
