<?php

/**
 * @file
 * Exhibition pages: content model for Modular pages in exhibition mode.
 *
 * For sites whose Modular page sections come from palefire_modular
 * (field_scroll_section). Run palefire_guide_setup.php first: exhibitions
 * reuse its blocks (guide_text, guide_accordion, guide_video) and its
 * field_guide_blocks storage. Config only: it never creates or changes
 * content.
 *
 * Run from the Drupal root on production:
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_setup.php
 *   drush cr
 *
 * Adds:
 * - "exhibition" to field_modular_type, so a page can be set to it.
 * - On node.modular_page (title page, exhibition mode only):
 *   field_exhibit_subtitle, field_exhibit_cover (Media: Image),
 *   field_exhibit_credit, field_exhibit_prompt.
 * - Paragraph types:
 *   - exhibit_page: one gallery of the exhibition (a section). Shared
 *     section fields, a lead image, its focus and "Show whole image"
 *     (field_exhibit_lead_whole, added 2026-10-21), and field_guide_blocks.
 *   - exhibit_entry: a person or book inside a gallery: heading (a TOC
 *     sub-link), anchor, biography, and its own blocks.
 *   - exhibit_figure: one image with a caption and a layout.
 *   - exhibit_gallery: several figures in a grid, with a shared caption.
 *   - exhibit_citation: a catalogue record, set like a museum label.
 *   - exhibit_audio: an oral history clip with a required transcript.
 * - "Sources" on guide_text's Style (field_text_style): a bibliography whose
 *   items become link targets (#src-<surname>).
 * - Image styles exhibit_640, _1280, _1920 and _2560 (scale, WebP).
 * - exhibit_page in field_scroll_section's allowed types.
 *
 * Safe to re-run: existing types, fields and display settings are kept.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;

if (PHP_SAPI !== 'cli') {
  return;
}

$bundle = 'modular_page';
if (!NodeType::load($bundle)) {
  throw new \RuntimeException("Content type '$bundle' not found.");
}
if (!FieldStorageConfig::loadByName('node', 'field_scroll_section')) {
  throw new \RuntimeException('node.field_scroll_section not found. Enable palefire_modular and set up Modular pages first.');
}
foreach (['guide_text', 'guide_section'] as $id) {
  if (!ParagraphsType::load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_guide_setup.php first.");
  }
}
foreach (['media', 'image', 'file', 'options', 'text'] as $module) {
  if (!\Drupal::moduleHandler()->moduleExists($module)) {
    \Drupal::service('module_installer')->install([$module]);
    echo "Enabled $module" . PHP_EOL;
  }
}

/** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $displays */
$displays = \Drupal::service('entity_display.repository');
$notes = [];

// Helpers (as in palefire_guide_setup.php). ----------------------------------

$storage = function (string $entity_type, string $name, string $type, array $settings = [], int $cardinality = 1): void {
  $existing = FieldStorageConfig::loadByName($entity_type, $name);
  if ($existing) {
    if ($existing->getType() !== $type) {
      throw new \RuntimeException("$entity_type.$name already exists as '{$existing->getType()}', not '$type'. Rename one of them and re-run.");
    }
    return;
  }
  FieldStorageConfig::create([
    'field_name' => $name,
    'entity_type' => $entity_type,
    'type' => $type,
    'settings' => $settings,
    'cardinality' => $cardinality,
  ])->save();
  echo "Created storage $entity_type.$name" . PHP_EOL;
};

$field = function (string $entity_type, string $target_bundle, string $name, array $values): void {
  if (!FieldConfig::loadByName($entity_type, $target_bundle, $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => $entity_type,
      'bundle' => $target_bundle,
    ] + $values)->save();
    echo "Created field $entity_type.$target_bundle.$name" . PHP_EOL;
  }
};

$paragraph_type = function (string $id, string $label, string $description): void {
  if (!ParagraphsType::load($id)) {
    ParagraphsType::create(['id' => $id, 'label' => $label, 'description' => $description])->save();
    echo "Created paragraph type $id" . PHP_EOL;
  }
};

