<?php

namespace Drupal\lc_event_registration\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\lc_event_registration\Service\RegistrationManager;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Event registration form with dynamic fields.
 */
class EventRegistrationForm extends FormBase {

  public function __construct(
    protected RegistrationManager $registration_manager,
    protected EntityTypeManagerInterface $entity_type_manager,
    protected ConfigFactoryInterface $config_factory
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('lc_event_registration.manager'),
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'lc_event_registration_form';
  }

  /**
   * Custom access check for the registration route.
   */
  public function access(AccountInterface $account, ?NodeInterface $node = NULL): AccessResult {
    if (!$node) {
      return AccessResult::forbidden();
    }

    // Check that the node has registration enabled.
    if (!$node->hasField('field_registration_enabled')) {
      return AccessResult::forbidden()->addCacheableDependency($node);
    }
    if (!(bool) $node->get('field_registration_enabled')->value) {
      return AccessResult::forbidden()->addCacheableDependency($node);
    }

    // Anonymous users are always allowed (they will get an account created).
    if ($account->isAnonymous()) {
      return AccessResult::allowed()->addCacheableDependency($node);
    }

    // Authenticated users need the 'register for events' permission.
    return AccessResult::allowedIfHasPermission($account, 'register for events')
      ->addCacheableDependency($node);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node) {
      $form['message'] = ['#markup' => $this->t('Event not found.')];
      return $form;
    }

    // Check capacity.
    if (!$this->registration_manager->is_registration_open($node)) {
      $form['message'] = ['#markup' => $this->t('Registration for this event is closed or full.')];
      return $form;
    }

    $form_state->set('event_nid', $node->id());

    // Get required optional fields for this event.
    $required_fields = $this->registration_manager->get_required_fields($node);
    $form_state->set('required_fields', $required_fields);

    // Pre-fill for logged-in users.
    $account = $this->currentUser();
    $pre_fill = [];
    if ($account->isAuthenticated()) {
      $user = $this->entity_type_manager->getStorage('user')->load($account->id());
      if ($user) {
        $pre_fill['email'] = $user->getEmail();
        if ($user->hasField('field_first_name')) {
          $pre_fill['first_name'] = $user->get('field_first_name')->value ?? '';
        }
        if ($user->hasField('field_last_name')) {
          $pre_fill['last_name'] = $user->get('field_last_name')->value ?? '';
        }
        if ($user->hasField('field_organisation')) {
          $pre_fill['organisation'] = $user->get('field_organisation')->value ?? '';
        }
      }
    }

    // Page title (rendered inline since the block is hidden on /events/*).
    $form['page_title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h1',
      '#value' => $this->t('Event registration'),
      '#weight' => -200,
    ];

