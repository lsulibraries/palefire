<?php

/**
 * @file
 * The Special Collections homepage's Study tab: its text as three facts.
 *
 * The tab's panel prints the Quiet Study room's photo over its text, a
 * framed print that links to the room's page (_section-tabs.scss). The
 * hero's first column already says what the room is and the photo carries
 * the link, so the tab's opening sentence and its link go, and the three
 * facts (a row of columns under the photo) lead.
 *
 * From the theme folder:
 *   drush php:script scripts/palefire_section_tabs_study_facts.php
 *   drush php:script scripts/palefire_section_tabs_study_facts.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. Optional: page=<alias> (default /v2/special).
 *
 * It changes the text only while it is still the text that
 * palefire_section_tabs_special.php wrote, or the earlier version of this
 * script's (2026-11-09, the facts with their own link), spacing aside: text
 * an editor has changed is reported and left as it is. It stops, saving nothing, when
 * the page has a newer draft than its published revision.
 */

declare(strict_types=1);

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

$olds = [];
$olds[] = <<<'HTML'
<p>Hill Memorial Library has a Quiet Study room, open to everyone whenever the building is open. No request or appointment needed to study with your own work.</p>
<ul>
<li><strong>Open to all.</strong> LSU or not.</li>
<li><strong>Quiet.</strong> Keep conversations and calls outside.</li>
<li><strong>No food or drink.</strong></li>
</ul>
<p>[Editor: seats, Wi-Fi, outlets and lighting, once confirmed with Hill staff.]</p>
<p><a href="/v2/special/quietstudy">The Quiet Study room: hours and directions</a></p>
HTML;

$facts = <<<'HTML'
<ul>
<li><strong>Open to all.</strong> LSU or not. No request or appointment.</li>
<li><strong>Quiet.</strong> Keep conversations and calls outside.</li>
<li><strong>No food or drink.</strong> Both are welcome in the first-floor lobby.</li>
</ul>
<p>[Editor: seats, Wi-Fi, outlets and lighting, once confirmed with Hill staff.]</p>
HTML;
$olds[] = $facts . "\n" . '<p><a href="/v2/special/quietstudy">Quiet Study hours and directions</a></p>';
$new = $facts;

$same = static fn (string $a, string $b): bool => preg_replace('/\s+/', ' ', trim($a)) === preg_replace('/\s+/', ' ', trim($b));

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

// The Study tab's text. ----------------------------------------------------------
$found = NULL;
foreach ($node->get('field_scroll_section')->referencedEntities() as $section) {
  if ($section->bundle() !== 'section_tabs' || !$section->hasField('field_tabs')) {
    continue;
  }
  foreach ($section->get('field_tabs')->referencedEntities() as $tab) {
    if (!$tab instanceof ParagraphInterface || !$tab->hasField('field_tab_anchor') || $tab->get('field_tab_anchor')->value !== 'study') {
      continue;
    }
    foreach ($tab->get('field_guide_blocks')->referencedEntities() as $block) {
      if ($block->bundle() === 'guide_text' && $block->hasField('field_block_body')) {
        $found = [$section, $tab, $block];
        break 3;
      }
    }
  }
}
if (!$found) {
  echo "No Study tab with a text block on $alias. Nothing to do." . PHP_EOL;
  return;
}
[$section, $tab, $block] = $found;
$body = (string) $block->get('field_block_body')->value;
if ($same($body, $new)) {
  echo 'The Study tab already has the three facts. Nothing to do.' . PHP_EOL;
  return;
}
if (!array_filter($olds, static fn (string $old): bool => $same($body, $old))) {
  echo 'The Study tab\'s text has been edited since it was set up, so it is left as it is. To get the row of facts under the photo, start the text with a list whose items each open with a bold term:' . PHP_EOL . PHP_EOL . $new . PHP_EOL;
  return;
}

echo ($apply ? '' : '[dry run] ') . 'Study tab: the three facts and the editor note only (the hero says what the room is; the photo links to its page).' . PHP_EOL;
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$block->set('field_block_body', ['value' => $new, 'format' => $block->get('field_block_body')->format]);
foreach ([$block, $tab, $section] as $paragraph) {
  $paragraph->setNeedsSave(TRUE);
}
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('Study tab: three facts under the Quiet Study photo (palefire_section_tabs_study_facts.php).');
$node->save();
echo "Saved a new revision of \"{$node->label()}\". Run: drush cr" . PHP_EOL;
