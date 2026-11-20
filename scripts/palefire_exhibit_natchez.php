<?php

/**
 * @file
 * Exhibition page: "New from Old Natchez" (LSU Libraries Special
 * Collections, 2002; shown at the Historic Natchez Foundation, with an
 * online version later moved to the Showcase pages /natchez/introduction
 * and nodes 290–298) as an unpublished Modular page in exhibition mode.
 * Run palefire_exhibit_setup.php first.
 *
 * Copy the "natchez-masters" folder (77 JPEGs, up to 2,560px) to the
 * server first, then:
 *
 *   drush php:script scripts/palefire_exhibit_natchez.php -- images=$HOME/natchez-masters
 *   drush php:script scripts/palefire_exhibit_natchez.php -- images=$HOME/natchez-masters apply=1
 *   drush cr
 *
 * Arguments after "--":
 *   images=<folder>       Required. The JPEG masters.
 *   apply=1               Save. Without it, a dry run: it checks the
 *                         fields and files and says what it would build.
 *   format=<text format>  Text format for the text (default: WYSIWYG).
 *   path=<alias>          URL alias (default /v2/special/exhibitions/natchez).
 *
 * The text, in data/natchez.json, is word for word from the old pages
 * (Tara Zachary’s 2002 texts), corrected where docs/special-exhibition-
 * corrections/natchez.md lists. Ten galleries, every one a wall: the
 * Introduction, the eight collections, and About the Exhibition (the
 * collections at LSU, the credits and the bibliography). Each collection
 * opens with text plates on the family and the papers (Schwartz also on
 * Natchez under occupation), then one entry per item: its pages, turned in
 * the object view, its note and, where the old site had one, its
 * transcription.
 *
 * Images: the 2002 exhibition’s own 300 dpi TIFFs (R:\SpecialCollections\
 * Exhibitions\Completed Exhibitions\EXHIB_Natchez Exhibit Images - 2002),
 * the Louisiana Digital Library’s masters for two McMurran letters, and
 * the old web images for the three McMurran letters found in neither.
 * Each is imported once as an Image media item (named "Natchez: <file
 * name>", reused on a re-run) under public://exhibitions/natchez.
 *
 * Creates one Modular page, UNPUBLISHED. It stops if a Modular page with
 * the same title exists.
 */

declare(strict_types=1);

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;

if (PHP_SAPI !== 'cli') {
  return;
}

$args = [];
foreach ((isset($extra) && is_array($extra)) ? $extra : [] as $arg) {
  if (str_contains((string) $arg, '=')) {
    [$k, $v] = explode('=', (string) $arg, 2);
    $args[trim($k)] = trim($v, " \t\"'");
  }
}
$apply = ($args['apply'] ?? '') === '1';

foreach (['exhibit_page', 'exhibit_entry', 'exhibit_figure', 'exhibit_gallery', 'guide_text'] as $id) {
  if (!ParagraphsType::load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_exhibit_setup.php first.");
  }
}

$images_dir = rtrim($args['images'] ?? '', '/\\');
if ($images_dir === '' || !is_dir($images_dir)) {
  throw new \RuntimeException('Pass images=<folder>: the natchez-masters folder copied to the server.');
}

$data_file = __DIR__ . '/data/natchez.json';
$data = json_decode((string) file_get_contents($data_file), TRUE);
if (!is_array($data) || count($data['pages'] ?? []) !== 10) {
  throw new \RuntimeException("Couldn't read the 10 galleries from $data_file.");
}

$title = $data['title'];
$found = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', $title)
  ->range(0, 1)->execute();
if ($found) {
  throw new \RuntimeException("A page titled '$title' already exists (node " . reset($found) . '). Delete it first to rebuild it.');
}

// Text uses the WYSIWYG format, never full_html.
$formats = filter_formats();
$format = $args['format'] ?? NULL;
foreach ($format === NULL ? $formats : [] as $id => $candidate) {
  if ($id === 'wysiwyg' || strcasecmp((string) $candidate->label(), 'WYSIWYG') === 0) {
    $format = $id;
    break;
  }
}
if ($format === NULL || !isset($formats[$format])) {
  throw new \RuntimeException('No WYSIWYG text format found. Pass format=<machine name>.');
}
echo "Text format: $format" . PHP_EOL;

// The Image media type and its source field.
$media_type = NULL;
foreach (\Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple() as $candidate) {
  if ($candidate->getSource()->getPluginId() === 'image' && ($media_type === NULL || $candidate->id() === 'image')) {
    $media_type = $candidate;
  }
}
if (!$media_type) {
  throw new \RuntimeException('No Image media type found.');
}
$source_field = $media_type->getSource()->getConfiguration()['source_field'];

