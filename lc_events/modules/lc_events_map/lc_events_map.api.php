<?php

/**
 * @file
 * Hooks provided by the lc_events_map module.
 */

/**
 * Alters the Leaflet formatter settings of the event map.
 *
 * Called when lc_events_map is installed, before the map is added to the
 * default event display. $settings starts from the Leaflet formatter
 * defaults, with the values of lc_events_map.settings:formatter applied.
 * Afterwards the map is configured in Manage display, like any field.
 *
 * @param array $settings
 *   The leaflet_formatter_default settings.
 */
function hook_lc_events_map_formatter_settings_alter(array &$settings) {
  // A map defined by the site in hook_leaflet_map_info(), e.g. a vector
  // style, and a marker styled by the theme.
  $settings['leaflet_map'] = 'my_site_map';
  $settings['height'] = 280;
  $settings['gesture_handling'] = TRUE;
  $settings['icon']['iconType'] = 'html';
  $settings['icon']['html'] = '<span class="my-site-marker"></span>';
  $settings['icon']['iconSize'] = ['x' => 40, 'y' => 40];
  $settings['icon']['iconAnchor'] = ['x' => 20, 'y' => 20];
}
