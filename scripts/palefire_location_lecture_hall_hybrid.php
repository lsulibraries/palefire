<?php

/**
 * @file
 * Lecture Hall: not set up for hybrid meetings or online presentations.
 *
 *   drush php:script scripts/palefire_location_lecture_hall_hybrid.php
 *   drush cr
 *
 * Run after palefire_location_setup.php and
 * palefire_location_lecture_hall_booking.php.
 *
 * - A Space policy "No hybrid or online events" (not allowed, the laptop
 *   mark, key no_hybrid), created once; editors own its words after.
 * - Added to the Lecture Hall room (hill_memorial.lecture_hall) after its
 *   AV policy, so it shows as a mark at the top and under Not allowed in
 *   House rules.
 * - The Booking text's equipment line "Microphones, wireless and
 *   hybrid-capable" becomes "Wireless microphones", so the page doesn't
 *   promise hybrid. Only that exact line is changed; a new revision.
 *
 * When the hall is set up for hybrid, remove the policy from the room (the
 * term can stay for other rooms).
 *
 * Safe to re-run: anything already done is left as it is.
 */

declare(strict_types=1);

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

if (PHP_SAPI !== 'cli') {
  return;
}

$terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$find = static fn (string $vid, string $field, string $value): ?Term => ($ids = $terms->getQuery()->accessCheck(FALSE)
  ->condition('vid', $vid)->condition($field, $value)->range(0, 1)->execute()) ? $terms->load(reset($ids)) : NULL;

// 1. The policy. --------------------------------------------------------------
$policy = $find('space_policy', 'field_policy_key', 'no_hybrid');
if (!$policy) {
  $policy = Term::create([
    'vid' => 'space_policy',
    'name' => 'No hybrid or online events',
    'description' => ['value' => 'The hall isn’t set up for hybrid meetings or online presentations at the moment.', 'format' => 'plain_text'],
    'field_policy_state' => 'not_allowed',
    'field_policy_icon' => 'laptop',
    'field_policy_key' => 'no_hybrid',
  ]);
  $policy->save();
  echo "Created policy: No hybrid or online events\n";
}

// 2. On the Lecture Hall room, after its AV policy. ---------------------------
$room = $find('building_space', 'field_map_key', 'hill_memorial.lecture_hall');
if (!$room) {
  throw new \RuntimeException('Room term hill_memorial.lecture_hall not found.');
}
$ids = array_map('intval', array_column($room->get('field_space_policies')->getValue(), 'target_id'));
if (!in_array((int) $policy->id(), $ids, TRUE)) {
  $av = $find('space_policy', 'field_policy_key', 'podium_av');
  $at = $av ? array_search((int) $av->id(), $ids, TRUE) : FALSE;
  array_splice($ids, $at === FALSE ? count($ids) : $at + 1, 0, [(int) $policy->id()]);
  $room->set('field_space_policies', array_map(static fn (int $id): array => ['target_id' => $id], $ids));
  $room->save();
  echo "Added it to the Lecture Hall room\n";
}

// 3. The Booking text's equipment line. ---------------------------------------
$path = \Drupal::service('path_alias.manager')->getPathByAlias('/v2/special/lecturehall');
$node = preg_match('#^/node/(\d+)$#', $path, $m) ? Node::load((int) $m[1]) : NULL;
$field = $node && $node->hasField('field_scroll_section') ? 'field_scroll_section' : 'field_sections';
foreach ($node ? $node->get($field) : [] as $item) {
  $section = $item->entity;
  if (!$section || $section->bundle() !== 'location_booking' || !$section->hasField('field_booking_body')) {
    continue;
  }
  $body = $section->get('field_booking_body');
  $old = '<li>Microphones, wireless and hybrid-capable</li>';
  if (str_contains((string) $body->value, $old)) {
    $section->set('field_booking_body', [
      'value' => str_replace($old, '<li>Wireless microphones</li>', (string) $body->value),
      'format' => $body->format,
    ]);
    $section->setNeedsSave(TRUE);
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Lecture Hall: equipment no longer says hybrid-capable (palefire_location_lecture_hall_hybrid.php).');
    $node->save();
    echo "Booking text: equipment line updated\n";
  }
  break;
}
echo "Done. Run: drush cr\n";
