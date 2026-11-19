<?php

/**
 * @file
 * Exhibitions: link each digital exhibition to the post about its show in
 * the building, so it carries the “Also a physical exhibition” tag.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_physical_links.php
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_physical_links.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it says what it would do and saves nothing.
 *
 * Sets field_exhibit_physical_post (added by
 * palefire_exhibit_showcase_posts.php) on eight exhibitions, each to an
 * existing news Article about the same show (found 2026-11-19 by title,
 * date and text). A link that is already set is left alone. Each
 * exhibition’s and post’s title must still contain the words below, so a
 * renumbered or retitled node is skipped, not mislinked.
 *
 * Shows with no post on the site, so not linked here: Audubon at Oakley
 * (2008), Jazz Fest 101 (2016), History of KLSU, History of Graphic
 * Design in South Louisiana.
 *
 * Undo: clear “Physical exhibition post” on the exhibition’s edit form.
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

// Exhibition node => [a word in its title, post node, a word in the post’s
// title, the show].
$links = [
  132637 => ['Occult', 22549, 'Occult', 'Hill Memorial Library, Jan 24 – Mar 6, 2010'],
  132636 => ['AVES', 22624, 'AVES', 'Hill Memorial Library, May 23 – Sep 10, 2011'],
  133430 => ['Tiger Stadium', 22752, 'Gridiron', 'Lecture Hall, fall 2013'],
  133434 => ['Integrating LSU', 22777, 'Integrating LSU', 'Lecture Hall, Jan 21 – Mar 29, 2014'],
  133428 => ['Lady', 22812, 'Cooperative Extension', 'Lecture Hall preview, spring 2014 (its listening station)'],
  133427 => ['Cooperative Extension', 22858, 'Cooperative Exten', 'Hill Memorial Library, fall 2014'],
  133432 => ['Tempests', 22908, 'Tempests', 'Lecture Hall Gallery, Jun 8 – Sep 12, 2015'],
  133431 => ['Georgetown 272', 23114, 'Georgetown 272', 'Lecture Hall, Apr 10 – Jun 30, 2018'],
];

$storage = \Drupal::entityTypeManager()->getStorage('node');
$set = 0;
foreach ($links as $nid => [$word, $post_nid, $post_word, $show]) {
  $exhibition = $storage->load($nid);
  $post = $storage->load($post_nid);
  if (!$exhibition instanceof NodeInterface || !$exhibition->hasField('field_exhibit_physical_post')) {
    echo "Skip node/$nid: not found, or no Physical exhibition post field (run palefire_exhibit_showcase_posts.php)." . PHP_EOL;
    continue;
  }
  if (stripos($exhibition->label(), $word) === FALSE) {
    echo "Skip node/$nid: its title “{$exhibition->label()}” no longer mentions “$word”." . PHP_EOL;
    continue;
  }
  if (!$post instanceof NodeInterface || $post->bundle() !== 'article' || !$post->isPublished()) {
    echo "Skip node/$nid: node/$post_nid is not a published Article." . PHP_EOL;
    continue;
  }
  if (stripos($post->label(), $post_word) === FALSE) {
    echo "Skip node/$nid: post node/$post_nid “{$post->label()}” no longer mentions “$post_word”." . PHP_EOL;
    continue;
  }
  $current = $exhibition->get('field_exhibit_physical_post')->target_id;
  if ($current) {
    echo "Leave “{$exhibition->label()}” (node/$nid): already linked to node/$current." . PHP_EOL;
    continue;
  }
  $say("Link “{$exhibition->label()}” (node/$nid) to “{$post->label()}” (node/$post_nid): $show.");
  if ($apply) {
    $exhibition->set('field_exhibit_physical_post', $post_nid);
    $exhibition->setNewRevision(TRUE);
    $exhibition->setRevisionLogMessage('Physical exhibition post: node/' . $post_nid . ' (palefire_exhibit_physical_links.php)');
    $exhibition->save();
  }
  $set++;
}
$say("$set of " . count($links) . ' linked.');
