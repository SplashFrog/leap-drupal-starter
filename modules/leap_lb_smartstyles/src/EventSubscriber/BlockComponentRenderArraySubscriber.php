<?php

declare(strict_types=1);

namespace Drupal\leap_lb_smartstyles\EventSubscriber;

use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\leap_lb_smartstyles\SmartStylesManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to process Layout Builder style classes for components.
 *
 * This subscriber catches blocks as they are being rendered by Layout Builder.
 * It intercepts the style IDs configured by the editor, delegates them to
 * the SmartStylesManager for "bucketing", and then injects that bucketed
 * array directly into the block's render array (`#smartstyles_block_classes`)
 * so it is available during the hook_preprocess_block phase.
 */
final class BlockComponentRenderArraySubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new BlockComponentRenderArraySubscriber object.
   *
   * @param \Symfony\Component\EventDispatcher\EventSubscriberInterface $original
   *   The original contrib module subscriber (injected for priority context).
   * @param \Drupal\leap_lb_smartstyles\SmartStylesManager $manager
   *   The Smart Styles manager.
   */
  public function __construct(
    private readonly EventSubscriberInterface $original,
    private readonly SmartStylesManager $manager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // We use a higher weight (50) to ensure we execute after both
    // Layout Builder core and the original layout_builder_styles contrib module.
    return [
      LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY => ['onBuildRender', 50],
    ];
  }

  /**
   * Add each component's smart styles to the render array.
   *
   * @param \Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent $event
   *   The event object containing the component configuration.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event): void {
    $build = $event->getBuild();
    if (empty($build)) {
      return;
    }

    $selected_styles = $event->getComponent()->get('layout_builder_styles_style');
    if (!$selected_styles) {
      return;
    }

    // Ensure we are working with an array.
    $selected_ids = is_array($selected_styles) ? $selected_styles : [$selected_styles];

    // Preserve IDs for core theme suggestions logic if needed.
    $build['#layout_builder_style'] = $selected_ids;

    // Delegate the complex machine-name parsing and CSS class bucketing
    // to the manager service, and inject the result into the render pipeline.
    $build['#smartstyles_block_classes'] = $this->manager->bucketBlockStyles($selected_ids);

    $event->setBuild($build);
  }

}
