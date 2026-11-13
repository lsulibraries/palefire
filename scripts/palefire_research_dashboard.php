<?php

/**
 * @file
 * "LSU Research, by the Numbers": a dashboard of LSU research from OpenAlex,
 * as an UNPUBLISHED Modular page of the plain kind (no mode: the breadcrumb
 * bar, the title, banded sections). Run palefire_guide_setup.php first.
 *
 *   drush php:script scripts/palefire_research_dashboard.php
 *   drush php:script scripts/palefire_research_dashboard.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1.
 *
 * No title page: the figures show on first load. Two sections:
 * - Research since 2020: one sentence, then the dashboard, a text block that
 *   is only "[research:dashboard]" (includes/research-figures.html.twig):
 *   figures in a row, then a grid of panels (works each year, open access,
 *   kinds of work, top journals, publishers, funders and topics, the most
 *   cited works), the full width of the column.
 * - About the data: OpenAlex, what to read with care, and whom to ask
 *   (Colton Ray's librarian card when his Staff page exists, else his
 *   contact in words).
 *
 * The figures are live: palefire_research_pull.php fetches them from
 * OpenAlex each night (Drupal\palefire\ResearchFigures, cron). Run the pull
 * once before the page is first viewed; until then the dashboard says the
 * figures are on their way.
 *
 * This script owns the page's words and can be run again; each build is a
 * new revision and leaves the page unpublished. A page built as an
 * exhibition by an earlier version goes back to the plain kind, its title
 * page fields emptied. It stops if the page was edited in Drupal (force=1
 * overrides).
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
foreach (['guide_section', 'guide_text'] as $id) {
  if (!$paragraph_types->load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_guide_setup.php first.");
  }
}
$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');

$title = 'LSU Research, by the Numbers';

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

// Colton's Staff page, for the librarian card, if sections take the card.
// None: his contact in words.
$blocks_field = FieldConfig::loadByName('paragraph', 'guide_section', 'field_guide_blocks');
$allowed = $blocks_field ? ($blocks_field->getSetting('handler_settings')['target_bundles'] ?? []) : [];
$staff = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'staff')
  ->condition('title', 'Colton Ray')
  ->range(0, 1)->execute();
$staff_id = $staff ? (int) reset($staff) : NULL;
$has_card = $staff_id && $paragraph_types->load('librarian_card') && (!$allowed || isset($allowed['librarian_card']));

// The page's lede where it is listed or shared (field_intro); the page
// itself opens on the section's sentence and the figures.
$intro = '<p>A dashboard of LSU research since 2020: what is published, where it appears, who funds it, how often it is cited and how much of it anyone can read for free, from open OpenAlex data, updated each night.</p>';

// Sections: title, anchor and blocks. A block is [heading, body, style], or
// ['card', heading, staff node id].
$sections = [
  [
    'title' => 'Research since 2020',
    'anchor' => 'dashboard',
    'blocks' => [
      [NULL, '<p>Every work with an LSU author since 2020, counted from open OpenAlex data and updated each night.</p><p>[research:dashboard]</p>', 'default'],
    ],
  ],
  [
    'title' => 'About the data',
    'anchor' => 'about-the-data',
    'blocks' => [
      ['OpenAlex', <<<'HTML'
<p><a href="https://openalex.org/">OpenAlex</a> is a free, open index of the world’s scholarly works, authors, sources and institutions, run by the nonprofit OurResearch. Its data is in the public domain, so anyone can check, reuse or build on these figures.</p>
<p>A work counts for LSU when OpenAlex links one of its authors to Louisiana State University (<a href="https://ror.org/05ect4e57">ROR 05ect4e57</a>). Open access is OpenAlex’s reading of each work: diamond and gold are open journals, hybrid is an open article in a subscription journal, green is a free copy in a repository, bronze is free on the publisher’s site without an open license.</p>
HTML, 'default'],
      ['Read with care', <<<'HTML'
<ul>
<li>Affiliations come from the publications themselves and are sometimes missing or wrong, so a few LSU works are absent and a few are counted that should not be.</li>
<li>Counts differ from Scopus, Web of Science and Google Scholar, which index different works.</li>
<li>Citations grow over time, so recent works look less cited than they will be.</li>
<li>The current year is partial, and works are still being added to it.</li>
</ul>
HTML, 'default'],
      $has_card
        ? ['card', 'Questions about these figures', $staff_id]
        : ['Questions about these figures', '<p>Colton Ray, Scholarly Publications and Analytics Librarian, can help with research impact, open access and publishing questions: <a href="mailto:coltonray@lsu.edu">coltonray@lsu.edu</a>, 225-578-4338.</p>', 'default'],
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

echo $prefix . ($has_card ? "Questions: librarian card for Colton Ray (node/$staff_id)." : 'Questions: no librarian card (no Staff page titled "Colton Ray", or sections do not take the card), so his contact is in words.') . PHP_EOL;
if (!\Drupal::state()->get('palefire.research_figures')) {
  echo $prefix . 'No research figures yet: run palefire_research_pull.php, or the dashboard waits.' . PHP_EOL;
}
if ($node) {
  $latest = $storage->getLatestRevisionId($node->id());
  if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
    throw new \RuntimeException("'$title' has a newer draft (revision $latest). Publish or discard it first.");
  }
  $last_log = (string) $node->getRevisionLogMessage();
  if ($last_log === $log_message) {
    echo "'$title' (node/{$node->id()}) already has this build. Nothing to do." . PHP_EOL;
    return;
  }
  if (!str_starts_with($last_log, $log_prefix) && !$force) {
    throw new \RuntimeException("'$title' (node/{$node->id()}) was last saved outside this script (log: \"$last_log\"). Copy anything worth keeping into this script, then run with force=1.");
  }
  echo "{$prefix}Rebuild node/{$node->id()} as a plain page in a new revision" . ($node->isPublished() ? ', still published' : ', unpublished') . '.' . PHP_EOL;
}
else {
  echo "{$prefix}Create '$title' as an unpublished page." . PHP_EOL;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];
$has_style = (bool) FieldConfig::loadByName('paragraph', 'guide_text', 'field_text_style');
$has_anchor = (bool) FieldConfig::loadByName('paragraph', 'guide_section', 'field_section_anchor');
$paragraphs = [];
foreach ($sections as $item) {
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
    'type' => 'guide_section',
    'field_section_title' => $item['title'],
    'field_guide_blocks' => $blocks,
  ];
  if ($has_anchor) {
    $values['field_section_anchor'] = $item['anchor'];
  }
  $paragraphs[] = ['entity' => Paragraph::create($values)];
}

if (!$node) {
  $node = Node::create([
    'type' => 'modular_page',
    'title' => $title,
    'status' => 0,
    'path' => ['alias' => '/' . ltrim($args['path'] ?? '/v2/research-dashboard', '/')],
  ]);
}
// The plain kind: no mode, and nothing left of an earlier title page.
foreach (['field_modular_type', 'field_exhibit_kicker', 'field_exhibit_subtitle', 'field_exhibit_keyfacts', 'field_exhibit_prompt', 'field_exhibit_action', 'field_exhibit_title_style'] as $name) {
  if (isset($node_fields[$name])) {
    $node->set($name, NULL);
  }
}
if (isset($node_fields['field_intro'])) {
  $node->set('field_intro', $html($intro));
}
$node->set('field_scroll_section', $paragraphs);
$node->setNewRevision(TRUE);
$node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
$node->setRevisionLogMessage($log_message);
$node->save();

echo "Saved '$title' (" . ($node->isPublished() ? 'published' : 'unpublished') . "): node/{$node->id()}. Run: drush cr" . PHP_EOL;
