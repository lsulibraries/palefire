<?php

/**
 * @file
 * Audubon: shows Elephant Folio, Octavo Edition and Drawings and Proofs as
 * walls, on a site where palefire_exhibit_audubon.php already built the
 * page. Re-run palefire_exhibit_setup.php first (it adds the Display and
 * Sheet size fields), then:
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_audubon_wall.php
 *   drush cr
 *
 * Optional after "--": format=<text format> (default: WYSIWYG).
 *
 * From data/audubon.json, by gallery title and entry anchor:
 * - Display "wall" on those three galleries, and the editions' sheet sizes
 *   (40 × 27 in, 11 × 7 in) for the object view's "Actual size".
 * - "Drawing" and "Proof" captions on the 11 drawing-and-proof pairs, which
 *   name the tile and the comparison slider. Only empty captions are
 *   filled, and a gallery's Display or sheet size is set only where it is
 *   still empty or "story", so an editor's own choices are kept.
 *
 * Saves the page as a new revision. Safe to re-run.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
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

foreach (['field_exhibit_display', 'field_exhibit_sheet'] as $name) {
  if (!FieldConfig::loadByName('paragraph', 'exhibit_page', $name)) {
    throw new \RuntimeException("$name is not on Gallery: run palefire_exhibit_setup.php first.");
  }
}

$data = json_decode((string) file_get_contents(__DIR__ . '/data/audubon.json'), TRUE);
if (!is_array($data) || count($data['pages'] ?? []) !== 7) {
  throw new \RuntimeException("Couldn't read data/audubon.json.");
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
  ->condition('title', $data['title'])
  ->execute();
if (count($ids) !== 1) {
  throw new \RuntimeException('Expected one Modular page titled "' . $data['title'] . '", found ' . count($ids) . '.');
}
$node = Node::load(reset($ids));

// The data file, indexed: each gallery's display and sheet, and each
// entry's image-group figure captions.
$galleries = [];
$pairs = [];
foreach ($data['pages'] as $page) {
  $galleries[$page['title']] = ['display' => $page['display'] ?? 'story', 'sheet' => $page['sheet'] ?? NULL];
  foreach ($page['blocks'] as $block) {
    if ($block['type'] !== 'entry') {
      continue;
    }
    foreach ($block['blocks'] as $child) {
      if ($child['type'] === 'gallery') {
        $pairs[$block['anchor']] = array_map(fn ($figure) => $figure['caption'], $child['figures']);
      }
    }
  }
}

$log = [];
foreach ($node->get('field_scroll_section')->referencedEntities() as $gallery) {
  if ($gallery->bundle() !== 'exhibit_page') {
    continue;
  }
  $title = (string) $gallery->get('field_section_title')->value;
  $want = $galleries[$title] ?? NULL;
  if (!$want) {
    continue;
  }
  if ($want['display'] === 'wall' && (string) $gallery->get('field_exhibit_display')->value !== 'wall') {
    $gallery->set('field_exhibit_display', 'wall');
    $gallery->setNeedsSave(TRUE);
    $log[] = "Wall: $title";
  }
  if ($want['sheet'] && $gallery->get('field_exhibit_sheet')->isEmpty()) {
    $gallery->set('field_exhibit_sheet', $want['sheet']);
    $gallery->setNeedsSave(TRUE);
    $log[] = "Sheet size: $title ({$want['sheet']})";
  }

  foreach ($gallery->get('field_guide_blocks')->referencedEntities() as $entry) {
    if ($entry->bundle() !== 'exhibit_entry') {
      continue;
    }
    $anchor = (string) $entry->get('field_section_anchor')->value;
    if (!isset($pairs[$anchor])) {
      continue;
    }
    foreach ($entry->get('field_guide_blocks')->referencedEntities() as $block) {
      if ($block->bundle() !== 'exhibit_gallery') {
        continue;
      }
      foreach ($block->get('field_gallery_items')->referencedEntities() as $i => $figure) {
        $caption = $pairs[$anchor][$i] ?? '';
        if ($caption !== '' && trim(strip_tags((string) $figure->get('field_figure_caption')->value)) === '') {
          $figure->set('field_figure_caption', ['value' => $caption, 'format' => $format]);
          $figure->setNeedsSave(TRUE);
          $block->setNeedsSave(TRUE);
          $entry->setNeedsSave(TRUE);
          $gallery->setNeedsSave(TRUE);
          $log[] = "Caption: $anchor " . ($i + 1) . ' ' . strip_tags($caption);
        }
      }
      break;
    }
  }
}

if ($log) {
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage('Audubon: Elephant Folio, Octavo Edition and Drawings and Proofs as walls (palefire_exhibit_audubon_wall.php).');
  $node->save();
}
foreach ($log as $line) {
  echo $line . PHP_EOL;
}
echo 'Done: ' . count($log) . ' change(s). Run: drush cr' . PHP_EOL;
