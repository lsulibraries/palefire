<?php

/**
 * @file
 * LSU Union Art Gallery: shows its five galleries of shows as walls, on a
 * site where palefire_exhibit_unionartgallery.php already built the page.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_unionartgallery_wall.php
 *   drush cr
 *
 * Optional arguments after "--":
 *   dry-run=1             List what would change; save nothing.
 *   nid=<node id>         The page (default: the Modular page titled
 *                         "LSU Union Art Gallery").
 *   format=<text format>  Text format for the text (default: WYSIWYG).
 *   from=<site>           Where to download an image the server doesn't have
 *                         (default: https://lib.lsu.edu).
 *
 * From data/unionartgallery.json, for Studio Art, Louisiana Artists,
 * Interdisciplinary Collaboration, Celebration of Books and Visiting
 * Artists (matched by gallery title): Display "wall", and the gallery's
 * entries rebuilt as one entry per image, titled by its caption, under a
 * sub-gallery heading per show (an entry with no image: its title and any
 * text). The images are the same "Union Art Gallery: <file name>" media
 * items the page already uses.
 *
 * A gallery that is already a wall is left alone, so an editor's later
 * changes are kept. Saves the page as a new revision. Safe to re-run.
 */

declare(strict_types=1);

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

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
$dry = !empty($args['dry-run']);
$from = rtrim($args['from'] ?? 'https://lib.lsu.edu', '/');

if (!FieldConfig::loadByName('paragraph', 'exhibit_page', 'field_exhibit_display')) {
  throw new \RuntimeException('field_exhibit_display is not on Gallery: run palefire_exhibit_setup.php first.');
}

$data = json_decode((string) file_get_contents(__DIR__ . '/data/unionartgallery.json'), TRUE);
if (!is_array($data) || count($data['pages'] ?? []) !== 6) {
  throw new \RuntimeException("Couldn't read data/unionartgallery.json.");
}

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

if (!empty($args['nid'])) {
  $node = Node::load((int) $args['nid']);
}
else {
  $ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
    ->condition('type', 'modular_page')
    ->condition('title', $data['title'])
    ->execute();
  if (count($ids) !== 1) {
    throw new \RuntimeException('Expected one Modular page titled "' . $data['title'] . '", found ' . count($ids) . '. Pass nid=<node id>.');
  }
  $node = Node::load(reset($ids));
}
if (!$node || $node->bundle() !== 'modular_page') {
  throw new \RuntimeException('Not a Modular page.');
}

// The Image media type, for an image the page doesn't have yet.
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
$ref = fn (?int $id) => $id ? ['target_id' => $id] : NULL;
$keep = fn (array $values) => array_filter($values, fn ($v) => $v !== NULL && $v !== '');

/** @var \Drupal\Core\File\FileSystemInterface $fs */
$fs = \Drupal::service('file_system');
$destination = 'public://exhibitions/unionartgallery';
$media_ids = [];

// The page's media item for a file (by name), else imported as the build
// script does: from disk, else downloaded from from=. Nothing is imported
// on a dry run.
$media = function (array $f) use (&$media_ids, $fs, $destination, $media_type, $source_field, $from, $dry, &$skipped): ?int {
  $relative = $f['image'];
  if (array_key_exists($relative, $media_ids)) {
    return $media_ids[$relative];
  }
  $base = pathinfo($relative, PATHINFO_FILENAME);
  $name = 'Union Art Gallery: ' . $base;
  $existing = \Drupal::entityQuery('media')->accessCheck(FALSE)
    ->condition('bundle', $media_type->id())->condition('name', $name)->range(0, 1)->execute();
  if ($existing) {
    return $media_ids[$relative] = (int) reset($existing);
  }
  if ($dry) {
    echo "Would import $relative" . PHP_EOL;
    return $media_ids[$relative] = NULL;
  }
  $fs->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  $safe = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($base)), '-') . '.jpg';
  $path = DRUPAL_ROOT . '/' . $relative;
  if (is_file($path)) {
    $uri = $fs->copy($path, "$destination/$safe", FileExists::Rename);
  }
  else {
    $url = $from . '/' . implode('/', array_map('rawurlencode', explode('/', $relative)));
    try {
      $bytes = (string) \Drupal::httpClient()->get($url, ['timeout' => 30])->getBody();
    }
    catch (\Throwable $e) {
      $bytes = '';
    }
    if ($bytes === '' || @getimagesizefromstring($bytes) === FALSE) {
      $skipped[] = "Image not found on disk or at $url";
      return $media_ids[$relative] = NULL;
    }
    $uri = $fs->saveData($bytes, "$destination/$safe", FileExists::Rename);
  }
  $file = File::create(['uri' => $uri, 'status' => 1]);
  $file->save();
  [$width, $height] = getimagesize($fs->realpath($uri)) ?: [NULL, NULL];
  $item = Media::create([
    'bundle' => $media_type->id(),
    'name' => $name,
    'status' => 1,
    $source_field => ['target_id' => $file->id(), 'alt' => $f['alt'] ?? '', 'width' => $width, 'height' => $height],
  ]);
  $item->save();
  echo "Imported $relative" . PHP_EOL;
  return $media_ids[$relative] = (int) $item->id();
};

