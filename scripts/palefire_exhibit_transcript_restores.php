<?php

/**
 * @file
 * Puts back transcript wording the migration changed.
 *
 *   drush php:script scripts/palefire_exhibit_transcript_restores.php
 *   drush php:script scripts/palefire_exhibit_transcript_restores.php -- apply=1
 *   drush cr
 *
 * A transcript keeps what the speaker said. The migration had tidied 41
 * passages in seven oral history exhibitions: words added (“to”, “the”,
 * “for”), dropped (“still”, “in”, “of”), swapped (“go” → “got”, “kinda” →
 * “kind of”, “ya know” → “you know”) or reordered. Each goes back to the old
 * transcript's words; spelling and punctuation fixes stay. The pairs are in
 * data/transcript-restores.json, and the exhibitions' data files already
 * carry them.
 *
 * Only transcripts change (an Audio block's transcript, or the Text block
 * standing in for a clip whose recording wasn't found). A passage an editor
 * has already changed is listed and left alone. A dry run unless apply=1.
 * Each changed exhibition is saved as a new revision. Safe to re-run.
 */

declare(strict_types=1);

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\node\Entity\Node;

if (PHP_SAPI !== 'cli') {
  return;
}

$args = [];
foreach ((isset($extra) && is_array($extra)) ? $extra : [] as $arg) {
  if (str_contains($arg, '=')) {
    [$key, $value] = explode('=', $arg, 2);
    $args[$key] = $value;
  }
}
$apply = ($args['apply'] ?? '') === '1';

$pairs = json_decode((string) file_get_contents(__DIR__ . '/data/transcript-restores.json'), TRUE, 512, JSON_THROW_ON_ERROR);
$fields = ['field_audio_transcript', 'field_block_body'];
$total = 0;

foreach ($pairs as $name => $list) {
  if (str_starts_with($name, '_')) {
    continue;
  }
  $data = json_decode((string) file_get_contents(__DIR__ . "/data/$name.json"), TRUE, 512, JSON_THROW_ON_ERROR);
  $title = $data['title'];
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

  $fix = function (FieldableEntityInterface $paragraph) use ($fields, $list, &$found, &$count): void {
    foreach ($fields as $field) {
      if (!$paragraph->hasField($field) || $paragraph->get($field)->isEmpty()) {
        continue;
      }
      $value = (string) $paragraph->get($field)->value;
      $new = $value;
      foreach ($list as $i => [$now, $old]) {
        if (str_contains($new, $now)) {
          $new = str_replace($now, $old, $new);
          $found[$i] = TRUE;
          $count++;
          echo "  $now -> $old" . PHP_EOL;
        }
        elseif (str_contains($new, $old)) {
          $found[$i] = TRUE;
        }
      }
      if ($new !== $value) {
        $paragraph->get($field)->value = $new;
        $paragraph->setNeedsSave(TRUE);
      }
    }
  };

  // Depth first; a changed paragraph marks its parents so they save too.
  $descend = function (FieldableEntityInterface $paragraph) use (&$descend, $fix): bool {
    $fix($paragraph);
    foreach (['field_guide_blocks', 'field_gallery_items', 'field_accordion_items'] as $field) {
      if ($paragraph->hasField($field)) {
        foreach ($paragraph->get($field)->referencedEntities() as $child) {
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
  foreach ($list as $i => [$now]) {
    if (empty($found[$i])) {
      echo "  Not found (edited since?): $now" . PHP_EOL;
    }
  }
  echo "  $count passage(s) restored." . PHP_EOL;
  $total += $count;
  if ($changed && $apply) {
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Transcripts: the speakers’ own words restored (palefire_exhibit_transcript_restores.php).');
    $node->save();
    echo '  Saved.' . PHP_EOL;
  }
}

echo "$total passage(s) in all. " . ($apply ? 'Done. Run: drush cr' : 'Dry run: nothing saved. Add -- apply=1 to save.') . PHP_EOL;
