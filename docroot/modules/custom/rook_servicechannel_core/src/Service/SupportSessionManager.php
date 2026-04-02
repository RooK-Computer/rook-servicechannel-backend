<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_core\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\rook_servicechannel_core\Entity\SupportSession;
use Drupal\rook_servicechannel_core\SupportSessionStatus;

final class SupportSessionManager {

  private const HEARTBEAT_TIMEOUT_SECONDS = 30;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Creates a new support session.
   */
  public function createSession(string $pin, string $consoleIpAddress, ?string $vpnPeerIpAddress = NULL): SupportSession {
    $now = $this->time->getRequestTime();

    /** @var \Drupal\rook_servicechannel_core\Entity\SupportSession $session */
    $session = $this->entityTypeManager
      ->getStorage('support_session')
      ->create([
        'pin' => $pin,
        'status' => SupportSessionStatus::OPEN,
        'console_ip_address' => $consoleIpAddress,
        'vpn_peer_ip_address' => $vpnPeerIpAddress,
        'started_at' => $now,
        'last_heartbeat_at' => $now,
        'expires_at' => $this->buildHeartbeatExpiryTimestamp($now),
        'active_terminal_count' => 0,
      ]);

    $session->save();
    return $session;
  }

  /**
   * Loads the newest matching session for a PIN.
   */
  public function loadLatestSessionByPin(string $pin): ?SupportSession {
    $ids = $this->entityTypeManager
      ->getStorage('support_session')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('pin', $pin)
      ->sort('id', 'DESC')
      ->range(0, 1)
      ->execute();

    if ($ids === []) {
      return NULL;
    }

    /** @var \Drupal\rook_servicechannel_core\Entity\SupportSession|null $session */
    $session = $this->entityTypeManager
      ->getStorage('support_session')
      ->load((int) reset($ids));

    return $session;
  }

  /**
   * Records an accepted heartbeat and refreshes the timeout window.
   */
  public function markHeartbeat(SupportSession $session, string $observedIpAddress): SupportSession {
    $now = $this->time->getRequestTime();

    $session->set('console_ip_address', $observedIpAddress);
    $session->set('last_heartbeat_at', $now);
    $session->set('expires_at', $this->buildHeartbeatExpiryTimestamp($now));
    $session->save();

    return $session;
  }

  /**
   * Marks a support session as active.
   */
  public function activateSession(SupportSession $session): SupportSession {
    $session->set('status', SupportSessionStatus::ACTIVE);
    if ($session->get('claimed_at')->isEmpty()) {
      $session->set('claimed_at', $this->time->getRequestTime());
    }
    $session->save();

    return $session;
  }

  /**
   * Marks a live support session as open again after active use ended.
   */
  public function markSessionOpen(SupportSession $session): SupportSession {
    if ($this->isClosed($session)) {
      throw new \LogicException('A closed support session cannot be moved back to open.');
    }

    $session->set('status', SupportSessionStatus::OPEN);
    $session->set('active_terminal_count', 0);
    $session->save();

    return $session;
  }

  /**
   * Closes a support session with an explicit reason.
   */
  public function closeSession(SupportSession $session, string $reason): SupportSession {
    $now = $this->time->getRequestTime();

    $session->set('status', SupportSessionStatus::CLOSED);
    $session->set('close_reason', $reason);
    $session->set('closed_at', $now);
    $session->set('expires_at', $now);
    $session->save();

    return $session;
  }

  /**
   * Closes an open or active session once its heartbeat window elapsed.
   */
  public function expireSessionIfTimedOut(SupportSession $session): SupportSession {
    if ($this->isClosed($session) || !$this->isHeartbeatTimedOut($session)) {
      return $session;
    }

    return $this->closeSession($session, 'heartbeat_timeout');
  }

  /**
   * Returns whether the heartbeat grace window has elapsed.
   */
  public function isHeartbeatTimedOut(SupportSession $session): bool {
    $expires_at = $session->get('expires_at')->value;

    if ($expires_at === NULL || $expires_at === '') {
      return FALSE;
    }

    return (int) $expires_at < $this->time->getRequestTime();
  }

  /**
   * Returns whether the session is already closed.
   */
  public function isClosed(SupportSession $session): bool {
    return (string) $session->get('status')->value === SupportSessionStatus::CLOSED;
  }

  /**
   * Returns the configured heartbeat timeout window in seconds.
   */
  public function getHeartbeatTimeoutSeconds(): int {
    return self::HEARTBEAT_TIMEOUT_SECONDS;
  }

  /**
   * Builds a session expiry timestamp from a heartbeat reference time.
   */
  private function buildHeartbeatExpiryTimestamp(int $referenceTime): int {
    return $referenceTime + self::HEARTBEAT_TIMEOUT_SECONDS;
  }

}
