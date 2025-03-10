<?php

namespace Drupal\lc_zenodo_publications\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SettingsForm extends ConfigFormBase {

  public function getFormId() {
    return 'lc_zenodo_publications_settings';
  }

  protected function getEditableConfigNames() {
    return ['lc_zenodo_publications.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('lc_zenodo_publications.settings');

    $form['size'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of publications'),
      '#default_value' => $config->get('size') ?: 10,
    ];

    $form['community'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Community'),
      '#default_value' => $config->get('community'),
    ];

    $form['update_interval'] = [
      '#type' => 'select',
      '#title' => $this->t('Update Interval'),
      '#options' => [
        'never' => $this->t('Never'),
        'daily' => $this->t('Daily'),
        'weekly' => $this->t('Weekly'),
        'monthly' => $this->t('Monthly'),
        'cron' => $this->t('Every time cron runs'),
      ],
      '#default_value' => $config->get('update_interval') ?: 'never',
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('lc_zenodo_publications.settings')
      ->set('size', $form_state->getValue('size'))
      ->set('community', $form_state->getValue('community'))
      ->set('update_interval', $form_state->getValue('update_interval'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
