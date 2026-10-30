<?php

/**
 * @file
 * Shorter edit forms for Modular pages and their sections (Paragraphs).
 *
 * Run from the Drupal root:
 *   drush php:script themes/custom/palefire/scripts/palefire_edit_form_tidy.php
 *   drush php:script themes/custom/palefire/scripts/palefire_edit_form_tidy.php -- apply=1
 *   drush cr
 *
 * Without apply=1 it lists what it would change and every section field's
 * current label and help text, and saves nothing. Only the Modular page
 * and the paragraph types it can hold (at any depth) are touched; the old
 * theme's content types are left as they are. With apply=1:
 * - Every Paragraphs field (Sections, Tiles, ...) opens collapsed: one
 *   summary row per item, with Edit,
 *   Duplicate and "Add above", and dragging rows reorders them. Opening one
 *   item closes the others; "Edit all" / "Collapse all" sit above the list.
 * - Field groups shown as details (field_group) start closed.
 * - The Auto / Light / Dark section theme (field_section_theme, and any
 *   other list field with auto, light and dark values) leaves the forms.
 *   Saved values stay, so pages look the same; new sections get the field's
 *   default.
 * - Layout (field_tile_layout): "masonry" reads "Default (Grid / List
 *   switch)" and comes first; the other options get plain labels, and the
 *   help text describes only the options the field has.
 * - Clearer labels and help text for the Modular page and its landing
 *   sections ($fields below). Guide and exhibition fields already have
 *   their own wording and are left alone, as are fields not in $fields
 *   (listed by the dry run).
 *
 * Idempotent: a second run changes nothing. Undo: edit the fields and Manage
 * form display by hand (no other config is touched).
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;

if (PHP_SAPI !== 'cli') {
  return;
}

$args = [];
foreach ((isset($extra) && is_array($extra)) ? $extra : [] as $arg) {
  if (str_contains((string) $arg, '=')) {
    [$k, $v] = explode('=', (string) $arg, 2);
    $args[trim($k)] = trim($v);
  }
}
$apply = !empty($args['apply']);
$changes = 0;
$note = function (string $line) use (&$changes, $apply): void {
  $changes++;
  echo ($apply ? '  done: ' : '  would: ') . $line . PHP_EOL;
};

/** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $repo */
$repo = \Drupal::service('entity_display.repository');

// The bundles whose labels are rewritten: the Modular page and its landing
// sections. Guide and exhibition paragraphs keep their own wording.
$label_bundles = [
  'node.modular_page',
  'paragraph.bento_search',
  'paragraph.events_carousel',
  'paragraph.hero_tile',
  'paragraph.landing_department',
  'paragraph.landing_hero',
  'paragraph.landing_stacks',
  'paragraph.map',
  'paragraph.tile_showcase',
];

// Labels and help text, by field name, on the bundles above that have the
// field; NULL keeps what is there. A third value limits a field to those
// bundles (field_tiles is "Featured item" on From the stacks).
$fields = [
  'field_section_title' => [
    'Heading',
    'The section\'s heading, also listed under "On this page". A few words.',
  ],
  'field_section_anchor' => [
    'Link name (optional)',
    'Ends a link straight to this section: "visit" makes /special#visit. Lowercase letters, numbers and hyphens. Blank makes one from the heading.',
  ],
  'field_section_toc_hide' => [
    'Leave out of "On this page"',
    'Tick to keep this section out of the page\'s list of sections.',
  ],
  'field_section_height' => [
    'Height',
    'How much of the screen the section fills.',
  ],
  'field_section_snap' => [
    'Snap into place when scrolling',
    'The page settles on this section as visitors scroll to it. Best on sections that fill the screen.',
  ],
  'field_section_bg' => [
    'Background image (optional)',
    NULL,
  ],
  'field_bg_videos' => [
    'Background video',
    'Plays silently behind the section.',
  ],
  'field_bg_poster' => [
    'Background still image',
    'Shown while the video loads, and in its place when it can\'t play.',
  ],
  'field_bento_hide_lead' => [
    'Hide lead panel',
    'Leave out the title and intro panel so the building map opens the section. Only used when a building map is added; the page title stays for screen readers.',
  ],
  'field_quick_guide_title' => ['Quick guide heading', NULL],
  'field_quick_guide_intro' => ['Quick guide text', NULL],
  'field_quick_guide_link' => ['Quick guide link', NULL],
  'field_hero_banners' => ['Banners', NULL],
  'field_tiles' => [
    'Cards',
    'Tiles or Modular pages, in the order shown; drag to reorder. Each card uses the item\'s title, cover image and Category terms, and links to it. Unpublished items are left out.',
    ['paragraph.tile_showcase'],
  ],
  'field_scroll_section' => [
    'Sections',
    'The page\'s sections, top to bottom. Drag a row to move it; Edit opens it. Add new ones with the button under the list.',
  ],
  'field_image_cover' => ['Cover image', NULL],
  'field_authorized_editors' => [
    'Authorized editors',
    'Staff who can edit this page.',
  ],
  'field_tile_eyebrow' => [
    'Kicker',
    'The small line above the heading, e.g. "Teaching & Research". Optional.',
  ],
  'field_tile_lede' => [
    'Intro',
    'One or two sentences under the heading. Optional.',
  ],
  'field_tile_link' => [
    'Link',
    'An optional link after the cards. Its text should say where it goes, e.g. "Ask about a program".',
  ],
  'field_hero_tiles' => [
    'Hero tiles',
    'Up to three tiles under the hero. Drag to reorder. Empty shows placeholder tiles.',
  ],
  'field_hero_tile_page' => [
    'Page',
    'The tile shows this page\'s title, intro and background poster, and links to it.',
  ],
  'field_hero_tile_label' => [
    'Label',
    'The small line above the title, e.g. "Exhibition" or "Guide". Optional.',
  ],
  'field_hero_tile_when' => [
    'Dates',
    'Under the title, e.g. "Sep 1 – Dec 19, 2026" or "On view · Permanent". Optional.',
  ],
  'field_landing_blog_category' => [
    'Blog categories',
    NULL,
  ],
];

