<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_gateway_api\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\rook_servicechannel_gateway_api\Exception\GatewayApiException;
use Drupal\rook_servicechannel_gateway_api\Service\GatewayGrantValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class GatewayGrantController implements ContainerInjectionInterface {

  public function __construct(
    private readonly GatewayGrantValidator $gatewayGrantValidator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('rook_servicechannel_gateway_api.gateway_grant_validator'),
    );
  }

  /**
   * Validates and redeems a terminal grant token.
   */
  public function validateToken(Request $request): JsonResponse {
    try {
      $payload = $this->decodeJsonBody($request);
      $token = $this->requireToken($payload);

      return new JsonResponse(
        $this->gatewayGrantValidator->validateToken($token),
        200,
      );
    }
    catch (GatewayApiException $exception) {
      return $this->errorResponse($exception->getApiCode(), $exception->getMessage());
    }
    catch (\Throwable $throwable) {
      return $this->errorResponse('validate_token_failed', $throwable->getMessage());
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
   * Extracts the required token field from a decoded payload.
   *
   * @param array<string, mixed> $payload
   *   Decoded request payload.
   */
  private function requireToken(array $payload): string {
    $token = $payload['token'] ?? NULL;
    if (!is_string($token) || trim($token) === '') {
      throw new \InvalidArgumentException('The request must include a non-empty "token" field.');
    }

    return trim($token);
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
