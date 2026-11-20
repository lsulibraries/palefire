<?php

/**
 * @file
 * Exhibitions: a biography card's two editor fields on Exhibition: Entry.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_bio_setup.php
 *   drush cr
 *
 * Config only: it never changes content. Safe to re-run: a field that
 * exists is left as it is.
 *
 * An entry whose biography runs to 150 words or more shows as a card
 * (paragraph--exhibit-entry.html.twig): Life and Role as facts, a short
 * summary, and "Read the biography", which opens the whole life in a panel
 * beside the gallery (js/exhibit-bio.js). Adds to exhibit_entry:
 * - field_entry_summary: "Summary (optional)", plain text, two sentences
 *   or so. Empty, the card shows the biography's opening sentence in
 *   italics, so editors can see which cards still need one.
 * - field_entry_role: "Role (optional)", e.g. "Ornithologist, Academy of
 *   Natural Sciences".
 * Life comes from the heading's trailing "(1901-1984)", so no field.
 *
 * Undo: delete the two fields in Manage fields of Exhibition: Entry.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\paragraphs\Entity\ParagraphsType;

if (PHP_SAPI !== 'cli') {
  return;
}

if (!ParagraphsType::load('exhibit_entry')) {
  throw new \RuntimeException("'exhibit_entry' not found. Run palefire_exhibit_setup.php first.");
}

$fields = [
  'field_entry_summary' => [
    'type' => 'string_long',
    'settings' => [],
    'label' => 'Summary (optional)',
    'description' => 'Two sentences on who they were and why they are in the exhibition, shown on the biography’s card. Empty, the card shows the biography’s opening sentence in italics.',
    'widget' => ['type' => 'string_textarea', 'weight' => 2, 'settings' => ['rows' => 3, 'placeholder' => '']],
  ],
  'field_entry_role' => [
    'type' => 'string',
    'settings' => ['max_length' => 120],
    'label' => 'Role (optional)',
    'description' => 'A few words, e.g. “Ornithologist, Academy of Natural Sciences”. Shown on the biography’s card beside Life, which comes from the heading’s “(1901-1984)”.',
    'widget' => ['type' => 'string_textfield', 'weight' => 2, 'settings' => ['size' => 60, 'placeholder' => '']],
  ],
];

$form = \Drupal::service('entity_display.repository')->getFormDisplay('paragraph', 'exhibit_entry', 'default');
foreach ($fields as $name => $def) {
  $storage = FieldStorageConfig::loadByName('paragraph', $name);
  if ($storage && $storage->getType() !== $def['type']) {
    throw new \RuntimeException("paragraph.$name already exists as '{$storage->getType()}', not '{$def['type']}'.");
  }
  if (!$storage) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'type' => $def['type'],
      'settings' => $def['settings'],
      'cardinality' => 1,
    ])->save();
    echo "Created storage paragraph.$name" . PHP_EOL;
  }
  if (!FieldConfig::loadByName('paragraph', 'exhibit_entry', $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => 'exhibit_entry',
      'label' => $def['label'],
      'description' => $def['description'],
    ])->save();
    echo "Created field paragraph.exhibit_entry.$name" . PHP_EOL;
  }
  if (!$form->getComponent($name)) {
    $form->setComponent($name, $def['widget']);
  }
}
$form->save();
// The template prints both itself: nothing on the view display.
echo 'Done. Fill Summary and Role on an entry’s edit form (Exhibition: Entry).' . PHP_EOL;
