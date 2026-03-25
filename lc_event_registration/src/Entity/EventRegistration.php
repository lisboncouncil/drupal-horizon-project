<?php

namespace Drupal\lc_event_registration\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the EventRegistration entity.
 *
 * @ContentEntityType(
 *   id = "event_registration",
 *   label = @Translation("Event registration"),
 *   base_table = "event_registration",
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "id"
 *   },
 *   admin_permission = "administer event_registration"
 * )
 */
class EventRegistration extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['event_nid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Event'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'node')
      ->setSetting('handler_settings', ['target_bundles' => ['event' => 'event']]);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Registrant'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'user');

    $fields['first_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('First name'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['last_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Last name'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['email'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email'))
      ->setRequired(TRUE);

    $fields['nationality'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Nationality'))
      ->setRequired(FALSE)
      ->setSetting('max_length', 255);

    $fields['date_of_birth'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Date of birth'))
      ->setRequired(FALSE)
      ->setSetting('max_length', 10);

    $fields['document_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Document type'))
      ->setRequired(FALSE)
      ->setSettings([
        'allowed_values' => [
          'passport' => 'Passport',
          'identity_card' => 'Identity Card',
        ],
      ]);

    $fields['document_number'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Document number'))
      ->setRequired(FALSE)
      ->setSetting('max_length', 100);

    $fields['document_expiry_date'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Document expiry date'))
      ->setRequired(FALSE)
      ->setSetting('max_length', 10);

    $fields['organisation'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Organisation'))
      ->setRequired(FALSE)
      ->setSetting('max_length', 255);

    $fields['mobile_number'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Mobile number'))
      ->setRequired(FALSE)
      ->setSetting('max_length', 20);

    $fields['participation_mode'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Participation mode'))
      ->setRequired(FALSE)
      ->setSettings([
        'allowed_values' => [
          'in_person' => 'In Person',
          'online' => 'Online',
        ],
      ]);

    $fields['lunch_attendance'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Lunch attendance'))
      ->setRequired(FALSE)
      ->setSettings([
        'allowed_values' => [
          'yes' => 'Yes',
          'no' => 'No',
        ],
      ]);

    $fields['photo_consent'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Consent to photos and videos'))
      ->setRequired(FALSE)
      ->setSettings([
        'allowed_values' => [
          'agree' => 'I agree to be photographed and recorded',
          'disagree' => 'I do NOT agree to be photographed and recorded',
        ],
      ]);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setRequired(TRUE)
      ->setDefaultValue('confirmed')
      ->setSettings([
        'allowed_values' => [
          'confirmed' => 'Confirmed',
          'cancelled' => 'Cancelled',
        ],
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'));

    return $fields;
  }

}
