<?php

namespace Drupal\leap_lb_smartstyles;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\VerticalTabs;

class LayoutSmartStylesBlock {
  /**
   * @var string $fieldPrefix
   *   The beginning of the naming convention of the layout builder style.
   */
  protected $fieldPrefix = 'layout_builder_style_';

  /**
   * @var string $formGroupName
   *   The name of the new form group.
   */
  protected $formGroupName = 'leap_layout_block_customization';

  /**
   * @var string $layoutBuilderFormValidationName
   *   The name of the original LB method name we need to override.
   */
  protected $layoutBuilderFormValidationName = '_layout_builder_styles_submit_block_form';

  /**
   * Main function that will update / manipulate the layout block form.
   *
   * @param $form
   *   The full form of the block that is being updated.
   *
   * @return mixed
   *   Return the form element back.
   */
  public function updateLayoutBuilderForm($form) {
    $styleFields = $this->getLayoutStyleElements($form);
    if (count($styleFields) > 0) {
      $form[$this->formGroupName] = [
        '#type' => 'vertical_tabs',
        '#process' => [
          [VerticalTabs::class, 'processVerticalTabs'],
        ],
        '#pre_render' => [
          [VerticalTabs::class, 'preRenderVerticalTabs'],
        ],
        '#default_tab' => array_key_first($styleFields),
      ];

      foreach ($styleFields as $detailField => $elements) {
        $form[strtolower($detailField)] = [
          '#type' => 'details',
          '#title' => ucwords(strtolower(str_replace('_', ' ', $detailField))),
          '#group' => $this->formGroupName,
        ];

        foreach ($elements as $fieldName => $element) {
          $form[strtolower($detailField)][$fieldName] = $element;
          unset($form[$fieldName]);
        }
      }
    }

    // Our submit handler must execute before the default one, because the
    // default handler stores the section & component data in the tempstore
    // and we need to update those objects before that happens. Also removing
    // the contrib module submit handler as we are doing our own custom hook
    // to change the form.
    if (in_array($this->layoutBuilderFormValidationName, $form['#submit'])) {
      $submit_key = array_search($this->layoutBuilderFormValidationName, $form['#submit']);
      unset($form['#submit'][$submit_key]);
      array_unshift($form['#submit'], '_leap_lb_smartstyles_layout_builder_styles_submit_block_form');
    }

    return $form;
  }

  public function updateSubmitBlockForm(array $form, FormStateInterface $formState) {
    $foundFields = [];

    foreach ($form as $key => $formElement) {
      if (is_array($formElement) && key_exists('#group', $formElement) && ($formElement['#group'] === $this->formGroupName)) {
        foreach ($formElement as $groupKey => $groupElement) {
          $stringKey = (string) $groupKey;
          if (str_starts_with($stringKey, $this->fieldPrefix)) {
            $value = $formState->getValues()[$key][$groupKey];
            if (is_array($value)) {
              $foundFields += $value;
            }
            else {
              $foundFields[] = $value;
            }
          }
        }
      }
    }

    return $foundFields;
  }

  /**
   * Method to find the layout builder styles elements and set up an array to easily update from.
   *
   * @param $form
   *   The form element.
   *
   * @return array
   *   The returned array of found elements.
   */
  protected function getLayoutStyleElements($form) {
    $newFormDisplay = [];
    foreach ($form as $key => $element) {
      $stringKey = (string) $key;
      if (str_starts_with($stringKey, $this->fieldPrefix)) {
        $keyParts = explode('__', $stringKey);
        if (!key_exists($keyParts[1], $newFormDisplay)) {
          $newFormDisplay[$keyParts[1]] = [];
        }

        $newFormDisplay[$keyParts[1]][$stringKey] = $element;
      }
    }

    return $newFormDisplay;
  }
}