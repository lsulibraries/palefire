<?php

/**
 * @file
 * Exhibition page: "John James Audubon in Louisiana" (LSU Libraries digital
 * exhibition, 2021, from "Audubon at Oakley", 2008) as an unpublished
 * Modular page in exhibition mode. Run palefire_exhibit_setup.php first.
 *
 * Copy the "Audubon 4K" folder to the server first, then:
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_audubon.php -- images=/tmp/audubon-4k path=/v2/exhibitions/audubon
 *   drush cr
 *
 * Optional arguments after "--":
 *   images=<folder>       Required. The "Audubon 4K" folder.
 *   format=<text format>  Text format for the text (default: WYSIWYG).
 *   path=<alias>          URL alias, e.g. path=/v2/exhibitions/audubon
 *
 * The text, in data/audubon.json, is word for word: the landing and section
 * pages from the Wayback Machine's copies of
 * lib.lsu.edu/exhibitions/john-james-audubon, and the 78 bird and manuscript
 * pages it never captured from the students' copy at /audubon, with its
 * mis-encoded characters decoded (checked against the pages the Wayback
 * Machine has: no word differs). Changes: spacing and punctuation (README),
 * headings from the 2021 menus, links point at the new anchors; gallery lead
 * captions and alt text are new.
 *
 * Images: each JPEG is imported once as an Image media item (named
 * "Audubon: <file name>", reused on a re-run) under
 * public://exhibitions/audubon. Missing files are listed at the end; add
 * their image in the editor.
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

foreach (['exhibit_page', 'exhibit_entry', 'exhibit_figure', 'exhibit_gallery', 'exhibit_citation', 'guide_text'] as $id) {
  if (!ParagraphsType::load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_exhibit_setup.php first.");
  }
}

$images_dir = rtrim($args['images'] ?? '', '/\\');
if ($images_dir === '' || !is_dir($images_dir)) {
  throw new \RuntimeException('Pass images=<folder>: the "Audubon 4K" folder copied to the server.');
}

$data_file = __DIR__ . '/data/audubon.json';
$data = json_decode((string) file_get_contents($data_file), TRUE);
if (!is_array($data) || count($data['pages'] ?? []) !== 7) {
  throw new \RuntimeException("Couldn't read the 7 galleries from $data_file.");
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

// Images. --------------------------------------------------------------------

/** @var \Drupal\Core\File\FileSystemInterface $fs */
$fs = \Drupal::service('file_system');
$destination = 'public://exhibitions/audubon';
$fs->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
$media_ids = [];

// One media item per file, reused when it exists ("Audubon: <file>").
$media = function (?string $relative) use (&$media_ids, $images_dir, $fs, $destination, $media_type, $source_field, $data, &$skipped): ?int {
  if (!$relative) {
    return NULL;
  }
  if (isset($media_ids[$relative])) {
    return $media_ids[$relative];
  }
  $name = 'Audubon: ' . pathinfo($relative, PATHINFO_FILENAME);
  $existing = \Drupal::entityQuery('media')->accessCheck(FALSE)
    ->condition('bundle', $media_type->id())->condition('name', $name)->range(0, 1)->execute();
  if ($existing) {
    return $media_ids[$relative] = (int) reset($existing);
  }
  $path = $images_dir . '/' . $relative;
  if (!is_file($path)) {
    $skipped[] = "Image not found: $path";
    return $media_ids[$relative] = NULL;
  }
  $safe = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(pathinfo($relative, PATHINFO_FILENAME))), '-') . '.jpg';
  $uri = $fs->copy($path, "$destination/$safe", FileExists::Rename);
  $file = File::create(['uri' => $uri, 'status' => 1]);
  $file->save();
  [$width, $height] = getimagesize($path) ?: [NULL, NULL];
  $item = Media::create([
    'bundle' => $media_type->id(),
    'name' => $name,
    'status' => 1,
    $source_field => [
      'target_id' => $file->id(),
      'alt' => $data['alt'][$relative] ?? '',
      'width' => $width,
      'height' => $height,
    ],
  ]);
  $item->save();
  echo "Imported $relative" . PHP_EOL;
  return $media_ids[$relative] = (int) $item->id();
};

// Blocks. --------------------------------------------------------------------

$ref = fn (?int $id) => $id ? ['target_id' => $id] : NULL;
$keep = fn (array $values) => array_filter($values, fn ($v) => $v !== NULL && $v !== '');

