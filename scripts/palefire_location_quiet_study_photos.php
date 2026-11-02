<?php

/**
 * @file
 * The Quiet Study page: a "The room" photos section, empty for now.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_location_quiet_study_photos.php
 *   drush cr
 *
 * Run after palefire_location_quiet_study.php.
 *
 * Adds a Location: photos section titled "The room" to /v2/special/
 * quietstudy, right after its Location section, as on the Lecture Hall.
 * With no photos it shows three empty frames captioned "Photo coming soon"
 * (paragraph--location-photos.html.twig); add the photos in the section's
 * Photos field and they replace the frames. A new revision.
 *
 * Safe to re-run: a page that already has a photos section is left as it is.
 */

declare(strict_types=1);

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;

if (PHP_SAPI !== 'cli') {
  return;
}

if (!ParagraphsType::load('location_photos')) {
  throw new \RuntimeException('Paragraph type location_photos not found. Run palefire_location_split.php first.');
}
$path = \Drupal::service('path_alias.manager')->getPathByAlias('/v2/special/quietstudy');
$node = preg_match('#^/node/(\d+)$#', $path, $m) ? Node::load((int) $m[1]) : NULL;
if (!$node) {
  echo "No page at /v2/special/quietstudy. Run palefire_location_quiet_study.php first.\n";
  return;
}
$field = $node->hasField('field_scroll_section') ? 'field_scroll_section' : 'field_sections';
$items = [];
$at = 0;
foreach ($node->get($field) as $delta => $item) {
  $bundle = $item->entity?->bundle();
  if ($bundle === 'location_photos') {
    echo "The Quiet Study already has a photos section; left as it is.\n";
    return;
  }
  if ($bundle === 'location_space') {
    $at = $delta + 1;
  }
  $items[] = ['entity' => $item->entity];
}
$photos = Paragraph::create(['type' => 'location_photos', 'field_section_title' => 'The room']);
array_splice($items, $at, 0, [['entity' => $photos]]);
$node->set($field, $items);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('Quiet Study: "The room" photos section, empty until photos are added (palefire_location_quiet_study_photos.php).');
$node->save();
echo 'Added "The room" to the Quiet Study (node/' . $node->id() . "). Run: drush cr\n";