// field_tile_layout options, in the order the select lists them: label,
// then the sentence for the field's help text.
$layouts = [
  'masonry' => ['Default (Grid / List switch)', 'Default: tall cards that visitors can switch to a list.'],
  'stagger' => ['Staggered row (tall cards)', 'Staggered row: tall cards on an offset rhythm, swipeable on phones.'],
  'grid' => ['Grid (3 columns)', 'Grid: landscape cards in three columns, fewer on smaller screens.'],
  'index' => ['Index (numbered list)', 'Index: numbered rows with each item\'s note, no pictures.'],
  'ledger' => ['Ledger (rows by category)', 'Ledger: rows grouped under their Category labels, beside the heading.'],
  'ask' => ['Ask (FAQs and a question form)', 'Ask: Special Collections FAQs and a question form; Cards is not used.'],
];

// Paragraphs widget settings for a short, re-orderable form.
$collapsed = [
  'edit_mode' => 'closed',
  'closed_mode' => 'summary',
  'autocollapse' => 'all',
  'closed_mode_threshold' => 0,
];
$features = ['add_above' => 'add_above', 'collapse_edit_all' => 'collapse_edit_all', 'duplicate' => 'duplicate'];

// Which fields are the Auto / Light / Dark theme select.
$is_theme_field = function (string $entity_type, string $name): bool {
  if ($name === 'field_section_theme') {
    return TRUE;
  }
  $storage = FieldStorageConfig::loadByName($entity_type, $name);
  if (!$storage || $storage->getType() !== 'list_string') {
    return FALSE;
  }
  $keys = array_map('strtolower', array_keys($storage->getSetting('allowed_values') ?: []));
  return !array_diff(['auto', 'light', 'dark'], $keys);
};

// 1. Form displays. ------------------------------------------------------------
// The Modular page, then every paragraph type it can hold, at any depth.
if (!NodeType::load('modular_page')) {
  echo 'Content type modular_page not found: nothing to do.' . PHP_EOL;
  return;
}
$field_manager = \Drupal::service('entity_field.manager');
$all_paragraphs = array_keys(ParagraphsType::loadMultiple());
$targets = [['node', 'modular_page']];
for ($i = 0; $i < count($targets); $i++) {
  [$entity_type, $bundle] = $targets[$i];
  foreach ($field_manager->getFieldDefinitions($entity_type, $bundle) as $definition) {
    if ($definition->getType() !== 'entity_reference_revisions' || $definition->getSetting('target_type') !== 'paragraph') {
      continue;
    }
    $handler = $definition->getSetting('handler_settings') ?: [];
    $listed = array_keys(array_filter($handler['target_bundles'] ?? []));
    // No list allows every type; "negate" allows every type but the list.
    $allowed_types = !$listed ? $all_paragraphs
      : (!empty($handler['negate']) ? array_diff($all_paragraphs, $listed) : $listed);
    foreach ($allowed_types as $type) {
      if (!in_array(['paragraph', $type], $targets, TRUE)) {
        $targets[] = ['paragraph', $type];
      }
    }
  }
}

