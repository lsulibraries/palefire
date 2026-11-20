<?php

/**
 * @file
 * AVES: American spelling in its pictures' alt text ("Colour plate" is
 * "Color plate").
 *
 *   drush php:script scripts/palefire_exhibit_aves_alt_spelling.php
 *   drush php:script scripts/palefire_exhibit_aves_alt_spelling.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it lists each alt text it would change and
 * saves nothing. Only "colour" (Colour, coloured, colours) changes; titles
 * quoted in captions are not touched. A picture that is a media item is
 * saved as a new media revision; one stored on the figure saves the page
 * as a new revision. Safe to re-run.
 */

declare(strict_types=1);

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\media\MediaInterface;
use Drupal\node\Entity\Node;

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

$ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', 'AVES')
  ->execute();
if (count($ids) !== 1) {
  throw new \RuntimeException('Expected one Modular page titled "AVES", found ' . count($ids) . '.');
}
$node = Node::load(reset($ids));
$latest = \Drupal::entityTypeManager()->getStorage('node')->getLatestRevisionId($node->id());
if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
  throw new \RuntimeException("The page has a newer draft (revision $latest). Publish or discard it first.");
}

$fix = fn (string $alt) => (string) preg_replace('/\b([Cc])olour/u', '$1olor', $alt);
$media = [];
$page_changed = FALSE;

// Every figure on the page, however deep: its image's alt, on the media
// item or on the figure itself.
$walk = function (FieldableEntityInterface $entity) use (&$walk, $fix, $say, &$media, &$page_changed): void {
  if ($entity->hasField('field_figure_image') && !$entity->get('field_figure_image')->isEmpty()) {
    $item = $entity->get('field_figure_image')->first();
    $target = $item->entity;
    if ($target instanceof MediaInterface) {
      $source = $target->getSource()->getConfiguration()['source_field'] ?? NULL;
      if ($source && $target->hasField($source) && !$target->get($source)->isEmpty()) {
        $alt = (string) $target->get($source)->alt;
        if ($fix($alt) !== $alt && !isset($media[$target->id()])) {
          $media[$target->id()] = $target;
          $say('Media ' . $target->id() . ': ' . $alt . ' => ' . $fix($alt));
        }
      }
    }
    elseif (isset($item->alt) && $fix((string) $item->alt) !== (string) $item->alt) {
      $say('Figure ' . $entity->id() . ': ' . $item->alt . ' => ' . $fix((string) $item->alt));
      $item->alt = $fix((string) $item->alt);
      if (method_exists($entity, 'setNeedsSave')) {
        $entity->setNeedsSave(TRUE);
      }
      $page_changed = TRUE;
    }
  }
  foreach ($entity->getFieldDefinitions() as $name => $definition) {
    if ($definition->getType() === 'entity_reference_revisions') {
      foreach ($entity->get($name)->referencedEntities() as $child) {
        $walk($child);
      }
    }
  }
};
$walk($node);

if (!$media && !$page_changed) {
  $say('Nothing to change.');
  return;
}
if (!$apply) {
  $say('Nothing saved. Run again with apply=1.');
  return;
}
foreach ($media as $item) {
  $source = $item->getSource()->getConfiguration()['source_field'];
  $item->get($source)->alt = $fix((string) $item->get($source)->alt);
  $item->setNewRevision(TRUE);
  $item->setRevisionLogMessage('AVES: American spelling in alt text (palefire_exhibit_aves_alt_spelling.php).');
  $item->save();
}
if ($page_changed) {
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage('AVES: American spelling in alt text (palefire_exhibit_aves_alt_spelling.php). Restore the previous revision to undo.');
  $node->save();
}
$say('Saved ' . count($media) . ' media item(s)' . ($page_changed ? ' and the page' : '') . '. Run: drush cr');
