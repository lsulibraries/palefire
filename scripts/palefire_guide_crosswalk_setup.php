<?php

/**
 * @file
 * Guide block "Field crosswalk": an interactive chart of which MODS elements
 * become which ingest CSV columns, per institution, drawn from the shared
 * master mapping. Run palefire_guide_setup.php first.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_crosswalk_setup.php
 *   drush cr
 *
 * Config only: it never creates or changes content. The guide page itself
 * comes from palefire_guide_mods_crosswalk.php.
 *
 * Editors need no code. The chart is drawn in the browser
 * (js/guide-crosswalk.js) from the mapping spreadsheet uploaded to the block,
 * so updating the page means uploading a new copy of the spreadsheet:
 * in Excel, File > Save a copy > "CSV UTF-8 (Comma delimited)", then
 * Remove and Upload on the block. Everything else is a labelled field:
 *
 * Adds paragraph type guide_crosswalk ("Guide: Field crosswalk"):
 *   field_block_title         Heading (shared with the other guide blocks).
 *   field_block_intro         Intro, WYSIWYG (shared).
 *   field_crosswalk_file      Mapping spreadsheet (.csv), required.
 *   field_crosswalk_default   Institution shown first, by its exact name.
 *   field_crosswalk_caption   Small print under the chart, WYSIWYG.
 *   field_crosswalk_table     Show the table of every mapping row (on).
 *   field_crosswalk_tbd       Show columns still marked TBD (on).
 * and offers it in a guide section's Content and a Section tabs tab.
 *
 * Template: paragraph--guide-crosswalk.html.twig. Script and styles:
 * library palefire/guide-crosswalk (js/guide-crosswalk.js,
 * scss/guide-crosswalk.scss compiled to css/guide-crosswalk.css).
 *
 * Safe to re-run: existing types, fields and display settings are kept.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\paragraphs\Entity\ParagraphsType;

if (PHP_SAPI !== 'cli') {
  return;
}

if (!ParagraphsType::load('guide_section') || !FieldConfig::loadByName('paragraph', 'guide_section', 'field_guide_blocks')) {
  throw new \RuntimeException('guide_section not found. Run palefire_guide_setup.php first.');
}
foreach (['field_block_title', 'field_block_intro'] as $name) {
  if (!FieldStorageConfig::loadByName('paragraph', $name)) {
    throw new \RuntimeException("paragraph.$name not found. Run palefire_guide_setup.php first.");
  }
}
if (!\Drupal::moduleHandler()->moduleExists('file')) {
  \Drupal::service('module_installer')->install(['file']);
  echo 'Enabled: file' . PHP_EOL;
}

// Helpers (as in palefire_louisiana_newspapers_setup.php). ------------------

$storage = function (string $name, string $type, array $settings = [], int $cardinality = 1): void {
  $existing = FieldStorageConfig::loadByName('paragraph', $name);
  if ($existing) {
    if ($existing->getType() !== $type) {
      throw new \RuntimeException("paragraph.$name already exists as '{$existing->getType()}', not '$type'. Rename one of them and re-run.");
    }
    return;
  }
  FieldStorageConfig::create([
    'field_name' => $name,
    'entity_type' => 'paragraph',
    'type' => $type,
    'settings' => $settings,
    'cardinality' => $cardinality,
  ])->save();
  echo "Created storage paragraph.$name" . PHP_EOL;
};

$field = function (string $bundle, string $name, array $values): void {
  if (!FieldConfig::loadByName('paragraph', $bundle, $name)) {
    FieldConfig::create(['field_name' => $name, 'entity_type' => 'paragraph', 'bundle' => $bundle] + $values)->save();
    echo "Created field paragraph.$bundle.$name" . PHP_EOL;
  }
};

$repo = \Drupal::service('entity_display.repository');

// Type. ----------------------------------------------------------------------

$bundle = 'guide_crosswalk';
if (!ParagraphsType::load($bundle)) {
  ParagraphsType::create([
    'id' => $bundle,
    'label' => 'Guide: Field crosswalk',
    'description' => 'A chart of which MODS elements become which CSV columns, for each institution, drawn from an uploaded copy of the master mapping spreadsheet.',
  ])->save();
  echo "Created paragraph type $bundle" . PHP_EOL;
}

// Storages. ------------------------------------------------------------------

$storage('field_crosswalk_file', 'file', [
  'uri_scheme' => 'public',
  'display_field' => FALSE,
  'display_default' => FALSE,
]);
$storage('field_crosswalk_default', 'string', ['max_length' => 255]);
$storage('field_crosswalk_caption', 'text_long');
$storage('field_crosswalk_table', 'boolean');
$storage('field_crosswalk_tbd', 'boolean');

// Fields, in form order. -----------------------------------------------------

$field($bundle, 'field_block_title', [
  'label' => 'Heading',
  'description' => 'Optional. An h3 above the chart, listed in "On this page".',
]);
$field($bundle, 'field_block_intro', ['label' => 'Intro (optional)']);
$field($bundle, 'field_crosswalk_file', [
  'label' => 'Mapping spreadsheet (CSV)',
  'required' => TRUE,
  'description' => 'The master mapping, saved as CSV. In Excel open mapping.xlsx, choose File > Save a copy, and pick "CSV UTF-8 (Comma delimited)". To update the chart, Remove the old file and upload the new one. The file needs the columns xpaths, needed, att_needed, field_name, att_field_name and institution_list.',
  'settings' => [
    'file_extensions' => 'csv',
    'file_directory' => 'guides/crosswalk',
    'max_filesize' => '20 MB',
    'description_field' => FALSE,
  ],
]);
$field($bundle, 'field_crosswalk_default', [
  'label' => 'Institution shown first',
  'description' => 'Optional. Its name exactly as in the spreadsheet\'s institution_list, e.g. "Louisiana State University". Leave blank to show the institution with the most mapping rows.',
]);
$field($bundle, 'field_crosswalk_caption', [
  'label' => 'Caption',
  'description' => 'Optional small print under the chart: where the spreadsheet came from and when it was saved.',
]);
$field($bundle, 'field_crosswalk_table', [
  'label' => 'Show a table of every mapping row under the chart',
  'default_value' => [['value' => 1]],
  'settings' => ['on_label' => 'Show', 'off_label' => 'Hide'],
]);
$field($bundle, 'field_crosswalk_tbd', [
  'label' => 'Show columns still marked TBD in the mapping',
  'default_value' => [['value' => 1]],
  'settings' => ['on_label' => 'Show', 'off_label' => 'Hide'],
]);

// Form: widgets not set yet, so later edits in Field UI stand. ---------------

$form = $repo->getFormDisplay('paragraph', $bundle, 'default');
$widgets = [
  'field_block_title' => ['type' => 'string_textfield', 'weight' => 0],
  'field_block_intro' => ['type' => 'text_textarea', 'weight' => 1, 'settings' => ['rows' => 4, 'placeholder' => '']],
  'field_crosswalk_file' => ['type' => 'file_generic', 'weight' => 2, 'settings' => ['progress_indicator' => 'throbber']],
  'field_crosswalk_default' => ['type' => 'string_textfield', 'weight' => 3, 'settings' => ['size' => 60, 'placeholder' => 'Louisiana State University']],
  'field_crosswalk_caption' => ['type' => 'text_textarea', 'weight' => 4, 'settings' => ['rows' => 2, 'placeholder' => '']],
  'field_crosswalk_table' => ['type' => 'boolean_checkbox', 'weight' => 5, 'settings' => ['display_label' => TRUE]],
  'field_crosswalk_tbd' => ['type' => 'boolean_checkbox', 'weight' => 6, 'settings' => ['display_label' => TRUE]],
];
foreach ($widgets as $name => $widget) {
  if (!$form->getComponent($name)) {
    $form->setComponent($name, $widget);
  }
}
$form->save();

// View: the template prints everything itself but the intro. -----------------

$view = $repo->getViewDisplay('paragraph', $bundle, 'default');
if ($view->isNew()) {
  foreach (array_keys($view->getComponents()) as $name) {
    $view->removeComponent($name);
  }
  $view->setComponent('field_block_intro', ['type' => 'text_default', 'label' => 'hidden', 'weight' => 0]);
  $view->setStatus(TRUE);
  $view->save();
  echo "Set up paragraph.$bundle.default display" . PHP_EOL;
}

// Offer it in a guide section's Content and a Section tabs tab. -------------

foreach (['guide_section', 'section_tab'] as $parent) {
  $config = FieldConfig::loadByName('paragraph', $parent, 'field_guide_blocks');
  if (!$config) {
    continue;
  }
  $handler = $config->getSetting('handler_settings') ?: [];
  // An empty list (or a negated one) already allows every type.
  if (empty($handler['target_bundles']) || !empty($handler['negate'])) {
    continue;
  }
  if (empty($handler['target_bundles'][$bundle])) {
    $handler['target_bundles'][$bundle] = $bundle;
    $handler['target_bundles_drag_drop'][$bundle] = ['enabled' => TRUE, 'weight' => count($handler['target_bundles_drag_drop'] ?? [])];
    $config->setSetting('handler_settings', $handler)->save();
    echo "Allowed $bundle in paragraph.$parent.field_guide_blocks" . PHP_EOL;
  }
}

echo 'Done. Run: drush cr' . PHP_EOL;
