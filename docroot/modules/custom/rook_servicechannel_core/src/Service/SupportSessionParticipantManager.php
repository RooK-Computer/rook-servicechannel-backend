<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_core\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\rook_servicechannel_core\Entity\SupportSession;
use Drupal\user\UserInterface;

final class SupportSessionParticipantManager {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Couples a user to a support session and refreshes the last-seen timestamp.
   */
  public function coupleSession(SupportSession $session, UserInterface $account): void {
    $record = $this->database
      ->select('rook_support_session_participant', 'participant')
      ->fields('participant', ['id'])
      ->condition('support_session_id', (int) $session->id())
      ->condition('user_id', (int) $account->id())
      ->condition('state', 'coupled')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    $now = $this->time->getRequestTime();

    if ($record !== FALSE) {
      $this->database
        ->update('rook_support_session_participant')
        ->fields([
          'last_seen_at' => $now,
        ])
        ->condition('id', (int) $record['id'])
        ->execute();
      return;
    }

    $this->database
      ->insert('rook_support_session_participant')
      ->fields([
        'support_session_id' => (int) $session->id(),
        'user_id' => (int) $account->id(),
        'state' => 'coupled',
        'coupled_at' => $now,
        'last_seen_at' => $now,
      ])
      ->execute();
  }

  /**
   * Returns whether a user is actively coupled to a support session.
   */
  public function isCoupled(SupportSession $session, UserInterface $account): bool {
    $participant_id = $this->database
      ->select('rook_support_session_participant', 'participant')
      ->fields('participant', ['id'])
      ->condition('support_session_id', (int) $session->id())
      ->condition('user_id', (int) $account->id())
      ->condition('state', 'coupled')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return $participant_id !== FALSE;
  }

}
