<?php

/**
 * @file
 * Creates the building map content model and syncs rooms from
 * maps/buildings.yml. Idempotent: safe to re-run after editing that file.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_building_map_setup.php
 *   drush php:script themes/custom/palefire/scripts/palefire_building_map_setup.php -- cleanup
 *   drush cr
 *
 * Taxonomy: Building spaces (building_space), hierarchical
 *   Building term (field_map_key = building key, e.g. hill_memorial)
 *     description         intro beside the map (default for every page)
 *   └ Room term (field_map_key = building.space, e.g. hill_memorial.reading_room)
 *     description         tooltip text
 *     field_space_access  walk_in | appointment
 *     field_space_link    optional: the room links here
 *     field_space_note    optional: a short note after the access line in
 *                         the tooltip, e.g. "Now showing"
 *     field_space_media   optional: a photo of the room, an Image media
 *                         item (shown in image style pf_map_room, 960x640
 *                         WebP). Replaces the first version's plain image
 *                         field_space_image, deleted once no room uses it.
 *     Published           unpublish to draw the room as plain floor
 *   Terms are created from buildings.yml; existing terms are never overwritten.
 *
 * Paragraph types
 *   bento_search  (existing)  field_bento_map -> building_map
 *   building_map  "Building map"
 *     field_map_building   list_string   which building (buildings.yml)
 *     field_map_heading    string        optional heading override
 *     field_map_intro      text_long     optional intro override
 *     field_map_here       list_string   optional: building.space this page is
 *                                        about; marked "here" on the map
 *     field_map_overrides  ERR -> map_room_override (rarely used)
 *   map_room_override  "Room override"  (only inside building_map)
 *     field_room_key       list_string   building.space
 *     field_space_body / field_space_access / field_space_link: each optional,
 *     replaces the room term's value on this map only.
 *
 * Upgrading from the per-page "Map space" version: map_space and
 * building_map.field_map_spaces are no longer used. Run with "cleanup" to
 * delete them (and any Map space paragraphs); without it they are only listed.
 */

use Drupal\Component\Serialization\Yaml;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\image\Entity\ImageStyle;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

if (PHP_SAPI !== 'cli') {
  return;
}
$cleanup = in_array('cleanup', $extra ?? [], TRUE);

$installer = \Drupal::service('module_installer');
foreach (['taxonomy', 'paragraphs', 'entity_reference_revisions', 'options', 'text', 'link', 'image', 'media'] as $module) {
  if (!\Drupal::moduleHandler()->moduleExists($module)) {
    $installer->install([$module]);
    echo "Enabled $module\n";
  }
}

$file = dirname(__DIR__) . '/maps/buildings.yml';
$buildings = is_readable($file) ? (Yaml::decode(file_get_contents($file)) ?: []) : [];
if (!$buildings) {
  echo "No buildings in $file.\n";
  return;
}
if (!ParagraphsType::load('bento_search')) {
  echo "Paragraph type bento_search not found (palefire_modular). Install it first, then re-run.\n";
  return;
}

$repo = \Drupal::service('entity_display.repository');
$format = FilterFormat::load('basic_html') ? 'basic_html' : filter_fallback_format();

// Helpers ---------------------------------------------------------------------
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

$storage = function (string $entity_type, string $name, string $field_type, array $settings = [], int $cardinality = 1) {
  if (!FieldStorageConfig::loadByName($entity_type, $name)) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => $entity_type,
      'type' => $field_type,
      'settings' => $settings,
      'cardinality' => $cardinality,
    ])->save();
    echo "Created storage $entity_type.$name\n";
  }
};

// list_string options follow buildings.yml. Removing an option that content
// still uses is refused by Drupal; that's reported, not fatal.
$options = function (string $entity_type, string $name, array $allowed) {
  $config = FieldStorageConfig::loadByName($entity_type, $name);
  if ($config && $config->getSetting('allowed_values') != $allowed) {
    try {
      $config->setSetting('allowed_values', $allowed)->save();
      echo "Updated options for $entity_type.$name\n";
    }
    catch (\Exception $e) {
      echo "Could not update options for $entity_type.$name: " . $e->getMessage() . "\n";
    }
  }
};

