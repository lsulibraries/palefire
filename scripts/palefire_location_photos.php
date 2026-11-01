<?php

/**
 * @file
 * Location pages: photos of the room, and the Lecture Hall's three setups.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_location_photos.php
 *   drush php:script themes/custom/palefire/scripts/palefire_location_photos.php -- page=0
 *   drush cr
 *
 * Run after palefire_location_setup.php.
 *
 * - Location sections (location_space) gain "Photos" (field_location_photos):
 *   Image media items from the library, in order. The page shows them as
 *   "The room", its first part: each photo with its media name as the
 *   caption ("Lecture") and its alt text for screen readers.
 * - Image style pf_location_photo: 4:5, 800x1000, WebP.
 * - Content (skipped with page=0): the Lecture Hall's three setups from
 *   Special Collections' reservation page (lectureshape, conferenceushape,
 *   classroomshape in public://2024-01), each made an Image media item once
 *   and added to the page's Location section while its Photos are empty
 *   (force=1 replaces them). A new revision.
 *
 * Safe to re-run: anything that already exists is left as it is.
 */

declare(strict_types=1);

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\ParagraphsType;

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
$make_page = ($args['page'] ?? '1') !== '0';
$force = ($args['force'] ?? '0') === '1';

if (!ParagraphsType::load('location_space')) {
  throw new \RuntimeException('Paragraph type location_space not found. Run palefire_location_setup.php first.');
}

// The Image media type, as palefire_building_map_setup.php picks it.
$image_types = [];
foreach (\Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple() as $media_type) {
  if ($media_type->getSource()->getPluginId() === 'image') {
    $image_types[$media_type->id()] = $media_type->id();
  }
}
if (isset($image_types['image'])) {
  $image_types = ['image' => 'image'];
}
if (!$image_types) {
  echo "No Image media type found (Structure > Media types, source \"Image\"). Add one and re-run.\n";
  return;
}

// 1. The field. --------------------------------------------------------------
if (!FieldStorageConfig::loadByName('paragraph', 'field_location_photos')) {
  FieldStorageConfig::create([
    'field_name' => 'field_location_photos',
    'entity_type' => 'paragraph',
    'type' => 'entity_reference',
    'settings' => ['target_type' => 'media'],
    'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
  ])->save();
  echo "Created storage paragraph.field_location_photos\n";
}
if (!FieldConfig::loadByName('paragraph', 'location_space', 'field_location_photos')) {
  FieldConfig::create([
    'field_name' => 'field_location_photos',
    'entity_type' => 'paragraph',
    'bundle' => 'location_space',
    'label' => 'Photos',
    'description' => 'Optional. Photos of the room, in order, shown as "The room" (4:5, cropped from the middle). The media item\'s name is the caption, e.g. "Lecture" for a setup; its alt text describes what the photo shows. At least 800 pixels wide.',
    'settings' => ['handler' => 'default:media', 'handler_settings' => ['target_bundles' => $image_types, 'auto_create' => FALSE]],
  ])->save();
  echo "Added field_location_photos to paragraph.location_space\n";
}
$repo = \Drupal::service('entity_display.repository');
$form = $repo->getFormDisplay('paragraph', 'location_space', 'default');
if (!$form->getComponent('field_location_photos')) {
  $weight = (int) max(array_column($form->getComponents(), 'weight') ?: [0]);
  $form->setComponent('field_location_photos', (\Drupal::moduleHandler()->moduleExists('media_library')
    ? ['type' => 'media_library_widget', 'settings' => ['media_types' => array_values($image_types)]]
    : ['type' => 'entity_reference_autocomplete', 'settings' => ['match_operator' => 'CONTAINS', 'size' => 60, 'placeholder' => '']]) + ['weight' => $weight + 1])->save();
}
// The template prints them from the preprocess.
$view = $repo->getViewDisplay('paragraph', 'location_space', 'default');
if ($view->getComponent('field_location_photos')) {
  $view->removeComponent('field_location_photos')->save();
}

