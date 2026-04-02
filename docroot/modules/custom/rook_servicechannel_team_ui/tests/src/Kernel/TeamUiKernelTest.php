<?php

declare(strict_types=1);

namespace Drupal\Tests\rook_servicechannel_team_ui\Kernel;

use Drupal\Core\Render\HtmlResponse;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Covers team UI provisioning and page access.
 */
#[RunTestsInSeparateProcesses]
final class TeamUiKernelTest extends KernelTestBase {

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
    'rook_servicechannel_team_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system', 'rook_servicechannel_team_ui']);

    $this->container->get('module_handler')->loadInclude('rook_servicechannel_client_api', 'install');
    _rook_servicechannel_client_api_ensure_service_role();

    $this->container->get('module_handler')->loadInclude('rook_servicechannel_team_ui', 'install');
    _rook_servicechannel_team_ui_ensure_service_role_access();
  }

  public function testServiceRoleGetsTeamUiPermission(): void {
    $role = $this->container->get('entity_type.manager')->getStorage('user_role')->load('service');

    self::assertNotNull($role);
    self::assertTrue($role->hasPermission('access rook team ui'));
  }

  public function testServiceUserCanOpenTeamUiPage(): void {
    $this->config('rook_servicechannel_team_ui.settings')
      ->set('gateway_base_url', 'https://gateway.example.test')
      ->set('gateway_terminal_path', '/gateway/terminal')
      ->save();

    $service_user = $this->createUserWithRoles(['service']);
    $this->container->get('current_user')->setAccount($service_user);

    $response = $this->requestPage('/servicechannel/team');

    self::assertInstanceOf(HtmlResponse::class, $response);
    self::assertSame(200, $response->getStatusCode());
    self::assertStringContainsString('data-rook-team-ui', $response->getContent() ?: '');

    $attachments = $response->getAttachments();
    self::assertContains('rook_servicechannel_team_ui/app', $attachments['library'] ?? []);
    self::assertSame(
      'https://gateway.example.test',
      $attachments['drupalSettings']['rookServicechannelTeamUi']['gatewayBaseUrl'] ?? NULL,
    );
    self::assertSame(
      '/gateway/terminal',
      $attachments['drupalSettings']['rookServicechannelTeamUi']['gatewayTerminalPath'] ?? NULL,
    );
  }

  public function testMenuLinksExposeTeamUiAndSettingsPages(): void {
    $module_path = $this->container->get('extension.list.module')->getPath('rook_servicechannel_team_ui');
    $definitions = Yaml::parseFile($module_path . '/rook_servicechannel_team_ui.links.menu.yml');

    self::assertIsArray($definitions);

    $team_ui_link = $definitions['rook_servicechannel_team_ui.app'] ?? NULL;
    $settings_link = $definitions['rook_servicechannel_team_ui.settings'] ?? NULL;

    self::assertIsArray($team_ui_link);
    self::assertIsArray($settings_link);
    self::assertSame('main', $team_ui_link['menu_name'] ?? NULL);
    self::assertSame('rook_servicechannel_team_ui.app', $team_ui_link['route_name'] ?? NULL);
    self::assertSame('system.admin_config_system', $settings_link['parent'] ?? NULL);
    self::assertSame('rook_servicechannel_team_ui.settings', $settings_link['route_name'] ?? NULL);

    self::assertNotNull($this->container->get('router.route_provider')->getRouteByName('rook_servicechannel_team_ui.app'));
    self::assertNotNull($this->container->get('router.route_provider')->getRouteByName('rook_servicechannel_team_ui.settings'));
  }

  public function testRegularUserCannotOpenTeamUiPage(): void {
    $regular_user = $this->createUserWithRoles([]);
    $this->container->get('current_user')->setAccount($regular_user);

    $this->expectException(AccessDeniedHttpException::class);
    $this->requestPage('/servicechannel/team');
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
      'name' => 'team_ui_user_' . $suffix,
      'mail' => 'team_ui_user_' . $suffix . '@example.com',
      'status' => 1,
    ]);

    foreach ($roles as $role) {
      $user->addRole($role);
    }

    $user->save();

    return $user;
  }

  /**
   * Issues an HTTP request against Drupal's kernel.
   */
  private function requestPage(string $path): HtmlResponse {
    $request = Request::create($path, 'GET');

    $response = $this->container
      ->get('http_kernel')
      ->handle($request, HttpKernelInterface::MAIN_REQUEST, FALSE);

    self::assertInstanceOf(HtmlResponse::class, $response);

    return $response;
  }

}
