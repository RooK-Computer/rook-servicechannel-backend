<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_team_ui\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class TeamUiController implements ContainerInjectionInterface {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('config.factory'),
    );
  }

  /**
   * Builds the Team UI page.
   */
  public function build(): array {
    $config = $this->configFactory->get('rook_servicechannel_team_ui.settings');

    return [
      '#theme' => 'rook_servicechannel_team_ui_app',
      '#attached' => [
        'library' => [
          'rook_servicechannel_team_ui/app',
        ],
        'drupalSettings' => [
          'rookServicechannelTeamUi' => [
            'pinLookupUrl' => Url::fromRoute('rook_servicechannel_client_api.pinlookup')->toString(),
            'sessionStatusUrl' => Url::fromRoute('rook_servicechannel_client_api.sessionstatus')->toString(),
            'requestShellUrl' => Url::fromRoute('rook_servicechannel_client_api.requestshell')->toString(),
            'gatewayBaseUrl' => trim((string) $config->get('gateway_base_url')),
            'gatewayTerminalPath' => trim((string) ($config->get('gateway_terminal_path') ?: '/gateway/terminal')),
          ],
        ],
      ],
      '#cache' => [
        'contexts' => ['user.roles'],
        'max-age' => 0,
      ],
    ];
  }

}
