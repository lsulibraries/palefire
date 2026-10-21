<?php

/**
 * @file
 * Louisiana Newspapers guide: the content model for its parish map and
 * family trees. Run palefire_guide_setup.php first.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_louisiana_newspapers_setup.php
 *   drush cr
 *
 * Config only: it never creates or changes content. The guide page itself
 * comes from palefire_guide_louisiana_newspapers.php.
 *
 * Adds paragraph types, all offered in a guide section's Content:
 * - parish_map ("Guide: Parish map"): a clickable map of Louisiana's 64
 *   parishes beside a panel (paragraph--parish-map.html.twig). Fields:
 *   field_block_title, field_block_intro (shared with the other guide
 *   blocks) and field_parish_entries, one Parish item per parish.
 * - parish_entry ("Parish"): field_parish (which parish) and three
 *   formatted text fields for the WYSIWYG editor: field_parish_digital
 *   (Digital newspapers & magazines), field_parish_websites (Newspaper &
 *   magazine websites) and field_parish_microfilm (Newspapers on
 *   microfilm). An empty field leaves its group out of the panel.
 * - family_trees ("Guide: Family trees"): field_block_title and
 *   field_block_intro. The trees themselves are read live from the records
 *   behind /louisiananewspapers (palefire.theme, _palefire_newspaper_trees()),
 *   so there is nothing to fill in; the parish map lists each parish's trees
 *   the same way.
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

// The parish keys and names: PALEFIRE_LA_PARISHES in palefire.theme, which
// the theme only loads with the theme, so read them from the file.
$theme_file = dirname(__DIR__) . '/palefire.theme';
if (!preg_match('/const PALEFIRE_LA_PARISHES = \[(.*?)\];/s', (string) file_get_contents($theme_file), $m)
  || !preg_match_all("/'([a-z_]+)' => '([^']+)'/", $m[1], $pairs)
  || count($pairs[1]) !== 64) {
  throw new \RuntimeException("Couldn't read the 64 parishes (PALEFIRE_LA_PARISHES) from $theme_file.");
}
$parishes = array_combine($pairs[1], $pairs[2]);

// Helpers (as in palefire_guide_setup.php). --------------------------------

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

$paragraph_type = function (string $id, string $label, string $description): void {
  if (!ParagraphsType::load($id)) {
    ParagraphsType::create(['id' => $id, 'label' => $label, 'description' => $description])->save();
    echo "Created paragraph type $id" . PHP_EOL;
  }
};

// Form widgets that aren't set yet, so later edits in Field UI stand.
$form = function (string $bundle, array $components): void {
  $display = \Drupal::service('entity_display.repository')->getFormDisplay('paragraph', $bundle, 'default');
  foreach ($components as $name => $component) {
    if (!$display->getComponent($name)) {
      $display->setComponent($name, $component);
    }
  }
  $display->save();
};

// A new view display shows only the listed fields; an existing one is left
// to Manage display.
$view = function (string $bundle, array $components): void {
  $display = \Drupal::service('entity_display.repository')->getViewDisplay('paragraph', $bundle, 'default');
  if ($display->isNew()) {
    foreach (array_keys($display->getComponents()) as $name) {
      if (!isset($components[$name])) {
        $display->removeComponent($name);
      }
    }
    foreach ($components as $name => $component) {
      $display->setComponent($name, $component);
    }
    $display->setStatus(TRUE);
    $display->save();
    echo "Set up paragraph.$bundle.default display" . PHP_EOL;
  }
};

$intro_view = ['field_block_intro' => ['type' => 'text_default', 'label' => 'hidden', 'weight' => 0]];
$title_form = ['type' => 'string_textfield', 'weight' => 0];
$intro_form = ['type' => 'text_textarea', 'weight' => 1, 'settings' => ['rows' => 4]];

// Types. -------------------------------------------------------------------

$paragraph_type('parish_map', 'Guide: Parish map', 'A clickable map of Louisiana\'s parishes. Each Parish item holds that parish\'s lists; its family trees are added automatically.');
$paragraph_type('parish_entry', 'Parish', 'One parish on a Parish map: its digital newspapers, newspaper websites and microfilm.');
$paragraph_type('family_trees', 'Guide: Family trees', 'Every newspaper family tree on the site, with a search box and a parish filter.');

// Storages. ----------------------------------------------------------------

$storage('field_parish', 'list_string', ['allowed_values' => $parishes]);
foreach (['digital', 'websites', 'microfilm'] as $group) {
  $storage('field_parish_' . $group, 'text_long');
}
$storage('field_parish_entries', 'entity_reference_revisions', ['target_type' => 'paragraph'], -1);

// parish_entry. ------------------------------------------------------------

$field('parish_entry', 'field_parish', ['label' => 'Parish', 'required' => TRUE]);
$field('parish_entry', 'field_parish_digital', [
  'label' => 'Digital newspapers & magazines',
  'description' => 'A bulleted list: each newspaper as a link, then a line break (Shift+Enter) and where its issues are online.',
]);
$field('parish_entry', 'field_parish_websites', [
  'label' => 'Newspaper & magazine websites',
  'description' => 'A bulleted list of links. End an official parish journal\'s name with an asterisk (*); the panel then explains it.',
]);
$field('parish_entry', 'field_parish_microfilm', [
  'label' => 'Newspapers on microfilm',
  'description' => 'Optional. The city in bold, then a bulleted list of titles. Use bold, not a heading.',
]);
$form('parish_entry', [
  'field_parish' => ['type' => 'options_select', 'weight' => 0],
  'field_parish_digital' => ['type' => 'text_textarea', 'weight' => 1, 'settings' => ['rows' => 8]],
  'field_parish_websites' => ['type' => 'text_textarea', 'weight' => 2, 'settings' => ['rows' => 5]],
  'field_parish_microfilm' => ['type' => 'text_textarea', 'weight' => 3, 'settings' => ['rows' => 4]],
]);
// The map's template prints these itself.
$view('parish_entry', []);

// parish_map. --------------------------------------------------------------

$field('parish_map', 'field_block_title', ['label' => 'Heading', 'description' => 'Optional. An h3 above the map, listed in "On this page".']);
$field('parish_map', 'field_block_intro', ['label' => 'Intro (optional)']);
$field('parish_map', 'field_parish_entries', [
  'label' => 'Parishes',
  'description' => 'One item per parish. A parish without an item still shows its family trees.',
  'settings' => [
    'handler' => 'default:paragraph',
    'handler_settings' => [
      'target_bundles' => ['parish_entry' => 'parish_entry'],
      'negate' => 0,
      'target_bundles_drag_drop' => ['parish_entry' => ['enabled' => TRUE, 'weight' => 0]],
    ],
  ],
]);
$form('parish_map', [
  'field_block_title' => $title_form,
  'field_block_intro' => $intro_form,
  'field_parish_entries' => [
    'type' => 'paragraphs',
    'weight' => 2,
    'settings' => [
      'title' => 'Parish',
      'title_plural' => 'Parishes',
      'edit_mode' => 'closed',
      'closed_mode' => 'summary',
      'autocollapse' => 'all',
      'add_mode' => 'button',
      'form_display_mode' => 'default',
      'default_paragraph_type' => 'parish_entry',
    ],
  ],
]);
$view('parish_map', $intro_view);

// family_trees. ------------------------------------------------------------

$field('family_trees', 'field_block_title', ['label' => 'Heading', 'description' => 'Optional. An h3, listed in "On this page".']);
$field('family_trees', 'field_block_intro', ['label' => 'Intro (optional)']);
$form('family_trees', ['field_block_title' => $title_form, 'field_block_intro' => $intro_form]);
$view('family_trees', $intro_view);

// Offer both in a guide section's Content. ---------------------------------

$blocks = FieldConfig::loadByName('paragraph', 'guide_section', 'field_guide_blocks');
$settings = $blocks->getSetting('handler_settings');
$changed = FALSE;
// An empty list (or a negated one) already allows every type.
foreach (empty($settings['target_bundles']) || !empty($settings['negate']) ? [] : ['parish_map', 'family_trees'] as $type) {
  if (empty($settings['target_bundles'][$type])) {
    $settings['target_bundles'][$type] = $type;
    $settings['target_bundles_drag_drop'][$type] = ['enabled' => TRUE, 'weight' => count($settings['target_bundles_drag_drop'] ?? [])];
    $changed = TRUE;
  }
}
if ($changed) {
  $blocks->setSetting('handler_settings', $settings)->save();
  echo 'Added parish_map and family_trees to guide_section.field_guide_blocks' . PHP_EOL;
}

echo 'Done. Run: drush cr' . PHP_EOL;
