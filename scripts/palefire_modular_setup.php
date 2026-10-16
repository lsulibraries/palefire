<?php

/**
 * @file
 * One-time, idempotent setup for Modular page sections (Paragraphs).
 *
 * Run from the Drupal root:
 *   drush php:script web/themes/custom/palefire/scripts/palefire_modular_setup.php
 *
 * Creates:
 * - Paragraph types: section_text ("Section: Text"),
 *   section_media ("Section: Text + image").
 * - Shared section fields on both types: title, anchor, show-in-TOC,
 *   full-page (scroll-snap), style.
 * - node.modular_page.field_sections (unlimited, re-orderable Paragraphs).
 * - Form and view displays.
 * - Optional Visit info on node.modular_page: field_intro, field_hours_location
 *   (LibCal), field_location_name, field_location_query, field_location_display.
 * - A "light-map" value on palefire_modular's field_section_theme.
 *
 * Safe to re-run: existing config is left untouched.
 * Afterwards export config (drush cex) so it lives in your config sync dir.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;

if (PHP_SAPI !== 'cli') {
  return;
}

$bundle = 'modular_page';

// 0. Requirements. -------------------------------------------------------------
if (!NodeType::load($bundle)) {
  throw new \RuntimeException("Content type '$bundle' not found.");
}
$needed = array_filter(
  ['paragraphs', 'entity_reference_revisions', 'text', 'options', 'image'],
  fn ($m) => !\Drupal::moduleHandler()->moduleExists($m)
);
if ($needed) {
  // Throws if the code is missing: composer require drupal/paragraphs.
  \Drupal::service('module_installer')->install(array_values($needed));
  echo 'Enabled: ' . implode(', ', $needed) . PHP_EOL;
}

/** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $displays */
$displays = \Drupal::service('entity_display.repository');

// Helpers. ---------------------------------------------------------------------
$storage = function (string $entity_type, string $name, string $type, array $settings = [], int $cardinality = 1): void {
  if (!FieldStorageConfig::loadByName($entity_type, $name)) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => $entity_type,
      'type' => $type,
      'settings' => $settings,
      'cardinality' => $cardinality,
    ])->save();
    echo "Created storage $entity_type.$name" . PHP_EOL;
  }
};
$field = function (string $entity_type, string $bundle, string $name, array $values): void {
  if (!FieldConfig::loadByName($entity_type, $bundle, $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
    ] + $values)->save();
    echo "Created field $entity_type.$bundle.$name" . PHP_EOL;
  }
};

// 1. Paragraph types. ----------------------------------------------------------
$types = [
  'section_text' => ['Section: Text', 'Heading + formatted text.'],
  'section_media' => ['Section: Text + image', 'Heading, text, and an image beside it.'],
];
foreach ($types as $id => [$label, $description]) {
  if (!ParagraphsType::load($id)) {
    ParagraphsType::create(['id' => $id, 'label' => $label, 'description' => $description])->save();
    echo "Created paragraph type $id" . PHP_EOL;
  }
}

// 2. Field storages (paragraph fields are shared across bundles). ------------
$storage('paragraph', 'field_section_title', 'string', ['max_length' => 255]);
$storage('paragraph', 'field_section_anchor', 'string', ['max_length' => 64]);
$storage('paragraph', 'field_show_in_toc', 'boolean');
$storage('paragraph', 'field_full_page', 'boolean');
$storage('paragraph', 'field_section_style', 'list_string', [
  'allowed_values' => [
    ['value' => 'default', 'label' => 'Default (page background)'],
    ['value' => 'light', 'label' => 'Light'],
    ['value' => 'dark', 'label' => 'Dark'],
    ['value' => 'brand', 'label' => 'Brand (purple)'],
  ],
]);
$storage('paragraph', 'field_section_body', 'text_long');
$storage('paragraph', 'field_section_image', 'image', [
  'uri_scheme' => 'public',
  'default_image' => ['uuid' => NULL, 'alt' => '', 'title' => '', 'width' => NULL, 'height' => NULL],
]);
$storage('paragraph', 'field_image_position', 'list_string', [
  'allowed_values' => [
    ['value' => 'end', 'label' => 'Image after text (right on wide screens)'],
    ['value' => 'start', 'label' => 'Image before text (left on wide screens)'],
  ],
]);
$storage('node', 'field_sections', 'entity_reference_revisions', ['target_type' => 'paragraph'], -1);

