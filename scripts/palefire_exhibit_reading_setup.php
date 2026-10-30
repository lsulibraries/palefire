<?php

/**
 * @file
 * Exhibition readings: a book or article in a reading list, typeset as a
 * cover (paragraph type exhibit_reading).
 *
 * Run palefire_exhibit_setup.php first (exhibit_gallery), and add the Link
 * field field_figure_link to Exhibition: Image first: a reading re-uses its
 * storage. Config only: it never creates or changes content.
 *
 * Run from the theme folder on production:
 *   drush php:script scripts/palefire_exhibit_reading_setup.php
 *   drush cr
 *
 * Adds:
 * - Paragraph type exhibit_reading (Exhibition: Reading) with
 *   field_reading_kind (Book or Article, required, default Book),
 *   field_reading_author, field_reading_title (required),
 *   field_reading_source (publisher or journal), field_reading_details
 *   (year, or volume, issue and pages) and field_figure_link.
 * - exhibit_reading in Exhibition: Image group's allowed types
 *   (field_gallery_items), after Image. The group's add button then reads
 *   "Add Item" instead of "Add Image", unless it was renamed in the UI.
 *
 * Readings show nothing until the theme has their template
 * (paragraph--exhibit-reading.html.twig): add them to pages after that.
 *
 * The template prints the fields itself, so the view display is empty.
 * Safe to re-run: existing types, fields and display settings are kept.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\link\LinkItemInterface;
use Drupal\paragraphs\Entity\ParagraphsType;

if (PHP_SAPI !== 'cli') {
  return;
}

if (!ParagraphsType::load('exhibit_gallery')) {
  throw new \RuntimeException("'exhibit_gallery' not found. Run palefire_exhibit_setup.php first.");
}
$link_storage = FieldStorageConfig::loadByName('paragraph', 'field_figure_link');
if (!$link_storage || $link_storage->getType() !== 'link') {
  throw new \RuntimeException('paragraph.field_figure_link (Link) not found. Add it to Exhibition: Image in Manage fields first.');
}

$bundle = 'exhibit_reading';

// Helpers (as in palefire_exhibit_setup.php). --------------------------------

$storage = function (string $name, string $type, array $settings = []): void {
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
    'cardinality' => 1,
  ])->save();
  echo "Created storage paragraph.$name" . PHP_EOL;
};

$field = function (string $name, array $values) use ($bundle): void {
  if (!FieldConfig::loadByName('paragraph', $bundle, $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => $bundle,
    ] + $values)->save();
    echo "Created field paragraph.$bundle.$name" . PHP_EOL;
  }
};

// 1. Paragraph type. ---------------------------------------------------------

if (!ParagraphsType::load($bundle)) {
  ParagraphsType::create([
    'id' => $bundle,
    'label' => 'Exhibition: Reading',
    'description' => 'A book or article in a reading list, shown as a typeset cover. Add it to an Image group.',
  ])->save();
  echo "Created paragraph type $bundle" . PHP_EOL;
}

// 2. Fields. -----------------------------------------------------------------

$storage('field_reading_kind', 'list_string', ['allowed_values' => [
  'book' => 'Book',
  'article' => 'Article',
]]);
$storage('field_reading_author', 'string', ['max_length' => 255]);
$storage('field_reading_title', 'string', ['max_length' => 255]);
$storage('field_reading_source', 'string', ['max_length' => 255]);
$storage('field_reading_details', 'string', ['max_length' => 255]);

$field('field_reading_kind', [
  'label' => 'Kind',
  'required' => TRUE,
  'default_value' => [['value' => 'book']],
  'description' => 'Books are set as a coloured cover; articles as a journal offprint.',
]);
$field('field_reading_author', [
  'label' => 'Author (optional)',
  'description' => 'As in the citation, surname first: <em>Forkner, Ben, ed.</em>',
]);
$field('field_reading_title', [
  'label' => 'Title',
  'required' => TRUE,
  'description' => 'Without quotes or italics: the theme adds them.',
]);
$field('field_reading_source', [
  'label' => 'Publisher or journal (optional)',
  'description' => 'A book\'s publisher (<em>LSU Press</em>) or an article\'s journal (<em>White House History</em>).',
]);
$field('field_reading_details', [
  'label' => 'Details (optional)',
  'description' => 'A book\'s year (<em>2018</em>), or an article\'s volume, issue, year and pages (<em>34 (Fall 2013): 44–53</em>).',
]);
$field('field_figure_link', [
  'label' => 'Link (optional)',
  'description' => 'The catalog record or the article. Without one, the reading shows with no arrow and isn\'t a link.',
  'settings' => [
    'link_type' => LinkItemInterface::LINK_GENERIC,
    'title' => DRUPAL_DISABLED,
  ],
]);

// 3. Displays. ---------------------------------------------------------------

/** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $displays */
$displays = \Drupal::service('entity_display.repository');

