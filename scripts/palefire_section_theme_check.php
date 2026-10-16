<?php

/**
 * @file
 * Read-only check: section theme options and one page's section themes.
 *
 * Run on production from the palefire theme folder:
 *   drush php:script scripts/palefire_section_theme_check.php
 *   drush php:script scripts/palefire_section_theme_check.php /special
 *
 * The argument is the page's path alias without the /v2 base path; it
 * defaults to /special. Prints:
 * - the options on paragraph.field_section_theme (light-map must be there);
 * - each section on the page with its saved theme, for the published
 *   revision and, if it differs, the latest (draft) revision.
 * Changes nothing.
 */

use Drupal\field\Entity\FieldStorageConfig;

$storage = FieldStorageConfig::loadByName('paragraph', 'field_section_theme');
if (!$storage) {
  echo 'paragraph.field_section_theme not found.' . PHP_EOL;
  return;
}
$allowed = $storage->getSetting('allowed_values') ?: [];
echo 'Section theme options:' . PHP_EOL;
foreach ($allowed as $key => $label) {
  echo '  ' . $key . ' = ' . $label . PHP_EOL;
}
if (!isset($allowed['light-map'])) {
  echo 'light-map is missing: run scripts/palefire_visit_setup.php, then drush cr.' . PHP_EOL;
}

$alias = isset($extra[0]) && $extra[0] !== '' ? $extra[0] : '/special';
$path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
if (!preg_match('#^/node/(\d+)$#', $path, $m)) {
  echo 'No node found at ' . $alias . '.' . PHP_EOL;
  return;
}
$nodes = \Drupal::entityTypeManager()->getStorage('node');
$node = $nodes->load($m[1]);
if (!$node || !$node->hasField('field_scroll_section')) {
  echo 'Node ' . $m[1] . ' has no field_scroll_section.' . PHP_EOL;
  return;
}

$list = function ($entity, $heading) {
  echo PHP_EOL . $heading . ' (revision ' . $entity->getRevisionId() . '):' . PHP_EOL;
  foreach ($entity->get('field_scroll_section') as $delta => $item) {
    $p = $item->entity;
    if (!$p) {
      continue;
    }
    $title = $p->hasField('field_section_title') && !$p->get('field_section_title')->isEmpty()
      ? $p->get('field_section_title')->value : '(no title)';
    $theme = $p->hasField('field_section_theme') && !$p->get('field_section_theme')->isEmpty()
      ? $p->get('field_section_theme')->value : '(empty, shows as light)';
    echo '  ' . ($delta + 1) . '. ' . $title . ' [' . $p->bundle() . '] theme: ' . $theme . PHP_EOL;
  }
};

echo PHP_EOL . 'Node ' . $node->id() . ': ' . $node->label() . PHP_EOL;
$list($node, 'Published');
$latest = $nodes->getLatestRevisionId($node->id());
if ($latest && $latest != $node->getRevisionId()) {
  $list($nodes->loadRevision($latest), 'Latest revision, not published');
}
