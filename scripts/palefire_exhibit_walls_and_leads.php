<?php

/**
 * @file
 * Exhibitions: more galleries as walls, a lead for every gallery, and
 * biographies open with each clip where clips come with lives.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_walls_and_leads.php
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_walls_and_leads.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it says what it would do and saves nothing.
 *
 * - Tiger Stadium, The Narrators: its 33 text blocks (“<h3>Name</h3>
 *   <p><strong>Role</strong></p> life…”) become an entry each, the life
 *   its Biography, the role its Role, with the narrator’s portrait from
 *   the exhibition (“Tiger Stadium: charlesalexander01”) where there is
 *   one; without one the narrator hangs as a name tile. Only while the
 *   gallery is still text blocks with no entries.
 * - Display “wall” (from story): AVES’s Birds in the New World, Featherwork,
 *   Opening Scientific Exploration, Beginning of Collections, Traveling
 *   Naturalists and The Destruction of the Atlantic Forest (its later
 *   galleries are walls already); Cooperative Extension’s Narrators;
 *   Integrating LSU’s The Nation; Tiger Stadium’s Lagniappe and The
 *   Narrators; Lady in the South’s From the Scrapbook. Loose pictures
 *   (Lagniappe, the scrapbook) hang as tiles of their own
 *   (_palefire_exhibit_loose()).
 * - Leads, where empty, for galleries with no picture of their own (the
 *   theme gives every other gallery its first picture): AVES’s Sources
 *   (Marcgraf’s page) and Acknowledgments (Hernández’s toucan); Occult’s
 *   Index (the Iamblichus title page); Integrating LSU’s Timeline (marchers
 *   arm in arm, 1968) and About the Exhibition (students on the steps);
 *   Mossville’s The Full Interviews (an interview under way); Tiger
 *   Stadium’s The Narrators (the 1958 champions); Graphic Design’s The
 *   Interviews (Byron Levy’s Preservation Hall print, its old cover).
 * - “Open biographies with each clip”, where off: Georgetown 272 and
 *   Cooperative Extension.
 *
 * Galleries are saved in place, without a new revision (as the other
 * exhibition scripts); a page with a newer draft is skipped. Safe to re-run.
 * Undo: set Display back to “Story” and clear the Lead on each gallery’s
 * edit form; untick the checkbox on the page.
 */

declare(strict_types=1);

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

$sc = '/v2/special/exhibitions/';
$oh = '/v2/oralhistory/exhibitions/';
$aliases = \Drupal::service('path_alias.manager');
$nodes = \Drupal::entityTypeManager()->getStorage('node');
$media_storage = \Drupal::entityTypeManager()->getStorage('media');

// The page at an alias, unless it has a newer draft.
$page = function (string $alias) use ($aliases, $nodes): ?NodeInterface {
  $path = $aliases->getPathByAlias($alias);
  $node = preg_match('#^/node/(\d+)$#', $path, $m) ? $nodes->load($m[1]) : NULL;
  if (!$node instanceof NodeInterface) {
    echo "No page at $alias. Skipped." . PHP_EOL;
    return NULL;
  }
  $latest = $nodes->getLatestRevisionId($node->id());
  if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
    echo "$alias has a newer draft (revision $latest). Skipped: publish or discard it first." . PHP_EOL;
    return NULL;
  }
  return $node;
};
// A page's gallery by its heading.
$gallery = function (NodeInterface $node, string $title): ?ParagraphInterface {
  foreach ($node->get('field_scroll_section')->referencedEntities() as $section) {
    if ($section->bundle() === 'exhibit_page' && trim((string) $section->get('field_section_title')->value) === $title) {
      return $section;
    }
  }
  echo "  “{$node->label()}” has no gallery “{$title}”. Skipped." . PHP_EOL;
  return NULL;
};
// A media item by its name, else by its file’s name.
$media = function (string $name) use ($media_storage): ?int {
  $ids = \Drupal::entityQuery('media')->accessCheck(FALSE)->condition('name', $name)->range(0, 1)->execute();
  if ($ids) {
    return (int) reset($ids);
  }
  $file = preg_replace('/^[^:]+:\s*/', '', $name);
  $files = \Drupal::entityQuery('file')->accessCheck(FALSE)->condition('filename', $file . '.', 'STARTS_WITH')->execute();
  foreach ($files as $fid) {
    $usage = \Drupal::service('file.usage')->listUsage(\Drupal::entityTypeManager()->getStorage('file')->load($fid));
    foreach (array_keys($usage['file']['media'] ?? $usage['media']['media'] ?? []) as $mid) {
      return (int) $mid;
    }
  }
  return NULL;
};
$saves = 0;
$save = function (ParagraphInterface $paragraph) use ($apply, &$saves): void {
  $saves++;
  if ($apply) {
    $paragraph->setNewRevision(FALSE);
    $paragraph->save();
  }
};
$touched = [];