// 3. Fields per paragraph type. ----------------------------------------------
$common = [
  'field_section_title' => [
    'label' => 'Section title',
    'required' => TRUE,
    'description' => 'Shown as the section heading (H2) and in the "On this page" navigation. Keep it short and descriptive.',
  ],
  'field_section_anchor' => [
    'label' => 'Anchor ID (optional)',
    'description' => 'Custom link fragment, e.g. "hours" gives /page#hours. Leave blank to generate one from the title.',
  ],
  'field_show_in_toc' => [
    'label' => 'Show in "On this page" navigation',
    'default_value' => [['value' => 1]],
    'settings' => ['on_label' => 'Yes', 'off_label' => 'No'],
  ],
  'field_full_page' => [
    'label' => 'Full-page section (scroll-snap)',
    'description' => 'Fill the screen and snap into place while scrolling (tablet and larger). Content taller than the screen still scrolls normally.',
    'default_value' => [['value' => 0]],
    'settings' => ['on_label' => 'Full page', 'off_label' => 'Standard'],
  ],
  'field_section_style' => [
    'label' => 'Section style',
    'required' => TRUE,
    'default_value' => [['value' => 'default']],
  ],
  'field_section_body' => [
    'label' => 'Body',
  ],
];
$media_only = [
  'field_section_image' => [
    'label' => 'Image',
    'required' => TRUE,
    'settings' => [
      'alt_field' => TRUE,
      'alt_field_required' => TRUE,
      'title_field' => FALSE,
      'file_extensions' => 'png jpg jpeg webp avif',
      'max_filesize' => '5 MB',
      'file_directory' => 'sections/[date:custom:Y]-[date:custom:m]',
    ],
  ],
  'field_image_position' => [
    'label' => 'Image position',
    'required' => TRUE,
    'default_value' => [['value' => 'end']],
  ],
];

$widgets = [
  'field_section_title' => ['type' => 'string_textfield', 'weight' => 0],
  'field_section_anchor' => ['type' => 'string_textfield', 'weight' => 1, 'settings' => ['size' => 30, 'placeholder' => 'e.g. hours']],
  'field_section_body' => ['type' => 'text_textarea', 'weight' => 2, 'settings' => ['rows' => 8]],
  'field_section_image' => ['type' => 'image_image', 'weight' => 3, 'settings' => ['preview_image_style' => 'thumbnail', 'progress_indicator' => 'throbber']],
  'field_image_position' => ['type' => 'options_select', 'weight' => 4],
  'field_section_style' => ['type' => 'options_select', 'weight' => 5],
  'field_full_page' => ['type' => 'boolean_checkbox', 'weight' => 6, 'settings' => ['display_label' => TRUE]],
  'field_show_in_toc' => ['type' => 'boolean_checkbox', 'weight' => 7, 'settings' => ['display_label' => TRUE]],
];
$formatters = [
  'field_section_body' => ['type' => 'text_default', 'label' => 'hidden', 'weight' => 0],
  'field_section_image' => ['type' => 'image', 'label' => 'hidden', 'weight' => 1, 'settings' => ['image_style' => 'large', 'image_link' => '', 'image_loading' => ['attribute' => 'lazy']]],
];
// Control fields never render as content; the theme reads them directly.
$hidden = ['field_section_title', 'field_section_anchor', 'field_show_in_toc', 'field_full_page', 'field_section_style', 'field_image_position'];

foreach (array_keys($types) as $type) {
  $fields = $common + ($type === 'section_media' ? $media_only : []);
  foreach ($fields as $name => $values) {
    $field('paragraph', $type, $name, $values);
  }

  $form = $displays->getFormDisplay('paragraph', $type, 'default');
  foreach ($fields as $name => $values) {
    $form->setComponent($name, $widgets[$name]);
  }
  $form->save();

  $view = $displays->getViewDisplay('paragraph', $type, 'default');
  foreach ($fields as $name => $values) {
    isset($formatters[$name]) ? $view->setComponent($name, $formatters[$name]) : $view->removeComponent($name);
  }
  foreach ($hidden as $name) {
    $view->removeComponent($name);
  }
  $view->save();
}

// 4. Sections field on Modular page. -----------------------------------------
$field('node', $bundle, 'field_sections', [
  'label' => 'Sections',
  'description' => 'Add sections, then drag to re-order. Each section title appears in the page\'s "On this page" navigation.',
  'settings' => [
    'handler' => 'default:paragraph',
    'handler_settings' => [
      'negate' => 0,
      'target_bundles' => array_combine(array_keys($types), array_keys($types)),
      'target_bundles_drag_drop' => [
        'section_text' => ['enabled' => TRUE, 'weight' => 0],
        'section_media' => ['enabled' => TRUE, 'weight' => 1],
      ],
    ],
  ],
]);

$displays->getFormDisplay('node', $bundle, 'default')
  ->setComponent('field_sections', [
    'type' => 'paragraphs',
    'weight' => 5,
    'settings' => [
      'title' => 'Section',
      'title_plural' => 'Sections',
      'edit_mode' => 'closed',
      'closed_mode' => 'summary',
      'autocollapse' => 'none',
      'closed_mode_threshold' => 0,
      'add_mode' => 'modal',
      'form_display_mode' => 'default',
      'default_paragraph_type' => '_none',
      'features' => [
        'duplicate' => 'duplicate',
        'collapse_edit_all' => 'collapse_edit_all',
        'add_above' => 'add_above',
      ],
    ],
  ])
  ->save();

