<?php

namespace Drupal\commerce_shipping;

use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity\EntityPermissionProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides permissions for shipments.
 */
class ShipmentPermissionProvider implements EntityPermissionProviderInterface, EntityHandlerInterface {

  use StringTranslationTrait;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Constructs a new ShipmentPermissionProvider object.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   */
  public function __construct(EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildPermissions(EntityTypeInterface $entity_type) {
    $entity_type_id = $entity_type->id();
    $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
    $permissions = [];
    foreach ($bundles as $bundle_name => $bundle_info) {
      $permissions["manage {$bundle_name} {$entity_type_id}"] = [
        'title' => $this->t('[Shipments] Manage %bundle', [
          '%bundle' => $bundle_info['label'],
        ]),
        'provider' => 'commerce_shipping',
      ];
    }

    return $permissions;
  }

}
