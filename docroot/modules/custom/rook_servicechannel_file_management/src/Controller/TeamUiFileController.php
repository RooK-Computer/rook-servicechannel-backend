<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_file_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\rook_servicechannel_core\Entity\SupportSession;
use Drupal\rook_servicechannel_core\Service\SupportSessionManager;
use Drupal\rook_servicechannel_core\Service\SupportSessionParticipantManager;
use Drupal\rook_servicechannel_file_management\Service\ManagedFileManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class TeamUiFileController extends ControllerBase {

  public function __construct(
    private readonly ManagedFileManager $managedFileManager,
    private readonly SupportSessionManager $supportSessionManager,
    private readonly SupportSessionParticipantManager $participantManager,
    private readonly EntityTypeManagerInterface $entityManager,
    private readonly AccountProxyInterface $currentAccount,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('rook_servicechannel_file_management.managed_file_manager'),
      $container->get('rook_servicechannel_core.support_session_manager'),
      $container->get('rook_servicechannel_core.support_session_participant_manager'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  public function list(Request $request): JsonResponse {
    $payload = $this->decodeJson($request);
    $session = $this->resolveOptionalSession($payload['pin'] ?? NULL);

    return new JsonResponse([
      'files' => $this->managedFileManager->buildSidebarFileList(
      $this->currentAccount,
        $session,
        is_string($payload['search'] ?? NULL) ? $payload['search'] : NULL,
      ),
    ]);
  }

  public function upload(Request $request): JsonResponse {
    try {
      $pin = trim((string) $request->request->get('pin', ''));
      if ($pin === '') {
        throw new \InvalidArgumentException('The upload request requires a pin field.');
      }

      $session = $this->requireCoupledSession($pin);
      $upload = $request->files->get('file');
      if (!$upload instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
        throw new \InvalidArgumentException('The upload request requires a file field.');
      }

      $node = $this->managedFileManager->createSessionFile($this->currentAccount, $session, $upload);
      return new JsonResponse([
        'file' => $this->managedFileManager->buildFileRecord($node, $this->currentAccount),
      ]);
    }
    catch (\Throwable $throwable) {
      return new JsonResponse([
        'message' => $throwable->getMessage(),
      ], 500);
    }
  }

  public function delete(Request $request): JsonResponse {
    try {
      $payload = $this->decodeJson($request);
      $file_id = (int) ($payload['fileId'] ?? 0);
      $node = $file_id > 0 ? $this->managedFileManager->loadManagedNode($file_id) : NULL;

      if (!$node instanceof NodeInterface) {
        throw new \InvalidArgumentException('The requested file was not found.');
      }

      $this->managedFileManager->deleteManagedFile($node, $this->currentAccount);
      return new JsonResponse(['status' => 'deleted']);
    }
    catch (\Throwable $throwable) {
      return new JsonResponse([
        'message' => $throwable->getMessage(),
      ], 500);
    }
  }

  /**
   * @return array<string, mixed>
   */
  private function decodeJson(Request $request): array {
    $content = trim($request->getContent());
    if ($content === '') {
      return [];
    }

    $payload = json_decode($content, TRUE, 512, JSON_THROW_ON_ERROR);
    return is_array($payload) ? $payload : [];
  }

  private function resolveOptionalSession(mixed $pin): ?SupportSession {
    if (!is_string($pin) || trim($pin) === '') {
      return NULL;
    }

    try {
      return $this->requireCoupledSession(trim($pin));
    }
    catch (\Throwable) {
      return NULL;
    }
  }

  private function requireCoupledSession(string $pin): SupportSession {
    $session = $this->supportSessionManager->loadLatestSessionByPin($pin);
    $account = $this->entityManager->getStorage('user')->load($this->currentAccount->id());

    if (!$session instanceof SupportSession || !$account || $this->supportSessionManager->isClosed($session) || !$this->participantManager->isCoupled($session, $account)) {
      throw new AccessDeniedHttpException('The current Service user is not coupled to that session.');
    }

    return $session;
  }

}
