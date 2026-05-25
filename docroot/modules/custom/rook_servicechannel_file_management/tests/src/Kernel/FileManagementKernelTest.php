<?php

declare(strict_types=1);

namespace Drupal\Tests\rook_servicechannel_file_management\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\rook_servicechannel_core\Entity\SupportSession;
use Drupal\rook_servicechannel_file_management\Controller\FileManagementPageController;
use Drupal\rook_servicechannel_file_management\Controller\FileDownloadController;
use Drupal\rook_servicechannel_file_management\FileLifetime;
use Drupal\rook_servicechannel_file_management\Form\PersistentFileUploadForm;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Covers file visibility, download access and cleanup behavior.
 */
#[RunTestsInSeparateProcesses]
final class FileManagementKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'filter',
    'node',
    'options',
    'text',
    'rook_servicechannel_core',
    'rook_servicechannel_console_api',
    'rook_servicechannel_console_ip_guard',
    'rook_servicechannel_file_management',
  ];

  protected function setUp(): void {
    parent::setUp();

    $private_path = sys_get_temp_dir() . '/rook-file-management-private';
    if (!is_dir($private_path)) {
      mkdir($private_path, 0777, TRUE);
    }
    $this->setSetting('file_private_path', $private_path);

    $this->installSchema('node', ['node_access']);
    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('node');
    $this->installEntitySchema('support_session');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('rook_servicechannel_core', [
      'rook_support_audit_log',
      'rook_support_session_participant',
    ]);
    $this->installConfig(['system', 'user', 'node', 'file', 'rook_servicechannel_console_ip_guard']);
    $this->config('rook_servicechannel_console_ip_guard.settings')
      ->set('allowed_ips', ['127.0.0.1/32', '10.0.0.0/8'])
      ->save();

    $this->createManagedFileBundle();
    $this->ensureServiceRole();
  }

  public function testDownloadAccessUsesBrowserVisibilityAndVpnTrust(): void {
    $owner = $this->createServiceUser();
    $outsider = $this->createServiceUser();
    $node = $this->createManagedFileNode($owner, 'private-owner.txt', FileLifetime::PERSISTENT, FALSE);
    $controller = FileDownloadController::create($this->container);

    $this->container->get('current_user')->setAccount($owner);
    $owner_response = $controller->download(Request::create('/servicechannel/files/' . $node->id() . '/download', 'GET', [], [], [], [
      'REMOTE_ADDR' => '127.0.0.1',
    ]), $node);
    self::assertInstanceOf(BinaryFileResponse::class, $owner_response);
    self::assertSame(200, $owner_response->getStatusCode());

    $this->container->get('current_user')->setAccount($outsider);
    $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
    $controller->download(Request::create('/servicechannel/files/' . $node->id() . '/download', 'GET', [], [], [], [
      'REMOTE_ADDR' => '203.0.113.5',
    ]), $node);
  }

  public function testIpGuardAllowsAnonymousDownloadFromAllowedIp(): void {
    $owner = $this->createServiceUser();
    $node = $this->createManagedFileNode($owner, 'vpn-visible.txt', FileLifetime::PERSISTENT, FALSE);
    $controller = FileDownloadController::create($this->container);

    $this->container->get('current_user')->setAccount(new AnonymousUserSession());
    $vpn_response = $controller->download(Request::create('/servicechannel/files/' . $node->id() . '/download', 'GET', [], [], [], [
      'REMOTE_ADDR' => '10.23.4.5',
    ]), $node);
    self::assertInstanceOf(BinaryFileResponse::class, $vpn_response);
    self::assertSame(200, $vpn_response->getStatusCode());
  }

  public function testIpGuardBlocksAnonymousDownloadFromDeniedIp(): void {
    $owner = $this->createServiceUser();
    $node = $this->createManagedFileNode($owner, 'guarded-download.txt', FileLifetime::PERSISTENT, FALSE);
    $controller = FileDownloadController::create($this->container);

    $this->config('rook_servicechannel_console_ip_guard.settings')
      ->set('allowed_ips', ['192.0.2.10/32'])
      ->save();
    $this->container->get('current_user')->setAccount(new AnonymousUserSession());

    $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
    $controller->download(Request::create('/servicechannel/files/' . $node->id() . '/download', 'GET', [], [], [], [
      'REMOTE_ADDR' => '127.0.0.1',
    ]), $node);
  }

  public function testSidebarApiReturnsVisibleFilesAndCleanupDeletesClosedSessionFiles(): void {
    $owner = $this->createServiceUser();
    $other = $this->createServiceUser();
    $this->container->get('current_user')->setAccount($owner);

    $shared = $this->createManagedFileNode($other, 'shared-guide.txt', FileLifetime::PERSISTENT, TRUE);
    $mine = $this->createManagedFileNode($owner, 'mine.txt', FileLifetime::PERSISTENT, FALSE);
    $session = $this->container->get('rook_servicechannel_core.support_session_manager')->createSession('4711', '10.0.0.5');
    $this->container->get('rook_servicechannel_core.support_session_participant_manager')->coupleSession($session, $owner);
    $session_file = $this->createManagedFileNode($owner, 'session-note.txt', FileLifetime::SESSION, FALSE, $session);

    $response = $this->jsonPost('/api/client/1/files/list', ['pin' => '4711']);
    self::assertSame(200, $response->getStatusCode());

    $payload = $this->decodeJsonResponse($response);
    self::assertCount(3, $payload['files']);
    $origins = array_values(array_unique(array_map(
      static fn (array $file): string => (string) $file['origin'],
      $payload['files'],
    )));
    sort($origins);
    self::assertSame(['mine', 'session', 'shared'], $origins);

    $this->container->get('rook_servicechannel_core.support_session_manager')->closeSession($session, 'manual');
    $deleted = $this->container->get('rook_servicechannel_file_management.managed_file_manager')->cleanupClosedSessionFiles();

    self::assertSame(1, $deleted);
    self::assertNull($this->container->get('entity_type.manager')->getStorage('node')->load((int) $session_file->id()));
    self::assertNotNull($this->container->get('entity_type.manager')->getStorage('node')->load((int) $mine->id()));
    self::assertNotNull($this->container->get('entity_type.manager')->getStorage('node')->load((int) $shared->id()));
  }

  public function testUploadFormSubmitStillWorksAfterFormSerialization(): void {
    $owner = $this->createServiceUser();
    $this->container->get('current_user')->setAccount($owner);

    $file = $this->container->get('file.repository')->writeData(
      'serialized upload payload',
      'temporary://serialized-upload.txt',
    );
    $file->setPermanent();
    $file->save();

    $form_object = PersistentFileUploadForm::create($this->container);
    $restored_form_object = unserialize(serialize($form_object));

    self::assertInstanceOf(PersistentFileUploadForm::class, $restored_form_object);

    $form = [];
    $form_state = (new FormState())
      ->setValue('managed_file', [(int) $file->id()])
      ->setValue('title', 'Serialized form upload')
      ->setValue('description', 'Created through a serialized form object.')
      ->setValue('shared', TRUE);

    $restored_form_object->submitForm($form, $form_state);

    $ids = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'rook_managed_file')
      ->condition('title', 'Serialized form upload')
      ->execute();

    self::assertCount(1, $ids);
  }

  public function testOverviewPageShowsOwnedPersistentFiles(): void {
    $owner = $this->createServiceUser();
    $this->container->get('current_user')->setAccount($owner);

    $this->createManagedFileNode($owner, 'overview-visible.txt', FileLifetime::PERSISTENT, FALSE);

    $build = FileManagementPageController::create($this->container)->overview(Request::create('/servicechannel/files'));

    self::assertSame('overview-visible.txt', $build['owned']['table']['#rows'][0]['title']);
    self::assertSame('overview-visible.txt', $build['owned']['table']['#rows'][0]['filename']);
  }

  private function createManagedFileBundle(): void {
    NodeType::create([
      'type' => 'rook_managed_file',
      'name' => 'RooK managed file',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_file_lifetime',
      'entity_type' => 'node',
      'type' => 'list_string',
      'settings' => [
        'allowed_values' => [
          FileLifetime::PERSISTENT => 'Persistent',
          FileLifetime::SESSION => 'Session',
        ],
      ],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_file_lifetime',
      'entity_type' => 'node',
      'bundle' => 'rook_managed_file',
      'label' => 'Lifetime',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_managed_file',
      'entity_type' => 'node',
      'type' => 'file',
      'settings' => [
        'target_type' => 'file',
        'uri_scheme' => 'private',
      ],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_managed_file',
      'entity_type' => 'node',
      'bundle' => 'rook_managed_file',
      'label' => 'File',
      'settings' => [
        'handler' => 'default:file',
        'handler_settings' => [],
        'file_directory' => 'rook-tests',
        'file_extensions' => '',
        'max_filesize' => '',
      ],
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_file_description',
      'entity_type' => 'node',
      'type' => 'string_long',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_file_description',
      'entity_type' => 'node',
      'bundle' => 'rook_managed_file',
      'label' => 'Description',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_file_shared',
      'entity_type' => 'node',
      'type' => 'boolean',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_file_shared',
      'entity_type' => 'node',
      'bundle' => 'rook_managed_file',
      'label' => 'Shared',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_support_session_id',
      'entity_type' => 'node',
      'type' => 'integer',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_support_session_id',
      'entity_type' => 'node',
      'bundle' => 'rook_managed_file',
      'label' => 'Support session ID',
    ])->save();
  }

  private function ensureServiceRole(): void {
    $role = Role::create([
      'id' => 'service',
      'label' => 'Service',
    ]);
    $role->grantPermission('access rook file management');
    $role->grantPermission('access content');
    $role->save();
  }

  private function createServiceUser(): User {
    $suffix = bin2hex(random_bytes(4));
    $user = User::create([
      'name' => 'service_user_' . $suffix,
      'mail' => 'service_user_' . $suffix . '@example.com',
      'status' => 1,
    ]);
    $user->addRole('service');
    $user->save();

    return $user;
  }

  private function createManagedFileNode(User $owner, string $filename, string $lifetime, bool $shared, ?SupportSession $session = NULL): Node {
    $file = $this->container->get('file.repository')->writeData(
      'test file payload',
      'temporary://' . $filename,
    );
    $file->setPermanent();
    $file->save();

    $node = Node::create([
      'type' => 'rook_managed_file',
      'title' => $filename,
      'uid' => (int) $owner->id(),
      'status' => 1,
      'field_file_lifetime' => $lifetime,
      'field_managed_file' => ['target_id' => (int) $file->id()],
      'field_file_shared' => $shared ? 1 : 0,
      'field_support_session_id' => $session?->id(),
    ]);
    $node->save();

    return $node;
  }

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
        'REMOTE_ADDR' => '127.0.0.1',
      ],
      json_encode($payload, JSON_THROW_ON_ERROR),
    );

    $response = $this->handleRequest($request);
    self::assertInstanceOf(JsonResponse::class, $response);
    return $response;
  }

  private function handleRequest(Request $request) {
    return $this->container->get('http_kernel')->handle($request, HttpKernelInterface::MAIN_REQUEST, FALSE);
  }

  /**
   * @return array<string, mixed>
   */
  private function decodeJsonResponse(JsonResponse $response): array {
    $decoded = json_decode($response->getContent() ?: '{}', TRUE, 512, JSON_THROW_ON_ERROR);
    self::assertIsArray($decoded);
    return $decoded;
  }

}
