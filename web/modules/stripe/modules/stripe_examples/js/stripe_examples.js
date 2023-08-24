/**
 * @file
 * Provides stripe attachment logic.
 */

(function ($, document) {

  'use strict';

  // Doing it at the document level

  $(document).on('stripe:element:create', function(event, data) {
    console.log(event.target);
    console.log('Stripe element ' + data.type + ' is about to be created:', data);
  });

  $(document).on('stripe:element:created', function(event, data) {
    console.log(event.target);
    console.log('Stripe element ' + data.type + ' has been created:', data);
  });

  $(document).on('stripe:error', function(event, str) {
    console.log(event.target);
    console.log('Stripe error:', str);
  });

})(jQuery, document);
