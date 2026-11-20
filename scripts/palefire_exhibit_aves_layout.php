<?php

/**
 * @file
 * AVES: six galleries hung as walls, life dates as dates, and Biographies
 * as a timeline.
 *
 *   drush php:script scripts/palefire_exhibit_aves_layout.php
 *   drush php:script scripts/palefire_exhibit_aves_layout.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it lists what it would change and saves
 * nothing. Works on the page as it is now (editors' changes kept), not on
 * scripts/data/aves.json.
 *
 * - Walls: Collecting by Individuals, Museum Men, Collecting by Museums and
 *   Life Histories (31 people) get Display "wall". Each chapter's essay is
 *   the wall's intro, folded with "Read more"; a portrait is a tile, and a
 *   life with no portrait a name tile (palefire.theme
 *   _palefire_exhibit_wall()). Every tile opens the life in the object view.
 * - People and Places (10 photographs) and Modern Fieldwork (5 and a group
 *   of 4) become walls too: each photograph or group is wrapped in an entry
 *   with a short title written from its caption (TITLES below), the caption
 *   unchanged. Modern Fieldwork's RAP text becomes its intro.
 * - Life dates: a heading's "(1809-1882)" moves to the entry's Date
 *   ("1809/1882"), which the card shows as Life and the timeline places.
 *   The heading keeps the name. Its Anchor is set to the anchor it had
 *   (#charles-darwin-1809-1882), so links to it still land. A pair
 *   ("Robert Schomburgk (1804-1865) & Richard Schomburgk (1811-1891)")
 *   keeps its heading and gets the span of both lives as its Date.
 * - Biographies: its typed list of names is replaced by a short intro, and
 *   the gallery gets Display "timeline": every life in the order born,
 *   each linking to its biography (src/ExhibitTimeline.php).
 *
 * Fields already set (a Date, an Anchor, a wall) are left alone, so it is
 * safe to re-run. Stops if a gallery or caption is not found, or the page
 * has a newer draft. Saves the page as a new revision; restore the
 * previous one from the Revisions tab to undo.
 */

declare(strict_types=1);

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
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

foreach (['exhibit_page' => 'field_exhibit_display', 'exhibit_entry' => 'field_entry_date'] as $bundle => $name) {
  if (!FieldConfig::loadByName('paragraph', $bundle, $name)) {
    throw new \RuntimeException("$name not found on $bundle. Run palefire_exhibit_setup.php first.");
  }
}

$walls = ['Collecting by Individuals', 'Museum Men', 'Collecting by Museums', 'Life Histories', 'People and Places', 'Modern Fieldwork'];
$timeline = 'Biographies';

// Gallery => [start of the caption => the entry's title]. A group of
// pictures is found by its first picture's caption.
$titles = [
  'People and Places' => [
    'Most early accounts of exploration' => 'The interior of Guiana',
    'Maximilian Wied-Neuwied was charmed' => 'Wied on the Rio Doce',
    'Although it is fair to say' => 'Spix and a ritual drink',
    'Henry Walter Bates mobbed' => 'Bates mobbed by aracaris',
    'This photo, from Charles Chubb' => 'Camp in British Guiana',
    'Blair Beebe was one of the first women' => 'The Beebes at the barranca',
    'In Blair Beebe’s opinion' => 'Blair Beebe’s tent',
    'Two places for studying birds' => 'Two castles',
    'Field research today' => 'Field research today',
    'Candles and computers in camp' => 'Candles and computers',
  ],
  'Modern Fieldwork' => [
    'A young John O’Neill in 1977' => 'John O’Neill on the Rio Heath, 1977',
    'Transportation on the Rio Shesha' => 'On the Rio Shesha, 1987',
    'Condor Expedition, 2006' => 'Condor Expedition, 2006',
    'Mules have always had a hard time' => 'Life in the Condor camp',
    'Preparing specimens of Jocotoco antpittas' => 'Preparing Jocotoco antpittas',
    'Despite the large number of LSUMNS expeditions' => 'Tumbes, 2009',
  ],
];

