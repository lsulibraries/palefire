<?php

/**
 * @file
 * A Lady in the South: About the Exhibition as a wall.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_lady_about_wall.php
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_lady_about_wall.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1.
 *
 * About was a story of two yearbook portraits and four text blocks, each
 * opening on its own heading (Dorothy Colvin Howell, A.B. Clark, In their
 * words, Acknowledgments). With no lead, it opened on its first picture,
 * Dorothy Colvin’s 227 px portrait, blown up to the full screen behind all
 * of its text. It becomes a wall: the two narrators as picture tiles
 * (portrait, and the biography in the object view), then In their words
 * and Acknowledgments as text plates (an entry with a heading, a Summary
 * and the text, unchanged but for the heading, which becomes the entry’s).
 * The gallery gets a lead of its own, the Home Economics Club page from
 * the 1933 Gumbo (“Lady in the South: hist_20”, 1101 px), with its
 * caption, so the wall does not open on a portrait either. An existing
 * lead is kept.
 *
 * The exhibition’s poster (lady_menu2), where it has since been added as a
 * seventh block after Acknowledgments, becomes that plate’s picture, so
 * Acknowledgments is a picture tile too (the poster, then the credits).
 *
 * Only while the gallery is still those six blocks (or seven, with the
 * poster last), in that order, with no entries: each figure’s file and
 * each text’s heading are checked first,
 * and nothing is saved if one differs. Saved in place, without a new
 * revision, as the other exhibition scripts. Needs the Summary field on
 * entries (palefire_exhibit_bio_setup.php).
 *
 * Undo: set the gallery’s Display back to Story and clear its Lead; the
 * text and pictures are all still there, in entries.
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

// The blocks as built (scripts/data/lady-in-the-south.json), in order: a
// figure by its file, or a text by its heading. Each text becomes an
// entry; a figure goes into the entry of the text after it.
$expected = [
  ['figure', 'dorothy'],
  ['text', 'Dorothy Colvin Howell'],
  ['figure', 'clark2'],
  ['text', 'A.B. Clark'],
  ['text', 'In their words'],
  ['text', 'Acknowledgments'],
];
// Added since: the poster, last. Taken into Acknowledgments where present.
$poster = 'lady_menu2';
// Text plates’ summaries (a picture tile needs none).
$summaries = [
  'In their words' => 'Dorothy Colvin Howell and A.B. Clark remember making do with orange crates, husbands drying the silver, and what the houses meant in the life of the American woman.',
  'Acknowledgments' => 'Who curated the listening station and the Hill Memorial Library exhibition it was part of, and the people who helped make it.',
];
$lead_name = 'Lady in the South: hist_20';
$lead_caption = '<p>Home Economics Club. <em>LSU Gumbo</em>, 1933.</p>';

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
$about = NULL;
foreach ($node->get('field_scroll_section')->referencedEntities() as $section) {
  if ($section->bundle() === 'exhibit_page' && trim((string) $section->get('field_section_title')->value) === 'About the Exhibition') {
    $about = $section;
  }
}
if (!$about) {
  throw new \RuntimeException('“' . $node->label() . '” has no About the Exhibition gallery.');
}
if (!Paragraph::create(['type' => 'exhibit_entry'])->hasField('field_entry_summary')) {
  throw new \RuntimeException('Entries have no Summary field. Run scripts/palefire_exhibit_bio_setup.php first.');
}

$plain = fn ($html) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
// A figure’s file name (“clark2”), from its media item.
$file_of = function ($figure): string {
  $media = $figure->get('field_figure_image')->entity;
  if (!$media) {
    return '';
  }
  $source = $media->getSource()->getConfiguration()['source_field'] ?? NULL;
  $file = $source && $media->hasField($source) && $media->get($source)->entity ? pathinfo($media->get($source)->entity->getFilename(), PATHINFO_FILENAME) : '';
  return $file !== '' ? $file : preg_replace('/^Lady in the South:\s*/', '', $media->label());
};

