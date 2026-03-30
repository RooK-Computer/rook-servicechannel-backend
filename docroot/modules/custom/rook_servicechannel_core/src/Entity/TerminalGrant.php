<?php

declare(strict_types=1);

namespace Drupal\rook_servicechannel_core\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\rook_servicechannel_core\TerminalGrantStatus;

/**
 * Defines the terminal grant entity.
 */
#[ContentEntityType(
  id: 'terminal_grant',
  label: new TranslatableMarkup('Terminal grant'),
  label_collection: new TranslatableMarkup('Terminal grants'),
  label_singular: new TranslatableMarkup('terminal grant'),
  label_plural: new TranslatableMarkup('terminal grants'),
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'token_hash',
  ],
  handlers: [
    'access' => EntityAccessControlHandler::class,
    'list_builder' => EntityListBuilder::class,
  ],
  admin_permission: 'administer rook service channel',
  base_table: 'rook_terminal_grant',
)]
class TerminalGrant extends ContentEntityBase {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values): void {
    parent::preCreate($storage, $values);
    $values += [
      'status' => TerminalGrantStatus::ISSUED,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['token_hash'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Token hash'))
      ->setDescription(new TranslatableMarkup('Persistent hash of the opaque terminal token.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 128);

    $fields['support_session_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Support session'))
      ->setDescription(new TranslatableMarkup('Support session this terminal grant belongs to.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'support_session')
      ->setSetting('handler', 'default');

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Service user'))
      ->setDescription(new TranslatableMarkup('Drupal user this terminal grant was issued for.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default');

    $fields['console_ip_address'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Console IP address'))
      ->setDescription(new TranslatableMarkup('Console IP address bound to the grant.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 64);

    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Status'))
      ->setDescription(new TranslatableMarkup('Current terminal grant state.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 32)
      ->addPropertyConstraints('value', [
        'Choice' => [
          'choices' => TerminalGrantStatus::all(),
        ],
      ]);

    $fields['issued_at'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Issued at'))
      ->setDescription(new TranslatableMarkup('Unix timestamp when the grant was issued.'));

    $fields['redeemed_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Redeemed at'))
      ->setDescription(new TranslatableMarkup('Unix timestamp when the grant was first redeemed.'))
      ->setRequired(FALSE);

    $fields['last_used_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Last used at'))
      ->setDescription(new TranslatableMarkup('Unix timestamp when the grant was last seen in use.'))
      ->setRequired(FALSE);

    $fields['expires_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Expires at'))
      ->setDescription(new TranslatableMarkup('Unix timestamp when the grant expires.'))
      ->setRequired(TRUE);

    $fields['reconnect_valid_until'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Reconnect valid until'))
      ->setDescription(new TranslatableMarkup('Unix timestamp until which reconnect reuse is allowed.'))
      ->setRequired(FALSE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Changed'))
      ->setDescription(new TranslatableMarkup('Unix timestamp of the last grant update.'));

    return $fields;
  }

}
