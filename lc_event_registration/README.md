# LC Event Registration

A Drupal 10/11 module that adds a full event registration system to the `lc_events` content type. It provides per-event configuration, user auto-creation, capacity management, HTML email notifications, iCal download, and an administrative registrations view.

---

## Requirements

| Dependency | Reason |
|---|---|
| `lc_events` | Provides the `event` content type and its fields |
| `drupal:node` | Node entity |
| `drupal:user` | User creation on registration |
| `drupal:datetime` | Open/close date fields |
| `drupal:field`, `drupal:options` | Field storage and list fields |
| `drupal:views` | Admin registrations view |

The module may be installed before or after `lc_events`. If `lc_events` is installed later, the event fields are created automatically via `hook_modules_installed()`.

---

## Installation

```bash
ddru pm:enable lc_event_registration -y
ddru cr
```

### Known Installation Issue — Config Already Exists

If you reinstall the module (uninstall + enable) after the site has been running for some time, Drupal may refuse to install with:

> Configuration objects provided by lc_event_registration already exist in active configuration

This happens because the config was not cleaned up on uninstall. Fix:

```bash
ddru php:eval "
\$ext = \Drupal::configFactory()->getEditable('core.extension');
\$m = \$ext->get('module');
\$m['lc_event_registration'] = 0;
asort(\$m);
\$ext->set('module', \$m)->save();
\Drupal::keyValue('system.schema')->set('lc_event_registration', 8000);
echo 'Done';
"
ddru cr
```

### Update Hooks Not Running After Reinstall

When a module is reinstalled, Drupal initialises the schema version to the latest update hook number without actually executing the hooks. If newly added fields (e.g. `photo_consent`) are missing, run the relevant logic manually:

```bash
ddru php:eval "
\$update_manager = \Drupal::entityDefinitionUpdateManager();
\$entity_type = \Drupal::entityTypeManager()->getDefinition('event_registration');
\$defs = \Drupal\lc_event_registration\Entity\EventRegistration::baseFieldDefinitions(\$entity_type);
\$update_manager->installFieldStorageDefinition('photo_consent', 'event_registration', 'lc_event_registration', \$defs['photo_consent']);
\$fs = \Drupal\field\Entity\FieldStorageConfig::loadByName('node', 'field_registration_fields');
\$fs->setSetting('allowed_values', ['nationality'=>'Nationality','date_of_birth'=>'Date of birth','document_type'=>'Document type','document_number'=>'Document number','document_expiry_date'=>'Document expiry date','organisation'=>'Organisation / Company','mobile_number'=>'Mobile number','participation_mode'=>'Participation mode (In Person / Online)','lunch_attendance'=>'Lunch attendance','photo_consent'=>'Consent to photos and videos']);
\$fs->save();
echo 'Done';
"
ddru cr
```

---

## Configuration

Settings are available at `/admin/config/lc-event-registration/settings` (requires the `administer event_registration` permission).

| Setting | Default | Description |
|---|---|---|
| Email logo | — | PNG/JPG uploaded to `public://lc_event_registration/`. Displayed in email headers. SVG not supported. |
| Geoapify API key | — | Used to generate a cached static map image for physical event locations in confirmation emails. Leave empty to hide the map. |
| GDPR consent text | — | HTML text shown next to the GDPR consent checkbox on the registration form. |
| Email primary colour | `#0B4A89` | Header and button colour in confirmation emails. |
| Email secondary colour | `#5a9e56` | Header colour in organiser notification emails. |
| Privacy policy URL | `/privacy` | Internal path linked in email footers. |
| Site base URL | — | Override the base URL used in links within emails (e.g. `https://example.eu`). Required when running Drush in CLI context, where `http://default` would otherwise be used. |

---

## How It Works

### Registration Fields on the Event Node

On installation, the module adds the following fields to the `event` content type:

| Field | Type | Purpose |
|---|---|---|
| `field_registration_enabled` | Boolean | Master switch; registration only works when this is checked |
| `field_registration_open_date` | Date | If set, the registration button is hidden before this date |
| `field_registration_close_date` | Date | If set, registration closes after this date |
| `field_registration_capacity` | Integer | Maximum number of confirmed registrations; 0 = unlimited |
| `field_registration_fields` | List (multiple) | Which optional fields to show on the registration form |
| `field_registration_intro` | Text (long) | Introductory text shown above the registration form |
| `field_registration_thank_you` | Text (long) | Custom message shown on the confirmation page |
| `field_registration_notify_emails` | String | Comma-separated email addresses to notify on each new registration |

All fields are grouped into a collapsible **Registration settings** fieldset on the event edit form, with all controls hidden unless `field_registration_enabled` is checked.

### Optional Registration Form Fields

The following fields can be toggled per event via `field_registration_fields`:

