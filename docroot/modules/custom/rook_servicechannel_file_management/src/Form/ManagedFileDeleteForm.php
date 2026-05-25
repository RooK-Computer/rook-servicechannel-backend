<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_file_management\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\rook_servicechannel_file_management\Service\ManagedFileAccessManager;
use Drupal\rook_servicechannel_file_management\Service\ManagedFileManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class ManagedFileDeleteForm extends ConfirmFormBase {

  protected ManagedFileManager $managedFileManager;
  protected ManagedFileAccessManager $accessManager;

  public function __construct(
    ManagedFileManager $managedFileManager,
    ManagedFileAccessManager $accessManager,
  ) {
    $this->managedFileManager = $managedFileManager;
    $this->accessManager = $accessManager;
  }

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('rook_servicechannel_file_management.managed_file_manager'),
      $container->get('rook_servicechannel_file_management.access_manager'),
    );
  }

  public function getFormId(): string {
    return 'rook_servicechannel_file_management_delete_form';
  }

  public function getQuestion(): string {
    return (string) $this->t('Delete this file?');
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('rook_servicechannel_file_management.overview');
  }

  public function getConfirmText(): string {
    return (string) $this->t('Delete');
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || !$this->accessManager->canDelete($node, $this->currentUser())) {
      throw new AccessDeniedHttpException();
    }

    $form_state->set('node_id', (int) $node->id());
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $node = $this->managedFileManager->loadManagedNode((int) $form_state->get('node_id'));
    if (!$node instanceof NodeInterface) {
      throw new \RuntimeException('The file could not be loaded.');
    }

    $this->managedFileManager->deleteManagedFile($node, $this->currentUser());
    $this->messenger()->addStatus($this->t('The file was deleted.'));
    $form_state->setRedirect('rook_servicechannel_file_management.overview');
  }

}
