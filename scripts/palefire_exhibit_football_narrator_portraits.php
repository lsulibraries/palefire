<?php

/**
 * @file
 * Saturday Night in Tiger Stadium, The Narrators: pictures for seven
 * narrators who hang as name tiles.
 *
 * Six portraits come from the old exhibition's biography pages; the
 * narrators became entries (palefire_exhibit_walls_and_leads.php) before
 * these files were among the masters, or under another name:
 *   David Browndyke, John Hunter, Roger Ogden, Thomas Ruffin, Cecil Taylor
 *   (new media from the images folder) and Margaret Jameson (her existing
 *   "Tiger Stadium: margaretjamesonhr", the old biography's photo).
 * Jesse Coates has no portrait in the old exhibition or the Louisiana
 * Digital Library; his entry gets the Chemical Engineering Building, now
 * Jesse Coates Hall (LSU Photograph Collection, RG #A5000, Box 17 Folder 2;
 * louisianadigitallibrary.org/islandora/object/lsu-ua-uap:2261), with a
 * caption saying what it is. coateshall.jpg is that record's JP2 master
 * (the LDL export on L:), the print's border cropped, 2560 px high.
 *
 * Copy the six JPEGs (coateshall.jpg, ceciltaylor.jpg, davidbrowndyke.jpg,
 * johnhunter.jpg, rogerogden.jpg, thomasruffin.jpg) to the server, then:
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_football_narrator_portraits.php -- images=/path/to/folder
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_football_narrator_portraits.php -- images=/path/to/folder apply=1
 *   drush cr
 *
 * A dry run unless apply=1. Each picture is an Image media item named
 * "Tiger Stadium: <file name>" under public://exhibitions/tiger-stadium
 * (reused on a re-run), added to the entry as a figure (layout "side
 * start", as the other narrators' portraits). An entry that has a figure
 * already is left alone, so it is safe to re-run. The gallery is saved in
 * place, without a new revision; a page with a newer draft is skipped.
 * Undo: remove the figure from the entry on the page's edit form.
 */

declare(strict_types=1);

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\NodeInterface;
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
$apply = ($args['apply'] ?? '') === '1';
$say = fn (string $line) => print(($apply ? '' : '[dry run] ') . $line . PHP_EOL);
$images_dir = rtrim($args['images'] ?? '', '/\\');

// Narrator => [file, alt text, caption].
$pictures = [
  'David Browndyke' => ['davidbrowndyke.jpg', 'David Browndyke, number 4, kicks as a kneeling teammate holds the ball.', ''],
  'Jesse Coates' => ['coateshall.jpg', 'A two-story building with a tile roof behind a wide lawn, framed by live oak branches.', '<p>The Chemical Engineering Building, now Jesse Coates Hall, between 1940 and 1960. LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.</p>'],
  'John Hunter' => ['johnhunter.jpg', 'John Hunter smiles on the telephone at his desk, a small football helmet beside his pen stand.', ''],
  'Margaret Jameson' => ['margaretjamesonhr.jpg', '', ''],
  'Roger Ogden' => ['rogerogden.jpg', 'Roger Ogden as a student, smiling, in a white shirt and tie.', ''],
  'Thomas Ruffin' => ['thomasruffin.jpg', 'Portrait of Thomas Ruffin in glasses, a dark suit and tie.', ''],
  'Cecil Taylor' => ['ceciltaylor.jpg', 'Cecil Taylor, in a light suit and bow tie, talks with two students sitting on a low wall.', ''],
];

// The page and its gallery. ---------------------------------------------------

$alias = '/v2/oralhistory/exhibitions/football';
$nodes = \Drupal::entityTypeManager()->getStorage('node');
$path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
$node = preg_match('#^/node/(\d+)$#', $path, $m) ? $nodes->load($m[1]) : NULL;
if (!$node instanceof NodeInterface) {
  throw new \RuntimeException("No page at $alias.");
}
$latest = $nodes->getLatestRevisionId($node->id());
if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
  throw new \RuntimeException("$alias has a newer draft (revision $latest): publish or discard it first.");
}
$gallery = NULL;
foreach ($node->get('field_scroll_section')->referencedEntities() as $section) {
  if ($section->bundle() === 'exhibit_page' && trim((string) $section->get('field_section_title')->value) === 'The Narrators') {
    $gallery = $section;
  }
}
if (!$gallery) {
  throw new \RuntimeException("“{$node->label()}” has no gallery “The Narrators”.");
}
$format = NULL;
foreach ($gallery->get('field_guide_blocks')->referencedEntities() as $entry) {
  if ($entry->bundle() === 'exhibit_entry' && $entry->hasField('field_entry_bio')) {
    $format ??= $entry->get('field_entry_bio')->format;
  }
}

