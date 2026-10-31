<?php

/**
 * @file
 * Staff in Palefire: the directory, profiles and Who to ask.
 *
 * Run from the Drupal root on production:
 *   drush php:script themes/custom/palefire/scripts/palefire_staff_setup.php
 *   drush cr
 *
 * Optional arguments after "--":
 *   staff=<type>   The Staff content type (default: staff).
 *
 * What it does:
 * - Creates the View palefire_staff (Content, published Staff) with two
 *   pages, both under /v2 so they get this theme and leave the live /staff
 *   and /<name> pages alone:
 *     /v2/staff     directory  views-view--palefire-staff--directory.html.twig
 *     /v2/staff/%   profile    views-view--palefire-staff--profile.html.twig
 *   The View only provides the routes: the templates print what
 *   palefire_preprocess_views_view() reads from the Staff nodes
 *   (src/StaffDirectory.php). The profile's slug is the person's alias
 *   ("/lilibedoya" gives /v2/staff/lilibedoya), else the node ID; anything
 *   else is a 404.
 * - Creates two paragraph types for Who to ask:
 *     staff_contacts  "Who to ask", a section: the shared section fields
 *                     (from bento_search), Eyebrow, Lede and Link (from
 *                     tile_showcase) and Contacts (field_staff_contacts).
 *     staff_contact   One row: Topic (field_contact_topic) and Person
 *                     (field_librarian, the Librarian card's field).
 *   staff_contacts is allowed wherever bento_search is, such as the Modular
 *   page's sections. Template: paragraph--staff-contacts.html.twig.
 * - Image styles: pf_staff_thumb (192 x 192, the directory's 48px photos)
 *   and pf_staff_portrait (640 x 800, the profile's), scaled and cropped from
 *   the top centre (faces sit high in a headshot), in WebP when the toolkit
 *   can write it.
 *
 * Safe to re-run: anything that already exists is left as it is. Fill the
 * section on /v2/special with palefire_staff_contacts_section.php.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\views\Entity\View;

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
$staff = $args['staff'] ?? 'staff';
if (!NodeType::load($staff)) {
  echo "Content type $staff not found. Re-run with -- staff=<type>.\n";
  return;
}
foreach (['bento_search', 'tile_showcase'] as $needed) {
  if (!ParagraphsType::load($needed)) {
    echo "Paragraph type $needed not found. Run palefire_tile_showcase_setup.php first.\n";
    return;
  }
}

// 1. The View. ----------------------------------------------------------------
$view_id = 'palefire_staff';
foreach (['/v2/staff'] as $taken) {
  $path = \Drupal::service('path_alias.manager')->getPathByAlias($taken);
  if ($path !== $taken) {
    echo "Note: $taken is already an alias of $path, which wins over the View's page. Change that alias first.\n";
  }
}
if (!View::load($view_id)) {
  $node_field = static fn (string $field, string $plugin, array $more = []): array => [
    'id' => $field,
    'table' => 'node_field_data',
    'field' => $field,
    'entity_type' => 'node',
    'entity_field' => $field,
    'plugin_id' => $plugin,
  ] + $more;
  View::create([
    'id' => $view_id,
    'label' => 'Staff (Palefire)',
    'module' => 'views',
    'description' => 'Routes for /v2/staff and /v2/staff/<slug>. The Palefire theme prints the pages (src/StaffDirectory.php); edit their look there, not here.',
    'tag' => 'palefire',
    'base_table' => 'node_field_data',
    'base_field' => 'nid',
    'display' => [
      'default' => [
        'id' => 'default',
        'display_title' => 'Default',
        'display_plugin' => 'default',
        'position' => 0,
        'display_options' => [
          'title' => 'Staff directory',
          'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
          'cache' => ['type' => 'tag', 'options' => []],
          'query' => ['type' => 'views_query', 'options' => []],
          'exposed_form' => ['type' => 'basic', 'options' => []],
          // The templates read the Staff nodes themselves: one row is enough.
          'pager' => ['type' => 'some', 'options' => ['items_per_page' => 1, 'offset' => 0]],
          'style' => ['type' => 'default', 'options' => []],
          'row' => ['type' => 'fields', 'options' => []],
          'fields' => [
            'nid' => $node_field('nid', 'field', ['label' => '', 'exclude' => TRUE]),
          ],
          'filters' => [
            'status' => $node_field('status', 'boolean', ['value' => '1', 'group' => 1, 'expose' => ['operator' => '']]),
            'type' => $node_field('type', 'bundle', ['value' => [$staff => $staff], 'group' => 1]),
          ],
          'sorts' => [
            'title' => $node_field('title', 'standard', ['order' => 'ASC']),
          ],
          'display_extenders' => [],
        ],
      ],
      'directory' => [
        'id' => 'directory',
        'display_title' => 'Directory',
        'display_plugin' => 'page',
        'position' => 1,
        'display_options' => [
          'path' => 'v2/staff',
          'display_extenders' => [],
        ],
      ],
      'profile' => [
        'id' => 'profile',
        'display_title' => 'Profile',
        'display_plugin' => 'page',
        'position' => 2,
        'display_options' => [
          'path' => 'v2/staff/%',
          'title' => 'Staff',
          'defaults' => ['title' => FALSE, 'arguments' => FALSE],
          // Global: Null takes the slug without filtering; the theme looks
          // the person up and answers 404 when there is none.
          'arguments' => [
            'null' => [
              'id' => 'null',
              'table' => 'views',
              'field' => 'null',
              'plugin_id' => 'null',
              'default_action' => 'not found',
              'must_not_be' => FALSE,
            ],
          ],
          'display_extenders' => [],
        ],
      ],
    ],
  ])->save();
  echo "Created view $view_id: /v2/staff and /v2/staff/%\n";
}

// 2. Paragraph types. -----------------------------------------------------------
$types = [
  'staff_contact' => ['Staff: Contact', 'One row of Who to ask: a topic and the person to write to.'],
  'staff_contacts' => ['Who to ask', 'Topics, each with the person to write to, and a link to the department\'s staff.'],
];
foreach ($types as $id => [$label, $description]) {
  if (!ParagraphsType::load($id)) {
    ParagraphsType::create(['id' => $id, 'label' => $label, 'description' => $description])->save();
    echo "Created paragraph type $id\n";
  }
}

$repo = \Drupal::service('entity_display.repository');

// Copies a field from another bundle: its settings, form widget and display.
$copy = static function (string $name, string $from, string $to, bool $show) use ($repo): void {
  $source = FieldConfig::loadByName('paragraph', $from, $name);
  if (!$source) {
    echo "Skipped $name: $from doesn't have it.\n";
    return;
  }
  if (!FieldConfig::loadByName('paragraph', $to, $name)) {
    FieldConfig::create([
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

// Who to ask: the section fields as bento_search shows them, then Eyebrow,
// Lede and Link, which the scroll-section field template prints.
foreach (['field_section_title', 'field_section_anchor', 'field_section_snap', 'field_section_height', 'field_section_theme', 'field_section_toc_hide', 'field_section_bg'] as $name) {
  $copy($name, 'bento_search', 'staff_contacts', TRUE);
}
foreach (['field_tile_eyebrow', 'field_tile_lede', 'field_tile_link'] as $name) {
  $copy($name, 'tile_showcase', 'staff_contacts', FALSE);
}

// A row's person: the Librarian card's field when guides are set up.
if (FieldConfig::loadByName('paragraph', 'librarian_card', 'field_librarian')) {
  $copy('field_librarian', 'librarian_card', 'staff_contact', FALSE);
}
else {
  if (!FieldStorageConfig::loadByName('paragraph', 'field_librarian')) {
    FieldStorageConfig::create([
      'field_name' => 'field_librarian',
      'entity_type' => 'paragraph',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
    ])->save();
    echo "Created storage paragraph.field_librarian\n";
  }
  if (!FieldConfig::loadByName('paragraph', 'staff_contact', 'field_librarian')) {
    FieldConfig::create([
      'field_name' => 'field_librarian',
      'entity_type' => 'paragraph',
      'bundle' => 'staff_contact',
      'label' => 'Person',
      'required' => TRUE,
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => ['target_bundles' => [$staff => $staff], 'sort' => ['field' => 'title', 'direction' => 'ASC'], 'auto_create' => FALSE],
      ],
    ])->save();
    echo "Added field_librarian to paragraph.staff_contact\n";
  }
}

if (!FieldStorageConfig::loadByName('paragraph', 'field_contact_topic')) {
  FieldStorageConfig::create([
    'field_name' => 'field_contact_topic',
    'entity_type' => 'paragraph',
    'type' => 'string',
    'settings' => ['max_length' => 120],
  ])->save();
  echo "Created storage paragraph.field_contact_topic\n";
}
if (!FieldConfig::loadByName('paragraph', 'staff_contact', 'field_contact_topic')) {
  FieldConfig::create([
    'field_name' => 'field_contact_topic',
    'entity_type' => 'paragraph',
    'bundle' => 'staff_contact',
    'label' => 'Topic',
    'description' => 'What to write to this person about, e.g. "Rare books and instruction". A person can have more than one row.',
    'required' => TRUE,
  ])->save();
  echo "Added field_contact_topic to paragraph.staff_contact\n";
}

if (!FieldStorageConfig::loadByName('paragraph', 'field_staff_contacts')) {
  FieldStorageConfig::create([
    'field_name' => 'field_staff_contacts',
    'entity_type' => 'paragraph',
    'type' => 'entity_reference_revisions',
    'cardinality' => -1,
    'settings' => ['target_type' => 'paragraph'],
  ])->save();
  echo "Created storage paragraph.field_staff_contacts\n";
}
if (!FieldConfig::loadByName('paragraph', 'staff_contacts', 'field_staff_contacts')) {
  FieldConfig::create([
    'field_name' => 'field_staff_contacts',
    'entity_type' => 'paragraph',
    'bundle' => 'staff_contacts',
    'label' => 'Contacts',
    'description' => 'Shown in this order. Each row shows the person\'s name (linked to their profile), job title and email, from their Staff page.',
    'required' => TRUE,
    'settings' => [
      'handler' => 'default:paragraph',
      'handler_settings' => [
        'target_bundles' => ['staff_contact' => 'staff_contact'],
        'negate' => 0,
        'target_bundles_drag_drop' => ['staff_contact' => ['enabled' => TRUE, 'weight' => 0]],
      ],
    ],
  ])->save();
  echo "Added field_staff_contacts to paragraph.staff_contacts\n";
}

// Widgets: a row is two fields, so rows stay open; the section's own fields
// keep what was copied.
$form = $repo->getFormDisplay('paragraph', 'staff_contact', 'default');
$form->setComponent('field_contact_topic', ['type' => 'string_textfield', 'weight' => 0, 'settings' => ['size' => 60, 'placeholder' => 'Rare books and instruction']]);
$form->setComponent('field_librarian', ['type' => 'entity_reference_autocomplete', 'weight' => 1, 'settings' => ['match_operator' => 'CONTAINS', 'match_limit' => 10, 'size' => 60, 'placeholder' => 'Start typing a name']]);
$form->save();
$form = $repo->getFormDisplay('paragraph', 'staff_contacts', 'default');
if (!$form->getComponent('field_staff_contacts')) {
  $weight = (int) max(array_column($form->getComponents(), 'weight') ?: [0]);
  $form->setComponent('field_staff_contacts', [
    'type' => 'paragraphs',
    'weight' => $weight + 1,
    'settings' => [
      'title' => 'Contact',
      'title_plural' => 'Contacts',
      'edit_mode' => 'open',
      'add_mode' => 'button',
      'form_display_mode' => 'default',
      'default_paragraph_type' => 'staff_contact',
    ],
  ])->save();
}
// The template prints the rows itself.
$repo->getViewDisplay('paragraph', 'staff_contacts', 'default')->removeComponent('field_staff_contacts')->save();
$repo->getViewDisplay('paragraph', 'staff_contact', 'default')->removeComponent('field_contact_topic')->removeComponent('field_librarian')->save();

// 3. Allow staff_contacts wherever bento_search is allowed. -------------------
foreach (FieldConfig::loadMultiple() as $config) {
  if (!in_array($config->getType(), ['entity_reference_revisions', 'entity_reference'], TRUE)
    || $config->getSetting('target_type') !== 'paragraph') {
    continue;
  }
  $handler = $config->getSetting('handler_settings') ?: [];
  $bundles = $handler['target_bundles'] ?? NULL;
  // Paragraphs' "exclude" mode lists bundles that are NOT allowed; any
  // other bundle, including the new one, is already allowed.
  if (($handler['negate'] ?? FALSE) || !is_array($bundles) || !isset($bundles['bento_search']) || isset($bundles['staff_contacts'])) {
    continue;
  }
  $handler['target_bundles']['staff_contacts'] = 'staff_contacts';
  if (isset($handler['target_bundles_drag_drop'])) {
    $handler['target_bundles_drag_drop']['staff_contacts'] = ['enabled' => TRUE, 'weight' => count($handler['target_bundles_drag_drop'])];
  }
  $config->setSetting('handler_settings', $handler)->save();
  echo "Allowed staff_contacts in " . $config->id() . "\n";
}

// 4. Image styles. --------------------------------------------------------------
$toolkit = \Drupal::service('image.toolkit.manager')->getDefaultToolkit();
$webp = $toolkit && in_array('webp', $toolkit->getSupportedExtensions(), TRUE);
$styles = [
  'pf_staff_thumb' => ['Staff directory photo 192', 192, 192],
  'pf_staff_portrait' => ['Staff profile portrait 640', 640, 800],
];
foreach ($styles as $id => [$label, $width, $height]) {
  if (ImageStyle::load($id)) {
    continue;
  }
  $style = ImageStyle::create(['name' => $id, 'label' => $label]);
  $style->addImageEffect([
    'id' => 'image_scale_and_crop',
    'weight' => 1,
    'data' => ['width' => $width, 'height' => $height, 'anchor' => 'center-top'],
  ]);
  if ($webp) {
    $style->addImageEffect(['id' => 'image_convert', 'weight' => 2, 'data' => ['extension' => 'webp']]);
  }
  $style->save();
  echo "Created image style $id\n";
}

echo "Staff setup complete. Run: drush cr\n";
