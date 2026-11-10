<?php

/**
 * @file
 * Copy prices per item, not per page, in pages already built: the "How to
 * Use Special Collections" guide, the Special Collections Glossary's
 * "Duplication order" (also its pop-up on every guide) and the Special
 * Collections homepage's Visit tab, if staff have kept that text. Prices
 * come from "Ordering Copies from Special Collections"
 * (/v2/special/guides/duplication).
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_copy_prices_per_item.php
 *   drush php:script themes/custom/palefire/scripts/palefire_copy_prices_per_item.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it lists what it would change and saves nothing.
 *
 * Looks in every long text field of every paragraph for the exact old
 * sentences below and swaps in the new ones. Each page it changes gets one
 * new revision (restore the old text from its Revisions tab); its published
 * state is left alone. A page with a newer draft than its published
 * revision is skipped and named, so the draft isn't lost: fix it by hand.
 * Text that still says "50 cents a page" in other words is listed at the
 * end, for staff. Running it again changes nothing.
 */

declare(strict_types=1);

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
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

$swaps = [
  // How to Use Special Collections, Option 2.
  'Scans cost 50 cents a page, plus a $10 processing fee for each order;'
  => 'Photocopies and low-resolution PDF scans cost 50 cents an item, and high-resolution JPEG or TIFF scans $1 an item, plus a $10 processing fee for each order;',
  // Glossary: Duplication order.
  'Scans are 50 cents a page plus a $10 processing fee per order,'
  => 'Scans are 50 cents an item ($1 at high resolution) plus a $10 processing fee per order,',
  // Homepage Visit tab.
  'Scans are 50 cents a page plus a $10 order fee,'
  => 'Scans are 50 cents an item ($1 at high resolution) plus a $10 order fee,',
];
$leftover = '50 cents a page';

$etm = \Drupal::entityTypeManager();
$fields = array_filter(
  \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('paragraph'),
  fn ($def) => in_array($def->getType(), ['text_long', 'text_with_summary', 'string_long'], TRUE),
);

// Paragraphs (default revisions) holding the phrase, by field.
$hits = [];
foreach (array_keys($fields) as $field) {
  $ids = $etm->getStorage('paragraph')->getQuery()->accessCheck(FALSE)
    ->condition($field . '.value', $leftover, 'CONTAINS')->execute();
  foreach ($ids as $id) {
    $hits[(int) $id][] = $field;
  }
}
if (!$hits) {
  echo 'No paragraph says "' . $leftover . '". Nothing to do.' . PHP_EOL;
  return;
}

// Their host nodes, through nested paragraphs.
$hosts = [];
foreach ($etm->getStorage('paragraph')->loadMultiple(array_keys($hits)) as $paragraph) {
  $parent = $paragraph;
  while ($parent instanceof ParagraphInterface) {
    $parent = $parent->getParentEntity();
  }
  if ($parent instanceof NodeInterface) {
    $hosts[(int) $parent->id()] = TRUE;
  }
  else {
    echo "Skipped paragraph {$paragraph->id()}: not on a node." . PHP_EOL;
  }
}

/** @var \Drupal\Core\Entity\RevisionableStorageInterface $node_storage */
$node_storage = $etm->getStorage('node');

// Swaps the text in $entity and every paragraph under it; marks changed
// paragraphs and their parents for saving. Returns the remaining old-price
// snippets; adds the number of swaps to $count.
$walk = function (ContentEntityInterface $entity, int &$count) use (&$walk, $swaps, $leftover): array {
  $left = [];
  $changed = FALSE;
  foreach ($entity->getFields() as $name => $items) {
    $type = $items->getFieldDefinition()->getType();
    if (in_array($type, ['text_long', 'text_with_summary', 'string_long'], TRUE)) {
      foreach ($items as $item) {
        $value = (string) $item->value;
        $new = strtr($value, $swaps);
        if ($new !== $value) {
          $count += substr_count($value, '50 cents a page') - substr_count($new, '50 cents a page');
          $item->value = $new;
          $changed = TRUE;
        }
        if (str_contains($new, $leftover) && preg_match('~.{0,60}' . preg_quote($leftover, '~') . '.{0,60}~su', strip_tags($new), $m)) {
          $left[] = $entity->getEntityTypeId() . ' ' . $entity->id() . ' ' . $name . ': …' . trim($m[0]) . '…';
        }
      }
    }
    elseif ($type === 'entity_reference_revisions') {
      foreach ($items as $item) {
        $child = $item->entity;
        if ($child instanceof ParagraphInterface) {
          $before = $count;
          $left = [...$left, ...$walk($child, $count)];
          if ($count > $before) {
            $child->setNeedsSave(TRUE);
            $changed = TRUE;
          }
        }
      }
    }
  }
  if ($changed && $entity instanceof ParagraphInterface) {
    $entity->setNeedsSave(TRUE);
  }
  return $left;
};

$leftovers = [];
foreach ($node_storage->loadMultiple(array_keys($hosts)) as $node) {
  $label = '"' . $node->label() . '" (node/' . $node->id() . ')';
  $latest = $node_storage instanceof RevisionableStorageInterface ? $node_storage->getLatestRevisionId($node->id()) : NULL;
  if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
    echo "Skipped $label: it has a newer draft (revision $latest). Change the price there by hand." . PHP_EOL;
    continue;
  }
  $count = 0;
  $leftovers = [...$leftovers, ...$walk($node, $count)];
  if ($count === 0) {
    continue;
  }
  if ($apply) {
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Copy prices per item, not per page (palefire_copy_prices_per_item.php).');
    $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $node->save();
    echo "Updated $label: $count sentence(s)." . PHP_EOL;
  }
  else {
    echo "Would update $label: $count sentence(s)." . PHP_EOL;
  }
}

if ($leftovers) {
  echo PHP_EOL . 'Still "' . $leftover . '" in other words (check by hand):' . PHP_EOL;
  foreach (array_unique($leftovers) as $line) {
    echo '  ' . $line . PHP_EOL;
  }
}
echo PHP_EOL . ($apply ? 'Done. Run: drush cr' : 'Dry run: nothing saved. Add -- apply=1 to save.') . PHP_EOL;
