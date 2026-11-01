<?php

/**
 * @file
 * Exhibitions showcase: a Modular page Type that lists every exhibition.
 *
 *   drush php:script scripts/palefire_exhibit_showcase.php
 *   drush cr
 *
 * Adds:
 * - "showcase" to field_modular_type. A page set to it prints the
 *   exhibitions under its <h1> (includes/exhibit-showcase.html.twig): a
 *   depth carousel, then an index of all of them. Its own sections follow.
 * - On node.modular_page, beside "Physical exhibition note":
 *   - field_exhibit_showcase (list): Featured, Listed or Not listed.
 *     Featured exhibitions come first, the carousel opens on them, and
 *     they carry a "Featured" label. Empty means Listed, except for a page
 *     with a main button (a program page: Teaching, Research Grants…),
 *     which is left out unless set to Listed or Featured.
 *   - field_exhibit_feature_note (text): why it is featured, e.g. "On view
 *     in Hill Memorial Library through March 6". Shown with the label.
 * - The page itself, unpublished, at /v2/special/exhibitions (title
 *   "Exhibitions", Type showcase), when no page has that address yet.
 *
 * Only adds: existing fields, values and pages are untouched. Safe to
 * re-run. Run palefire_exhibit_setup.php first.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;

if (PHP_SAPI !== 'cli') {
  return;
}

$bundle = 'modular_page';
$after = 'field_exhibit_physical_note';
$path = '/v2/special/exhibitions';

$mode_storage = FieldStorageConfig::loadByName('node', 'field_modular_type');
if (!$mode_storage || !FieldConfig::loadByName('node', $bundle, $after)) {
  throw new \RuntimeException('Exhibition fields not found. Run palefire_exhibit_setup.php first.');
}

// 1. The Type value. ---------------------------------------------------------

$allowed = $mode_storage->getSetting('allowed_values') ?? [];
if (!isset($allowed['showcase'])) {
  $allowed['showcase'] = 'Exhibitions showcase';
  $mode_storage->setSetting('allowed_values', $allowed)->save();
  echo 'Added "showcase" to field_modular_type' . PHP_EOL;
}

// 2. Fields. -----------------------------------------------------------------

$fields = [
  'field_exhibit_showcase' => [
    'type' => 'list_string',
    'storage_settings' => ['allowed_values' => [
      'featured' => 'Featured',
      'listed' => 'Listed',
      'hidden' => 'Not listed',
    ]],
    'label' => 'Exhibitions showcase',
    'description' => 'Exhibition pages only: how this page appears on the Exhibitions page. Featured comes first, with a label, e.g. while a related physical exhibition is on or for a current event. Empty means Listed (program pages with a main button are left out unless set here).',
    'widget' => ['type' => 'options_select'],
  ],
  'field_exhibit_feature_note' => [
    'type' => 'string',
    'storage_settings' => ['max_length' => 160],
    'label' => 'Why it is featured (optional)',
    'description' => 'Exhibition pages only, when Featured: one short line shown with the label, e.g. "On view in Hill Memorial Library through March 6".',
    'widget' => ['type' => 'string_textfield'],
  ],
];

foreach ($fields as $name => $def) {
  $storage = FieldStorageConfig::loadByName('node', $name);
  if ($storage && $storage->getType() !== $def['type']) {
    throw new \RuntimeException("node.$name already exists as '{$storage->getType()}', not '{$def['type']}'. Delete it in Manage fields and re-run.");
  }
  if (!$storage) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'type' => $def['type'],
      'settings' => $def['storage_settings'],
      'cardinality' => 1,
    ])->save();
    echo "Created storage node.$name" . PHP_EOL;
  }
  if (!FieldConfig::loadByName('node', $bundle, $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'bundle' => $bundle,
      'label' => $def['label'],
      'description' => $def['description'],
      'required' => FALSE,
    ])->save();
    echo "Created field node.$bundle.$name" . PHP_EOL;
  }
}

// 3. Form: both right under "Physical exhibition note", in its group. ------

/** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $displays */
$displays = \Drupal::service('entity_display.repository');
$form = $displays->getFormDisplay('node', $bundle, 'default');
$new = array_values(array_filter(array_keys($fields), fn ($name) => !$form->getComponent($name)));
if ($new) {
  $components = $form->getComponents();
  $weight = isset($components[$after])
    ? (int) $components[$after]['weight'] + 1
    : max(array_merge([0], array_map(fn ($c) => (int) ($c['weight'] ?? 0), $components))) + 1;
  // Make room: whatever sat under the note moves down.
  if (isset($components[$after])) {
    foreach ($components as $other => $component) {
      if ((int) ($component['weight'] ?? 0) >= $weight) {
        $component['weight'] = (int) $component['weight'] + count($new);
        $form->setComponent($other, $component);
      }
    }
  }
  foreach ($new as $i => $name) {
    $form->setComponent($name, $fields[$name]['widget'] + ['weight' => $weight + $i, 'settings' => []]);
  }
  foreach ($form->getThirdPartySettings('field_group') as $group_name => $group) {
    $children = $group['children'] ?? [];
    $at = array_search($after, $children, TRUE);
    if ($at !== FALSE) {
      array_splice($children, $at + 1, 0, array_diff($new, $children));
      $group['children'] = $children;
      $form->setThirdPartySetting('field_group', $group_name, $group);
      echo "Added to the \"{$group['label']}\" group" . PHP_EOL;
    }
  }
  $form->save();
  echo 'Added to the form: ' . implode(', ', $new) . PHP_EOL;
}

// The theme reads both (PalefireHooks::showcase()); nothing prints them.
foreach (array_keys($displays->getViewModeOptionsByBundle('node', $bundle)) as $mode) {
  $view = $displays->getViewDisplay('node', $bundle, $mode);
  if (!$view->isNew()) {
    foreach (array_keys($fields) as $name) {
      $view->removeComponent($name);
    }
    $view->save();
  }
}

// 4. The page. ---------------------------------------------------------------

$existing = \Drupal::service('path_alias.repository')->lookupByAlias($path, 'en')
  ?? \Drupal::service('path_alias.repository')->lookupByAlias($path, \Drupal::languageManager()->getDefaultLanguage()->getId());
if ($existing) {
  echo "A page already has $path ({$existing['path']}); left as it is." . PHP_EOL;
}
else {
  $node = Node::create([
    'type' => $bundle,
    'title' => 'Exhibitions',
    'status' => 0,
    'field_modular_type' => 'showcase',
    'path' => ['alias' => $path, 'pathauto' => 0],
  ]);
  $node->save();
  echo "Created \"Exhibitions\" (node/{$node->id()}), unpublished, at $path" . PHP_EOL;
}

echo 'Done. Run drush cr. Mark an exhibition Featured under "Exhibitions showcase" on its edit form.' . PHP_EOL;
