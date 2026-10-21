<?php

/**
 * @file
 * AVES: corrects typos in the existing AVES page.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_aves_typos.php
 *   drush cr
 *
 * Exact replacements in the gallery entries' names and in every text of the
 * page (bodies, captions, bios, lead captions, transcripts): missing spaces
 * and brackets, stray spaces in hyphens and dashes, a doubled word, and a
 * few slips (Vieillot's dates, Weimar, Chubb). data/aves.json carries the
 * same corrections. A text an editor has changed so the typo is gone is
 * left alone; anything not found is listed at the end.
 *
 * Saves the page as a new revision. Safe to re-run.
 */

declare(strict_types=1);

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\node\Entity\Node;

if (PHP_SAPI !== 'cli') {
  return;
}

// [typo, correction]: each typo is unique in the page.
$fixes = [
  // Names (entry titles, and so the TOC and "In this gallery").
  ['Alcide d’Orbigny(1802-1857)', 'Alcide d’Orbigny (1802-1857)'],
  ['John Gould 1804-1881)', 'John Gould (1804-1881)'],
  ['Robert Schomburgk 1804-1865)', 'Robert Schomburgk (1804-1865)'],
  ['William Phelps, Jr.(1902-1988)', 'William Phelps, Jr. (1902-1988)'],
  ['Louis Jean Pierre Vieillot (1610-1644)', 'Louis Jean Pierre Vieillot (1748-1831)'],
  ['Francis L. De LaPorte', 'Francis L. de Laporte'],
  // Texts.
  ['tyrant- flycatchers', 'tyrant-flycatchers'],
  ['fast- growing', 'fast-growing'],
  ['ninety- three', 'ninety-three'],
  ['Markgraf’s <em> Historia naturalis Brasiliae, </em>(Leiden', 'Markgraf’s <em>Historia naturalis Brasiliae</em> (Leiden'],
  ['hummingbirds;and', 'hummingbirds; and'],
  ['rooms,artificial', 'rooms, artificial'],
  ['mammals,and', 'mammals, and'],
  ['setting,and', 'setting, and'],
  ['habitudes.</em><strong>Rio', 'habitudes.</em> <strong>Rio'],
  ['to the base camp,</p>', 'to the base camp.</p>'],
  ['passes rapidly from from blossom', 'passes rapidly from blossom'],
  ['jail– “a putrid', 'jail – “a putrid'],
  ['of the world –that', 'of the world – that'],
  ['and others– who', 'and others – who'],
  ['ornithologists– Ridgway', 'ornithologists – Ridgway'],
  ['perfect mentor– I', 'perfect mentor – I'],
  ['Brasilien</em> (Weimer, 1825-33)', 'Brasilien</em> (Weimar, 1825-33)'],
  ['Brasiliens </em>(Weimer, 1822-1831)', 'Brasiliens</em> (Weimar, 1822-1831)'],
  ['Charles Chubbs’s', 'Charles Chubb’s'],
];

$ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', 'AVES')
  ->execute();
if (count($ids) !== 1) {
  throw new \RuntimeException('Expected one Modular page titled "AVES", found ' . count($ids) . '.');
}
$node = Node::load(reset($ids));

$fields = ['field_block_title', 'field_block_body', 'field_figure_caption', 'field_entry_bio', 'field_exhibit_lead_caption', 'field_audio_transcript'];
$found = [];
$log = [];

$fix = function (FieldableEntityInterface $paragraph) use ($fields, $fixes, &$found, &$log): void {
  foreach ($fields as $name) {
    if (!$paragraph->hasField($name) || $paragraph->get($name)->isEmpty()) {
      continue;
    }
    $value = (string) $paragraph->get($name)->value;
    $new = $value;
    foreach ($fixes as $i => [$typo, $correction]) {
      if (str_contains($new, $typo)) {
        $new = str_replace($typo, $correction, $new);
        $found[$i] = TRUE;
        $log[] = "$typo -> $correction";
      }
      elseif (str_contains($new, $correction)) {
        $found[$i] = TRUE;
      }
    }
    if ($new !== $value) {
      $paragraph->get($name)->value = $new;
      $paragraph->setNeedsSave(TRUE);
    }
  }
};

// Every paragraph under a block list, depth first; a changed paragraph marks
// its parents, so the nested ones are saved (as in
// palefire_exhibit_aves_update.php).
$descend = function (FieldableEntityInterface $paragraph) use (&$descend, $fix): bool {
  $fix($paragraph);
  foreach (['field_guide_blocks', 'field_gallery_items', 'field_accordion_items'] as $name) {
    if ($paragraph->hasField($name)) {
      foreach ($paragraph->get($name)->referencedEntities() as $child) {
        if ($descend($child)) {
          $paragraph->setNeedsSave(TRUE);
        }
      }
    }
  }
  return $paragraph->needsSave();
};

foreach ($node->get('field_scroll_section')->referencedEntities() as $gallery) {
  $descend($gallery);
}

if ($log) {
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage('AVES: typos (palefire_exhibit_aves_typos.php).');
  $node->save();
}
foreach ($log as $line) {
  echo $line . PHP_EOL;
}
foreach ($fixes as $i => [$typo]) {
  if (empty($found[$i])) {
    echo "Not found (already edited?): $typo" . PHP_EOL;
  }
}
echo 'Done: ' . count($log) . ' correction(s). Run: drush cr' . PHP_EOL;
