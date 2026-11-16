<?php

/**
 * @file
 * Adds the "What Hill Holds" section (Collections) to the Special
 * Collections homepage: a Tile showcase in the Ledger layout, one row per
 * collection page, after "Plan your visit" and before "Beyond the Basics".
 *
 *   drush php:script scripts/palefire_special_collections_section.php
 *   drush php:script scripts/palefire_special_collections_section.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it reports what it would do and saves nothing.
 *
 * Optional arguments after "--":
 *   apply=1          Save.
 *   page=<alias>     The homepage (default /v2/special).
 *   before=<title>   The section to precede (default "Beyond the Basics");
 *                    not found, the new one goes before the stacks
 *                    (Upcoming, Featured, blog), else last.
 *   force=1          Save even if a collection page is unpublished.
 *                    Visitors would see only the published rows.
 *
 * Rows, in order: Louisiana and Lower Mississippi Valley Collections and
 * Book Collections (palefire_special_collections_pages.php), University
 * Archives and Oral History (existing landing pages). A page that isn't
 * found is left out and reported. Each row shows the page's title and
 * intro, as Time in the Archive does. Ledger rows carry the old site's tile
 * anchor (the title in lowercase letters and digits, e.g.
 * #louisianaandlowermississippivalleycollections), printed by
 * paragraph--tile-showcase.html.twig, so old links to the LLMVC tile land
 * on its row once this page takes over /special.
 *
 * Kept short on purpose: four rows and no pictures (about the height of
 * Time in the Archive), because the homepage is already long. The detail
 * is on each collection's own page.
 *
 * It stops, saving nothing, when:
 * - a section titled "What Hill Holds" is already on the page;
 * - none of the collection pages exist;
 * - a collection page is unpublished (unless force=1; a dry run only warns);
 * - the homepage has a newer draft than its published revision, which a
 *   save here would bypass.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
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
$force = ($args['force'] ?? '') === '1';
$alias = '/' . trim($args['page'] ?? '/v2/special', '/');
$before = $args['before'] ?? 'Beyond the Basics';

$title = 'What Hill Holds';
$collections = [
  'Louisiana and Lower Mississippi Valley Collections',
  'Book Collections',
  'University Archives',
  'Oral History',
];

// The fields this needs. ---------------------------------------------------
$type = 'tile_showcase';
foreach (['field_section_title', 'field_tile_layout', 'field_tiles', 'field_tile_eyebrow', 'field_tile_lede', 'field_tile_link'] as $name) {
  if (!FieldConfig::loadByName('paragraph', $type, $name)) {
    throw new \RuntimeException("paragraph.$type.$name not found. Run palefire_tile_showcase_setup.php and palefire_tile_showcase_gallery_setup.php first.");
  }
}
$handler = FieldConfig::loadByName('paragraph', $type, 'field_tiles')->getSetting('handler_settings') ?: [];
if (!empty($handler['target_bundles']) && !isset($handler['target_bundles']['modular_page'])) {
  throw new \RuntimeException('Tiles (field_tiles) cannot reference Modular pages. Run palefire_tile_showcase_gallery_setup.php first.');
}
$allowed = FieldStorageConfig::loadByName('paragraph', 'field_tile_layout')->getSetting('allowed_values');
if (!isset($allowed['ledger'])) {
  throw new \RuntimeException('"ledger" is not a Layout value yet. Run palefire_special_programs_section.php first (it adds it).');
}

// The homepage. ------------------------------------------------------------
$path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
if (!preg_match('#^/node/(\d+)$#', $path, $m)) {
  throw new \RuntimeException("No node found at $alias.");
}
$storage = \Drupal::entityTypeManager()->getStorage('node');
$node = $storage->load($m[1]);
if (!$node || !$node->hasField('field_scroll_section')) {
  throw new \RuntimeException("Node {$m[1]} has no field_scroll_section.");
}
$latest = $storage->getLatestRevisionId($node->id());
if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
  throw new \RuntimeException("$alias has a newer draft (revision $latest) than its published revision. Publish or discard it first.");
}

$log = [];
$sections = $node->get('field_scroll_section')->referencedEntities();
$position = NULL;
$neighbour = NULL;
foreach ($sections as $delta => $section) {
  $label = $section->hasField('field_section_title') ? (string) $section->get('field_section_title')->value : '';
  if ($label === $title) {
    echo "\"$title\" is already on $alias (section " . ($delta + 1) . '). Nothing to do.' . PHP_EOL;
    return;
  }
  if ($position === NULL && $label === $before) {
    $position = $delta;
    $neighbour = $section;
  }
}
if ($position === NULL) {
  foreach ($sections as $delta => $section) {
    if ($section->bundle() === 'landing_stacks') {
      $position = $delta;
      break;
    }
  }
  $log[] = "No section titled \"$before\": the new section goes " . ($position === NULL ? 'last' : 'before the stacks') . '.';
}
$position ??= count($sections);
// Match the other tile showcases (snap, height, theme, TOC).
if (!$neighbour) {
  foreach ($sections as $section) {
    if ($section->bundle() === $type) {
      $neighbour = $section;
      break;
    }
  }
}

// The collection pages. ----------------------------------------------------
$tiles = [];
$unpublished = [];
foreach ($collections as $collection) {
  $ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
    ->condition('type', 'modular_page')
    ->condition('title', $collection)
    ->sort('status', 'DESC')
    ->range(0, 1)->execute();
  if (!$ids) {
    $log[] = "Left out \"$collection\": no Modular page with that title.";
    continue;
  }
  $page = $storage->load(reset($ids));
  $tiles[] = ['target_id' => (int) $page->id()];
  $log[] = "Row: \"$collection\" (node {$page->id()}, " . $page->toUrl()->toString() . ($page->isPublished() ? '' : ', unpublished') . ').';
  if (!$page->isPublished()) {
    $unpublished[] = $collection;
  }
}
if (!$tiles) {
  throw new \RuntimeException('None of the collection pages exist. Run palefire_special_collections_pages.php first.');
}
if ($unpublished && !$force) {
  if ($apply) {
    throw new \RuntimeException('Unpublished: ' . implode(', ', $unpublished) . '. Publish them first, or pass force=1.');
  }
  $log[] = 'Unpublished: ' . implode(', ', $unpublished) . '. Publish them before apply=1 (or pass force=1).';
}

$values = [
  'type' => $type,
  'field_section_title' => $title,
  'field_tile_eyebrow' => 'Collections',
  'field_tile_lede' => 'Five centuries of books, the papers of Louisiana’s families and leaders, LSU’s own records and thousands of recorded voices.',
  'field_tile_link' => ['uri' => 'internal:/v2/search?mode=special', 'title' => 'Search the collections'],
  'field_tile_layout' => 'ledger',
  'field_tiles' => $tiles,
];
foreach (['field_section_snap', 'field_section_height', 'field_section_theme', 'field_section_toc_hide'] as $name) {
  if ($neighbour && $neighbour->hasField($name) && !$neighbour->get($name)->isEmpty()
    && FieldConfig::loadByName('paragraph', $type, $name)) {
    $values[$name] = $neighbour->get($name)->getValue();
  }
}
$log[] = "Insert \"$title\" as section " . ($position + 1) . ' of ' . (count($sections) + 1) . " on $alias.";

foreach ($log as $line) {
  echo ($apply ? '' : '[dry run] ') . $line . PHP_EOL;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$items = $node->get('field_scroll_section')->getValue();
array_splice($items, $position, 0, [['entity' => Paragraph::create($values)]]);
$node->set('field_scroll_section', $items);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage("Special Collections: \"$title\" section, Collections (palefire_special_collections_section.php).");
$node->save();
echo "Saved a new revision of \"{$node->label()}\". Run: drush cr" . PHP_EOL;
