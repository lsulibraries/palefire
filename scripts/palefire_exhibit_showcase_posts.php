<?php

/**
 * @file
 * Exhibitions showcase: physical exhibitions, from their blog posts.
 *
 *   drush php:script scripts/palefire_exhibit_showcase_posts.php
 *   drush cr
 *
 * A physical exhibition lives as an Article (a blog post about it) in the
 * Exhibitions Article Category. This adds:
 * - On Article:
 *   - field_exhibit_on_view (date range, days): when it is on view. While
 *     today is in the range the post is Featured on the Exhibitions page
 *     by itself, with "On view in <Where> through <end>".
 *   - field_exhibit_where (text): e.g. "Hill Memorial Library".
 *   - field_exhibit_showcase and field_exhibit_feature_note (the same
 *     fields as on exhibitions): Featured, Listed or Not listed by hand.
 * - On Modular page:
 *   - field_exhibit_physical_post (an Article): the post about an online
 *     exhibition's physical show. The exhibition keeps one row, with the
 *     post as a second link, and the post isn't listed again.
 *   - field_showcase_category (an Article Category): the posts a showcase
 *     page lists. Set to "Exhibitions" here on every showcase page where
 *     it is empty.
 *
 * Only adds: existing fields, values and pages are untouched, except the
 * empty category on showcase pages. Safe to re-run. Run
 * palefire_exhibit_showcase.php first.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

if (PHP_SAPI !== 'cli') {
  return;
}

if (!FieldStorageConfig::loadByName('node', 'field_exhibit_showcase')) {
  throw new \RuntimeException('field_exhibit_showcase not found. Run palefire_exhibit_showcase.php first.');
}
$article_category = FieldConfig::loadByName('node', 'article', 'field_article_category');
$vocabularies = $article_category ? ($article_category->getSetting('handler_settings')['target_bundles'] ?? []) : [];
if (!$vocabularies) {
  throw new \RuntimeException('Article has no field_article_category with a vocabulary.');
}
if (!\Drupal::moduleHandler()->moduleExists('datetime_range')) {
  \Drupal::service('module_installer')->install(['datetime_range']);
  echo 'Enabled datetime_range' . PHP_EOL;
}

/** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $displays */
$displays = \Drupal::service('entity_display.repository');

// Storage (if new) and the field on a bundle (if new).
$add = function (string $bundle, string $name, array $storage, array $field): void {
  $existing = FieldStorageConfig::loadByName('node', $name);
  if ($existing && $existing->getType() !== $storage['type']) {
    throw new \RuntimeException("node.$name already exists as '{$existing->getType()}', not '{$storage['type']}'. Delete it in Manage fields and re-run.");
  }
  if (!$existing) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'cardinality' => 1,
    ] + $storage)->save();
    echo "Created storage node.$name" . PHP_EOL;
  }
  if (!FieldConfig::loadByName('node', $bundle, $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'bundle' => $bundle,
      'required' => FALSE,
    ] + $field)->save();
    echo "Created field node.$bundle.$name" . PHP_EOL;
  }
};

// Widgets in order after $after (or last), in its field group if it has
// one; fields already on the form are left where they are. Nothing on the
// view displays: the theme reads them (PalefireHooks::showcase()).
$place = function (string $bundle, string $after, array $widgets) use ($displays): void {
  $form = $displays->getFormDisplay('node', $bundle, 'default');
  $new = array_diff_key($widgets, $form->getComponents());
  if ($new) {
    $components = $form->getComponents();
    $weight = isset($components[$after])
      ? (int) $components[$after]['weight'] + 1
      : max(array_merge([0], array_map(fn ($c) => (int) ($c['weight'] ?? 0), $components))) + 1;
    if (isset($components[$after])) {
      foreach ($components as $other => $component) {
        if ((int) ($component['weight'] ?? 0) >= $weight) {
          $component['weight'] = (int) $component['weight'] + count($new);
          $form->setComponent($other, $component);
        }
      }
    }
    foreach (array_keys($new) as $i => $name) {
      $form->setComponent($name, $new[$name] + ['weight' => $weight + $i, 'settings' => []]);
    }
    foreach ($form->getThirdPartySettings('field_group') as $group_name => $group) {
      $children = $group['children'] ?? [];
      $at = array_search($after, $children, TRUE);
      if ($at !== FALSE) {
        array_splice($children, $at + 1, 0, array_diff(array_keys($new), $children));
        $group['children'] = $children;
        $form->setThirdPartySetting('field_group', $group_name, $group);
      }
    }
    $form->save();
    echo "Added to the $bundle form: " . implode(', ', array_keys($new)) . PHP_EOL;
  }
  foreach (array_keys($displays->getViewModeOptionsByBundle('node', $bundle)) as $mode) {
    $view = $displays->getViewDisplay('node', $bundle, $mode);
    if (!$view->isNew()) {
      foreach (array_keys($widgets) as $name) {
        $view->removeComponent($name);
      }
      $view->save();
    }
  }
};

