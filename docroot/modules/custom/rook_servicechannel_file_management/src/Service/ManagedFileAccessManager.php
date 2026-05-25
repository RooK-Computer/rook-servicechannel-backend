<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_file_management\Service;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\rook_servicechannel_file_management\FileLifetime;

final class ManagedFileAccessManager {

  public const BUNDLE = 'rook_managed_file';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function isManagedFileNode(NodeInterface $node): bool {
    return $node->bundle() === self::BUNDLE;
  }

  public function isPersistent(NodeInterface $node): bool {
    return $this->isManagedFileNode($node)
      && (string) $node->get('field_file_lifetime')->value === FileLifetime::PERSISTENT;
  }

  public function isSession(NodeInterface $node): bool {
    return $this->isManagedFileNode($node)
      && (string) $node->get('field_file_lifetime')->value === FileLifetime::SESSION;
  }

  public function isOwner(NodeInterface $node, AccountInterface $account): bool {
    return !$account->isAnonymous() && (int) $node->getOwnerId() === (int) $account->id();
  }

  public function isShared(NodeInterface $node): bool {
    return (bool) $node->get('field_file_shared')->value;
  }

  public function isServiceAccount(AccountInterface $account): bool {
    return $account->hasPermission('administer nodes')
      || $account->hasPermission('access rook file management')
      || in_array('service', $account->getRoles(), TRUE);
  }

  public function canView(NodeInterface $node, AccountInterface $account): bool {
    if (!$this->isManagedFileNode($node)) {
      return FALSE;
    }

    if ($account->hasPermission('administer nodes')) {
      return TRUE;
    }

    if (!$this->isServiceAccount($account)) {
      return FALSE;
    }

    if ($this->isPersistent($node)) {
      return $this->isOwner($node, $account) || $this->isShared($node);
    }

    if ($this->isSession($node)) {
      return $this->isOwner($node, $account);
    }

    return FALSE;
  }

  public function canEditMetadata(NodeInterface $node, AccountInterface $account): bool {
    return $this->isPersistent($node) && ($account->hasPermission('administer nodes') || $this->isOwner($node, $account));
  }

  public function canReplace(NodeInterface $node, AccountInterface $account): bool {
    return $this->canEditMetadata($node, $account);
  }

  public function canDelete(NodeInterface $node, AccountInterface $account): bool {
    if ($account->hasPermission('administer nodes')) {
      return $this->isManagedFileNode($node);
    }

    if ($this->isPersistent($node)) {
      return $this->isOwner($node, $account);
    }

    if ($this->isSession($node)) {
      return $this->isOwner($node, $account);
    }

    return FALSE;
  }

  public function getNodeAccessResult(NodeInterface $node, string $op, AccountInterface $account): AccessResult {
    if (!$this->isManagedFileNode($node)) {
      return AccessResult::neutral();
    }

    return match ($op) {
      'view' => AccessResult::allowedIf($this->canView($node, $account))->cachePerUser(),
      'update' => AccessResult::allowedIf($this->canEditMetadata($node, $account))->cachePerUser(),
      'delete' => AccessResult::allowedIf($this->canDelete($node, $account))->cachePerUser(),
      default => AccessResult::neutral(),
    };
  }

}
