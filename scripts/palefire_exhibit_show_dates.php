<?php

/**
 * @file
 * Exhibitions: the dates and place of each show in the building, on the
 * news post about it, so the exhibitions band's Press slot can say
 * "Shown Jan–Mar 2010" under "Read the release".
 *
 *   drush php:script scripts/palefire_exhibit_show_dates.php
 *   drush php:script scripts/palefire_exhibit_show_dates.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it says what it would do and saves nothing.
 *
 * Sets On view (field_exhibit_on_view) and Where (field_exhibit_where) on
 * the posts that palefire_exhibit_physical_links.php linked, where each
 * post's own text gives both the opening and closing dates (checked
 * 2026-11-20). A field that already has a value is left alone, and a post
 * whose title no longer contains the word below is skipped.
 *
 * Posts whose text doesn't give both dates are listed, not changed: fill
 * On view on the post's edit form when the dates are known.
 *
 * Undo: clear On view and Where on the post's edit form.
 */

declare(strict_types=1);

use Drupal\node\NodeInterface;

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
$say = fn (string $line) => print(($apply ? '' : '[dry run] ') . $line . PHP_EOL);

// Post node => [a word in its title, opens, closes, where, what the post
// says].
$shows = [
  22549 => ['Occult', '2010-01-25', '2010-03-06', 'Hill Memorial Library Lecture Hall', '“on display in the library’s lecture hall from Jan. 25 through Mar. 6” (posted Jan 22, 2010)'],
  22624 => ['AVES', '2011-05-23', '2011-09-10', 'Hill Memorial Library', '“opens on May 23 at Hill Memorial Library … runs until Sept. 10” (posted May 12, 2011)'],
  22777 => ['Integrating LSU', '2014-01-21', '2014-03-29', 'Hill Memorial Library Lecture Hall', '“On Exhibition January 21 - March 29, 2014 in the Hill Memorial Library Lecture Hall”'],
  23114 => ['Georgetown 272', '2018-04-10', '2018-06-30', 'Hill Memorial Library Lecture Hall', '“on exhibit in the Hill Memorial Library Lecture Hall April 10 through June 30, 2018”'],
];
// Linked posts without both dates in their text.
$unknown = [
  22752 => 'Tiger Stadium (Tales from the Gridiron): “now on display in the Lecture Hall”, posted Sep 19, 2013; no closing date.',
  22812 => 'A Lady in the South (Cooperative Extension preview): “currently on display in the lecture hall”, posted Apr 24, 2014; no dates.',
  22858 => 'Cooperative Extension at LSU: opening reception Oct 16, 2014; no closing date.',
  22908 => 'Tempests: talks Aug 25 – Sep 8, 2015 in the Lecture Hall Gallery; the show’s own dates aren’t given.',
];

$storage = \Drupal::entityTypeManager()->getStorage('node');
$saved = 0;
foreach ($shows as $nid => [$word, $from, $to, $where, $source]) {
  $post = $storage->load($nid);
  if (!$post instanceof NodeInterface || !str_contains($post->label(), $word)) {
    $say("Skipped node/$nid: not found, or its title no longer contains “{$word}”.");
    continue;
  }
  if (!$post->hasField('field_exhibit_on_view') || !$post->hasField('field_exhibit_where')) {
    throw new \RuntimeException('On view / Where not found on Articles. Run palefire_exhibit_showcase_posts.php first.');
  }
  $changes = [];
  if ($post->get('field_exhibit_on_view')->isEmpty()) {
    $post->set('field_exhibit_on_view', ['value' => $from, 'end_value' => $to]);
    $changes[] = "On view $from – $to";
  }
  if ($post->get('field_exhibit_where')->isEmpty()) {
    $post->set('field_exhibit_where', $where);
    $changes[] = "Where “{$where}”";
  }
  if (!$changes) {
    $say("node/$nid ($word): already set, left alone.");
    continue;
  }
  $say("node/$nid ($word): " . implode('; ', $changes) . ". Source: $source");
  if ($apply) {
    $post->setNewRevision(TRUE);
    $post->setRevisionLogMessage('Show dates and place from the post’s own text (palefire_exhibit_show_dates.php).');
    $post->save();
    $saved++;
  }
}

foreach ($unknown as $nid => $note) {
  $say("Not changed, node/$nid: $note");
}
$say($apply ? "Saved $saved post(s)." : 'Nothing saved. Run again with apply=1.');
