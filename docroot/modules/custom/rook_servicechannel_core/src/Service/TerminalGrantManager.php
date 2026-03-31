<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_core\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rook_servicechannel_core\Entity\SupportSession;
use Drupal\rook_servicechannel_core\Entity\TerminalGrant;
use Drupal\rook_servicechannel_core\TerminalGrantStatus;
use Drupal\user\UserInterface;

final class TerminalGrantManager {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Issues a new terminal grant and returns the entity plus plain token.
   *
   * @return array{grant: \Drupal\rook_servicechannel_core\Entity\TerminalGrant, token: string}
   *   The persisted grant and the one-time plain token.
   */
  public function issueGrant(
    SupportSession $session,
    UserInterface $account,
    string $consoleIpAddress,
    int $expiresAt,
    int $reconnectValidUntil,
  ): array {
    $token = bin2hex(random_bytes(32));

    /** @var \Drupal\rook_servicechannel_core\Entity\TerminalGrant $grant */
    $grant = $this->entityTypeManager
      ->getStorage('terminal_grant')
      ->create([
        'token_hash' => hash('sha256', $token),
        'support_session_id' => $session->id(),
        'user_id' => $account->id(),
        'console_ip_address' => $consoleIpAddress,
        'status' => TerminalGrantStatus::ISSUED,
        'issued_at' => $this->time->getRequestTime(),
        'expires_at' => $expiresAt,
        'reconnect_valid_until' => $reconnectValidUntil,
      ]);

    $grant->save();

    return [
      'grant' => $grant,
      'token' => $token,
    ];
  }

  /**
   * Loads a persisted grant by its plain token.
   */
  public function loadGrantByToken(string $token): ?TerminalGrant {
    $ids = $this->entityTypeManager
      ->getStorage('terminal_grant')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('token_hash', hash('sha256', $token))
      ->sort('id', 'DESC')
      ->range(0, 1)
      ->execute();

    if ($ids === []) {
      return NULL;
    }

    /** @var \Drupal\rook_servicechannel_core\Entity\TerminalGrant|null $grant */
    $grant = $this->entityTypeManager
      ->getStorage('terminal_grant')
      ->load((int) reset($ids));

    return $grant;
  }

  /**
   * Revokes still-active grants for the same session and user.
   */
  public function revokeOutstandingGrants(SupportSession $session, UserInterface $account): void {
    $ids = $this->entityTypeManager
      ->getStorage('terminal_grant')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('support_session_id', (int) $session->id())
      ->condition('user_id', (int) $account->id())
      ->condition('status', [
        TerminalGrantStatus::ISSUED,
        TerminalGrantStatus::REDEEMED,
      ], 'IN')
      ->execute();

    if ($ids === []) {
      return;
    }

    /** @var \Drupal\rook_servicechannel_core\Entity\TerminalGrant[] $grants */
    $grants = $this->entityTypeManager
      ->getStorage('terminal_grant')
      ->loadMultiple($ids);

    foreach ($grants as $grant) {
      $this->revokeGrant($grant);
    }
  }

  /**
   * Marks a grant as redeemed.
   */
  public function redeemGrant(TerminalGrant $grant): TerminalGrant {
    $now = $this->time->getRequestTime();

    $grant->set('status', TerminalGrantStatus::REDEEMED);
    if ($grant->get('redeemed_at')->isEmpty()) {
      $grant->set('redeemed_at', $now);
    }
    $grant->set('last_used_at', $now);
    $grant->save();

    return $grant;
  }

  /**
   * Refreshes the "last used" timestamp for a grant.
   */
  public function markGrantUsed(TerminalGrant $grant): TerminalGrant {
    $grant->set('last_used_at', $this->time->getRequestTime());
    $grant->save();

    return $grant;
  }

  /**
   * Marks a grant as revoked.
   */
  public function revokeGrant(TerminalGrant $grant): TerminalGrant {
    $grant->set('status', TerminalGrantStatus::REVOKED);
    $grant->save();

    return $grant;
  }

  /**
   * Marks a grant as expired.
   */
  public function expireGrant(TerminalGrant $grant): TerminalGrant {
    $grant->set('status', TerminalGrantStatus::EXPIRED);
    $grant->save();

    return $grant;
  }

}
