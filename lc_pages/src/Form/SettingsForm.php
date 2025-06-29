<?php

namespace Drupal\lc_pages\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lc_pages_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['lc_pages.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('lc_pages.settings');

    $form['additional_issues'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Accessibility - additional issues'),
      '#format' => $config->get('additional_issues_format') ?: 'basic_html',
      '#default_value' => $config->get('additional_issues'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Add validation logic here.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
   
    $this->config('lc_pages.settings')
      ->set('additional_issues', $form_state->getValue('additional_issues')['value'])
      ->set('additional_issues_format', $form_state->getValue('additional_issues')['format'])
      ->save();

    parent::submitForm($form, $form_state);
  }
}