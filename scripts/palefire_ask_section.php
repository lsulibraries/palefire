<?php

/**
 * @file
 * Adds the "Ask Special Collections" section to the Special Collections
 * homepage: a Tile showcase in the Ask layout, with the Special Collections
 * FAQ tiles beside a question form that sends to the same LibAnswers queue
 * as AskUs (templates/includes/ask-special.html.twig, js/ask-form.js).
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_ask_section.php
 *   drush php:script themes/custom/palefire/scripts/palefire_ask_section.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it reports what it would do, and which FAQ
 * tiles the section would list, and saves nothing.
 *
 * Optional arguments after "--":
 *   apply=1         Save.
 *   page=<alias>    The homepage (default /v2/special).
 *   after=<title>   The section to follow. Not given or not found, the new
 *                   one goes before the stacks (Upcoming, Featured, blog),
 *                   else last.
 *
 * What it does:
 * - Adds "ask" to the Tile showcase's Layout field (field_tile_layout),
 *   keeping every value already there, if it is missing.
 * - Adds one tile_showcase section: Eyebrow "Ask Us", Title "Ask Special
 *   Collections", a Lede, Link "More ways to ask" (AskUs: Special
 *   Collections, where chat is),
 *   Layout Ask, and no Tiles: with none picked, the section lists the FAQ
 *   tiles itself (_palefire_ask_faqs() in palefire.theme). Pick Tiles in
 *   the section later to choose and order them by hand.
 * - Saves a new revision of the homepage.
 *
 * It stops, saving nothing, when:
 * - a section titled "Ask Special Collections" is already on the page;
 * - the homepage has a newer draft than its published revision, which a
 *   save here would bypass.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
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
$alias = '/' . trim($args['page'] ?? '/v2/special', '/');
$after = $args['after'] ?? '';

$title = 'Ask Special Collections';

// The fields this needs. ---------------------------------------------------
$type = 'tile_showcase';
foreach (['field_section_title', 'field_tile_layout', 'field_tile_eyebrow', 'field_tile_lede', 'field_tile_link'] as $name) {
  if (!FieldConfig::loadByName('paragraph', $type, $name)) {
    throw new \RuntimeException("paragraph.$type.$name not found. Run palefire_tile_showcase_setup.php and palefire_tile_showcase_gallery_setup.php first.");
  }
}
$layout = FieldStorageConfig::loadByName('paragraph', 'field_tile_layout');
$allowed = $layout->getSetting('allowed_values');
$log = [];
if (!isset($allowed['ask'])) {
  $log[] = 'Add "ask" to field_tile_layout.';
}

// The FAQs it would list (as _palefire_ask_faqs()). ------------------------
$terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => 'FAQ']);
$faqs = [];
if ($terms) {
  $faqs = \Drupal::entityQuery('node')->accessCheck(FALSE)
    ->condition('type', 'tile')
    ->condition('status', 1)
    ->condition('field_category', array_keys($terms), 'IN')
    ->condition('field_subcategory', 'Special Collections', 'CONTAINS')
    ->sort('field_tile_search_weight', 'DESC')
    ->sort('title')
    ->range(0, 8)
    ->execute();
  if (!$faqs) {
    // Show the Subcategories the FAQ tiles do have, exactly as stored.
    $all = \Drupal::entityQuery('node')->accessCheck(FALSE)
      ->condition('type', 'tile')
      ->condition('field_category', array_keys($terms), 'IN')
      ->execute();
    $seen = [];
    foreach (\Drupal::entityTypeManager()->getStorage('node')->loadMultiple($all) as $tile) {
      $key = json_encode((string) $tile->get('field_subcategory')->value) . ($tile->isPublished() ? '' : ' (unpublished)');
      $seen[$key] = ($seen[$key] ?? 0) + 1;
    }
    arsort($seen);
    foreach ($seen as $value => $count) {
      $log[] = "FAQ tiles with Subcategory $value: $count.";
    }
  }
}
if (!$faqs) {
  $log[] = 'No published Tile has Category "FAQ" and Subcategory "Special Collections": the section would show the form and the AskUs link only.';
}
foreach (\Drupal::entityTypeManager()->getStorage('node')->loadMultiple($faqs) as $tile) {
  $answer = 'no answer text';
  foreach (['field_formatted_text', 'field_tile_linked_text', 'field_tile_long_summary'] as $name) {
    if ($tile->hasField($name) && trim(strip_tags((string) $tile->get($name)->value)) !== '') {
      $answer = "answer from $name";
      break;
    }
  }
  $link = $tile->hasField('field_tile_link') ? (string) $tile->get('field_tile_link')->uri : '';
  $log[] = "FAQ: \"{$tile->label()}\" (node {$tile->id()}, $answer" . ($link ? ", link $link" : ', no link') . ').';
}

// The homepage. ------------------------------------------------------------
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
  throw new \RuntimeException("$alias has a newer draft (revision $latest) than its published revision. Publish or discard it first.");
}

$sections = $node->get('field_scroll_section')->referencedEntities();
$position = NULL;
foreach ($sections as $delta => $section) {
  $label = $section->hasField('field_section_title') ? (string) $section->get('field_section_title')->value : '';
  if ($label === $title) {
    echo "\"$title\" is already on $alias (section " . ($delta + 1) . '). Nothing to do.' . PHP_EOL;
    return;
  }
  if ($position === NULL && $after !== '' && $label === $after) {
    $position = $delta + 1;
  }
}
if ($position === NULL) {
  foreach ($sections as $delta => $section) {
    if ($section->bundle() === 'landing_stacks') {
      $position = $delta;
      break;
    }
  }
  if ($after !== '') {
    $log[] = "No section titled \"$after\": the new section goes " . ($position === NULL ? 'last' : 'before the stacks') . '.';
  }
}
$position ??= count($sections);

$values = [
  'type' => $type,
  'field_section_title' => $title,
  'field_tile_eyebrow' => 'Ask Us',
  'field_tile_lede' => 'Answers to questions researchers often ask, and a form to send your own to Special Collections staff.',
  'field_tile_link' => ['uri' => 'https://askus.lib.lsu.edu/special', 'title' => 'More ways to ask'],
  'field_tile_layout' => 'ask',
];
$log[] = "Insert \"$title\" as section " . ($position + 1) . ' of ' . (count($sections) + 1) . " on $alias.";

foreach ($log as $line) {
  echo ($apply ? '' : '[dry run] ') . $line . PHP_EOL;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

if (!isset($allowed['ask'])) {
  $allowed['ask'] = 'Ask (FAQs and a question form, for Special Collections)';
  $layout->setSetting('allowed_values', $allowed)->save();
}

$items = $node->get('field_scroll_section')->getValue();
array_splice($items, $position, 0, [['entity' => Paragraph::create($values)]]);
$node->set('field_scroll_section', $items);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage("Special Collections: \"$title\" section, FAQs and question form (palefire_ask_section.php).");
$node->save();
echo "Saved a new revision of \"{$node->label()}\". Run: drush cr" . PHP_EOL;
