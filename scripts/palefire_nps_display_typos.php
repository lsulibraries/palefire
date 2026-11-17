<?php

/**
 * @file
 * Fixes the typos in the news post "Main Library Display: History of the
 * National Park Service" (node/131825) before the Fall 2026 newsletter links
 * to it. Newsletter reviewers flagged "Untied States’", "bear spary" and
 * "US. Fish and Wildlife"; the rest came from a read-through.
 *
 *   drush php:script scripts/palefire_nps_display_typos.php
 *   drush php:script scripts/palefire_nps_display_typos.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. Only the exact text below is touched (body and
 * its summary, the title, the author title), so an editor's other changes
 * stay. Saved as a new revision. Safe to re-run: fixed text no longer
 * matches. It stops, saving nothing, if the post has a newer draft than its
 * current revision.
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

$nid = 131825;

// [what to find, what to put instead]. Matched as saved; where the saved
// text may hold either an HTML entity or the character, both are listed.
$body_fixes = [
  ['Untied States', 'United States'],
  ['King’s Canyon', 'Kings Canyon'],
  ['King&rsquo;s Canyon', 'Kings Canyon'],
  ['Photo:National Park Service', 'Photo: National Park Service'],
  ['Photo:Minnesota Historical Society', 'Photo: Minnesota Historical Society'],
  ['Creation of the National Parks Service', 'Creation of the National Park Service'],
  ['Franklin K Lane', 'Franklin K. Lane'],
  ['the National Parks Service (NPS)', 'the National Park Service (NPS)'],
  ['future generations. Stephen Mather', 'future generations.” Stephen Mather'],
  ['the US. unemployment rate', 'the U.S. unemployment rate'],
  ['years, US. National Park', 'years, U.S. National Park'],
  ['there has been over 16 billion', 'there have been over 16 billion'],
  ['US. Fish and Wildlife employee', 'U.S. Fish and Wildlife Service employee'],
  ['a place to visit–they', 'a place to visit—they'],
  ['a place to visit&ndash;they', 'a place to visit&mdash;they'],
  ['US. troops', 'U.S. troops'],
  ['Clifford Berryman\'s', 'Clifford Berryman’s'],
  ['Naturalist, George Melendez Wright, in the 1930s', 'Naturalist George Melendez Wright in the 1930s'],
  ['feed the bears but they', 'feed the bears, but they'],
  ['bear spary', 'bear spray'],
];
$title_fixes = [
  ['National Parks Service', 'National Park Service'],
];
$author_title_fixes = [
  ['Patent and Trademarks Librarian', 'Patents and Trademarks Librarian'],
];

$storage = \Drupal::entityTypeManager()->getStorage('node');
$node = $storage->load($nid);
if (!$node) {
  throw new \RuntimeException("No node/$nid.");
}
$latest = $storage->getLatestRevisionId($nid);
if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
  throw new \RuntimeException("node/$nid has a newer draft (revision $latest). Publish or discard it first.");
}

/**
 * Applies $fixes to $text, printing what matched. Returns the new text.
 */
$fix = function (string $label, string $text, array $fixes): string {
  foreach ($fixes as [$from, $to]) {
    $n = substr_count($text, $from);
    if ($n) {
      $text = str_replace($from, $to, $text);
      echo "  $label ($n×): \"$from\" → \"$to\"" . PHP_EOL;
    }
  }
  return $text;
};

echo ($apply ? 'Fixing' : 'Dry run for') . ' node/' . $nid . ': ' . $node->label() . PHP_EOL;
$changed = FALSE;

$title = $fix('title', (string) $node->label(), $title_fixes);
if ($title !== $node->label()) {
  $node->setTitle($title);
  $changed = TRUE;
}

if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
  $item = $node->get('body')->first()->getValue();
  $value = $fix('body', (string) ($item['value'] ?? ''), $body_fixes);
  $summary = $fix('summary', (string) ($item['summary'] ?? ''), $body_fixes);
  if ($value !== ($item['value'] ?? '') || $summary !== ($item['summary'] ?? '')) {
    $item['value'] = $value;
    $item['summary'] = $summary;
    $node->set('body', $item);
    $changed = TRUE;
  }
}

if ($node->hasField('field_author_title') && !$node->get('field_author_title')->isEmpty()) {
  $old = (string) $node->get('field_author_title')->value;
  $new = $fix('author title', $old, $author_title_fixes);
  if ($new !== $old) {
    $node->get('field_author_title')->value = $new;
    $changed = TRUE;
  }
}

if (!$changed) {
  echo 'Nothing to fix (already done, or the text has changed).' . PHP_EOL;
  return;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Add -- apply=1 to save.' . PHP_EOL;
  return;
}

$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('Typo fixes before the Fall 2026 newsletter (palefire_nps_display_typos.php).');
$node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
$node->save();
echo 'Saved a new revision.' . PHP_EOL;
