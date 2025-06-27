<?php

namespace Drupal\lc_pages\Controller;
use Drupal\Core\Controller\ControllerBase;

/**
 * Provides route responses for the Example module.
 */
class LCPagesController extends ControllerBase {

  /**
   * Returns a simple page.
   *
   * @return array
   *   A simple renderable array.
   */
  public function joinTheProjectPage() {
           
    return [
      '#theme' => 'hpage_join_the_project',
    ];
  }


  /**
   * Returns a simple page.
   *
   * @return array
   *   A simple renderable array.
   */
  public function privacyPage() {
      
    $config = \Drupal::config('lc_pages.settings');
      
    return [
      '#theme' => 'hpage_privacy',
      '#data_owner' => $config->get('data_owner'),
      '#data_owner_email' => $config->get('data_owner_email'),
      '#do_not_track' => $config->get('do_not_track'),
      '#document_timestamp' => date('j M Y', filemtime(__FILE__)),
      '#site_name' => \Drupal::config('system.site')->get('name'),
    ];
  }
  
  /**
   * Returns a simple page.
   *
   * @return array
   *   A simple renderable array.
   */
  public function cookiesPage() {
      
    $config = \Drupal::config('lc_pages.settings');
      
    return [
      '#theme' => 'hpage_cookies',
      '#data_owner' => $config->get('data_owner'),
      '#data_owner_email' => $config->get('data_owner_email'),
      '#do_not_track' => $config->get('do_not_track'),
      '#document_timestamp' => date('j M Y', filemtime(__FILE__)),
      '#site_name' => \Drupal::config('system.site')->get('name'),
    ];
  }
  
  /**
   * Returns a simple page.
   *
   * @return array
   *   A simple renderable array.
   */
  public function accessibilityStatementPage() {
      
    $config = \Drupal::config('lc_pages.settings');
      
    return [
      '#theme' => 'hpage_accessibility_statement',
      '#data_owner' => $config->get('data_owner'),
      '#data_owner_email' => $config->get('data_owner_email'),
      '#do_not_track' => $config->get('do_not_track'),
      '#document_timestamp' => date('j M Y', filemtime(__FILE__)),
      '#site_name' => \Drupal::config('system.site')->get('name'),
      '#email_accessibility' => $config->get('email_accessibility'),
      '#accessibility_date' => $config->get('accessibility_date'),
      '#accessibility_date_next' => $config->get('accessibility_date_next'),
    ];
  }

}