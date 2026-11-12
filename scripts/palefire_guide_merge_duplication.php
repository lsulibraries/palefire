<?php

/**
 * @file
 * Merges "Ordering Copies from Special Collections" into "How to Use
 * Special Collections", so copies have one guide instead of two.
 *
 *   drush php:script scripts/palefire_guide_merge_duplication.php
 *   drush php:script scripts/palefire_guide_merge_duplication.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it lists each change and saves nothing.
 *
 * Optional arguments after "--":
 *   apply=1        Save.
 *   page=<alias>   The guide that stays (default
 *                  /v2/special/guides/special-collections).
 *   from=<alias>   The guide merged into it (default
 *                  /v2/special/guides/duplication).
 *
 * Changes, each skipped when already made:
 * - Copies of the old guide's live "Prices" (renamed "Copy prices"),
 *   "Copyright and permissions" and "Citing Special Collections" sections
 *   go into the guide: Copy prices after "Requesting and visiting" (anchor
 *   #copy-prices, which the homepage's Prices buttons and the dock's Order
 *   copies use), the other two before "Asking Special Collections a
 *   question". Its "How to order" and "Get help" are left out: the guide's
 *   Option 2 and Get help already say the same. Copies, so any edits made
 *   on the live page come along; the old page keeps its own.
 * - Every resource list that links to the old guide (Related guides on the
 *   guide itself, the Glossary and so on) drops that link, and the Resource
 *   for it is unpublished, so no one is sent to a page that is gone.
 * - The old guide is unpublished (a new revision, so it can be restored
 *   from Revisions), and its alias now opens the merged guide, so
 *   bookmarks still work. The guide's own alias is saved again after it so
 *   Drupal keeps linking to /guides/special-collections (the newest alias
 *   wins).
 *
 * It stops, saving nothing, when either guide has a newer draft than its
 * current revision, which a save here would bypass.
 */

declare(strict_types=1);

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

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
$alias = '/' . trim($args['page'] ?? '/v2/special/guides/special-collections', '/');
$from_alias = '/' . trim($args['from'] ?? '/v2/special/guides/duplication', '/');

$etm = \Drupal::entityTypeManager();
$storage = $etm->getStorage('node');
$aliases = $etm->getStorage('path_alias');
$log = [];

/**
 * The node an alias points at, checked for a pending draft.
 */
$load = function (string $alias) use ($storage): ?NodeInterface {
  $path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
  if (!preg_match('#^/node/(\d+)$#', $path, $m)) {
    return NULL;
  }
  $node = $storage->load($m[1]);
  if (!$node instanceof NodeInterface || !$node->hasField('field_scroll_section')) {
    throw new \RuntimeException("Node {$m[1]} at $alias has no field_scroll_section.");
  }
  $latest = $storage->getLatestRevisionId($node->id());
  if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
    throw new \RuntimeException("$alias has a newer draft (revision $latest) than its current revision. Publish or discard it first.");
  }
  return $node;
};

$guide = $load($alias);
if (!$guide) {
  throw new \RuntimeException("No node found at $alias.");
}
$old = $load($from_alias);
if ($old && (int) $old->id() === (int) $guide->id()) {
  // The old alias already opens the guide: a run with apply=1 finished.
  $old = NULL;
}
if (!$old) {
  // Found by title, in case only the alias moved.
  $ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
    ->condition('type', 'modular_page')
    ->condition('title', 'Ordering Copies from Special Collections')
    ->range(0, 1)->execute();
  $old = $ids ? $storage->load(reset($ids)) : NULL;
}
if (!$old) {
  throw new \RuntimeException('"Ordering Copies from Special Collections" not found.');
}
echo "Guide: \"{$guide->label()}\" (node/{$guide->id()}). Merging in: \"{$old->label()}\" (node/{$old->id()})." . PHP_EOL;

$title_of = fn (ParagraphInterface $p) => $p->hasField('field_section_title') ? trim((string) $p->get('field_section_title')->value) : '';

// 1. Sections. ---------------------------------------------------------------

// Old title => [new title, the guide section it goes after or before].
$moves = [
  'Prices' => ['Copy prices', 'after', ['Requesting and visiting']],
  'Copyright and permissions' => ['Copyright and permissions', 'before', ['Asking Special Collections a question', 'Frequently asked questions', 'Related guides', 'Get help']],
  'Citing Special Collections' => ['Citing Special Collections', 'before', ['Asking Special Collections a question', 'Frequently asked questions', 'Related guides', 'Get help']],
];

