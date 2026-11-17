<?php

/**
 * @file
 * The Special Collections homepage's Study tab: its facts from the room term.
 *
 * The hero's Study tab now prints the Quiet Study room's facts from its term
 * (Taxonomy › Building spaces › Quiet Study: policies, sound, capacity and
 * details lines; _palefire_tab_facts() in palefire.theme), the same data as
 * the room's own page. The facts typed into the tab's text
 * (palefire_section_tabs_study_facts.php) and its "[Editor: seats, Wi-Fi…]"
 * line would repeat them, so this takes that text block out of the tab.
 *
 * From the theme folder, after the theme change is pulled:
 *   drush php:script scripts/palefire_section_tabs_study_room_facts.php
 *   drush php:script scripts/palefire_section_tabs_study_room_facts.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. Optional: page=<alias> (default /v2/special).
 *
 * It removes the block only while its text is still what
 * palefire_section_tabs_study_facts.php wrote (with or without the editor
 * line), spacing aside: text an editor has changed is reported and left as
 * it is. It stops, saving nothing, when the page has a newer draft than its
 * published revision. The removed block stays in the page's earlier
 * revisions, so Revisions › Revert brings it back.
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

$facts = <<<'HTML'
<ul>
<li><strong>Open to all.</strong> LSU or not. No request or appointment.</li>
<li><strong>Quiet.</strong> Keep conversations and calls outside.</li>
<li><strong>No food or drink.</strong> Both are welcome in the first-floor lobby.</li>
</ul>
HTML;
$olds = [
  $facts . "\n" . '<p>[Editor: seats, Wi-Fi, outlets and lighting, once confirmed with Hill staff.]</p>',
  $facts,
];

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

// The Study tab's text block. --------------------------------------------------
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
  echo "No Study tab text block on $alias: the facts come from the room term already. Nothing to do." . PHP_EOL;
  return;
}
[$section, $tab, $block] = $found;
$body = (string) $block->get('field_block_body')->value;
if (!array_filter($olds, static fn (string $old): bool => $same($body, $old))) {
  echo 'The Study tab’s text has been edited since it was set up, so it is left as it is. The room’s facts now print above it from the Quiet Study term; remove anything it repeats by hand.' . PHP_EOL . PHP_EOL . $body . PHP_EOL;
  return;
}

echo ($apply ? '' : '[dry run] ') . 'Study tab: remove the typed facts and the editor line (the facts now come from the Quiet Study room term).' . PHP_EOL;
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$keep = [];
foreach ($tab->get('field_guide_blocks') as $item) {
  if ((int) $item->target_id !== (int) $block->id()) {
    $keep[] = ['target_id' => $item->target_id, 'target_revision_id' => $item->target_revision_id];
  }
}
$tab->set('field_guide_blocks', $keep);
$tab->setNeedsSave(TRUE);
$section->setNeedsSave(TRUE);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('Study tab: facts from the Quiet Study room term, typed text removed (palefire_section_tabs_study_room_facts.php).');
$node->save();
echo "Saved a new revision of \"{$node->label()}\". Run: drush cr" . PHP_EOL;
