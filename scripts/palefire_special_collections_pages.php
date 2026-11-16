<?php

/**
 * @file
 * Collection pages for Special Collections: "Louisiana and Lower Mississippi
 * Valley Collections" and "Book Collections", as unpublished Modular pages
 * in exhibition mode (like the program pages). Run palefire_exhibit_setup.php
 * first.
 *
 * Copy the "collections-images" folder to the server first, then:
 *
 *   drush php:script scripts/palefire_special_collections_pages.php -- images=/home/fossa/collections-images
 *   drush cr
 *
 * Optional arguments after "--":
 *   images=<folder>       The folder of JPEGs (see Images). Without it the
 *                         pages are built with no pictures, and each missing
 *                         one is listed so an editor can add it.
 *   format=<text format>  Text format for the text (default: WYSIWYG).
 *   only=llmvc|books      Build one page.
 *
 * Addresses: /v2/special/llmvc and /v2/special/bookcollections. The old
 * site's links keep working once these pages take over the old addresses:
 * the Book Collections entries keep the old tile anchors
 * (#rarebookcollection, #eamcilhennynaturalhistorycollection,
 * #laughlincollection, #wyatthoustonday), and the Collections section on
 * the homepage (palefire_special_collections_section.php) gives its LLMVC
 * row the old tile's #louisianaandlowermississippivalleycollections.
 *
 * Text: the old site's pages as the Wayback Machine has them (October 2023,
 * lib.lsu.edu/special/CC/LLMVC, /special/CC/book, /special/cc/llmvc/photos,
 * /papers and /scope), which are fuller than the tiles on /special today,
 * and the Wyatt Houston Day entry from the live /special/bookcollections,
 * which the 2023 page didn't have yet. The opening of the Rare Books
 * research guide (guides.lib.lsu.edu/rarebooks) is folded into Book
 * Collections, which links to its 18 subject checklists rather than copying
 * them: the curator keeps them in LibGuides. Reworded lightly; American
 * spelling; the 2023 typos fixed. Catalog links go to Combined Search
 * (/v2/search, mode=special), which survives the move to a new catalog;
 * the old catalog record links are gone.
 *
 * Images: Special Collections items from the Louisiana Digital Library,
 * converted from the LDL export's JP2 masters (2400px on the long side).
 * Each is imported once as an Image media item ("Collections: <file
 * name>", reused on a re-run) under public://special/collections:
 *   llmvc-mugnier-natchez.jpg      lsu-sc-gfm:61 (title image)
 *   llmvc-cox-letter.jpg           lsu-sc-bowmanturnbull:14
 *   llmvc-lytle-riverfront.jpg     lsu-sc-p15140coll12:43
 *   llmvc-jackson-square.jpg       lsu-sc-gfm:52
 *   llmvc-young-rivers.jpg         lsu-sc-dyp:119
 *   llmvc-long-inauguration.jpg    lsu-sc-hpl:10
 *   books-audubon-flamingo.jpg     lsu-sc-jjadp:21 (title image)
 *   books-ashburnham-noah.jpg      lsu-sc-mmf:11
 *   books-stones-iris.jpg          lsu-sc-msw:209
 *   books-audubon-crane.jpg        lsu-sc-jjadp:51
 * Every other gallery's lead image (eleven more JPEGs, in the same folder)
 * is listed in data/special-collections-leads.json; see
 * palefire_special_collections_leads.php. The book collections other than
 * Rare Book and McIlhenny have no pictures on the LDL: their entries are
 * text until Special Collections photographs them, and their galleries
 * open on manuscript facsimiles.
 *
 * Creates up to two Modular pages, UNPUBLISHED, with no "Exhibitions
 * showcase" listing (Not listed). A page whose title already exists is
 * skipped: delete it to rebuild it.
 */

declare(strict_types=1);

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\file\Entity\File;
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

foreach (['exhibit_page', 'exhibit_entry', 'exhibit_figure', 'exhibit_gallery', 'guide_text'] as $id) {
  if (!ParagraphsType::load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_exhibit_setup.php first.");
  }
}

$only = $args['only'] ?? NULL;
if ($only !== NULL && !in_array($only, ['llmvc', 'books'], TRUE)) {
  throw new \RuntimeException('only= takes llmvc or books.');
}
$images_dir = rtrim($args['images'] ?? '', '/\\');
if ($images_dir !== '' && !is_dir($images_dir)) {
  throw new \RuntimeException("images=$images_dir is not a folder on this server.");
}
if ($images_dir === '') {
  echo 'No images= folder: the pages are built without pictures.' . PHP_EOL;
}

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
echo "Text format: $format" . PHP_EOL;

