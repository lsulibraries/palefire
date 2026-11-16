<?php

/**
 * @file
 * Refreshes What Endures, the Oral History podcast, from its SoundCloud feed
 * (Drupal\palefire\PodcastFeed).
 *
 *   drush php:script scripts/palefire_podcast_pull.php
 *   drush php:script scripts/palefire_podcast_pull.php -- dry=1
 *
 * Run nightly from the server's crontab, like the research figures. One
 * request to SoundCloud's public podcast feed; no key. The episodes go to
 * Drupal's State and the Oral History homepage's podcast section and hero
 * row redraw; nothing else changes. A failed request keeps the last good
 * pull. A dry run (dry=1) prints what came back without keeping it.
 */

declare(strict_types=1);

use Drupal\palefire\PodcastFeed;

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

$data = PodcastFeed::pull();
$latest = $data['episodes'][0];
$summary = sprintf('%d episodes; newest "%s" (%s).', count($data['episodes']), $latest['title'], date('j M Y', $latest['date']));

if (($args['dry'] ?? '') === '1') {
  print "Dry run, nothing kept. $summary\n";
  foreach ($data['episodes'] as $episode) {
    printf("  %s  %s%s  %d:%02d\n", date('Y-m-d', $episode['date']), $episode['number'] ? 'Ep. ' . $episode['number'] . ' ' : '', $episode['title'], intdiv($episode['length'], 60), $episode['length'] % 60);
  }
  return;
}
PodcastFeed::save($data);
print "Podcast updated: $summary\n";
