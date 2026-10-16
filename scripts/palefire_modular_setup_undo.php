<?php

/**
 * @file
 * Removes what a partial palefire_modular_setup.php run left behind on a
 * site that uses palefire_modular.
 *
 * On such a site the old script created the paragraph types section_text and
 * section_media and the storages paragraph.field_show_in_toc and
 * paragraph.field_full_page, then stopped at field_section_style. None of
 * them is used there. This script deletes each one only if it is still
 * unused: no paragraphs of the type, no field lists the type as a target,
 * no field uses the storage. Anything in use is reported and kept.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_modular_setup_undo.php
 *   drush cr
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\paragraphs\Entity\ParagraphsType;

if (PHP_SAPI !== 'cli') {
  return;
}

// Fields whose storage the old script owns: they block deleting the storage.
foreach (['field_show_in_toc', 'field_full_page'] as $name) {
  $storage = FieldStorageConfig::loadByName('paragraph', $name);
  if (!$storage) {
    continue;
  }
  if ($storage->getBundles()) {
    echo "Kept paragraph.$name: used by " . implode(', ', array_keys($storage->getBundles())) . PHP_EOL;
    continue;
  }
  $storage->delete();
  echo "Deleted storage paragraph.$name" . PHP_EOL;
}

// Paragraph types that some field still offers as a target.
$targeted = [];
foreach (FieldConfig::loadMultiple() as $field) {
  if (in_array($field->getType(), ['entity_reference_revisions', 'entity_reference'], TRUE)
    && $field->getSetting('target_type') === 'paragraph') {
    $handler = $field->getSetting('handler_settings') ?: [];
    foreach (array_keys($handler['target_bundles'] ?? []) as $bundle) {
      $targeted[$bundle][] = $field->id();
    }
  }
}

foreach (['section_text', 'section_media'] as $id) {
  $type = ParagraphsType::load($id);
  if (!$type) {
    continue;
  }
  $count = (int) \Drupal::entityQuery('paragraph')
    ->accessCheck(FALSE)
    ->condition('type', $id)
    ->count()
    ->execute();
  if ($count || isset($targeted[$id])) {
    echo "Kept paragraph type $id: $count paragraph(s)"
      . (isset($targeted[$id]) ? ', target of ' . implode(', ', $targeted[$id]) : '') . PHP_EOL;
    continue;
  }
  $type->delete();
  echo "Deleted paragraph type $id" . PHP_EOL;
}

echo 'Done. Run: drush cr' . PHP_EOL;
