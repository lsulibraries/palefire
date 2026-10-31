<?php

/**
 * @file
 * Adds "Coming from out of town" to the Hill Memorial Library parking guide:
 * the LibGuide's "Travel Resources for Visitors" page
 * (guides.lib.lsu.edu/specialcollectionsintro/travel), which How to Use
 * Special Collections doesn't carry.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_parking_travel.php
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_parking_travel.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it reports what it would do and saves nothing.
 *
 * Optional arguments after "--":
 *   apply=1          Save.
 *   page=<alias>     The guide (default
 *                    /v2/special/guides/parking-and-travel-hill-memorial-library).
 *   before=<title>   The section to go before (default "Get help"). Not
 *                    found, the new one goes last.
 *
 * What it adds: one guide_section with a Text block (airports, lodging,
 * Baton Rouge) and a Resource list. Resources are found by link first, so
 * existing ones are reused. The LibGuide's parking lots are already in the
 * guide; its dated "Best of 225" lists, Yelp and Tripadvisor are left out.
 *
 * It stops, saving nothing, when:
 * - a section titled "Coming from out of town" is already on the page;
 * - the guide has a newer draft than its current revision, which a save
 *   here would bypass.
 */

declare(strict_types=1);

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
$alias = '/' . trim($args['page'] ?? '/v2/special/guides/parking-and-travel-hill-memorial-library', '/');
$before = $args['before'] ?? 'Get help';

$title = 'Coming from out of town';
$body = <<<'HTML'
<p>Special Collections welcomes researchers from other states and countries. A few things help when you plan the trip.</p>
<h4>Getting to Baton Rouge</h4>
<p>Baton Rouge Metropolitan Airport (BTR) is the closest, with car rental at the airport, and Uber and Lyft in town. Its website lists taxis, shuttles and the local bus. Louis Armstrong New Orleans International Airport (MSY) has more flights; from there, rent a car or book a shuttle to Baton Rouge.</p>
<h4>Where to stay</h4>
<p>LSU's campus travel site has discounted rates at hotels near campus. Researchers staying a week or more often rent a short-term apartment nearby.</p>
<h4>Around Baton Rouge</h4>
<p>Visit Baton Rouge lists restaurants and things to do, for the evenings and the days the Reading Room is closed.</p>
HTML;

$wanted = [
  ['https://www.flybtr.com/', 'Baton Rouge Metropolitan Airport (BTR)', 'Flights, car rental, and taxis, shuttles and buses into town.'],
  ['https://www.flymsy.com/', 'New Orleans International Airport (MSY)', 'More flights, about 75 miles from campus. Rent a car or book a shuttle to Baton Rouge.'],
  ['https://campustravel.com/university/louisiana-state-university/', 'Hotels near LSU', 'Discounted rates at hotels near campus, through LSU\'s campus travel site.'],
  ['https://www.visitbatonrouge.com/restaurants/', 'Restaurants in Baton Rouge', 'Where to eat, from Visit Baton Rouge.'],
  ['https://www.visitbatonrouge.com/things-to-do/', 'Things to do in Baton Rouge', 'Museums, music, outdoors and events, from Visit Baton Rouge.'],
];

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
$log = [];
if ($position === NULL) {
  $log[] = "No section titled \"$before\": the new section goes last.";
  $position = count($sections);
}
if ($format === NULL) {
  throw new \RuntimeException("$alias has no guide Text block to take a text format from.");
}

// Resources. ---------------------------------------------------------------

$resources = [];
foreach ($wanted as [$url, $name, $desc]) {
  $ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
    ->condition('type', 'resource')
    ->condition('field_resource_link.uri', $url)
    ->range(0, 1)->execute();
  if ($ids) {
    $resources[] = $storage->load(reset($ids));
    $log[] = "Resource \"$name\": reuse node " . reset($ids) . '.';
    continue;
  }
  $resources[] = Node::create([
    'type' => 'resource',
    'title' => $name,
    'status' => 1,
    'field_resource_link' => ['uri' => $url],
    'field_resource_type' => 'website',
    'field_resource_desc' => $desc,
    'field_resource_proxy' => FALSE,
  ]);
  $log[] = "Resource \"$name\": create.";
}

$log[] = "Insert \"$title\" as section " . ($position + 1) . ' of ' . (count($sections) + 1) . " on $alias (text format $format).";
foreach ($log as $line) {
  echo ($apply ? '' : '[dry run] ') . $line . PHP_EOL;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

foreach ($resources as $resource) {
  if ($resource->isNew()) {
    $resource->save();
  }
}
$values = [
  'type' => 'guide_section',
  'field_section_title' => $title,
  'field_guide_blocks' => [
    Paragraph::create([
      'type' => 'guide_text',
      'field_block_body' => ['value' => $body, 'format' => $format],
    ]),
    Paragraph::create([
      'type' => 'resource_list',
      'field_resources' => array_map(fn ($r) => ['target_id' => $r->id()], $resources),
    ]),
  ],
];
if ($theme !== NULL) {
  $values['field_section_theme'] = $theme;
}

$items = array_map(fn ($e) => ['entity' => $e], $sections);
array_splice($items, $position, 0, [['entity' => Paragraph::create($values)]]);
$node->set('field_scroll_section', $items);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage("Guide: \"$title\" section, from the Special Collections travel LibGuide (palefire_guide_parking_travel.php).");
$node->save();
echo "Saved a new revision of \"{$node->label()}\". Run: drush cr" . PHP_EOL;
