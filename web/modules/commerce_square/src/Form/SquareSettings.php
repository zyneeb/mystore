<?php

namespace Drupal\commerce_square\Form;

use Drupal\commerce_square\Connect;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Square\Environment;
use Square\Exceptions\ApiException;
use Square\Models\ObtainTokenRequest;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\commerce_square\ErrorHelper;
use Drupal\Core\State\State;

/**
 * Provides a configuration form for Square settings.
 */
class SquareSettings extends ConfigFormBase {

  /**
   * The state store.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * The Connect application.
   *
   * @var \Drupal\commerce_square\Connect
   */
  protected $connect;

  /**
   * The csrf token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;

  /**
   * Constructs a new SquareSettings object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\State\State $state
   *   The object State.
   * @param \Drupal\commerce_square\Connect $connect
   *   The Connect application.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token_generator
   *   The CSRF token generator.
   */
  public function __construct(ConfigFactoryInterface $config_factory, State $state, Connect $connect, CsrfTokenGenerator $csrf_token_generator) {
    parent::__construct($config_factory);
    $this->state = $state;
    $this->connect = $connect;
    $this->csrfToken = $csrf_token_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('commerce_square.connect'),
      $container->get('csrf_token')
    );
  }

  /**
   * Scope of the permissions for the request.
   *
   * @var array
   */
  protected $permissionScope = [
    'MERCHANT_PROFILE_READ',
    'PAYMENTS_READ',
    'PAYMENTS_WRITE',
    'CUSTOMERS_READ',
    'CUSTOMERS_WRITE',
    'ORDERS_WRITE',
  ];

  /**
   * Returns the Authorize URL for requesting an OAuth authorization code.
   */
  public function getAuthorizeUrl() {
    return 'https://squareup.com/oauth2/authorize';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['commerce_square.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_square_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('commerce_square.settings');

    // When merchants return from the Square OAuth authorize page with a valid
    // authorization code, they are redirected to this settings form. If this
    // form builder sees a code in the query parameters, it attempts to obtain
    // the OAuth access token and refresh token, storing both in the site's
    // state via key / value pairs, and returning a redirect response that
    // reloads the settings form route without query parameters.
    $code = $this->getRequest()->query->get('code');
    if (!empty($code)) {
      return $this->requestAccessToken($code);
    }

    if (!$form_state->isProcessingInput()) {
      $this->messenger()->addWarning($this->t('After clicking save you will be redirected to Square to sign in and connect your Drupal Commerce store.'));
    }

    $form['oauth'] = [
      '#type' => 'fieldset',
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      '#title' => $this->t('OAuth'),
    ];
    $form['oauth']['instructions'] = [
      '#markup' => $this->t('<p>Configure the OAuth information for your Square Connect application. You can get this by selecting your app <a href="https://connect.squareup.com/apps">here</a> and clicking on the OAuth tab.</p>'),
    ];
    $form['oauth']['app_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application Secret'),
      '#default_value' => $config->get('app_secret'),
      '#description' => $this->t('<p>The Application Secret identifies your application to Square for OAuth authentication.</p>'),
      '#required' => TRUE,
    ];
    $form['oauth']['redirect_url'] = [
      '#type' => 'item',
      '#title' => $this->t('Redirect URL'),
      '#markup' => Url::fromRoute('commerce_square.oauth.obtain', [], ['absolute' => TRUE])->toString(),
      '#description' => $this->t('Copy this URL and use it for the redirect URL field in your app OAuth settings.'),
    ];

    $form['credentials'] = [
      '#type' => 'fieldset',
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      '#title' => $this->t('Credentials'),
    ];
    $form['credentials']['introduction'] = [
      '#markup' => $this->t('Provide the production application ID for your application.'),
    ];
    $form['credentials']['app_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application Name'),
      '#default_value' => $config->get('app_name'),
      '#required' => TRUE,
    ];
    $form['credentials']['production_app_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application ID'),
      '#default_value' => $config->get('production_app_id'),
      '#required' => TRUE,
    ];

    $form['sandbox'] = [
      '#type' => 'fieldset',
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      '#title' => $this->t('Sandbox'),
    ];
    $form['sandbox']['instructions'] = [
      '#markup' => $this->t('Enter in your application sandbox environment information.'),
    ];
    $form['sandbox']['sandbox_app_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sandbox Application ID'),
      '#default_value' => $config->get('sandbox_app_id'),
      '#required' => TRUE,
      '#description' => $this->t('<p>The Application Secret identifies your application to Square for OAuth authentication.</p>'),
    ];
    $form['sandbox']['sandbox_access_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sandbox Access Token'),
      '#default_value' => $config->get('sandbox_access_token'),
      '#description' => $this->t('<p>This is one of your sandbox test account authorizations.</p>'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('commerce_square.settings');
    $config
      ->set('app_name', $form_state->getValue('app_name'))
      ->set('app_secret', $form_state->getValue('app_secret'))
      ->set('sandbox_app_id', $form_state->getValue('sandbox_app_id'))
      ->set('sandbox_access_token', $form_state->getValue('sandbox_access_token'))
      ->set('production_app_id', $form_state->getValue('production_app_id'));
    $config->save();

    $options = [
      'query' => [
        'client_id' => $config->get('production_app_id'),
        'state' => $this->csrfToken->get(),
        'scope' => implode(' ', $this->permissionScope),
      ],
    ];
    $url = Url::fromUri($this->getAuthorizeUrl(), $options);
    $form_state->setResponse(new TrustedRedirectResponse($url->toString()));
    parent::submitForm($form, $form_state);
  }

  /**
   * Requests an OAuth access token from Square using the authorization code.
   *
   * @param string $code
   *   An authorization code for the grants required by this integration.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response that will reload the settings form.
   */
  public function requestAccessToken($code) {
    $config = $this->config('commerce_square.settings');

    try {
      $oauth_api = $this->connect->getClient(Environment::PRODUCTION)->getOAuthApi();

      // Prepare and submit an obtain token request.
      $request = new ObtainTokenRequest(
        $config->get('production_app_id'),
        $config->get('app_secret'),
        'authorization_code'
      );
      $request->setCode($code);
      $response = $oauth_api->obtainToken($request);

      // If the request succeeded, store the result in the site state. The
      // code is only good for 5 minutes, but absent a failure to redirect
      // properly, there shouldn't be any reason for it to fail.
      if ($response->isSuccess()) {
        $result = $response->getResult();
        $this->state->setMultiple([
          'commerce_square.production_access_token' => $result->getAccessToken(),
          'commerce_square.production_access_token_expiry' => strtotime($result->getExpiresAt()),
          'commerce_square.production_refresh_token' => $result->getRefreshToken(),
        ]);
        $this->messenger()->addStatus($this->t('Your Drupal Commerce store and Square have been successfully connected.'));
      }
      else {
        // Otherwise, throw an exception that is caught and displayed as an
        // error message on the page when the form builds.
        throw ErrorHelper::convertException(
          new ApiException(
            $response->getBody(),
            $response->getRequest()
          )
        );
      }
    }
    catch (ApiException $e) {
      $this->messenger()->addError($e->getResponseBody()->message);
    }

    // Interrupt the form building process at this point, reloading the page
    // with the temporary OAuth code removed from the URL.
    return new RedirectResponse(Url::fromRoute('commerce_square.settings')->toString());
  }

}
