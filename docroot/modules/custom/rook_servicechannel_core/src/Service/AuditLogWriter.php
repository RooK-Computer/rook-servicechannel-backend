<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_core\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

final class AuditLogWriter {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Writes an append-only audit record.
   *
   * @param array<string, mixed> $payload
   *   Additional payload to store as JSON.
   */
  public function write(
    string $eventType,
    ?int $supportSessionId = NULL,
    ?int $terminalGrantId = NULL,
    ?int $userId = NULL,
    ?string $ipAddress = NULL,
    array $payload = [],
  ): void {
    $this->database->insert('rook_support_audit_log')
      ->fields([
        'support_session_id' => $supportSessionId,
        'terminal_grant_id' => $terminalGrantId,
        'user_id' => $userId,
        'event_type' => $eventType,
        'ip_address' => $ipAddress,
        'payload_json' => $payload === [] ? NULL : json_encode($payload, JSON_THROW_ON_ERROR),
        'created_at' => $this->time->getRequestTime(),
      ])
      ->execute();
  }

}
