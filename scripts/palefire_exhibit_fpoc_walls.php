<?php

/**
 * @file
 * Free People of Color in Louisiana: The Collections and Terminology as
 * walls of text plates, from data/fpoc.json, on the page
 * palefire_exhibit_fpoc.php already built.
 *
 *   drush php:script scripts/palefire_exhibit_fpoc_walls.php
 *   drush php:script scripts/palefire_exhibit_fpoc_walls.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1.
 *
 * The Collections: Display "wall". Each partner's opening line gains its
 * count of collections and their years, which becomes its plate's summary.
 *
 * Terminology: the opening stays as the gallery's text. "Creole",
 * Controlled Vocabularies and Historical Terminology become an entry each,
 * a text plate on the wall. The historical terms stay one list inside the
 * last entry. This happens only while the gallery is still one text block
 * with no entries; after that it's left alone.
 *
 * Galleries and blocks are saved in place, without a new revision. Safe to
 * re-run.
 */

declare(strict_types=1);

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
foreach (['collections', 'terminology'] as $anchor) {
  if (!isset($galleries[$anchor], $pages[$anchor])) {
    throw new \RuntimeException("No \"$anchor\" gallery on the page or in data/fpoc.json.");
  }
}

$changed = 0;
$save = function (ParagraphInterface $paragraph) use ($apply, &$changed): void {
  $changed++;
  if ($apply) {
    $paragraph->setNewRevision(FALSE);
    $paragraph->save();
  }
};
// A text block's body set to $html, keeping its text format.
$set_body = function (ParagraphInterface $block, string $html) use ($save): bool {
  if (trim((string) $block->get('field_block_body')->value) === trim($html)) {
    return FALSE;
  }
  $block->set('field_block_body', ['value' => trim($html), 'format' => $block->get('field_block_body')->format]);
  $save($block);
  return TRUE;
};
$first_text = function (ParagraphInterface $parent): ?ParagraphInterface {
  foreach ($parent->get('field_guide_blocks')->referencedEntities() as $block) {
    if ($block->bundle() === 'guide_text') {
      return $block;
    }
  }
  return NULL;
};
$wall = function (ParagraphInterface $gallery) use ($save): void {
  if ((string) $gallery->get('field_exhibit_display')->value === 'wall') {
    echo '  Display: wall already' . PHP_EOL;
    return;
  }
  $gallery->set('field_exhibit_display', 'wall');
  $save($gallery);
  echo '  Display: story → wall' . PHP_EOL;
};

// The Collections. -----------------------------------------------------------
echo 'The Collections:' . PHP_EOL;
$gallery = $galleries['collections'];
$wanted = [];
foreach ($pages['collections']['blocks'] as $b) {
  if ($b['type'] === 'entry') {
    $wanted[$b['anchor']] = $b;
  }
}
foreach ($gallery->get('field_guide_blocks')->referencedEntities() as $entry) {
  $anchor = $entry->bundle() === 'exhibit_entry' ? (string) $entry->get('field_section_anchor')->value : '';
  if (!isset($wanted[$anchor])) {
    continue;
  }
  $block = $first_text($entry);
  if (!$block) {
    echo "  $anchor: no text block, left alone" . PHP_EOL;
    continue;
  }
  echo "  $anchor: " . ($set_body($block, $wanted[$anchor]['blocks'][0]['body']) ? 'opening line with its count of collections' : 'up to date') . PHP_EOL;
}
$wall($gallery);

// Terminology. ---------------------------------------------------------------
echo 'Terminology:' . PHP_EOL;
$gallery = $galleries['terminology'];
$blocks = $gallery->get('field_guide_blocks')->referencedEntities();
$entries = array_filter($blocks, fn ($b) => $b->bundle() === 'exhibit_entry');
if ($entries) {
  echo '  has ' . count($entries) . ' entries already, left alone' . PHP_EOL;
}
elseif (count($blocks) !== 1 || $blocks[0]->bundle() !== 'guide_text') {
  echo '  not the single text block it was built as, left alone' . PHP_EOL;
}
else {
  $intro = $blocks[0];
  $format = $intro->get('field_block_body')->format;
  $source = $pages['terminology']['blocks'];
  if ($source[0]['type'] !== 'text') {
    throw new \RuntimeException('data/fpoc.json: Terminology should open with its text.');
  }
  $set_body($intro, $source[0]['body']);
  echo '  opening text kept as the gallery’s text' . PHP_EOL;
  $new = [$intro];
  foreach (array_slice($source, 1) as $b) {
    echo '  entry: ' . $b['title'] . PHP_EOL;
    $new[] = Paragraph::create([
      'type' => 'exhibit_entry',
      'field_block_title' => $b['title'],
      'field_section_anchor' => $b['anchor'],
      'field_guide_blocks' => array_map(fn ($t) => Paragraph::create([
        'type' => 'guide_text',
        'field_block_body' => ['value' => trim($t['body']), 'format' => $format],
        'field_text_style' => $t['style'] ?? 'default',
      ]), $b['blocks']),
    ]);
  }
  $gallery->set('field_guide_blocks', $new);
  $save($gallery);
}
$wall($gallery);

if (!$apply) {
  echo "Dry run: $changed saves to make. Nothing saved. Add apply=1 to save." . PHP_EOL;
  return;
}
\Drupal::entityTypeManager()->getStorage('node')->resetCache([$node->id()]);
echo "Saved ($changed saves). Run drush cr." . PHP_EOL;
