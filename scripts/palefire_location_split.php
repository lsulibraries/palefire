<?php

/**
 * @file
 * Location pages, one section per part: the room's page as /v2/special is
 * built, so each part is a section of its own, titled, numbered, banded and
 * in the TOC.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_location_split.php
 *   drush php:script themes/custom/palefire/scripts/palefire_location_split.php -- page=0
 *   drush cr
 *
 * Run after palefire_location_setup.php and palefire_location_photos.php.
 *
 * Config:
 * - Paragraph types location_photos ("Location: photos": Photos,
 *   field_location_photos) and location_visit ("Location: hours and
 *   directions": the page's LibCal hours and Google Maps query, no fields of
 *   its own), each with the shared section fields from bento_search (title,
 *   anchor, snap, height, theme, hide from TOC) and an optional Room.
 *   Allowed wherever bento_search is.
 * - The Location section's form drops Booking text and Photos: those now
 *   live on Location: booking and Location: photos. The fields stay (and
 *   Booking link stays: it is the button at the top).
 *
 * Content (skipped with page=0): the page at /v2/special/lecturehall, when
 * its Location section is still the only location section, becomes
 *   Location (the top: title, map, facts)
 *   Location: photos           "The room"            its Photos
 *   Location: hours and dir.   "Hours and directions"
 *   Location: house rules      "House rules"
 *   Location: booking          "Book the Lecture Hall"  its Booking text
 * then any other sections as they were. The moved Photos and Booking text
 * are cleared on the Location section. A new revision, so the old layout
 * can be restored.
 *
 * Safe to re-run: anything that already exists is left as it is.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
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
$make_page = ($args['page'] ?? '1') !== '0';

$source = 'bento_search';
foreach ([$source, 'location_space', 'location_rules', 'location_booking'] as $required) {
  if (!ParagraphsType::load($required)) {
    throw new \RuntimeException("Paragraph type $required not found. Run palefire_location_setup.php first.");
  }
}
$photos_field = FieldConfig::loadByName('paragraph', 'location_space', 'field_location_photos');
if (!$photos_field) {
  throw new \RuntimeException('Location has no Photos field. Run palefire_location_photos.php first.');
}

$repo = \Drupal::service('entity_display.repository');
$source_form = $repo->getFormDisplay('paragraph', $source, 'default');
$source_view = $repo->getViewDisplay('paragraph', $source, 'default');
$space_form = $repo->getFormDisplay('paragraph', 'location_space', 'default');
$shared = ['field_section_title', 'field_section_anchor', 'field_section_snap', 'field_section_height', 'field_section_theme', 'field_section_toc_hide'];

// Copies a field from another bundle (settings, widget), kept out of the
// view display: the templates print it.
$copy = static function (FieldConfig $from, string $bundle, $form, $view, $from_form) {
  $name = $from->getName();
  if (!FieldConfig::loadByName('paragraph', $bundle, $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => $bundle,
      'label' => $from->getLabel(),
      'description' => $from->getDescription(),
      'required' => $from->isRequired(),
      'settings' => $from->getSettings(),
      'default_value' => $from->getDefaultValueLiteral(),
    ])->save();
    echo "Added $name to paragraph.$bundle\n";
  }
  if (($component = $from_form->getComponent($name)) && !$form->getComponent($name)) {
    $form->setComponent($name, $component);
  }
};

// 1. The two new section types. ----------------------------------------------
$types = [
  'location_photos' => ['Location: photos', 'Photos of the room, three across, captioned with each photo\'s name. The first is also the photo behind the page\'s top when the room has none of its own.'],
  'location_visit' => ['Location: hours and directions', 'The page\'s hours (its LibCal location), this week, and links to all hours, Google Maps and parking.'],
];
$room = FieldConfig::loadByName('paragraph', 'location_rules', 'field_location_space');
foreach ($types as $type => [$label, $description]) {
  if (!ParagraphsType::load($type)) {
    ParagraphsType::create(['id' => $type, 'label' => $label, 'description' => $description])->save();
    echo "Created paragraph type $type\n";
  }
  $form = $repo->getFormDisplay('paragraph', $type, 'default');
  $view = $repo->getViewDisplay('paragraph', $type, 'default');
  foreach ($shared as $name) {
    $from = FieldConfig::loadByName('paragraph', $source, $name);
    if (!$from) {
      echo "Skipped $name: bento_search doesn't have it.\n";
      continue;
    }
    $copy($from, $type, $form, $view, $source_form);
    if ($component = $source_view->getComponent($name)) {
      $view->setComponent($name, $component);
    }
    else {
      $view->removeComponent($name);
    }
  }
  if ($room) {
    $copy($room, $type, $form, $view, $repo->getFormDisplay('paragraph', 'location_rules', 'default'));
    $view->removeComponent('field_location_space');
  }
  if ($type === 'location_photos') {
    $copy($photos_field, $type, $form, $view, $space_form);
    $view->removeComponent('field_location_photos');
  }
  $form->save();
  $view->save();
}

// Allow them wherever bento_search is allowed.
foreach (FieldConfig::loadMultiple() as $config) {
  if (!in_array($config->getType(), ['entity_reference_revisions', 'entity_reference'], TRUE)
    || $config->getSetting('target_type') !== 'paragraph') {
    continue;
  }
  $handler = $config->getSetting('handler_settings') ?: [];
  $bundles = $handler['target_bundles'] ?? NULL;
  if (($handler['negate'] ?? FALSE) || !is_array($bundles) || !isset($bundles[$source])) {
    continue;
  }
  $changed = FALSE;
  foreach (array_keys($types) as $type) {
    if (isset($handler['target_bundles'][$type])) {
      continue;
    }
    $handler['target_bundles'][$type] = $type;
    if (isset($handler['target_bundles_drag_drop'])) {
      $handler['target_bundles_drag_drop'][$type] = ['enabled' => TRUE, 'weight' => count($handler['target_bundles_drag_drop'])];
    }
    $changed = TRUE;
  }
  if ($changed) {
    $config->setSetting('handler_settings', $handler)->save();
    echo 'Allowed the new location sections in ' . $config->id() . "\n";
  }
}

// The Location section's form: Booking text and Photos moved out.
$changed = FALSE;
foreach (['field_booking_body', 'field_location_photos'] as $name) {
  if ($space_form->getComponent($name)) {
    $space_form->removeComponent($name);
    $changed = TRUE;
  }
}
if ($changed) {
  $space_form->save();
  echo "Location form: Booking text and Photos now on their own sections\n";
}

if (!$make_page) {
  echo "Config done (page=0: no content). Run: drush cr\n";
  return;
}

// 2. The Lecture Hall page. --------------------------------------------------
$path = \Drupal::service('path_alias.manager')->getPathByAlias('/v2/special/lecturehall');
$node = preg_match('#^/node/(\d+)$#', $path, $m) ? Node::load((int) $m[1]) : NULL;
if (!$node) {
  echo "No page at /v2/special/lecturehall. Done. Run: drush cr\n";
  return;
}
$field = $node->hasField('field_scroll_section') ? 'field_scroll_section' : 'field_sections';
$location = NULL;
$others = [];
foreach ($node->get($field) as $item) {
  $section = $item->entity;
  if (!$section) {
    continue;
  }
  if (in_array($section->bundle(), ['location_photos', 'location_visit', 'location_rules', 'location_booking'], TRUE)) {
    echo "Page node/{$node->id()} is split already. Done. Run: drush cr\n";
    return;
  }
  if ($section->bundle() === 'location_space' && !$location) {
    $location = $section;
    continue;
  }
  $others[] = $section;
}
if (!$location) {
  echo "Page node/{$node->id()} has no Location section. Done.\n";
  return;
}

$room_name = $location->get('field_location_space')->entity ? $location->get('field_location_space')->entity->label() : $node->label();
$sections = [$location];
$titled = static fn (string $type, string $title, array $values = []) => Paragraph::create(['type' => $type, 'field_section_title' => $title] + $values);
if (!$location->get('field_location_photos')->isEmpty()) {
  $sections[] = $titled('location_photos', 'The room', ['field_location_photos' => $location->get('field_location_photos')->getValue()]);
}
$hours = $node->hasField('field_hours_location') && !$node->get('field_hours_location')->isEmpty();
$query = $node->hasField('field_location_query') && !$node->get('field_location_query')->isEmpty();
if ($hours || $query) {
  $sections[] = $titled('location_visit', 'Hours and directions');
}
$sections[] = $titled('location_rules', 'House rules');
$booking = [];
if (!$location->get('field_booking_body')->isEmpty()) {
  $booking['field_booking_body'] = $location->get('field_booking_body')->getValue();
}
$sections[] = $titled('location_booking', 'Book the ' . $room_name, $booking);

$location->set('field_location_photos', []);
$location->set('field_booking_body', NULL);
$location->setNeedsSave(TRUE);
$node->set($field, array_merge($sections, $others));
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('Location page: one section per part, as /v2/special (palefire_location_split.php).');
$node->save();
echo 'Split page node/' . $node->id() . ': ' . count($sections) . " sections (Location, then its parts).\n";
echo "Done. Run: drush cr\n";
