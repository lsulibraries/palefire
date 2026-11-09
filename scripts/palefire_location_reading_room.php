<?php

/**
 * @file
 * The Reading Room page: where Special Collections materials are read.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_location_reading_room.php
 *   drush cr
 *
 * Run after palefire_location_setup.php, palefire_location_photos.php and
 * palefire_location_split.php (the Space policies, the room fields and the
 * location section types).
 *
 * The Quick Start Guide's "Read it at Hill" stop (option A's last) links
 * here (includes/guide-request-paths.html.twig).
 *
 * Content:
 * - The Reading Room room term (hill_memorial.reading_room): its policies
 *   and sound (quiet), filled in only where empty. Capacity and photos are
 *   left for editors.
 * - An unpublished Modular page "Reading Room" at /v2/special/readingroom,
 *   as the Lecture Hall's: Location (its button goes to the Special
 *   Collections Request System), Hours and directions (Hill Memorial's
 *   LibCal hours, 236), House rules, and "Book a visit".
 *
 * Facts: the How to Use Special Collections guide
 * (palefire_guide_special_collections.php): open to the LSU community and to
 * researchers from anywhere; appointments recommended, not required; pencils
 * only; bags, coats, food and drink in a locker; photos without flash after a
 * Camera User Policy form; requests reach the room in 5 to 15 minutes, off-
 * site collections in 3 to 7 days.
 *
 * Safe to re-run: anything that already exists is left as it is.
 */

declare(strict_types=1);

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;

if (PHP_SAPI !== 'cli') {
  return;
}

foreach (['location_space', 'location_visit', 'location_rules', 'location_booking'] as $required) {
  if (!ParagraphsType::load($required)) {
    throw new \RuntimeException("Paragraph type $required not found. Run palefire_location_setup.php and palefire_location_split.php first.");
  }
}
$terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

// 1. The Reading Room. --------------------------------------------------------
$found = $terms->getQuery()->accessCheck(FALSE)
  ->condition('vid', 'building_space')
  ->condition('field_map_key', 'hill_memorial.reading_room')
  ->range(0, 1)->execute();
$room = $found ? $terms->load(reset($found)) : NULL;
if (!$room) {
  throw new \RuntimeException('Room term hill_memorial.reading_room not found. Run palefire_building_map_setup.php first.');
}
$policy_ids = [];
foreach (['walk_in', 'research_account', 'non_circulating', 'pencils_only', 'bags_locker', 'photos_form', 'no_flash', 'hill_no_food'] as $key) {
  $found = $terms->getQuery()->accessCheck(FALSE)
    ->condition('vid', 'space_policy')
    ->condition('field_policy_key', $key)
    ->range(0, 1)->execute();
  if (!$found) {
    throw new \RuntimeException("Space policy $key not found. Run palefire_location_setup.php first.");
  }
  $policy_ids[] = ['target_id' => (int) reset($found)];
}
$room_changed = FALSE;
if ($room->get('field_space_policies')->isEmpty()) {
  $room->set('field_space_policies', $policy_ids);
  $room_changed = TRUE;
}
if ($room->get('field_space_sound')->isEmpty()) {
  $room->set('field_space_sound', 'quiet');
  $room_changed = TRUE;
}
if ($room_changed) {
  $room->save();
  echo "Filled in the Reading Room's policies and sound\n";
}

// 2. The Reading Room page. ---------------------------------------------------
$alias = '/v2/special/readingroom';
if (\Drupal::service('path_alias.manager')->getPathByAlias($alias) !== $alias) {
  echo "Page $alias exists; left as it is. Done. Run: drush cr\n";
  return;
}
$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');
$sections_field = isset($node_fields['field_scroll_section']) ? 'field_scroll_section' : 'field_sections';
if (!isset($node_fields[$sections_field])) {
  throw new \RuntimeException('Modular page has no sections field.');
}
$format = NULL;
foreach (filter_formats() as $id => $candidate) {
  if ($id === 'wysiwyg' || strcasecmp((string) $candidate->label(), 'WYSIWYG') === 0) {
    $format = $id;
    break;
  }
}
$format = $format ?? 'basic_html';
$request_system = 'https://specialcollections.lib.lsu.edu/logon';

$titled = static fn (string $type, string $title, array $values = []) => Paragraph::create(['type' => $type, 'field_section_title' => $title] + $values);
$values = [
  'type' => 'modular_page',
  'title' => 'Reading Room',
  'status' => 0,
  $sections_field => [
    Paragraph::create([
      'type' => 'location_space',
      'field_location_space' => ['target_id' => $room->id()],
      'field_booking_link' => ['uri' => $request_system, 'title' => 'Book a visit'],
    ]),
    $titled('location_visit', 'Hours and directions'),
    $titled('location_rules', 'House rules'),
    $titled('location_booking', 'Book a visit', [
      'field_booking_body' => [
        'value' => '<p>Find the item in the catalog and choose Request Item, then pick an appointment on the request form. Appointments aren’t required, but with one your materials are waiting when you arrive. Walk-in requests usually reach the room in 5 to 15 minutes.</p><p>Some collections are stored off campus and take 3 to 7 days to retrieve, so request those well ahead. Anyone can sign in or register as a Guest: you don’t need to be affiliated with LSU.</p>',
        'format' => $format,
      ],
      'field_booking_link' => ['uri' => $request_system, 'title' => 'Sign in to request'],
    ]),
  ],
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
$node->setRevisionLogMessage('Reading Room location page (palefire_location_reading_room.php).');
$node->save();
echo 'Created unpublished page: node/' . $node->id() . " ($alias)\n";
echo "Review it, then publish. Run: drush cr\n";
