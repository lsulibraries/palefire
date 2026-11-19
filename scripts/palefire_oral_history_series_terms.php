<?php

/**
 * @file
 * Seeds the Oral history series vocabulary with a term for every series.
 *
 * Content editors keep each series' descriptions on its term (Structure >
 * Taxonomy > Oral history series), and Oral History's homepage reads them
 * there first (_palefire_oral_history_series_terms() in palefire.theme). This
 * makes the terms: one per series, named as the series is, with the theme's
 * own descriptions as a start. Its Short summary is the short line (cards,
 * the deck's caption) and its Description the fuller one.
 *
 * Set up first, by hand in Drupal: the vocabulary (machine name
 * oral_history_series), its Short summary field (field_short_summary, Text
 * (plain)) and, for My Content, its Authorized editors field
 * (field_authorized_editors, a reference to users).
 *
 * Usage, from the theme's folder:
 *   drush php:script scripts/palefire_oral_history_series_terms.php
 *   drush php:script scripts/palefire_oral_history_series_terms.php -- apply=1
 *
 * A dry run unless apply=1. A series without a term gets one; a term that
 * exists keeps whatever an editor wrote, and only its empty fields are
 * filled. Optional: editors=<user IDs, comma separated> adds those users to
 * every term's Authorized editors (none removed), so the series show in
 * their My Content.
 */

declare(strict_types=1);

use Drupal\taxonomy\Entity\Term;

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
$prefix = $apply ? '' : '[dry run] ';

$vid = 'oral_history_series';
if (!\Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load($vid)) {
  throw new \RuntimeException("Vocabulary '$vid' not found. Create it first (Structure > Taxonomy > Add vocabulary, machine name $vid).");
}
$fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('taxonomy_term', $vid);
if (!isset($fields['field_short_summary'])) {
  throw new \RuntimeException("Field 'field_short_summary' not found on '$vid'. Add a Text (plain) field named Short summary first.");
}
$has_editors = isset($fields['field_authorized_editors']);

$editors = [];
foreach (array_filter(array_map('trim', explode(',', $args['editors'] ?? ''))) as $uid) {
  if (!ctype_digit($uid) || !\Drupal::entityTypeManager()->getStorage('user')->load((int) $uid)) {
    throw new \RuntimeException("User $uid not found.");
  }
  $editors[] = (int) $uid;
}
if ($editors && !$has_editors) {
  throw new \RuntimeException("Field 'field_authorized_editors' not found on '$vid'. Add it first, or leave out editors=.");
}

// The theme's own descriptions.
if (!function_exists('_palefire_oral_history_series_details')) {
  require_once __DIR__ . '/../palefire.theme';
}
$short = _palefire_oral_history_series_descriptions();
$long = _palefire_oral_history_series_details();

// Every series by the name the records carry (palefire_search's list), else
// the theme's.
$names = class_exists('\Drupal\palefire_search\OralHistorySeries')
  ? array_values(\Drupal\palefire_search\OralHistorySeries::FOLDERS)
  : array_keys($short + $long);
$key = static fn(string $name): string => strtr(trim($name), ["'" => '’']);

$existing = [];
foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid]) as $term) {
  $existing[$key((string) $term->label())] = $term;
}

$made = $filled = $same = 0;
foreach (array_unique($names) as $name) {
  $k = $key($name);
  $term = $existing[$k] ?? NULL;
  $changes = [];
  if (!$term) {
    $term = Term::create(['vid' => $vid, 'name' => $name]);
    $changes[] = 'new';
  }
  if ($term->get('field_short_summary')->isEmpty() && ($short[$k] ?? '') !== '') {
    $term->set('field_short_summary', $short[$k]);
    $changes[] = 'short summary';
  }
  if ($term->get('description')->isEmpty() && ($long[$k] ?? '') !== '') {
    $term->set('description', ['value' => $long[$k], 'format' => 'plain_text']);
    $changes[] = 'description';
  }
  if ($editors) {
    $have = array_map(static fn(array $item): int => (int) $item['target_id'], $term->get('field_authorized_editors')->getValue());
    $add = array_diff($editors, $have);
    if ($add) {
      $term->set('field_authorized_editors', array_map(static fn(int $uid): array => ['target_id' => $uid], array_merge($have, $add)));
      $changes[] = 'editors +' . implode(',', $add);
    }
  }
  if (!$changes) {
    $same++;
    continue;
  }
  in_array('new', $changes, TRUE) ? $made++ : $filled++;
  echo $prefix . $name . ': ' . implode(', ', $changes) . PHP_EOL;
  if ($apply) {
    $term->save();
  }
}

echo $prefix . "$made new, $filled filled in, $same unchanged." . PHP_EOL;
if (!$apply) {
  echo 'Run again with -- apply=1 to save.' . PHP_EOL;
}
