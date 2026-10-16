<?php

/**
 * @file
 * Guide pages: content model for LibGuides-style Modular pages.
 *
 * For sites whose Modular page sections come from palefire_modular
 * (field_scroll_section). Config only: it never creates or changes content.
 *
 * Run from the Drupal root on production:
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_setup.php
 *   drush cr
 *
 * The Staff content type and the Subjects vocabulary are found by name
 * ("staff", "subject"). If the script can't tell which one to use, it says
 * so and lists the candidates; pass the machine names after "--":
 *   drush php:script …/palefire_guide_setup.php -- staff=staff_profile vocab=subjects
 *
 * Adds:
 * - Content type "Resource" (resource): one link, database, book or tool,
 *   shared by every guide that lists it. Fields: field_resource_link,
 *   field_resource_desc, field_resource_citation, field_resource_type,
 *   field_resource_labels, field_resource_proxy.
 * - View modes node.guide_item (a resource in a list) and node.guide_card
 *   (a staff member on a Librarian card). guide_card is set up on the Staff
 *   type with its email, phone, image and title-like fields; change it in
 *   Staff > Manage display > Guide card.
 * - Paragraph types: guide_section (a section: shared section fields +
 *   field_guide_blocks), and the blocks it holds: guide_text,
 *   resource_list, guide_accordion (+ accordion_item), librarian_card,
 *   guide_video.
 * - On node.modular_page: field_guide_owner, field_guide_subjects,
 *   field_guide_source. guide_section is added to field_scroll_section's
 *   allowed types.
 *
 * Safe to re-run: existing types, fields and display settings are kept.
 * Field storages are shared across bundles and reused when they exist; a
 * storage that exists with a different field type stops the script.
 *
 * List fields take allowed_values as [key => label] (see
 * palefire_visit_setup.php for why).
 */

declare(strict_types=1);

use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\taxonomy\Entity\Vocabulary;

if (PHP_SAPI !== 'cli') {
  return;
}

// Arguments after "--": staff=<node type> vocab=<vocabulary>.
$args = [];
foreach ((isset($extra) && is_array($extra)) ? $extra : [] as $arg) {
  if (str_contains((string) $arg, '=')) {
    [$k, $v] = explode('=', (string) $arg, 2);
    $args[trim($k)] = trim($v);
  }
}

$bundle = 'modular_page';
if (!NodeType::load($bundle)) {
  throw new \RuntimeException("Content type '$bundle' not found.");
}
if (!FieldStorageConfig::loadByName('node', 'field_scroll_section')) {
  throw new \RuntimeException('node.field_scroll_section not found. Enable palefire_modular and set up Modular pages first.');
}
foreach (['paragraphs', 'entity_reference_revisions', 'options', 'text', 'link', 'taxonomy'] as $module) {
  if (!\Drupal::moduleHandler()->moduleExists($module)) {
    \Drupal::service('module_installer')->install([$module]);
    echo "Enabled $module" . PHP_EOL;
  }
}

/** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $displays */
$displays = \Drupal::service('entity_display.repository');
$notes = [];

// Helpers. -------------------------------------------------------------------

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

