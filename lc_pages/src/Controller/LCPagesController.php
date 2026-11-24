<?php

namespace Drupal\lc_pages\Controller;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;


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
      '#document_timestamp' => \Drupal::service('date.formatter')->format(filemtime(__FILE__), 'custom', 'j M Y'),
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
      '#document_timestamp' => \Drupal::service('date.formatter')->format(filemtime(__FILE__), 'custom', 'j M Y'),
      '#site_name' => \Drupal::config('system.site')->get('name'),
      '#partners_url' => $this->partners_url(),
      '#partners_link' => $this->partners_link(),
    ];
  }

  /**
   * URL of the partners list page (lc_section_partners, views partners).
   *
   * Built from the route, so it follows the path set on the view.
   *
   * @return string
   *   Empty when lc_section_partners is not installed.
   */
  protected function partners_url(): string {
    $route_name = 'view.partners.page_1';
    $routes = \Drupal::service('router.route_provider')->getRoutesByNames([$route_name]);

    return $routes ? Url::fromRoute($route_name)->toString() : '';
  }

  /**
   * "Consortium Partners", linked to the partners list when there is one.
   *
   * A render array, so that it can go into a {% trans %} placeholder.
   */
  protected function partners_link(): array {
    $text = $this->t('Consortium Partners');

    return $this->partners_url() ? Link::createFromRoute($text, 'view.partners.page_1')->toRenderable() : ['#markup' => $text];
  }

  /**
   * The date of a settings value ("27 June 2025") in the page language.
   *
   * Values that are not a date are returned as they are.
   */
  protected function localized_date(?string $date): ?string {
    $time = $date ? strtotime($date) : FALSE;

    return $time ? \Drupal::service('date.formatter')->format($time, 'custom', 'j F Y') : $date;
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
      '#site_name' => \Drupal::config('system.site')->get('name'),
      '#email_accessibility' => $config->get('email_accessibility'),
      '#accessibility_date' => $this->localized_date($config->get('accessibility_date')),
      '#accessibility_date_next' => $this->localized_date($config->get('accessibility_date_next')),
      '#additional_issues' => $config->get('additional_issues'),
    ];
  }

}