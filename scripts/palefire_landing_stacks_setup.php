<?php

/**
 * @file
 * Creates the "From the stacks" paragraph type (landing_stacks).
 *
 * The Special Collections homepage's last section: one featured item (a
 * Tile or a Modular page) beside the next three events and the newest blog
 * posts. Template: paragraph--landing-stacks.html.twig; preprocess:
 * palefire_preprocess_paragraph__landing_stacks(); events: js/upcoming.js.
 *
 * Run from the Drupal root on production:
 *   drush php:script themes/custom/palefire/scripts/palefire_landing_stacks_setup.php
 *   drush cr
 *
 * What it does:
 * - Creates the paragraph type landing_stacks.
 * - Adds the shared section fields from bento_search (title, anchor, snap,
 *   height, theme, hide from TOC, background), copying each field's
 *   settings, form widget and display, as the Tile showcase setup does.
 * - Reuses two existing storages:
 *     field_tiles                  Featured item: a Tile or a Modular page
 *                                  (the first one shows)
 *     field_landing_blog_category  Blog categories, with the Landing hero's
 *                                  settings (Article Category terms)
 * - Adds two new text fields, the events list's LibCal settings (the same
 *   ids the events carousel block uses, its data-calendar and data-campus):
 *     field_stacks_calendar  LibCal calendar ID (default 8030, "Events")
 *     field_stacks_campus    LibCal campus ID (default 262, Hill Memorial)
 * - Allows landing_stacks in every paragraph reference field that already
 *   allows bento_search, such as the Modular page's sections.
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

$type = 'landing_stacks';
$source = 'bento_search';

if (!ParagraphsType::load($source)) {
  echo "Paragraph type bento_search not found (palefire_modular). Install it first, then re-run.\n";
  return;
}
if (!FieldStorageConfig::loadByName('paragraph', 'field_tiles')) {
  echo "Storage paragraph.field_tiles not found: run palefire_tile_showcase_setup.php first.\n";
  return;
}

// 1. Paragraph type. ----------------------------------------------------------
if (!ParagraphsType::load($type)) {
  ParagraphsType::create([
    'id' => $type,
    'label' => 'From the stacks',
    'description' => 'A featured item beside the next events and the newest blog posts (Special Collections homepage).',
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

// 3. Featured item and blog categories (existing storages). ------------------
$featured_bundles = array_filter(
  ['tile' => 'tile', 'modular_page' => 'modular_page'],
  static fn (string $bundle): bool => (bool) NodeType::load($bundle),
);
if (!FieldConfig::loadByName('paragraph', $type, 'field_tiles')) {
  FieldConfig::create([
    'field_name' => 'field_tiles',
    'entity_type' => 'paragraph',
    'bundle' => $type,
    'label' => 'Featured item',
    'description' => 'An object, collection or exhibition to feature: a Tile or a Modular page. Only the first one shows; change it every week or so. Its card uses the cover image, title, Category terms and intro.',
    'required' => FALSE,
    'settings' => [
      'handler' => 'default:node',
      'handler_settings' => [
        'target_bundles' => $featured_bundles,
        'sort' => ['field' => 'title', 'direction' => 'ASC'],
        'auto_create' => FALSE,
      ],
    ],
  ])->save();
  echo "Added field_tiles (Featured item) to paragraph.$type\n";
}

// The Landing hero's settings, so both pick from the same vocabularies.
$hero_category = FieldConfig::loadByName('paragraph', 'landing_hero', 'field_landing_blog_category');
if (!FieldStorageConfig::loadByName('paragraph', 'field_landing_blog_category') || !$hero_category) {
  echo "Skipped field_landing_blog_category: the Landing hero doesn't have it (palefire_landing_hero_tiles_setup.php). Add it by hand.\n";
}
elseif (!FieldConfig::loadByName('paragraph', $type, 'field_landing_blog_category')) {
  FieldConfig::create([
    'field_name' => 'field_landing_blog_category',
    'entity_type' => 'paragraph',
    'bundle' => $type,
    'label' => 'Blog categories',
    'description' => 'The newest three articles in any of these Article Categories are listed.',
    'required' => FALSE,
    'settings' => $hero_category->getSettings(),
  ])->save();
  echo "Added field_landing_blog_category to paragraph.$type\n";
}

// 4. LibCal settings (new storages). -----------------------------------------
$libcal = [
  'field_stacks_calendar' => ['LibCal calendar ID', '8030', 'The LibCal calendar the events list reads ("Events" is 8030). The same number as the events carousel\'s Calendars setting.'],
  'field_stacks_campus' => ['LibCal campus ID', '262', 'Only events at this campus are listed (Hill Memorial Library is 262).'],
];
foreach ($libcal as $name => [$label, $default, $description]) {
  if (!FieldStorageConfig::loadByName('paragraph', $name)) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'type' => 'string',
      'settings' => ['max_length' => 32],
    ])->save();
    echo "Created storage paragraph.$name\n";
  }
  if (!FieldConfig::loadByName('paragraph', $type, $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => $type,
      'label' => $label,
      'description' => $description,
      'required' => TRUE,
      'default_value' => [['value' => $default]],
    ])->save();
    echo "Added $name to paragraph.$type\n";
  }
}

// 5. Form and view displays. -------------------------------------------------
$weight = (int) max(array_column($form->getComponents(), 'weight') ?: [0]);
if (!$form->getComponent('field_tiles')) {
  $form->setComponent('field_tiles', [
    'type' => 'entity_reference_autocomplete',
    'weight' => ++$weight,
    'settings' => ['match_operator' => 'CONTAINS', 'match_limit' => 10, 'size' => 60, 'placeholder' => 'Start typing a Tile or page title'],
  ]);
}
if (FieldConfig::loadByName('paragraph', $type, 'field_landing_blog_category') && !$form->getComponent('field_landing_blog_category')) {
  // Checkboxes: every category in view, any number ticked.
  $form->setComponent('field_landing_blog_category', ['type' => 'options_buttons', 'weight' => ++$weight]);
}
foreach (array_keys($libcal) as $name) {
  if (!$form->getComponent($name)) {
    $form->setComponent($name, ['type' => 'string_textfield', 'weight' => ++$weight, 'settings' => ['size' => 12]]);
  }
}
$form->save();

// The template prints all of it itself.
foreach (['field_tiles', 'field_landing_blog_category', 'field_stacks_calendar', 'field_stacks_campus'] as $name) {
  $view->removeComponent($name);
}
$view->save();

// 6. Allow landing_stacks wherever bento_search is allowed. ------------------
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
  echo "Allowed landing_stacks in " . $config->id() . "\n";
}

echo "From the stacks setup complete. Run: drush cr\n";
