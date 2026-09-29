# LC Event Map

Adds a map of the venue to the event page.

- `field_event_location` (geofield) is filled in automatically from
  `field_event_address` when the event is saved, with the OpenStreetMap
  Nominatim geocoder: no API key is needed.
- The map is shown with Leaflet, right after the address.
- The schema.org Event metadata of `lc_events` gets the coordinates.

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
