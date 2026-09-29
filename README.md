# Drupal boilerplate for Horizon Project's websites

A collection of modules and configuration used to build a new Horizon
project website of the Lisbon Council.

Requires Drupal 10.3 or later, and a site installed with the **standard**
profile. Tested on Drupal 11.4.

## Modules

| Module | What it adds |
|---|---|
| `lc_base` | Shared `body` and `field_image` field storages. Needed since Drupal 11.4, whose standard profile no longer creates them. Installed automatically as a dependency. |
| `lc_hcommon` | Basic page and News (`article`) content types, News view, default empty pages (project description, governance, contact, open data, outcomes) and the main and footer menu entries. Uninstalls the core `contact` module. |
| `lc_pages` | Privacy, cookies, accessibility statement and "join the project" pages. |
| `lc_section_partners` | Partner content type, partners view, Partner manager role, Country vocabulary with the EU27 countries. |
| `lc_section_wp` | Work package content type and view, WP manager role. Requires `lc_section_partners`. |
| `lc_section_pilots` | Pilot content type and view. Requires `lc_section_partners`. |
| `lc_section_campaign` | Campaign content type and view. |
| `lc_deliverables` | Material content type, Material type vocabulary, public deliverables page. |
| `lc_events` | Event content type, event list and calendar, Event category vocabulary. |
| `lc_events_map` | Map of the event venue: geocoded from the address with OpenStreetMap Nominatim and shown with Leaflet. No API key needed. Optional. |
| `lc_event_registration` | Registration to events, with configurable fields, automatic user creation and capacity management. |
| `lc_glossary` | Glossary content type and view. |
| `lc_social` | Social links block for the footer. |
| `lc_zenodo_publications` | Publications of a Zenodo community. |

## Menu entries

- Main menu: About (Description of the project, Partners, Governance), News,
  Pilots, Outcomes, Contact
- Footer menu: Privacy, Cookies, Open data, Contact

## How to install

With Composer, from the root of the Drupal project:

```
composer config repositories.lc vcs https://github.com/lisboncouncil/drupal-horizon-project
composer require lisboncouncil/drupal-horizon-project:dev-main
```

The package is installed in `web/modules/custom/drupal-horizon-project`,
together with the contrib modules it needs: address, ds, empty_fields,
field_group and smart_date.

### Optional modules

The LC modules work without them and use them when they are installed,
before or after the LC modules:

| Module | Adds |
|---|---|
| `drupal/pathauto` | URL aliases for events and glossary terms |
| `drupal/metatag`, `drupal/schema_metatag` (enable `schema_event`) | Meta tags and schema.org Event metadata |
| `drupal/calendar_view` | Calendar page of the events |
| `drupal/scheduler` | Scheduled publishing of events and materials |
| `drupal/svg_image` | SVG files in the material image |
| `drupal/geofield`, `drupal/leaflet`, `drupal/geocoder`, `geocoder-php/nominatim-provider` | Required by `lc_events_map` |

For example:

```
composer require drupal/pathauto drupal/metatag drupal/schema_metatag drupal/scheduler
```

Then enable the modules you need, for example:

```
drush en lc_hcommon lc_pages lc_section_partners lc_section_wp lc_section_pilots lc_events lc_events_map
```

## Install test

`scripts/install-test.sh` installs every module on a fresh site, one at a
time and then all together. It runs on GitHub Actions on every push, weekly,
and against the development version of the next Drupal release.

To run it locally on a throwaway Composer project that requires this package
and drush:

```
scripts/install-test.sh /path/to/test-project
```