// Sets form widgets that aren't set yet, so later edits in Field UI stand.
$form = function (string $entity_type, string $target_bundle, array $components): void {
  $display = \Drupal::service('entity_display.repository')->getFormDisplay($entity_type, $target_bundle, 'default');
  foreach ($components as $name => $component) {
    if (!$display->getComponent($name)) {
      $display->setComponent($name, $component);
    }
  }
  $display->save();
};

// View displays: only when new (Manage display owns them afterwards). The
// templates print the fields themselves, so most displays are empty.
$view = function (string $entity_type, string $target_bundle, array $components): void {
  $display = \Drupal::service('entity_display.repository')->getViewDisplay($entity_type, $target_bundle, 'default');
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
    echo "Set up $entity_type.$target_bundle.default display" . PHP_EOL;
  }
};

$err = fn (array $targets) => [
  'handler' => 'default:paragraph',
  'handler_settings' => [
    'target_bundles' => array_combine($targets, $targets),
    'negate' => 0,
    'target_bundles_drag_drop' => array_combine($targets, array_map(fn ($t, $w) => ['enabled' => TRUE, 'weight' => $w], $targets, array_keys($targets))),
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
    'autocollapse' => 'none',
    'add_mode' => 'modal',
    'form_display_mode' => 'default',
    'default_paragraph_type' => $default,
  ],
];

$wysiwyg = fn (int $weight, int $rows = 6) => ['type' => 'text_textarea', 'weight' => $weight, 'settings' => ['rows' => $rows]];
$textfield = fn (int $weight) => ['type' => 'string_textfield', 'weight' => $weight];
$select = fn (int $weight) => ['type' => 'options_select', 'weight' => $weight];

// 0. Image media type. -------------------------------------------------------

$image_types = [];
foreach (\Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple() as $media_type) {
  if ($media_type->getSource()->getPluginId() === 'image') {
    $image_types[$media_type->id()] = $media_type->id();
  }
}
if (!$image_types) {
  throw new \RuntimeException('No Image media type found (Structure > Media types, source "Image"). Add one and re-run.');
}
// Prefer the core "image" type when there are several.
if (isset($image_types['image'])) {
  $image_types = ['image' => 'image'];
}
echo 'Image media type: ' . implode(', ', $image_types) . PHP_EOL;

$media_ref = fn (string $label, string $help, bool $required = FALSE) => [
  'label' => $label,
  'description' => $help,
  'required' => $required,
  'settings' => ['handler' => 'default:media', 'handler_settings' => ['target_bundles' => $image_types, 'auto_create' => FALSE]],
];
$media_widget = \Drupal::moduleHandler()->moduleExists('media_library')
  ? ['type' => 'media_library_widget', 'settings' => ['media_types' => array_values($image_types)]]
  : ['type' => 'entity_reference_autocomplete', 'settings' => ['match_operator' => 'CONTAINS', 'size' => 60, 'placeholder' => '']];

// 1. Image styles. -----------------------------------------------------------
// Scale only (never upscale) and WebP. The theme builds srcset from these
// (_palefire_exhibit_image()); the 4K originals are never sent to visitors.

foreach ([640, 1280, 1920, 2560] as $width) {
  $id = 'exhibit_' . $width;
  if (ImageStyle::load($id)) {
    continue;
  }
  $style = ImageStyle::create(['name' => $id, 'label' => "Exhibition $width"]);
  $style->addImageEffect(['id' => 'image_scale', 'weight' => 0, 'data' => ['width' => $width, 'height' => NULL, 'upscale' => FALSE]]);
  $style->addImageEffect(['id' => 'image_convert', 'weight' => 1, 'data' => ['extension' => 'webp']]);
  $style->save();
  echo "Created image style $id" . PHP_EOL;
}

// 2. Storages. ---------------------------------------------------------------

