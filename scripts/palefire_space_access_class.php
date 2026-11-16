<?php

/**
 * @file
 * Access "For classes": rooms faculty use for class partnerships.
 *
 *   drush php:script scripts/palefire_space_access_class.php
 *   drush cr
 *
 * The McIlhenny Room isn't booked by the public: faculty plan a class with
 * Special Collections. Its Access said "Book online", with no booking on
 * its page. This adds:
 * - "class" (For classes) to field_space_access, beside Walk in, Book
 *   online and Event rental (the theme's _palefire_space_access_kinds());
 * - Access "class" on the McIlhenny Room's Building spaces term
 *   (hill_memorial.mcilhenny_room), and on any map override of that room,
 *   when they still say "appointment". A room set to anything else is left
 *   as it is.
 *
 * Only adds: safe to re-run.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldStorageConfig;

if (PHP_SAPI !== 'cli') {
  return;
}

$room_key = 'hill_memorial.mcilhenny_room';

// 1. The Access value, on terms and on map overrides. -----------------------

foreach (['taxonomy_term', 'paragraph'] as $entity_type) {
  $storage = FieldStorageConfig::loadByName($entity_type, 'field_space_access');
  if (!$storage) {
    continue;
  }
  $allowed = $storage->getSetting('allowed_values') ?? [];
  if (!isset($allowed['class'])) {
    // After Book online, before Event rental.
    $sorted = [];
    foreach ($allowed as $key => $label) {
      if ($key === 'rental') {
        $sorted['class'] = 'For classes (faculty plan a class with us)';
      }
      $sorted[$key] = $label;
    }
    $sorted += ['class' => 'For classes (faculty plan a class with us)'];
    $storage->setSetting('allowed_values', $sorted)->save();
    echo "Added \"class\" to {$entity_type}.field_space_access" . PHP_EOL;
  }
}

// 2. The McIlhenny Room. ------------------------------------------------------

$entity_types = \Drupal::entityTypeManager();
$terms = $entity_types->getStorage('taxonomy_term');
$ids = $terms->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', 'building_space')
  ->condition('field_map_key', $room_key)
  ->execute();
if (!$ids) {
  echo "No Building spaces term with map key {$room_key}." . PHP_EOL;
}
foreach ($terms->loadMultiple($ids) as $term) {
  $access = (string) $term->get('field_space_access')->value;
  if ($access === 'appointment' || $access === '') {
    $term->set('field_space_access', 'class');
    $term->save();
    echo 'Set Access "For classes" on ' . $term->label() . PHP_EOL;
  }
  else {
    echo $term->label() . " already has Access \"{$access}\": left as it is." . PHP_EOL;
  }
}

// Map overrides of the room (field_map_overrides) that say Book online.
$paragraphs = $entity_types->getStorage('paragraph');
if (FieldStorageConfig::loadByName('paragraph', 'field_room_key') && FieldStorageConfig::loadByName('paragraph', 'field_space_access')) {
  $ids = $paragraphs->getQuery()
    ->accessCheck(FALSE)
    ->condition('field_room_key', $room_key)
    ->condition('field_space_access', 'appointment')
    ->execute();
  foreach ($paragraphs->loadMultiple($ids) as $override) {
    $override->set('field_space_access', 'class');
    $override->save();
    echo 'Set Access "For classes" on map override ' . $override->id() . PHP_EOL;
  }
}
