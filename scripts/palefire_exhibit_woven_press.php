<?php

/**
 * @file
 * Woven Louisiana History: what the 2024 press release adds, on a page
 * already built by palefire_exhibit_woven.php.
 *
 *   drush php:script scripts/palefire_exhibit_woven_press.php -- images=$HOME/woven-web/masters
 *   drush php:script scripts/palefire_exhibit_woven_press.php -- images=$HOME/woven-web/masters apply=1
 *   drush cr
 *
 * A dry run unless apply=1. LSU Libraries’ press release of August 29,
 * 2024 (lib.lsu.edu/24/08/29/fallexhibition) dates the Hill Memorial
 * Library exhibition (September 23–December 13, 2024), names its
 * organizers, and captions three photographs whose scans are in the
 * exhibition’s Publicity images folder. From data/woven.json:
 * - the credit line and the physical exhibition note;
 * - the cover’s label and Gladys Clark’s portrait: “with handwoven
 *   towels, c. 1980” (the old page said placemats, 1980s), and its alt;
 * - the gallery’s lead: Gladys Clark weaving in costume, c. 1950, in place
 *   of the uncaptioned coton jaune photograph;
 * - a picture tile, the Acadian Handicraft display in New York, c. 1949,
 *   after The Acadian Handicraft Project;
 * - a text plate, The Hill Memorial Library Exhibition, with Michael E.
 *   Mamp’s and Leah Wood Jewett’s words, before About the Interviews.
 *
 * Only adds what is missing and sets what differs, so a second run changes
 * nothing. A page not built yet is left alone: palefire_exhibit_woven.php
 * builds it with all of this. The gallery is saved in place; the page in
 * a new revision.
 */

declare(strict_types=1);

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;

if (PHP_SAPI !== 'cli') {
  return;
}

$args = [];
foreach ((isset($extra) && is_array($extra)) ? $extra : [] as $arg) {
  if (str_contains((string) $arg, '=')) {
    [$k, $v] = explode('=', (string) $arg, 2);
    $args[trim($k)] = trim($v, " \t\"'");
  }
}
$apply = ($args['apply'] ?? '') === '1';
$prefix = $apply ? '' : '[dry run] ';

$data = json_decode((string) file_get_contents(__DIR__ . '/data/woven.json'), TRUE);
if (!is_array($data) || count($data['pages'] ?? []) !== 1) {
  throw new \RuntimeException('Couldn’t read data/woven.json.');
}
$page_data = $data['pages'][0];
$by_anchor = [];
foreach ($page_data['blocks'] as $b) {
  $by_anchor[$b['anchor']] = $b;
}

$path = \Drupal::service('path_alias.manager')->getPathByAlias('/v2/oralhistory/exhibitions/woven');
$nodes = \Drupal::entityTypeManager()->getStorage('node');
$node = preg_match('#^/node/(\d+)$#', $path, $m) ? $nodes->load($m[1]) : NULL;
if (!$node instanceof NodeInterface) {
  echo 'No page at /v2/oralhistory/exhibitions/woven yet: palefire_exhibit_woven.php builds it with these changes. Nothing to do.' . PHP_EOL;
  return;
}
$latest = $nodes->getLatestRevisionId($node->id());
if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
  echo "The page has a newer draft (revision $latest). Publish or discard it first. Nothing saved." . PHP_EOL;
  return;
}
$gallery = NULL;
foreach ($node->get('field_scroll_section')->referencedEntities() as $section) {
  if ($section->bundle() === 'exhibit_page' && (string) $section->get('field_section_anchor')->value === $page_data['anchor']) {
    $gallery = $section;
  }
}
if (!$gallery) {
  throw new \RuntimeException('“' . $node->label() . '” has no ' . $page_data['title'] . ' gallery.');
}

$images_dir = rtrim($args['images'] ?? '', '/\\');
// The text format the build used (its credit’s), never full_html.
$format = (string) ($node->hasField('field_exhibit_credit') ? $node->get('field_exhibit_credit')->format : '') ?: 'wysiwyg';
$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];
$changes = [];

