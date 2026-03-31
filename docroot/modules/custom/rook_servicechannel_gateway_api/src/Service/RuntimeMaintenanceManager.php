<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_gateway_api\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rook_servicechannel_core\Entity\SupportSession;
use Drupal\rook_servicechannel_core\Entity\TerminalGrant;
use Drupal\rook_servicechannel_core\Service\SupportSessionManager;
use Drupal\rook_servicechannel_core\Service\TerminalGrantManager;
use Drupal\rook_servicechannel_core\SupportSessionStatus;
use Drupal\rook_servicechannel_core\TerminalGrantStatus;

final class RuntimeMaintenanceManager {

  private const CLOSED_SESSION_RETENTION_SECONDS = 3600;

  public function __construct(
    private readonly TimeInterface $time,
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly SupportSessionManager $supportSessionManager,
    private readonly TerminalGrantManager $terminalGrantManager,
  ) {}

  /**
   * Runs routine backend maintenance for sessions and grants.
   */
  public function runMaintenance(): void {
    $this->closeExpiredSessions();
    $this->expireExpiredGrants();
    $this->deleteStaleClosedSessions();
  }

  /**
   * Closes open or active sessions whose heartbeat window elapsed.
   */
  private function closeExpiredSessions(): void {
    $now = $this->time->getRequestTime();

    $ids = $this->entityTypeManager
      ->getStorage('support_session')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', [
        SupportSessionStatus::OPEN,
        SupportSessionStatus::ACTIVE,
      ], 'IN')
      ->condition('expires_at', $now, '<')
      ->execute();

    if ($ids === []) {
      return;
    }

    /** @var \Drupal\rook_servicechannel_core\Entity\SupportSession[] $sessions */
    $sessions = $this->entityTypeManager
      ->getStorage('support_session')
      ->loadMultiple($ids);

    foreach ($sessions as $session) {
      $this->supportSessionManager->closeSession($session, 'heartbeat_timeout');
    }
  }

  /**
   * Expires grants whose validity window has elapsed.
   */
  private function expireExpiredGrants(): void {
    $now = $this->time->getRequestTime();

    $ids = $this->entityTypeManager
      ->getStorage('terminal_grant')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', [
        TerminalGrantStatus::ISSUED,
        TerminalGrantStatus::REDEEMED,
      ], 'IN')
      ->condition('expires_at', $now, '<')
      ->execute();

    if ($ids === []) {
      return;
    }

    /** @var \Drupal\rook_servicechannel_core\Entity\TerminalGrant[] $grants */
    $grants = $this->entityTypeManager
      ->getStorage('terminal_grant')
      ->loadMultiple($ids);

    foreach ($grants as $grant) {
      $this->terminalGrantManager->expireGrant($grant);
    }
  }

  /**
   * Deletes old closed sessions and their lightweight technical relations.
   */
  private function deleteStaleClosedSessions(): void {
    $cutoff = $this->time->getRequestTime() - self::CLOSED_SESSION_RETENTION_SECONDS;

    $ids = $this->entityTypeManager
      ->getStorage('support_session')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', SupportSessionStatus::CLOSED)
      ->condition('closed_at', $cutoff, '<=')
      ->execute();

    if ($ids === []) {
      return;
    }

    $this->deleteSessionRelatedGrants(array_map('intval', $ids));

    $this->database
      ->delete('rook_support_session_participant')
      ->condition('support_session_id', array_map('intval', $ids), 'IN')
      ->execute();

    /** @var \Drupal\rook_servicechannel_core\Entity\SupportSession[] $sessions */
    $sessions = $this->entityTypeManager
      ->getStorage('support_session')
      ->loadMultiple($ids);

    if ($sessions !== []) {
      $this->entityTypeManager
        ->getStorage('support_session')
        ->delete($sessions);
    }
  }

  /**
   * Deletes grants belonging to sessions that are about to be removed.
   *
   * @param int[] $sessionIds
   *   Session IDs to clean up.
   */
  private function deleteSessionRelatedGrants(array $sessionIds): void {
    $grant_ids = $this->entityTypeManager
      ->getStorage('terminal_grant')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('support_session_id', $sessionIds, 'IN')
      ->execute();

    if ($grant_ids === []) {
      return;
    }

    /** @var \Drupal\rook_servicechannel_core\Entity\TerminalGrant[] $grants */
    $grants = $this->entityTypeManager
      ->getStorage('terminal_grant')
      ->loadMultiple($grant_ids);

    if ($grants !== []) {
      $this->entityTypeManager
        ->getStorage('terminal_grant')
        ->delete($grants);
    }
  }

}
