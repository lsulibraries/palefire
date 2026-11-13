<?php

/**
 * @file
 * "LSU Libraries Newsletter": the public archive of the email newsletter. A
 * shelf of past issues' front pages, each opened on the page, and a before/after slider
 * for the newest redesign. A Modular page in guide mode at /v2/newsletters,
 * saved UNPUBLISHED: the slider shows the Fall 2026 issue, so publish the
 * page once that issue is sent. Run palefire_guide_setup.php first;
 * palefire_guide_wide_setup.php gives the gallery and slider the full width.
 *
 *   drush php:script scripts/palefire_newsletter_archive.php
 *   drush php:script scripts/palefire_newsletter_archive.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1.
 *
 * The issues are links in ordinary text blocks, so editors keep the page
 * current in Drupal:
 * - Past issues: a block holding "[newsletter-archive]" and a list, newest
 *   first. Each item is a link to the issue's Constant Contact preview
 *   (campaignlp.constantcontact.com/em/<account>/<id>, the address behind
 *   "View This Email in Your Browser") with the issue's name as its words,
 *   then a colon, its line, and " · " and the day it was sent
 *   (<a>July 2026</a>: The Awards Issue · July 7, 2026). To add an issue
 *   once sent, add a list item at the top. Its front page is a capture kept
 *   in the theme, images/newsletters/<id>.webp (README, Newsletter front pages);
 *   until it is added the issue shows a cover set in type
 *   (Drupal\palefire\NewsletterReview::archive()).
 * - The new look: a block holding "[newsletter-compare]" and two such links,
 *   before then after.
 *
 * Issues as of 2026-11-13, from the campaigns sent in Constant Contact: the
 * five newsletters (giving, Audubon Day and holiday emails left out).
 *
 * This script owns the page's words until it is edited in Drupal: a run
 * replaces its sections in a new revision and stops if the page was saved
 * outside it (force=1 overrides). Publishing state and alias stay as they
 * are on a rebuild.
 *
 * Optional arguments after "--": apply=1, force=1, path=<alias> (default
 * /v2/newsletters), format=<text format>.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;
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
$force = ($args['force'] ?? '') === '1';

foreach (['guide_section', 'guide_text'] as $id) {
  if (!\Drupal::entityTypeManager()->getStorage('paragraphs_type')->load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_guide_setup.php first.");
  }
}

$title = 'LSU Libraries Newsletter';
$path = '/' . ltrim($args['path'] ?? '/v2/newsletters', '/');

$formats = filter_formats();
$format = $args['format'] ?? NULL;
foreach ($format === NULL ? $formats : [] as $id => $candidate) {
  if ($id === 'wysiwyg' || strcasecmp((string) $candidate->label(), 'WYSIWYG') === 0) {
    $format = $id;
    break;
  }
}
if ($format === NULL || !isset($formats[$format])) {
  throw new \RuntimeException('No WYSIWYG text format found. Pass format=<machine name>.');
}

$preview = fn (string $id) => 'https://campaignlp.constantcontact.com/em/1114771561534/' . $id;
$issue = fn (string $id, string $name, string $line, string $sent = '') => '<li><a href="' . $preview($id) . '">' . $name . '</a>: ' . $line . ($sent !== '' ? ' · ' . $sent : '') . '</li>';

$intro = '<p>News from LSU Libraries, a few times a year: new collections and acquisitions, research and teaching, events and the people behind them.</p>';

$sections = [
  [
    'title' => 'The new look',
    'wide' => TRUE,
    'blocks' => [
      [NULL, '<p>With the Fall 2026 issue the newsletter takes on LSU’s brand colors: a Night Game purple band for each story, gold rules between them, and section headings and buttons in purple. Slide to compare it with the issue before.</p>'
        . '<p>[newsletter-compare]</p>'
        . '<ul>'
        . $issue('b6748903-9540-4a50-ade8-a48cab890ee6', 'July 2026', 'The Awards Issue')
        . $issue('9e92c0f7-dc6b-4701-b751-8731705cddc1', 'Fall 2026', 'Faculty authors, fall highlights and upcoming events')
        . '</ul>'],
    ],
  ],
  [
    'title' => 'Past issues',
    'wide' => TRUE,
    'blocks' => [
      [NULL, '<p>[newsletter-archive]</p>'
        . '<ul>'
        . $issue('b6748903-9540-4a50-ade8-a48cab890ee6', 'July 2026', 'The Awards Issue', 'July 7, 2026')
        . $issue('70df0842-0b91-419d-b694-dd5ce5818670', 'April 2026', 'A rare first edition of Audubon’s Quadrupeds of North America', 'April 8, 2026')
        . $issue('15ea6191-b5f5-496e-85ed-bc8296c2cb03', 'January 2026', '50 Years of Book Bazaars', 'February 2, 2026')
        . $issue('2b549e7a-fcc8-4a17-95d2-014a39d37ba8', 'October 2025', 'Public Pathogens: Louisiana’s historical struggle with disease', 'October 8, 2025')
        . $issue('99cd96c7-42d1-4a07-b301-a32428303f22', 'August 2025 Special Edition', 'Enhancing LSU research and scholarship, and a student guide to the fall semester', 'August 27, 2025')
        . '</ul>'],
    ],
  ],
];

$fingerprint = substr(sha1(json_encode([$intro, $sections], JSON_THROW_ON_ERROR)), 0, 12);
$log_prefix = 'palefire_newsletter_archive.php build';
$log_message = "$log_prefix $fingerprint";
$prefix = $apply ? '' : '[dry run] ';

$repository = \Drupal::service('path_alias.repository');
$alias = $repository->lookupByAlias($path, 'en') ?? $repository->lookupByAlias($path, \Drupal::languageManager()->getDefaultLanguage()->getId());
$storage = \Drupal::entityTypeManager()->getStorage('node');
$node = NULL;
if ($alias && preg_match('#^/node/(\d+)$#', $alias['path'], $m)) {
  $node = $storage->load((int) $m[1]);
  if (!$node || $node->bundle() !== 'modular_page') {
    throw new \RuntimeException("$path belongs to {$alias['path']}, which is not a Modular page. Choose another path=.");
  }
}

if ($node) {
  $latest = $storage->getLatestRevisionId($node->id());
  if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
    throw new \RuntimeException("'{$node->label()}' has a newer draft (revision $latest). Publish or discard it first.");
  }
  $last_log = (string) $node->getRevisionLogMessage();
  if ($last_log === $log_message) {
    echo "node/{$node->id()} at $path already has this build. Nothing to do." . PHP_EOL;
    return;
  }
  if (!str_starts_with($last_log, $log_prefix) && !$force) {
    throw new \RuntimeException("node/{$node->id()} was last saved outside this script (log: \"$last_log\"), perhaps to add an issue. Copy anything worth keeping into this script, then run with force=1.");
  }
  echo "{$prefix}Rebuild node/{$node->id()} at $path in a new revision; it stays " . ($node->isPublished() ? 'published' : 'unpublished') . '.' . PHP_EOL;
}
else {
  echo "{$prefix}Create '$title', unpublished, at $path. Publish it once Fall 2026 is sent." . PHP_EOL;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];
$has_theme = (bool) FieldConfig::loadByName('paragraph', 'guide_section', 'field_section_theme');
$has_wide = (bool) FieldConfig::loadByName('paragraph', 'guide_section', 'field_section_wide');
$has_style = (bool) FieldConfig::loadByName('paragraph', 'guide_text', 'field_text_style');
$paragraphs = [];
foreach ($sections as $item) {
  $blocks = [];
  foreach ($item['blocks'] as [$heading, $body]) {
    $values = ['type' => 'guide_text', 'field_block_body' => $html($body)];
    if ($heading !== NULL) {
      $values['field_block_title'] = $heading;
    }
    if ($has_style) {
      $values['field_text_style'] = 'default';
    }
    $blocks[] = Paragraph::create($values);
  }
  $values = [
    'type' => 'guide_section',
    'field_section_title' => $item['title'],
    'field_guide_blocks' => $blocks,
  ];
  if ($has_theme) {
    $values['field_section_theme'] = 'light';
  }
  if ($has_wide) {
    $values['field_section_wide'] = $item['wide'] ? 1 : 0;
  }
  $paragraphs[] = ['entity' => Paragraph::create($values)];
}

$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');
if ($node) {
  $node->set('field_scroll_section', $paragraphs);
  if (isset($node_fields['field_intro'])) {
    $node->set('field_intro', $html($intro));
  }
  $node->setNewRevision(TRUE);
  $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
  $node->setRevisionLogMessage($log_message);
  $node->save();
  echo "Saved a new revision: node/{$node->id()} at $path. Run: drush cr" . PHP_EOL;
  return;
}

$values = [
  'type' => 'modular_page',
  'title' => $title,
  'status' => 0,
  'field_scroll_section' => $paragraphs,
  'path' => ['alias' => $path, 'pathauto' => 0],
];
if (isset($node_fields['field_modular_type'])) {
  $values['field_modular_type'] = 'guide';
}
if (isset($node_fields['field_intro'])) {
  $values['field_intro'] = $html($intro);
}
$page = Node::create($values);
$page->setRevisionLogMessage($log_message);
$page->save();
echo "Created '$title', unpublished: node/{$page->id()} (" . $page->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
echo 'Run: drush cr' . PHP_EOL;
