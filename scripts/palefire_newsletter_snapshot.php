<?php

/**
 * @file
 * Keeps the site’s own copy of each sent newsletter, so the archive at
 * /v2/newsletters no longer depends on Constant Contact.
 *
 *   drush php:script scripts/palefire_newsletter_snapshot.php
 *   drush php:script scripts/palefire_newsletter_snapshot.php -- apply=1
 *   drush cache:tags palefire_newsletter_review
 *
 * A dry run unless apply=1: it fetches each issue and lists what it would
 * save. For every issue on the archive page’s list with a sent date
 * ("· July 7, 2026"), it fetches the public preview once, downloads each
 * image it shows (img src, background=, CSS url(); Constant Contact’s
 * files, imgssl and ctctcdn hosts and any other) into
 * public://newsletters/<campaign>/, points those images at the copies, and
 * saves the email as issue.html in the same folder.
 * Drupal\palefire\NewsletterReview then shows that copy on the archive and
 * in before and after, and fetches Constant Contact only for an issue
 * without one. Drafts and review pages always fetch live.
 *
 * Only images are copied. Links stay as they are: the preview links to
 * the real addresses, not Constant Contact’s tracking redirects, and the
 * archive shows links as plain text anyway. Links to documents kept on
 * files.constantcontact.com would stop working with the account; they are
 * listed, not changed. Web fonts (fonts.gstatic.com) are left alone. An
 * image that can’t be downloaded keeps its Constant Contact address and is
 * listed; run again to try it again.
 *
 * Saved by drush as fossa with Drupal’s file and folder permissions (0664
 * and 0775 by default), in fossa’s group, www-data, so the web server can
 * read them.
 *
 * Optional arguments after "--":
 *   apply=1           Save.
 *   force=1           Fetch and save again an issue already kept, and its
 *                     images.
 *   issue=<campaign>  Only this issue (the id in its preview address), sent
 *                     or not.
 *   account=<number>  The Constant Contact account, for an issue= not on
 *                     the archive’s list. Default 1114771561534.
 */

declare(strict_types=1);

use Drupal\Core\Cache\Cache;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\palefire\NewsletterReview;

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
$force = ($args['force'] ?? '') === '1';
$only = strtolower($args['issue'] ?? '');
$uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';
if ($only !== '' && !preg_match($uuid, $only)) {
  throw new \RuntimeException('issue= must be a Constant Contact campaign id (8-4-4-4-12 hex).');
}

$listed = NewsletterReview::pastIssues()['issues'];
$issues = [];
foreach ($listed as $issue) {
  if ($only !== '' ? $issue['id'] === $only : !empty($issue['sent'])) {
    $issues[] = $issue;
  }
}
if ($only !== '' && !$issues) {
  $account = $args['account'] ?? '1114771561534';
  if (!preg_match('/^\d{6,15}$/', $account)) {
    throw new \RuntimeException('account= must be digits.');
  }
  $issues[] = ['account' => $account, 'id' => $only, 'name' => $only, 'sent' => NULL];
}
$unsent = $only === '' ? count($listed) - count($issues) : 0;

echo ($apply ? 'Saving' : 'Dry run (apply=1 to save)') . ': ' . count($issues) . ' issue(s)' . ($unsent ? ", $unsent on the list without a sent date skipped (drafts stay live)" : '') . ".\n\n";

$client = \Drupal::httpClient();
$files = \Drupal::service('file_system');
$types = [
  'image/jpeg' => 'jpg',
  'image/png' => 'png',
  'image/gif' => 'gif',
  'image/webp' => 'webp',
];
$totals = ['saved' => 0, 'kept' => 0, 'images' => 0, 'reused' => 0, 'failed' => 0];