| Key | Label |
|---|---|
| `nationality` | Nationality |
| `date_of_birth` | Date of birth |
| `document_type` | Document type (Passport / Identity Card) |
| `document_number` | Document number |
| `document_expiry_date` | Document expiry date |
| `organisation` | Organisation / Company |
| `mobile_number` | Mobile number |
| `participation_mode` | Participation mode (In Person / Online) |
| `lunch_attendance` | Lunch attendance (Yes / No) |
| `photo_consent` | Consent to photos and videos |

All selected fields are stored on the `event_registration` entity (custom content entity, table `event_registration`).

### Registration Status Logic

`RegistrationManager::get_registration_status()` returns one of:

| Status | Condition |
|---|---|
| `disabled` | `field_registration_enabled` is unchecked |
| `not_yet_open` | Today is before `field_registration_open_date` |
| `closed` | Today is after `field_registration_close_date` |
| `full` | `field_registration_capacity > 0` and confirmed registrations ≥ capacity |
| `open` | All checks passed |

### Registration Button (Pseudo-Field)

The module declares `event_registration_action` as a pseudo-field via `hook_entity_extra_field_info()`. It renders differently depending on context:

| Condition | Output |
|---|---|
| Authenticated user already registered | Green Bootstrap alert: "You are registered for this event." |
| Registration not yet open | Warning alert with the open date |
| Registration closed or full | Warning alert with the reason |
| Registration open | "Register for this event" button → `/events/{node}/register` |

The render cache is varied by `user` context so each visitor sees the correct state.

### User Auto-Creation

When an anonymous user submits the registration form, `RegistrationManager::resolve_or_create_user()` looks up the provided email address:

- **Existing user found** → the registration is linked to that account.
- **No existing user** → a new Drupal account is created (`status = 1`, random password). Drupal's standard "register without approval required" verification email is sent. The following profile fields are populated if they exist on the user entity: `field_first_name`, `field_last_name`, `field_organisation`, `field_profile_country` (matched against the `country` taxonomy vocabulary by name).

Authenticated users skip this step entirely.

### Emails

Two HTML emails are sent on each successful registration:

**1. Confirmation email to the registrant**
- Rendered via `lc_event_registration_email_confirmation` Twig template
- Includes: event title, date, location, agenda, online link, iCal download link, optional static map image
- Wrapped in `lc_event_registration_email_wrapper` (branded header + footer)

**2. Notification email to organisers**
- Sent to every address in `field_registration_notify_emails`
- Includes: registrant's name, email, all filled optional fields, current registration count vs capacity, link to the admin registrations view

Both emails are styled using the primary/secondary colours configured in the settings form.

### iCal Download

`/events/{node}/register/calendar.ics` generates an iCal file from the event date (using the `smart_date` field). Accessible to everyone; linked from confirmation emails.

### Admin View

A Views page is provided at `/admin/content/event-registrations` listing all registrations with filters for event and status. Requires the `view event_registration` permission.

Individual registrations can be cancelled at `/admin/content/event-registrations/{id}/cancel` (CSRF-protected, requires `cancel event_registration`).

---

## Routes

| Route | Path | Access |
|---|---|---|
| `lc_event_registration.settings` | `/admin/config/lc-event-registration/settings` | `administer event_registration` |
| `lc_event_registration.register` | `/events/{node}/register` | Custom access (registration must be open) |
| `lc_event_registration.thank_you` | `/events/{node}/register/thank-you` | Public |
| `lc_event_registration.ical` | `/events/{node}/register/calendar.ics` | Public |
| `lc_event_registration.cancel` | `/admin/content/event-registrations/{id}/cancel` | `cancel event_registration` + CSRF token |

---

## Permissions

| Permission | Recommended roles |
|---|---|
| `register for events` | Anonymous, Authenticated |
| `view event_registration` | Event manager, Administrator |
| `cancel event_registration` | Event manager, Administrator |
| `administer event_registration` | Administrator only (security-sensitive) |

---

## Body Class

Pages under `lc_event_registration.*` routes automatically receive the `event-registration` body class, allowing theme-level targeting of registration pages.

---

## Divergences from Abstraction — COcyber-Specific Customisations

The following parts of the module contain hardcoded or tightly coupled logic that was designed specifically for the COcyber platform. These **must be reviewed and adapted** when deploying this module to another project.

### 1. User Profile Field Mapping (`RegistrationManager::create_user_from_registration`)

The user auto-creation logic maps registration data to COcyber-specific user profile fields:

```php
field_first_name, field_last_name, field_organisation, field_profile_country
```

`field_profile_country` is resolved by querying the `country` taxonomy vocabulary by name. Both the field names and the vocabulary machine name are COcyber conventions. On another site, these fields will simply be skipped if absent, but the mapping will not match any different field schema without code changes.

### 2. Country Taxonomy Vocabulary

