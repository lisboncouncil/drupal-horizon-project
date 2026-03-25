<?php

namespace Drupal\lc_event_registration\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\lc_event_registration\Entity\EventRegistration;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * Manages event registration business logic.
 */
class RegistrationManager {

  /**
   * Optional fields that can be toggled per event.
   */
  public const OPTIONAL_FIELDS = [
    'nationality',
    'date_of_birth',
    'document_type',
    'document_number',
    'document_expiry_date',
    'organisation',
    'mobile_number',
    'participation_mode',
    'lunch_attendance',
    'photo_consent',
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entity_type_manager,
    protected Connection $database,
    protected LoggerInterface $logger,
    protected MailManagerInterface $mail_manager,
    protected ConfigFactoryInterface $config_factory,
    protected PasswordGeneratorInterface $password_generator
  ) {}

  /**
   * Check whether registration is open for a given event.
   */
  public function is_registration_open(NodeInterface $event): bool {
    if (!$event->hasField('field_registration_enabled')) {
      return FALSE;
    }
    if (!(bool) $event->get('field_registration_enabled')->value) {
      return FALSE;
    }

    // Check registration date window.
    $today = date('Y-m-d');
    if ($event->hasField('field_registration_open_date')) {
      $open_date = $event->get('field_registration_open_date')->value;
      if ($open_date && $today < $open_date) {
        return FALSE;
      }
    }
    if ($event->hasField('field_registration_close_date')) {
      $close_date = $event->get('field_registration_close_date')->value;
      if ($close_date && $today > $close_date) {
        return FALSE;
      }
    }

    $capacity = (int) $event->get('field_registration_capacity')->value;
    if ($capacity > 0 && $this->get_registration_count($event) >= $capacity) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Get the registration status reason for display purposes.
   */
  public function get_registration_status(NodeInterface $event): string {
    if (!$event->hasField('field_registration_enabled')) {
      return 'disabled';
    }
    if (!(bool) $event->get('field_registration_enabled')->value) {
      return 'disabled';
    }

    $today = date('Y-m-d');
    if ($event->hasField('field_registration_open_date')) {
      $open_date = $event->get('field_registration_open_date')->value;
      if ($open_date && $today < $open_date) {
        return 'not_yet_open';
      }
    }
    if ($event->hasField('field_registration_close_date')) {
      $close_date = $event->get('field_registration_close_date')->value;
      if ($close_date && $today > $close_date) {
        return 'closed';
      }
    }

    $capacity = (int) $event->get('field_registration_capacity')->value;
    if ($capacity > 0 && $this->get_registration_count($event) >= $capacity) {
      return 'full';
    }

    return 'open';
  }

  /**
   * Count confirmed registrations for an event.
   */
  public function get_registration_count(NodeInterface $event): int {
    return (int) $this->entity_type_manager
      ->getStorage('event_registration')
      ->getQuery()
      ->condition('event_nid', $event->id())
      ->condition('status', 'confirmed')
      ->accessCheck(FALSE)
      ->count()
      ->execute();
  }

  /**
   * Check whether an email is already registered for an event.
   */
  public function is_already_registered(NodeInterface $event, string $email): bool {
    $count = $this->entity_type_manager
      ->getStorage('event_registration')
      ->getQuery()
      ->condition('event_nid', $event->id())
      ->condition('email', $email)
      ->condition('status', 'confirmed')
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    return ((int) $count) > 0;
  }

  /**
   * Get the list of required optional fields for a given event.
   */
  public function get_required_fields(NodeInterface $event): array {
    if (!$event->hasField('field_registration_fields')) {
      return [];
    }
    $values = $event->get('field_registration_fields')->getValue();
    return array_column($values, 'value');
  }

  /**
   * Register a user for an event.
   *
   * @param \Drupal\node\NodeInterface $event
   *   The event node.
   * @param array $data
   *   Registration data from the form.
   * @param \Drupal\user\UserInterface|null $account
   *   The user account, or NULL for anonymous.
   *
   * @return \Drupal\lc_event_registration\Entity\EventRegistration
   *   The created registration entity.
   */
  public function register(NodeInterface $event, array $data, ?UserInterface $account = NULL): EventRegistration {
    // Resolve or create user account.
    if (!$account || $account->isAnonymous()) {
      $account = $this->resolve_or_create_user($data);
    }

    $values = [
      'event_nid' => $event->id(),
      'uid' => $account->id(),
      'first_name' => $data['first_name'],
      'last_name' => $data['last_name'],
      'email' => $data['email'],
      'status' => 'confirmed',
    ];

    // Add optional fields if present.
    foreach (self::OPTIONAL_FIELDS as $field) {
      if (!empty($data[$field])) {
        $values[$field] = $data[$field];
      }
    }

    /** @var \Drupal\lc_event_registration\Entity\EventRegistration $registration */
    $registration = EventRegistration::create($values);
    $registration->save();

    $this->logger->info('Event registration created: @email for event @event', [
      '@email' => $data['email'],
      '@event' => $event->label(),
    ]);

    // Send confirmation email to registrant.
    $this->send_confirmation_email($registration, $event);

    // Send notification email(s) to event organisers.
    $this->send_notification_email($registration, $event);

    return $registration;
  }

  /**
   * Find existing user by email or create a new one.
   */
  protected function resolve_or_create_user(array $data): UserInterface {
    // Check if user with this email already exists.
    $existing = $this->entity_type_manager
      ->getStorage('user')
      ->loadByProperties(['mail' => $data['email']]);

    if (!empty($existing)) {
      return reset($existing);
    }

    return $this->create_user_from_registration($data);
  }

  /**
   * Create a new Drupal user account from registration data.
   */
  public function create_user_from_registration(array $data): UserInterface {
    $user = User::create([
      'name' => $data['email'],
      'mail' => $data['email'],
      'status' => 1,
      'pass' => $this->password_generator->generate(20),
    ]);

    // Map overlapping fields to user profile.
    if ($user->hasField('field_first_name') && !empty($data['first_name'])) {
      $user->set('field_first_name', $data['first_name']);
    }
    if ($user->hasField('field_last_name') && !empty($data['last_name'])) {
      $user->set('field_last_name', $data['last_name']);
    }
    if ($user->hasField('field_organisation') && !empty($data['organisation'])) {
      $user->set('field_organisation', $data['organisation']);
    }
    if ($user->hasField('field_profile_country') && !empty($data['nationality'])) {
      // Try to find a matching country taxonomy term.
      $terms = $this->entity_type_manager
        ->getStorage('taxonomy_term')
        ->loadByProperties([
          'vid' => 'country',
          'name' => $data['nationality'],
        ]);
      if (!empty($terms)) {
        $user->set('field_profile_country', reset($terms)->id());
      }
    }

    $user->save();

    // Send verification email.
    _user_mail_notify('register_no_approval_required', $user);

    $this->logger->info('User account created for event registration: @email', [
      '@email' => $data['email'],
    ]);

    return $user;
  }

  /**
   * Send a confirmation email to the registrant.
   */
  protected function send_confirmation_email(EventRegistration $registration, NodeInterface $event): void {
    $email = $registration->get('email')->value;
    $base_url = $this->get_base_url();

    // Agenda.
    $agenda_html = '';
    if ($event->hasField('field_event_agenda') && !$event->get('field_event_agenda')->isEmpty()) {
      $agenda_item = $event->get('field_event_agenda')->first();
      $agenda_html = check_markup($agenda_item->value, $agenda_item->format ?? 'basic_html');
    }

    // Static map image (only if coordinates and API key are available).
    $coordinates = $this->get_event_coordinates($event);
    $map_image_url = NULL;
    if ($coordinates && $this->format_event_location($event)) {
      $map_image_url = $this->get_static_map_url($coordinates);
    }

    // Online event link.
    $online_link = '';
    if ($event->hasField('field_event_online_link') && !$event->get('field_event_online_link')->isEmpty()) {
      $online_link = $event->get('field_event_online_link')->first()->getUrl()->toString();
    }

    // Email colours from config.
    $settings = $this->config_factory->get('lc_event_registration.settings');
    $primary_color = $settings->get('email_primary_color') ?: '#0B4A89';
    $secondary_color = $settings->get('email_secondary_color') ?: '#5a9e56';

    // Render the confirmation body via Twig.
    $body_render = [
      '#theme' => 'lc_event_registration_email_confirmation',
      '#first_name' => $registration->get('first_name')->value,
      '#last_name' => $registration->get('last_name')->value,
      '#email' => $email,
      '#event_title' => $event->label(),
      '#event_url' => $base_url . $event->toUrl('canonical')->toString(),
      '#event_date' => $this->format_event_date($event),
      '#event_location' => $this->format_event_location($event),
      '#coordinates' => $coordinates,
      '#map_image_url' => $map_image_url,
      '#agenda_html' => $agenda_html,
      '#organisation' => $registration->get('organisation')->value ?? '',
      '#online_link' => $online_link,
      '#ical_url' => $base_url . Url::fromRoute('lc_event_registration.ical', ['node' => $event->id()])->toString(),
      '#primary_color' => $primary_color,
      '#secondary_color' => $secondary_color,
    ];
    $body_content = $this->render_template($body_render);

    $html = $this->build_email_wrapper($primary_color, $body_content);

    $params = [
      'subject' => "Registration confirmed: " . $event->label(),
      'body' => $html,
    ];

    $langcode = $this->config_factory->get('system.site')->get('langcode') ?? 'en';
    $this->mail_manager->mail('lc_event_registration', 'registration_confirmed', $email, $langcode, $params);
  }

  /**
   * Send notification email(s) to event organisers.
   */
  protected function send_notification_email(EventRegistration $registration, NodeInterface $event): void {
    if (!$event->hasField('field_registration_notify_emails') || $event->get('field_registration_notify_emails')->isEmpty()) {
      return;
    }

    $emails_raw = $event->get('field_registration_notify_emails')->value;
    $emails = array_filter(array_map('trim', explode(',', $emails_raw)));
    if (empty($emails)) {
      return;
    }

    $base_url = $this->get_base_url();

    // Collect non-empty optional fields.
    $field_labels = [
      'organisation' => 'Organisation',
      'nationality' => 'Nationality',
      'date_of_birth' => 'Date of birth',
      'document_type' => 'Document type',
      'document_number' => 'Document number',
      'document_expiry_date' => 'Document expiry date',
      'mobile_number' => 'Mobile number',
      'participation_mode' => 'Participation mode',
      'lunch_attendance' => 'Lunch attendance',
      'photo_consent' => 'Consent to photos/videos',
    ];
    $optional_fields = [];
    foreach ($field_labels as $field => $label) {
      $value = $registration->get($field)->value ?? '';
      if ($value !== '') {
        $optional_fields[] = ['label' => $label, 'value' => $value];
      }
    }

    // Render the notification body via Twig.
    $body_render = [
      '#theme' => 'lc_event_registration_email_notification',
      '#event_title' => $event->label(),
      '#first_name' => $registration->get('first_name')->value,
      '#last_name' => $registration->get('last_name')->value,
      '#reg_email' => $registration->get('email')->value,
      '#optional_fields' => $optional_fields,
      '#count' => $this->get_registration_count($event),
      '#capacity' => (int) $event->get('field_registration_capacity')->value,
      '#admin_url' => $base_url . Url::fromRoute('view.event_registrations.page_1')->toString(),
    ];
    $body_content = $this->render_template($body_render);

    $settings = $this->config_factory->get('lc_event_registration.settings');
    $secondary_color = $settings->get('email_secondary_color') ?: '#5a9e56';
    $html = $this->build_email_wrapper($secondary_color, $body_content);

    $langcode = $this->config_factory->get('system.site')->get('langcode') ?? 'en';
    $params = [
      'subject' => "New registration: " . $event->label(),
      'body' => $html,
    ];

    foreach ($emails as $notify_email) {
      if (\Drupal::service('email.validator')->isValid($notify_email)) {
        $this->mail_manager->mail('lc_event_registration', 'registration_notification', $notify_email, $langcode, $params);
      }
    }
  }

  /**
   * Get a reliable base URL, even in CLI context.
   *
   * If a site_base_url is configured, it is returned directly.
   * Otherwise, Drupal's request object is used. When no proper
   * request context exists (CLI, drush), the request returns
   * 'http://default', which is returned as a last resort.
   */
  protected function get_base_url(): string {
    // Prefer the explicitly configured base URL.
    $configured_url = $this->config_factory->get('lc_event_registration.settings')->get('site_base_url');
    if (!empty($configured_url)) {
      return rtrim($configured_url, '/');
    }

    try {
      $host = \Drupal::request()->getHost();
      if ($host !== 'default') {
        return \Drupal::request()->getSchemeAndHttpHost();
      }
    }
    catch (\Exception $e) {
      // No request available.
    }

    return 'http://default';
  }

  /**
   * Build the shared HTML email wrapper using Twig template.
   */
  protected function build_email_wrapper(string $header_color, string $body_content): string {
    $base_url = $this->get_base_url();
    $logo_url = '';
    $fid = $this->config_factory->get('lc_event_registration.settings')->get('email_logo_fid');
    if ($fid) {
      /** @var \Drupal\file\FileInterface|null $file */
      $file = $this->entity_type_manager->getStorage('file')->load($fid);
      if ($file) {
        $logo_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
        // Fix base URL if it resolves to http://default.
        if (str_contains($logo_url, '://default')) {
          $logo_url = $base_url . \Drupal::service('file_url_generator')->generateString($file->getFileUri());
        }
      }
    }

    $settings = $this->config_factory->get('lc_event_registration.settings');
    $privacy_url = $settings->get('privacy_url') ?? '/privacy';
    $primary_color = $settings->get('email_primary_color') ?? '#0B4A89';

    $wrapper_render = [
      '#theme' => 'lc_event_registration_email_wrapper',
      '#header_color' => $header_color,
      '#primary_color' => $primary_color,
      '#logo_url' => $logo_url,
      '#site_name' => $this->config_factory->get('system.site')->get('name'),
      '#site_url' => $base_url,
      '#privacy_url' => $base_url . $privacy_url,
      '#body_content' => $body_content,
    ];

    return $this->render_template($wrapper_render);
  }

  /**
   * Render a Twig template to a string.
   */
  protected function render_template(array $render_array): string {
    return (string) \Drupal::service('renderer')->renderPlain($render_array);
  }

  /**
   * Format the event date from the smartdate field.
   */
  protected function format_event_date(NodeInterface $event): string {
    if (!$event->hasField('field_event_date') || $event->get('field_event_date')->isEmpty()) {
      return '';
    }
    $date_item = $event->get('field_event_date')->first();
    $start_ts = $date_item->value;
    $end_ts = $date_item->end_value ?? $start_ts;

    $start_date = date('d F Y', $start_ts);
    $start_time = date('H:i', $start_ts);
    $end_time = date('H:i', $end_ts);

    // Same day: "15 March 2026, 09:00 – 17:00".
    if (date('Y-m-d', $start_ts) === date('Y-m-d', $end_ts)) {
      if ($start_time === $end_time || $start_time === '00:00') {
        return $start_date;
      }
      return "$start_date, $start_time &ndash; $end_time";
    }

    // Multi-day.
    $end_date = date('d F Y', $end_ts);
    return "$start_date &ndash; $end_date";
  }

  /**
   * Format the event location from the address field.
   */
  protected function format_event_location(NodeInterface $event): string {
    if (!$event->hasField('field_event_address') || $event->get('field_event_address')->isEmpty()) {
      return '';
    }
    $address = $event->get('field_event_address')->first()->getValue();
    $parts = array_filter([
      $address['address_line1'] ?? '',
      $address['locality'] ?? '',
      $address['country_code'] ?? '',
    ]);
    return implode(', ', $parts);
  }

  /**
   * Get event coordinates from the geolocation field.
   */
  protected function get_event_coordinates(NodeInterface $event): ?array {
    if (!$event->hasField('field_event_geolocation') || $event->get('field_event_geolocation')->isEmpty()) {
      return NULL;
    }
    $geo = $event->get('field_event_geolocation')->first();
    $lat = $geo->get('lat')->getValue();
    $lng = $geo->get('lng')->getValue();
    if (empty($lat) || empty($lng)) {
      return NULL;
    }
    return ['lat' => (float) $lat, 'lng' => (float) $lng];
  }

  /**
   * Get the URL of a cached static map image for the given coordinates.
   *
   * Downloads the image from Geoapify on first request and caches it
   * in public://lc_event_registration/maps/.
   *
   * @return string|null
   *   Absolute URL to the cached map image, or NULL if unavailable.
   */
  protected function get_static_map_url(array $coordinates): ?string {
    $api_key = $this->config_factory->get('lc_event_registration.settings')->get('geoapify_api_key');
    if (empty($api_key)) {
      return NULL;
    }

    $lat = $coordinates['lat'];
    $lng = $coordinates['lng'];
    $base_url = $this->get_base_url();

    // Cache filename based on coordinates (rounded to 5 decimals).
    $hash = md5(sprintf('%.5f,%.5f', $lat, $lng));
    $directory = 'public://lc_event_registration/maps';
    $filepath = $directory . '/' . $hash . '.png';

    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');

    // Return cached version if it exists.
    if (file_exists($filepath)) {
      $url = \Drupal::service('file_url_generator')->generateAbsoluteString($filepath);
      if (str_contains($url, '://default')) {
        $url = $base_url . \Drupal::service('file_url_generator')->generateString($filepath);
      }
      return $url;
    }

    // Ensure directory exists.
    $file_system->prepareDirectory($directory, $file_system::CREATE_DIRECTORY | $file_system::MODIFY_PERMISSIONS);

    // Download from Geoapify.
    $map_url = sprintf(
      'https://maps.geoapify.com/v1/staticmap?style=osm-bright&width=600&height=400&center=lonlat:%s,%s&zoom=14&scaleFactor=2&marker=lonlat:%s,%s;type:awesome;color:%%23e01401&apiKey=%s',
      $lng,
      $lat,
      $lng,
      $lat,
      $api_key,
    );

    try {
      $response = \Drupal::httpClient()->get($map_url, ['timeout' => 15]);
      if ($response->getStatusCode() === 200) {
        $file_system->saveData($response->getBody()->getContents(), $filepath, $file_system::EXISTS_REPLACE);
        $url = \Drupal::service('file_url_generator')->generateAbsoluteString($filepath);
        if (str_contains($url, '://default')) {
          $url = $base_url . \Drupal::service('file_url_generator')->generateString($filepath);
        }
        return $url;
      }
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to download static map from Geoapify: @error', [
        '@error' => $e->getMessage(),
      ]);
    }

    return NULL;
  }

  /**
   * Cancel a registration.
   */
  public function cancel_registration(EventRegistration $registration): void {
    $registration->set('status', 'cancelled');
    $registration->save();

    $this->logger->info('Event registration cancelled: @id', [
      '@id' => $registration->id(),
    ]);
  }

}
