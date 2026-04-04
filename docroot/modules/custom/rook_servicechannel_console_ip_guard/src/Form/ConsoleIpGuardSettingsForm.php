<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_console_ip_guard\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
final class ConsoleIpGuardSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['rook_servicechannel_console_ip_guard.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'rook_servicechannel_console_ip_guard_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $allowed_ips = $this->config('rook_servicechannel_console_ip_guard.settings')->get('allowed_ips') ?? [];

    $form['allowed_ips'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed source IPs or CIDR ranges'),
      '#default_value' => implode(PHP_EOL, is_array($allowed_ips) ? $allowed_ips : []),
      '#description' => $this->t('Enter one IPv4 or IPv6 address or CIDR range per line. Requests from other addresses are blocked for the console API.'),
      '#rows' => 8,
      '#required' => FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    foreach ($this->normalizeAllowedIps((string) $form_state->getValue('allowed_ips')) as $allowed_ip) {
      if (!$this->isValidAllowedIp($allowed_ip)) {
        $form_state->setErrorByName('allowed_ips', $this->t('The entry %entry is not a valid IP address or CIDR range.', ['%entry' => $allowed_ip]));
        break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->configFactory->getEditable('rook_servicechannel_console_ip_guard.settings')
      ->set('allowed_ips', $this->normalizeAllowedIps((string) $form_state->getValue('allowed_ips')))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Normalizes the textarea input into a config-safe allowlist.
   *
   * @return string[]
   *   Trimmed IP or CIDR entries without empty lines.
   */
  private function normalizeAllowedIps(string $value): array {
    $lines = preg_split('/\R/', $value) ?: [];

    return array_values(array_filter(array_map(static fn(string $line): string => trim($line), $lines)));
  }

  /**
   * Checks whether an entry is a valid IP address or CIDR range.
   */
  private function isValidAllowedIp(string $value): bool {
    if (filter_var($value, FILTER_VALIDATE_IP) !== FALSE) {
      return TRUE;
    }

    if (!str_contains($value, '/')) {
      return FALSE;
    }

    [$address, $prefix] = explode('/', $value, 2);
    if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== FALSE) {
      return ctype_digit($prefix) && (int) $prefix >= 0 && (int) $prefix <= 32;
    }

    if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== FALSE) {
      return ctype_digit($prefix) && (int) $prefix >= 0 && (int) $prefix <= 128;
    }

    return FALSE;
  }

}