// The Image media type and its source field.
$media_type = NULL;
foreach (\Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple() as $candidate) {
  if ($candidate->getSource()->getPluginId() === 'image' && ($media_type === NULL || $candidate->id() === 'image')) {
    $media_type = $candidate;
  }
}
if (!$media_type) {
  throw new \RuntimeException('No Image media type found.');
}
$source_field = $media_type->getSource()->getConfiguration()['source_field'];

$skipped = [];
$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];
$keep = fn (array $values) => array_filter($values, fn ($v) => $v !== NULL && $v !== '');
$ref = fn (?int $id) => $id ? ['target_id' => $id] : NULL;

// Images. --------------------------------------------------------------------

// Alt text: what each picture shows. The title images are decorative
// (the field says so), but carry alt for the media library.
$alt = [
  'llmvc-mugnier-natchez' => 'The steamboat Natchez at a New Orleans landing, its deck stacked high with cotton bales.',
  'llmvc-cox-letter' => 'A one-page handwritten letter from Nathaniel Cox in New Orleans to Daniel Turnbull, dated October 11, 1824.',
  'llmvc-lytle-riverfront' => 'A small mounted photograph of the Baton Rouge riverfront, low buildings along the water’s edge.',
  'llmvc-jackson-square' => 'A bird’s-eye view over the gardens of Jackson Square to the Mississippi River and its warehouses.',
  'llmvc-young-rivers' => 'Saxophonist James Rivers in a tuxedo and cap, holding his saxophone, against a plain studio backdrop.',
  'llmvc-long-inauguration' => 'Huey P. Long and former governor O. H. Simpson seated side by side in a crowd on Inauguration Day.',
  'books-audubon-flamingo' => 'Audubon’s uncolored proof of the American Flamingo, the bird bending its neck to the water.',
  'books-ashburnham-noah' => 'A facsimile page of the Ashburnham Pentateuch: Noah’s ark, its decks full of figures, above the floodwaters.',
  'books-stones-iris' => 'Margaret Stones’s watercolor of Virginia Iris: violet flowers on tall stems among long leaves.',
  'books-audubon-crane' => 'Audubon’s pen and ink drawing of a whooping crane over a pencil sketch, on stained paper.',
];

/** @var \Drupal\Core\File\FileSystemInterface $fs */
$fs = \Drupal::service('file_system');
$destination = 'public://special/collections';
if ($images_dir !== '') {
  $fs->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
}
$media_ids = [];

// One media item per file, reused when it exists ("Collections: <file>").
$media = function (string $file) use (&$media_ids, &$skipped, $images_dir, $fs, $destination, $media_type, $source_field, &$alt): ?int {
  if (array_key_exists($file, $media_ids)) {
    return $media_ids[$file];
  }
  $name = 'Collections: ' . $file;
  $existing = \Drupal::entityQuery('media')->accessCheck(FALSE)
    ->condition('bundle', $media_type->id())->condition('name', $name)->range(0, 1)->execute();
  if ($existing) {
    return $media_ids[$file] = (int) reset($existing);
  }
  $path = $images_dir . '/' . $file . '.jpg';
  if ($images_dir === '' || !is_file($path)) {
    $skipped[] = "Image not found: $file.jpg. Add it in the editor.";
    return $media_ids[$file] = NULL;
  }
  $uri = $fs->copy($path, "$destination/$file.jpg", FileExists::Rename);
  $saved = File::create(['uri' => $uri, 'status' => 1]);
  $saved->save();
  [$width, $height] = getimagesize($path) ?: [NULL, NULL];
  $item = Media::create([
    'bundle' => $media_type->id(),
    'name' => $name,
    'status' => 1,
    $source_field => [
      'target_id' => $saved->id(),
      'alt' => $alt[$file] ?? '',
      'width' => $width,
      'height' => $height,
    ],
  ]);
  $item->save();
  echo "Imported $file.jpg" . PHP_EOL;
  return $media_ids[$file] = (int) $item->id();
};

// Blocks. --------------------------------------------------------------------

$text = fn (string $body, string $style = 'default') => Paragraph::create([
  'type' => 'guide_text',
  'field_block_body' => $html($body),
  'field_text_style' => $style,
]);

$figure = fn (string $file, string $caption, string $layout = 'side_end') => Paragraph::create($keep([
  'type' => 'exhibit_figure',
  'field_figure_image' => $ref($media($file)),
  'field_figure_caption' => $html('<p>' . $caption . '</p>'),
  'field_figure_layout' => $layout,
]));

