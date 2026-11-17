<?php

/**
 * @file
 * Oral history exhibitions: the same Category as Mossville, the title
 * cover as the card picture, and the poster in the last gallery.
 *
 * For each exhibition under /v2/oralhistory/exhibitions/:
 * - Category (field_category): adds Mossville History Project's terms
 *   ("Digital", "Exhibition", shown as "Digital Exhibition" on cards and in
 *   the Oral History page's Find us list), keeping any it has.
 * - Card picture: empties Cover image (field_image_cover, the poster from
 *   the old Showcase, palefire_oral_history_exhibit_covers.php), so cards
 *   (_palefire_card_image_item()) fall back to the exhibition's own cover,
 *   its title page photograph (field_exhibit_cover). Only when it has one.
 * - The poster: added at the end of the last gallery (its About or
 *   Acknowledgements) as an Exhibition: Image block, centered in the text
 *   column, captioned "The exhibition’s poster.", unless that gallery
 *   already shows it.
 *
 *   drush php:script scripts/palefire_oral_history_exhibit_cards.php
 *   drush php:script scripts/palefire_oral_history_exhibit_cards.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. Each changed exhibition is saved in a new
 * revision; the last gallery in place (as palefire_exhibit_section_lead.php),
 * so the page's reference to it stays good. Running it again changes
 * nothing.
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
    $args[trim($k)] = trim($v);
  }
}
$apply = ($args['apply'] ?? '') === '1';
$prefix = $apply ? '' : '[dry run] ';

// The caption's text format: WYSIWYG, as the exhibitions' builds use.
$format = NULL;
foreach (filter_formats() as $id => $candidate) {
  if ($id === 'wysiwyg' || strcasecmp((string) $candidate->label(), 'WYSIWYG') === 0) {
    $format = $id;
    break;
  }
}
if ($format === NULL) {
  throw new \RuntimeException('No WYSIWYG text format found.');
}

$folders = ['mossvilleexhibit', 'gu272', 'graphicdesign', 'klsu', 'storm', 'jf', 'lsuag', 'lady', 'rpoe', 'dntume', 'football'];

$nodes = \Drupal::entityTypeManager()->getStorage('node');
$aliases = \Drupal::service('path_alias.manager');
$load = static function (string $folder) use ($aliases, $nodes): ?NodeInterface {
  $system = $aliases->getPathByAlias('/v2/oralhistory/exhibitions/' . $folder);
  $node = preg_match('#^/node/(\d+)$#', $system, $m) ? $nodes->load($m[1]) : NULL;
  return $node instanceof NodeInterface ? $node : NULL;
};

$model = $load('mossvilleexhibit');
if (!$model || !$model->hasField('field_category') || $model->get('field_category')->isEmpty()) {
  throw new \RuntimeException('Mossville History Project has no Category to copy.');
}
$terms = [];
foreach ($model->get('field_category')->referencedEntities() as $term) {
  $terms[(int) $term->id()] = $term->label();
}
echo 'Category from Mossville: ' . implode(', ', $terms) . PHP_EOL;

foreach ($folders as $folder) {
  $node = $load($folder);
  if (!$node) {
    echo "{$prefix}$folder: no page yet, skipped." . PHP_EOL;
    continue;
  }
  $label = "$folder (node {$node->id()})";
  $changes = [];

  // Category.
  $add = [];
  if ($node->hasField('field_category')) {
    $has = array_map('intval', array_column($node->get('field_category')->getValue(), 'target_id'));
    $add = array_diff_key($terms, array_flip($has));
    if ($add) {
      $changes[] = 'Category + ' . implode(', ', $add);
    }
  }

  // The poster: off the card, into the last gallery.
  $poster = ($node->hasField('field_image_cover') && !$node->get('field_image_cover')->isEmpty())
    ? $node->get('field_image_cover')->entity : NULL;
  $own_cover = $node->hasField('field_exhibit_cover') && !$node->get('field_exhibit_cover')->isEmpty();
  $last = NULL;
  foreach ($node->get('field_scroll_section')->referencedEntities() as $section) {
    if ($section->bundle() === 'exhibit_page') {
      $last = $section;
    }
  }
  $placed = FALSE;
  if ($poster && $last) {
    foreach ($last->get('field_guide_blocks')->referencedEntities() as $block) {
      if ($block->bundle() === 'exhibit_figure' && (int) $block->get('field_figure_image')->target_id === (int) $poster->id()) {
        $placed = TRUE;
      }
    }
  }
  $place = $poster && $last && !$placed;
  $clear = $poster && $own_cover;
  if ($place) {
    $changes[] = 'poster "' . $poster->label() . '" to the end of "' . $last->get('field_section_title')->value . '"';
  }
  if ($clear) {
    $changes[] = 'card picture: the title cover (Cover image emptied)';
  }
  elseif ($poster) {
    $changes[] = 'card picture kept: no title cover to use';
  }

  if (!$add && !$place && !$clear) {
    echo "{$prefix}$label: nothing to change." . PHP_EOL;
    continue;
  }
  echo "{$prefix}$label: " . implode('; ', $changes) . '.' . PHP_EOL;
  if (!$apply) {
    continue;
  }

  if ($place) {
    $figure = Paragraph::create([
      'type' => 'exhibit_figure',
      'field_figure_image' => ['target_id' => $poster->id()],
      'field_figure_caption' => ['value' => '<p>The exhibition’s poster.</p>', 'format' => $format],
      'field_figure_layout' => 'center',
    ]);
    $figure->setParentEntity($last, 'field_guide_blocks');
    $figure->save();
    $last->get('field_guide_blocks')->appendItem($figure);
    $last->setNewRevision(FALSE);
    $last->save();
    $nodes->resetCache([$node->id()]);
    $node = $nodes->load($node->id());
  }
  foreach ($add as $tid => $name) {
    $node->get('field_category')->appendItem(['target_id' => $tid]);
  }
  if ($clear) {
    $node->set('field_image_cover', []);
  }
  if ($add || $clear) {
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Digital Exhibition category, title cover as card picture, poster in the last gallery (palefire_oral_history_exhibit_cards.php).');
    $node->save();
  }
}

if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
}
