<?php

/**
 * @file
 * A Lady in the South: History as a wall of six chapters.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_lady_history_wall.php
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_lady_history_wall.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1.
 *
 * History was one essay of 13 paragraphs, with no headings, and 21
 * photographs between them. It becomes a wall in the way of a museum
 * wall: six text plates (an entry with a heading, a Summary and the
 * chapter’s paragraphs, unchanged), each followed by its photographs, now
 * entries with a short title of their own (their captions are mostly
 * credit lines, or repeat). The object view steps through them in order,
 * so the essay still reads through: plate, its pictures, next plate.
 *
 * Only while the gallery is still those 13 text blocks and 21 figures,
 * in that order, with no entries: each paragraph’s opening words and each
 * picture’s file are checked first, and nothing is saved if one differs.
 * The gallery’s lead stays. Saved in place, without a new revision, as
 * the other exhibition scripts. Needs the Summary field on entries
 * (palefire_exhibit_bio_setup.php).
 *
 * Undo: set the gallery’s Display back to Story; the text and pictures
 * are all still there, in entries.
 */

declare(strict_types=1);

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

// The chapters: heading, summary, the opening words of each paragraph,
// then each picture (its file, without .jpg) with its title.
$chapters = [
  [
    'title' => 'Vocational education and the home',
    'summary' => 'Land-grant acts made home economics a vocation, as industry split work from home and families worried their daughters would lose the skills of the kitchen.',
    'texts' => ['The national importance of vocational education', 'The history of home management at LSU', 'It is within this cultural landscape'],
    'pictures' => ['hist_1' => 'Pressure canning', 'hist_2' => 'Around the wood stove', 'hist_3' => 'Canning at the sink'],
  ],
  [
    'title' => 'Schools in the Depression',
    'summary' => 'New Deal money rebuilt Louisiana’s schools with home economics rooms and cottages, and both LSU and Southern University trained home economics teachers.',
    'texts' => ['In the early 1930s in Louisiana', 'Vocational education in colleges in Louisiana'],
    'pictures' => ['hist_4' => 'Hoeing the rows', 'hist_5' => 'A family meal', 'hist_7' => 'At the rolling ironer', 'hist_8' => 'Ironing in a sunlit room', 'hist_9' => 'Ironing linens, 1939'],
  ],
  [
    'title' => 'Home economics at LSU',
    'summary' => 'Founded in 1915 with three sewing machines, LSU’s home economics program grew through two world wars; after the second, married couples lived in the houses.',
    'texts' => ['The LSU Women’s Home Economics Program, founded in 1915', 'For example, after World War II'],
    'pictures' => ['hist_10' => 'At the sewing machine, 1939', 'hist_11' => 'In the living room, 1939', 'hist_12' => 'The cottage in the Gumbo, 1939', 'hist_14' => '“All-Feminine Reign Ends at Morris House”'],
  ],
  [
    'title' => 'Huey Long builds a campus',
    'summary' => 'Huey Long set out to make LSU a national leader, and money from the sale of the Capitol grounds built, among much else, a home economics practice cottage.',
    'texts' => ['The LSU Women’s Home Economics Program in the 1930s', 'Long’s vision of LSU'],
    'pictures' => ['hist_16' => 'Huey Long at Homecoming, 1933', 'hist_19' => 'Campus map, 1939', 'hist_15' => 'The campus from the air, 1939', 'hist_17' => 'The original cottage, 1934'],
  ],
  [
    'title' => 'Federal and state money',
    'summary' => 'Under Governor Leche, New Deal projects reshaped the campus; a new infirmary took the cottage’s place, and the state built two home management houses.',
    'texts' => ['Federal funding did not have a major impact', 'The state also contributed funds'],
    'pictures' => ['hist_18' => 'Finding the Agnes Morris House, 1939', 'hist_20' => 'Home Economics Club, 1933'],
  ],
  [
    'title' => 'Living in the houses',
    'summary' => 'Home economics was among the most popular choices for women in the late 1930s, and each major lived nine weeks in the Helen Carter or Agnes Morris House.',
    'texts' => ['In the late 1930s and early 1940s', 'The women carried out their residences'],
    'pictures' => ['hist_23' => 'The Agnes Morris House', 'hist_22' => 'Laundry day, ca. 1943', 'hist_24' => 'The Helen Carter House, before 2011'],
  ],
];

$path = \Drupal::service('path_alias.manager')->getPathByAlias('/v2/oralhistory/exhibitions/lady');
$nodes = \Drupal::entityTypeManager()->getStorage('node');
$node = preg_match('#^/node/(\d+)$#', $path, $m) ? $nodes->load($m[1]) : NULL;
if (!$node instanceof NodeInterface) {
  throw new \RuntimeException('No page at /v2/oralhistory/exhibitions/lady.');
}
$latest = $nodes->getLatestRevisionId($node->id());
if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
  echo "The page has a newer draft (revision $latest). Publish or discard it first. Nothing saved." . PHP_EOL;
  return;
}
$history = NULL;
foreach ($node->get('field_scroll_section')->referencedEntities() as $section) {
  if ($section->bundle() === 'exhibit_page' && trim((string) $section->get('field_section_title')->value) === 'History') {
    $history = $section;
  }
}
if (!$history) {
  throw new \RuntimeException('“' . $node->label() . '” has no History gallery.');
}
if (!Paragraph::create(['type' => 'exhibit_entry'])->hasField('field_entry_summary')) {
  throw new \RuntimeException('Entries have no Summary field. Run scripts/palefire_exhibit_bio_setup.php first.');
}

