<?php

/**
 * @file
 * The Photographs gallery on /v2/special/llmvc becomes a wall (Display
 * "wall"): its image group of three photographs is replaced by three
 * entries, one per studio (Andrew D. Lytle, George François Mugnier, Donn
 * Young), each holding its photograph with the same caption, so each hangs
 * as a tile and opens full screen. The gallery's text stays above the wall.
 * palefire_special_collections_pages.php builds new pages this way.
 *
 *   drush php:script scripts/palefire_special_collections_photo_wall.php
 *   drush php:script scripts/palefire_special_collections_photo_wall.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. The group's caption ("140 years of Louisiana
 * photography…") goes with it. Safe to re-run: a gallery that is already a
 * wall, or has no image group, is left alone. It stops, saving nothing, on
 * a page that has a newer draft than its current revision.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\paragraphs\Entity\Paragraph;

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

if (!FieldConfig::loadByName('paragraph', 'exhibit_page', 'field_exhibit_display')) {
  throw new \RuntimeException('paragraph.exhibit_page.field_exhibit_display not found. Run palefire_exhibit_setup.php first.');
}

// Each photograph's entry, by the start of its caption.
$entries = [
  'Andrew D. Lytle' => ['Andrew D. Lytle', 'lytle'],
  'George François Mugnier' => ['George François Mugnier', 'mugnier'],
  'Donn Young' => ['Donn Young', 'donn-young'],
];

$alias = '/v2/special/llmvc';
$storage = \Drupal::entityTypeManager()->getStorage('node');
$path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
if (!preg_match('#^/node/(\d+)$#', $path, $m) || !($node = $storage->load($m[1]))) {
  throw new \RuntimeException("No page at $alias.");
}
$latest = $storage->getLatestRevisionId($node->id());
if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
  throw new \RuntimeException("$alias has a newer draft (revision $latest). Publish or discard it first.");
}

$gallery = NULL;
foreach ($node->get('field_scroll_section')->referencedEntities() as $candidate) {
  if ($candidate->bundle() === 'exhibit_page' && $candidate->get('field_section_anchor')->value === 'photographs') {
    $gallery = $candidate;
    break;
  }
}
if (!$gallery) {
  throw new \RuntimeException("$alias has no Photographs gallery (anchor photographs).");
}
if ($gallery->get('field_exhibit_display')->value === 'wall') {
  echo "$alias: Photographs is already a wall. Nothing to do." . PHP_EOL;
  return;
}

$blocks = [];
$made = [];
foreach ($gallery->get('field_guide_blocks')->referencedEntities() as $block) {
  if ($block->bundle() !== 'exhibit_gallery') {
    $blocks[] = $block;
    continue;
  }
  foreach ($block->get('field_gallery_items')->referencedEntities() as $figure) {
    if ($figure->bundle() !== 'exhibit_figure' || $figure->get('field_figure_image')->isEmpty()) {
      continue;
    }
    $caption = trim(strip_tags((string) $figure->get('field_figure_caption')->value));
    [$title, $anchor] = [mb_substr($caption, 0, 40), ''];
    foreach ($entries as $start => $entry) {
      if (str_starts_with($caption, $start)) {
        [$title, $anchor] = $entry;
        break;
      }
    }
    $copy = Paragraph::create([
      'type' => 'exhibit_figure',
      'field_figure_image' => $figure->get('field_figure_image')->getValue(),
      'field_figure_caption' => $figure->get('field_figure_caption')->getValue(),
      'field_figure_layout' => 'center',
    ]);
    $blocks[] = Paragraph::create([
      'type' => 'exhibit_entry',
      'field_block_title' => $title,
      'field_section_anchor' => $anchor,
      'field_guide_blocks' => [$copy],
    ]);
    $made[] = $title;
  }
}
if (!$made) {
  echo "$alias: no image group in Photographs. Nothing to do." . PHP_EOL;
  return;
}

echo ($apply ? '' : '[dry run] ') . "$alias: Photographs becomes a wall of " . count($made) . ' entries: ' . implode(', ', $made) . '.' . PHP_EOL;
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}
$gallery->set('field_guide_blocks', $blocks);
$gallery->set('field_exhibit_display', 'wall');
$gallery->setNeedsSave(TRUE);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('Photographs as a wall (palefire_special_collections_photo_wall.php).');
$node->save();
echo "Saved a new revision of \"{$node->label()}\". Run: drush cr" . PHP_EOL;