// Creates the field, or brings label/description up to date.
$field = function (string $entity_type, string $bundle, string $name, string $label, array $values = []) {
  $config = FieldConfig::loadByName($entity_type, $bundle, $name);
  $description = $values['description'] ?? '';
  if (!$config) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'label' => $label,
    ] + $values)->save();
    echo "Added $name to $entity_type.$bundle\n";
  }
  elseif ($config->getLabel() !== $label || $config->getDescription() !== $description || (isset($values['required']) && $config->isRequired() !== $values['required'])) {
    $config->setLabel($label)->setDescription($description);
    if (isset($values['required'])) {
      $config->setRequired($values['required']);
    }
    $config->save();
    echo "Updated $name on $entity_type.$bundle\n";
  }
};

$err = fn (string $target) => [
  'handler' => 'default:paragraph',
  'handler_settings' => [
    'target_bundles' => [$target => $target],
    'negate' => 0,
    'target_bundles_drag_drop' => [$target => ['enabled' => TRUE, 'weight' => 0]],
  ],
];

$paragraphs_widget = fn (int $weight, string $title, string $plural, string $default) => [
  'type' => 'paragraphs',
  'weight' => $weight,
  'settings' => [
    'title' => $title,
    'title_plural' => $plural,
    'edit_mode' => 'closed',
    'closed_mode' => 'summary',
    'autocollapse' => 'all',
    'add_mode' => 'button',
    'form_display_mode' => 'default',
    'default_paragraph_type' => $default,
  ],
];

$access_values = ['walk_in' => 'Public walk-in', 'appointment' => 'Request an appointment'];
$link_settings = ['link_type' => 17, 'title' => 1];  // Generic link, optional link text.

// Building spaces vocabulary ---------------------------------------------------
if (!Vocabulary::load('building_space')) {
  Vocabulary::create([
    'vid' => 'building_space',
    'name' => 'Building spaces',
    'description' => 'Buildings and rooms shown on building maps. Rooms come from the theme (maps/buildings.yml); edit their text, access and links here.',
  ])->save();
  echo "Created vocabulary building_space\n";
}

$storage('taxonomy_term', 'field_map_key', 'string', ['max_length' => 128]);
$storage('taxonomy_term', 'field_space_access', 'list_string', ['allowed_values' => $access_values]);
$storage('taxonomy_term', 'field_space_link', 'link');
$field('taxonomy_term', 'building_space', 'field_map_key', 'Map key', [
  'required' => FALSE,
  'description' => 'Set by the setup script. Ties the term to the drawing.',
]);
$field('taxonomy_term', 'building_space', 'field_space_access', 'Access', [
  'description' => 'Rooms only. Walk-in rooms get the full hatch; appointment rooms a lighter one.',
]);
$field('taxonomy_term', 'building_space', 'field_space_link', 'Link', [
  'settings' => $link_settings,
  'description' => 'Rooms only, optional. Clicking the room or its list entry goes here (e.g. the appointment request form). The link text becomes the hint at the bottom of the tooltip; leave it empty for "Request an appointment" / "Plan your visit".',
]);

$storage('taxonomy_term', 'field_space_note', 'string', ['max_length' => 40]);
$field('taxonomy_term', 'building_space', 'field_space_note', 'Tooltip note', [
  'description' => 'Rooms only, optional. A few words after the access line at the top of the tooltip, e.g. "Now showing" for a gallery with an exhibition up. Clear it when it no longer applies.',
]);

// The room's photo: an Image media item from the library, as the rest of
// Modular (palefire_exhibit_setup.php), so a photo is uploaded once, with
// its alt text and credit, and can be reused.
$image_types = [];
foreach (\Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple() as $media_type) {
  if ($media_type->getSource()->getPluginId() === 'image') {
    $image_types[$media_type->id()] = $media_type->id();
  }
}
// Prefer the core "image" type when there are several.
if (isset($image_types['image'])) {
  $image_types = ['image' => 'image'];
}
$media_widget = \Drupal::moduleHandler()->moduleExists('media_library')
  ? ['type' => 'media_library_widget', 'weight' => 5, 'settings' => ['media_types' => array_values($image_types)]]
  : ['type' => 'entity_reference_autocomplete', 'weight' => 5, 'settings' => ['match_operator' => 'CONTAINS', 'size' => 60, 'placeholder' => '']];
if ($image_types) {
  $storage('taxonomy_term', 'field_space_media', 'entity_reference', ['target_type' => 'media']);
  $field('taxonomy_term', 'building_space', 'field_space_media', 'Photo', [
    'settings' => ['handler' => 'default:media', 'handler_settings' => ['target_bundles' => $image_types, 'auto_create' => FALSE]],
    'description' => 'Rooms only, optional. A wide, evenly lit photo of the room without people, at least 960 x 640 pixels. The tooltip shows a 2:1 strip from its middle, in black and white at first.',
  ]);
}
else {
  echo "No Image media type found (Structure > Media types, source \"Image\"): room photos skipped. Add one and re-run.\n";
}

