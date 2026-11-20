<?php

/**
 * @file
 * Guide page: "Special Collections Exhibitions", every change made to the text,
 * layout and images of the Special Collections online exhibitions (AVES,
 * Audubon, Occult, Union Art Gallery, Free People of Color) when they moved to Palefire, and the
 * open proposals for AVES's biographies,
 * for curators to check: what was corrected (old → new), what to verify,
 * and what was written new. As a Modular page in guide mode. Run
 * palefire_guide_setup.php and palefire_guide_blocks_setup.php first.
 *
 *   drush php:script scripts/palefire_guide_special_exhibitions.php
 *   drush php:script scripts/palefire_guide_special_exhibitions.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. Built as palefire_guide_palefire_redesign.php:
 * this script is the page's source and can be run again. A new page is
 * created UNPUBLISHED; after that each build replaces the intro and
 * sections in a new revision, and stops if the page was edited in Drupal or
 * has a newer draft (force=1 overrides the first).
 *
 * Optional arguments after "--": apply=1, force=1, format=<text format>,
 * path=<alias> (default /v2/guides/special-collections-exhibitions).
 *
 * The exhibitions' sections come from docs/special-exhibition-corrections/*.md,
 * written by the sessions that built each exhibition. Each old → new
 * correction becomes a table row (Where | Before | After), the old text in
 * <del> and the new in <ins>, styled by _guide-corrections.scss.
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

foreach (['guide_section', 'guide_text', 'guide_accordion'] as $id) {
  if (!\Drupal::entityTypeManager()->getStorage('paragraphs_type')->load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_guide_setup.php first.");
  }
}
foreach (['guide_flow', 'guide_status_board', 'guide_stats', 'guide_code'] as $id) {
  if (!\Drupal::entityTypeManager()->getStorage('paragraphs_type')->load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_guide_blocks_setup.php first.");
  }
}

$title = 'Special Collections Exhibitions';
$old_titles = [];

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
$html_filter = $formats[$format]->filters('filter_html');
if ($html_filter && $html_filter->status) {
  $allowed = (string) ($html_filter->settings['allowed_html'] ?? '');
  foreach (['table', 'thead', 'del', 'ins', 'em'] as $tag) {
    if (!preg_match('/<' . $tag . '[\s>]/', $allowed)) {
      echo "Warning: the $format format strips <$tag>, so the corrections tables lose their styling. Allow it in the format." . PHP_EOL;
    }
  }
}

// Content helpers: plain arrays, so a dry run creates nothing and the
// fingerprint covers exactly what the page will say. ------------------------

$sections = [];
$section = function (string $heading, array $blocks) use (&$sections): void {
  $sections[] = ['title' => $heading, 'blocks' => $blocks];
};
$text = fn (?string $heading, string $body, string $style = 'default') => [
  'kind' => 'text',
  'heading' => $heading,
  'body' => $body,
  'style' => $style,
];
$accordion = fn (?string $heading, array $items, string $intro = '') => [
  'kind' => 'accordion',
  'heading' => $heading,
  'intro' => $intro,
  'items' => $items,
];
$flow = fn (?string $heading, array $steps, string $caption = '') => [
  'kind' => 'flow',
  'heading' => $heading,
  'steps' => $steps,
  'caption' => $caption,
];
$step = fn (string $title, string $note = '', string $kind = 'step', string $link = '', string $group = '', string $if = '', string $then = '', string $then_note = '', string $tone = 'plain') => [
  'title' => $title,
  'note' => $note,
  'kind' => $kind,
  'link' => $link,
  'group' => $group,
  'if' => $if,
  'then' => $then,
  'then_note' => $then_note,
  'tone' => $tone,
];
$status = fn (?string $heading, array $items) => [
  'kind' => 'status',
  'heading' => $heading,
  'items' => $items,
];
$stats = fn (?string $heading, array $items) => [
  'kind' => 'stats',
  'heading' => $heading,
  'items' => $items,
];

$intro = '<p>How the online exhibitions of LSU Libraries Special Collections moved to the new site: what changed on each page, corrections to the old text, things to check, wording written new, and what is proposed next for biographies. For curators and editors to review, one exhibition at a time.</p>';

// Sections. ----------------------------------------------------------------

$section('How to use this page', [
  $text(NULL, <<<'HTML'
<p>This page records how the online exhibitions of LSU Libraries Special Collections moved to the new site, and what is still open. Each exhibition has four lists:</p>
<ul>
<li><strong>Changed on the page:</strong> how the exhibition is laid out now: galleries, walls, transcriptions, readings and images added.</li>
<li><strong>Corrected:</strong> changes made to the old text, written as old → new: typos, names, dates and punctuation.</li>
<li><strong>To verify:</strong> things left as written but in doubt, or that couldn’t be confirmed. These need a curator’s answer.</li>
<li><strong>New wording to check:</strong> text with no old counterpart, such as alt text, captions written new, credit lines and gallery lead captions.</li>
</ul>
<p>Changes made the same way everywhere are described once, in How the exhibitions were moved, and not listed per exhibition. The oral history exhibitions have their own page, the Exhibition Migration Review.</p>
<p>To report a mistake, or to answer a To verify item, note the exhibition and the gallery or entry, and send it to the web team. A fix is made in the exhibition’s data and then on the page, so a rebuild keeps it.</p>
HTML),
]);

$section('How the exhibitions were moved', [
  $text(NULL, <<<'HTML'
<h3>Where they came from</h3>
<ul>
<li><strong>AVES</strong> (2012) and <strong>Occult Science and Philosophy in the Renaissance</strong> (2011) were Hill Online Exhibitions on exhibitions.blogs.lib.lsu.edu, a blog that is gone. Their text comes from the Wayback Machine’s copies.</li>
<li><strong>John James Audubon in Louisiana</strong> (2021) was at lib.lsu.edu/exhibitions/john-james-audubon. The Wayback Machine has its landing and section pages; the bird and manuscript pages come from the students’ copy at /audubon.</li>
<li><strong>LSU Union Art Gallery</strong> was a six-page showcase on libstage, never on the public site.</li>
<li><strong>Free People of Color in Louisiana</strong> (2015) was a set of static pages at /sites/all/files/sc/fpoc, later copied to Showcase pages under /fpoc. Its text comes from the static pages.</li>
</ul>
<p>Each now lives at /v2/special/exhibitions/&lt;name&gt;, as one page of galleries. Each is built by a script from a data file in the theme (scripts/data/&lt;name&gt;.json), so it can be rebuilt, and every correction is made in the data file as well as on the page.</p>
<h3>Text</h3>
<ul>
<li>Carried over word for word, then corrected only where listed in each exhibition’s Corrected list.</li>
<li>Text the old pages had saved in the wrong character encoding was decoded back to the original characters exactly.</li>
<li>Straight quotes and apostrophes became curly ones (’ “ ”), and spaced hyphens used as dashes became dashes. These are not listed one by one.</li>
<li>“Middleton Library” became “Main Library”.</li>
<li>Links between the old pages point at the new galleries and entries. Links to other sites open in a new tab and say so.</li>
<li>Every image has new alt text written for people who use screen readers. Alt text is listed under New wording to check.</li>
</ul>
<h3>Layout</h3>
<ul>
<li><strong>Galleries</strong> take the place of the old pages, each with a lead image and a number in the contents.</li>
<li><strong>Entries</strong> are the people, books, birds and shows inside a gallery, each with its own heading and anchor, so other pages can link to it.</li>
<li><strong>Walls</strong> show a gallery’s images side by side at their own proportions, each opening full screen with its caption and text (Audubon’s editions and drawings, all of the Union Art Gallery, Occult’s books, and AVES’s people and photographs). A person with no portrait is a name tile.</li>
<li><strong>Folds</strong>: a long opening essay or text shows its first lines with Read more, and a long Sources list its first twelve.</li>
<li><strong>Catalog records</strong> are set apart from the text, and readings are typeset as covers linked to the catalog.</li>
</ul>
<h3>Images</h3>
<ul>
<li>Each original (4K where there was one) is imported once into the media library, named after its exhibition, so an image used twice is one item and a better scan can replace it everywhere at once.</li>
<li>On the page, each image is served as WebP at the size the screen needs (640, 1,280, 1,920 or 2,560 pixels wide) and loads only as it comes into view.</li>
</ul>
HTML),
]);

$section('AVES', [
  $text(NULL, <<<'HTML'
<p>Now at <a href="/v2/special/exhibitions/aves">/v2/special/exhibitions/aves</a>.</p>
<p>Exhibition: AVES: A Survey of the Literature of Neotropical Ornithology (Hill Online Exhibitions, 2012), selections from the E. A. McIlhenny Natural History Collection, funded by the Coypu Foundation.<br>Old site: the Wayback Machine’s copies of exhibitions.blogs.lib.lsu.edu/?p=5206, all 20 pages and the 53 “Read more” pop-ups.</p>
<h3>Changed on the page</h3>
<ul><li>The 20 pages are 19 galleries on one page. Each person or book is an entry with its own heading and anchor, so a link such as <code>#schauensee</code> lands on it.
</li><li>Each pop-up’s full biography replaces the shortened lead the gallery showed. The “Image:” and “Biography:” labels became the figure and the biography themselves. 43 entries have one, from 118 to 1,293 words. See Biographies, below.
</li><li>Links between pages point at the new anchors. The Biographies gallery is now a timeline of the lives in the order born, each opening its biography (2026-11-20), in place of its hand-written list of names; Humboldt’s life, told in Opening Scientific Exploration, is linked from its intro.
</li><li>Six galleries hang as walls (2026-11-20): Collecting by Individuals, Museum Men, Collecting by Museums and Life Histories, each chapter’s essay as the wall’s intro and a tile for each person (a name tile where there is no portrait), and People and Places and Modern Fieldwork, each photograph an entry with a short title. Life dates moved from the headings into each entry’s Date; the anchors stay as they were.
</li><li>Entry names written in capitals use Capital Case (Francisco Hernández, Georg Markgraf, Juan Ignacio Molina and others).
</li><li>16 of the 17 images the 2012 site showed without a 4K file were added from “Section 4 (additions)”, made from the catalog’s TIFFs.
</li><li>Unused 4K plates and photographs became the galleries’ lead images.
</li></ul>
<h3>Corrected</h3>
<p>Names (entry titles, so also the contents and “In this gallery”):</p>
<table>
<thead><tr><th scope="col">Before</th><th scope="col">After</th></tr></thead>
<tbody>
<tr><td><del>Alcide d’Orbigny(1802-1857)</del></td><td><ins>Alcide d’Orbigny (1802-1857)</ins></td></tr>
<tr><td><del>John Gould 1804-1881)</del></td><td><ins>John Gould (1804-1881)</ins></td></tr>
<tr><td><del>Robert Schomburgk 1804-1865)</del></td><td><ins>Robert Schomburgk (1804-1865)</ins></td></tr>
<tr><td><del>William Phelps, Jr.(1902-1988)</del></td><td><ins>William Phelps, Jr. (1902-1988)</ins></td></tr>
<tr><td><del>Louis Jean Pierre Vieillot (1610-1644)</del></td><td><ins>Louis Jean Pierre Vieillot (1748-1831)</ins> <em>the dates were Markgraf’s</em></td></tr>
<tr><td><del>Francis L. De LaPorte</del></td><td><ins>Francis L. de Laporte</ins></td></tr>
</tbody>
</table>
<p>Text:</p>
<table>
<thead><tr><th scope="col">Before</th><th scope="col">After</th></tr></thead>
<tbody>
<tr><td><del>tyrant- flycatchers</del></td><td><ins>tyrant-flycatchers</ins></td></tr>
<tr><td><del>fast- growing</del></td><td><ins>fast-growing</ins></td></tr>
<tr><td><del>ninety- three</del></td><td><ins>ninety-three</ins></td></tr>
<tr><td><del>Markgraf’s <em>Historia naturalis Brasiliae,</em> (Leiden</del></td><td><ins>Markgraf’s <em>Historia naturalis Brasiliae</em> (Leiden</ins></td></tr>
<tr><td><del>hummingbirds;and</del></td><td><ins>hummingbirds; and</ins></td></tr>
<tr><td><del>rooms,artificial</del></td><td><ins>rooms, artificial</ins></td></tr>
<tr><td><del>mammals,and</del></td><td><ins>mammals, and</ins></td></tr>
<tr><td><del>setting,and</del></td><td><ins>setting, and</ins></td></tr>
<tr><td><del>habitudes.Rio</del></td><td><ins>habitudes. Rio</ins></td></tr>
<tr><td><del>to the base camp,</del></td><td><ins>to the base camp.</ins> <em>end of a paragraph</em></td></tr>
<tr><td><del>passes rapidly from from blossom</del></td><td><ins>passes rapidly from blossom</ins></td></tr>
<tr><td><del>jail– “a putrid</del></td><td><ins>jail – “a putrid</ins></td></tr>
<tr><td><del>of the world –that</del></td><td><ins>of the world – that</ins></td></tr>
<tr><td><del>and others– who</del></td><td><ins>and others – who</ins></td></tr>
<tr><td><del>ornithologists– Ridgway</del></td><td><ins>ornithologists – Ridgway</ins></td></tr>
<tr><td><del>perfect mentor– I</del></td><td><ins>perfect mentor – I</ins></td></tr>
<tr><td><del>(Weimer, 1825-33)</del></td><td><ins>(Weimar, 1825-33)</ins></td></tr>
<tr><td><del>(Weimer, 1822-1831)</del></td><td><ins>(Weimar, 1822-1831)</ins></td></tr>
<tr><td><del>Charles Chubbs’s</del></td><td><ins>Charles Chubb’s</ins></td></tr>
<tr><td><del>took himwest</del></td><td><ins>took him west</ins> <em>Hernández, 2026-11-20</em></td></tr>
<tr><td><del>Middleton Library</del></td><td><ins>Main Library</ins> <em>every text</em></td></tr>
</tbody>
</table>
<h3>To verify</h3>
<ul><li>Schomburgk’s <em>Twelve Views</em> has no file in any of the image folders, so its figure has no image. Is there a scan?
</li><li>The lead images’ captions are taken from the print catalog’s illustration lists. Seven leads (Greenfield, Red-capped Manakin, the Andes, Buenaventura, Chubb paddling, the ongoing expedition and the Solitary Eagle) have no source and are decorative, with no caption.
</li><li>Modern Fieldwork, “Transportation on the Rio Shesha”: the caption ends with “Unfortunately, the lush scenery and abundant wildlife experienced by Weid are gone; today the Rio Doce valley is almost completely deforested.”, the last sentence of the Wied caption in People and Places, and opens a quotation from Stap it never closes. Both as on the 2012 site. Remove the sentence?
</li></ul>
<h3>New wording to check</h3>
<ul><li>The 16 titles for the photographs on the People and Places and Modern Fieldwork walls (2026-11-20), each written from its caption: People and Places: The interior of Guiana, Wied on the Rio Doce, Spix and a ritual drink, Bates mobbed by aracaris, Camp in British Guiana, The Beebes at the barranca, Blair Beebe’s tent, Two castles, Field research today, Candles and computers. Modern Fieldwork: John O’Neill on the Rio Heath, 1977; On the Rio Shesha, 1987; Condor Expedition, 2006; Life in the Condor camp; Preparing Jocotoco antpittas; Tumbes, 2009. And the Biographies timeline’s intro.
</li><li>The 42 biography cards’ Summary and Role (<code>scripts/palefire_exhibit_aves_bio_cards.php</code>, 2026-11-20): two sentences each, drafted only from that entry’s own biography. Change any on the entry’s edit form; the script never overwrites a filled field.
</li><li>The 16 gallery lead captions, the credit line and alt text for every image.
</li></ul>
HTML),
]);

$section('Free People of Color in Louisiana: Revealing an Unknown Past', [
  $text(NULL, <<<'HTML'
<p>Built at <a href="/v2/special/exhibitions/fpoc">/v2/special/exhibitions/fpoc</a>, not published yet.</p>
<p>Exhibition: Free People of Color in Louisiana: Revealing an Unknown Past, the online exhibition of the NEH grant project (2013–2015) of LSU Libraries Special Collections, the Louisiana State Museum, the New Orleans Public Library, The Historic New Orleans Collection and Tulane University’s Louisiana Research Collection.<br>Old site: the static pages at /sites/all/files/sc/fpoc (2015), copied to twelve Showcase pages under /fpoc (with /special/fpoc), whose characters were mis-encoded. The text is taken from the static pages.</p>
<h3>Changed on the page</h3>
<ul><li>Eleven galleries: Introduction; the six chapters of Michael Taylor’s history (Contexts, Early Days, Transition, Golden Age, Decline and Civil War, Legacies); The Collections; Terminology; Resources; About the Project.
</li><li>The Collections: one entry per partner, each with a link to its research center, and its 60 collections as paragraphs.
</li><li>Resources: the Selected Bibliography is a Sources list; the three lists of links follow.
</li><li>About the Project takes in the Grant Team page.
</li><li>Pictures: the old pages had four, at 250px. Three came from The Historic New Orleans Collection’s own holdings and aren’t in the digital collection (the Jules Lion lithograph of the cathedral, the portrait of Victor Séjour, and the <em>Variétés du Carnaval</em> sheet music), so they are left out. The twelve pictures on the new page all come from the project’s collection in the Louisiana Digital Library, made from its 4,000px masters: Bellazire Meullion’s 1845 receipt (from the old About page, now at full size), the Warburg letter (the cover), Marianne’s petition, the Circe act of freedom, the Durand letter, the Lemelle certification, the receipt to Rose signed by Andrew Jackson, the Durnford memorandum book, the Register of free colored persons (its cover and a page), the 1860 sheriff’s sale broadside and the Desdunes prospectus.
</li><li>Lead pictures for the three galleries that had none, each tied to something already on the page: The Collections opens on Eugène Warburg’s 1851 sketch of a marble floor for St. Louis Cathedral, from the Samuel Wilson, Jr. Collection of St. Louis Cathedral Papers, which also holds Jules Lion’s December 1850 offer to paint the cathedral’s ceiling (Lion’s lithograph of the cathedral opened the old exhibition, and Warburg’s letter is the cover). Terminology reuses the page of the Register of free colored persons, whose color column records the glossary’s terms. Resources opens on one of the old exhibition’s own pictures, brought back: Basile Barès’s <em>Les Variétés du Carnaval</em> (1875). The old pages showed The Historic New Orleans Collection’s copy; this is Tulane’s, from the Louisiana Sheet Music Collection in the Hogan Archive, rendered from Tulane’s scan at 2,560px. Barès is one of the composers named in Golden Age. Tulane marks the scan “In Copyright”; the music itself, from 1875, is long out of copyright.
</li><li>The Collections and Terminology are walls of text plates: each partner, and each part of Terminology (“Creole”, Controlled Vocabularies, the historical terms), is a plate with its opening words that opens the full text in reading view. Each partner’s opening line now counts its collections and their years. The glossary of historical terms stays one list rather than a plate per word.
</li><li>Golden Age and Decline and Civil War are walls too: each chapter is four text plates at the turns in its argument (Statehood and a booming city; Authors, artists and musicians; Fortunes in business; Beyond New Orleans. The laws close in; Leaving Louisiana; Divided loyalties; A mixed blessing), each with one of Michael Taylor’s own sentences on it, and the chapter’s documents hung between them. The text is unchanged; only where it divides and the plate titles are new. Resources is a wall of four plates, one per list.
</li></ul>
<h3>Corrected</h3>
<table>
<thead><tr><th scope="col">Before</th><th scope="col">After</th></tr></thead>
<tbody>
<tr><td><del>Ponce de Léon</del></td><td><ins>Ponce de León</ins></td></tr>
<tr><td><del>their experiences..</del></td><td><ins>their experiences.</ins></td></tr>
<tr><td><del>a 25 years-long liaison</del></td><td><ins>a 25-year liaison</ins></td></tr>
<tr><td><del>crystalized</del></td><td><ins>crystallized</ins></td></tr>
<tr><td><del>French speaking-free people of color</del></td><td><ins>French-speaking free people of color</ins></td></tr>
<tr><td><del>Lousiiana</del></td><td><ins>Louisiana</ins> <em>Johanna Lee Davis Smith’s dissertation, in the bibliography</em></td></tr>
<tr><td><del>expressed in herein</del></td><td><ins>expressed herein</ins> <em>the NEH notice</em></td></tr>
<tr><td><del>àcet État</del></td><td><ins>à cet État</ins> <em>Marigny’s <em>Réflexions</em>, in the collection list</em></td></tr>
</tbody>
</table>
<ul><li>Curly quotes and apostrophes, and en dashes in number ranges (1718–1803).
</li>
<li>The italics and spacing around <em>creole</em>, <em>criollo</em> and <em>crioulo</em> (Terminology).
</li></ul>
<h3>Links</h3>
<ul><li>The Grant Team is listed as it was in 2015, without its e-mail addresses.
</li><li>LSU catalog links go to Combined Search; LSU and Louisiana State Museum finding aids (PDFs) still work and are kept.
</li><li>The partners’ old catalog and finding aid links no longer work (The Historic New Orleans Collection’s catalog, Tulane’s Archon and Voyager, the New Orleans Public Library’s nutrias.org pages), so they’re gone; each partner’s entry links to its research center instead.
</li><li>Resources: the links the Showcase pages had already updated are used (UNC Greensboro, the Amistad Research Center and Le Musée de f.p.c. on the Wayback Machine, Rootsweb). The University of Virginia’s visual record has moved to slaveryimages.org.
</li><li>Put back (2026-11-20): each collection’s link to its digitized items. The 2015 site had “View the collection online” under each one, pointing to CONTENTdm, which no longer works; it was taken off between 2016 and 2018. Now “In the Louisiana Digital Library” searches the project’s LDL collection for the collection’s name, for 58 of the 60. Jacques’s 1859 passport and the 1860 registration certificate aren’t in the LDL, so they have none. The bakers’ declarations and passports are searched as the LDL titles them, “Office of the Mayor passports”.
</li><li>Put back (2026-11-20): Terminology’s closing pointer to the encyclopedia entry on Creoles, taken off between 2020 and 2023. KnowLA is now 64 Parishes, so it links to 64parishes.org/entry/creoles.
</li></ul>
<h3>To verify</h3>
<p>Left as written:</p>
<ul><li>“Institute Catholique” (usually “Institution Catholique des Orphelins Indigents”, or “Institut Catholique”).
</li><li>“Henriette Delille … foundress of the Sisters of the Holy Family, the second oldest Catholic religious order for women of color.”
</li><li>“Réflexions … Reprèsentans” in the collection list keeps the 1848 title page’s spelling.
</li><li>The grant team’s titles are as of 2015.
</li></ul>
<h3>New wording to check</h3>
<ul><li>The credit line.
</li><li>The twelve captions and alt texts, written from each item’s LDL record.
</li><li>The cover’s object label (A. Sampson, Letter introducing the sculptor Eugène Warburg, 1852).
</li><li>The Creoles sentence in Terminology, reworded for 64 Parishes.
</li><li>The LDL searches are on the collections’ names, so some bring up neighbors too (“Metoyer” papers find each other); each one’s first results are the right items.
</li></ul>
HTML),
]);

$section('John James Audubon in Louisiana', [
  $text(NULL, <<<'HTML'
<p>Now at <a href="/v2/special/exhibitions/audubon">/v2/special/exhibitions/audubon</a>.</p>
<p>Exhibition: John James Audubon in Louisiana (LSU Libraries digital exhibition, 2021, from <em>Audubon at Oakley</em>, 2008, curated by Elaine Smyth).<br>Old site: the Wayback Machine’s copies of lib.lsu.edu/exhibitions/john-james-audubon and, for the 78 bird and manuscript pages it never captured, the students’ copy at /audubon (nodes 437–520).</p>
<h3>Changed on the page</h3>
<ul><li>Seven galleries on one page, one entry per bird or manuscript. Headings use the 2021 menu names.
</li><li>Elephant Folio, Octavo Edition and Drawings and Proofs are walls: the plates side by side at their own proportions, sortable by plate number or name. The object view shows each edition’s sheet at actual size (40 × 27 in and 11 × 7 in).
</li><li>The 11 drawing-and-proof pairs are compared with a slider, each drawing aligned over its proof.
</li><li>Manuscripts: The Hurricane and The Bear show all four of their pages (the 2021 site showed a close-up), Children’s Warbler shows its whole page, and each has a transcription, page by page.
</li><li>Suggested Reading’s list of citations became typeset covers: Selected works (18 books, 5 articles) and Fiction (2 books), each linked to its catalog record or article.
</li><li>Black-throated Waxwing’s drawing is the full sheet, in place of the 2021 close-up.
</li><li>The pages’ mis-encoded characters were decoded and checked against the pages the Wayback Machine has. No word differs.
</li></ul>
<h3>Corrected</h3>
<ul><li>Spacing and punctuation: spaces around “[now” and “vol. 1”; missing periods and commas; “ : ” and “ ;” in the readings.
</li></ul>
<table>
<thead><tr><th scope="col">Before</th><th scope="col">After</th></tr></thead>
<tbody>
<tr><td><del>FIeld</del></td><td><ins>Field</ins></td></tr>
<tr><td><del>Sociey</del></td><td><ins>Society</ins></td></tr>
<tr><td><del>Boneparte</del></td><td><ins>Bonaparte</ins> <em>headings</em></td></tr>
<tr><td><del>Prarie</del></td><td><ins>Prairie</ins> <em>headings</em></td></tr>
<tr><td><del>Flamigo</del></td><td><ins>Flamingo</ins> <em>headings: the students’ titles; the 2021 menus had them right</em></td></tr>
</tbody>
</table>
<ul><li>Agreed with the editors: six Latin names (Psittacus, Sylvia childrenii, Sylvia roscoe, Haematopus palliatus, Ictinia mississippiensis, Picus querulus), “Susanne Low”, and Heitman’s pages 44–53.
</li></ul>
<h3>To verify</h3>
<ul><li>Three octavo citations give “Philadelphia: J. B. Chevalier, 1856” (Pine Creeping Wood-warbler, Wood Duck, Yellow-throated Vireo). The 1856 edition was not Chevalier’s.
</li></ul>
<h3>New wording to check</h3>
<ul><li>Gallery lead captions, the “Drawing” and “Proof” captions, alt text, the credit line and the physical exhibition note.
</li></ul>
HTML),
]);

$section('LSU Union Art Gallery: Building Community Through Art, 1964–2016', [
  $text(NULL, <<<'HTML'
<p>Built at <a href="/v2/special/exhibitions/unionartgallery">/v2/special/exhibitions/unionartgallery</a>, not published yet.</p>
<p>Exhibition: LSU Union Art Gallery: Building Community Through Art, 1964–2016, flyers, photographs and negatives from the records of the LSU Union Art Gallery.<br>Old site: the six-page showcase at /lsu-unionartgallery, on libstage only.</p>
<h3>Changed on the page</h3>
<ul><li>The introduction, then one gallery per old page: Studio Art, Louisiana Artists, Interdisciplinary Collaboration, Celebration of Books and Visiting Artists.
</li><li>All five are walls: one tile per image, titled by its caption, under a heading for each show.
</li><li>The 91 images were already on the server and kept the old page’s alt text.
</li></ul>
<h3>Corrected</h3>
<ul><li>Text encoding: apostrophes and quotes saved wrongly up to four times over were decoded exactly to ’ “ ”.
</li></ul>
<table>
<thead><tr><th scope="col">Before</th><th scope="col">After</th></tr></thead>
<tbody>
<tr><td><del>Clyde Connel</del></td><td><ins>Clyde Connell</ins> <em>as in its own captions</em></td></tr>
<tr><td><del>Judi Stalh</del></td><td><ins>Judi Stahl</ins> <em>as elsewhere</em></td></tr>
<tr><td><del>gallery talk aby</del></td><td><ins>gallery talk by</ins></td></tr>
</tbody>
</table>
<ul><li>The missing closing quotation mark on “Humor in the Jugular Vein”.
</li>
<li>Trailing periods on some captions.
</li></ul>
<h3>To verify</h3>
<p>Left as written:</p>
<ul><li>One artist appears as “Claude Roberts Trevithick”, “Claudia Roberts” and “Claudia Robert Trevnthick”.
</li><li>“Pamela Micheal”
</li><li>The “Transparent Motifs” heading sits over a “Transparent Motives” flyer.
</li></ul>
<h3>New wording to check</h3>
<ul><li>The credit line, and the cover’s caption and alt text (Janice Sachse, <em>Noon in My Garden</em>). The other gallery leads use the old covers’ alt text as their caption.
</li></ul>
HTML),
]);

$section('Occult Science and Philosophy in the Renaissance', [
  $text(NULL, <<<'HTML'
<p>Now at <a href="/v2/special/exhibitions/occult">/v2/special/exhibitions/occult</a>.</p>
<p>Exhibition: Occult Science and Philosophy in the Renaissance (Hill Online Exhibitions, 2011; shown in Hill Memorial Library in 2010), drawn from the Rare Book, McIlhenny and Rhoades Collections, curated by Michael Taylor.<br>Old site: the Wayback Machine’s copy of exhibitions.blogs.lib.lsu.edu/?p=1257&amp;all=1. The students’ copy at /occult was not used, because its text has encoding errors.</p>
<h3>Changed on the page</h3>
<ul><li>Eleven galleries on one page at first, one entry per book, each with its catalog record. Since 2026-11-20 the eight themed galleries are one wall, The Objects: the 22 books side by side under their eight themes, each opening full screen with its record and commentary (<code>scripts/palefire_exhibit_occult_wall.php</code>).
</li><li>40 images from the “Occult Science 4K” folder.
</li><li>Links point at the new anchors, and the Index links to each book.
</li></ul>
<h3>Corrected</h3>
<table>
<thead><tr><th scope="col">Before</th><th scope="col">After</th></tr></thead>
<tbody>
<tr><td><del>Amboise Paré</del></td><td><ins>Ambroise Paré</ins></td></tr>
</tbody>
</table>
<ul><li>Missing spaces and line breaks in the catalog records and the Index.
</li>
<li>Felgenhauer filed under F in the Index.
</li></ul>
<table>
<thead><tr><th scope="col">Before</th><th scope="col">After</th></tr></thead>
<tbody>
<tr><td><del>Middleton Library</del></td><td><ins>Main Library</ins> <em>as on AVES</em></td></tr>
</tbody>
</table>
<h3>To verify</h3>
<ul><li>Defoe: the catalog record says Oxford: D. A. Talboys, 1840, but the scan (and its folder, BF 1601 D3 1728) is the London, 1728 title page.
</li></ul>
<h3>New wording to check</h3>
<ul><li>Image captions, gallery lead captions, alt text, the credit line and the physical exhibition note.
</li></ul>
HTML),
]);

$section('Woven Louisiana History: Acadian Textiles', [
  $text(NULL, <<<'HTML'
<p>Built at <a href="/v2/oralhistory/exhibitions/woven">/v2/oralhistory/exhibitions/woven</a>, not published yet. It is two interviews, so it lives with the oral history exhibitions; /v2/special/exhibitions/woven redirects there.</p>
<p>Exhibition: Woven Louisiana History: Acadian Textiles, two interview excerpts with makers for LSU’s Acadian Handicraft Project (1942–1962): the weaver Gladys Clark and the seamstress Laura Mae Romero.<br>Old site: /woven/clark and /woven/romero, one photograph, one recording and one transcript each.</p>
<h3>Changed on the page</h3>
<ul><li>One gallery, In Their Words, a wall: the two interviews as picture tiles (photograph, clip, transcript and a short biography), with two text plates, The Acadian Handicraft Project and About the Interviews.
</li><li>New text from LSU Libraries finding aids (Acadian Handicraft Project Records, Mss. 1880; Gladys Clark Papers, Mss. 4953): the introduction, Gladys Clark’s biography and the project plate. Laura Mae Romero’s biography comes from her own words.
</li><li>Pictures: Gladys Clark’s portrait from its 2,750 × 4,200 rescan for the 2024 Hill exhibition (the old page’s was 750 px), and a new gallery lead from the same files, a basket of coton jaune. Laura Mae Romero’s 1945 newspaper photograph stays at 233 px: no better copy on the R: drive or in the Louisiana Digital Library.
</li></ul>
<h3>Corrected</h3>
<table>
<thead><tr><th scope="col">Before</th><th scope="col">After</th></tr></thead>
<tbody>
<tr><td><del>the loom that I’ve been waiting on</del></td><td><ins>the loom that I’ve been weaving on</ins></td></tr>
<tr><td><del>I didn’t want a card and spin</del></td><td><ins>I didn’t want to card and spin</ins></td></tr>
<tr><td><del>What my mother would spend</del></td><td><ins>What my mother would spin</ins></td></tr>
<tr><td><del>Pam Rabalis</del></td><td><ins>Pam Rabalais</ins></td></tr>
<tr><td><del>Abbeville Meriodional</del></td><td><ins><em>Abbeville Meridional</em></ins></td></tr>
</tbody>
</table>
<ul><li>Speaker labels in bold on their own paragraphs, a dozen small transcript fixes and the brackets made whole; the full list is in the theme’s docs/special-exhibition-corrections/woven.md.
</li></ul>
<h3>To verify</h3>
<ul><li>Gladys Clark’s interviewer: the old source line says Maida Bergeron, its transcript Maida Owens.
</li><li>The cover year, 2024 (the old pages have no date), and the coton jaune photograph’s source.
</li><li>Left as heard: “little pin offers”, “Mrs. Dickie Daspitt”, “Mr. Isaac Hank”, and Louise Olivier “from Grand Coteau” (the finding aid says Arnaudville).
</li></ul>
<h3>New wording to check</h3>
<ul><li>The introduction, the credit line, both biographies, both text plates, the clip titles, the lead caption and the alt text.
</li></ul>
HTML),
]);

$section('Biographies', [
  $text(NULL, <<<'HTML'
<p><strong>Status: proposed, not decided.</strong> AVES is the only Special Collections exhibition with biographies so far: 43 of them, 118 to 1,293 words (about 400 on average), three of them for two people (Godman and Salvin, the Schomburgks, the Phelpses). Together they are most of the exhibition’s 48,000 words. Today each one sits in its entry under a “Biography” label, folded after about ten lines with a “Read the full biography” button, and the Biographies gallery is a hand-written list of links.</p>
<h3>What works</h3>
<ul>
<li>Every person has an anchor, so a link such as /v2/special/exhibitions/aves#schauensee reaches them.</li>
<li>The fold keeps a long life from pushing the books and plates out of sight.</li>
</ul>
<h3>What doesn’t</h3>
<ul>
<li>A link to a person lands on the heading with the biography still folded.</li>
<li>The fold cuts mid-sentence, at a height, not at a thought.</li>
<li>Life dates live in the heading text, “Rodolphe Meyer de Schauensee (1901-1984)”, so the site can’t sort, compare or draw them.</li>
<li>The Biographies list is typed by hand, and has to be edited whenever an entry changes.</li>
</ul>
<h3>Proposals, smallest first</h3>
<ol>
<li><strong>Open on arrival.</strong> When the address names a person, unfold their biography. A script change only, no content.</li>
<li><strong>Life dates as data.</strong> Move “(1901-1984)” out of the heading into the entry’s Date field as a range, shown under the name. The three pairs keep their dates in the heading, since the field holds one range. A script can do the other 40, and the dates also let a gallery be shown as a timeline.</li>
<li><strong>A short summary.</strong> An optional “In brief” field of one or two sentences, written by a curator, shown while folded in place of the clipped first lines. With no summary, the fold stays as it is.</li>
<li><strong>A generated index of people.</strong> Replace the hand-written list with an index built from the entries: name, life dates and gallery, sortable by name or by birth, each linking to the entry. Drawn as thin lifespan bars on one shared axis (1514 to 2004), it shows at a glance who overlapped with whom, in the Swiss manner of the rest of the site.</li>
<li><strong>Portraits.</strong> Where Special Collections holds a likeness, a small portrait beside the name. Only where one exists, never a placeholder.</li>
</ol>
<p>Not proposed: a separate page per person, or a site-wide list of people shared across exhibitions. With one exhibition of biographies, that would add editing work without readers to gain from it. It can be revisited if Audubon or a new exhibition adds lives.</p>
<p><strong>Suggested order:</strong> 1 and 2 now (no new writing needed), 4 next, 3 and 5 as curators have time.</p>
HTML),
]);

// End of sections. ---------------------------------------------------------

$block_count = 0;
foreach ($sections as $item) {
  $block_count += count($item['blocks']);
}
$fingerprint = substr(sha1(json_encode([$intro, $sections], JSON_THROW_ON_ERROR)), 0, 12);
$log_prefix = 'palefire_guide_special_exhibitions.php build';
$log_message = "$log_prefix $fingerprint";

// What's there now. --------------------------------------------------------

$storage = \Drupal::entityTypeManager()->getStorage('node');
$found = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', [$title, ...$old_titles], 'IN')
  ->range(0, 1)->execute();
$node = $found ? $storage->load((int) reset($found)) : NULL;
$prefix = $apply ? '' : '[dry run] ';

echo "Text format: $format" . PHP_EOL;
echo "Content: " . count($sections) . " sections, $block_count blocks, build $fingerprint." . PHP_EOL;

if ($node) {
  $latest = $storage->getLatestRevisionId($node->id());
  if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
    throw new \RuntimeException("'$title' has a newer draft (revision $latest) than its current revision. Publish or discard it first.");
  }
  $last_log = (string) $node->getRevisionLogMessage();
  if ($last_log === $log_message) {
    echo "'$title' (node/{$node->id()}) already has this build. Nothing to do." . PHP_EOL;
    return;
  }
  if (!str_starts_with($last_log, $log_prefix) && !$force) {
    throw new \RuntimeException("'$title' (node/{$node->id()}) was last saved outside this script (log: \"$last_log\"), and a build would replace any edits. Check its Revisions tab, copy anything worth keeping into this script, then run with force=1.");
  }
  $state = $node->isPublished() ? 'published' : 'unpublished';
  echo "{$prefix}Replace the intro and " . count($node->get('field_scroll_section')) . " sections of node/{$node->id()}, in a new revision. It stays $state." . PHP_EOL;
}
else {
  echo "{$prefix}Create '$title' as an unpublished guide page." . PHP_EOL;
}
foreach ($sections as $item) {
  echo "{$prefix}  {$item['title']} (" . count($item['blocks']) . ' blocks)' . PHP_EOL;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

// Build. -------------------------------------------------------------------

$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];
$keep = fn (array $values) => array_filter($values, fn ($v) => $v !== NULL);

$to_paragraph = function (array $block) use ($html, $keep): Paragraph {
  $blank = fn (string $value) => $value !== '' ? $value : NULL;
  // Notes and captions are WYSIWYG fields; here they are written as plain text.
  $rich = fn (string $value) => $value !== '' ? $html('<p>' . htmlspecialchars($value, ENT_NOQUOTES) . '</p>') : NULL;
  if ($block['kind'] === 'flow') {
    $children = [];
    foreach ($block['steps'] as $item) {
      $children[] = Paragraph::create($keep([
        'type' => 'guide_flow_step',
        'field_item_title' => $item['title'],
        'field_flow_note' => $rich($item['note']),
        'field_flow_kind' => $item['kind'],
        'field_flow_group' => $blank($item['group']),
        'field_flow_link' => $blank($item['link']),
        'field_flow_branch_title' => $blank($item['then']),
        'field_flow_branch_label' => $blank($item['if']),
        'field_flow_branch_note' => $rich($item['then_note']),
        'field_flow_branch_tone' => $item['tone'],
      ]));
    }
    return Paragraph::create($keep([
      'type' => 'guide_flow',
      'field_block_title' => $block['heading'],
      'field_flow_steps' => $children,
      'field_flow_caption' => $rich($block['caption']),
    ]));
  }
  if ($block['kind'] === 'status') {
    $children = [];
    foreach ($block['items'] as [$state, $item_title, $note]) {
      $children[] = Paragraph::create([
        'type' => 'guide_status_item',
        'field_status_state' => $state,
        'field_item_title' => $item_title,
        'field_status_note' => $rich($note),
      ]);
    }
    return Paragraph::create($keep([
      'type' => 'guide_status_board',
      'field_block_title' => $block['heading'],
      'field_status_items' => $children,
    ]));
  }
  if ($block['kind'] === 'stats') {
    $children = [];
    foreach ($block['items'] as [$value, $label]) {
      $children[] = Paragraph::create([
        'type' => 'guide_stat',
        'field_stat_value' => $value,
        'field_stat_label' => $label,
      ]);
    }
    return Paragraph::create($keep([
      'type' => 'guide_stats',
      'field_block_title' => $block['heading'],
      'field_stat_items' => $children,
    ]));
  }
  if ($block['kind'] === 'code') {
    return Paragraph::create($keep([
      'type' => 'guide_code',
      'field_block_title' => $block['heading'],
      'field_code_text' => $block['text'],
      'field_code_caption' => $rich($block['caption']),
    ]));
  }
  if ($block['kind'] === 'accordion') {
    $children = [];
    foreach ($block['items'] as [$item_title, $item_body]) {
      $children[] = Paragraph::create([
        'type' => 'accordion_item',
        'field_item_title' => $item_title,
        'field_block_body' => $html($item_body),
      ]);
    }
    return Paragraph::create($keep([
      'type' => 'guide_accordion',
      'field_block_title' => $block['heading'],
      'field_block_intro' => $block['intro'] !== '' ? $html($block['intro']) : NULL,
      'field_accordion_items' => $children,
    ]));
  }
  return Paragraph::create($keep([
    'type' => 'guide_text',
    'field_block_title' => $block['heading'],
    'field_block_body' => $html($block['body']),
    'field_text_style' => $block['style'],
  ]));
};

$has_theme = (bool) FieldConfig::loadByName('paragraph', 'guide_section', 'field_section_theme');
$paragraphs = [];
foreach ($sections as $item) {
  $values = [
    'type' => 'guide_section',
    'field_section_title' => $item['title'],
    'field_guide_blocks' => array_map($to_paragraph, $item['blocks']),
  ];
  if ($has_theme) {
    $values['field_section_theme'] = 'light';
  }
  $paragraphs[] = ['entity' => Paragraph::create($values)];
}

$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');

if ($node) {
  $node->setTitle($title);
  $node->set('field_scroll_section', $paragraphs);
  if (isset($node_fields['field_intro'])) {
    $node->set('field_intro', $html($intro));
  }
  $node->setNewRevision(TRUE);
  $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
  $node->setRevisionLogMessage($log_message);
  $node->save();
  echo "Saved a new revision of '$title': node/{$node->id()}. Run: drush cr" . PHP_EOL;
  return;
}

$values = [
  'type' => 'modular_page',
  'title' => $title,
  'status' => 0,
  'field_scroll_section' => $paragraphs,
  'path' => ['alias' => '/' . ltrim($args['path'] ?? '/v2/guides/special-collections-exhibitions', '/')],
];
if (isset($node_fields['field_modular_type'])) {
  $values['field_modular_type'] = 'guide';
}
if (isset($node_fields['field_intro'])) {
  $values['field_intro'] = $html($intro);
}

// Owner: Kyle Tanglao's Staff node. Subjects are left empty on purpose: this
// is a review list, not a research guide.
$skipped = [];
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

$page = Node::create($values);
$page->setRevisionLogMessage($log_message);
$page->save();

echo 'Created unpublished guide page: node/' . $page->id() . ' (' . $page->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
foreach ($skipped as $line) {
  echo 'Skipped: ' . $line . PHP_EOL;
}
echo 'Run: drush cr' . PHP_EOL;
