<?php

/**
 * @file
 * AVES: brings the existing AVES page up to date with data/aves.json.
 *
 * For a site where palefire_exhibit_aves.php already built the page. Copy
 * the "Section 4 (additions)" folder (JPEGs made from the catalogue's TIFFs
 * in aves_folder/additional images) next to the other "Section …" folders
 * on the server, then:
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_aves_update.php -- images="/path/to/AVES exhibition 4K"
 *   drush cr
 *
 * Optional after "--": format=<text format> (default: WYSIWYG); force=1 to
 * also write lead captions that still carry an "[Editor: …]" line on a
 * published page (they are skipped otherwise).
 *
 * What it changes, and only where the page still has what the build
 * scripts wrote (an editor's own changes are kept):
 * - Entry names written in capitals get the Capital Case of the data file
 *   (Francisco Hernández, Georg Markgraf, Juan Ignacio Molina, …).
 * - "Middleton Library" becomes "Main Library" in every text of the page.
 * - Figures without an image get theirs from "Section 4 (additions)" (16 of
 *   the 17 the 2012 site showed; Schomburgk's Twelve Views has no file).
 * - Lead image captions: empty ones, and drafts still starting with
 *   "[Editor:", get the data file's text, taken from the print catalogue's
 *   illustration lists.
 *
 * Saves the page as a new revision. Safe to re-run.
 */

declare(strict_types=1);

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;

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

$images_dir = rtrim($args['images'] ?? '', '/\\');
if ($images_dir === '' || !is_dir($images_dir . '/Section 4 (additions)')) {
  throw new \RuntimeException('Pass images=<folder>: the "AVES exhibition 4K" folder on the server, with "Section 4 (additions)" in it.');
}

$data = json_decode((string) file_get_contents(__DIR__ . '/data/aves.json'), TRUE);
if (!is_array($data) || count($data['pages'] ?? []) !== 19) {
  throw new \RuntimeException("Couldn't read data/aves.json.");
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

$ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', 'AVES')
  ->execute();
if (count($ids) !== 1) {
  throw new \RuntimeException('Expected one Modular page titled "AVES", found ' . count($ids) . '.');
}
$node = Node::load(reset($ids));
$published = $node->isPublished();

// The data file, indexed. ----------------------------------------------------

$entries = [];
$leads = [];
$walk = function (array $blocks) use (&$walk, &$entries): void {
  foreach ($blocks as $block) {
    if ($block['type'] === 'entry') {
      $figures = [];
      foreach ($block['blocks'] as $child) {
        foreach ($child['type'] === 'figure' ? [$child] : ($child['figures'] ?? []) as $figure) {
          $figures[] = $figure['image'] ?? NULL;
        }
      }
      $entries[$block['anchor']] = ['title' => $block['title'], 'figures' => $figures];
    }
  }
};
foreach ($data['pages'] as $page) {
  $walk($page['blocks']);
  if (!empty($page['lead_caption'])) {
    $leads[$page['title']] = $page['lead_caption'];
  }
}

// Images (as in palefire_exhibit_aves.php). ---------------------------------

$media_type = NULL;
foreach (\Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple() as $candidate) {
  if ($candidate->getSource()->getPluginId() === 'image' && ($media_type === NULL || $candidate->id() === 'image')) {
    $media_type = $candidate;
  }
}
$source_field = $media_type->getSource()->getConfiguration()['source_field'];
/** @var \Drupal\Core\File\FileSystemInterface $fs */
$fs = \Drupal::service('file_system');
$destination = 'public://exhibitions/aves';
$fs->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

$media = function (string $relative) use ($images_dir, $fs, $destination, $media_type, $source_field, $data): ?int {
  $name = 'AVES: ' . pathinfo($relative, PATHINFO_FILENAME);
  $existing = \Drupal::entityQuery('media')->accessCheck(FALSE)
    ->condition('bundle', $media_type->id())->condition('name', $name)->range(0, 1)->execute();
  if ($existing) {
    return (int) reset($existing);
  }
  $path = $images_dir . '/' . $relative;
  if (!is_file($path)) {
    echo "Image not found: $path" . PHP_EOL;
    return NULL;
  }
  $safe = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(pathinfo($relative, PATHINFO_FILENAME))), '-') . '.jpg';
  $file = File::create(['uri' => $fs->copy($path, "$destination/$safe", FileExists::Rename), 'status' => 1]);
  $file->save();
  [$width, $height] = getimagesize($path) ?: [NULL, NULL];
  $item = Media::create([
    'bundle' => $media_type->id(),
    'name' => $name,
    'status' => 1,
    $source_field => ['target_id' => $file->id(), 'alt' => $data['alt'][$relative] ?? '', 'width' => $width, 'height' => $height],
  ]);
  $item->save();
  echo "Imported $relative" . PHP_EOL;
  return (int) $item->id();
};

