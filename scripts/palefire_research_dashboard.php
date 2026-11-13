<?php

/**
 * @file
 * "LSU Research, by the Numbers": a page that presents Colton Ray's OpenAlex
 * Research Dashboard (lsuopenalex.lsusites.org) and links out to it, as an
 * UNPUBLISHED Modular page in exhibition mode. Run palefire_exhibit_setup.php
 * (title page fields, galleries) and palefire_guide_setup.php first.
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
 * Title page: the kicker "Research", the title, a subtitle, the intro, three
 * live key facts, the prompt, and "Open the dashboard" as the main button
 * beside Begin and Contents. No cover image: a type-only title page until an
 * editor adds one (Exhibition title image).
 *
 * Galleries: Output, Open access, Where it is published, Funding and topics,
 * Most cited, The full dashboard, About the data, Questions (Colton's
 * librarian card when his Staff page exists and galleries allow the card,
 * else his contact in words).
 *
 * The figures are live: a text block holding "[research:<part>]" draws that
 * part, and a key fact "Label: [research:<name>]" that figure, from what
 * palefire_research_pull.php last fetched from OpenAlex
 * (Drupal\palefire\ResearchFigures, nightly cron). Run the pull once before
 * the page is first viewed: until then each part says the figures are on
 * their way, and the key facts are left out.
 *
 * This script owns the page's words and can be run again; each build is a
 * new revision and leaves the page unpublished. A page built as the plain
 * kind by an earlier version becomes an exhibition. It stops if the page was
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
foreach (['exhibit_page', 'guide_text'] as $id) {
  if (!$paragraph_types->load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_exhibit_setup.php and palefire_guide_setup.php first.");
  }
}
$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');
foreach (['field_modular_type', 'field_exhibit_kicker', 'field_exhibit_keyfacts', 'field_exhibit_action'] as $name) {
  if (!isset($node_fields[$name])) {
    throw new \RuntimeException("node.modular_page.$name not found. Run palefire_exhibit_setup.php first.");
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

// Colton's Staff page, for the librarian card, if galleries take the card.
// None: his contact in words.
$blocks_field = FieldConfig::loadByName('paragraph', 'exhibit_page', 'field_guide_blocks');
$allowed = $blocks_field ? ($blocks_field->getSetting('handler_settings')['target_bundles'] ?? []) : [];
$staff = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'staff')
  ->condition('title', 'Colton Ray')
  ->range(0, 1)->execute();
$staff_id = $staff ? (int) reset($staff) : NULL;
$has_card = $staff_id && $paragraph_types->load('librarian_card') && (!$allowed || isset($allowed['librarian_card']));

// The title page. -------------------------------------------------------------
$head = [
  'kicker' => 'Research',
  'subtitle' => 'What LSU researchers publish, and who reads it',
  'intro' => '<p>Where LSU research appears, who funds it, how often it is cited and how much of it anyone can read for free: every work with an LSU author since 2020, counted from open OpenAlex data and updated each night.</p>',
  'keyfacts' => [
    'Works since 2020: [research:works]',
    'Free to read: [research:open]',
    'Citations in {year}: [research:citations]',
  ],
  'prompt' => 'Explore the figures here, or open the full dashboard.',
  'action' => ['uri' => $dashboard, 'title' => 'Open the dashboard'],
];

$link = '<p><a href="' . $dashboard . '">Open the LSU Research Dashboard</a> (opens lsuopenalex.lsusites.org)</p>';

// A text block whose body holds "[research:<part>]" draws that part live.
$live = fn (string $part, string $lead = '') => $lead . '<p>[research:' . $part . ']</p>';

// Galleries: title, anchor and blocks. A block is [heading, body, style], or
// ['card', heading, staff node id].
$galleries = [
  [
    'title' => 'Output',
    'anchor' => 'output',
    'blocks' => [
      ['Works each year', $live('years', '<p>Articles, books, chapters, dissertations, datasets and other works with at least one LSU author, published since January 1, 2020.</p>'), 'default'],
      ['Kinds of work', $live('kinds'), 'default'],
    ],
  ],
  [
    'title' => 'Open access',
    'anchor' => 'open-access',
    'blocks' => [
      [NULL, $live('access', '<p>How LSU research can be read: free in an open access journal, free in a repository or on the publisher’s site, or by subscription only. Shares are of all works since 2020.</p>'), 'default'],
    ],
  ],
  [
    'title' => 'Where it is published',
    'anchor' => 'where-published',
    'blocks' => [
      ['Top journals', $live('journals'), 'default'],
      ['Top publishers', $live('publishers', '<p>Counted with their imprints, so a publisher’s many journals add up together.</p>'), 'default'],
    ],
  ],
  [
    'title' => 'Funding and topics',
    'anchor' => 'funding-topics',
    'blocks' => [
      ['Top funders', $live('funders', '<p>Works that name the funder in their acknowledgments.</p>'), 'default'],
      ['Top research topics', $live('topics', '<p>Each work’s main topic, as OpenAlex classifies it.</p>'), 'default'],
    ],
  ],
  [
    'title' => 'Most cited',
    'anchor' => 'most-cited',
    'blocks' => [
      [NULL, $live('cited', '<p>The ten works with an LSU author since 2020 that other works cite most. Large collaborations, such as physics reviews written by hundreds of authors, often lead.</p>'), 'default'],
    ],
  ],
  [
    'title' => 'The full dashboard',
    'anchor' => 'dashboard',
    'blocks' => [
      [NULL, <<<'HTML'
<p>Colton Ray’s LSU Research Dashboard goes further: choose any range of years and every chart and table follows.</p>
<ul>
<li>Open access by type and over time, and estimated article processing charges.</li>
<li>Publication trends for the leading publishers.</li>
<li>The newest publications, searchable and sortable.</li>
<li>The most published LSU authors.</li>
</ul>
HTML, 'default'],
      [NULL, $link, 'callout'],
    ],
  ],
  [
    'title' => 'About the data',
    'anchor' => 'about-the-data',
    'blocks' => [
      ['OpenAlex', <<<'HTML'
<p><a href="https://openalex.org/">OpenAlex</a> is a free, open index of the world’s scholarly works, authors, sources and institutions, run by the nonprofit OurResearch. Its data is in the public domain, so anyone can check, reuse or build on these figures.</p>
<p>A work counts for LSU when OpenAlex links one of its authors to Louisiana State University (<a href="https://ror.org/05ect4e57">ROR 05ect4e57</a>). This page asks OpenAlex for its counts each night; the figures on it can differ slightly from the dashboard’s, which keeps its own daily copy.</p>
HTML, 'default'],
      ['Read with care', <<<'HTML'
<ul>
<li>Affiliations come from the publications themselves and are sometimes missing or wrong, so a few LSU works are absent and a few are counted that should not be.</li>
<li>Counts differ from Scopus, Web of Science and Google Scholar, which index different works.</li>
<li>Citations grow over time, so recent works look less cited than they will be.</li>
<li>The current year is partial, and works are still being added to it.</li>
</ul>
HTML, 'default'],
    ],
  ],
  [
    'title' => 'Questions',
    'anchor' => 'questions',
    'blocks' => [
      $has_card
        ? ['card', 'Ask about the dashboard', $staff_id]
        : ['Ask about the dashboard', '<p>Colton Ray, Scholarly Publications and Analytics Librarian, built the dashboard and can help with research impact, open access and publishing questions: <a href="mailto:coltonray@lsu.edu">coltonray@lsu.edu</a>, 225-578-4338.</p>', 'default'],
    ],
  ],
];

$fingerprint = substr(sha1(json_encode([$head, $galleries], JSON_THROW_ON_ERROR)), 0, 12);
$log_prefix = 'palefire_research_dashboard.php build';
$log_message = "$log_prefix $fingerprint";

$storage = \Drupal::entityTypeManager()->getStorage('node');
$found = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', $title)
  ->range(0, 1)->execute();
$node = $found ? $storage->load((int) reset($found)) : NULL;
$prefix = $apply ? '' : '[dry run] ';

echo $prefix . ($has_card ? "Questions: librarian card for Colton Ray (node/$staff_id)." : 'Questions: no librarian card (no Staff page titled "Colton Ray", or galleries do not take the card), so his contact is in words.') . PHP_EOL;
if (!\Drupal::state()->get('palefire.research_figures')) {
  echo $prefix . 'No research figures yet: run palefire_research_pull.php, or the parts wait and the key facts are left out.' . PHP_EOL;
}
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
  echo "{$prefix}Rebuild node/{$node->id()} as an exhibition in a new revision, unpublished." . PHP_EOL;
}
else {
  echo "{$prefix}Create '$title' as an unpublished exhibition page." . PHP_EOL;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];
$has_style = (bool) FieldConfig::loadByName('paragraph', 'guide_text', 'field_text_style');
$has_display = (bool) FieldConfig::loadByName('paragraph', 'exhibit_page', 'field_exhibit_display');
$paragraphs = [];
foreach ($galleries as $item) {
  $blocks = [];
  foreach ($item['blocks'] as $block) {
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
  $values = [
    'type' => 'exhibit_page',
    'field_section_title' => $item['title'],
    'field_section_anchor' => $item['anchor'],
    'field_guide_blocks' => $blocks,
  ];
  if ($has_display) {
    $values['field_exhibit_display'] = 'story';
  }
  $paragraphs[] = ['entity' => Paragraph::create($values)];
}

if (!$node) {
  $node = Node::create([
    'type' => 'modular_page',
    'title' => $title,
    'path' => ['alias' => '/' . ltrim($args['path'] ?? '/v2/research-dashboard', '/')],
  ]);
}
$fields = [
  'field_modular_type' => 'exhibition',
  'field_exhibit_title_style' => 'sans',
  'field_exhibit_kicker' => $head['kicker'],
  'field_exhibit_subtitle' => $head['subtitle'],
  'field_intro' => $html($head['intro']),
  'field_exhibit_keyfacts' => array_map(fn ($line) => ['value' => $line], $head['keyfacts']),
  'field_exhibit_prompt' => $head['prompt'],
  'field_exhibit_action' => $head['action'],
];
foreach ($fields as $name => $value) {
  if (isset($node_fields[$name])) {
    $node->set($name, $value);
  }
}
$node->setUnpublished();
$node->set('field_scroll_section', $paragraphs);
$node->setNewRevision(TRUE);
$node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
$node->setRevisionLogMessage($log_message);
$node->save();

echo "Saved '$title', unpublished: node/{$node->id()} (" . $node->toUrl('canonical', ['absolute' => TRUE])->toString() . '). Run: drush cr' . PHP_EOL;
