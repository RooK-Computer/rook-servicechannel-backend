<?php

declare(strict_types=1);

namespace Drupal\Tests\rook_servicechannel_console_api\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Covers the main support-session console API flows.
 */
#[RunTestsInSeparateProcesses]
final class ConsoleSessionApiKernelTest extends KernelTestBase {

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
    'rook_servicechannel_console_api',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('support_session');
    $this->installSchema('rook_servicechannel_core', [
      'rook_support_audit_log',
      'rook_support_session_participant',
    ]);
  }

  public function testBeginStatusPingAndEndSessionFlow(): void {
    $begin_response = $this->jsonPost('/api/console/1/beginsession');
    self::assertSame(200, $begin_response->getStatusCode());

    $begin_payload = $this->decodeJsonResponse($begin_response);
    self::assertSame('open', $begin_payload['session']['status']);
    self::assertMatchesRegularExpression('/^\d{4}$/', $begin_payload['session']['pin']);
    self::assertSame('127.0.0.1', $begin_payload['session']['ipAddress']);

    $pin = $begin_payload['session']['pin'];

    $status_response = $this->jsonPost('/api/console/1/status', ['pin' => $pin]);
    self::assertSame(200, $status_response->getStatusCode());
    self::assertSame('open', $this->decodeJsonResponse($status_response)['session']['status']);

    $ping_response = $this->jsonPost('/api/console/1/ping', ['pin' => $pin]);
    self::assertSame(200, $ping_response->getStatusCode());
    self::assertSame([], $this->decodeJsonResponse($ping_response));

    $end_response = $this->jsonPost('/api/console/1/endsession', ['pin' => $pin]);
    self::assertSame(200, $end_response->getStatusCode());
    self::assertSame([], $this->decodeJsonResponse($end_response));

    $closed_status_response = $this->jsonPost('/api/console/1/status', ['pin' => $pin]);
    self::assertSame(200, $closed_status_response->getStatusCode());
    self::assertSame('closed', $this->decodeJsonResponse($closed_status_response)['session']['status']);

    $events = $this->container->get('database')
      ->select('rook_support_audit_log', 'log')
      ->fields('log', ['event_type'])
      ->orderBy('id')
      ->execute()
      ->fetchCol();

    self::assertSame([
      'session_started',
      'session_heartbeat',
      'session_closed',
    ], $events);
  }

  public function testLatestStartWinsForSameSourceIp(): void {
    $first_begin = $this->decodeJsonResponse($this->jsonPost('/api/console/1/beginsession'));
    $second_begin = $this->decodeJsonResponse($this->jsonPost('/api/console/1/beginsession'));

    self::assertNotSame($first_begin['session']['pin'], $second_begin['session']['pin']);

    $first_status = $this->decodeJsonResponse($this->jsonPost('/api/console/1/status', ['pin' => $first_begin['session']['pin']]));
    $second_status = $this->decodeJsonResponse($this->jsonPost('/api/console/1/status', ['pin' => $second_begin['session']['pin']]));

    self::assertSame('closed', $first_status['session']['status']);
    self::assertSame('open', $second_status['session']['status']);
  }

  /**
   * Sends a JSON POST request into Drupal's kernel.
   */
  private function jsonPost(string $path, array $payload = [], string $ipAddress = '127.0.0.1'): JsonResponse {
    $request = Request::create(
      $path,
      'POST',
      [],
      [],
      [],
      [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'REMOTE_ADDR' => $ipAddress,
      ],
      $payload === [] ? '' : json_encode($payload, JSON_THROW_ON_ERROR),
    );

    $response = $this->container
      ->get('http_kernel')
      ->handle($request, HttpKernelInterface::MAIN_REQUEST, FALSE);

    self::assertInstanceOf(JsonResponse::class, $response);

    return $response;
  }

  /**
   * Decodes a JSON response into an array.
   *
   * @return array<string, mixed>
   *   Response payload.
   */
  private function decodeJsonResponse(JsonResponse $response): array {
    return json_decode($response->getContent() ?: '{}', TRUE, 512, JSON_THROW_ON_ERROR);
  }

}