foreach (['default', 'full'] as $mode) {
  if ($mode === 'full' && !array_key_exists('full', $displays->getViewModeOptionsByBundle('node', $bundle))) {
    continue;
  }
  $displays->getViewDisplay('node', $bundle, $mode)
    ->setComponent('field_sections', [
      'type' => 'entity_reference_revisions_entity_view',
      'label' => 'hidden',
      'weight' => 5,
      'settings' => ['view_mode' => 'default'],
    ])
    ->save();
}

// 5. Visit info on Modular page (all optional). ------------------------------
// Shown beside the page title as the "Visit" card (Option B): intro text,
// today's hours from LibCal, and a Google Maps link / click-to-load map.
// Each part only prints when its field is filled in.
//
// LibCal location IDs (the "lid" in LibCal's hours widget). Edit the labels
// here, re-run the script, or change them later in Field UI.
$libcal_locations = [
  ['value' => '241', 'label' => 'LibCal location 241'],
  ['value' => '236', 'label' => 'LibCal location 236'],
];
$storage('node', 'field_intro', 'text_long');
$storage('node', 'field_hours_location', 'list_string', ['allowed_values' => $libcal_locations]);
$storage('node', 'field_location_name', 'string', ['max_length' => 255]);
$storage('node', 'field_location_query', 'string', ['max_length' => 255]);
$storage('node', 'field_location_display', 'list_string', [
  'allowed_values' => [
    ['value' => 'link', 'label' => 'Link to Google Maps only'],
    ['value' => 'embed', 'label' => 'Link + mini map (loads on click)'],
  ],
]);

$visit = [
  'field_intro' => [
    'label' => 'Intro text',
    'description' => 'A short paragraph shown under the page title, above the search form.',
    'widget' => ['type' => 'text_textarea', 'settings' => ['rows' => 4]],
    'formatter' => ['type' => 'text_default'],
  ],
  'field_hours_location' => [
    'label' => 'Hours (LibCal location)',
    'description' => 'Shows "Open now / Closed now" and today\'s hours from LibCal. Leave as "- None -" to hide hours.',
    'widget' => ['type' => 'options_select'],
    'formatter' => ['type' => 'list_key'],
  ],
  'field_location_name' => [
    'label' => 'Location name',
    'description' => 'e.g. Hill Memorial Library. Shown on the Visit card and used as the map link text.',
    'widget' => ['type' => 'string_textfield', 'settings' => ['size' => 60, 'placeholder' => 'Hill Memorial Library']],
    'formatter' => ['type' => 'string'],
  ],
  'field_location_query' => [
    'label' => 'Map search',
    'description' => 'What Google Maps should search for, e.g. "Hill Memorial Library, Baton Rouge, LA 70803". Leave blank to hide the map.',
    'widget' => ['type' => 'string_textfield', 'settings' => ['size' => 60, 'placeholder' => 'Hill Memorial Library, Baton Rouge, LA 70803']],
    'formatter' => ['type' => 'string'],
  ],
  'field_location_display' => [
    'label' => 'Map display',
    'description' => 'A link only, or a link plus a small map that loads when someone clicks it.',
    'default_value' => [['value' => 'link']],
    'widget' => ['type' => 'options_select'],
    'formatter' => ['type' => 'list_key'],
  ],
];
$weight = 1;
$form = $displays->getFormDisplay('node', $bundle, 'default');
foreach ($visit as $name => $info) {
  $field('node', $bundle, $name, array_intersect_key($info, array_flip(['label', 'description', 'default_value'])));
  if (!$form->getComponent($name)) {
    $form->setComponent($name, $info['widget'] + ['weight' => $weight]);
  }
  $weight++;
}
$form->save();

// The theme prints these fields itself (paragraph--bento-search.html.twig:
// intro + "Visit" card, via _palefire_bento_visit() in palefire.theme), so
// they stay hidden in the node's view displays.
foreach (['default', 'full'] as $mode) {
  if ($mode === 'full' && !array_key_exists('full', $displays->getViewModeOptionsByBundle('node', $bundle))) {
    continue;
  }
  $view = $displays->getViewDisplay('node', $bundle, $mode);
  foreach (array_keys($visit) as $name) {
    $view->removeComponent($name);
  }
  $view->save();
}

// 6. "Light, dark at the map" section theme. ---------------------------------
// palefire_modular's field_section_theme: adds the light-map value, which
// keeps the bento section light until its building map scrolls into view
// (building-map.js), then switches the page chrome to dark.
$theme_storage = FieldStorageConfig::loadByName('paragraph', 'field_section_theme');
if ($theme_storage && $theme_storage->getType() === 'list_string') {
  $allowed = $theme_storage->getSetting('allowed_values');
  if (!isset($allowed['light-map'])) {
    $allowed['light-map'] = 'Light, dark at the map';
    $theme_storage->setSetting('allowed_values', $allowed)->save();
    echo 'Added field_section_theme value light-map' . PHP_EOL;
  }
}
else {
  echo 'Note: paragraph.field_section_theme not found; add a "light-map" option to your section theme list by hand.' . PHP_EOL;
}

echo 'Palefire modular page setup complete. Run: drush cr && drush cex' . PHP_EOL;
