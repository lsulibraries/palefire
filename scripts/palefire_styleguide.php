<?php

/**
 * @file
 * The Palefire style guide: a public Modular page in exhibition mode at
 * /v2/special/internal/styleguide, built from the theme's own components.
 *
 *   drush php:script scripts/palefire_styleguide.php
 *   drush cr
 *
 * Optional arguments after "--":
 *   image=<media id>      An Image media item for the figure examples and
 *                         the title page. Without it they are left out.
 *   format=<text format>  Text format for the text (default: WYSIWYG).
 *
 * This script owns the page: run it again after a component is added or
 * changed and it rebuilds every gallery on the existing page (a new
 * revision, so the Revisions tab keeps the old one). Edits made in the
 * editor are replaced on the next run: change the text here instead.
 * Notes and the checklist for adding a component: docs/styleguide.md.
 *
 * Plates (colour, type, grid, spacing, lines, motion) are drawn by the
 * theme from its tokens: a text paragraph that is only "[styleguide:<plate>]"
 * (palefire_preprocess_paragraph__guide_text()). The rest of the page is
 * live examples of the exhibition blocks, each with a note for editors.
 *
 * Needs palefire_exhibit_setup.php; readings need
 * palefire_exhibit_reading_setup.php (left out without it). Not listed on
 * the Exhibitions showcase when that field exists.
 */

declare(strict_types=1);

use Drupal\media\Entity\Media;
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
    $args[trim($k)] = trim($v, " \t\"'");
  }
}

foreach (['exhibit_page', 'exhibit_entry', 'exhibit_figure', 'exhibit_gallery', 'exhibit_citation', 'guide_text', 'guide_accordion', 'accordion_item'] as $id) {
  if (!ParagraphsType::load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_exhibit_setup.php first.");
  }
}

$path = '/v2/special/internal/styleguide';
$title = 'Palefire Style Guide';
$skipped = [];

// Text uses the WYSIWYG format, never full_html.
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

$image = NULL;
if (!empty($args['image'])) {
  $image = Media::load((int) $args['image']);
  if (!$image || $image->getSource()->getPluginId() !== 'image') {
    throw new \RuntimeException("Media {$args['image']} is not an Image media item.");
  }
}

$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];
$keep = fn (array $values) => array_filter($values, fn ($v) => $v !== NULL && $v !== '' && $v !== []);
// The block's name as editors see it under "Add Block".
$label = fn (string $type) => (string) (ParagraphsType::load($type)?->label() ?? $type);

// Blocks. --------------------------------------------------------------------

$text = fn (string $body, string $style = 'default') => Paragraph::create([
  'type' => 'guide_text',
  'field_block_body' => $html($body),
  'field_text_style' => $style,
]);

// A note for editors: a Callout opening with "For editors".
$note = fn (string $body) => $text("<h4>For editors</h4>\n" . trim($body), 'callout');

$citation = fn (string $body) => Paragraph::create(['type' => 'exhibit_citation', 'field_block_body' => $html($body)]);

$figure = fn (string $caption, string $layout) => $image ? Paragraph::create([
  'type' => 'exhibit_figure',
  'field_figure_image' => ['target_id' => $image->id()],
  'field_figure_caption' => $html($caption),
  'field_figure_layout' => $layout,
]) : NULL;

$accordion = function (array $items, string $intro = '') use ($html, $keep): Paragraph {
  $children = [];
  foreach ($items as [$item_title, $body]) {
    $children[] = Paragraph::create(['type' => 'accordion_item', 'field_item_title' => $item_title, 'field_block_body' => $html($body)]);
  }
  return Paragraph::create($keep([
    'type' => 'guide_accordion',
    'field_block_intro' => $intro !== '' ? $html($intro) : NULL,
    'field_accordion_items' => $children,
  ]));
};

$readings = NULL;
if (ParagraphsType::load('exhibit_reading')) {
  $reading = fn (string $kind, string $author, string $reading_title, string $source, string $details, string $link = '') => Paragraph::create($keep([
    'type' => 'exhibit_reading',
    'field_reading_kind' => $kind,
    'field_reading_author' => $author,
    'field_reading_title' => $reading_title,
    'field_reading_source' => $source,
    'field_reading_details' => $details,
    'field_figure_link' => $link !== '' ? ['uri' => $link] : NULL,
  ]));
  $readings = Paragraph::create([
    'type' => 'exhibit_gallery',
    'field_block_title' => 'Suggested reading',
    'field_gallery_items' => [
      $reading('book', 'Josef Müller-Brockmann', 'Grid Systems in Graphic Design', 'Niggli', '1981'),
      $reading('book', 'Emil Ruder', 'Typography: A Manual of Design', 'Niggli', '1967'),
      $reading('article', 'W3C', 'Web Content Accessibility Guidelines (WCAG) 2.2', 'W3C Recommendation', '2023', 'https://www.w3.org/TR/WCAG22/'),
    ],
  ]);
}
else {
  $skipped[] = 'Readings: exhibit_reading not found (palefire_exhibit_reading_setup.php).';
}
if (!$image) {
  $skipped[] = 'Figures and the title image: pass image=<media id> to show them.';
}

