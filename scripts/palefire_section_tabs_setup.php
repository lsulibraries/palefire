<?php

/**
 * @file
 * Creates the Section tabs paragraph types (section_tabs, section_tab).
 *
 * A few ways in, one panel at a time: the Special Collections homepage's
 * Visit / Study / Ask. Template: paragraph--section-tabs.html.twig;
 * preprocess: palefire_preprocess_paragraph__section_tabs(); styles:
 * _section-tabs.scss; behaviour: js/section-tabs.js.
 *
 * Run from the Drupal root on production, after palefire_guide_setup.php:
 *   drush php:script themes/custom/palefire/scripts/palefire_section_tabs_setup.php
 *   drush cr
 *
 * What it does:
 * - Creates two paragraph types:
 *     section_tabs  "Section tabs", a section: the shared section fields
 *                   (from bento_search), Eyebrow, Lede and Link (from
 *                   tile_showcase) and Tabs (field_tabs).
 *     section_tab   One tab:
 *                     field_tab_label   Label (required; 1-2 short words)
 *                     field_tab_icon    Icon: a Font Awesome 5 name
 *                     field_tab_anchor  Link name (optional; #visit)
 *                     field_guide_blocks  Content: the guide section's
 *                                       blocks (text, resource lists,
 *                                       accordions, librarian cards,
 *                                       videos), plus the Tile showcase
 *                                       for its Ask layout
 * - Allows section_tabs wherever bento_search is allowed, such as the
 *   Modular page's sections.
 *
 * Safe to re-run: anything that already exists is left as it is.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\paragraphs\Entity\ParagraphsType;

if (PHP_SAPI !== 'cli') {
  return;
}

foreach (['bento_search' => 'palefire_modular', 'guide_section' => 'palefire_guide_setup.php'] as $needed => $from) {
  if (!ParagraphsType::load($needed)) {
    echo "Paragraph type $needed not found ($from). Set it up first, then re-run.\n";
    return;
  }
}
$guide_blocks = FieldConfig::loadByName('paragraph', 'guide_section', 'field_guide_blocks');
if (!$guide_blocks) {
  echo "guide_section has no field_guide_blocks: run palefire_guide_setup.php first.\n";
  return;
}

$repo = \Drupal::service('entity_display.repository');

// 1. Paragraph types. -----------------------------------------------------------
$types = [
  'section_tabs' => ['Section tabs', 'A few ways in, one panel at a time: labelled tabs with icons over their panels (Special Collections homepage: Visit, Study, Ask).'],
  'section_tab' => ['Tab', 'One tab of a Section tabs section: its label, icon and content.'],
];
foreach ($types as $id => [$label, $description]) {
  if (!ParagraphsType::load($id)) {
    ParagraphsType::create(['id' => $id, 'label' => $label, 'description' => $description])->save();
    echo "Created paragraph type $id\n";
  }
}

// Copies a field from another bundle: its settings, form widget and display.
$copy = static function (string $name, string $from, string $to, bool $show, array $overrides = []) use ($repo): void {
  $source = FieldConfig::loadByName('paragraph', $from, $name);
  if (!$source) {
    echo "Skipped $name: $from doesn't have it.\n";
    return;
  }
  if (!FieldConfig::loadByName('paragraph', $to, $name)) {
    FieldConfig::create($overrides + [
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => $to,
      'label' => $source->getLabel(),
      'description' => $source->getDescription(),
      'required' => $source->isRequired(),
      'settings' => $source->getSettings(),
      'default_value' => $source->getDefaultValueLiteral(),
    ])->save();
    echo "Added $name to paragraph.$to\n";
  }
  $form = $repo->getFormDisplay('paragraph', $to, 'default');
  if (($component = $repo->getFormDisplay('paragraph', $from, 'default')->getComponent($name)) && !$form->getComponent($name)) {
    $form->setComponent($name, $component)->save();
  }
  $view = $repo->getViewDisplay('paragraph', $to, 'default');
  $component = $repo->getViewDisplay('paragraph', $from, 'default')->getComponent($name);
  if ($show && $component) {
    $view->setComponent($name, $component)->save();
  }
  else {
    $view->removeComponent($name)->save();
  }
};

// 2. The section: shared section fields, then Eyebrow, Lede and Link. ----------
foreach (['field_section_title', 'field_section_anchor', 'field_section_snap', 'field_section_height', 'field_section_theme', 'field_section_toc_hide', 'field_section_bg'] as $name) {
  $copy($name, 'bento_search', 'section_tabs', TRUE);
}
foreach (['field_tile_eyebrow', 'field_tile_lede', 'field_tile_link'] as $name) {
  $copy($name, 'tile_showcase', 'section_tabs', FALSE);
}

// 3. A tab: label, icon, link name. ---------------------------------------------
$strings = [
  'field_tab_label' => [64, 'Label', TRUE, 'One or two short words, printed in small capitals over the tab: "Visit", "Study", "Ask us". Longer labels wrap on phones.'],
  'field_tab_icon' => [64, 'Icon', FALSE, 'A Font Awesome 5 icon name, as on fontawesome.com: "landmark", "book-reader", "comments". Solid by default; for another style put it first: "duotone/landmark". The icon must exist in /libraries/fontawesome-pro/svgs on this site.'],
  'field_tab_anchor' => [64, 'Link name', FALSE, 'Optional. The tab\'s link: /v2/special#visit opens it. Defaults to the label in lowercase with hyphens. Keep it unlike any section\'s link name.'],
];
foreach ($strings as $name => [$max, $label, $required, $description]) {
  if (!FieldStorageConfig::loadByName('paragraph', $name)) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'type' => 'string',
      'settings' => ['max_length' => $max],
    ])->save();
    echo "Created storage paragraph.$name\n";
  }
  if (!FieldConfig::loadByName('paragraph', 'section_tab', $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => 'section_tab',
      'label' => $label,
      'description' => $description,
      'required' => $required,
    ])->save();
    echo "Added $name to paragraph.section_tab\n";
  }
}

// 4. A tab's content: the guide section's blocks, plus the Tile showcase. ------
if (!FieldConfig::loadByName('paragraph', 'section_tab', 'field_guide_blocks')) {
  $settings = $guide_blocks->getSettings();
  $handler = $settings['handler_settings'] ?? [];
  if (ParagraphsType::load('tile_showcase') && !($handler['negate'] ?? FALSE)) {
    $handler['target_bundles']['tile_showcase'] = 'tile_showcase';
    if (isset($handler['target_bundles_drag_drop'])) {
      $handler['target_bundles_drag_drop']['tile_showcase'] = ['enabled' => TRUE, 'weight' => count($handler['target_bundles_drag_drop'])];
    }
  }
  $settings['handler_settings'] = $handler;
  FieldConfig::create([
    'field_name' => 'field_guide_blocks',
    'entity_type' => 'paragraph',
    'bundle' => 'section_tab',
    'label' => 'Content',
    'description' => 'What the tab shows, in order: text (a [request-paths] or [special-collections-faqs] line adds the request diagram or the FAQs), resource lists, accordions, librarian cards, videos, or a Tile showcase (its Ask layout for the question form).',
    'required' => TRUE,
    'settings' => $settings,
  ])->save();
  echo "Added field_guide_blocks to paragraph.section_tab\n";
}

// 5. The section's Tabs. ----------------------------------------------------------
if (!FieldStorageConfig::loadByName('paragraph', 'field_tabs')) {
  FieldStorageConfig::create([
    'field_name' => 'field_tabs',
    'entity_type' => 'paragraph',
    'type' => 'entity_reference_revisions',
    'cardinality' => -1,
    'settings' => ['target_type' => 'paragraph'],
  ])->save();
  echo "Created storage paragraph.field_tabs\n";
}
if (!FieldConfig::loadByName('paragraph', 'section_tabs', 'field_tabs')) {
  FieldConfig::create([
    'field_name' => 'field_tabs',
    'entity_type' => 'paragraph',
    'bundle' => 'section_tabs',
    'label' => 'Tabs',
    'description' => 'Two to four tabs, in this order; the first one opens with the page.',
    'required' => TRUE,
    'settings' => [
      'handler' => 'default:paragraph',
      'handler_settings' => [
        'target_bundles' => ['section_tab' => 'section_tab'],
        'negate' => 0,
        'target_bundles_drag_drop' => ['section_tab' => ['enabled' => TRUE, 'weight' => 0]],
      ],
    ],
  ])->save();
  echo "Added field_tabs to paragraph.section_tabs\n";
}

// 6. Widgets and displays. ----------------------------------------------------------
$form = $repo->getFormDisplay('paragraph', 'section_tab', 'default');
$form->setComponent('field_tab_label', ['type' => 'string_textfield', 'weight' => 0, 'settings' => ['size' => 24, 'placeholder' => 'Visit']]);
$form->setComponent('field_tab_icon', ['type' => 'string_textfield', 'weight' => 1, 'settings' => ['size' => 24, 'placeholder' => 'landmark']]);
$form->setComponent('field_tab_anchor', ['type' => 'string_textfield', 'weight' => 2, 'settings' => ['size' => 24, 'placeholder' => 'visit']]);
if (!$form->getComponent('field_guide_blocks')) {
  $form->setComponent('field_guide_blocks', [
    'type' => 'paragraphs',
    'weight' => 3,
    'settings' => [
      'title' => 'Block',
      'title_plural' => 'Blocks',
      'edit_mode' => 'closed',
      'add_mode' => 'modal',
      'form_display_mode' => 'default',
      'default_paragraph_type' => 'guide_text',
    ],
  ]);
}
$form->save();

$form = $repo->getFormDisplay('paragraph', 'section_tabs', 'default');
if (!$form->getComponent('field_tabs')) {
  $weight = (int) max(array_column($form->getComponents(), 'weight') ?: [0]);
  $form->setComponent('field_tabs', [
    'type' => 'paragraphs',
    'weight' => $weight + 1,
    'settings' => [
      'title' => 'Tab',
      'title_plural' => 'Tabs',
      'edit_mode' => 'closed',
      'add_mode' => 'button',
      'form_display_mode' => 'default',
      'default_paragraph_type' => 'section_tab',
    ],
  ])->save();
}

// The template prints the tabs itself.
$repo->getViewDisplay('paragraph', 'section_tabs', 'default')->removeComponent('field_tabs')->save();
$view = $repo->getViewDisplay('paragraph', 'section_tab', 'default');
foreach (['field_tab_label', 'field_tab_icon', 'field_tab_anchor'] as $name) {
  $view->removeComponent($name);
}
$view->setComponent('field_guide_blocks', ['type' => 'entity_reference_revisions_entity_view', 'label' => 'hidden', 'weight' => 0, 'settings' => ['view_mode' => 'default']]);
$view->save();

// 7. Allow section_tabs wherever bento_search is allowed. -------------------------
foreach (FieldConfig::loadMultiple() as $config) {
  if (!in_array($config->getType(), ['entity_reference_revisions', 'entity_reference'], TRUE)
    || $config->getSetting('target_type') !== 'paragraph') {
    continue;
  }
  $handler = $config->getSetting('handler_settings') ?: [];
  $bundles = $handler['target_bundles'] ?? NULL;
  // Paragraphs' "exclude" mode lists bundles that are NOT allowed; any
  // other bundle, including the new one, is already allowed.
  if (($handler['negate'] ?? FALSE) || !is_array($bundles) || !isset($bundles['bento_search']) || isset($bundles['section_tabs'])) {
    continue;
  }
  $handler['target_bundles']['section_tabs'] = 'section_tabs';
  if (isset($handler['target_bundles_drag_drop'])) {
    $handler['target_bundles_drag_drop']['section_tabs'] = ['enabled' => TRUE, 'weight' => count($handler['target_bundles_drag_drop'])];
  }
  $config->setSetting('handler_settings', $handler)->save();
  echo "Allowed section_tabs in " . $config->id() . "\n";
}

echo "Section tabs setup complete. Run: drush cr\n";
