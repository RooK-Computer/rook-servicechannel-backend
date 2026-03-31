<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_console_api\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rook_servicechannel_core\Entity\SupportSession;
use Drupal\rook_servicechannel_core\Service\AuditLogWriter;
use Drupal\rook_servicechannel_core\Service\SupportSessionManager;
use Drupal\rook_servicechannel_core\SupportSessionStatus;

final class ConsoleSessionLifecycleManager {

  private const PIN_MIN = 1000;
  private const PIN_MAX = 9999;
  private const PIN_GENERATION_ATTEMPTS = 100;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    private readonly SupportSessionManager $supportSessionManager,
    private readonly AuditLogWriter $auditLogWriter,
  ) {}

  /**
   * Starts a fresh support session for the calling console IP.
   */
  public function beginSession(string $observedIpAddress): SupportSession {
    foreach ($this->loadLiveSessionsByIpAddress($observedIpAddress) as $existing_session) {
      $this->supportSessionManager->closeSession($existing_session, 'replaced_by_latest_start');
      $this->auditLogWriter->write(
        'session_closed',
        (int) $existing_session->id(),
        NULL,
        NULL,
        $observedIpAddress,
        ['reason' => 'replaced_by_latest_start'],
      );
    }

    $session = $this->supportSessionManager->createSession(
      $this->generateUniquePin(),
      $observedIpAddress,
      $observedIpAddress,
    );

    $this->auditLogWriter->write(
      'session_started',
      (int) $session->id(),
      NULL,
      NULL,
      $observedIpAddress,
    );

    return $session;
  }

  /**
   * Returns the latest session for a PIN, updating timeout state if needed.
   */
  public function getSessionStatus(string $pin): ?SupportSession {
    $session = $this->loadLatestSessionByPin($pin);

    if ($session === NULL) {
      return NULL;
    }

    return $this->closeExpiredSessionIfNeeded($session);
  }

  /**
   * Applies a heartbeat to the matching live session.
   */
  public function acceptHeartbeat(string $pin, string $observedIpAddress): ?SupportSession {
    $session = $this->loadLatestSessionByPin($pin);

    if ($session === NULL) {
      return NULL;
    }

    $session = $this->closeExpiredSessionIfNeeded($session);
    if ($this->isClosed($session)) {
      return NULL;
    }

    $previous_ip_address = (string) $session->get('console_ip_address')->value;
    $session = $this->supportSessionManager->markHeartbeat($session, $observedIpAddress);

    $payload = [];
    if ($previous_ip_address !== $observedIpAddress) {
      $payload['previousIpAddress'] = $previous_ip_address;
      $payload['updatedIpAddress'] = $observedIpAddress;
    }

    $this->auditLogWriter->write(
      'session_heartbeat',
      (int) $session->id(),
      NULL,
      NULL,
      $observedIpAddress,
      $payload,
    );

    return $session;
  }

  /**
   * Ends the latest session for a PIN.
   */
  public function endSession(string $pin, string $observedIpAddress): ?SupportSession {
    $session = $this->loadLatestSessionByPin($pin);

    if ($session === NULL) {
      return NULL;
    }

    $session = $this->closeExpiredSessionIfNeeded($session);
    if ($this->isClosed($session)) {
      return $session;
    }

    $session = $this->supportSessionManager->closeSession($session, 'ended_by_agent');
    $this->auditLogWriter->write(
      'session_closed',
      (int) $session->id(),
      NULL,
      NULL,
      $observedIpAddress,
      ['reason' => 'ended_by_agent'],
    );

    return $session;
  }

  /**
   * Loads the newest matching session for a PIN.
   */
  private function loadLatestSessionByPin(string $pin): ?SupportSession {
    return $this->supportSessionManager->loadLatestSessionByPin($pin);
  }

  /**
   * Loads all still-live sessions for a console IP or VPN peer IP.
   *
   * @return \Drupal\rook_servicechannel_core\Entity\SupportSession[]
   *   Matching open or active sessions.
   */
  private function loadLiveSessionsByIpAddress(string $observedIpAddress): array {
    $query = $this->entityTypeManager
      ->getStorage('support_session')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', [
        SupportSessionStatus::OPEN,
        SupportSessionStatus::ACTIVE,
      ], 'IN');

    $ip_group = $query->orConditionGroup()
      ->condition('console_ip_address', $observedIpAddress)
      ->condition('vpn_peer_ip_address', $observedIpAddress);

    $ids = $query
      ->condition($ip_group)
      ->execute();

    if ($ids === []) {
      return [];
    }

    /** @var \Drupal\rook_servicechannel_core\Entity\SupportSession[] $sessions */
    $sessions = $this->entityTypeManager
      ->getStorage('support_session')
      ->loadMultiple($ids);

    return array_values($sessions);
  }

  /**
   * Generates an unused 4-digit PIN for live sessions.
   */
  private function generateUniquePin(): string {
    for ($attempt = 0; $attempt < self::PIN_GENERATION_ATTEMPTS; $attempt++) {
      $pin = (string) random_int(self::PIN_MIN, self::PIN_MAX);

      $ids = $this->entityTypeManager
        ->getStorage('support_session')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('pin', $pin)
        ->condition('status', [
          SupportSessionStatus::OPEN,
          SupportSessionStatus::ACTIVE,
        ], 'IN')
        ->range(0, 1)
        ->execute();

      if ($ids === []) {
        return $pin;
      }
    }

    throw new \RuntimeException('Unable to generate a unique 4-digit PIN.');
  }

  /**
   * Closes expired sessions once their heartbeat window elapsed.
   */
  private function closeExpiredSessionIfNeeded(SupportSession $session): SupportSession {
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
      (string) $session->get('console_ip_address')->value,
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