$hidden_theme = [];
foreach ($targets as [$entity_type, $bundle]) {
  $form = $repo->getFormDisplay($entity_type, $bundle, 'default');
  if ($form->isNew()) {
    continue;
  }
  $id = "$entity_type.$bundle";
  $dirty = FALSE;

  foreach ($form->getComponents() as $name => $component) {
    $type = $component['type'] ?? '';
    if (in_array($type, ['paragraphs', 'entity_reference_paragraphs'], TRUE)) {
      $settings = $component['settings'] ?? [];
      $want = $settings;
      if ($type === 'paragraphs') {
        $want = array_merge($settings, $collapsed);
        $want['features'] = $features + array_filter($settings['features'] ?? []);
      }
      else {
        // The classic widget only knows open / closed / preview.
        $want['edit_mode'] = 'closed';
      }
      // The Modular page's sections list says "Section", not "Paragraph".
      if ($name === 'field_scroll_section' && in_array($want['title'] ?? 'Paragraph', ['Paragraph', ''], TRUE)) {
        $want['title'] = 'Section';
        $want['title_plural'] = 'Sections';
      }
      if ($want != $settings) {
        $component['settings'] = $want;
        $form->setComponent($name, $component);
        $note("$id: $name opens collapsed");
        $dirty = TRUE;
      }
    }
    if ($is_theme_field($entity_type, $name)) {
      $form->removeComponent($name);
      $hidden_theme[$name] = TRUE;
      $note("$id: $name (Auto / Light / Dark) hidden from the form");
      $dirty = TRUE;
    }
  }

  foreach ($form->getThirdPartySettings('field_group') as $group_name => $group) {
    if (($group['format_type'] ?? '') === 'details' && !empty($group['format_settings']['open'])) {
      $group['format_settings']['open'] = FALSE;
      $form->setThirdPartySetting('field_group', $group_name, $group);
      $note("$id: group \"{$group['label']}\" starts closed");
      $dirty = TRUE;
    }
  }

  if ($dirty && $apply) {
    $form->save();
  }
}
if (!$hidden_theme) {
  echo 'No Auto / Light / Dark field found on any form (already hidden, or named differently: see the list below).' . PHP_EOL;
}

// 2. Layout options. -----------------------------------------------------------
if ($layout = FieldStorageConfig::loadByName('paragraph', 'field_tile_layout')) {
  $allowed = $layout->getSetting('allowed_values') ?: [];
  $sorted = [];
  foreach ($layouts as $key => [$label]) {
    if (isset($allowed[$key])) {
      $sorted[$key] = $label;
    }
  }
  // Values this script doesn't know keep their label, after the rest.
  $sorted += $allowed;
  // Compare order too: the select lists them in this order.
  if (array_keys($sorted) !== array_keys($allowed) || $sorted != $allowed) {
    $note('field_tile_layout options: ' . implode(' | ', $sorted));
    if ($apply) {
      $layout->setSetting('allowed_values', $sorted)->save();
    }
  }
  $help = implode(' ', array_map(fn ($key) => $layouts[$key][1], array_intersect(array_keys($layouts), array_keys($allowed))));
  foreach (ParagraphsType::loadMultiple() as $bundle => $type) {
    if (!$field = FieldConfig::loadByName('paragraph', $bundle, 'field_tile_layout')) {
      continue;
    }
    if ($field->getLabel() !== 'Layout' || $field->getDescription() !== $help) {
      $note("paragraph.$bundle.field_tile_layout help: $help");
      if ($apply) {
        $field->setLabel('Layout')->setDescription($help)->save();
      }
    }
  }
}
else {
  echo 'paragraph.field_tile_layout not found: Layout options unchanged.' . PHP_EOL;
}

// 3. Labels and help text. -----------------------------------------------------
$bundles = array_map(fn ($id) => explode('.', $id, 2), $label_bundles);
foreach ($bundles as [$entity_type, $bundle]) {
  foreach ($fields as $name => $info) {
    [$label, $description] = $info;
    if (isset($info[2]) && !in_array("$entity_type.$bundle", $info[2], TRUE)) {
      continue;
    }
    if (!$field = FieldConfig::loadByName($entity_type, $bundle, $name)) {
      continue;
    }
    $new_label = $label ?? $field->getLabel();
    $new_description = $description ?? $field->getDescription();
    if ($field->getLabel() !== $new_label || $field->getDescription() !== $new_description) {
      $note("$entity_type.$bundle.$name: " . ($field->getLabel() === $new_label
        ? "\"$new_label\" help text"
        : "\"{$field->getLabel()}\" -> \"$new_label\""));
      if ($apply) {
        $field->setLabel($new_label)->setDescription($new_description)->save();
      }
    }
  }
}

// 4. The rest, for the next pass at their wording. ----------------------------
if (!$apply) {
  echo PHP_EOL . 'Section fields this script leaves as they are (form order):' . PHP_EOL;
  foreach ($bundles as [$entity_type, $bundle]) {
    $form = $repo->getFormDisplay($entity_type, $bundle, 'default');
    $components = $form->getComponents();
    uasort($components, fn ($a, $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));
    $lines = [];
    foreach (array_keys($components) as $name) {
      $field = FieldConfig::loadByName($entity_type, $bundle, $name);
      if (!$field || isset($fields[$name]) || $name === 'field_tile_layout' || $is_theme_field($entity_type, $name)) {
        continue;
      }
      $lines[] = "    $name ({$field->getType()}): \"{$field->getLabel()}\"" .
        ($field->getDescription() !== '' ? ' - ' . mb_strimwidth(strip_tags((string) $field->getDescription()), 0, 110, '...') : '');
    }
    if ($lines) {
      echo "  $entity_type.$bundle" . PHP_EOL . implode(PHP_EOL, $lines) . PHP_EOL;
    }
  }
}

echo PHP_EOL . ($changes
  ? ($apply ? "$changes change(s) saved. Run drush cr." : "$changes change(s) to make. Nothing saved. Re-run with: -- apply=1")
  : 'Nothing to change.') . PHP_EOL;
