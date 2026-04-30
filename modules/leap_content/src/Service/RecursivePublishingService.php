<?php

declare(strict_types=1);

namespace Drupal\leap_content\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;

/**
 * Manages recursive publishing of nested components like Layout Builder and Paragraphs.
 *
 * THE PROBLEM:
 * When an editor makes a Draft revision of a Node that contains Layout Builder
 * Inline Blocks or Paragraphs, those child entities also get new Draft revisions.
 * If the editor publishes the Node without opening the Edit form (e.g., via a View
 * bulk action or the Moderation state widget on the View tab), Drupal Core
 * publishes the Node, but FORGETS to publish the child Block/Paragraph revisions.
 * The public sees the new Node state, but the old block content.
 *
 * THE SOLUTION:
 * This service hooks into hook_entity_update, checks if the parent entity is
 * now the Default (Published) revision, and aggressively traverses its structure
 * to find any child entities that are still stuck in a Draft state, explicitly
 * promoting them to Default.
 */
final class RecursivePublishingService {

  /**
   * Constructs the RecursivePublishingService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Recursively publishes draft revisions of inline blocks and paragraphs
   * when their parent entity is published.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The parent entity that was just saved.
   */
  public function handleRecursivePublishing(EntityInterface $entity): void {
    // Only act on entities that support revisions and are currently being
    // saved as the "Default" (Live/Published) revision.
    if (!$entity instanceof RevisionableInterface || !$entity->isDefaultRevision()) {
      return;
    }

    // Static memory cache to prevent infinite loops. Since we call ->save()
    // on child entities, we must ensure we don't accidentally recurse back up.
    $static_cache = &drupal_static(__METHOD__, []);
    $entity_key = $entity->getEntityTypeId() . '_' . $entity->id() . '_' . $entity->getRevisionId();
    if (isset($static_cache[$entity_key])) {
      return;
    }
    $static_cache[$entity_key] = TRUE;

    // 1. Layout Builder Inline Blocks Synchronization.
    if ($entity->getEntityTypeId() === 'node' && $entity->hasField('layout_builder__layout') && !$entity->get('layout_builder__layout')->isEmpty()) {
      $sections = $entity->get('layout_builder__layout')->getSections();
      foreach ($sections as $section) {
        foreach ($section->getComponents() as $component) {
          $plugin_id = $component->getPluginId();
          // We only care about custom inline blocks, not globally placed ones.
          if (str_starts_with($plugin_id, 'inline_block:')) {
            $configuration = $component->get('configuration');
            if (!empty($configuration['block_revision_id'])) {
              $block = $this->entityTypeManager->getStorage('block_content')->loadRevision($configuration['block_revision_id']);

              // If the block is stuck in a non-default revision, promote and save it.
              if ($block instanceof RevisionableInterface && !$block->isDefaultRevision()) {
                $block->isDefaultRevision(TRUE);
                $block->save();
              }
            }
          }
        }
      }
    }

    // 2. Entity Reference Revisions (Paragraphs) Synchronization.
    // Dynamically discover any ERR fields on the parent entity.
    foreach ($entity->getFieldDefinitions() as $field_name => $field_definition) {
      if ($field_definition->getType() === 'entity_reference_revisions') {
        if ($entity->hasField($field_name) && !$entity->get($field_name)->isEmpty()) {
          foreach ($entity->get($field_name) as $item) {
            if ($item->entity && $item->entity->getEntityTypeId() === 'paragraph') {
              $paragraph = $item->entity;

              // If the paragraph is stuck in a non-default revision, promote and save it.
              if ($paragraph instanceof RevisionableInterface && !$paragraph->isDefaultRevision()) {
                $paragraph->isDefaultRevision(TRUE);
                $paragraph->save();
              }
            }
          }
        }
      }
    }
  }

}
