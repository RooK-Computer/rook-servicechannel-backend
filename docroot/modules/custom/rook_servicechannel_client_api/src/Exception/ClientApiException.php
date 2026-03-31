<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_client_api\Exception;

final class ClientApiException extends \RuntimeException {

  public function __construct(
    private readonly string $apiCode,
    string $message,
  ) {
    parent::__construct($message);
  }

  public function getApiCode(): string {
    return $this->apiCode;
  }

}