// The first version's plain image field: removed while no room uses it.
if ($old_photo = FieldConfig::loadByName('taxonomy_term', 'building_space', 'field_space_image')) {
  $used = \Drupal::entityQuery('taxonomy_term')
    ->accessCheck(FALSE)
    ->condition('vid', 'building_space')
    ->exists('field_space_image')
    ->execute();
  if (!$used) {
    $old_photo->delete();
    echo "Deleted the unused image field field_space_image (now field_space_media)\n";
  }
  else {
    $names = array_map(fn (Term $term) => '"' . $term->label() . '"', Term::loadMultiple($used));
    echo 'field_space_image still holds photos for ' . implode(', ', $names) . ": add them to the Media library, pick them in Photo, then re-run to delete the old field.\n";
  }
}

// The tooltip's photo: 3:2 at twice the tooltip's 480px, as WebP.
if (!ImageStyle::load('pf_map_room')) {
  $style = ImageStyle::create(['name' => 'pf_map_room', 'label' => 'Building map room (960x640)']);
  $style->addImageEffect([
    'id' => 'image_scale_and_crop',
    'weight' => 0,
    'data' => ['width' => 960, 'height' => 640, 'anchor' => 'center-center'],
  ]);
  $style->addImageEffect([
    'id' => 'image_convert',
    'weight' => 1,
    'data' => ['extension' => 'webp'],
  ]);
  $style->save();
  echo "Created image style pf_map_room\n";
}

// Relabel the term description for this vocabulary.
$definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('taxonomy_term', 'building_space');
$desc_label = 'Tooltip text / intro';
$desc_help = 'Rooms: one or two sentences shown on hover or focus (no links; use the Link field). Buildings: the intro beside the map.';
$override = $definitions['description']->getConfig('building_space');
if ($override->getLabel() !== $desc_label || $override->getDescription() !== $desc_help) {
  $override->setLabel($desc_label)->setDescription($desc_help)->save();
  echo "Relabelled term description\n";
}

$term_form = $repo->getFormDisplay('taxonomy_term', 'building_space', 'default')
  ->setComponent('name', ['type' => 'string_textfield', 'weight' => 0])
  ->setComponent('description', ['type' => 'text_textarea', 'weight' => 1, 'settings' => ['rows' => 3]])
  ->setComponent('field_space_access', ['type' => 'options_buttons', 'weight' => 2])
  ->setComponent('field_space_link', ['type' => 'link_default', 'weight' => 3])
  ->setComponent('field_space_note', ['type' => 'string_textfield', 'weight' => 4, 'settings' => ['size' => 40, 'placeholder' => 'Now showing']])
  ->removeComponent('field_map_key');
if ($image_types) {
  $term_form->setComponent('field_space_media', $media_widget);
}
$term_form->save();
// The theme prints the note and photo from the preprocess, not the display.
$term_view = $repo->getViewDisplay('taxonomy_term', 'building_space', 'default');
foreach (['field_space_note', 'field_space_media'] as $name) {
  $term_view->removeComponent($name);
}
$term_view->save();

// Sync terms from buildings.yml (create missing; never overwrite).
$find = function (string $map_key): ?Term {
  $ids = \Drupal::entityQuery('taxonomy_term')
    ->accessCheck(FALSE)
    ->condition('vid', 'building_space')
    ->condition('field_map_key', $map_key)
    ->range(0, 1)
    ->execute();
  return $ids ? Term::load(reset($ids)) : NULL;
};
$text = fn (?string $value) => $value ? ['value' => '<p>' . htmlspecialchars($value) . '</p>', 'format' => $format] : NULL;

