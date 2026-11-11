<?php

/**
 * @file
 * Guide sections: a "Wide section" checkbox, so a section holding a chart,
 * map or wide table can use the full width of the page while every other
 * section keeps the reading width. Run palefire_guide_setup.php first.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_wide_setup.php
 *   drush cr
 *
 * Config only: it never changes content. Editors tick the box on a section
 * (Edit > the section > "Wide section").
 *
 * Adds paragraph.guide_section.field_section_wide (boolean, off). When it is
 * on, paragraph--guide-section.html.twig adds .pf-guide--wide and attaches
 * palefire/guide-wide (scss/guide-wide.scss): the section drops the guide's
 * 52rem cap, text blocks keep their reading width, and charts, maps and
 * tables spread out.
 *
 * Safe to re-run: existing fields and display settings are kept.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\paragraphs\Entity\ParagraphsType;

if (PHP_SAPI !== 'cli') {
  return;
}

if (!ParagraphsType::load('guide_section')) {
  throw new \RuntimeException('guide_section not found. Run palefire_guide_setup.php first.');
}

$name = 'field_section_wide';
$existing = FieldStorageConfig::loadByName('paragraph', $name);
if ($existing && $existing->getType() !== 'boolean') {
  throw new \RuntimeException("paragraph.$name already exists as '{$existing->getType()}', not 'boolean'.");
}
if (!$existing) {
  FieldStorageConfig::create([
    'field_name' => $name,
    'entity_type' => 'paragraph',
    'type' => 'boolean',
    'cardinality' => 1,
  ])->save();
  echo "Created storage paragraph.$name" . PHP_EOL;
}

if (!FieldConfig::loadByName('paragraph', 'guide_section', $name)) {
  FieldConfig::create([
    'field_name' => $name,
    'entity_type' => 'paragraph',
    'bundle' => 'guide_section',
    'label' => 'Wide section',
    'description' => 'Let this section use the full width of the page, for a chart, map or wide table. Text in it keeps its usual reading width. Leave it off for sections that are mostly text.',
    'default_value' => [['value' => 0]],
    'settings' => ['on_label' => 'Wide', 'off_label' => 'Reading width'],
  ])->save();
  echo "Created field paragraph.guide_section.$name" . PHP_EOL;
}

// Form: a checkbox after the section's own settings. The template reads the
// field itself, so it never prints as content.
$displays = \Drupal::service('entity_display.repository');
$form = $displays->getFormDisplay('paragraph', 'guide_section', 'default');
if (!$form->getComponent($name)) {
  $weights = array_column($form->getComponents(), 'weight');
  $form->setComponent($name, [
    'type' => 'boolean_checkbox',
    'weight' => $weights ? max($weights) + 1 : 10,
    'settings' => ['display_label' => TRUE],
  ])->save();
  echo "Added the Wide section checkbox to the guide section form" . PHP_EOL;
}
$view = $displays->getViewDisplay('paragraph', 'guide_section', 'default');
if ($view->getComponent($name)) {
  $view->removeComponent($name)->save();
}

echo 'Done. Run: drush cr' . PHP_EOL;
