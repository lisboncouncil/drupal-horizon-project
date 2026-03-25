<?php

namespace Drupal\lc_event_registration\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for LC Event Registration.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['lc_event_registration.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'lc_event_registration_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('lc_event_registration.settings');

    $form['email_logo'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Email logo'),
      '#description' => $this->t('Upload a PNG or JPG logo for email headers. Recommended height: 60px. SVG is not supported by most email clients.'),
      '#upload_location' => 'public://lc_event_registration/',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg gif'],
        'file_validate_image_resolution' => ['0', '200x200'],
      ],
      '#default_value' => $config->get('email_logo_fid') ? [$config->get('email_logo_fid')] : NULL,
    ];

    $form['geoapify_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Geoapify API key'),
      '#description' => $this->t('API key for Geoapify static map images in confirmation emails. Leave empty to hide the map.'),
      '#default_value' => $config->get('geoapify_api_key') ?? '',
      '#maxlength' => 128,
    ];

    $form['consent_gdpr_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('GDPR consent text'),
      '#description' => $this->t('Text shown next to the GDPR consent checkbox on the registration form. HTML is allowed.'),
      '#default_value' => $config->get('consent_gdpr_text') ?? '',
      '#rows' => 4,
    ];

    $form['email_primary_color'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email primary colour'),
      '#description' => $this->t('Hex colour used for the confirmation email header and buttons (e.g. #0B4A89).'),
      '#default_value' => $config->get('email_primary_color') ?? '#0B4A89',
      '#maxlength' => 7,
    ];

    $form['email_secondary_color'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email secondary colour'),
      '#description' => $this->t('Hex colour used for the notification email header and accent elements (e.g. #5a9e56).'),
      '#default_value' => $config->get('email_secondary_color') ?? '#5a9e56',
      '#maxlength' => 7,
    ];

    $form['privacy_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Privacy policy URL'),
      '#description' => $this->t('Internal path to the privacy policy page (e.g. /privacy).'),
      '#default_value' => $config->get('privacy_url') ?? '/privacy',
      '#maxlength' => 255,
    ];

    $form['site_base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site base URL'),
      '#description' => $this->t('Override the base URL used in emails (e.g. https://example.eu). Leave empty to auto-detect from the current request.'),
      '#default_value' => $config->get('site_base_url') ?? '',
      '#maxlength' => 255,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $fid = NULL;
    $logo_value = $form_state->getValue('email_logo');
    if (!empty($logo_value)) {
      $fid = reset($logo_value);
      // Mark the file as permanent.
      /** @var \Drupal\file\FileInterface $file */
      $file = \Drupal::entityTypeManager()->getStorage('file')->load($fid);
      if ($file && $file->isTemporary()) {
        $file->setPermanent();
        $file->save();
        // Register file usage so it is not cleaned up.
        \Drupal::service('file.usage')->add($file, 'lc_event_registration', 'config', 1);
      }
    }

    $api_key = trim($form_state->getValue('geoapify_api_key') ?? '');

    $this->config('lc_event_registration.settings')
      ->set('email_logo_fid', $fid)
      ->set('geoapify_api_key', $api_key ?: NULL)
      ->set('consent_gdpr_text', trim($form_state->getValue('consent_gdpr_text') ?? ''))
      ->set('email_primary_color', trim($form_state->getValue('email_primary_color') ?? '#0B4A89'))
      ->set('email_secondary_color', trim($form_state->getValue('email_secondary_color') ?? '#5a9e56'))
      ->set('privacy_url', trim($form_state->getValue('privacy_url') ?? '/privacy'))
      ->set('site_base_url', trim($form_state->getValue('site_base_url') ?? ''))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
