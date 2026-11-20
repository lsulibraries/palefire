<?php

/**
 * @file
 * Free People of Color in Louisiana: Golden Age, Decline and Civil War and
 * Resources as walls, from data/fpoc.json, on the page that
 * palefire_exhibit_fpoc.php built.
 *
 *   drush php:script scripts/palefire_exhibit_fpoc_history_walls.php
 *   drush php:script scripts/palefire_exhibit_fpoc_history_walls.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1.
 *
 * Each history chapter is cut into text plates at turns in Michael Taylor's
 * argument, with the plate titles approved by the user and each summary
 * taken from the chapter's own sentences. Its documents hang between the
 * plates as tiles. Read in the object view from the first plate, the
 * chapter follows Taylor's order. Resources is one plate per list.
 *
 * The text is not changed, only divided. A gallery is rebuilt only when it
 * is still the story it was built as (Display "story", no entries) and its
 * text matches data/fpoc.json word for word. Otherwise it's left alone and
 * the dry run says why. Figures keep their live caption and image. Safe
 * to re-run.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
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

$data = json_decode((string) file_get_contents(__DIR__ . '/data/fpoc.json'), TRUE);
if (!is_array($data) || empty($data['pages'])) {
  throw new \RuntimeException("Couldn't read data/fpoc.json.");
}
$pages = [];
foreach ($data['pages'] as $page) {
  $pages[$page['anchor']] = $page;
}
if (!FieldConfig::loadByName('paragraph', 'exhibit_entry', 'field_entry_summary')) {
  throw new \RuntimeException('Exhibit entries have no Summary field (field_entry_summary).');
}

$ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', $data['title'])
  ->execute();
if (count($ids) !== 1) {
  throw new \RuntimeException('Expected one Modular page titled "' . $data['title'] . '", found ' . count($ids) . '.');
}
$node = \Drupal::entityTypeManager()->getStorage('node')->load(reset($ids));
echo 'Page: ' . $node->label() . ' (node ' . $node->id() . ')' . PHP_EOL;

$galleries = [];
foreach ($node->get('field_scroll_section')->referencedEntities() as $section) {
  if ($section->bundle() === 'exhibit_page') {
    $galleries[(string) $section->get('field_section_anchor')->value] = $section;
  }
}

// Text as words only, for comparing the live text with the data.
$words = fn (string $html) => trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '</li>', '</h3>', '<br>'], ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
// A figure's media file name ("FPOC: rose-receipt-1815" → rose-receipt-1815.jpg).
$figure_file = function (ParagraphInterface $figure): string {
  $media = $figure->get('field_figure_image')->entity;
  return $media ? preg_replace('/^FPOC:\s*/', '', $media->label()) . '.jpg' : '';
};

$saves = 0;
foreach (['golden-age', 'decline', 'resources'] as $anchor) {
  $page = $pages[$anchor] ?? NULL;
  $gallery = $galleries[$anchor] ?? NULL;
  echo ($page['title'] ?? $anchor) . ':' . PHP_EOL;
  if (!$page || !$gallery || ($page['display'] ?? '') !== 'wall') {
    echo '  not in the page or not a wall in data/fpoc.json, left alone' . PHP_EOL;
    continue;
  }
  if ((string) $gallery->get('field_exhibit_display')->value === 'wall') {
    echo '  a wall already, left alone' . PHP_EOL;
    continue;
  }
  $live = $gallery->get('field_guide_blocks')->referencedEntities();
  if (array_filter($live, fn ($b) => $b->bundle() === 'exhibit_entry')) {
    echo '  has entries already, left alone' . PHP_EOL;
    continue;
  }

  // The live text in order, and its figures by file name.
  $live_text = '';
  $figures = [];
  $format = NULL;
  foreach ($live as $block) {
    if ($block->bundle() === 'guide_text') {
      $live_text .= ' ' . (string) $block->get('field_block_body')->value;
      $format ??= $block->get('field_block_body')->format;
    }
    elseif ($block->bundle() === 'exhibit_figure') {
      $figures[$figure_file($block)] = $block;
    }
  }
  // The data's text in the same order. Resources' list headings become the
  // plates' titles, so they're compared as text too.
  $data_text = '';
  foreach ($page['blocks'] as $entry) {
    foreach ($entry['blocks'] as $b) {
      if ($b['type'] === 'text') {
        $data_text .= ' ' . ($anchor === 'resources' ? $entry['title'] . ' ' : '') . $b['body'];
      }
    }
  }
  if ($words($live_text) !== $words($data_text)) {
    echo '  its text differs from data/fpoc.json (edited since it was built?), left alone' . PHP_EOL;
    continue;
  }

  $new = [];
  foreach ($page['blocks'] as $entry) {
    $children = [];
    foreach ($entry['blocks'] as $b) {
      if ($b['type'] === 'text') {
        $children[] = Paragraph::create([
          'type' => 'guide_text',
          'field_block_body' => ['value' => trim($b['body']), 'format' => $format],
          'field_text_style' => $b['style'] ?? 'default',
        ]);
      }
      elseif ($b['type'] === 'figure') {
        $old = $figures[$b['image']] ?? NULL;
        if (!$old) {
          throw new \RuntimeException("$anchor: no live figure for {$b['image']}.");
        }
        // A copy of the live figure: its image, caption and layout.
        $children[] = Paragraph::create([
          'type' => 'exhibit_figure',
          'field_figure_image' => ['target_id' => $old->get('field_figure_image')->target_id],
          'field_figure_caption' => ['value' => $old->get('field_figure_caption')->value, 'format' => $old->get('field_figure_caption')->format],
          'field_figure_layout' => $old->get('field_figure_layout')->value,
        ]);
      }
    }
    $kind = !empty($entry['summary']) ? 'plate' : 'picture';
    echo "  $kind: {$entry['title']}" . PHP_EOL;
    $new[] = Paragraph::create(array_filter([
      'type' => 'exhibit_entry',
      'field_block_title' => $entry['title'],
      'field_section_anchor' => $entry['anchor'],
      'field_entry_summary' => $entry['summary'] ?? NULL,
      'field_guide_blocks' => $children,
    ], fn ($v) => $v !== NULL));
  }
  echo '  Display: story → wall' . PHP_EOL;
  $saves++;
  if ($apply) {
    $gallery->set('field_guide_blocks', $new);
    $gallery->set('field_exhibit_display', 'wall');
    $gallery->setNewRevision(FALSE);
    $gallery->save();
  }
}

if (!$apply) {
  echo "Dry run: $saves galleries to rebuild. Nothing saved. Add apply=1 to save." . PHP_EOL;
  return;
}
\Drupal::entityTypeManager()->getStorage('node')->resetCache([$node->id()]);
echo "Saved $saves galleries. Run drush cr." . PHP_EOL;
