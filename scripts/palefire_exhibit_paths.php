<?php

/**
 * @file
 * Exhibition pages: one URL per gallery, e.g. /v2/exhibitions/aves/featherwork.
 *
 * Adds a path alias "<page alias>/<gallery anchor>" for every gallery
 * (exhibit_page with an Anchor) of every Modular page in exhibition mode.
 * Each points at the page itself (/node/<id>), so Drupal serves the same
 * page there; js/exhibition.js opens that gallery and shows the gallery's
 * path in the address bar instead of #anchor. Content, not config.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_paths.php
 *   drush cr
 *
 * Optional after "--": nid=<node id> for one page; prune=1 also removes
 * gallery aliases whose gallery is gone or renamed.
 *
 * Re-run after adding, removing or renaming a gallery's Anchor, or after
 * changing the page's URL alias.
 *
 * The page keeps its own address: Drupal links to a node through its
 * alias in the node's language first, and the gallery aliases are saved
 * with no language ("und"). If the page's own alias has no language
 * either, the newest alias would win, so the script saves the page's alias
 * again after the gallery aliases, to keep it the newest, and says so.
 */

declare(strict_types=1);

use Drupal\Core\Language\LanguageInterface;
use Drupal\node\Entity\Node;

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

if (!\Drupal::moduleHandler()->moduleExists('path_alias')) {
  throw new \RuntimeException('The Path alias module is not enabled.');
}
$storage = \Drupal::entityTypeManager()->getStorage('path_alias');
$und = LanguageInterface::LANGCODE_NOT_SPECIFIED;

$query = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('field_modular_type', 'exhibition');
if (!empty($args['nid'])) {
  $query->condition('nid', (int) $args['nid']);
}
$nids = $query->execute();
if (!$nids) {
  echo 'No exhibition pages found.' . PHP_EOL;
  return;
}

foreach (Node::loadMultiple($nids) as $node) {
  $system = '/node/' . $node->id();
  $own = $storage->loadByProperties(['path' => $system]);
  // The page's own alias: the one Drupal links to now.
  $current = \Drupal::service('path_alias.manager')->getAliasByPath($system, $node->language()->getId());
  $main = NULL;
  foreach ($own as $alias) {
    if ($alias->getAlias() === $current) {
      $main = $alias;
      break;
    }
  }
  if (!$main) {
    echo "Skipped node/{$node->id()} ({$node->label()}): it has no URL alias." . PHP_EOL;
    continue;
  }
  $base = rtrim($main->getAlias(), '/');
  echo "{$node->label()}: $base" . PHP_EOL;

  // Gallery anchors, as palefire_modular uses them.
  $wanted = [];
  foreach ($node->get('field_scroll_section')->referencedEntities() as $gallery) {
    if ($gallery->bundle() !== 'exhibit_page') {
      continue;
    }
    $anchor = trim((string) $gallery->get('field_section_anchor')->value);
    if ($anchor === '') {
      echo '  No anchor, no path: ' . $gallery->get('field_section_title')->value . PHP_EOL;
      continue;
    }
    $wanted[$base . '/' . $anchor] = TRUE;
  }

  $added = 0;
  foreach (array_keys($wanted) as $path) {
    if ($storage->loadByProperties(['alias' => $path, 'path' => $system])) {
      continue;
    }
    if ($storage->loadByProperties(['alias' => $path])) {
      echo "  Taken by another page, skipped: $path" . PHP_EOL;
      continue;
    }
    $storage->create(['path' => $system, 'alias' => $path, 'langcode' => $und])->save();
    echo "  Added $path" . PHP_EOL;
    $added++;
  }

  if (!empty($args['prune'])) {
    foreach ($own as $alias) {
      $path = $alias->getAlias();
      if ($alias->id() !== $main->id() && str_starts_with($path, $base . '/') && !isset($wanted[$path])) {
        $alias->delete();
        echo "  Removed $path" . PHP_EOL;
      }
    }
  }

  // Keep the page's own alias first for its links (see above).
  if ($added && $main->language()->getId() === $und) {
    $values = ['path' => $system, 'alias' => $main->getAlias(), 'langcode' => $und];
    $main->delete();
    $storage->create($values)->save();
    echo "  Saved the page's own alias again ($base) so its links keep using it." . PHP_EOL;
  }
}

echo 'Done. Run: drush cr' . PHP_EOL;
