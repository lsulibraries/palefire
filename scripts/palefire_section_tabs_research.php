<?php

/**
 * @file
 * Turns the Special Collections homepage's Visit tab into Research, with the
 * Quick Start Guide's content, and takes the Quick Start out of the hero.
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
 *   icon "search".
 * - Its blocks are replaced by one Guide text with the Quick Start Guide's
 *   content: the intro, "Before you visit" (a Heading 4), the Full guide,
 *   Prices and Parking & travel links, and the request paths A, B and C
 *   ("[request-paths]"). The intro, the Before you visit list and the full
 *   guide's URL come from the hero's Quick Guide fields when they are
 *   filled, else the hero's built-in text.
 * - With the paths in a tab, the hero leaves its Quick Start out
 *   (_palefire_tab_paths() in palefire.theme), and "Reach out" goes to the
 *   Ask us tab's question form.
 * - Saves a new revision of the homepage. The old Visit text stays in the
 *   previous revision.
 *
 * It stops, saving nothing, when:
 * - there is no Section tabs section, or no Visit tab, and no Research tab;
 * - a Research tab is already there;
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
$hero = NULL;
foreach ($node->get('field_scroll_section')->referencedEntities() as $section) {
  $tabs ??= $section->bundle() === 'section_tabs' ? $section : NULL;
  $hero ??= $section->bundle() === 'landing_hero' ? $section : NULL;
}
if (!$tabs instanceof ParagraphInterface) {
  throw new \RuntimeException("No Section tabs section on $alias. Run palefire_section_tabs_special.php first.");
}

$value = static fn (ParagraphInterface $p, string $name): string => $p->hasField($name) ? trim((string) $p->get($name)->value) : '';
$visit = NULL;
foreach ($tabs->get('field_tabs')->referencedEntities() as $tab) {
  if (strcasecmp($value($tab, 'field_tab_label'), 'Research') === 0 || $value($tab, 'field_tab_anchor') === 'research') {
    echo "A Research tab is already on $alias. Nothing to do." . PHP_EOL;
    return;
  }
  if ($visit === NULL && ($value($tab, 'field_tab_anchor') === 'visit' || strcasecmp($value($tab, 'field_tab_label'), 'Visit') === 0)) {
    $visit = $tab;
  }
}
if (!$visit instanceof ParagraphInterface) {
  throw new \RuntimeException("No Visit tab in the Section tabs on $alias.");
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

// The Quick Start's content: the hero's fields, else its built-in text
// (includes/landing.html.twig). ---------------------------------------------------
$field = static fn (string $name): string => $hero instanceof ParagraphInterface && $hero->hasField($name) ? trim((string) $hero->get($name)->value) : '';
$intro = $field('field_quick_guide_intro') ?: '<p>Special Collections materials don’t circulate. Choose one of <strong>three ways</strong> to start: see it in the Reading Room (A), order a digital copy (B), or have a librarian help you find what you need first (C).</p>';
$before = $field('field_quick_guide_visit') ?: <<<'HTML'
<ul>
<li>You don’t need to be affiliated with LSU: sign in or register as a Guest.</li>
<li>Appointments aren’t required, but with one your materials are waiting when you arrive.</li>
<li>Staff bring the materials to you in the Reading Room at Hill.</li>
<li>Scans are 50 cents a page, plus a $10 fee per order, emailed in 5–7 business days.</li>
</ul>
HTML;
$guide_url = '/v2/special/guides/special-collections';
$link = $hero instanceof ParagraphInterface && $hero->hasField('field_quick_guide_link') ? $hero->get('field_quick_guide_link')->first() : NULL;
if ($link && !$link->isEmpty()) {
  $guide_url = $link->getUrl()->toString();
}

$html = trim($intro) . "\n<h4>Before you visit</h4>\n" . trim($before) . "\n" . <<<HTML
<ul>
<li><a href="$guide_url">Full guide: How to Use Special Collections</a></li>
<li><a href="/v2/special/guides/duplication">Prices for copies</a></li>
<li><a href="/v2/special/guides/parking-hill-memorial-library">Parking &amp; travel</a></li>
</ul>
<p>[request-paths]</p>
HTML;

$old = count($visit->get('field_guide_blocks')->referencedEntities());
echo ($apply ? '' : '[dry run] ') . "Tab \"{$value($visit, 'field_tab_label')}\" becomes Research (#research, icon search); its $old block(s) are replaced by the Quick Start text and the request paths." . PHP_EOL;
echo ($apply ? '' : '[dry run] ') . 'Intro and Before you visit from ' . ($field('field_quick_guide_intro') || $field('field_quick_guide_visit') ? "the hero's Quick Guide fields" : "the hero's built-in text") . "; text format $format; full guide $guide_url." . PHP_EOL;
echo ($apply ? '' : '[dry run] ') . 'The hero leaves its Quick Start out once the theme sees the paths in a tab.' . PHP_EOL;
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
$node->setRevisionLogMessage('Special Collections: Visit tab renamed Research, with the Quick Start Guide content; Quick Start out of the hero (palefire_section_tabs_research.php).');
$node->save();
echo "Saved a new revision of \"{$node->label()}\". Run: drush cr" . PHP_EOL;
