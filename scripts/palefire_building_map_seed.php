<?php

/**
 * @file
 * Adds a starter Hill Memorial building map (heading, intro, all eight rooms)
 * to the bento search section of one page. Run after
 * palefire_building_map_setup.php. Idempotent: a bento that already has a map
 * is left alone.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_building_map_seed.php -- /special
 *   drush cr
 *
 * The argument is the page's path alias (without the site's /v2 base path) or
 * its node ID. Default: /special. Edit the text and add room links afterwards in
 * the bento section's "Building map" field.
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

if (PHP_SAPI !== 'cli') {
  return;
}

foreach (['building_map', 'map_space'] as $bundle) {
  if (!\Drupal::entityTypeManager()->getStorage('paragraphs_type')->load($bundle)) {
    echo "Paragraph type $bundle not found. Run palefire_building_map_setup.php first.\n";
    return;
  }
}

// Find the page ---------------------------------------------------------------
$target = $extra[0] ?? '/special';
$node = NULL;
if (ctype_digit((string) $target)) {
  $node = Node::load((int) $target);
}
else {
  $aliases = \Drupal::service('path_alias.manager');
  foreach (array_unique(['/' . ltrim($target, '/'), '/' . ltrim(preg_replace('#^/?v2/#', '', $target), '/')]) as $alias) {
    if (preg_match('#^/node/(\d+)$#', $aliases->getPathByAlias($alias), $m)) {
      $node = Node::load((int) $m[1]);
      break;
    }
  }
}
if (!$node) {
  echo "No node found for \"$target\". Pass the path alias (e.g. /special) or the node ID.\n";
  return;
}
echo "Page: node/{$node->id()} \"{$node->label()}\"\n";

// Find bento_search paragraphs on it (top level, or one level down) -----------
$find = function ($entity, int $depth) use (&$find): array {
  $found = [];
  foreach ($entity->getFields() as $items) {
    if ($items->getFieldDefinition()->getType() !== 'entity_reference_revisions') {
      continue;
    }
    foreach ($items as $item) {
      $child = $item->entity;
      if (!$child instanceof ParagraphInterface) {
        continue;
      }
      if ($child->bundle() === 'bento_search') {
        $found[] = $child;
      }
      elseif ($depth < 1) {
        $found = array_merge($found, $find($child, $depth + 1));
      }
    }
  }
  return $found;
};
$bentos = $find($node, 0);
if (!$bentos) {
  echo "No bento_search section on this page.\n";
  return;
}

// Starter content (edit in the UI afterwards) ----------------------------------
$rooms = [
  ['reading_room', 'walk_in', 'Reading Room', 'Use Special Collections materials at the reading room tables. No appointment needed during open hours.'],
  ['gallery_1', 'walk_in', 'Floor 1 Gallery', 'Our current exhibition on the first floor. Open to everyone during building hours.'],
  ['lecture_hall', 'appointment', 'Lecture Hall', 'Talks, class sessions and events. Request a date with your group size and AV needs.'],
  ['gallery_2', 'walk_in', 'Floor 2 Gallery', 'Second-floor exhibition space overlooking the lobby. Open to everyone during building hours.'],
  ['quiet_study', 'walk_in', 'Quiet Study', 'Individual, quiet study on the second floor. Walk in any time the building is open.'],
  ['seminar_space', 'appointment', 'Seminar Space', 'A small-group room for classes and instruction sessions using collection materials.'],
  ['mcilhenny_room', 'appointment', 'McIlhenny Room', 'Available by request for meetings and events. Contact Special Collections to book.'],
  ['oral_history', 'appointment', 'Oral History', 'Meet with oral history staff or listen to recordings by appointment.'],
];
$langcode = $node->language()->getId();
$format = filter_default_format();

foreach ($bentos as $bento) {
  if (!$bento->hasField('field_bento_map')) {
    echo "Bento paragraph {$bento->id()} has no field_bento_map. Run palefire_building_map_setup.php first.\n";
    continue;
  }
  if (!$bento->get('field_bento_map')->isEmpty()) {
    echo "Bento paragraph {$bento->id()} already has a map; skipped.\n";
    continue;
  }

  $map = Paragraph::create([
    'type' => 'building_map',
    'langcode' => $langcode,
    'field_map_heading' => 'Visit Hill Memorial Library',
    'field_map_intro' => [
      'value' => '<p>Exhibitions, the reading room and quiet study are open to everyone. Seminar, lecture, McIlhenny Room and oral history spaces are available by request.</p>',
      'format' => $format,
    ],
  ]);
  $map->setParentEntity($bento, 'field_bento_map');
  $map->save();

  $spaces = [];
  foreach ($rooms as [$region, $access, $title, $text]) {
    $space = Paragraph::create([
      'type' => 'map_space',
      'langcode' => $langcode,
      'field_map_region' => $region,
      'field_space_access' => $access,
      'field_space_title' => $title,
      'field_space_body' => ['value' => "<p>$text</p>", 'format' => $format],
    ]);
    // The tooltip id uses the parent map's id, so set it before saving.
    $space->setParentEntity($map, 'field_map_spaces');
    $space->save();
    $spaces[] = ['target_id' => $space->id(), 'target_revision_id' => $space->getRevisionId()];
  }
  $map->set('field_map_spaces', $spaces);
  $map->save();

  // Save the bento in place (same revision the page already points to).
  $bento->set('field_bento_map', ['target_id' => $map->id(), 'target_revision_id' => $map->getRevisionId()]);
  $bento->setNewRevision(FALSE);
  $bento->save();
  echo "Added building map {$map->id()} with " . count($spaces) . " rooms to bento paragraph {$bento->id()}.\n";
}

// The page's render cache holds the old bento markup.
\Drupal\Core\Cache\Cache::invalidateTags($node->getCacheTags());
echo "Done. Run drush cr, then check the page.\n";
