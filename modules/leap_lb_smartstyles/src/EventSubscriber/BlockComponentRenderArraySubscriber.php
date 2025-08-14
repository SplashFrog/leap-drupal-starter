<?php

namespace Drupal\leap_lb_smartstyles\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\layout_builder\LayoutBuilderEvents;

/**
 * Event subscriber to the initial render array.
 */
class BlockComponentRenderArraySubscriber implements EventSubscriberInterface {

  /**
   * The decorated event subscriber service.
   *
   * @var \Symfony\Component\EventDispatcher\EventSubscriberInterface
   */
  protected $original;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * BlockComponentRenderArraySubscriber constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventSubscriberInterface $original
   * *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Access configuration.
   */
  public function __construct(EventSubscriberInterface $original, EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $config_factory) {
    $this->original = $original;
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Layout Builder also subscribes to this event to build the initial render
    // array. We use a higher weight so that we execute after it.
    $events[LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY] = [
      'onBuildRender',
      50,
    ];
    return $events;
  }

  /**
   * Add each component's block styles to the render array.
   *
   * @param \Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent $event
   *   The section component render event.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event) {
    $build = $event->getBuild();
    // This shouldn't happen - Layout Builder should have already created the
    // initial build data.
    if (empty($build)) {
      return;
    }

    $selectedStyles = $event->getComponent()->get('layout_builder_styles_style');
    if ($selectedStyles) {
      // Convert single selection to an array for consistent processing.
      if (!is_array($selectedStyles)) {
        $selectedStyles = [$selectedStyles];
      }

      // Pass the selected style(s) to the render array so we can use the data
      // when adding block theme suggestions.
      // See layout_builder_styles_theme_suggestions_block_alter().
      $build['#layout_builder_style'] = $selectedStyles;
      $build['#smartstyles_block_classes'] = [];

      // Retrieve all styles from selection(s).
      //if (!isset($build['#attributes']['class']) || !is_array($build['#attributes']['class'])) {
      //  $build['#attributes']['class'] = [];
      //}
      foreach ($selectedStyles as $styleId) {
        // Account for incorrectly configured component configuration which may
        // have a NULL style ID. We cannot pass NULL to the storage handler or
        // it will throw an exception.
        if (empty($styleId)) {
          continue;
        }
        /** @var \Drupal\layout_builder_styles\LayoutBuilderStyleInterface $style */
        $style = $this->entityTypeManager->getStorage('layout_builder_style')->load($styleId);
        if ($style) {
          $splitName = explode('__', $styleId);
          $subkey = (count($splitName) > 2 ? $splitName[1] : 'general');

          $classes = \preg_split('(\r\n|\r|\n)', $style->getClasses());

          $existing_classes = (is_array($build['#smartstyles_block_classes']) && array_key_exists($subkey, $build['#smartstyles_block_classes']) ? $build['#smartstyles_block_classes'][$subkey] : []);
          $grouped_classes = array_merge($existing_classes, $classes);
          $build['#smartstyles_block_classes'][$subkey] = $grouped_classes;

          $build['#cache']['tags'][] = 'config:layout_builder_styles.style.' . $style->id();
        }
      }
      $event->setBuild($build);
    }
  }

}
