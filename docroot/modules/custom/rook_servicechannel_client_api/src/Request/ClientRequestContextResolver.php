<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_client_api\Request;

use Symfony\Component\HttpFoundation\Request;

final class ClientRequestContextResolver {

  /**
   * Returns the effective client IP for the current request.
   */
  public function getObservedIpAddress(Request $request): ?string {
    return $request->getClientIp();
  }

}
