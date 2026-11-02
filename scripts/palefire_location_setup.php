<?php

/**
 * @file
 * Location pages: space policies kept in one place, three section types,
 * and the Lecture Hall page.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_location_setup.php
 *   drush php:script themes/custom/palefire/scripts/palefire_location_setup.php -- page=0
 *   drush cr
 *
 * Run palefire_building_map_setup.php first (it makes the Building spaces
 * vocabulary and the Hill Memorial room terms).
 *
 * One source of truth: a policy ("Pencils only", "No food or drink") is a
 * term in Space policies. Rooms (Building spaces terms) list the policies
 * that apply to them; location pages, their house rules and any future
 * Find-us card read them from the room. Edit a policy's wording once and
 * every room that uses it changes.
 *
 * Taxonomy: Space policies (space_policy)
 *   name               the rule, as visitors read it ("Pencils only")
 *   description        optional detail under it (plain text)
 *   field_policy_state allowed | required | not_allowed
 *   field_policy_icon  which mark (templates/includes/space-icon.html.twig)
 *   field_policy_key   machine name the setup uses to find its own terms
 *
 * Building spaces (building_space) room terms gain:
 *   field_space_policies  Space policies that apply, in order
 *   field_space_sound     quiet | conversation | collaborative
 *   field_space_capacity  short text, e.g. "Fewer than 100 people"
 *
 * Paragraph types (allowed wherever bento_search is):
 *   location_space    "Location": the top of a room's page. The room's
 *                     floor, access, sound and capacity, its policies as
 *                     marks with tooltips, today's hours (the page's
 *                     LibCal location), the building map with the room
 *                     marked, and its Booking link as the button.
 *                     field_location_space picks the room. The other parts
 *                     are sections of their own (palefire_location_split.php).
 *   location_rules    "Location: house rules": the room's policies in three
 *                     columns (Allowed, Required, Not allowed) and its sound
 *                     level. Room optional: empty, the page's Location
 *                     section's room.
 *   location_booking  "Location: booking": text and a booking link, with
 *                     the room's capacity and its Required policies.
 * Each also gets the shared section fields from bento_search (title,
 * anchor, snap, height, theme, hide from TOC).
 *
 * Content (skipped with page=0):
 * - Starter Space policies (below), each created once.
 * - The Lecture Hall room term (hill_memorial.lecture_hall): its policies,
 *   sound and capacity, filled in only where empty.
 * - An unpublished Modular page "Lecture Hall" at /v2/special/lecturehall:
 *   one Location section with its booking text and link, Hill Memorial's
 *   LibCal hours (236), unless a page at that alias, or at its first one
 *   (/v2/special/spaces/lecture-hall), exists. palefire_location_split.php
 *   then makes each part a section of its own.
 *
 * Lecture Hall facts: LSU Libraries' meeting space policy (fewer than 100
 * people; university-affiliated groups, others only when the Libraries
 * co-sponsor; podium, microphone, projection screen, tables and chairs; at
 * least two weeks' notice, possibly a service charge; after-hours events by
 * permission, possibly a fee; alcohol needs an LSU permit) and Policy
 * Memorandum 14 (food and drink allowed in the Lecture Hall, nowhere else
 * in Hill but the staff lounge).
 *
 * Safe to re-run: anything that already exists is left as it is.
 *
 * List fields take allowed_values as [key => label], the runtime format
 * FieldStorageConfig::create() expects (not the YAML [['value', 'label']]).
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

if (PHP_SAPI !== 'cli') {
  return;
}

$args = [];
foreach ((isset($extra) && is_array($extra)) ? $extra : [] as $arg) {
  if (str_contains((string) $arg, '=')) {
    [$k, $v] = explode('=', (string) $arg, 2);
    $args[trim($k)] = trim($v);
  }
}
$make_page = ($args['page'] ?? '1') !== '0';

$source = 'bento_search';
if (!ParagraphsType::load($source)) {
  throw new \RuntimeException('Paragraph type bento_search not found (palefire_modular).');
}
if (!Vocabulary::load('building_space')) {
  throw new \RuntimeException('Vocabulary building_space not found. Run palefire_building_map_setup.php first.');
}
foreach (['options', 'link', 'text'] as $module) {
  if (!\Drupal::moduleHandler()->moduleExists($module)) {
    \Drupal::service('module_installer')->install([$module]);
    echo "Enabled $module\n";
  }
}

$repo = \Drupal::service('entity_display.repository');

// Helpers. ------------------------------------------------------------------
$storage = function (string $entity_type, string $name, string $type, array $settings = [], int $cardinality = 1): void {
  if (!FieldStorageConfig::loadByName($entity_type, $name)) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => $entity_type,
      'type' => $type,
      'settings' => $settings,
      'cardinality' => $cardinality,
    ])->save();
    echo "Created storage $entity_type.$name\n";
  }
};
$field = function (string $entity_type, string $bundle, string $name, string $label, string $description = '', bool $required = FALSE, array $settings = []): bool {
  if (FieldConfig::loadByName($entity_type, $bundle, $name)) {
    return FALSE;
  }
  FieldConfig::create([
    'field_name' => $name,
    'entity_type' => $entity_type,
    'bundle' => $bundle,
    'label' => $label,
    'description' => $description,
    'required' => $required,
    'settings' => $settings,
  ])->save();
  echo "Added $name to $entity_type.$bundle\n";
  return TRUE;
};
$widget = static function (string $entity_type, string $bundle, string $name, array $component) use ($repo): void {
  $form = $repo->getFormDisplay($entity_type, $bundle, 'default');
  if (!$form->getComponent($name)) {
    $weight = (int) max(array_column($form->getComponents(), 'weight') ?: [0]);
    $form->setComponent($name, $component + ['weight' => $weight + 1])->save();
  }
  // Templates print these: keep them out of the default view display.
  $view = $repo->getViewDisplay($entity_type, $bundle, 'default');
  if ($view->getComponent($name)) {
    $view->removeComponent($name)->save();
  }
};

// 1. Space policies vocabulary. --------------------------------------------
if (!Vocabulary::load('space_policy')) {
  Vocabulary::create([
    'vid' => 'space_policy',
    'name' => 'Space policies',
    'description' => 'Rules for library spaces ("Pencils only", "No food or drink"). Rooms in Building spaces list the ones that apply; location pages show them. Edit one here and every room using it changes.',
  ])->save();
  echo "Created vocabulary space_policy\n";
}
$states = [
  'allowed' => 'Allowed (the mark on its own)',
  'required' => 'Required (the mark in a square)',
  'not_allowed' => 'Not allowed (the mark struck through)',
];
// Keys match templates/includes/space-icon.html.twig.
$icons = [
  'walk' => 'Person walking (open to all)',
  'clock' => 'Clock (appointment, time)',
  'moon' => 'Crescent moon (after hours)',
  'calendar' => 'Calendar with a check (reservation)',
  'calendar_walk' => 'Calendar with an arrow (walk up, bookings first)',
  'card' => 'ID card (account, eligibility)',
  'key' => 'Key',
  'food' => 'Fork and knife (food)',
  'cup_lid' => 'Cup with a lid (lidded drinks)',
  'cup' => 'Open cup (drinks)',
  'glass' => 'Wine glass (alcohol)',
  'pencil' => 'Pencil',
  'pen' => 'Pen nib (pens, ink)',
  'locker' => 'Locker',
  'bag' => 'Bag',
  'camera' => 'Camera (photos)',
  'flash' => 'Lightning bolt (flash)',
  'book_lock' => 'Book with a lock (stays in the room)',
  'step_free' => 'Wheelchair (step-free access)',
  'elevator' => 'Elevator',
  'plug' => 'Plug (power)',
  'screen' => 'Screen (display, projector)',
  'mic' => 'Microphone (podium, sound)',
  'laptop' => 'Laptop',
  'talk' => 'Speech bubbles (talking welcome)',
  'group' => 'Three people (groups)',
];
$storage('taxonomy_term', 'field_policy_state', 'list_string', ['allowed_values' => $states]);
$storage('taxonomy_term', 'field_policy_icon', 'list_string', ['allowed_values' => $icons]);
$storage('taxonomy_term', 'field_policy_key', 'string', ['max_length' => 64]);
$field('taxonomy_term', 'space_policy', 'field_policy_state', 'State', 'How the mark is drawn. Shape, not color, tells the states apart.', TRUE);
$field('taxonomy_term', 'space_policy', 'field_policy_icon', 'Mark', 'The icon beside the rule.', TRUE);
$field('taxonomy_term', 'space_policy', 'field_policy_key', 'Machine name', 'Set by the setup script for its starter policies; leave empty on new ones.');
$widget('taxonomy_term', 'space_policy', 'field_policy_state', ['type' => 'options_buttons']);
$widget('taxonomy_term', 'space_policy', 'field_policy_icon', ['type' => 'options_select']);
$widget('taxonomy_term', 'space_policy', 'field_policy_key', ['type' => 'string_textfield', 'settings' => ['size' => 30]]);

// 2. Room fields on Building spaces. ----------------------------------------
$storage('taxonomy_term', 'field_space_policies', 'entity_reference', ['target_type' => 'taxonomy_term'], -1);
$storage('taxonomy_term', 'field_space_sound', 'list_string', ['allowed_values' => [
  'quiet' => 'Quiet',
  'conversation' => 'Conversation',
  'collaborative' => 'Collaborative',
]]);
$storage('taxonomy_term', 'field_space_capacity', 'string', ['max_length' => 80]);
$field('taxonomy_term', 'building_space', 'field_space_policies', 'Policies', 'Rules for this room, from Space policies, in the order to show them. Location pages group them as Allowed, Required and Not allowed.', FALSE, [
  'handler' => 'default:taxonomy_term',
  'handler_settings' => ['target_bundles' => ['space_policy' => 'space_policy'], 'sort' => ['field' => 'name', 'direction' => 'asc'], 'auto_create' => FALSE],
]);
$field('taxonomy_term', 'building_space', 'field_space_sound', 'Sound level', 'Quiet, conversation or collaborative. Rooms only.');
$field('taxonomy_term', 'building_space', 'field_space_capacity', 'Capacity', 'Short, e.g. "Fewer than 100 people" or "2 to 6 people".');
$widget('taxonomy_term', 'building_space', 'field_space_policies', [
  'type' => 'entity_reference_autocomplete',
  'settings' => ['match_operator' => 'CONTAINS', 'match_limit' => 10, 'size' => 60, 'placeholder' => 'Start typing a policy'],
]);
$widget('taxonomy_term', 'building_space', 'field_space_sound', ['type' => 'options_buttons']);
$widget('taxonomy_term', 'building_space', 'field_space_capacity', ['type' => 'string_textfield', 'settings' => ['size' => 40]]);

// 3. Paragraph types. --------------------------------------------------------
$types = [
  'location_space' => ['Location', 'A room\'s floor, access, sound, policies at a glance, hours and the building map with the room marked. Put it first on a location page.'],
  'location_rules' => ['Location: house rules', 'The room\'s policies, as Allowed, Required and Not allowed, with its sound level.'],
  'location_booking' => ['Location: booking', 'How to book the room: text, a booking link, the room\'s capacity and what booking needs.'],
];
$source_form = $repo->getFormDisplay('paragraph', $source, 'default');
$source_view = $repo->getViewDisplay('paragraph', $source, 'default');
$shared = ['field_section_title', 'field_section_anchor', 'field_section_snap', 'field_section_height', 'field_section_theme', 'field_section_toc_hide'];

$storage('paragraph', 'field_location_space', 'entity_reference', ['target_type' => 'taxonomy_term']);
$storage('paragraph', 'field_booking_body', 'text_long');
$storage('paragraph', 'field_booking_link', 'link');
$room_ref = [
  'handler' => 'default:taxonomy_term',
  'handler_settings' => ['target_bundles' => ['building_space' => 'building_space'], 'sort' => ['field' => 'name', 'direction' => 'asc'], 'auto_create' => FALSE],
];

foreach ($types as $type => [$label, $description]) {
  if (!ParagraphsType::load($type)) {
    ParagraphsType::create(['id' => $type, 'label' => $label, 'description' => $description])->save();
    echo "Created paragraph type $type\n";
  }
  $form = $repo->getFormDisplay('paragraph', $type, 'default');
  $view = $repo->getViewDisplay('paragraph', $type, 'default');
  foreach ($shared as $name) {
    $from = FieldConfig::loadByName('paragraph', $source, $name);
    if (!$from) {
      echo "Skipped $name: bento_search doesn't have it.\n";
      continue;
    }
    if (!FieldConfig::loadByName('paragraph', $type, $name)) {
      FieldConfig::create([
        'field_name' => $name,
        'entity_type' => 'paragraph',
        'bundle' => $type,
        'label' => $from->getLabel(),
        'description' => $from->getDescription(),
        'required' => $from->isRequired(),
        'settings' => $from->getSettings(),
        'default_value' => $from->getDefaultValueLiteral(),
      ])->save();
      echo "Added $name to paragraph.$type\n";
    }
    if (($component = $source_form->getComponent($name)) && !$form->getComponent($name)) {
      $form->setComponent($name, $component);
    }
    if ($component = $source_view->getComponent($name)) {
      $view->setComponent($name, $component);
    }
    else {
      $view->removeComponent($name);
    }
  }
  $form->save();
  $view->save();

  $field('paragraph', $type, 'field_location_space', 'Room', $type === 'location_space'
    ? 'The room this page is about (Building spaces). Its policies, sound level and capacity are edited on the room.'
    : 'Leave empty to use the room of this page\'s Location section.', $type === 'location_space', $room_ref);
  $widget('paragraph', $type, 'field_location_space', [
    'type' => 'entity_reference_autocomplete',
    'settings' => ['match_operator' => 'CONTAINS', 'match_limit' => 10, 'size' => 60, 'placeholder' => 'Start typing a room'],
  ]);
}
// Booking fields on Booking, and on Location, which shows its own House
// rules and Booking under the map (one section per room page).
foreach (['location_booking', 'location_space'] as $type) {
  $field('paragraph', $type, 'field_booking_body', 'Booking text', 'How booking works: notice, who decides, costs. Empty: no Booking part.');
  $field('paragraph', $type, 'field_booking_link', 'Booking link', 'Where to request the room. The link text is the button\'s label.', FALSE, ['title' => 2, 'link_type' => 17]);
  $widget('paragraph', $type, 'field_booking_body', ['type' => 'text_textarea', 'settings' => ['rows' => 5]]);
  $widget('paragraph', $type, 'field_booking_link', ['type' => 'link_default']);
}

// Allow the three wherever bento_search is allowed.
foreach (FieldConfig::loadMultiple() as $config) {
  if (!in_array($config->getType(), ['entity_reference_revisions', 'entity_reference'], TRUE)
    || $config->getSetting('target_type') !== 'paragraph') {
    continue;
  }
  $handler = $config->getSetting('handler_settings') ?: [];
  $bundles = $handler['target_bundles'] ?? NULL;
  if (($handler['negate'] ?? FALSE) || !is_array($bundles) || !isset($bundles[$source])) {
    continue;
  }
  $changed = FALSE;
  foreach (array_keys($types) as $type) {
    if (isset($handler['target_bundles'][$type])) {
      continue;
    }
    $handler['target_bundles'][$type] = $type;
    if (isset($handler['target_bundles_drag_drop'])) {
      $handler['target_bundles_drag_drop'][$type] = ['enabled' => TRUE, 'weight' => count($handler['target_bundles_drag_drop'])];
    }
    $changed = TRUE;
  }
  if ($changed) {
    $config->setSetting('handler_settings', $handler)->save();
    echo 'Allowed the location sections in ' . $config->id() . "\n";
  }
}

if (!$make_page) {
  echo "Config done (page=0: no content). Run: drush cr\n";
  return;
}

// 4. Starter policies. -------------------------------------------------------
// [key => [name, state, icon, detail]]. Created once; editors own them after.
$policies = [
  'walk_in' => ['Open to all', 'allowed', 'walk', 'Walk in any time the building is open.'],
  'by_appointment' => ['By appointment', 'required', 'clock', 'Make an appointment before you come.'],
  'reservation' => ['Reservation only', 'required', 'calendar', 'Request the room before you come.'],
  'walk_up' => ['Walk up, bookings first', 'allowed', 'calendar_walk', 'Use it when it is free. A reservation always takes priority.'],
  'research_account' => ['Research account', 'required', 'card', 'Create one before or when you arrive.'],
  'food_drink_ok' => ['Food and drink allowed', 'allowed', 'food', ''],
  'lidded_drinks' => ['Lidded drinks', 'allowed', 'cup_lid', 'Drinks need a lid.'],
  'hill_no_food' => ['No food or drink', 'not_allowed', 'food', 'Anywhere in Hill Memorial Library but the Lecture Hall.'],
  'pencils_only' => ['Pencils only', 'required', 'pencil', 'Pens and ink stay in your locker.'],
  'bags_locker' => ['Bags in a locker', 'required', 'locker', 'Bags, cases, coats and umbrellas go in a free locker in the lobby.'],
  'photos_form' => ['Photos allowed', 'allowed', 'camera', 'After you fill in a Camera User Policy form at the desk.'],
  'no_flash' => ['No flash', 'not_allowed', 'flash', ''],
  'non_circulating' => ['Materials stay in the room', 'required', 'book_lock', 'Special Collections materials don\'t circulate.'],
  'step_free' => ['Step-free entrance', 'allowed', 'step_free', 'The front entrance, facing the Quad.'],
  'lsu_groups' => ['University groups', 'required', 'card', 'For university-affiliated groups. Others only when LSU Libraries co-sponsor the event.'],
  'two_weeks' => ['Two weeks\' notice', 'required', 'clock', 'Request the date at least two weeks ahead.'],
  'podium_av' => ['Podium, microphone and screen', 'allowed', 'mic', 'A projection screen, tables and chairs too.'],
  'after_hours' => ['After hours by permission', 'required', 'moon', 'Events outside library hours need permission and may have a fee.'],
  'alcohol_permit' => ['Alcohol by LSU permit', 'required', 'glass', 'Serving alcohol needs a special permit from LSU.'],
];
$terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$policy_ids = [];
foreach ($policies as $key => [$name, $state, $icon, $detail]) {
  $found = $terms->getQuery()->accessCheck(FALSE)
    ->condition('vid', 'space_policy')
    ->condition('field_policy_key', $key)
    ->range(0, 1)->execute();
  if ($found) {
    $policy_ids[$key] = (int) reset($found);
    continue;
  }
  $term = Term::create([
    'vid' => 'space_policy',
    'name' => $name,
    'description' => ['value' => $detail, 'format' => 'plain_text'],
    'field_policy_state' => $state,
    'field_policy_icon' => $icon,
    'field_policy_key' => $key,
  ]);
  $term->save();
  $policy_ids[$key] = (int) $term->id();
  echo "Created policy: $name\n";
}

// 5. The Lecture Hall room. --------------------------------------------------
$found = $terms->getQuery()->accessCheck(FALSE)
  ->condition('vid', 'building_space')
  ->condition('field_map_key', 'hill_memorial.lecture_hall')
  ->range(0, 1)->execute();
$room = $found ? $terms->load(reset($found)) : NULL;
if (!$room) {
  throw new \RuntimeException('Room term hill_memorial.lecture_hall not found. Run palefire_building_map_setup.php first.');
}
$room_changed = FALSE;
if ($room->get('field_space_policies')->isEmpty()) {
  $room->set('field_space_policies', array_map(fn ($key) => ['target_id' => $policy_ids[$key]], [
    'reservation', 'lsu_groups', 'two_weeks', 'food_drink_ok', 'podium_av', 'after_hours', 'alcohol_permit', 'step_free',
  ]));
  $room_changed = TRUE;
}
if ($room->get('field_space_sound')->isEmpty()) {
  $room->set('field_space_sound', 'collaborative');
  $room_changed = TRUE;
}
if ($room->get('field_space_capacity')->isEmpty()) {
  $room->set('field_space_capacity', 'Fewer than 100 people');
  $room_changed = TRUE;
}
if ($room_changed) {
  $room->save();
  echo "Filled in the Lecture Hall room's policies, sound and capacity\n";
}

// 6. The Lecture Hall page. --------------------------------------------------
$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');
$sections_field = isset($node_fields['field_scroll_section']) ? 'field_scroll_section' : 'field_sections';
if (!isset($node_fields[$sections_field])) {
  throw new \RuntimeException('Modular page has no sections field.');
}
$alias = '/v2/special/lecturehall';
$page_found = FALSE;
foreach ([$alias, '/v2/special/spaces/lecture-hall'] as $taken) {
  $path = \Drupal::service('path_alias.manager')->getPathByAlias($taken);
  if ($path === $taken) {
    continue;
  }
  $page_found = TRUE;
  // The page exists: left as it is. (This used to merge its House rules
  // and Booking sections into the Location section; location pages are now
  // one section per part, palefire_location_split.php.)
  echo "Page $taken exists; left as it is.\n";
  break;
}
if ($page_found) {
  echo "Done. Run: drush cr\n";
  return;
}
$format = NULL;
foreach (filter_formats() as $id => $candidate) {
  if ($id === 'wysiwyg' || strcasecmp((string) $candidate->label(), 'WYSIWYG') === 0) {
    $format = $id;
    break;
  }
}
$format = $format ?? 'basic_html';

$sections = [
  Paragraph::create([
    'type' => 'location_space',
    'field_location_space' => ['target_id' => $room->id()],
    'field_booking_body' => [
      'value' => '<p>Request your date early, at least two weeks ahead. There may be a service charge, and events outside library hours need permission and may have an extra fee.</p><p>The hall is for university-affiliated groups. Other groups can use it when LSU Libraries co-sponsor the event.</p>',
      'format' => $format,
    ],
    'field_booking_link' => [
      'uri' => 'https://lib.lsu.edu/special#reservelecturehall',
      'title' => 'Request the Lecture Hall',
    ],
  ]),
];
$values = [
  'type' => 'modular_page',
  'title' => 'Lecture Hall',
  'status' => 0,
  $sections_field => $sections,
  'path' => ['alias' => $alias, 'pathauto' => 0],
  'field_hours_location' => '236',
  'field_location_name' => 'Hill Memorial Library',
  'field_location_query' => 'Hill Memorial Library, LSU, Baton Rouge, LA',
];
foreach (array_keys($values) as $name) {
  if (str_starts_with($name, 'field_') && !isset($node_fields[$name])) {
    unset($values[$name]);
  }
}
$node = Node::create($values);
$node->setRevisionLogMessage('Lecture Hall location page (palefire_location_setup.php).');
$node->save();
echo 'Created unpublished page: node/' . $node->id() . " ($alias)\n";
echo "Review it, then publish. Run: drush cr\n";
