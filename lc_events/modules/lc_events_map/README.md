# LC Event Map

Adds a map of the venue to the event page.

- `field_event_location` (geofield) is filled in automatically from
  `field_event_address` when the event is saved, with the OpenStreetMap
  Nominatim geocoder: no API key is needed.
- The map is shown with Leaflet, right after the address.
- The schema.org Event metadata of `lc_events` gets the coordinates.

## Map settings

When the module is installed, the map is added to the default event display
with the settings in `lc_events_map.settings:formatter`:

| Key | Default | |
|---|---|---|
| `leaflet_map` | `openstreetmap` | Map definition, from `hook_leaflet_map_info()`: e.g. a site map with a vector style (a layer with `'type' => 'vector'`) |
| `height`, `height_unit` | `400`, `px` | Height of the map |
| `disable_wheel` | `true` | The mouse wheel scrolls the page instead of zooming the map |
| `gesture_handling` | `false` | Two fingers to move the map on touch screens, Ctrl + wheel to zoom |
| `icon` | Leaflet marker | Marker: `iconType` `marker`, `html` (with `html`, `html_class`, `iconSize`, `iconAnchor`) or `circle_marker` |

To change them before the module is installed, override them in
`settings.php`:

```php
$config['lc_events_map.settings']['formatter']['leaflet_map'] = 'my_site_map';
$config['lc_events_map.settings']['formatter']['height'] = 280;
```

or implement `hook_lc_events_map_formatter_settings_alter()` in a module
installed before this one (see `lc_events_map.api.php`). A map definition that
does not exist falls back to `openstreetmap`, with a warning in the log.

Once installed, the map is a field like any other: change it in Manage
display of the Event content type (field "Event location").

## Requirements

```
composer require drupal/geofield drupal/leaflet drupal/geocoder geocoder-php/nominatim-provider
```

## Notes

- Nominatim is a free service with a usage policy: at most one request per
  second and a User-Agent identifying the site. The module sets the
  User-Agent to the site name and e-mail. For a large number of events,
  consider a self-hosted instance or another provider
  (`/admin/config/system/geocoder/geocoder-provider`).
- Uninstalling the module deletes the coordinates stored on the events.

## Replaces

Up to lc_events 1.1 the map used geolocation with Google Maps
(`field_event_geolocation`), which required a Google API key. Existing sites
keep that field until they migrate by hand: data is not converted
automatically.
