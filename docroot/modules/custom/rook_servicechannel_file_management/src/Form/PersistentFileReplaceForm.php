<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_file_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\rook_servicechannel_file_management\Service\ManagedFileAccessManager;
use Drupal\rook_servicechannel_file_management\Service\ManagedFileManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class PersistentFileReplaceForm extends FormBase {

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
    return 'rook_servicechannel_file_management_replace_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || !$this->accessManager->canReplace($node, $this->currentUser())) {
      throw new AccessDeniedHttpException();
    }

    $form_state->set('node_id', (int) $node->id());
    $form['managed_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Replacement file'),
      '#field_name' => 'managed_file',
      '#required' => TRUE,
      '#upload_validators' => [
        'FileExtension' => [],
      ],
      '#upload_location' => sprintf('private://rook-servicechannel/persistent/%d', (int) $this->currentUser()->id()),
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Replace'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $node = $this->managedFileManager->loadManagedNode((int) $form_state->get('node_id'));
    if (!$node instanceof NodeInterface) {
      throw new \RuntimeException('The file could not be loaded.');
    }

    $file_ids = array_map('intval', (array) $form_state->getValue('managed_file', []));
    if ($file_ids === []) {
      $form_state->setErrorByName('managed_file', $this->t('Upload a replacement file first.'));
      return;
    }

    $this->managedFileManager->replacePersistentFile($node, $this->currentUser(), $file_ids[0]);
    $this->messenger()->addStatus($this->t('The file was replaced.'));
    $form_state->setRedirect('rook_servicechannel_file_management.overview');
  }

}