// Galleries. -----------------------------------------------------------------

$sections = [];
$gallery = function (string $gallery_title, string $anchor, array $blocks) use (&$sections): void {
  $sections[] = Paragraph::create([
    'type' => 'exhibit_page',
    'field_section_title' => $gallery_title,
    'field_section_anchor' => $anchor,
    'field_exhibit_opener' => 'compact',
    'field_guide_blocks' => array_values(array_filter($blocks)),
  ]);
};

$gallery('About this guide', 'about', [
  $text(<<<'HTML'
<p>Palefire is the LSU Libraries theme: a quiet, typographic frame for collections, in the manner of a museum catalogue. Its grammar is Swiss. Type, a strict grid and generous space carry the page, the hairline separates, and colour is kept for meaning.</p>
<p>This guide is built from the theme itself. Every plate is drawn from the same tokens the site uses, so when a token changes the plate changes with it. Every example below is a real block, as editors add it.</p>
<ul>
<li><strong>Calm.</strong> Few colours, soft lines, slow and subtle motion.</li>
<li><strong>Typographic.</strong> Hierarchy from size, weight and space, not from boxes.</li>
<li><strong>Accessible.</strong> WCAG 2.2 AA in every reading colour, with or without motion.</li>
<li><strong>Light.</strong> Little CSS and script, images sized and lazy, no layout shift.</li>
</ul>
<p>[styleguide:overview]</p>
HTML),
  $note(<<<'HTML'
<p>This page is generated by <code>scripts/palefire_styleguide.php</code> in the theme. Changes made here in the editor are replaced the next time the script runs. The notes behind it are in <code>docs/styleguide.md</code>.</p>
<p>A paragraph that is only <code>[styleguide:colors]</code> (or overview, type, grid, spacing, lines, motion, buttons) in any text block draws that plate after the text.</p>
HTML),
]);

$gallery('Colour', 'colour', [
  $text(<<<'HTML'
<p>LSU purple and gold are the brand. Gold fails contrast on white, so it appears only on purple or dark grounds. Everything else comes from the reading colour a visitor has chosen in Settings, so these swatches show the reading colour in use: pick another one and they repaint, with their contrast measured again.</p>
<p>[styleguide:colors]</p>
HTML),
  $note(<<<'HTML'
<p>Colour comes from the theme, not from the editor. For emphasis use a Callout block, like this one. Every reading colour keeps it legible.</p>
HTML),
]);

$gallery('Typography', 'type', [
  $text(<<<'HTML'
<p>One family does the work: Roboto for text, menus and headings, at sizes that grow with the screen. Exhibition titles may be set in Playfair Display, an art-book display face, at display sizes only.</p>
<p>[styleguide:type]</p>
HTML),
  $note(<<<'HTML'
<p>Inside a block that has its own heading, use Heading 4: every Heading 3 becomes a link in the table of contents. Pick an exhibition's title face under "Exhibition title style" (Serif or Sans).</p>
HTML),
]);

$gallery('Grid and breakpoints', 'grid', [
  $text(<<<'HTML'
<p>Bootstrap's twelve columns underlie every layout, mobile first: one column on a phone, more as the screen allows. Reading text holds a measure of about 75 characters, wherever the column is wider.</p>
<p>[styleguide:grid]</p>
HTML),
  $note(<<<'HTML'
<p>An image's Layout places it on the grid: beside the text (image first or second), centred in the text column, or full width with a slow parallax.</p>
HTML),
]);

$gallery('Spacing and lines', 'spacing', [
  $text(<<<'HTML'
<p>Space is the main divider. Where a line is needed it is the hairline, a fifth of the section's line colour, like the rows of the opening hours. Fields and buttons without words keep a full 3:1, and every focusable thing shows a clear ring.</p>
<p>[styleguide:spacing]</p>
<p>[styleguide:lines]</p>
HTML),
  $note(<<<'HTML'
<p>Don't add empty paragraphs for space or horizontal rules for separation: blocks are spaced by the theme.</p>
HTML),
]);

