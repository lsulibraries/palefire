<?php

/**
 * @file
 * Free People of Color in Louisiana: "Show whole image" off on its galleries.
 *
 *   drush php:script scripts/palefire_exhibit_fpoc_lead_whole.php
 *   drush php:script scripts/palefire_exhibit_fpoc_lead_whole.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1.
 *
 * palefire_exhibit_fpoc.php ticked "Show whole image"
 * (field_exhibit_lead_whole) on every gallery with a lead, as a default
 * rather than a choice. On a wall it used to be ignored. Now it frames the
 * lead, whole, over a blurred copy of itself, for a picture too small to
 * fill the screen. These leads are 2,560px to 4,000px masters, so the
 * galleries keep their full-screen leads: the box is unticked on each
 * gallery that has it, unless data/fpoc.json asks for it ("whole": true).
 *
 * Galleries are saved in place, without a new revision. Safe to re-run.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
  return;
}

$args = [];
foreach ((isset($extra) && is_array($extra)) ? $extra : [] as $arg) {
  if (str_contains((string) $arg, '=')) {
    [$k, $v] = explode('=', (string) $arg, 2);
    $args[trim($k)] = trim($v, " \t\"'");
  }
}
$apply = ($args['apply'] ?? '') === '1';

$data = json_decode((string) file_get_contents(__DIR__ . '/data/fpoc.json'), TRUE);
if (!is_array($data) || empty($data['pages'])) {
  throw new \RuntimeException("Couldn't read data/fpoc.json.");
}
$keep = [];
foreach ($data['pages'] as $page) {
  if (!empty($page['whole'])) {
    $keep[(string) $page['anchor']] = TRUE;
  }
}

$ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', $data['title'])
  ->execute();
if (count($ids) !== 1) {
  throw new \RuntimeException('Expected one Modular page titled "' . $data['title'] . '", found ' . count($ids) . '.');
}
$node = \Drupal::entityTypeManager()->getStorage('node')->load(reset($ids));
echo 'Page: ' . $node->label() . ' (node ' . $node->id() . ')' . PHP_EOL;

$changed = 0;
foreach ($node->get('field_scroll_section')->referencedEntities() as $section) {
  if ($section->bundle() !== 'exhibit_page' || !$section->hasField('field_exhibit_lead_whole')) {
    continue;
  }
  $anchor = (string) $section->get('field_section_anchor')->value;
  $whole = (bool) $section->get('field_exhibit_lead_whole')->value;
  if (!$whole) {
    continue;
  }
  if (isset($keep[$anchor])) {
    echo '  ' . $anchor . ': kept (data asks for it)' . PHP_EOL;
    continue;
  }
  echo '  ' . $anchor . ': Show whole image off' . PHP_EOL;
  $changed++;
  if ($apply) {
    $section->set('field_exhibit_lead_whole', 0);
    $section->setNewRevision(FALSE);
    $section->save();
  }
}

echo ($apply ? 'Changed ' : 'Would change ') . $changed . ' galleries.' . PHP_EOL;
if (!$apply && $changed) {
  echo 'Dry run: run again with -- apply=1 to save.' . PHP_EOL;
}
