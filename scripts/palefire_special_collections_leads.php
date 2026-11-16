<?php

/**
 * @file
 * Gives every gallery on the two collection pages a lead image (the full
 * width picture at the top of a gallery, as Manuscripts has on the LLMVC
 * page), on pages already built by palefire_special_collections_pages.php.
 * That script now builds new pages with these leads too.
 *
 * Copy the new JPEGs into the "collections-images" folder on the server,
 * then:
 *
 *   drush php:script scripts/palefire_special_collections_leads.php -- images=$HOME/collections-images
 *   drush php:script scripts/palefire_special_collections_leads.php -- images=$HOME/collections-images apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it reports what it would do and saves nothing.
 *
 * Arguments after "--":
 *   images=<folder>  Required. The folder of JPEGs.
 *   apply=1          Save.
 *
 * Galleries are found by their anchor. A gallery that already has a lead
 * is left alone, so it is safe to re-run and never replaces a picture an
 * editor chose. Each image is imported once as an Image media item
 * ("Collections: <file name>", reused when it exists) under
 * public://special/collections.
 *
 * The galleries, files, captions and alt text are in
 * data/special-collections-leads.json. The images, from the LDL export's
 * JP2 masters (2400px on the long side):
 *   LLMVC
 *     Books                 llmvc-delisle-map.jpg      lsu-sc-lmp:7
 *     Photographs           llmvc-winans-capitol.jpg   lsu-sc-p15140coll46:48
 *     Political Papers      llmvc-long-platform.jpg    lsu-sc-hpl:9
 *     Louisiana Newspapers  llmvc-new-advocate.jpg     louisiananewspapers-eastbatonrouge:3
 *     Giving                llmvc-norman-natchez.jpg   lsu-sc-tjp:87
 *   Book Collections (manuscript facsimiles: the collections themselves
 *   have nothing on the LDL)
 *     Finding books         books-codex-aureus.jpg     lsu-sc-mmf:44
 *     Foundations           books-dioscorides.jpg      lsu-sc-mmf:60
 *     Poetry and fiction    books-cantigas.jpg         lsu-sc-mmf:34
 *     Imagination           books-beato.jpg            lsu-sc-mmf:23
 *     Games                 books-ajedrez.jpg          lsu-sc-mmf:80
 *     Subject collections   books-medicamentos.jpg     lsu-sc-mmf:92
 *
 * It stops, saving nothing, when a page isn't found or has a newer draft
 * than its published revision (a save here would bypass it).
 */

declare(strict_types=1);

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

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
$images_dir = rtrim($args['images'] ?? '', '/\\');
if ($images_dir === '' || !is_dir($images_dir)) {
  throw new \RuntimeException('Pass images=<folder>: the collections-images folder on this server.');
}
foreach (['field_exhibit_lead', 'field_exhibit_lead_focus'] as $name) {
  if (!FieldConfig::loadByName('paragraph', 'exhibit_page', $name)) {
    throw new \RuntimeException("paragraph.exhibit_page.$name not found. Run palefire_exhibit_setup.php first.");
  }
}
$has_caption = (bool) FieldConfig::loadByName('paragraph', 'exhibit_page', 'field_exhibit_lead_caption');

// alias => [gallery anchor => [file, caption, alt]], shared with
// palefire_special_collections_pages.php.
$leads = json_decode((string) file_get_contents(__DIR__ . '/data/special-collections-leads.json'), TRUE);
if (!is_array($leads) || !$leads) {
  throw new \RuntimeException('Couldn’t read data/special-collections-leads.json.');
}

// Captions use the WYSIWYG format, as the pages' text does.
$formats = filter_formats();
$format = NULL;
foreach ($formats as $id => $candidate) {
  if ($id === 'wysiwyg' || strcasecmp((string) $candidate->label(), 'WYSIWYG') === 0) {
    $format = $id;
    break;
  }
}

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
$destination = 'public://special/collections';

// Find each media item, or import it (apply only).
$media = function (string $file, string $alt) use ($apply, $images_dir, $fs, $destination, $media_type, $source_field): array {
  $name = 'Collections: ' . $file;
  $existing = \Drupal::entityQuery('media')->accessCheck(FALSE)
    ->condition('bundle', $media_type->id())->condition('name', $name)->range(0, 1)->execute();
  if ($existing) {
    return [(int) reset($existing), 'existing media'];
  }
  $path = "$images_dir/$file.jpg";
  if (!is_file($path)) {
    return [NULL, "missing: $path"];
  }
  if (!$apply) {
    return [NULL, "would import $file.jpg"];
  }
  $fs->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  $uri = $fs->copy($path, "$destination/$file.jpg", FileExists::Rename);
  $saved = File::create(['uri' => $uri, 'status' => 1]);
  $saved->save();
  [$width, $height] = getimagesize($path) ?: [NULL, NULL];
  $item = Media::create([
    'bundle' => $media_type->id(),
    'name' => $name,
    'status' => 1,
    $source_field => ['target_id' => $saved->id(), 'alt' => $alt, 'width' => $width, 'height' => $height],
  ]);
  $item->save();
  return [(int) $item->id(), "imported $file.jpg"];
};

$storage = \Drupal::entityTypeManager()->getStorage('node');
$nodes = [];
foreach (array_keys($leads) as $alias) {
  $path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
  if (!preg_match('#^/node/(\d+)$#', $path, $m) || !($node = $storage->load($m[1]))) {
    throw new \RuntimeException("No page at $alias. Run palefire_special_collections_pages.php first.");
  }
  $latest = $storage->getLatestRevisionId($node->id());
  if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
    throw new \RuntimeException("$alias has a newer draft (revision $latest) than its current revision. Publish or discard it first.");
  }
  $nodes[$alias] = $node;
}

foreach ($nodes as $alias => $node) {
  $changed = 0;
  $missing = $leads[$alias];
  foreach ($node->get('field_scroll_section')->referencedEntities() as $gallery) {
    if ($gallery->bundle() !== 'exhibit_page' || !$gallery->hasField('field_section_anchor')) {
      continue;
    }
    $anchor = (string) $gallery->get('field_section_anchor')->value;
    if (!isset($leads[$alias][$anchor])) {
      continue;
    }
    unset($missing[$anchor]);
    $label = $gallery->get('field_section_title')->value;
    if (!$gallery->get('field_exhibit_lead')->isEmpty()) {
      echo ($apply ? '' : '[dry run] ') . "$alias: \"$label\" already has a lead. Left alone." . PHP_EOL;
      continue;
    }
    [$file, $caption, $alt] = $leads[$alias][$anchor];
    [$id, $note] = $media($file, $alt);
    echo ($apply ? '' : '[dry run] ') . "$alias: \"$label\" gets $file.jpg ($note)." . PHP_EOL;
    if (!$id) {
      continue;
    }
    $gallery->set('field_exhibit_lead', ['target_id' => $id]);
    $gallery->set('field_exhibit_lead_focus', 'center');
    if ($has_caption && $format) {
      $gallery->set('field_exhibit_lead_caption', ['value' => '<p>' . $caption . '</p>', 'format' => $format]);
    }
    $gallery->setNeedsSave(TRUE);
    $changed++;
  }
  foreach (array_keys($missing) as $anchor) {
    echo "$alias: no gallery with the anchor \"$anchor\". Skipped." . PHP_EOL;
  }
  if ($apply && $changed) {
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage("Lead images for $changed galleries (palefire_special_collections_leads.php).");
    $node->save();
    echo "Saved a new revision of \"{$node->label()}\" ($changed leads)." . PHP_EOL;
  }
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}
echo 'Run: drush cr' . PHP_EOL;
