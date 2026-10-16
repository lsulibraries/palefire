<?php

/**
 * @file
 * Visit card and "light-map" theme setup for Modular pages (Option B).
 *
 * For sites whose Modular page sections come from palefire_modular
 * (bento_search, field_section_theme, ...). It only adds config: it does
 * not touch paragraph types or field_sections.
 *
 * Run from the Drupal root on production:
 *   drush php:script themes/custom/palefire/scripts/palefire_visit_setup.php
 *   drush cr
 *
 * Adds:
 * - Optional fields on node.modular_page: field_intro, field_hours_location
 *   (LibCal), field_location_name, field_location_query,
 *   field_location_display, with form widgets. They stay hidden in the view
 *   displays (the bento template prints them).
 * - A "light-map" value ("Light, dark at the map") on palefire_modular's
 *   paragraph.field_section_theme.
 *
 * Safe to re-run: existing fields are left as they are.
 *
 * List fields take allowed_values as [key => label], the runtime format
 * FieldStorageConfig::create() expects. The [['value' => .., 'label' => ..]]
 * form is only for config YAML; passed here it fails with
 * "settings.allowed_values.0.label.0 doesn't exist".
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;

if (PHP_SAPI !== 'cli') {
  return;
}

$bundle = 'modular_page';
if (!NodeType::load($bundle)) {
  throw new \RuntimeException("Content type '$bundle' not found.");
}
foreach (['text', 'options'] as $module) {
  if (!\Drupal::moduleHandler()->moduleExists($module)) {
    \Drupal::service('module_installer')->install([$module]);
  }
}

/** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $displays */
$displays = \Drupal::service('entity_display.repository');

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

// 1. Visit fields on Modular page (all optional). -----------------------------
// Shown beside the page title as the "Visit" card (Option B): intro text,
// today's hours from LibCal, and a Google Maps link / click-to-load map.
// Each part only prints when its field is filled in.
//
// LibCal location IDs (the "lid" in LibCal's hours widget). Edit the labels
// here, re-run the script, or change them later in Field UI.
// Keys are the IDs, values the labels editors see.
$libcal_locations = [
  '241' => 'LibCal location 241',
  '236' => 'LibCal location 236',
];
$storage('node', 'field_intro', 'text_long');
$storage('node', 'field_hours_location', 'list_string', ['allowed_values' => $libcal_locations]);
$storage('node', 'field_location_name', 'string', ['max_length' => 255]);
$storage('node', 'field_location_query', 'string', ['max_length' => 255]);
$storage('node', 'field_location_display', 'list_string', [
  'allowed_values' => [
    'link' => 'Link to Google Maps only',
    'embed' => 'Link + mini map (loads on click)',
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

// 2. "Light, dark at the map" section theme. ---------------------------------
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

echo 'Visit setup complete. Run: drush cr' . PHP_EOL;
