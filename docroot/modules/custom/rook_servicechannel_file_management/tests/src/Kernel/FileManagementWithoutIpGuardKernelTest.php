<?php

declare(strict_types=1);

namespace Drupal\Tests\rook_servicechannel_file_management\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\rook_servicechannel_file_management\Controller\FileDownloadController;
use Drupal\rook_servicechannel_file_management\FileLifetime;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Verifies anonymous downloads when the console IP guard module is disabled.
 */
#[RunTestsInSeparateProcesses]
final class FileManagementWithoutIpGuardKernelTest extends KernelTestBase {

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
    'rook_servicechannel_file_management',
  ];

  protected function setUp(): void {
    parent::setUp();

    $private_path = sys_get_temp_dir() . '/rook-file-management-private-no-guard';
    if (!is_dir($private_path)) {
      mkdir($private_path, 0777, TRUE);
    }
    $this->setSetting('file_private_path', $private_path);

    $this->installSchema('node', ['node_access']);
    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('node');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('rook_servicechannel_core', [
      'rook_support_audit_log',
      'rook_support_session_participant',
    ]);
    $this->installConfig(['system', 'user', 'node', 'file']);

    $this->createManagedFileBundle();
    $this->ensureServiceRole();
  }

  public function testAnonymousDownloadIsAllowedWithoutIpGuardModule(): void {
    $owner = $this->createServiceUser();
    $node = $this->createManagedFileNode($owner, 'anonymous-open.txt', FileLifetime::PERSISTENT, FALSE);
    $controller = FileDownloadController::create($this->container);

    $this->container->get('current_user')->setAccount(new AnonymousUserSession());
    $response = $controller->download(Request::create('/servicechannel/files/' . $node->id() . '/download', 'GET', [], [], [], [
      'REMOTE_ADDR' => '203.0.113.5',
    ]), $node);

    self::assertInstanceOf(BinaryFileResponse::class, $response);
    self::assertSame(200, $response->getStatusCode());
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

  private function createServiceUser(): User {
    $user = User::create([
      'name' => 'service-user-' . uniqid('', TRUE),
      'status' => 1,
    ]);
    $user->addRole('service');
    $user->save();
    return $user;
  }

  private function ensureServiceRole(): void {
    if (Role::load('service') === NULL) {
      Role::create([
        'id' => 'service',
        'label' => 'Service',
      ])->save();
    }
  }

  private function createManagedFileNode(User $owner, string $filename, string $lifetime, bool $shared): Node {
    $file = $this->container->get('file.repository')->writeData(
      'dummy file content for ' . $filename,
      'temporary://' . $filename,
    );
    $file->setPermanent();
    $file->save();

    $node = Node::create([
      'type' => 'rook_managed_file',
      'title' => $filename,
      'uid' => (int) $owner->id(),
      'status' => Node::PUBLISHED,
      'field_file_lifetime' => $lifetime,
      'field_managed_file' => ['target_id' => (int) $file->id()],
      'field_file_description' => '',
      'field_file_shared' => $shared ? 1 : 0,
    ]);
    $node->save();

    return $node;
  }

}
