<?php

/**
 * @file
 * Replaces the Prices section's blocks on "Ordering Copies from Special
 * Collections" (built by palefire_guide_duplication.php) with the price
 * list in palefire_guide_duplication_prices.html.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_duplication_prices.php
 *   drush cr
 *
 * Optional arguments after "--":
 *   path=<alias>  The guide's URL alias (default:
 *                 /v2/special/guides/duplication).
 *
 * Saves a new revision of the page, so the old Prices can be restored from
 * its Revisions tab. The new block takes the text format of the section's
 * first old block. Other sections, and the page's published state, are
 * left alone. Running it again replaces the price list with itself.
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
    $args[trim($k)] = trim($v);
  }
}
$alias = '/' . ltrim(($args['path'] ?? '') !== '' ? $args['path'] : '/v2/special/guides/duplication', '/');

$system_path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
if (!preg_match('#^/node/(\d+)$#', $system_path, $match)) {
  throw new \RuntimeException("No page found at $alias.");
}
$page = \Drupal::entityTypeManager()->getStorage('node')->load($match[1]);
if (!$page instanceof NodeInterface || !$page->hasField('field_scroll_section')) {
  throw new \RuntimeException("node/{$match[1]} isn't a Modular page.");
}

// The section through its field item, so the page's save (below) saves it
// as a new revision; saved on its own it would change the old page
// revision too.
$section = NULL;
foreach ($page->get('field_scroll_section') as $item) {
  $candidate = $item->entity;
  if ($candidate instanceof ParagraphInterface && $candidate->bundle() === 'guide_section'
    && trim((string) $candidate->get('field_section_title')->value) === 'Prices') {
    $section = $candidate;
    break;
  }
}
if (!$section) {
  throw new \RuntimeException('No section titled "Prices" on ' . $alias . '.');
}

// The old blocks' text format, else the WYSIWYG one.
$format = NULL;
foreach ($section->get('field_guide_blocks')->referencedEntities() as $block) {
  if ($block->hasField('field_block_body') && !$block->get('field_block_body')->isEmpty()) {
    $format = $block->get('field_block_body')->format;
    break;
  }
}
foreach ($format === NULL ? filter_formats() : [] as $id => $candidate) {
  if ($id === 'wysiwyg' || strcasecmp((string) $candidate->label(), 'WYSIWYG') === 0) {
    $format = $id;
    break;
  }
}
if ($format === NULL) {
  throw new \RuntimeException('No WYSIWYG text format found.');
}

$markup = file_get_contents(__DIR__ . '/palefire_guide_duplication_prices.html');
if ($markup === FALSE || trim($markup) === '') {
  throw new \RuntimeException('palefire_guide_duplication_prices.html is missing or empty.');
}

$section->set('field_guide_blocks', [
  Paragraph::create([
    'type' => 'guide_text',
    'field_block_body' => ['value' => trim($markup), 'format' => $format],
    'field_text_style' => 'default',
  ]),
]);
$section->setNeedsSave(TRUE);

$page->setNewRevision(TRUE);
$page->setRevisionLogMessage('Prices as a price list (palefire_guide_duplication_prices.php).');
$page->save();

echo "Updated the Prices section on $alias (node/{$page->id()}), text format $format. Run: drush cr" . PHP_EOL;
