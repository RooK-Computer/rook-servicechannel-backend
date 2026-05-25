<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_file_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rook_servicechannel_file_management\Service\ManagedFileManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class PersistentFileUploadForm extends FormBase {

  protected ManagedFileManager $managedFileManager;

  public function __construct(
    ManagedFileManager $managedFileManager,
  ) {
    $this->managedFileManager = $managedFileManager;
  }

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('rook_servicechannel_file_management.managed_file_manager'),
    );
  }

  public function getFormId(): string {
    return 'rook_servicechannel_file_management_upload_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
    ];
    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#rows' => 4,
    ];
    $form['shared'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Globally share this file with other Service users'),
    ];
    $form['managed_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('File'),
      '#field_name' => 'managed_file',
      '#required' => TRUE,
      '#upload_validators' => [
        'FileExtension' => [],
      ],
      '#upload_location' => sprintf('private://rook-servicechannel/persistent/%d', (int) $this->currentUser()->id()),
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $file_ids = array_map('intval', (array) $form_state->getValue('managed_file', []));
    if ($file_ids === []) {
      $form_state->setErrorByName('managed_file', $this->t('Upload a file first.'));
      return;
    }

    $this->managedFileManager->createPersistentFile(
      $this->currentUser(),
      (string) $form_state->getValue('title'),
      (string) $form_state->getValue('description'),
      (bool) $form_state->getValue('shared'),
      $file_ids[0],
    );

    $this->messenger()->addStatus($this->t('The file was uploaded.'));
    $form_state->setRedirect('rook_servicechannel_file_management.overview');
  }

}
