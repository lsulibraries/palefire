<?php

/**
 * @file
 * Location pages: three kinds of access, capacity as a number, and area.
 *
 *   drush php:script scripts/palefire_location_access_area.php
 *   drush cr
 *
 * Run after palefire_building_map_setup.php and palefire_location_setup.php.
 *
 * - Access (field_space_access, on room terms and map room overrides) gets
 *   three options: walk_in "Walk in", appointment "Book online" (the key
 *   stays, so nothing else moves) and rental "Room rental (fee)". The
 *   Lecture Hall becomes Room rental, on its room term and any map override
 *   that said appointment.
 * - Capacity's help text asks for a number (shown "~100"); the Lecture
 *   Hall's "Fewer than 100 people" becomes "100". Other words are left.
 * - A new room field, Estimated area (field_space_area, whole square feet),
 *   shown in the ledger as "~2,400 sq ft". Left empty: fill it in on each
 *   room's Building spaces term.
 *
 * Safe to re-run: anything already done is left as it is.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

if (PHP_SAPI !== 'cli') {
  return;
}

$entity_types = \Drupal::entityTypeManager();
$terms = $entity_types->getStorage('taxonomy_term');

// 1. Access options. -----------------------------------------------------------
$access_values = ['walk_in' => 'Walk in', 'appointment' => 'Book online', 'rental' => 'Room rental (fee)'];
foreach (['taxonomy_term', 'paragraph'] as $entity_type) {
  $config = FieldStorageConfig::loadByName($entity_type, 'field_space_access');
  if ($config && $config->getSetting('allowed_values') != $access_values) {
    $config->setSetting('allowed_values', $access_values)->save();
    echo "Access options updated on $entity_type\n";
  }
}

// 2. The Lecture Hall: Room rental, capacity 100. -------------------------------
$ids = $terms->getQuery()->accessCheck(FALSE)
  ->condition('vid', 'building_space')
  ->condition('field_map_key', 'hill_memorial.lecture_hall')
  ->range(0, 1)->execute();
$room = $ids ? $terms->load(reset($ids)) : NULL;
if (!$room) {
  throw new \RuntimeException('Room term hill_memorial.lecture_hall not found.');
}
$changed = FALSE;
if (in_array((string) $room->get('field_space_access')->value, ['', 'appointment'], TRUE)) {
  $room->set('field_space_access', 'rental');
  $changed = TRUE;
  echo "Lecture Hall: Room rental\n";
}
if ($room->hasField('field_space_capacity') && $room->get('field_space_capacity')->value === 'Fewer than 100 people') {
  $room->set('field_space_capacity', '100');
  $changed = TRUE;
  echo "Lecture Hall: capacity 100\n";
}
if ($changed) {
  $room->save();
}
if ($entity_types->hasDefinition('paragraph') && FieldStorageConfig::loadByName('paragraph', 'field_room_key')) {
  $paragraphs = $entity_types->getStorage('paragraph');
  $ids = $paragraphs->getQuery()->accessCheck(FALSE)
    ->condition('type', 'map_room_override')
    ->condition('field_room_key', 'hill_memorial.lecture_hall')
    ->condition('field_space_access', 'appointment')
    ->execute();
  foreach ($paragraphs->loadMultiple($ids) as $override) {
    $override->set('field_space_access', 'rental')->save();
    echo "Map override {$override->id()}: Room rental\n";
  }
}

// 3. Capacity's help text. ------------------------------------------------------
$capacity = FieldConfig::loadByName('taxonomy_term', 'building_space', 'field_space_capacity');
$help = 'A number, shown as "~100"; or short words, e.g. "2 to 6 people".';
if ($capacity && $capacity->getDescription() !== $help) {
  $capacity->setDescription($help)->save();
  echo "Capacity help text updated\n";
}

// 4. Estimated area. ------------------------------------------------------------
if (!FieldStorageConfig::loadByName('taxonomy_term', 'field_space_area')) {
  FieldStorageConfig::create([
    'field_name' => 'field_space_area',
    'entity_type' => 'taxonomy_term',
    'type' => 'integer',
    'settings' => ['unsigned' => TRUE, 'size' => 'normal'],
  ])->save();
  echo "Created storage taxonomy_term.field_space_area\n";
}
if (!FieldConfig::loadByName('taxonomy_term', 'building_space', 'field_space_area')) {
  FieldConfig::create([
    'field_name' => 'field_space_area',
    'entity_type' => 'taxonomy_term',
    'bundle' => 'building_space',
    'label' => 'Estimated area',
    'description' => 'Rooms only. Square feet, a round number; shown as "~2,400 sq ft".',
    'settings' => ['min' => 1, 'max' => NULL, 'prefix' => '', 'suffix' => ' sq ft'],
  ])->save();
  echo "Added field_space_area to building_space\n";
}
$repo = \Drupal::service('entity_display.repository');
$form = $repo->getFormDisplay('taxonomy_term', 'building_space', 'default');
if (!$form->getComponent('field_space_area')) {
  $weight = $form->getComponent('field_space_capacity')['weight'] ?? 50;
  $form->setComponent('field_space_area', [
    'type' => 'number',
    'weight' => $weight + 1,
    'settings' => ['placeholder' => 'e.g. 2400'],
  ])->save();
  echo "Estimated area on the room form, after Capacity\n";
}
// The template prints it: keep it out of the default view display.
$view = $repo->getViewDisplay('taxonomy_term', 'building_space', 'default');
if ($view->getComponent('field_space_area')) {
  $view->removeComponent('field_space_area')->save();
}

echo "Done. Run: drush cr\n";
