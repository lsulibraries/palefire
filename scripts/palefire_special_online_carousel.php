<?php

/**
 * @file
 * Special Collections' Online carousel: every Special Collections exhibition
 * on Palefire, newest first.
 *
 *   drush php:script scripts/palefire_special_online_carousel.php
 *   drush php:script scripts/palefire_special_online_carousel.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1.
 *
 * The carousel on /v2/special is a Tile showcase (paragraph 1038) whose
 * tiles are all exhibitions, so it shows as the exhibitions band
 * (includes/exhibit-carousel.html.twig) in the order of its tiles. This
 * sets its tiles to the exhibitions below, sorted by Exhibition year
 * (field_exhibit_year), newest first, then by title. Of the Oral History
 * exhibitions only Woven Louisiana History is in it, as it is a Hill
 * Memorial Library show.
 *
 * An address that doesn't resolve to an exhibition page stops the run
 * before anything is saved. The paragraph is saved in place, without a
 * new revision. Safe to re-run.
 */

declare(strict_types=1);

use Drupal\node\NodeInterface;

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

$paragraph_id = 1038;
$paths = [
  '/v2/special/exhibitions/audubon',
  '/v2/special/exhibitions/aves',
  '/v2/special/exhibitions/occult',
  '/v2/special/exhibitions/fpoc',
  '/v2/special/exhibitions/made-in-america',
  '/v2/oralhistory/exhibitions/woven',
  '/v2/special/exhibitions/blue-and-grey',
  '/v2/special/exhibitions/louisiana-for-bibliophiles',
  '/v2/special/exhibitions/natchez',
  '/v2/special/exhibitions/creole',
  '/v2/special/exhibitions/brhistory',
  '/v2/special/exhibitions/british-voices',
  '/v2/special/exhibitions/sugar',
];

$paragraph = \Drupal::entityTypeManager()->getStorage('paragraph')->load($paragraph_id);
if (!$paragraph || !$paragraph->hasField('field_tiles')) {
  throw new \RuntimeException('Paragraph ' . $paragraph_id . ' (the Online carousel) not found, or it has no tiles.');
}
echo 'Carousel: paragraph ' . $paragraph_id . ' (' . $paragraph->bundle() . ')' . PHP_EOL;

echo 'Now:' . PHP_EOL;
foreach ($paragraph->get('field_tiles') as $item) {
  echo '  ' . ($item->entity ? $item->entity->label() . ' (node ' . $item->entity->id() . ')' : '[missing ' . $item->target_id . ']') . PHP_EOL;
}

$aliases = \Drupal::service('path_alias.manager');
$storage = \Drupal::entityTypeManager()->getStorage('node');
$rows = [];
foreach ($paths as $path) {
  $system = $aliases->getPathByAlias($path);
  $node = preg_match('#^/node/(\d+)$#', $system, $m) ? $storage->load($m[1]) : NULL;
  if (!$node instanceof NodeInterface || $node->bundle() !== 'modular_page' || !$node->hasField('field_modular_type') || $node->get('field_modular_type')->value !== 'exhibition') {
    throw new \RuntimeException($path . ' is not an exhibition page (' . $system . '). Nothing saved.');
  }
  $year = $node->hasField('field_exhibit_year') && !$node->get('field_exhibit_year')->isEmpty() ? (int) $node->get('field_exhibit_year')->value : 0;
  $rows[] = ['node' => $node, 'year' => $year, 'title' => $node->label(), 'published' => $node->isPublished()];
}
usort($rows, fn (array $a, array $b): int => [$b['year'], $a['title']] <=> [$a['year'], $b['title']]);

echo 'New (newest first):' . PHP_EOL;
foreach ($rows as $row) {
  echo '  ' . ($row['year'] ?: '----') . '  ' . $row['title'] . ' (node ' . $row['node']->id() . ')' . ($row['published'] ? '' : '  [unpublished: hidden from the carousel until published]') . PHP_EOL;
}

if ($apply) {
  $paragraph->set('field_tiles', array_map(fn (array $row): array => ['target_id' => $row['node']->id()], $rows));
  $paragraph->setNewRevision(FALSE);
  $paragraph->save();
  echo 'Saved ' . count($rows) . ' tiles.' . PHP_EOL;
}
else {
  echo 'Dry run: run again with -- apply=1 to save.' . PHP_EOL;
}
