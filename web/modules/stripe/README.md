# [Stripe](https://stripe.com/) Payment Gateway integration with Drupal.

## Description

This is a development module that provides the bare elememnts and requirements
needed to integrate stripe with Drupal 8. It has the necessary libraries
dependencies and assets to include.

Look inside for the stripe_examples module for a simple implementation of the
features exposed by this module.

The 2+ version of this module uses the latest [PaymentIntent API](https://stripe.com/docs/payments/payment-intents). This follows Stripe's best practices for handling different scenarios, including [SCA](https://stripe.com/docs/strong-customer-authentication) and [Payment Request Buttons](https://stripe.com/docs/stripe-js/elements/payment-request-button).

The new API recommends and for the sake of simplicity and compatibility with different payment methods to use this new API and let the Stripe.js library handle most of the authentications. 

**This means that the charge will succeed in the form on the borwser and not on form submission**.

This doesn't fit so nicely within the usual FAPI validate/submit unless you rely on things like hold/capture method which is only supported for cards.

The PaymentIntent API also needs the amount to be charged to the card to be known in advanced and sometimes (i.e. donation forms) thats not possible.

To workaround this, the form element adds a new AJAX submit that first makes sure that the form is validated properly (thus will submit sucessfully) before proceeding with the stripe payment confirmation. It also allows other modules to respond to an event and return useful information for this module to update its client-side states (like bililng details and updated amounts).



## Requirements

- Drupal 8.8+
- Webform 6+

### Installation
```
composer require drupal/stripe
```

### Testing
[Testing information >>>](https://stripe.com/docs/testing)

### Configuration

Log into your Stripe.com account and visit the "Account settings" area. Click
on the "API Keys" icon, and copy the values for Test Secret Key,
Test Publishable Key, Live Secret Key, and Live Publishable Key and paste them
into the module configuration under admin/config/stripe.