// Images: one media item per file ("Woven: <file>"), as the build does.
$media_type = NULL;
foreach (\Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple() as $candidate) {
  if ($candidate->getSource()->getPluginId() === 'image' && ($media_type === NULL || $candidate->id() === 'image')) {
    $media_type = $candidate;
  }
}
$source_field = $media_type->getSource()->getConfiguration()['source_field'];
$find_media = function (string $file) use ($media_type): ?Media {
  $ids = \Drupal::entityQuery('media')->accessCheck(FALSE)
    ->condition('bundle', $media_type->id())->condition('name', 'Woven: ' . pathinfo($file, PATHINFO_FILENAME))->range(0, 1)->execute();
  return $ids ? Media::load(reset($ids)) : NULL;
};
$media = function (string $file) use ($apply, $prefix, $images_dir, $find_media, $media_type, $source_field, $data): ?int {
  if ($existing = $find_media($file)) {
    return (int) $existing->id();
  }
  $path = $images_dir . '/' . $file;
  if ($images_dir === '' || !is_file($path)) {
    throw new \RuntimeException("Image not found: $path. Pass images=<folder>: woven-web/masters on the server.");
  }
  [$width, $height] = getimagesize($path) ?: [NULL, NULL];
  echo "{$prefix}Import $file ({$width} × {$height})" . PHP_EOL;
  if (!$apply) {
    return NULL;
  }
  $fs = \Drupal::service('file_system');
  $destination = 'public://exhibitions/woven';
  $fs->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  $item = File::create(['uri' => $fs->copy($path, "$destination/$file", FileExists::Rename), 'status' => 1]);
  $item->save();
  $entity = Media::create([
    'bundle' => $media_type->id(),
    'name' => 'Woven: ' . pathinfo($file, PATHINFO_FILENAME),
    'status' => 1,
    $source_field => ['target_id' => $item->id(), 'alt' => $data['alt'][$file] ?? '', 'width' => $width, 'height' => $height],
  ]);
  $entity->save();
  return (int) $entity->id();
};

// Paragraphs for a new entry, as the build makes them.
$build = function (array $b) use (&$build, $media, $html): Paragraph {
  switch ($b['type']) {
    case 'text':
      return Paragraph::create(['type' => 'guide_text', 'field_block_body' => $html($b['body']), 'field_text_style' => $b['style'] ?? 'default']);

    case 'figure':
      $id = $media($b['image']);
      return Paragraph::create(array_filter([
        'type' => 'exhibit_figure',
        'field_figure_image' => $id ? ['target_id' => $id] : NULL,
        'field_figure_caption' => $html($b['caption']),
        'field_figure_layout' => $b['layout'] ?? 'side_start',
      ]));
  }
  return Paragraph::create(array_filter([
    'type' => 'exhibit_entry',
    'field_block_title' => $b['title'],
    'field_section_anchor' => $b['anchor'],
    'field_entry_summary' => $b['summary'] ?? NULL,
    'field_guide_blocks' => array_map($build, $b['blocks']),
  ]));
};

// The page’s own fields.
$node_values = [
  'field_exhibit_credit' => $html($data['credit']),
  'field_exhibit_physical_note' => $data['physical_note'],
  'field_exhibit_cover_title' => 'Gladys Clark with handwoven towels',
  'field_exhibit_cover_date' => 'c. 1980',
];
$node_changed = FALSE;
foreach ($node_values as $name => $value) {
  if (!$node->hasField($name)) {
    continue;
  }
  $now = is_array($value) ? trim((string) $node->get($name)->value) : (string) $node->get($name)->value;
  if ($now !== (is_array($value) ? $value['value'] : $value)) {
    $changes[] = "Page: $name";
    $node->set($name, $value);
    $node_changed = TRUE;
  }
}

// Gladys Clark’s portrait: its alt (the media item) and caption.
$portrait = $find_media('gladys-clark-with-towels.jpg');
$portrait_alt = $data['alt']['gladys-clark-with-towels.jpg'];
$alt_changed = $portrait && (string) $portrait->get($source_field)->alt !== $portrait_alt;
if ($alt_changed) {
  $changes[] = 'Alt text: Woven: gladys-clark-with-towels (placemats → towels)';
}

