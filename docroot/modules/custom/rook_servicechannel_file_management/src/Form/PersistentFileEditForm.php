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

final class PersistentFileEditForm extends FormBase {

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
    return 'rook_servicechannel_file_management_edit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || !$this->accessManager->canEditMetadata($node, $this->currentUser())) {
      throw new AccessDeniedHttpException();
    }

    $form_state->set('node_id', (int) $node->id());
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
      '#default_value' => $node->label(),
    ];
    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#rows' => 4,
      '#default_value' => (string) $node->get('field_file_description')->value,
    ];
    $form['shared'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Globally share this file with other Service users'),
      '#default_value' => (bool) $node->get('field_file_shared')->value,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $node = $this->managedFileManager->loadManagedNode((int) $form_state->get('node_id'));
    if (!$node instanceof NodeInterface) {
      throw new \RuntimeException('The file could not be loaded.');
    }

    $this->managedFileManager->updatePersistentMetadata(
      $node,
      $this->currentUser(),
      (string) $form_state->getValue('title'),
      (string) $form_state->getValue('description'),
      (bool) $form_state->getValue('shared'),
    );

    $this->messenger()->addStatus($this->t('The file metadata was updated.'));
    $form_state->setRedirect('rook_servicechannel_file_management.overview');
  }

}
