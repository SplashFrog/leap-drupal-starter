<?php
namespace Drupal\leap_admin;

use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\workflows\Entity\Workflow;

/**
 * A Helper Class to assist with the changing states for bulk actions.
 */
class ContentModeration {

  /**
   * A Message string.
   *
   * @var string
   */
  public $message = '';

  /**
   * An array of workflows to check.
   *
   * @var string[]
   */
  protected $workflows = [
    'editorial',
  ];

  /**
   * A local version of the Entity to be changed.
   *
   * @var null
   */
  private $entity = null;

  /**
   * The Entity ID.
   *
   * @var int|string|null
   */
  private $id = 0;

  public function __construct($entity) {
    $this->entity = $entity;
    $this->id = $this->entity->id();
  }

  public function transitionTo($state = '') {
    if ($state == '') {
      $this->message = "No moderation state called.";
      \Drupal::logger('leap_admin')->notice($this->message);
      return FALSE;
    }

    if (!$this->validateWorkflowState($state)) {
      $this->message = "The moderation state '" . $state . "' does not exist.";
      \Drupal::logger('leap_admin')->notice($this->message);
      return FALSE;
    }

    $user = \Drupal::currentUser();
    $allLanguages = ContentModerationHelper::getAllEnabledLanguages();

    foreach ($allLanguages as $langcode => $languageName) {
      if ($this->entity->hasTranslation($langcode)) {
        \Drupal::logger('leap_admin')->notice(ucfirst($state) . " latest revision $langcode for " . $this->id);

        $latestRevision = self::_latest_revision($this->entity, $this->entity->id(), $vid, $langcode);
        if (!$latestRevision === FALSE) {
          $this->entity = $latestRevision;
        }

        // Set the Moderation State to the new state.
        $this->entity->set('moderation_state', $state);
        // If there is a Revision Log a part of the entity, update the log.
        if ($this->entity instanceof RevisionLogInterface) {
          $this->entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
          $this->entity->setRevisionLogMessage(t('Bulk operation ' . $state . ' revision'));
          $this->entity->setRevisionUserId($user->id());
        }
        $this->entity->setSyncing(TRUE);
        $this->entity->setRevisionTranslationAffected(TRUE);
        $this->entity->save();
      }
    }

    return $this->entity;
  }

  /**
   * A way to set the workflows to check for the passed state.
   *
   * @param array $workflows
   *
   * @return void
   */
  public function setWorkflows(array $workflows) {
    $this->workflows = $workflows;
  }

  /**
   * Get the latest revision.
   */
  public static function _latest_revision($entity, $entityId, &$vid, $langcode = NULL) {
    // Can be removed once we move to Drupal >= 8.6.0 , currently on 8.5.0.
    // See change record here: https://www.drupal.org/node/2942013 .
    $lang = $langcode;
    if (!isset($lang)) {
      $lang = \Drupal::languageManager()->getCurrentLanguage()->getId();
    }
    $latestRevisionResult = \Drupal::entityTypeManager()->getStorage($entity->getEntityType()->id())->getQuery()
      ->latestRevision()
      ->condition($entity->getEntityType()->getKey('id'), $entityId, '=')
      ->accessCheck(TRUE)
      ->execute();
    if (count($latestRevisionResult)) {
      $node_revision_id = key($latestRevisionResult);
      if ($node_revision_id == $vid) {
        // There is no pending revision, the current revision is the latest.
        return FALSE;
      }
      $vid = $node_revision_id;
      $latestRevision = \Drupal::entityTypeManager()->getStorage($entity->getEntityType()->id())->loadRevision($node_revision_id);
      if ($latestRevision->language()->getId() != $lang && $latestRevision->hasTranslation($lang)) {
        $latestRevision = $latestRevision->getTranslation($lang);
      }
      return $latestRevision;
    }
    return FALSE;
  }

  /**
   * Validate that the workflow state exists.
   *
   * @return Boolean
   *   Return TRUE if found, FALSE if not.
   */
  protected function validateWorkflowState($stateToCheck) {
    foreach ($this->workflows as $wid) {
      $workflow = Workflow::load($wid);
      if ($workflow) {
        $workflowPlugin = $workflow->getTypePlugin();
        $states = $workflowPlugin->getStates();
        if (isset($states[$stateToCheck])) {
          return true;
        }
      }
    }

    return false;
  }

}
