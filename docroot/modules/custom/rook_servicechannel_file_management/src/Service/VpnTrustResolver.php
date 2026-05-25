<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_file_management\Service;

use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

final class VpnTrustResolver {

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly ?AccessInterface $consoleIpGuardAccessCheck = NULL,
  ) {}

  public function isTrustedDownloadRequest(Request $request): bool {
    if (!$this->moduleHandler->moduleExists('rook_servicechannel_console_ip_guard')) {
      return TRUE;
    }

    if (!$this->consoleIpGuardAccessCheck instanceof AccessInterface) {
      return FALSE;
    }

    return $this->consoleIpGuardAccessCheck
      ->access(new Route('/servicechannel/files/{node}/download'), $request)
      ->isAllowed();
  }

}
