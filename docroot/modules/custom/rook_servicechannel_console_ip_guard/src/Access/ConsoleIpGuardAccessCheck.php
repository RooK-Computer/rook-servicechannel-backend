<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_console_ip_guard\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\Routing\Route;

final class ConsoleIpGuardAccessCheck implements AccessInterface {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Checks whether the current request IP is allowed to reach console routes.
   */
  public function access(Route $route, Request $request): AccessResult {
    $allowed_ips = $this->configFactory
      ->get('rook_servicechannel_console_ip_guard.settings')
      ->get('allowed_ips') ?? [];

    if (!is_array($allowed_ips)) {
      return AccessResult::forbidden('The configured allowlist must be an array.')->setCacheMaxAge(0);
    }

    $normalized_allowed_ips = array_values(array_filter(array_map(static fn(mixed $value): string => is_string($value) ? trim($value) : '', $allowed_ips)));
    if ($normalized_allowed_ips === []) {
      return AccessResult::forbidden('No allowed source IPs are configured for the console API guard.')->setCacheMaxAge(0);
    }

    $client_ip = $request->getClientIp();
    if ($client_ip === NULL || $client_ip === '') {
      return AccessResult::forbidden('Unable to determine the request IP address.')->setCacheMaxAge(0);
    }

    foreach ($normalized_allowed_ips as $allowed_ip) {
      if (IpUtils::checkIp($client_ip, $allowed_ip)) {
        return AccessResult::allowed()->setCacheMaxAge(0);
      }
    }

    return AccessResult::forbidden('The request IP address is not allowed to access the console API.')->setCacheMaxAge(0);
  }

}
