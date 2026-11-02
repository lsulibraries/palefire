<?php

/**
 * @file
 * The Lecture Hall's How to book, as named steps for pf-steps
 * (includes/steps.html.twig): each a bold name and a one-line note, in the
 * order people act. The form is linked from its step; the Reserve button
 * under the steps goes there too.
 *
 *   1  Send the form       Reservation form, at least two weeks ahead
 *   2  Wait for our email  It isn't reserved until we confirm
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_location_steps.php
 *   drush cr
 *
 * Replaces only the numbered list under the How to book subhead in the
 * Booking text, and only while it is still the wording
 * palefire_location_lecture_hall_booking.php first wrote (three sentences)
 * or this script's first version (Check the date, Send the form, Wait for
 * our email), so an editor's own steps are kept; force=1 replaces them
 * anyway. The rest of the text is left as it is. A new revision, so the
 * old text can be restored.
 */

use Drupal\Component\Utility\Html;
use Drupal\node\Entity\Node;

$force = in_array('force=1', $extra ?? [], TRUE);

$path = \Drupal::service('path_alias.manager')->getPathByAlias('/v2/special/lecturehall');
$node = preg_match('#^/node/(\d+)$#', $path, $m) ? Node::load((int) $m[1]) : NULL;
if (!$node) {
  echo "No page at /v2/special/lecturehall.\n";
  return;
}
$field = $node->hasField('field_scroll_section') ? 'field_scroll_section' : 'field_sections';
$section = NULL;
foreach ($node->get($field) as $item) {
  $entity = $item->entity;
  if ($entity && $entity->hasField('field_booking_body') && str_contains((string) $entity->get('field_booking_body')->value, 'How to book')) {
    $section = $entity;
    break;
  }
}
if (!$section) {
  echo "No Booking text with a How to book subhead on the page.\n";
  return;
}

$value = (string) $section->get('field_booking_body')->value;
$dom = Html::load($value);
$list = NULL;
foreach ($dom->getElementsByTagName('h3') as $h3) {
  if (trim($h3->textContent) !== 'How to book') {
    continue;
  }
  for ($next = $h3->nextSibling; $next; $next = $next->nextSibling) {
    if ($next instanceof \DOMElement) {
      $list = strtolower($next->tagName) === 'ol' ? $next : NULL;
      break;
    }
  }
  break;
}
if (!$list) {
  echo "How to book has no numbered list under it; nothing changed.\n";
  return;
}
$old = trim(preg_replace('/\s+/', ' ', $list->textContent));
if (str_starts_with($old, 'Send the form Reservation form')) {
  echo "The steps are already up to date; nothing changed.\n";
  return;
}
$ours = str_starts_with($old, 'Send the reservation form, at least two weeks ahead.') || str_starts_with($old, 'Check the date Ask Andrew Loyacano');
if (!$force && !$ours) {
  echo "How to book already edited; left as it is (force=1 replaces it).\n";
  return;
}

$steps = <<<'HTML'
<ol>
<li><strong>Send the form</strong> <a href="https://lsu.formstack.com/forms/lecture_hall_reservations">Reservation form</a>, at least two weeks ahead</li>
<li><strong>Wait for our email</strong> It isn’t reserved until we confirm</li>
</ol>
HTML;
$fragment = Html::load($steps)->getElementsByTagName('ol')->item(0);
$list->parentNode->replaceChild($dom->importNode($fragment, TRUE), $list);

$section->set('field_booking_body', [
  'value' => Html::serialize($dom),
  'format' => $section->get('field_booking_body')->format,
]);
$section->setNeedsSave(TRUE);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('Lecture Hall: How to book as named steps (palefire_location_steps.php).');
$node->save();
echo 'Updated the Lecture Hall How to book (node/' . $node->id() . ").\n";
echo "Done. Run: drush cr\n";