// The data's blocks: text, a figure, or an entry of them.
$block = function (array $b) use (&$block, $media, $ref, $keep, $html, &$skipped): ?Paragraph {
  switch ($b['type']) {
    case 'text':
      return Paragraph::create([
        'type' => 'guide_text',
        'field_block_body' => $html($b['body']),
        'field_text_style' => $b['style'] ?? 'default',
      ]);

    case 'figure':
      $id = $media($b);
      if (!$id) {
        $skipped[] = 'No image for "' . $b['image'] . '": add it in the editor.';
      }
      return Paragraph::create($keep([
        'type' => 'exhibit_figure',
        'field_figure_image' => $ref($id),
        'field_figure_caption' => ($b['caption'] ?? '') !== '' ? $html($b['caption']) : NULL,
        'field_figure_layout' => $b['layout'] ?? 'center',
      ]));

    case 'entry':
      return Paragraph::create($keep([
        'type' => 'exhibit_entry',
        'field_block_title' => $b['title'],
        'field_section_anchor' => $b['anchor'],
        'field_guide_blocks' => array_values(array_filter(array_map($block, $b['blocks']))),
      ]));
  }
  return NULL;
};

$walls = [];
foreach ($data['pages'] as $page) {
  if (($page['display'] ?? 'story') === 'wall') {
    $walls[$page['title']] = $page;
  }
}

$log = [];
foreach ($node->get('field_scroll_section')->referencedEntities() as $gallery) {
  if ($gallery->bundle() !== 'exhibit_page') {
    continue;
  }
  $title = (string) $gallery->get('field_section_title')->value;
  $page = $walls[$title] ?? NULL;
  if (!$page) {
    continue;
  }
  if ((string) $gallery->get('field_exhibit_display')->value === 'wall') {
    $log[] = "Already a wall, left alone: $title";
    continue;
  }
  $entries = array_values(array_filter(array_map($block, $page['blocks'])));
  $images = count(array_filter($page['blocks'], fn ($b) => $b['type'] === 'entry' && array_filter($b['blocks'], fn ($c) => $c['type'] === 'figure')));
  $log[] = "Wall: $title (" . $images . ' images, ' . (count($entries) - $images) . ' sub-galleries)';
  if (!$dry) {
    $gallery->set('field_exhibit_display', 'wall');
    $gallery->set('field_guide_blocks', $entries);
    $gallery->setNeedsSave(TRUE);
  }
}

if ($log && !$dry && array_filter($log, fn ($line) => str_starts_with($line, 'Wall:'))) {
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage('Union Art Gallery: the five galleries of shows as walls, one tile per image under each show (palefire_exhibit_unionartgallery_wall.php).');
  $node->save();
}
foreach ($log as $line) {
  echo ($dry ? '[dry run] ' : '') . $line . PHP_EOL;
}
foreach (array_unique($skipped) as $line) {
  echo 'Skipped: ' . $line . PHP_EOL;
}
echo $dry ? 'Dry run: nothing saved.' . PHP_EOL : 'Done. Run: drush cr' . PHP_EOL;