$building_options = [];
$room_options = [];
$known = [];
$bw = 0;
foreach ($buildings as $bkey => $building) {
  $label = $building['label'] ?? $bkey;
  $building_options[$bkey] = $label;
  $known[$bkey] = TRUE;
  $parent = $find($bkey);
  if (!$parent) {
    $parent = Term::create([
      'vid' => 'building_space',
      'name' => $label,
      'description' => $text($building['intro'] ?? NULL),
      'field_map_key' => $bkey,
      'weight' => $bw,
    ]);
    $parent->save();
    echo "Created building term \"$label\"\n";
  }
  $bw++;
  $rw = 0;
  foreach ($building['spaces'] ?? [] as $skey => $space) {
    $map_key = "$bkey.$skey";
    $known[$map_key] = TRUE;
    $room_label = $space['label'] ?? $skey;
    $room_options[$map_key] = "$label: $room_label (Floor " . ($space['floor'] ?? 1) . ')';
    if (!$find($map_key)) {
      Term::create([
        'vid' => 'building_space',
        'name' => $room_label,
        'parent' => [$parent->id()],
        'description' => $text($space['tooltip'] ?? NULL),
        'field_map_key' => $map_key,
        'field_space_access' => $space['access'] ?? 'walk_in',
        'weight' => $rw,
      ])->save();
      echo "  Created room \"$room_label\"\n";
    }
    $rw++;
  }
}
$orphans = [];
$ids = \Drupal::entityQuery('taxonomy_term')->accessCheck(FALSE)->condition('vid', 'building_space')->execute();
foreach (Term::loadMultiple($ids) as $term) {
  $mk = (string) $term->get('field_map_key')->value;
  if (!isset($known[$mk])) {
    $orphans[] = '"' . $term->label() . '"' . ($mk ? " ($mk)" : ' (no map key: added by hand?)');
  }
}
if ($orphans) {
  echo "Terms not in buildings.yml (not shown on any map): " . implode(', ', $orphans) . "\n";
}

// building_map -------------------------------------------------------------------
$type('building_map', 'Building map', 'A building floor map with hover tooltips, shown in the bento under the search form. Rooms and their text come from Building spaces.');
$type('map_room_override', 'Room override', 'Replaces one room\'s tooltip text, access or link on this map only.');

$storage('paragraph', 'field_map_building', 'list_string', ['allowed_values' => $building_options]);
$options('paragraph', 'field_map_building', $building_options);
$storage('paragraph', 'field_map_heading', 'string', ['max_length' => 120]);
$storage('paragraph', 'field_map_intro', 'text_long');
$storage('paragraph', 'field_map_overrides', 'entity_reference_revisions', ['target_type' => 'paragraph'], -1);
$storage('paragraph', 'field_map_here', 'list_string', ['allowed_values' => $room_options]);
$options('paragraph', 'field_map_here', $room_options);

$field('paragraph', 'building_map', 'field_map_building', 'Building', [
  'required' => TRUE,
  'default_value' => [['value' => array_key_first($building_options)]],
  'description' => 'Rooms, tooltips and links come from Structure > Taxonomy > Building spaces.',
]);
$field('paragraph', 'building_map', 'field_map_heading', 'Heading', [
  'description' => 'Optional. Shown as an h3 above the map and in the page TOC. Leave empty for the building\'s default (e.g. "Visit Hill Memorial Library").',
]);
$field('paragraph', 'building_map', 'field_map_intro', 'Intro', [
  'description' => 'Optional. Leave empty to use the building\'s description from Building spaces.',
]);
$field('paragraph', 'building_map', 'field_map_here', 'You are here (optional)', [
  'description' => 'For a page about one room: marks that room on the map with a "Here" arrow. Leave empty on general pages. Ignored if the room is in another building or unpublished.',
]);
$field('paragraph', 'building_map', 'field_map_overrides', 'Room overrides (optional)', [
  'settings' => $err('map_room_override'),
  'description' => 'Rarely needed. Changes one room\'s tooltip text, access or link on this page only.',
]);

$form = $repo->getFormDisplay('paragraph', 'building_map', 'default')
  ->setComponent('field_map_building', ['type' => 'options_select', 'weight' => 0])
  ->setComponent('field_map_heading', ['type' => 'string_textfield', 'weight' => 1])
  ->setComponent('field_map_intro', ['type' => 'text_textarea', 'weight' => 2, 'settings' => ['rows' => 2]])
  ->setComponent('field_map_here', ['type' => 'options_select', 'weight' => 3])
  ->setComponent('field_map_overrides', $paragraphs_widget(20, 'Room override', 'Room overrides', 'map_room_override'));
$view = $repo->getViewDisplay('paragraph', 'building_map', 'default');
// The template prints everything from the preprocess.
foreach (['field_map_building', 'field_map_heading', 'field_map_intro', 'field_map_here', 'field_map_overrides', 'field_map_spaces'] as $name) {
  $view->removeComponent($name);
}
if (FieldConfig::loadByName('paragraph', 'building_map', 'field_map_spaces')) {
  $form->removeComponent('field_map_spaces');
}
$form->save();
$view->save();

