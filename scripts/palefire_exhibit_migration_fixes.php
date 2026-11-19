<?php

/**
 * @file
 * Fixes three mistakes found in the Exhibition Migration Review.
 *
 *   drush php:script scripts/palefire_exhibit_migration_fixes.php
 *   drush php:script scripts/palefire_exhibit_migration_fixes.php -- apply=1
 *   drush cr
 *
 * Mossville History Project:
 * - "Photo. Photo by …" becomes "Photo by …" in 17 clip captions and 4
 *   gallery lead captions. The migration doubled "Photo." wherever the old
 *   caption had two spaces before its credit.
 * - The c48 photo shows Vera Payne, but its caption (also used as the
 *   Family & Community lead caption) named Lenoria Ambrose and her
 *   interviewers. It now reads as the same photo does on c2.
 * - Two Work photos whose caption is only a credit (c55, c43) now name
 *   who is in them: John Bernard, and Delma and Christine Bennett.
 * History of KLSU: "gone,or" becomes "gone, or" in the C38 transcript.
 *
 * data/mossville.json and data/klsu.json carry the same fixes. A text an
 * editor has already changed is left alone, and anything not found is
 * listed. A dry run unless apply=1. Each page is saved as a new revision.
 * Safe to re-run.
 */

declare(strict_types=1);

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\node\Entity\Node;

if (PHP_SAPI !== 'cli') {
  return;
}

$args = [];
foreach ($extra ?? [] as $arg) {
  if (str_contains($arg, '=')) {
    [$key, $value] = explode('=', $arg, 2);
    $args[$key] = $value;
  }
}
$apply = ($args['apply'] ?? '') === '1';

// Per page: [typo, correction, media name or NULL, all matches or one].
// A media name limits the fix to the figure showing that image; such a
// fix runs before the general one so it still finds the old caption.
$pages = [
  'Mossville History Project' => [
    ['<p>Photo. Photo by Kyle Tanglao.</p>', '<p>John Bernard. Photo by Kyle Tanglao.</p>', 'Mossville: 06_john_bernard'],
    ['<p>Photo. Photo by Kyle Tanglao.</p>', '<p>Delma and Christine Bennett. Photo by Kyle Tanglao.</p>', 'Mossville: 04_delma_and_christine_bennett'],
    ['Lenoria Ambrose interviewed by Chelsea Arseneault and Teresha Ussin, 2015. Photo. Photo by Kyle Tanglao.', 'Vera Payne, 2015. Photo by Kyle Tanglao.', NULL],
    ['Photo. Photo by ', 'Photo by ', NULL],
  ],
  'History of KLSU' => [
    ['So the building is gone,or the station?', 'So the building is gone, or the station?', NULL],
  ],
];

$fields = ['field_figure_caption', 'field_exhibit_lead_caption', 'field_audio_transcript', 'field_block_body'];

foreach ($pages as $title => $fixes) {
  $ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
    ->condition('type', 'modular_page')
    ->condition('title', $title)
    ->execute();
  if (count($ids) !== 1) {
    echo "Skipped $title: expected one Modular page with that title, found " . count($ids) . '.' . PHP_EOL;
    continue;
  }
  $node = Node::load(reset($ids));
  $found = [];
  $count = 0;

  $fix = function (FieldableEntityInterface $paragraph) use ($fields, $fixes, &$found, &$count): void {
    $media = NULL;
    if ($paragraph->hasField('field_figure_image') && ($entity = $paragraph->get('field_figure_image')->entity)) {
      $media = $entity->label();
    }
    foreach ($fields as $name) {
      if (!$paragraph->hasField($name) || $paragraph->get($name)->isEmpty()) {
        continue;
      }
      $value = (string) $paragraph->get($name)->value;
      $new = $value;
      foreach ($fixes as $i => [$typo, $correction, $only]) {
        if ($only !== NULL && $only !== $media) {
          continue;
        }
        if (str_contains($new, $typo)) {
          $count += substr_count($new, $typo);
          $new = str_replace($typo, $correction, $new);
          $found[$i] = TRUE;
          echo "  $typo -> $correction" . PHP_EOL;
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

  // Depth first; a changed paragraph marks its parents so they save too.
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

  echo "$title:" . PHP_EOL;
  $changed = FALSE;
  foreach ($node->get('field_scroll_section')->referencedEntities() as $gallery) {
    $changed = $descend($gallery) || $changed;
  }
  foreach ($fixes as $i => [$typo]) {
    if (empty($found[$i])) {
      echo "  Not found (already edited?): $typo" . PHP_EOL;
    }
  }
  echo "  $count correction(s)." . PHP_EOL;
  if ($changed && $apply) {
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Migration fixes (palefire_exhibit_migration_fixes.php).');
    $node->save();
    echo '  Saved.' . PHP_EOL;
  }
}

echo $apply ? 'Done. Run: drush cr' . PHP_EOL : 'Dry run: nothing saved. Add -- apply=1 to save.' . PHP_EOL;
