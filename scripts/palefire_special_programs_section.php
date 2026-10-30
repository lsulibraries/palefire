<?php

/**
 * @file
 * Adds the "Time in the Archive" section (Teaching & Research) to the
 * Special Collections homepage: a Tile showcase in the Ledger layout listing
 * the program pages from palefire_special_programs.php.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_special_programs_section.php
 *   drush php:script themes/custom/palefire/scripts/palefire_special_programs_section.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it reports what it would do and saves nothing.
 *
 * Optional arguments after "--":
 *   apply=1         Save.
 *   page=<alias>    The homepage (default /v2/special).
 *   after=<title>   The section to follow (default "Off the Walls, Online");
 *                   not found, the new one goes before the stacks (Upcoming,
 *                   Featured, blog), else last.
 *   force=1         Save even if a program page is unpublished. Visitors
 *                   would see the section's heading with only the
 *                   published rows under it, or none.
 *
 * What it does:
 * - Adds "ledger" to the Tile showcase's Layout field (field_tile_layout),
 *   keeping every value already there, if it is missing.
 * - Adds one tile_showcase section after "Off the Walls, Online": Eyebrow
 *   "Teaching & Research" (the index rule; its number follows the page),
 *   Title "Time in the Archive", a Lede, Link "Ask about a program" (Ask
 *   Special Collections), Layout Ledger, and the four program pages in
 *   order: Teaching, Faculty Fellowship, Research Grants, Summer Project.
 *   A page that isn't found is left out and reported. Snap, height, theme
 *   and hide from TOC are copied from the section it follows, so the two
 *   match.
 * - Saves a new revision of the homepage.
 *
 * It stops, saving nothing, when:
 * - a section titled "Time in the Archive" is already on the page;
 * - none of the program pages exist;
 * - a program page is unpublished (unless force=1; a dry run only warns);
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
$after = $args['after'] ?? 'Off the Walls, Online';

$title = 'Time in the Archive';
$programs = [
  'Teaching with Special Collections',
  'Special Collections Faculty Fellowship',
  'Special Collections Research Grants',
  'Special Collections Summer Project',
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
$layout = FieldStorageConfig::loadByName('paragraph', 'field_tile_layout');
$allowed = $layout->getSetting('allowed_values');
$log = [];
if (!isset($allowed['ledger'])) {
  $log[] = 'Add "ledger" to field_tile_layout.';
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

// The program pages. -------------------------------------------------------
$tiles = [];
$unpublished = [];
foreach ($programs as $program) {
  $ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
    ->condition('type', 'modular_page')
    ->condition('title', $program)
    ->range(0, 1)->execute();
  if (!$ids) {
    $log[] = "Left out \"$program\": no Modular page with that title. Run palefire_special_programs.php, or add it by hand later.";
    continue;
  }
  $page = $storage->load(reset($ids));
  $tiles[] = ['target_id' => (int) $page->id()];
  $log[] = "Row: \"$program\" (node {$page->id()}" . ($page->isPublished() ? '' : ', unpublished') . ').';
  if (!$page->isPublished()) {
    $unpublished[] = $program;
  }
}
if (!$tiles) {
  throw new \RuntimeException('None of the program pages exist. Run palefire_special_programs.php first.');
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
  'field_tile_eyebrow' => 'Teaching & Research',
  'field_tile_lede' => 'Class visits, fellowships and grants for instructors, scholars and students whose work starts in Hill’s collections.',
  'field_tile_link' => ['uri' => 'https://askus.lib.lsu.edu#special', 'title' => 'Ask about a program'],
  'field_tile_layout' => 'ledger',
  'field_tiles' => $tiles,
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

if (!isset($allowed['ledger'])) {
  $allowed['ledger'] = 'Ledger (rows under their categories, beside the heading)';
  $layout->setSetting('allowed_values', $allowed)->save();
}

$items = $node->get('field_scroll_section')->getValue();
array_splice($items, $position, 0, [['entity' => Paragraph::create($values)]]);
$node->set('field_scroll_section', $items);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage("Special Collections: \"$title\" section, Teaching & Research (palefire_special_programs_section.php).");
$node->save();
echo "Saved a new revision of \"{$node->label()}\". Run: drush cr" . PHP_EOL;
