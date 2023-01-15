<?php
namespace Drupal\lc_hcommon\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Provides route responses for the Example module.
 */
class LCHCommonController extends ControllerBase {

  /**
   * Returns a simple page.
   *
   * @return array
   *   A simple renderable array.
   */
  public function privacyPage() {
      
    $config = \Drupal::config('lc_hcommon.settings');
      
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
      
    $config = \Drupal::config('lc_hcommon.settings');
      
    return [
      '#theme' => 'hpage_cookies',
      '#data_owner' => $config->get('data_owner'),
      '#data_owner_email' => $config->get('data_owner_email'),
      '#do_not_track' => $config->get('do_not_track'),
      '#document_timestamp' => date('j M Y', filemtime(__FILE__)),
      '#site_name' => \Drupal::config('system.site')->get('name'),
    ];
  }

}