<?php

/**
 * @file
 * Adds "Frequently asked questions" to the "How to Use Special Collections"
 * guide: every Special Collections FAQ, most asked first, in the order the
 * homepage's Ask Special Collections section uses.
 *
 *   drush php:script scripts/palefire_guide_faqs_section.php
 *   drush php:script scripts/palefire_guide_faqs_section.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it reports what it would do, and the FAQs the
 * section would list, and saves nothing.
 *
 * Optional arguments after "--":
 *   apply=1          Save.
 *   page=<alias>     The guide (default /v2/special/guides/special-collections).
 *   before=<title>   The section to go before (default "Get help"). Not
 *                    found, the new one goes last.
 *
 * What it adds: one guide_section, "Frequently asked questions", holding
 * one Text block: a line of intro, then a paragraph that is only
 * "[special-collections-faqs]". The theme draws the FAQs there
 * (palefire_preprocess_paragraph__guide_text(), guide-faqs.html.twig), so
 * the list follows the FAQ tiles: a new, edited or reweighted tile shows
 * there on its own. Content only: no fields or settings change.
 *
 * It stops, saving nothing, when:
 * - a section titled "Frequently asked questions" is already on the page;
 * - the guide has a newer draft than its current revision, which a save
 *   here would bypass.
 */

declare(strict_types=1);

use Drupal\paragraphs\Entity\Paragraph;

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
$alias = '/' . trim($args['page'] ?? '/v2/special/guides/special-collections', '/');
$before = $args['before'] ?? 'Get help';

$title = 'Frequently asked questions';
$log = [];

// The FAQs it would list (as _palefire_ask_faqs() with no limit). ----------
$terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => 'FAQ']);
$faqs = $terms ? \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'tile')
  ->condition('status', 1)
  ->condition('field_category', array_keys($terms), 'IN')
  ->condition('field_subcategory', 'Special Collections', 'CONTAINS')
  ->sort('field_tile_search_weight', 'DESC')
  ->sort('title')
  ->execute() : [];
if (!$faqs) {
  $log[] = 'No published Tile has Category "FAQ" and Subcategory "Special Collections": the section would show its intro only.';
}
$n = 0;
foreach (\Drupal::entityTypeManager()->getStorage('node')->loadMultiple($faqs) as $tile) {
  $weight = $tile->hasField('field_tile_search_weight') ? (string) $tile->get('field_tile_search_weight')->value : '';
  $log[] = sprintf('FAQ %d: "%s" (node %d, weight %s).', ++$n, $tile->label(), $tile->id(), $weight === '' ? 'none' : $weight);
}

// The guide. ---------------------------------------------------------------
$path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
if (!preg_match('#^/node/(\d+)$#', $path, $m)) {
  throw new \RuntimeException("No node found at $alias.");
}
$storage = \Drupal::entityTypeManager()->getStorage('node');
$node = $storage->load($m[1]);
if (!$node || !$node->hasField('field_scroll_section')) {
  throw new \RuntimeException("Node {$m[1]} has no field_scroll_section.");
}
$latest = $storage->getLatestRevisionId($node->id());
if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
  throw new \RuntimeException("$alias has a newer draft (revision $latest) than its current revision. Publish or discard it first.");
}

$sections = $node->get('field_scroll_section')->referencedEntities();
$position = NULL;
$format = NULL;
$theme = NULL;
foreach ($sections as $delta => $section) {
  $label = $section->hasField('field_section_title') ? (string) $section->get('field_section_title')->value : '';
  if ($label === $title) {
    echo "\"$title\" is already on $alias (section " . ($delta + 1) . '). Nothing to do.' . PHP_EOL;
    return;
  }
  if ($position === NULL && $label === $before) {
    $position = $delta;
  }
  // The text format and section theme the guide's own sections use.
  if ($section->bundle() === 'guide_section') {
    if ($theme === NULL && $section->hasField('field_section_theme')) {
      $theme = $section->get('field_section_theme')->value;
    }
    foreach ($section->hasField('field_guide_blocks') ? $section->get('field_guide_blocks')->referencedEntities() : [] as $block) {
      if ($format === NULL && $block->bundle() === 'guide_text') {
        $format = $block->get('field_block_body')->format;
      }
    }
  }
}
if ($position === NULL) {
  $log[] = "No section titled \"$before\": the new section goes last.";
  $position = count($sections);
}
if ($format === NULL) {
  throw new \RuntimeException("$alias has no guide Text block to take a text format from.");
}

$text = Paragraph::create([
  'type' => 'guide_text',
  'field_block_body' => [
    'value' => '<p>Every Special Collections FAQ, the most asked first. Open a question for its answer, or <a href="/v2/special#ask-special-collections">send us your own</a>.</p>' . "\n<p>[special-collections-faqs]</p>",
    'format' => $format,
  ],
]);
$values = [
  'type' => 'guide_section',
  'field_section_title' => $title,
  'field_guide_blocks' => [$text],
];
if ($theme !== NULL) {
  $values['field_section_theme'] = $theme;
}
$log[] = "Insert \"$title\" as section " . ($position + 1) . ' of ' . (count($sections) + 1) . " on $alias (text format $format).";

foreach ($log as $line) {
  echo ($apply ? '' : '[dry run] ') . $line . PHP_EOL;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$items = $node->get('field_scroll_section')->getValue();
array_splice($items, $position, 0, [['entity' => Paragraph::create($values)]]);
$node->set('field_scroll_section', $items);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage("Guide: \"$title\" section, every Special Collections FAQ (palefire_guide_faqs_section.php).");
$node->save();
echo "Saved a new revision of \"{$node->label()}\". Run: drush cr" . PHP_EOL;
