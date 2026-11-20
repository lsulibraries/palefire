<?php

/**
 * @file
 * Free People of Color in Louisiana: lead images for the galleries built
 * without one (The Collections, Terminology, Resources), from
 * data/fpoc.json, on the page palefire_exhibit_fpoc.php already built.
 *
 * Copy the "fpoc-web/leads" folder (2 JPEGs: the Warburg sketch and the Barès cover) to the server first, then:
 *
 *   drush php:script scripts/palefire_exhibit_fpoc_leads.php -- images=$HOME/fpoc-leads
 *   drush php:script scripts/palefire_exhibit_fpoc_leads.php -- images=$HOME/fpoc-leads apply=1
 *   drush cr
 *
 * A dry run unless apply=1. Only a gallery whose lead is empty is given
 * one, with its focus, "Show whole image" and caption; any other gallery,
 * or one an editor has already filled, is left alone. Images are imported
 * as "FPOC: <file name>" media under public://exhibitions/fpoc, or reused
 * when that media item exists (Terminology reuses the register page shown
 * in Decline and Civil War). Galleries are saved in place, without a new
 * revision. Safe to re-run.
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

$data = json_decode((string) file_get_contents(__DIR__ . '/data/fpoc.json'), TRUE);
if (!is_array($data) || empty($data['pages'])) {
  throw new \RuntimeException("Couldn't read data/fpoc.json.");
}
$wanted = [];
foreach ($data['pages'] as $page) {
  if (in_array($page['anchor'], ['collections', 'terminology', 'resources'], TRUE)) {
    $wanted[$page['anchor']] = $page;
  }
}

$ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', $data['title'])
  ->execute();
if (count($ids) !== 1) {
  throw new \RuntimeException('Expected one Modular page titled "' . $data['title'] . '", found ' . count($ids) . '. Run palefire_exhibit_fpoc.php first.');
}
$node = \Drupal::entityTypeManager()->getStorage('node')->load(reset($ids));
echo 'Page: ' . $node->label() . ' (node ' . $node->id() . ')' . PHP_EOL;

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
$destination = 'public://exhibitions/fpoc';

// The media item for a file: reused by name, else imported (apply only).
$media = function (string $file) use ($images_dir, $fs, $destination, $media_type, $source_field, $data, $apply): ?Media {
  $name = 'FPOC: ' . pathinfo($file, PATHINFO_FILENAME);
  $existing = \Drupal::entityQuery('media')->accessCheck(FALSE)
    ->condition('bundle', $media_type->id())->condition('name', $name)->range(0, 1)->execute();
  if ($existing) {
    echo "  $name: exists, reused" . PHP_EOL;
    return Media::load(reset($existing));
  }
  $path = $images_dir . '/' . $file;
  if ($images_dir === '' || !is_file($path)) {
    echo "  $name: NOT FOUND at $path (pass images=<the fpoc-web/leads folder on the server>)" . PHP_EOL;
    return NULL;
  }
  [$width, $height] = getimagesize($path) ?: [NULL, NULL];
  echo "  $name: new, {$width} x {$height}" . PHP_EOL;
  if (!$apply) {
    return NULL;
  }
  $fs->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
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
  return $entity;
};

// The captions' text format: the one the other leads use.
$format = NULL;
$sections = array_filter($node->get('field_scroll_section')->referencedEntities(), fn ($s) => $s->bundle() === 'exhibit_page');
foreach ($sections as $section) {
  if (!$format && $section->hasField('field_exhibit_lead_caption') && !$section->get('field_exhibit_lead_caption')->isEmpty()) {
    $format = $section->get('field_exhibit_lead_caption')->format;
  }
}

$changed = 0;
foreach ($sections as $section) {
  $anchor = (string) $section->get('field_section_anchor')->value;
  if (!isset($wanted[$anchor])) {
    continue;
  }
  $page = $wanted[$anchor];
  echo $section->get('field_section_title')->value . ':' . PHP_EOL;
  if (!$section->get('field_exhibit_lead')->isEmpty()) {
    echo '  has a lead already (' . $section->get('field_exhibit_lead')->entity?->label() . '), left alone' . PHP_EOL;
    continue;
  }
  $item = $media($page['lead']);
  if (!$apply) {
    $changed++;
    continue;
  }
  if (!$item) {
    continue;
  }
  $section->set('field_exhibit_lead', ['target_id' => $item->id()]);
  if ($section->hasField('field_exhibit_lead_focus')) {
    $section->set('field_exhibit_lead_focus', $page['focus'] ?? 'center');
  }
  if ($section->hasField('field_exhibit_lead_whole')) {
    $section->set('field_exhibit_lead_whole', 1);
  }
  if ($section->hasField('field_exhibit_lead_caption') && !empty($page['lead_caption']) && $format) {
    $section->set('field_exhibit_lead_caption', ['value' => $page['lead_caption'], 'format' => $format]);
  }
  // Saved in place, so the page's reference to this revision stays good.
  $section->setNewRevision(FALSE);
  $section->save();
  $changed++;
  echo '  lead set' . PHP_EOL;
}

if (!$apply) {
  echo "Dry run: $changed gallery leads to set. Nothing saved. Add apply=1 to save." . PHP_EOL;
  return;
}
\Drupal::entityTypeManager()->getStorage('node')->resetCache([$node->id()]);
echo "Saved $changed gallery leads. Run drush cr." . PHP_EOL;