$blocks = $gallery->get('field_guide_blocks')->referencedEntities();
$anchors = array_map(fn ($p) => $p->hasField('field_section_anchor') ? (string) $p->get('field_section_anchor')->value : '', $blocks);
$clark_caption = $by_anchor['gladys-clark']['blocks'][0]['caption'];
$caption_target = NULL;
$i = array_search('gladys-clark', $anchors, TRUE);
if ($i !== FALSE) {
  foreach ($blocks[$i]->get('field_guide_blocks')->referencedEntities() as $child) {
    if ($child->bundle() === 'exhibit_figure' && trim((string) $child->get('field_figure_caption')->value) !== trim($clark_caption)) {
      $caption_target = $child;
      $changes[] = 'Caption: Gladys Clark with handwoven towels, c. 1980';
      break;
    }
  }
}

// The lead.
$lead_media = $find_media($page_data['lead']);
$lead_now = (int) $gallery->get('field_exhibit_lead')->target_id;
$lead_new = $lead_media ? (int) $lead_media->id() : NULL;
$lead_changed = $lead_new === NULL || $lead_now !== $lead_new;
if ($lead_changed) {
  $changes[] = 'Lead: Gladys Clark weaving in costume, c. 1950';
}

// The new tile and plate, where missing, in data/woven.json’s order.
$order = array_column($page_data['blocks'], 'anchor');
$missing = array_values(array_diff(['an-acadian-handicraft-display-in-new-york', 'the-hill-memorial-library-exhibition'], $anchors));
foreach ($missing as $anchor) {
  $changes[] = 'Add: ' . $by_anchor[$anchor]['title'];
}

if (!$changes) {
  echo 'Nothing to do: the page already has the press release’s changes.' . PHP_EOL;
  return;
}
foreach ($changes as $line) {
  echo "{$prefix}$line" . PHP_EOL;
}
if (!$apply) {
  if ($lead_changed && !$lead_media) {
    $media($page_data['lead']);
  }
  foreach ($missing as $anchor) {
    foreach ($by_anchor[$anchor]['blocks'] as $b) {
      if ($b['type'] === 'figure' && !$find_media($b['image'])) {
        $media($b['image']);
      }
    }
  }
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

if ($alt_changed) {
  $portrait->get($source_field)->alt = $portrait_alt;
  $portrait->save();
}
if ($caption_target) {
  $caption_target->set('field_figure_caption', $html($clark_caption));
  $caption_target->setNewRevision(FALSE);
  $caption_target->save();
}
if ($lead_changed) {
  $gallery->set('field_exhibit_lead', ['target_id' => $media($page_data['lead'])]);
  if ($gallery->hasField('field_exhibit_lead_caption')) {
    $gallery->set('field_exhibit_lead_caption', $html($page_data['lead_caption']));
  }
  if ($gallery->hasField('field_exhibit_lead_focus')) {
    $gallery->set('field_exhibit_lead_focus', $page_data['focus'] ?? 'center');
  }
}
if ($missing) {
  // Keep every block; put each new one in its place in the data’s order.
  $keyed = [];
  foreach ($blocks as $n => $block) {
    $keyed[] = [$anchors[$n], $block];
  }
  foreach ($missing as $anchor) {
    $want = array_search($anchor, $order, TRUE);
    $at = count($keyed);
    foreach ($keyed as $n => [$have]) {
      $pos = array_search($have, $order, TRUE);
      if ($pos !== FALSE && $pos > $want) {
        $at = $n;
        break;
      }
    }
    array_splice($keyed, $at, 0, [[$anchor, $build($by_anchor[$anchor])]]);
  }
  $gallery->set('field_guide_blocks', array_column($keyed, 1));
}
$gallery->setNewRevision(FALSE);
$gallery->save();
if ($node_changed) {
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage('Woven Louisiana History: credit, exhibition note and cover label from the 2024 press release (palefire_exhibit_woven_press.php).');
  $node->save();
}
\Drupal::service('cache_tags.invalidator')->invalidateTags($node->getCacheTagsToInvalidate());
echo 'Saved. Run drush cr.' . PHP_EOL;
