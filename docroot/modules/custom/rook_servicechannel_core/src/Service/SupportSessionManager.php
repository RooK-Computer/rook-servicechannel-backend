<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_core\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\rook_servicechannel_core\Entity\SupportSession;
use Drupal\rook_servicechannel_core\SupportSessionStatus;

final class SupportSessionManager {

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
        'expires_at' => $now + 30,
        'active_terminal_count' => 0,
      ]);

    $session->save();
    return $session;
  }

  /**
   * Records an accepted heartbeat and refreshes the timeout window.
   */
  public function markHeartbeat(SupportSession $session, string $observedIpAddress): SupportSession {
    $now = $this->time->getRequestTime();

    $session->set('console_ip_address', $observedIpAddress);
    $session->set('last_heartbeat_at', $now);
    $session->set('expires_at', $now + 30);
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

}
