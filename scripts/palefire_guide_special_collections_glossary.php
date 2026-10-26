<?php

/**
 * @file
 * Guide page: "Special Collections Glossary", as an unpublished Modular page
 * in guide mode. Run palefire_guide_setup.php first.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_special_collections_glossary.php
 *   drush cr
 *
 * Optional arguments after "--":
 *   format=<text format>  Text format for the guide's text (default:
 *                         the WYSIWYG format).
 *   path=<alias>          URL alias (default
 *                         /v2/guides/special-collections-glossary).
 *
 * A quick reference, not a second how-to: about 30 terms patrons meet when
 * they use Special Collections, grouped by the stage of a visit (finding,
 * requesting, the Reading Room, copies and rights), one guide accordion per
 * stage. Definitions are one or two sentences; the longer explanations stay
 * in "How to Use Special Collections"
 * (palefire_guide_special_collections.php), which the glossary links to.
 * LSU service details come from that guide (October 2026). Where only staff
 * can say how LSU does something, the text is marked "[Editor: …]".
 *
 * Creates:
 * - Resources for the Special Collections tools (found by link first, so the
 *   ones palefire_guide_special_collections.php made are reused).
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

foreach (['resource' => 'node_type', 'guide_section' => 'paragraphs_type'] as $id => $storage_id) {
  if (!\Drupal::entityTypeManager()->getStorage($storage_id)->load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_guide_setup.php first.");
  }
}

$title = 'Special Collections Glossary';
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

// The full guide this one points to: its own address when it exists, else
// the alias its script suggests.
$full = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', 'How to Use Special Collections')
  ->range(0, 1)->execute();
$full_url = $full ? Node::load((int) reset($full))->toUrl()->toString() : '/v2/guides/special-collections';
if (!$full) {
  $skipped[] = "No 'How to Use Special Collections' page was found; links to it use $full_url. Check them.";
}

// Resources (same links as palefire_guide_special_collections.php). -------

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

$r('catalog', 'Library Catalog', 'https://catalog.lib.lsu.edu', 'search', 'Books, journals, media and more that LSU Libraries owns or licenses. Check a record for the call number, format and current availability.');
$r('findingaids', 'Finding Aids', 'https://lib.lsu.edu/findingaids', 'search', 'Descriptions and box and folder lists for manuscript collections and University Archives.');
$r('account', 'Special Collections account', 'https://specialcollections.lib.lsu.edu/logon', 'tool', 'Register, then request materials, book an appointment and place duplication orders.', '', ['Free']);
$r('archives', 'University Archives', 'https://lib.lsu.edu/universityarchives', 'website', 'The records of LSU itself: offices, departments, student life and university publications.');
$r('oralhistory', 'T. Harry Williams Center for Oral History', 'https://lib.lsu.edu/oralhistory', 'website', 'Oral history collections and finding aids, listing both processed and unprocessed interviews.');
$r('ldl', 'Louisiana Digital Library', 'https://www.louisianadigitallibrary.org', 'database', 'Digitized photographs, manuscripts and oral histories, including many interviews you can listen to online.', '', ['Free', 'Online']);
$r('special', 'Special Collections & Archives', 'https://www.lib.lsu.edu/special', 'website', 'Rare books, manuscripts, university records and other unique materials, with the finding aids that describe them.');
$r('askspecial', 'Ask Us: Special Collections', 'https://askus.lib.lsu.edu/special', 'person', 'Send Special Collections staff a question, or browse answers to common ones.');

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

// Sections: one per stage of a visit, terms in the order you meet them. ----

$section('Finding materials', [
  $text(NULL, '<p><strong>Each term in a sentence or two.</strong> For the whole process, from catalog search to Reading Room, see <a href="' . $full_url . '">How to Use Special Collections</a>.</p>', 'callout'),
  $accordion('From collection to folder', [
    ['Archives', '<p>Records kept because of their lasting value, usually unpublished and one of a kind: letters, diaries, business records, photographs, recordings. Archives are described by collection rather than item by item.</p>'],
    ['Collection', '<p>Everything that came from one person, family, organization or LSU office, kept together. You search for a collection first, then narrow it down to the box you want.</p>'],
    ['Manuscript', '<p>A handwritten or typed document, such as a letter, diary or ledger. "Manuscript collection" also means a collection of personal or family papers, or an organization\'s records.</p>', ['findingaids']],
    ['Mss. number', '<p>A manuscript collection\'s identifier, such as Mss. 5401. Include it in every request and question, and in your citations.</p>'],
    ['Record group', '<p>In University Archives, the records of one LSU office or department, gathered under one finding aid.</p>', ['archives']],
    ['Catalog record', '<p>A collection\'s or book\'s entry in the library catalog: its title, number, summary and, for archives, a link to the finding aid. It\'s also where you choose "Request Item".</p>', ['catalog']],
    ['Call number', '<p>The number that identifies where a book or volume is kept. Special Collections books aren\'t on open shelves, so staff use it to retrieve the book for you.</p>'],
    ['Finding aid', '<p>A description of an archival collection: its background, what it covers, any restrictions, and a list of what\'s in each box. It\'s the map you use to decide what to request.</p>', ['findingaids']],
    ['Scope and content note', '<p>The part of a finding aid that says what kinds of material a collection holds and what topics it covers. Skim it to see whether a collection fits your research.</p>'],
    ['Container list', '<p>The part of a finding aid that lists the boxes and folders and what\'s in each. Copy box and folder numbers from here, exactly as they appear.</p>'],
    ['Series', '<p>A group of related records within a collection, such as "Correspondence" or "Photographs". A container list is often arranged by series.</p>'],
    ['Box and folder', '<p>You request a box; inside it, papers are kept in numbered folders. Keep the folders in the order you found them.</p>'],
    ['Provenance', '<p>Who created or owned materials before they came to the library. Archives keep each collection together by provenance rather than sorting it by subject.</p>'],
    ['Rare book', '<p>A printed book kept in Special Collections because of its age, scarcity, condition or significance. Rare books are used in the Reading Room, never borrowed.</p>'],
    ['Ephemera', '<p>Printed items made to be thrown away: tickets, flyers, programs, postcards, menus. Often a vivid record of everyday life.</p>'],
    ['Oral history abstract and transcript', '<p>An oral history interview may have a recording, a transcript (a written word-for-word copy) and an abstract (a short summary of what was discussed). Read the abstract first.</p>', ['oralhistory', 'ldl']],
    ['Digitized and born-digital', '<p>Digitized material was scanned or recorded from an original; born-digital material, such as email or digital photographs, was created on a computer. Most Special Collections materials haven\'t been digitized.</p>', ['ldl']],
  ]),
]);

$section('Requesting', [
  $accordion('Before your visit', [
    ['Special Collections account', '<p>The free account you use to request materials, book an appointment and order copies. Everyone needs one, LSU-affiliated or not: LSU users sign in with single sign-on, and everyone else registers as a Guest.</p>', ['account']],
    ['Request Item', '<p>The link in a catalog record that sends staff a request for a box, volume or item. Make one request for each box or volume; you can have up to 10 open at a time.</p>', ['catalog']],
    ['Retrieval', '<p>Staff bringing your requested materials from storage to the Reading Room. During a visit it usually takes 5 to 15 minutes.</p>'],
    ['Off-site storage', '<p>Some collections are kept off campus and take 3 to 7 days to retrieve. Request them well ahead of your visit.</p>'],
    ['Appointment', '<p>A booked time in the Reading Room. Appointments aren\'t required but are recommended. Book one through your Special Collections account.</p>', ['account']],
    ['Processed and unprocessed', '<p>A processed collection has been arranged and described, usually with a finding aid. An unprocessed one hasn\'t yet, so it may be hard to search and may need a curator\'s permission to use. Ask before you request it.</p>'],
    ['Restricted', '<p>Material that can\'t be used, or can be used only with permission, because of privacy, law, a donor\'s wishes or its condition. A finding aid\'s summary notes any restrictions.</p>'],
    ['Embargo', '<p>A restriction with an end date: the material opens to researchers on a set date. [Editor: say whether LSU uses this term for archival collections as well as theses and dissertations.]</p>'],
  ]),
]);

$section('In the Reading Room', [
  $accordion('At the table', [
    ['Reading Room', '<p>The supervised room in Hill Memorial Library where you use Special Collections materials. It\'s open to the LSU community and to researchers from anywhere.</p>'],
    ['Non-circulating', '<p>Can\'t be borrowed. Nothing in Special Collections leaves the building, so you use it in the Reading Room or order a copy.</p>'],
    ['Lockers', '<p>Where you leave food, drinks, pens, bags, cases, coats and umbrellas before you go in. You can bring pencils, paper, laptops, tablets, cameras and phones.</p>'],
    ['Pencil only', '<p>Pens can leave permanent marks, so notes in the Reading Room are taken in pencil or on a device.</p>'],
    ['Book cradle and weights', '<p>Supports that hold a fragile book open at a safe angle, and soft weights that keep its pages flat. Staff will show you how to use them.</p>'],
    ['Camera User Policy', '<p>A form you complete at the desk before photographing archival and manuscript materials. Photography is allowed without flash.</p>'],
  ]),
]);

$section('Copies, rights and citing', [
  $accordion('After your visit', [
    ['Duplication order', '<p>A request for copies, placed through your Special Collections account with "Switch to Duplication Order Form". Scans are 50 cents a page plus a $10 processing fee per order, and every order needs staff approval.</p>', ['account']],
    ['Copyright', '<p>The legal right to copy and publish a work. LSU doesn\'t own the copyright to most of its materials, and having a copy doesn\'t give you permission to publish it.</p>'],
    ['Public domain', '<p>Works no longer protected by copyright, which anyone can reuse. Old doesn\'t always mean public domain, and being digitized doesn\'t make an item public domain.</p>'],
    ['Rights statement', '<p>A short statement on a digital item that says what is known about its copyright and reuse. Check it before you share or publish an image.</p>', ['ldl']],
    ['Permission to publish', '<p>Approval from the copyright holder to publish or reproduce a work. Staff can point you to an item\'s rights information, but the rights decision is yours. [Editor: say whether Special Collections also asks for a credit line or its own permission form.]</p>'],
    ['Preferred citation', '<p>The citation a finding aid suggests for its collection. Copy it as given, then add the box, folder and item you used.</p>', ['findingaids']],
  ]),
]);

$section('Get help', [
  $text(NULL, '<p>Met a term that isn\'t here? Ask at the Reading Room desk in Hill Memorial Library, call Special Collections at (225) 578-6568, email <a href="mailto:special@lsu.edu">special@lsu.edu</a>, or send a question through Ask Us.</p>'),
  $list(NULL, ['askspecial', 'special']),
]);

// The page. ----------------------------------------------------------------

$values = [
  'type' => 'modular_page',
  'title' => $title,
  'status' => 0,
  'field_scroll_section' => $sections,
  'path' => ['alias' => '/' . ltrim($args['path'] ?? '/v2/guides/special-collections-glossary', '/')],
];
$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');
if (isset($node_fields['field_modular_type'])) {
  $values['field_modular_type'] = 'guide';
}
if (isset($node_fields['field_intro'])) {
  $values['field_intro'] = $html('<p>The words you\'ll meet when you use Special Collections, from finding aid to rights statement, each in a sentence or two and in the order you\'ll come across them.</p>');
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

$page = Node::create($values);
$page->setRevisionLogMessage('Guide: Special Collections Glossary (palefire_guide_special_collections_glossary.php).');
$page->save();

echo 'Created unpublished guide page: node/' . $page->id() . ' (' . $page->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
foreach ($skipped as $line) {
  echo 'Skipped: ' . $line . PHP_EOL;
}
echo 'Fill in the [Editor: …] notes, review it, then publish. Run: drush cr' . PHP_EOL;