$view_mode = function (string $id, string $label): void {
  if (!EntityViewMode::load("node.$id")) {
    EntityViewMode::create(['id' => "node.$id", 'label' => $label, 'targetEntityType' => 'node'])->save();
    echo "Created view mode node.$id" . PHP_EOL;
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

// View displays: show only the listed fields and hide everything else, but
// only when the display is new. Once it exists, Manage display owns it and
// re-runs leave it alone.
$view = function (string $entity_type, string $target_bundle, string $mode, array $components): void {
  $repo = \Drupal::service('entity_display.repository');
  $display = $repo->getViewDisplay($entity_type, $target_bundle, $mode);
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
    echo "Set up $entity_type.$target_bundle.$mode display" . PHP_EOL;
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

$paragraphs_widget = fn (int $weight, string $title, string $plural, string $default, string $add_mode = 'button') => [
  'type' => 'paragraphs',
  'weight' => $weight,
  'settings' => [
    'title' => $title,
    'title_plural' => $plural,
    'edit_mode' => 'closed',
    'closed_mode' => 'summary',
    'autocollapse' => 'none',
    'add_mode' => $add_mode,
    'form_display_mode' => 'default',
    'default_paragraph_type' => $default,
  ],
];

$node_ref = fn (array $bundles, string $sort = 'title') => [
  'handler' => 'default:node',
  'handler_settings' => [
    'target_bundles' => array_combine($bundles, $bundles),
    'sort' => ['field' => $sort, 'direction' => 'ASC'],
    'auto_create' => FALSE,
  ],
];

$plain = ['type' => 'string', 'label' => 'hidden'];
$autocomplete = ['type' => 'entity_reference_autocomplete', 'settings' => ['match_operator' => 'CONTAINS', 'size' => 60, 'placeholder' => '']];

// 0. Find the Staff content type and the Subjects vocabulary. ---------------

$pick = function (string $kind, string $needle, array $options, ?string $given) use (&$notes): ?string {
  if ($given !== NULL && $given !== '') {
    if (!isset($options[$given])) {
      throw new \RuntimeException("$kind '$given' not found. Available: " . implode(', ', array_keys($options)));
    }
    return $given;
  }
  $hits = array_filter($options, fn ($label, $id) => stripos((string) $id, $needle) !== FALSE || stripos((string) $label, $needle) !== FALSE, ARRAY_FILTER_USE_BOTH);
  if (count($hits) === 1) {
    return (string) array_key_first($hits);
  }
  $list = $hits ?: $options;
  $notes[] = sprintf(
    "%s: %s. Re-run with %s=<machine name>. Candidates: %s",
    $kind,
    $hits ? 'more than one match' : 'none found',
    $kind === 'Staff content type' ? 'staff' : 'vocab',
    implode(', ', array_map(fn ($id, $label) => "$id ($label)", array_keys($list), $list)),
  );
  return NULL;
};

$node_types = array_map(fn ($t) => $t->label(), NodeType::loadMultiple());
unset($node_types['resource'], $node_types[$bundle]);
$staff = $pick('Staff content type', 'staff', $node_types, $args['staff'] ?? NULL);
$vocabularies = array_map(fn ($v) => $v->label(), Vocabulary::loadMultiple());
$vocab = $pick('Subjects vocabulary', 'subject', $vocabularies, $args['vocab'] ?? NULL);
echo 'Staff content type: ' . ($staff ?? '(not set)') . PHP_EOL;
echo 'Subjects vocabulary: ' . ($vocab ?? '(not set)') . PHP_EOL;

// Remote video media type (oEmbed), for guide_video.
$video_types = [];
if (\Drupal::moduleHandler()->moduleExists('media')) {
  foreach (\Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple() as $media_type) {
    if ($media_type->getSource()->getPluginId() === 'oembed:video') {
      $video_types[$media_type->id()] = $media_type->id();
    }
  }
}

// 1. Resource content type. --------------------------------------------------

if (!NodeType::load('resource')) {
  NodeType::create([
    'type' => 'resource',
    'name' => 'Resource',
    'description' => 'A link, database, book, tool or file that guides list. Edit it once and every guide that lists it updates.',
    'new_revision' => TRUE,
    'preview_mode' => 0,
    'display_submitted' => FALSE,
  ])->save();
  echo 'Created content type resource' . PHP_EOL;
}

$resource_types = [
  'article' => 'Article',
  'book' => 'Book or e-book',
  'database' => 'Database',
  'guide' => 'Guide or handbook',
  'standard' => 'Standard or checklist',
  'tool' => 'Tool or software',
  'course' => 'Course, tutorial or webinar',
  'video' => 'Video',
  'pdf' => 'PDF',
  'word' => 'Word document',
  'download' => 'Other download',
  'search' => 'Catalog search',
  'person' => 'Person or service',
  'website' => 'Website',
];

$storage('node', 'field_resource_link', 'link');
$storage('node', 'field_resource_desc', 'string_long');
$storage('node', 'field_resource_citation', 'string', ['max_length' => 255]);
$storage('node', 'field_resource_type', 'list_string', ['allowed_values' => $resource_types]);
$storage('node', 'field_resource_labels', 'string', ['max_length' => 32], 3);
$storage('node', 'field_resource_proxy', 'boolean');

$field('node', 'resource', 'field_resource_link', [
  'label' => 'Link',
  'required' => TRUE,
  'description' => 'Paste the plain address. For LSU-licensed resources, tick "Requires LSU login" instead of pasting a libezp proxy link.',
  // 17 = internal and external links; 0 = no link text (the title is the text).
  'settings' => ['link_type' => 17, 'title' => 0],
]);
$field('node', 'resource', 'field_resource_desc', [
  'label' => 'Description',
  'description' => 'One or two sentences on what it is and when to use it.',
]);
$field('node', 'resource', 'field_resource_citation', [
  'label' => 'Citation or source',
  'description' => 'Short line under the title, e.g. "Grant & Booth, 2009" or "Cochrane".',
]);
$field('node', 'resource', 'field_resource_type', [
  'label' => 'Type',
  'required' => TRUE,
  'description' => 'Sets the icon.',
  'default_value' => [['value' => 'website']],
]);
$field('node', 'resource', 'field_resource_labels', [
  'label' => 'Labels',
  'description' => 'Up to three short tags, e.g. Free, Paid, Online, Example, 30 min.',
]);
$field('node', 'resource', 'field_resource_proxy', [
  'label' => 'Requires LSU login',
  'description' => 'Sends the link through the library proxy (libezp) and shows an "LSU login" label.',
  'settings' => ['on_label' => 'Yes', 'off_label' => 'No'],
  'default_value' => [['value' => 0]],
]);

$form('node', 'resource', [
  'title' => ['type' => 'string_textfield', 'weight' => 0],
  'field_resource_link' => ['type' => 'link_default', 'weight' => 1, 'settings' => ['placeholder_url' => 'https://']],
  'field_resource_proxy' => ['type' => 'boolean_checkbox', 'weight' => 2, 'settings' => ['display_label' => TRUE]],
  'field_resource_type' => ['type' => 'options_select', 'weight' => 3],
  'field_resource_citation' => ['type' => 'string_textfield', 'weight' => 4],
  'field_resource_desc' => ['type' => 'string_textarea', 'weight' => 5, 'settings' => ['rows' => 3]],
  'field_resource_labels' => ['type' => 'string_textfield', 'weight' => 6, 'settings' => ['size' => 32]],
]);

$view_mode('guide_item', 'Guide item');
$view_mode('guide_card', 'Guide card');
// node--resource--guide-item.html.twig prints the resource from variables
// built in palefire.theme, so this display shows no fields.
$view('node', 'resource', 'guide_item', []);
$view('node', 'resource', 'default', [
  'field_resource_link' => ['type' => 'link', 'label' => 'hidden', 'weight' => 0],
  'field_resource_citation' => ['type' => 'string', 'label' => 'hidden', 'weight' => 1],
  'field_resource_desc' => ['type' => 'basic_string', 'label' => 'hidden', 'weight' => 2],
]);

// 2. Staff: Guide card display. ---------------------------------------------

if ($staff) {
  $components = [];
  $weight = 0;
  $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $staff);
  $wanted = '/title|position|role|pronoun|office|location|room|phone|email|photo|image|headshot/i';
  foreach ($definitions as $name => $definition) {
    if (!str_starts_with($name, 'field_')) {
      continue;
    }
    $type = $definition->getType();
    $match = preg_match($wanted, $name) || preg_match($wanted, (string) $definition->getLabel());
    $component = match (TRUE) {
      $type === 'email' => ['type' => 'email_mailto', 'label' => 'inline'],
      $type === 'telephone' => ['type' => 'telephone_link', 'label' => 'inline'],
      $type === 'image' && $match => ['type' => 'image', 'label' => 'hidden', 'settings' => ['image_style' => 'thumbnail', 'image_link' => '', 'image_loading' => ['attribute' => 'lazy']]],
      in_array($type, ['string', 'list_string'], TRUE) && $match => ['type' => $type === 'string' ? 'string' : 'list_default', 'label' => preg_match('/title|position|role|pronoun/i', $name . $definition->getLabel()) ? 'hidden' : 'inline'],
      default => NULL,
    };
    if ($component) {
      $components[$name] = $component + ['weight' => $weight++];
    }
  }
  $view('node', $staff, 'guide_card', $components);
  echo 'Guide card fields on ' . $staff . ': ' . ($components ? implode(', ', array_keys($components)) : 'none matched, set them in Manage display > Guide card') . PHP_EOL;
}

// 3. Paragraph types. --------------------------------------------------------

$paragraph_type('guide_section', 'Guide section', 'A titled section of a guide page. Holds text, resource lists, accordions, librarian cards and videos.');
$paragraph_type('guide_text', 'Guide: Text', 'Formatted text with an optional heading. Tables, lists and callouts.');
$paragraph_type('resource_list', 'Guide: Resource list', 'A list of Resources (links, databases, books, tools).');
$paragraph_type('guide_accordion', 'Guide: Accordion', 'Collapsible items. Each one can hold text and Resources.');
$paragraph_type('accordion_item', 'Accordion item', 'One collapsible item in a Guide accordion.');
$paragraph_type('librarian_card', 'Guide: Librarian card', 'Contact card for a staff member.');
if ($video_types) {
  $paragraph_type('guide_video', 'Guide: Video', 'A YouTube or Vimeo video that loads when someone presses play.');
}
else {
  $notes[] = 'No remote video (oEmbed) media type found, so the Guide: Video block was skipped. Add one (Structure > Media types, source "Remote video") and re-run.';
}

$blocks = array_values(array_filter(['guide_text', 'resource_list', 'guide_accordion', $staff ? 'librarian_card' : NULL, $video_types ? 'guide_video' : NULL]));

// Shared storages.
$storage('paragraph', 'field_block_title', 'string', ['max_length' => 255]);
$storage('paragraph', 'field_block_body', 'text_long');
$storage('paragraph', 'field_block_intro', 'text_long');
$storage('paragraph', 'field_text_style', 'list_string', ['allowed_values' => ['default' => 'Plain', 'callout' => 'Callout (highlighted box)']]);
$storage('paragraph', 'field_resources', 'entity_reference', ['target_type' => 'node'], -1);
$storage('paragraph', 'field_guide_blocks', 'entity_reference_revisions', ['target_type' => 'paragraph'], -1);
$storage('paragraph', 'field_accordion_items', 'entity_reference_revisions', ['target_type' => 'paragraph'], -1);
$storage('paragraph', 'field_item_title', 'string', ['max_length' => 255]);
if ($staff) {
  $storage('paragraph', 'field_librarian', 'entity_reference', ['target_type' => 'node']);
}
if ($video_types) {
  $storage('paragraph', 'field_video', 'entity_reference', ['target_type' => 'media']);
}

$block_title = fn (string $help) => ['label' => 'Heading', 'description' => $help . ' Shows as a sub-link under the section in "On this page".'];

// guide_section: palefire_modular's shared section fields, reused from their
// existing storages. Snap and height are left off on purpose: long text
// sections don't scroll-snap well, and without the fields the module treats
// them as auto-height, non-snapping sections.
$shared = [
  'field_section_title' => ['label' => 'Section title', 'required' => TRUE, 'description' => 'The section heading and its label in "On this page".'],
  'field_section_anchor' => ['label' => 'Anchor (optional)', 'description' => 'Custom link fragment, e.g. "search-tips". Defaults to the title.'],
  'field_section_theme' => ['label' => 'Section theme'],
  'field_section_toc_hide' => ['label' => 'Hide from "On this page"'],
  'field_section_bg' => ['label' => 'Background image (optional)'],
];
$section_form = [];
$weight = 0;
foreach ($shared as $name => $values) {
  if (!FieldStorageConfig::loadByName('paragraph', $name)) {
    if ($name === 'field_section_title') {
      throw new \RuntimeException('paragraph.field_section_title not found. Set up palefire_modular\'s shared section fields first.');
    }
    continue;
  }
  $field('paragraph', 'guide_section', $name, $values);
  // Reuse the widget another section type already uses for this field.
  $component = NULL;
  foreach (\Drupal::service('entity_type.bundle.info')->getBundleInfo('paragraph') as $other => $info) {
    if ($other === 'guide_section' || !FieldConfig::loadByName('paragraph', $other, $name)) {
      continue;
    }
    $component = $displays->getFormDisplay('paragraph', $other, 'default')->getComponent($name);
    if ($component) {
      break;
    }
  }
  if (!$component) {
    $type = FieldStorageConfig::loadByName('paragraph', $name)->getType();
    $component = match (TRUE) {
      $type === 'boolean' => ['type' => 'boolean_checkbox', 'settings' => ['display_label' => TRUE]],
      str_starts_with($type, 'list_') => ['type' => 'options_select'],
      $type === 'image' => ['type' => 'image_image'],
      $type === 'entity_reference' => \Drupal::moduleHandler()->moduleExists('media_library') ? ['type' => 'media_library_widget'] : ['type' => 'entity_reference_autocomplete'],
      default => ['type' => 'string_textfield'],
    };
  }
  $section_form[$name] = ['weight' => $weight++] + $component;
}
$field('paragraph', 'guide_section', 'field_guide_blocks', [
  'label' => 'Content',
  'description' => 'Text, resource lists, accordions, librarian cards and videos, in order.',
  'settings' => $err($blocks),
]);
$section_form['field_guide_blocks'] = $paragraphs_widget(20, 'Block', 'Blocks', 'guide_text', 'modal');
$form('paragraph', 'guide_section', $section_form);
// palefire_modular prints the section title and background; the template
// (paragraph--guide-section.html.twig) prints the blocks.
$view('paragraph', 'guide_section', 'default', [
  'field_guide_blocks' => ['type' => 'entity_reference_revisions_entity_view', 'label' => 'hidden', 'weight' => 0, 'settings' => ['view_mode' => 'default']],
]);

// guide_text.
$field('paragraph', 'guide_text', 'field_block_title', $block_title('Optional.'));
$field('paragraph', 'guide_text', 'field_block_body', ['label' => 'Text', 'required' => TRUE]);
$field('paragraph', 'guide_text', 'field_text_style', ['label' => 'Style', 'default_value' => [['value' => 'default']]]);
$form('paragraph', 'guide_text', [
  'field_block_title' => ['type' => 'string_textfield', 'weight' => 0],
  'field_block_body' => ['type' => 'text_textarea', 'weight' => 1, 'settings' => ['rows' => 8]],
  'field_text_style' => ['type' => 'options_buttons', 'weight' => 2],
]);
$view('paragraph', 'guide_text', 'default', [
  'field_block_body' => ['type' => 'text_default', 'label' => 'hidden', 'weight' => 0],
]);

// resource_list.
$field('paragraph', 'resource_list', 'field_block_title', $block_title('Optional.'));
$field('paragraph', 'resource_list', 'field_block_intro', ['label' => 'Intro (optional)']);
$field('paragraph', 'resource_list', 'field_resources', [
  'label' => 'Resources',
  'required' => TRUE,
  'description' => 'Start typing a Resource title. Add new ones at Content > Add content > Resource.',
  'settings' => $node_ref(['resource']),
]);
$form('paragraph', 'resource_list', [
  'field_block_title' => ['type' => 'string_textfield', 'weight' => 0],
  'field_block_intro' => ['type' => 'text_textarea', 'weight' => 1, 'settings' => ['rows' => 2]],
  'field_resources' => $autocomplete + ['weight' => 2],
]);
$view('paragraph', 'resource_list', 'default', [
  'field_block_intro' => ['type' => 'text_default', 'label' => 'hidden', 'weight' => 0],
]);

// accordion_item.
$field('paragraph', 'accordion_item', 'field_item_title', ['label' => 'Title', 'required' => TRUE, 'description' => 'The line people click to open the item.']);
$field('paragraph', 'accordion_item', 'field_block_body', ['label' => 'Text (optional)']);
$field('paragraph', 'accordion_item', 'field_resources', [
  'label' => 'Resources (optional)',
  'settings' => $node_ref(['resource']),
]);
$form('paragraph', 'accordion_item', [
  'field_item_title' => ['type' => 'string_textfield', 'weight' => 0],
  'field_block_body' => ['type' => 'text_textarea', 'weight' => 1, 'settings' => ['rows' => 4]],
  'field_resources' => $autocomplete + ['weight' => 2],
]);
$view('paragraph', 'accordion_item', 'default', [
  'field_block_body' => ['type' => 'text_default', 'label' => 'hidden', 'weight' => 0],
]);

// guide_accordion.
$field('paragraph', 'guide_accordion', 'field_block_title', $block_title('Optional.'));
$field('paragraph', 'guide_accordion', 'field_block_intro', ['label' => 'Intro (optional)']);
$field('paragraph', 'guide_accordion', 'field_accordion_items', [
  'label' => 'Items',
  'required' => TRUE,
  'settings' => $err(['accordion_item']),
]);
$form('paragraph', 'guide_accordion', [
  'field_block_title' => ['type' => 'string_textfield', 'weight' => 0],
  'field_block_intro' => ['type' => 'text_textarea', 'weight' => 1, 'settings' => ['rows' => 2]],
  'field_accordion_items' => $paragraphs_widget(2, 'Item', 'Items', 'accordion_item'),
]);
$view('paragraph', 'guide_accordion', 'default', [
  'field_block_intro' => ['type' => 'text_default', 'label' => 'hidden', 'weight' => 0],
  'field_accordion_items' => ['type' => 'entity_reference_revisions_entity_view', 'label' => 'hidden', 'weight' => 1, 'settings' => ['view_mode' => 'default']],
]);

// librarian_card.
if ($staff) {
  $field('paragraph', 'librarian_card', 'field_block_title', $block_title('Optional, e.g. "Your librarian".'));
  $field('paragraph', 'librarian_card', 'field_librarian', [
    'label' => 'Staff member',
    'required' => TRUE,
    'settings' => $node_ref([$staff]),
  ]);
  $form('paragraph', 'librarian_card', [
    'field_block_title' => ['type' => 'string_textfield', 'weight' => 0],
    'field_librarian' => $autocomplete + ['weight' => 1],
  ]);
  $view('paragraph', 'librarian_card', 'default', []);
}

// guide_video.
if ($video_types) {
  $field('paragraph', 'guide_video', 'field_block_title', $block_title('Optional.'));
  $field('paragraph', 'guide_video', 'field_video', [
    'label' => 'Video',
    'required' => TRUE,
    'settings' => [
      'handler' => 'default:media',
      'handler_settings' => ['target_bundles' => $video_types, 'auto_create' => FALSE],
    ],
  ]);
  $media_widget = \Drupal::moduleHandler()->moduleExists('media_library')
    ? ['type' => 'media_library_widget', 'settings' => ['media_types' => array_values($video_types)]]
    : $autocomplete;
  $form('paragraph', 'guide_video', [
    'field_block_title' => ['type' => 'string_textfield', 'weight' => 0],
    'field_video' => $media_widget + ['weight' => 1],
  ]);
  $view('paragraph', 'guide_video', 'default', []);
}

// 4. Modular page: guide fields and the new section type. -------------------

if ($staff) {
  $storage('node', 'field_guide_owner', 'entity_reference', ['target_type' => 'node']);
  $field('node', $bundle, 'field_guide_owner', [
    'label' => 'Guide owner',
    'description' => 'Shown as "Guide by" under the title on Guide pages.',
    'settings' => $node_ref([$staff]),
  ]);
}
if ($vocab) {
  $storage('node', 'field_guide_subjects', 'entity_reference', ['target_type' => 'taxonomy_term'], -1);
  $field('node', $bundle, 'field_guide_subjects', [
    'label' => 'Subjects',
    'settings' => [
      'handler' => 'default:taxonomy_term',
      'handler_settings' => ['target_bundles' => [$vocab => $vocab], 'auto_create' => FALSE, 'sort' => ['field' => 'name', 'direction' => 'asc']],
    ],
  ]);
}
$storage('node', 'field_guide_source', 'link');
$field('node', $bundle, 'field_guide_source', [
  'label' => 'Original LibGuide (optional)',
  'description' => 'The LibGuide this page replaces. Not shown to visitors; useful for redirects.',
  'settings' => ['link_type' => 16, 'title' => 0],
]);
$form('node', $bundle, array_filter([
  'field_guide_owner' => $staff ? $autocomplete + ['weight' => 3] : NULL,
  'field_guide_subjects' => $vocab ? ['type' => 'entity_reference_autocomplete_tags', 'weight' => 4, 'settings' => ['match_operator' => 'CONTAINS', 'size' => 60, 'placeholder' => '']] : NULL,
  'field_guide_source' => ['type' => 'link_default', 'weight' => 5, 'settings' => ['placeholder_url' => 'https://guides.lib.lsu.edu/']],
]));
// The theme prints owner and subjects in the page head (guide mode only).
foreach (['default', 'full'] as $mode) {
  if ($mode === 'full' && !array_key_exists('full', $displays->getViewModeOptionsByBundle('node', $bundle))) {
    continue;
  }
  $node_view = $displays->getViewDisplay('node', $bundle, $mode);
  foreach (['field_guide_owner', 'field_guide_subjects', 'field_guide_source'] as $name) {
    $node_view->removeComponent($name);
  }
  $node_view->save();
}

// Allow guide_section in field_scroll_section.
$sections = FieldConfig::loadByName('node', $bundle, 'field_scroll_section');
if ($sections) {
  $settings = $sections->getSetting('handler_settings') ?: [];
  $targets = $settings['target_bundles'] ?? [];
  $negate = !empty($settings['negate']);
  $changed = FALSE;
  if ($negate && isset($targets['guide_section'])) {
    unset($targets['guide_section']);
    $changed = TRUE;
  }
  elseif (!$negate && $targets && !isset($targets['guide_section'])) {
    $targets['guide_section'] = 'guide_section';
    $settings['target_bundles_drag_drop']['guide_section'] = ['enabled' => TRUE, 'weight' => count($targets)];
    $changed = TRUE;
  }
  if ($changed) {
    $settings['target_bundles'] = $targets;
    $sections->setSetting('handler_settings', $settings)->save();
    echo 'Allowed guide_section in field_scroll_section' . PHP_EOL;
  }
}

// field_modular_type needs the "guide" value (palefire_modular ships it).
$mode_storage = FieldStorageConfig::loadByName('node', 'field_modular_type');
if ($mode_storage && !isset(($mode_storage->getSetting('allowed_values') ?? [])['guide'])) {
  $notes[] = 'node.field_modular_type has no "guide" value. Add it (Modular page > Manage fields) so pages can use guide mode.';
}

foreach ($notes as $note) {
  echo 'Note: ' . $note . PHP_EOL;
}
echo 'Guide setup complete. Run: drush cr' . PHP_EOL;