$group = fn (array $figures, string $caption) => Paragraph::create($keep([
  'type' => 'exhibit_gallery',
  'field_gallery_items' => $figures,
  'field_figure_caption' => $html('<p>' . $caption . '</p>'),
]));

// A collection: its name (a sub-link in the table of contents, with the old
// site's anchor where it had one), then its text and pictures as blocks.
// Not the Biography field, which would label the text "Biography".
$entry = fn (string $name, string $anchor, array $blocks) => Paragraph::create([
  'type' => 'exhibit_entry',
  'field_block_title' => $name,
  'field_section_anchor' => $anchor,
  'field_guide_blocks' => array_values(array_filter($blocks)),
]);

$has_display = (bool) FieldConfig::loadByName('paragraph', 'exhibit_page', 'field_exhibit_display');
$has_lead_caption = (bool) FieldConfig::loadByName('paragraph', 'exhibit_page', 'field_exhibit_lead_caption');
// Every gallery opens on a lead image. Manuscripts passes its own; the rest
// are in data/special-collections-leads.json (by gallery anchor: file,
// caption, alt), which palefire_special_collections_leads.php also reads to
// add them to pages built before they existed.
$lead_table = [];
foreach (json_decode((string) file_get_contents(__DIR__ . '/data/special-collections-leads.json'), TRUE) ?: [] as $page_leads) {
  $lead_table += $page_leads;
}
foreach ($lead_table as $row) {
  $alt[$row[0]] = $row[2];
}
$gallery = function (string $title, string $anchor, array $blocks, ?string $lead = NULL, ?string $lead_caption = NULL, string $focus = 'center') use ($keep, $ref, $media, $html, $has_display, $has_lead_caption, &$lead_table): Paragraph {
  if ($lead === NULL && isset($lead_table[$anchor])) {
    [$lead, $lead_caption] = $lead_table[$anchor];
  }
  return Paragraph::create($keep([
    'type' => 'exhibit_page',
    'field_section_title' => $title,
    'field_section_anchor' => $anchor,
    'field_exhibit_display' => $has_display ? 'story' : NULL,
    'field_exhibit_lead' => $lead ? $ref($media($lead)) : NULL,
    'field_exhibit_lead_focus' => $lead ? $focus : NULL,
    'field_exhibit_lead_caption' => $lead && $lead_caption && $has_lead_caption ? $html('<p>' . $lead_caption . '</p>') : NULL,
    'field_guide_blocks' => array_values(array_filter($blocks)),
  ]));
};

// Combined Search, Special Collections sources.
$search = fn (string $query) => '/v2/search?q=' . rawurlencode($query) . '&amp;mode=special';
$ask = '<p>Questions about the collections? <a href="/v2/special#ask-question">Ask Special Collections</a>.</p>';

// The pages. -------------------------------------------------------------------
// Galleries are built in closures, so a page that already exists imports no
// images and creates no paragraphs.

$pages = [];

