<?php

declare(strict_types=1);

namespace Drupal\leap_admin;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\node\NodeInterface;
use Drupal\workflows\Entity\Workflow;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Service to handle bulk moderation state transitions.
 *
 * This service provides a robust mechanism to transition nodes through
 * Content Moderation workflows while ensuring all translations are
 * kept in sync and revision logs are correctly maintained.
 */
final class BulkModerationService {

  /**
   * The module logger channel.
   */
  private readonly LoggerInterface $logger;

  /**
   * The workflows to validate against.
   *
   * @var string[]
   */
  private array $workflows = ['editorial'];

  /**
   * Constructs a new BulkModerationService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user proxy.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The datetime service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LanguageManagerInterface $languageManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly TimeInterface $time,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->logger = $logger_factory->get('leap_admin');
  }

  /**
   * Transitions a node to a new moderation state across all translations.
   *
   * This method identifies the latest revision for every enabled language,
   * updates the moderation state, creates a new revision with an automated
   * log message, and persists the changes.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to transition.
   * @param string $state
   *   The target moderation state (e.g., 'published', 'archived').
   *
   * @return \Drupal\node\NodeInterface|null
   *   The updated node entity, or NULL if the transition failed.
   */
  public function transitionNode(NodeInterface $node, string $state): ?NodeInterface {
    if (empty($state)) {
      $this->logger->notice('No moderation state provided.');
      return NULL;
    }

    if (!$this->validateWorkflowState($state)) {
      $this->logger->notice("The moderation state '{$state}' does not exist in the configured workflows.");
      return NULL;
    }

    $all_languages = $this->getAllEnabledLanguages();
    $updated_node = NULL;

    foreach ($all_languages as $langcode) {
      if ($node->hasTranslation($langcode)) {
        $this->logger->notice("Transitioning latest revision ($langcode) for node {$node->id()} to {$state}.");

        $latest_revision = $this->getLatestRevision($node, $langcode);
        $entity_to_update = $latest_revision ?: $node;

        // Set the Moderation State.
        $entity_to_update->set('moderation_state', $state);

        // Update the Revision Log metadata.
        if ($entity_to_update instanceof RevisionLogInterface) {
          $entity_to_update->setRevisionCreationTime($this->time->getRequestTime());
          $entity_to_update->setRevisionLogMessage("Bulk operation transitioned state to {$state}.");
          $entity_to_update->setRevisionUserId($this->currentUser->id());
        }

        // Standard flags to ensure translations and sync-state are respected.
        $entity_to_update->setSyncing(TRUE);
        $entity_to_update->setRevisionTranslationAffected(TRUE);
        $entity_to_update->save();

        // Return the final state of the entity.
        $updated_node = $entity_to_update;
      }
    }

    return $updated_node;
  }

  /**
   * Validates if a state exists in the monitored workflows.
   *
   * @param string $state_to_check
   *   The machine name of the state.
   *
   * @return bool
   *   TRUE if the state is valid within any configured workflow.
   */
  private function validateWorkflowState(string $state_to_check): bool {
    foreach ($this->workflows as $wid) {
      /** @var \Drupal\workflows\WorkflowInterface $workflow */
      $workflow = Workflow::load($wid);
      if ($workflow) {
        $workflow_plugin = $workflow->getTypePlugin();
        $states = $workflow_plugin->getStates();
        if (isset($states[$state_to_check])) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Retrieves the latest revision of a node for a specific language.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The base node.
   * @param string $langcode
   *   The target language code.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The latest revision entity, translated if necessary.
   */
  private function getLatestRevision(NodeInterface $node, string $langcode): ?NodeInterface {
    $node_storage = $this->entityTypeManager->getStorage('node');

    $latest_revision_ids = $node_storage->getQuery()
      ->latestRevision()
      ->condition('nid', $node->id())
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($latest_revision_ids)) {
      $revision_id = key($latest_revision_ids);
      /** @var \Drupal\node\NodeInterface $latest_revision */
      $latest_revision = $node_storage->loadRevision($revision_id);

      // Ensure we are working with the correct translation of the latest revision.
      if ($latest_revision && $latest_revision->language()->getId() !== $langcode && $latest_revision->hasTranslation($langcode)) {
        return $latest_revision->getTranslation($langcode);
      }
      return $latest_revision;
    }

    return NULL;
  }

  /**
   * Helper function to get all enabled languages.
   *
   * Prioritizes the site's current language to the start of the array to
   * ensure the default context is processed first.
   *
   * @return string[]
   *   An array of langcodes.
   */
  private function getAllEnabledLanguages(): array {
    $languages = $this->languageManager->getLanguages();
    $current_language = $this->languageManager->getCurrentLanguage();

    $langcodes = [];
    $langcodes[] = $current_language->getId();

    foreach ($languages as $langcode => $language) {
      if ($langcode !== $current_language->getId()) {
        $langcodes[] = $langcode;
      }
    }

    return $langcodes;
  }

}