// Media. ----------------------------------------------------------------------

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
/** @var \Drupal\Core\File\FileSystemInterface $fs */
$fs = \Drupal::service('file_system');
$destination = 'public://exhibitions/tiger-stadium';

// The media item "Tiger Stadium: <file>": the existing one, else imported
// (on apply). 0 in a dry run for one that would be imported; NULL when
// neither is possible.
$media = function (string $file, string $alt) use ($apply, $images_dir, $fs, $destination, $media_type, $source_field): ?int {
  $name = 'Tiger Stadium: ' . pathinfo($file, PATHINFO_FILENAME);
  $ids = \Drupal::entityQuery('media')->accessCheck(FALSE)->condition('name', $name)->range(0, 1)->execute();
  if ($ids) {
    return (int) reset($ids);
  }
  $path = $images_dir . '/' . $file;
  if ($images_dir === '' || !is_file($path)) {
    echo "  Not found: $path (pass images=<folder>)." . PHP_EOL;
    return NULL;
  }
  if (!$apply) {
    return 0;
  }
  $fs->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  $uri = $fs->copy($path, "$destination/" . strtolower($file), FileExists::Rename);
  $saved = File::create(['uri' => $uri, 'status' => 1]);
  $saved->save();
  [$width, $height] = getimagesize($path) ?: [NULL, NULL];
  $item = Media::create([
    'bundle' => $media_type->id(),
    'name' => $name,
    'status' => 1,
    $source_field => [
      'target_id' => $saved->id(),
      'alt' => $alt,
      'width' => $width,
      'height' => $height,
    ],
  ]);
  $item->save();
  return (int) $item->id();
};

// Figures. --------------------------------------------------------------------

$added = 0;
$found = [];
foreach ($gallery->get('field_guide_blocks')->referencedEntities() as $entry) {
  $name = trim((string) ($entry->hasField('field_block_title') ? $entry->get('field_block_title')->value : ''));
  if ($entry->bundle() !== 'exhibit_entry' || !isset($pictures[$name])) {
    continue;
  }
  $found[$name] = TRUE;
  [$file, $alt, $caption] = $pictures[$name];
  $children = $entry->get('field_guide_blocks')->referencedEntities();
  if (array_filter($children, fn ($child) => $child->bundle() === 'exhibit_figure')) {
    echo "$name: has a picture already. Left alone." . PHP_EOL;
    continue;
  }
  $mid = $media($file, $alt);
  if ($mid === NULL) {
    echo "$name: skipped." . PHP_EOL;
    continue;
  }
  $say("$name: " . ($mid ? "media/$mid" : 'new media') . " ($file)" . ($caption !== '' ? ', with a caption' : '') . '.');
  $added++;
  if ($apply) {
    $values = [
      'type' => 'exhibit_figure',
      'field_figure_image' => ['target_id' => $mid],
      'field_figure_layout' => 'side_start',
    ];
    if ($caption !== '') {
      $values['field_figure_caption'] = ['value' => $caption, 'format' => $format ?? 'wysiwyg'];
    }
    $figure = Paragraph::create($values);
    // The picture first, as on the other narrators.
    array_unshift($children, $figure);
    $entry->set('field_guide_blocks', $children);
    $entry->setNewRevision(FALSE);
    $entry->save();
  }
}
foreach (array_diff_key($pictures, $found) as $name => $unused) {
  echo "$name: no entry with that title in The Narrators." . PHP_EOL;
}
echo ($apply ? "Added $added pictures." : "Would add $added pictures. Run again with apply=1.") . PHP_EOL;
