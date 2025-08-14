<?php

namespace Drupal\leap_blocks;

use Drupal\block_content\BlockContentInterface;
use Drupal\paragraphs\Entity\Paragraph;

class OptionsBuilder {
  /**
   * @var array $blockFields;
   *   The list of fields that will put into twig variable options.
   */
  protected $blockFields = [];

  /**
   * @var array $paragraphFields;
   *   The list of fields that will put into twig variable options.
   */
  protected $paragraphFields = [];

  /**
   * Sets the internal array of fields to check for values.
   *
   * @param array $list
   *   The list of fields to check.
   * @return void
   */
  public function setBlockList(array $list): void {
    $this->blockFields = $list;
  }

  /**
   * Sets the internal array of fields to check for values.
   *
   * @param array $list
   *   The list of fields to check.
   * @return void
   */
  public function setParagraphList(array $list): void {
    $this->paragraphFields = $list;
  }

  /**
   * Builds a block's set fields for twig variables.
   *
   * @param BlockContentInterface $block_content
   *   The block content.
   * @param array $variables
   *   The variables array from the preprocessing hook. Defaults to an empty array.
   * @return void
   */
  public function buildBlockOptions(BlockContentInterface $block_content, array &$variables = []): void {
    if (is_array($this->blockFields) && !empty($this->blockFields)) {
      $styleOptions = [];
      foreach ($this->blockFields as $optionKey => $fieldName) {
        // Make sure the key is not numeric.
        $key = (is_numeric($optionKey) ? strtolower($fieldName) : $optionKey);
        $styleOptions[$key] = '';

        if ($block_content->hasField($fieldName)) {
          $fieldValue = $block_content->get($fieldName)->getValue();
          if (!empty($fieldValue)) {
            $value = is_array($fieldValue) ? $fieldValue[0]['value'] : $fieldValue;
            $styleOptions[$key] = preg_replace('/_/', '-', $value);
          }
        }
      }

      $variables['block_options'] = $styleOptions;
    }
  }

  /**
   * Builds a block's set fields for twig variables.
   * *
   * @param Paragraph $paragraph
   *   The paragraph content.
   * @param array $variables
   *   The variables array from the preprocessing hook. Defaults to an empty array.
   * @return void
   */
  public function buildParagraphOptions(Paragraph $paragraph, array &$variables = []): void {
    if (is_array($this->paragraphFields) && !empty($this->paragraphFields)) {
      $styleOptions = [];
      foreach ($this->paragraphFields as $optionKey => $fieldName) {
        // Make sure the key is not numeric.
        $key = (is_numeric($optionKey) ? strtolower($fieldName) : $optionKey);
        $styleOptions[$key] = '';

        if ($paragraph->hasField($fieldName)) {
          $fieldValue = $paragraph->get($fieldName)->getValue();
          if (!empty($fieldValue)) {
            $value = is_array($fieldValue) ? $fieldValue[0]['value'] : $fieldValue;
            $styleOptions[$key] = preg_replace('/_/', '-', $value);
          }
        }
      }

      $variables['paragraph_options'] = $styleOptions;
    }
  }
}
