<?php

/**
 * @file
 * Moves the oral history exhibitions already built to
 * /v2/oralhistory/exhibitions/<folder>, the old site's folder name
 * lowercased (jf, gu272, storm...), from /v2/oralhistory/exhibitions/<name>,
 * /v2/special/exhibitions/<name> or /v2/exhibitions/<name>.
 *
 *   drush php:script scripts/palefire_oral_history_exhibit_paths.php
 *   drush php:script scripts/palefire_oral_history_exhibit_paths.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. Only the URL alias changes (the node is not
 * saved, so no new revision). With the Redirect module on, each old
 * address gets a 301 to the page, so old links keep working. An
 * exhibition not built yet is skipped: its build script's usage line
 * already gives the new path=.
 */

declare(strict_types=1);

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

// The name each was built under so far => its address, the old site's
// folder (lib.lsu.edu/sites/all/files/oralhistory/presentations/<folder>),
// lowercased.
$names = [
  'mossville' => 'mossvilleexhibit',
  'do-not-tear-up-my-earth' => 'dntume',
  'georgetown-272' => 'gu272',
  'graphic-design' => 'graphicdesign',
  'integrating-lsu' => 'rpoe',
  'jazzfest' => 'jf',
  'klsu' => 'klsu',
  'lady-in-the-south' => 'lady',
  'tempests' => 'storm',
  'tiger-stadium' => 'football',
  'cooperative-extension' => 'lsuag',
];
$old_bases = ['/v2/oralhistory/exhibitions/', '/v2/special/exhibitions/', '/v2/exhibitions/'];
$new_base = '/v2/oralhistory/exhibitions/';

$aliases = \Drupal::entityTypeManager()->getStorage('path_alias');
$redirects = \Drupal::moduleHandler()->moduleExists('redirect') ? \Drupal::entityTypeManager()->getStorage('redirect') : NULL;
$prefix = $apply ? '' : '[dry run] ';

foreach ($names as $name => $folder) {
  $new = $new_base . $folder;
  if ($aliases->loadByProperties(['alias' => $new])) {
    echo "{$prefix}$name: already at $new." . PHP_EOL;
    continue;
  }
  $found = NULL;
  foreach ($old_bases as $base) {
    if ($matches = $aliases->loadByProperties(['alias' => $base . $name])) {
      $found = reset($matches);
      break;
    }
  }
  if (!$found) {
    echo "{$prefix}$name: not built yet, skipped." . PHP_EOL;
    continue;
  }
  $old = $found->getAlias();
  $system = $found->getPath();
  echo "{$prefix}$name: $old → $new ($system)" . ($redirects ? ', with a 301 from the old address' : ', no redirect (Redirect module off)') . '.' . PHP_EOL;
  if (!$apply) {
    continue;
  }
  $found->setAlias($new);
  $found->save();
  if ($redirects && !$redirects->loadByProperties(['redirect_source__path' => ltrim($old, '/')])) {
    $redirects->create([
      'redirect_source' => ['path' => ltrim($old, '/'), 'query' => []],
      'redirect_redirect' => ['uri' => 'internal:' . $system],
      'status_code' => 301,
      'language' => $found->language()->getId(),
    ])->save();
  }
}

if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
}