// Tiger Stadium’s narrators, as entries. ----------------------------------
if (($football = $page($oh . 'football')) && ($narrators = $gallery($football, 'The Narrators'))) {
  $touched[$football->id()] = $football;
  $blocks = $narrators->get('field_guide_blocks')->referencedEntities();
  if (array_filter($blocks, fn ($b) => $b->bundle() !== 'guide_text')) {
    echo 'Leave The Narrators: it has entries (or other blocks) already.' . PHP_EOL;
  }
  else {
    // The exhibition’s pictures by name without the trailing number:
    // “charlesalexander” => “Tiger Stadium: charlesalexander01”.
    $portraits = [];
    $ids = \Drupal::entityQuery('media')->accessCheck(FALSE)->condition('name', 'Tiger Stadium: ', 'STARTS_WITH')->sort('name')->execute();
    foreach ($media_storage->loadMultiple($ids) as $item) {
      $key = preg_replace('/\d+$/', '', preg_replace('/[^a-z0-9]/', '', strtolower(substr($item->label(), strlen('Tiger Stadium: ')))));
      $portraits[$key] ??= (int) $item->id();
    }
    $has_role = Paragraph::create(['type' => 'exhibit_entry'])->hasField('field_entry_role');
    $new = [];
    $with = 0;
    $without = [];
    foreach ($blocks as $block) {
      $body = trim((string) $block->get('field_block_body')->value);
      $format = $block->get('field_block_body')->format;
      if (!preg_match('#^<h3[^>]*>(.*?)</h3>\s*(?:<p>\s*<strong>(.*?)</strong>\s*</p>)?(.*)$#su', $body, $m)) {
        // No name: it stays as the gallery’s text.
        $new[] = $block;
        continue;
      }
      $name = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
      $role = trim(html_entity_decode(strip_tags($m[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
      $bio = trim($m[3]);
      if ($role !== '' && !$has_role) {
        $bio = '<p><strong>' . htmlspecialchars($role) . '</strong></p>' . $bio;
      }
      $values = [
        'type' => 'exhibit_entry',
        'field_block_title' => $name,
        'field_entry_bio' => ['value' => $bio, 'format' => $format],
      ];
      if ($role !== '' && $has_role) {
        $values['field_entry_role'] = $role;
      }
      $mid = $portraits[preg_replace('/[^a-z0-9]/', '', strtolower($name))] ?? NULL;
      if ($mid) {
        $values['field_guide_blocks'] = [Paragraph::create([
          'type' => 'exhibit_figure',
          'field_figure_image' => ['target_id' => $mid],
          'field_figure_layout' => 'side_start',
        ])];
        $with++;
      }
      else {
        $without[] = $name;
      }
      $new[] = Paragraph::create($values);
    }
    $count = count(array_filter($new, fn ($p) => $p->isNew()));
    $say("The Narrators: $count text blocks become entries, $with with their portrait" . ($without ? '; as name tiles: ' . implode(', ', $without) : '') . '.');
    $narrators->set('field_guide_blocks', $new);
    $save($narrators);
  }
}

// Walls. ------------------------------------------------------------------
$walls = [
  $sc . 'aves' => ['Birds in the New World', 'Featherwork', 'Opening Scientific Exploration', 'Beginning of Collections', 'Traveling Naturalists', 'The Destruction of the Atlantic Forest'],
  $oh . 'lsuag' => ['Narrators'],
  $oh . 'rpoe' => ['The Nation'],
  $oh . 'football' => ['Lagniappe', 'The Narrators'],
  $oh . 'lady' => ['From the Scrapbook'],
];
foreach ($walls as $alias => $titles) {
  if (!$node = $page($alias)) {
    continue;
  }
  $touched[$node->id()] = $node;
  foreach ($titles as $title) {
    if (!$section = $gallery($node, $title)) {
      continue;
    }
    $display = (string) $section->get('field_exhibit_display')->value;
    if ($display === 'wall') {
      echo "Leave “{$node->label()}” / $title: a wall already." . PHP_EOL;
      continue;
    }
    if ($display !== '' && $display !== 'story') {
      echo "Leave “{$node->label()}” / $title: its display is “{$display}”." . PHP_EOL;
      continue;
    }
    $say("“{$node->label()}” / $title: story → wall.");
    $section->set('field_exhibit_display', 'wall');
    $save($section);
  }
}

// Leads. ------------------------------------------------------------------
$leads = [
  [$sc . 'aves', 'Sources', 'AVES: Marcgraf page'],
  [$sc . 'aves', 'Acknowledgments', 'AVES: Hernandez toucan'],
  [$sc . 'occult', 'Index', 'Occult: iamblichus-de-mysteriis-title-page'],
  [$oh . 'rpoe', 'Timeline', 'Integrating LSU: march1968'],
  [$oh . 'rpoe', 'About the Exhibition', 'Integrating LSU: harambe'],
  [$oh . 'mossvilleexhibit', 'The Full Interviews', 'Mossville: 9_shot_of_room_butch_jen_wife_equipment'],
  [$oh . 'football', 'The Narrators', 'Tiger Stadium: 1958champs'],
  [$oh . 'graphicdesign', 'The Interviews', 'Graphic Design: byron_levy_preservation_hall'],
];
foreach ($leads as [$alias, $title, $name]) {
  if (!($node = $page($alias)) || !($section = $gallery($node, $title))) {
    continue;
  }
  $touched[$node->id()] = $node;
  if (!$section->get('field_exhibit_lead')->isEmpty()) {
    echo "Leave “{$node->label()}” / $title: it has a lead (" . $section->get('field_exhibit_lead')->entity?->label() . ').' . PHP_EOL;
    continue;
  }
  if (!$mid = $media($name)) {
    echo "Skip “{$node->label()}” / $title: no media “{$name}”." . PHP_EOL;
    continue;
  }
  $say("“{$node->label()}” / $title: lead media/$mid ($name).");
  $section->set('field_exhibit_lead', ['target_id' => $mid]);
  $save($section);
}

// Biographies open with each clip. ------------------------------------------
foreach ([$oh . 'gu272', $oh . 'lsuag'] as $alias) {
  if (!$node = $page($alias)) {
    continue;
  }
  if (!$node->hasField('field_exhibit_bios_open')) {
    echo 'Skip biographies: no field_exhibit_bios_open. Run scripts/palefire_exhibit_bios_open.php first.' . PHP_EOL;
    break;
  }
  if ($node->get('field_exhibit_bios_open')->value) {
    echo "Leave “{$node->label()}”: biographies open already." . PHP_EOL;
    continue;
  }
  $say("“{$node->label()}”: open biographies with each clip.");
  $node->set('field_exhibit_bios_open', 1);
  $saves++;
  if ($apply) {
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Open biographies with each clip (palefire_exhibit_walls_and_leads.php)');
    $node->save();
  }
}

if (!$apply) {
  echo "Dry run: $saves saves to make. Nothing saved. Add apply=1 to save." . PHP_EOL;
  return;
}
foreach ($touched as $node) {
  \Drupal::service('cache_tags.invalidator')->invalidateTags($node->getCacheTagsToInvalidate());
}
$nodes->resetCache(array_keys($touched));
echo "Saved ($saves saves). Run drush cr." . PHP_EOL;