$pages['llmvc'] = [
  'title' => 'Louisiana and Lower Mississippi Valley Collections',
  'path' => '/v2/special/llmvc',
  'kicker' => 'Collection',
  'subtitle' => 'The largest gathering of Louisiana’s history anywhere',
  'intro' => '<p>Papers of families, plantations, merchants and politicians, with books, maps, newspapers, sheet music and nearly a quarter of a million photographs, from the French and Spanish colonial years to today. The Louisiana and Lower Mississippi Valley Collections (LLMVC) are the largest gathering of materials on the region in existence.</p>',
  'keyfacts' => [
    'Manuscript groups: 5,000+',
    'Items: 10 million+',
    'Photographs: nearly 250,000',
  ],
  'action' => ['uri' => 'internal:/v2/search?mode=special', 'title' => 'Search the collections'],
  'prompt' => 'Read from the manuscripts to the photographs, or choose a part of the collection from the table of contents.',
  'cover' => 'llmvc-mugnier-natchez',
  'galleries' => fn () => [
    $gallery('Manuscripts', 'manuscripts', [
      $text(<<<'HTML'
<p>The LLMVC is one of the nation’s premier repositories of historical documents on the antebellum plantation, the Civil War and the Reconstruction South. More than 5,000 manuscript groups, over ten million items, hold the papers of individuals and families, the records of plantations, merchants and financial institutions, and the files of political, social and labor organizations.</p>
<p>The manuscripts cover Louisiana and the Lower Mississippi Valley from Memphis to New Orleans, and are especially strong for Natchez, St. Francisville, Baton Rouge and New Orleans. They reach back to the French and Spanish colonial periods, and the collecting goes on: 18th- and 19th-century manuscripts still arrive, along with large 20th-century collections on the region’s social, economic, political, cultural, literary, environmental and military history.</p>
HTML),
      $text(<<<'HTML'
<h3>Finding manuscripts</h3>
<ul>
<li><a href="/v2/special/guides/special-collections">How to Use Special Collections</a>: from a collection’s finding aid down to the box and folder you need.</li>
<li><a href="/v2/search?mode=special">Combined Search</a>: catalog records and PDF finding aids, searched together.</li>
</ul>
HTML),
    ], 'llmvc-cox-letter', 'Letter from Nathaniel Cox, a New Orleans cotton factor, to Daniel Turnbull, October 11, 1824. Bowman-Turnbull Family Papers.', 'top'),

    $gallery('Books', 'books', [
      $text(<<<'HTML'
<p>Beside its manuscripts, the LLMVC holds more than 50,000 books, periodicals, maps, state documents, newspapers, pieces of sheet music, recordings and other published materials, from the 17th century to the present, documenting the social, economic, political, cultural, literary and environmental history of Louisiana and its people.</p>
<p>It is especially strong in books on plantation life, Civil War-era Louisiana, and state and local politics, in fiction and poetry by Louisiana authors, and in early travel accounts of French colonial North America.</p>
<ul>
<li><a href="https://guides.lib.lsu.edu/Louisiana-History">Louisiana History Research Tools</a>: the published reference works most used for researching people and places in Louisiana history.</li>
<li><a href="/v2/special/bookcollections">Book Collections</a>: the named collections among Special Collections’ 150,000 books, from incunables to comic books.</li>
</ul>
HTML),
    ]),

    $gallery('Photographs', 'photographs', [
      $text(<<<'HTML'
<p>Photography arrived in the western hemisphere from France in late 1839. New Orleans first recorded it in 1840, and the same year saw the first photographic exhibition in Baton Rouge, when Jules Lyon came upriver from New Orleans to show his daguerreotypes. Photographs have been part of Louisiana history ever since.</p>
<p>The LLMVC holds nearly a quarter of a million photographic images. The earliest is a daguerreotype portrait of Varina Davis, wife of Jefferson Davis, made around 1845. Recent acquisitions include Jamie Baldridge’s <em>The Everywhere Chronicles</em>, winner of the 2008 Lucie Award. In between is a range of material on Louisiana’s people, places and events, and on people, places and events much further afield.</p>
<p>Many photographs are part of family papers or the records of businesses and organizations, but the LLMVC also holds studio collections: the Norman Studio (Natchez, Mississippi, about 1850–1950), Andrew D. Lytle (Baton Rouge, about 1863–1910), George François Mugnier (New Orleans, 1885–1910) and Fonville Winans (Baton Rouge). More recent is the work of Donn Young (New Orleans, about 1980–2005), salvaged after Hurricane Katrina.</p>
HTML),
      $group([
        $figure('llmvc-lytle-riverfront', 'Andrew D. Lytle, the Baton Rouge riverfront about 1863, before the levee was built. Carte de visite. Andrew D. Lytle Collection.', 'center'),
        $figure('llmvc-jackson-square', 'George François Mugnier, Bird’s-Eye View, Jackson Square and River, about 1885. Glass plate negative. Mugnier New Orleans Scenes.', 'center'),
        $figure('llmvc-young-rivers', 'Donn Young, saxophonist James Rivers, December 17, 2003. Donn Young Photographs.', 'center'),
      ], '140 years of Louisiana photography, from a Civil War carte de visite to a digital portrait.'),
      $text('<p>More than 20 of the photograph collections can be seen, in part, on the <a href="https://louisianadigitallibrary.org/">Louisiana Digital Library</a>.</p>'),
    ]),

    $gallery('Political Papers', 'political-papers', [
      $text(<<<'HTML'
<p>The LLMVC holds many political papers collections, with an emphasis on elected officials at the state and national levels. Among them are the personal papers of many of the most important figures in the region’s political history: Huey Long, Russell Long and other members of the Long family, and Senators J. Bennett Johnston and John Breaux. Other collections document party politics and the people around political office, such as consultants, staff, lobbying groups and statewide parties.</p>
HTML),
      $figure('llmvc-long-inauguration', 'Huey P. Long and former governor O. H. Simpson on Inauguration Day, Baton Rouge, May 21, 1928. Huey P. Long Photograph Album.'),
      $text('<p>Their finding aids are in <a href="/v2/search?mode=special">Combined Search</a> with the rest. Search by name, for example <a href="' . $search('Russell Long') . '">Russell Long</a> or <a href="' . $search('Breaux') . '">John Breaux</a>.</p>'),
    ]),

    $gallery('Louisiana Newspapers', 'louisiana-newspapers', [
      $text(<<<'HTML'
<p>Since the 1930s, LSU Libraries has collected and preserved Louisiana newspapers, historical and current, with preservation microfilm from the 1940s. From 1986 the Libraries took part in the United States Newspaper Program, sponsored by the Library of Congress and funded by the National Endowment for the Humanities, which identified, located and cataloged more than 1,700 Louisiana newspaper titles, some from as early as 1794.</p>
<p><a href="/v2/special/guides/louisiananewspapers">Louisiana Newspapers</a>: what we hold, parish by parish, and where to read it.</p>
HTML),
    ]),

    $gallery('Giving to the collections', 'giving', [
      $text(<<<'HTML'
<p>The LLMVC is built on gifts from individuals, families and organizations. It welcomes printed materials, manuscripts and photographs in its collecting areas.</p>
<p><a href="/v2/special/guides/donating-materials">Donating Materials to Special Collections</a>: what we collect and how to offer it.</p>
HTML),
      $text($ask),
    ]),
  ],
];

