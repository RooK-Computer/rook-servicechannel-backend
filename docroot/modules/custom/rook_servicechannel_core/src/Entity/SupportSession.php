<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_core\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\rook_servicechannel_core\SupportSessionStatus;

/**
 * Defines the support session entity.
 */
#[ContentEntityType(
  id: 'support_session',
  label: new TranslatableMarkup('Support session'),
  label_collection: new TranslatableMarkup('Support sessions'),
  label_singular: new TranslatableMarkup('support session'),
  label_plural: new TranslatableMarkup('support sessions'),
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'pin',
  ],
  handlers: [
    'access' => EntityAccessControlHandler::class,
    'list_builder' => EntityListBuilder::class,
  ],
  admin_permission: 'administer rook service channel',
  base_table: 'rook_support_session',
)]
class SupportSession extends ContentEntityBase {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values): void {
    parent::preCreate($storage, $values);
    $values += [
      'status' => SupportSessionStatus::OPEN,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['pin'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('PIN'))
      ->setDescription(new TranslatableMarkup('Short-lived 4-digit PIN for coupling a support session.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 16);

    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Status'))
      ->setDescription(new TranslatableMarkup('Current support session state.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 32)
      ->addPropertyConstraints('value', [
        'Choice' => [
          'choices' => SupportSessionStatus::all(),
        ],
      ]);

    $fields['console_ip_address'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Console IP address'))
      ->setDescription(new TranslatableMarkup('Current console IP address seen by the backend.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 64);

    $fields['vpn_peer_ip_address'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('VPN peer IP address'))
      ->setDescription(new TranslatableMarkup('VPN peer address if it differs from the effective console IP address.'))
      ->setRequired(FALSE)
      ->setSetting('max_length', 64);

    $fields['close_reason'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Close reason'))
      ->setDescription(new TranslatableMarkup('Why the support session was closed.'))
      ->setRequired(FALSE)
      ->setSetting('max_length', 64);

    $fields['active_terminal_count'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Active terminal count'))
      ->setDescription(new TranslatableMarkup('Number of currently active terminals bound to this session.'))
      ->setRequired(TRUE)
      ->setDefaultValue(0);

    $fields['started_at'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Started at'))
      ->setDescription(new TranslatableMarkup('Unix timestamp of the session start.'));

    $fields['last_heartbeat_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Last heartbeat at'))
      ->setDescription(new TranslatableMarkup('Unix timestamp of the last accepted heartbeat.'))
      ->setRequired(FALSE);

    $fields['claimed_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Claimed at'))
      ->setDescription(new TranslatableMarkup('Unix timestamp when the session was first coupled to a service user.'))
      ->setRequired(FALSE);

    $fields['expires_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Expires at'))
      ->setDescription(new TranslatableMarkup('Unix timestamp when the session should expire if nothing refreshes it.'))
      ->setRequired(FALSE);

    $fields['closed_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Closed at'))
      ->setDescription(new TranslatableMarkup('Unix timestamp when the session was closed.'))
      ->setRequired(FALSE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Changed'))
      ->setDescription(new TranslatableMarkup('Unix timestamp of the last session update.'));

    return $fields;
  }

}
