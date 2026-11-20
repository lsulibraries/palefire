<?php

/**
 * @file
 * History of KLSU: each call sign's logo over its gallery's title, and the
 * gallery titles to the years printed on those logos.
 *
 *   drush php:script scripts/palefire_exhibit_klsu_marks.php
 *   drush php:script scripts/palefire_exhibit_klsu_marks.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. Run palefire_exhibit_setup.php first (it adds
 * the Gallery's Mark field, field_exhibit_mark). The logos are redrawn
 * from the old exhibition's icons without their dates, as one-color SVGs
 * in data/klsu-marks; each is copied to public://exhibitions/marks as
 * klsu-<call sign>.svg (reused on a re-run) and set on the gallery with
 * the same anchor (kfgc, wjbo, wlsu, wprg, klsu) when its Mark is empty.
 *
 * Titles (data/klsu.json): KFGC, 1915–1926; WJBO, 1936–1947; WLSU,
 * 1947–1957, 1965–1979; WPRG, 1979–1983; KLSU, 1983–present. A title is
 * changed only while it is still the first draft's, so an editor's own
 * stands.
 *
 * Stops, saving nothing, on a page with a newer draft.
 */

declare(strict_types=1);

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\file\Entity\File;
use Drupal\node\NodeInterface;

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
$say = fn (string $line) => print(($apply ? '' : '[dry run] ') . $line . PHP_EOL);

if (!FieldConfig::loadByName('paragraph', 'exhibit_page', 'field_exhibit_mark')) {
  throw new \RuntimeException('Gallery has no Mark field. Run scripts/palefire_exhibit_setup.php first.');
}
$data = json_decode((string) file_get_contents(__DIR__ . '/data/klsu.json'), TRUE, 512, JSON_THROW_ON_ERROR);
$pages = [];
foreach ($data['pages'] as $page) {
  $pages[$page['anchor']] = $page;
}
// The first draft's titles (85f30f6), replaced while they stand.
$old_titles = [
  'kfgc' => 'KFGC, 1915–1934',
  'wjbo' => 'WJBO, 1934–1947',
  'wlsu' => 'WLSU, 1947–1973',
  'wprg' => 'WPRG, 1979–1982',
  'klsu' => 'KLSU, 1983–2015',
];

$alias = '/v2/oralhistory/exhibitions/klsu';
$storage = \Drupal::entityTypeManager()->getStorage('node');
$path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
$node = preg_match('#^/node/(\d+)$#', $path, $m) ? $storage->load($m[1]) : NULL;
if (!$node instanceof NodeInterface) {
  $say("No page at $alias. Nothing to do.");
  return;
}
$latest = $storage->getLatestRevisionId($node->id());
if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
  $say("$alias has a newer draft (revision $latest). Publish or discard it first.");
  return;
}

/** @var \Drupal\Core\File\FileSystemInterface $fs */
$fs = \Drupal::service('file_system');
$destination = 'public://exhibitions/marks';
// The logo's file entity, copied on first use (apply only).
$mark_file = function (string $relative) use ($fs, $destination, $apply): ?int {
  $source = __DIR__ . '/data/' . $relative;
  if (!is_file($source)) {
    throw new \RuntimeException("Missing $source.");
  }
  $uri = $destination . '/klsu-' . basename($source);
  $existing = \Drupal::entityQuery('file')->accessCheck(FALSE)->condition('uri', $uri)->range(0, 1)->execute();
  if ($existing) {
    return (int) reset($existing);
  }
  if (!$apply) {
    return 0;
  }
  $fs->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  $uri = $fs->copy($source, $uri, FileExists::Replace);
  $file = File::create(['uri' => $uri, 'status' => 1, 'filemime' => 'image/svg+xml']);
  $file->save();
  return (int) $file->id();
};

$changes = 0;
foreach ($node->get('field_scroll_section')->referencedEntities() as $section) {
  $anchor = $section->hasField('field_section_anchor') ? (string) $section->get('field_section_anchor')->value : '';
  if ($section->bundle() !== 'exhibit_page' || !isset($pages[$anchor]['mark'])) {
    continue;
  }
  $page = $pages[$anchor];
  $dirty = FALSE;
  $title = (string) $section->get('field_section_title')->value;
  if ($title === ($old_titles[$anchor] ?? NULL)) {
    $say("$anchor: title “{$title}” → “{$page['title']}”.");
    $section->set('field_section_title', $page['title']);
    $dirty = TRUE;
  }
  elseif ($title !== $page['title']) {
    $say("$anchor: title “{$title}” is an editor’s; left alone.");
  }
  if ($section->get('field_exhibit_mark')->isEmpty()) {
    $say("$anchor: mark klsu-" . basename($page['mark']) . '.');
    $fid = $mark_file($page['mark']);
    if ($fid) {
      $section->set('field_exhibit_mark', ['target_id' => $fid]);
    }
    $dirty = TRUE;
  }
  if ($dirty) {
    $section->setNeedsSave(TRUE);
    $changes++;
  }
}

if (!$changes) {
  $say("$alias: marks and titles already set.");
  return;
}
if ($apply) {
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage('Call-sign logos over the gallery titles; titles to the logos’ years (palefire_exhibit_klsu_marks.php).');
  $node->save();
}
$say($apply ? "Saved $changes gallery(ies) on node/{$node->id()}. Run: drush cr" : "$changes gallery(ies) to change. Nothing saved. Run again with apply=1.");
