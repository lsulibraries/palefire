<?php

/**
 * @file
 * Quiets "Written at Hill" on the Special Collections homepage: moves it
 * after "Time in the Archive" (the programs, then what came of them) and
 * keeps its five newest books, one row (_tile-showcase.scss, Covers).
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_special_publications_move.php
 *   drush php:script themes/custom/palefire/scripts/palefire_special_publications_move.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it reports what it would do and saves nothing.
 *
 * Optional arguments after "--":
 *   apply=1         Save.
 *   page=<alias>    The homepage (default /v2/special).
 *   after=<title>   The section to follow (default "Time in the Archive").
 *   keep=<n>        Books to keep, from the top (default 5). The rest are
 *                   taken out of the section; they stay in the previous
 *                   revision, and on the research guide.
 *
 * The index numbers follow the page, so they renumber themselves. Stops,
 * saving nothing, when either section is missing or the homepage has a
 * newer draft than its published revision.
 */

declare(strict_types=1);

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
$after = $args['after'] ?? 'Time in the Archive';
$keep = max(1, (int) ($args['keep'] ?? 5));
$title = 'Written at Hill';

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

$items = $node->get('field_scroll_section')->getValue();
$sections = $node->get('field_scroll_section')->referencedEntities();
$label = static fn ($section): string => $section->hasField('field_section_title') ? (string) $section->get('field_section_title')->value : '';
$from = NULL;
$target = NULL;
foreach ($sections as $delta => $section) {
  if ($label($section) === $title) {
    $from = $delta;
  }
  if ($label($section) === $after) {
    $target = $delta;
  }
}
if ($from === NULL) {
  throw new \RuntimeException("No \"$title\" section on $alias.");
}
if ($target === NULL) {
  throw new \RuntimeException("No \"$after\" section on $alias.");
}
$section = $sections[$from];
$log = [];

// The move. --------------------------------------------------------------------
$move = $from !== $target + 1;
if ($move) {
  $item = array_splice($items, $from, 1)[0];
  $to = $target + ($target > $from ? 0 : 1);
  array_splice($items, $to, 0, [$item]);
  $log[] = "Move \"$title\" from section " . ($from + 1) . ' to ' . ($to + 1) . ", after \"$after\".";
}
else {
  $log[] = "\"$title\" already follows \"$after\".";
}

// The books. -------------------------------------------------------------------
$books = $section->get('field_tile_publications')->getValue();
$trim = count($books) > $keep;
foreach ($section->get('field_tile_publications')->referencedEntities() as $i => $book) {
  $log[] = ($i < $keep ? 'Keep' : 'Take out') . ': "' . $book->get('field_pub_title')->value . '".';
}

foreach ($log as $line) {
  echo ($apply ? '' : '[dry run] ') . $line . PHP_EOL;
}
if (!$move && !$trim) {
  echo 'Nothing to do.' . PHP_EOL;
  return;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

if ($trim) {
  $section->set('field_tile_publications', array_slice($books, 0, $keep));
  $section->setNeedsSave(TRUE);
  foreach ($items as &$item) {
    if ((int) ($item['target_id'] ?? 0) === (int) $section->id()) {
      $item = ['entity' => $section];
    }
  }
  unset($item);
}
$node->set('field_scroll_section', $items);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage("Special Collections: \"$title\" after \"$after\", $keep books (palefire_special_publications_move.php).");
$node->save();
echo "Saved a new revision of \"{$node->label()}\". Run: drush cr" . PHP_EOL;
