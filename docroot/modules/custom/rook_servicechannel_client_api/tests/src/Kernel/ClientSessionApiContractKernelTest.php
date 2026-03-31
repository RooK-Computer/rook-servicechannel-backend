<?php

declare(strict_types=1);

namespace Drupal\Tests\rook_servicechannel_client_api\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Validates the client API contract against the OpenAPI draft.
 */
#[RunTestsInSeparateProcesses]
final class ClientSessionApiContractKernelTest extends KernelTestBase {

  /**
   * Cached OpenAPI document.
   *
   * @var array<string, mixed>|null
   */
  private static ?array $openApiDocument = NULL;

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

    $service_user = User::create([
      'name' => 'service_contract_user',
      'mail' => 'service_contract_user@example.com',
      'status' => 1,
    ]);
    $service_user->addRole('service');
    $service_user->save();

    $this->container->get('current_user')->setAccount($service_user);

    $this->container
      ->get('rook_servicechannel_core.support_session_manager')
      ->createSession('4711', '10.0.0.5');

    $this->jsonPost('/api/client/1/pinlookup', ['pin' => '4711']);
  }

  public function testSuccessResponsesMatchOpenApiContract(): void {
    foreach ([
      '/api/client/1/pinlookup',
      '/api/client/1/sessionstatus',
      '/api/client/1/requestshell',
    ] as $path) {
      $payload = ['pin' => '4711'];
      $this->assertRequestMatchesSchema($path, 'post', $payload);
      $response = $this->jsonPost($path, $payload);
      $this->assertResponseMatchesSchema($path, 'post', $response);
    }
  }

  public function testErrorResponseMatchesOpenApiContract(): void {
    $payload = ['pin' => '9999'];
    $this->assertRequestMatchesSchema('/api/client/1/sessionstatus', 'post', $payload);

    $response = $this->jsonPost('/api/client/1/sessionstatus', $payload);
    self::assertSame(500, $response->getStatusCode());

    $this->assertResponseMatchesSchema('/api/client/1/sessionstatus', 'post', $response);
  }

  /**
   * Asserts that the given request payload matches the OpenAPI request schema.
   *
   * @param array<string, mixed> $payload
   *   The decoded request body.
   */
  private function assertRequestMatchesSchema(string $path, string $method, array $payload): void {
    $operation = $this->getOperation($path, $method);
    $schema = $operation['requestBody']['content']['application/json']['schema'] ?? NULL;

    self::assertIsArray($schema, sprintf('No request schema found for %s %s.', strtoupper($method), $path));
    $this->assertSchemaMatchesData($schema, $payload, sprintf('request %s %s', strtoupper($method), $path));
  }

  /**
   * Asserts that the response body matches the OpenAPI response schema.
   */
  private function assertResponseMatchesSchema(string $path, string $method, JsonResponse $response): void {
    $operation = $this->getOperation($path, $method);
    $responses = $operation['responses'] ?? [];
    $status_code = (string) $response->getStatusCode();
    $response_definition = $responses[$status_code] ?? $responses['default'] ?? NULL;

    self::assertIsArray($response_definition, sprintf('No response schema found for %s %s (%s).', strtoupper($method), $path, $status_code));

    $schema = $response_definition['content']['application/json']['schema'] ?? NULL;
    self::assertIsArray($schema, sprintf('No JSON response schema found for %s %s (%s).', strtoupper($method), $path, $status_code));

    $this->assertSchemaMatchesData(
      $schema,
      $this->decodeJsonResponse($response),
      sprintf('response %s %s (%s)', strtoupper($method), $path, $status_code),
    );
  }

  /**
   * Recursively asserts that data matches the given OpenAPI schema subset.
   *
   * @param array<string, mixed> $schema
   *   OpenAPI schema fragment.
   * @param mixed $data
   *   Decoded JSON value.
   */
  private function assertSchemaMatchesData(array $schema, mixed $data, string $context): void {
    if (isset($schema['$ref'])) {
      $this->assertSchemaMatchesData($this->resolveSchemaReference($schema['$ref']), $data, $context);
      return;
    }

    $type = $schema['type'] ?? NULL;
    if ($type === 'object') {
      self::assertIsArray($data, sprintf('Expected %s to be an object.', $context));
      self::assertFalse(array_is_list($data) && $data !== [], sprintf('Expected %s to decode to an object, got a list.', $context));

      $properties = $schema['properties'] ?? [];
      self::assertIsArray($properties, sprintf('Expected properties for %s to be an array.', $context));

      foreach ($schema['required'] ?? [] as $required_property) {
        self::assertArrayHasKey($required_property, $data, sprintf('Missing required property "%s" for %s.', $required_property, $context));
      }

      if (($schema['additionalProperties'] ?? TRUE) === FALSE) {
        $unexpected_properties = array_diff(array_keys($data), array_keys($properties));
        self::assertSame([], array_values($unexpected_properties), sprintf('Unexpected properties for %s: %s', $context, implode(', ', $unexpected_properties)));
      }

      foreach ($properties as $property_name => $property_schema) {
        if (!array_key_exists($property_name, $data)) {
          continue;
        }

        self::assertIsArray($property_schema, sprintf('Property schema for %s.%s must be an array.', $context, $property_name));
        $this->assertSchemaMatchesData($property_schema, $data[$property_name], $context . '.' . $property_name);
      }

      return;
    }

    if ($type === 'string') {
      self::assertIsString($data, sprintf('Expected %s to be a string.', $context));

      if (isset($schema['minLength'])) {
        self::assertGreaterThanOrEqual((int) $schema['minLength'], mb_strlen($data), sprintf('Expected %s to have minLength %d.', $context, (int) $schema['minLength']));
      }

      return;
    }

    self::fail(sprintf('Unsupported schema type for %s: %s', $context, var_export($type, TRUE)));
  }

  /**
   * Returns an operation definition from the OpenAPI document.
   *
   * @return array<string, mixed>
   *   The operation definition.
   */
  private function getOperation(string $path, string $method): array {
    $document = $this->getOpenApiDocument();
    $operation = $document['paths'][$path][strtolower($method)] ?? NULL;

    self::assertIsArray($operation, sprintf('Operation %s %s must exist in the OpenAPI document.', strtoupper($method), $path));

    return $operation;
  }

  /**
   * Resolves an internal OpenAPI schema reference.
   *
   * @return array<string, mixed>
   *   The resolved schema.
   */
  private function resolveSchemaReference(string $reference): array {
    self::assertStringStartsWith('#/', $reference, sprintf('Only internal schema refs are supported, got %s.', $reference));

    $node = $this->getOpenApiDocument();
    foreach (explode('/', substr($reference, 2)) as $segment) {
      self::assertIsArray($node, sprintf('Unable to resolve OpenAPI ref segment "%s" in %s.', $segment, $reference));
      self::assertArrayHasKey($segment, $node, sprintf('Missing OpenAPI ref segment "%s" in %s.', $segment, $reference));
      $node = $node[$segment];
    }

    self::assertIsArray($node, sprintf('Resolved OpenAPI ref %s must point to an array schema.', $reference));

    return $node;
  }

  /**
   * Loads and caches the OpenAPI draft from the repository root.
   *
   * @return array<string, mixed>
   *   Parsed OpenAPI document.
   */
  private function getOpenApiDocument(): array {
    if (self::$openApiDocument !== NULL) {
      return self::$openApiDocument;
    }

    $document = Yaml::parseFile(dirname(DRUPAL_ROOT) . '/spec/openapi/03-web-backend-rest.openapi.yaml');
    self::assertIsArray($document, 'The OpenAPI document must parse to an array.');

    self::$openApiDocument = $document;
    return self::$openApiDocument;
  }

  /**
   * Sends a JSON POST request into Drupal's kernel.
   *
   * @param array<string, mixed> $payload
   *   Request payload.
   */
  private function jsonPost(string $path, array $payload = []): JsonResponse {
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
