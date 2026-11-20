<?php

/**
 * @file
 * Exhibition page: "Louisiana for Bibliophiles: A History of Reading in the
 * Bayou State" (LSU Libraries Special Collections, Hill Memorial Library,
 * 2011; online at /louisianaforbibliophile/introduction and nine sibling
 * pages) as an unpublished Modular page in exhibition mode, at
 * /v2/special/exhibitions/louisiana-for-bibliophiles.
 * Run palefire_exhibit_setup.php first.
 *
 * Copy the "bibliophiles-web" folder’s masters/ (74 JPEGs) to the server
 * first, then:
 *
 *   drush php:script scripts/palefire_exhibit_louisiana_for_bibliophiles.php -- images=$HOME/bibliophiles-masters
 *   drush php:script scripts/palefire_exhibit_louisiana_for_bibliophiles.php -- images=$HOME/bibliophiles-masters apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it checks the files and the fields and lists
 * what it would build, saving nothing.
 *
 * Optional arguments after "--":
 *   images=<folder>       Required. The JPEG masters.
 *   format=<text format>  Text format for the text (default: WYSIWYG).
 *   path=<alias>          URL alias (default
 *                         /v2/special/exhibitions/louisiana-for-bibliophiles).
 *
 * The text is in data/louisiana-for-bibliophiles.json: the old Introduction
 * as the page’s intro, then the old site’s seven sections as galleries, all
 * walls (Display "wall") with a lead: Early Days, Creole Authors, African
 * Americans, Women’s Reading and Novels, Practical Reading, Newspapers and
 * Louisiana Libraries, and About the Exhibition (the curators and the old
 * Index, its "See …" lines now links to the galleries). Each section of
 * the old text is a text plate (heading, written summary, the text) hung
 * before its pictures; books with several pages are one tile that pages
 * through them. Changes (repeated paragraphs removed, a few typos and
 * accents, book titles in italics) are listed in
 * docs/special-exhibition-corrections/louisiana-for-bibliophiles.md.
 *
 * Images: 58 from the exhibition’s TIFF scans on the Special Collections
 * share (cropped to the old pages’ framing, up to 2,560 px; the old pages’
 * were 1,000 px), 16 at the old size where no scan was found (the
 * Louisiana Digital Library has none of them). Each is imported once as an
 * Image media item (named "Bibliophiles: <file name>", reused on a re-run)
 * under public://exhibitions/louisiana-for-bibliophiles.
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
$prefix = $apply ? '' : '[dry run] ';

foreach (['exhibit_page', 'exhibit_entry', 'exhibit_figure', 'exhibit_gallery', 'guide_text'] as $id) {
  if (!ParagraphsType::load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_exhibit_setup.php first.");
  }
}

$images_dir = rtrim($args['images'] ?? '', '/\\');
if ($images_dir === '' || !is_dir($images_dir)) {
  throw new \RuntimeException('Pass images=<folder>: the bibliophiles-web/masters folder copied to the server.');
}

$data_file = __DIR__ . '/data/louisiana-for-bibliophiles.json';
$data = json_decode((string) file_get_contents($data_file), TRUE);
if (!is_array($data) || count($data['pages'] ?? []) !== 8) {
  throw new \RuntimeException("Couldn’t read the eight galleries from $data_file.");
}

$title = $data['title'];
$found = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', $title)
  ->range(0, 1)->execute();
if ($found) {
  throw new \RuntimeException("A page titled '$title' already exists (node " . reset($found) . '). Delete it first to rebuild it.');
}

$alias = '/' . ltrim($args['path'] ?? '/v2/special/exhibitions/louisiana-for-bibliophiles', '/');
if (\Drupal::entityTypeManager()->getStorage('path_alias')->loadByProperties(['alias' => $alias])) {
  throw new \RuntimeException("$alias is already taken. Pass path=<alias>.");
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

/** @var \Drupal\Core\File\FileSystemInterface $fs */
$fs = \Drupal::service('file_system');
$destination = 'public://exhibitions/louisiana-for-bibliophiles';
if ($apply) {
  $fs->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
}