// 1. Article. ----------------------------------------------------------------

$add('article', 'field_exhibit_on_view', ['type' => 'daterange', 'settings' => ['datetime_type' => 'date']], [
  'label' => 'On view (optional)',
  'description' => 'Physical exhibitions (Exhibitions category): first and last day. While it is on view, the Exhibitions page features it.',
]);
$add('article', 'field_exhibit_where', ['type' => 'string', 'settings' => ['max_length' => 120]], [
  'label' => 'Where (optional)',
  'description' => 'Physical exhibitions: e.g. "Hill Memorial Library".',
]);
$add('article', 'field_exhibit_showcase', ['type' => 'list_string'], [
  'label' => 'Exhibitions showcase',
  'description' => 'Physical exhibitions: how this post appears on the Exhibitions page. Empty means Listed, and Featured while it is on view. Not listed hides it.',
]);
$add('article', 'field_exhibit_feature_note', ['type' => 'string'], [
  'label' => 'Why it is featured (optional)',
  'description' => 'When Featured: one short line shown with the label. Empty while on view: "On view in <Where> through <last day>".',
]);
$place('article', 'field_article_category', [
  'field_exhibit_on_view' => ['type' => 'daterange_default'],
  'field_exhibit_where' => ['type' => 'string_textfield'],
  'field_exhibit_showcase' => ['type' => 'options_select'],
  'field_exhibit_feature_note' => ['type' => 'string_textfield'],
]);

// 2. Modular page. -----------------------------------------------------------

$add('modular_page', 'field_exhibit_physical_post', ['type' => 'entity_reference', 'settings' => ['target_type' => 'node']], [
  'label' => 'Physical exhibition post (optional)',
  'description' => 'Exhibition pages only: the blog post about this exhibition in the building. The Exhibitions page shows it as a second link here and doesn\'t list it again; its On view dates can feature this exhibition.',
  'settings' => [
    'handler' => 'default:node',
    'handler_settings' => ['target_bundles' => ['article' => 'article'], 'auto_create' => FALSE],
  ],
]);
$add('modular_page', 'field_showcase_category', ['type' => 'entity_reference', 'settings' => ['target_type' => 'taxonomy_term']], [
  'label' => 'Exhibition posts category',
  'description' => 'Showcase pages only: the Article Category whose posts (physical exhibitions) are listed with the online exhibitions.',
  'settings' => [
    'handler' => 'default:taxonomy_term',
    'handler_settings' => ['target_bundles' => $vocabularies, 'auto_create' => FALSE],
  ],
]);
$place('modular_page', 'field_exhibit_feature_note', [
  'field_exhibit_physical_post' => ['type' => 'entity_reference_autocomplete'],
  'field_showcase_category' => ['type' => 'options_select'],
]);

// 3. "Exhibitions" on the showcase pages. ------------------------------------

$terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
  'vid' => array_values($vocabularies),
  'name' => 'Exhibitions',
]);
$term = reset($terms);
if (!$term) {
  echo 'No Article Category named "Exhibitions": choose the category on the Exhibitions page by hand.' . PHP_EOL;
}
else {
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $ids = $storage->getQuery()->accessCheck(FALSE)
    ->condition('type', 'modular_page')
    ->condition('field_modular_type', 'showcase')
    ->execute();
  foreach ($storage->loadMultiple($ids) as $page) {
    if ($page->get('field_showcase_category')->isEmpty()) {
      $page->set('field_showcase_category', $term->id());
      $page->setNewRevision(TRUE);
      $page->setRevisionLogMessage('Exhibition posts category: Exhibitions (palefire_exhibit_showcase_posts.php)');
      $page->save();
      echo "Set \"{$page->label()}\" (node/{$page->id()}) to list posts in Exhibitions (term {$term->id()})" . PHP_EOL;
    }
  }
}

echo 'Done. Run drush cr. On a post in Exhibitions, fill On view and Where; on an online exhibition with a physical show, choose its post.' . PHP_EOL;
