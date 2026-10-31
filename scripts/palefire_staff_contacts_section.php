<?php

/**
 * @file
 * Adds "Who to ask" to the Special Collections homepage: topics, each with
 * the person to write to, and a link to the department's staff in the
 * directory (paragraph--staff-contacts.html.twig).
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_staff_contacts_section.php
 *   drush php:script themes/custom/palefire/scripts/palefire_staff_contacts_section.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it lists the person it found for each topic,
 * and saves nothing. Run palefire_staff_setup.php first.
 *
 * Optional arguments after "--":
 *   apply=1         Save.
 *   page=<alias>    The homepage (default /v2/special).
 *   after=<title>   The section to follow (default "Ask Special
 *                   Collections"). Not found, the new one goes before the
 *                   stacks, else last.
 *
 * Each topic's person is the published Staff node whose Job title matches
 * ($topics). A topic with no match, or more than one, is left out and
 * listed: add or change rows in the section's edit form. The section
 * links to /v2/staff?department=special-collections.
 *
 * It stops, saving nothing, when a section titled "Who to ask" is already
 * on the page, or when the homepage has a newer draft than its published
 * revision.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\paragraphs\Entity\Paragraph;

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
$after = $args['after'] ?? 'Ask Special Collections';
$title = 'Who to ask';

// Topic => the Job title (field_title) of the person to write to.
$topics = [
  'Rare books and instruction' => '/curator of books/i',
  'Manuscripts and exhibitions' => '/curator of manuscripts/i',
  'Research visits and reproductions' => '/head of research and public services/i',
  'Oral histories' => '/director.*oral history/i',
  'Events, tours and class visits' => '/outreach.*events/i',
  'Digitization and digital collections' => '/head of digital programs/i',
];

foreach ([['staff_contacts', 'field_staff_contacts'], ['staff_contacts', 'field_tile_eyebrow'], ['staff_contact', 'field_contact_topic'], ['staff_contact', 'field_librarian']] as [$type, $name]) {
  if (!FieldConfig::loadByName('paragraph', $type, $name)) {
    throw new \RuntimeException("paragraph.$type.$name not found. Run palefire_staff_setup.php first.");
  }
}
$log = [];

// The people. ------------------------------------------------------------------
$storage = \Drupal::entityTypeManager()->getStorage('node');
$people = $storage->loadMultiple(\Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'staff')
  ->condition('status', 1)
  ->execute());
$rows = [];
foreach ($topics as $topic => $pattern) {
  $found = array_filter($people, static fn ($person) => $person->hasField('field_title') && preg_match($pattern, (string) $person->get('field_title')->value));
  if (count($found) !== 1) {
    $names = implode(', ', array_map(static fn ($person) => $person->label(), $found));
    $log[] = "Left out \"$topic\": " . ($found ? count($found) . " people match ($names)" : 'no Job title matches') . '. Add it in the edit form.';
    continue;
  }
  $person = reset($found);
  $rows[] = ['topic' => $topic, 'person' => $person];
  $log[] = "\"$topic\": {$person->label()}, {$person->get('field_title')->value} (node {$person->id()}).";
}

// The homepage. ------------------------------------------------------------
$path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
if (!preg_match('#^/node/(\d+)$#', $path, $m)) {
  throw new \RuntimeException("No node found at $alias.");
}
$node = $storage->load($m[1]);
if (!$node || !$node->hasField('field_scroll_section')) {
  throw new \RuntimeException("Node {$m[1]} has no field_scroll_section.");
}
$latest = $storage->getLatestRevisionId($node->id());
if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
  throw new \RuntimeException("$alias has a newer draft (revision $latest) than its published revision. Publish or discard it first.");
}

$sections = $node->get('field_scroll_section')->referencedEntities();
$position = NULL;
foreach ($sections as $delta => $section) {
  $label = $section->hasField('field_section_title') ? (string) $section->get('field_section_title')->value : '';
  if ($label === $title) {
    echo "\"$title\" is already on $alias (section " . ($delta + 1) . '). Nothing to do.' . PHP_EOL;
    return;
  }
  if ($position === NULL && $label === $after) {
    $position = $delta + 1;
  }
}
if ($position === NULL) {
  foreach ($sections as $delta => $section) {
    if ($section->bundle() === 'landing_stacks') {
      $position = $delta;
      break;
    }
  }
  $log[] = "No section titled \"$after\": the new section goes " . ($position === NULL ? 'last' : 'before the stacks') . '.';
}
$position ??= count($sections);
$log[] = "Insert \"$title\" with " . count($rows) . ' rows as section ' . ($position + 1) . ' of ' . (count($sections) + 1) . " on $alias.";

foreach ($log as $line) {
  echo ($apply ? '' : '[dry run] ') . $line . PHP_EOL;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$section = Paragraph::create([
  'type' => 'staff_contacts',
  'field_section_title' => $title,
  'field_tile_eyebrow' => 'Staff',
  'field_tile_lede' => 'Write to the person who looks after what you need. For anything else, ask Special Collections above and we’ll pass it on.',
  'field_tile_link' => ['uri' => 'internal:/v2/staff?department=special-collections', 'title' => 'All Special Collections staff'],
  'field_staff_contacts' => array_map(static fn (array $row) => ['entity' => Paragraph::create([
    'type' => 'staff_contact',
    'field_contact_topic' => $row['topic'],
    'field_librarian' => ['target_id' => $row['person']->id()],
  ])], $rows),
]);

$items = $node->get('field_scroll_section')->getValue();
array_splice($items, $position, 0, [['entity' => $section]]);
$node->set('field_scroll_section', $items);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage("Special Collections: \"$title\" section (palefire_staff_contacts_section.php).");
$node->save();
echo "Saved a new revision of \"{$node->label()}\". Run: drush cr" . PHP_EOL;