$storage('node', 'field_exhibit_subtitle', 'string', ['max_length' => 255]);
$storage('node', 'field_exhibit_cover', 'entity_reference', ['target_type' => 'media']);
$storage('node', 'field_exhibit_credit', 'text_long');
$storage('node', 'field_exhibit_prompt', 'string', ['max_length' => 255]);

$storage('paragraph', 'field_exhibit_lead', 'entity_reference', ['target_type' => 'media']);
$storage('paragraph', 'field_exhibit_lead_focus', 'list_string', ['allowed_values' => [
  'center' => 'Centre',
  'top' => 'Top',
  'bottom' => 'Bottom',
  'left' => 'Left',
  'right' => 'Right',
]]);
$storage('paragraph', 'field_exhibit_lead_whole', 'boolean');
$storage('paragraph', 'field_entry_bio', 'text_long');
$storage('paragraph', 'field_figure_image', 'entity_reference', ['target_type' => 'media']);
$storage('paragraph', 'field_figure_caption', 'text_long');
$storage('paragraph', 'field_figure_layout', 'list_string', ['allowed_values' => [
  'side_start' => 'Beside the text, image first',
  'side_end' => 'Beside the text, image second',
  'center' => 'Centred in the text column',
  'wide' => 'Full width, slow parallax',
]]);
$storage('paragraph', 'field_gallery_items', 'entity_reference_revisions', ['target_type' => 'paragraph'], -1);
$storage('paragraph', 'field_audio_file', 'file', ['uri_scheme' => 'public']);
$storage('paragraph', 'field_audio_credit', 'string', ['max_length' => 255]);
$storage('paragraph', 'field_audio_transcript', 'text_long');
// Shared with the guide blocks (palefire_guide_setup.php made them).
foreach (['field_block_title', 'field_block_body', 'field_guide_blocks', 'field_section_title', 'field_section_anchor'] as $name) {
  if (!FieldStorageConfig::loadByName('paragraph', $name)) {
    throw new \RuntimeException("paragraph.$name not found. Run palefire_guide_setup.php first.");
  }
}

// 3. Paragraph types. --------------------------------------------------------

$paragraph_type('exhibit_figure', 'Exhibition: Image', 'One image with its caption. Click opens it full screen.');
$paragraph_type('exhibit_gallery', 'Exhibition: Image group', 'Several images side by side, each with its own caption, and an optional caption for the group.');
$paragraph_type('exhibit_citation', 'Exhibition: Catalogue record', 'The book\'s bibliographic record, set like a museum label.');
$paragraph_type('exhibit_audio', 'Exhibition: Audio', 'An oral history clip with its transcript.');
$paragraph_type('exhibit_entry', 'Exhibition: Entry', 'A person or a book: heading, biography and its images, records and text.');
$paragraph_type('exhibit_page', 'Exhibition: Gallery', 'One page of an exhibition. Visitors move between galleries with the table of contents.');

// exhibit_figure.
$field('paragraph', 'exhibit_figure', 'field_figure_image', $media_ref('Image', 'Alt text comes from the media item: describe what the image shows. The caption below is shown with it.'));
$field('paragraph', 'exhibit_figure', 'field_figure_caption', ['label' => 'Caption (optional)']);
$field('paragraph', 'exhibit_figure', 'field_figure_layout', ['label' => 'Layout', 'required' => TRUE, 'default_value' => [['value' => 'side_start']]]);
$form('paragraph', 'exhibit_figure', [
  'field_figure_image' => $media_widget + ['weight' => 0],
  'field_figure_caption' => $wysiwyg(1, 4),
  'field_figure_layout' => ['type' => 'options_buttons', 'weight' => 2],
]);
$view('paragraph', 'exhibit_figure', ['field_figure_caption' => ['type' => 'text_default', 'label' => 'hidden', 'weight' => 0]]);

