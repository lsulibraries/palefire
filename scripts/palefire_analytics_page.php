<?php

/**
 * @file
 * Guide page: "Website Analytics", the staff report on how the website is
 * used, as an UNPUBLISHED Modular page in guide mode, so only signed-in staff
 * who can see unpublished content can open it. Run palefire_guide_setup.php
 * first.
 *
 *   drush php:script scripts/palefire_analytics_page.php
 *   drush php:script scripts/palefire_analytics_page.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. The figures are not in the page: a text block
 * that is only "[analytics:report]" draws them from what
 * palefire_analytics_pull.php fetched last night (Drupal\palefire\AnalyticsReport).
 * This script owns the page's words and can be run again; each build is a
 * new revision, and it stops if the page was edited in Drupal (force=1
 * overrides). Every build leaves the page unpublished: search terms are
 * people's own words.
 *
 * Optional arguments after "--": apply=1, force=1, format=<text format>,
 * path=<alias> (default /v2/internal/analytics).
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

$title = 'Website Analytics';

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

$intro = '<p>How people use the library website: page views on the new and old sites, the kinds of page they read, and what they search for. For library staff; updated each night from Google Analytics.</p>';

$sections = [
  [
    'title' => 'Last 28 days',
    'blocks' => [
      [NULL, '<p>[analytics:report]</p>'],
    ],
  ],
  [
    'title' => 'About these figures',
    'blocks' => [
      ['How pages are counted', <<<'HTML'
<p>Every page on the new site says what it is: its part of the site (Special Collections, or the rest of the library) and its kind (a guide, an exhibition, a location, Combined Search…). The figures are grouped by those labels, not by web address, so they carry on unchanged when the new pages leave /v2. A page without the labels is counted as the old site.</p>
<p>A page view is counted when a page opens, including moves between new-site pages that don't reload the browser. A search is counted when someone runs a new Combined Search; changing filters, sorting or pages of results doesn't count again.</p>
HTML],
      ['Privacy', <<<'HTML'
<p>Search terms are what people typed. Email addresses are removed before anything reaches Google Analytics, and Google withholds rare rows, but a search can still contain a name, so this page stays unpublished and the report shows only to signed-in staff. Please don't copy search terms to public places.</p>
HTML],
      ['More detail', <<<'HTML'
<p>For anything this page doesn't show, use Google Analytics itself (property "Library Website"): Reports, then Engagement, then Pages and screens, with Page section or Page type added as a second column; or Explore, with Search term and Search mode. Nov 11–12, 2026 carries an annotation: page views from in-page navigation changed how they were counted that week.</p>
HTML],
    ],
  ],
];

$fingerprint = substr(sha1(json_encode([$intro, $sections], JSON_THROW_ON_ERROR)), 0, 12);
$log_prefix = 'palefire_analytics_page.php build';
$log_message = "$log_prefix $fingerprint";

$storage = \Drupal::entityTypeManager()->getStorage('node');
$found = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', $title)
  ->range(0, 1)->execute();
$node = $found ? $storage->load((int) reset($found)) : NULL;
$prefix = $apply ? '' : '[dry run] ';

if ($node) {
  $latest = $storage->getLatestRevisionId($node->id());
  if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
    throw new \RuntimeException("'$title' has a newer draft (revision $latest). Publish or discard it first.");
  }
  $last_log = (string) $node->getRevisionLogMessage();
  if ($last_log === $log_message && !$node->isPublished()) {
    echo "'$title' (node/{$node->id()}) already has this build. Nothing to do." . PHP_EOL;
    return;
  }
  if (!str_starts_with($last_log, $log_prefix) && !$force) {
    throw new \RuntimeException("'$title' (node/{$node->id()}) was last saved outside this script (log: \"$last_log\"). Copy anything worth keeping into this script, then run with force=1.");
  }
  echo "{$prefix}Rebuild node/{$node->id()} in a new revision, unpublished." . PHP_EOL;
}
else {
  echo "{$prefix}Create '$title' as an unpublished guide page." . PHP_EOL;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];
$has_theme = (bool) FieldConfig::loadByName('paragraph', 'guide_section', 'field_section_theme');
$paragraphs = [];
foreach ($sections as $item) {
  $blocks = [];
  foreach ($item['blocks'] as [$heading, $body]) {
    $blocks[] = Paragraph::create(array_filter([
      'type' => 'guide_text',
      'field_block_title' => $heading,
      'field_block_body' => $html($body),
    ], fn ($v) => $v !== NULL));
  }
  $values = [
    'type' => 'guide_section',
    'field_section_title' => $item['title'],
    'field_guide_blocks' => $blocks,
  ];
  if ($has_theme) {
    $values['field_section_theme'] = 'light';
  }
  $paragraphs[] = ['entity' => Paragraph::create($values)];
}

$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');

if (!$node) {
  $values = [
    'type' => 'modular_page',
    'title' => $title,
    'path' => ['alias' => '/' . ltrim($args['path'] ?? '/v2/internal/analytics', '/')],
  ];
  if (isset($node_fields['field_modular_type'])) {
    $values['field_modular_type'] = 'guide';
  }
  $node = Node::create($values);
}
$node->setUnpublished();
$node->set('field_scroll_section', $paragraphs);
if (isset($node_fields['field_intro'])) {
  $node->set('field_intro', $html($intro));
}
$node->setNewRevision(TRUE);
$node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
$node->setRevisionLogMessage($log_message);
$node->save();

echo "Saved '$title', unpublished: node/{$node->id()} (" . $node->toUrl('canonical', ['absolute' => TRUE])->toString() . '). Run: drush cr' . PHP_EOL;
