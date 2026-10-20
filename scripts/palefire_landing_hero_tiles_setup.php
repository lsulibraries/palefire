<?php

/**
 * @file
 * Landing hero: the three opening tiles from Modular pages, and blog
 * categories that take any number of terms.
 *
 * Run from the Drupal root on production:
 *   drush php:script themes/custom/palefire/scripts/palefire_landing_hero_tiles_setup.php
 *   drush cr
 *
 * What it does:
 * - Creates the paragraph type hero_tile ("Hero tile"), one per tile:
 *     field_hero_tile_page   Page: a Modular page (required). The tile shows
 *                            its title, its intro as the line that opens,
 *                            its landing's background poster as the image,
 *                            and links to it.
 *     field_hero_tile_label  Label: the small line above the title
 *                            ("Exhibition", "Guide"), optional.
 *     field_hero_tile_when   Dates or line: under the title ("Sep 1 – Dec
 *                            19, 2026", "On view · Permanent"), optional.
 * - Adds to the Landing hero (landing_hero):
 *     field_hero_tiles       Tiles: up to three Hero tile paragraphs. Empty,
 *                            the placeholder tiles stay.
 *     field_landing_blog_category
 *                            Blog categories: Article Category terms, any
 *                            number. The latest article in any of them
 *                            fills the "Latest from the blog" card. If the
 *                            field already exists with one value, it is
 *                            opened up to any number (existing values stay).
 * The template prints all of these itself, so they're hidden from the
 * paragraph's display.
 *
 * Safe to re-run: anything that already exists is left as it is.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\paragraphs\Entity\ParagraphsType;

if (PHP_SAPI !== 'cli') {
  return;
}

$hero = 'landing_hero';
$tile = 'hero_tile';

if (!ParagraphsType::load($hero)) {
  echo "Paragraph type landing_hero not found. Run palefire_landing_hero_setup.php first.\n";
  return;
}
if (!\Drupal::moduleHandler()->moduleExists('entity_reference_revisions')) {
  echo "entity_reference_revisions is not enabled (Paragraphs needs it).\n";
  return;
}

$repo = \Drupal::service('entity_display.repository');

/**
 * Creates a field storage and field if they don't exist yet.
 */
$add = function (string $bundle, string $name, array $storage, array $field) {
  if (!FieldStorageConfig::loadByName('paragraph', $name)) {
    FieldStorageConfig::create(['field_name' => $name, 'entity_type' => 'paragraph'] + $storage)->save();
    echo "Created storage paragraph.$name\n";
  }
  if (!FieldConfig::loadByName('paragraph', $bundle, $name)) {
    FieldConfig::create(['field_name' => $name, 'entity_type' => 'paragraph', 'bundle' => $bundle] + $field)->save();
    echo "Added $name to paragraph.$bundle\n";
  }
};

// 1. Hero tile paragraph type. -----------------------------------------------
if (!ParagraphsType::load($tile)) {
  ParagraphsType::create([
    'id' => $tile,
    'label' => 'Hero tile',
    'description' => 'One of the Landing hero\'s opening tiles: a Modular page, with an optional label and dates.',
  ])->save();
  echo "Created paragraph type $tile\n";
}

$add($tile, 'field_hero_tile_page', [
  'type' => 'entity_reference',
  'cardinality' => 1,
  'settings' => ['target_type' => 'node'],
], [
  'label' => 'Page',
  'description' => 'The tile shows this page\'s title, its intro (when the tile opens) and its landing\'s background poster, and links to it.',
  'required' => TRUE,
  'settings' => [
    'handler' => 'default:node',
    'handler_settings' => [
      'target_bundles' => ['modular_page' => 'modular_page'],
      'sort' => ['field' => 'title', 'direction' => 'ASC'],
      'auto_create' => FALSE,
    ],
  ],
]);
$add($tile, 'field_hero_tile_label', [
  'type' => 'string',
  'settings' => ['max_length' => 60],
], [
  'label' => 'Label',
  'description' => 'The small line above the title, e.g. "Exhibition", "Permanent exhibition" or "Guide". Optional.',
]);
$add($tile, 'field_hero_tile_when', [
  'type' => 'string',
  'settings' => ['max_length' => 80],
], [
  'label' => 'Dates or line',
  'description' => 'Under the title, e.g. "Sep 1 – Dec 19, 2026" or "On view · Permanent". Optional.',
]);

