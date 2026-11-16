<?php

/**
 * @file
 * Builds Oral History's homepage, /v2/oralhistory, on the pattern of the
 * Special Collections homepage (/v2/special).
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_oral_history_page.php
 *   drush php:script themes/custom/palefire/scripts/palefire_oral_history_page.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it reports what it would build and saves nothing.
 *
 * Optional arguments after "--":
 *   apply=1         Save.
 *   publish=1       Publish the new page (default: saved unpublished, for a
 *                   look first; publish it in its edit form later).
 *   from=<alias>    The page to copy the landing from (default /v2/special).
 *   path=<alias>    The new page's address (default /v2/oralhistory).
 *   override=1      Rebuild the page already at that address (same node,
 *                   address and history, in a new revision), keeping the
 *                   background videos and poster chosen on it: the hero
 *                   plays them as a faded ground behind the intro
 *                   (.pf-landing--wash). Its published state stays unless
 *                   publish=1.
 *
 * What it builds, top to bottom:
 * - A copy of the Special Collections page's Landing hero: the Hill film and
 *   poster, the Visit card (the page's location fields come with the copy),
 *   and a copy of its Building map with Here set to the Oral History room
 *   (hill_memorial.oral_history). With Here set, the theme makes it Oral
 *   History's landing (_palefire_hero_map_banner(), landing.html.twig): the
 *   first banner is the map with the room lit, the search is Oral histories
 *   in Special Collections mode, the buttons are Listen online and the FAQs,
 *   the Quick Start is Oral History's three ways, and Browse by series
 *   follows it. The Quick Guide fields are emptied so that text shows.
 * - Banners: the Mossville History Project exhibition, the Georgetown 272
 *   tile and the podcast tile (the newest blog post comes after them).
 * - Tile showcases: "Voices in the Collection" (finding aids, the LDL,
 *   copies, FAQs), "Recording Your Own" (the Center's services, as a
 *   ledger) and "Listen In" (the digital exhibitions and the podcast).
 *   The tiles are the old /oralhistory page's, by node ID; one that is
 *   missing is left out and listed.
 * - "Who to ask", with the Director of the Williams Center (the published
 *   Staff node whose Job title matches), when there is exactly one.
 *
 * It stops, saving nothing, when a page already has the address (unless
 * override=1), or the source page has no Landing hero with a Building map.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
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
$apply = ($args['apply'] ?? '') === '1';
$publish = ($args['publish'] ?? '') === '1';
$from = '/' . trim($args['from'] ?? '/v2/special', '/');
$path = '/' . trim($args['path'] ?? '/v2/oralhistory', '/');
$override = ($args['override'] ?? '') === '1';

$storage = \Drupal::entityTypeManager()->getStorage('node');
$at = static function (string $alias) use ($storage): ?NodeInterface {
  $system = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
  return preg_match('#^/node/(\d+)$#', $system, $m) ? $storage->load($m[1]) : NULL;
};
$log = [];

// The address. -------------------------------------------------------------
$existing = $at($path);
if ($existing && !$override) {
  echo "$path is already node {$existing->id()} (\"{$existing->label()}\", {$existing->bundle()}). Run again with override=1 to rebuild it, keeping its background videos." . PHP_EOL;
  return;
}
if ($existing && !$existing->hasField('field_scroll_section')) {
  throw new \RuntimeException("$path is node {$existing->id()}, a {$existing->bundle()} with no Sections field: rebuild it by hand, or give another path=.");
}

// The videos already chosen on the page, from the first section (or the
// page) that has any: the new hero plays them as its faded ground.
$chosen = NULL;
if ($existing) {
  $holders = array_merge($existing->get('field_scroll_section')->referencedEntities(), [$existing]);
  foreach ($holders as $holder) {
    if ($holder->hasField('field_bg_videos') && !$holder->get('field_bg_videos')->isEmpty()) {
      $chosen = $holder;
      break;
    }
  }
}

// The source page and its landing. -----------------------------------------
$source = $at($from);
if (!$source || !$source->hasField('field_scroll_section')) {
  throw new \RuntimeException("No Modular page found at $from.");
}
$hero_source = NULL;
$showcase_source = NULL;
foreach ($source->get('field_scroll_section')->referencedEntities() as $section) {
  if (!$hero_source && $section->bundle() === 'landing_hero') {
    $hero_source = $section;
  }
  if (!$showcase_source && $section->bundle() === 'tile_showcase') {
    $showcase_source = $section;
  }
}
$map_source = $hero_source && $hero_source->hasField('field_bento_map') ? $hero_source->get('field_bento_map')->entity : NULL;
if (!$map_source instanceof ParagraphInterface) {
  throw new \RuntimeException("$from has no Landing hero with a Building map.");
}
if (!$map_source->hasField('field_map_here')) {
  throw new \RuntimeException('The Building map has no Here field (field_map_here). Run palefire_location_setup.php first.');
}

// A node by ID, or by alias, reported either way.
$pick = static function (int|string $ref, string $why) use ($storage, $at, &$log): ?int {
  $node = is_int($ref) ? $storage->load($ref) : $at($ref);
  if (!$node instanceof NodeInterface) {
    $log[] = "Left out $why: " . (is_int($ref) ? "node $ref" : $ref) . ' not found.';
    return NULL;
  }
  $log[] = "  $why: \"{$node->label()}\" (node {$node->id()}, {$node->bundle()}" . ($node->isPublished() ? '' : ', unpublished: visitors won’t see it') . ').';
  return (int) $node->id();
};
$mossville = $pick('/v2/special/exhibitions/mossville', 'Mossville exhibition') ?? $pick(91045, 'Mossville tile');

// The hero. ----------------------------------------------------------------
$hero = $hero_source->createDuplicate();
$map = $map_source->createDuplicate();
$map->set('field_map_here', 'hill_memorial.oral_history');
$hero->set('field_bento_map', [['entity' => $map]]);
$log[] = "Landing hero: a copy of $from's, its Building map's Here set to hill_memorial.oral_history.";

foreach (['field_bg_videos', 'field_bg_poster'] as $name) {
  if ($chosen && $chosen->hasField($name) && $hero->hasField($name) && !$chosen->get($name)->isEmpty()) {
    $hero->set($name, $chosen->get($name)->getValue());
    $log[] = "  $name: the existing page's (" . count($chosen->get($name)) . '), played as a faded ground behind the intro.';
  }
}
if ($existing && !$chosen) {
  $log[] = "  The existing page has no background videos: the hero keeps $from's film.";
}

foreach (['field_quick_guide_intro', 'field_quick_guide_link', 'field_quick_guide_visit'] as $name) {
  if ($hero->hasField($name) && !$hero->get($name)->isEmpty()) {
    $hero->set($name, NULL);
    $log[] = "  Emptied $name (Oral History's built-in text shows).";
  }
}
if ($hero->hasField('field_quick_guide_title')) {
  $hero->set('field_quick_guide_title', 'Quick Start Guide');
}

if ($hero->hasField('field_hero_banners')) {
  $log[] = 'Banners (after the map banner):';
  $banners = array_values(array_filter([
    $mossville,
    $pick(91044, 'Georgetown 272 tile'),
    $pick(92, 'Oral History Podcast tile'),
  ]));
  $hero->set('field_hero_banners', array_map(static fn (int $id): array => ['target_id' => $id], $banners));
}
else {
  $log[] = 'The Landing hero has no Banners field: only the map banner and the blog post show.';
}

// The newest post in a blog category named Oral History, if there is one.
if ($hero->hasField('field_landing_blog_category')) {
  $vocabularies = array_keys($hero->getFieldDefinition('field_landing_blog_category')->getSetting('handler_settings')['target_bundles'] ?? []);
  $terms = $vocabularies ? \Drupal::entityQuery('taxonomy_term')->accessCheck(FALSE)
    ->condition('vid', $vocabularies, 'IN')
    ->condition('name', 'Oral History')
    ->range(0, 1)->execute() : [];
  if ($terms) {
    $hero->set('field_landing_blog_category', [['target_id' => (int) reset($terms)]]);
    $log[] = 'Blog banner: the newest post in "Oral History" (term ' . reset($terms) . ').';
  }
  else {
    $log[] = "Blog banner: no \"Oral History\" blog category; it keeps $from's categories. Change it in the hero's Blog categories.";
  }
}

// The tile showcases. ------------------------------------------------------
$type = 'tile_showcase';
foreach (['field_section_title', 'field_tile_layout', 'field_tiles', 'field_tile_eyebrow', 'field_tile_lede', 'field_tile_link'] as $name) {
  if (!FieldConfig::loadByName('paragraph', $type, $name)) {
    throw new \RuntimeException("paragraph.$type.$name not found. Run palefire_tile_showcase_setup.php and palefire_tile_showcase_gallery_setup.php first.");
  }
}
$layouts = FieldStorageConfig::loadByName('paragraph', 'field_tile_layout')->getSetting('allowed_values');

$showcases = [
  [
    'title' => 'Voices in the Collection',
    'eyebrow' => 'The collection',
    'lede' => 'Finding aids for every series, interviews to hear online in the Louisiana Digital Library, and copies of recordings and transcripts to order.',
    'link' => ['uri' => 'https://www.louisianadigitallibrary.org/lsu-thwcoh', 'title' => 'Listen online'],
    'layout' => 'stagger',
    'tiles' => [
      [88, 'Oral History Collections'],
      [851, 'Oral History on the LDL'],
      [93, 'Order copies of oral history'],
      [95, 'Oral History FAQs'],
    ],
  ],
  [
    'title' => 'Recording Your Own',
    'eyebrow' => 'Services',
    'lede' => 'Guides, workshops and seminars for anyone starting an oral history, and partnerships with the Williams Center to record Louisiana’s communities.',
    'link' => NULL,
    'layout' => 'ledger',
    'tiles' => [
      [96, 'Create your own oral history'],
      [98, 'Collaborate with the Williams Center'],
      [102, 'Oral History Research Consultations'],
      [99, 'Oral History Workshops'],
      [100, 'Oral History Seminars'],
    ],
  ],
  [
    'title' => 'Listen In',
    'eyebrow' => 'Exhibitions',
    'lede' => 'Digital exhibitions made from the Center’s interviews, from Mossville to Tiger Stadium, and What Endures, its podcast.',
    'link' => ['uri' => 'https://oralhistory.blogs.lib.lsu.edu/podcast-library/', 'title' => 'All podcast episodes'],
    'layout' => 'masonry',
    'tiles' => [
      [$mossville, 'Mossville History Project'],
      [91044, 'The Georgetown 272'],
      [91043, 'History of Graphic Design in South Louisiana'],
      [50508, 'History of KLSU'],
      [50506, 'Tempests: Storms in the Archives'],
      [50505, 'New Orleans Jazz & Heritage Festival'],
      [50504, 'Cooperative Extension at LSU'],
      [50503, 'A Lady in the South'],
      [50502, 'The Relentless Pursuit of “Equal”'],
      [50501, 'Do Not Tear Up My Earth'],
      [50500, 'Saturday Night in Tiger Stadium'],
      [92, 'Oral History Podcast'],
      [94, 'Oral History on Facebook'],
    ],
  ],
];

$sections = [$hero];
foreach ($showcases as $spec) {
  $layout = isset($layouts[$spec['layout']]) ? $spec['layout'] : 'grid';
  $log[] = "Section \"{$spec['title']}\" ($layout" . ($layout !== $spec['layout'] ? ", as {$spec['layout']} isn't a Layout option" : '') . '):';
  $tiles = [];
  foreach ($spec['tiles'] as [$ref, $why]) {
    $id = $ref === NULL ? NULL : $pick($ref, $why);
    if ($id) {
      $tiles[] = ['target_id' => $id];
    }
  }
  if (!$tiles) {
    $log[] = '  Left out: none of its tiles exist.';
    continue;
  }
  $values = [
    'type' => $type,
    'field_section_title' => $spec['title'],
    'field_tile_eyebrow' => $spec['eyebrow'],
    'field_tile_lede' => $spec['lede'],
    'field_tile_layout' => $layout,
    'field_tiles' => $tiles,
  ];
  if ($spec['link']) {
    $values['field_tile_link'] = $spec['link'];
  }
  // Snap, height, theme and TOC as the Special Collections page's showcases.
  foreach (['field_section_snap', 'field_section_height', 'field_section_theme', 'field_section_toc_hide'] as $name) {
    if ($showcase_source && $showcase_source->hasField($name) && !$showcase_source->get($name)->isEmpty()
      && FieldConfig::loadByName('paragraph', $type, $name)) {
      $values[$name] = $showcase_source->get($name)->getValue();
    }
  }
  $sections[] = Paragraph::create($values);
}

// Who to ask. --------------------------------------------------------------
if (FieldConfig::loadByName('paragraph', 'staff_contacts', 'field_staff_contacts') && FieldConfig::loadByName('paragraph', 'staff_contact', 'field_librarian')) {
  $people = array_filter(
    $storage->loadMultiple(\Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'staff')->condition('status', 1)->execute()),
    static fn ($person) => $person->hasField('field_title') && preg_match('/director.*oral history/i', (string) $person->get('field_title')->value),
  );
  if (count($people) === 1) {
    $person = reset($people);
    $log[] = "Section \"Who to ask\": {$person->label()}, {$person->get('field_title')->value} (node {$person->id()}).";
    $sections[] = Paragraph::create([
      'type' => 'staff_contacts',
      'field_section_title' => 'Who to ask',
      'field_tile_eyebrow' => 'Staff',
      'field_tile_lede' => 'Write to the Williams Center about an interview, a project or a partnership.',
      'field_tile_link' => ['uri' => 'internal:/v2/staff?department=oral-history', 'title' => 'All Oral History staff'],
      'field_staff_contacts' => [['entity' => Paragraph::create([
        'type' => 'staff_contact',
        'field_contact_topic' => 'Interviews, projects and partnerships',
        'field_librarian' => ['target_id' => $person->id()],
      ])]],
    ]);
  }
  else {
    $log[] = 'Left out "Who to ask": ' . count($people) . ' published Staff match "Director … Oral History". Add it in the edit form.';
  }
}

// The page. ----------------------------------------------------------------
if ($existing) {
  // The same node, address and history: its sections and the copied fields
  // are replaced, in a new revision (the old page stays in its Revisions).
  $page = $existing;
  foreach ($source->getFields(FALSE) as $name => $items) {
    if (str_starts_with($name, 'field_') && !in_array($name, ['field_scroll_section', 'field_intro', 'field_bg_videos', 'field_bg_poster'], TRUE) && $page->hasField($name)) {
      $page->set($name, $items->getValue());
    }
  }
}
else {
  $page = $source->createDuplicate();
  $page->set('created', \Drupal::time()->getRequestTime());
}
$page->set('title', 'Oral History');
if ($page->hasField('field_intro')) {
  $page->set('field_intro', [
    'value' => '<p>The T. Harry Williams Center for Oral History is the largest and most comprehensive oral history repository in Louisiana, and one of the largest in the South.</p>',
    'format' => $source->get('field_intro')->format ?: 'basic_html',
  ]);
}
$page->set('field_scroll_section', array_map(static fn ($section): array => ['entity' => $section], $sections));
if (!$existing) {
  $page->set('path', ['alias' => $path, 'pathauto' => 0]);
}
$page->set('changed', \Drupal::time()->getRequestTime());
if ($publish || !$existing) {
  $page->setPublished($publish);
}

// The other fields that come with the copy, for a look before saving.
$copied = [];
foreach ($page->getFields(FALSE) as $name => $items) {
  if (str_starts_with($name, 'field_') && !in_array($name, ['field_scroll_section', 'field_intro'], TRUE) && !$items->isEmpty()) {
    $copied[] = $name;
  }
}
$log[] = 'Also copied from ' . $from . ': ' . ($copied ? implode(', ', $copied) : 'no other fields') . '.';
$log[] = ($existing ? "Rebuilt node {$existing->id()}" : 'New page') . ' "Oral History" at ' . $path . ', ' . count($sections) . ' sections, ' . ($page->isPublished() ? 'published' : 'unpublished') . '.';

foreach ($log as $line) {
  echo ($apply ? '' : '[dry run] ') . $line . PHP_EOL;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$page->setNewRevision(TRUE);
$page->setRevisionLogMessage("Oral History homepage, built from $from (palefire_oral_history_page.php).");
$page->save();
echo "Saved \"{$page->label()}\" as node {$page->id()} at $path. Run: drush cr" . PHP_EOL;