$subjects = [
  'book-history' => 'History of Books and Printing',
  'medieval' => 'Medieval & Renaissance Manuscript Facsimiles',
  'art' => 'Art & Art History',
  'architecture' => 'Architecture & Landscape Architecture',
  'art-nouveau' => 'Arts & Crafts and Art Nouveau Book Design',
  'japanese-illustrated-books' => 'Japanese Illustrated Books & Prints',
  'history' => 'History',
  'classical-studies' => 'Classical Studies',
  'french-history' => 'French History',
  'slavery' => 'History of Slavery',
  'latin-america' => 'Latin American History',
  'caribbean' => 'Caribbean History',
  'literature-languages' => 'Literature & Languages',
  'british-literature1' => 'British Literature, 1550–1700',
  'british-literature2' => 'British Literature, 1700–1799',
  'dictionaries' => 'Dictionaries & Language Study',
  'religion' => 'Religion',
  'new-acquisitions' => 'Recent Acquisitions',
];
$subject_list = '<ul>';
foreach ($subjects as $slug => $label) {
  $subject_list .= '<li><a href="https://guides.lib.lsu.edu/rarebooks/' . $slug . '">' . str_replace('&', '&amp;', $label) . '</a></li>';
}
$subject_list .= '</ul>';

$pages['books'] = [
  'title' => 'Book Collections',
  'path' => '/v2/special/bookcollections',
  'kicker' => 'Collection',
  'subtitle' => 'Five centuries of print, from incunables to comic books',
  'intro' => '<p>More than 150,000 books for study and teaching in fields from ancient history to present-day Louisiana. They reach back more than five hundred years, to the first books printed in Europe, with natural history and scientific illustration a particular strength.</p>',
  'keyfacts' => [
    'Books: 150,000+',
    'Rare Book Collection: 25,000+ titles',
    'Earliest: 15th century',
  ],
  'action' => ['uri' => 'internal:/v2/search?mode=special', 'title' => 'Search the collections'],
  'prompt' => 'Browse the collections in order, or choose one from the table of contents.',
  'cover' => 'books-audubon-flamingo',
  'galleries' => fn () => [
    $gallery('Finding books', 'finding-books', [
      $text(<<<'HTML'
<p>Every book in Special Collections is in the catalog, and you can search it with the other Special Collections sources in <a href="/v2/search?mode=special">Combined Search</a>.</p>
<p>Many rare books on the history and culture of Louisiana belong to the <a href="/v2/special/llmvc">Louisiana and Lower Mississippi Valley Collections</a>, whose 50,000 published items are described there.</p>
<p>Teaching? Librarians build class sessions around rare books, from the history of printing to books as material culture. See <a href="/v2/special/programs/teaching">Teaching with Special Collections</a>.</p>
HTML),
      $text('<h3>Browse by subject</h3><p>The Guide to Rare Books keeps detailed checklists of rare books in selected subjects, for research, presentations and creative projects.</p>' . $subject_list),
    ]),

    $gallery('Foundations', 'foundations', [
      $entry('Rare Book Collection', 'rarebookcollection', [
        $text(<<<'HTML'
<p>The Rare Book Collection begins with European printing in the fifteenth century. Its strengths include the history of books and printing, eighteenth-century British literature, Christianity, travel and exploration, fine printing and book arts, and the history of slavery. With more than twenty-five thousand titles, it is rich source material for research, teaching and creative projects.</p>
HTML),
        $figure('books-ashburnham-noah', 'Noah’s ark, from a facsimile of the Ashburnham Pentateuch, a sixth-century manuscript of the first books of the Bible. One of more than 125 manuscript facsimiles at Hill.'),
        $text('<p>Highlights include several incunables (books from the first fifty years of printing), <a href="' . $search('Dürer') . '">Albrecht Dürer’s</a> book on perspective and the shaping of letters, atlases by <a href="' . $search('Ptolemy') . '">Ptolemy</a> and <a href="' . $search('Ortelius') . '">Ortelius</a>, and Denis Diderot’s <a href="' . $search('Encyclopédie Diderot') . '"><em>Encyclopédie</em></a>. There are first editions of Edmund Spenser’s <a href="' . $search('Faerie Queene') . '"><em>The Faerie Queene</em></a> and John Donne’s <a href="' . $search('Donne Poems') . '"><em>Poems</em></a>, the <a href="' . $search('Shakespeare comedies histories tragedies 1632') . '">Second Folio</a> of Shakespeare’s plays (1632), and William Morris’s edition of Chaucer, the <a href="' . $search('Kelmscott Chaucer') . '">Kelmscott Chaucer</a>.</p>'
          . '<p>Among other notable works are a 1610 copy of John Foxe’s <a href="' . $search('Foxe Book of Martyrs') . '"><em>Book of Martyrs</em></a>, Lord Kingsborough’s <a href="' . $search('Antiquities of Mexico') . '"><em>Antiquities of Mexico</em></a> and Edward Curtis’s <a href="' . $search('Curtis North American Indian') . '"><em>The North American Indian</em></a>. One of the collection’s most important items is the <a href="' . $search('Description de l’Égypte') . '"><em>Description de l’Égypte</em></a>, the illustrated record of Egypt’s antiquities and natural history made by the French scholars who went with Napoleon’s expedition. There are also high-quality reproductions of more than 125 <a href="https://guides.lib.lsu.edu/rarebooks/medieval">medieval and Renaissance manuscripts</a>.</p>'),
      ]),
      $entry('E. A. McIlhenny Natural History Collection', 'eamcilhennynaturalhistorycollection', [
        $text(<<<'HTML'
<p>In 1971 John Stauffer McIlhenny gave LSU the natural history portion of the library of his uncle, Edward Avery McIlhenny, of the Louisiana family that has made Tabasco pepper sauce since 1868. E. A. McIlhenny was also a well-known conservationist who founded a bird sanctuary and a botanical garden, Jungle Gardens, on the family’s land at Avery Island. His books joined the LSU Libraries’ own holdings, and the collection has grown ever since.</p>
<p>It is particularly strong in botanical and ornithological illustration. Highlights include the double elephant folio of John James Audubon’s <em>Birds of America</em>; an archive of original drawings, some in Audubon’s own hand; important works by Mark Catesby, John Gould, Edward Lear and Sir Joseph Banks; a complete run of <em>Curtis’s Botanical Magazine</em>, published without a break since 1787; and the original watercolors of the <em>Flora of Louisiana</em> by the Australian botanical artist Margaret Stones.</p>
HTML),
        $group([
          $figure('books-audubon-crane', 'John James Audubon, Whooping Crane, pen and ink over pencil, for the first octavo edition of Birds of America (1840–1844). John James Audubon Drawings and Proofs.', 'center'),
          $figure('books-stones-iris', 'Margaret Stones, Virginia Iris, watercolor. Native Flora of Louisiana.', 'center'),
        ], 'Both are on the Louisiana Digital Library with the rest of their collections.'),
        $text('<p>See them in person on Audubon Day, when the double elephant folio is shown, or online in <a href="/v2/special/exhibitions/audubon">John James Audubon in Louisiana</a>.</p>'),
      ]),
    ]),

    $gallery('Poetry and fiction', 'poetry-and-fiction', [
      $entry('Wyatt Houston Day Collection of Poetry by African Americans', 'wyatthoustonday', [
        $text(<<<'HTML'
<p>The book dealer Wyatt Houston Day assembled his collection of African American poetry over a lifetime as an enthusiastic collector and scholar. Its more than 800 items are anchored by Phillis Wheatley’s <em>Poems on Comic, Serious and Moral Subjects</em> (London, 1787). Other highlights include a comprehensive collection of the works of Paul Laurence Dunbar, a wide sampling of the Harlem Renaissance, and many works signed by Amiri Baraka.</p>
HTML),
      ]),
      $entry('Andrei Codrescu Collection of Outsider Literature', 'codrescu', [
        $text(<<<'HTML'
<p>Andrei Codrescu, a Romanian émigré, taught English at LSU from 1984 until he retired in 2009. The author of more than 30 books, he was the founding editor of the literary journal <em>Exquisite Corpse</em>. His collection holds about 5,000 volumes of late 20th-century fiction and poetry, many of them ephemeral or from small presses. Codrescu’s personal papers are also in Special Collections.</p>
HTML),
      ]),
      $entry('David Madden Collection of Civil War Fiction', 'madden', [
        $text(<<<'HTML'
<p>Established in 2002, this collection focuses on Civil War fiction published after 1950, complementing the Richard H. Wilmer, Jr. Collection of mostly earlier Civil War novels at the University of North Carolina. While researching his novel <em>Sharpshooter</em> (1996), the LSU English professor David Madden gathered a large collection of Civil War fiction. He gave the novels to the LSU Libraries through the former United States Civil War Center, and they form the core of the collection.</p>
HTML),
      ]),
      $entry('Michael Lehman Williamson Collection of Civil War Books for Young People', 'williamson', [
        $text(<<<'HTML'
<p>Established in 1994 by the Lehman Williamson family in memory of their son, Michael, this collection holds books published from 1862 to the present. Other major donors include BellSouth and Mrs. George Ann Brown, in memory of her husband, Felton. Unique in the nation, it has more than 900 books about the Civil War written for children: biographies, fiction about children’s lives during the war, and nonfiction. A selection was shown at Hill Memorial Library in 2002 as <em>Blue and Gray for Boys and Girls: An Exhibition of Children’s Civil War Literature</em>.</p>
HTML),
      ]),
    ]),

    $gallery('Imagination', 'imagination', [
      $entry('Laughlin Collection', 'laughlincollection', [
        $text(<<<'HTML'
<p>In 1986 LSU bought the library of the New Orleans photographer Clarence John Laughlin, the father of American surrealist photography and author of <a href="/v2/search?q=Ghosts%20along%20the%20Mississippi&amp;mode=special"><em>Ghosts along the Mississippi</em></a> (1948). First called “The Laughlin Library of the Arts,” it has more than thirty thousand items, with a focus on science fiction, fantasy and mystery writing. Other subjects include 19th- and 20th-century art and design (particularly Arts and Crafts and Art Nouveau book design), photography, Victoriana, humor, sex, psychology, spiritualism, surrealism and the occult.</p>
HTML),
      ]),
      $entry('Russell Mann Sherlock Holmes Research Collection', 'sherlock-holmes', [
        $text(<<<'HTML'
<p>This extensive collection came in 2016 from Dr. Russell Mann, a retired professor of journalism at the University of Louisiana at Lafayette, who began building it in the 1990s. It is especially strong in “non-canonical” fiction (Holmes stories by authors other than Arthur Conan Doyle), comic books and graphic novels featuring Holmes, Holmes on stage, screen and radio, and rare scholarly publications, including the journals of Holmes societies around the world. Hill Memorial Library showed items from it in the fall of 2016.</p>
HTML),
      ]),
      $entry('Bowlus Comic Book Collection', 'bowlus', [
        $text(<<<'HTML'
<p>The William Morton Bowlus Collection holds more than 7,000 comic books, many from the Silver Age (the late 1950s and 1960s), and related materials. Bowlus, an LSU alumnus, collected comic books from grade school until his untimely death in his late twenties. His mother, Martha Bowlus, gave the collection to the LSU Libraries to honor his memory and keep together a collection he loved. It offers a wealth of graphic art and storytelling from a formative period in American culture.</p>
HTML),
      ]),
    ]),

    $gallery('Games of skill and chance', 'games', [
      $entry('Gladney Chess Collection', 'chess', [
        $text(<<<'HTML'
<p>Part of the personal collection of the late Frank Gladney, this collection holds nineteenth- and twentieth-century books and periodicals on the history of chess: instruction books, biographies of famous players, and American and European chess magazines and newsletters, with a small number of ephemeral publications.</p>
HTML),
      ]),
      $entry('Carriere Collection of Poker and Hoyle', 'poker', [
        $text(<<<'HTML'
<p>Judge Oliver P. Carriere, a New Orleans jurist, turned his hobby, the study and play of poker, into a large and comprehensive collection of books on the game. It includes most of the early editions of the <a href="/v2/search?q=Hoyle&amp;mode=special">works of Edmond Hoyle</a> (1672–1769), the eighteenth-century English writer on fashionable card games and, later, board games such as chess and backgammon. Carriere also collected nearly all of the vast literature on poker. Together they document the history of games of chance and other pastimes, and the complex social and legal questions around them. The library also holds Judge Carriere’s personal correspondence.</p>
HTML),
      ]),
    ]),

    $gallery('Subject collections', 'subject-collections', [
      $entry('Judge Warren Jones Lincoln Collection', 'lincoln', [
        $text(<<<'HTML'
<p>Warren L. Jones, a native of Nebraska, had a distinguished legal career in Florida. Early in life he took an interest in Abraham Lincoln, and in time he built an extensive collection of books, pamphlets and printed ephemera on Lincoln. Drawn to LSU by the prominence of the Civil War historian T. Harry Williams, Judge Jones gave his collection to the university in 1970.</p>
HTML),
      ]),
      $entry('Rhoades Crayfish Collection', 'crayfish', [
        $text(<<<'HTML'
<p>Spanning four centuries, this exhaustive collection of scientific literature on the taxonomy and culture of crayfish (the scientific, not the culinary, spelling of crawfish) was the working library of the Ohio zoologist and aquaculture pioneer Rendell Rhoades, a professor of biology and director of the Science Division at Ashland College. From his undergraduate days until his death in 1976, Rhoades collected every book that mentioned crayfish. It came to LSU through a joint gift and purchase from his widow, Nancy Rhoades.</p>
<p>The texts range from a 1548 edition of Pliny’s <em>History of the World</em> to the <em>Natural History of Economic Crustaceans of the United States</em> (1893), which observes that “Americans, as a rule, do not regard the eating of cray-fishes with much favor.” Many rare and important early works of natural history are included, in French, Spanish, Italian, Dutch, German, Russian, Chinese and, of course, Latin.</p>
HTML),
      ]),
      $text($ask),
    ]),
  ],
];

