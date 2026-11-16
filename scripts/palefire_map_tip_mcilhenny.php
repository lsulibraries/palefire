<?php

/**
 * @file
 * Shortens the McIlhenny Room's text in the building map's room label.
 *
 *   drush php:script scripts/palefire_map_tip_mcilhenny.php
 *   drush php:script scripts/palefire_map_tip_mcilhenny.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. The label shows the room term's description
 * (hill_memorial.mcilhenny_room), unless a map's Room override has its own
 * Tooltip text: those are listed, not changed, since an editor set them for
 * one page. The old text is printed so it can be restored by hand.
 *
 * Why: the label is a quick note over the map, about three lines; the full
 * description belongs on the room's own page.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
  return;
}

$args = [];
foreach ((isset($extra) && is_array($extra)) ? $extra : [] as $arg) {
  [$key, $value] = array_pad(explode('=', (string) $arg, 2), 2, '');
  $args[$key] = $value;
}
$apply = ($args['apply'] ?? '') === '1';
$prefix = $apply ? '' : '[dry run] ';

$room_key = 'hill_memorial.mcilhenny_room';
$text = '<p>Our main instruction space, with seats for about fifty, a large screen and display tables. Contact us to plan a class.</p>';

$types = \Drupal::entityTypeManager();
$terms = $types->getStorage('taxonomy_term');
$found = $terms->getQuery()->accessCheck(FALSE)
  ->condition('vid', 'building_space')
  ->condition('field_map_key', $room_key)
  ->range(0, 1)->execute();
$term = $found ? $terms->load(reset($found)) : NULL;
if (!$term) {
  throw new \RuntimeException("Room term $room_key not found.");
}

$old = (string) $term->get('description')->value;
if (trim($old) === $text) {
  echo "The room term already has the short text.\n";
}
else {
  echo "Old text:\n$old\n\nNew text:\n$text\n";
  if ($apply) {
    $format = (string) ($term->get('description')->format ?: 'basic_html');
    $term->set('description', ['value' => $text, 'format' => $format]);
    $term->save();
  }
  echo $prefix . 'Updated the room term (' . $term->id() . ").\n";
}

// Room overrides with their own text win over the term on that map.
$paragraphs = $types->getStorage('paragraph');
$ids = $paragraphs->getQuery()->accessCheck(FALSE)
  ->condition('type', 'map_room_override')
  ->condition('field_room_key', $room_key)
  ->execute();
foreach ($paragraphs->loadMultiple($ids) as $override) {
  if ($override->hasField('field_space_body') && !$override->get('field_space_body')->isEmpty()) {
    echo 'Note: Room override paragraph ' . $override->id() . ' has its own Tooltip text, which wins on its map. Edit it in the page form.' . "\n";
  }
}
