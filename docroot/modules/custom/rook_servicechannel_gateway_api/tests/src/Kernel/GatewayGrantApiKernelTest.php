<?php

declare(strict_types=1);

namespace Drupal\Tests\rook_servicechannel_gateway_api\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Covers gateway validation and runtime cleanup flows.
 */
#[RunTestsInSeparateProcesses]
final class GatewayGrantApiKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'rook_servicechannel_core',
    'rook_servicechannel_gateway_api',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('support_session');
    $this->installEntitySchema('terminal_grant');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('rook_servicechannel_core', [
      'rook_support_audit_log',
      'rook_support_session_participant',
    ]);
  }

  public function testValidateTokenRedeemsIssuedGrant(): void {
    [$session, $token] = $this->createSessionAndGrant();

    $response = $this->jsonPost('/api/gateway/1/validateToken', ['token' => $token]);

    self::assertSame(200, $response->getStatusCode());
    self::assertSame('10.0.0.5', $this->decodeJsonResponse($response)['ipAddress']);

    $grant = $this->loadLatestGrantForSession((int) $session->id());
    self::assertSame('redeemed', (string) $grant->get('status')->value);
  }

  public function testReconnectWithinGraceWindowRemainsValid(): void {
    [$session, $token] = $this->createSessionAndGrant();

    $first_response = $this->jsonPost('/api/gateway/1/validateToken', ['token' => $token]);
    self::assertSame(200, $first_response->getStatusCode());

    $second_response = $this->jsonPost('/api/gateway/1/validateToken', ['token' => $token]);
    self::assertSame(200, $second_response->getStatusCode());
    self::assertSame('10.0.0.5', $this->decodeJsonResponse($second_response)['ipAddress']);
  }

  public function testRedeemedGrantIsRejectedAfterReconnectWindow(): void {
    [$session, $token] = $this->createSessionAndGrant();

    $this->jsonPost('/api/gateway/1/validateToken', ['token' => $token]);

    $grant = $this->loadLatestGrantForSession((int) $session->id());
    $grant->set('reconnect_valid_until', \Drupal::time()->getRequestTime() - 1);
    $grant->save();

    $response = $this->jsonPost('/api/gateway/1/validateToken', ['token' => $token]);

    self::assertSame(500, $response->getStatusCode());
    self::assertSame('grant_already_redeemed', $this->decodeJsonResponse($response)['code']);
  }

  public function testRuntimeMaintenanceExpiresGrantAndDeletesOldClosedSession(): void {
    $manager = $this->container->get('rook_servicechannel_core.support_session_manager');
    $maintenance = $this->container->get('rook_servicechannel_gateway_api.runtime_maintenance_manager');

    $active_session = $manager->createSession('4711', '10.0.0.5');
    $active_session->set('expires_at', \Drupal::time()->getRequestTime() - 5);
    $active_session->save();

    $account = $this->createUser();
    $grant_data = $this->container->get('rook_servicechannel_core.terminal_grant_manager')->issueGrant(
      $active_session,
      $account,
      '10.0.0.5',
      \Drupal::time()->getRequestTime() - 1,
      \Drupal::time()->getRequestTime() + 30,
    );

    $stale_session = $manager->createSession('9999', '10.0.0.9');
    $manager->closeSession($stale_session, 'manual');
    $stale_session->set('closed_at', \Drupal::time()->getRequestTime() - 3700);
    $stale_session->save();

    $maintenance->runMaintenance();

    $reloaded_active = $this->container->get('entity_type.manager')->getStorage('support_session')->load((int) $active_session->id());
    self::assertSame('closed', (string) $reloaded_active->get('status')->value);

    $reloaded_grant = $this->container->get('entity_type.manager')->getStorage('terminal_grant')->load((int) $grant_data['grant']->id());
    self::assertSame('expired', (string) $reloaded_grant->get('status')->value);

    $deleted_stale = $this->container->get('entity_type.manager')->getStorage('support_session')->load((int) $stale_session->id());
    self::assertNull($deleted_stale);
  }

  /**
   * Creates a session plus matching terminal grant and returns both.
   *
   * @return array{0: \Drupal\rook_servicechannel_core\Entity\SupportSession, 1: string}
   *   Session entity and plain token.
   */
  private function createSessionAndGrant(): array {
    $session = $this->container->get('rook_servicechannel_core.support_session_manager')->createSession('4711', '10.0.0.5');
    $account = $this->createUser();

    $grant_data = $this->container->get('rook_servicechannel_core.terminal_grant_manager')->issueGrant(
      $session,
      $account,
      '10.0.0.5',
      \Drupal::time()->getRequestTime() + 120,
      \Drupal::time()->getRequestTime() + 30,
    );

    return [$session, $grant_data['token']];
  }

  /**
   * Creates and saves a simple active user.
   */
  private function createUser(): User {
    $suffix = bin2hex(random_bytes(4));

    $user = User::create([
      'name' => 'gateway_user_' . $suffix,
      'mail' => 'gateway_user_' . $suffix . '@example.com',
      'status' => 1,
    ]);
    $user->save();

    return $user;
  }

  /**
   * Loads the latest grant for a session.
   */
  private function loadLatestGrantForSession(int $sessionId) {
    $ids = $this->container->get('entity_type.manager')
      ->getStorage('terminal_grant')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('support_session_id', $sessionId)
      ->sort('id', 'DESC')
      ->range(0, 1)
      ->execute();

    return $this->container->get('entity_type.manager')
      ->getStorage('terminal_grant')
      ->load((int) reset($ids));
  }

  /**
   * Sends a JSON POST request into Drupal's kernel.
   *
   * @param array<string, mixed> $payload
   *   Request payload.
   */
  private function jsonPost(string $path, array $payload): JsonResponse {
    $request = Request::create(
      $path,
      'POST',
      [],
      [],
      [],
      [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
      ],
      json_encode($payload, JSON_THROW_ON_ERROR),
    );

    $response = $this->container
      ->get('http_kernel')
      ->handle($request, HttpKernelInterface::MAIN_REQUEST, FALSE);

    self::assertInstanceOf(JsonResponse::class, $response);

    return $response;
  }

  /**
   * Decodes a JSON response into an associative array.
   *
   * @return array<string, mixed>
   *   Response payload.
   */
  private function decodeJsonResponse(JsonResponse $response): array {
    $decoded = json_decode($response->getContent() ?: '{}', TRUE, 512, JSON_THROW_ON_ERROR);
    self::assertIsArray($decoded);

    return $decoded;
  }

}