$skipped = [];
$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];

// Every picture the data names: a lead, a figure or a page of a group.
$files = [$data['cover']];
$collect = function (array $b) use (&$collect, &$files): void {
  if (!empty($b['image'])) {
    $files[] = $b['image'];
  }
  foreach ($b['figures'] ?? [] as $f) {
    $files[] = $f['image'];
  }
  foreach ($b['blocks'] ?? [] as $child) {
    $collect($child);
  }
};
foreach ($data['pages'] as $page) {
  $files[] = $page['lead'];
  $collect($page);
}
$files = array_values(array_unique($files));
$missing = array_values(array_filter($files, fn ($f) => !is_file("$images_dir/$f")));

if (!$apply) {
  $entries = 0;
  foreach ($data['pages'] as $page) {
    $entries += count($page['blocks']);
    echo 'Gallery: ' . $page['title'] . ' (' . count($page['blocks']) . ' entries, lead ' . $page['lead'] . ')' . PHP_EOL;
  }
  foreach (['field_entry_summary' => 'exhibit_entry', 'field_exhibit_display' => 'exhibit_page', 'field_exhibit_lead_caption' => 'exhibit_page', 'field_exhibit_lead_whole' => 'exhibit_page'] as $field => $bundle) {
    if (!FieldConfig::loadByName('paragraph', $bundle, $field)) {
      echo "Missing field: $field on $bundle. Run palefire_exhibit_setup.php first." . PHP_EOL;
    }
  }
  echo 'Pictures: ' . count($files) . ', missing from ' . $images_dir . ': ' . count($missing) . PHP_EOL;
  foreach ($missing as $file) {
    echo "  Missing: $file" . PHP_EOL;
  }
  echo "Dry run: would create the unpublished page '$title' with " . count($data['pages']) . " galleries and $entries entries. Nothing saved. Add apply=1 to save." . PHP_EOL;
  return;
}

// Images. --------------------------------------------------------------------

/** @var \Drupal\Core\File\FileSystemInterface $fs */
$fs = \Drupal::service('file_system');
$destination = 'public://exhibitions/natchez';
$fs->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
$media_ids = [];

// One media item per file, reused when it exists ("Natchez: <file>").
$media = function (?string $file) use (&$media_ids, $images_dir, $fs, $destination, $media_type, $source_field, $data, &$skipped): ?int {
  if (!$file) {
    return NULL;
  }
  if (array_key_exists($file, $media_ids)) {
    return $media_ids[$file];
  }
  $name = 'Natchez: ' . pathinfo($file, PATHINFO_FILENAME);
  $existing = \Drupal::entityQuery('media')->accessCheck(FALSE)
    ->condition('bundle', $media_type->id())->condition('name', $name)->range(0, 1)->execute();
  if ($existing) {
    return $media_ids[$file] = (int) reset($existing);
  }
  $path = $images_dir . '/' . $file;
  if (!is_file($path)) {
    $skipped[] = "Image not found: $path";
    return $media_ids[$file] = NULL;
  }
  $uri = $fs->copy($path, "$destination/$file", FileExists::Rename);
  $item = File::create(['uri' => $uri, 'status' => 1]);
  $item->save();
  [$width, $height] = getimagesize($path) ?: [NULL, NULL];
  $entity = Media::create([
    'bundle' => $media_type->id(),
    'name' => $name,
    'status' => 1,
    $source_field => [
      'target_id' => $item->id(),
      'alt' => $data['alt'][$file] ?? '',
      'width' => $width,
      'height' => $height,
    ],
  ]);
  $entity->save();
  echo "Imported $file" . PHP_EOL;
  return $media_ids[$file] = (int) $entity->id();
};

// Blocks. --------------------------------------------------------------------

$ref = fn (?int $id) => $id ? ['target_id' => $id] : NULL;
$keep = fn (array $values) => array_filter($values, fn ($v) => $v !== NULL && $v !== '');

$figure = function (array $b, string $layout) use ($media, $ref, $keep, $html, &$skipped): Paragraph {
  $id = $media($b['image']);
  if (!$id) {
    $skipped[] = 'No image for "' . $b['image'] . '": add it in the editor.';
  }
  return Paragraph::create($keep([
    'type' => 'exhibit_figure',
    'field_figure_image' => $ref($id),
    'field_figure_caption' => ($b['caption'] ?? '') !== '' ? $html($b['caption']) : NULL,
    'field_figure_layout' => $layout,
  ]));
};