// Check everything before changing anything.
$blocks = $about->get('field_guide_blocks')->referencedEntities();
$problems = [];
$has_poster = count($blocks) === count($expected) + 1;
if ($has_poster) {
  $last = end($blocks);
  if ($last->bundle() !== 'exhibit_figure' || !str_starts_with($file_of($last), $poster)) {
    $problems[] = 'block ' . count($blocks) . " should be the poster $poster, but is " . ($last->bundle() === 'exhibit_figure' ? $file_of($last) : $last->bundle());
  }
}
elseif (count($blocks) !== count($expected)) {
  $problems[] = count($blocks) . ' blocks, expected ' . count($expected) . ' (or ' . (count($expected) + 1) . ' with the poster last)';
}
foreach ($expected as $i => [$type, $name]) {
  $block = $blocks[$i] ?? NULL;
  if ($type === 'figure') {
    if (!$block || $block->bundle() !== 'exhibit_figure') {
      $problems[] = 'block ' . ($i + 1) . " should be the picture $name";
    }
    elseif (!str_starts_with($file_of($block), $name)) {
      $problems[] = 'block ' . ($i + 1) . " should be $name, but is " . $file_of($block);
    }
  }
  else {
    $body = $block && $block->bundle() === 'guide_text' ? (string) $block->get('field_block_body')->value : '';
    $heading = preg_match('#^\s*<h3[^>]*>(.*?)</h3>#s', $body, $h) ? $plain($h[1]) : '';
    if ($heading !== $name) {
      $problems[] = 'block ' . ($i + 1) . " should be the text headed “{$name}”, but is " . ($heading !== '' ? "headed “{$heading}”" : ($block ? $block->bundle() : 'missing'));
    }
  }
}
if ($problems) {
  echo 'Leave About the Exhibition: it is not the gallery this was written for. Nothing saved.' . PHP_EOL . '  - ' . implode(PHP_EOL . '  - ', $problems) . PHP_EOL;
  return;
}

$new = [];
$pending = [];
$tiles = 0;
// The poster goes before Acknowledgments, as a tile’s picture comes before
// its text.
$plan = $expected;
if ($has_poster) {
  array_splice($plan, count($plan) - 1, 0, [['figure', $poster]]);
  $poster_block = array_pop($blocks);
  array_splice($blocks, count($blocks) - 1, 0, [$poster_block]);
}
foreach ($plan as $i => [$type, $name]) {
  $block = $blocks[$i];
  if ($type === 'figure') {
    $pending[] = Paragraph::create([
      'type' => 'exhibit_figure',
      'field_figure_image' => ['target_id' => $block->get('field_figure_image')->target_id],
      'field_figure_caption' => ['value' => $block->get('field_figure_caption')->value, 'format' => $block->get('field_figure_caption')->format],
      'field_figure_layout' => 'side_start',
    ]);
    continue;
  }
  // The heading becomes the entry’s; the text keeps the rest, unchanged.
  $body = preg_replace('#^\s*<h3[^>]*>.*?</h3>\s*#s', '', (string) $block->get('field_block_body')->value);
  $text = Paragraph::create([
    'type' => 'guide_text',
    'field_block_body' => ['value' => $body, 'format' => $block->get('field_block_body')->format],
  ] + ($block->hasField('field_text_style') && !$block->get('field_text_style')->isEmpty() ? ['field_text_style' => $block->get('field_text_style')->value] : []));
  $values = [
    'type' => 'exhibit_entry',
    'field_block_title' => $name,
    'field_guide_blocks' => array_merge($pending, [$text]),
  ];
  if (isset($summaries[$name])) {
    $values['field_entry_summary'] = $summaries[$name];
  }
  echo ($pending ? 'Picture tile: ' : 'Text plate: ') . $name . ' (' . str_word_count(strip_tags($body)) . ' words)' . PHP_EOL;
  $tiles += $pending ? 1 : 0;
  $new[] = Paragraph::create($values);
  $pending = [];
}

// The lead, where empty.
$lead_id = NULL;
if ($about->get('field_exhibit_lead')->isEmpty()) {
  $ids = \Drupal::entityQuery('media')->accessCheck(FALSE)->condition('name', $lead_name)->range(0, 1)->execute();
  $lead_id = $ids ? (int) reset($ids) : NULL;
  echo $lead_id ? "Lead: $lead_name" . PHP_EOL : "Skipped the lead: no media item “{$lead_name}”. Choose one in the editor." . PHP_EOL;
}
else {
  echo 'Lead: kept (' . $about->get('field_exhibit_lead')->entity?->label() . ')' . PHP_EOL;
}
echo 'Display: ' . ((string) $about->get('field_exhibit_display')->value ?: 'story') . ' → wall' . PHP_EOL;
if (!$apply) {
  echo 'Dry run: About the Exhibition becomes ' . count($new) . " entries ($tiles picture tiles, " . (count($new) - $tiles) . ' text plates). Nothing saved. Add apply=1 to save.' . PHP_EOL;
  return;
}
$about->set('field_guide_blocks', $new);
$about->set('field_exhibit_display', 'wall');
if ($lead_id) {
  $about->set('field_exhibit_lead', ['target_id' => $lead_id]);
  if ($about->hasField('field_exhibit_lead_caption')) {
    $about->set('field_exhibit_lead_caption', ['value' => $lead_caption, 'format' => $blocks[1]->get('field_block_body')->format]);
  }
}
$about->setNewRevision(FALSE);
$about->save();
\Drupal::service('cache_tags.invalidator')->invalidateTags($node->getCacheTagsToInvalidate());
$nodes->resetCache([$node->id()]);
echo 'Saved. Run drush cr.' . PHP_EOL;
