<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_gateway_api\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\rook_servicechannel_core\Entity\SupportSession;
use Drupal\rook_servicechannel_core\Entity\TerminalGrant;
use Drupal\rook_servicechannel_core\Service\SupportSessionManager;
use Drupal\rook_servicechannel_core\Service\TerminalGrantManager;
use Drupal\rook_servicechannel_core\TerminalGrantStatus;
use Drupal\rook_servicechannel_gateway_api\Exception\GatewayApiException;

final class GatewayGrantValidator {

  public function __construct(
    private readonly TimeInterface $time,
    private readonly SupportSessionManager $supportSessionManager,
    private readonly TerminalGrantManager $terminalGrantManager,
  ) {}

  /**
   * Validates and redeems a terminal grant token.
   *
   * @return array{ipAddress: string}
   *   The validation result payload.
   */
  public function validateToken(string $token): array {
    $grant = $this->terminalGrantManager->loadGrantByToken($token);

    if ($grant === NULL) {
      throw new GatewayApiException('grant_not_found', 'No terminal grant was found for the supplied token.');
    }

    $session = $this->loadGrantSession($grant);
    $session = $this->supportSessionManager->expireSessionIfTimedOut($session);

    if ($this->supportSessionManager->isClosed($session)) {
      $this->terminalGrantManager->expireGrant($grant);
      throw new GatewayApiException('session_not_available', 'The support session bound to this grant is no longer available.');
    }

    if ((string) $session->get('console_ip_address')->value !== (string) $grant->get('console_ip_address')->value) {
      $this->terminalGrantManager->revokeGrant($grant);
      throw new GatewayApiException('grant_console_ip_mismatch', 'The console IP address for this grant no longer matches the active session.');
    }

    $now = $this->time->getRequestTime();
    $expires_at = (int) $grant->get('expires_at')->value;

    if ($expires_at < $now) {
      $this->terminalGrantManager->expireGrant($grant);
      throw new GatewayApiException('grant_expired', 'The terminal grant has expired.');
    }

    $status = (string) $grant->get('status')->value;
    if ($status === TerminalGrantStatus::ISSUED) {
      $this->terminalGrantManager->redeemGrant($grant);
    }
    elseif ($status === TerminalGrantStatus::REDEEMED) {
      $reconnect_valid_until = (int) ($grant->get('reconnect_valid_until')->value ?: 0);
      if ($reconnect_valid_until < $now) {
        $this->terminalGrantManager->revokeGrant($grant);
        throw new GatewayApiException('grant_already_redeemed', 'The terminal grant has already been redeemed.');
      }

      $this->terminalGrantManager->markGrantUsed($grant);
    }
    elseif ($status === TerminalGrantStatus::EXPIRED) {
      throw new GatewayApiException('grant_expired', 'The terminal grant has expired.');
    }
    elseif ($status === TerminalGrantStatus::REVOKED) {
      throw new GatewayApiException('grant_revoked', 'The terminal grant has been revoked.');
    }
    else {
      throw new GatewayApiException('grant_invalid_state', 'The terminal grant is in an unsupported state.');
    }

    return [
      'ipAddress' => (string) $grant->get('console_ip_address')->value,
    ];
  }

  /**
   * Loads the support session referenced by a grant.
   */
  private function loadGrantSession(TerminalGrant $grant): SupportSession {
    $session = $grant->get('support_session_id')->entity;

    if (!$session instanceof SupportSession) {
      throw new GatewayApiException('grant_session_missing', 'The support session bound to this grant could not be loaded.');
    }

    return $session;
  }
}
