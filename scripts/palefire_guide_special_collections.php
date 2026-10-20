<?php

/**
 * @file
 * Guide page: "How to Use Special Collections", as an unpublished Modular
 * page in guide mode. Run palefire_guide_setup.php first.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_special_collections.php -- path=/v2/guides/special-collections
 *   drush cr
 *
 * Optional arguments after "--":
 *   format=<text format>  Text format for the guide's text (default:
 *                         the WYSIWYG format).
 *   path=<alias>          URL alias, e.g. path=/v2/guides/special-collections
 *
 * Written for someone who has never used an archive: what Special
 * Collections offers first (see an item in the Reading Room, or order a
 * digital copy, as a decision tree), then how a collection is described
 * (catalog record, finding aid, box, folder, item), the path through
 * Manuscripts, University Archives and Oral History, and the do's and
 * don'ts of asking Special Collections a question through Ask Us. Service
 * details come from lib.lsu.edu/special, /findingaids, /oralhistory, /visitsc
 * (duplication) and the Special Collections Ask Us form (October 2026).
 *
 * Creates:
 * - Resources for the Special Collections tools (found by link first, so
 *   existing Resources, e.g. from palefire_guide_research_help.php, are
 *   reused).
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

$title = 'How to Use Special Collections';
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

$r('catalog', 'Library Catalog', 'https://catalog.lib.lsu.edu', 'search', 'Books, journals, media and more that LSU Libraries owns or licenses. Check a record for the call number, format and current availability.');
$r('findingaids', 'Finding Aids', 'https://lib.lsu.edu/findingaids', 'search', 'Descriptions and box and folder lists for manuscript collections and University Archives.');
$r('account', 'Special Collections account', 'https://specialcollections.lib.lsu.edu/logon', 'tool', 'Register, then request materials, book an appointment and place duplication orders.', '', ['Free']);
$r('subjectguides', 'Manuscript Subject Guides', 'https://lib.lsu.edu/special/manuscriptsubjectguides', 'guide', 'Manuscript collections grouped by topic, each with a short description and its Mss. number.');
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

// Sections. ----------------------------------------------------------------

// The decision tree is a nested bulleted list: _guide.scss draws any list
// with a list inside it as a tree, so editors can change it in WYSIWYG.
$section('Requesting and visiting', [
  $text('Two ways to use an item', <<<'HTML'
<p>Special Collections materials don't circulate, so there are two ways to use one: see it in the Reading Room, or order a digital copy. Both start the same way, in the library catalog.</p>
<ul>
<li><strong>Find the item in the catalog and choose "Request Item"</strong>, then sign in to your Special Collections account.
<ul>
<li><strong>Option 1: See it in person</strong>
<ul>
<li>Fill in the request form. Add the box or volume number from the finding aid.</li>
<li>Book a Reading Room appointment in your account (recommended, not required).</li>
<li>Visit Hill Memorial Library. Staff bring the materials to your table.</li>
</ul>
</li>
<li><strong>Option 2: Get a digital copy</strong>
<ul>
<li>Choose "Switch to Duplication Order Form" at the top right of the request form.</li>
<li>Say exactly what to copy: the box, folder and pages.</li>
<li>Staff review the order and email you an invoice with payment instructions.</li>
<li>Pay the invoice, and the copies are sent to you.</li>
</ul>
</li>
</ul>
</li>
</ul>
HTML),
  $text(NULL, '<p><strong>Not sure where to start?</strong> Box and folder numbers come from the collection\'s finding aid, explained further down this guide. If you can\'t tell what to request, ask Special Collections through Ask Us first.</p>', 'callout'),
  $text('Option 1: See it in the Reading Room', <<<'HTML'
<ol>
<li><strong>Create a Special Collections account.</strong> Everyone who uses the materials needs one. First-time users are taken to a registration form.</li>
<li><strong>Search the catalog</strong> with the library set to Special Collections.</li>
<li><strong>Open the record and choose "Request Item"</strong> at the bottom of the page. It's added to your account.</li>
<li><strong>Make one request for each box, volume or serial.</strong> Add the box number from the finding aid.</li>
<li><strong>Request before you arrive.</strong> You can have up to 10 requests open. Mark the rest "Keep for My Review" and submit them once the first ones are filled.</li>
<li><strong>Book an appointment.</strong> Appointments aren't required, but they're recommended. Book one through your Special Collections account, and check the Reading Room hours before you come.</li>
</ol>
HTML),
  $text('Option 2: Order a digital copy', <<<'HTML'
<p>Special Collections makes copies for a fee, for personal or research use. Every copy needs a staff member's approval, and staff can refuse or change an order because of an item's format, condition or restrictions.</p>
<ol>
<li><strong>Find the catalog record</strong> for each book, manuscript collection or other item you want copied.</li>
<li><strong>Choose "Request Item"</strong> under the record's Available tab, and sign in to your Special Collections account.</li>
<li><strong>Choose "Switch to Duplication Order Form"</strong> at the top right of the New Request form.</li>
<li><strong>Describe exactly what to copy.</strong> For manuscript collections, give the box and folder numbers from the finding aid.</li>
<li><strong>Wait for the invoice.</strong> Staff review the order and email an invoice with payment instructions, usually within 24 to 48 hours (not counting weekends and holidays).</li>
</ol>
<p>Copies are for personal research and scholarly use. Providing a copy doesn't give permission to publish it, and LSU doesn't own the copyright to most of its materials.</p>
HTML),
  $text('Timing', <<<'HTML'
<ul>
<li>Materials requested during a visit usually reach the Reading Room in 5 to 15 minutes.</li>
<li>Some collections are stored off campus and take 3 to 7 days to retrieve. Request those well ahead and ask staff for help.</li>
<li>Duplication orders aren't taken by phone. Place them through your Special Collections account.</li>
</ul>
HTML),
  $accordion('In the Reading Room', [
    ['What to bring, and what to leave in a locker', '<p>You can bring pencils, paper or notebooks, laptops, tablets, cameras, chargers and phones. Leave food, drinks, pens, bags, cases, coats and umbrellas in a locker.</p>'],
    ['Handling the materials', <<<'HTML'
<ul>
<li>Make sure your hands are clean and dry.</li>
<li>Use pencil only.</li>
<li>Keep materials flat on the table, and use the book cradles and weights provided.</li>
<li>Use one box or volume at a time, and keep folders in the order you found them.</li>
<li>Don't mark, fold, trace or lean on anything, or write with materials under your paper.</li>
<li>Return everything to the desk when you're done. Staff check belongings as you leave.</li>
</ul>
HTML],
    ['Taking photographs', '<p>You can photograph archival and manuscript materials, without flash, after you complete a Camera User Policy form. Ask at the desk.</p>'],
    ['Items on exhibit', '<p>Items on exhibit can\'t be used unless special approval was arranged with staff in advance.</p>'],
  ]),
  $list(NULL, ['account', 'catalog']),
]);

$section('What Special Collections is', [
  $text(NULL, <<<'HTML'
<p>LSU Libraries Special Collections keeps rare books, manuscripts, university records, oral histories and other materials that exist nowhere else. It's in Hill Memorial Library on the LSU Quad (95 Fieldhouse Dr., Baton Rouge), and its Reading Room is open to the LSU community and to researchers from anywhere.</p>
<p>Using it is different from borrowing a library book. Nothing leaves the building, most of it isn't on a shelf you can browse, and staff bring it to you. The rest of this guide explains how collections are described, so you can find exactly what to request.</p>
HTML),
]);

$section('How a collection is described', [
  $text('From collection to page', <<<'HTML'
<p>Archives are described from the top down. You start with a whole collection and narrow it to the single folder or item you want.</p>
<ol>
<li><strong>Collection.</strong> Everything that came from one person, family, organization or LSU office, such as a family's letters and diaries. Most have a number, like Mss. 5401.</li>
<li><strong>Finding aid.</strong> The document that describes the collection and lists what's in each box.</li>
<li><strong>Box.</strong> What you request. Staff bring one box at a time.</li>
<li><strong>Folder.</strong> Inside each box, papers are kept in numbered folders.</li>
<li><strong>Item.</strong> A single letter, diary, photograph, tape or volume.</li>
</ol>
HTML),
  $text('Two ways in', <<<'HTML'
<h4>The catalog record</h4>
<p>Most collections have a record in the library catalog, just like a book. It gives the collection's title, its number, a summary and a link to its finding aid. Searching the catalog is the best place to start.</p>
<h4>Our collection pages</h4>
<p>Special Collections also lists collections on its own web pages: the Manuscript Subject Guides (collections grouped by topic), the oral history collections and the University Archives pages. They're a good way to browse a topic, and they point you to the same finding aids.</p>
HTML),
  $list(NULL, ['catalog', 'subjectguides']),
]);

$section('What is a finding aid?', [
  $text('A map of a collection', <<<'HTML'
<p>A finding aid is a description of an archival collection. Archives are usually too big to catalog item by item, so the finding aid is how you find your way around one. Most of ours are online, many as PDFs, and are listed on the Finding Aids page.</p>
<p>A finding aid typically has:</p>
<h4>A summary</h4>
<p>The collection's title, number, dates and size, and any restrictions on using it.</p>
<h4>Background</h4>
<p>A short biography of the person or history of the organization that created the papers.</p>
<h4>A scope and content note</h4>
<p>What kinds of material are in the collection (letters, ledgers, photographs) and what topics they're about.</p>
<h4>A container list</h4>
<p>The list of boxes and folders, with what's in each one. This is the part you use to decide what to request.</p>
HTML),
  $text('How to read one', <<<'HTML'
<ol>
<li><strong>Read the summary first.</strong> Check the dates and the access note: some material is restricted.</li>
<li><strong>Skim the scope and content note</strong> to see whether the collection covers your topic.</li>
<li><strong>Search the container list</strong> for names, places, dates or topics (Ctrl+F or Cmd+F in a PDF).</li>
<li><strong>Write down the details exactly:</strong> the collection title and number, then each box and folder you want.</li>
</ol>
HTML),
  $text(NULL, '<p><strong>Copy box and folder numbers only from a finding aid.</strong> Numbers from an old citation or an AI tool are often wrong, and an unverified request can delay your visit.</p>', 'callout'),
  $text('When there\'s no finding aid', <<<'HTML'
<p>Some collections, especially small ones, don't have a finding aid. Their catalog record describes the contents, and it may include a list of items. Request from the catalog record, or ask us which part of the collection you need.</p>
HTML),
  $list(NULL, ['findingaids']),
]);

$section('Finding your way by collection type', [
  $text(NULL, '<p>Special Collections has three kinds of collections that you\'ll use most. Each follows the same idea, from collection down to item, with a few differences.</p>'),
  $accordion('Three paths', [
    ['Manuscripts', <<<'HTML'
<p>Personal papers, family papers and the records of businesses and organizations from Louisiana and the Lower Mississippi Valley.</p>
<ol>
<li>Find the collection in the catalog, or browse the Manuscript Subject Guides.</li>
<li>Open the finding aid. If there isn't one, use the description and item list in the catalog record.</li>
<li>From the container list, note the Mss. number, box and folder.</li>
<li>Request the box. In the Reading Room, work through it folder by folder.</li>
</ol>
HTML, ['subjectguides', 'findingaids']],
    ['University Archives', <<<'HTML'
<p>The records of LSU itself: its offices and departments, student organizations, publications and photographs.</p>
<ol>
<li>Find the collection in the catalog, or on the University Archives pages. Most belong to a record group, which gathers the records of one LSU office or department.</li>
<li>Open the record group's finding aid. If the records aren't part of a record group, their catalog record has the description and item list.</li>
<li>Note the box and folder, or the single item, you need.</li>
<li>Request the box or item.</li>
</ol>
HTML, ['archives', 'findingaids']],
    ['Oral History', <<<'HTML'
<p>Recorded interviews collected by the T. Harry Williams Center for Oral History. Each interview may have three parts: the recording (tape), a transcript and an abstract (a short summary of what was discussed).</p>
<ol>
<li>Find the interview in the catalog, or in an oral history series on the Oral History pages.</li>
<li>Check whether the interview is processed. Processed interviews have an online abstract, so read it first to see whether the interview covers your topic.</li>
<li>Look for the interview on the Louisiana Digital Library: many can be heard or read online.</li>
<li>If it isn't online, request the recording or transcript, or order a copy. Have the collection number, tape number and interviewee's name ready.</li>
</ol>
<p>Unprocessed interviews haven't been fully described yet. You can use them only with a curator's permission, so ask us first.</p>
HTML, ['oralhistory', 'ldl']],
  ]),
]);

$section('Asking Special Collections a question', [
  $text('When to use Ask Us', <<<'HTML'
<p>Use the Special Collections form on Ask Us when you aren't sure which collection or box you need, can't find a finding aid, have a question about access or copies, or want help planning a visit. Your question goes straight to Special Collections staff.</p>
<p>Ask Us is for questions, not for requests. To have a box brought to the Reading Room or to order a copy, use "Request Item" and your Special Collections account.</p>
HTML),
  $text('Filling in the form', <<<'HTML'
<h4>Affiliation</h4>
<p>Whether you're an LSU student, faculty or staff member, alum, researcher from another institution or member of the public.</p>
<h4>Purpose of research</h4>
<p>A class assignment, thesis, publication, genealogy and so on. It tells staff what kind of answer will help, and whether permissions may come up.</p>
<h4>Question and more detail</h4>
<p>Your question in a sentence, then the detail: what you've already found and what you still need.</p>
HTML),
  $text('Do', <<<'HTML'
<ul>
<li><strong>Name the collection exactly</strong>, with its Mss. number or record group, as the catalog record or finding aid shows it.</li>
<li><strong>List box and folder numbers</strong> from the finding aid, and paste a link to the finding aid or catalog record.</li>
<li><strong>Say what you're trying to learn</strong>, not only what you want to see. Staff may know a better collection.</li>
<li><strong>Give your dates:</strong> when you plan to visit, or when you need copies by.</li>
<li><strong>For oral histories</strong>, include the collection number, tape number and interviewee's name.</li>
<li><strong>Ask one research question per submission</strong>, so it reaches the right person quickly.</li>
<li><strong>Check your email address</strong> before you submit: that's where the answer goes.</li>
</ul>
HTML),
  $text('Don\'t', <<<'HTML'
<ul>
<li><strong>Don't use Ask Us to place a retrieval request or order copies.</strong> Both go through "Request Item" in the catalog and your Special Collections account.</li>
<li><strong>Don't send box or folder numbers you haven't checked</strong> in a finding aid, such as numbers from an AI tool or an old footnote.</li>
<li><strong>Don't ask for "everything about" a broad topic.</strong> Narrow it to a person, place, event or date range.</li>
<li><strong>Don't assume something is online.</strong> Most materials haven't been digitized; ask whether they have.</li>
<li><strong>Don't send the same question by form, email and chat.</strong> One ticket is enough, and duplicates slow every answer down.</li>
<li><strong>Don't include sensitive personal information</strong>, such as ID or financial details.</li>
<li><strong>Don't ask staff to decide whether you can publish an item.</strong> They can point you to its rights information, but the rights decision is yours.</li>
</ul>
HTML),
  $text('An example', <<<'HTML'
<p>The collection and numbers here are invented, to show the shape of a good question.</p>
<h4>Too vague</h4>
<p>"Do you have anything on the 1927 flood?"</p>
<h4>Ready to answer</h4>
<p>"I'm writing a thesis on the 1927 flood in Pointe Coupee Parish. The finding aid for the Doe Family Papers (Mss. 0000) lists letters from 1927 in Box 3, Folders 12 to 14. Are those letters about the flood, and can I see them on a visit next month?"</p>
HTML, 'callout'),
  $list(NULL, ['askspecial']),
]);

$section('Get help', [
  $text(NULL, '<p>Ask at the Reading Room desk in Hill Memorial Library, call Special Collections at (225) 578-6568, email <a href="mailto:special@lsu.edu">special@lsu.edu</a>, or send a question through Ask Us.</p>'),
  $list('Special Collections tools', ['special', 'findingaids', 'account', 'askspecial']),
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
  $values['field_intro'] = $html('<p>New to archives? Choose how to use an item (see it in the Reading Room, or order a digital copy), then learn what a finding aid is, how collections are organized, and how to ask us a question that gets a fast answer.</p>');
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
if (!empty($args['path'])) {
  $values['path'] = ['alias' => '/' . ltrim($args['path'], '/')];
}

$page = Node::create($values);
$page->setRevisionLogMessage('Guide: How to Use Special Collections (palefire_guide_special_collections.php).');
$page->save();

echo 'Created unpublished guide page: node/' . $page->id() . ' (' . $page->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
foreach ($skipped as $line) {
  echo 'Skipped: ' . $line . PHP_EOL;
}
echo 'Review it, then publish. Run: drush cr' . PHP_EOL;