$blocks = $history->get('field_guide_blocks')->referencedEntities();
$texts = array_values(array_filter($blocks, fn ($b) => $b->bundle() === 'guide_text'));
$figures = array_values(array_filter($blocks, fn ($b) => $b->bundle() === 'exhibit_figure'));
if (count($texts) + count($figures) !== count($blocks)) {
  echo 'Leave History: it has entries or other blocks already (' . count($blocks) . ' blocks). Nothing saved.' . PHP_EOL;
  return;
}
$plain = fn ($html) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
// Each figure by its file name (“hist_14”), from its media item.
$by_file = [];
foreach ($figures as $figure) {
  $media = $figure->get('field_figure_image')->entity;
  $file = '';
  if ($media) {
    $source = $media->getSource()->getConfiguration()['source_field'] ?? NULL;
    $file = $source && $media->hasField($source) && $media->get($source)->entity ? pathinfo($media->get($source)->entity->getFilename(), PATHINFO_FILENAME) : '';
    if ($file === '' || !preg_match('/^hist_\d+/', $file)) {
      $file = preg_replace('/^Lady in the South:\s*/', '', $media->label());
    }
  }
  $file = preg_replace('/^(hist_\d+).*$/', '$1', $file);
  $by_file[$file] = $figure;
}

// Check everything before changing anything.
$problems = [];
$t = 0;
foreach ($chapters as $chapter) {
  foreach ($chapter['texts'] as $opening) {
    $have = isset($texts[$t]) ? $plain($texts[$t]->get('field_block_body')->value) : '';
    if (!str_starts_with($have, $opening)) {
      $problems[] = 'paragraph ' . ($t + 1) . ' should open “' . $opening . '”, but opens “' . mb_substr($have, 0, 50) . '”';
    }
    $t++;
  }
  foreach (array_keys($chapter['pictures']) as $file) {
    if (!isset($by_file[$file])) {
      $problems[] = "no picture $file";
    }
  }
}
if ($t !== count($texts)) {
  $problems[] = count($texts) . " paragraphs, expected $t";
}
$wanted = array_sum(array_map(fn ($c) => count($c['pictures']), $chapters));
if ($wanted !== count($figures)) {
  $problems[] = count($figures) . " pictures, expected $wanted";
}
if ($problems) {
  echo 'Leave History: it is not the gallery this was written for. Nothing saved.' . PHP_EOL . '  - ' . implode(PHP_EOL . '  - ', $problems) . PHP_EOL;
  return;
}

$new = [];
$t = 0;
foreach ($chapters as $n => $chapter) {
  echo sprintf('%02d %s (%d paragraphs)', $n + 1, $chapter['title'], count($chapter['texts'])) . PHP_EOL;
  $paragraphs = [];
  foreach ($chapter['texts'] as $opening) {
    $text = $texts[$t++];
    $paragraphs[] = Paragraph::create([
      'type' => 'guide_text',
      'field_block_body' => ['value' => $text->get('field_block_body')->value, 'format' => $text->get('field_block_body')->format],
    ] + ($text->hasField('field_text_style') && !$text->get('field_text_style')->isEmpty() ? ['field_text_style' => $text->get('field_text_style')->value] : []));
  }
  $new[] = Paragraph::create([
    'type' => 'exhibit_entry',
    'field_block_title' => $chapter['title'],
    'field_entry_summary' => $chapter['summary'],
    'field_guide_blocks' => $paragraphs,
  ]);
  foreach ($chapter['pictures'] as $file => $title) {
    $figure = $by_file[$file];
    echo "     $title ($file)" . PHP_EOL;
    $copy = [
      'type' => 'exhibit_figure',
      'field_figure_image' => ['target_id' => $figure->get('field_figure_image')->target_id],
      'field_figure_caption' => ['value' => $figure->get('field_figure_caption')->value, 'format' => $figure->get('field_figure_caption')->format],
      'field_figure_layout' => 'side_start',
    ];
    $new[] = Paragraph::create([
      'type' => 'exhibit_entry',
      'field_block_title' => $title,
      'field_guide_blocks' => [Paragraph::create($copy)],
    ]);
  }
}
echo 'Display: ' . ((string) $history->get('field_exhibit_display')->value ?: 'story') . ' → wall' . PHP_EOL;
if (!$apply) {
  echo 'Dry run: History becomes ' . count($new) . ' entries (6 plates, ' . count($figures) . ' pictures). Nothing saved. Add apply=1 to save.' . PHP_EOL;
  return;
}
$history->set('field_guide_blocks', $new);
$history->set('field_exhibit_display', 'wall');
$history->setNewRevision(FALSE);
$history->save();
\Drupal::service('cache_tags.invalidator')->invalidateTags($node->getCacheTagsToInvalidate());
$nodes->resetCache([$node->id()]);
echo 'Saved. Run drush cr.' . PHP_EOL;
