<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_core;

final class SupportSessionStatus {

  public const OPEN = 'open';
  public const ACTIVE = 'active';
  public const CLOSED = 'closed';

  /**
   * Returns all valid support session statuses.
   *
   * @return string[]
   *   Valid support session status values.
   */
  public static function all(): array {
    return [
      self::OPEN,
      self::ACTIVE,
      self::CLOSED,
    ];
  }

}