$intro = '<p>The lives in this exhibition, in the order they were born. Each name opens its biography in its gallery. Alexander von Humboldt’s life is told in <a href="#opening-scientific-exploration">Opening Scientific Exploration</a>.</p>';

$ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', 'AVES')
  ->execute();
if (count($ids) !== 1) {
  throw new \RuntimeException('Expected one Modular page titled "AVES", found ' . count($ids) . '.');
}
$node = Node::load(reset($ids));
$storage = \Drupal::entityTypeManager()->getStorage('node');
$latest = $storage->getLatestRevisionId($node->id());
if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
  throw new \RuntimeException("The page has a newer draft (revision $latest). Publish or discard it first.");
}

$plain = fn (string $html) => trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
$caption_of = function (ParagraphInterface $block) use ($plain): string {
  if ($block->bundle() === 'exhibit_gallery') {
    $own = $plain((string) $block->get('field_figure_caption')->value);
    if ($own !== '') {
      return $own;
    }
    $first = $block->get('field_gallery_items')->referencedEntities()[0] ?? NULL;
    return $first ? $plain((string) $first->get('field_figure_caption')->value) : '';
  }
  return $plain((string) $block->get('field_figure_caption')->value);
};

$galleries = [];
foreach ($node->get('field_scroll_section')->referencedEntities() as $gallery) {
  if ($gallery->bundle() === 'exhibit_page') {
    $galleries[trim((string) $gallery->get('field_section_title')->value)] = $gallery;
  }
}
$missing = array_diff([...$walls, $timeline], array_keys($galleries));
if ($missing) {
  throw new \RuntimeException('Galleries not found (renamed?): ' . implode(', ', $missing) . '. Nothing changed.');
}

$changed = 0;

// 1. Pictures wrapped in titled entries. Checked in full before anything
// changes, so a caption not found stops the run.
$wraps = [];
foreach ($titles as $name => $map) {
  $blocks = $galleries[$name]->get('field_guide_blocks')->referencedEntities();
  $loose = array_filter($blocks, fn ($b) => in_array($b->bundle(), ['exhibit_figure', 'exhibit_gallery'], TRUE));
  if (!$loose) {
    $say("$name: its pictures are already in entries, left alone.");
    continue;
  }
  $new = [];
  foreach ($blocks as $block) {
    if (!in_array($block->bundle(), ['exhibit_figure', 'exhibit_gallery'], TRUE)) {
      $new[] = $block;
      continue;
    }
    $caption = $caption_of($block);
    $title = NULL;
    foreach ($map as $start => $candidate) {
      if (str_starts_with($caption, $start)) {
        $title = $candidate;
        break;
      }
    }
    if ($title === NULL) {
      throw new \RuntimeException("$name: no title for the picture captioned “" . mb_substr($caption, 0, 60) . "…”. Add it to \$titles. Nothing changed.");
    }
    $new[] = Paragraph::create([
      'type' => 'exhibit_entry',
      'field_block_title' => $title,
      'field_guide_blocks' => [$block->createDuplicate()],
      'status' => $block->isPublished(),
    ]);
    $say("$name: “{$title}” ← " . mb_substr($caption, 0, 70) . '…');
  }
  $wraps[$name] = $new;
}
foreach ($wraps as $name => $new) {
  $galleries[$name]->set('field_guide_blocks', $new);
  $galleries[$name]->setNeedsSave(TRUE);
  $changed++;
}

// 2. Walls.
foreach ($walls as $name) {
  $gallery = $galleries[$name];
  if ($gallery->get('field_exhibit_display')->value === 'wall') {
    $say("$name: already a wall.");
    continue;
  }
  $gallery->set('field_exhibit_display', 'wall');
  $gallery->setNeedsSave(TRUE);
  $changed++;
  $entries = count(array_filter($gallery->get('field_guide_blocks')->referencedEntities(), fn ($b) => $b->bundle() === 'exhibit_entry')) ?: count($wraps[$name] ?? []);
  $say("$name: Display wall ($entries entries).");
}

