<?php

/**
 * @file
 * Renames "Visitor Parking at Hill Memorial Library" to "Parking and Travel
 * to Hill Memorial Library", now that it also covers coming from out of
 * town (palefire_guide_parking_travel.php).
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_hill_rename.php
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_hill_rename.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it lists each change and saves nothing.
 *
 * Optional arguments after "--":
 *   apply=1          Save.
 *   page=<alias>     The guide (default
 *                    /v2/special/guides/parking-hill-memorial-library).
 *   path=<alias>     Its new alias (default
 *                    /v2/special/guides/parking-and-travel-hill-memorial-library).
 *
 * Changes, each skipped when already made:
 * - the title;
 * - the intro, if it is still the one palefire_guide_parking.php wrote;
 * - a new URL alias. The old one is kept, so bookmarks and links elsewhere
 *   still open the guide; Drupal links to the newest alias. The theme's
 *   links (Visit card, Quick Guide) look the guide up by its old alias,
 *   so they follow without a code change.
 * - the title of each Resource linking to the guide (Related guides).
 *
 * It stops, saving nothing, when the guide has a newer draft than its
 * current revision, which a save here would bypass.
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
$alias = '/' . trim($args['page'] ?? '/v2/special/guides/parking-hill-memorial-library', '/');
$new_alias = '/' . trim($args['path'] ?? '/v2/special/guides/parking-and-travel-hill-memorial-library', '/');

$title = 'Parking and Travel to Hill Memorial Library';
$old_intro = 'Where to park when you visit Special Collections at Hill Memorial Library without an LSU parking permit. Parking right by the building is limited, so plan on a 5 to 10 minute walk.';
$new_intro = '<p>Getting to Special Collections at Hill Memorial Library: where to park without an LSU parking permit, accessible parking, and travel and lodging if you are coming from out of town. Parking right by the building is limited, so plan on a 5 to 10 minute walk.</p>';

$path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
if (!preg_match('#^/node/(\d+)$#', $path, $m)) {
  throw new \RuntimeException("No node found at $alias.");
}
$storage = \Drupal::entityTypeManager()->getStorage('node');
$node = $storage->load($m[1]);
if (!$node) {
  throw new \RuntimeException("Node {$m[1]} not found.");
}
$latest = $storage->getLatestRevisionId($node->id());
if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
  throw new \RuntimeException("$alias has a newer draft (revision $latest) than its current revision. Publish or discard it first.");
}

$log = [];
$changed = FALSE;

if ($node->label() === $title) {
  $log[] = 'Title: already "' . $title . '".';
}
else {
  $log[] = 'Title: "' . $node->label() . "\" → \"$title\".";
  $node->setTitle($title);
  $changed = TRUE;
}

if ($node->hasField('field_intro') && !$node->get('field_intro')->isEmpty()) {
  $intro = $node->get('field_intro')->first();
  $plain = trim(html_entity_decode(strip_tags((string) $intro->value), ENT_QUOTES | ENT_HTML5));
  if ($plain === trim(strip_tags($new_intro))) {
    $log[] = 'Intro: already updated.';
  }
  elseif ($plain === $old_intro) {
    $log[] = 'Intro: updated to mention travel.';
    $intro->value = $new_intro;
    $changed = TRUE;
  }
  else {
    $log[] = 'Intro: changed by an editor; left alone. Consider mentioning travel.';
  }
}

$aliases = \Drupal::entityTypeManager()->getStorage('path_alias');
$taken = $aliases->loadByProperties(['alias' => $new_alias]);
$mine = array_filter($taken, fn ($a) => $a->getPath() === "/node/{$node->id()}");
if ($taken && !$mine) {
  throw new \RuntimeException("$new_alias already belongs to " . reset($taken)->getPath() . '. Pass path=<another alias>.');
}
$log[] = $mine ? "Alias: $new_alias already there." : "Alias: add $new_alias (keeping $alias).";

$resources = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'resource')
  ->condition('field_resource_link.uri', ["entity:node/{$node->id()}", "internal:$alias", "internal:/node/{$node->id()}"], 'IN')
  ->execute();
$rename = [];
foreach ($storage->loadMultiple($resources) as $resource) {
  if ($resource->label() !== $title) {
    $rename[] = $resource;
    $log[] = 'Resource ' . $resource->id() . ': "' . $resource->label() . "\" → \"$title\".";
  }
}

foreach ($log as $line) {
  echo ($apply ? '' : '[dry run] ') . $line . PHP_EOL;
}
if (!$changed && $mine && !$rename) {
  echo 'Nothing to change.' . PHP_EOL;
  return;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

if ($changed) {
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage("Renamed \"$title\" (palefire_guide_hill_rename.php).");
  $node->save();
}
// After the node's save, which rewrites the alias its path field holds.
if (!$mine) {
  // In the old alias's language, so the two are looked up alike.
  $old = $aliases->loadByProperties(['alias' => $alias, 'path' => "/node/{$node->id()}"]);
  $aliases->create([
    'path' => "/node/{$node->id()}",
    'alias' => $new_alias,
    'langcode' => $old ? reset($old)->language()->getId() : $node->language()->getId(),
  ])->save();
}
foreach ($rename as $resource) {
  $resource->setTitle($title);
  $resource->save();
}
echo "Saved. Run: drush cr" . PHP_EOL;
