<?php

/**
 * @file
 * Location pages: the accessible entrance as directions, not a policy mark.
 *
 *   drush php:script scripts/palefire_location_entrance.php
 *   drush cr
 *
 * Run after palefire_building_map_setup.php and palefire_location_setup.php.
 *
 * - A new Building spaces field, Accessible entrance (field_space_entrance,
 *   plain text): where the step-free way in is. Written on the building's
 *   term, so every room in it shows it under Hours and directions; a room
 *   can say its own. Hill Memorial Library gets "To the right of the main
 *   exterior entrance stairs." while its field is empty.
 * - The Step-free entrance policy mark (field_policy_key step_free) comes
 *   off every room, and its term is unpublished, not deleted, so it can be
 *   brought back. Every building has a way in; the page now says which
 *   door.
 *
 * Safe to re-run: anything already done is left as it is.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

if (PHP_SAPI !== 'cli') {
  return;
}

$terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$find = static function (string $vid, string $field, string $value) use ($terms) {
  $ids = $terms->getQuery()->accessCheck(FALSE)
    ->condition('vid', $vid)
    ->condition($field, $value)
    ->range(0, 1)->execute();
  return $ids ? $terms->load(reset($ids)) : NULL;
};

// 1. Accessible entrance. -------------------------------------------------------
if (!FieldStorageConfig::loadByName('taxonomy_term', 'field_space_entrance')) {
  FieldStorageConfig::create([
    'field_name' => 'field_space_entrance',
    'entity_type' => 'taxonomy_term',
    'type' => 'string_long',
  ])->save();
  echo "Created storage taxonomy_term.field_space_entrance\n";
}
if (!FieldConfig::loadByName('taxonomy_term', 'building_space', 'field_space_entrance')) {
  FieldConfig::create([
    'field_name' => 'field_space_entrance',
    'entity_type' => 'taxonomy_term',
    'bundle' => 'building_space',
    'label' => 'Accessible entrance',
    'description' => 'Where the step-free way in is (the door, a ramp, a lift), in a sentence. Write it on the building; rooms show their building\'s unless they say their own. Shown under Hours and directions.',
  ])->save();
  echo "Added field_space_entrance to building_space\n";
}
$repo = \Drupal::service('entity_display.repository');
$form = $repo->getFormDisplay('taxonomy_term', 'building_space', 'default');
if (!$form->getComponent('field_space_entrance')) {
  $form->setComponent('field_space_entrance', [
    'type' => 'string_textarea',
    'weight' => ($form->getComponent('description')['weight'] ?? 0) + 1,
    'settings' => ['rows' => 2, 'placeholder' => 'To the right of the main entrance stairs.'],
  ])->save();
  echo "Accessible entrance on the Building spaces form\n";
}
// The template prints it: keep it out of the default view display.
$view = $repo->getViewDisplay('taxonomy_term', 'building_space', 'default');
if ($view->getComponent('field_space_entrance')) {
  $view->removeComponent('field_space_entrance')->save();
}

$hill = $find('building_space', 'field_map_key', 'hill_memorial');
if (!$hill) {
  echo "No Hill Memorial Library building term (field_map_key hill_memorial); its entrance is left for an editor.\n";
}
elseif (trim((string) $hill->get('field_space_entrance')->value) === '') {
  $hill->set('field_space_entrance', 'To the right of the main exterior entrance stairs.')->save();
  echo "Hill Memorial Library: accessible entrance filled in\n";
}

// 2. The Step-free entrance mark off every room. --------------------------------
$step_free = $find('space_policy', 'field_policy_key', 'step_free');
if ($step_free) {
  $id = (int) $step_free->id();
  $rooms = $terms->getQuery()->accessCheck(FALSE)
    ->condition('vid', 'building_space')
    ->condition('field_space_policies', $id)
    ->execute();
  foreach ($terms->loadMultiple($rooms) as $room) {
    $kept = array_values(array_filter(
      $room->get('field_space_policies')->getValue(),
      static fn (array $item): bool => (int) $item['target_id'] !== $id,
    ));
    $room->set('field_space_policies', $kept)->save();
    echo "{$room->label()}: Step-free entrance mark removed\n";
  }
  if ($step_free->isPublished()) {
    $step_free->setUnpublished()->save();
    echo "Step-free entrance policy unpublished\n";
  }
}

echo "Done. Run: drush cr\n";
