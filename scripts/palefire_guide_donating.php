<?php

/**
 * @file
 * Guide page: "Donating Materials to Special Collections", as an
 * unpublished Modular page in guide mode: what Special Collections
 * collects, what it can and usually can't accept, examples from its
 * collections (an Image group), how a gift is made, and whom to contact.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_donating.php
 *   drush cr
 *
 * Before running it, allow Image groups in guide sections (by hand, in the
 * UI): Structure > Paragraph types > Guide section > Manage fields >
 * field_guide_blocks > Edit, tick "Image group" (exhibit_gallery), Save.
 * The script stops until that is done; it changes no configuration.
 *
 * Optional arguments after "--":
 *   format=<text format>  Text format for the guide's text (default:
 *                         the WYSIWYG format).
 *   path=<alias>          URL alias (default
 *                         /v2/special/guides/donating-materials).
 *
 * Contacts and links are from the Donate Materials panel on
 * lib.lsu.edu/special (checked 2026-10-30). What Special Collections
 * accepts and declines is a draft from common archival practice, marked
 * "[Editor: …]" where only the curators can say.
 *
 * Example images reuse the exhibitions' Image media items by name
 * ("Audubon: …", "Occult: …", "Mossville: …"). One that isn't found, and
 * the two left for the curators to choose, are figures without an image,
 * which only editors see ("Image to add").
 *
 * Creates:
 * - Resources for the donation links (found by link first, so existing
 *   Resources are reused).
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

foreach (['resource' => 'node_type', 'guide_section' => 'paragraphs_type', 'exhibit_gallery' => 'paragraphs_type', 'exhibit_figure' => 'paragraphs_type'] as $id => $storage_id) {
  if (!\Drupal::entityTypeManager()->getStorage($storage_id)->load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_guide_setup.php and palefire_exhibit_setup.php first.");
  }
}

// Image groups must be allowed in guide sections (done by hand in the UI).
$blocks_field = FieldConfig::loadByName('paragraph', 'guide_section', 'field_guide_blocks');
$handler = $blocks_field ? $blocks_field->getSetting('handler_settings') : [];
$bundles = array_keys(array_filter($handler['target_bundles'] ?? []));
$negate = !empty($handler['negate']);
$allowed = $bundles === [] ? !$negate : (in_array('exhibit_gallery', $bundles, TRUE) xor $negate);
if (!$allowed) {
  throw new \RuntimeException('Guide sections do not allow Image groups yet. In the UI: Structure > Paragraph types > Guide section > Manage fields > field_guide_blocks > Edit, tick "Image group", Save. Then run this again.');
}

$title = 'Donating Materials to Special Collections';
$found = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', $title)
  ->range(0, 1)->execute();
if ($found) {
  echo "Skipped: '$title' already exists (node " . reset($found) . '). Delete it first to rebuild it.' . PHP_EOL;
  return;
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

$r('deeds', 'Deeds of Gift: What Every Donor Should Know', 'https://www2.archivists.org/publications/brochures/deeds-of-gift', 'website', 'What a deed of gift covers, from the Society of American Archivists.', '', ['Donors']);
$r('family', 'Donating Your Personal or Family Papers to a Repository', 'https://www2.archivists.org/publications/brochures/donating-familyrecs', 'website', 'How to prepare family papers, letters and photographs for a library or archive, from the Society of American Archivists.', '', ['Donors']);
$r('orgs', 'Donating Your Organizational Records to a Repository', 'https://www2.archivists.org/publications/brochures/donating-orgrecs', 'website', 'For businesses, churches, clubs and other organizations offering their records, from the Society of American Archivists.', '', ['Donors']);
$r('oldbooks', 'Your Old Books', 'https://rbms.info/yob/', 'website', 'What makes an old book rare or valuable, and how to find an appraiser, from the Rare Books and Manuscripts Section (ACRL).', '', ['Donors']);
$r('give', 'Give to LSU Libraries', 'https://bbis32491p.sky.blackbaud.com/give-now', 'website', 'Make a financial gift to Special Collections online.');
$r('special', 'Special Collections', 'https://www.lib.lsu.edu/special', 'website', 'Reading room hours, collections and services at Hill Memorial Library.');
$r('askus', 'Ask a librarian', 'https://askus.lib.lsu.edu/ask', 'person', 'Text, call, email or chat with the library.');

echo 'Resources ready: ' . count($resources) . PHP_EOL;

// Images: the exhibitions' media items, by name. ---------------------------

$media = function (string $name): ?int {
  $ids = \Drupal::entityQuery('media')->accessCheck(FALSE)
    ->condition('name', $name)
    ->range(0, 1)->execute();
  if (!$ids) {
    echo "Not found: media \"$name\". Its figure is left for an editor to add the image." . PHP_EOL;
  }
  return $ids ? (int) reset($ids) : NULL;
};

// Block helpers (same shapes as palefire_guide_parking.php). -------------

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

// An example: the kind of material first, then the item and where it is
// shown. media: a media item's name, or NULL for one an editor adds.
$example = fn (?string $media_name, string $caption) => Paragraph::create($keep([
  'type' => 'exhibit_figure',
  'field_figure_image' => $media_name && ($id = $media($media_name)) ? ['target_id' => $id] : NULL,
  'field_figure_caption' => $html($caption),
  'field_figure_layout' => 'center',
]));

$gallery = fn (?string $heading, array $figures, string $caption = '') => Paragraph::create($keep([
  'type' => 'exhibit_gallery',
  'field_block_title' => $heading,
  'field_gallery_items' => $figures,
  'field_figure_caption' => $caption !== '' ? $html($caption) : NULL,
]));

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

// The guide. ----------------------------------------------------------------

$sections = [
  $section('Before you offer', [
    $text(NULL, '<p><strong>Please contact a curator before you bring or send anything.</strong> We can only take materials we have agreed to accept, and items left at the desk or mailed without notice may have to be returned.</p>', 'callout'),
    $text('In short', <<<'HTML'
<ol>
<li><strong>Tell us what you have.</strong> Email a curator with a short description: what the materials are, who made or owned them, their dates, about how much there is, and a few photographs.</li>
<li><strong>We review it.</strong> The curator checks whether it fits our collections and whether we can care for it, and may ask to see it in person.</li>
<li><strong>We agree the gift.</strong> If we can accept it, you and the library sign a deed of gift, which transfers ownership and sets out any terms.</li>
<li><strong>We bring it in.</strong> We arrange delivery or pick-up with you, then catalog the materials so researchers can find and use them.</li>
</ol>
HTML),
  ]),
  $section('What we collect', [
    $text(NULL, <<<'HTML'
<p>Special Collections at Hill Memorial Library keeps original, rare and unique materials for research, teaching and exhibition. We collect most actively in:</p>
<ul>
<li><strong>Louisiana and the Lower Mississippi Valley:</strong> the people, families, communities, businesses and organizations of the region, from the colonial era to today.</li>
<li><strong>LSU history:</strong> records of the university and of its faculty, students and alumni, kept by University Archives.</li>
<li><strong>Rare books:</strong> early printed books, fine and illustrated editions, natural history and Louisiana imprints.</li>
<li><strong>Oral histories:</strong> recorded interviews, through the T. Harry Williams Center for Oral History.</li>
</ul>
<p>[Editor: check these areas against the current collecting policy, and link it if it is public.]</p>
HTML),
  ]),
  $section('What we can accept', [
    $text(NULL, <<<'HTML'
<p>Original materials in good condition that document our collecting areas, for example:</p>
<ul>
<li>Letters, diaries, journals, memoirs and scrapbooks</li>
<li>Family papers and genealogies, plantation and business records, ledgers and account books</li>
<li>Records of churches, clubs, schools, unions and other organizations</li>
<li>Photographs, negatives, slides, postcards and albums, ideally with names, places and dates</li>
<li>Maps, plans, architectural drawings and broadsides</li>
<li>Rare and early books, pamphlets, sheet music and ephemera such as programs, posters and menus</li>
<li>Audio and video recordings, and born-digital files such as email, documents and digital photographs</li>
</ul>
<p>A whole group of papers tells more than a few pieces taken from it, so please keep materials together and in their original order. Don't sort, clean or repair them first.</p>
HTML),
  ]),
  $section('What we usually can\'t accept', [
    $accordion(NULL, [
      ['Copies rather than originals', '<p>Photocopies, scans and printouts, unless the originals are lost. If you are keeping the originals, ask whether a digital copy would help instead.</p>'],
      ['Common published books', '<p>Textbooks, encyclopedias, magazines, condensed or book-club editions and most books published in the last century, which other libraries already hold. The LSU Libraries main collection or a local Friends of the Library sale may welcome them. [Editor: name where to send them, if anywhere.]</p>'],
      ['Items with mold, pests or water damage', '<p>Active mold or insects can spread to the rest of our collections. Please tell us about any damage when you first get in touch: some items can still be accepted after treatment. Don\'t try to clean them yourself.</p>'],
      ['Things you don\'t own', '<p>We can only accept a gift from its owner, or from someone with the right to give it, such as an estate\'s executor. Materials that belong to an employer or to a government office usually can\'t be given privately.</p>'],
      ['Loans, and gifts with lasting conditions', '<p>We accept gifts, not loans. We can agree to reasonable, time-limited restrictions (closing private papers for a set number of years, for example), but not terms that require us to keep every item on display, return it later or keep it from researchers forever.</p>'],
      ['Objects, artwork and memorabilia', '<p>Furniture, clothing, uniforms, framed art and other objects are usually outside our scope, unless they belong with papers we are accepting. [Editor: say which kinds of object Special Collections does take, if any, and suggest a museum for the rest.]</p>'],
      ['Material we already hold, or outside our areas', '<p>Duplicates of what is already in our collections, and materials with no connection to our collecting areas. We are happy to suggest a library, archive or museum that would be a better home.</p>'],
    ], '<p>We turn some offers down because we can\'t care for them properly or because they belong somewhere else, not because they lack value.</p>'),
  ]),
  $section('Examples from our collections', [
    $gallery(NULL, [
      $example('Audubon: folio-carolina-parrot', '<p><strong>Rare books and plates.</strong> Carolina Parrot, from John James Audubon\'s <em>The Birds of America</em>. Shown in <a href="/v2/special/exhibitions/audubon">John James Audubon in Louisiana</a>.</p>'),
      $example('Occult: bacon-sylva-sylvarum-frontispiece', '<p><strong>Early printed books.</strong> The frontispiece of Francis Bacon\'s <em>Sylva Sylvarum</em>. Shown in <a href="/v2/special/exhibitions/occult">Occult Science and Philosophy in the Renaissance</a>.</p>'),
      $example('Mossville: smith_huber_hand_drawn_map', '<p><strong>Community history.</strong> A memory map of Mossville drawn by Huber “Mickey” Smith Sr. Shown in the <a href="/v2/special/exhibitions/mossville">Mossville History Project</a>.</p>'),
      $example('Mossville: ambrose_lenoria_and_interviewer_2', '<p><strong>Oral histories.</strong> Lenoria Ambrose, interviewed by Chelsea Arseneault and Teresha Ussin, 2015. From the <a href="/v2/special/exhibitions/mossville">Mossville History Project</a>.</p>'),
      $example(NULL, '<p><strong>Family papers.</strong> [Editor: add an image of letters, a diary or a family album from a donated collection, with its name.]</p>'),
      $example(NULL, '<p><strong>LSU history.</strong> [Editor: add an image from University Archives, such as a yearbook, program or photograph, with its collection.]</p>'),
    ], '<p>Materials like these are open to researchers in our Reading Room. Select an image to see it larger.</p>'),
  ]),
  $section('Making the gift', [
    $accordion(NULL, [
      ['What is a deed of gift?', '<p>A short agreement, signed by you and the library, that transfers ownership of the materials to LSU. It records what was given and any restrictions, and says who holds the copyright.</p>', ['deeds']],
      ['Can you tell me what my items are worth?', '<p>No. Federal tax rules don\'t allow a library that receives a gift to appraise it. If you plan to claim a tax deduction, arrange an independent, qualified appraiser yourself, before you make the gift; for gifts over $5,000 the IRS requires one (Form 8283). Please consult your own tax adviser.</p>', ['oldbooks']],
      ['What happens to the copyright?', '<p>Owning a letter or photograph isn\'t the same as owning its copyright. If you hold the copyright, you can transfer it to LSU in the deed of gift or keep it; either way, researchers can still use the materials for study.</p>'],
      ['Can I restrict access to part of a gift?', '<p>Sometimes, for a set period: for example, closing personal papers until a date you agree with the curator. We keep restrictions few and time-limited so the materials can be used.</p>'],
      ['Will my materials be on display?', '<p>Most materials are kept in our secure, climate-controlled stacks and used in the Reading Room, and some appear in exhibitions and online. We can\'t promise that any one item will be shown.</p>'],
      ['What if you can\'t keep everything?', '<p>[Editor: say what happens to items that turn out to be outside the collection, such as returning them or passing them on with the donor\'s agreement, and that the deed of gift sets this out.]</p>'],
      ['Can I give money instead, or as well?', '<p>Yes. Financial gifts help us buy, preserve and digitize collections.</p>', ['give']],
    ]),
    $list('Before you give', ['family', 'orgs', 'oldbooks']),
  ]),
  $section('Contact a curator', [
    $text(NULL, <<<'HTML'
<ul>
<li><strong>Books:</strong> John Miles, <a href="mailto:johnmiles@lsu.edu">johnmiles@lsu.edu</a>, <a href="tel:+12255786547">(225) 578-6547</a></li>
<li><strong>Manuscripts, archival materials and photographs:</strong> Leah Jewett, <a href="mailto:lwood@lsu.edu">lwood@lsu.edu</a></li>
</ul>
<p>[Editor: add contacts for University Archives and for oral histories, or say that the curators above will pass offers on.]</p>
HTML),
    $list(NULL, ['special', 'askus']),
  ]),
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
    echo 'Skipped: Guide owner: no Staff node titled like "Kyle Tanglao" was found. Set it on the page.' . PHP_EOL;
  }
}

$values = [
  'type' => 'modular_page',
  'title' => $title,
  'status' => 0,
  'field_scroll_section' => $sections,
  'path' => ['alias' => '/' . ltrim($args['path'] ?? '/v2/special/guides/donating-materials', '/')],
];
if (isset($node_fields['field_modular_type'])) {
  $values['field_modular_type'] = 'guide';
}
if (isset($node_fields['field_intro'])) {
  $values['field_intro'] = $html('<p>Special Collections grows through the generosity of donors. Here is what we collect, what we can and can\'t accept, and how to offer letters, papers, photographs, books and other materials for preservation.</p>');
}
if ($owner !== NULL) {
  $values['field_guide_owner'] = ['target_id' => $owner];
}

$page = Node::create($values);
$page->setRevisionLogMessage("Guide: $title (palefire_guide_donating.php).");
$page->save();
echo 'Created unpublished guide page: node/' . $page->id() . ' (' . $page->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
echo 'Fill in the [Editor: …] notes, add the two example images, review, then publish. Run: drush cr' . PHP_EOL;
