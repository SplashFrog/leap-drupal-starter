<?php

declare(strict_types=1);

namespace Drupal\leap_admin\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events to modify administrative routes.
 *
 * This subscriber is used to surgically remove or alter core and contrib
 * routes to provide a cleaner, more streamlined administrative interface.
 */
final class AdminRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    // Remove the default moderated content route.
    // The LEAP Starter provides an enhanced 'Content' view that integrates
    // moderation filters directly, making the standalone moderated content
    // tab redundant.
    if ($collection->get('content_moderation.admin_moderated_content')) {
      $collection->remove('content_moderation.admin_moderated_content');
    }
  }

}
