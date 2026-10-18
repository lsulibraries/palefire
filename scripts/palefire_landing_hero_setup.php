<?php

/**
 * @file
 * Creates the "Landing hero" paragraph type (landing_hero).
 *
 * Landing hero is a bento search without the search form: a background
 * video, an optional lead panel (the page <h1> when it is the first
 * section, and the page intro), the building map and the Visit strip.
 * Template: paragraph--landing-hero.html.twig.
 *
 * Run from the Drupal root on production:
 *   drush php:script themes/custom/palefire/scripts/palefire_landing_hero_setup.php
 *   drush cr
 *
 * What it does:
 * - Creates the paragraph type landing_hero.
 * - Adds bento_search's existing fields to it. The field storages are
 *   shared, so no new database tables are needed. Each field copies its
 *   settings from bento_search, and so do its form widget and display:
 *     field_bg_videos        Background videos
 *     field_bg_poster        Background poster
 *     field_bento_map        Building map (building_map paragraph)
 *     field_bento_hide_lead  "Hide lead panel" (only used with a map)
 *   A field that bento_search doesn't have is skipped and reported.
 * - Allows landing_hero in every paragraph reference field that already
 *   allows bento_search, such as the Modular page's sections.
 *
 * Safe to re-run: anything that already exists is left as it is.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\paragraphs\Entity\ParagraphsType;

if (PHP_SAPI !== 'cli') {
  return;
}

$hero = 'landing_hero';
$bento = 'bento_search';

if (!ParagraphsType::load($bento)) {
  echo "Paragraph type bento_search not found (palefire_modular). Install it first, then re-run.\n";
  return;
}

if (!ParagraphsType::load($hero)) {
  ParagraphsType::create([
    'id' => $hero,
    'label' => 'Landing hero',
    'description' => 'Background video with the page title and intro, an optional building map and the Visit card. Like Bento search, without the search box.',
  ])->save();
  echo "Created paragraph type $hero\n";
}

// Labels and help that differ from bento_search's.
$overrides = [
  'field_bento_hide_lead' => [
    'label' => 'Hide lead panel',
    'description' => 'Leave out the title and intro panel so the building map opens the hero. Only used when a building map is added; the page title stays for screen readers.',
  ],
];

$repo = \Drupal::service('entity_display.repository');
$bento_form = $repo->getFormDisplay('paragraph', $bento, 'default');
$bento_view = $repo->getViewDisplay('paragraph', $bento, 'default');
$hero_form = $repo->getFormDisplay('paragraph', $hero, 'default');
$hero_view = $repo->getViewDisplay('paragraph', $hero, 'default');

foreach (['field_bg_videos', 'field_bg_poster', 'field_bento_map', 'field_bento_hide_lead'] as $name) {
  $source = FieldConfig::loadByName('paragraph', $bento, $name);
  if (!$source) {
    echo "Skipped $name: bento_search doesn't have it.\n";
    continue;
  }
  if (!FieldConfig::loadByName('paragraph', $hero, $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => $hero,
      'label' => $overrides[$name]['label'] ?? $source->getLabel(),
      'description' => $overrides[$name]['description'] ?? $source->getDescription(),
      'required' => $source->isRequired(),
      'settings' => $source->getSettings(),
      'default_value' => $source->getDefaultValueLiteral(),
    ])->save();
    echo "Added $name to paragraph.$hero\n";
  }
  if (($component = $bento_form->getComponent($name)) && !$hero_form->getComponent($name)) {
    $hero_form->setComponent($name, $component);
  }
  // The template prints these itself; copy whatever bento_search does
  // (shown or hidden) so the two render the same.
  if (($component = $bento_view->getComponent($name)) && !$hero_view->getComponent($name)) {
    $hero_view->setComponent($name, $component);
  }
}
$hero_form->save();
$hero_view->save();

// Allow the hero wherever bento_search is allowed.
foreach (FieldConfig::loadMultiple() as $config) {
  if (!in_array($config->getType(), ['entity_reference_revisions', 'entity_reference'], TRUE)
    || $config->getSetting('target_type') !== 'paragraph') {
    continue;
  }
  $handler = $config->getSetting('handler_settings') ?: [];
  $bundles = $handler['target_bundles'] ?? NULL;
  // Paragraphs' "exclude" mode lists bundles that are NOT allowed; any
  // other bundle, including the new one, is already allowed.
  if (($handler['negate'] ?? FALSE) || !is_array($bundles) || !isset($bundles[$bento]) || isset($bundles[$hero])) {
    continue;
  }
  $handler['target_bundles'][$hero] = $hero;
  if (isset($handler['target_bundles_drag_drop'])) {
    $weight = $handler['target_bundles_drag_drop'][$bento]['weight'] ?? 0;
    $handler['target_bundles_drag_drop'][$hero] = ['enabled' => TRUE, 'weight' => $weight + 1];
  }
  $config->setSetting('handler_settings', $handler)->save();
  echo "Allowed landing_hero in " . $config->id() . "\n";
}

echo "Done. Run drush cr.\n";
