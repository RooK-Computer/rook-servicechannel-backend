<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_file_management\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\node\NodeInterface;
use Drupal\rook_servicechannel_core\Entity\SupportSession;
use Drupal\rook_servicechannel_core\Service\AuditLogWriter;
use Drupal\rook_servicechannel_core\SupportSessionStatus;
use Drupal\rook_servicechannel_file_management\FileLifetime;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class ManagedFileManager {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    private readonly FileSystemInterface $fileSystem,
    private readonly FileRepositoryInterface $fileRepository,
    private readonly ManagedFileAccessManager $accessManager,
    private readonly AuditLogWriter $auditLogWriter,
  ) {}

  public function loadManagedNode(int $id): ?NodeInterface {
    $node = $this->entityTypeManager->getStorage('node')->load($id);
    return $node instanceof NodeInterface && $this->accessManager->isManagedFileNode($node) ? $node : NULL;
  }

  /**
   * @return \Drupal\node\NodeInterface[]
   */
  public function loadOwnedPersistentFiles(AccountInterface $account, ?string $search = NULL): array {
    return $this->filterBySearch(
      $this->loadByQuery([
        'uid' => (int) $account->id(),
        'field_file_lifetime' => FileLifetime::PERSISTENT,
      ]),
      $search,
    );
  }

  /**
   * @return \Drupal\node\NodeInterface[]
   */
  public function loadSharedPersistentFiles(AccountInterface $account, ?string $search = NULL): array {
    $files = array_filter(
      $this->loadByQuery([
        'field_file_lifetime' => FileLifetime::PERSISTENT,
        'field_file_shared' => 1,
      ]),
      fn (NodeInterface $node): bool => !$this->accessManager->isOwner($node, $account),
    );

    return $this->filterBySearch($files, $search);
  }

  /**
   * @return \Drupal\node\NodeInterface[]
   */
  public function loadSessionFiles(AccountInterface $account, SupportSession $session, ?string $search = NULL): array {
    return $this->filterBySearch(
      $this->loadByQuery([
        'uid' => (int) $account->id(),
        'field_file_lifetime' => FileLifetime::SESSION,
        'field_support_session_id' => (int) $session->id(),
      ]),
      $search,
    );
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  public function buildSidebarFileList(AccountInterface $account, ?SupportSession $session, ?string $search = NULL): array {
    $files = [
      ...$this->loadOwnedPersistentFiles($account, $search),
      ...$this->loadSharedPersistentFiles($account, $search),
    ];

    if ($session !== NULL) {
      $files = [
        ...$files,
        ...$this->loadSessionFiles($account, $session, $search),
      ];
    }

    usort($files, static fn (NodeInterface $left, NodeInterface $right): int => (int) $right->getCreatedTime() <=> (int) $left->getCreatedTime());

    return array_map(
      fn (NodeInterface $node): array => $this->buildFileRecord($node, $account),
      $files,
    );
  }

  public function createPersistentFile(AccountInterface $owner, string $title, string $description, bool $shared, int $fileId): NodeInterface {
    $file = $this->claimExistingFile($fileId);
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => ManagedFileAccessManager::BUNDLE,
      'title' => trim($title),
      'uid' => (int) $owner->id(),
      'status' => NodeInterface::PUBLISHED,
      'field_file_lifetime' => FileLifetime::PERSISTENT,
      'field_managed_file' => ['target_id' => (int) $file->id()],
      'field_file_description' => trim($description),
      'field_file_shared' => $shared ? 1 : 0,
    ]);
    $node->save();

    $this->auditLogWriter->write(
      'file_upload_succeeded',
      NULL,
      NULL,
      (int) $owner->id(),
      NULL,
      [
        'fileNodeId' => (int) $node->id(),
        'lifetime' => FileLifetime::PERSISTENT,
        'shared' => $shared,
      ],
    );

    return $node;
  }

  public function createSessionFile(AccountInterface $owner, SupportSession $session, UploadedFile $upload): NodeInterface {
    $file = $this->storeUploadedFile(
      $upload,
      sprintf('private://rook-servicechannel/session/%d/%d', (int) $session->id(), (int) $owner->id()),
    );

    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => ManagedFileAccessManager::BUNDLE,
      'title' => $file->getFilename(),
      'uid' => (int) $owner->id(),
      'status' => NodeInterface::PUBLISHED,
      'field_file_lifetime' => FileLifetime::SESSION,
      'field_managed_file' => ['target_id' => (int) $file->id()],
      'field_file_shared' => 0,
      'field_support_session_id' => (int) $session->id(),
    ]);
    $node->save();

    $this->auditLogWriter->write(
      'file_upload_succeeded',
      (int) $session->id(),
      NULL,
      (int) $owner->id(),
      NULL,
      [
        'fileNodeId' => (int) $node->id(),
        'lifetime' => FileLifetime::SESSION,
      ],
    );

    return $node;
  }

  public function updatePersistentMetadata(NodeInterface $node, AccountInterface $actor, string $title, string $description, bool $shared): NodeInterface {
    if (!$this->accessManager->canEditMetadata($node, $actor)) {
      $this->auditFailure('file_edit_failed', $node, $actor, ['reason' => 'forbidden']);
      throw new AccessDeniedHttpException('You may not edit this file.');
    }

    $previous_shared = (bool) $node->get('field_file_shared')->value;
    $node->setTitle(trim($title));
    $node->set('field_file_description', trim($description));
    $node->set('field_file_shared', $shared ? 1 : 0);
    $node->save();

    $this->auditLogWriter->write(
      $previous_shared !== $shared ? 'file_share_change_succeeded' : 'file_edit_succeeded',
      $this->getSupportSessionId($node),
      NULL,
      (int) $actor->id(),
      NULL,
      [
        'fileNodeId' => (int) $node->id(),
        'shared' => $shared,
      ],
    );

    return $node;
  }

  public function replacePersistentFile(NodeInterface $node, AccountInterface $actor, int $fileId): NodeInterface {
    if (!$this->accessManager->canReplace($node, $actor)) {
      $this->auditFailure('file_replace_failed', $node, $actor, ['reason' => 'forbidden']);
      throw new AccessDeniedHttpException('You may not replace this file.');
    }

    $previous_file = $this->getReferencedFile($node);
    $next_file = $this->claimExistingFile($fileId);
    $node->set('field_managed_file', ['target_id' => (int) $next_file->id()]);
    $node->save();

    if ($previous_file !== NULL && (int) $previous_file->id() !== (int) $next_file->id()) {
      $previous_file->delete();
    }

    $this->auditLogWriter->write(
      'file_replace_succeeded',
      $this->getSupportSessionId($node),
      NULL,
      (int) $actor->id(),
      NULL,
      ['fileNodeId' => (int) $node->id()],
    );

    return $node;
  }

  public function deleteManagedFile(NodeInterface $node, AccountInterface $actor, bool $isCleanup = FALSE): void {
    if (!$isCleanup && !$this->accessManager->canDelete($node, $actor)) {
      $this->auditFailure('file_delete_failed', $node, $actor, ['reason' => 'forbidden']);
      throw new AccessDeniedHttpException('You may not delete this file.');
    }

    $this->deleteManagedFileInternal($node, $actor, $isCleanup);
  }

  public function cleanupClosedSessionFiles(): int {
    $deleted = 0;

    foreach ($this->loadByQuery(['field_file_lifetime' => FileLifetime::SESSION]) as $node) {
      $session_id = $this->getSupportSessionId($node);
      if ($session_id === NULL) {
        $this->deleteManagedFileInternal($node, NULL, TRUE);
        $deleted++;
        continue;
      }

      $session = $this->entityTypeManager->getStorage('support_session')->load($session_id);
      if (!$session instanceof SupportSession || (string) $session->get('status')->value === SupportSessionStatus::CLOSED) {
        $this->deleteManagedFileInternal($node, NULL, TRUE);
        $deleted++;
      }
    }

    return $deleted;
  }

  /**
   * @return array<string, mixed>
   */
  public function buildFileRecord(NodeInterface $node, AccountInterface $account): array {
    $file = $this->getReferencedFile($node);
    $is_persistent = $this->accessManager->isPersistent($node);
    $is_owner = $this->accessManager->isOwner($node, $account);

    return [
      'id' => (int) $node->id(),
      'title' => $node->label(),
      'filename' => $file?->getFilename() ?? '',
      'description' => (string) $node->get('field_file_description')->value,
      'downloadPath' => Url::fromRoute('rook_servicechannel_file_management.download', ['node' => (int) $node->id()])->toString(),
      'lifetime' => (string) $node->get('field_file_lifetime')->value,
      'origin' => $is_persistent ? ($is_owner ? 'mine' : 'shared') : 'session',
      'shared' => (bool) $node->get('field_file_shared')->value,
      'createdAt' => (int) $node->getCreatedTime(),
      'changedAt' => (int) $node->getChangedTime(),
      'ownerId' => (int) $node->getOwnerId(),
      'canDelete' => $this->accessManager->canDelete($node, $account),
      'canEdit' => $this->accessManager->canEditMetadata($node, $account),
      'canReplace' => $this->accessManager->canReplace($node, $account),
      'sessionId' => $this->getSupportSessionId($node),
    ];
  }

  public function getReferencedFile(NodeInterface $node): ?FileInterface {
    $target = $node->get('field_managed_file')->entity;
    return $target instanceof FileInterface ? $target : NULL;
  }

  public function getSupportSessionId(NodeInterface $node): ?int {
    $value = $node->get('field_support_session_id')->value;
    return $value === NULL || $value === '' ? NULL : (int) $value;
  }

  public function auditFailure(string $eventType, ?NodeInterface $node, ?AccountInterface $actor, array $payload = []): void {
    $this->auditEvent($eventType, $node, $actor, $payload);
  }

  public function auditSuccess(string $eventType, ?NodeInterface $node, ?AccountInterface $actor, array $payload = []): void {
    $this->auditEvent($eventType, $node, $actor, $payload);
  }

  private function deleteManagedFileInternal(NodeInterface $node, ?AccountInterface $actor, bool $isCleanup): void {
    $file = $this->getReferencedFile($node);
    $node_id = (int) $node->id();
    $node->delete();

    if ($file !== NULL) {
      $file->delete();
    }

    $this->auditEvent(
      $isCleanup ? 'file_cleanup_succeeded' : 'file_delete_succeeded',
      $node,
      $actor,
      [
        'fileNodeId' => $node_id,
      ],
    );
  }

  private function auditEvent(string $eventType, ?NodeInterface $node, ?AccountInterface $actor, array $payload = []): void {
    $this->auditLogWriter->write(
      $eventType,
      $node !== NULL ? $this->getSupportSessionId($node) : NULL,
      NULL,
      $actor?->id() !== NULL ? (int) $actor->id() : NULL,
      NULL,
      $payload + [
        'fileNodeId' => $node?->id(),
      ],
    );
  }

  /**
   * @param array<string, int|string> $conditions
   *
   * @return \Drupal\node\NodeInterface[]
   */
  private function loadByQuery(array $conditions): array {
    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', ManagedFileAccessManager::BUNDLE)
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('created', 'DESC');

    foreach ($conditions as $field => $value) {
      $query->condition($field, $value);
    }

    $ids = $query->execute();
    if ($ids === []) {
      return [];
    }

    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($ids);
    return array_values(array_filter($nodes, fn ($node): bool => $node instanceof NodeInterface));
  }

  /**
   * @param \Drupal\node\NodeInterface[] $nodes
   *
   * @return \Drupal\node\NodeInterface[]
   */
  private function filterBySearch(array $nodes, ?string $search): array {
    $needle = mb_strtolower(trim((string) $search));
    if ($needle === '') {
      return $nodes;
    }

    return array_values(array_filter($nodes, function (NodeInterface $node) use ($needle): bool {
      $file = $this->getReferencedFile($node);
      $haystacks = [
        mb_strtolower($node->label() ?? ''),
        mb_strtolower((string) $node->get('field_file_description')->value),
        mb_strtolower($file?->getFilename() ?? ''),
      ];

      foreach ($haystacks as $haystack) {
        if ($haystack !== '' && str_contains($haystack, $needle)) {
          return TRUE;
        }
      }

      return FALSE;
    }));
  }

  private function claimExistingFile(int $fileId): FileInterface {
    $file = $this->entityTypeManager->getStorage('file')->load($fileId);
    if (!$file instanceof FileInterface) {
      throw new \RuntimeException('The uploaded file could not be loaded.');
    }

    $file->setPermanent();
    $file->save();

    return $file;
  }

  private function storeUploadedFile(UploadedFile $upload, string $directory): FileInterface {
    $target_directory = $directory;
    $this->fileSystem->prepareDirectory(
      $target_directory,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS,
    );

    $destination = sprintf('%s/%s', rtrim($target_directory, '/'), $upload->getClientOriginalName());
    $file = $this->fileRepository->writeData(
      (string) file_get_contents($upload->getRealPath()),
      $destination,
      FileExists::Rename,
    );
    $file->setPermanent();
    $file->save();

    return $file;
  }

}
