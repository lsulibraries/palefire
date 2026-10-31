<?php

/**
 * @file
 * Adds the "Written at Hill" section (In Print) to the Special Collections
 * homepage: a Tile showcase in the Covers layout with the eight most recent
 * books from the research guide "Select Publications using Special
 * Collections' Holdings" (guides.lib.lsu.edu/c.php?g=1091798&p=9443015),
 * which stays the full list.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_special_publications.php
 *   drush php:script themes/custom/palefire/scripts/palefire_special_publications.php -- apply=1
 *   drush cr
 *
 * Run palefire_publications_setup.php first. A dry run unless apply=1: it
 * reports what it would do and saves nothing.
 *
 * Optional arguments after "--":
 *   apply=1         Save.
 *   page=<alias>    The homepage (default /v2/special).
 *   after=<title>   The section to follow (default "Off the Walls, Online");
 *                   not found, the new one goes before the stacks (Upcoming,
 *                   Featured, blog), else last.
 *
 * What it does:
 * - Adds one tile_showcase section after "Off the Walls, Online": Eyebrow
 *   "In Print" (the index rule; its number follows the page), Title
 *   "Written at Hill", a Lede, Link "All publications" (the guide), Layout
 *   Covers, and eight Publication paragraphs, newest first, each linked to
 *   its catalog record (the guide's own links). No covers: each gets a
 *   jacket set in type until an editor adds its Cover. Snap, height, theme
 *   and hide from TOC are copied from the section it follows.
 * - Saves a new revision of the homepage.
 *
 * Two corrections to the guide: "Into the Bright Sunshine" is by Samuel G.
 * Freedman (the guide has "Freeman"), and the publishers are shortened.
 *
 * It stops, saving nothing, when:
 * - a section titled "Written at Hill" is already on the page;
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
$alias = '/' . trim($args['page'] ?? '/v2/special', '/');
$after = $args['after'] ?? 'Off the Walls, Online';

$title = 'Written at Hill';
$catalog = 'https://lsu.ent.sirsi.net/client/en_US/lsu/search/detailnonmodal/ent:$002f$002fSD_LSU$002f0$002fSD_LSU:%s/one';
$books = [
  ['You Are My Sunshine: Jimmie Davis & the Biography of a Song', 'Robert Mann', 'LSU Press, 2025', '8120961'],
  ['The Carceral City: Slavery and the Making of Mass Incarceration in New Orleans, 1803–1930', 'John K. Bardes', 'UNC Press, 2024', '7990443'],
  ['Mad with Freedom: The Political Economy of Blackness, Insanity, and Civil Rights in the U.S. South, 1840–1940', 'Élodie Edwards-Grossi', 'LSU Press, 2023', '7633226'],
  ['Freedom’s Crescent: The Civil War and the Destruction of Slavery in the Lower Mississippi Valley', 'John C. Rodrigue', 'Cambridge University Press, 2023', '7650775'],
  ['Into the Bright Sunshine: Young Hubert Humphrey and the Fight for Civil Rights', 'Samuel G. Freedman', 'Oxford University Press, 2023', '7738847'],
  ['The 272: The Families Who Were Enslaved and Sold to Build the American Catholic Church', 'Rachel L. Swarns', 'Random House, 2023', '7661748'],
  ['Kingfish U: Huey Long and LSU', 'Robert Mann', 'LSU Press, 2023', '7583616'],
  ['Lost Causes: Confederate Demobilization and the Making of Veteran Identity', 'Bradley R. Clampitt', 'LSU Press, 2022', '7633160'],
];

// The fields this needs. ---------------------------------------------------
$type = 'tile_showcase';
foreach (['field_section_title', 'field_tile_layout', 'field_tile_eyebrow', 'field_tile_lede', 'field_tile_link', 'field_tile_publications'] as $name) {
  if (!FieldConfig::loadByName('paragraph', $type, $name)) {
    throw new \RuntimeException("paragraph.$type.$name not found. Run palefire_tile_showcase_gallery_setup.php and palefire_publications_setup.php first.");
  }
}
$allowed = FieldStorageConfig::loadByName('paragraph', 'field_tile_layout')->getSetting('allowed_values');
if (!isset($allowed['covers'])) {
  throw new \RuntimeException('field_tile_layout has no "covers". Run palefire_publications_setup.php first.');
}
$log = [];

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

$sections = $node->get('field_scroll_section')->referencedEntities();
$position = NULL;
$neighbour = NULL;
foreach ($sections as $delta => $section) {
  $label = $section->hasField('field_section_title') ? (string) $section->get('field_section_title')->value : '';
  if ($label === $title) {
    echo "\"$title\" is already on $alias (section " . ($delta + 1) . '). Nothing to do.' . PHP_EOL;
    return;
  }
  if ($position === NULL && $label === $after) {
    $position = $delta + 1;
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
  $log[] = "No section titled \"$after\": the new section goes " . ($position === NULL ? 'last' : 'before the stacks') . '.';
}
$position ??= count($sections);

foreach ($books as [$book_title, $author, $imprint]) {
  $log[] = "Book: \"$book_title\", $author ($imprint).";
}

$values = [
  'type' => $type,
  'field_section_title' => $title,
  'field_tile_eyebrow' => 'In Print',
  'field_tile_lede' => 'Recent books by scholars whose research began in Hill’s reading room, each linked to its record in the LSU Libraries catalog.',
  'field_tile_link' => ['uri' => 'https://guides.lib.lsu.edu/c.php?g=1091798&p=9443015', 'title' => 'All publications'],
  'field_tile_layout' => 'covers',
];
// Match the section it follows.
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

$values['field_tile_publications'] = [];
foreach ($books as [$book_title, $author, $imprint, $record]) {
  $values['field_tile_publications'][] = ['entity' => Paragraph::create([
    'type' => 'publication',
    'field_pub_title' => $book_title,
    'field_pub_author' => $author,
    'field_pub_imprint' => $imprint,
    'field_pub_link' => ['uri' => sprintf($catalog, $record)],
  ])];
}

$items = $node->get('field_scroll_section')->getValue();
array_splice($items, $position, 0, [['entity' => Paragraph::create($values)]]);
$node->set('field_scroll_section', $items);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage("Special Collections: \"$title\" section, In Print (palefire_special_publications.php).");
$node->save();
echo "Saved a new revision of \"{$node->label()}\". Run: drush cr" . PHP_EOL;
