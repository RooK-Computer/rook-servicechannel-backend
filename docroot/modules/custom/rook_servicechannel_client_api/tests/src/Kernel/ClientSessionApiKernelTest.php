<?php

declare(strict_types=1);

namespace Drupal\Tests\rook_servicechannel_client_api\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Covers the main web client API flows.
 */
#[RunTestsInSeparateProcesses]
final class ClientSessionApiKernelTest extends KernelTestBase {

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
    'rook_servicechannel_client_api',
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

    $this->container->get('module_handler')->loadInclude('rook_servicechannel_client_api', 'install');
    _rook_servicechannel_client_api_ensure_service_role();
  }

  public function testServiceRoleIsProvisioned(): void {
    $role = $this->container->get('entity_type.manager')->getStorage('user_role')->load('service');

    self::assertNotNull($role);
    self::assertSame('Service', $role->label());
    self::assertTrue($role->hasPermission('access rook client api'));
  }

  public function testPinLookupStatusAndRequestShellFlow(): void {
    $session = $this->container
      ->get('rook_servicechannel_core.support_session_manager')
      ->createSession('4711', '10.0.0.5');

    $service_user = $this->createUserWithRoles(['service']);
    $this->container->get('current_user')->setAccount($service_user);

    $pin_lookup_response = $this->jsonPost('/api/client/1/pinlookup', ['pin' => '4711']);
    self::assertSame(200, $pin_lookup_response->getStatusCode());
    self::assertSame('active', $this->decodeJsonResponse($pin_lookup_response)['session']['status']);

    $status_response = $this->jsonPost('/api/client/1/sessionstatus', ['pin' => '4711']);
    self::assertSame(200, $status_response->getStatusCode());
    self::assertSame('active', $this->decodeJsonResponse($status_response)['session']['status']);

    $request_shell_response = $this->jsonPost('/api/client/1/requestshell', ['pin' => '4711']);
    self::assertSame(200, $request_shell_response->getStatusCode());
    self::assertNotSame('', $this->decodeJsonResponse($request_shell_response)['grant']['token']);

    $participant_rows = $this->container->get('database')
      ->select('rook_support_session_participant', 'participant')
      ->fields('participant', ['state'])
      ->condition('support_session_id', (int) $session->id())
      ->condition('user_id', (int) $service_user->id())
      ->execute()
      ->fetchCol();

    self::assertSame(['coupled'], $participant_rows);

    $grant_count = $this->container->get('entity_type.manager')
      ->getStorage('terminal_grant')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('support_session_id', (int) $session->id())
      ->condition('user_id', (int) $service_user->id())
      ->count()
      ->execute();

    self::assertSame(1, (int) $grant_count);
  }

  public function testAccessIsDeniedWithoutServiceRole(): void {
    $regular_user = $this->createUserWithRoles([]);
    $this->container->get('current_user')->setAccount($regular_user);

    $this->expectException(AccessDeniedHttpException::class);
    $this->sendJsonPost('/api/client/1/pinlookup', ['pin' => '4711']);
  }

  public function testUnknownPinReturnsErrorPayload(): void {
    $service_user = $this->createUserWithRoles(['service']);
    $this->container->get('current_user')->setAccount($service_user);

    $response = $this->jsonPost('/api/client/1/sessionstatus', ['pin' => '9999']);

    self::assertSame(500, $response->getStatusCode());
    self::assertSame('session_not_found', $this->decodeJsonResponse($response)['code']);
  }

  /**
   * Creates and saves a Drupal user with the given roles.
   *
   * @param string[] $roles
   *   Role machine names.
   */
  private function createUserWithRoles(array $roles): User {
    $suffix = bin2hex(random_bytes(4));

    $user = User::create([
      'name' => 'user_' . $suffix,
      'mail' => 'user_' . $suffix . '@example.com',
      'status' => 1,
    ]);

    foreach ($roles as $role) {
      $user->addRole($role);
    }

    $user->save();

    return $user;
  }

  /**
   * Sends a JSON POST request and returns the JsonResponse.
   *
   * @param array<string, mixed> $payload
   *   Request payload.
   */
  private function jsonPost(string $path, array $payload = []): JsonResponse {
    $response = $this->sendJsonPost($path, $payload);
    self::assertInstanceOf(JsonResponse::class, $response);

    return $response;
  }

  /**
   * Sends a JSON POST request into Drupal's kernel.
   *
   * @param array<string, mixed> $payload
   *   Request payload.
   */
  private function sendJsonPost(string $path, array $payload = []): mixed {
    $request = Request::create(
      $path,
      'POST',
      [],
      [],
      [],
      [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'REMOTE_ADDR' => '127.0.0.1',
      ],
      json_encode($payload, JSON_THROW_ON_ERROR),
    );

    return $this->container
      ->get('http_kernel')
      ->handle($request, HttpKernelInterface::MAIN_REQUEST, FALSE);
  }

  /**
   * Decodes a JSON response into an array.
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