// 3. Life dates, on every entry in the page (depth first; a changed entry
// marks its gallery, so it is saved).
$years = '\(\s*(?:b\.\s*)?(\d{4})\s*(?:[-–—]\s*(\d{4})?)?\s*\)';
$lives = 0;
$date = function (ParagraphInterface $entry) use ($years, $say, &$lives): bool {
  $heading = trim((string) $entry->get('field_block_title')->value);
  if (!$entry->get('field_entry_date')->isEmpty() || !preg_match_all('/' . $years . '/u', $heading, $all, PREG_SET_ORDER)) {
    return FALSE;
  }
  $anchor = trim((string) $entry->get('field_section_anchor')->value);
  if (count($all) === 1 && preg_match('/^(.*\S)\s*' . $years . '$/u', $heading, $m)) {
    // One person: the dates leave the heading for the Date.
    $value = !empty($m[3]) ? $m[2] . '/' . $m[3] : $m[2];
    $entry->set('field_entry_date', $value);
    if ($anchor === '') {
      $entry->set('field_section_anchor', Html::getClass($heading));
    }
    // "b. 1925" stays in the heading, the card's Life ("b. 1925").
    if (!empty($m[3])) {
      $entry->set('field_block_title', $m[1]);
    }
    $say("  {$heading} → “" . (!empty($m[3]) ? $m[1] : $heading) . "”, Date {$value}" . ($anchor === '' ? ', Anchor #' . Html::getClass($heading) : ''));
  }
  else {
    // A pair: the span of both lives; the heading as it is.
    $numbers = [];
    foreach ($all as $set) {
      $numbers[] = (int) $set[1];
      if (!empty($set[2])) {
        $numbers[] = (int) $set[2];
      }
    }
    $value = min($numbers) . '/' . max($numbers);
    $entry->set('field_entry_date', $value);
    $say("  {$heading}: Date {$value} (a pair; heading unchanged)");
  }
  $lives++;
  return TRUE;
};
$descend = function (FieldableEntityInterface $paragraph) use (&$descend, $date): bool {
  $changed = $paragraph->bundle() === 'exhibit_entry' && $date($paragraph);
  if ($paragraph->hasField('field_guide_blocks')) {
    foreach ($paragraph->get('field_guide_blocks')->referencedEntities() as $child) {
      $changed = $descend($child) || $changed;
    }
  }
  if ($changed) {
    $paragraph->setNeedsSave(TRUE);
  }
  return $changed;
};
$say('Life dates:');
foreach ($galleries as $gallery) {
  if ($descend($gallery)) {
    $changed++;
  }
}
$say($lives ? "  $lives entries dated." : '  None to move (already dated).');

// 4. Biographies as a timeline.
$bio = $galleries[$timeline];
if ($bio->get('field_exhibit_display')->value !== 'timeline') {
  $bio->set('field_exhibit_display', 'timeline');
  foreach ($bio->get('field_guide_blocks')->referencedEntities() as $block) {
    if ($block->bundle() === 'guide_text' && str_contains((string) $block->get('field_block_body')->value, '<br')) {
      // The typed list of names, now the timeline's to give.
      $block->set('field_block_body', ['value' => $intro, 'format' => $block->get('field_block_body')->format]);
      $block->setNeedsSave(TRUE);
      $say("$timeline: its list of names replaced by a short intro.");
    }
  }
  $bio->setNeedsSave(TRUE);
  $changed++;
  $say("$timeline: Display timeline.");
}
else {
  $say("$timeline: already a timeline.");
}

if (!$changed) {
  $say('Nothing to change.');
  return;
}
if (!$apply) {
  $say('Nothing saved. Run again with apply=1.');
  return;
}
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('AVES: six galleries as walls, life dates as Dates, Biographies as a timeline (palefire_exhibit_aves_layout.php). Restore the previous revision to undo.');
$node->save();
$say('Saved. Run: drush cr');