foreach ($issues as $issue) {
  $id = $issue['id'];
  $dir = sprintf(NewsletterReview::SNAPSHOT_DIR, $id);
  $target = $dir . '/' . NewsletterReview::SNAPSHOT_FILE;
  $label = $issue['name'] . ($issue['sent'] ? ' (sent ' . $issue['sent'] . ')' : '') . " [$id]";
  if (is_file($target) && !$force) {
    echo "Kept already: $label\n";
    $totals['kept']++;
    continue;
  }

  $preview = sprintf(NewsletterReview::PREVIEW, $issue['account'], $id);
  try {
    $response = $client->get($preview, [
      'timeout' => 20,
      'allow_redirects' => FALSE,
      'headers' => ['Accept' => 'text/html'],
      'http_errors' => FALSE,
    ]);
    $html = (string) $response->getBody();
    $status = $response->getStatusCode();
  }
  catch (\Throwable $e) {
    $html = '';
    $status = 0;
  }
  if ($status !== 200 || stripos($html, '<html') === FALSE || strlen($html) > 2000000) {
    echo "FAILED to fetch: $label (HTTP $status). Nothing saved for it.\n";
    $totals['failed']++;
    continue;
  }

  // Every remote image address as written in the email (entities and all),
  // so each occurrence can be replaced exactly.
  $found = [];
  preg_match_all('#\b(?:src|background)\s*=\s*(["\'])(https?://[^"\'\s>]+)\1#i', $html, $m);
  $found = array_merge($found, $m[2]);
  preg_match_all('#url\(\s*(?:&quot;|["\'])?(https?://[^)"\'\s]+?)(?:&quot;|["\'])?\s*\)#i', $html, $m);
  $found = array_merge($found, $m[1]);
  $found = array_values(array_unique(array_filter($found, fn(string $url): bool => !preg_match('#^https?://fonts\.(?:gstatic|googleapis)\.com/#i', $url))));
  // Longest first, so an address that begins another isn’t replaced inside it.
  usort($found, fn(string $a, string $b): int => strlen($b) <=> strlen($a));

  // Documents kept by Constant Contact: they go with the account.
  preg_match_all('#\bhref\s*=\s*(["\'])(https?://files\.constantcontact\.com/[^"\']+)\1#i', $html, $m);
  $documents = array_values(array_unique($m[2]));

  echo ($force && is_file($target) ? 'Again: ' : 'New: ') . $label . ' — ' . count($found) . " image(s)\n";
  foreach ($documents as $document) {
    echo "  Note: links a file kept on Constant Contact (not copied): $document\n";
  }
  if (!$apply) {
    foreach ($found as $url) {
      echo "  would copy $url\n";
    }
    continue;
  }

  if (!$files->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
    echo "  FAILED: can’t create $dir. Check that fossa can write to the files folder.\n";
    $totals['failed']++;
    continue;
  }

  $copied = 0;
  $missed = [];
  foreach ($found as $url) {
    $real = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $stem = substr(sha1($real), 0, 16);
    $have = glob($files->realpath($dir) . '/' . $stem . '.*') ?: [];
    $local = NULL;
    if ($have && !$force) {
      $local = $dir . '/' . basename($have[0]);
      $totals['reused']++;
    }
    else {
      try {
        $image = $client->get($real, ['timeout' => 20, 'http_errors' => FALSE]);
        $type = strtolower(trim(explode(';', $image->getHeaderLine('Content-Type'))[0]));
        $body = (string) $image->getBody();
        if ($image->getStatusCode() === 200 && isset($types[$type]) && $body !== '' && strlen($body) < 15000000) {
          $local = $files->saveData($body, $dir . '/' . $stem . '.' . $types[$type], FileExists::Replace) ?: NULL;
        }
      }
      catch (\Throwable $e) {
        $local = NULL;
      }
      if ($local) {
        $copied++;
      }
    }
    if ($local) {
      // Stored as public://…, made a web address when shown
      // (NewsletterReview::snapshot()).
      $html = str_replace($url, $local, $html);
    }
    else {
      $missed[] = $url;
    }
  }
  $totals['images'] += $copied;
  $totals['failed'] += count($missed);
  foreach ($missed as $url) {
    echo "  FAILED to copy (kept remote): $url\n";
  }

  if ($files->saveData($html, $target, FileExists::Replace)) {
    $totals['saved']++;
    echo "  Saved $target ($copied image(s) downloaded" . (count($found) - $copied - count($missed) ? ', ' . (count($found) - $copied - count($missed)) . ' already here' : '') . ")\n";
  }
  else {
    echo "  FAILED to save $target\n";
    $totals['failed']++;
  }
}

if ($apply && $totals['saved']) {
  Cache::invalidateTags([NewsletterReview::cacheTag()]);
}

echo "\n" . ($apply
  ? "Issues saved: {$totals['saved']}; already kept: {$totals['kept']}; images downloaded: {$totals['images']}; images reused: {$totals['reused']}; failures: {$totals['failed']}.\n"
  : "Already kept: {$totals['kept']}; failures: {$totals['failed']}. Nothing saved (dry run).\n");
if ($apply && $totals['saved'] && function_exists('posix_getgrgid')) {
  $real = $files->realpath('public://newsletters');
  $group = $real ? (posix_getgrgid((int) filegroup($real))['name'] ?? '?') : '?';
  echo "public://newsletters is in group $group" . ($group === 'www-data' ? ".\n" : " — the web server needs www-data: chgrp -R www-data on it.\n");
}
