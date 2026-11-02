<?php

/**
 * @file
 * Location pages: a room's own facts and the person to ask.
 *
 *   drush php:script scripts/palefire_location_details.php
 *   drush cr
 *
 * Run after palefire_location_setup.php and palefire_location_quiet_study.php.
 *
 * - Two new Building spaces fields:
 *   - Details (field_space_details, text, any number): "Label: value",
 *     one per line, shown in the facts row after Access, Capacity and
 *     Sound ("Wi-Fi: eduroam"). A label that is a photo's name puts its
 *     value under that photo instead ("Lecture: 80 seats").
 *   - Contact (field_space_contact, a Staff page): the person to ask,
 *     shown beside the room's button ("Questions? Meredith Contreras").
 * - Lecture Hall (content, while each is empty or as the setup left it):
 *   seats for its three layouts, "xx" until they are counted; capacity
 *   "Under 100" (was 100, shown "~100"), as the policy words it; Meredith
 *   Contreras (mcontreras@lsu.edu) as its contact. Its Booking text loses
 *   the Layouts list (the photos show them, with their seats) and says
 *   "under 100 people". A new revision.
 * - Quiet Study: seats "xx"; Wi-Fi eduroam, Outlets and Lighting "xx",
 *   placeholders for an editor to replace.
 *
 * Safe to re-run: anything already filled in is left as it is.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\TermInterface;

if (PHP_SAPI !== 'cli') {
  return;
}

$repo = \Drupal::service('entity_display.repository');
$form = $repo->getFormDisplay('taxonomy_term', 'building_space', 'default');
$view = $repo->getViewDisplay('taxonomy_term', 'building_space', 'default');
$weight = ($form->getComponent('field_space_capacity')['weight'] ?? $form->getComponent('description')['weight'] ?? 0) + 1;

// 1. Details. -------------------------------------------------------------------
if (!FieldStorageConfig::loadByName('taxonomy_term', 'field_space_details')) {
  FieldStorageConfig::create([
    'field_name' => 'field_space_details',
    'entity_type' => 'taxonomy_term',
    'type' => 'string',
    'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
  ])->save();
  echo "Created storage taxonomy_term.field_space_details\n";
}
if (!FieldConfig::loadByName('taxonomy_term', 'building_space', 'field_space_details')) {
  FieldConfig::create([
    'field_name' => 'field_space_details',
    'entity_type' => 'taxonomy_term',
    'bundle' => 'building_space',
    'label' => 'Details',
    'description' => 'Optional. The room\'s own facts, one per line as "Label: value" (Wi-Fi: eduroam). They follow Access, Capacity and Sound on its page. A label that is the name of one of its photos (Lecture: 80 seats) goes under that photo instead.',
  ])->save();
  echo "Added field_space_details to building_space\n";
}
if (!$form->getComponent('field_space_details')) {
  $form->setComponent('field_space_details', [
    'type' => 'string_textfield',
    'weight' => $weight,
    'settings' => ['size' => 60, 'placeholder' => 'Wi-Fi: eduroam'],
  ])->save();
  echo "Details on the Building spaces form\n";
}

// 2. Contact. -------------------------------------------------------------------
if (!FieldStorageConfig::loadByName('taxonomy_term', 'field_space_contact')) {
  FieldStorageConfig::create([
    'field_name' => 'field_space_contact',
    'entity_type' => 'taxonomy_term',
    'type' => 'entity_reference',
    'settings' => ['target_type' => 'node'],
  ])->save();
  echo "Created storage taxonomy_term.field_space_contact\n";
}
if (!FieldConfig::loadByName('taxonomy_term', 'building_space', 'field_space_contact')) {
  FieldConfig::create([
    'field_name' => 'field_space_contact',
    'entity_type' => 'taxonomy_term',
    'bundle' => 'building_space',
    'label' => 'Contact',
    'description' => 'Optional. The person to ask about this room (their Staff page). Shown beside its button, with their email and phone.',
    'settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['staff' => 'staff'], 'auto_create' => FALSE]],
  ])->save();
  echo "Added field_space_contact to building_space\n";
}
$form = $repo->getFormDisplay('taxonomy_term', 'building_space', 'default');
if (!$form->getComponent('field_space_contact')) {
  $form->setComponent('field_space_contact', [
    'type' => 'entity_reference_autocomplete',
    'weight' => $weight + 1,
    'settings' => ['match_operator' => 'CONTAINS', 'size' => 60, 'placeholder' => ''],
  ])->save();
  echo "Contact on the Building spaces form\n";
}
// The templates print them: keep them out of the default view display.
foreach (['field_space_details', 'field_space_contact'] as $name) {
  if ($view->getComponent($name)) {
    $view->removeComponent($name)->save();
  }
}

// 3. Content. -------------------------------------------------------------------
$page = static function (string $alias): ?Node {
  $path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
  return preg_match('#^/node/(\d+)$#', $path, $m) ? Node::load((int) $m[1]) : NULL;
};
$sections = static function (Node $node): array {
  $field = $node->hasField('field_scroll_section') ? 'field_scroll_section' : 'field_sections';
  $out = [];
  foreach ($node->get($field)->referencedEntities() as $section) {
    $out[$section->bundle()] ??= $section;
  }
  return $out;
};
$fill = static function (TermInterface $room, array $values): bool {
  $changed = FALSE;
  foreach ($values as $field => $value) {
    if ($room->hasField($field) && $room->get($field)->isEmpty()) {
      $room->set($field, $value);
      $changed = TRUE;
    }
  }
  return $changed;
};

// The Lecture Hall.
$hall = $page('/v2/special/lecturehall');
$parts = $hall ? $sections($hall) : [];
$room = isset($parts['location_space']) ? $parts['location_space']->get('field_location_space')->entity : NULL;
if (!$room instanceof TermInterface) {
  echo "No Lecture Hall page or room; its content is left for an editor.\n";
}
else {
  $staff = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'staff', 'field_staff_email' => 'mcontreras@lsu.edu']);
  $values = ['field_space_details' => ['Lecture: xx seats', 'Conference or U-shape: xx seats', 'Classroom: xx seats']];
  if ($staff) {
    $values['field_space_contact'] = ['target_id' => (int) reset($staff)->id()];
  }
  else {
    echo "No Staff page with mcontreras@lsu.edu; the Lecture Hall's Contact is left for an editor.\n";
  }
  $changed = $fill($room, $values);
  if (in_array(trim((string) $room->get('field_space_capacity')->value), ['100', '~100'], TRUE)) {
    $room->set('field_space_capacity', 'Under 100');
    $changed = TRUE;
  }
  if ($changed) {
    $room->save();
    echo "Lecture Hall: seats per layout (xx), capacity Under 100, contact\n";
  }

  // The Booking text: no Layouts list, "under 100 people".
  $booking = $parts['location_booking'] ?? NULL;
  if ($booking && $booking->hasField('field_booking_body') && !$booking->get('field_booking_body')->isEmpty()) {
    $body = (string) $booking->get('field_booking_body')->value;
    $new = preg_replace('#\s*<h3>\s*Layouts\s*</h3>\s*<ul>.*?</ul>#is', '', $body);
    $new = str_replace('fewer than 100 people', 'under 100 people', $new);
    if ($new !== $body) {
      $booking->set('field_booking_body', ['value' => $new, 'format' => $booking->get('field_booking_body')->format]);
      $booking->setNeedsSave(TRUE);
      $hall->setNewRevision(TRUE);
      $hall->setRevisionLogMessage('Lecture Hall: Booking text without the Layouts list, "under 100 people" (palefire_location_details.php).');
      $hall->save();
      echo "Lecture Hall: Booking text updated (node/{$hall->id()})\n";
    }
  }
}

// The Quiet Study.
$study = $page('/v2/special/quietstudy');
$parts = $study ? $sections($study) : [];
$room = isset($parts['location_space']) ? $parts['location_space']->get('field_location_space')->entity : NULL;
if (!$room instanceof TermInterface) {
  echo "No Quiet Study page or room; its details are left for an editor.\n";
}
elseif ($fill($room, [
  'field_space_capacity' => 'xx',
  'field_space_details' => ['Wi-Fi: eduroam', 'Outlets: xx', 'Lighting: xx'],
])) {
  $room->save();
  echo "Quiet Study: seats and details (placeholders)\n";
}

echo "Done. Run: drush cr\n";
