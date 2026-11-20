<?php

/**
 * @file
 * Occult Science: its eight themed galleries become one wall, "The
 * Objects", on a site where palefire_exhibit_occult.php built the page.
 *
 *   drush php:script scripts/palefire_exhibit_occult_wall.php
 *   drush php:script scripts/palefire_exhibit_occult_wall.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. Each themed gallery (Ancient Origins …
 * Signs and Symbols) held two to four books, too few for a wall of its
 * own. Now one gallery, Display "wall", hangs all 22 books, each theme a
 * sub-gallery heading over its books (an entry with a heading and no
 * image, as on the Union Art Gallery). Each tile opens in the object view
 * with its catalog record and commentary, which scroll in the panel, so a
 * long commentary never stretches the wall.
 *
 * Works from the page as it is now, not from scripts/data/occult.json: the
 * book entries are copied with everything an editor changed, keeping their
 * anchors, so links from the Index and the introduction still land. Each
 * theme's heading takes the theme gallery's anchor (#fabled-creatures).
 * The new gallery takes the first theme's lead image and caption; the
 * other seven leads stay in the media library. Introduction, Index and
 * About the Exhibition are left as they are.
 *
 * Optional after "--": apply=1, nid=<node id> (default: the page at
 * /v2/special/exhibitions/occult), title=<gallery title> (default "The
 * Objects").
 *
 * Saves the page as a new revision, so the old galleries can be restored
 * from its Revisions tab. Stops if the page already has the wall, or has a
 * newer draft.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

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
$say = fn (string $line) => print(($apply ? '' : '[dry run] ') . $line . PHP_EOL);
$wall_title = $args['title'] ?? 'The Objects';
$wall_anchor = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($wall_title)), '-');

if (!FieldConfig::loadByName('paragraph', 'exhibit_page', 'field_exhibit_display')) {
  throw new \RuntimeException('field_exhibit_display is not on Gallery: run palefire_exhibit_setup.php first.');
}

$storage = \Drupal::entityTypeManager()->getStorage('node');
if (!empty($args['nid'])) {
  $node = $storage->load((int) $args['nid']);
}
else {
  $path = \Drupal::service('path_alias.manager')->getPathByAlias('/v2/special/exhibitions/occult');
  $node = preg_match('#^/node/(\d+)$#', $path, $m) ? $storage->load($m[1]) : NULL;
}
if (!$node instanceof NodeInterface || $node->bundle() !== 'modular_page' || !str_contains($node->label(), 'Occult')) {
  throw new \RuntimeException('Occult Science page not found. Pass nid=<node id>.');
}
$latest = $storage->getLatestRevisionId($node->id());
if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
  throw new \RuntimeException("The page has a newer draft (revision $latest). Publish or discard it first.");
}

// The themed galleries, in page order.
$themes = [
  'Ancient Origins', 'Nature’s Secrets', 'The Alchemical Quest', 'Science or Sorcery?',
  'Written in the Stars', 'The Sixth Sense', 'Fabled Creatures', 'Signs and Symbols',
];
$title_of = fn (ParagraphInterface $p) => trim((string) ($p->hasField('field_section_title') ? $p->get('field_section_title')->value : ''));
$anchor_of = function (ParagraphInterface $p) use ($title_of): string {
  $anchor = $p->hasField('field_section_anchor') ? trim((string) $p->get('field_section_anchor')->value) : '';
  return $anchor !== '' ? $anchor : trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($title_of($p))), '-');
};

$sections = $node->get('field_scroll_section')->referencedEntities();
foreach ($sections as $section) {
  if ($section->bundle() === 'exhibit_page' && $title_of($section) === $wall_title) {
    $say("The page already has a gallery titled “{$wall_title}”. Nothing to do.");
    return;
  }
}

$found = [];
$first_at = NULL;
foreach ($sections as $i => $section) {
  if ($section->bundle() === 'exhibit_page' && in_array($title_of($section), $themes, TRUE)) {
    $found[$title_of($section)] = $section;
    $first_at ??= $i;
  }
}
$missing = array_diff($themes, array_keys($found));
if ($missing) {
  throw new \RuntimeException('Themed galleries not found (renamed?): ' . implode(', ', $missing) . '. Nothing changed.');
}

// The wall's blocks: per theme, a heading entry, then copies of its books.
$blocks = [];
$books = 0;
$report = [];
foreach ($themes as $theme) {
  $gallery = $found[$theme];
  $heading = Paragraph::create([
    'type' => 'exhibit_entry',
    'field_block_title' => $theme,
    'field_section_anchor' => $anchor_of($gallery),
  ]);
  // Any text the theme gallery had outside its entries goes under the
  // heading.
  $intro = [];
  $entries = [];
  foreach ($gallery->get('field_guide_blocks')->referencedEntities() as $block) {
    if (!$block->isPublished()) {
      continue;
    }
    if ($block->bundle() === 'exhibit_entry') {
      $entries[] = $block->createDuplicate();
    }
    elseif ($block->bundle() === 'guide_text') {
      $intro[] = $block->createDuplicate();
    }
    else {
      $report[] = "  Left out of the wall ({$theme}): a {$block->bundle()} outside any entry. Restore it from the revision if wanted.";
    }
  }
  if ($intro) {
    $heading->set('field_guide_blocks', $intro);
  }
  $blocks[] = $heading;
  array_push($blocks, ...$entries);
  $books += count($entries);
  $report[] = sprintf('  %s (#%s): %d books%s', $theme, $anchor_of($gallery), count($entries), $intro ? ', with its text under the heading' : '');
}

// The new gallery, with the first theme's lead.
$first = $found[$themes[0]];
$values = [
  'type' => 'exhibit_page',
  'field_section_title' => $wall_title,
  'field_section_anchor' => $wall_anchor,
  'field_exhibit_display' => 'wall',
  'field_guide_blocks' => $blocks,
];
foreach (['field_exhibit_lead', 'field_exhibit_lead_caption', 'field_exhibit_lead_focus', 'field_exhibit_lead_whole', 'field_exhibit_lead_mirror', 'field_exhibit_opener'] as $field) {
  if ($first->hasField($field) && !$first->get($field)->isEmpty()) {
    $values[$field] = $first->get($field)->getValue();
  }
}
$wall = Paragraph::create($values);

// The page's sections: the wall where the first theme was, the themes out.
$new = [];
foreach ($sections as $i => $section) {
  if ($i === $first_at) {
    $new[] = $wall;
  }
  if (!in_array($section, $found, TRUE)) {
    $new[] = $section;
  }
}

$say("Occult Science (node/{$node->id()}): the eight themed galleries become one wall, “{$wall_title}” (#{$wall_anchor}), {$books} books under eight headings:");
foreach ($report as $line) {
  $say($line);
}
$say('Galleries after: ' . implode(' · ', array_map(fn ($s) => $s === $wall ? $wall_title : ($title_of($s) ?: $s->bundle()), $new)));

if (!$apply) {
  $say('Nothing saved. Run again with apply=1.');
  return;
}
$node->set('field_scroll_section', $new);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('Occult Science: the eight themed galleries as one wall, The Objects (palefire_exhibit_occult_wall.php). Restore the previous revision to undo.');
$node->save();
$say('Saved. Run: drush cr');