// Images. --------------------------------------------------------------------

$media_ids = [];

// One media item per file, reused when it exists ("Bibliophiles: <file>").
// A dry run only checks the file is there.
$media = function (?string $file) use (&$media_ids, $apply, $prefix, $images_dir, $fs, $destination, $media_type, $source_field, $data, &$skipped): ?int {
  if (!$file) {
    return NULL;
  }
  if (array_key_exists($file, $media_ids)) {
    return $media_ids[$file];
  }
  $name = 'Bibliophiles: ' . pathinfo($file, PATHINFO_FILENAME);
  $existing = \Drupal::entityQuery('media')->accessCheck(FALSE)
    ->condition('bundle', $media_type->id())->condition('name', $name)->range(0, 1)->execute();
  if ($existing) {
    echo "{$prefix}Reusing media " . reset($existing) . " for $file" . PHP_EOL;
    return $media_ids[$file] = (int) reset($existing);
  }
  $path = $images_dir . '/' . $file;
  if (!is_file($path)) {
    $skipped[] = "Image not found: $path";
    return $media_ids[$file] = NULL;
  }
  [$width, $height] = getimagesize($path) ?: [NULL, NULL];
  if (!$apply) {
    echo "{$prefix}Would import $file ({$width} × {$height})" . PHP_EOL;
    return $media_ids[$file] = NULL;
  }
  $uri = $fs->copy($path, "$destination/$file", FileExists::Rename);
  $item = File::create(['uri' => $uri, 'status' => 1]);
  $item->save();
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
$has_entry = fn (string $name) => (bool) FieldConfig::loadByName('paragraph', 'exhibit_entry', $name);

$figure = function (array $b, string $layout) use ($media, $ref, $keep, $html, $apply, &$skipped): Paragraph {
  $id = $media($b['image'] ?? NULL);
  if (!$id && $apply) {
    $skipped[] = 'No image for "' . $b['image'] . '": the figure keeps its caption; add the image in the editor.';
  }
  return Paragraph::create($keep([
    'type' => 'exhibit_figure',
    'field_figure_image' => $ref($id),
    'field_figure_caption' => ($b['caption'] ?? '') !== '' ? $html($b['caption']) : NULL,
    'field_figure_layout' => $layout,
  ]));
};

$block = function (array $b) use (&$block, $figure, $keep, $html, $has_entry, &$skipped): ?Paragraph {
  switch ($b['type']) {
    case 'text':
      return Paragraph::create([
        'type' => 'guide_text',
        'field_block_body' => $html($b['body']),
        'field_text_style' => $b['style'] ?? 'default',
      ]);

    case 'figure':
      return $figure($b, $b['layout'] ?? 'side_start');

    // A book’s pages as one tile that pages through them.
    case 'gallery':
      return Paragraph::create($keep([
        'type' => 'exhibit_gallery',
        'field_gallery_items' => array_map(fn ($f) => $figure($f, 'center'), $b['figures']),
        'field_figure_caption' => ($b['caption'] ?? '') !== '' ? $html($b['caption']) : NULL,
      ]));

    case 'entry':
      if (!empty($b['summary']) && !$has_entry('field_entry_summary')) {
        $skipped[] = 'field_entry_summary is not on Entry; run palefire_exhibit_bio_setup.php. "' . $b['title'] . '" hangs as a picture tile without words.';
      }
      return Paragraph::create($keep([
        'type' => 'exhibit_entry',
        'field_block_title' => $b['title'],
        'field_section_anchor' => $b['anchor'],
        // A text plate’s words on a wall (palefire.theme, _palefire_exhibit_wall()).
        'field_entry_summary' => !empty($b['summary']) && $has_entry('field_entry_summary') ? $b['summary'] : NULL,
        'field_guide_blocks' => array_values(array_filter(array_map($block, $b['blocks']))),
      ]));
  }
  return NULL;
};

// Each gallery is a wall with a lead and its caption.
$has = fn (string $name) => (bool) FieldConfig::loadByName('paragraph', 'exhibit_page', $name);
if (!$has('field_exhibit_display')) {
  $skipped[] = 'field_exhibit_display is not on Gallery; run palefire_exhibit_setup.php. The galleries read as stories.';
}
$sections = [];
foreach ($data['pages'] as $page) {
  $lead = $ref($media($page['lead'] ?? NULL));
  $sections[] = Paragraph::create($keep([
    'type' => 'exhibit_page',
    'field_section_title' => $page['title'],
    'field_section_anchor' => $page['anchor'],
    'field_exhibit_lead' => $lead,
    'field_exhibit_lead_focus' => $page['focus'] ?? 'center',
    // A lead under 1280 × 720 is shown whole over its own blur, not
    // enlarged (only African Americans’ Teachers’ Journal cover, 819 × 1,000).
    'field_exhibit_lead_whole' => !empty($page['whole']) && $has('field_exhibit_lead_whole') ? 1 : NULL,
    'field_exhibit_lead_caption' => !empty($page['lead_caption']) && $has('field_exhibit_lead_caption') ? $html($page['lead_caption']) : NULL,
    'field_exhibit_display' => $has('field_exhibit_display') ? ($page['display'] ?? 'story') : NULL,
    'field_guide_blocks' => array_values(array_filter(array_map($block, $page['blocks']))),
  ]));
  echo "{$prefix}Built gallery: " . $page['title'] . ' (' . count($page['blocks']) . ' entries)' . PHP_EOL;
}

// The page. ------------------------------------------------------------------

$label = $data['cover_label'] ?? [];
$values = [
  'type' => 'modular_page',
  'title' => $title,
  'status' => 0,
  'field_scroll_section' => $sections,
  'field_modular_type' => 'exhibition',
  'field_exhibit_subtitle' => $data['subtitle'] !== '' ? $data['subtitle'] : NULL,
  'field_exhibit_credit' => $html($data['credit']),
  'field_exhibit_cover' => $ref($media($data['cover'])),
  'field_intro' => $html($data['intro']),
  'field_exhibit_year' => $data['year'],
  'field_exhibit_physical_note' => $data['physical_note'] !== '' ? $data['physical_note'] : NULL,
  // The cover is a photograph of Hill’s reading room: it fills the frame,
  // with its object label.
  'field_exhibit_cover_title' => $label['title'] ?? NULL,
  'field_exhibit_cover_date' => $label['date'] ?? NULL,
  'field_exhibit_cover_medium' => $label['medium'] ?? NULL,
  'path' => ['alias' => $alias],
];
$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');
foreach (array_keys($values) as $name) {
  if (str_starts_with($name, 'field_') && !isset($node_fields[$name])) {
    $skipped[] = "$name is not on Modular page; run palefire_exhibit_setup.php.";
    unset($values[$name]);
  }
}

if (!$apply) {
  echo "{$prefix}Would create the unpublished exhibition page '$title' at $alias." . PHP_EOL;
  foreach (array_unique($skipped) as $line) {
    echo "{$prefix}Problem: $line" . PHP_EOL;
  }
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$node = Node::create(array_filter($values, fn ($v) => $v !== NULL));
$node->setRevisionLogMessage('Exhibition: Louisiana for Bibliophiles, from /louisianaforbibliophile (palefire_exhibit_louisiana_for_bibliophiles.php).');
$node->save();

echo 'Created unpublished exhibition page: node/' . $node->id() . ' (' . $node->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
foreach (array_unique($skipped) as $line) {
  echo 'Skipped: ' . $line . PHP_EOL;
}
echo 'Review it, then publish. Run: drush cr' . PHP_EOL;