$gallery('Motion', 'motion', [
  $text(<<<'HTML'
<p>Movement is slow, eased and brief: colour arrives quickly and leaves slowly, pages rise into place, exhibition images settle or are drawn by a curtain. Nothing depends on it, and nothing waits hidden: a page title is at full strength from the first frame, and what reveals on scroll starts at 85% and only brightens. The system setting and Settings &gt; Reduce motion stop all of it.</p>
<p>[styleguide:motion]</p>
HTML),
]);

$gallery('Buttons and links', 'buttons', [
  $text(<<<'HTML'
<p>One button in two strengths, and one text link. The primary button is the accent, filled, for the one main action in a view; every other button is unfilled in a hairline frame. Words are mono capitals. An arrow says where a link goes: → stays on the site, ↗ opens another site in a new tab.</p>
<p>[styleguide:buttons]</p>
HTML),
  $note(<<<'HTML'
<p>Buttons come with their blocks: there is nothing to add in the editor. Keep button words short and say what happens ("Request the Lecture Hall", not "Click here").</p>
HTML),
]);

$gallery('Steps', 'steps', [
  $text(<<<'HTML'
<p>A short how-to done in order: two to four steps, each a short name and a one-line note. Wide screens set them across, each under a large accent number; phones run them down one rail, each number in a diamond. A step can have sub-steps, the Quick Start's stops: small diamonds with a duotone icon. One step alone has no number: its name heads its sub-steps, which run across. The numbering says what kind of list it is: letters for alternatives (the Quick Start's A, B and C: pick one), 1, 2, 3 for a sequence, and 01, 02 only for the section index rules.</p>
<p>[styleguide:steps]</p>
HTML),
  $note(<<<'HTML'
<p>In a Booking text, write How to book as a numbered list under its subhead, each item opening with its name in bold: <strong>Send the form</strong> At least two weeks ahead. Keep the name to two to four words and the note to one line, with no full stop. Without a bold name the list prints as written.</p>
<p>Sub-steps are a numbered list inside a step, written the same way. Give one an icon by starting it with <code>[icon:name]</code>, a Font Awesome duotone name (search, file-signature, calendar-check); without one its diamond shows its number.</p>
HTML),
]);

$gallery('Object labels', 'labels', [
  $text(<<<'HTML'
<p>The caption a museum sets beside an object, for a record in the collections: who made it and when they lived, what it is called, its date, medium and size, then a hairline, the collection it belongs to and its call number. The title is the one bright line; the maker and the record are in mono capitals, and the call number never breaks across lines. A card uses the compact label: maker, title, date and call number.</p>
<p>[styleguide:labels]</p>
HTML),
  $note(<<<'HTML'
<p>Labels come from a record's fields: there is nothing to add in the editor yet. Write the date as the catalog has it ("1820–1865", "ca. 1850"), sizes in centimetres, height first, and the collection's full name.</p>
HTML),
]);