    // Two-column layout wrapper inside the form.
    $form['layout'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['row']],
      '#weight' => -100,
    ];

    $form['layout']['form_column'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['col-lg-8', 'order-last', 'order-lg-first']],
    ];

    $form['layout']['sidebar'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['col-lg-4', 'order-first', 'order-lg-last']],
    ];

    // Build event info sidebar.
    $form['layout']['sidebar']['event_info'] = $this->build_event_sidebar($node);

    // Intro text from the event (shown above the form fields).
    if ($node->hasField('field_registration_intro') && !$node->get('field_registration_intro')->isEmpty()) {
      $intro_item = $node->get('field_registration_intro')->first();
      $form['layout']['form_column']['intro'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['mb-4 border-bottom', 'pb-3']],
        '#value' => check_markup($intro_item->value, $intro_item->format ?? 'basic_html'),
        '#weight' => -10,
      ];
    }

    // Always-required fields.
    $form['layout']['form_column']['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First name'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#default_value' => $pre_fill['first_name'] ?? '',
    ];

    $form['layout']['form_column']['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last name'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#default_value' => $pre_fill['last_name'] ?? '',
    ];

    $form['layout']['form_column']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#default_value' => $pre_fill['email'] ?? '',
    ];

    // Configurable optional fields.
    if (in_array('nationality', $required_fields)) {
      $form['layout']['form_column']['nationality'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Nationality'),
        '#required' => TRUE,
        '#maxlength' => 255,
      ];
    }

    if (in_array('date_of_birth', $required_fields)) {
      $form['layout']['form_column']['date_of_birth'] = [
        '#type' => 'date',
        '#title' => $this->t('Date of birth'),
        '#required' => TRUE,
      ];
    }

    if (in_array('document_type', $required_fields)) {
      $form['layout']['form_column']['document_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Document type'),
        '#required' => TRUE,
        '#options' => [
          '' => $this->t('- Select -'),
          'passport' => $this->t('Passport'),
          'identity_card' => $this->t('Identity Card'),
        ],
        '#description' => $this->t('At this event the in presence people are required to present a valid identification document. Please provide the type of identification document you will present at the event.'),
      ];
    }

    if (in_array('document_number', $required_fields)) {
      $form['layout']['form_column']['document_number'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Document number'),
        '#required' => TRUE,
        '#maxlength' => 100,
      ];
    }

    if (in_array('document_expiry_date', $required_fields)) {
      $form['layout']['form_column']['document_expiry_date'] = [
        '#type' => 'date',
        '#title' => $this->t('Document expiry date'),
        '#required' => TRUE,
        '#description' => $this->t('At this event the in presence people are required to present a valid identification document.'),
      ];
    }

    if (in_array('organisation', $required_fields)) {
      $form['layout']['form_column']['organisation'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Organisation / Company'),
        '#required' => TRUE,
        '#maxlength' => 255,
        '#default_value' => $pre_fill['organisation'] ?? '',
      ];
    }

    if (in_array('mobile_number', $required_fields)) {
      $form['layout']['form_column']['mobile_number'] = [
        '#type' => 'tel',
        '#title' => $this->t('Mobile number'),
        '#required' => TRUE,
        '#maxlength' => 20,
      ];
    }

    if (in_array('participation_mode', $required_fields)) {
      $form['layout']['form_column']['participation_mode'] = [
        '#type' => 'radios',
        '#title' => $this->t('Please let us know how you would like to participate'),
        '#options' => [
          'in_person' => $this->t('In Person'),
          'online' => $this->t('Online'),
        ],
        '#required' => TRUE,
      ];
    }

    if (in_array('lunch_attendance', $required_fields)) {
      $form['layout']['form_column']['lunch_attendance'] = [
        '#type' => 'radios',
        '#title' => $this->t('Do you plan to attend the lunch at 12:00 p.m.?'),
        '#options' => [
          'yes' => $this->t('Yes'),
          'no' => $this->t('No'),
        ],
        '#required' => TRUE,
        '#states' => [
          'visible' => [
            ':input[name="participation_mode"]' => ['value' => 'in_person'],
          ],
        ],
      ];
    }

    if (in_array('photo_consent', $required_fields)) {
      $form['layout']['form_column']['photo_consent'] = [
        '#type' => 'radios',
        '#title' => $this->t('Consent to Photos and Videos'),
        '#description' => $this->t('I understand that photos and videos may be taken during the event and consent to their use by COcyber for promotional purposes. I know I can withdraw my consent anytime by contacting <a href="mailto:shafagh.kashef@ulb.be">shafagh.kashef@ulb.be</a>.'),
        '#options' => [
          'agree' => $this->t('I agree to be photographed and recorded.'),
          'disagree' => $this->t('I do NOT agree to be photographed and recorded.'),
        ],
        '#required' => TRUE,
        '#weight' => 85,
      ];
    }

    // Privacy and GDPR consent — values read from module configuration.
    $settings = $this->config_factory->get('lc_event_registration.settings');
    $privacy_path = $settings->get('privacy_url') ?: '/privacy';
    $privacy_url = Url::fromUri('internal:' . $privacy_path)->setAbsolute()->toString();

    $form['layout']['form_column']['consent_privacy'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I confirm that I have read, understood, and agree to the <a href="@url" target="_blank">Privacy Policy</a>.', ['@url' => $privacy_url]),
      '#required' => TRUE,
      '#weight' => 90,
    ];

    $gdpr_text = $settings->get('consent_gdpr_text') ?: '';
    $form['layout']['form_column']['consent_gdpr'] = [
      '#type' => 'checkbox',
      '#title' => ['#markup' => $gdpr_text],
      '#required' => TRUE,
      '#weight' => 91,
    ];

    $form['layout']['form_column']['actions'] = [
      '#weight' => 100,
    ];
    $form['layout']['form_column']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Register'),
      '#button_type' => 'primary',
    ];

    return $form;
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

    // Registration closes.
    if ($node->hasField('field_registration_close_date') && !$node->get('field_registration_close_date')->isEmpty()) {
      $close_date = $node->get('field_registration_close_date')->value;
      $items[] = [
        'icon' => 'bi-clock',
        'label' => $this->t('Registration closes'),
        'value' => date('d F Y', strtotime($close_date)),
      ];
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
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $email = $form_state->getValue('email');
    $event_nid = $form_state->get('event_nid');

    if (!$event_nid) {
      $form_state->setErrorByName('email', $this->t('Invalid event.'));
      return;
    }

    $event = $this->entity_type_manager->getStorage('node')->load($event_nid);
    if (!$event) {
      $form_state->setErrorByName('email', $this->t('Event not found.'));
      return;
    }

    // Check for duplicate registration.
    if ($this->registration_manager->is_already_registered($event, $email)) {
      $form_state->setErrorByName('email', $this->t('This email is already registered for this event.'));
    }

    // Re-check capacity (race condition protection).
    if (!$this->registration_manager->is_registration_open($event)) {
      $form_state->setErrorByName('email', $this->t('Registration is now full. Please try again later.'));
    }

    // Validate lunch_attendance: required when participation_mode is in_person.
    $required_fields = $form_state->get('required_fields') ?? [];
    if (in_array('lunch_attendance', $required_fields)
      && $form_state->getValue('participation_mode') === 'in_person'
      && empty($form_state->getValue('lunch_attendance'))) {
      $form_state->setErrorByName('lunch_attendance', $this->t('Please indicate whether you plan to attend the lunch.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $event_nid = $form_state->get('event_nid');
    $event = $this->entity_type_manager->getStorage('node')->load($event_nid);

    $data = [
      'first_name' => $form_state->getValue('first_name'),
      'last_name' => $form_state->getValue('last_name'),
      'email' => $form_state->getValue('email'),
    ];

    // Collect optional field values.
    foreach (RegistrationManager::OPTIONAL_FIELDS as $field) {
      $value = $form_state->getValue($field);
      if ($value !== NULL && $value !== '') {
        // Convert date fields from Y-m-d (HTML5 date input) to dd-mm-yyyy.
        if (in_array($field, ['date_of_birth', 'document_expiry_date'])) {
          $parsed = \DateTime::createFromFormat('Y-m-d', $value);
          if ($parsed) {
            $value = $parsed->format('d-m-Y');
          }
        }
        $data[$field] = $value;
      }
    }

    $account = $this->currentUser();
    $user = NULL;
    if ($account->isAuthenticated()) {
      $user = $this->entity_type_manager->getStorage('user')->load($account->id());
    }

    $this->registration_manager->register($event, $data, $user);

    $form_state->setRedirect('lc_event_registration.thank_you', ['node' => $event->id()]);
  }

}
