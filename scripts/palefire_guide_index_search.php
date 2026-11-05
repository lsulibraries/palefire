<?php

/**
 * @file
 * Guide page: "Index Search Project", the living status of the new search
 * page at /v2/search, as a Modular page in guide mode. Run
 * palefire_guide_setup.php and palefire_guide_blocks_setup.php first.
 *
 *   drush php:script scripts/palefire_guide_index_search.php
 *   drush php:script scripts/palefire_guide_index_search.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. Built the same way as
 * palefire_guide_ldl_migration.php: this script is the page's source and can
 * be run again. A new page is created UNPUBLISHED; after that each build
 * replaces the intro and sections in a new revision, and stops if the page
 * was edited in Drupal or has a newer draft (force=1 overrides the first).
 *
 * Optional arguments after "--": apply=1, force=1, format=<text format>,
 * path=<alias> (default /v2/guides/index-search-project).
 *
 * Status changes go in the "Status at a glance" section below. This page may
 * be published, so security findings and server details stay out of it.
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

foreach (['guide_section', 'guide_text', 'guide_accordion'] as $id) {
  if (!\Drupal::entityTypeManager()->getStorage('paragraphs_type')->load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_guide_setup.php first.");
  }
}
foreach (['guide_flow', 'guide_status_board', 'guide_stats', 'guide_code'] as $id) {
  if (!\Drupal::entityTypeManager()->getStorage('paragraphs_type')->load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_guide_blocks_setup.php first.");
  }
}

$title = 'Index Search Project';

// Guide text uses the WYSIWYG format (machine name or label "WYSIWYG"),
// never full_html.
$formats = filter_formats();
$format = $args['format'] ?? NULL;
foreach ($format === NULL ? $formats : [] as $id => $candidate) {
  if ($id === 'wysiwyg' || strcasecmp((string) $candidate->label(), 'WYSIWYG') === 0) {
    $format = $id;
    break;
  }
}
if ($format === NULL) {
  throw new \RuntimeException('No WYSIWYG text format found. Pass format=<machine name>.');
}
if (!isset($formats[$format])) {
  throw new \RuntimeException("Text format '$format' not found.");
}

// Content helpers: plain arrays, so a dry run creates nothing and the
// fingerprint covers exactly what the page will say. ------------------------

$sections = [];
$section = function (string $heading, array $blocks) use (&$sections): void {
  $sections[] = ['title' => $heading, 'blocks' => $blocks];
};
$text = fn (?string $heading, string $body, string $style = 'default') => [
  'kind' => 'text',
  'heading' => $heading,
  'body' => $body,
  'style' => $style,
];
$accordion = fn (?string $heading, array $items, string $intro = '') => [
  'kind' => 'accordion',
  'heading' => $heading,
  'intro' => $intro,
  'items' => $items,
];

$flow = fn (?string $heading, array $steps, string $caption = '') => [
  'kind' => 'flow',
  'heading' => $heading,
  'steps' => $steps,
  'caption' => $caption,
];
$step = fn (string $title, string $note = '', string $kind = 'step', string $link = '', string $group = '', string $if = '', string $then = '', string $then_note = '', string $tone = 'plain') => [
  'title' => $title,
  'note' => $note,
  'kind' => $kind,
  'link' => $link,
  'group' => $group,
  'if' => $if,
  'then' => $then,
  'then_note' => $then_note,
  'tone' => $tone,
];
$status = fn (?string $heading, array $items) => [
  'kind' => 'status',
  'heading' => $heading,
  'items' => $items,
];
$stats = fn (?string $heading, array $items) => [
  'kind' => 'stats',
  'heading' => $heading,
  'items' => $items,
];
$code = fn (?string $heading, string $text, string $caption = '') => [
  'kind' => 'code',
  'heading' => $heading,
  'text' => $text,
  'caption' => $caption,
];

$intro = '<p>What the new search page, the Index, covers, what\'s finished, what we found along the way, and what comes next.</p>';

// Sections. ----------------------------------------------------------------

$section('Status at a glance', [
  $text(NULL, <<<'HTML'
<p><strong>Status as of Thu 5 Nov 2026:</strong> the first build is running on production at /v2/search and searches real content in all four modes. Nothing links to it yet: the site's search forms still go to the old page. Filters that combine, chosen-filter chips, sorting and results that change without a page reload are on production, and the filters and sorting are checked for logged-out visitors. Search operators are the current work.</p>
HTML, 'callout'),
  $status('Where each stage stands', [
    ['done', 'Review of the current search', 'The one_search module, its 11 Views, the search index and the connector modules are read.'],
    ['done', 'Names', 'Index is the working name for visitors, with four modes: All, Research, Website and Special Collections. The address is /v2/search.'],
    ['done', 'Page design', 'Layout and rows are styled, with no sideways scrolling at 320, 768, 1024 and 1440 pixels on sample data.'],
    ['done', 'Module first run', 'palefire_search is enabled on production. All four modes, source filters, paging, Articles & Books, Research Guides, Staff, Gear, Databases and E-Textbooks return real results.'],
    ['running', 'Filters and sorting', 'Choose several filters at once, remove them one at a time, and sort News and Articles & Books by date. Results change in place, without reloading the page. Filters and sorting are checked on production for logged-out visitors; changing in place still needs a check in a browser, logged in and out.'],
    ['running', 'Search operators', 'AND, OR, NOT, a minus sign and parentheses, read the same way in every source that can honor them. Research Guides can\'t, so the page says what it searched for instead. Exact phrases in the site\'s own content wait for a reindex.'],
    ['running', 'Pictures in results', 'Staff, News, Gear and Pages show their picture, and Articles & Books shows a cover when Discovery has one. A result with no picture keeps its text at full width.'],
    ['todo', 'More sorting and filters', 'A date range, sorting by title, and a clean year for Archive Records. These need changes to the search index and a reindex.'],
    ['todo', 'Palefire pages in search', 'modular_page isn\'t in the search index, so Palefire pages can\'t appear in results until it\'s added and the site is reindexed.'],
    ['todo', 'Scholarly Repository', 'A link for now. The repository\'s public address answered a request from outside the site with a Cloudflare challenge; the server\'s log will confirm whether the site gets the same.'],
    ['todo', 'Point the search forms here', 'The header panel, landing and 404 searches still go to /bento. They move once the page has been tested.'],
    ['todo', 'Move Discovery and the Catalog to Alma', 'Waiting on the migration. See Alma below.'],
  ]),
]);

$section('What it searches', [
  $text(NULL, <<<'HTML'
<table>
<thead><tr><th scope="col">Mode</th><th scope="col">Sources</th><th scope="col">Shown as</th></tr></thead>
<tbody>
<tr><td>All</td><td>Every source</td><td>Labelled sections, top three of each</td></tr>
<tr><td>Research</td><td>Articles &amp; Books, Databases, E-Textbooks, Research Guides, Archive Records, plus links to the Scholarly Repository and Catalog</td><td>Labelled sections</td></tr>
<tr><td>Website</td><td>Pages, Questions &amp; Answers, Gear, News and Notes, Staff</td><td>One ranked list with filters</td></tr>
<tr><td>Special Collections</td><td>Archive Records, Special Collections news</td><td>One ranked list with filters</td></tr>
</tbody>
</table>
<p>Sources that live in the site's own index can share one ranked list. Outside services can't be ranked against them, so they stay in their own sections.</p>
HTML),
]);

$section('How it works', [
  $flow(NULL, [
    $step('A visitor searches', 'A normal web address, /v2/search?q=, so any search can be shared.'),
    $step('Local results come first', 'One direct query to the site\'s search index, matching all the words.'),
    $step('Outside services follow', 'Each loads on its own after the page, so a slow service can\'t hold it up.'),
    $step('Failures say so', 'A service that can\'t be reached says that, instead of showing no results.'),
  ]),
]);

$section('What we found in the old search', [
  $text(NULL, <<<'HTML'
<table>
<thead><tr><th scope="col">Finding</th><th scope="col">What the Index does</th></tr></thead>
<tbody>
<tr><td>Some sources match any word, others all words. "huey long" finds 1,267 Archive Records; "huey" alone finds 630.</td><td>Matches all words across the local sources.</td></tr>
<tr><td>Words under three letters are ignored, which seems to turn off the filter. "ai" and "r&amp;b" return identical counts.</td><td>Doesn't send those to the index, and tells the visitor why.</td></tr>
<tr><td>The search term goes into outside addresses unencoded, which is likely why "r&amp;b" returns about 51.9 million results.</td><td>Encodes the term first. To be confirmed on first run.</td></tr>
<tr><td>Errors from outside services look like zero results.</td><td>Reports them as unavailable and doesn't keep them.</td></tr>
<tr><td>Result descriptions only appear in hover tooltips.</td><td>Shows them in the result.</td></tr>
</tbody>
</table>
HTML),
]);

$section('Alma', [
  $text(NULL, <<<'HTML'
<p>The Libraries are moving from EDS and the current catalog to Alma. A sandbox may be available, but it isn't fully populated yet.</p>
<p>The page is ready for this. Visitors see "Articles &amp; Books" and "Catalog", not a vendor's name, and each service is one definition in the module, so moving it won't change the page's addresses or design.</p>
HTML),
]);

$section('Next steps', [
  $text(NULL, <<<'HTML'
<ol>
<li>Test the new filters, sorting and in-place results on production, logged in and logged out.</li>
<li>Review the page's design together.</li>
<li>Add Palefire pages to the search index and reindex, along with the date range, title sorting and exact phrases.</li>
<li>Confirm whether the Scholarly Repository can be searched from the server.</li>
<li>Move the site's search forms to the Index and redirect /bento.</li>
<li>Connect Alma when the sandbox is ready.</li>
</ol>
HTML),
]);

// End of sections. ---------------------------------------------------------

$block_count = 0;
foreach ($sections as $item) {
  $block_count += count($item['blocks']);
}
$fingerprint = substr(sha1(json_encode([$intro, $sections], JSON_THROW_ON_ERROR)), 0, 12);
$log_prefix = 'palefire_guide_index_search.php build';
$log_message = "$log_prefix $fingerprint";

// What's there now. --------------------------------------------------------

$storage = \Drupal::entityTypeManager()->getStorage('node');
$found = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', $title)
  ->range(0, 1)->execute();
$node = $found ? $storage->load((int) reset($found)) : NULL;
$prefix = $apply ? '' : '[dry run] ';

echo "Text format: $format" . PHP_EOL;
echo "Content: " . count($sections) . " sections, $block_count blocks, build $fingerprint." . PHP_EOL;

if ($node) {
  $latest = $storage->getLatestRevisionId($node->id());
  if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
    throw new \RuntimeException("'$title' has a newer draft (revision $latest) than its current revision. Publish or discard it first.");
  }
  $last_log = (string) $node->getRevisionLogMessage();
  if ($last_log === $log_message) {
    echo "'$title' (node/{$node->id()}) already has this build. Nothing to do." . PHP_EOL;
    return;
  }
  if (!str_starts_with($last_log, $log_prefix) && !$force) {
    throw new \RuntimeException("'$title' (node/{$node->id()}) was last saved outside this script (log: \"$last_log\"), and a build would replace any edits. Check its Revisions tab, copy anything worth keeping into this script, then run with force=1.");
  }
  $state = $node->isPublished() ? 'published' : 'unpublished';
  echo "{$prefix}Replace the intro and " . count($node->get('field_scroll_section')) . " sections of node/{$node->id()}, in a new revision. It stays $state." . PHP_EOL;
}
else {
  echo "{$prefix}Create '$title' as an unpublished guide page." . PHP_EOL;
}
foreach ($sections as $item) {
  echo "{$prefix}  {$item['title']} (" . count($item['blocks']) . ' blocks)' . PHP_EOL;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

// Build. -------------------------------------------------------------------

$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];
$keep = fn (array $values) => array_filter($values, fn ($v) => $v !== NULL);

$to_paragraph = function (array $block) use ($html, $keep): Paragraph {
  $blank = fn (string $value) => $value !== '' ? $value : NULL;
  // Notes and captions are WYSIWYG fields; here they are written as plain text.
  $rich = fn (string $value) => $value !== '' ? $html('<p>' . htmlspecialchars($value, ENT_NOQUOTES) . '</p>') : NULL;
  if ($block['kind'] === 'flow') {
    $children = [];
    foreach ($block['steps'] as $item) {
      $children[] = Paragraph::create($keep([
        'type' => 'guide_flow_step',
        'field_item_title' => $item['title'],
        'field_flow_note' => $rich($item['note']),
        'field_flow_kind' => $item['kind'],
        'field_flow_group' => $blank($item['group']),
        'field_flow_link' => $blank($item['link']),
        'field_flow_branch_title' => $blank($item['then']),
        'field_flow_branch_label' => $blank($item['if']),
        'field_flow_branch_note' => $rich($item['then_note']),
        'field_flow_branch_tone' => $item['tone'],
      ]));
    }
    return Paragraph::create($keep([
      'type' => 'guide_flow',
      'field_block_title' => $block['heading'],
      'field_flow_steps' => $children,
      'field_flow_caption' => $rich($block['caption']),
    ]));
  }
  if ($block['kind'] === 'status') {
    $children = [];
    foreach ($block['items'] as [$state, $item_title, $note]) {
      $children[] = Paragraph::create([
        'type' => 'guide_status_item',
        'field_status_state' => $state,
        'field_item_title' => $item_title,
        'field_status_note' => $rich($note),
      ]);
    }
    return Paragraph::create($keep([
      'type' => 'guide_status_board',
      'field_block_title' => $block['heading'],
      'field_status_items' => $children,
    ]));
  }
  if ($block['kind'] === 'stats') {
    $children = [];
    foreach ($block['items'] as [$value, $label]) {
      $children[] = Paragraph::create([
        'type' => 'guide_stat',
        'field_stat_value' => $value,
        'field_stat_label' => $label,
      ]);
    }
    return Paragraph::create($keep([
      'type' => 'guide_stats',
      'field_block_title' => $block['heading'],
      'field_stat_items' => $children,
    ]));
  }
  if ($block['kind'] === 'code') {
    return Paragraph::create($keep([
      'type' => 'guide_code',
      'field_block_title' => $block['heading'],
      'field_code_text' => $block['text'],
      'field_code_caption' => $rich($block['caption']),
    ]));
  }
  if ($block['kind'] === 'accordion') {
    $children = [];
    foreach ($block['items'] as [$item_title, $item_body]) {
      $children[] = Paragraph::create([
        'type' => 'accordion_item',
        'field_item_title' => $item_title,
        'field_block_body' => $html($item_body),
      ]);
    }
    return Paragraph::create($keep([
      'type' => 'guide_accordion',
      'field_block_title' => $block['heading'],
      'field_block_intro' => $block['intro'] !== '' ? $html($block['intro']) : NULL,
      'field_accordion_items' => $children,
    ]));
  }
  return Paragraph::create($keep([
    'type' => 'guide_text',
    'field_block_title' => $block['heading'],
    'field_block_body' => $html($block['body']),
    'field_text_style' => $block['style'],
  ]));
};

$has_theme = (bool) FieldConfig::loadByName('paragraph', 'guide_section', 'field_section_theme');
$paragraphs = [];
foreach ($sections as $item) {
  $values = [
    'type' => 'guide_section',
    'field_section_title' => $item['title'],
    'field_guide_blocks' => array_map($to_paragraph, $item['blocks']),
  ];
  if ($has_theme) {
    $values['field_section_theme'] = 'light';
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
  echo "Saved a new revision of '$title': node/{$node->id()}. Run: drush cr" . PHP_EOL;
  return;
}

$values = [
  'type' => 'modular_page',
  'title' => $title,
  'status' => 0,
  'field_scroll_section' => $paragraphs,
  'path' => ['alias' => '/' . ltrim($args['path'] ?? '/v2/guides/index-search-project', '/')],
];
if (isset($node_fields['field_modular_type'])) {
  $values['field_modular_type'] = 'guide';
}
if (isset($node_fields['field_intro'])) {
  $values['field_intro'] = $html($intro);
}

// Owner: Kyle Tanglao's Staff node. Subjects are left empty on purpose: this
// is a project record, not a research guide.
$skipped = [];
$owner_field = $node_fields['field_guide_owner'] ?? NULL;
if ($owner_field) {
  $staff_bundles = array_keys($owner_field->getSetting('handler_settings')['target_bundles'] ?? []);
  $match = $staff_bundles ? \Drupal::entityQuery('node')->accessCheck(FALSE)
    ->condition('type', $staff_bundles, 'IN')
    ->condition('title', 'Kyle', 'CONTAINS')
    ->condition('title', 'Tanglao', 'CONTAINS')
    ->range(0, 1)->execute() : [];
  if ($match) {
    $values['field_guide_owner'] = ['target_id' => (int) reset($match)];
  }
  else {
    $skipped[] = 'Guide owner: no Staff node titled like "Kyle Tanglao" was found. Set it on the page.';
  }
}

$page = Node::create($values);
$page->setRevisionLogMessage($log_message);
$page->save();

echo 'Created unpublished guide page: node/' . $page->id() . ' (' . $page->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
foreach ($skipped as $line) {
  echo 'Skipped: ' . $line . PHP_EOL;
}
echo 'Run: drush cr' . PHP_EOL;
