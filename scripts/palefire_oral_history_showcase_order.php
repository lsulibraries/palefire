<?php

/**
 * @file
 * Oral History’s Showcase: the exhibitions newest first, and their facts.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_oral_history_showcase_order.php
 *   drush php:script themes/custom/palefire/scripts/palefire_oral_history_showcase_order.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it says what it would do and saves nothing.
 *
 * - Years (field_exhibit_year), where empty: History of Graphic Design in
 *   South Louisiana 2019 (the project closed late 2019), History of KLSU
 *   2015 (its centennial, 1915–2015).
 * - Press (field_exhibit_physical_post), where empty: Mossville History
 *   Project to “Mossville Oral History Project available online” (May 3,
 *   2017). The others were set by palefire_exhibit_physical_links.php;
 *   Jazz Fest, KLSU, Graphic Design and Do Not Tear Up My Earth have no
 *   post on the site (checked 2026-11-20).
 * - Graphic Design’s canvas (field_exhibit_cover): its poster, Don Smith
 *   painting by a window, in place of Byron Levy at Preservation Hall,
 *   and its cover label (the footnote under the canvas) to match: “Don
 *   Smith in his studio, ca. 1961”, the exhibition’s poster.
 * - Order: the Showcase’s tiles (the tile showcase on /v2/oralhistory
 *   whose items are all oral history exhibitions) newest first. Within a
 *   year, by the month the show opened: 2015 KLSU then Tempests (as
 *   before); 2014 Cooperative Extension (Oct), Jazz Fest (Apr–May), A Lady
 *   in the South (spring preview), The Relentless Pursuit of “Equal”
 *   (Jan). Any tile not listed keeps its place after these.
 *
 * Undo: drag the tiles back on the page’s edit form; clear the fields on
 * each exhibition’s edit form.
 */

declare(strict_types=1);

use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

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
$say = fn (string $line) => print(($apply ? '' : '[dry run] ') . $line . PHP_EOL);

$aliases = \Drupal::service('path_alias.manager');
$storage = \Drupal::entityTypeManager()->getStorage('node');
$base = '/v2/oralhistory/exhibitions/';
$by_alias = function (string $alias) use ($aliases, $storage): ?NodeInterface {
  $path = $aliases->getPathByAlias($alias);
  return preg_match('#^/node/(\d+)$#', $path, $m) ? $storage->load($m[1]) : NULL;
};
$save = function (NodeInterface $node, string $log) use ($apply): void {
  if ($apply) {
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage($log . ' (palefire_oral_history_showcase_order.php)');
    $node->save();
  }
};

// Years. ---------------------------------------------------------------
foreach (['graphicdesign' => 2019, 'klsu' => 2015] as $slug => $year) {
  $node = $by_alias($base . $slug);
  if (!$node || !$node->hasField('field_exhibit_year')) {
    echo "Skip $base$slug: not found, or no Year field." . PHP_EOL;
    continue;
  }
  if (!$node->get('field_exhibit_year')->isEmpty()) {
    echo "Leave “{$node->label()}”: its year is already {$node->get('field_exhibit_year')->value}." . PHP_EOL;
    continue;
  }
  $say("Year of “{$node->label()}” (node/{$node->id()}): $year.");
  $node->set('field_exhibit_year', $year);
  $save($node, "Year: $year");
}

// Press. ---------------------------------------------------------------
$mossville = $by_alias($base . 'mossvilleexhibit');
$post = $storage->load(23039);
if (!$mossville || !$mossville->hasField('field_exhibit_physical_post')) {
  echo 'Skip Mossville: not found, or no Physical exhibition post field.' . PHP_EOL;
}
elseif (!$post instanceof NodeInterface || $post->bundle() !== 'article' || !$post->isPublished() || stripos($post->label(), 'Mossville') === FALSE) {
  echo 'Skip Mossville: node/23039 is no longer a published Article about Mossville.' . PHP_EOL;
}
elseif ($current = $mossville->get('field_exhibit_physical_post')->target_id) {
  echo "Leave “{$mossville->label()}”: already linked to node/$current." . PHP_EOL;
}
else {
  $say("Press of “{$mossville->label()}” (node/{$mossville->id()}): “{$post->label()}” (node/23039).");
  $mossville->set('field_exhibit_physical_post', 23039);
  $save($mossville, 'Physical exhibition post: node/23039');
}

