<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_client_api\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rook_servicechannel_client_api\Exception\ClientApiException;
use Drupal\rook_servicechannel_client_api\Request\ClientRequestContextResolver;
use Drupal\rook_servicechannel_client_api\Service\ClientSessionManager;
use Drupal\rook_servicechannel_core\Entity\SupportSession;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ClientSessionController implements ContainerInjectionInterface {

  public function __construct(
    private readonly ClientSessionManager $clientSessionManager,
    private readonly ClientRequestContextResolver $requestContextResolver,
    private readonly AccountProxyInterface $currentUser,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('rook_servicechannel_client_api.client_session_manager'),
      $container->get('rook_servicechannel_client_api.request_context_resolver'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Couples a session by PIN for the current Service user.
   */
  public function pinLookup(Request $request): JsonResponse {
    try {
      $pin = $this->requirePin($this->decodeJsonBody($request));
      $session = $this->clientSessionManager->pinLookup(
        $pin,
        $this->requireCurrentUser(),
        $this->requestContextResolver->getObservedIpAddress($request),
      );

      return $this->successResponse([
        'session' => $this->buildSessionPayload($session),
      ]);
    }
    catch (ClientApiException $exception) {
      return $this->errorResponse($exception->getApiCode(), $exception->getMessage());
    }
    catch (\Throwable $throwable) {
      return $this->errorResponse('pin_lookup_failed', $throwable->getMessage());
    }
  }

  /**
   * Returns the frontend session status for the current Service user.
   */
  public function sessionStatus(Request $request): JsonResponse {
    try {
      $pin = $this->requirePin($this->decodeJsonBody($request));
      $session = $this->clientSessionManager->getSessionStatus(
        $pin,
        $this->requireCurrentUser(),
        $this->requestContextResolver->getObservedIpAddress($request),
      );

      return $this->successResponse([
        'session' => $this->buildSessionPayload($session),
      ]);
    }
    catch (ClientApiException $exception) {
      return $this->errorResponse($exception->getApiCode(), $exception->getMessage());
    }
    catch (\Throwable $throwable) {
      return $this->errorResponse('session_status_failed', $throwable->getMessage());
    }
  }

  /**
   * Issues a terminal grant token for the current Service user.
   */
  public function requestShell(Request $request): JsonResponse {
    try {
      $pin = $this->requirePin($this->decodeJsonBody($request));
      $grant_data = $this->clientSessionManager->requestShell(
        $pin,
        $this->requireCurrentUser(),
        $this->requestContextResolver->getObservedIpAddress($request),
      );

      return $this->successResponse([
        'grant' => [
          'token' => $grant_data['token'],
        ],
      ]);
    }
    catch (ClientApiException $exception) {
      return $this->errorResponse($exception->getApiCode(), $exception->getMessage());
    }
    catch (\Throwable $throwable) {
      return $this->errorResponse('request_shell_failed', $throwable->getMessage());
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
   * Loads the current Drupal user as a full entity.
   */
  private function requireCurrentUser(): UserInterface {
    $account = $this->entityTypeManager
      ->getStorage('user')
      ->load($this->currentUser->id());

    if (!$account instanceof UserInterface) {
      throw new \RuntimeException('The current service user account could not be loaded.');
    }

    return $account;
  }

  /**
   * Builds the frontend-facing session payload.
   *
   * @return array<string, string>
   *   Session view payload.
   */
  private function buildSessionPayload(SupportSession $session): array {
    return [
      'status' => (string) $session->get('status')->value,
    ];
  }

  /**
   * Returns a successful API response.
   *
   * @param array<string, mixed> $payload
   *   Response body.
   */
  private function successResponse(array $payload): JsonResponse {
    return new JsonResponse($payload, 200);
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
