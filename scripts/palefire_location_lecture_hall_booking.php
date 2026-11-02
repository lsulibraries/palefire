<?php

/**
 * @file
 * The Lecture Hall page's Booking part, from Special Collections' own
 * reservation text (lib.lsu.edu/special#reservelecturehall): who can book,
 * how to book (the form, two weeks' notice, the email that confirms it,
 * who to ask about a date), the service charges, the equipment and the
 * layouts. The Booking link goes straight to the reservation form, so the
 * page's top button does too.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_location_setup.php
 *   drush php:script themes/custom/palefire/scripts/palefire_location_lecture_hall_booking.php
 *   drush cr
 *
 * Run after palefire_location_setup.php (it makes the page and merges its
 * sections). Fills in the Location section's Booking text and link only
 * while they are empty or still the setup's placeholder, so an editor's own
 * wording is kept; force=1 replaces them anyway. A new revision, so the old
 * text can be restored.
 */

use Drupal\node\Entity\Node;

$force = FALSE;
foreach ($extra ?? [] as $arg) {
  if ($arg === 'force=1') {
    $force = TRUE;
  }
}

$path = \Drupal::service('path_alias.manager')->getPathByAlias('/v2/special/lecturehall');
$node = preg_match('#^/node/(\d+)$#', $path, $m) ? Node::load((int) $m[1]) : NULL;
if (!$node) {
  echo "No page at /v2/special/lecturehall. Run palefire_location_setup.php first.\n";
  return;
}
$field = $node->hasField('field_scroll_section') ? 'field_scroll_section' : 'field_sections';
// The text goes on the Booking section (palefire_location_split.php), the
// link on the Location section, whose button it is. Before the split, both
// on the Location section.
$top = NULL;
$location = NULL;
foreach ($node->get($field) as $item) {
  $bundle = $item->entity ? $item->entity->bundle() : '';
  if ($bundle === 'location_space' && !$top) {
    $top = $item->entity;
  }
  if ($bundle === 'location_booking' && !$location) {
    $location = $item->entity;
  }
}
$location = $location ?? $top;
if (!$location || !$location->hasField('field_booking_body')) {
  echo "The page has no Booking or Location section with Booking fields.\n";
  return;
}

// The setup's placeholder (palefire_location_setup.php), compared as text.
$placeholder = 'Request your date early, at least two weeks ahead.';
$current = trim(strip_tags((string) $location->get('field_booking_body')->value));
if (!$force && $current !== '' && !str_starts_with($current, $placeholder)) {
  echo "Booking text already edited; left as it is (force=1 replaces it).\n";
  return;
}

$format = $location->get('field_booking_body')->format;
if (!$format) {
  $format = 'basic_html';
  foreach (filter_formats() as $id => $candidate) {
    if ($id === 'wysiwyg' || strcasecmp((string) $candidate->label(), 'WYSIWYG') === 0) {
      $format = $id;
      break;
    }
  }
}

$body = <<<'HTML'
<p>The Lecture Hall is open to LSU departments, colleges and organizations for meetings, classes and events of fewer than 100 people. Groups without a university affiliation can use it only for events co-sponsored by LSU Libraries.</p>
<h3>How to book</h3>
<ol>
<li><strong>Send the form</strong> <a href="https://lsu.formstack.com/forms/lecture_hall_reservations">Reservation form</a>, at least two weeks ahead</li>
<li><strong>Wait for our email</strong> It isn’t reserved until we confirm</li>
</ol>
<h3>Service charges</h3>
<ul>
<li>Two hours (minimum): $100</li>
<li>A full day (up to 8 hours): $250</li>
</ul>
<p>Equipment is included in the fee.</p>
<h3>Equipment</h3>
<ul>
<li>Podium and computer</li>
<li>Microphones, wireless and hybrid-capable</li>
<li>A ceiling-mounted projection screen and two wall-mounted TVs</li>
<li>Document camera</li>
<li>Tables and chairs</li>
</ul>
<h3>Layouts</h3>
<ul>
<li><a href="https://lib.lsu.edu/sites/default/files/2024-01/conferenceushape.jpg">Conference or U-shape</a></li>
<li><a href="https://lib.lsu.edu/sites/default/files/2024-01/lectureshape.jpg">Lecture</a></li>
<li><a href="https://lib.lsu.edu/sites/default/files/2024-01/classroomshape.jpg">Classroom</a></li>
</ul>
HTML;

$location->set('field_booking_body', ['value' => $body, 'format' => $format]);
$location->setNeedsSave(TRUE);
$button = $top ?? $location;
if ($button->hasField('field_booking_link')) {
  $button->set('field_booking_link', [
    'uri' => 'https://lsu.formstack.com/forms/lecture_hall_reservations',
    'title' => '',
  ]);
  $button->setNeedsSave(TRUE);
}
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('Lecture Hall: Booking text and the reservation form link from Special Collections (palefire_location_lecture_hall_booking.php).');
$node->save();
echo 'Updated the Lecture Hall Booking text and link (node/' . $node->id() . ").\n";
echo "Done. Run: drush cr\n";
