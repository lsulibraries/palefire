<?php

/**
 * @file
 * Creates the Building map content model (Option B: the map lives inside the
 * bento section, under the search form). Idempotent: safe to re-run.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_building_map_setup.php
 *   drush cr
 *
 * Paragraph types
 *   bento_search  (existing)
 *     field_bento_map    ERR -> building_map  optional map under the search form
 *   building_map  "Building map"      (only inside bento_search)
 *     field_map_heading  string           h3 above the map (shows in the TOC)
 *     field_map_intro    text_long        intro beside the map
 *     field_map_spaces   ERR -> map_space one per room
 *   map_space     "Map space"         (only inside building_map)
 *     field_map_region   list_string      which SVG region it switches on
 *     field_space_access list_string      walk_in | appointment
 *     field_space_title  string           tooltip heading + accessible name
 *     field_space_body   text_long        tooltip text (a sentence or two)
 *     field_space_link   link             optional: the room links here
 *
 * Upgrading from the earlier "Section: Building map" version: the script
 * relabels the existing types/fields and adds McIlhenny Room. It does not
 * delete anything; see README for the old fields you can remove by hand.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\paragraphs\Entity\ParagraphsType;

$installer = \Drupal::service('module_installer');
foreach (['paragraphs', 'entity_reference_revisions', 'options', 'text', 'link'] as $module) {
  if (!\Drupal::moduleHandler()->moduleExists($module)) {
    $installer->install([$module]);
    echo "Enabled $module\n";
  }
}

$repo = \Drupal::service('entity_display.repository');

$type = function (string $id, string $label, string $description) {
  $existing = ParagraphsType::load($id);
  if (!$existing) {
    ParagraphsType::create(['id' => $id, 'label' => $label, 'description' => $description])->save();
    echo "Created paragraph type $id\n";
  }
  elseif ($existing->label() !== $label || $existing->get('description') !== $description) {
    $existing->set('label', $label)->set('description', $description)->save();
    echo "Updated paragraph type $id\n";
  }
};

$storage = function (string $name, string $field_type, array $settings = [], int $cardinality = 1) {
  if (!FieldStorageConfig::loadByName('paragraph', $name)) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'type' => $field_type,
      'settings' => $settings,
      'cardinality' => $cardinality,
    ])->save();
    echo "Created storage $name\n";
  }
};

// Creates the field, or brings label/description up to date on re-runs.
$field = function (string $bundle, string $name, string $label, bool $required = FALSE, array $settings = [], string $description = '') {
  $config = FieldConfig::loadByName('paragraph', $bundle, $name);
  if (!$config) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => $bundle,
      'label' => $label,
      'required' => $required,
      'settings' => $settings,
      'description' => $description,
    ])->save();
    echo "Added $name to $bundle\n";
  }
  elseif ($config->getLabel() !== $label || $config->getDescription() !== $description) {
    $config->setLabel($label)->setDescription($description)->save();
    echo "Updated $name on $bundle\n";
  }
};

$err_settings = fn (string $target) => [
  'handler' => 'default:paragraph',
  'handler_settings' => [
    'target_bundles' => [$target => $target],
    'negate' => 0,
    'target_bundles_drag_drop' => [$target => ['enabled' => TRUE, 'weight' => 0]],
  ],
];

if (!ParagraphsType::load('bento_search')) {
  echo "Paragraph type bento_search not found (palefire_modular). Install it first, then re-run.\n";
  return;
}

// Paragraph types -------------------------------------------------------
$type('building_map', 'Building map', 'Hill Memorial floor map shown in the bento under the search form. Each Map space switches one room on and supplies its tooltip.');
$type('map_space', 'Map space', 'One room on the building map and the text of its tooltip.');

// map_space ------------------------------------------------------------------
// Order matters for editors only; the list on the page is always Floor 1 first.
$regions = [
  'reading_room' => 'Reading Room (Floor 1)',
  'gallery_1' => 'Gallery (Floor 1)',
  'lecture_hall' => 'Lecture Hall (Floor 1)',
  'gallery_2' => 'Gallery (Floor 2)',
  'quiet_study' => 'Quiet Study (Floor 2)',
  'seminar_space' => 'Seminar Space (Floor 2)',
  'mcilhenny_room' => 'McIlhenny Room (Floor 2)',
  'oral_history' => 'Oral History (Floor 2)',
];
$storage('field_map_region', 'list_string', ['allowed_values' => $regions]);
$region_storage = FieldStorageConfig::loadByName('paragraph', 'field_map_region');
if ($region_storage->getSetting('allowed_values') != $regions) {
  $region_storage->setSetting('allowed_values', $regions)->save();
  echo "Updated field_map_region rooms\n";
}
$storage('field_space_access', 'list_string', ['allowed_values' => [
  'walk_in' => 'Public walk-in',
  'appointment' => 'Request an appointment',
]]);
$storage('field_space_title', 'string', ['max_length' => 120]);
$storage('field_space_body', 'text_long');
$storage('field_space_link', 'link');

$field('map_space', 'field_map_region', 'Map region', TRUE, [], 'The room on the map this entry controls. Use each region once per map.');
$field('map_space', 'field_space_access', 'Access', TRUE, [], 'Walk-in spaces get the full hatch; appointment spaces a lighter one.');
$field('map_space', 'field_space_title', 'Title', TRUE, [], 'Room name in the list and at the top of the tooltip.');
$field('map_space', 'field_space_body', 'Tooltip text', FALSE, [], 'One or two sentences shown when someone hovers or focuses the room. No links here: the room itself links to the field below.');
$field('map_space', 'field_space_link', 'Link', FALSE, [
  // 17 = LinkItemInterface::LINK_GENERIC, 1 = DRUPAL_OPTIONAL link text.
  'link_type' => 17,
  'title' => 1,
], 'Optional. Clicking the room or its list entry goes here (e.g. the appointment request form). The link text becomes the hint at the bottom of the tooltip; leave it empty for "Request an appointment" / "Plan your visit".');

$form = $repo->getFormDisplay('paragraph', 'map_space', 'default')
  ->setComponent('field_map_region', ['type' => 'options_select', 'weight' => 0])
  ->setComponent('field_space_access', ['type' => 'options_buttons', 'weight' => 1])
  ->setComponent('field_space_title', ['type' => 'string_textfield', 'weight' => 2])
  ->setComponent('field_space_body', ['type' => 'text_textarea', 'weight' => 3, 'settings' => ['rows' => 3]])
  ->setComponent('field_space_link', ['type' => 'link_default', 'weight' => 4]);
// Earlier version had a dialog image; tooltips don't use it.
if (FieldConfig::loadByName('paragraph', 'map_space', 'field_space_image')) {
  $form->removeComponent('field_space_image');
}
$form->save();

// Region, access, title and link are printed by the template directly.
$view = $repo->getViewDisplay('paragraph', 'map_space', 'default')
  ->setComponent('field_space_body', ['type' => 'text_default', 'label' => 'hidden', 'weight' => 0])
  ->removeComponent('field_map_region')
  ->removeComponent('field_space_access')
  ->removeComponent('field_space_title')
  ->removeComponent('field_space_link');
if (FieldConfig::loadByName('paragraph', 'map_space', 'field_space_image')) {
  $view->removeComponent('field_space_image');
}
$view->save();

// building_map ---------------------------------------------------------------
$storage('field_map_heading', 'string', ['max_length' => 120]);
$storage('field_map_intro', 'text_long');
$storage('field_map_spaces', 'entity_reference_revisions', ['target_type' => 'paragraph'], -1);

$field('building_map', 'field_map_heading', 'Heading', FALSE, [], 'Shown as an h3 above the map and listed in the page TOC. Leave empty for "Visit Hill Memorial Library".');
$field('building_map', 'field_map_intro', 'Intro', FALSE, [], 'A sentence or two beside the map (hours, address, how to request access).');
$field('building_map', 'field_map_spaces', 'Spaces', FALSE, $err_settings('map_space'), 'One entry per room. Rooms without an entry show as plain floor.');

$repo->getFormDisplay('paragraph', 'building_map', 'default')
  ->setComponent('field_map_heading', ['type' => 'string_textfield', 'weight' => 0])
  ->setComponent('field_map_intro', ['type' => 'text_textarea', 'weight' => 1, 'settings' => ['rows' => 3]])
  ->setComponent('field_map_spaces', [
    'type' => 'paragraphs',
    'weight' => 2,
    'settings' => [
      'title' => 'Space',
      'title_plural' => 'Spaces',
      'edit_mode' => 'closed',
      'add_mode' => 'button',
      'form_display_mode' => 'default',
      'default_paragraph_type' => 'map_space',
    ],
  ])
  ->save();

// Heading is printed by the template; spaces render as hidden tooltip sources.
$repo->getViewDisplay('paragraph', 'building_map', 'default')
  ->removeComponent('field_map_heading')
  ->setComponent('field_map_intro', ['type' => 'text_default', 'label' => 'hidden', 'weight' => 0])
  ->setComponent('field_map_spaces', [
    'type' => 'entity_reference_revisions_entity_view',
    'label' => 'hidden',
    'weight' => 1,
    'settings' => ['view_mode' => 'default'],
  ])
  ->save();

// bento_search: the map slot -------------------------------------------------
$storage('field_bento_map', 'entity_reference_revisions', ['target_type' => 'paragraph'], 1);
$field('bento_search', 'field_bento_map', 'Building map', FALSE, $err_settings('building_map'), 'Optional. Shows the Hill Memorial map under the search form.');

$repo->getFormDisplay('paragraph', 'bento_search', 'default')
  ->setComponent('field_bento_map', [
    'type' => 'paragraphs',
    'weight' => 50,
    'settings' => [
      'title' => 'Building map',
      'title_plural' => 'Building maps',
      'edit_mode' => 'closed',
      'add_mode' => 'button',
      'form_display_mode' => 'default',
      'default_paragraph_type' => 'building_map',
    ],
  ])
  ->save();
$repo->getViewDisplay('paragraph', 'bento_search', 'default')
  ->setComponent('field_bento_map', [
    'type' => 'entity_reference_revisions_entity_view',
    'label' => 'hidden',
    'weight' => 50,
    'settings' => ['view_mode' => 'default'],
  ])
  ->save();

// Leftovers from the earlier stand-alone section version --------------------
$old = [];
foreach (FieldConfig::loadMultiple() as $config) {
  if ($config->getTargetEntityTypeId() === 'paragraph' && $config->getTargetBundle() === 'building_map'
    && !in_array($config->getName(), ['field_map_heading', 'field_map_intro', 'field_map_spaces'], TRUE)) {
    $old[] = $config->getName();
  }
}
if (FieldConfig::loadByName('paragraph', 'map_space', 'field_space_image')) {
  $old[] = 'map_space.field_space_image';
}
if ($old) {
  echo "Unused fields from the earlier version (safe to delete in the UI): " . implode(', ', $old) . "\n";
}
$host = FieldConfig::loadByName('node', 'modular_page', 'field_scroll_section');
$bundles = $host ? ($host->getSetting('handler_settings')['target_bundles'] ?? []) : [];
if (isset($bundles['building_map'])) {
  echo "building_map is still allowed in node.modular_page.field_scroll_section; remove it there so it is only added via the bento.\n";
}

echo "Done. Run drush cr.\n";
