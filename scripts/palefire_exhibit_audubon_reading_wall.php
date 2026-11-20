<?php

/**
 * @file
 * Audubon: hangs Suggested Reading as a wall of readings. The gallery's
 * Display goes from "story" to "wall"; with no entries, the theme's
 * _palefire_exhibit_reading_wall() hangs its covers on the wall, each with
 * its citation under it, after a full-screen lead with the gallery's facts.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_audubon_reading_wall.php
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_audubon_reading_wall.php -- apply=1
 *   drush cr
 *
 * Without apply=1 it only reports what it would change. The Display is set
 * only where it is still empty or "story", so an editor's own choice is
 * kept. Saves the page as a new revision. Safe to re-run.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;

if (PHP_SAPI !== 'cli') {
  return;
}

$args = [];
foreach ((isset($extra) && is_array($extra)) ? $extra : [] as $arg) {
  if (str_contains((string) $arg, '=')) {
    [$k, $v] = explode('=', (string) $arg, 2);
    $args[trim($k)] = trim($v, " \t\"'");
  }
}
$apply = ($args['apply'] ?? '') === '1';

if (!FieldConfig::loadByName('paragraph', 'exhibit_page', 'field_exhibit_display')) {
  throw new \RuntimeException('field_exhibit_display is not on Gallery: run palefire_exhibit_setup.php first.');
}

$ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', 'John James Audubon in Louisiana')
  ->execute();
if (count($ids) !== 1) {
  throw new \RuntimeException('Expected one Modular page titled "John James Audubon in Louisiana", found ' . count($ids) . '.');
}
$node = Node::load(reset($ids));

$gallery = NULL;
foreach ($node->get('field_scroll_section')->referencedEntities() as $section) {
  if ($section->bundle() === 'exhibit_page' && trim((string) $section->get('field_section_title')->value) === 'Suggested Reading') {
    $gallery = $section;
    break;
  }
}
if (!$gallery) {
  throw new \RuntimeException('No "Suggested Reading" gallery on the Audubon page.');
}

$readings = 0;
foreach ($gallery->get('field_guide_blocks')->referencedEntities() as $block) {
  if ($block->bundle() === 'exhibit_gallery') {
    foreach ($block->get('field_gallery_items')->referencedEntities() as $item) {
      $readings += $item->bundle() === 'exhibit_reading' ? 1 : 0;
    }
  }
}
if (!$readings) {
  throw new \RuntimeException('Suggested Reading has no readings: run palefire_exhibit_audubon_readings.php first.');
}

$display = (string) $gallery->get('field_exhibit_display')->value;
if ($display === 'wall') {
  echo "Suggested Reading is already a wall ($readings readings): nothing changed." . PHP_EOL;
  return;
}
if ($display !== '' && $display !== 'story') {
  echo "Suggested Reading's Display is \"$display\", an editor's choice: nothing changed." . PHP_EOL;
  return;
}

echo "Wall: Suggested Reading ($readings readings)" . PHP_EOL;
if (!$apply) {
  echo 'Dry run: nothing saved. Add "-- apply=1" to save.' . PHP_EOL;
  return;
}
$gallery->set('field_exhibit_display', 'wall');
$gallery->setNeedsSave(TRUE);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('Audubon: Suggested Reading as a wall of readings (palefire_exhibit_audubon_reading_wall.php).');
$node->save();
echo 'Saved. Run: drush cr' . PHP_EOL;
