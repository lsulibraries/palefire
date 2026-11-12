<?php

/**
 * @file
 * Adds a "Related guides" section to the Special Collections guides, so
 * each links to the others a reader is likely to need next.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_related.php
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_related.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it reports what it would do and saves nothing.
 *
 * Optional arguments after "--":
 *   apply=1        Save.
 *   only=<title>   Only this guide (its exact title).
 *
 * Guides are found by title (Modular pages). Each related guide becomes a
 * Resource, type "Guide or handbook", linked by node (entity:node/<nid>),
 * so the link follows the page if its alias changes; an existing Resource
 * with that link is reused. The section holds one Resource list and goes
 * before "Get help" or "Contact a curator", else last.
 *
 * Skipped, with a line saying why:
 * - a guide that isn't found, already has "Related guides", or has a newer
 *   draft than its current revision (a save here would bypass it);
 * - a related guide that isn't found or isn't published, so visitors never
 *   get a link they can't open. Publish it and run this again: guides that
 *   already have the section are left alone, so add it there by hand.
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
$only = $args['only'] ?? NULL;

$heading = 'Related guides';
$before = ['Get help', 'Contact a curator'];

// Each guide's one-line description, as its Resource shows it.
$desc = [
  'How to Use Special Collections' => 'Requesting materials, visiting the Reading Room, reading a finding aid and asking for help.',
  'Parking and Travel to Hill Memorial Library' => 'Where to park without an LSU permit, accessible parking, and getting to Baton Rouge.',
  'Special Collections Glossary' => 'The archive terms you meet when finding, requesting and using materials, in plain words.',
  'Donating Materials to Special Collections' => 'What Special Collections collects and accepts, and how a gift is made.',
];

// Guide => related guides, in the order shown. Ordering Copies from Special
// Collections is part of How to Use Special Collections now
// (palefire_guide_merge_duplication.php).
$related = [
  'How to Use Special Collections' => [
    'Parking and Travel to Hill Memorial Library',
    'Special Collections Glossary',
    'Donating Materials to Special Collections',
  ],
  'Parking and Travel to Hill Memorial Library' => [
    'How to Use Special Collections',
    'Special Collections Glossary',
  ],
  'Special Collections Glossary' => [
    'How to Use Special Collections',
  ],
  'Donating Materials to Special Collections' => [
    'How to Use Special Collections',
  ],
];

$storage = \Drupal::entityTypeManager()->getStorage('node');
$guides = [];
$find = function (string $title) use ($storage, &$guides): ?Node {
  if (!array_key_exists($title, $guides)) {
    $ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
      ->condition('type', 'modular_page')
      ->condition('title', $title)
      ->range(0, 1)->execute();
    $guides[$title] = $ids ? $storage->load(reset($ids)) : NULL;
  }
  return $guides[$title];
};

// One Resource per related guide, found by its link or created on apply.
$resources = [];
$resource = function (Node $guide) use (&$resources, $storage, $desc, $apply): array {
  $uri = 'entity:node/' . $guide->id();
  if (!isset($resources[$uri])) {
    $ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
      ->condition('type', 'resource')
      ->condition('field_resource_link.uri', $uri)
      ->range(0, 1)->execute();
    $node = $ids ? $storage->load(reset($ids)) : Node::create([
      'type' => 'resource',
      'title' => $guide->label(),
      'status' => 1,
      'field_resource_link' => ['uri' => $uri],
      'field_resource_type' => 'guide',
      'field_resource_desc' => $desc[$guide->label()] ?? '',
      'field_resource_proxy' => FALSE,
    ]);
    $new = $node->isNew();
    if ($apply && $new) {
      $node->save();
    }
    $resources[$uri] = [$node, $new];
  }
  return $resources[$uri];
};

$saved = 0;
foreach ($related as $title => $targets) {
  if ($only !== NULL && $only !== $title) {
    continue;
  }
  $prefix = ($apply ? '' : '[dry run] ') . "\"$title\": ";
  $node = $find($title);
  if (!$node || !$node->hasField('field_scroll_section')) {
    echo $prefix . 'not found; skipped.' . PHP_EOL;
    continue;
  }
  $latest = $storage->getLatestRevisionId($node->id());
  if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
    echo $prefix . "has a newer draft (revision $latest); skipped. Publish or discard it first." . PHP_EOL;
    continue;
  }

  $sections = $node->get('field_scroll_section')->referencedEntities();
  $position = NULL;
  $format = NULL;
  $theme = NULL;
  foreach ($sections as $delta => $section) {
    $label = $section->hasField('field_section_title') ? (string) $section->get('field_section_title')->value : '';
    if ($label === $heading) {
      echo $prefix . "already has \"$heading\"; skipped." . PHP_EOL;
      continue 2;
    }
    if ($position === NULL && in_array($label, $before, TRUE)) {
      $position = $delta;
    }
    if ($section->bundle() === 'guide_section' && $theme === NULL && $section->hasField('field_section_theme')) {
      $theme = $section->get('field_section_theme')->value;
    }
  }
  $position ??= count($sections);

  $links = [];
  $names = [];
  foreach ($targets as $target_title) {
    $target = $find($target_title);
    if (!$target) {
      echo $prefix . "related \"$target_title\" not found; left out." . PHP_EOL;
      continue;
    }
    if (!$target->isPublished()) {
      echo $prefix . "related \"$target_title\" is unpublished; left out." . PHP_EOL;
      continue;
    }
    [$res, $new] = $resource($target);
    $links[] = $res;
    $names[] = $target_title . ($new ? ' (new Resource)' : '');
  }
  if (!$links) {
    echo $prefix . 'no related guide to link; skipped.' . PHP_EOL;
    continue;
  }
  echo $prefix . "add \"$heading\" as section " . ($position + 1) . ' of ' . (count($sections) + 1) . ': ' . implode(', ', $names) . '.' . PHP_EOL;
  if (!$apply) {
    continue;
  }

  $values = [
    'type' => 'guide_section',
    'field_section_title' => $heading,
    'field_guide_blocks' => [Paragraph::create([
      'type' => 'resource_list',
      'field_resources' => array_map(fn ($r) => ['target_id' => $r->id()], $links),
    ])],
  ];
  if ($theme !== NULL && FieldConfig::loadByName('paragraph', 'guide_section', 'field_section_theme')) {
    $values['field_section_theme'] = $theme;
  }
  $items = array_map(fn ($e) => ['entity' => $e], $sections);
  array_splice($items, $position, 0, [['entity' => Paragraph::create($values)]]);
  $node->set('field_scroll_section', $items);
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage("Guide: \"$heading\" section (palefire_guide_related.php).");
  $node->save();
  $saved++;
}

echo $apply ? "Saved $saved guide(s). Run: drush cr" . PHP_EOL : 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