$gallery('Exhibition blocks', 'blocks', [
  $text(<<<'HTML'
<p>The blocks an exhibition gallery is made of, each as it appears on a page. The notes say where to find them under "Add Block".</p>
HTML),
  $note('<p><strong>' . $label('guide_text') . '</strong>, Style: Plain. Text with Heading 4, lists, links and tables.</p>'),
  $text(<<<'HTML'
<h4>A heading inside a block</h4>
<p>Reading text in an exhibition is set larger and looser than elsewhere on the site, for long reading. Links stay <a href="#about">underlined</a>, so they never depend on colour alone.</p>
<ol>
<li>Numbered lists for sequences.</li>
<li>Bulleted lists for everything else.</li>
</ol>
HTML),
  $note('<p><strong>' . $label('exhibit_citation') . '</strong>: the book\'s record, set like a museum label.</p>'),
  $citation('<p>Müller-Brockmann, Josef. <em>Grid Systems in Graphic Design.</em> Niggli, 1981. (Sample record for this guide.)</p>'),
  $note('<p><strong>' . $label('exhibit_entry') . '</strong>: a person or book inside a gallery. Its heading is a link under the gallery in the table of contents; a long biography folds behind "Read the full biography".</p>'),
  Paragraph::create([
    'type' => 'exhibit_entry',
    'field_block_title' => 'Josef Müller-Brockmann (1914–1996)',
    'field_section_anchor' => 'muller-brockmann',
    'field_entry_bio' => $html('<p>Swiss graphic designer and teacher in Zurich, and a leading voice of the International Typographic Style. His <em>Grid Systems in Graphic Design</em> (1981) set out the modular grid this theme follows.</p>'),
    'field_guide_blocks' => array_values(array_filter([
      $figure('<p>An example figure: the caption sits under the image, which opens full screen.</p>', 'side_start'),
    ])),
  ]),
  $image ? $note('<p><strong>' . $label('exhibit_figure') . '</strong>: one image with a caption. Its alt text comes from the media item: describe what the image shows.</p>') : NULL,
  $figure('<p>Layout: Centred in the text column.</p>', 'center'),
  $note('<p><strong>' . $label('guide_accordion') . '</strong>: questions and details a reader can open when they need them.</p>'),
  $accordion([
    ['What belongs in an accordion?', '<p>Material most readers can skip: rules, prices, long lists.</p>'],
    ['What doesn\'t?', '<p>Anything everyone needs. Keep it in the text.</p>'],
  ]),
  $readings ? $note('<p><strong>' . $label('exhibit_gallery') . '</strong> holding <strong>' . $label('exhibit_reading') . '</strong> items: books and articles typeset as covers, with a Grid / List switch.</p>') : NULL,
  $readings,
  $note('<p><strong>' . $label('guide_text') . '</strong>, Style: Sources. A bibliography: each item becomes a link target (#src-surname).</p>'),
  $text(<<<'HTML'
<ul>
<li>Müller-Brockmann, Josef. <em>Grid Systems in Graphic Design.</em> Niggli, 1981.</li>
<li>Ruder, Emil. <em>Typography: A Manual of Design.</em> Niggli, 1967.</li>
</ul>
HTML, 'sources'),
]);

// The page. ------------------------------------------------------------------

$values = [
  'title' => $title,
  'status' => 1,
  'field_scroll_section' => $sections,
  'field_modular_type' => 'exhibition',
  'field_exhibit_kicker' => 'Style guide',
  'field_exhibit_title_style' => 'sans',
  'field_exhibit_subtitle' => 'Colour, type, grid, motion and the blocks exhibitions are built from',
  'field_exhibit_credit' => $html('<p>Palefire, the LSU Libraries theme for Drupal. Maintained with the theme.</p>'),
  'field_exhibit_keyfacts' => ['Plates: 10', 'Standard: WCAG 2.2 AA', 'Updated: ' . date('F j, Y')],
  'field_exhibit_prompt' => 'Read it in order, or choose a plate from the table of contents.',
  'field_exhibit_cover' => $image ? ['target_id' => $image->id()] : NULL,
  'field_exhibit_showcase' => 'hidden',
];
$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');
foreach (array_keys($values) as $name) {
  if (str_starts_with($name, 'field_') && !isset($node_fields[$name])) {
    if ($name !== 'field_exhibit_showcase') {
      $skipped[] = "$name is not on Modular page; run palefire_exhibit_setup.php.";
    }
    unset($values[$name]);
  }
}

$alias = \Drupal::service('path_alias.repository')->lookupByAlias($path, 'en')
  ?? \Drupal::service('path_alias.repository')->lookupByAlias($path, \Drupal::languageManager()->getDefaultLanguage()->getId());
$node = NULL;
if ($alias && preg_match('#^/node/(\d+)$#', $alias['path'], $m)) {
  $node = Node::load((int) $m[1]);
  if ($node && $node->bundle() !== 'modular_page') {
    throw new \RuntimeException("$path belongs to node/{$node->id()}, which is not a Modular page. Move it, then re-run.");
  }
}

if ($node) {
  foreach ($values as $name => $value) {
    $node->set($name, $value);
  }
  $node->setNewRevision(TRUE);
  $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
  $node->setRevisionLogMessage('Style guide rebuilt (palefire_styleguide.php).');
  $node->save();
  echo "Rebuilt the style guide: node/{$node->id()} at $path" . PHP_EOL;
}
else {
  $node = Node::create(['type' => 'modular_page', 'path' => ['alias' => $path, 'pathauto' => 0]] + array_filter($values, fn ($v) => $v !== NULL));
  $node->setRevisionLogMessage('Style guide (palefire_styleguide.php).');
  $node->save();
  echo "Created the style guide, published: node/{$node->id()} at $path" . PHP_EOL;
}
foreach (array_unique($skipped) as $line) {
  echo 'Skipped: ' . $line . PHP_EOL;
}
echo 'Run: drush cr' . PHP_EOL;
