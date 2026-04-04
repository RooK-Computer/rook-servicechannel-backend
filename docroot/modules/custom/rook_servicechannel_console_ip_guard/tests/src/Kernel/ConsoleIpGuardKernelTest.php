<?php

declare(strict_types=1);

namespace Drupal\Tests\rook_servicechannel_console_ip_guard\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies optional IP-based hardening for console API routes.
 */
#[RunTestsInSeparateProcesses]
final class ConsoleIpGuardKernelTest extends KernelTestBase {

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
    'rook_servicechannel_console_ip_guard',
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
    $this->installConfig(['rook_servicechannel_console_ip_guard']);
  }

  public function testDeniedIpReceivesForbiddenResponse(): void {
    $this->container
      ->get('config.factory')
      ->getEditable('rook_servicechannel_console_ip_guard.settings')
      ->set('allowed_ips', ['192.0.2.10'])
      ->save();

    $this->expectException(AccessDeniedHttpException::class);
    $this->post('/api/console/1/beginsession', [], '127.0.0.1');
  }

  public function testAllowedIpCanReachConsoleApi(): void {
    $this->container
      ->get('config.factory')
      ->getEditable('rook_servicechannel_console_ip_guard.settings')
      ->set('allowed_ips', ['127.0.0.1'])
      ->save();

    $response = $this->post('/api/console/1/beginsession', [], '127.0.0.1');
    self::assertSame(200, $response->getStatusCode());
  }

  public function testMenuLinksExposeSettingsPageInAdminAndMainMenus(): void {
    $module_path = $this->container->get('extension.list.module')->getPath('rook_servicechannel_console_ip_guard');
    $definitions = Yaml::parseFile($module_path . '/rook_servicechannel_console_ip_guard.links.menu.yml');

    self::assertIsArray($definitions);

    $main_link = $definitions['rook_servicechannel_console_ip_guard.main'] ?? NULL;
    $settings_link = $definitions['rook_servicechannel_console_ip_guard.settings'] ?? NULL;

    self::assertIsArray($main_link);
    self::assertIsArray($settings_link);
    self::assertSame('main', $main_link['menu_name'] ?? NULL);
    self::assertSame('rook_servicechannel_console_ip_guard.settings', $main_link['route_name'] ?? NULL);
    self::assertSame('system.admin_config_system', $settings_link['parent'] ?? NULL);
    self::assertSame('rook_servicechannel_console_ip_guard.settings', $settings_link['route_name'] ?? NULL);

    self::assertNotNull($this->container->get('router.route_provider')->getRouteByName('rook_servicechannel_console_ip_guard.settings'));
  }

  /**
   * Sends a JSON request into the Drupal kernel.
   */
  private function post(string $path, array $payload, string $ipAddress) {
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

    return $this->container
      ->get('http_kernel')
      ->handle($request, HttpKernelInterface::MAIN_REQUEST, FALSE);
  }

}
