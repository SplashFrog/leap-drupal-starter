<?php

declare(strict_types=1);

namespace Drupal\leap_admin\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Action\Plugin\Action\EntityActionBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\leap_admin\BulkModerationService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\NodeInterface;

/**
 * Custom action to publish nodes via moderation state.
 *
 * This action leverages the BulkModerationService to transition nodes into
 * the 'published' state while ensuring all translations are processed.
 */
#[Action(
  id: 'leap_node_publish_action',
  label: new TranslatableMarkup('Publish content'),
  type: 'node'
)]
final class NodePublishAction extends EntityActionBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a NodePublishAction object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\leap_admin\BulkModerationService $moderationService
   *   The LEAP bulk moderation service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    private readonly BulkModerationService $moderationService,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('leap_admin.bulk_moderation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?EntityInterface $entity = NULL): void {
    // Permission and Type guard.
    if (!$entity instanceof NodeInterface || !$entity->access('update')) {
      return;
    }

    $updated_entity = $this->moderationService->transitionNode($entity, 'published');

    // Verification: Ensure the entity is actually published.
    if (!$updated_entity || !$updated_entity->isPublished()) {
      $this->messenger()->addError($this->t('Something went wrong. The entity could not be published.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (!$object instanceof NodeInterface) {
      return $return_as_object ? AccessResult::forbidden() : FALSE;
    }

    $access = $object->access('update', $account, TRUE);
    return $return_as_object ? $access : $access->isAllowed();
  }

}
