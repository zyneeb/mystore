<?php

namespace Drupal\stripe_examples\EventSubscriber;

use Drupal\stripe\Event\StripeEvents;
use Drupal\stripe\Event\StripePaymentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class EntityTypeSubscriber.
 *
 * @package Drupal\stripe_examples\EventSubscriber
 */
class StripeExamplesEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The event names to listen for, and the methods that should be executed.
   */
  public static function getSubscribedEvents() {
    return [
      StripeEvents::PAYMENT => 'updatePayment',
    ];
  }

  /**
   * React to a config object being saved.
   *
   * @param \Drupal\stripe\Event\StripePaymentEvent $event
   *   Stripe payment event.
   */
  public function updatePayment(StripePaymentEvent $event) {
    $form = $event->getForm();
    if ($form['#form_id'] == 'stripe_examples_simple_checkout') {
      $event->setTotal(2500, 'StripeExamplesEventSubscriber');
    }
  }
}
