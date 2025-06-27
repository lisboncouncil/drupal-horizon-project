<?php

namespace Drupal\lc_social\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;


/**
 * Provides Social Links Block.
 *
 * @Block(
 *   id = "lc_social_block",
 *   admin_label = @Translation("Social Links"),
 *   category = @Translation("LC")
 * )
 */
class SocialLinksBlock extends BlockBase {


  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'email' => '',
      'twitter' => '',
      'linkedin' => '',
      'youtube' => '',
      'mastodon' => '',
      'facebook' => '',
      'instagram' => ''
    ];
  }


  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    
    $data = [
      'email' => $config['email'] ?? '',
      'twitter' => $config['twitter'] ?? '',
      'linkedin' => $config['linkedin'] ?? '',
      'youtube' => $config['youtube'] ?? '',
      'mastodon' => $config['mastodon'] ?? '',
      'facebook' => $config['facebook'] ?? '',
      'instagram' => $config['instagram'] ?? '',
    ];

    return [
      '#theme' => 'social_links',
      '#data' => $data,
      '#attached' => array(
        'library' => array(
          'lc_social/social-links',
        )
      )
    ];
  }

  /**
  * {@inheritdoc}
  */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['email'] = [
      '#type' => 'textfield',
      '#title' => "Email",
      '#default_value' => $this->configuration['email'],
    ];

    $form['twitter'] = [
      '#type' => 'textfield',
      '#title' => "Twitter",
      '#default_value' => $this->configuration['twitter'],
    ];

    $form['linkedin'] = [
      '#type' => 'textfield',
      '#title' => "Linkedin",
      '#default_value' => $this->configuration['linkedin'],
    ];

    $form['youtube'] = [
      '#type' => 'textfield',
      '#title' => "Youtube",
      '#default_value' => $this->configuration['youtube'],
    ];

    $form['mastodon'] = [
      '#type' => 'textfield',
      '#title' => "Mastodon",
      '#default_value' => $this->configuration['mastodon'],
    ];

    $form['facebook'] = [
      '#type' => 'textfield',
      '#title' => "Facebook",
      '#default_value' => $this->configuration['facebook'],
    ];

    $form['instagram'] = [
      '#type' => 'textfield',
      '#title' => "Instagram",
      '#default_value' => $this->configuration['instagram'],
    ];

    return $form;
  }
  

    /**
  * {@inheritdoc}
  */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->configuration['email'] = $values['email'];
    $this->configuration['twitter'] = $values['twitter'];
    $this->configuration['linkedin'] = $values['linkedin'];
    $this->configuration['youtube'] = $values['youtube'];
    $this->configuration['mastodon'] = $values['mastodon'];
    $this->configuration['facebook'] = $values['facebook'];
    $this->configuration['instagram'] = $values['instagram'];
  }


}
