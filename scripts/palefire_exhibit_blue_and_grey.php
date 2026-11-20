<?php

/**
 * @file
 * Exhibition page: "Blue and Gray for Boys and Girls" (LSU Libraries
 * Special Collections; Civil War books for young people from the Michael
 * Lehman Williamson Collection, in twelve cases from 1860 to 2001; online at
 * /BlueandGrey, node/299–313) as an unpublished Modular page in exhibition
 * mode. Run palefire_exhibit_setup.php and palefire_exhibit_bio_setup.php
 * first.
 *
 * Copy the "blue-and-grey-web/masters" folder (86 JPEGs, up to 2560 px) to
 * the server first, then:
 *
 *   drush php:script scripts/palefire_exhibit_blue_and_grey.php -- images=$HOME/blue-and-grey-masters path=/v2/special/exhibitions/blue-and-grey
 *   drush php:script scripts/palefire_exhibit_blue_and_grey.php -- images=$HOME/blue-and-grey-masters path=/v2/special/exhibitions/blue-and-grey apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it reads the data, checks every image is in
 * the folder and lists the galleries, saving nothing.
 *
 * Arguments after "--":
 *   images=<folder>       Required. The JPEG masters.
 *   format=<text format>  Text format for the text (default: WYSIWYG).
 *   path=<alias>          URL alias, e.g. path=/v2/special/exhibitions/blue-and-grey
 *   apply=1               Save.
 *
 * The text, in data/blue-and-grey.json: the case texts, book lists and
 * author profiles of the online exhibition. Five of its fifteen pages
 * (Cases 1, 2, 4 and 12 and Author Profiles) return an error on the live
 * site, their text bloated by characters mis-encoded on every save; their
 * text comes from the exhibition’s own 2012 label files (R:\Special
 * Collections\Exhibitions\Completed Exhibitions\EXHIB_Old Times There_Civil
 * War_Summer 2012), which match the pages that still open word for word.
 * The introduction, empty on the live page, is the 2012 wall label.
 * Corrections are listed in docs/special-exhibition-corrections/
 * blue-and-grey.md.
 *
 * Every gallery is a wall with a lead picture: the case’s text as a text
 * plate (with its Sources), then its books as tiles (cover or plate, with
 * the catalog record), the books with no picture as an “Also in the case”
 * plate, and the photograph of the case as it was shown. The author
 * profiles are name tiles; the links are three plates.
 *
 * Pictures: the old pages had a 750 px photograph of each case and one
 * 300 px drawing. The book pictures are new, from the 2012 scans on R: (up
 * to 10,000 px), made into 2,560 px JPEGs; Boykin’s Boys and Girls Stories
 * of the War is Duke University Libraries’ copy, from the Internet Archive.
 * Each is imported once as an Image media item (named "Blue and Gray:
 * <file name>", reused on a re-run) under public://exhibitions/blue-and-grey.
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

foreach (['exhibit_page', 'exhibit_entry', 'exhibit_figure', 'exhibit_gallery', 'exhibit_citation', 'guide_text'] as $id) {
  if (!ParagraphsType::load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_exhibit_setup.php first.");
  }
}

$images_dir = rtrim($args['images'] ?? '', '/\\');
if ($images_dir === '' || !is_dir($images_dir)) {
  throw new \RuntimeException('Pass images=<folder>: the blue-and-grey-web/masters folder copied to the server.');
}

$data_file = __DIR__ . '/data/blue-and-grey.json';
$data = json_decode((string) file_get_contents($data_file), TRUE);
if (!is_array($data) || count($data['pages'] ?? []) !== 15) {
  throw new \RuntimeException("Couldn't read the 15 galleries from $data_file.");
}

$title = $data['title'];
$found = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', $title)
  ->range(0, 1)->execute();
if ($found) {
  throw new \RuntimeException("A page titled '$title' already exists (node " . reset($found) . '). Delete it first to rebuild it.');
}

// Every image the data names must be in the folder.
$missing = array_values(array_filter(array_keys($data['alt']), fn ($file) => !is_file("$images_dir/$file")));
echo 'Images: ' . (count($data['alt']) - count($missing)) . ' of ' . count($data['alt']) . " found in $images_dir" . PHP_EOL;
foreach ($missing as $file) {
  echo "  Missing: $file" . PHP_EOL;
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

foreach ($data['pages'] as $page) {
  echo $prefix . 'Gallery: ' . $page['title'] . ' (' . count($page['blocks']) . ' entries, lead ' . $page['lead'] . ')' . PHP_EOL;
}
if (!$apply) {
  echo '[dry run] Nothing saved. Run again with apply=1 to build the page.' . PHP_EOL;
  return;
}

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
$entry_field = fn (string $name) => (bool) FieldConfig::loadByName('paragraph', 'exhibit_entry', $name);

// Images. --------------------------------------------------------------------

/** @var \Drupal\Core\File\FileSystemInterface $fs */
$fs = \Drupal::service('file_system');
$destination = 'public://exhibitions/blue-and-grey';
$fs->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
$media_ids = [];

