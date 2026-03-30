<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_core;

final class TerminalGrantStatus {

  public const ISSUED = 'issued';
  public const REDEEMED = 'redeemed';
  public const EXPIRED = 'expired';
  public const REVOKED = 'revoked';

  /**
   * Returns all valid terminal grant statuses.
   *
   * @return string[]
   *   Valid terminal grant status values.
   */
  public static function all(): array {
    return [
      self::ISSUED,
      self::REDEEMED,
      self::EXPIRED,
      self::REVOKED,
    ];
  }

}
