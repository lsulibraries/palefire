<?php

/**
 * @file
 * Adds the Section tabs to the Special Collections homepage: Visit, Study
 * and Ask us, under the hero, and moves the Ask Special Collections section
 * into the Ask us tab.
 *
 * Run palefire_section_tabs_setup.php first. From the Drupal root:
 *   drush php:script themes/custom/palefire/scripts/palefire_section_tabs_special.php
 *   drush php:script themes/custom/palefire/scripts/palefire_section_tabs_special.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it reports what it would do and saves nothing.
 *
 * Optional arguments after "--":
 *   apply=1         Save.
 *   page=<alias>    The homepage (default /v2/special).
 *   format=<name>   Text format for the tab text (default: the WYSIWYG
 *                   format).
 *   keep-ask=1      Leave the Ask Special Collections section where it is
 *                   (the Ask us tab gets a new Ask showcase instead).
 *
 * What it does:
 * - Adds one section_tabs section after the hero (the first section):
 *   Eyebrow "Before you visit", Title "Plan your time at Hill", and three
 *   tabs:
 *     Visit   (landmark)     the Reading Room steps from How to Use Special
 *                            Collections, and the digital copy option
 *     Study   (book-reader)  the Quiet Study room: open to all, quiet, no
 *                            food or drink; links to /v2/special/quietstudy
 *     Ask us  (comments)     the Ask Special Collections showcase (FAQs
 *                            beside the question form)
 * - Ask us: copies the page's Ask Special Collections section (a Tile
 *   showcase in the Ask layout) into the tab, with its tiles and settings
 *   but without its title (the tab names it), and takes the old section off
 *   the page (the paragraph stays in the old revision). Without that section
 *   it makes a new Ask showcase. keep-ask=1 leaves the old section alone.
 * - Saves a new revision of the homepage.
 *
 * Text marked [Editor: …] is for staff to check before publishing.
 *
 * It stops, saving nothing, when:
 * - a Section tabs section is already on the page;
 * - the homepage has a newer draft than its published revision, which a
 *   save here would bypass.
 */

declare(strict_types=1);

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
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
$alias = '/' . trim($args['page'] ?? '/v2/special', '/');
$keep_ask = ($args['keep-ask'] ?? '') === '1';

foreach (['section_tabs', 'section_tab', 'guide_text', 'tile_showcase'] as $type) {
  if (!ParagraphsType::load($type)) {
    throw new \RuntimeException("Paragraph type $type not found. Run palefire_section_tabs_setup.php (and the guide and tile showcase setups) first.");
  }
}

// Text format: WYSIWYG, as the guides use. --------------------------------------
$formats = filter_formats();
$format = $args['format'] ?? NULL;
foreach ($format === NULL ? $formats : [] as $id => $candidate) {
  if ($id === 'wysiwyg' || strcasecmp((string) $candidate->label(), 'WYSIWYG') === 0) {
    $format = $id;
    break;
  }
}
if ($format === NULL || !isset($formats[$format])) {
  throw new \RuntimeException('No WYSIWYG text format found. Pass format=<machine name>.');
}

// The homepage. ----------------------------------------------------------------
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
$ask = NULL;
$ask_delta = NULL;
foreach ($sections as $delta => $section) {
  if ($section->bundle() === 'section_tabs') {
    echo "A Section tabs section is already on $alias (section " . ($delta + 1) . '). Nothing to do.' . PHP_EOL;
    return;
  }
  if ($ask === NULL && $section->bundle() === 'tile_showcase' && $section->hasField('field_tile_layout') && $section->get('field_tile_layout')->value === 'ask') {
    $ask = $section;
    $ask_delta = $delta;
  }
}

$log = [];

// Visit. ----------------------------------------------------------------------
$visit = <<<'HTML'
<p>Special Collections materials don't circulate: you see them in the Reading Room at Hill Memorial Library, which is open to the LSU community and to researchers from anywhere.</p>
<ol>
<li><strong>Find the item</strong> in the <a href="https://catalog.lib.lsu.edu">library catalog</a>, with the library set to Special Collections.</li>
<li><strong>Request it.</strong> Open the record and choose Request Item at the end of the row you want, under the Available tab. Sign in to your Special Collections account (anyone can register).</li>
<li><strong>Book an appointment.</strong> Not required, but recommended: your items are waiting when you arrive. Check the Reading Room hours before you come.</li>
<li><strong>Read it at Hill.</strong> Staff bring your materials to you in the Reading Room.</li>
</ol>
<p><strong>Need a copy instead?</strong> Choose Request Item, then Switch to Duplication Order Form. Scans are 50 cents a page plus a $10 order fee, emailed in 5 to 7 business days.</p>
<p><a href="/v2/special/guides/special-collections">Read the full guide: How to Use Special Collections</a></p>
HTML;

