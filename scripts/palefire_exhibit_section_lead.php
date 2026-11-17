<?php

/**
 * @file
 * Sets the lead image (the large cover at the top of a gallery) of one
 * gallery on an exhibition page that is already built, found by the page's
 * title and the gallery's anchor. For a picture chosen after the build,
 * e.g. Mossville's About the Project (the project's sign on an interview
 * table). Copy the JPEG to the server, then:
 *
 *   drush php:script scripts/palefire_exhibit_section_lead.php -- page="Mossville History Project" anchor=about file=$HOME/mossville-about-cover.jpg prefix=Mossville alt="The Mossville History Project sign, Come Tell Your Story, on an interview table with water bottles and a recorder."
 *   drush php:script scripts/palefire_exhibit_section_lead.php -- page="Mossville History Project" anchor=about file=$HOME/mossville-about-cover.jpg prefix=Mossville alt="The Mossville History Project sign, Come Tell Your Story, on an interview table with water bottles and a recorder." apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it reports what it would do and saves nothing.
 *
 * Arguments after "--":
 *   page=<title>      Required. The exhibition page's title.
 *   anchor=<anchor>   Required. The gallery's anchor (its #link).
 *   file=<path>       Required. The image (JPEG or PNG) on the server.
 *   alt=<text>        Required. Alt text for the image.
 *   prefix=<name>     The media item's name prefix, as the build used
 *                     ("Mossville" makes "Mossville: <file name>"; reused
 *                     when it exists). Default: the page's title.
 *   dir=<uri>         Where the file goes. Default: the folder of the
 *                     page's first lead image, else public://exhibitions.
 *   caption=<html>    The lead's caption (credit). Default: left as it is.
 *   focus=<value>     The crop's focus (center, top, bottom …). Default:
 *                     left as it is.
 *   apply=1           Save.
 *
 * A gallery whose lead is already set is given the new one (the old media
 * item is kept). The gallery is saved in place, without a new revision.
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

$title = $args['page'] ?? '';
$anchor = $args['anchor'] ?? '';
$path = $args['file'] ?? '';
$alt = $args['alt'] ?? '';
$apply = ($args['apply'] ?? '') === '1';
if ($title === '' || $anchor === '' || $alt === '' || !is_file($path)) {
  throw new \RuntimeException('Pass page="<title>", anchor=<anchor>, file=<path to the image on the server> and alt="<alt text>".');
}
[$width, $height] = getimagesize($path) ?: [NULL, NULL];
if (!$width) {
  throw new \RuntimeException("$path is not an image this server can read.");
}

$ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', $title)
  ->execute();
if (count($ids) !== 1) {
  throw new \RuntimeException('Expected one Modular page titled "' . $title . '", found ' . count($ids) . '.');
}
$node = \Drupal::entityTypeManager()->getStorage('node')->load(reset($ids));

$gallery = NULL;
$first_lead = NULL;
foreach ($node->get('field_scroll_section')->referencedEntities() as $section) {
  if ($section->bundle() !== 'exhibit_page') {
    continue;
  }
  if (!$first_lead && $section->hasField('field_exhibit_lead') && !$section->get('field_exhibit_lead')->isEmpty()) {
    $first_lead = $section->get('field_exhibit_lead')->entity;
  }
  if ($section->get('field_section_anchor')->value === $anchor) {
    $gallery = $section;
  }
}
if (!$gallery) {
  throw new \RuntimeException("No gallery with the anchor '$anchor' on node " . $node->id() . '.');
}
echo 'Page: ' . $node->label() . ' (node ' . $node->id() . ')' . PHP_EOL;
echo 'Gallery: ' . $gallery->get('field_section_title')->value . PHP_EOL;
$current = $gallery->get('field_exhibit_lead')->entity;
echo 'Current lead: ' . ($current ? $current->label() : 'none') . PHP_EOL;

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

/** @var \Drupal\Core\File\FileSystemInterface $fs */
$fs = \Drupal::service('file_system');
$directory = $args['dir'] ?? '';
if ($directory === '' && $first_lead && !$first_lead->get($source_field)->isEmpty()) {
  $directory = $fs->dirname($first_lead->get($source_field)->entity->getFileUri());
}
$directory = $directory !== '' ? $directory : 'public://exhibitions';

$name = ($args['prefix'] ?? $title) . ': ' . pathinfo($path, PATHINFO_FILENAME);
$existing = \Drupal::entityQuery('media')->accessCheck(FALSE)
  ->condition('bundle', $media_type->id())->condition('name', $name)->range(0, 1)->execute();
echo 'New lead: ' . $name . ($existing ? ' (exists, reused)' : " ({$width} x {$height}, to $directory)") . PHP_EOL;

if (!$apply) {
  echo 'Dry run: nothing saved. Add apply=1 to save.' . PHP_EOL;
  return;
}

if ($existing) {
  $media = Media::load(reset($existing));
}
else {
  $fs->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  $safe = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(pathinfo($path, PATHINFO_FILENAME))), '-') . '.' . strtolower(pathinfo($path, PATHINFO_EXTENSION));
  $uri = $fs->copy($path, "$directory/$safe", FileExists::Rename);
  $file = File::create(['uri' => $uri, 'status' => 1]);
  $file->save();
  $media = Media::create([
    'bundle' => $media_type->id(),
    'name' => $name,
    'status' => 1,
    $source_field => [
      'target_id' => $file->id(),
      'alt' => $alt,
      'width' => $width,
      'height' => $height,
    ],
  ]);
  $media->save();
  echo 'Imported ' . $name . PHP_EOL;
}

$gallery->set('field_exhibit_lead', ['target_id' => $media->id()]);
if (isset($args['focus']) && $gallery->hasField('field_exhibit_lead_focus')) {
  $gallery->set('field_exhibit_lead_focus', $args['focus']);
}
if (isset($args['caption']) && $gallery->hasField('field_exhibit_lead_caption')) {
  $format = $gallery->get('field_exhibit_lead_caption')->format ?: 'wysiwyg';
  $gallery->set('field_exhibit_lead_caption', ['value' => $args['caption'], 'format' => $format]);
}
// Saved in place (no new revision), so the page's reference to this
// revision of the gallery stays good.
$gallery->setNewRevision(FALSE);
$gallery->save();
\Drupal::entityTypeManager()->getStorage('node')->resetCache([$node->id()]);
echo 'Saved. Run drush cr.' . PHP_EOL;