// exhibit_gallery.
$field('paragraph', 'exhibit_gallery', 'field_block_title', ['label' => 'Heading (optional)', 'description' => 'Shows as a sub-link in the table of contents.']);
$field('paragraph', 'exhibit_gallery', 'field_gallery_items', [
  'label' => 'Images',
  'required' => TRUE,
  'description' => 'Two or more. Their Layout setting is ignored inside a group.',
  'settings' => $err(['exhibit_figure']),
]);
$field('paragraph', 'exhibit_gallery', 'field_figure_caption', ['label' => 'Caption for the group (optional)']);
$form('paragraph', 'exhibit_gallery', [
  'field_block_title' => $textfield(0),
  'field_gallery_items' => $paragraphs_widget(1, 'Image', 'Images', 'exhibit_figure'),
  'field_figure_caption' => $wysiwyg(2, 4),
]);
$view('paragraph', 'exhibit_gallery', [
  'field_gallery_items' => ['type' => 'entity_reference_revisions_entity_view', 'label' => 'hidden', 'weight' => 0, 'settings' => ['view_mode' => 'default']],
  'field_figure_caption' => ['type' => 'text_default', 'label' => 'hidden', 'weight' => 1],
]);

// exhibit_citation.
$field('paragraph', 'exhibit_citation', 'field_block_body', ['label' => 'Record', 'required' => TRUE, 'description' => 'Title, imprint, format and references, as in the catalogue.']);
$form('paragraph', 'exhibit_citation', ['field_block_body' => $wysiwyg(0, 4)]);
$view('paragraph', 'exhibit_citation', ['field_block_body' => ['type' => 'text_default', 'label' => 'hidden', 'weight' => 0]]);

// exhibit_audio.
$field('paragraph', 'exhibit_audio', 'field_block_title', ['label' => 'Title', 'required' => TRUE]);
$field('paragraph', 'exhibit_audio', 'field_audio_file', [
  'label' => 'Audio file',
  'required' => TRUE,
  'description' => 'MP3 or M4A. Keep clips short; long interviews are better linked from the transcript.',
  'settings' => ['file_extensions' => 'mp3 m4a ogg oga wav', 'file_directory' => 'exhibitions/audio', 'max_filesize' => '64 MB', 'description_field' => FALSE],
]);
$field('paragraph', 'exhibit_audio', 'field_audio_credit', ['label' => 'Speaker and date (optional)', 'description' => 'e.g. "John P. O\'Neill, interviewed 2011".']);
$field('paragraph', 'exhibit_audio', 'field_audio_transcript', ['label' => 'Transcript', 'required' => TRUE, 'description' => 'Required for accessibility (WCAG 1.2.1).']);
$form('paragraph', 'exhibit_audio', [
  'field_block_title' => $textfield(0),
  'field_audio_file' => ['type' => 'file_generic', 'weight' => 1, 'settings' => ['progress_indicator' => 'throbber']],
  'field_audio_credit' => $textfield(2),
  'field_audio_transcript' => $wysiwyg(3, 8),
]);
$view('paragraph', 'exhibit_audio', ['field_audio_transcript' => ['type' => 'text_default', 'label' => 'hidden', 'weight' => 0]]);

// Blocks an entry and a gallery page may hold.
$video = ParagraphsType::load('guide_video') ? ['guide_video'] : [];
$entry_blocks = ['guide_text', 'exhibit_figure', 'exhibit_gallery', 'exhibit_citation', 'exhibit_audio'];
$page_blocks = array_merge(['guide_text', 'exhibit_entry', 'exhibit_figure', 'exhibit_gallery', 'exhibit_citation', 'exhibit_audio', 'guide_accordion'], $video);

