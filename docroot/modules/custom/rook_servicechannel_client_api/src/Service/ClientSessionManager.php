<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_client_api\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\rook_servicechannel_client_api\Exception\ClientApiException;
use Drupal\rook_servicechannel_core\Entity\SupportSession;
use Drupal\rook_servicechannel_core\Service\AuditLogWriter;
use Drupal\rook_servicechannel_core\Service\SupportSessionManager;
use Drupal\rook_servicechannel_core\Service\SupportSessionParticipantManager;
use Drupal\rook_servicechannel_core\Service\TerminalGrantManager;
use Drupal\rook_servicechannel_core\SupportSessionStatus;
use Drupal\user\UserInterface;

final class ClientSessionManager {

  public function __construct(
    private readonly TimeInterface $time,
    private readonly SupportSessionManager $supportSessionManager,
    private readonly SupportSessionParticipantManager $supportSessionParticipantManager,
    private readonly TerminalGrantManager $terminalGrantManager,
    private readonly AuditLogWriter $auditLogWriter,
  ) {}

  /**
   * Couples a service user to a live support session.
   */
  public function pinLookup(string $pin, UserInterface $account, ?string $ipAddress = NULL): SupportSession {
    $session = $this->requireSessionByPin($pin, $ipAddress);

    if ($this->isClosed($session)) {
      throw new ClientApiException('session_not_available', 'The support session is no longer available.');
    }

    $this->supportSessionParticipantManager->coupleSession($session, $account);
    $session = $this->supportSessionManager->activateSession($session);

    $this->auditLogWriter->write(
      'pin_lookup',
      (int) $session->id(),
      NULL,
      (int) $account->id(),
      $ipAddress,
      ['pin' => $pin],
    );

    return $session;
  }

  /**
   * Returns the frontend-facing session state for a coupled service user.
   */
  public function getSessionStatus(string $pin, UserInterface $account, ?string $ipAddress = NULL): SupportSession {
    $session = $this->requireSessionByPin($pin, $ipAddress);
    $this->requireCoupledSession($session, $account);

    return $session;
  }

  /**
   * Issues a terminal grant for a coupled service user.
   *
   * @return array{grant: \Drupal\rook_servicechannel_core\Entity\TerminalGrant, token: string}
   *   The persisted grant and its one-time token.
   */
  public function requestShell(string $pin, UserInterface $account, ?string $ipAddress = NULL): array {
    $session = $this->requireSessionByPin($pin, $ipAddress);
    $this->requireCoupledSession($session, $account);

    if ($this->isClosed($session)) {
      throw new ClientApiException('session_not_available', 'The support session is no longer available.');
    }

    $this->terminalGrantManager->revokeOutstandingGrants($session, $account);

    $expires_at = (int) ($session->get('expires_at')->value ?: $this->time->getRequestTime() + 30);
    $grant_data = $this->terminalGrantManager->issueGrant(
      $session,
      $account,
      (string) $session->get('console_ip_address')->value,
      $expires_at,
      $this->time->getRequestTime() + 30,
    );

    $this->auditLogWriter->write(
      'grant_issued',
      (int) $session->id(),
      (int) $grant_data['grant']->id(),
      (int) $account->id(),
      $ipAddress,
      ['pin' => $pin],
    );

    return $grant_data;
  }

  /**
   * Loads a session by PIN and closes it if it has timed out.
   */
  private function requireSessionByPin(string $pin, ?string $ipAddress): SupportSession {
    $session = $this->supportSessionManager->loadLatestSessionByPin($pin);

    if ($session === NULL) {
      throw new ClientApiException('session_not_found', 'No support session was found for the supplied PIN.');
    }

    $session = $this->closeExpiredSessionIfNeeded($session, $ipAddress);

    return $session;
  }

  /**
   * Ensures that the current service user is coupled to the session.
   */
  private function requireCoupledSession(SupportSession $session, UserInterface $account): void {
    if ($this->supportSessionParticipantManager->isCoupled($session, $account)) {
      $this->supportSessionParticipantManager->coupleSession($session, $account);
      return;
    }

    throw new ClientApiException('session_not_coupled', 'The support session is not coupled to the current service user.');
  }

  /**
   * Closes timed-out sessions once their heartbeat window elapsed.
   */
  private function closeExpiredSessionIfNeeded(SupportSession $session, ?string $ipAddress): SupportSession {
    if ($this->isClosed($session)) {
      return $session;
    }

    $expires_at = $session->get('expires_at')->value;
    if ($expires_at === NULL || $expires_at === '') {
      return $session;
    }

    if ((int) $expires_at >= $this->time->getRequestTime()) {
      return $session;
    }

    $session = $this->supportSessionManager->closeSession($session, 'heartbeat_timeout');
    $this->auditLogWriter->write(
      'session_closed',
      (int) $session->id(),
      NULL,
      NULL,
      $ipAddress ?? (string) $session->get('console_ip_address')->value,
      ['reason' => 'heartbeat_timeout'],
    );

    return $session;
  }

  /**
   * Returns whether the session is already closed.
   */
  private function isClosed(SupportSession $session): bool {
    return (string) $session->get('status')->value === SupportSessionStatus::CLOSED;
  }

}
