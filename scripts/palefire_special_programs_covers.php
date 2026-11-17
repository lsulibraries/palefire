<?php

/**
 * @file
 * Covers and citations for the four program pages in Time in the Archive
 * on /v2/special, so the section's rows show a picture behind it on hover
 * (js/ledger-photo.js), as What Hill Holds does. Each picture is an item
 * from the LSU Special Collections on the Louisiana Digital Library:
 *   Teaching             programs-byrne-euclid.jpg          lsu-sc-sce:22
 *   Faculty Fellowship   programs-kircher-musurgia.jpg      lsu-sc-sce:82
 *   Research Grants      programs-louisiana-routes-map.jpg  lsu-sc-lmp:77
 *   Summer Project       programs-spell-tractor.jpg         lsu-sc-sce:93
 *
 * Copy the JPEGs into the "collections-images" folder on the server, then:
 *
 *   drush php:script scripts/palefire_special_programs_covers.php -- images=$HOME/collections-images
 *   drush php:script scripts/palefire_special_programs_covers.php -- images=$HOME/collections-images apply=1
 *   drush cr
 *
 * A dry run unless apply=1. Sets the card cover only (Cover image,
 * field_image_cover): the pages' own tops are unchanged. A page that
 * already has a cover keeps it. Fills only empty cover label fields
 * (field_exhibit_cover_*, palefire_exhibit_cover_label.php). Each image is
 * imported once as an Image media item ("Collections: <file name>", reused
 * when it exists) under public://special/collections. Safe to re-run. It
 * stops, saving nothing, on a page that has a newer draft than its current
 * revision.
 */

declare(strict_types=1);

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
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

// alias => [file, alt, label].
$pages = [
  '/v2/special/programs/teaching' => [
    'programs-byrne-euclid',
    'Two pages of Byrne’s Euclid, the proofs drawn in red, yellow and blue squares, triangles and circles.',
    [
      'maker' => 'Oliver Byrne (1810–1880)',
      'title' => 'The First Six Books of the Elements of Euclid',
      'date' => '1847',
      'medium' => 'Printed in color for William Pickering, London',
      'url' => 'https://louisianadigitallibrary.org/islandora/object/lsu-sc-sce:22',
    ],
  ],
  '/v2/special/programs/faculty-fellowship' => [
    'programs-kircher-musurgia',
    'Engraved frontispiece of Kircher’s Musurgia Universalis: a globe under the heavens, with muses and figures making music.',
    [
      'maker' => 'Athanasius Kircher (1602–1680)',
      'title' => 'Musurgia Universalis',
      'date' => '1650',
      'medium' => 'Engraved frontispiece, Rome',
      'url' => 'https://louisianadigitallibrary.org/islandora/object/lsu-sc-sce:82',
    ],
  ],
  '/v2/special/programs/research-grants' => [
    'programs-louisiana-routes-map',
    'A colored 1850 map of Louisiana, its parishes, rivers, roads and steamboat routes.',
    [
      'maker' => 'Thomas, Cowperthwait & Co.',
      'title' => 'New Map of Louisiana, with Its Canals, Roads & Distances',
      'date' => '1850',
      'medium' => 'Map, Philadelphia. G4010 1850 .T56',
      'url' => 'https://louisianadigitallibrary.org/islandora/object/lsu-sc-lmp:77',
    ],
  ],
  '/v2/special/programs/summer-project' => [
    'programs-spell-tractor',
    'Two young women posing on an old farm tractor under oak trees.',
    [
      'maker' => 'Viela Spell',
      'title' => 'Tractor Gals',
      'medium' => 'Photograph, Crowley, Louisiana. Viela Spell Photograph Collection, Mss. 4633',
      'url' => 'https://louisianadigitallibrary.org/islandora/object/lsu-sc-sce:93',
    ],
  ],
];

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

// Check every page first, so a stop saves nothing.
$storage = \Drupal::entityTypeManager()->getStorage('node');
$nodes = [];
foreach (array_keys($pages) as $alias) {
  $path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
  if (!preg_match('#^/node/(\d+)$#', $path, $m) || !($node = $storage->load($m[1]))) {
    throw new \RuntimeException("No page at $alias.");
  }
  if (!$node->hasField('field_image_cover')) {
    throw new \RuntimeException("$alias has no Cover image field (field_image_cover).");
  }
  $latest = $storage->getLatestRevisionId($node->id());
  if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
    throw new \RuntimeException("$alias has a newer draft (revision $latest). Publish or discard it first.");
  }
  $nodes[$alias] = $node;
}

foreach ($pages as $alias => [$file, $alt, $label]) {
  $node = $nodes[$alias];
  $done = [];
  if ($node->get('field_image_cover')->isEmpty()) {
    [$media_id, $note] = $media($file, $alt);
    $done[] = "cover ($note)";
    if ($media_id) {
      $node->set('field_image_cover', ['target_id' => $media_id]);
    }
  }
  else {
    echo "$alias: has a cover already; kept." . PHP_EOL;
  }
  foreach ($label as $key => $value) {
    $field = 'field_exhibit_cover_' . $key;
    if ($node->hasField($field) && $node->get($field)->isEmpty()) {
      $node->set($field, $value);
      $done[] = "label $key";
    }
  }
  if (!$done) {
    echo "$alias: nothing to do." . PHP_EOL;
    continue;
  }
  echo ($apply ? '' : '[dry run] ') . "$alias: " . implode(', ', $done) . '.' . PHP_EOL;
  if ($apply) {
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Card cover and its citation (palefire_special_programs_covers.php).');
    $node->save();
  }
}
echo $apply ? 'Run: drush cr' . PHP_EOL : 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
