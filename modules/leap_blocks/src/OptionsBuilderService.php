<?php

declare(strict_types=1);

namespace Drupal\leap_blocks;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Service to extract field values and format them as CSS classes or SDC props.
 *
 * This utility acts as a bridge between Drupal's strict machine names
 * (often using underscores) and frontend CSS/SDC requirements
 * (which typically prefer dashed notation for utility classes).
 */
final class OptionsBuilderService {

  /**
   * The list of fields to check on the entity.
   *
   * Formatted as ['target_array_key' => 'drupal_field_machine_name'].
   *
   * @var array<string|int, string>
   */
  private array $fieldList = [];

  /**
   * Sets the internal array of fields to check for values.
   *
   * @param array<string|int, string> $list
   *   An associative array mapping the desired output key to the Drupal field name.
   *   Example: ['bg_color' => 'field_background_color'].
   */
  public function setFieldList(array $list): void {
    $this->fieldList = $list;
  }

  /**
   * Builds the style options array based on the configured field list.
   *
   * Iterates through the provided field map, checks if the entity actually
   * possesses that field and if it contains a value, and then automatically
   * sanitizes the string (converting underscores to hyphens) to ensure
   * CSS class compatibility.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity (Block Content, Paragraph, Node, etc.) to extract values from.
   *
   * @return array<string, string>
   *   A flat array of formatted style options. Keys correspond to the provided
   *   field list, and values are the extracted (and dashed) string values.
   */
  public function buildOptions(ContentEntityInterface $entity): array {
    if (empty($this->fieldList)) {
      return [];
    }

    $style_options = [];

    foreach ($this->fieldList as $option_key => $field_name) {
      // If the developer provided a simple indexed array instead of an associative map,
      // fallback to using the raw field name as the array key.
      $key = is_numeric($option_key) ? strtolower($field_name) : (string) $option_key;

      // Default to empty string to prevent Twig 'undefined variable' warnings.
      $style_options[$key] = '';

      if ($entity->hasField($field_name) && !$entity->get($field_name)->isEmpty()) {
        $value = $entity->get($field_name)->value;
        if (is_string($value)) {
          // Convert database-friendly underscores to frontend-friendly hyphens.
          // Example: 'bg_dark_subtle' becomes 'bg-dark-subtle'.
          $style_options[$key] = str_replace('_', '-', $value);
        }
      }
    }

    return $style_options;
  }

}
