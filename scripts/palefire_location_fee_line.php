<?php

/**
 * @file
 * The Lecture Hall's description without "Fees apply."
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_location_fee_line.php
 *   drush cr
 *
 * Need to know, at the top of the room's page, says "Fees apply" for every
 * Event rental (_palefire_location_need_to_know()), so the description
 * under the title no longer needs to. Takes that one sentence out of the
 * Lecture Hall room term's description (Building spaces,
 * hill_memorial.lecture_hall) and leaves the rest as an editor wrote it.
 * Does nothing when the sentence isn't there.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
  return;
}

$terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$ids = $terms->getQuery()->accessCheck(FALSE)
  ->condition('vid', 'building_space')
  ->condition('field_map_key', 'hill_memorial.lecture_hall')
  ->range(0, 1)->execute();
$room = $ids ? $terms->load(reset($ids)) : NULL;
if (!$room) {
  echo "Room term hill_memorial.lecture_hall not found.\n";
  return;
}

$description = $room->get('description');
$value = (string) $description->value;
// The sentence, with the space before it (or an empty paragraph it leaves).
$new = preg_replace('/\s*Fees apply\.?/u', '', $value, 1);
$new = preg_replace('#<p>\s*</p>#u', '', (string) $new);
if ($new === $value) {
  echo "No \"Fees apply.\" in the Lecture Hall's description; nothing changed.\n";
  return;
}
$room->set('description', ['value' => $new, 'format' => $description->format]);
$room->save();
echo 'Lecture Hall description: ' . trim(strip_tags($new)) . "\n";
echo "Done. Run: drush cr\n";
