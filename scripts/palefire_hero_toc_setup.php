<?php

/**
 * @file
 * Adds "Contents in the column" to the Landing hero: a box, unticked by
 * default. Ticked, the hero's column lists the page's sections, numbered as
 * their rules are, in place of the Highlights (Banners, the blog post); a
 * unit homepage's Find us banner stays first, the picture the column rests
 * on (includes/landing.html.twig, _palefire_hero_toc()). Ticks it on Oral
 * History's hero (its map's Here room ends in .oral_history). Safe to run
 * again; it never unticks a hero.
 *
 *   drush php:script scripts/palefire_hero_toc_setup.php
 *   drush cr
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\paragraphs\ParagraphInterface;

if (PHP_SAPI !== 'cli') {
  return;
}

$name = 'field_hero_toc';
$bundle = 'landing_hero';

if (!\Drupal::entityTypeManager()->getStorage('paragraphs_type')->load($bundle)) {
  throw new \RuntimeException("Paragraph type $bundle not found.");
}

$existing = FieldStorageConfig::loadByName('paragraph', $name);
if ($existing && $existing->getType() !== 'boolean') {
  throw new \RuntimeException("paragraph.$name already exists as '{$existing->getType()}', not boolean.");
}
if (!$existing) {
  FieldStorageConfig::create([
    'field_name' => $name,
    'entity_type' => 'paragraph',
    'type' => 'boolean',
    'cardinality' => 1,
  ])->save();
  echo "Created storage paragraph.$name" . PHP_EOL;
}

if (!FieldConfig::loadByName('paragraph', $bundle, $name)) {
  FieldConfig::create([
    'field_name' => $name,
    'entity_type' => 'paragraph',
    'bundle' => $bundle,
    'label' => 'Contents in the column',
    'description' => 'Tick to list the page’s sections in the column beside the title, in place of the Banners and the blog post. A unit homepage’s Find us map stays first.',
    'required' => FALSE,
    'default_value' => [['value' => 0]],
    'settings' => ['on_label' => 'Contents', 'off_label' => 'Highlights'],
  ])->save();
  echo "Created field paragraph.$bundle.$name" . PHP_EOL;
}

$display = \Drupal::service('entity_display.repository')->getFormDisplay('paragraph', $bundle, 'default');
if (!$display->getComponent($name)) {
  $display->setComponent($name, ['type' => 'boolean_checkbox', 'weight' => 51, 'settings' => ['display_label' => TRUE]]);
  $display->save();
  echo "Added Contents in the column to the Landing hero form." . PHP_EOL;
}

// Oral History's hero: ticked.
$storage = \Drupal::entityTypeManager()->getStorage('paragraph');
foreach ($storage->loadByProperties(['type' => $bundle]) as $hero) {
  if (!$hero instanceof ParagraphInterface || !$hero->hasField('field_bento_map') || !$hero->hasField($name)) {
    continue;
  }
  $map = $hero->get('field_bento_map')->entity;
  $here = $map instanceof ParagraphInterface && $map->hasField('field_map_here') ? (string) $map->get('field_map_here')->value : '';
  if (!str_ends_with($here, '.oral_history') || (bool) $hero->get($name)->value) {
    continue;
  }
  $hero->set($name, 1);
  $hero->setNewRevision(FALSE);
  $hero->save();
  echo 'Ticked it on Oral History’s hero (paragraph ' . $hero->id() . ').' . PHP_EOL;
}

echo 'Done. Run: drush cr' . PHP_EOL;
