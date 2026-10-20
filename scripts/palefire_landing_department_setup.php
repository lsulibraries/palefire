<?php

/**
 * @file
 * Creates the "Landing department" paragraph type (landing_department).
 *
 * Landing department is a copy of Bento search (bento_search): background
 * video, the lead panel (the page <h1> when it is the first section, the
 * page intro and "Search LSU Libraries"), the building map, the Visit strip
 * and the sub-header bar, plus the Landing hero's tile accordion under the
 * search. Template: paragraph--landing-department.html.twig.
 *
 * Run from the Drupal root on production, after
 * palefire_landing_hero_tiles_setup.php (which creates the Hero tile type
 * and the Tiles field):
 *   drush php:script themes/custom/palefire/scripts/palefire_landing_department_setup.php
 *   drush cr
 *
 * What it does:
 * - Creates the paragraph type landing_department.
 * - Adds every field bento_search has (section title, anchor, snap, height,
 *   theme, background videos and poster, map, "Hide lead panel" and the
 *   rest). The field storages are shared, so no new database tables are
 *   needed. Each field copies its settings, form widget and display from
 *   bento_search, and so do the form's field groups, if any.
 * - Adds field_hero_tiles (Tiles: up to three Hero tiles), with the
 *   Landing hero's settings and widget. Empty, no tiles show.
 * - Allows landing_department in every paragraph reference field that
 *   already allows bento_search, such as the Modular page's sections.
 *
 * Safe to re-run: anything that already exists is left as it is.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldConfigInterface;
use Drupal\paragraphs\Entity\ParagraphsType;

if (PHP_SAPI !== 'cli') {
  return;
}

$department = 'landing_department';
$bento = 'bento_search';
$hero = 'landing_hero';

if (!ParagraphsType::load($bento)) {
  echo "Paragraph type bento_search not found (palefire_modular). Install it first, then re-run.\n";
  return;
}
$tiles_source = FieldConfig::loadByName('paragraph', $hero, 'field_hero_tiles');
if (!$tiles_source) {
  echo "landing_hero has no field_hero_tiles. Run palefire_landing_hero_tiles_setup.php first, then re-run.\n";
  return;
}

if (!ParagraphsType::load($department)) {
  ParagraphsType::create([
    'id' => $department,
    'label' => 'Landing department',
    'description' => 'Like Bento search (background video, page title and intro, Search LSU Libraries, building map, Visit card), with up to three tiles under the search.',
  ])->save();
  echo "Created paragraph type $department\n";
}

$repo = \Drupal::service('entity_display.repository');
$fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('paragraph', $bento);

/**
 * Adds a copy of $source to the department, if it isn't there yet.
 */
$copy = function (FieldConfigInterface $source, array $overrides = []) use ($department) {
  $name = $source->getName();
  if (FieldConfig::loadByName('paragraph', $department, $name)) {
    return;
  }
  FieldConfig::create([
    'field_name' => $name,
    'entity_type' => 'paragraph',
    'bundle' => $department,
    'label' => $overrides['label'] ?? $source->getLabel(),
    'description' => $overrides['description'] ?? $source->getDescription(),
    'required' => $source->isRequired(),
    'settings' => $source->getSettings(),
    'default_value' => $source->getDefaultValueLiteral(),
  ])->save();
  echo "Added $name to paragraph.$department\n";
};

foreach ($fields as $definition) {
  if ($definition instanceof FieldConfigInterface) {
    $copy($definition);
  }
}
$copy($tiles_source, [
  'description' => 'Up to three tiles under the search. Empty, no tiles show.',
]);

// Widgets and displays: bento_search's for its fields, the hero's for the
// tiles. Components the department already has are left as they are.
$sources = [
  $bento => array_keys($fields),
  $hero => ['field_hero_tiles'],
];
foreach (['getFormDisplay', 'getViewDisplay'] as $method) {
  $target = $repo->$method('paragraph', $department, 'default');
  foreach ($sources as $bundle => $names) {
    $display = $repo->$method('paragraph', $bundle, 'default');
    foreach ($display->getComponents() as $name => $component) {
      if (in_array($name, $names, TRUE) && !$target->getComponent($name)) {
        $target->setComponent($name, $component);
      }
    }
  }
  // The template prints the tiles itself.
  if ($method === 'getViewDisplay') {
    $target->removeComponent('field_hero_tiles');
  }
  // Field groups (field_group module), if bento_search's form has any.
  if ($method === 'getFormDisplay' && !$target->getThirdPartySettings('field_group')) {
    foreach ($repo->getFormDisplay('paragraph', $bento, 'default')->getThirdPartySettings('field_group') as $key => $group) {
      $target->setThirdPartySetting('field_group', $key, $group);
    }
  }
  $target->save();
}

// Allow the department wherever bento_search is allowed.
foreach (FieldConfig::loadMultiple() as $config) {
  if (!in_array($config->getType(), ['entity_reference_revisions', 'entity_reference'], TRUE)
    || $config->getSetting('target_type') !== 'paragraph') {
    continue;
  }
  $handler = $config->getSetting('handler_settings') ?: [];
  $bundles = $handler['target_bundles'] ?? NULL;
  // Paragraphs' "exclude" mode lists bundles that are NOT allowed; any
  // other bundle, including the new one, is already allowed.
  if (($handler['negate'] ?? FALSE) || !is_array($bundles) || !isset($bundles[$bento]) || isset($bundles[$department])) {
    continue;
  }
  $handler['target_bundles'][$department] = $department;
  if (isset($handler['target_bundles_drag_drop'])) {
    $weight = $handler['target_bundles_drag_drop'][$bento]['weight'] ?? 0;
    $handler['target_bundles_drag_drop'][$department] = ['enabled' => TRUE, 'weight' => $weight + 1];
  }
  $config->setSetting('handler_settings', $handler)->save();
  echo "Allowed $department in " . $config->id() . "\n";
}

echo "Done. Run drush cr.\n";
