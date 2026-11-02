<?php

/**
 * @file
 * The Quiet Study page: a walk-in room, the second location page.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_location_quiet_study.php
 *   drush cr
 *
 * Run after palefire_location_setup.php, palefire_location_photos.php and
 * palefire_location_split.php (the Space policies, the room fields and the
 * location section types).
 *
 * The Lecture Hall is booked by request; Quiet Study is walk-in, with no
 * photos, no capacity and nothing to book, so its page shows how a location
 * page reads with those parts missing.
 *
 * Content:
 * - The Quiet Study room term (hill_memorial.quiet_study): its policies
 *   (Open to all, No food or drink) and sound (quiet), filled in only where
 *   empty. Capacity is left for editors.
 * - An unpublished Modular page "Quiet Study" at /v2/special/quietstudy:
 *   Location (no button), Hours and directions (Hill Memorial's LibCal
 *   hours, 236), House rules. No photos or booking section.
 *
 * Facts: lib.lsu.edu/special (individual, quiet study on the second floor;
 * walk in any time the building is open; no food or drink).
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

foreach (['location_space', 'location_visit', 'location_rules'] as $required) {
  if (!ParagraphsType::load($required)) {
    throw new \RuntimeException("Paragraph type $required not found. Run palefire_location_setup.php and palefire_location_split.php first.");
  }
}
$terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

// 1. The Quiet Study room. ---------------------------------------------------
$found = $terms->getQuery()->accessCheck(FALSE)
  ->condition('vid', 'building_space')
  ->condition('field_map_key', 'hill_memorial.quiet_study')
  ->range(0, 1)->execute();
$room = $found ? $terms->load(reset($found)) : NULL;
if (!$room) {
  throw new \RuntimeException('Room term hill_memorial.quiet_study not found. Run palefire_building_map_setup.php first.');
}
$policy_ids = [];
foreach (['walk_in', 'hill_no_food'] as $key) {
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
  echo "Filled in the Quiet Study room's policies and sound\n";
}

// 2. The Quiet Study page. ---------------------------------------------------
$alias = '/v2/special/quietstudy';
if (\Drupal::service('path_alias.manager')->getPathByAlias($alias) !== $alias) {
  echo "Page $alias exists; left as it is. Done. Run: drush cr\n";
  return;
}
$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');
$sections_field = isset($node_fields['field_scroll_section']) ? 'field_scroll_section' : 'field_sections';
if (!isset($node_fields[$sections_field])) {
  throw new \RuntimeException('Modular page has no sections field.');
}
$titled = static fn (string $type, string $title) => Paragraph::create(['type' => $type, 'field_section_title' => $title]);
$values = [
  'type' => 'modular_page',
  'title' => 'Quiet Study',
  'status' => 0,
  $sections_field => [
    Paragraph::create(['type' => 'location_space', 'field_location_space' => ['target_id' => $room->id()]]),
    $titled('location_visit', 'Hours and directions'),
    $titled('location_rules', 'House rules'),
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
$node->setRevisionLogMessage('Quiet Study location page (palefire_location_quiet_study.php).');
$node->save();
echo 'Created unpublished page: node/' . $node->id() . " ($alias)\n";
echo "Review it, then publish. Run: drush cr\n";
