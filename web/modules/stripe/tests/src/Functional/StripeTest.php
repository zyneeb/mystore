<?php

namespace Drupal\Tests\stripe\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Test module installation.
 *
 * @group stripe
 */
class StripeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['stripe'];

  /**
   * Test callback.
   */
  public function testSettings(): void {
    $admin_user = $this->drupalCreateUser([
      'access administration pages',
      'administer stripe',
    ]);
    $this->drupalLogin($admin_user);
    $this->drupalGet(Url::fromRoute('stripe.settings'));
    $this->assertSession()->pageTextContains('Bear in mind that this configuration will be exported in plain text and likely kept under version control.');
  }

}