// exhibit_entry.
$field('paragraph', 'exhibit_entry', 'field_block_title', ['label' => 'Heading', 'required' => TRUE, 'description' => 'e.g. "Francisco Hernández (1514-1587)". Shows as a sub-link under the gallery in the table of contents.']);
$field('paragraph', 'exhibit_entry', 'field_section_anchor', ['label' => 'Anchor (optional)', 'description' => 'Link fragment, e.g. "hernandez", so other pages can link to #hernandez. Defaults to the heading.']);
$field('paragraph', 'exhibit_entry', 'field_entry_bio', ['label' => 'Biography (optional)', 'description' => 'The full biography. Long ones show their opening lines with a "Read the full biography" button.']);
$field('paragraph', 'exhibit_entry', 'field_guide_blocks', [
  'label' => 'Content',
  'description' => 'Images, catalogue records, audio and text, in order.',
  'settings' => $err($entry_blocks),
]);
$form('paragraph', 'exhibit_entry', [
  'field_block_title' => $textfield(0),
  'field_section_anchor' => $textfield(1),
  'field_entry_bio' => $wysiwyg(2, 8),
  'field_guide_blocks' => $paragraphs_widget(3, 'Block', 'Blocks', 'guide_text'),
]);
$view('paragraph', 'exhibit_entry', [
  'field_entry_bio' => ['type' => 'text_default', 'label' => 'hidden', 'weight' => -1],
  'field_guide_blocks' => ['type' => 'entity_reference_revisions_entity_view', 'label' => 'hidden', 'weight' => 0, 'settings' => ['view_mode' => 'default']],
]);

// exhibit_page: palefire_modular's shared section fields, reused with the
// widget another section type already uses (as guide_section does).
$shared = [
  'field_section_title' => ['label' => 'Gallery title', 'required' => TRUE, 'description' => 'The gallery heading and its label in the table of contents.'],
  'field_section_anchor' => ['label' => 'Anchor (optional)', 'description' => 'Custom link fragment, e.g. "featherwork". Defaults to the title.'],
  'field_section_toc_hide' => ['label' => 'Hide from the table of contents'],
];
$section_form = [];
$weight = 0;
foreach ($shared as $name => $values) {
  if (!FieldStorageConfig::loadByName('paragraph', $name)) {
    continue;
  }
  $field('paragraph', 'exhibit_page', $name, $values);
  $component = $displays->getFormDisplay('paragraph', 'guide_section', 'default')->getComponent($name)
    ?: (FieldStorageConfig::loadByName('paragraph', $name)->getType() === 'boolean'
      ? ['type' => 'boolean_checkbox', 'settings' => ['display_label' => TRUE]]
      : ['type' => 'string_textfield']);
  $section_form[$name] = ['weight' => $weight++] + $component;
}
$field('paragraph', 'exhibit_page', 'field_exhibit_lead', $media_ref('Lead image (optional)', 'Shown full width at the top of the gallery with a curtain reveal, then panned from top to bottom as the page scrolls. Decorative: it is not announced, so use an image whose content the gallery doesn\'t depend on.'));
// Added 2026-10-21. Re-running the script adds it to an existing site.
$field('paragraph', 'exhibit_page', 'field_exhibit_lead_whole', [
  'label' => 'Show whole image',
  'description' => 'Show the lead uncropped, centred on the page, instead of as a full-width band. Good for plates on white paper and tall images.',
  'settings' => ['on_label' => 'Whole image', 'off_label' => 'Full-width band'],
]);
$field('paragraph', 'exhibit_page', 'field_exhibit_lead_focus', ['label' => 'Keep in view when cropped', 'default_value' => [['value' => 'center']]]);
$field('paragraph', 'exhibit_page', 'field_guide_blocks', [
  'label' => 'Content',
  'description' => 'Entries, images, catalogue records, audio and text, in order.',
  'settings' => $err($page_blocks),
]);
$section_form['field_exhibit_lead'] = $media_widget + ['weight' => 10];
$section_form['field_exhibit_lead_focus'] = $select(11);
$section_form['field_exhibit_lead_whole'] = ['type' => 'boolean_checkbox', 'weight' => 12, 'settings' => ['display_label' => TRUE]];
$section_form['field_guide_blocks'] = $paragraphs_widget(20, 'Block', 'Blocks', 'exhibit_entry');
$form('paragraph', 'exhibit_page', $section_form);
$view('paragraph', 'exhibit_page', [
  'field_guide_blocks' => ['type' => 'entity_reference_revisions_entity_view', 'label' => 'hidden', 'weight' => 0, 'settings' => ['view_mode' => 'default']],
]);

