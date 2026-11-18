<?php

/**
 * @file
 * Landing hero: where its background video plays, and its filter.
 *
 *   drush php:script scripts/palefire_hero_video_filter.php
 *   drush php:script scripts/palefire_hero_video_filter.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it reports what it would do and saves nothing.
 *
 * Adds Video (field_hero_video, a list) to the Landing hero (paragraph
 * landing_hero):
 * - empty: Beside. The film in the right-hand column, as the column's
 *   spotlight before the highlights (the landing's default).
 * - faded: Main column, faded. The film behind the title, intro and
 *   search, under an 82% veil of the page color that deepens to the page
 *   color at its foot; the column shows the highlights from the start.
 *   Oral History's look until now (it was fixed to that page).
 * - mono: Main column, black and white. As Faded, the color taken out.
 * - none: Main column, no filter. On dark color themes the film as it is,
 *   only fading into the page at its foot: only for a film already chosen
 *   to be dark enough for white text over it. Light themes keep the veil.
 *
 * The Spotlight is off whenever the video is in the main column (there is
 * no film beside the title for its light to come from); its help text says
 * so.
 *
 * Sets Faded on the heroes of /v2/oralhistory (so it looks as it did) and
 * /v2/special, where their Video is still empty, each in a new revision of
 * its page.
 *
 * Safe to re-run: anything already done is left as it is.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\ParagraphsType;

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

$bundle = 'landing_hero';
$name = 'field_hero_video';
if (!ParagraphsType::load($bundle)) {
  echo "No Landing hero paragraph type ($bundle).\n";
  return;
}

// 1. The field. --------------------------------------------------------------
$existing = FieldStorageConfig::loadByName('paragraph', $name);
if ($existing && $existing->getType() !== 'list_string') {
  throw new \RuntimeException("paragraph.$name already exists as '{$existing->getType()}', not list_string.");
}
if (!$existing) {
  echo "{$prefix}Create storage paragraph.$name\n";
  if ($apply) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'type' => 'list_string',
      // Key => label: ListItemBase makes the value/label pairs itself.
      'settings' => [
        'allowed_values' => [
          'faded' => 'Main column, faded',
          'mono' => 'Main column, black and white',
          'none' => 'Main column, no filter',
        ],
      ],
    ])->save();
  }
}
if (!FieldConfig::loadByName('paragraph', $bundle, $name)) {
  echo "{$prefix}Add $name to paragraph.$bundle\n";
  if ($apply) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => $bundle,
      'label' => 'Video',
      'description' => 'Where the background videos play. Empty: beside, in the right-hand column. Main column: behind the title and search, faded (in color, under the page color), black and white, or with no filter. No filter shows the video as it is on dark color themes, so pick it only for a video dark enough for white text over it; light themes still fade it.',
      'required' => FALSE,
    ])->save();
  }
}
if ($apply) {
  $repo = \Drupal::service('entity_display.repository');
  $form = $repo->getFormDisplay('paragraph', $bundle, 'default');
  if (!$form->getComponent($name)) {
    $form->setComponent($name, [
      'type' => 'options_select',
      'weight' => ($form->getComponent('field_hero_spotlight')['weight'] ?? 50) + 1,
    ])->save();
    echo "Video on the Landing hero form\n";
  }
  // The template reads it: keep it out of the default view display.
  $view = $repo->getViewDisplay('paragraph', $bundle, 'default');
  if ($view->getComponent($name)) {
    $view->removeComponent($name)->save();
  }
}

// The Spotlight (field_hero_spotlight) is off whenever the film is in the
// main column (_landing-features.scss): its help says so.
$spotlight = FieldConfig::loadByName('paragraph', $bundle, 'field_hero_spotlight');
$spotlight_help = 'A warm light from the film’s edge over the title and text, when Video is Beside. Off on its own when the video plays in the main column.';
if ($spotlight && $spotlight->getDescription() !== $spotlight_help) {
  echo "{$prefix}Spotlight help: only when Video is Beside\n";
  if ($apply) {
    $spotlight->setDescription($spotlight_help)->save();
  }
}

// 2. Faded on Oral History and Special Collections. ---------------------------
$storage = \Drupal::entityTypeManager()->getStorage('node');
foreach (['/v2/oralhistory', '/v2/special'] as $alias) {
  $system = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
  $node = preg_match('#^/node/(\d+)$#', $system, $m) ? $storage->load($m[1]) : NULL;
  if (!$node instanceof NodeInterface || !$node->hasField('field_scroll_section')) {
    echo "$alias: no Modular page there; left alone.\n";
    continue;
  }
  $latest = $storage->getLatestRevisionId($node->id());
  if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
    echo "$alias (node/{$node->id()}) has a newer draft than its current revision; left alone. Set its hero's Video by hand.\n";
    continue;
  }
  $hero = NULL;
  foreach ($node->get('field_scroll_section')->referencedEntities() as $section) {
    if ($section->bundle() === $bundle) {
      $hero = $section;
      break;
    }
  }
  if (!$hero) {
    echo "$alias: no Landing hero; left alone.\n";
    continue;
  }
  // The pages are loaded only after the field is made, so a field added
  // in this run is on them. In a dry run it isn't there yet.
  if ($apply && !$hero->hasField($name)) {
    echo "$alias: the hero has no Video field; left alone.\n";
    continue;
  }
  if ($hero->hasField($name) && !$hero->get($name)->isEmpty()) {
    echo "$alias: Video is already '{$hero->get($name)->value}'; left as it is.\n";
    continue;
  }
  echo "{$prefix}$alias (node/{$node->id()}): hero Video set to Main column, faded, in a new revision.\n";
  if ($apply) {
    $hero->set($name, 'faded');
    $hero->setNeedsSave(TRUE);
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Hero video in the main column, faded (palefire_hero_video_filter.php).');
    $node->save();
  }
}

echo $apply ? "Done. Run: drush cr\n" : "Dry run: nothing saved. Run again with apply=1 to save.\n";
