<?php

/**
 * @file
 * Turns the Special Collections homepage's Visit tab into Research, holding
 * the Quick Start Guide's request paths (A, B and C).
 *
 * Run after palefire_section_tabs_special.php. From the Drupal root:
 *   drush php:script themes/custom/palefire/scripts/palefire_section_tabs_research.php
 *   drush php:script themes/custom/palefire/scripts/palefire_section_tabs_research.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it reports what it would do and saves nothing.
 *
 * Optional arguments after "--":
 *   apply=1         Save.
 *   page=<alias>    The homepage (default /v2/special).
 *   format=<name>   Text format for the tab text (default: the text format
 *                   the Visit tab's text uses, else WYSIWYG).
 *
 * What it does:
 * - The Section tabs' Visit tab (anchor "visit", or the label "Visit")
 *   becomes Research: label "Research", anchor "research" (#research),
 *   icon "search". A Research tab already there (this script's first
 *   version, which also copied the intro and Before you visit into it) is
 *   reworked in place.
 * - Its blocks are replaced by one Guide text that is only
 *   "[request-paths]": the paths A, B and C. "Reach out" goes to the Ask us
 *   tab's question form.
 * - The theme draws the page's Section tabs in the hero's Quick Start, in
 *   place of its paths, between the intro and Before you visit
 *   (_palefire_hero_tabs() in palefire.theme), and leaves the section out
 *   of the page below. Nothing to do here for that.
 * - Saves a new revision of the homepage. The old Visit text stays in the
 *   previous revision.
 *
 * It stops, saving nothing, when:
 * - there is no Section tabs section, or neither a Research nor a Visit tab;
 * - the Research tab already holds only the request paths;
 * - the homepage has a newer draft than its published revision.
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
    $args[trim($k)] = trim($v);
  }
}
$apply = ($args['apply'] ?? '') === '1';
$alias = '/' . trim($args['page'] ?? '/v2/special', '/');

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

$tabs = NULL;
foreach ($node->get('field_scroll_section')->referencedEntities() as $section) {
  $tabs ??= $section->bundle() === 'section_tabs' ? $section : NULL;
}
if (!$tabs instanceof ParagraphInterface) {
  throw new \RuntimeException("No Section tabs section on $alias. Run palefire_section_tabs_special.php first.");
}

$value = static fn (ParagraphInterface $p, string $name): string => $p->hasField($name) ? trim((string) $p->get($name)->value) : '';
$visit = NULL;
$research = NULL;
foreach ($tabs->get('field_tabs')->referencedEntities() as $tab) {
  if ($research === NULL && (strcasecmp($value($tab, 'field_tab_label'), 'Research') === 0 || $value($tab, 'field_tab_anchor') === 'research')) {
    $research = $tab;
  }
  if ($visit === NULL && ($value($tab, 'field_tab_anchor') === 'visit' || strcasecmp($value($tab, 'field_tab_label'), 'Visit') === 0)) {
    $visit = $tab;
  }
}
$visit = $research ?? $visit;
if (!$visit instanceof ParagraphInterface) {
  throw new \RuntimeException("No Research or Visit tab in the Section tabs on $alias.");
}
$html = '<p>[request-paths]</p>';
$blocks = $visit->get('field_guide_blocks')->referencedEntities();
if ($research && count($blocks) === 1 && $blocks[0]->bundle() === 'guide_text' && trim((string) $blocks[0]->get('field_block_body')->value) === $html) {
  echo "The Research tab on $alias already holds only the request paths. Nothing to do." . PHP_EOL;
  return;
}

// Text format: the Visit text's, else WYSIWYG. ---------------------------------
$formats = filter_formats();
$format = $args['format'] ?? NULL;
foreach ($format === NULL ? $visit->get('field_guide_blocks')->referencedEntities() : [] as $block) {
  if ($block->bundle() === 'guide_text' && !$block->get('field_block_body')->isEmpty()) {
    $format = $block->get('field_block_body')->format;
    break;
  }
}
foreach ($format === NULL ? $formats : [] as $id => $candidate) {
  if ($id === 'wysiwyg' || strcasecmp((string) $candidate->label(), 'WYSIWYG') === 0) {
    $format = $id;
    break;
  }
}
if ($format === NULL || !isset($formats[$format])) {
  throw new \RuntimeException('No WYSIWYG text format found. Pass format=<machine name>.');
}

$old = count($visit->get('field_guide_blocks')->referencedEntities());
echo ($apply ? '' : '[dry run] ') . "Tab \"{$value($visit, 'field_tab_label')}\" becomes Research (#research, icon search); its $old block(s) are replaced by the request paths (text format $format)." . PHP_EOL;
echo ($apply ? '' : '[dry run] ') . 'The hero draws the tabs in its Quick Start, between the intro and Before you visit.' . PHP_EOL;
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$visit->set('field_tab_label', 'Research');
$visit->set('field_tab_anchor', 'research');
$visit->set('field_tab_icon', 'search');
$visit->set('field_guide_blocks', [Paragraph::create([
  'type' => 'guide_text',
  'field_block_body' => ['value' => $html, 'format' => $format],
])]);
$visit->setNeedsSave(TRUE);
$tabs->setNeedsSave(TRUE);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('Special Collections: Visit tab renamed Research, holding the request paths (palefire_section_tabs_research.php).');
$node->save();
echo "Saved a new revision of \"{$node->label()}\". Run: drush cr" . PHP_EOL;