// Changes. ---------------------------------------------------------------------

$log = [];
$text_fields = ['field_block_body', 'field_figure_caption', 'field_entry_bio', 'field_exhibit_lead_caption', 'field_audio_transcript'];

// "Middleton Library" -> "Main Library" in a paragraph's texts.
$rename_library = function (FieldableEntityInterface $paragraph) use ($text_fields, &$log): void {
  foreach ($text_fields as $name) {
    if (!$paragraph->hasField($name) || $paragraph->get($name)->isEmpty()) {
      continue;
    }
    $value = (string) $paragraph->get($name)->value;
    if (str_contains($value, 'Middleton Library')) {
      $paragraph->get($name)->value = str_replace('Middleton Library', 'Main Library', $value);
      $paragraph->setNeedsSave(TRUE);
      $log[] = 'Main Library: ' . $paragraph->bundle() . ' ' . $paragraph->id();
    }
  }
};

// Every paragraph under a block list, depth first. A changed paragraph
// marks its parents too: a nested paragraph is only saved when its parent
// is (entity_reference_revisions saves the children of what it saves).
$descend = function (FieldableEntityInterface $paragraph, callable $visit) use (&$descend): bool {
  $visit($paragraph);
  foreach (['field_guide_blocks', 'field_gallery_items', 'field_accordion_items'] as $name) {
    if ($paragraph->hasField($name)) {
      foreach ($paragraph->get($name)->referencedEntities() as $child) {
        if ($descend($child, $visit)) {
          $paragraph->setNeedsSave(TRUE);
        }
      }
    }
  }
  return $paragraph->needsSave();
};

foreach ($node->get('field_scroll_section')->referencedEntities() as $gallery) {
  if ($gallery->bundle() !== 'exhibit_page') {
    continue;
  }
  $title = (string) $gallery->get('field_section_title')->value;

  // Lead caption: empty, or still a draft.
  if (isset($leads[$title]) && $gallery->hasField('field_exhibit_lead_caption')) {
    $current = (string) $gallery->get('field_exhibit_lead_caption')->value;
    $draft = str_starts_with(trim(strip_tags($current)), '[Editor:');
    $new_draft = str_contains($leads[$title], '[Editor:');
    if ((trim(strip_tags($current)) === '' || $draft) && $current !== $leads[$title]) {
      if ($new_draft && $published && empty($args['force'])) {
        $log[] = "Skipped draft caption (page is published): $title";
      }
      else {
        $gallery->set('field_exhibit_lead_caption', ['value' => $leads[$title], 'format' => $format]);
        $gallery->setNeedsSave(TRUE);
        $log[] = "Lead caption: $title";
      }
    }
  }

  $descend($gallery, function (FieldableEntityInterface $paragraph) use ($rename_library, $entries, $media, &$log): void {
    $rename_library($paragraph);
    if ($paragraph->bundle() !== 'exhibit_entry') {
      return;
    }
    $anchor = (string) $paragraph->get('field_section_anchor')->value;
    if (!isset($entries[$anchor])) {
      return;
    }
    // A name the build wrote in capitals.
    $name = (string) $paragraph->get('field_block_title')->value;
    if ($name !== $entries[$anchor]['title'] && preg_replace('/[^\p{L}]/u', '', $name) === mb_strtoupper(preg_replace('/[^\p{L}]/u', '', $name))) {
      $paragraph->set('field_block_title', $entries[$anchor]['title']);
      $paragraph->setNeedsSave(TRUE);
      $log[] = "Name: $name -> {$entries[$anchor]['title']}";
    }
    // Figures in the entry's order; fill the empty ones.
    $index = 0;
    foreach ($paragraph->get('field_guide_blocks')->referencedEntities() as $block) {
      $figures = $block->bundle() === 'exhibit_figure' ? [$block] : ($block->bundle() === 'exhibit_gallery' ? $block->get('field_gallery_items')->referencedEntities() : []);
      foreach ($figures as $figure) {
        $relative = $entries[$anchor]['figures'][$index++] ?? NULL;
        if ($relative && $figure->get('field_figure_image')->isEmpty() && str_starts_with($relative, 'Section 4 (additions)/')) {
          $id = $media($relative);
          if ($id) {
            $figure->set('field_figure_image', ['target_id' => $id]);
            $figure->setNeedsSave(TRUE);
            $log[] = "Image: $anchor <- $relative";
          }
        }
      }
    }
  });
}

if ($log) {
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage('AVES: names, Main Library, images and lead captions (palefire_exhibit_aves_update.php).');
  $node->save();
}
foreach ($log as $line) {
  echo $line . PHP_EOL;
}
echo 'Done: ' . count($log) . ' change(s). Run: drush cr' . PHP_EOL;
