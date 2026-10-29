<?php

/**
 * @file
 * Audubon: the Manuscripts gallery's whole pages and transcriptions, on a
 * site where palefire_exhibit_audubon.php already built the page.
 *
 * Copy the "manuscripts" folder of "Audubon 4K" (nine JPEGs made from the
 * Special Collections TIFFs, long edge 3,840px) to the server, then:
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_audubon_manuscripts.php -- images=/tmp/audubon-4k
 *   drush cr
 *
 * Arguments after "--":
 *   images=<folder>       Required. The folder holding "manuscripts/".
 *   format=<text format>  Text format for the text (default: WYSIWYG).
 *   force=1               Write the transcriptions even if the format's
 *                         allowed HTML seems to strip <s> (struck words).
 *
 * From data/audubon.json, by entry anchor:
 * - The Hurricane and The Bear: their 2021 close-up becomes an image group
 *   of their four pages (the object view turns them, js/exhibition.js).
 *   Children's Warbler's figure gets its whole page.
 * - A transcription after each entry's text, with a heading per page.
 *
 * Only figures still showing the 2021 close-up are replaced, and an entry
 * that already has a transcription keeps it, so an editor's own changes
 * are kept. The close-ups stay in the media library (one is the gallery's
 * lead). Saves the page as a new revision. Safe to re-run.
 */

declare(strict_types=1);

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
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

$images_dir = rtrim($args['images'] ?? '', '/\\');
if ($images_dir === '' || !is_dir($images_dir . '/manuscripts')) {
  throw new \RuntimeException('Pass images=<folder>: the "Audubon 4K" folder on the server, with "manuscripts" in it.');
}

$data = json_decode((string) file_get_contents(__DIR__ . '/data/audubon.json'), TRUE);
$gallery_data = NULL;
foreach ($data['pages'] ?? [] as $page) {
  if (($page['anchor'] ?? '') === 'manuscripts') {
    $gallery_data = $page;
  }
}
if (!$gallery_data) {
  throw new \RuntimeException("Couldn't read the Manuscripts gallery from data/audubon.json.");
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
// Struck words are the transcription's deletions: without <s> they would
// read as Audubon's text.
$html_filter = $formats[$format]->filters('filter_html');
$allowed = $html_filter && $html_filter->status ? (string) ($html_filter->settings['allowed_html'] ?? '') : '';
if ($allowed !== '' && !preg_match('/<s[\s>]/', $allowed) && empty($args['force'])) {
  throw new \RuntimeException("The $format format's allowed HTML has no <s>, so struck-through words would lose their strike. Add <s> in Configuration > Text formats (or the editor's Strikethrough button), or pass force=1.");
}

$ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', $data['title'])
  ->execute();
if (count($ids) !== 1) {
  throw new \RuntimeException('Expected one Modular page titled "' . $data['title'] . '", found ' . count($ids) . '.');
}
$node = Node::load(reset($ids));

// Images (as in palefire_exhibit_audubon.php). ------------------------------

$media_type = NULL;
foreach (\Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple() as $candidate) {
  if ($candidate->getSource()->getPluginId() === 'image' && ($media_type === NULL || $candidate->id() === 'image')) {
    $media_type = $candidate;
  }
}
$source_field = $media_type->getSource()->getConfiguration()['source_field'];
/** @var \Drupal\Core\File\FileSystemInterface $fs */
$fs = \Drupal::service('file_system');
$destination = 'public://exhibitions/audubon';
$fs->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

$media = function (string $relative) use ($images_dir, $fs, $destination, $media_type, $source_field, $data): int {
  $name = 'Audubon: ' . pathinfo($relative, PATHINFO_FILENAME);
  $existing = \Drupal::entityQuery('media')->accessCheck(FALSE)
    ->condition('bundle', $media_type->id())->condition('name', $name)->range(0, 1)->execute();
  if ($existing) {
    return (int) reset($existing);
  }
  $path = $images_dir . '/' . $relative;
  if (!is_file($path)) {
    throw new \RuntimeException("Image not found: $path");
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

// Whether a figure still shows its 2021 close-up.
$crops = ['Audubon: manuscript-childrens-warbler-manuscript', 'Audubon: manuscript-the-bear', 'Audubon: manuscript-the-hurricane'];
$is_crop = function (FieldableEntityInterface $figure) use ($crops): bool {
  $item = $figure->get('field_figure_image')->entity;
  return $item && in_array((string) $item->label(), $crops, TRUE);
};

// Changes. ---------------------------------------------------------------------

$log = [];
foreach ($node->get('field_scroll_section')->referencedEntities() as $gallery) {
  if ($gallery->bundle() !== 'exhibit_page' || (string) $gallery->get('field_section_anchor')->value !== 'manuscripts') {
    continue;
  }
  foreach ($gallery->get('field_guide_blocks')->referencedEntities() as $entry) {
    if ($entry->bundle() !== 'exhibit_entry') {
      continue;
    }
    $anchor = (string) $entry->get('field_section_anchor')->value;
    $want = NULL;
    foreach ($gallery_data['blocks'] as $b) {
      if (($b['anchor'] ?? '') === $anchor) {
        $want = $b;
      }
    }
    if (!$want) {
      continue;
    }
    $blocks = $entry->get('field_guide_blocks')->referencedEntities();
    $changed = FALSE;

    // The pages, in place of the close-up.
    foreach ($want['blocks'] as $b) {
      if (!in_array($b['type'], ['figure', 'gallery'], TRUE)) {
        continue;
      }
      foreach ($blocks as $i => $block) {
        if ($block->bundle() !== 'exhibit_figure' || !$is_crop($block)) {
          continue;
        }
        if ($b['type'] === 'figure') {
          $block->set('field_figure_image', ['target_id' => $media($b['image'])]);
          $block->setNeedsSave(TRUE);
        }
        else {
          $blocks[$i] = Paragraph::create([
            'type' => 'exhibit_gallery',
            'field_gallery_items' => array_map(fn ($f) => Paragraph::create([
              'type' => 'exhibit_figure',
              'field_figure_image' => ['target_id' => $media($f['image'])],
              'field_figure_layout' => 'center',
            ]), $b['figures']),
          ]);
        }
        $changed = TRUE;
        $log[] = "Pages: $anchor (" . ($b['type'] === 'figure' ? 1 : count($b['figures'])) . ')';
        break;
      }
    }

    // The transcription, after the entry's text.
    $has = FALSE;
    foreach ($blocks as $block) {
      $has = $has || ($block->bundle() === 'guide_text' && str_contains((string) $block->get('field_block_body')->value, '<h4>Transcription</h4>'));
    }
    foreach ($has ? [] : $want['blocks'] as $b) {
      if ($b['type'] === 'text' && str_contains($b['body'], '<h4>Transcription</h4>')) {
        $blocks[] = Paragraph::create([
          'type' => 'guide_text',
          'field_block_body' => ['value' => $b['body'], 'format' => $format],
          'field_text_style' => 'default',
        ]);
        $changed = TRUE;
        $log[] = "Transcription: $anchor";
      }
    }

    if ($changed) {
      $entry->set('field_guide_blocks', array_values($blocks));
      $entry->setNeedsSave(TRUE);
      $gallery->setNeedsSave(TRUE);
    }
  }
}

if ($log) {
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage('Audubon: manuscript pages and transcriptions (palefire_exhibit_audubon_manuscripts.php).');
  $node->save();
}
foreach ($log as $line) {
  echo $line . PHP_EOL;
}
echo 'Done: ' . count($log) . ' change(s). Run: drush cr' . PHP_EOL;
