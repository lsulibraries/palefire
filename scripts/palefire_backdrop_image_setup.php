<?php

/**
 * @file
 * Background image: an optional picture on Articles and Modular pages.
 *
 * Run on the server from the Drupal root:
 *   drush php:script scripts/palefire_backdrop_image_setup.php
 *   drush php:script scripts/palefire_backdrop_image_setup.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it says what it would add. Safe to run again;
 * what exists is left as it is.
 *
 * Adds field_backdrop_image (Media: Image, one) to the article and
 * modular_page node types, on the edit form after the page's own picture
 * (Image, or Cover image) and on no view display: the theme reads it
 * (_palefire_backdrop_item() in palefire.theme). Where a link to the page
 * fills a background on hover or focus (News and events, the opening
 * screen's highlights, the ledger rows of a tile showcase, From the
 * stacks), this picture shows instead of the cover or Image.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

if (PHP_SAPI !== 'cli') {
  return;
}

$args = [];
foreach ($extra ?? [] as $arg) {
  if (str_contains((string) $arg, '=')) {
    [$key, $value] = explode('=', (string) $arg, 2);
    $args[$key] = $value;
  }
}
$apply = ($args['apply'] ?? '') === '1';
echo $apply ? 'Applying.' . PHP_EOL : 'Dry run: nothing is saved. Add -- apply=1 to apply.' . PHP_EOL;

$name = 'field_backdrop_image';
// Bundle => the field the widget follows on the edit form.
$bundles = ['article' => 'field_image', 'modular_page' => 'field_image_cover'];
$label = 'Background image (optional)';
$help = 'Shown large behind this page’s link when someone points at it or tabs to it: News and events, the opening screen’s highlights, lists of pages. A landscape picture at least 1920 pixels wide. Empty uses the page’s own picture.';

$image_types = [];
foreach (\Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple() as $media_type) {
  if ($media_type->getSource()->getPluginId() === 'image') {
    $image_types[$media_type->id()] = $media_type->id();
  }
}
if (!$image_types) {
  throw new \RuntimeException('No Image media type found (Structure > Media types, source "Image").');
}
if (isset($image_types['image'])) {
  $image_types = ['image' => 'image'];
}
echo 'Image media type: ' . implode(', ', $image_types) . PHP_EOL;
$widget = \Drupal::moduleHandler()->moduleExists('media_library')
  ? ['type' => 'media_library_widget', 'settings' => ['media_types' => array_values($image_types)]]
  : ['type' => 'entity_reference_autocomplete', 'settings' => ['match_operator' => 'CONTAINS', 'size' => 60, 'placeholder' => '']];

$existing = FieldStorageConfig::loadByName('node', $name);
if ($existing && $existing->getType() !== 'entity_reference') {
  throw new \RuntimeException("node.$name already exists as '{$existing->getType()}'. Delete it in Manage fields and re-run.");
}
if (!$existing) {
  echo "Storage node.$name: new" . PHP_EOL;
  if ($apply) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => 1,
      'settings' => ['target_type' => 'media'],
    ])->save();
  }
}

/** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $displays */
$displays = \Drupal::service('entity_display.repository');
$types = \Drupal::entityTypeManager()->getStorage('node_type');

foreach ($bundles as $bundle => $after) {
  if (!$types->load($bundle)) {
    echo "No $bundle node type: skipped." . PHP_EOL;
    continue;
  }
  if (!FieldConfig::loadByName('node', $bundle, $name)) {
    echo "Field node.$bundle.$name: new" . PHP_EOL;
    if ($apply) {
      FieldConfig::create([
        'field_name' => $name,
        'entity_type' => 'node',
        'bundle' => $bundle,
        'label' => $label,
        'description' => $help,
        'required' => FALSE,
        'settings' => ['handler' => 'default:media', 'handler_settings' => ['target_bundles' => $image_types, 'auto_create' => FALSE]],
      ])->save();
    }
  }

  // The edit form: after $after, in its field group if it has one.
  $form = $displays->getFormDisplay('node', $bundle, 'default');
  $components = $form->getComponents();
  if (!isset($components[$name])) {
    echo "Edit form of $bundle: placed after " . (isset($components[$after]) ? $after : 'the last field') . PHP_EOL;
    $weight = isset($components[$after])
      ? (int) $components[$after]['weight'] + 1
      : max(array_merge([0], array_map(static fn ($c) => (int) ($c['weight'] ?? 0), $components))) + 1;
    if (isset($components[$after])) {
      foreach ($components as $other => $component) {
        if ((int) ($component['weight'] ?? 0) >= $weight) {
          $component['weight'] = (int) $component['weight'] + 1;
          $form->setComponent($other, $component);
        }
      }
    }
    $form->setComponent($name, $widget + ['weight' => $weight]);
    foreach ($form->getThirdPartySettings('field_group') as $group_name => $group) {
      $children = $group['children'] ?? [];
      $at = array_search($after, $children, TRUE);
      if ($at !== FALSE && !in_array($name, $children, TRUE)) {
        array_splice($children, $at + 1, 0, [$name]);
        $group['children'] = $children;
        $form->setThirdPartySetting('field_group', $group_name, $group);
      }
    }
    if ($apply) {
      $form->save();
    }
  }

  // Not on any view display: the theme reads it.
  foreach (array_keys($displays->getViewModeOptionsByBundle('node', $bundle)) as $mode) {
    $view = $displays->getViewDisplay('node', $bundle, $mode);
    if (!$view->isNew() && $view->getComponent($name)) {
      echo "View display $bundle.$mode: hidden" . PHP_EOL;
      if ($apply) {
        $view->removeComponent($name)->save();
      }
    }
  }
}

echo ($apply ? 'Done.' : 'Dry run done.') . ' Then: drush cr' . PHP_EOL;
