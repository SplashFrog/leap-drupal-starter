<?php

namespace Drupal\leap_smartpaths;

use Drupal\Core\Cache\Cache;
use Drupal\node\Entity\Node;

/**
 * Provides functionality for recursive URL construction.
 */
class SmartPaths {
  /**
   * Array of node ids.
   */
  protected array $addedNodes;

  /**
   * Name of the field to check for parent page.
   */
  protected string $parentField;

  /**
   * Name of the field to check for another slug to use.
   */
  protected string $optionalPathField;

  public function __construct() {
    $this->addedNodes = [];
    $this->parentField = 'field_parent_content';
    $this->optionalPathField = 'field_optional_path';
  }

  /**
   * Builds the page title using the optional field or node title.
   *
   * @param Node $node
   *   The node object.
   * @param array $options
   *   Array of options for alias cleaner.
   *
   * @return string
   *   The proper URL part.
   */
  public function buildPageTitle(Node $node, array $options = []): string {
    if (empty($node)) {
      return "";
    }

    $optional_text = "";
    $node_title = $node->getTitle();

    if ($node->hasField($this->optionalPathField)) {
      $optional_field = $node->get($this->optionalPathField)->getValue();
      if (!empty($optional_field)) {
        $optional_text = $optional_field[0]['value'];
      }
    }

    return \Drupal::service('pathauto.alias_cleaner')->cleanString((empty($optional_text) ? $node_title : $optional_text), $options);
  }

  /**
   * Builds out the path of a given node based on the referenced parent page.
   *
   * @param int|null $node_id
   *   The passed node ID.
   * @param Node|null $node
   *   The passed node object.
   * @param array $options
   *   The passed options.
   *
   * @return string
   *   Returns a string of the path.
   */
  public function buildParentPagePath(int $node_id = NULL, Node $node = NULL, array $options = []): string {
    if (empty($node_id) && empty($node)) {
      return "";
    }

    if (empty($node)) {
      $node = Node::load($node_id);
    }

    $parent_alias_path = "";
    $this->addedNodes = [];

    if ($node->hasField($this->parentField)) {
      $field_value = $node->get($this->parentField)->getValue();
      if (!empty($field_value)) {
        // There is a parent page so loop through until we reach the top.
        $do_loop = TRUE;
        $parent_id = $field_value[0]['target_id'];
        $this->addedNodes[] = $parent_id;

        while ($do_loop) {
          $parent_data = $this->getParentPage($parent_id, $options);
          if (!$parent_data['status']) {
            $do_loop = FALSE;
            continue;
          }

          $parent_alias_path = (!empty($parent_alias_path)) ? $parent_data['path'] . "/" . $parent_alias_path : $parent_data['path'];
          if (empty($parent_data['id']) || in_array($parent_data['id'], $this->addedNodes)) {
            $do_loop = FALSE;
            continue;
          }

          $parent_id = $parent_data['id'];
          $this->addedNodes[] = $parent_data['id'];
        }
      }
    }

    return $parent_alias_path;
  }

  /**
   * Gets a cleaned URL segment using the passed node id.
   *
   * @param int $node_id
   *   The node ID.
   * @param array $options
   *   An array of options.
   *
   * @return array
   *   Returns an array with information.
   */
  protected function getParentPage(int $node_id, array $options = []): array {
    $parent_status = [
      'status' => FALSE,
      'path' => "",
      'id' => NULL,
    ];

    $parent_node = Node::load($node_id);
    if (empty($parent_node)) {
      return $parent_status;
    }

    $parent_status['status'] = TRUE;
    $tokenized_title = $this->buildPageTitle($parent_node, $options);
    $parent_status['path'] = $tokenized_title;

    if ($parent_node->hasField($this->parentField)) {
      $field_value = $parent_node->get($this->parentField)->getValue();
      // There is no value so there is no parent.
      if (empty($field_value)) {
        return $parent_status;
      }

      $parent_status['id'] = $field_value[0]['target_id'];
    }

    return $parent_status;
  }

  /**
   * Primary method to update all children of a node.
   *
   * @param Node $node
   *   The node object.
   */
  public function updateChildrenPages(Node $node): void {
    $children = $this->findChildrenPages($node->id());
    if (!empty($children)) {
      foreach ($children as $child) {
        $child_node = Node::load($child);

        // Validate if the Pathauto is on. If not, skip.
        $child_auto = $child_node->get('path');
        if ($child_auto->pathauto) {
          $this->updateChildPage($child_node);
        }
      }
    }
  }

  /**
   * Finds the children nodes of the passed node ID.
   *
   * @param int $parent_id
   *   The node id that is the parent.
   *
   * @return array|int
   *   Returns the query result as an arrary or FALSE.
   */
  protected function findChildrenPages(int $parent_id): array|int {
    $query = \Drupal::entityQuery('node');
    return $query
      ->accessCheck(FALSE)
      ->condition($this->parentField, $parent_id)
      ->condition('status', TRUE)
      ->execute();
  }

  /**
   * Process used to update the child node's path alias through pathauto.
   *
   * @param Node $node
   *   The node object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function updateChildPage(Node $node): void {
    // Using Path Alias manager, we update the pathauto paths.
    // First load the children paths by the path using the node id.
    $path_alias_manager = \Drupal::entityTypeManager()->getStorage('path_alias');
    $child_path_objects = $path_alias_manager->loadByProperties([
      'path' => '/node/' . $node->id(),
    ]);

    // If no children paths present, return.
    if (empty($child_path_objects)) {
      return;
    }

    // Go through the aliases loaded, and do any replacements necessary.
    foreach ($child_path_objects as $child) {
      $new_child_path = $this->buildParentPagePath(NULL, $node, []);
      if (empty($new_child_path)) {
        continue;
      }

      $parent_title = $this->buildPageTitle($node);
      $tokenized_title = \Drupal::service('pathauto.alias_cleaner')->cleanString($parent_title, []);
      $replacement_path = '/' . $new_child_path . '/' . $tokenized_title;

      $child->alias = $replacement_path;
      $child->save();

      // Invalidate tags on the node so the path shows up correctly.
      Cache::invalidateTags($node->getCacheTags());

      // Now that the child alias was updated successfully, check for any
      // children of this node and call this function accordingly.
      $this->updateChildrenPages($node);
    }
  }

}
