<?php

/**
 * @file
 * The review mode hub: the Palefire pages up for review, each with its
 * thumbs, its approved notes by section and a link into review mode (the
 * page with ?review), then any other page that has feedback. A Modular page
 * in guide mode, PUBLISHED, at an address nothing links to, and never
 * indexed (palefire_page_attachments_alter()). Run palefire_guide_setup.php
 * first.
 *
 *   drush php:script scripts/palefire_page_review_hub.php
 *   drush php:script scripts/palefire_page_review_hub.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1.
 *
 * The pages are the list of links under "[page-review-hub]" in the first
 * text block (Drupal\palefire\PageReview): edit that list in Drupal to add
 * or remove pages, one link per line. Only links to /v2 pages count. Review
 * mode works on every /v2 page whether it is listed or not; the list just
 * says where to start.
 *
 * Notes go to the Webform "page_review", made by
 * scripts/palefire_page_review_webform.php, and show here and on the page
 * in review mode once staff tick Approved on them in its results.
 *
 * A page is rebuilt in a new revision when this script's text changes; it
 * stops if the page was edited in Drupal (force=1 overrides), so after the
 * first run, edit the page in Drupal rather than here.
 *
 * Optional arguments after "--":
 *   apply=1               Save.
 *   force=1               Replace the page even if it was edited in Drupal.
 *   path=<alias>          Default /v2/internal/page-review.
 *   format=<text format>  Default: the WYSIWYG format.
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

$path = '/' . ltrim($args['path'] ?? '/v2/internal/page-review', '/');
$title = 'Page Review';

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

$intro = '<p>Palefire pages up for review. Open one in review mode to leave notes and thumbs on the page itself, section by section: no sign-in, and your name is optional. Every Palefire page works the same way: add ?review to the end of its address.</p>';

$sections = [
  [
    'title' => 'Pages',
    'wide' => TRUE,
    'blocks' => [
      [NULL, <<<'HTML'
<p>[page-review-hub]</p>
<ul>
<li><a href="/v2/special">Special Collections</a></li>
<li><a href="/v2/search">Combined Search</a></li>
<li><a href="/v2/newsletters">Newsletters</a></li>
<li><a href="/v2/research-dashboard">Research Dashboard</a></li>
<li><a href="/v2/internal/pr-timeline">PR Timeline</a></li>
</ul>
HTML, 'default'],
    ],
  ],
  [
    'title' => 'How review mode works',
    'wide' => FALSE,
    'blocks' => [
      [NULL, <<<'HTML'
<ul>
<li>In review mode, a small pin sits beside each section heading. Choose one to write about that section, or use <strong>Review this page</strong> at the foot of the screen to write about the page as a whole.</li>
<li>Thumbs count right away. Notes go to the web team first and show here and on the page only after staff approve them, with the name you gave, or as Anonymous.</li>
<li>Links to other Palefire pages stay in review mode. Choose <strong>Exit review</strong> in the panel to leave it.</li>
<li>Text size and colors can be changed in Settings, the gear icon at the top of every page.</li>
<li>This page isn’t listed anywhere or found by search engines. Please don’t pass the link on beyond the people asked to look.</li>
</ul>
HTML, 'default'],
    ],
  ],
];

$fingerprint = substr(sha1(json_encode([$intro, $sections], JSON_THROW_ON_ERROR)), 0, 12);
$log_prefix = 'palefire_page_review_hub.php build';
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

$types = \Drupal::entityTypeManager();
if (!$types->hasDefinition('webform') || !$types->getStorage('webform')->load(\Drupal\palefire\PageReview::WEBFORM)) {
  echo "No Webform 'page_review' yet: review mode says the form isn't set up until it is created (scripts/palefire_page_review_webform.php -- apply=1)." . PHP_EOL;
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
    throw new \RuntimeException("node/{$node->id()} was last saved outside this script (log: \"$last_log\"). Copy anything worth keeping into this script, then run with force=1.");
  }
  echo "{$prefix}Rebuild node/{$node->id()} at $path in a new revision; it stays " . ($node->isPublished() ? 'published' : 'unpublished') . '.' . PHP_EOL;
}
else {
  echo "{$prefix}Create '$title', published, at $path." . PHP_EOL;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];
$has_style = (bool) FieldConfig::loadByName('paragraph', 'guide_text', 'field_text_style');
$has_theme = (bool) FieldConfig::loadByName('paragraph', 'guide_section', 'field_section_theme');
$has_wide = (bool) FieldConfig::loadByName('paragraph', 'guide_section', 'field_section_wide');
$paragraphs = [];
foreach ($sections as $item) {
  $blocks = [];
  foreach ($item['blocks'] as [$heading, $body, $style]) {
    $values = ['type' => 'guide_text', 'field_block_body' => $html($body)];
    if ($heading !== NULL) {
      $values['field_block_title'] = $heading;
    }
    if ($has_style) {
      $values['field_text_style'] = $style;
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
  $node->setTitle($title);
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
  'status' => 1,
  'promote' => 0,
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
echo "Created '$title', published: node/{$page->id()} (" . $page->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
echo 'Run: drush cr' . PHP_EOL;
