<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_console_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\rook_servicechannel_console_api\Request\ConsoleRequestContextResolver;
use Drupal\rook_servicechannel_console_api\Service\ConsoleSessionLifecycleManager;
use Drupal\rook_servicechannel_core\Entity\SupportSession;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ConsoleSessionController extends ControllerBase {

  public function __construct(
    private readonly ConsoleSessionLifecycleManager $consoleSessionLifecycleManager,
    private readonly ConsoleRequestContextResolver $requestContextResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('rook_servicechannel_console_api.console_session_lifecycle_manager'),
      $container->get('rook_servicechannel_console_api.request_context_resolver'),
    );
  }

  /**
   * Starts a new support session and returns the generated PIN.
   */
  public function beginSession(Request $request): JsonResponse {
    try {
      $this->decodeJsonBody($request);
      $session = $this->consoleSessionLifecycleManager->beginSession(
        $this->requestContextResolver->getObservedIpAddress($request),
      );

      return $this->successResponse(['session' => $this->buildSessionPayload($session)]);
    }
    catch (\Throwable $throwable) {
      return $this->errorResponse('begin_session_failed', $throwable->getMessage());
    }
  }

  /**
   * Returns the current status for the supplied session PIN.
   */
  public function status(Request $request): JsonResponse {
    try {
      $payload = $this->decodeJsonBody($request);
      $pin = $this->requirePin($payload);
      $session = $this->consoleSessionLifecycleManager->getSessionStatus($pin);

      if ($session === NULL) {
        return $this->errorResponse('session_not_found', 'No support session was found for the supplied PIN.');
      }

      return $this->successResponse(['session' => $this->buildSessionPayload($session)]);
    }
    catch (\Throwable $throwable) {
      return $this->errorResponse('status_failed', $throwable->getMessage());
    }
  }

  /**
   * Accepts a session heartbeat for the supplied PIN.
   */
  public function ping(Request $request): JsonResponse {
    try {
      $payload = $this->decodeJsonBody($request);
      $pin = $this->requirePin($payload);
      $session = $this->consoleSessionLifecycleManager->acceptHeartbeat(
        $pin,
        $this->requestContextResolver->getObservedIpAddress($request),
      );

      if ($session === NULL) {
        return $this->errorResponse('heartbeat_rejected', 'The support session is missing or no longer active.');
      }

      return $this->successResponse();
    }
    catch (\Throwable $throwable) {
      return $this->errorResponse('ping_failed', $throwable->getMessage());
    }
  }

  /**
   * Ends the session for the supplied PIN.
   */
  public function endSession(Request $request): JsonResponse {
    try {
      $payload = $this->decodeJsonBody($request);
      $pin = $this->requirePin($payload);
      $session = $this->consoleSessionLifecycleManager->endSession(
        $pin,
        $this->requestContextResolver->getObservedIpAddress($request),
      );

      if ($session === NULL) {
        return $this->errorResponse('session_not_found', 'No support session was found for the supplied PIN.');
      }

      return $this->successResponse();
    }
    catch (\Throwable $throwable) {
      return $this->errorResponse('end_session_failed', $throwable->getMessage());
    }
  }

  /**
   * Decodes the JSON request body.
   *
   * @return array<string, mixed>
   *   The decoded JSON body.
   */
  private function decodeJsonBody(Request $request): array {
    $content = trim($request->getContent());

    if ($content === '') {
      return [];
    }

    $payload = json_decode($content, TRUE, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) {
      throw new \UnexpectedValueException('The request body must decode to a JSON object.');
    }

    return $payload;
  }

  /**
   * Extracts the required PIN field from a decoded payload.
   *
   * @param array<string, mixed> $payload
   *   Decoded request payload.
   */
  private function requirePin(array $payload): string {
    $pin = $payload['pin'] ?? NULL;
    if (!is_string($pin) || trim($pin) === '') {
      throw new \InvalidArgumentException('The request must include a non-empty "pin" field.');
    }

    return trim($pin);
  }

  /**
   * Maps a support session entity to the public response shape.
   *
   * @return array<string, string>
   *   API response payload for a session.
   */
  private function buildSessionPayload(SupportSession $session): array {
    return [
      'status' => (string) $session->get('status')->value,
      'pin' => (string) $session->get('pin')->value,
      'ipAddress' => (string) $session->get('console_ip_address')->value,
    ];
  }

  /**
   * Returns a successful API response.
   *
   * @param array<string, mixed> $payload
   *   Response body.
   */
  private function successResponse(array $payload = []): JsonResponse {
    return new JsonResponse($payload === [] ? new \stdClass() : $payload, 200);
  }

  /**
   * Returns a draft-compatible error response.
   */
  private function errorResponse(string $code, string $message): JsonResponse {
    return new JsonResponse([
      'code' => $code,
      'message' => $message,
    ], 500);
  }

}
