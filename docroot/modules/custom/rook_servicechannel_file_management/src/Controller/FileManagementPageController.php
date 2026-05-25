<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_file_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\rook_servicechannel_file_management\Service\ManagedFileManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

final class FileManagementPageController extends ControllerBase {

  public function __construct(
    private readonly ManagedFileManager $managedFileManager,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('rook_servicechannel_file_management.managed_file_manager'),
      $container->get('date.formatter'),
    );
  }

  public function overview(Request $request): array {
    $search = trim((string) $request->query->get('q', ''));
    $account = $this->currentUser();

    $build = [
      'actions' => [
        '#type' => 'container',
        'upload' => Link::fromTextAndUrl('Upload persistent file', Url::fromRoute('rook_servicechannel_file_management.upload'))->toRenderable(),
      ],
      'search' => [
        '#type' => 'inline_template',
        '#template' => '
          <form method="get" action="{{ action }}" class="rook-file-management-search">
            <label for="rook-file-search">Search</label>
            <input id="rook-file-search" type="search" name="q" value="{{ value }}" placeholder="Search title or filename">
            <button type="submit">Filter</button>
          </form>
        ',
        '#context' => [
          'action' => Url::fromRoute('rook_servicechannel_file_management.overview')->toString(),
          'value' => $search,
        ],
      ],
      'owned' => $this->buildTable(
        'My persistent files',
        $this->managedFileManager->loadOwnedPersistentFiles($account, $search),
        $account,
      ),
      'shared' => $this->buildTable(
        'Shared by other Service users',
        $this->managedFileManager->loadSharedPersistentFiles($account, $search),
        $account,
      ),
    ];

    return $build;
  }

  /**
   * @param \Drupal\node\NodeInterface[] $nodes
   */
  private function buildTable(string $title, array $nodes, $account): array {
    $rows = [];

    foreach ($nodes as $node) {
      $record = $this->managedFileManager->buildFileRecord($node, $account);
      $actions = [
        Link::fromTextAndUrl('Download', Url::fromRoute('rook_servicechannel_file_management.download', ['node' => $record['id']]))->toString(),
      ];

      if ($record['canEdit']) {
        $actions[] = Link::fromTextAndUrl('Edit', Url::fromRoute('rook_servicechannel_file_management.edit', ['node' => $record['id']]))->toString();
      }

      if ($record['canReplace']) {
        $actions[] = Link::fromTextAndUrl('Replace', Url::fromRoute('rook_servicechannel_file_management.replace', ['node' => $record['id']]))->toString();
      }

      if ($record['canDelete']) {
        $actions[] = Link::fromTextAndUrl('Delete', Url::fromRoute('rook_servicechannel_file_management.delete', ['node' => $record['id']]))->toString();
      }

      $rows[] = [
        'title' => $record['title'],
        'filename' => $record['filename'],
        'description' => $record['description'],
        'sharing' => $record['shared'] ? 'Shared' : 'Private',
        'updated' => $this->dateFormatter->format((int) $record['changedAt'], 'short'),
        'actions' => ['data' => ['#markup' => implode(' | ', $actions)]],
      ];
    }

    return [
      '#type' => 'details',
      '#title' => $title,
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#empty' => 'No files found.',
        '#header' => ['Title', 'Filename', 'Description', 'Sharing', 'Updated', 'Actions'],
        '#rows' => $rows,
      ],
    ];
  }

}