$form = $repo->getFormDisplay('paragraph', $tile, 'default');
$form->setComponent('field_hero_tile_page', [
  'type' => 'entity_reference_autocomplete',
  'weight' => 0,
  'settings' => ['match_operator' => 'CONTAINS', 'match_limit' => 10, 'size' => 60, 'placeholder' => 'Start typing a page title'],
]);
$form->setComponent('field_hero_tile_label', ['type' => 'string_textfield', 'weight' => 1, 'settings' => ['size' => 40]]);
$form->setComponent('field_hero_tile_when', ['type' => 'string_textfield', 'weight' => 2, 'settings' => ['size' => 40]]);
$form->save();
$view = $repo->getViewDisplay('paragraph', $tile, 'default');
foreach (['field_hero_tile_page', 'field_hero_tile_label', 'field_hero_tile_when'] as $name) {
  $view->removeComponent($name);
}
$view->save();

// 2. Landing hero: tiles and blog categories. ---------------------------------
$add($hero, 'field_hero_tiles', [
  'type' => 'entity_reference_revisions',
  'cardinality' => 3,
  'settings' => ['target_type' => 'paragraph'],
], [
  'label' => 'Tiles',
  'description' => 'The three tiles under the search. Empty, placeholder tiles show.',
  'settings' => [
    'handler' => 'default:paragraph',
    'handler_settings' => [
      'target_bundles' => [$tile => $tile],
      'negate' => 0,
      'target_bundles_drag_drop' => [$tile => ['enabled' => TRUE, 'weight' => 0]],
    ],
  ],
]);

// The vocabularies Article's own Article Category field uses, so the two
// always match whatever the vocabulary's machine name is.
$article_category = FieldConfig::loadByName('node', 'article', 'field_article_category');
$vocabularies = $article_category ? ($article_category->getSetting('handler_settings')['target_bundles'] ?? []) : [];
$category = FieldStorageConfig::loadByName('paragraph', 'field_landing_blog_category');
if ($category && $category->getCardinality() !== FieldStorageConfig::CARDINALITY_UNLIMITED) {
  $category->setCardinality(FieldStorageConfig::CARDINALITY_UNLIMITED)->save();
  echo "Opened paragraph.field_landing_blog_category up to any number of terms\n";
}
if (!$vocabularies && !FieldConfig::loadByName('paragraph', $hero, 'field_landing_blog_category')) {
  echo "Skipped field_landing_blog_category: Article has no field_article_category with a vocabulary. Add the field by hand.\n";
}
else {
  $add($hero, 'field_landing_blog_category', [
    'type' => 'entity_reference',
    'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    'settings' => ['target_type' => 'taxonomy_term'],
  ], [
    'label' => 'Blog categories',
    'description' => 'The "Latest from the blog" card shows the newest article in any of these Article Categories.',
    'settings' => [
      'handler' => 'default:taxonomy_term',
      'handler_settings' => [
        'target_bundles' => $vocabularies,
        'auto_create' => FALSE,
      ],
    ],
  ]);
}

$form = $repo->getFormDisplay('paragraph', $hero, 'default');
$weight = (int) max(array_column($form->getComponents(), 'weight') ?: [0]);
if (!$form->getComponent('field_hero_tiles')) {
  $form->setComponent('field_hero_tiles', [
    'type' => 'paragraphs',
    'weight' => ++$weight,
    'settings' => [
      'title' => 'Tile',
      'title_plural' => 'Tiles',
      'edit_mode' => 'closed',
      'closed_mode' => 'summary',
      'add_mode' => 'button',
      'form_display_mode' => 'default',
      'default_paragraph_type' => $tile,
    ],
  ]);
}
if (FieldConfig::loadByName('paragraph', $hero, 'field_landing_blog_category')) {
  // Checkboxes: every category in view, any number ticked.
  $form->setComponent('field_landing_blog_category', ['type' => 'options_buttons', 'weight' => ++$weight]);
}
$form->save();

$view = $repo->getViewDisplay('paragraph', $hero, 'default');
$view->removeComponent('field_hero_tiles');
$view->removeComponent('field_landing_blog_category');
$view->save();

echo "Done. Run drush cr.\n";
