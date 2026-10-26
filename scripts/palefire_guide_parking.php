<?php

/**
 * @file
 * Guide pages: visitor parking for the Main Library and for Special
 * Collections at Hill Memorial Library, as two unpublished Modular pages in
 * guide mode. Run palefire_guide_setup.php first.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_parking.php
 *   drush cr
 *
 * Optional arguments after "--":
 *   only=main|hill  Build one guide only (default: both).
 *   format=<text format>  Text format for the guides' text (default:
 *                         the WYSIWYG format).
 *   main_path=<alias>  URL alias (default /v2/guides/parking-main-library).
 *   hill_path=<alias>  URL alias (default /v2/guides/parking-hill-memorial-library).
 *
 * Facts are from LSU Parking & Transportation Services (checked 2026-10-26):
 * lsu.edu/parking/permit/visitor.php, …/permit/ada.php and
 * …/resources/union-square-garage.php, and for Hill the Special Collections
 * travel page (guides.lib.lsu.edu/specialcollectionsintro/travel). Rules
 * neither source states (evenings and weekends, game days, building
 * entrances) are marked "[Editor: …]".
 *
 * Creates:
 * - Resources for the parking pages (found by link first, so existing
 *   Resources are reused and the two guides share them).
 * - Up to two Modular pages, UNPUBLISHED, owned by Kyle Tanglao when a Staff
 *   node with that name exists. A guide whose title already exists is
 *   skipped, never updated.
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

foreach (['resource' => 'node_type', 'guide_section' => 'paragraphs_type'] as $id => $storage_id) {
  if (!\Drupal::entityTypeManager()->getStorage($storage_id)->load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_guide_setup.php first.");
  }
}

$only = $args['only'] ?? NULL;
if ($only !== NULL && !in_array($only, ['main', 'hill'], TRUE)) {
  throw new \RuntimeException("only= must be 'main' or 'hill'.");
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

$r('visitor', 'Visitor parking options', 'https://www.lsu.edu/parking/permit/visitor.php', 'website', 'Pay-to-park locations, the visitor day permit and current rates, from LSU Parking & Transportation Services.');
$r('portal', 'LSU Parking Portal', 'https://lsu.t2hosted.com/cmn/auth_ext.aspx', 'tool', 'Buy a visitor day permit ahead of time: choose "Manage Account", then log in as "Guest".', '', ['Guest login']);
$r('map', 'Pay-to-park map', 'https://map.lsu.edu/?id=743#!ce/81108?ct/24241', 'tool', 'LSU\'s interactive campus map with the pay-to-park visitor areas marked.');
$r('garage', 'Union Square Parking Garage', 'https://www.lsu.edu/parking/resources/union-square-garage.php', 'website', 'Hourly visitor parking on the second floor. Enter from East Campus Drive.');
$r('ada', 'Accessible parking', 'https://www.lsu.edu/parking/permit/ada.php', 'website', 'How visitors with a state disability placard or plate park on campus.');
$r('appeals', 'Appeal a parking citation', 'https://www.lsu.edu/parking/citations/contesting.php', 'website', 'The visitor appeals process, if you receive a ticket.');
$r('hours', 'Library hours', 'https://www.lib.lsu.edu/hours', 'website', 'Opening hours for every LSU Libraries location.');
$r('special', 'Special Collections', 'https://www.lib.lsu.edu/special', 'website', 'Reading room hours, appointments and how to request materials before your visit.');
$r('askus', 'Ask a librarian', 'https://askus.lib.lsu.edu/ask', 'person', 'Text, call, email or chat with the library.');

echo 'Resources ready: ' . count($resources) . PHP_EOL;

// Block helpers (same shapes as palefire_guide_demo.php). ----------------

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

$accordion = function (?string $heading, array $items, string $intro = '') use ($ids, $html, $keep): Paragraph {
  $children = [];
  foreach ($items as $item) {
    $children[] = Paragraph::create($keep([
      'type' => 'accordion_item',
      'field_item_title' => $item[0],
      'field_block_body' => !empty($item[1]) ? $html($item[1]) : NULL,
      'field_resources' => !empty($item[2]) ? $ids($item[2]) : NULL,
    ]));
  }
  return Paragraph::create($keep([
    'type' => 'guide_accordion',
    'field_block_title' => $heading,
    'field_block_intro' => $intro !== '' ? $html($intro) : NULL,
    'field_accordion_items' => $children,
  ]));
};

$section = function (string $heading, array $blocks, string $theme = 'light'): Paragraph {
  $values = [
    'type' => 'guide_section',
    'field_section_title' => $heading,
    'field_guide_blocks' => array_values(array_filter($blocks)),
  ];
  if (FieldConfig::loadByName('paragraph', 'guide_section', 'field_section_theme')) {
    $values['field_section_theme'] = $theme;
  }
  return Paragraph::create($values);
};

// Shared blocks: both libraries use the same visitor lots. -----------------

$pay_callout = fn () => $text(NULL, '<p><strong>On weekdays from 7 a.m. to 8 p.m., visitors pay to park.</strong> Pay-to-park spaces are $3.00 an hour, by debit or credit card only. Note your space number and license plate before you pay.</p>', 'callout');

$lots_table = fn (string $walk) => $text('Pay-to-park lots nearby', <<<HTML
<table>
<caption>Visitor parking closest to the library</caption>
<thead><tr><th scope="col">Where</th><th scope="col">How to find it</th><th scope="col">Walk</th></tr></thead>
<tbody>
<tr><th scope="row">Union Square Parking Garage</th><td>Enter from East Campus Drive. Visitor spaces are on the 2nd floor only.</td><td>$walk</td></tr>
<tr><th scope="row">Welcome Center Lot</th><td>Corner of Highland Road and Dalrymple Drive</td><td>$walk</td></tr>
<tr><th scope="row">Thomas Boyd Visitor Lot</th><td>Near Memorial Tower</td><td>$walk</td></tr>
</tbody>
</table>
<p>All three are $3.00 an hour on weekdays, 7 a.m. to 8 p.m. The pay-to-park map shows each visitor area.</p>
HTML);

$day_permit = fn (string $who) => $text('A day permit', <<<HTML
<p>$who a visitor day permit is often simpler than paying by the hour.</p>
<ul>
<li><strong>Cost:</strong> \$12 a day, for up to 10 consecutive days. You can buy it ahead of time.</li>
<li><strong>Where it works:</strong> any Commuter or X lot. The campus map shows which lots those are.</li>
<li><strong>How to buy:</strong> open the LSU Parking Portal, choose "Manage Account" and log in as "Guest".</li>
</ul>
HTML);

$accessible = fn (string $entrance) => $text('Accessible parking', <<<HTML
<p>Visitors with a disability don't need an LSU permit. Display your valid state-issued disability placard or license plate, then either pay to park or buy a visitor day permit. The usual visitor fees and event restrictions still apply.</p>
<p>$entrance</p>
HTML);

$questions = fn () => $accordion('Common questions', [
  ['Do I pay in the evening or at the weekend?', '<p>Pay-to-park is enforced on weekdays, 7 a.m. to 8 p.m. [Editor: confirm with Parking & Transportation Services what applies outside those hours and at weekends, and say it here.]</p>'],
  ['What about home game days and big events?', '<p>Event restrictions apply to visitor parking too, and lots near the stadium fill or close on game days. [Editor: link LSU\'s game-day parking page, and say whether the library keeps its usual hours.]</p>'],
  ['I have an LSU parking permit. Do I need anything else?', '<p>No. Park in the lots your permit allows.</p>'],
  ['I got a ticket. What now?', '<p>Visitors can appeal a citation through Parking & Transportation Services.</p>', ['appeals']],
]);

$parking_help = fn () => $text('Parking questions', '<p>LSU Parking & Transportation Services: call <a href="tel:+12255785000">(225) 578-5000</a> or email <a href="mailto:parking@lsu.edu">parking@lsu.edu</a>.</p>');

// The guides. ---------------------------------------------------------------

$guides = [];

$guides['main'] = [
  'title' => 'Visitor Parking at the Main Library',
  'path' => $args['main_path'] ?? '/v2/guides/parking-main-library',
  'intro' => '<p>Where to park when you visit the Main Library without an LSU parking permit: the closest pay-to-park lots, the day permit for longer stays, and accessible parking.</p>',
  'sections' => [
    $section('Before you come', [
      $pay_callout(),
      $text('In short', <<<'HTML'
<ol>
<li><strong>Park in a pay-to-park visitor area</strong>, such as the Union Square Garage's 2nd floor, or buy a day permit before you arrive.</li>
<li><strong>Pay by card</strong> as the signs at your space show, with your space number and license plate to hand.</li>
<li><strong>Walk to the Main Library</strong> on the LSU Quad, next door to Hill Memorial Library.</li>
</ol>
<p>[Editor: add the Main Library's street address and the entrance visitors should use.]</p>
HTML),
      $list(NULL, ['hours']),
    ]),
    $section('Where to park', [
      $lots_table('5–10 min [Editor: confirm]'),
      $list(NULL, ['map', 'garage', 'visitor']),
    ]),
    $section('Staying all day', [
      $day_permit('Studying or working here for most of the day?'),
      $list(NULL, ['portal']),
    ]),
    $section('Accessible parking', [
      $accessible('[Editor: name the Main Library\'s accessible entrance and the nearest accessible spaces to it.]'),
      $list(NULL, ['ada']),
    ]),
    $section('Questions', [
      $questions(),
    ]),
    $section('Get help', [
      $parking_help(),
      $list('Ask the library', ['askus']),
    ]),
  ],
];

$guides['hill'] = [
  'title' => 'Visitor Parking at Hill Memorial Library',
  'path' => $args['hill_path'] ?? '/v2/guides/parking-hill-memorial-library',
  'intro' => '<p>Where to park when you visit Special Collections at Hill Memorial Library without an LSU parking permit. Parking right by the building is limited, so plan on a 5 to 10 minute walk.</p>',
  'sections' => [
    $section('Before you come', [
      $pay_callout(),
      $text('In short', <<<'HTML'
<ol>
<li><strong>Plan your visit with Special Collections first.</strong> Check reading room hours, and request materials ahead so they are ready when you arrive.</li>
<li><strong>Park in a pay-to-park visitor area</strong>, or buy a day permit if you are staying for several hours or days.</li>
<li><strong>Walk to Hill Memorial Library</strong>, 95 Field House Drive. It is near the LSU Quad, between the Main Library and the Cox Communications Academic Center, just east of the LSU Campus Mounds.</li>
</ol>
HTML),
      $list(NULL, ['special']),
    ]),
    $section('Where to park', [
      $lots_table('5–10 min'),
      $list(NULL, ['map', 'garage', 'visitor']),
    ]),
    $section('Researching for several days', [
      $day_permit('Working in the reading room for the day, or coming back on several days?'),
      $list(NULL, ['portal']),
    ]),
    $section('Accessible parking', [
      $accessible('[Editor: name Hill Memorial Library\'s accessible entrance and the nearest accessible spaces to it.]'),
      $list(NULL, ['ada']),
    ]),
    $section('Questions', [
      $questions(),
    ]),
    $section('Get help', [
      $parking_help(),
      $text('Your visit', '<p>Special Collections front desk: <a href="tel:+12255786544">(225) 578-6544</a>. Research help at the reference desk: <a href="tel:+12255786568">(225) 578-6568</a>.</p>'),
    ]),
  ],
];

// Owner: Kyle Tanglao's Staff node. ---------------------------------------

$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');
$owner = NULL;
$owner_field = $node_fields['field_guide_owner'] ?? NULL;
if ($owner_field) {
  $staff_bundles = array_keys($owner_field->getSetting('handler_settings')['target_bundles'] ?? []);
  $match = $staff_bundles ? \Drupal::entityQuery('node')->accessCheck(FALSE)
    ->condition('type', $staff_bundles, 'IN')
    ->condition('title', 'Kyle', 'CONTAINS')
    ->condition('title', 'Tanglao', 'CONTAINS')
    ->range(0, 1)->execute() : [];
  $owner = $match ? (int) reset($match) : NULL;
  if ($owner === NULL) {
    echo 'Skipped: Guide owner: no Staff node titled like "Kyle Tanglao" was found. Set it on each page.' . PHP_EOL;
  }
}

foreach ($guides as $key => $guide) {
  if ($only !== NULL && $only !== $key) {
    continue;
  }
  $found = \Drupal::entityQuery('node')->accessCheck(FALSE)
    ->condition('type', 'modular_page')
    ->condition('title', $guide['title'])
    ->range(0, 1)->execute();
  if ($found) {
    echo "Skipped: '{$guide['title']}' already exists (node " . reset($found) . '). Delete it first to rebuild it.' . PHP_EOL;
    continue;
  }

  $values = [
    'type' => 'modular_page',
    'title' => $guide['title'],
    'status' => 0,
    'field_scroll_section' => $guide['sections'],
    'path' => ['alias' => '/' . ltrim($guide['path'], '/')],
  ];
  if (isset($node_fields['field_modular_type'])) {
    $values['field_modular_type'] = 'guide';
  }
  if (isset($node_fields['field_intro'])) {
    $values['field_intro'] = $html($guide['intro']);
  }
  if ($owner !== NULL) {
    $values['field_guide_owner'] = ['target_id' => $owner];
  }

  $page = Node::create($values);
  $page->setRevisionLogMessage("Guide: {$guide['title']} (palefire_guide_parking.php).");
  $page->save();
  echo 'Created unpublished guide page: node/' . $page->id() . ' (' . $page->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
}

echo 'Fill in the [Editor: …] notes, review, then publish. Run: drush cr' . PHP_EOL;