// Study. ----------------------------------------------------------------------
$study = <<<'HTML'
<p>Hill Memorial Library has a Quiet Study room, open to everyone whenever the building is open. No request or appointment needed to study with your own work.</p>
<ul>
<li><strong>Open to all.</strong> LSU or not.</li>
<li><strong>Quiet.</strong> Keep conversations and calls outside.</li>
<li><strong>No food or drink.</strong></li>
</ul>
<p>[Editor: seats, Wi-Fi, outlets and lighting, once confirmed with Hill staff.]</p>
<p><a href="/v2/special/quietstudy">The Quiet Study room: hours and directions</a></p>
HTML;

$text = static fn (string $html): Paragraph => Paragraph::create([
  'type' => 'guide_text',
  'field_block_body' => ['value' => trim($html), 'format' => $format],
]);

// Ask us: the page's Ask section, copied without its title, or a new one. ------
if ($ask instanceof ParagraphInterface && !$keep_ask) {
  $ask_block = $ask->createDuplicate();
  $log[] = 'Ask us: copy "' . ($ask->hasField('field_section_title') ? $ask->get('field_section_title')->value : 'Ask') . '" (section ' . ($ask_delta + 1) . ') into the tab, then take that section off the page.';
}
else {
  $ask_block = Paragraph::create(['type' => 'tile_showcase', 'field_tile_layout' => 'ask']);
  $log[] = $ask ? 'Ask us: a new Ask showcase (keep-ask=1: the Ask Special Collections section stays).' : 'Ask us: a new Ask showcase (no Ask Special Collections section on the page).';
}
// The tab names it: no second heading, eyebrow, lede or link inside the panel.
foreach (['field_section_title', 'field_tile_eyebrow', 'field_tile_lede', 'field_tile_link'] as $name) {
  if ($ask_block->hasField($name)) {
    $ask_block->set($name, NULL);
  }
}

$tab = static fn (string $label, string $icon, string $anchor, array $blocks): Paragraph => Paragraph::create([
  'type' => 'section_tab',
  'field_tab_label' => $label,
  'field_tab_icon' => $icon,
  'field_tab_anchor' => $anchor,
  'field_guide_blocks' => $blocks,
]);

$values = [
  'type' => 'section_tabs',
  'field_section_title' => 'Plan your time at Hill',
  'field_tabs' => [
    $tab('Visit', 'landmark', 'visit', [$text($visit)]),
    $tab('Study', 'book-reader', 'study', [$text($study)]),
    $tab('Ask us', 'comments', 'ask', [$ask_block]),
  ],
];
$fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('paragraph', 'section_tabs');
if (isset($fields['field_tile_eyebrow'])) {
  $values['field_tile_eyebrow'] = 'Before you visit';
}
if (isset($fields['field_tile_link'])) {
  $values['field_tile_link'] = ['uri' => 'internal:/v2/special/guides/special-collections', 'title' => 'Full guide'];
}

// Place: after the hero (the first section). ------------------------------------
$position = min(1, count($sections));
$first = $sections[0] ?? NULL;
$first_name = $first ? (trim((string) ($first->hasField('field_section_title') ? $first->get('field_section_title')->value : '')) ?: $first->bundle()) : 'nothing';
$log[] = 'Insert "Plan your time at Hill" (Section tabs: Visit, Study, Ask us) as section ' . ($position + 1) . " on $alias, after \"$first_name\".";
$log[] = "Tab text format: $format. The Study tab has an [Editor: …] line to fill in.";

foreach ($log as $line) {
  echo ($apply ? '' : '[dry run] ') . $line . PHP_EOL;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$items = $node->get('field_scroll_section')->getValue();
if ($ask instanceof ParagraphInterface && !$keep_ask) {
  array_splice($items, $ask_delta, 1);
  if ($ask_delta < $position) {
    $position--;
  }
}
array_splice($items, $position, 0, [['entity' => Paragraph::create($values)]]);
$node->set('field_scroll_section', $items);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('Special Collections: Section tabs (Visit, Study, Ask us) under the hero; Ask Special Collections moved into the Ask us tab (palefire_section_tabs_special.php).');
$node->save();
echo "Saved a new revision of \"{$node->label()}\". Run: drush cr" . PHP_EOL;
