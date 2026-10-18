<?php

/**
 * @file
 * Creates the "Tile showcase" paragraph type (tile_showcase).
 *
 * One paragraph type for the Special Collections concept's How-to guides,
 * Collections and Specializations sections: a list of Tile nodes shown as
 * image cards, in a staggered row or a grid.
 * Template: paragraph--tile-showcase.html.twig.
 *
 * Run from the Drupal root on production:
 *   drush php:script themes/custom/palefire/scripts/palefire_tile_showcase_setup.php
 *   drush cr
 *
 * What it does:
 * - Creates the paragraph type tile_showcase.
 * - Adds two new fields:
 *     field_tile_layout  Layout: "Staggered row" (stagger) or "Grid" (grid)
 *     field_tiles        Tiles: references to Tile nodes, unlimited, in order
 * - Adds the shared section fields from bento_search (title, anchor, snap,
 *   height, theme, hide from TOC, background), copying each field's
 *   settings, form widget and display. The storages are shared, so no new
 *   tables are needed for these. A field bento_search doesn't have is
 *   skipped and reported.
 * - Allows tile_showcase in every paragraph reference field that already
 *   allows bento_search, such as the Modular page's sections.
 * - Image styles for the cards (Tile covers are 16:9, 1920 x 1080):
 *     tile_showcase_portrait_sm   432 x 720   (staggered row)
 *     tile_showcase_portrait_md   648 x 1080
 *     tile_showcase_landscape_sm  640 x 400   (grid)
 *     tile_showcase_landscape_md  960 x 600
 *     tile_showcase_landscape_lg  1280 x 800
 *   All scale and crop from the centre, and convert to WebP when the image
 *   toolkit supports it.
 * - Responsive image styles tile_showcase_portrait and
 *   tile_showcase_landscape: one srcset with a sizes attribute (core's
 *   "responsive_image" viewport-sizing breakpoint), so the browser picks the
 *   smallest file that fills the card.
 *
 * Safe to re-run: anything that already exists is left as it is.
 *
 * List fields take allowed_values as [key => label], the runtime format
 * FieldStorageConfig::create() expects (not the YAML [['value', 'label']]).
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;

if (PHP_SAPI !== 'cli') {
  return;
}

$type = 'tile_showcase';
$source = 'bento_search';

if (!ParagraphsType::load($source)) {
  echo "Paragraph type bento_search not found (palefire_modular). Install it first, then re-run.\n";
  return;
}
if (!NodeType::load('tile')) {
  echo "Content type tile not found.\n";
  return;
}
foreach (['options', 'image', 'responsive_image'] as $module) {
  if (!\Drupal::moduleHandler()->moduleExists($module)) {
    \Drupal::service('module_installer')->install([$module]);
    echo "Enabled $module\n";
  }
}

// 1. Paragraph type. ----------------------------------------------------------
if (!ParagraphsType::load($type)) {
  ParagraphsType::create([
    'id' => $type,
    'label' => 'Tile showcase',
    'description' => 'Tiles as image cards, in a staggered row (How-to guides, Specializations) or a grid (Collections).',
  ])->save();
  echo "Created paragraph type $type\n";
}

$repo = \Drupal::service('entity_display.repository');
$form = $repo->getFormDisplay('paragraph', $type, 'default');
$view = $repo->getViewDisplay('paragraph', $type, 'default');

// 2. Shared section fields, copied from bento_search. ------------------------
$source_form = $repo->getFormDisplay('paragraph', $source, 'default');
$source_view = $repo->getViewDisplay('paragraph', $source, 'default');
$shared = [
  'field_section_title',
  'field_section_anchor',
  'field_section_snap',
  'field_section_height',
  'field_section_theme',
  'field_section_toc_hide',
  'field_section_bg',
];
foreach ($shared as $name) {
  $from = FieldConfig::loadByName('paragraph', $source, $name);
  if (!$from) {
    echo "Skipped $name: bento_search doesn't have it.\n";
    continue;
  }
  if (!FieldConfig::loadByName('paragraph', $type, $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => $type,
      'label' => $from->getLabel(),
      'description' => $from->getDescription(),
      'required' => $from->isRequired(),
      'settings' => $from->getSettings(),
      'default_value' => $from->getDefaultValueLiteral(),
    ])->save();
    echo "Added $name to paragraph.$type\n";
  }
  if (($component = $source_form->getComponent($name)) && !$form->getComponent($name)) {
    $form->setComponent($name, $component);
  }
  // palefire_modular and the field template print these; copy whatever
  // bento_search does (shown or hidden) so sections render the same.
  if ($component = $source_view->getComponent($name)) {
    $view->setComponent($name, $component);
  }
  else {
    $view->removeComponent($name);
  }
}

// 3. Layout and tiles. --------------------------------------------------------
if (!FieldStorageConfig::loadByName('paragraph', 'field_tile_layout')) {
  FieldStorageConfig::create([
    'field_name' => 'field_tile_layout',
    'entity_type' => 'paragraph',
    'type' => 'list_string',
    'settings' => [
      'allowed_values' => [
        'stagger' => 'Staggered row (tall cards)',
        'grid' => 'Grid (3 columns)',
      ],
    ],
  ])->save();
  echo "Created storage paragraph.field_tile_layout\n";
}
if (!FieldStorageConfig::loadByName('paragraph', 'field_tiles')) {
  FieldStorageConfig::create([
    'field_name' => 'field_tiles',
    'entity_type' => 'paragraph',
    'type' => 'entity_reference',
    'cardinality' => -1,
    'settings' => ['target_type' => 'node'],
  ])->save();
  echo "Created storage paragraph.field_tiles\n";
}
if (!FieldConfig::loadByName('paragraph', $type, 'field_tile_layout')) {
  FieldConfig::create([
    'field_name' => 'field_tile_layout',
    'entity_type' => 'paragraph',
    'bundle' => $type,
    'label' => 'Layout',
    'description' => 'Staggered row: tall cards on an offset rhythm, swipeable on phones. Grid: three columns, two on tablets, one on phones.',
    'required' => TRUE,
    'default_value' => [['value' => 'stagger']],
  ])->save();
  echo "Added field_tile_layout to paragraph.$type\n";
}
if (!FieldConfig::loadByName('paragraph', $type, 'field_tiles')) {
  FieldConfig::create([
    'field_name' => 'field_tiles',
    'entity_type' => 'paragraph',
    'bundle' => $type,
    'label' => 'Tiles',
    'description' => "Shown in this order. Each card uses the Tile's title, Tile Cover image, Category terms (as the label, e.g. \"Dept + Medium\") and links to the Tile's own page. Unpublished Tiles are left out.",
    'required' => TRUE,
    'settings' => [
      'handler' => 'default:node',
      'handler_settings' => [
        'target_bundles' => ['tile' => 'tile'],
        'sort' => ['field' => 'title', 'direction' => 'ASC'],
        'auto_create' => FALSE,
      ],
    ],
  ])->save();
  echo "Added field_tiles to paragraph.$type\n";
}

$weight = (int) max(array_column($form->getComponents(), 'weight') ?: [0]);
if (!$form->getComponent('field_tile_layout')) {
  $form->setComponent('field_tile_layout', ['type' => 'options_buttons', 'weight' => ++$weight]);
}
if (!$form->getComponent('field_tiles')) {
  $form->setComponent('field_tiles', [
    'type' => 'entity_reference_autocomplete',
    'weight' => ++$weight,
    'settings' => ['match_operator' => 'CONTAINS', 'match_limit' => 10, 'size' => 60, 'placeholder' => 'Start typing a Tile title'],
  ]);
}
$form->save();

// The template prints the cards itself.
$view->removeComponent('field_tile_layout');
$view->removeComponent('field_tiles');
$view->save();

// 4. Allow tile_showcase wherever bento_search is allowed. -------------------
foreach (FieldConfig::loadMultiple() as $config) {
  if (!in_array($config->getType(), ['entity_reference_revisions', 'entity_reference'], TRUE)
    || $config->getSetting('target_type') !== 'paragraph') {
    continue;
  }
  $handler = $config->getSetting('handler_settings') ?: [];
  $bundles = $handler['target_bundles'] ?? NULL;
  // Paragraphs' "exclude" mode lists bundles that are NOT allowed; any
  // other bundle, including the new one, is already allowed.
  if (($handler['negate'] ?? FALSE) || !is_array($bundles) || !isset($bundles[$source]) || isset($bundles[$type])) {
    continue;
  }
  $handler['target_bundles'][$type] = $type;
  if (isset($handler['target_bundles_drag_drop'])) {
    $handler['target_bundles_drag_drop'][$type] = ['enabled' => TRUE, 'weight' => count($handler['target_bundles_drag_drop'])];
  }
  $config->setSetting('handler_settings', $handler)->save();
  echo "Allowed tile_showcase in " . $config->id() . "\n";
}

// 5. Image styles. ------------------------------------------------------------
$toolkit = \Drupal::service('image.toolkit.manager')->getDefaultToolkit();
$webp = $toolkit && in_array('webp', $toolkit->getSupportedExtensions(), TRUE);
if (!$webp) {
  echo "Note: the image toolkit can't write WebP; the card images stay JPEG/PNG.\n";
}
$styles = [
  'tile_showcase_portrait_sm' => ['Tile showcase portrait 432', 432, 720],
  'tile_showcase_portrait_md' => ['Tile showcase portrait 648', 648, 1080],
  'tile_showcase_landscape_sm' => ['Tile showcase landscape 640', 640, 400],
  'tile_showcase_landscape_md' => ['Tile showcase landscape 960', 960, 600],
  'tile_showcase_landscape_lg' => ['Tile showcase landscape 1280', 1280, 800],
];
foreach ($styles as $id => [$label, $width, $height]) {
  if (ImageStyle::load($id)) {
    continue;
  }
  $style = ImageStyle::create(['name' => $id, 'label' => $label]);
  $style->addImageEffect([
    'id' => 'image_scale_and_crop',
    'weight' => 1,
    'data' => ['width' => $width, 'height' => $height, 'anchor' => 'center-center'],
  ]);
  if ($webp) {
    $style->addImageEffect(['id' => 'image_convert', 'weight' => 2, 'data' => ['extension' => 'webp']]);
  }
  $style->save();
  echo "Created image style $id\n";
}

// 6. Responsive image styles. -------------------------------------------------
// sizes: how wide the card is, roughly, at each width.
// Portrait: a 17rem card in the swipeable row; a fifth to a quarter of the
// window beside the sidebar on wide screens.
// Landscape: full width, then half, then a third beside the sidebar.
$responsive = [
  'tile_showcase_portrait' => [
    'label' => 'Tile showcase portrait',
    'fallback' => 'tile_showcase_portrait_md',
    'sizes' => '(min-width: 1200px) 22vw, (min-width: 992px) 28vw, 17rem',
    'styles' => ['tile_showcase_portrait_sm', 'tile_showcase_portrait_md'],
  ],
  'tile_showcase_landscape' => [
    'label' => 'Tile showcase landscape',
    'fallback' => 'tile_showcase_landscape_md',
    'sizes' => '(min-width: 1200px) 28vw, (min-width: 576px) 50vw, 100vw',
    'styles' => ['tile_showcase_landscape_sm', 'tile_showcase_landscape_md', 'tile_showcase_landscape_lg'],
  ],
];
$storage = \Drupal::entityTypeManager()->getStorage('responsive_image_style');
foreach ($responsive as $id => $info) {
  if ($storage->load($id)) {
    continue;
  }
  $storage->create([
    'id' => $id,
    'label' => $info['label'],
    'breakpoint_group' => 'responsive_image',
    'fallback_image_style' => $info['fallback'],
    'image_style_mappings' => [
      [
        'breakpoint_id' => 'responsive_image.viewport_sizing',
        'multiplier' => '1x',
        'image_mapping_type' => 'sizes',
        'image_mapping' => [
          'sizes' => $info['sizes'],
          'sizes_image_styles' => $info['styles'],
        ],
      ],
    ],
  ])->save();
  echo "Created responsive image style $id\n";
}

echo "Tile showcase setup complete. Run: drush cr\n";
