<?php

/**
 * @file
 * Exhibitions: adds "Frame the cover" to Modular pages.
 *
 *   drush php:script scripts/palefire_exhibit_cover_framed.php
 *   drush cr
 *
 * Creates field_exhibit_cover_framed (boolean) on Modular page and puts its
 * checkbox on the form under the exhibition’s cover image
 * (field_exhibit_cover), in the same field group when that field is in
 * one. Ticked, the cover is shown whole at its own scale over a blurred
 * wash of itself instead of filling the screen (.pf-exhibit-cover--framed,
 * _exhibition.scss), for a cover that is too small or soft to blow up.
 * Not shown through Manage display: PalefireHooks::exhibitHead() reads it.
 *
 * Only adds: existing fields, values and the view display are untouched.
 * Safe to re-run. Undo: delete the field in Manage fields.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;

if (PHP_SAPI !== 'cli') {
  return;
}

$bundle = 'modular_page';
$name = 'field_exhibit_cover_framed';
$after = 'field_exhibit_cover';

if (!NodeType::load($bundle)) {
  throw new \RuntimeException("Content type '$bundle' not found.");
}
if (!FieldConfig::loadByName('node', $bundle, $after)) {
  throw new \RuntimeException("$after is not on Modular page. Run palefire_exhibit_setup.php first.");
}

$storage = FieldStorageConfig::loadByName('node', $name);
if ($storage && $storage->getType() !== 'boolean') {
  throw new \RuntimeException("node.$name already exists as '{$storage->getType()}', not 'boolean'. Delete it in Manage fields and re-run.");
}
if (!$storage) {
  FieldStorageConfig::create([
    'field_name' => $name,
    'entity_type' => 'node',
    'type' => 'boolean',
    'cardinality' => 1,
  ])->save();
  echo "Created storage node.$name" . PHP_EOL;
}

if (!FieldConfig::loadByName('node', $bundle, $name)) {
  FieldConfig::create([
    'field_name' => $name,
    'entity_type' => 'node',
    'bundle' => $bundle,
    'label' => 'Frame the cover',
    'description' => 'For a cover image that looks soft blown up to fill the screen: show it whole, at its own scale, over a blurred wash of itself.',
    'required' => FALSE,
    'default_value' => [['value' => 0]],
    'settings' => ['on_label' => 'Framed', 'off_label' => 'Full screen'],
  ])->save();
  echo "Created field node.$bundle.$name" . PHP_EOL;
}
else {
  echo "Field node.$bundle.$name already exists" . PHP_EOL;
}

// The checkbox, right under the cover image (or last, without it).
/** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $displays */
$displays = \Drupal::service('entity_display.repository');
$form = $displays->getFormDisplay('node', $bundle, 'default');
if ($form->getComponent($name)) {
  echo 'Already on the form' . PHP_EOL;
}
else {
  $components = $form->getComponents();
  $weight = isset($components[$after])
    ? (int) $components[$after]['weight'] + 1
    : max(array_merge([0], array_map(fn ($c) => (int) ($c['weight'] ?? 0), $components))) + 1;
  // Make room: whatever sat just under the cover moves down one.
  if (isset($components[$after])) {
    foreach ($components as $other => $component) {
      if ((int) ($component['weight'] ?? 0) >= $weight) {
        $component['weight'] = (int) $component['weight'] + 1;
        $form->setComponent($other, $component);
      }
    }
  }
  $form->setComponent($name, [
    'type' => 'boolean_checkbox',
    'weight' => $weight,
    'settings' => ['display_label' => TRUE],
  ]);
  // Into the cover’s field group (field_group), if it has one.
  foreach ($form->getThirdPartySettings('field_group') as $group_name => $group) {
    $children = $group['children'] ?? [];
    $at = array_search($after, $children, TRUE);
    if ($at !== FALSE && !in_array($name, $children, TRUE)) {
      array_splice($children, $at + 1, 0, [$name]);
      $group['children'] = $children;
      $form->setThirdPartySetting('field_group', $group_name, $group);
      echo "Added to the \"{$group['label']}\" group" . PHP_EOL;
    }
  }
  $form->save();
  echo "Added the checkbox to the form (weight $weight)" . PHP_EOL;
}

echo 'Done. Run drush cr, then tick "Frame the cover" on an exhibition.' . PHP_EOL;
