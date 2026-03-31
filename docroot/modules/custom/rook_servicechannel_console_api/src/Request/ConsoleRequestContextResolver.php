<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_console_api\Request;

use Symfony\Component\HttpFoundation\Request;

final class ConsoleRequestContextResolver {

  /**
   * Returns the effective client IP for the current request.
   */
  public function getObservedIpAddress(Request $request): string {
    $client_ip = $request->getClientIp();

    if ($client_ip === NULL || $client_ip === '') {
      throw new \RuntimeException('Unable to determine the client IP address.');
    }

    return $client_ip;
  }

}