$has_summary = (bool) FieldConfig::loadByName('paragraph', 'exhibit_entry', 'field_entry_summary');
$block = function (array $b) use (&$block, $figure, $keep, $html, $has_summary): ?Paragraph {
  switch ($b['type']) {
    case 'text':
      return Paragraph::create([
        'type' => 'guide_text',
        'field_block_body' => $html($b['body']),
        'field_text_style' => $b['style'] ?? 'default',
      ]);

    case 'figure':
      return $figure($b, $b['layout'] ?? 'center');

    // A letter’s pages: turned in the object view, "4 pages" on the wall.
    case 'gallery':
      return Paragraph::create($keep([
        'type' => 'exhibit_gallery',
        'field_gallery_items' => array_map(fn ($f) => $figure($f, 'center'), $b['figures']),
        'field_figure_caption' => ($b['caption'] ?? '') !== '' ? $html($b['caption']) : NULL,
      ]));

    case 'entry':
      return Paragraph::create($keep([
        'type' => 'exhibit_entry',
        'field_block_title' => $b['title'],
        'field_section_anchor' => $b['anchor'],
        // A text plate’s words on a wall (palefire.theme, _palefire_exhibit_wall()).
        'field_entry_summary' => !empty($b['summary']) && $has_summary ? $b['summary'] : NULL,
        'field_guide_blocks' => array_values(array_filter(array_map($block, $b['blocks']))),
      ]));
  }
  return NULL;
};

// Each lead is one of the gallery’s own documents. All ten are 1,500px or
// more, so they fill the screen; "Show whole image" (framed, over a blur)
// is only for a lead under 1280×720, which the data marks "whole": true.
$has = fn (string $name) => (bool) FieldConfig::loadByName('paragraph', 'exhibit_page', $name);
$sections = [];
foreach ($data['pages'] as $page) {
  $lead = $ref($media($page['lead'] ?? NULL));
  $sections[] = Paragraph::create($keep([
    'type' => 'exhibit_page',
    'field_section_title' => $page['title'],
    'field_section_anchor' => $page['anchor'],
    'field_exhibit_lead' => $lead,
    'field_exhibit_lead_focus' => $lead ? ($page['focus'] ?? 'center') : NULL,
    'field_exhibit_lead_whole' => $lead && !empty($page['whole']) && $has('field_exhibit_lead_whole') ? 1 : NULL,
    'field_exhibit_lead_caption' => $lead && !empty($page['lead_caption']) && $has('field_exhibit_lead_caption') ? $html($page['lead_caption']) : NULL,
    'field_exhibit_display' => $has('field_exhibit_display') ? ($page['display'] ?? 'wall') : NULL,
    'field_guide_blocks' => array_values(array_filter(array_map($block, $page['blocks']))),
  ]));
  echo 'Built gallery: ' . $page['title'] . PHP_EOL;
}

// The page. ------------------------------------------------------------------

$label = $data['cover_label'];
$values = [
  'type' => 'modular_page',
  'title' => $title,
  'status' => 0,
  'field_scroll_section' => $sections,
  'field_modular_type' => 'exhibition',
  'field_exhibit_subtitle' => $data['subtitle'],
  'field_exhibit_credit' => $html($data['credit']),
  'field_exhibit_cover' => $ref($media($data['cover'])),
  'field_exhibit_year' => $data['year'],
  'field_exhibit_physical_note' => $data['physical_note'],
  // The cover is a small photograph from a scrapbook (1244×914, under
  // 720p; no larger copy exists): shown whole over a blur, with its object
  // label. Dropped below if the field is missing.
  'field_exhibit_cover_framed' => 1,
  'field_exhibit_cover_maker' => $label['maker'],
  'field_exhibit_cover_title' => $label['title'],
  'field_exhibit_cover_date' => $label['date'],
  'field_exhibit_cover_medium' => $label['medium'],
];
$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');
foreach (array_keys($values) as $name) {
  if (str_starts_with($name, 'field_') && !isset($node_fields[$name])) {
    $skipped[] = "$name is not on Modular page; run its setup script, or fill it in the editor.";
    unset($values[$name]);
  }
}
$values['path'] = ['alias' => '/' . ltrim($args['path'] ?? '/v2/special/exhibitions/natchez', '/')];

$node = Node::create(array_filter($values, fn ($v) => $v !== NULL));
$node->setRevisionLogMessage('Exhibition: New from Old Natchez, from the 2002 pages (palefire_exhibit_natchez.php).');
$node->save();

echo 'Created unpublished exhibition page: node/' . $node->id() . ' (' . $node->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
foreach (array_unique($skipped) as $line) {
  echo 'Skipped: ' . $line . PHP_EOL;
}
echo 'Review it, then publish. Run: drush cr' . PHP_EOL;
