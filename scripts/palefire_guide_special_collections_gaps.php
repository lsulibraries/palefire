<?php

/**
 * @file
 * Fills what "How to Use Special Collections" was missing from the LibGuide
 * it replaces (guides.lib.lsu.edu/specialcollectionsintro).
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_special_collections_gaps.php
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_special_collections_gaps.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it lists each change as done, already there or
 * not found, and saves nothing.
 *
 * Optional arguments after "--":
 *   apply=1          Save.
 *   page=<alias>     The guide (default /v2/special/guides/special-collections).
 *
 * Changes, each skipped when already made:
 * - New section "What's in the collections", before "How a collection is
 *   described": the kinds of material held, and primary and secondary
 *   sources.
 * - "In the Reading Room": a first item, "Signing in" (photo ID at the
 *   Public Services Desk on each visit); portable scanners join the locker
 *   list.
 * - Option A: appointments can be booked up to 30 days ahead.
 * - "What is a finding aid?": a few finding aids are on paper only; LSU
 *   Libraries' "Using a Special Collections finding aid" video.
 * - "How a collection is described": the Special Collections research
 *   guides join its Resources.
 * - "Get help": which desk each number is for.
 *
 * Text edits are exact replacements in the guide's texts. A text an editor
 * has reworded is left alone and reported "not found".
 *
 * It stops, saving nothing, when the guide has a newer draft than its
 * current revision, which a save here would bypass.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;
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
$log = [];

// [find, replace]: straight apostrophes also match ’ and &#39;.
$fixes = [
  [
    'Leave food, drinks, pens, bags, cases, coats and umbrellas in a locker.',
    'Leave food, drinks, pens, portable scanners, bags, cases, coats and umbrellas in a locker.',
  ],
  [
    'Book one through your Special Collections account,',
    'Book one up to 30 days ahead through your Special Collections account,',
  ],
  [
    'and are listed on the Finding Aids page.',
    'and are listed on the Finding Aids page. A few exist only on paper; ask to see them in the Reading Room.',
  ],
  [
    'call Special Collections at (225) 578-6568,',
    'call the reference desk at <a href="tel:+12255786568">(225) 578-6568</a> for research help or the front desk at <a href="tel:+12255786544">(225) 578-6544</a> for anything else,',
  ],
];

$new_section = "What's in the collections";
$new_section_before = 'How a collection is described';
$new_section_body = <<<'HTML'
<p>Special Collections holds rare books and other published works, manuscripts and other unpublished papers, photographs, maps, the official records of LSU, Louisiana newspapers, Louisiana state documents and oral histories.</p>
<h4>Primary and secondary sources</h4>
<p>Most of it is <strong>primary sources</strong>: material made at the time by the people involved, such as diaries, letters, photographs, newspaper reports written as events happened, official records and speeches. <strong>Secondary sources</strong> come later and interpret primary ones, as textbooks, biographies and histories do. Special Collections holds both, but its primary sources are the part you won't find anywhere else.</p>
HTML;

$signing_in = ['Signing in', '<p>On each visit, sign in at the Public Services Desk in the lobby and show a photo ID.</p>'];

$video_url = 'https://www.youtube.com/watch?v=oVXidLMz4dw';
$video_name = 'Using a Special Collections finding aid';

$guides_url = 'https://guides.lib.lsu.edu/specialcollections';

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
$title_of = fn ($p, string $field) => $p->hasField($field) ? (string) $p->get($field)->value : '';
$blocks_of = fn ($section) => $section->hasField('field_guide_blocks') ? $section->get('field_guide_blocks')->referencedEntities() : [];
$section_named = function (string $name) use ($sections, $title_of) {
  foreach ($sections as $delta => $section) {
    if ($title_of($section, 'field_section_title') === $name) {
      return [$delta, $section];
    }
  }
  return [NULL, NULL];
};

// The text format and theme the guide's own sections use.
$format = NULL;
$theme = NULL;
foreach ($sections as $section) {
  if ($section->bundle() !== 'guide_section') {
    continue;
  }
  if ($theme === NULL && $section->hasField('field_section_theme')) {
    $theme = $section->get('field_section_theme')->value;
  }
  foreach ($blocks_of($section) as $block) {
    if ($format === NULL && $block->bundle() === 'guide_text') {
      $format = $block->get('field_block_body')->format;
    }
  }
}
if ($format === NULL) {
  throw new \RuntimeException("$alias has no guide Text block to take a text format from.");
}

// Every text on the page: [paragraph, field, parents to mark for saving].
$texts = [];
foreach ($sections as $section) {
  foreach ($blocks_of($section) as $block) {
    foreach (['field_block_body', 'field_block_intro'] as $field) {
      if ($block->hasField($field)) {
        $texts[] = [$block, $field, [$section]];
      }
    }
    foreach ($block->hasField('field_accordion_items') ? $block->get('field_accordion_items')->referencedEntities() : [] as $item) {
      if ($item->hasField('field_block_body')) {
        $texts[] = [$item, 'field_block_body', [$block, $section]];
      }
    }
  }
}

// Field values from the loaded entities, so edits made to them here are
// what gets saved.
$as_items = fn (array $entities) => array_map(fn ($e) => ['entity' => $e], array_values($entities));

$dirty = [];
$mark = function (array $paragraphs) use (&$dirty): void {
  foreach ($paragraphs as $p) {
    $p->setNeedsSave(TRUE);
    $dirty[spl_object_id($p)] = $p;
  }
};

// Text replacements. -------------------------------------------------------

foreach ($fixes as [$find, $replace]) {
  $status = 'not found (reworded by an editor?)';
  foreach (["'", '’', '&#39;'] as $apostrophe) {
    $f = str_replace("'", $apostrophe, $find);
    $r = str_replace("'", $apostrophe, $replace);
    foreach ($texts as [$p, $field, $parents]) {
      $value = (string) $p->get($field)->value;
      if (str_contains($value, $r)) {
        $status = 'already there';
        break 2;
      }
      if (str_contains($value, $f)) {
        $p->get($field)->value = str_replace($f, $r, $value);
        $mark(array_merge([$p], $parents));
        $status = 'replaced';
        break 2;
      }
    }
  }
  $log[] = "Text \"$find\": $status.";
}

// "Signing in", first in "In the Reading Room". ----------------------------

$accordion = NULL;
foreach ($sections as $section) {
  foreach ($blocks_of($section) as $block) {
    if ($block->bundle() === 'guide_accordion' && $title_of($block, 'field_block_title') === 'In the Reading Room') {
      $accordion = [$block, $section];
    }
  }
}
if (!$accordion) {
  $log[] = 'Accordion "In the Reading Room": not found; "Signing in" not added.';
}
else {
  [$block, $section] = $accordion;
  $items = $block->get('field_accordion_items')->referencedEntities();
  if (array_filter($items, fn ($i) => $title_of($i, 'field_item_title') === $signing_in[0])) {
    $log[] = '"Signing in": already there.';
  }
  else {
    $value = $as_items($items);
    array_unshift($value,['entity' => Paragraph::create([
      'type' => 'accordion_item',
      'field_item_title' => $signing_in[0],
      'field_block_body' => ['value' => $signing_in[1], 'format' => $format],
    ])]);
    $block->set('field_accordion_items', $value);
    $mark([$block, $section]);
    $log[] = '"Signing in": added as the first item of "In the Reading Room".';
  }
}

// Finding aid video. -------------------------------------------------------

[, $fa_section] = $section_named('What is a finding aid?');
$video_field = FieldConfig::loadByName('paragraph', 'guide_video', 'field_video');
if (!$fa_section) {
  $log[] = 'Section "What is a finding aid?": not found; video not added.';
}
elseif (!$video_field) {
  $log[] = 'Video: there is no Guide: Video block (no remote video media type).';
}
else {
  $media_storage = \Drupal::entityTypeManager()->getStorage('media');
  $media = NULL;
  foreach (array_keys($video_field->getSetting('handler_settings')['target_bundles'] ?? []) as $type) {
    $source_field = \Drupal::entityTypeManager()->getStorage('media_type')->load($type)?->getSource()->getConfiguration()['source_field'] ?? NULL;
    if (!$source_field) {
      continue;
    }
    $existing = \Drupal::entityQuery('media')->accessCheck(FALSE)
      ->condition('bundle', $type)->condition($source_field, '%oVXidLMz4dw%', 'LIKE')->range(0, 1)->execute();
    $media = $existing ? $media_storage->load(reset($existing)) : $media_storage->create([
      'bundle' => $type,
      'name' => $video_name,
      $source_field => $video_url,
      'status' => 1,
    ]);
    break;
  }
  $blocks = $blocks_of($fa_section);
  $has = $media && !$media->isNew() && array_filter($blocks, fn ($b) => $b->bundle() === 'guide_video' && (int) $b->get('field_video')->target_id === (int) $media->id());
  if (!$media) {
    $log[] = 'Video: no remote video media type with a source field.';
  }
  elseif ($has) {
    $log[] = "Video \"$video_name\": already there.";
  }
  else {
    // After the section's first text, "A map of a collection".
    $at = 0;
    foreach ($blocks as $i => $b) {
      if ($b->bundle() === 'guide_text') {
        $at = $i + 1;
        break;
      }
    }
    if ($apply && $media->isNew()) {
      $media->save();
    }
    $value = $as_items($blocks);
    array_splice($value, $at, 0, [['entity' => Paragraph::create([
      'type' => 'guide_video',
      'field_block_title' => 'Video: ' . $video_name,
      'field_video' => $media->isNew() ? [] : ['target_id' => $media->id()],
    ])]]);
    $fa_section->set('field_guide_blocks', $value);
    $mark([$fa_section]);
    $log[] = "Video \"$video_name\": added as block " . ($at + 1) . ' of "What is a finding aid?"' . ($media->isNew() ? ' (new media item)' : '') . '.';
  }
}

// Research guides in "How a collection is described". ----------------------

[, $described] = $section_named($new_section_before);
$list = NULL;
foreach ($described ? $blocks_of($described) : [] as $b) {
  if ($b->bundle() === 'resource_list') {
    $list = $b;
    break;
  }
}
if (!$list) {
  $log[] = "Resources in \"$new_section_before\": no resource list found; research guides not added.";
}
else {
  $ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
    ->condition('type', 'resource')
    ->condition('field_resource_link.uri', $guides_url)
    ->range(0, 1)->execute();
  $resource = $ids ? $storage->load(reset($ids)) : Node::create([
    'type' => 'resource',
    'title' => 'Special Collections Research Guides',
    'status' => 1,
    'field_resource_link' => ['uri' => $guides_url],
    'field_resource_type' => 'guide',
    'field_resource_desc' => 'Guides to the major subject areas, such as rare books, Louisiana newspapers and Louisiana history, by Special Collections staff.',
    'field_resource_proxy' => FALSE,
  ]);
  $listed = array_map(fn ($v) => (int) $v['target_id'], $list->get('field_resources')->getValue());
  if (!$resource->isNew() && in_array((int) $resource->id(), $listed, TRUE)) {
    $log[] = 'Research guides: already listed.';
  }
  else {
    if ($apply && $resource->isNew()) {
      $resource->save();
    }
    if (!$resource->isNew()) {
      $list->get('field_resources')->appendItem(['target_id' => $resource->id()]);
      $mark([$list, $described]);
    }
    $log[] = 'Research guides: added to the Resources in "' . $new_section_before . '"' . ($ids ? '' : ' (new Resource)') . '.';
  }
}

// New section. -------------------------------------------------------------

$section_items = $as_items($sections);
[$exists] = $section_named($new_section);
[$before_at] = $section_named($new_section_before);
if ($exists !== NULL) {
  $log[] = "Section \"$new_section\": already there.";
}
else {
  $values = [
    'type' => 'guide_section',
    'field_section_title' => $new_section,
    'field_guide_blocks' => [Paragraph::create([
      'type' => 'guide_text',
      'field_block_body' => ['value' => $new_section_body, 'format' => $format],
    ])],
  ];
  if ($theme !== NULL) {
    $values['field_section_theme'] = $theme;
  }
  $at = $before_at ?? count($section_items);
  array_splice($section_items, $at, 0, [['entity' => Paragraph::create($values)]]);
  $log[] = "Section \"$new_section\": added as section " . ($at + 1) . ($before_at === NULL ? " (no \"$new_section_before\", so last)" : '') . '.';
}

foreach ($log as $line) {
  echo ($apply ? '' : '[dry run] ') . $line . PHP_EOL;
}
$changed = $dirty || count($section_items) !== count($sections);
if (!$changed) {
  echo 'Nothing to change.' . PHP_EOL;
  return;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$node->set('field_scroll_section', $section_items);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('Guide: what the LibGuide had and this lacked (palefire_guide_special_collections_gaps.php).');
$node->save();
echo "Saved a new revision of \"{$node->label()}\". Run: drush cr" . PHP_EOL;