// Form widgets that aren't set yet, so later edits in Field UI stand.
$form = $displays->getFormDisplay('paragraph', $bundle, 'default');
$widgets = [
  'field_reading_kind' => ['type' => 'options_buttons', 'weight' => 0],
  'field_reading_author' => ['type' => 'string_textfield', 'weight' => 1],
  'field_reading_title' => ['type' => 'string_textfield', 'weight' => 2],
  'field_reading_source' => ['type' => 'string_textfield', 'weight' => 3],
  'field_reading_details' => ['type' => 'string_textfield', 'weight' => 4],
  'field_figure_link' => ['type' => 'link_default', 'weight' => 5, 'settings' => ['placeholder_url' => 'https://lsu.ent.sirsi.net/…']],
];
foreach ($widgets as $name => $widget) {
  if (!$form->getComponent($name)) {
    $form->setComponent($name, $widget);
  }
}
$form->save();

// View display: only when new, and empty (the template prints the fields).
$view = $displays->getViewDisplay('paragraph', $bundle, 'default');
if ($view->isNew()) {
  foreach (array_keys($view->getComponents()) as $name) {
    $view->removeComponent($name);
  }
  $view->setStatus(TRUE)->save();
  echo "Set up paragraph.$bundle.default display" . PHP_EOL;
}

// 4. Allow readings in the Image group. --------------------------------------

$items = FieldConfig::loadByName('paragraph', 'exhibit_gallery', 'field_gallery_items');
if ($items) {
  $settings = $items->getSetting('handler_settings') ?: [];
  $targets = $settings['target_bundles'] ?? [];
  $negate = !empty($settings['negate']);
  $changed = FALSE;
  if ($negate && isset($targets[$bundle])) {
    unset($targets[$bundle]);
    $changed = TRUE;
  }
  elseif (!$negate && $targets && !isset($targets[$bundle])) {
    $targets[$bundle] = $bundle;
    $settings['target_bundles_drag_drop'][$bundle] = ['enabled' => TRUE, 'weight' => count($targets)];
    $changed = TRUE;
  }
  if ($changed) {
    $settings['target_bundles'] = $targets;
    $items->setSetting('handler_settings', $settings)->save();
    echo "Allowed $bundle in exhibit_gallery.field_gallery_items" . PHP_EOL;
  }

  // "Add Image" would hide that a group can hold readings.
  $group_form = $displays->getFormDisplay('paragraph', 'exhibit_gallery', 'default');
  $widget = $group_form->getComponent('field_gallery_items');
  if ($widget && ($widget['settings']['title'] ?? '') === 'Image' && ($widget['settings']['title_plural'] ?? '') === 'Images') {
    $widget['settings']['title'] = 'Item';
    $widget['settings']['title_plural'] = 'Items';
    $group_form->setComponent('field_gallery_items', $widget)->save();
    echo 'Image group: the add button now reads "Add Item"' . PHP_EOL;
  }
}
else {
  echo 'Note: exhibit_gallery.field_gallery_items not found; add Exhibition: Reading to an Image group by hand.' . PHP_EOL;
}

echo 'Reading setup complete. Run: drush cr' . PHP_EOL;
