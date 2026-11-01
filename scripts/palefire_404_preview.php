<?php

/**
 * @file
 * Copies the site's 404 page to /v2/404, unpublished, so the Palefire 404
 * page can be previewed: this theme only draws pages under /v2, and a real
 * 404 can't be opened as a page of its own.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_404_preview.php
 *   drush php:script themes/custom/palefire/scripts/palefire_404_preview.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it says what it would do and saves nothing.
 *
 * Optional arguments after "--":
 *   apply=1          Save.
 *   from=<alias>     The 404 page to copy (default /404, the page the site
 *                    shows for "page not found").
 *   path=<alias>     The copy's alias (default /v2/404, which must match
 *                    PalefireHooks::NOT_FOUND_PREVIEW).
 *
 * The copy is unpublished: editors see it, visitors and search engines
 * don't. The theme draws it with page--404.html.twig, so its own fields are
 * not shown; they are copied only so it stays a faithful copy. Paragraphs
 * are duplicated with it, so editing one page never changes the other.
 * It does nothing when the alias already exists.
 */

declare(strict_types=1);

use Drupal\Core\Entity\ContentEntityInterface;

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
$from = '/' . trim($args['from'] ?? '/404', '/');
$to = '/' . trim($args['path'] ?? '/v2/404', '/');

$aliases = \Drupal::entityTypeManager()->getStorage('path_alias');
if ($taken = $aliases->loadByProperties(['alias' => $to])) {
  echo "$to already exists (" . reset($taken)->getPath() . '). Nothing to do.' . PHP_EOL;
  return;
}

$path = \Drupal::service('path_alias.manager')->getPathByAlias($from);
if (!preg_match('#^/node/(\d+)$#', $path, $m)) {
  throw new \RuntimeException("No node found at $from. Pass from=<alias of the 404 page>.");
}
$storage = \Drupal::entityTypeManager()->getStorage('node');
$source = $storage->load($m[1]);
if (!$source) {
  throw new \RuntimeException("Node {$m[1]} not found.");
}

$title = 'Page not found (Palefire preview)';
echo ($apply ? '' : '[dry run] ') . "Copy node {$source->id()} \"{$source->label()}\" ($from, {$source->bundle()}) to $to as \"$title\", unpublished." . PHP_EOL;
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$copy = $source->createDuplicate();
// Paragraphs belong to one host: give the copy its own.
foreach ($copy->getFieldDefinitions() as $name => $definition) {
  if ($definition->getType() !== 'entity_reference_revisions' || $copy->get($name)->isEmpty()) {
    continue;
  }
  $items = [];
  foreach ($copy->get($name)->referencedEntities() as $child) {
    if ($child instanceof ContentEntityInterface) {
      $items[] = $child->createDuplicate();
    }
  }
  $copy->set($name, $items);
}
$copy->setTitle($title);
$copy->setUnpublished();
$copy->set('path', ['alias' => $to, 'pathauto' => 0]);
if ($copy->hasField('promote')) {
  $copy->set('promote', 0);
}
$copy->setNewRevision(TRUE);
$copy->setRevisionLogMessage("Copy of $from for previewing the Palefire 404 page (palefire_404_preview.php).");
$copy->save();

echo "Saved node {$copy->id()} at $to (unpublished). Run drush cr, then open $to while signed in." . PHP_EOL;