// map_room_override ------------------------------------------------------------------
$storage('paragraph', 'field_room_key', 'list_string', ['allowed_values' => $room_options]);
$options('paragraph', 'field_room_key', $room_options);
$storage('paragraph', 'field_space_body', 'text_long');
$storage('paragraph', 'field_space_access', 'list_string', ['allowed_values' => $access_values]);
$storage('paragraph', 'field_space_link', 'link');

$field('paragraph', 'map_room_override', 'field_room_key', 'Room', ['required' => TRUE]);
$field('paragraph', 'map_room_override', 'field_space_body', 'Tooltip text', [
  'description' => 'Optional. Leave empty to keep the room\'s text.',
]);
$field('paragraph', 'map_room_override', 'field_space_access', 'Access', [
  'required' => FALSE,
  'description' => 'Optional. Leave empty to keep the room\'s setting.',
]);
$field('paragraph', 'map_room_override', 'field_space_link', 'Link', [
  'settings' => $link_settings,
  'description' => 'Optional. Leave empty to keep the room\'s link.',
]);

$repo->getFormDisplay('paragraph', 'map_room_override', 'default')
  ->setComponent('field_room_key', ['type' => 'options_select', 'weight' => 0])
  ->setComponent('field_space_body', ['type' => 'text_textarea', 'weight' => 1, 'settings' => ['rows' => 2]])
  ->setComponent('field_space_access', ['type' => 'options_buttons', 'weight' => 2])
  ->setComponent('field_space_link', ['type' => 'link_default', 'weight' => 3])
  ->save();
$override_view = $repo->getViewDisplay('paragraph', 'map_room_override', 'default');
foreach (['field_room_key', 'field_space_body', 'field_space_access', 'field_space_link'] as $name) {
  $override_view->removeComponent($name);
}
$override_view->save();

// bento_search: the map slot -----------------------------------------------------
$storage('paragraph', 'field_bento_map', 'entity_reference_revisions', ['target_type' => 'paragraph'], 1);
$field('paragraph', 'bento_search', 'field_bento_map', 'Building map', [
  'settings' => $err('building_map'),
  'description' => 'Optional. Shows a building map under the search form.',
]);
$repo->getFormDisplay('paragraph', 'bento_search', 'default')
  ->setComponent('field_bento_map', $paragraphs_widget(50, 'Building map', 'Building maps', 'building_map'))
  ->save();
// Rendered by palefire_preprocess_paragraph__bento_search(), not the display.
$repo->getViewDisplay('paragraph', 'bento_search', 'default')
  ->removeComponent('field_bento_map')
  ->save();

// Earlier per-page "Map space" version ------------------------------------------------
$legacy_ids = ParagraphsType::load('map_space')
  ? \Drupal::entityQuery('paragraph')->accessCheck(FALSE)->condition('type', 'map_space')->execute()
  : [];
$legacy_field = FieldConfig::loadByName('paragraph', 'building_map', 'field_map_spaces');
if (ParagraphsType::load('map_space') || $legacy_field) {
  if ($cleanup) {
    $storage_handler = \Drupal::entityTypeManager()->getStorage('paragraph');
    foreach (array_chunk($legacy_ids, 50) as $chunk) {
      $storage_handler->delete($storage_handler->loadMultiple($chunk));
    }
    if ($legacy_ids) {
      echo 'Deleted ' . count($legacy_ids) . " Map space paragraph(s)\n";
    }
    if ($legacy_field) {
      $legacy_field->delete();
      echo "Deleted building_map.field_map_spaces\n";
    }
    foreach (['field_map_region', 'field_space_access', 'field_space_title', 'field_space_body', 'field_space_link', 'field_space_image'] as $name) {
      if ($config = FieldConfig::loadByName('paragraph', 'map_space', $name)) {
        $config->delete();
      }
    }
    if ($legacy_type = ParagraphsType::load('map_space')) {
      $legacy_type->delete();
      echo "Deleted paragraph type map_space\n";
    }
  }
  else {
    echo 'Old per-page rooms are no longer used: paragraph type map_space (' . count($legacy_ids) . " paragraph(s)) and building_map.field_map_spaces. Re-run with \"-- cleanup\" to delete them.\n";
  }
}

echo "Done. Run drush cr.\n";
