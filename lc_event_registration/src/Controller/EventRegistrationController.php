<?php

namespace Drupal\lc_event_registration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\lc_event_registration\Entity\EventRegistration;
use Drupal\lc_event_registration\Service\RegistrationManager;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for event registrations.
 */
class EventRegistrationController extends ControllerBase {

  public function __construct(
    protected RegistrationManager $registration_manager
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('lc_event_registration.manager')
    );
  }

  /**
   * Thank you page shown after successful registration.
   */
  public function thank_you(NodeInterface $node): array {
    $build = [];

    // Page title.
    $build['page_title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h1',
      '#value' => $this->t('Registration confirmed'),
      '#weight' => -200,
    ];

    // Two-column layout.
    $build['layout'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['row']],
      '#weight' => -100,
    ];

    $build['layout']['main'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['col-lg-8', 'order-last', 'order-lg-first']],
    ];

    $build['layout']['sidebar'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['col-lg-4', 'order-first', 'order-lg-last']],
    ];

    // Custom thank you message from the event, or default.
    $thank_you_markup = '';
    if ($node->hasField('field_registration_thank_you') && !$node->get('field_registration_thank_you')->isEmpty()) {
      $text_item = $node->get('field_registration_thank_you')->first();
      $thank_you_markup = check_markup($text_item->value, $text_item->format ?? 'basic_html');
    }
    else {
      $thank_you_markup = '<p>' . $this->t('Thank you for registering for <strong>@event</strong>. You will receive a confirmation email shortly.', [
        '@event' => $node->label(),
      ]) . '</p>';
    }

    $build['layout']['main']['message'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['alert', 'alert-success', 'mb-4']],
      '#value' => $thank_you_markup,
    ];

    // iCal download button.
    $ical_url = Url::fromRoute('lc_event_registration.ical', ['node' => $node->id()]);
    $build['layout']['main']['ical'] = [
      '#type' => 'link',
      '#title' => $this->t('Add to calendar'),
      '#url' => $ical_url,
      '#attributes' => [
        'class' => ['btn', 'btn-outline-primary', 'me-2', 'mb-3'],
        'download' => $this->t('event') . '.ics',
      ],
      'icon' => [
        '#markup' => '<i class="bi bi-calendar-plus me-1"></i>',
      ],
    ];

    // Back to event button.
    $build['layout']['main']['back'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to event'),
      '#url' => $node->toUrl(),
      '#attributes' => ['class' => ['btn', 'btn-outline-secondary', 'mb-3']],
    ];

    // Sidebar with event info (reuses same pattern as the form).
    $build['layout']['sidebar']['event_info'] = $this->build_event_sidebar($node);

    return $build;
  }

  /**
   * Generate an iCal (.ics) file for the event.
   */
  public function ical(NodeInterface $node): Response {
    $title = $node->label();
    $uid = 'event-' . $node->id() . '@' . \Drupal::request()->getHost();
    $now = gmdate('Ymd\THis\Z');

    // Event date (smartdate field stores timestamps).
    $dtstart = $now;
    $dtend = $now;
    if ($node->hasField('field_event_date') && !$node->get('field_event_date')->isEmpty()) {
      $date_item = $node->get('field_event_date')->first();
      $start_ts = $date_item->value;
      $end_ts = $date_item->end_value ?? $start_ts;
      $dtstart = gmdate('Ymd\THis\Z', $start_ts);
      $dtend = gmdate('Ymd\THis\Z', $end_ts);
    }

    // Location from address field.
    $location = '';
    if ($node->hasField('field_event_address') && !$node->get('field_event_address')->isEmpty()) {
      $address = $node->get('field_event_address')->first()->getValue();
      $parts = array_filter([
        $address['address_line1'] ?? '',
        $address['locality'] ?? '',
        $address['country_code'] ?? '',
      ]);
      $location = implode(', ', $parts);
    }

    // Event URL.
    $url = $node->toUrl('canonical', ['absolute' => TRUE])->toString();

    // Description from body field.
    $description = '';
    if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
      $description = strip_tags($node->get('body')->value);
      // Truncate to a reasonable length for iCal.
      if (mb_strlen($description) > 500) {
        $description = mb_substr($description, 0, 497) . '...';
      }
    }

    $site_name = $this->config('system.site')->get('name') ?? 'Event';
    $ics = $this->build_ics($uid, $title, $dtstart, $dtend, $location, $description, $url, $now, $site_name);

    return new Response($ics, 200, [
      'Content-Type' => 'text/calendar; charset=utf-8',
      'Content-Disposition' => 'attachment; filename="event.ics"',
    ]);
  }

  /**
   * Build an iCalendar string.
   */
  protected function build_ics(string $uid, string $title, string $dtstart, string $dtend, string $location, string $description, string $url, string $now, string $site_name = 'Event'): string {
    $lines = [
      'BEGIN:VCALENDAR',
      'VERSION:2.0',
      'PRODID:-//' . $site_name . '//Event Registration//EN',
      'CALSCALE:GREGORIAN',
      'METHOD:PUBLISH',
      'BEGIN:VEVENT',
      'UID:' . $uid,
      'DTSTAMP:' . $now,
      'DTSTART:' . $dtstart,
      'DTEND:' . $dtend,
      'SUMMARY:' . $this->ical_escape($title),
    ];

    if ($location) {
      $lines[] = 'LOCATION:' . $this->ical_escape($location);
    }
    if ($description) {
      $lines[] = 'DESCRIPTION:' . $this->ical_escape($description);
    }
    if ($url) {
      $lines[] = 'URL:' . $url;
    }

    $lines[] = 'END:VEVENT';
    $lines[] = 'END:VCALENDAR';

    return implode("\r\n", $lines) . "\r\n";
  }

  /**
   * Escape a string for use in an iCalendar field.
   */
  protected function ical_escape(string $text): string {
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace(',', '\\,', $text);
    $text = str_replace(';', '\\;', $text);
    $text = str_replace("\n", '\\n', $text);
    return $text;
  }

  /**
   * Build the event information sidebar.
   */
  protected function build_event_sidebar(NodeInterface $node): array {
    $sidebar = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'bg-light', 'p-4', 'mb-4', 'sticky-top']],
    ];

    $sidebar['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h4',
      '#value' => $node->label(),
      '#attributes' => ['class' => ['mb-3']],
    ];

    $items = [];

    // Event date.
    if ($node->hasField('field_event_date') && !$node->get('field_event_date')->isEmpty()) {
      $timestamp = $node->get('field_event_date')->value;
      $items[] = [
        'icon' => 'bi-calendar-event',
        'label' => $this->t('Date'),
        'value' => date('d F Y', $timestamp),
      ];
    }

    // Event address.
    if ($node->hasField('field_event_address') && !$node->get('field_event_address')->isEmpty()) {
      $address = $node->get('field_event_address')->first()->getValue();
      $parts = array_filter([
        $address['address_line1'] ?? '',
        $address['locality'] ?? '',
        $address['country_code'] ?? '',
      ]);
      if (!empty($parts)) {
        $items[] = [
          'icon' => 'bi-geo-alt',
          'label' => $this->t('Location'),
          'value' => implode(', ', $parts),
        ];
      }
    }

    foreach ($items as $index => $item) {
      $sidebar['item_' . $index] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['mb-3']],
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['text-muted', 'small']],
          '#value' => '<i class="bi ' . $item['icon'] . ' me-1"></i>' . $item['label'],
        ],
        'value' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['fw-semibold']],
          '#value' => $item['value'],
        ],
      ];
    }

    // Link back to event page.
    $sidebar['back_link'] = [
      '#type' => 'link',
      '#title' => $this->t('View event details'),
      '#url' => $node->toUrl(),
      '#attributes' => ['class' => ['btn', 'btn-outline-secondary', 'btn-sm', 'mt-2', 'w-100']],
    ];

    return $sidebar;
  }

  /**
   * Cancel a registration.
   */
  public function cancel(EventRegistration $event_registration): RedirectResponse {
    $this->registration_manager->cancel_registration($event_registration);
    $this->messenger()->addStatus($this->t('Registration cancelled.'));
    return $this->redirect('view.event_registrations.page_1');
  }

}
