<?php

namespace Drupal\stripe\Event;

use Stripe\Event as StripeEvent;

/**
 * Wraps a stripe event for webhook.
 */
class StripeWebhookEvent extends \Symfony\Contracts\EventDispatcher\Event {

  /**
   * Stripe API event object.
   *
   * @var \Stripe\Event
   */
  protected $event;

  /**
   * Constructs a Stripe Webhook Event object.
   *
   * @param \Stripe\Event $event
   *   Stripe API event object object.
   */
  public function __construct(StripeEvent $event) {
    $this->event = $event;
  }

  /**
   * Gets Stripe API event object.
   *
   * @return \Stripe\Event
   *   The Stripe API event object
   */
  public function getEvent() {
    return $this->event;
  }

}