$old_sections = [];
foreach ($old->get('field_scroll_section')->referencedEntities() as $section) {
  $old_sections[$title_of($section)] = $section;
}

$sections = $guide->get('field_scroll_section')->referencedEntities();
if (count($sections) !== $guide->get('field_scroll_section')->count()) {
  throw new \RuntimeException("$alias refers to a section that no longer exists. Save the page once in the UI first.");
}
$guide_changed = FALSE;
foreach ($moves as $from_title => [$to_title, $where, $anchors]) {
  $titles = array_map($title_of, $sections);
  if (in_array($to_title, $titles, TRUE)) {
    $log[] = "Section \"$to_title\": already on the guide.";
    continue;
  }
  if (!isset($old_sections[$from_title])) {
    $log[] = "Section \"$from_title\": not on the old guide, skipped.";
    continue;
  }
  $position = count($sections);
  foreach ($anchors as $anchor) {
    $at = array_search($anchor, $titles, TRUE);
    if ($at !== FALSE) {
      $position = $where === 'after' ? $at + 1 : $at;
      break;
    }
  }
  // createDuplicate() copies the nested blocks too; Resources are shared.
  $copy = $old_sections[$from_title]->createDuplicate();
  $copy->set('field_section_title', $to_title);
  array_splice($sections, $position, 0, [$copy]);
  $guide_changed = TRUE;
  $log[] = "Section \"$to_title\": copy of \"$from_title\", inserted as section " . ($position + 1) . ' of ' . count($sections) . '.';
}
if ($guide_changed) {
  $guide->set('field_scroll_section', array_map(fn ($s) => ['entity' => $s], $sections));
}

// 2. Links to the old guide in resource lists. -------------------------------

$resource_ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'resource')
  ->condition('field_resource_link.uri', [
    "entity:node/{$old->id()}",
    "internal:/node/{$old->id()}",
    "internal:$from_alias",
    'https://lib.lsu.edu' . $from_alias,
    'https://www.lib.lsu.edu' . $from_alias,
  ], 'IN')
  ->execute();
$resource_ids = array_map('intval', $resource_ids);

// Drops those Resources from $entity and every paragraph under it, marking
// changed paragraphs for saving. Returns the number dropped.
$strip = function (ContentEntityInterface $entity) use (&$strip, $resource_ids): int {
  $dropped = 0;
  foreach ($entity->getFields() as $name => $items) {
    $type = $items->getFieldDefinition()->getType();
    if ($name === 'field_resources' && $type === 'entity_reference') {
      $keep = array_values(array_filter($items->getValue(), fn ($v) => !in_array((int) ($v['target_id'] ?? 0), $resource_ids, TRUE)));
      if (count($keep) !== $items->count()) {
        $dropped += $items->count() - count($keep);
        $entity->set($name, $keep);
      }
    }
    elseif ($type === 'entity_reference_revisions') {
      foreach ($items as $item) {
        if ($item->entity instanceof ParagraphInterface) {
          $n = $strip($item->entity);
          if ($n) {
            $item->entity->setNeedsSave(TRUE);
            $dropped += $n;
          }
        }
      }
    }
  }
  return $dropped;
};

$hosts = [];
if ($resource_ids) {
  $pids = $etm->getStorage('paragraph')->getQuery()->accessCheck(FALSE)
    ->condition('field_resources', $resource_ids, 'IN')->execute();
  foreach ($etm->getStorage('paragraph')->loadMultiple($pids) as $paragraph) {
    $parent = $paragraph;
    while ($parent instanceof ParagraphInterface) {
      $parent = $parent->getParentEntity();
    }
    if ($parent instanceof NodeInterface && (int) $parent->id() !== (int) $old->id()) {
      $hosts[(int) $parent->id()] = TRUE;
    }
  }
}
// The guide itself, already loaded (its new sections must not be lost).
$others = [];
if (isset($hosts[(int) $guide->id()])) {
  unset($hosts[(int) $guide->id()]);
}
$n = $resource_ids ? $strip($guide) : 0;
if ($n) {
  $guide_changed = TRUE;
  $log[] = "Guide: drop $n link(s) to the old guide (Related guides).";
}
foreach ($storage->loadMultiple(array_keys($hosts)) as $host) {
  $latest = $storage->getLatestRevisionId($host->id());
  if ($latest && (int) $latest !== (int) $host->getRevisionId()) {
    $log[] = "Skipped \"{$host->label()}\" (node/{$host->id()}): it has a newer draft. Remove the Ordering Copies link there by hand.";
    continue;
  }
  $n = $strip($host);
  if ($n) {
    $others[] = $host;
    $log[] = "\"{$host->label()}\" (node/{$host->id()}): drop $n link(s) to the old guide.";
  }
}
$resources = array_filter($storage->loadMultiple($resource_ids), fn ($r) => $r->isPublished());
foreach ($resources as $resource) {
  $log[] = "Resource \"{$resource->label()}\" (node/{$resource->id()}): unpublish.";
}

