<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_team_ui\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

final class TeamUiSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['rook_servicechannel_team_ui.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'rook_servicechannel_team_ui_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('rook_servicechannel_team_ui.settings');

    $form['gateway_base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Gateway base URL'),
      '#default_value' => $config->get('gateway_base_url'),
      '#description' => $this->t('Optional gateway origin such as https://gateway.example.test or ws://gateway.example.test. Leave empty to reuse the current site origin.'),
      '#required' => FALSE,
    ];

    $form['gateway_terminal_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Gateway terminal path'),
      '#default_value' => $config->get('gateway_terminal_path') ?: '/gateway/terminal',
      '#description' => $this->t('Absolute path for the WebSocket terminal handshake before the UI sends the `authorize` message.'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $gateway_base_url = trim((string) $form_state->getValue('gateway_base_url'));
    if ($gateway_base_url !== '') {
      $parts = parse_url($gateway_base_url);
      $scheme = is_array($parts) ? ($parts['scheme'] ?? '') : '';
      if (!in_array($scheme, ['http', 'https', 'ws', 'wss'], TRUE)) {
        $form_state->setErrorByName('gateway_base_url', $this->t('The gateway base URL must start with http://, https://, ws:// or wss://.'));
      }
    }

    $path = trim((string) $form_state->getValue('gateway_terminal_path'));
    if ($path === '' || $path[0] !== '/') {
      $form_state->setErrorByName('gateway_terminal_path', $this->t('The gateway terminal path must start with a slash.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->configFactory->getEditable('rook_servicechannel_team_ui.settings')
      ->set('gateway_base_url', trim((string) $form_state->getValue('gateway_base_url')))
      ->set('gateway_terminal_path', trim((string) $form_state->getValue('gateway_terminal_path')))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
