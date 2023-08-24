/**
 * @file
 * Provides stripe attachment logic.
 */

(function ($, window, Drupal, drupalSettings, Stripe, once) {

  'use strict';

  var stripe = null;

  Drupal.theme.stripeSucceeded = function (str) {
    return '<div class="stripe messages messages--status">' + Drupal.checkPlain(str) + '</div>';
  };

  // Argument passed from InvokeCommand.
  $.fn.stripeUpdatePaymentIntent = function(argument) {
    // Set textfield's
    // value to the passed arguments.
    var $element = $('[data-drupal-stripe-trigger="' + argument.trigger + '"]');
    var $form = $element.closest('form');
    if (argument.error) {
      $form.trigger('stripe:submit');
      return;
    }
    var elementData = $element.data('drupal-stripe-initialized');
    if (elementData) {
      var client_secret = $element.find('.drupal-stripe-client-secret').val();

      if (elementData.type == 'card') {
        stripe.confirmCardPayment(client_secret, {
          payment_method: {
            card: elementData.element,
            billing_details: argument.billing_details
          }
        })
        .then(function(result) {
          if (result.error) {
            // Show error to your customer
            $element.trigger('stripe:error', result.error.message);
          } else {
            // Send the token to your server
            $form.trigger('stripe:submit');
          }
        });
      }

      if (elementData.type == 'paymentrequest') {
        var paymentRequest = elementData.options.paymentRequest;
        if (argument.total) {
          paymentRequest.update({total: argument.total});
        }
        paymentRequest.show();
      }
    }
  };

  /**
   * Attaches the behavior for the card element
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   */
  Drupal.behaviors.stripe = {
    attach: function (context, settings) {

      // Create a Stripe client
      if (drupalSettings.stripe && drupalSettings.stripe.apiKey && !stripe) {
        stripe = Stripe(drupalSettings.stripe.apiKey);
      }

      // Stripe was not initialized, do nothing.
      if (!stripe) {
        return;
      }
      for (var base in settings.stripe.elements) {
        var $element = $('#' + base, context);
        if (!$element.length) {
          continue;
        }

        // Only process each element once
        if ($element.data('drupal-stripe-initialized')) {
          continue;
        }
        // Isolate each iteration of the for loop above for this one stripe
        // element
        (function ($element, elementSettings) {
          var $form = $element.closest('form');

          // Adding a stripe processing class using our custom events
          $form.on('stripe:submit:start', function(e) {
            $(this).addClass('stripe-processing');
          });

          $form.on('stripe:submit:stop', function(e) {
            $(this).removeClass('stripe-processing');
          });

          var client_secret = $element.find('.drupal-stripe-client-secret').val();
          var $payment_intent = $element.find('.drupal-stripe-payment-intent');

          if ($payment_intent.data('payment-intent-status') == 'succeeded') {
            $element.after(Drupal.theme('stripeSucceeded', 'You have already been charged, please submit the rest of the form.'));
            return;
          }

          // Create an instance of Elements
          var elements = stripe.elements();

          var stripeElementOptions = {};

          // Allow other modules to change these options
          $element.trigger('stripe:element:create', {
            type: elementSettings.type,
            stripe: stripe,
            elements: elements,
            options: stripeElementOptions
          });

          var stripeElement = {};

          if (elementSettings.type == 'card') {
            // Create an instance of the card Element
            stripeElement = elements.create('card', stripeElementOptions);

            // Add an instance of the card Element into the `card-element` <div>
            stripeElement.mount($element.find('.drupal-stripe-element')[0]);

            // Handle real-time validation errors from the card Element.
            stripeElement.on('change', function(event) {
              $element.trigger('stripe:error', event.error ? event.error.message : "");
            });
          }

          if (elementSettings.type == 'paymentrequest') {

            var paymentRequest = stripe.paymentRequest({
              country: elementSettings.country,
              currency: elementSettings.currency,
              total: {
                label: elementSettings.label,
                amount: elementSettings.amount,
              },
              requestPayerName: true,
              requestPayerEmail: true,
            });

            stripeElementOptions.paymentRequest = paymentRequest;

            // Create an instance of the PaymentRequest Element
            stripeElement = elements.create('paymentRequestButton', stripeElementOptions);

            // Check the availability of the Payment Request API first.
            paymentRequest.canMakePayment().then(function($element, result) {
              if (result) {
                var $form = $element.closest('form');
                stripeElement.mount($element.find('.drupal-stripe-element')[0]);

                stripeElement.on('click', function(event) {
                  event.preventDefault();
                  if (HTMLFormElement.prototype.reportValidity) {
                    if (!$form[0].reportValidity()) {
                      return false;
                    }
                  }
                  $form.trigger('stripe:submit:start');

                  var ajaxId = new Date().getTime();
                  $element.attr('data-drupal-stripe-trigger', ajaxId);
                  $element.find('.drupal-stripe-trigger').val(ajaxId);

                  var formValues = $form.find(':input').not('.drupal-stripe-trigger, input[name="form_build_id"]').serialize();
                  $form.attr('data-stripe-form-submit-last', formValues);

                  $element.find('.drupal-stripe-update').trigger('mousedown');
                });
              } else {
                $element.parent('.form-type-stripe-paymentrequest').hide();
              }
            }.bind(null, $element));

            paymentRequest.on('cancel', function() {
              $form.trigger('stripe:submit:stop');
            });

            paymentRequest.on('paymentmethod', function(ev) {
              // Confirm the PaymentIntent without handling potential next actions (yet).
              stripe.confirmCardPayment(
                client_secret,
                {payment_method: ev.paymentMethod.id},
                {handleActions: false}
              ).then(function(confirmResult) {
                if (confirmResult.error) {
                  // Report to the browser that the payment failed, prompting it to
                  // re-show the payment interface, or show an error message and close
                  // the payment interface.
                  $element.trigger('stripe:error', confirmResult.error.message);
                  ev.complete('fail');
                  $form.trigger('stripe:submit:stop');
                } else {
                  // Report to the browser that the confirmation was successful, prompting
                  // it to close the browser payment method collection interface.
                  ev.complete('success');
                  // Check if the PaymentIntent requires any actions and if so let Stripe.js
                  // handle the flow. If using an API version older than "2019-02-11" instead
                  // instead check for: `paymentIntent.status === "requires_source_action"`.
                  if (confirmResult.paymentIntent.status === "requires_action") {
                    // Let Stripe.js handle the rest of the payment flow.
                    stripe.confirmCardPayment(client_secret).then(function(result) {
                      if (result.error) {
                        $element.trigger('stripe:error', result.error.message);
                        // The payment failed -- ask your customer for a new payment method.
                      } else {
                        // The payment has succeeded.
                        $form.trigger('stripe:submit');
                      }
                    });
                  } else {
                    // The payment has succeeded.
                    $form.trigger('stripe:submit');
                  }
                }
              });
            });

          }

          // Allow other modules to act on the element created
          var eventData = {
            type: elementSettings.type,
            stripe: stripe,
            elements: elements,
            options: stripeElementOptions,
            element: stripeElement,
            settings: elementSettings
          };
          $element.trigger('stripe:element:created', eventData);

          $element.data('drupal-stripe-initialized', eventData);
          $form.data('drupal-stripe-element-' + elementSettings.type, $element);

          $element.bind('stripe:error', function(event, text) {
            var displayError = $element.find('.drupal-stripe-errors')[0];
            $form.removeAttr('data-stripe-form-submit-last');
            $form.trigger('stripe:submit:stop');
            displayError.textContent = text;
          })


          $(once('drupal-stripe-submit-click', $form)).find(':submit').click(function(event) {
            var $element = $(event.currentTarget);
            var $form = $element.closest('form');

            if (HTMLFormElement.prototype.reportValidity) {
              if (!$form[0].reportValidity()) {
                return true;
              }
            }

            if ($form.data('drupal-stripe-submit')) {
              $form.data('drupal-stripe-submit', false);
              // We were told to submit, at this point form should validate
              // and the card has been sucessfully charged.
              return true;
            }

            event.preventDefault();

            var formValues = $form.find(':input').not('.drupal-stripe-trigger, input[name="form_build_id"]').serialize();
            var previousValues = $form.attr('data-stripe-form-submit-last');

            // event.currentTarget.submit();
            // @TODO: Check if this is actually necessary.
            // Using the same approach as drupal own double submit prevention
            // @see core/drupal.form
            if (previousValues !== formValues) {
              $form.attr('data-stripe-form-submit-last', formValues);

              $form.trigger('stripe:submit:start');

              var $element = $form.data('drupal-stripe-element-card');
              var ajaxId = new Date().getTime();
              $element.attr('data-drupal-stripe-trigger', ajaxId);
              $element.find('.drupal-stripe-trigger').val(ajaxId);
              $element.find('.drupal-stripe-update').trigger('mousedown');

              // $form.trigger('submit', [true]);
            }
            else {
              console.log('Prevent double submit');
            }
          });

          $(once('drupal-stripe-submit', $form)).on('stripe:submit', function(event) {
            var $form = $(this);

            // Tell our click handler to allow normal submission
            $form.data('drupal-stripe-submit', true);

            // Attempt to find the proper submit button
            var $submit = $();
            // Look first in the element selector, if any
            if (elementSettings.submit_selector) {
              for (var i in elementSettings.submit_selector) {
                var selector = elementSettings.submit_selector[i];
                $submit = $form.find(selector);
                if ($submit.length) {
                  break;
                }
              }
            }
            // Otherwise look for a .js-stripe-submit class, which is another
            // mean of flagging the default submit button.
            if (!$submit.length) {
              $form.find('.js-stripe-submit');
            }
            // And fallback to the first submit button available in the form
            // making sure it's not the stripe's ajax update one.
            if (!$submit.length) {
              $submit = $form.find('.js-form-submit:not(.drupal-stripe-update)');
            }

            // There should always be one, but just in case make sure we click
            // the first one.
            $submit.first().trigger('click');
          });

        })($element, settings.stripe.elements[base]);
      }
    }
  };

})(jQuery, window, Drupal, drupalSettings, Stripe, once);
