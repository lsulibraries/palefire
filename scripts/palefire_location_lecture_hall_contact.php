<?php

/**
 * @file
 * Lecture Hall: Andrew Loyacano as the person to ask, again.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_location_lecture_hall_contact.php
 *   drush cr
 *
 * Sets the Lecture Hall room's Contact (field_space_contact) to the Staff
 * page with aloyacano@lsu.edu, so "Questions? …" beside its buttons names
 * him instead of Meredith Contreras. Other rooms are left as they are.
 *
 * Safe to re-run.
 */

declare(strict_types=1);

use Drupal\taxonomy\TermInterface;

if (PHP_SAPI !== 'cli') {
  return;
}

$nodes = \Drupal::entityTypeManager()->getStorage('node');

$path = \Drupal::service('path_alias.manager')->getPathByAlias('/v2/special/lecturehall');
$hall = preg_match('#^/node/(\d+)$#', $path, $m) ? $nodes->load($m[1]) : NULL;
if (!$hall) {
  echo "No Lecture Hall page at /v2/special/lecturehall; nothing changed.\n";
  return;
}

$room = NULL;
$field = $hall->hasField('field_scroll_section') ? 'field_scroll_section' : 'field_sections';
foreach ($hall->get($field)->referencedEntities() as $section) {
  if ($section->bundle() === 'location_space') {
    $room = $section->get('field_location_space')->entity;
    break;
  }
}
if (!$room instanceof TermInterface || !$room->hasField('field_space_contact')) {
  echo "The Lecture Hall page has no room with a Contact field; nothing changed.\n";
  return;
}

$staff = $nodes->loadByProperties(['type' => 'staff', 'field_staff_email' => 'aloyacano@lsu.edu']);
if (!$staff) {
  echo "No Staff page with aloyacano@lsu.edu; nothing changed.\n";
  return;
}
$andrew = reset($staff);

if ((int) $room->get('field_space_contact')->target_id === (int) $andrew->id()) {
  echo "The Lecture Hall's contact is already {$andrew->label()}; nothing changed.\n";
  return;
}

$room->set('field_space_contact', ['target_id' => (int) $andrew->id()]);
$room->save();
echo "Lecture Hall: contact is now {$andrew->label()} (node/{$andrew->id()}).\n";
echo "Done. Run: drush cr\n";
