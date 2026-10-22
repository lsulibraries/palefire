<?php

/**
 * @file
 * Swaps the file of an exhibition's Image media item for a new one, keeping
 * the media item (so every figure using it updates) and its alt text.
 *
 * For a better scan found after an exhibition was built, e.g. Audubon's
 * Black-throated Waxwing drawing (the full sheet in place of the 2021
 * close-up). Copy the new JPEG to the server, then:
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_replace_image.php -- media="Audubon: drawing-black-throated-waxwing-or-bohemian-chatterer-1" file=/tmp/drawing-black-throated-waxwing-or-bohemian-chatterer-1.jpg
 *   drush cr
 *
 * Arguments after "--":
 *   media=<name>  Required. The media item's name, as in Content > Media.
 *   file=<path>   Required. The new image (JPEG or PNG) on the server.
 *
 * The new file goes next to the old one (same directory, renamed if the
 * name is taken); the old file is left for Drupal to clean up once nothing
 * uses it. Image style derivatives follow the new file's own URL.
 */

declare(strict_types=1);

use Drupal\Core\File\FileExists;
use Drupal\file\Entity\File;

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

$name = $args['media'] ?? '';
$path = $args['file'] ?? '';
if ($name === '' || !is_file($path)) {
  throw new \RuntimeException('Pass media="<media name>" and file=<path to the new image on the server>.');
}
[$width, $height] = getimagesize($path) ?: [NULL, NULL];
if (!$width) {
  throw new \RuntimeException("$path is not an image this server can read.");
}

$storage = \Drupal::entityTypeManager()->getStorage('media');
$ids = $storage->getQuery()->accessCheck(FALSE)->condition('name', $name)->execute();
if (count($ids) !== 1) {
  throw new \RuntimeException('Expected one media item named "' . $name . '", found ' . count($ids) . '.');
}
$media = $storage->load(reset($ids));
$source = $media->getSource();
if ($source->getPluginId() !== 'image') {
  throw new \RuntimeException('"' . $name . '" is not an Image media item.');
}
$field = $source->getConfiguration()['source_field'];
$item = $media->get($field)->first();
$old = $item ? $item->entity : NULL;
if (!$old) {
  throw new \RuntimeException('"' . $name . '" has no file.');
}

/** @var \Drupal\Core\File\FileSystemInterface $fs */
$fs = \Drupal::service('file_system');
$directory = $fs->dirname($old->getFileUri());
$uri = $fs->copy($path, $directory . '/' . basename($path), FileExists::Rename);
$file = File::create(['uri' => $uri, 'status' => 1]);
$file->save();

$media->set($field, [
  'target_id' => $file->id(),
  'alt' => $item->alt,
  'title' => $item->title,
  'width' => $width,
  'height' => $height,
]);
$media->setNewRevision(TRUE);
$media->setRevisionLogMessage('New file from ' . basename($path) . ' (palefire_exhibit_replace_image.php).');
// The media library's thumbnail follows the new file.
$media->updateQueuedThumbnail();
$media->save();

echo "Replaced the file of \"$name\": {$old->getFileUri()} -> $uri ({$width}x{$height})." . PHP_EOL;
echo 'Run: drush cr' . PHP_EOL;