// 4. Node fields (the title page). -------------------------------------------

$field('node', $bundle, 'field_exhibit_subtitle', ['label' => 'Exhibition subtitle', 'description' => 'Exhibition pages only: the line under the title on the title page.']);
$field('node', $bundle, 'field_exhibit_cover', $media_ref('Exhibition title image', 'Exhibition pages only: full-screen image behind the title page. Decorative.'));
$field('node', $bundle, 'field_exhibit_credit', ['label' => 'Exhibition credit line', 'description' => 'Exhibition pages only: e.g. the collection and the funder.']);
$field('node', $bundle, 'field_exhibit_prompt', ['label' => 'Exhibition prompt (optional)', 'description' => 'Exhibition pages only. Default: "Choose a gallery from the table of contents to begin."']);
$form('node', $bundle, [
  'field_exhibit_subtitle' => $textfield(6),
  'field_exhibit_cover' => $media_widget + ['weight' => 7],
  'field_exhibit_credit' => $wysiwyg(8, 2),
  'field_exhibit_prompt' => $textfield(9),
]);
// The theme prints them in the title page (exhibition mode only).
foreach (['default', 'full'] as $mode) {
  if ($mode === 'full' && !array_key_exists('full', $displays->getViewModeOptionsByBundle('node', $bundle))) {
    continue;
  }
  $node_view = $displays->getViewDisplay('node', $bundle, $mode);
  foreach (['field_exhibit_subtitle', 'field_exhibit_cover', 'field_exhibit_credit', 'field_exhibit_prompt'] as $name) {
    $node_view->removeComponent($name);
  }
  $node_view->save();
}

// 5. Allowed values and section types. ---------------------------------------

// field_modular_type: add "exhibition". Adding a value never touches
// existing pages.
$mode_storage = FieldStorageConfig::loadByName('node', 'field_modular_type');
if ($mode_storage) {
  $allowed = $mode_storage->getSetting('allowed_values') ?? [];
  if (!isset($allowed['exhibition'])) {
    $allowed['exhibition'] = 'Exhibition';
    $mode_storage->setSetting('allowed_values', $allowed)->save();
    echo 'Added "exhibition" to field_modular_type' . PHP_EOL;
  }
}
else {
  $notes[] = 'node.field_modular_type not found; exhibition mode needs it.';
}

// guide_text Style: add "sources".
$style_storage = FieldStorageConfig::loadByName('paragraph', 'field_text_style');
if ($style_storage) {
  $allowed = $style_storage->getSetting('allowed_values') ?? [];
  if (!isset($allowed['sources'])) {
    $allowed['sources'] = 'Sources (bibliography: each list item is a link target)';
    $style_storage->setSetting('allowed_values', $allowed)->save();
    echo 'Added "sources" to field_text_style' . PHP_EOL;
  }
}

// Allow exhibit_page in field_scroll_section.
$sections = FieldConfig::loadByName('node', $bundle, 'field_scroll_section');
if ($sections) {
  $settings = $sections->getSetting('handler_settings') ?: [];
  $targets = $settings['target_bundles'] ?? [];
  $negate = !empty($settings['negate']);
  $changed = FALSE;
  if ($negate && isset($targets['exhibit_page'])) {
    unset($targets['exhibit_page']);
    $changed = TRUE;
  }
  elseif (!$negate && $targets && !isset($targets['exhibit_page'])) {
    $targets['exhibit_page'] = 'exhibit_page';
    $settings['target_bundles_drag_drop']['exhibit_page'] = ['enabled' => TRUE, 'weight' => count($targets)];
    $changed = TRUE;
  }
  if ($changed) {
    $settings['target_bundles'] = $targets;
    $sections->setSetting('handler_settings', $settings)->save();
    echo 'Allowed exhibit_page in field_scroll_section' . PHP_EOL;
  }
}

foreach ($notes as $note) {
  echo 'Note: ' . $note . PHP_EOL;
}
echo 'Exhibition setup complete. Give editors "Create new media" for images, then run: drush cr' . PHP_EOL;
