<?php

/**
 * @file
 * Exhibitions: an object label for a page's cover picture.
 *
 *   drush php:script scripts/palefire_exhibit_cover_label.php
 *   drush cr
 *
 * Adds four optional plain text fields to Modular page, on the form after
 * "Exhibition title image": field_exhibit_cover_maker, _title, _date and
 * _medium. With a title, the page's cover carries the label when the page is
 * the first of a Landing hero's banners (palefire.theme,
 * _palefire_cover_object_label()). Not shown through Manage display.
 *
 * Fills the Audubon exhibition's label, from its own texts, where its fields
 * are empty.
 *
 * Only adds: existing fields and filled values are untouched. Safe to re-run.
 * Undo: delete the fields in Manage fields.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;

if (PHP_SAPI !== 'cli') {
  return;
}

$bundle = 'modular_page';
$after = 'field_exhibit_cover';
$fields = [
  'field_exhibit_cover_maker' => ['Cover label: maker', 'Who made the cover picture, with their dates: John James Audubon (1785–1851).'],
  'field_exhibit_cover_title' => ['Cover label: title', "The picture's title. The label shows only when this is filled."],
  'field_exhibit_cover_date' => ['Cover label: date', 'When it was made: 1827–1838.'],
  'field_exhibit_cover_medium' => ['Cover label: medium', 'Its medium or edition: Folio edition, plate 21.'],
];

foreach ($fields as $name => [$label, $description]) {
  $storage = FieldStorageConfig::loadByName('node', $name);
  if ($storage && $storage->getType() !== 'string') {
    throw new \RuntimeException("node.$name already exists as '{$storage->getType()}', not 'string'. Delete it in Manage fields and re-run.");
  }
  if (!$storage) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'type' => 'string',
      'cardinality' => 1,
    ])->save();
    echo "Created storage node.$name" . PHP_EOL;
  }
  if (!FieldConfig::loadByName('node', $bundle, $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'bundle' => $bundle,
      'label' => $label,
      'description' => $description,
      'required' => FALSE,
    ])->save();
    echo "Created field node.$bundle.$name" . PHP_EOL;
  }
}

// On the form, after the cover and in its field group when it has one.
/** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $displays */
$displays = \Drupal::service('entity_display.repository');
$form = $displays->getFormDisplay('node', $bundle, 'default');
$weight = (int) ($form->getComponent($after)['weight'] ?? 7);
foreach (array_keys($fields) as $name) {
  if (!$form->getComponent($name)) {
    $form->setComponent($name, ['type' => 'string_textfield', 'weight' => $weight]);
  }
}
foreach ($form->getThirdPartySettings('field_group') as $group_name => $group) {
  $children = $group['children'] ?? [];
  $at = array_search($after, $children, TRUE);
  if ($at !== FALSE) {
    $new = array_values(array_diff(array_keys($fields), $children));
    array_splice($children, $at + 1, 0, $new);
    $group['children'] = $children;
    $form->setThirdPartySetting('field_group', $group_name, $group);
  }
}
$form->save();

// The Audubon exhibition's label, from scripts/data/audubon.json.
$values = [
  'field_exhibit_cover_maker' => 'John James Audubon (1785–1851)',
  'field_exhibit_cover_title' => 'Mocking Birds and rattlesnake, detail',
  'field_exhibit_cover_date' => '1827–1838',
  'field_exhibit_cover_medium' => 'Folio edition, plate 21',
];
$nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
  'type' => $bundle,
  'title' => 'John James Audubon in Louisiana',
]);
foreach ($nodes as $node) {
  assert($node instanceof Node);
  $changed = FALSE;
  foreach ($values as $name => $value) {
    if ($node->get($name)->isEmpty()) {
      $node->set($name, $value);
      $changed = TRUE;
    }
  }
  if ($changed) {
    $node->save();
    echo 'Filled the label on node ' . $node->id() . PHP_EOL;
  }
}
if (!$nodes) {
  echo 'No page titled "John James Audubon in Louisiana": no label filled.' . PHP_EOL;
}

echo 'Done. Run drush cr.' . PHP_EOL;
