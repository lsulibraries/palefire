<?php

/**
 * @file
 * Refreshes the LSU research figures from OpenAlex (Drupal\palefire\ResearchFigures).
 *
 *   drush php:script scripts/palefire_research_pull.php
 *   drush php:script scripts/palefire_research_pull.php -- dry=1
 *
 * Run nightly from the server's crontab. About ten requests to the OpenAlex
 * API, $0.0001 each, well inside the free daily budget, so no key is needed.
 * If the server's shared budget ever runs out, get a free key at
 * openalex.org/settings/api, put it alone in a file outside the web root
 * and this repo (readable only by the cron user), and pass key=<that path>.
 *
 * The figures go to Drupal's State and the page's parts redraw; nothing else
 * changes. A failed request keeps the last good pull. A dry run (dry=1)
 * prints what came back without keeping it.
 */

declare(strict_types=1);

use Drupal\palefire\ResearchFigures;

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

$key = NULL;
if (!empty($args['key'])) {
  if (!is_readable($args['key'])) {
    throw new \RuntimeException("Can't read the key file {$args['key']}.");
  }
  $key = trim((string) file_get_contents($args['key']));
}

$data = ResearchFigures::pull($key);
$summary = sprintf('%s works since %d, %d years, %d journals, %d funders, %d topics, %d most cited.', number_format($data['total']), ResearchFigures::FROM_YEAR, count($data['years']), count($data['journals']), count($data['funders']), count($data['topics']), count($data['cited']));

if (($args['dry'] ?? '') === '1') {
  print "Dry run, nothing kept. $summary\n";
  print json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
  return;
}
ResearchFigures::save($data);
print "Research figures updated: $summary\n";
