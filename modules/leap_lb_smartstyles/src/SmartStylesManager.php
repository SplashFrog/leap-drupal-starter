<?php

declare(strict_types=1);

namespace Drupal\leap_lb_smartstyles;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\VerticalTabs;

/**
 * Manages the logic for extracting and organizing Layout Builder Smart Styles.
 *
 * This service parses the configuration entities provided by the contrib
 * Layout Builder Styles module. It uses string parsing on the machine names
 * to "bucket" styles into logical groups, both for the backend UI forms
 * and the frontend Twig variables.
 */
final class SmartStylesManager {

  /**
   * The prefix used by the contrib module for its form fields.
   */
  private const string FIELD_PREFIX = 'layout_builder_style_';

  /**
   * The machine name for our custom vertical tabs form group.
   */
  private const string FORM_GROUP_NAME = 'leap_layout_block_customization';

  /**
   * The legacy submit handler we must replace due to DOM restructuring.
   */
  private const string LEGACY_SUBMIT_HANDLER = '_layout_builder_styles_submit_block_form';

  /**
   * Constructs a new SmartStylesManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager to load style configs.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Reorganizes the Layout Builder block form into vertical tabs.
   *
   * Parses the form looking for style fields. If it finds the `__` delimiter
   * in the machine name, it extracts the group name and moves the field into
   * a corresponding Vertical Tab Details element for a cleaner UI.
   *
   * @param array $form
   *   The block configuration form.
   */
  public function reorganizeStylesForm(array &$form): void {
    $style_elements = [];

    // Find style elements and group them by their machine name parts.
    foreach ($form as $key => $element) {
      if (str_starts_with((string) $key, self::FIELD_PREFIX)) {
        $parts = explode('__', (string) $key);
        $group_key = $parts[1] ?? 'general';
        $style_elements[$group_key][$key] = $element;
      }
    }

    if (empty($style_elements)) {
      return;
    }

    // Create the Vertical Tabs wrapper.
    $form[self::FORM_GROUP_NAME] = [
      '#type' => 'vertical_tabs',
      '#process' => [[VerticalTabs::class, 'processVerticalTabs']],
      '#pre_render' => [[VerticalTabs::class, 'preRenderVerticalTabs']],
      '#default_tab' => 'edit-' . array_key_first($style_elements),
    ];

    // Move elements into Details groups within the vertical tabs.
    foreach ($style_elements as $group_id => $elements) {
      $group_name = str_replace('_', ' ', $group_id);
      $form[$group_id] = [
        '#type' => 'details',
        '#title' => ucwords(strtolower($group_name)),
        '#group' => self::FORM_GROUP_NAME,
      ];

      foreach ($elements as $field_name => $element) {
        $form[$group_id][$field_name] = $element;
        unset($form[$field_name]);
      }
    }

    // Ensure our custom submit handler runs first, as the contrib module's
    // handler does not know how to traverse vertical tab arrays.
    if (isset($form['#submit']) && in_array(self::LEGACY_SUBMIT_HANDLER, $form['#submit'], TRUE)) {
      $key = array_search(self::LEGACY_SUBMIT_HANDLER, $form['#submit'], TRUE);
      unset($form['#submit'][$key]);
      array_unshift($form['#submit'], '_leap_lb_smartstyles_layout_builder_styles_submit_block_form');
    }
  }

  /**
   * Extracts selected style IDs from the submitted form state.
   *
   * Reverses the bucketing process to extract the selected IDs from the
   * vertical tab groupings and returns a flat array for saving.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The submitted form state.
   *
   * @return array
   *   A flat array of selected Layout Builder Style IDs.
   */
  public function getStylesFromFormState(FormStateInterface $form_state): array {
    $found_styles = [];
    $values = $form_state->getValues();

    foreach ($values as $group_values) {
      if (!is_array($group_values)) {
        continue;
      }
      foreach ($group_values as $key => $value) {
        if (str_starts_with((string) $key, self::FIELD_PREFIX) && !empty($value)) {
          if (is_array($value)) {
            $found_styles = array_merge($found_styles, array_values($value));
          }
          else {
            $found_styles[] = $value;
          }
        }
      }
    }
    return array_filter($found_styles);
  }

  /**
   * Processes layout-level variables to separate smart container classes.
   *
   * Standard behavior dumps all styles directly into `attributes.class`.
   * This method intercepts that, removing the classes from the root wrapper
   * and bucketing them into `layout_container` and `layout_styles` arrays
   * so the Twig template can route them to inner DOM elements.
   *
   * @param array $variables
   *   The layout preprocess variables.
   */
  public function processLayoutVariables(array &$variables): void {
    if (!isset($variables['settings']['layout_builder_styles_style'])) {
      return;
    }

    $selected_ids = $variables['settings']['layout_builder_styles_style'];
    $variables['layout_full_width'] = FALSE;
    $variables['layout_container'] = [];
    $variables['layout_styles'] = [];

    $style_storage = $this->entityTypeManager->getStorage('layout_builder_style');

    foreach ($selected_ids as $id) {
      /** @var \Drupal\layout_builder_styles\LayoutBuilderStyleInterface $style */
      $style = $style_storage->load($id);
      if (!$style) {
        continue;
      }

      $classes = preg_split('/\r\n|\r|\n/', $style->getClasses() ?: '');

      // Clean up the main attributes array (remove Dumb Styles).
      if (isset($variables['attributes']['class'])) {
        $variables['attributes']['class'] = array_diff($variables['attributes']['class'], $classes);
      }

      // Handle Container logic based on specific machine names.
      if (str_starts_with($id, 'layout_container__')) {
        if ($id === 'layout_container__full_width') {
          $variables['layout_full_width'] = TRUE;
        }
        elseif ($id === 'layout_container__centered') {
          $variables['layout_full_width'] = FALSE;
        }
        else {
          $variables['layout_container'] = array_merge($variables['layout_container'], $classes);
        }
      }
      else {
        // All other layout styles (padding, background colors, etc.).
        $variables['layout_styles'] = array_merge($variables['layout_styles'], $classes);
      }
    }
  }

  /**
   * Buckets block-level styles into the smart_styles array.
   *
   * Evaluates the machine names of selected block styles to group their
   * underlying CSS classes. (e.g., `block__text__centered` goes into the
   * `text` bucket).
   *
   * @param array $selected_ids
   *   The selected style IDs.
   *
   * @return array
   *   A multidimensional array of CSS classes keyed by group name.
   */
  public function bucketBlockStyles(array $selected_ids): array {
    $buckets = [];
    $style_storage = $this->entityTypeManager->getStorage('layout_builder_style');

    foreach ($selected_ids as $id) {
      if (empty($id)) {
        continue;
      }

      /** @var \Drupal\layout_builder_styles\LayoutBuilderStyleInterface $style */
      $style = $style_storage->load($id);
      if (!$style) {
        continue;
      }

      $parts = explode('__', $id);
      $bucket_id = $parts[1] ?? 'general';
      $classes = preg_split('/\r\n|\r|\n/', $style->getClasses() ?: '');

      $buckets[$bucket_id] = array_merge($buckets[$bucket_id] ?? [], $classes);
    }

    return $buckets;
  }

}
