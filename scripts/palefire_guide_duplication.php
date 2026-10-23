<?php

/**
 * @file
 * Guide page: "Ordering Copies from Special Collections", the duplication
 * prices and terms (lib.lsu.edu/special#specialcollectionsduplication) as an
 * unpublished Modular page in guide mode. Run palefire_guide_setup.php first.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_duplication.php
 *   drush cr
 *
 * Optional arguments after "--":
 *   format=<text format>  Text format for the guide's text (default:
 *                         the WYSIWYG format).
 *   path=<alias>          URL alias (default: /v2/special/guides/duplication,
 *                         where the landing's "Order prices" button points).
 *
 * Prices first (the $10 order fee, then each kind of copy), then how to
 * order, copyright and permissions, and how to cite. Service details come
 * from the Special Collections Duplication tile on lib.lsu.edu/special
 * (October 2026). No tables: the prices are short lists, so the WYSIWYG
 * format needs nothing extra.
 *
 * Creates:
 * - Resources for the tools it links (found by link first, so existing
 *   Resources, e.g. from palefire_guide_special_collections.php, are reused).
 * - One Modular page, UNPUBLISHED, owned by Kyle Tanglao when a Staff node
 *   with that name exists. It stops if a guide page with the same title
 *   exists.
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
$args['path'] = ($args['path'] ?? '') !== '' ? $args['path'] : '/v2/special/guides/duplication';

foreach (['resource' => 'node_type', 'guide_section' => 'paragraphs_type'] as $id => $storage_id) {
  if (!\Drupal::entityTypeManager()->getStorage($storage_id)->load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_guide_setup.php first.");
  }
}

$title = 'Ordering Copies from Special Collections';
$found = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', $title)
  ->range(0, 1)->execute();
if ($found) {
  throw new \RuntimeException("A page titled '$title' already exists (node " . reset($found) . '). Delete it first to rebuild it.');
}

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
echo "Text format: $format" . PHP_EOL;
$skipped = [];

// Resources. ---------------------------------------------------------------

$resources = [];
$r = function (string $key, string $name, string $url, string $type, string $desc = '', string $citation = '', array $labels = []) use (&$resources): void {
  $ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
    ->condition('type', 'resource')
    ->condition('field_resource_link.uri', $url)
    ->range(0, 1)->execute();
  if ($ids) {
    $resources[$key] = (int) reset($ids);
    return;
  }
  $node = Node::create([
    'type' => 'resource',
    'title' => $name,
    'status' => 1,
    'field_resource_link' => ['uri' => $url],
    'field_resource_type' => $type,
    'field_resource_desc' => $desc,
    'field_resource_citation' => $citation,
    'field_resource_labels' => array_map(fn ($l) => ['value' => $l], array_slice($labels, 0, 3)),
    'field_resource_proxy' => FALSE,
  ]);
  $node->save();
  $resources[$key] = (int) $node->id();
};

$r('account', 'Special Collections account', 'https://specialcollections.lib.lsu.edu/logon', 'tool', 'Register, then request materials, book an appointment and place duplication orders.', '', ['Free']);
$r('catalog', 'Library Catalog', 'https://catalog.lib.lsu.edu', 'search', 'Books, journals, media and more that LSU Libraries owns or licenses. Check a record for the call number, format and current availability.');
$r('findingaids', 'Finding Aids', 'https://lib.lsu.edu/findingaids', 'search', 'Descriptions and box and folder lists for manuscript collections and University Archives.');
$r('ldl', 'Louisiana Digital Library', 'https://www.louisianadigitallibrary.org', 'database', 'Digitized photographs, manuscripts and oral histories, including many interviews you can listen to online.', '', ['Free', 'Online']);
$r('askspecial', 'Ask Us: Special Collections', 'https://askus.lib.lsu.edu/special', 'person', 'Send Special Collections staff a question, or browse answers to common ones.');

echo 'Resources ready: ' . count($resources) . PHP_EOL;

// Block helpers (same shapes as palefire_guide_special_collections.php). --

$ids = fn (array $keys) => array_map(fn ($k) => ['target_id' => $resources[$k]], $keys);
$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];
$keep = fn (array $values) => array_filter($values, fn ($v) => $v !== NULL);

$text = fn (?string $heading, string $body, string $style = 'default') => Paragraph::create($keep([
  'type' => 'guide_text',
  'field_block_title' => $heading,
  'field_block_body' => $html($body),
  'field_text_style' => $style,
]));

$list = fn (?string $heading, array $keys, string $intro = '') => Paragraph::create($keep([
  'type' => 'resource_list',
  'field_block_title' => $heading,
  'field_block_intro' => $intro !== '' ? $html($intro) : NULL,
  'field_resources' => $ids($keys),
]));

$sections = [];
$section = function (string $heading, array $blocks, string $theme = 'light') use (&$sections): void {
  $values = [
    'type' => 'guide_section',
    'field_section_title' => $heading,
    'field_guide_blocks' => array_values(array_filter($blocks)),
  ];
  if (FieldConfig::loadByName('paragraph', 'guide_section', 'field_section_theme')) {
    $values['field_section_theme'] = $theme;
  }
  $sections[] = Paragraph::create($values);
};

// Sections. ----------------------------------------------------------------

$section('Prices', [
  $text(NULL, '<p><strong>Every order has a $10 processing fee</strong> on top of the prices below. One fee covers several requests submitted at the same time.</p>', 'callout'),
  $text('Photocopies and reference scans', <<<'HTML'
<ul>
<li><strong>$0.50 a page</strong> for low-resolution PDF scans (by email) or photocopies (in print).</li>
</ul>
<p>Images from Special Collections' digital collections are free to download, at low resolution, from the <a href="http://louisianadigitallibrary.org/lsu-sc">Louisiana Digital Library</a>.</p>
HTML),
  $text('High-resolution scans for publication', <<<'HTML'
<ul>
<li><strong>$1 a scan</strong>, as JPEG or TIFF files.</li>
<li><strong>$5 a disc</strong> if you'd like the files on CD or DVD.</li>
</ul>
<p>Photographs can be ordered as staff-made scans or photographic prints. Photographs can't be photocopied, or scanned with your own scanner.</p>
HTML),
  $text('Other copies', <<<'HTML'
<ul>
<li><strong>$25</strong> for a PDF of an LSU dissertation or thesis, when it isn't already available online.</li>
<li><strong>$10 a disc</strong> for a copy of a CD or DVD from the collections.</li>
<li><strong>Microfilm copies are currently unavailable.</strong> When they are offered: $60 for a reel-to-reel copy (only when LSU holds the negative), or $45 for a PDF of a reel.</li>
</ul>
HTML),
]);

$section('How to order', [
  $text(NULL, <<<'HTML'
<p>Special Collections makes copies for a fee, for personal or research use only. Every order needs a staff member's approval, and staff can refuse or change an order because of an item's format, condition or restrictions. Orders go through your Special Collections account; they can't be placed over the phone.</p>
<ol>
<li><strong>Sign in to your Special Collections account.</strong> LSU students, faculty and staff use single sign-on. Everyone else signs in or registers as a Guest (choose First Time Users to register).</li>
<li><strong>Find the catalog record</strong> for each book, manuscript collection or other item you want copied.</li>
<li><strong>Choose "Request Item"</strong> under the record's Available tab.</li>
<li><strong>Choose "Switch to Duplication Order Form"</strong> at the top right of the request form.</li>
<li><strong>Say exactly what to copy.</strong> For manuscript collections, give box and folder numbers from the catalog record or the finding aid.</li>
<li><strong>Pay the invoice.</strong> Staff review the order and email an invoice with payment instructions, usually within 24–48 hours (not counting weekends and holidays).</li>
</ol>
HTML),
  $text(NULL, '<p><strong>Questions about an order, or the materials to copy?</strong> Ask Special Collections through Ask Us.</p>', 'callout'),
  $list(NULL, ['account', 'catalog', 'findingaids', 'askspecial']),
]);

$section('Copyright and permissions', [
  $text(NULL, <<<'HTML'
<p>Copies of Special Collections materials, whether you make them or Special Collections does, fall under Section 107 (fair use) and Section 108 (libraries and archives) of the United States Copyright Act.</p>
<p>LSU owns the objects in Special Collections, but not their copyright, except where it has been explicitly transferred; LSU Libraries holds no copyrights. Special Collections can't search for copyright holders or advise you on copyright law.</p>
<p>A copy is for private study and research only; it isn't permission to use the material any other way. Whether your use is lawful, or needs the copyright holder's permission, is for you to decide, and you're responsible for it.</p>
<ul>
<li>LSU Libraries doesn't grant or deny permission to publish or otherwise distribute material.</li>
<li>Clearing copyright, trademark, patent, privacy and publicity rights is your responsibility.</li>
<li>Special Collections doesn't provide signed letters of permission or sign other permission forms.</li>
</ul>
HTML),
  $text(NULL, '<p>YOU AGREE TO INDEMNIFY AND HOLD HARMLESS LOUISIANA STATE UNIVERSITY, ITS OFFICERS, EMPLOYEES AND AGENTS FROM AND AGAINST ALL SUITS, CLAIMS, ACTIONS AND EXPENSES ARISING OUT OF YOUR USE OF DUPLICATIONS PROVIDED BY SPECIAL COLLECTIONS.</p>', 'callout'),
]);

$section('Citing Special Collections', [
  $text(NULL, '<p>Material from Special Collections must carry a citation or credit line in the form below.</p>'),
  $text('Manuscript collections', <<<'HTML'
<p>Name of the Manuscript Collection, Mss. [number], Louisiana and Lower Mississippi Valley Collections, LSU Libraries, Baton Rouge, La.</p>
<p>Spell out "Louisiana and Lower Mississippi Valley Collections" the first time; after that, "LLMVC" will do. If your work has a list of abbreviations, you can use "LLMVC" throughout.</p>
HTML),
  $text('University Archives', <<<'HTML'
<p>Name of the originating office followed by "Records", RG [record group number], Louisiana State University Archives, LSU Libraries, Baton Rouge, La.</p>
<p>Spell out "Louisiana State University Archives" the first time; after that, "LSUA" will do. If your work has a list of abbreviations, you can use "LSUA" throughout.</p>
HTML),
]);

$section('Get help', [
  $text(NULL, '<p>Ask at the Reading Room desk in Hill Memorial Library, call Special Collections at (225) 578-6568, email <a href="mailto:special@lsu.edu">special@lsu.edu</a>, or send a question through Ask Us.</p>'),
  $list('Special Collections tools', ['account', 'ldl', 'askspecial']),
]);

// The page. ----------------------------------------------------------------

$values = [
  'type' => 'modular_page',
  'title' => $title,
  'status' => 0,
  'field_scroll_section' => $sections,
];
$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');
if (isset($node_fields['field_modular_type'])) {
  $values['field_modular_type'] = 'guide';
}
if (isset($node_fields['field_intro'])) {
  $values['field_intro'] = $html('<p>What scans, photocopies and other copies of Special Collections materials cost, how to order them through your Special Collections account, and the copyright and citation terms that come with them.</p>');
}

// Owner: Kyle Tanglao's Staff node.
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

if (isset($node_fields['field_guide_subjects'])) {
  $vocabs = array_keys($node_fields['field_guide_subjects']->getSetting('handler_settings')['target_bundles'] ?? []);
  $terms = $vocabs ? \Drupal::entityQuery('taxonomy_term')->accessCheck(FALSE)
    ->condition('vid', $vocabs, 'IN')->condition('name', 'Research Guides')->execute() : [];
  $values['field_guide_subjects'] = array_map(fn ($tid) => ['target_id' => $tid], array_values($terms));
}
$values['path'] = ['alias' => '/' . ltrim($args['path'], '/')];

$page = Node::create($values);
$page->setRevisionLogMessage('Guide: Ordering Copies from Special Collections (palefire_guide_duplication.php).');
$page->save();

echo 'Created unpublished guide page: node/' . $page->id() . ' (' . $page->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
foreach ($skipped as $line) {
  echo 'Skipped: ' . $line . PHP_EOL;
}
echo 'Review it, then publish. Run: drush cr' . PHP_EOL;
