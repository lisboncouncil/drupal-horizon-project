<?php

/**
 * @file
 * Post update functions for the lc_events module.
 */

/**
 * Events list: past events newest first, sentence case titles, calendar link.
 *
 * Only values still equal to the ones shipped before are changed, so that a
 * customised view is kept:
 * - the past events (page_2, block_2) are sorted by date, newest first, when
 *   they use the default sort;
 * - "Upcoming Events", "Past Events" and "Events List" become sentence case;
 * - the link to /events-calendar under the upcoming events is removed when
 *   calendar_view is not installed (the calendar page does not exist).
 */
function lc_events_post_update_events_list_defaults() {
  $view = \Drupal::entityTypeManager()->getStorage('view')->load('events_list');
  if (!$view) {
    return t('The events_list view does not exist: nothing to update.');
  }
  $displays = $view->get('display');

  $titles = [
    'Events List' => 'Events list',
    'Upcoming Events' => 'Upcoming events',
    'Past Events' => 'Past events',
  ];
  foreach ($displays as $id => $display) {
    $title = $display['display_options']['title'] ?? NULL;
    if ($title !== NULL && isset($titles[$title])) {
      $displays[$id]['display_options']['title'] = $titles[$title];
    }
    if (($display['display_options']['block_description'] ?? NULL) === 'Past Events') {
      $displays[$id]['display_options']['block_description'] = 'Past events';
    }
  }

  $default_sorts = $displays['default']['display_options']['sorts'] ?? [];
  if (($default_sorts['field_event_date_value']['order'] ?? NULL) === 'ASC') {
    $past_sorts = $default_sorts;
    $past_sorts['field_event_date_value']['order'] = 'DESC';
    foreach (['page_2', 'block_2'] as $id) {
      if (isset($displays[$id]) && !isset($displays[$id]['display_options']['sorts'])) {
        $displays[$id]['display_options']['sorts'] = $past_sorts;
        $displays[$id]['display_options']['defaults']['sorts'] = FALSE;
      }
    }
  }

  $view->set('display', $displays);
  $view->save();

  if (!\Drupal::moduleHandler()->moduleExists('calendar_view')) {
    lc_events_set_calendar_link(FALSE);
  }

  return t('Events list updated.');
}
