<?php

/**
 * @file
 * Adds "Spotlight" to the Landing hero: a box, ticked by default, for the
 * cover's warm light from the film's edge over the type's side
 * (--pf-cover-glow, _landing-features.scss). Unticked, the hero has none
 * (.pf-landing--no-spotlight). Safe to run again.
 *
 *   drush php:script scripts/palefire_hero_spotlight_setup.php
 *   drush cr
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

if (PHP_SAPI !== 'cli') {
  return;
}

$name = 'field_hero_spotlight';
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
    'label' => 'Spotlight',
    'description' => 'A warm light from the film’s edge over the title and text. Untick for none (a unit’s homepage whose film is a faded background, say).',
    'required' => FALSE,
    'default_value' => [['value' => 1]],
    'settings' => ['on_label' => 'On', 'off_label' => 'Off'],
  ])->save();
  echo "Created field paragraph.$bundle.$name" . PHP_EOL;
}

$display = \Drupal::service('entity_display.repository')->getFormDisplay('paragraph', $bundle, 'default');
if (!$display->getComponent($name)) {
  $display->setComponent($name, ['type' => 'boolean_checkbox', 'weight' => 50, 'settings' => ['display_label' => TRUE]]);
  $display->save();
  echo "Added Spotlight to the Landing hero form." . PHP_EOL;
}

echo 'Done. Run: drush cr' . PHP_EOL;
