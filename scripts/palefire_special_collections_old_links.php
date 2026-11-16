<?php

/**
 * @file
 * Removes the links to the old site's Special Collections pages from the two
 * collection pages built by palefire_special_collections_pages.php (which
 * no longer writes them):
 * - LLMVC, Manuscripts: the "Manuscript Subject Guides" list item
 *   (lib.lsu.edu/special/manuscriptsubjectguides).
 * - Book Collections, Finding books: "Need help starting? See Tips for
 *   Finding Books" (lib.lsu.edu/special/research/find-books).
 * - Book Collections, McIlhenny: the link on "Audubon Day" (/special/audubon);
 *   the words stay.
 *
 *   drush php:script scripts/palefire_special_collections_old_links.php
 *   drush php:script scripts/palefire_special_collections_old_links.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. Only text blocks (guide_text) in the galleries
 * and their entries are touched, and only the exact text above: an editor's
 * own changes are left as they are. Safe to re-run. It stops, saving
 * nothing, on a page that has a newer draft than its current revision.
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

// [what to find, what to put instead]. Each body is matched as saved.
$replacements = [
  ['<li><a href="https://lib.lsu.edu/special/manuscriptsubjectguides">Manuscript Subject Guides</a>: the manuscripts on a topic, gathered in one list.</li>' . "\n", ''],
  ['<li><a href="https://lib.lsu.edu/special/manuscriptsubjectguides">Manuscript Subject Guides</a>: the manuscripts on a topic, gathered in one list.</li>', ''],
  [' Need help starting? See <a href="https://lib.lsu.edu/special/research/find-books">Tips for Finding Books</a>.', ''],
  ['<a href="/special/audubon">Audubon Day</a>', 'Audubon Day'],
];

$storage = \Drupal::entityTypeManager()->getStorage('node');
foreach (['/v2/special/llmvc', '/v2/special/bookcollections'] as $alias) {
  $path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
  if (!preg_match('#^/node/(\d+)$#', $path, $m) || !($node = $storage->load($m[1]))) {
    echo "No page at $alias. Skipped." . PHP_EOL;
    continue;
  }
  $latest = $storage->getLatestRevisionId($node->id());
  if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
    throw new \RuntimeException("$alias has a newer draft (revision $latest). Publish or discard it first.");
  }

  // The text blocks: each gallery's blocks, and each entry's blocks.
  $texts = [];
  foreach ($node->get('field_scroll_section')->referencedEntities() as $gallery) {
    if (!$gallery->hasField('field_guide_blocks')) {
      continue;
    }
    foreach ($gallery->get('field_guide_blocks')->referencedEntities() as $block) {
      if ($block->bundle() === 'guide_text') {
        $texts[] = [$gallery, NULL, $block];
      }
      elseif ($block->hasField('field_guide_blocks')) {
        foreach ($block->get('field_guide_blocks')->referencedEntities() as $inner) {
          if ($inner->bundle() === 'guide_text') {
            $texts[] = [$gallery, $block, $inner];
          }
        }
      }
    }
  }

  $changed = 0;
  foreach ($texts as [$gallery, $entry, $text]) {
    $body = $text->get('field_block_body')->value ?? '';
    $new = $body;
    foreach ($replacements as [$find, $put]) {
      $new = str_replace($find, $put, $new);
    }
    if ($new === $body) {
      continue;
    }
    $where = $gallery->get('field_section_title')->value . ($entry ? ' / ' . $entry->get('field_block_title')->value : '');
    echo ($apply ? '' : '[dry run] ') . "$alias: removed old-site links in \"$where\"." . PHP_EOL;
    $text->set('field_block_body', ['value' => $new, 'format' => $text->get('field_block_body')->format]);
    $text->setNeedsSave(TRUE);
    $entry?->setNeedsSave(TRUE);
    $gallery->setNeedsSave(TRUE);
    $changed++;
  }
  if (!$changed) {
    echo "$alias: no old-site links found." . PHP_EOL;
    continue;
  }
  if ($apply) {
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Removed links to the old Special Collections pages (palefire_special_collections_old_links.php).');
    $node->save();
    echo "Saved a new revision of \"{$node->label()}\"." . PHP_EOL;
  }
}
echo $apply ? 'Run: drush cr' . PHP_EOL : 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
