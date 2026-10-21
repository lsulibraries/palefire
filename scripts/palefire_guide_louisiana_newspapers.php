<?php

/**
 * @file
 * Guide page: "Louisiana Newspapers", the LibGuide
 * (guides.lib.lsu.edu/c.php?g=440633) as an unpublished Modular page in
 * guide mode. Run palefire_guide_setup.php and
 * palefire_louisiana_newspapers_setup.php first.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_louisiana_newspapers.php -- path=/v2/guides/louisiana-newspapers
 *   drush cr
 *
 * Optional arguments after "--":
 *   format=<text format>  Text format for the guide's text (default:
 *                         the WYSIWYG format).
 *   path=<alias>          URL alias, e.g. path=/v2/guides/louisiana-newspapers
 *
 * The LibGuide's text is carried over word for word (October 2026). The one
 * change, approved: a missing space in the Gretna Courier's dates
 * ("1891-1892,1895"). Its six pages become sections, the parish map first:
 *
 * 1. Newspapers by Parish: a Parish map with one Parish item per parish,
 *    holding the LibGuide's "Louisiana Digital Newspapers" and "Louisiana
 *    Newspaper Websites" tabs for that parish, plus the major titles on
 *    microfilm for the parishes of the seven cities on "Louisiana
 *    Newspapers on Microfilm". Family trees are added by the theme.
 * 2. Louisiana Digital Newspapers: the Databases tab, and the collections
 *    from lib.lsu.edu/louisiananewspapers.
 * 3. Louisiana Newspapers on Microfilm.
 * 4. Louisiana Newspaper Project Microfilm Indexes.
 * 5. Digitizing Louisiana Newspapers Project.
 * 6. Family Trees for Louisiana Newspapers: the text and a searchable list.
 * 7. Contact, from lib.lsu.edu/louisiananewspapers.
 *
 * The parish lists (over 1,400 entries) are in data/louisiana-newspapers.json,
 * made from the LibGuide's HTML.
 *
 * Creates one Modular page, UNPUBLISHED. It stops if a Modular page with
 * the same title exists.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;

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

foreach (['guide_section', 'guide_text', 'guide_accordion', 'accordion_item'] as $id) {
  if (!ParagraphsType::load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_guide_setup.php first.");
  }
}
foreach (['parish_map', 'parish_entry', 'family_trees'] as $id) {
  if (!ParagraphsType::load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_louisiana_newspapers_setup.php first.");
  }
}

$data_file = __DIR__ . '/data/louisiana-newspapers.json';
$data = json_decode((string) file_get_contents($data_file), TRUE);
if (!is_array($data) || count($data['parishes'] ?? []) !== 64) {
  throw new \RuntimeException("Couldn't read the 64 parishes from $data_file.");
}

$title = 'Louisiana Newspapers';
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

// Block helpers (same shapes as palefire_guide_special_collections.php). ---

$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];
$keep = fn (array $values) => array_filter($values, fn ($v) => $v !== NULL);

$text = fn (?string $heading, string $body, string $style = 'default') => Paragraph::create($keep([
  'type' => 'guide_text',
  'field_block_title' => $heading,
  'field_block_body' => $html($body),
  'field_text_style' => $style,
]));

$accordion = function (?string $heading, array $items) use ($html, $keep): Paragraph {
  $children = [];
  foreach ($items as [$item_title, $body]) {
    $children[] = Paragraph::create([
      'type' => 'accordion_item',
      'field_item_title' => $item_title,
      'field_block_body' => $html($body),
    ]);
  }
  return Paragraph::create($keep([
    'type' => 'guide_accordion',
    'field_block_title' => $heading,
    'field_accordion_items' => $children,
  ]));
};

$sections = [];
$section = function (string $heading, array $blocks) use (&$sections): void {
  $values = [
    'type' => 'guide_section',
    'field_section_title' => $heading,
    'field_guide_blocks' => array_values(array_filter($blocks)),
  ];
  if (FieldConfig::loadByName('paragraph', 'guide_section', 'field_section_theme')) {
    $values['field_section_theme'] = 'light';
  }
  $sections[] = Paragraph::create($values);
};

// Microfilm: the major titles in the LSU Library, room 141, by city (the
// LibGuide's "Louisiana Newspapers on Microfilm"), keyed by the city's
// parish. Section 3 lists them by city; the parish map shows each city
// under its parish.
$microfilm = [
  'rapides' => ['Alexandria newspapers', [
    '<em>The Daily Town Talk</em> (1901) on MICROFILM 309',
    '<em>The Alexandria Daily Town Talk</em> (1901-1999) on MICROFILM 309',
    '<em>The Town Talk</em> (1999) on MICROFILM 309',
  ]],
  'east_baton_rouge' => ['Baton Rouge newspapers', [
    '<em>Daily State-Times</em> (1908-1910) on MICROFILM 55',
    '<em>The New Advocate</em> (1910-1913) on MICROFILM 55',
    '<em>State Times Advocate</em> (1913-1991) on MICROFILM 55',
    '<em>Morning Advocate</em> (1925-1992) on MICROFILM 77',
    '<em>The Advocate</em> (1992-present) on MICROFILM 77',
  ]],
  'lafayette' => ['Lafayette newspapers', [
    '<em>The Lafayette Advertiser</em> (1900-1914) on MICROFILM 255',
    '<em>The Daily Advertiser</em> (1914-present) on MICROFILM 256',
  ]],
  'calcasieu' => ['Lake Charles newspapers', [
    '<em>Lake Charles Daily American</em> (1901-1910) on MICROFILM 410',
    '<em>Lake Charles Daily American-Press</em> (1910-1912) on MICROFILM 410',
    '<em>Lake Charles American-Press</em> (1912-1998) on MICROFILM 410',
    '<em>American Press</em> (1998-2015) on MICROFILM 410',
  ]],
  'ouachita' => ['Monroe newspapers', [
    '<em>The Monroe News-Star</em> (1909-1979) on MICROFILM 2184',
    '<em>The News-Star</em> (1979-1980) on MICROFILM 2184',
    '<em>Monroe Morning World</em> (1929-1978) on MICROFILM 2185',
    '<em>Morning World</em> (1978-1980) on MICROFILM 2185',
    '<em>News-Star-World</em> (1980-1988) on MICROFILM 2184',
    '<em>The News-Star</em> (1988-present) on MICROFILM 2184',
  ]],
  'orleans' => ['New Orleans newspapers', [
    '<em>The Daily Picayune</em> (1901-1914) on MICROFILM 138',
    '<em>The Times-Democrat, The Daily Picayune</em> (1914) on MICROFILM 138',
    '<em>The Times-Picayune</em> (1914-1980) on MICROFILM 138',
    '<em>Times-Picayune, The States-Item</em> (1980-1986) on MICROFILM 138',
    '<em>The Times-Picayune</em> (1986-present) on MICROFILM 138',
    '<em>The Daily States</em> (1916-1918) on MICROFILM 1707',
    '<em>New Orleans States</em> (1918-1958) on MICROFILM 1707',
    '<em>The Daily Item</em> (1901-1902) on MICROFILM 1708',
    '<em>The New Orleans Item</em> (1902-1958) on MICROFILM 1708',
    '<em>The Morning Tribune</em> (1924-1937) on MICROFILM 1708',
    '<em>New Orleans States and New Orleans Item</em> (1958-1960) on MICROFILM 1709',
    '<em>New Orleans States-Item</em> (1960-1970) on MICROFILM 1709',
    '<em>The States-Item</em> (1970-1980) on MICROFILM 1709',
    '<em>The Advocate</em> Daily New Orleans edition (2012-2013) on MICROFILM 77',
    '<em>The New Orleans Advocate</em> (2013-present) on MICROFILM 77 &amp; MICROFILM 6188',
  ]],
  'caddo' => ['Shreveport newspapers', [
    '<em>Shreveport Evening Journal</em> (1901) on MICROFILM 63',
    '<em>Shreveport Journal</em> (1902-1991) on MICROFILM 63',
    '<em>Shreveport Times</em> (1901-1979) on MICROFILM 497',
    '<em>The Times</em> (1979-present) on MICROFILM 497',
  ]],
];
$ul = fn (array $items) => '<ul><li>' . implode('</li><li>', $items) . '</li></ul>';

// 1. The parish map. -------------------------------------------------------

$entries = [];
foreach ($data['parishes'] as $key => $parish) {
  $values = [
    'type' => 'parish_entry',
    'field_parish' => $key,
    'field_parish_digital' => $parish['digital'] !== '' ? $html($parish['digital']) : NULL,
    'field_parish_websites' => $parish['websites'] !== '' ? $html($parish['websites']) : NULL,
    'field_parish_microfilm' => isset($microfilm[$key]) ? $html('<p><strong>' . $microfilm[$key][0] . '</strong></p>' . $ul($microfilm[$key][1])) : NULL,
  ];
  $entries[] = Paragraph::create($keep($values));
}
echo 'Parish items: ' . count($entries) . PHP_EOL;

$section('Newspapers by Parish', [
  Paragraph::create([
    'type' => 'parish_map',
    'field_block_intro' => $html(<<<'HTML'
<p><strong>Louisiana Digital Newspapers &amp; Magazines</strong> -- Note: Digital newspapers for a particular title may not duplicate all of the newspapers available in print or on microfilm. See the LSU Libraries catalog for complete holdings.</p>
<p><strong>Louisiana Newspaper &amp; Magazine Websites</strong> (asterisk denotes official parish journal)</p>
HTML),
    'field_parish_entries' => $entries,
  ]),
]);

// 2. Louisiana Digital Newspapers. -----------------------------------------

$section('Louisiana Digital Newspapers', [
  $text('Databases', $data['databases']),
  // The collections on lib.lsu.edu/louisiananewspapers.
  $text('Collections', <<<'HTML'
<ul>
<li><a href="https://louisianadigitallibrary.org/louisiananewspapers">Newspaper Collections on the LDL</a><br>Louisiana newspapers on the Louisiana Digital Library, spanning over 75 different publications.</li>
<li><a href="https://issuu.com/thedailyreveille/docs">The Reveille on Issuu</a><br>Access full copies of The Reveille printed since 2009 through issuu.</li>
<li><a href="https://louisianadigitallibrary.org/islandora/object/lsu-sc-reveille:collection">The Reveille on the LDL</a><br>Access digital issues of The Reveille from 1897 to the present on the Louisiana Digital Library.</li>
</ul>
HTML),
]);

// 3. Louisiana Newspapers on Microfilm. ------------------------------------

$section('Louisiana Newspapers on Microfilm', [
  $text('How to Find Louisiana Newspapers on Microfilm', '<p>Search our online catalog to find Louisiana newspapers in the LSU Libraries. They are available usually only on microfilm, as we keep very few original printed issues. Microfilm for almost all Louisiana newspapers is kept in Hill Memorial Library. The only exceptions are major Louisiana newspapers for the twentieth and twenty-first centuries, which are housed in the LSU Library. Go to room 141 to find these major titles:</p>'),
  $accordion(NULL, array_map(fn (array $city) => [$city[0], $ul($city[1])], array_values($microfilm))),
]);

// 4. Louisiana Newspaper Project Microfilm Indexes. ------------------------

$section('Louisiana Newspaper Project Microfilm Indexes', [
  $text('About Louisiana Newspaper Project Microfilm Indexes', <<<'HTML'
<p>These topical lists give titles, publication details, and LSU holdings information for hundreds of Louisiana newspapers. For a complete list of Louisiana newspapers at LSU and other repositories, consult the Louisiana Newspaper Project printout, which is available in and from the LSU Libraries Special Collections.</p>
<ul>
<li><a href="https://www.lib.lsu.edu/sites/all/files/sc/lnp/parishindex.pdf">Parish Index [PDF]</a></li>
<li><a href="https://www.lib.lsu.edu/sites/all/files/sc/lnp/chronindex.pdf">Chronological Index [PDF]</a></li>
<li><a href="https://www.lib.lsu.edu/sites/all/files/sc/lnp/lnp_civwar.pdf">Civil War Newspapers (1861-65) [PDF]</a></li>
<li><a href="https://www.lib.lsu.edu/sites/all/files/sc/lnp/lnp_french.pdf">French-Language Newspapers [PDF]</a></li>
<li><a href="https://www.lib.lsu.edu/sites/all/files/sc/lnp/lnp_black.pdf">Black Newspapers [PDF]</a></li>
</ul>
HTML),
]);

// 5. Digitizing Louisiana Newspapers Project. ------------------------------

$section('Digitizing Louisiana Newspapers Project', [
  $text('About the Digitizing Louisiana Newspapers Project', '<p>The <strong><a href="https://guides.lib.lsu.edu/c.php?g=1431778&amp;p=10627155">Digitizing Louisiana Newspapers Project</a></strong>, a program funded by the National Digital Newspaper Program and administered by the LSU Libraries between 2009 and 2015, digitized issues from 128 Louisiana newspaper titles dating from between 1836 and 1922 for the Library of Congress’s <strong><a href="http://chroniclingamerica.loc.gov/"><em>Chronicling America</em></a></strong> database. The project’s homepage contains lists of newspapers, teaching resources, and topic guides.</p>'),
]);

// 6. Family Trees for Louisiana Newspapers. --------------------------------

$section('Family Trees for Louisiana Newspapers', [
  Paragraph::create([
    'type' => 'family_trees',
    'field_block_title' => 'Find Family Trees for Louisiana Newspapers',
    'field_block_intro' => $html('<p>Many Louisiana newspapers have complex publishing histories, filled with mergers, acquisitions, extinctions, daily and weekly editions, and assorted title changes. Our newspaper <strong><a href="https://www.lib.lsu.edu/index.php/louisiananewspapers">"family trees"</a></strong> offer a visual organization of how newspapers changed over time and became related to other periodicals in the same city.</p>'),
  ]),
]);

// 7. Contact (lib.lsu.edu/louisiananewspapers). ----------------------------

$section('Contact', [
  $text(NULL, <<<'HTML'
<ul>
<li><a href="https://askus.lib.lsu.edu#special">Ask Special Collections</a></li>
<li><a href="mailto:hrasmuss@lsu.edu">hrasmuss@lsu.edu</a></li>
</ul>
HTML),
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
  // The introduction on lib.lsu.edu/louisiananewspapers.
  $values['field_intro'] = $html('<p>The LSU Libraries has collected and preserved both historical and current Louisiana newspapers since the 1930s and began microfilming dozens of titles in the 1940s.</p>');
}
if (isset($node_fields['field_guide_source'])) {
  $values['field_guide_source'] = ['uri' => 'https://guides.lib.lsu.edu/c.php?g=440633'];
}
if (isset($node_fields['field_guide_owner'])) {
  $skipped[] = 'Guide owner: the LibGuide names no owner. Set it on the page.';
}
if (isset($node_fields['field_guide_subjects'])) {
  // The LibGuide's subject.
  $vocabs = array_keys($node_fields['field_guide_subjects']->getSetting('handler_settings')['target_bundles'] ?? []);
  $terms = $vocabs ? \Drupal::entityQuery('taxonomy_term')->accessCheck(FALSE)
    ->condition('vid', $vocabs, 'IN')->condition('name', 'Special Collections')->execute() : [];
  $values['field_guide_subjects'] = array_map(fn ($tid) => ['target_id' => $tid], array_values($terms));
  if (!$terms) {
    $skipped[] = 'Subjects: no "Special Collections" term was found. Set it on the page.';
  }
}
if (!empty($args['path'])) {
  $values['path'] = ['alias' => '/' . ltrim($args['path'], '/')];
}

$page = Node::create($values);
$page->setRevisionLogMessage('Guide: Louisiana Newspapers, from the LibGuide (palefire_guide_louisiana_newspapers.php).');
$page->save();

echo 'Created unpublished guide page: node/' . $page->id() . ' (' . $page->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
foreach ($skipped as $line) {
  echo 'Skipped: ' . $line . PHP_EOL;
}
echo 'Review it, then publish. Run: drush cr' . PHP_EOL;
