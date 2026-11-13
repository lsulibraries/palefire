<?php

/**
 * @file
 * "LSU Research, by the Numbers": a page that presents Colton Ray's OpenAlex
 * Research Dashboard (lsuopenalex.lsusites.org) and links out to it, as an
 * UNPUBLISHED Modular page of the plain kind (no mode: banded sections, the
 * shared column and headings). Run palefire_guide_setup.php and
 * palefire_guide_blocks_setup.php first.
 *
 *   drush php:script scripts/palefire_research_dashboard.php
 *   drush php:script scripts/palefire_research_dashboard.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. The dashboard is linked, not framed: it loads its
 * own charting and table libraries and fonts from CDNs, which a Palefire page
 * should not carry, and a frame would hide its tables from keyboard and
 * screen-reader users.
 *
 * Sections: At a glance (Figures and the link to the dashboard), What you can
 * explore, About the data, Questions (Colton's librarian card when his Staff
 * page exists, else his contact in words).
 *
 * The figures were counted from the OpenAlex API on 2026-11-13: works with at
 * least one LSU author (OpenAlex I121820613, ROR 05ect4e57) published since
 * 2020-01-01. They are typed in, so they do not change with the dashboard;
 * refresh them here once a semester, or ask Colton for his.
 *
 * This script owns the page's words and can be run again; each build is a
 * new revision and leaves the page unpublished. It stops if the page was
 * edited in Drupal (force=1 overrides).
 *
 * Optional arguments after "--": apply=1, force=1, format=<text format>,
 * path=<alias> (default /v2/research-dashboard).
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

$paragraph_types = \Drupal::entityTypeManager()->getStorage('paragraphs_type');
foreach (['guide_section', 'guide_text', 'guide_stats', 'guide_stat'] as $id) {
  if (!$paragraph_types->load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_guide_setup.php and palefire_guide_blocks_setup.php first.");
  }
}

$title = 'LSU Research, by the Numbers';
$dashboard = 'https://lsuopenalex.lsusites.org/';

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

// Colton's Staff page, for the librarian card. None: his contact in words.
$staff = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'staff')
  ->condition('title', 'Colton Ray')
  ->range(0, 1)->execute();
$staff_id = $staff ? (int) reset($staff) : NULL;
$has_card = $staff_id && $paragraph_types->load('librarian_card');

$intro = '<p>What LSU researchers publish, where it appears, how often it is cited and how much of it anyone can read for free. The LSU Research Dashboard, built by LSU Libraries from open OpenAlex data, follows every work with an LSU author since 2020 and updates daily.</p>';

$link = '<p><a href="' . $dashboard . '">Open the LSU Research Dashboard</a> (opens lsuopenalex.lsusites.org)</p>';

// [heading, body, style] for text; ['stats', heading, figures, caption].
$sections = [
  [
    'title' => 'At a glance',
    'blocks' => [
      ['stats', NULL, [
        ['33,000+', 'Works with an LSU author since 2020'],
        ['75%', 'Free to read (open access)'],
        ['~4,800', 'New works a year'],
      ], '<p>Counted from OpenAlex on November 13, 2026: articles, books, chapters, datasets and other works with at least one LSU author, published since January 1, 2020. The dashboard has today’s figures.</p>'],
      [NULL, $link, 'callout'],
    ],
  ],
  [
    'title' => 'What you can explore',
    'blocks' => [
      [NULL, <<<'HTML'
<p>Choose a range of years, and every figure, chart and table follows it.</p>
<ul>
<li><strong>Overview:</strong> works per year, kinds of work, open access by type and over time, the leading publishers and journals, funding agencies, and estimated article processing charges.</li>
<li><strong>Recent Publications:</strong> the newest works, searchable and sortable.</li>
<li><strong>Top Cited:</strong> the twenty most cited works in the range.</li>
<li><strong>Authors and Topics:</strong> the most published LSU authors and the main research topics.</li>
</ul>
HTML, 'default'],
    ],
  ],
  [
    'title' => 'About the data',
    'blocks' => [
      ['OpenAlex', <<<'HTML'
<p><a href="https://openalex.org/">OpenAlex</a> is a free, open index of the world’s scholarly works, authors, sources and institutions, run by the nonprofit OurResearch. Its data is in the public domain, so anyone can check, reuse or build on these figures.</p>
<p>A work counts for LSU when OpenAlex links one of its authors to Louisiana State University (<a href="https://ror.org/05ect4e57">ROR 05ect4e57</a>). The dashboard gathers them once a day and keeps a copy, so it opens quickly.</p>
HTML, 'default'],
      ['Read with care', <<<'HTML'
<ul>
<li>Affiliations come from the publications themselves and are sometimes missing or wrong, so a few LSU works are absent and a few are counted that should not be.</li>
<li>Counts differ from Scopus, Web of Science and Google Scholar, which index different works.</li>
<li>Citations grow over time, so recent works look less cited than they will be.</li>
<li>Article processing charges are estimates from publishers’ list prices, not what LSU or its authors paid.</li>
</ul>
HTML, 'default'],
    ],
  ],
  [
    'title' => 'Questions',
    'blocks' => [
      $has_card
        ? ['card', 'Ask about the dashboard', $staff_id]
        : ['Ask about the dashboard', '<p>Colton Ray, Scholarly Publications and Analytics Librarian, built the dashboard and can help with research impact, open access and publishing questions: <a href="mailto:coltonray@lsu.edu">coltonray@lsu.edu</a>, 225-578-4338.</p>', 'default'],
    ],
  ],
];

$fingerprint = substr(sha1(json_encode([$intro, $sections], JSON_THROW_ON_ERROR)), 0, 12);
$log_prefix = 'palefire_research_dashboard.php build';
$log_message = "$log_prefix $fingerprint";

$storage = \Drupal::entityTypeManager()->getStorage('node');
$found = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', $title)
  ->range(0, 1)->execute();
$node = $found ? $storage->load((int) reset($found)) : NULL;
$prefix = $apply ? '' : '[dry run] ';

echo $prefix . ($has_card ? "Questions: librarian card for Colton Ray (node/$staff_id)." : 'Questions: no Staff page titled "Colton Ray", so his contact is in words.') . PHP_EOL;
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
  echo "{$prefix}Create '$title' as an unpublished Modular page." . PHP_EOL;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];
$has_caption = (bool) FieldConfig::loadByName('paragraph', 'guide_stats', 'field_stats_caption');
$has_style = (bool) FieldConfig::loadByName('paragraph', 'guide_text', 'field_text_style');
$paragraphs = [];
foreach ($sections as $item) {
  $blocks = [];
  foreach ($item['blocks'] as $block) {
    if ($block[0] === 'stats') {
      [, $heading, $figures, $caption] = $block;
      $stats = [];
      foreach ($figures as [$value, $label]) {
        $stats[] = Paragraph::create([
          'type' => 'guide_stat',
          'field_stat_value' => $value,
          'field_stat_label' => $label,
        ]);
      }
      $values = array_filter([
        'type' => 'guide_stats',
        'field_block_title' => $heading,
        'field_stat_items' => $stats,
      ], fn ($v) => $v !== NULL);
      if ($has_caption) {
        $values['field_stats_caption'] = $html($caption);
      }
      $blocks[] = Paragraph::create($values);
      continue;
    }
    if ($block[0] === 'card') {
      $blocks[] = Paragraph::create([
        'type' => 'librarian_card',
        'field_block_title' => $block[1],
        'field_librarian' => ['target_id' => $block[2]],
      ]);
      continue;
    }
    [$heading, $body, $style] = $block;
    $values = array_filter([
      'type' => 'guide_text',
      'field_block_title' => $heading,
      'field_block_body' => $html($body),
    ], fn ($v) => $v !== NULL);
    if ($has_style) {
      $values['field_text_style'] = $style;
    }
    $blocks[] = Paragraph::create($values);
  }
  $paragraphs[] = ['entity' => Paragraph::create([
    'type' => 'guide_section',
    'field_section_title' => $item['title'],
    'field_guide_blocks' => $blocks,
  ])];
}

$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');

if (!$node) {
  // No field_modular_type: the plain kind ("page"), banded.
  $node = Node::create([
    'type' => 'modular_page',
    'title' => $title,
    'path' => ['alias' => '/' . ltrim($args['path'] ?? '/v2/research-dashboard', '/')],
  ]);
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
