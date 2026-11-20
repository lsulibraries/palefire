<?php

/**
 * @file
 * Exhibitions: object labels for every exhibition's cover picture, shown
 * as the footnote under its canvas in the exhibitions band
 * (includes/exhibit-carousel.html.twig, _palefire_cover_object_label()).
 *
 *   drush php:script scripts/palefire_exhibit_cover_labels.php
 *   drush php:script scripts/palefire_exhibit_cover_labels.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. Each label comes from the exhibition's own
 * caption for the same picture (scripts/data/<exhibition>.json), or, where
 * the picture has no caption (AVES, Georgetown 272), only from what its alt
 * text says. Fills empty fields only (the field_exhibit_cover_* fields from
 * palefire_exhibit_cover_label.php), so an editor's own label stays.
 *
 * One correction: Audubon's cover is the Carolina Parrot (plate 26), but
 * its label said "Mocking Birds and rattlesnake, detail" (plate 21). Its
 * title and medium are replaced only while they still hold those words.
 *
 * Not labeled, for want of a source: Do Not Tear Up My Earth (the globe
 * held in two hands).
 *
 * Stops, saving nothing for that page, on a page with a newer draft.
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
    $args[trim($k)] = trim($v);
  }
}
$apply = ($args['apply'] ?? '') === '1';
$say = fn (string $line) => print(($apply ? '' : '[dry run] ') . $line . PHP_EOL);

$sc = '/v2/special/exhibitions/';
$oh = '/v2/oralhistory/exhibitions/';
$labels = [
  $sc . 'audubon' => [
    'maker' => 'John James Audubon (1785–1851)',
    'title' => 'Carolina Parrot',
    'date' => '1827–1838',
    'medium' => 'Folio edition, plate 26',
  ],
  $sc . 'aves' => [
    'maker' => 'John Gould (1804–1881)',
    'title' => 'Curl-crested Aracari',
    'medium' => 'Hand-colored lithograph. E. A. McIlhenny Natural History Collection',
  ],
  $sc . 'occult' => [
    'maker' => 'Erasmus Francisci (1627–1694)',
    'title' => 'The Sunflower',
    'date' => '1668',
    'medium' => 'Engraving, plate 796, from Lust- und Stats-Garten. Rare G480 .F7 Flat',
  ],
  $sc . 'unionartgallery' => [
    'maker' => 'Janice Sachse',
    'title' => 'Noon in My Garden',
    'medium' => 'From the LSU Union Art Gallery’s retrospective of her work',
  ],
  $oh . 'gu272' => [
    'title' => 'Healy Hall, Georgetown University',
    'medium' => 'Photograph',
  ],
  $oh . 'storm' => [
    'maker' => 'Yoichi R. Okamoto (1915–1985)',
    'title' => 'Hurricane Betsy flooding, seen from Air Force One',
    'date' => '1965',
    'medium' => 'Photograph. LBJ Library',
  ],
  $oh . 'lady' => [
    'title' => 'The Agnes Morris House',
    'medium' => 'Scrapbook photograph. Courtesy of the Human Ecology Department',
  ],
  $oh . 'lsuag' => [
    'title' => 'Groups at Colfax assembled to board the LSU Agriculture Train of Champions',
    'date' => '1949',
    'medium' => 'Photograph. Grant Parish',
  ],
  $oh . 'jf' => [
    'maker' => 'Mary Ann Caffery',
    'title' => 'Memory Map 3',
    'medium' => 'A day in her 2013 Jazz Fest',
  ],
  $oh . 'rpoe' => [
    'title' => 'Members of Alpha Kappa Alpha at LSU',
    'medium' => 'Photograph',
  ],
  $oh . 'football' => [
    'title' => 'The 1944 team in action',
    'date' => '1944',
    'medium' => 'Gumbo, 1944. LD3118 .G8, University Archives',
  ],
  $oh . 'graphicdesign' => [
    'maker' => 'Byron Levy',
    'title' => 'Sketchbook and art',
    'medium' => 'Photographed in July 2008, the day his oral history was taken',
  ],
  $oh . 'klsu' => [
    'title' => 'KLSU antenna',
    'date' => '1984',
    'medium' => 'Gumbo, 1984. LSU Archives, LSU Libraries',
  ],
  $oh . 'mossvilleexhibit' => [
    'title' => 'Map of Mossville',
    'date' => '1932',
    'medium' => 'LSU Cartographic Information Center',
  ],
];
// Wrong values to replace: path => [field key => the old value].
$replace = [
  $sc . 'audubon' => [
    'title' => 'Mocking Birds and rattlesnake, detail',
    'medium' => 'Folio edition, plate 21',
  ],
];

$storage = \Drupal::entityTypeManager()->getStorage('node');
$aliases = \Drupal::service('path_alias.manager');
$saved = 0;
foreach ($labels as $alias => $label) {
  $path = $aliases->getPathByAlias($alias);
  $node = preg_match('#^/node/(\d+)$#', $path, $m) ? $storage->load($m[1]) : NULL;
  if (!$node instanceof NodeInterface) {
    $say("No page at $alias. Skipped.");
    continue;
  }
  if (!$node->hasField('field_exhibit_cover_title')) {
    throw new \RuntimeException('Modular page has no cover label fields. Run scripts/palefire_exhibit_cover_label.php first.');
  }
  $latest = $storage->getLatestRevisionId($node->id());
  if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
    $say("$alias has a newer draft (revision $latest). Skipped: publish or discard it first.");
    continue;
  }
  $changes = [];
  foreach ($label as $key => $value) {
    $field = 'field_exhibit_cover_' . $key;
    if (!$node->hasField($field)) {
      continue;
    }
    $current = trim((string) $node->get($field)->value);
    $old = $replace[$alias][$key] ?? NULL;
    if ($current === '' || ($old !== NULL && $current === $old)) {
      if ($current !== $value) {
        $node->set($field, $value);
        $changes[] = $current === '' ? "$key “$value”" : "$key “$current” → “$value”";
      }
    }
  }
  if (!$changes) {
    $say("$alias: label already filled, left alone.");
    continue;
  }
  $say("$alias: " . implode('; ', $changes) . '.');
  if ($apply) {
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Cover object label (palefire_exhibit_cover_labels.php).');
    $node->save();
    $saved++;
  }
}
$say($apply ? "Saved $saved page(s). Run: drush cr" : 'Nothing saved. Run again with apply=1.');
