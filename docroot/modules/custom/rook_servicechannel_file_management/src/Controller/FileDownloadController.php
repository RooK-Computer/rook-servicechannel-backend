<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_file_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\rook_servicechannel_file_management\Service\ManagedFileAccessManager;
use Drupal\rook_servicechannel_file_management\Service\ManagedFileManager;
use Drupal\rook_servicechannel_file_management\Service\VpnTrustResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FileDownloadController extends ControllerBase {

  public function __construct(
    private readonly ManagedFileManager $managedFileManager,
    private readonly ManagedFileAccessManager $accessManager,
    private readonly VpnTrustResolver $vpnTrustResolver,
    private readonly AccountProxyInterface $currentAccount,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('rook_servicechannel_file_management.managed_file_manager'),
      $container->get('rook_servicechannel_file_management.access_manager'),
      $container->get('rook_servicechannel_file_management.vpn_trust_resolver'),
      $container->get('current_user'),
      $container->get('file_system'),
    );
  }

  public function download(Request $request, NodeInterface $node): BinaryFileResponse {
    if (!$this->accessManager->isManagedFileNode($node)) {
      throw new NotFoundHttpException();
    }

    $browser_allowed = $this->currentAccount->isAuthenticated() && $this->accessManager->canView($node, $this->currentAccount);
    $vpn_allowed = $this->vpnTrustResolver->isTrustedDownloadRequest($request);

    if (!$browser_allowed && !$vpn_allowed) {
      $this->managedFileManager->auditFailure('file_download_failed', $node, $this->currentAccount, ['reason' => 'forbidden']);
      throw new AccessDeniedHttpException('You may not download this file.');
    }

    $file = $this->managedFileManager->getReferencedFile($node);
    $uri = $file?->getFileUri();
    $path = $uri !== NULL ? $this->fileSystem->realpath($uri) : FALSE;

    if ($file === NULL || !$path || !is_file($path)) {
      $this->managedFileManager->auditFailure('file_download_failed', $node, $this->currentAccount, ['reason' => 'missing_file']);
      throw new NotFoundHttpException();
    }

    $this->managedFileManager->auditSuccess('file_download_succeeded', $node, $this->currentAccount, ['filename' => $file->getFilename()]);

    $response = new BinaryFileResponse($path);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file->getFilename());
    return $response;
  }

}