// Build. -------------------------------------------------------------------------

$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');
foreach (['field_exhibit_kicker', 'field_exhibit_keyfacts', 'field_exhibit_action', 'field_exhibit_cover'] as $name) {
  if (!isset($node_fields[$name])) {
    $skipped[] = "$name is not on Modular page: re-run palefire_exhibit_setup.php, then fill it in on each page.";
  }
}
$showcase_values = isset($node_fields['field_exhibit_showcase'])
  ? ($node_fields['field_exhibit_showcase']->getFieldStorageDefinition()->getSetting('allowed_values') ?? [])
  : [];

foreach ($pages as $key => $page) {
  if ($only !== NULL && $only !== $key) {
    continue;
  }
  $found = \Drupal::entityQuery('node')->accessCheck(FALSE)
    ->condition('type', 'modular_page')
    ->condition('title', $page['title'])
    ->range(0, 1)->execute();
  if ($found) {
    echo "Skipped '{$page['title']}': it already exists (node " . reset($found) . ').' . PHP_EOL;
    continue;
  }
  $taken = \Drupal::service('path_alias.manager')->getPathByAlias($page['path']);
  if ($taken !== $page['path']) {
    throw new \RuntimeException("{$page['path']} is already the address of $taken. Free it first.");
  }

  $values = [
    'type' => 'modular_page',
    'title' => $page['title'],
    'status' => 0,
    'field_scroll_section' => $page['galleries'](),
    'field_modular_type' => 'exhibition',
    'field_exhibit_title_style' => 'sans',
    'field_exhibit_subtitle' => $page['subtitle'],
    'field_intro' => $html($page['intro']),
    'field_exhibit_prompt' => $page['prompt'],
    'field_exhibit_kicker' => $page['kicker'],
    'field_exhibit_keyfacts' => array_map(fn ($line) => ['value' => $line], $page['keyfacts']),
    'field_exhibit_action' => $page['action'],
    'field_exhibit_cover' => $ref($media($page['cover'])),
    'field_exhibit_showcase' => isset($showcase_values['hidden']) ? 'hidden' : NULL,
    'path' => ['alias' => $page['path']],
  ];
  foreach (array_keys($values) as $name) {
    if (str_starts_with($name, 'field_') && !isset($node_fields[$name])) {
      unset($values[$name]);
    }
  }

  $node = Node::create(array_filter($values, fn ($v) => $v !== NULL));
  $node->setRevisionLogMessage("Collection page: {$page['title']}, from the old site's pages (palefire_special_collections_pages.php).");
  $node->save();
  echo 'Created unpublished page: node/' . $node->id() . ' (' . $node->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
}

foreach (array_unique($skipped) as $line) {
  echo 'Skipped: ' . $line . PHP_EOL;
}
echo 'Review both pages, then publish them and run palefire_special_collections_section.php. Run: drush cr' . PHP_EOL;