// 3. The old guide and its alias. --------------------------------------------

if ($old->isPublished()) {
  $log[] = "\"{$old->label()}\": unpublish (a new revision).";
}
$old_aliases = $aliases->loadByProperties(['path' => "/node/{$old->id()}"]);
$guide_aliases = $aliases->loadByProperties(['path' => "/node/{$guide->id()}"]);
$guide_has_from = (bool) array_filter($guide_aliases, fn ($a) => $a->getAlias() === $from_alias);
foreach ($old_aliases as $a) {
  $log[] = $a->getAlias() === $from_alias
    ? "Alias $from_alias: open the merged guide instead."
    : 'Alias ' . $a->getAlias() . ': remove (the old guide is unpublished).';
}
if (!$old_aliases && !$guide_has_from) {
  $log[] = "Alias $from_alias: not found; add it to the guide.";
}
if ($old_aliases || !$guide_has_from) {
  $log[] = "Alias $alias: save again, so it stays the guide's main address.";
}

foreach ($log as $line) {
  echo ($apply ? '' : '[dry run] ') . $line . PHP_EOL;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$stamp = \Drupal::time()->getRequestTime();
$message = 'Merged "Ordering Copies from Special Collections" into this guide (palefire_guide_merge_duplication.php).';
if ($guide_changed) {
  $guide->setNewRevision(TRUE);
  $guide->setRevisionLogMessage($message);
  $guide->setRevisionCreationTime($stamp);
  $guide->save();
  echo "Saved a new revision of \"{$guide->label()}\"." . PHP_EOL;
}
foreach ($others as $host) {
  $host->setNewRevision(TRUE);
  $host->setRevisionLogMessage('Dropped the link to "Ordering Copies from Special Collections", now part of "How to Use Special Collections" (palefire_guide_merge_duplication.php).');
  $host->setRevisionCreationTime($stamp);
  $host->save();
  echo "Saved a new revision of \"{$host->label()}\"." . PHP_EOL;
}
foreach ($resources as $resource) {
  $resource->setUnpublished();
  $resource->save();
}
if ($old->isPublished()) {
  $old->setUnpublished();
  $old->setNewRevision(TRUE);
  $old->setRevisionLogMessage('Unpublished: merged into "How to Use Special Collections" (palefire_guide_merge_duplication.php).');
  $old->setRevisionCreationTime($stamp);
  $old->save();
  echo "Unpublished \"{$old->label()}\" (node/{$old->id()})." . PHP_EOL;
}

// After the node saves, which rewrite the aliases their path fields hold.
$old_aliases = $aliases->loadByProperties(['path' => "/node/{$old->id()}"]);
$moved = FALSE;
foreach ($old_aliases as $a) {
  if ($a->getAlias() === $from_alias) {
    $a->setPath("/node/{$guide->id()}");
    $a->save();
    $moved = TRUE;
  }
  else {
    $a->delete();
  }
}
if (!$moved && !$guide_has_from) {
  $aliases->create(['path' => "/node/{$guide->id()}", 'alias' => $from_alias, 'langcode' => $guide->language()->getId()])->save();
  $moved = TRUE;
}
if ($moved) {
  // The newest alias is the one Drupal links to: recreate the guide's own.
  foreach ($aliases->loadByProperties(['path' => "/node/{$guide->id()}", 'alias' => $alias]) as $a) {
    $langcode = $a->language()->getId();
    $a->delete();
    $aliases->create(['path' => "/node/{$guide->id()}", 'alias' => $alias, 'langcode' => $langcode])->save();
  }
  echo "$from_alias now opens \"{$guide->label()}\"; it still links as $alias." . PHP_EOL;
}
echo 'Done. Run: drush cr' . PHP_EOL;
