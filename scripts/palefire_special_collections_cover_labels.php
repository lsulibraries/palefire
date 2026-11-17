<?php

/**
 * @file
 * Object labels for the covers of the two collection pages, from their
 * Louisiana Digital Library records. The What Hill Holds section on
 * /v2/special shows a page's label as the citation under its cover while
 * the cover fills the section (js/ledger-photo.js).
 * - /v2/special/llmvc: Mugnier, Steamboat Natchez (lsu-sc-gfm:61).
 * - /v2/special/bookcollections: Audubon, American Flamingo
 *   (lsu-sc-jjadp:21).
 *
 *   drush php:script scripts/palefire_special_collections_cover_labels.php
 *   drush php:script scripts/palefire_special_collections_cover_labels.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. Fills only empty fields (the four
 * field_exhibit_cover_* fields from palefire_exhibit_cover_label.php), so an
 * editor's own label stays. Safe to re-run. It stops, saving nothing, on a
 * page that has a newer draft than its current revision.
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

$labels = [
  '/v2/special/llmvc' => [
    'maker' => 'George François Mugnier (1855–1936)',
    'title' => 'Steamboat Natchez',
    'date' => 'About 1885–1910',
    'medium' => 'Glass plate negative. New Orleans Scenes, Mss. 1089, 1552',
  ],
  '/v2/special/bookcollections' => [
    'maker' => 'John James Audubon (1785–1851)',
    'title' => 'American Flamingo',
    'date' => '1840–1844',
    'medium' => 'Uncolored proof for the octavo Birds of America. E. A. McIlhenny Natural History Collection, Mss. 5160',
  ],
];

$storage = \Drupal::entityTypeManager()->getStorage('node');
foreach ($labels as $alias => $label) {
  $path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
  if (!preg_match('#^/node/(\d+)$#', $path, $m) || !($node = $storage->load($m[1]))) {
    echo "No page at $alias. Skipped." . PHP_EOL;
    continue;
  }
  if (!$node->hasField('field_exhibit_cover_title')) {
    throw new \RuntimeException('Modular page has no cover label fields. Run scripts/palefire_exhibit_cover_label.php first.');
  }
  $latest = $storage->getLatestRevisionId($node->id());
  if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
    throw new \RuntimeException("$alias has a newer draft (revision $latest). Publish or discard it first.");
  }
  $filled = [];
  foreach ($label as $key => $value) {
    $field = 'field_exhibit_cover_' . $key;
    if ($node->hasField($field) && $node->get($field)->isEmpty()) {
      $node->set($field, $value);
      $filled[] = $key;
    }
  }
  if (!$filled) {
    echo "$alias: label already filled." . PHP_EOL;
    continue;
  }
  echo ($apply ? '' : '[dry run] ') . "$alias: cover label " . implode(', ', $filled) . '.' . PHP_EOL;
  if ($apply) {
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Cover object label (palefire_special_collections_cover_labels.php).');
    $node->save();
  }
}
echo $apply ? 'Run: drush cr' . PHP_EOL : 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
