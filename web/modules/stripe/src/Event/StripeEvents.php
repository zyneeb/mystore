<?php

namespace Drupal\stripe\Event;

/**
 * Defines events for stripe webhooks.
 * */
final class StripeEvents {

  /**
   * The name of the event fired when a webhook is received.
   *
   * @Event
   */
  const WEBHOOK = 'stripe.webhook';

  /**
   * The name of the event fired when allowing modules to change the client side
   * properties of the stripe workflow, like updating prices or massaging post
   * data to fill biling address.
   *
   * @Event
   */
  const PAYMENT = 'stripe.payment';

}
