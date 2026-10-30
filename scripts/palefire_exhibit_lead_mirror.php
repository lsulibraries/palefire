<?php

/**
 * @file
 * Exhibitions: adds "Mirror lead image" to the gallery (exhibit_page).
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_lead_mirror.php
 *   drush cr
 *
 * Creates field_exhibit_lead_mirror (boolean) on the "Exhibition: Gallery"
 * paragraph type and puts its checkbox on the form under "Show whole image"
 * (field_exhibit_lead_whole), in the same field group when that field is in
 * one. Ticked, the theme flips the lead left to right on the page
 * (.pf-exhibit-mirror, _exhibition.scss); the lightbox shows the original.
 * Not shown through Manage display: palefire.theme reads the value.
 *
 * Only adds: existing fields, values and the view display are untouched.
 * Safe to re-run. Undo: delete the field in Manage fields.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\paragraphs\Entity\ParagraphsType;

if (PHP_SAPI !== 'cli') {
  return;
}

$bundle = 'exhibit_page';
$name = 'field_exhibit_lead_mirror';
$after = 'field_exhibit_lead_whole';

if (!ParagraphsType::load($bundle)) {
  throw new \RuntimeException("Paragraph type '$bundle' not found. Run palefire_exhibit_setup.php first.");
}

$storage = FieldStorageConfig::loadByName('paragraph', $name);
if ($storage && $storage->getType() !== 'boolean') {
  throw new \RuntimeException("paragraph.$name already exists as '{$storage->getType()}', not 'boolean'. Delete it in Manage fields and re-run.");
}
if (!$storage) {
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
    'label' => 'Mirror lead image',
    'description' => 'Flip the lead left to right on the page, e.g. so a bird faces into the text. Opened full screen it shows the right way round.',
    'required' => FALSE,
    'default_value' => [['value' => 0]],
    'settings' => ['on_label' => 'Mirrored', 'off_label' => 'As is'],
  ])->save();
  echo "Created field paragraph.$bundle.$name" . PHP_EOL;
}
else {
  echo "Field paragraph.$bundle.$name already exists" . PHP_EOL;
}

// The checkbox, right under "Show whole image" (or last, without it).
/** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $displays */
$displays = \Drupal::service('entity_display.repository');
$form = $displays->getFormDisplay('paragraph', $bundle, 'default');
if ($form->getComponent($name)) {
  echo "Already on the form" . PHP_EOL;
}
else {
  $components = $form->getComponents();
  $weight = isset($components[$after])
    ? (int) $components[$after]['weight'] + 1
    : max(array_merge([0], array_map(fn ($c) => (int) ($c['weight'] ?? 0), $components))) + 1;
  // Make room: whatever sat just under "Show whole image" moves down one.
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
  // Into "Show whole image"'s field group (field_group), if it has one.
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

echo 'Done. Run drush cr, then tick "Mirror lead image" on a gallery.' . PHP_EOL;