// 2. The image style. --------------------------------------------------------
if (!ImageStyle::load('pf_location_photo')) {
  $style = ImageStyle::create(['name' => 'pf_location_photo', 'label' => 'Location photo (800x1000)']);
  $style->addImageEffect(['id' => 'image_scale_and_crop', 'weight' => 0, 'data' => ['width' => 800, 'height' => 1000, 'anchor' => 'center-center']]);
  $style->addImageEffect(['id' => 'image_convert', 'weight' => 1, 'data' => ['extension' => 'webp']]);
  $style->save();
  echo "Created image style pf_location_photo\n";
}

if (!$make_page) {
  echo "Done (page=0). Run: drush cr\n";
  return;
}

// 3. The Lecture Hall's setups. ---------------------------------------------
$path = \Drupal::service('path_alias.manager')->getPathByAlias('/v2/special/lecturehall');
$node = preg_match('#^/node/(\d+)$#', $path, $m) ? Node::load((int) $m[1]) : NULL;
if (!$node) {
  echo "No page at /v2/special/lecturehall. Run palefire_location_setup.php first.\n";
  return;
}
$sections = $node->hasField('field_scroll_section') ? 'field_scroll_section' : 'field_sections';
$location = NULL;
foreach ($node->get($sections) as $item) {
  if ($item->entity && $item->entity->bundle() === 'location_space') {
    $location = $item->entity;
    break;
  }
}
if (!$location || !$location->hasField('field_location_photos')) {
  echo "The page has no Location section with Photos.\n";
  return;
}
if (!$force && !$location->get('field_location_photos')->isEmpty()) {
  echo "The Lecture Hall already has photos; left as they are (force=1 replaces them).\n";
  return;
}

$setups = [
  'lectureshape' => ['Lecture', 'The Lecture Hall set for a lecture: rows of chairs facing the front of the room, a portrait on the far wall.'],
  'conferenceushape' => ['Conference or U-shape', 'The Lecture Hall set as a conference: tables in a long U with chairs around the outside, tall windows along one side.'],
  'classroomshape' => ['Classroom', 'The Lecture Hall set as a classroom: rows of tables with chairs, all facing the podium at the front.'],
];
$media_type = reset($image_types);
$source_field = \Drupal::entityTypeManager()->getStorage('media_type')->load($media_type)->getSource()->getConfiguration()['source_field'];
$file_system = \Drupal::service('file_system');
$files = \Drupal::entityTypeManager()->getStorage('file');
$media_storage = \Drupal::entityTypeManager()->getStorage('media');
$ids = [];
foreach ($setups as $name => [$caption, $alt]) {
  $uri = "public://2024-01/$name.jpg";
  $file = current($files->loadByProperties(['uri' => $uri])) ?: NULL;
  if (!$file && !file_exists($uri)) {
    // Not on this server's disk (libstage): fetch the live copy.
    try {
      $data = (string) \Drupal::httpClient()->get("https://lib.lsu.edu/sites/default/files/2024-01/$name.jpg", ['timeout' => 20])->getBody();
    }
    catch (\Throwable $e) {
      echo "Could not fetch $name.jpg: {$e->getMessage()}\n";
      continue;
    }
    $directory = 'public://2024-01';
    $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    $uri = $file_system->saveData($data, $uri, FileExists::Replace);
  }
  if (!$file) {
    $file = File::create(['uri' => $uri, 'filename' => "$name.jpg", 'status' => 1, 'uid' => 1]);
    $file->save();
  }
  $existing = $media_storage->loadByProperties(['bundle' => $media_type, $source_field => $file->id()]);
  $media = current($existing) ?: NULL;
  if (!$media) {
    $media = Media::create([
      'bundle' => $media_type,
      'name' => $caption,
      'uid' => 1,
      'status' => 1,
      $source_field => ['target_id' => $file->id(), 'alt' => $alt],
    ]);
    $media->save();
    echo "Created media \"$caption\" ($name.jpg)\n";
  }
  $ids[] = ['target_id' => $media->id()];
}
if (!$ids) {
  echo "No photos to add.\n";
  return;
}

$location->set('field_location_photos', $ids);
$location->setNeedsSave(TRUE);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('Lecture Hall: the three setups as photos (palefire_location_photos.php).');
$node->save();
echo 'Added ' . count($ids) . ' photos to the Lecture Hall (node/' . $node->id() . ").\n";
echo "Done. Run: drush cr\n";
