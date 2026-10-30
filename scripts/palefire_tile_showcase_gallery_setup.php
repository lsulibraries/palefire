<?php

/**
 * @file
 * Adds the "Masonry" layout and a section header to the Tile showcase.
 *
 * For sections like "From the Galleries" on the Special Collections
 * homepage: tall cards on columns of mixed heights, with a Default / Grid /
 * List switch for the visitor, under an index rule numbered on from the
 * hero's 01 and 02. Templates: paragraph--tile-showcase.html.twig (the
 * cards) and field--node--field-scroll-section--modular-page.html.twig (the
 * rule, heading and lede).
 *
 * Run from the Drupal root on production, after
 * palefire_tile_showcase_setup.php:
 *   drush php:script themes/custom/palefire/scripts/palefire_tile_showcase_gallery_setup.php
 *   drush cr
 *
 * What it does:
 * - Adds "masonry" to the Layout field's allowed values (field_tile_layout),
 *   keeping every value already there (stagger, grid, index).
 * - Adds three optional fields to tile_showcase:
 *     field_tile_eyebrow  Eyebrow: the index rule's name ("From the
 *                         Galleries"). Filled, the section gets the rule
 *                         and its number; empty, no rule.
 *     field_tile_lede     Lede: a sentence under the heading.
 *     field_tile_link     Link: at the rule's right end ("Browse
 *                         exhibitions"); the arrow is drawn for you.
 *   The heading is the section's own Title (field_section_title).
 * - Lets Tiles reference Modular pages (exhibitions) as well as Tile nodes,
 *   if the field doesn't already.
 *
 * Safe to re-run: anything that already exists is left as it is.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;

if (PHP_SAPI !== 'cli') {
  return;
}

$type = 'tile_showcase';

if (!ParagraphsType::load($type)) {
  echo "Paragraph type tile_showcase not found. Run palefire_tile_showcase_setup.php first.\n";
  return;
}
if (!\Drupal::moduleHandler()->moduleExists('link')) {
  \Drupal::service('module_installer')->install(['link']);
  echo "Enabled link\n";
}

// 1. The Masonry layout. -------------------------------------------------------
$layout = FieldStorageConfig::loadByName('paragraph', 'field_tile_layout');
if (!$layout) {
  echo "Storage paragraph.field_tile_layout not found.\n";
  return;
}
$allowed = $layout->getSetting('allowed_values');
if (!isset($allowed['masonry'])) {
  $allowed['masonry'] = 'Default (Grid / List switch)';
  $layout->setSetting('allowed_values', $allowed)->save();
  echo "Added masonry to field_tile_layout\n";
}

// 2. Eyebrow, lede and link. ---------------------------------------------------
$fields = [
  'field_tile_eyebrow' => [
    'type' => 'string',
    'label' => 'Eyebrow',
    'description' => 'The name on the index rule above the heading, e.g. "From the Galleries". Its number (03, 04…) follows the section\'s place on the page. Leave empty for no rule.',
    'widget' => ['type' => 'string_textfield', 'settings' => ['size' => 60]],
  ],
  'field_tile_lede' => [
    'type' => 'string_long',
    'label' => 'Lede',
    'description' => 'A sentence or two under the heading. Plain text.',
    'widget' => ['type' => 'string_textarea', 'settings' => ['rows' => 3]],
  ],
  'field_tile_link' => [
    'type' => 'link',
    'label' => 'Link',
    'description' => 'Shown at the right end of the index rule, e.g. "Browse exhibitions". The arrow is added for you.',
    'settings' => ['link_type' => 17, 'title' => 1],
    'widget' => ['type' => 'link_default'],
  ],
];

$repo = \Drupal::service('entity_display.repository');
$form = $repo->getFormDisplay('paragraph', $type, 'default');
$view = $repo->getViewDisplay('paragraph', $type, 'default');
$weight = (int) max(array_column($form->getComponents(), 'weight') ?: [0]);

foreach ($fields as $name => $info) {
  if (!FieldStorageConfig::loadByName('paragraph', $name)) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'type' => $info['type'],
    ])->save();
    echo "Created storage paragraph.$name\n";
  }
  if (!FieldConfig::loadByName('paragraph', $type, $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => $type,
      'label' => $info['label'],
      'description' => $info['description'],
      'required' => FALSE,
      'settings' => $info['settings'] ?? [],
    ])->save();
    echo "Added $name to paragraph.$type\n";
  }
  if (!$form->getComponent($name)) {
    $form->setComponent($name, $info['widget'] + ['weight' => ++$weight]);
  }
  // The field template prints these itself.
  $view->removeComponent($name);
}
$form->save();
$view->save();

// 3. Exhibitions as tiles. ------------------------------------------------------
$tiles = FieldConfig::loadByName('paragraph', $type, 'field_tiles');
if ($tiles && NodeType::load('modular_page')) {
  $handler = $tiles->getSetting('handler_settings') ?: [];
  // No bundles listed means every content type is already allowed.
  if (!empty($handler['target_bundles']) && !isset($handler['target_bundles']['modular_page'])) {
    $handler['target_bundles']['modular_page'] = 'modular_page';
    $tiles->setSetting('handler_settings', $handler)->save();
    echo "Tiles can now reference Modular pages\n";
  }
}

echo "Tile showcase gallery setup complete. Run: drush cr\n";
