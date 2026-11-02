<?php

/**
 * @file
 * The Lecture Hall's button: the access default ("Request a date").
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_location_button.php
 *   drush cr
 *
 * Clears the Booking link's words while they are still the setup's
 * "Request the Lecture Hall", so the button, the Reserve section's button
 * and the phone dock all say the room's default (Event rental: "Request a
 * date", _palefire_space_access_kinds()). Other words are left as an
 * editor wrote them. Then lists where the room's Link and each Booking
 * link go: the room's Link is the map's way to the page, so on the page
 * itself the button uses the Booking link (_palefire_location_not_here()).
 * A new revision when anything changes.
 */

use Drupal\node\Entity\Node;

$path = \Drupal::service('path_alias.manager')->getPathByAlias('/v2/special/lecturehall');
$node = preg_match('#^/node/(\d+)$#', $path, $m) ? Node::load((int) $m[1]) : NULL;
if (!$node) {
  echo "No page at /v2/special/lecturehall.\n";
  return;
}
$field = $node->hasField('field_scroll_section') ? 'field_scroll_section' : 'field_sections';
$changed = FALSE;
foreach ($node->get($field)->referencedEntities() as $section) {
  if ($section->hasField('field_location_space') && ($room = $section->get('field_location_space')->entity)) {
    $link = $room->hasField('field_space_link') ? $room->get('field_space_link')->first() : NULL;
    echo 'Room link: ' . ($link ? $link->getUrl()->toString() : '(none)') . "\n";
  }
  if (!$section->hasField('field_booking_link') || $section->get('field_booking_link')->isEmpty()) {
    continue;
  }
  $item = $section->get('field_booking_link')->first();
  echo $section->bundle() . ' Booking link: ' . $item->getUrl()->toString() . ' "' . $item->title . "\"\n";
  if ($item->title === 'Request the Lecture Hall') {
    $item->title = '';
    $section->setNeedsSave(TRUE);
    $changed = TRUE;
    echo "  words cleared: the button says the default\n";
  }
}
if ($changed) {
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage('Lecture Hall: the button takes the default words (palefire_location_button.php).');
  $node->save();
}
echo "Done. Run: drush cr\n";