// One media item per file, reused when it exists ("Blue and Gray: <file>").
$media = function (?string $file) use (&$media_ids, $images_dir, $fs, $destination, $media_type, $source_field, $data, &$skipped): ?int {
  if (!$file) {
    return NULL;
  }
  if (array_key_exists($file, $media_ids)) {
    return $media_ids[$file];
  }
  $name = 'Blue and Gray: ' . pathinfo($file, PATHINFO_FILENAME);
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
    $skipped[] = 'No image for "' . $b['image'] . '": the figure keeps its caption; add the image in the editor.';
  }
  return Paragraph::create($keep([
    'type' => 'exhibit_figure',
    'field_figure_image' => $ref($id),
    'field_figure_caption' => ($b['caption'] ?? '') !== '' ? $html($b['caption']) : NULL,
    'field_figure_layout' => $layout,
  ]));
};

$block = function (array $b) use (&$block, $figure, $keep, $html, $entry_field): ?Paragraph {
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
        // A text plate's words on a wall (palefire.theme, _palefire_exhibit_wall()).
        'field_entry_summary' => !empty($b['summary']) && $entry_field('field_entry_summary') ? $b['summary'] : NULL,
        // An author: a name tile, the biography read in the object view.
        'field_entry_bio' => !empty($b['bio']) ? $html($b['bio']) : NULL,
        'field_entry_role' => !empty($b['role']) && $entry_field('field_entry_role') ? $b['role'] : NULL,
        'field_guide_blocks' => array_values(array_filter(array_map($block, $b['blocks']))),
      ]));
  }
  return NULL;
};

// Each lead is one of the gallery's own pictures, with its caption. A lead
// under 720p (1280 x 720) is shown whole ("whole" in the data); larger ones
// fill the screen. Every lead here is larger, so none is set.
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
    'field_exhibit_display' => $has('field_exhibit_display') ? ($page['display'] ?? 'story') : NULL,
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
  // "Frame the cover" only for a cover under 720p ("cover_framed" in the
  // data); this one is 2,013 x 2,560, so it fills the screen. A field missing
  // from Modular page is skipped below.
  'field_exhibit_cover_framed' => !empty($data['cover_framed']) ? 1 : 0,
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
if (!empty($args['path'])) {
  $values['path'] = ['alias' => '/' . ltrim($args['path'], '/')];
}

$node = Node::create(array_filter($values, fn ($v) => $v !== NULL));
$node->setRevisionLogMessage('Exhibition: Blue and Gray for Boys and Girls, from the online exhibition and its 2012 label files (palefire_exhibit_blue_and_grey.php).');
$node->save();

echo 'Created unpublished exhibition page: node/' . $node->id() . ' (' . $node->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
foreach (array_unique($skipped) as $line) {
  echo 'Skipped: ' . $line . PHP_EOL;
}
echo 'Review it, then publish. Run: drush cr' . PHP_EOL;