// Graphic Design’s canvas. -----------------------------------------------
// Its poster, Don Smith painting at a worktable by a window (the About
// page's “The exhibition’s poster.”, public://2024-09/donsmith_s.jpg),
// else the build's copy of the same photo (“Graphic Design: donsmith”).
$graphic = $by_alias($base . 'graphicdesign');
$poster = NULL;
$files = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['uri' => 'public://2024-09/donsmith_s.jpg']);
foreach ($files as $file) {
  $usage = \Drupal::service('file.usage')->listUsage($file);
  foreach (array_keys($usage['file']['media'] ?? $usage['media']['media'] ?? []) as $mid) {
    $poster = (int) $mid;
    break 2;
  }
}
if (!$poster) {
  $named = \Drupal::entityQuery('media')->accessCheck(FALSE)->condition('name', 'Graphic Design: donsmith')->range(0, 1)->execute();
  $poster = $named ? (int) reset($named) : NULL;
}
if (!$graphic || !$graphic->hasField('field_exhibit_cover')) {
  echo 'Skip Graphic Design’s canvas: not found, or no Cover field.' . PHP_EOL;
}
elseif (!$poster) {
  echo 'Skip Graphic Design’s canvas: no media for the poster (donsmith_s.jpg or “Graphic Design: donsmith”).' . PHP_EOL;
}
elseif ((int) $graphic->get('field_exhibit_cover')->target_id === $poster) {
  echo "Leave “{$graphic->label()}”: its canvas is already the poster (media/$poster)." . PHP_EOL;
}
else {
  $was = $graphic->get('field_exhibit_cover')->target_id ?: 'none';
  $say("Canvas of “{$graphic->label()}” (node/{$graphic->id()}): media/$poster, the poster (was media/$was).");
  $graphic->set('field_exhibit_cover', $poster);
  $save($graphic, "Cover: media/$poster, the exhibition’s poster");
}
// Its object label (the band's footnote under the canvas), for the poster:
// replaced while it still names Byron Levy's sketchbook, the old cover.
$label = [
  'maker' => NULL,
  'title' => 'Don Smith in his studio',
  'date' => 'ca. 1961',
  'medium' => 'The exhibition’s poster',
  'url' => NULL,
];
if ($graphic && $graphic->hasField('field_exhibit_cover_title')) {
  $title = (string) $graphic->get('field_exhibit_cover_title')->value;
  if ($title === $label['title']) {
    echo "Leave “{$graphic->label()}”: its cover label already names the poster." . PHP_EOL;
  }
  elseif ($title !== '' && $title !== 'Sketchbook and art') {
    echo "Leave “{$graphic->label()}”: its cover label is “{$title}”, edited since; set it on the edit form." . PHP_EOL;
  }
  else {
    $say("Cover label of “{$graphic->label()}”: “{$label['title']}, {$label['date']}”, {$label['medium']} (was “" . ($title ?: 'none') . '”).');
    foreach ($label as $key => $value) {
      if ($graphic->hasField('field_exhibit_cover_' . $key)) {
        $graphic->set('field_exhibit_cover_' . $key, $value);
      }
    }
    $save($graphic, 'Cover label: the poster');
  }
}

// Order. ---------------------------------------------------------------
$order = ['graphicdesign', 'gu272', 'mossvilleexhibit', 'klsu', 'storm', 'lsuag', 'jf', 'lady', 'rpoe', 'football', 'dntume'];
$page = $by_alias('/v2/oralhistory');
if (!$page) {
  echo 'Skip the order: /v2/oralhistory not found.' . PHP_EOL;
  return;
}
// Whether a paragraph is on the page now (each parent's field still holds
// it), not a leftover from an earlier build.
$on_page = function (ParagraphInterface $paragraph) use ($page): bool {
  $child = $paragraph;
  for ($depth = 0; $depth < 6; $depth++) {
    $parent = $child->getParentEntity();
    $field = $child->get('parent_field_name')->value;
    if (!$parent || !$field || !$parent->hasField($field)) {
      return FALSE;
    }
    $held = array_column($parent->get($field)->getValue(), 'target_id');
    if (!in_array($child->id(), $held)) {
      return FALSE;
    }
    if ($parent instanceof NodeInterface) {
      return (int) $parent->id() === (int) $page->id();
    }
    $child = $parent;
  }
  return FALSE;
};
$slug = function ($node) use ($aliases, $base): ?string {
  if (!$node instanceof NodeInterface || $node->bundle() !== 'modular_page') {
    return NULL;
  }
  $alias = $aliases->getAliasByPath('/node/' . $node->id());
  return str_starts_with($alias, $base) ? substr($alias, strlen($base)) : NULL;
};
$ids = \Drupal::entityQuery('paragraph')->accessCheck(FALSE)->condition('type', 'tile_showcase')->execute();
$found = 0;
foreach (\Drupal::entityTypeManager()->getStorage('paragraph')->loadMultiple($ids) as $paragraph) {
  if (!$paragraph->hasField('field_tiles') || $paragraph->get('field_tiles')->isEmpty()) {
    continue;
  }
  $tiles = [];
  foreach ($paragraph->get('field_tiles') as $delta => $item) {
    $tiles[] = ['slug' => $slug($item->entity), 'value' => $item->getValue(), 'delta' => $delta];
  }
  if (in_array(NULL, array_column($tiles, 'slug'), TRUE) || !$on_page($paragraph)) {
    continue;
  }
  $found++;
  $rank = array_flip($order);
  $sorted = $tiles;
  usort($sorted, fn ($a, $b) => [$rank[$a['slug']] ?? 99, $a['delta']] <=> [$rank[$b['slug']] ?? 99, $b['delta']]);
  $before = implode(', ', array_column($tiles, 'slug'));
  $after = implode(', ', array_column($sorted, 'slug'));
  if ($before === $after) {
    echo "Leave the Showcase (paragraph {$paragraph->id()}): already newest first." . PHP_EOL;
    continue;
  }
  $say("Showcase (paragraph {$paragraph->id()}): $before");
  $say("  becomes: $after");
  if ($apply) {
    $paragraph->set('field_tiles', array_column($sorted, 'value'));
    $paragraph->setNewRevision(FALSE);
    $paragraph->save();
    // The page's own cache tags, so its render cache drops the old order.
    \Drupal::service('cache_tags.invalidator')->invalidateTags($page->getCacheTagsToInvalidate());
  }
}
if (!$found) {
  echo 'No Showcase found on /v2/oralhistory (a tile showcase whose tiles are all oral history exhibitions).' . PHP_EOL;
}
