<?php

/**
 * @file
 * Gives each oral history exhibition the picture its tile had on the old
 * Oral History page's Showcase (lib.lsu.edu/oralhistory#Showcase), as its
 * card picture: Cover image (field_image_cover), which cards use before the
 * exhibition cover (_palefire_card_image_item()). The exhibition's own
 * cover, its full-screen title page, is left as it is.
 *
 *   drush php:script scripts/palefire_oral_history_exhibit_covers.php
 *   drush php:script scripts/palefire_oral_history_exhibit_covers.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. The file is the tile's own (Tile cover), not a
 * copy: one Image media item per file, reused when it exists, named
 * "Oral history exhibition card: <file>", with the tile's alt text. Each
 * changed exhibition is saved in a new revision. An exhibition not built
 * yet, or not yet under /v2/oralhistory/exhibitions/, is skipped.
 */

declare(strict_types=1);

use Drupal\node\NodeInterface;

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
$apply = ($args['apply'] ?? '') === '1';
$prefix = $apply ? '' : '[dry run] ';

// The exhibition's address (its old folder) => the old Showcase tile.
$tiles = [
  'mossvilleexhibit' => 91045,
  'gu272' => 91044,
  'graphicdesign' => 91043,
  'klsu' => 50508,
  'storm' => 50506,
  'jf' => 50505,
  'lsuag' => 50504,
  'lady' => 50503,
  'rpoe' => 50502,
  'dntume' => 50501,
  'football' => 50500,
];

$nodes = \Drupal::entityTypeManager()->getStorage('node');
$media_storage = \Drupal::entityTypeManager()->getStorage('media');
$aliases = \Drupal::service('path_alias.manager');

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

foreach ($tiles as $folder => $tile_id) {
  $path = '/v2/oralhistory/exhibitions/' . $folder;
  $system = $aliases->getPathByAlias($path);
  $exhibition = preg_match('#^/node/(\d+)$#', $system, $m) ? $nodes->load($m[1]) : NULL;
  if (!$exhibition instanceof NodeInterface) {
    echo "{$prefix}$folder: no page at $path yet, skipped." . PHP_EOL;
    continue;
  }
  if (!$exhibition->hasField('field_image_cover')) {
    echo "{$prefix}$folder: node {$exhibition->id()} has no Cover image field, skipped." . PHP_EOL;
    continue;
  }
  $tile = $nodes->load($tile_id);
  $item = ($tile instanceof NodeInterface && $tile->hasField('field_tile_cover') && !$tile->get('field_tile_cover')->isEmpty())
    ? $tile->get('field_tile_cover')->first() : NULL;
  $file = $item ? $item->entity : NULL;
  if (!$file) {
    echo "{$prefix}$folder: tile $tile_id has no Tile cover, skipped." . PHP_EOL;
    continue;
  }

  $name = 'Oral history exhibition card: ' . $file->getFilename();
  $found = $media_storage->getQuery()->accessCheck(FALSE)
    ->condition('bundle', $media_type->id())->condition('name', $name)->range(0, 1)->execute();
  $current = $exhibition->get('field_image_cover')->target_id;
  $size = $item->width && $item->height ? " ({$item->width}×{$item->height})" : '';
  if ($found && (int) $current === (int) reset($found)) {
    echo "{$prefix}$folder: already has {$file->getFilename()}." . PHP_EOL;
    continue;
  }
  echo "{$prefix}$folder (node {$exhibition->id()}): card picture {$file->getFilename()}$size, from tile $tile_id" . ($current ? ', replacing media ' . $current : '') . '.' . PHP_EOL;
  if (!$apply) {
    continue;
  }

  if ($found) {
    $media_id = (int) reset($found);
  }
  else {
    $media = $media_storage->create([
      'bundle' => $media_type->id(),
      'name' => $name,
      'uid' => 1,
      'status' => 1,
      $source_field => [
        'target_id' => $file->id(),
        'alt' => trim((string) $item->alt) ?: $exhibition->label(),
        'width' => $item->width,
        'height' => $item->height,
      ],
    ]);
    $media->save();
    $media_id = (int) $media->id();
  }
  $exhibition->set('field_image_cover', ['target_id' => $media_id]);
  $exhibition->setNewRevision(TRUE);
  $exhibition->setRevisionLogMessage('Card picture from the old Oral History Showcase (palefire_oral_history_exhibit_covers.php).');
  $exhibition->save();
}

if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
}