`field_profile_country` expects a vocabulary with machine name `country`. If the target project uses a different vocabulary or a different country field type (e.g. the Address module's country selector), the mapping will produce no output silently.

### 3. Document Types (Hardcoded Allowed Values)

`document_type` on the `event_registration` entity has hardcoded allowed values (`passport`, `identity_card`). This list is not configurable from the UI and must be changed in code if different document types are required.

### 4. Email Colour Defaults

Default email colours (`#0B4A89` primary, `#5a9e56` secondary) match the COcyber brand palette. They are overridable via the settings form but the defaults are embedded in code in multiple places (`SettingsForm`, `RegistrationManager`, `lc_event_registration_mail`).

### 5. Confirmation Email Subject Line (English-Only)

The email subject is hardcoded in English:

```php
'subject' => "Registration confirmed: " . $event->label(),
'subject' => "New registration: " . $event->label(),
```

There is no multilingual handling. On a multilingual site these strings must be wrapped in `t()` with the appropriate language context.

### 6. Static Map Provider (Geoapify)

The confirmation email optionally embeds a static map downloaded from the Geoapify API. The map style (`osm-bright`), dimensions (600×400), and marker style are hardcoded in `get_static_map_url()`. The map is cached in `public://lc_event_registration/maps/` and is never invalidated automatically.

### 7. Verification Email on Auto-Created Users

New users created via registration receive Drupal's built-in `register_no_approval_required` email. On COcyber this email has been customised site-wide. On another site the email template will differ and may or may not be appropriate for this flow.

### 8. GDPR Consent Checkbox

The registration form includes a GDPR consent checkbox whose text is configurable via the settings form but is **not stored per registration** — only a boolean `gdpr_consent` value is recorded on the form submission (it is a required form element, not an entity field). This is compliant for COcyber's use case but may not satisfy stricter consent-logging requirements on other projects.

---

## Manual Operations Required After Installation

### 1. Place the Registration Button in the Event Display

The `event_registration_action` pseudo-field must be manually placed in the event node display. It is **not positioned automatically** by the module.

**If using Display Suite:**
1. Go to `admin/structure/types/manage/event/display`
2. Ensure Display Suite is active on this display
3. Drag `Registration button` into the desired DS region (e.g. the right column)
4. Save the display

**If using standard Drupal display management (no DS):**
1. Go to `admin/structure/types/manage/event/display`
2. Drag `Registration button` out of the *Disabled* section into a visible region
3. Set label to *Hidden*
4. Save

> **Important:** The button will not appear on event pages until it is placed in the display and `field_registration_enabled` is checked on the event node.

### 2. Set Permissions

Review and assign permissions at `admin/people/permissions`:

- Grant `register for events` to **Anonymous user** and **Authenticated user** for open registration
- Grant `view event_registration` and `cancel event_registration` to the **Event manager** role
- Reserve `administer event_registration` for **Administrator**

### 3. Configure the Settings Form

Visit `/admin/config/lc-event-registration/settings` and configure:

- Upload an email logo
- Set the **Site base URL** (critical for correct links in emails when using Drush or when the request host is unreliable)
- Set the Geoapify API key if static maps in emails are required
- Customise the GDPR consent text for the target site's privacy policy
- Adjust email colours to match the site's brand

### 4. Add Breadcrumbs to Registration Pages (Optional)

Registration pages (`/events/{node}/register`, `/events/{node}/register/thank-you`) do not include breadcrumbs by default. To add them, implement `hook_system_breadcrumb_alter()` in the site theme or a custom module:

```php
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;

function mytheme_system_breadcrumb_alter(Breadcrumb &$breadcrumb, RouteMatchInterface $route_match, array $context): void {
  $route = $route_match->getRouteName();
  if (!str_starts_with($route, 'lc_event_registration.')) {
    return;
  }
  /** @var \Drupal\node\NodeInterface $node */
  $node = $route_match->getParameter('node');
  if (!$node) {
    return;
  }
  $breadcrumb->addLink(Link::createFromRoute('Home', '<front>'));
  $breadcrumb->addLink(Link::createFromRoute('Events', 'view.events_list.page_1'));
  $breadcrumb->addLink(Link::createFromRoute($node->label(), 'entity.node.canonical', ['node' => $node->id()]));
  $breadcrumb->addCacheContexts(['route']);
}
```

Adjust the Events list route name to match the actual Views route on the target site.

### 5. Map Cached Images — Clear When Coordinates Change

If an event's geolocation coordinates are corrected after the static map has been generated, the cached file in `public://lc_event_registration/maps/` must be deleted manually for the new map to be downloaded. The cache key is `md5(sprintf('%.5f,%.5f', $lat, $lng))`.

### 6. Enable `lc_events` Before or Alongside This Module

If `lc_events` is not installed when this module is enabled, the event fields will not be created (the `event` node type does not exist yet). The module handles this automatically via `hook_modules_installed()` — install `lc_events` afterwards and the fields will be added. However, always verify with:

```bash
ddru php:eval "
  \$fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'event');
  \$reg = array_filter(array_keys(\$fields), fn(\$k) => str_starts_with(\$k, 'field_registration'));
  echo implode(PHP_EOL, \$reg) ?: 'NO REGISTRATION FIELDS FOUND';
"
```
