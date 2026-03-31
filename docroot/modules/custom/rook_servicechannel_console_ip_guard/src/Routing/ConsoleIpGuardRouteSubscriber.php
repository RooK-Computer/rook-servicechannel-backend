<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_console_ip_guard\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

final class ConsoleIpGuardRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach ([
      'rook_servicechannel_console_api.begin_session',
      'rook_servicechannel_console_api.status',
      'rook_servicechannel_console_api.ping',
      'rook_servicechannel_console_api.end_session',
    ] as $route_name) {
      $route = $collection->get($route_name);
      if ($route === NULL) {
        continue;
      }

      $route->setRequirement('_rook_console_ip_guard', 'TRUE');
    }
  }

}
