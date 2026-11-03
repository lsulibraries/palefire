<?php

/**
 * @file
 * Gives the Special Collections homepage's Study tab the "book-open" icon.
 *
 * From the Drupal root:
 *   drush php:script themes/custom/palefire/scripts/palefire_section_tabs_study_icon.php
 *   drush php:script themes/custom/palefire/scripts/palefire_section_tabs_study_icon.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it reports what it would do and saves nothing.
 *
 * Optional arguments after "--":
 *   apply=1         Save.
 *   page=<alias>    The homepage (default /v2/special).
 *   icon=<name>     Another Font Awesome 5 name (default "book-open").
 *
 * What it does:
 * - The Section tabs' Study tab (anchor "study", or the label "Study") takes
 *   the icon: the solid one, carved into the track while the tab is off, and
 *   the Light one of the same name, printed on the key while it is on
 *   (includes/section-tabs.html.twig). Both must be in
 *   /libraries/fontawesome-pro/svgs/ (solid/ and light/).
 * - Saves a new revision of the homepage.
 *
 * It stops, saving nothing, when:
 * - there is no Section tabs section or no Study tab;
 * - the Study tab already has the icon;
 * - the icon's solid or Light SVG is missing;
 * - the homepage has a newer draft than its published revision.
 */

declare(strict_types=1);

use Drupal\paragraphs\ParagraphInterface;

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
$alias = '/' . trim($args['page'] ?? '/v2/special', '/');
$icon = strtolower($args['icon'] ?? 'book-open');
if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $icon)) {
  throw new \RuntimeException("\"$icon\" is not a Font Awesome icon name (lowercase letters, digits and hyphens).");
}

// Both styles the tab draws. --------------------------------------------------
$svgs = DRUPAL_ROOT . '/libraries/fontawesome-pro/svgs';
foreach (['solid', 'light'] as $style) {
  if (!is_file("$svgs/$style/$icon.svg")) {
    throw new \RuntimeException("No $style/$icon.svg in $svgs.");
  }
}

// The homepage. ----------------------------------------------------------------
$path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
if (!preg_match('#^/node/(\d+)$#', $path, $m)) {
  throw new \RuntimeException("No node found at $alias.");
}
$storage = \Drupal::entityTypeManager()->getStorage('node');
$node = $storage->load($m[1]);
if (!$node || !$node->hasField('field_scroll_section')) {
  throw new \RuntimeException("Node {$m[1]} has no field_scroll_section.");
}
$latest = $storage->getLatestRevisionId($node->id());
if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
  throw new \RuntimeException("$alias has a newer draft (revision $latest) than its published revision. Publish or discard it first.");
}

$tabs = NULL;
foreach ($node->get('field_scroll_section')->referencedEntities() as $section) {
  $tabs ??= $section->bundle() === 'section_tabs' ? $section : NULL;
}
if (!$tabs instanceof ParagraphInterface) {
  throw new \RuntimeException("No Section tabs section on $alias.");
}

$value = static fn (ParagraphInterface $p, string $name): string => $p->hasField($name) ? trim((string) $p->get($name)->value) : '';
$study = NULL;
foreach ($tabs->get('field_tabs')->referencedEntities() as $tab) {
  if ($study === NULL && ($value($tab, 'field_tab_anchor') === 'study' || strcasecmp($value($tab, 'field_tab_label'), 'Study') === 0)) {
    $study = $tab;
  }
}
if (!$study instanceof ParagraphInterface) {
  throw new \RuntimeException("No Study tab in the Section tabs on $alias.");
}
$old = $value($study, 'field_tab_icon');
if ($old === $icon) {
  echo "The Study tab on $alias already has the icon \"$icon\". Nothing to do." . PHP_EOL;
  return;
}

$prefix = $apply ? '' : '[dry run] ';
echo $prefix . "Study: icon \"$old\" becomes \"$icon\"." . PHP_EOL;
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$study->set('field_tab_icon', $icon);
$study->setNeedsSave(TRUE);
$tabs->setNeedsSave(TRUE);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage("Special Collections: Study tab icon $icon (palefire_section_tabs_study_icon.php).");
$node->save();
echo "Saved a new revision of \"{$node->label()}\". Run: drush cr" . PHP_EOL;
