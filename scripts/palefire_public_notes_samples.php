<?php

/**
 * @file
 * Sample notes and thumbs, to see how public notes look before real ones
 * arrive: on the PR timeline (Webform pr_timeline) and the Fall 2026
 * newsletter review page (Webform newsletter_review).
 *
 *   drush php:script scripts/palefire_public_notes_samples.php
 *   drush php:script scripts/palefire_public_notes_samples.php -- apply=1
 *   drush php:script scripts/palefire_public_notes_samples.php -- remove=1
 *   drush cr
 *
 * A dry run unless apply=1 or remove=1. apply=1 adds the samples (approved
 * notes, some with a thumb, and thumbs on their own) unless they are
 * already there; remove=1 deletes them again, and only them. Each sample is
 * marked in its submission's Notes field ("Sample from
 * palefire_public_notes_samples.php"), so it is easy to spot in the Results
 * tab too.
 *
 * The timeline samples are left on its placeholder items ("Placeholder ·
 * First event or item" and so on): once the items are renamed, they gather
 * under "On the timeline as a whole". Remove them before the page is used.
 *
 * Saving a sample runs the form's handlers like any submission: if a form
 * emails each note, this stops unless send_email=1 is added.
 *
 * Optional arguments after "--":
 *   form=pr_timeline | newsletter_review   Only that form (default both).
 *   issue=<campaign activity id>           The newsletter issue (default
 *                                          Fall 2026's).
 */

declare(strict_types=1);

use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;

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
$remove = ($args['remove'] ?? '') === '1';
$send_email = ($args['send_email'] ?? '') === '1';
$only = $args['form'] ?? '';
$issue = strtolower($args['issue'] ?? '9e92c0f7-dc6b-4701-b751-8731705cddc1');
$prefix = ($apply || $remove) ? '' : '[dry run] ';
$marker = 'Sample from palefire_public_notes_samples.php';
$day = 86400;
$now = \Drupal::time()->getRequestTime();

if (!\Drupal::moduleHandler()->moduleExists('webform')) {
  echo "The Webform module isn't enabled. Nothing done.\n";
  return;
}

// [about, name, note, verdict, approved, days ago]. A note that isn't
// approved stays out of sight, as a real one would until staff tick it.
$timeline = [
  ['Placeholder · First event or item', 'Jordan Ellis', "The banner photo still shows the old Hill Memorial sign. Could we swap in this fall's?", 'changes', TRUE, 6],
  ['Placeholder · First event or item', '', 'Love the welcome week page, it was easy to share with new grad students.', 'good', TRUE, 5],
  ['Placeholder · First event or item', '', '', 'good', FALSE, 5],
  ['Placeholder · First event or item', '', '', 'good', FALSE, 4],
  ['Placeholder · Second event or item', 'Sam Okafor', 'Can the press release mention the extended weekend hours too?', '', TRUE, 4],
  ['Placeholder · Second event or item', '', '', 'good', FALSE, 3],
  ['Placeholder · Second event or item', '', '', 'changes', FALSE, 3],
  ['Placeholder · Third event or item', '', '', 'good', FALSE, 2],
  ['', 'Priya N.', 'Maybe add the spring donor reception as its own item.', 'good', TRUE, 2],
  ['', '', 'This note is waiting for approval, so it never shows.', '', FALSE, 1],
];
$newsletter = [
  ['Book Talk with Dean Jonathan Earle', 'Avery', 'The talk date in the second paragraph says Oct 14, but the event page says Oct 15.', 'changes', TRUE, 3],
  ['Book Talk with Dean Jonathan Earle', '', '', 'good', FALSE, 3],
  ['Family Weekend Open House and Hill-o-Ween', '', 'Great photos. Could the caption name the student workers?', 'good', TRUE, 2],
  ['Family Weekend Open House and Hill-o-Ween', '', '', 'good', FALSE, 2],
  ['Family Weekend Open House and Hill-o-Ween', '', '', 'good', FALSE, 1],
  ['Richard Lipsey Recognizes the T. Harry Williams Center for Oral History', '', '', 'good', FALSE, 1],
  ['', 'Morgan Lee', 'Reads well overall. The Events section could come before New Resources.', 'good', TRUE, 1],
];

$sets = [
  'pr_timeline' => ['rows' => $timeline, 'about' => 'item', 'extra' => []],
  'newsletter_review' => ['rows' => $newsletter, 'about' => 'section', 'extra' => ['issue' => $issue, 'version' => 'sample']],
];
if ($only !== '') {
  if (!isset($sets[$only])) {
    echo "form= is pr_timeline or newsletter_review. Nothing done.\n";
    return;
  }
  $sets = [$only => $sets[$only]];
}

$storage = \Drupal::entityTypeManager()->getStorage('webform_submission');
foreach ($sets as $id => $set) {
  $webform = Webform::load($id);
  if (!$webform) {
    echo "$id: no such Webform yet; skipped.\n";
    continue;
  }
  $existing = $storage->getQuery()->accessCheck(FALSE)
    ->condition('webform_id', $id)
    ->condition('notes', $marker)
    ->execute();

  if ($remove) {
    echo "$id: deleting " . count($existing) . " samples.\n";
    if ($existing) {
      $storage->delete($storage->loadMultiple($existing));
    }
    continue;
  }
  if ($existing) {
    echo "$id: " . count($existing) . " samples are already there; remove=1 deletes them.\n";
    continue;
  }
  $elements = $webform->getElementsDecodedAndFlattened();
  foreach (['verdict', 'approved'] as $needed) {
    if (!isset($elements[$needed])) {
      echo "$id has no '$needed' element yet: run its webform script with replace=1 first. Skipped.\n";
      continue 2;
    }
  }
  $emails = array_filter(iterator_to_array($webform->getHandlers('email')), fn ($handler) => $handler->isEnabled());
  if ($emails && !$send_email) {
    echo "$id emails each note (" . count($emails) . " email handler): the samples would send " . count($set['rows']) . " emails. Skipped; add send_email=1 to send them anyway.\n";
    continue;
  }
  $notes = count(array_filter($set['rows'], fn ($row) => $row[2] !== '' && $row[4]));
  echo "{$prefix}$id: add " . count($set['rows']) . " samples ($notes approved notes shown; the rest are thumbs on their own or a note awaiting approval).\n";
  if (!$apply) {
    continue;
  }
  foreach ($set['rows'] as [$about, $name, $note, $verdict, $approved, $ago]) {
    $submission = WebformSubmission::create([
      'webform_id' => $id,
      'data' => [
        'name' => $name,
        $set['about'] => $about,
        'comment' => $note,
        'verdict' => $verdict,
        'approved' => $approved ? 1 : 0,
      ] + $set['extra'],
      'notes' => $marker,
    ]);
    $submission->set('created', $now - $ago * $day);
    $submission->set('completed', $now - $ago * $day);
    $submission->save();
  }
  echo "$id: done.\n";
}

if (!$apply && !$remove) {
  echo "Dry run. Add apply=1 to add the samples, or remove=1 to delete them.\n";
}
else {
  echo "Run: drush cr (the pages also redraw on their own).\n";
}
