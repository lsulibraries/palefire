<?php

/**
 * @file
 * Adds the "Covers" layout to the Tile showcase: books, not pages.
 *
 * For "Published from the Collections" on the Special Collections homepage:
 * books and films that used Hill's collections, each its cover (or a jacket
 * set in type when it has none), title, author and imprint, linked to its
 * catalog record. Templates: paragraph--tile-showcase.html.twig (the books)
 * and field--node--field-scroll-section--modular-page.html.twig (the rule,
 * heading and lede). Preprocess: _palefire_publications().
 *
 * Run from the Drupal root on production, after
 * palefire_tile_showcase_setup.php and palefire_tile_showcase_gallery_setup.php:
 *   drush php:script themes/custom/palefire/scripts/palefire_publications_setup.php
 *   drush cr
 *
 * What it does:
 * - Creates the paragraph type publication (one book), with:
 *     field_pub_title    Title, required: the full title. The cover and
 *                        the card's heading set the part before the first
 *                        colon large; the subtitle follows, quieter.
 *     field_pub_author   Author: "Robert Mann", "Edited by …".
 *     field_pub_imprint  Publisher and year: "LSU Press, 2025".
 *     field_pub_link     Link: the catalog record.
 *     field_pub_cover    Cover: an Image media item, optional. Without
 *                        one the card sets a jacket in type.
 * - Adds field_tile_publications (Publications: Publication paragraphs,
 *   unlimited, in order) to tile_showcase.
 * - Adds "covers" to the Layout field's allowed values (field_tile_layout).
 * - Makes Tiles (field_tiles) optional on tile_showcase: the Covers and Ask
 *   layouts have none, and a required Tiles field kept editors from saving
 *   those sections in the UI. The other layouts show nothing without tiles.
 * - Image style publication_cover: scaled to 480 wide (twice the widest
 *   card), never upscaled or cropped, WebP when the toolkit supports it.
 *
 * Safe to re-run: anything that already exists is left as it is.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\image\Entity\ImageStyle;
use Drupal\paragraphs\Entity\ParagraphsType;

if (PHP_SAPI !== 'cli') {
  return;
}

$type = 'tile_showcase';
if (!ParagraphsType::load($type)) {
  echo "Paragraph type tile_showcase not found. Run palefire_tile_showcase_setup.php first.\n";
  return;
}
$installer = \Drupal::service('module_installer');
foreach (['paragraphs', 'entity_reference_revisions', 'link', 'image', 'media'] as $module) {
  if (!\Drupal::moduleHandler()->moduleExists($module)) {
    $installer->install([$module]);
    echo "Enabled $module\n";
  }
}

$repo = \Drupal::service('entity_display.repository');

$storage = function (string $name, string $field_type, array $settings = [], int $cardinality = 1) {
  if (!FieldStorageConfig::loadByName('paragraph', $name)) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'type' => $field_type,
      'settings' => $settings,
      'cardinality' => $cardinality,
    ])->save();
    echo "Created storage paragraph.$name\n";
  }
};

$field = function (string $bundle, string $name, string $label, array $values = []) {
  if (!FieldConfig::loadByName('paragraph', $bundle, $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => $bundle,
      'label' => $label,
    ] + $values)->save();
    echo "Added $name to paragraph.$bundle\n";
  }
};

// 1. The Publication paragraph. ------------------------------------------------
if (!ParagraphsType::load('publication')) {
  ParagraphsType::create([
    'id' => 'publication',
    'label' => 'Publication',
    'description' => 'One book or film in a Tile showcase\'s Covers layout: its cover, title, author, publisher and catalog link.',
  ])->save();
  echo "Created paragraph type publication\n";
}

$storage('field_pub_title', 'string', ['max_length' => 255]);
$storage('field_pub_author', 'string', ['max_length' => 255]);
$storage('field_pub_imprint', 'string', ['max_length' => 255]);
$storage('field_pub_link', 'link');

$field('publication', 'field_pub_title', 'Title', [
  'required' => TRUE,
  'description' => 'The full title, e.g. "Kingfish U: Huey Long and LSU". The part before the first colon is set large; the subtitle follows it, quieter.',
]);
$field('publication', 'field_pub_author', 'Author', [
  'description' => 'As the title page has it, e.g. "Robert Mann" or "Edited by Ryan Orgera and Wayne Parent".',
]);
$field('publication', 'field_pub_imprint', 'Publisher and year', [
  'description' => 'Short, e.g. "LSU Press, 2025".',
]);
$field('publication', 'field_pub_link', 'Link', [
  'settings' => ['link_type' => 17, 'title' => 0],
  'description' => 'The catalog record, so readers can request the book.',
]);

// The cover: an Image media item, as the rest of Modular, uploaded once with
// its credit and reused. Prefer the core "image" type when there are several.
$image_types = [];
foreach (\Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple() as $media_type) {
  if ($media_type->getSource()->getPluginId() === 'image') {
    $image_types[$media_type->id()] = $media_type->id();
  }
}
if (isset($image_types['image'])) {
  $image_types = ['image' => 'image'];
}
if ($image_types) {
  $storage('field_pub_cover', 'entity_reference', ['target_type' => 'media']);
  $field('publication', 'field_pub_cover', 'Cover', [
    'settings' => ['handler' => 'default:media', 'handler_settings' => ['target_bundles' => $image_types, 'auto_create' => FALSE]],
    'description' => 'Optional. The front cover, at least 480 pixels wide, once Special Collections has cleared its use. It is shown whole, never cropped. Without one the card sets a jacket in type.',
  ]);
}
else {
  echo "No Image media type found (Structure > Media types, source \"Image\"): covers skipped, every book gets a typeset jacket. Add one and re-run.\n";
}

$form = $repo->getFormDisplay('paragraph', 'publication', 'default');
$view = $repo->getViewDisplay('paragraph', 'publication', 'default');
$widgets = [
  'field_pub_title' => ['type' => 'string_textfield', 'settings' => ['size' => 60]],
  'field_pub_author' => ['type' => 'string_textfield', 'settings' => ['size' => 60]],
  'field_pub_imprint' => ['type' => 'string_textfield', 'settings' => ['size' => 40]],
  'field_pub_link' => ['type' => 'link_default'],
  'field_pub_cover' => \Drupal::moduleHandler()->moduleExists('media_library')
    ? ['type' => 'media_library_widget', 'settings' => ['media_types' => array_values($image_types)]]
    : ['type' => 'entity_reference_autocomplete', 'settings' => ['match_operator' => 'CONTAINS', 'size' => 60, 'placeholder' => '']],
];
$weight = 0;
foreach ($widgets as $name => $widget) {
  if (!FieldConfig::loadByName('paragraph', 'publication', $name)) {
    continue;
  }
  if (!$form->getComponent($name)) {
    $form->setComponent($name, $widget + ['weight' => $weight]);
  }
  $weight++;
  // The showcase's template prints these itself.
  $view->removeComponent($name);
}
$form->save();
$view->save();

// 2. Publications on the Tile showcase. -----------------------------------------
$storage('field_tile_publications', 'entity_reference_revisions', ['target_type' => 'paragraph'], -1);
$field($type, 'field_tile_publications', 'Publications', [
  'description' => 'Covers layout only: the books, in order. Eight make two even rows on a wide screen.',
  'settings' => [
    'handler' => 'default:paragraph',
    'handler_settings' => [
      'target_bundles' => ['publication' => 'publication'],
      'negate' => 0,
      'target_bundles_drag_drop' => ['publication' => ['enabled' => TRUE, 'weight' => 0]],
    ],
  ],
]);
$form = $repo->getFormDisplay('paragraph', $type, 'default');
$view = $repo->getViewDisplay('paragraph', $type, 'default');
if (!$form->getComponent('field_tile_publications')) {
  $tiles = $form->getComponent('field_tiles');
  $form->setComponent('field_tile_publications', [
    'type' => 'paragraphs',
    'weight' => ($tiles['weight'] ?? 10) + 1,
    'settings' => [
      'title' => 'Publication',
      'title_plural' => 'Publications',
      'edit_mode' => 'closed',
      'closed_mode' => 'summary',
      'autocollapse' => 'all',
      'add_mode' => 'button',
      'form_display_mode' => 'default',
      'default_paragraph_type' => 'publication',
    ],
  ])->save();
}
$view->removeComponent('field_tile_publications')->save();

// 3. The Covers layout. -----------------------------------------------------------
$layout = FieldStorageConfig::loadByName('paragraph', 'field_tile_layout');
if ($layout) {
  $allowed = $layout->getSetting('allowed_values');
  if (!isset($allowed['covers'])) {
    $allowed['covers'] = 'Covers (books, from Publications)';
    $layout->setSetting('allowed_values', $allowed)->save();
    echo "Added covers to field_tile_layout\n";
  }
}
else {
  echo "Storage paragraph.field_tile_layout not found: add \"covers\" to the Layout field by hand.\n";
}

// 4. Tiles optional. -------------------------------------------------------------
$tiles = FieldConfig::loadByName('paragraph', $type, 'field_tiles');
if ($tiles && $tiles->isRequired()) {
  $tiles->setRequired(FALSE)
    ->setDescription(trim($tiles->getDescription() . ' Leave empty for the Covers and Ask layouts.'))
    ->save();
  echo "Tiles (field_tiles) is now optional on tile_showcase\n";
}

// 5. The cover's image style. -------------------------------------------------------
if (!ImageStyle::load('publication_cover')) {
  $style = ImageStyle::create(['name' => 'publication_cover', 'label' => 'Publication cover (480 wide)']);
  $style->addImageEffect(['id' => 'image_scale', 'weight' => 1, 'data' => ['width' => 480, 'height' => NULL, 'upscale' => FALSE]]);
  $toolkit = \Drupal::service('image.toolkit.manager')->getDefaultToolkit();
  if ($toolkit && in_array('webp', $toolkit->getSupportedExtensions(), TRUE)) {
    $style->addImageEffect(['id' => 'image_convert', 'weight' => 2, 'data' => ['extension' => 'webp']]);
  }
  $style->save();
  echo "Created image style publication_cover\n";
}

echo "Publications setup complete. Run: drush cr\n";
