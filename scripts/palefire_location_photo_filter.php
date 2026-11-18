<?php

/**
 * @file
 * Location pages: a Photo filter choice for the photo behind the top.
 *
 *   drush php:script scripts/palefire_location_photo_filter.php
 *   drush cr
 *
 * Run after palefire_location_setup.php.
 *
 * Adds Photo filter (field_location_photo_filter, a list) to the Location
 * section (paragraph location_space), under Room:
 * - faded: Faded, the default. The photo in color under the swatch’s veil
 *   (dark gray at 87% on dark swatches, the page color at 85% on light).
 * - mono: Black and white. The same veil, the color taken out and the
 *   photo tinted to the swatch.
 * - none: No filter. On dark swatches the photo as it is, with no veil:
 *   only for a photo already chosen to keep the white text readable. Light
 *   swatches keep their veil.
 * Empty is Faded, so pages already made look as they did. The template
 * reads the value (paragraph--location-space.html.twig); the field is not
 * in the view display.
 *
 * Safe to re-run: anything already done is left as it is.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\paragraphs\Entity\ParagraphsType;

if (PHP_SAPI !== 'cli') {
  return;
}

if (!ParagraphsType::load('location_space')) {
  echo "No Location paragraph type (location_space). Run palefire_location_setup.php first.\n";
  return;
}

$name = 'field_location_photo_filter';
if (!FieldStorageConfig::loadByName('paragraph', $name)) {
  FieldStorageConfig::create([
    'field_name' => $name,
    'entity_type' => 'paragraph',
    'type' => 'list_string',
    'settings' => [
      // Key => label: ListItemBase turns these into value/label pairs
      // itself (pairs here were nested twice and refused).
      'allowed_values' => [
        'faded' => 'Faded',
        'mono' => 'Black and white',
        'none' => 'No filter',
      ],
    ],
  ])->save();
  echo "Created storage paragraph.$name\n";
}
if (!FieldConfig::loadByName('paragraph', 'location_space', $name)) {
  FieldConfig::create([
    'field_name' => $name,
    'entity_type' => 'paragraph',
    'bundle' => 'location_space',
    'label' => 'Photo filter',
    'description' => 'How the room’s photo behind the top of the page looks. Faded: in color, darkened (the default). Black and white: darkened, without color. No filter: the photo as it is on dark color themes, so pick it only for a photo dark enough for white text over it; light themes still fade it.',
    'required' => FALSE,
    'default_value' => [['value' => 'faded']],
  ])->save();
  echo "Added $name to paragraph.location_space\n";
}

$repo = \Drupal::service('entity_display.repository');
$form = $repo->getFormDisplay('paragraph', 'location_space', 'default');
if (!$form->getComponent($name)) {
  $form->setComponent($name, [
    'type' => 'options_select',
    'weight' => ($form->getComponent('field_location_space')['weight'] ?? 0) + 1,
  ])->save();
  echo "Photo filter on the Location form\n";
}
// The template reads it: keep it out of the default view display.
$view = $repo->getViewDisplay('paragraph', 'location_space', 'default');
if ($view->getComponent($name)) {
  $view->removeComponent($name)->save();
}

echo "Done. Run: drush cr\n";