// A drawing's alignment over its proof (palefire_exhibit_setup.php adds the
// field; without it the pairs are compared unaligned).
$has_align = (bool) FieldConfig::loadByName('paragraph', 'exhibit_figure', 'field_figure_align');
$figure = function (array $b, string $layout) use ($media, $ref, $keep, $html, $has_align, &$skipped): Paragraph {
  $id = $media($b['image'] ?? NULL);
  if (!$id) {
    $skipped[] = 'No image for "' . $b['source'] . '": the figure keeps its caption; add the image in the editor.';
  }
  return Paragraph::create($keep([
    'type' => 'exhibit_figure',
    'field_figure_image' => $ref($id),
    'field_figure_caption' => ($b['caption'] ?? '') !== '' ? $html($b['caption']) : NULL,
    'field_figure_layout' => $layout,
    'field_figure_align' => $has_align ? ($b['align'] ?? NULL) : NULL,
  ]));
};

$block = function (array $b) use (&$block, $figure, $keep, $html): ?Paragraph {
  switch ($b['type']) {
    case 'text':
      return Paragraph::create([
        'type' => 'guide_text',
        'field_block_body' => $html($b['body']),
        'field_text_style' => $b['style'] ?? 'default',
      ]);

    case 'citation':
      return Paragraph::create(['type' => 'exhibit_citation', 'field_block_body' => $html($b['body'])]);

    case 'figure':
      return $figure($b, $b['layout'] ?? 'side_start');

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
        'field_entry_bio' => $b['bio'] !== '' ? $html($b['bio']) : NULL,
        'field_guide_blocks' => array_values(array_filter(array_map($block, $b['blocks']))),
      ]));
  }
  return NULL;
};

// Each lead is one of the gallery's own images, credited in its caption.
$has_caption = (bool) FieldConfig::loadByName('paragraph', 'exhibit_page', 'field_exhibit_lead_caption');
// Display and sheet size (added 2026-10-21): left out where the site's
// setup predates them (the galleries then read as stories; set them later).
$has_display = (bool) FieldConfig::loadByName('paragraph', 'exhibit_page', 'field_exhibit_display');
$has_sheet = (bool) FieldConfig::loadByName('paragraph', 'exhibit_page', 'field_exhibit_sheet');
if (!$has_display) {
  $skipped[] = 'field_exhibit_display is not on Gallery: re-run palefire_exhibit_setup.php, then set Display to "Wall" on Elephant Folio, Octavo Edition and Drawings and Proofs.';
}
$sections = [];
foreach ($data['pages'] as $page) {
  $sections[] = Paragraph::create($keep([
    'type' => 'exhibit_page',
    'field_section_title' => $page['title'],
    'field_section_anchor' => $page['anchor'],
    'field_exhibit_lead' => $ref($media($page['lead'] ?? NULL)),
    'field_exhibit_lead_focus' => $page['focus'] ?? 'center',
    'field_exhibit_lead_caption' => !empty($page['lead_caption']) && $has_caption ? $html($page['lead_caption']) : NULL,
    'field_exhibit_display' => $has_display ? ($page['display'] ?? 'story') : NULL,
    'field_exhibit_sheet' => $has_sheet ? ($page['sheet'] ?? NULL) : NULL,
    'field_guide_blocks' => array_values(array_filter(array_map($block, $page['blocks']))),
  ]));
  echo 'Built gallery: ' . $page['title'] . PHP_EOL;
}

// The page. ------------------------------------------------------------------

$values = [
  'type' => 'modular_page',
  'title' => $title,
  'status' => 0,
  'field_scroll_section' => $sections,
  'field_modular_type' => 'exhibition',
  'field_exhibit_subtitle' => $data['subtitle'] !== '' ? $data['subtitle'] : NULL,
  'field_exhibit_credit' => $html($data['credit']),
  'field_exhibit_cover' => $ref($media($data['cover'])),
  'field_exhibit_year' => $data['year'],
  'field_exhibit_physical_note' => $data['physical_note'],
];
$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');
foreach (array_keys($values) as $name) {
  if (str_starts_with($name, 'field_') && !isset($node_fields[$name])) {
    $skipped[] = "$name is not on Modular page; run palefire_exhibit_setup.php.";
    unset($values[$name]);
  }
}
if (!empty($args['path'])) {
  $values['path'] = ['alias' => '/' . ltrim($args['path'], '/')];
}

$node = Node::create(array_filter($values, fn ($v) => $v !== NULL));
$node->setRevisionLogMessage('Exhibition: John James Audubon in Louisiana, from the 2021 digital exhibition (palefire_exhibit_audubon.php).');
$node->save();

echo 'Created unpublished exhibition page: node/' . $node->id() . ' (' . $node->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
foreach (array_unique($skipped) as $line) {
  echo 'Skipped: ' . $line . PHP_EOL;
}
echo 'Review it, then publish. Run: drush cr' . PHP_EOL;
