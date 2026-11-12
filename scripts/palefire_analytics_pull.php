<?php

/**
 * @file
 * Refreshes the staff analytics report from GA4 (Drupal\palefire\AnalyticsReport).
 *
 *   drush php:script scripts/palefire_analytics_pull.php -- key=/path/to/key.json
 *
 * Run nightly from the server's crontab. The key is the JSON key of a
 * Google Cloud service account that has Viewer access to the GA4 property.
 * It stays on the server, outside the web root and out of this repo,
 * readable only by the user cron runs as. The figures go to Drupal's State
 * and the report page redraws; nothing else changes.
 *
 * A dry run (dry=1) prints what came back without keeping it.
 */

declare(strict_types=1);

use Drupal\palefire\AnalyticsReport;

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
if (empty($args['key'])) {
  throw new \RuntimeException('Pass key=<path to the service account JSON key>.');
}

$data = AnalyticsReport::pull($args['key']);
$views = 0;
foreach ($data['sections'] as $ranges) {
  $views += (int) ($ranges[0]['views'] ?? 0);
}
$summary = sprintf('%s new-site page views, %d searches (%d found nothing), %d results opened, %d controls, %d weeks.', number_format($views), $data['searches'][0], $data['empty_total'], $data['opened_total'], count($data['controls']), count($data['weeks']));

if (($args['dry'] ?? '') === '1') {
  print "Dry run, nothing kept. $summary\n";
  print json_encode(array_diff_key($data, ['terms' => 1, 'empty' => 1]), JSON_PRETTY_PRINT) . "\n";
  return;
}
AnalyticsReport::save($data);
print "Analytics report updated: $summary\n";
