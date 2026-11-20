<?php

/**
 * @file
 * Guide page: "Special Collections Exhibitions", every change made to the text,
 * layout and images of the Special Collections online exhibitions (AVES,
 * Audubon, Occult, Union Art Gallery, Free People of Color, New from Old Natchez, Sugar at LSU) when they moved to Palefire, and the
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
<li><strong>Creole Echoes</strong> (2001) was a set of Showcase pages under /creole, copied from the 2001 web exhibition, with a French version under /creole/fr. Its text comes from the English Showcase pages.</li>
<li><strong>New from Old Natchez</strong> (2002) was a set of Showcase pages under /natchez, whose Image and Transcript links go to the 2002 files. Its text comes from the Showcase pages.</li>
<li><strong>Made in America: Selected Imprints from 1776–1826</strong> (2026) had only an announcement at /260908/madeinamerica. Its text comes from the exhibition’s own panels.</li>
<li><strong>From Red Stick to River Capital</strong> (1999) was a set of static pages at /sites/all/files/sc/exhibitions/e-exhibits/redstick, later copied to Showcase pages under /brhistory. Its text comes from the Showcase pages, checked against the static pages.</li>
<li><strong>The Baton Rouge Bus Boycott</strong> (1998, revised 2002) was a set of Showcase pages under /boycott, linking to the 2002 static site for its clips and interviews. Its text comes from the Showcase pages, checked against the static pages. It is an oral history exhibition, so it lives at /v2/oralhistory/exhibitions/boycott.</li>
<li><strong>Blue and Gray for Boys and Girls</strong> (2012) was fifteen pages under /BlueandGrey. Five of them no longer open, so their text comes from the exhibition’s own 2012 label files, which match the pages that still open.</li>
<li><strong>Sugar at LSU: A Chronology</strong> (1995, revised 2002) was a set of Showcase pages under /sugar, with the same pictures at /sites/all/files/sc/exhibitions/e-exhibits/sugar. Its text comes from the Showcase pages.</li>
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
<ul><li>One gallery, In Their Words, a wall: the two interviews as picture tiles (photograph, clip, transcript and a short biography), with text plates between them: The Acadian Handicraft Project, The Hill Memorial Library Exhibition (from the 2024 press release, with its organizers’ words) and About the Interviews.
</li><li>New text from LSU Libraries finding aids (Acadian Handicraft Project Records, Mss. 1880; Gladys Clark Papers, Mss. 4953): the introduction, Gladys Clark’s biography and the project plate. Laura Mae Romero’s biography comes from her own words.
</li><li>Pictures: Gladys Clark’s portrait from its 2,750 × 4,200 rescan for the 2024 Hill exhibition (the old page’s was 750 px), and two photographs captioned in the 2024 press release: Gladys Clark weaving in costume, c. 1950 (the gallery’s lead, from a 5,569 × 4,430 scan), and an Acadian Handicraft display in New York, c. 1949. Laura Mae Romero’s 1945 newspaper photograph stays at 233 px: no better copy on the R: drive or in the Louisiana Digital Library.
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
</li><li>Settled by the <a href="https://lib.lsu.edu/24/08/29/fallexhibition">2024 press release</a>: the cover year, the exhibition in Hill Memorial Library (September 23–December 13, 2024) it belonged to, its organizers for the credit line, and the portrait’s caption (towels, c. 1980, not placemats).
</li><li>Left as heard: “little pin offers”, “Mrs. Dickie Daspitt”, “Mr. Isaac Hank”, and Louise Olivier “from Grand Coteau” (the finding aid says Arnaudville).
</li></ul>
<h3>New wording to check</h3>
<ul><li>The introduction, the credit line, both biographies, both text plates, the clip titles, the lead caption and the alt text.
</li></ul>
HTML),
]);

$section('Made in America: Selected Imprints from 1776–1826', [
  $text(NULL, <<<'HTML'
<p>Built at <a href="/v2/special/exhibitions/made-in-america">/v2/special/exhibitions/made-in-america</a>, not published yet.</p>
<p>Exhibition: Made in America: Selected Imprints from 1776–1826, for the nation’s 250th anniversary, in Hill Memorial Library from September 8, 2026, through the fall semester. Curated by Leah Wood Jewett, with text by Brandon Layton, John D. Miles and Lauren Coats; scans by Gabe Harrell.<br>Old site: one announcement page, /260908/madeinamerica, with three paragraphs and two object labels.</p>
<h3>Changed on the page</h3>
<ul><li>The text comes from the announcement and from the exhibition’s two panels, Introduction (Brandon Layton’s essay, Thomas Paine, John D. Miles’s Benjamin Franklin) and Printing &amp; Printers (Overview, Positions, Isaiah Thomas, Mathew Carey, Benjamin Levy), read from the panel files in the exhibition’s working folder on the R: drive.
</li><li>Four galleries, each a wall with a lead picture: Introduction (the panel essay as seven text plates, with Paine, Franklin and two objects between them), Printing and Printers, Louisiana, and Natural History and Science. The plate titles are new; each plate’s summary is one of the panel’s own sentences where one fits.
</li><li>Pictures: the announcement had two, at 350px. All 28 on the new page are the Digitization Lab’s 2026 scans for the exhibition (up to 29,000px), reduced to 2,560px: Bigelow’s tulip poplar (the cover) and bloodroot, the Louisiana Memorial newspaper of 1804, <em>The Constitutionalist</em> of 1811 with its report of the German Coast uprising, Eaton’s Erie Canal survey, Franklin’s Gulf Stream chart, Isaiah Thomas’s colophon, <em>Charlotte</em> printed for Mathew Carey, Carey’s list of books, Levy’s <em>Price-Current</em>, Moreau de Saint-Méry’s <em>Description</em>, Darby’s map, Jefferson’s batture <em>Proceedings</em>, the Battle of New Orleans print, Wilson, Say, Bartram, Dorsey and Coxe’s telegraph.
</li></ul>
<h3>Corrected</h3>
<table>
<thead><tr><th scope="col">Before</th><th scope="col">After</th></tr></thead>
<tbody>
<tr><td><del>Hiberian Journal</del></td><td><ins><em>Hibernian Journal</em></ins></td></tr>
<tr><td><del>James Fennimore Cooper</del></td><td><ins>James Fenimore Cooper</ins></td></tr>
<tr><td><del>Phyllis Wheatley</del></td><td><ins>Phillis Wheatley</ins></td></tr>
<tr><td><del>returning to the United States 1785</del></td><td><ins>returning to the United States in 1785</ins></td></tr>
<tr><td><del>Darby published the map following the War of 1812 by John Melish.</del></td><td><ins>The map was published following the War of 1812 by John Melish.</ins></td></tr>
</tbody>
</table>
<ul><li>“travelled” became “traveled”, number ranges take en dashes, and the type note says it describes the text in Hill Memorial Library. The full list is in the theme’s docs/special-exhibition-corrections/made-in-america.md.
</li></ul>
<h3>To verify</h3>
<ul><li>Left as written: Isaiah Thomas riding “on April 15th” (the ride was April 18, 1775); Webster’s “first American dictionary in 1783” (1783 is his speller); Franklin as “the consummate 19th-century man of letters”; Paine “making wire stays for sails”.
</li><li>Which newspaper printed the Louisiana Memorial (the scan has no masthead; August 1804), which book Isaiah Thomas’s 1788 colophon ends, and which of the two 1816 Darby maps is on display.
</li><li>Call numbers the catalog search didn’t find: Wilson, Dorsey, the Battle of New Orleans print, the Memorial newspaper and the <em>Price-Current</em>.
</li><li>The panels’ suggested reading list (a QR code) isn’t on the page; the list isn’t in the working folder.
</li></ul>
<h3>New wording to check</h3>
<ul><li>The gallery titles Louisiana and Natural History and Science, every plate title and summary, every object caption except the Bigelow and Darby labels, the four lead captions, the cover label and the alt text.
</li></ul>
HTML),
]);

$section('From Red Stick to River Capital: Three Centuries of Baton Rouge History', [
  $text(NULL, <<<'HTML'
<p>Built at <a href="/v2/special/exhibitions/brhistory">/v2/special/exhibitions/brhistory</a>, not published yet.</p>
<p>Exhibition: From Red Stick to River Capital: Three Centuries of Baton Rouge History, shown in Hill Memorial Library, March 8 – July 2, 1999, curated by V. Faye Phillips, with materials from the Louisiana and Lower Mississippi Valley Collections and University Archives.<br>Old site: the 1999 static pages at /sites/all/files/sc/exhibitions/e-exhibits/redstick, copied to fourteen Showcase pages under /brhistory. The text is taken from the Showcase pages and checked against the 1999 pages.</p>
<h3>Changed on the page</h3>
<ul><li>Fourteen galleries, one per old page: Introduction, the twelve cases (Native Americans and Colonial Origins through City Politics) and Other Photographs.
</li><li>Every gallery is a wall with a lead picture. The long text is divided into text plates between the pictures; the text is unchanged except for the corrections below, and the plate titles and summaries are new.
</li><li>Each case ends with an “In the case” plate listing everything shown in 1999, pictured here or not. Other Photographs ends with “Not pictured”.
</li><li>Pictures: the old pages had about 40 small images (250–700px). Where the Louisiana Digital Library holds the same item, or the same view, the new page uses the LDL master instead (rendered to 2,560px at most); the rest are the 1999 site’s own files, which are larger than the Showcase copies. Six pictures are new, each tied to something on the page: the Lytle view of Baton Rouge after the 1862 attack, the Lafayette Street streetcar, Hill Memorial Library in 1905, the Tunnard carriage factory, Lora Hinton (in place of his letterman plaque), and the Standard Oil postcard and 1904 Capitol postcard the case lists named but didn’t picture.
</li><li>Lead pictures: every gallery opens on an LDL master, mostly from Andrew D. Lytle’s Baton Rouge photographs. Thirteen are 720p or larger (1,500 × 1,221px for the Pintado plan, 2,560px wide for the rest) and fill the screen. Histories of Baton Rouge opens on Lytle’s 1861 Front Street panorama, which exists only at 1,000 × 382px; nothing larger shows the old riverfront, so it is the best lead there is, and “Show whole image” is ticked: it is shown whole over a blurred copy of itself instead of enlarged.
</li><li>The cover is Lytle’s State Capitol (lsu-sc-p15140coll12:267), 1,827 × 2,560px, so “Frame the cover” is left off. The script frames any cover, and shows whole any lead, under 720p (long side under 1,280px or short side under 720px).
</li><li>Credits move to the end of the Introduction, with the Louisiana Endowment for the Humanities.
</li></ul>
<h3>Where the pictures came from</h3>
<p>Searched for rescans:</p>
<ul><li><code>R:\SpecialCollections\Exhibitions\Completed Exhibitions</code>: EXHIB_Portrait_of_a_River_City_GANDY and “EXHIB_Portraits of a River City _ Gandy 2011” are Natchez material (the Gandy photographs), not Baton Rouge; EXHIB_Andrew_D_Lytle_Baton_Rouge_2008 holds that exhibition’s graphics and labels only, no scans; “EXHIB_We Built This City” has none of the 1999 items. Nothing there was used.
</li><li>The Louisiana Digital Library: Andrew D. Lytle’s Baton Rouge Photograph Collection, the LSU Libraries Postcard Collections, the University Archives Photographs Collection, the Pintado Papers, Fonville Winans’s aerial views, the William Waller Survey Collection and Historic Images of Baton Rouge. Matched by title and by comparing the pictures. A postcard’s or a photograph’s record is the compound object; the front is the child named below.
</li></ul>
<table>
<thead><tr><th scope="col">Picture</th><th scope="col">Source</th></tr></thead>
<tbody>
<tr><td>lytle-state-capitol.jpg (cover)</td><td>LDL lsu-sc-p15140coll12:267</td></tr>
<tr><td>lytle-main-street-high-water.jpg</td><td>LDL lsu-sc-p15140coll12:689</td></tr>
<tr><td>pintado-beauregard-1799.jpg</td><td>LDL lsu-sc-pintado:1455</td></tr>
<tr><td>gayoso-land-grant-1797.jpg</td><td>1999 site, case01a.jpg (960px); no LDL copy found</td></tr>
<tr><td>lytle-j-m-white.jpg</td><td>LDL lsu-sc-p15140coll12:461</td></tr>
<tr><td>lytle-convict-levee.jpg</td><td>LDL lsu-sc-p15140coll12:108 (1,000px)</td></tr>
<tr><td>lytle-federal-encampment.jpg</td><td>LDL lsu-sc-p15140coll12:647, same view as the old armycamp.jpg</td></tr>
<tr><td>lytle-after-attack-1862.jpg</td><td>LDL lsu-sc-p15140coll12:40 (1,000px); added</td></tr>
<tr><td>baton-rouge-dollar-1.jpg</td><td>1999 site, case04a.jpg (498px)</td></tr>
<tr><td>baton-rouge-dollar-2.jpg</td><td>1999 site, case04b.jpg (501px); on the 1999 page, left off the Showcase page</td></tr>
<tr><td>lytle-officers-in-camp.jpg</td><td>LDL lsu-sc-p15140coll12:651, same print as the old soldiers.jpg</td></tr>
<tr><td>lytle-firemens-parade.jpg</td><td>LDL lsu-sc-p15140coll12:718</td></tr>
<tr><td>postcard-st-josephs-convent.jpg</td><td>LDL lsu-sc-p16313coll10:1432 (front :1433), same card as the old case03a.jpg</td></tr>
<tr><td>postcard-high-school.jpg</td><td>LDL lsu-sc-p16313coll10:628 (front :629), same card as the old case05b.jpg</td></tr>
<tr><td>dance-card-front.jpg</td><td>1999 site, case03df.jpg (376px)</td></tr>
<tr><td>dance-card-back.jpg</td><td>1999 site, case03db.jpg (378px)</td></tr>
<tr><td>postcard-masonic-temple.jpg</td><td>LDL lsu-sc-p16313coll10:886 (front :887), same card as the old case03b.jpg</td></tr>
<tr><td>fire-company-notice.jpg</td><td>1999 site, case03e.jpg (383px)</td></tr>
<tr><td>playbill-academy-hall-1864.jpg</td><td>1999 site, case03c.jpg (409px)</td></tr>
<tr><td>lytle-texas-pacific-office.jpg</td><td>LDL lsu-sc-p15140coll12:573</td></tr>
<tr><td>postcard-standard-oil.jpg</td><td>LDL lsu-sc-p16313coll10:1747 (front :1748); listed in the case, not pictured in 1999</td></tr>
<tr><td>fresh-water-harbors.jpg</td><td>1999 site, case05c.jpg (458px)</td></tr>
<tr><td>railroad-stock-certificate-1867.jpg</td><td>1999 site, case05e.jpg (587px)</td></tr>
<tr><td>lytle-streetcar-lafayette.jpg</td><td>LDL lsu-sc-p15140coll12:140 (1,000px); added</td></tr>
<tr><td>aviation-meeting-1911.jpg</td><td>1999 site, case05d.jpg (693px)</td></tr>
<tr><td>ua-style-show-1956.jpg</td><td>LDL lsu-ua-uap:1632 (front :1630)</td></tr>
<tr><td>female-institute-1837.jpg</td><td>1999 site, case06b.jpg (617px)</td></tr>
<tr><td>comus-edic.jpg</td><td>1999 site, case06a.jpg (696px)</td></tr>
<tr><td>winans-aerial-1947.jpg</td><td>LDL lsu-sc-p15140coll46:23</td></tr>
<tr><td>latour-plano-1806.jpg</td><td>LDL lsu-sc-p16313coll57:130</td></tr>
<tr><td>postcard-southern-university.jpg</td><td>LDL lsu-sc-p16313coll10:988 (front :989)</td></tr>
<tr><td>bill-of-sale-1844.jpg</td><td>1999 site, case08c.jpg (566px)</td></tr>
<tr><td>longwood-plantation-ad.jpg</td><td>1999 site, case08b.jpg (585px)</td></tr>
<tr><td>ua-lora-hinton.jpg</td><td>LDL lsu-ua-uap:2159; the case had his letterman plaque</td></tr>
<tr><td>lytle-reymond-store.jpg</td><td>LDL lsu-sc-p15140coll12:468</td></tr>
<tr><td>tunnard-carriage-factory.jpg</td><td>LDL lsu-sc-brt:13; added (W. H. Tunnard of Case 3 was of this family)</td></tr>
<tr><td>lytle-sugar-refinery.jpg</td><td>LDL lsu-sc-p15140coll12:39 (1,000px)</td></tr>
<tr><td>lytle-front-street-1861.jpg</td><td>LDL lsu-sc-p15140coll12:690 (1,000px), shown whole</td></tr>
<tr><td>lytle-cadets-pentagon.jpg</td><td>LDL lsu-sc-p15140coll12:232</td></tr>
<tr><td>ua-boyd-dedication-1926.jpg</td><td>LDL lsu-ua-uap:1344 (front :1342)</td></tr>
<tr><td>lsu-dedication-program-1926.jpg</td><td>1999 site, case11b.jpg (404px)</td></tr>
<tr><td>ua-stadium-postcard.jpg</td><td>LDL lsu-ua-uap:1362 (front :1360), same card as the old case11a.jpg</td></tr>
<tr><td>lytle-hill-memorial-1905.jpg</td><td>LDL lsu-sc-p15140coll12:194 (1,000px); added</td></tr>
<tr><td>lsu-calendar-1913.jpg</td><td>1999 site, case11c.jpg (601px)</td></tr>
<tr><td>lytle-capitol-after-fire-1863.jpg</td><td>LDL lsu-sc-p15140coll12:265</td></tr>
<tr><td>ua-huey-long-funeral.jpg</td><td>LDL lsu-ua-uap:1674 (front :1672); the case had a photograph of the funeral flowers</td></tr>
<tr><td>earl-long.jpg</td><td>1999 site, case12a.jpg (699px)</td></tr>
<tr><td>postcard-state-capitol-1904.jpg</td><td>LDL lsu-sc-p16313coll10:1495 (front :1496); listed in the case</td></tr>
<tr><td>lytle-national-cemetery-rows.jpg</td><td>LDL lsu-sc-p15140coll12:37</td></tr>
<tr><td>lytle-national-cemetery.jpg</td><td>LDL lsu-sc-p15140coll12:754, same print as the old cemetery.jpg</td></tr>
<tr><td>gas-powered-tractor.jpg</td><td>1999 site, tractor.jpg (696px)</td></tr>
<tr><td>cars-on-capitol-steps.jpg</td><td>1999 site, cars.jpg (683px)</td></tr>
<tr><td>webb-moss-gin.jpg</td><td>1999 site, mossgin.jpg (686px)</td></tr>
<tr><td>bus-boycott-1953.jpg</td><td>1999 site, boycott.jpg (368px)</td></tr>
<tr><td>orene-muse-billboard.jpg</td><td>1999 site, register.jpg (486px)</td></tr>
<tr><td>peace-rally-1970.jpg</td><td>1999 site, protest.jpg (658px)</td></tr>
<tr><td>steele-burden-1993.jpg</td><td>1999 site, steele.jpg (554px)</td></tr>
<tr><td>rural-life-museum-1995.jpg</td><td>1999 site, statue.jpg (704px)</td></tr>
</tbody>
</table>
<h3>Corrected</h3>
<table>
<thead><tr><th scope="col">Before</th><th scope="col">After</th></tr></thead>
<tbody>
<tr><td><del>Americans residents</del></td><td><ins>American residents</ins></td></tr>
<tr><td><del>“red stick” baton rouge</del></td><td><ins>“red stick”—<em>baton rouge</em>—</ins></td></tr>
<tr><td><del>as shown by a the</del></td><td><ins>as shown by the</ins></td></tr>
<tr><td><del>whose log book is shown at left</del></td><td><ins>was in the exhibition</ins></td></tr>
<tr><td><del>and forming the Washington Fire Company</del></td><td><ins>forming the Washington Fire Company</ins></td></tr>
<tr><td><del>In 1997 by the … cited</del></td><td><ins>In 1997 the … cited</ins></td></tr>
<tr><td><del>Advocate,could</del></td><td><ins>Advocate, could</ins></td></tr>
<tr><td><del>Blakeman &amp; law</del></td><td><ins>Blakeman &amp; Law</ins></td></tr>
<tr><td><del>Provost Marshall’s</del></td><td><ins>Provost Marshal’s</ins></td></tr>
<tr><td><del>Lyndon Bains Johnson</del></td><td><ins>Lyndon Baines Johnson</ins></td></tr>
<tr><td><del>Curated</del></td><td><ins>curated</ins> <em>mid-sentence</em></td></tr>
<tr><td><del>National Register of Historic Buildings</del></td><td><ins>National Register of Historic Places</ins></td></tr>
</tbody>
</table>
<ul><li>“In 1706 the Terrebonne and Lafourche Parishes.” (garbled in 1999 too) → “In 1706 the Houmas moved south, and their descendants live today in Terrebonne and Lafourche Parishes.” This is a reconstruction; see To verify.
</li><li>Em dashes restored: “war—civilian hardships”, “nineties—a city”, “Standard Oil—a stance”
</li><li>“Freed- Persons” → “Freed-Persons”; “African- American” → “African-American”; “African-Americans students” → “African-American students”
</li><li>“recession-proof, ” quote spacing
</li><li>In captions: “Musuem” → “Museum”; “the original the landscaping” → “the original landscaping”
</li><li>Histories of Baton Rouge: a paragraph printed twice is printed once.
</li><li>Links to the 1999 image files inside the text are taken out (the pictures are on the wall).
</li><li>The dead Louisiana Endowment for the Humanities link now goes to https://www.leh.org/.
</li><li>Curly quotes and apostrophes, en dashes in year ranges, ellipses.
</li><li>“Items listed below…” and the “Interpretive Text:” labels are dropped; the plates do their work.
</li></ul>
<h3>To verify</h3>
<p>Left as written:</p>
<ul><li>The Houmas sentence above: what the 1999 text meant to say.
</li><li>David F. Boyd “served from 1870 to 1880”, then Thomas Boyd “1896 to 1927”: a gap, and the dates of both presidencies.
</li><li>The quotation from the Favrot pageant has no closing quotation mark in 1999; where it ends isn’t clear.
</li><li>“LSU, founded in 1859” (the Seminary opened in 1860).
</li><li>Hill Memorial Library “originally constructed in 1925”, beside Lytle’s photograph of the 1905 Hill on the old downtown campus.
</li><li>Figures as of 1999: the population of 231,000, Southern’s 16,000 students, the “139-year history”.
</li><li>Pintado’s Zayalo de Lemos plan, named in Case 1, is not pictured (no LDL copy found).
</li></ul>
<h3>New wording to check</h3>
<ul><li>The credit line.
</li><li>The plate titles and summaries.
</li><li>The captions and alt text, written from each item’s LDL record or the 1999 label.
</li><li>The lead choices, and the added pictures (Hinton, the Tunnard factory, the streetcar, Hill 1905, the 1862 view).
</li></ul>
HTML),
]);

$section('British Voices from South Asia', [
  $text(NULL, <<<'HTML'
<p>Built at <a href="/v2/special/exhibitions/british-voices">/v2/special/exhibitions/british-voices</a>, not published yet.</p>
<p>Exhibition: British Voices from South Asia, held in Hill Memorial Library, April 8 to August 6, 1996, curated and edited by Rosan Augusta Jordan and Frank de Caro, marking the T. Harry Williams Center for Oral History’s acquisition of their interviews with British people who lived and worked in India before Independence.<br>Old site: the 1996 web version at /special/exhibits/india/, copied to thirteen Showcase pages under /BritishVoices: an Introduction, six history sections and six pages of interview excerpts.</p>
<h3>Changed on the page</h3>
<ul><li>Thirteen galleries, all walls with a lead picture. Each history section is followed by its own interview excerpts (the old site kept them as six pages at the end).
</li><li>History sections: the interviewees’ quotations open each gallery; the essay is a text plate; every picture is an entry with its 1996 caption and the words that followed it. Pictures that shared a caption are one entry each, as an image group.
</li><li>Interview galleries: 42 text plates, each a few speakers’ excerpts in the old order, under the old pages’ own topic headings as sub-galleries.
</li><li>Pictures: the 103 JPEGs of the 1996 site, up to 600 px. No larger scans on the R: drive, in the Louisiana Digital Library or in the Wayback Machine; most of the objects were anonymous loans in 1996.
</li></ul>
<h3>Corrected</h3>
<table>
<thead><tr><th scope="col">Before</th><th scope="col">After</th></tr></thead>
<tbody>
<tr><td><del>on July 10, 1947</del></td><td><ins>on August 14 and 15, 1947</ins></td></tr>
<tr><td><del>Samuel Hewitt, Samuel Hewett</del></td><td><ins>Samuel Howitt</ins></td></tr>
<tr><td><del>Mortimer Menpe7s</del></td><td><ins>Mortimer Menpes</ins></td></tr>
<tr><td><del>19xx</del></td><td><ins>1924</ins> <em>the British Empire Exhibition, Wembley</em></td></tr>
<tr><td><del>Byderabad</del></td><td><ins>Hyderabad</ins></td></tr>
</tbody>
</table>
<ul><li>The Benares and Calcutta burning ghat postcards had each other’s captions, and one caption held two pictures; both are fixed.
</li><li>Left out: the second half of the old No India to Go To interviews page, which repeated The Lure of the East word for word, and a caption for a picture that isn’t there.
</li><li>The full list, with small typos, is in the theme’s docs/special-exhibition-corrections/british-voices.md.
</li></ul>
<h3>To verify</h3>
<ul><li>“Barely more than fifty years ago” (written in 1996).
</li><li>“Sir Robert Baden-Powell, Bart.” as the author of Pig-Sticking or Hog-Hunting, and “J. Hedway” as an engraver.
</li><li>The interviewees’ names and ranks as the old pages give them.
</li></ul>
<h3>New wording to check</h3>
<ul><li>The credit line and subtitle, the six essay plate headings, the interview plate headings and the 51 summaries, the cover’s object label and the alt text.
</li></ul>
HTML),
]);

$section('New from Old Natchez', [
  $text(NULL, <<<'HTML'
<p>Built at <a href="/v2/special/exhibitions/natchez">/v2/special/exhibitions/natchez</a>, not published yet.</p>
<p>Exhibition: New from Old Natchez, manuscripts about the Natchez area recently acquired by LSU Libraries Special Collections, shown at the Historic Natchez Foundation from February to August 2002 during the Historic Natchez Conference. Texts by Tara Zachary, Assistant Curator for Manuscripts.<br>Old site: the Showcase pages /natchez/introduction and nodes 290–298 (eight collections and the bibliography), whose Image and Transcript links go to the 2002 files. The text is from the Showcase pages, the transcriptions from the 2002 files.</p>
<h3>Changed on the page</h3>
<ul><li>Ten galleries, all walls with a lead picture: Introduction; the eight collections (Conner, Farrar, Hunt, McMurran-Austen, Muggah-Glover-Guyther, Peirce-Haralson-Rumble, Schwartz, Stewart); About the Exhibition (the Louisiana and Lower Mississippi Valley Collections, the credits and the bibliography).
</li><li>Each collection opens with text plates on the family and the papers; the Schwartz note on the Federal occupation is a plate of its own, Natchez under occupation. Each numbered item is an entry with a short title: its pages, turned in the object view, its note and its transcription. Rebecca Minor’s two letters, one item on the old page, are two entries.
</li><li>Pictures: 77, each the best copy found. Most are the 2002 exhibition’s own 300 dpi scans on the R: drive (EXHIB_Natchez Exhibit Images - 2002), about twice the old web size. Two McMurran letters come from the Louisiana Digital Library at full length (12 and 4 pages, where the old site showed 9 and 3 images); three McMurran letters are only in the old web images. The cover and two Introduction tiles are new crops from the scrapbook scans: the 1913 baptism under the hill and La Belle Talton.
</li><li>The 2002 contact details (e-mail, phone, the Foundation’s address) are gone; the physical exhibition is in the credit line.
</li></ul>
<h3>Corrected</h3>
<table>
<thead><tr><th scope="col">Before</th><th scope="col">After</th></tr></thead>
<tbody>
<tr><td><del>January 17, 1817</del></td><td><ins>January 29, 1817</ins> <em>Israel Loring’s letter, dated “29th Jany 1817”</em></td></tr>
<tr><td><del>Zachary’s Taylor’s nomination</del></td><td><ins>Zachary Taylor’s nomination</ins></td></tr>
<tr><td><del>J.W. Alfvord</del></td><td><ins>J.W. Alvord</ins></td></tr>
<tr><td><del>A letter … give an account</del></td><td><ins>gives an account</ins></td></tr>
<tr><td><del>Pierce-Haralson-Rumble</del></td><td><ins>Peirce-Haralson-Rumble</ins> <em>the Introduction</em></td></tr>
<tr><td><del>as new member of the elite</del></td><td><ins>as a new member of the elite</ins></td></tr>
<tr><td><del>Cartes de visites</del></td><td><ins>Cartes de visite</ins></td></tr>
<tr><td><del>teachers salaries</del></td><td><ins>teachers’ salaries</ins></td></tr>
</tbody>
</table>
<ul><li>Small fixes in “the John T. McMurran Sr.’s death”, “(d.1899) … Md,”, “p. 64-74” and the Harris citation; curly quotes and en dashes in ranges.
</li><li>Transcriptions: 20 characters the old files had lost in the Minor and Rumble letters are restored from context as quotation marks or dashes.
</li><li>The full list is in the theme’s docs/special-exhibition-corrections/natchez.md.
</li></ul>
<h3>To verify</h3>
<p>Left as written:</p>
<ul><li>Alie McMurran’s November 1856 letter has no transcription: the old file stops after its first line, and neither R: nor the LDL has one. The Lovell and Brady transcriptions end before the letters do, as on the old site.
</li><li>Mary Elizabeth McMurran is “(1835–1861)” but died “in 1864” in the next paragraph.
</li><li>The scrapbook is dated “ca. 1898–1903”, but holds photographs of 1905–1906 and ca. 1913.
</li><li>McCrery, MacCrerys and Macrery: three spellings of one family.
</li><li>The Schwartz collection is “Papers” in its heading and “J.C. Schwartz Records” in the Introduction.
</li></ul>
<h3>New wording to check</h3>
<ul><li>The subtitle (“Manuscripts recently acquired by LSU Special Collections”), the credit line and the physical-exhibition note.
</li><li>The entry and plate titles, the plates’ Summaries, the Introduction’s three tile notes, the captions, the cover’s object label and the alt text for every picture.
</li></ul>
HTML),
]);

$section('Blue and Gray for Boys and Girls', [
  $text(NULL, <<<'HTML'
<p>Built at <a href="/v2/special/exhibitions/blue-and-grey">/v2/special/exhibitions/blue-and-grey</a>, not published yet.</p>
<p>Exhibition: Blue and Gray for Boys and Girls, Civil War books for young people from the Michael Lehman Williamson Collection in Hill Memorial Library, in twelve cases from 1860 to 2001, with author profiles. The online text dates from July 2012; the 2012 introduction is signed by Leah Wood Jewett, Exhibitions Coordinator.<br>Old site: fifteen pages under /BlueandGrey (node/299–313). Cases 1, 2, 4 and 12 and Author Profiles return an error: each save had mis-encoded their characters again, until the pages were many megabytes. Their text comes from the exhibition’s 2012 label files on the R: drive, which match the pages that still open word for word. The old Introduction had no text, so the 2012 wall label is used.</p>
<h3>Changed on the page</h3>
<ul><li>Fifteen galleries, all walls with a lead picture: Introduction, Cases 1–12, Author Profiles and Links.
</li><li>Each case: its wall text as a text plate, with its Sources; its books as tiles, each with its catalog record and the old note; an “Also in the case” plate for books with no picture; and the old photograph of the case. Case 9 had no wall text.
</li><li>Author Profiles: 21 name tiles under Nineteenth century and Twentieth century, each with its source.
</li><li>Pictures: the old pages had a 750px photograph of each case and one small drawing. The 61 covers and 11 illustrations are new, from the exhibition’s 2012 scans on the R: drive (up to 10,000px). Boykin’s Boys and Girls Stories of the War, which LSU has only on microfilm, is Duke University Libraries’ copy from the Internet Archive. None of the books are in the Louisiana Digital Library. Eleven books have no scan anywhere; most can be seen in the case photographs.
</li><li>Cover: General Lee and Santa Claus (1867), with its object label. Every picture used as a cover or lead is 720p or larger, so the cover isn’t framed and no lead has “Show whole image” ticked: they fill the screen.
</li></ul>
<h3>Corrected</h3>
<table>
<thead><tr><th scope="col">Before</th><th scope="col">After</th></tr></thead>
<tbody>
<tr><td><del>Bellock &amp; Co.</del></td><td><ins>Blelock &amp; Co.</ins></td></tr>
<tr><td><del>W. E. B. Dubois</del></td><td><ins>W. E. B. Du Bois</ins></td></tr>
<tr><td><del>Schomberg Center for the Study of Black Culture</del></td><td><ins>Schomburg Center for Research in Black Culture</ins></td></tr>
<tr><td><del>Zienert, Karen</del></td><td><ins>Zeinert, Karen</ins></td></tr>
<tr><td><del>the Newberry Award-winning <em>Across Five Aprils</em></del></td><td><ins><em>Across Five Aprils</em>, a Newbery Honor book</ins></td></tr>
<tr><td><del>in 1950 he claimed the same prize</del></td><td><ins>in 1951</ins> (Carl Sandburg)</td></tr>
<tr><td><del>Fahs … 1990</del></td><td><ins>2001</ins> (Case 7 sources)</td></tr>
<tr><td><del>Matthew Brady</del></td><td><ins>Mathew Brady</ins></td></tr>
</tbody>
</table>
<ul><li>Curly quotes, dashes, titles in italics and call numbers in catalog form (PS1449 .C475 D78).
</li><li>The full list, with small typos, is in the theme’s docs/special-exhibition-corrections/blue-and-grey.md.
</li></ul>
<h3>Links</h3>
<ul><li>Kept: Carol Hurst’s Civil War in Children’s Literature; the Baldwin Library, de Grummond Collection and University of Minnesota at their new addresses; two Merry Coz pages and Dartmouth’s Casting Characters through the Wayback Machine.
</li><li>Dropped: nine links that are dead or now lead only to a home page.
</li></ul>
<h3>To verify</h3>
<ul><li>The year, 2012, and the credit: the case photographs and author sources look older (Case 12 ends in 2001), and the first curator isn’t named.
</li><li>Hannah Courageous’s call number “S 3523 O4711 H36”.
</li><li>“Reissued … see Case 12” for General Lee and Santa Claus and On the Blockade, which Case 12 doesn’t list.
</li><li>Robertson as “Chairman” of the Centennial Commission, born 1930 with no death date; Tate among the “Fugitive Poets” of the 1930s.
</li></ul>
<h3>New wording to check</h3>
<ul><li>The subtitle, credit line, cover label, plate headings, the Introduction’s closing sentence, the “Also in the case” plates, captions and the 86 alt texts.
</li></ul>
HTML),
]);

$section('The Baton Rouge Bus Boycott', [
  $text(NULL, <<<'HTML'
<p>Built at <a href="/v2/oralhistory/exhibitions/boycott">/v2/oralhistory/exhibitions/boycott</a> (/v2/special/exhibitions/boycott redirects there), not published yet.</p>
<p>Exhibition: The Baton Rouge Bus Boycott, an online exhibition of the T. Harry Williams Center for Oral History, made in summer 1998 by McKinley High School students (guided by LaVada U. Taylor) and the LSU class EDCI 5880 (Petra Munro and Pamela Dean), revised in 2002 by Mary Price and Matt Mullenix.<br>Old site: the Showcase pages /boycott/introduction and nodes 284–287, linking to the 2002 static site for the clips, Rev. Jemison’s story and three student interviews. The text is from the Showcase pages, checked against the static pages.</p>
<h3>Changed on the page</h3>
<ul><li>Four galleries, all walls with a lead picture: Chronology, 1950–1955 (19 dated wall labels and the strike’s newspaper headline); Background (Mary Price’s essay as five text plates between the eleven free ride photographs and three gas stations); The People (eight narrators, each a portrait, clip, transcript, role and biography; Johnnie A. Jones’s portrait and biography; the three student interviews as text plates); Our Thanks.
</li><li>Clips: the eight .wav excerpts as .m4a at −16 LUFS, each transcript checked against its recording, with read-along timings. Rev. Jemison’s story page and its two excerpts are part of his entry.
</li><li>Pictures: no larger copies were found on the R: drive or in the Louisiana Digital Library (the LDL has the interviews, not the photographs), so each is the old image, cropped to the photograph. The cover is the clean “Free Ride” original of the faded banner the Background page used. The strike headline and McKinley High School come from Integrating LSU.
</li><li>Six biographies link to the full interviews in the Louisiana Digital Library.
</li><li>The photo copyright warning and the dead interview abstract link are gone.
</li></ul>
<h3>Corrected</h3>
<table>
<thead><tr><th scope="col">Before</th><th scope="col">After</th></tr></thead>
<tbody>
<tr><td><del>married since 19100</del></td><td><ins>married since 1950</ins> <em>Isadore Tansil, from the 2002 page</em></td></tr>
<tr><td><del>What lead me to get involved</del></td><td><ins>What led me to get involved</ins> <em>Willis Reed, as he says it</em></td></tr>
<tr><td><del>what became know as the Baton Rouge Buss Boycott</del></td><td><ins>what became known as the Baton Rouge Bus Boycott</ins></td></tr>
<tr><td><del>Reverend Jemison said told the policeman</del></td><td><ins>Reverend Jemison told the policeman</ins></td></tr>
<tr><td><del>gaurd · bodygaurd · Reverened</del></td><td><ins>guard · bodyguard · Reverend</ins></td></tr>
<tr><td><del>a footsoldier patroling</del></td><td><ins>a foot soldier patrolling</ins></td></tr>
<tr><td><del>Bus driver “man handles” black woman rider</del></td><td><ins>A bus driver manhandles a black woman rider</ins></td></tr>
<tr><td><del>Jessie Webb</del></td><td><ins>Jesse Webb</ins></td></tr>
<tr><td><del>Mr. Jonnie A. Jones</del></td><td><ins>Johnnie A. Jones</ins></td></tr>
<tr><td><del>Erin Porch · Rahshada Jenkins</del></td><td><ins>Erin Porche · Rashada Jenkins</ins> <em>as in the LDL</em></td></tr>
<tr><td><del>On 19 June 1953</del></td><td><ins>On June 19, 1953</ins></td></tr>
</tbody>
</table>
<ul><li>About 40 small fixes in the three student transcripts (Scotlandville, Rougon, picket, Piccadilly and others), and curly quotes, dashes and spaced ellipses.
</li><li>The full list is in the theme’s docs/special-exhibition-corrections/boycott.md.
</li></ul>
<h3>To verify</h3>
<p>Left as written:</p>
<ul><li>When the boycott ended: called off June 23 with Ordinance 251 on June 24 (Chronology), ended June 24 (Background), June 26 (Integrating LSU).
</li><li>Who announced it on WLCS: Raymond Scott alone, Jemison and Scott, or Reed and Scott, depending on the page.
</li><li>Ernest Ritchie or Richie (Integrating LSU), and whether the gas station photographs are his or Horatio Thompson’s.
</li><li>The years of the interviews other than Jemison’s (1995) and Anderson’s (1998).
</li><li>Laborde’s “Dr. Jemison [sic]”; Robinson’s and Batiste’s mothers’ names, which differ from the LDL; a question missing from Robinson’s interview; Batiste’s “Julius Bond”; Courtney Zachary or Zachery.
</li></ul>
<h3>New wording to check</h3>
<ul><li>The subtitle (“A Recaptured Past”) and credit line, the Chronology headings, the plate titles and Summaries, the picture titles and captions, the clip titles and introductions, the narrators’ roles, the lead captions and the alt text for every picture.
</li></ul>
HTML),
]);

$section('Louisiana for Bibliophiles: A History of Reading in the Bayou State', [
  $text(NULL, <<<'HTML'
<p>Built at <a href="/v2/special/exhibitions/louisiana-for-bibliophiles">/v2/special/exhibitions/louisiana-for-bibliophiles</a>, not published yet.</p>
<p>Exhibition: Louisiana for Bibliophiles, Hill Memorial Library, June–September 2011, opened for the RBMS conference in Baton Rouge; curated by Tara Zachary Laver and Michael Taylor, the online version designed by Rachel Miller.<br>Old site: ten pages under /louisianaforbibliophile (node/113705–113714), with some characters mis-encoded.</p>
<h3>Changed on the page</h3>
<ul><li>The Introduction is the page’s intro. Eight galleries, all walls with a lead picture: Early Days, Creole Authors, African Americans, Women’s Reading and Novels, Practical Reading, Newspapers, Louisiana Libraries, and About the Exhibition (the curators, and the old Index as a plate whose “See …” lines link to the galleries).
</li><li>The text between pictures is 45 text plates, each with a new heading and summary, hung before the pictures it describes; the longest sections are split where the subject turns. Books shown in several pages are one tile that pages through them.
</li><li>Pictures: 58 of the 74 are new copies from the exhibition’s 2011 TIFF scans on the R: drive, cropped as the old pages had them, up to 2,560px (the old pages’ were 1,000px). Huey Long’s Bible is shown whole, the map beside his notes. Sixteen have no scan; the Louisiana Digital Library has none of them either.
</li><li>Cover: the Hill reading room, 1903–1904, with its object label.
</li></ul>
<h3>Corrected</h3>
<table>
<thead><tr><th scope="col">Before</th><th scope="col">After</th></tr></thead>
<tbody>
<tr><td><del>“Read—and share with your neighbors”</del></td><td><ins>“Read, and lend to your Neighbors”</ins> (as printed on Fisher’s pamphlet)</td></tr>
<tr><td><del>Pouponne et Balthazaar</del></td><td><ins>Pouponne et Balthazar</ins></td></tr>
<tr><td><del>appendre le français</del></td><td><ins>apprendre le français</ins> (Cherbonnier)</td></tr>
<tr><td><del>Fenelon’s</del></td><td><ins>Fénelon’s</ins></td></tr>
<tr><td><del>Pointe a la Hache</del></td><td><ins>Pointe à la Hache</ins></td></tr>
<tr><td><del>LLLMVC</del></td><td><ins>LLMVC</ins></td></tr>
<tr><td><del>LOUISiana Digital Library</del></td><td><ins>Louisiana Digital Library</ins></td></tr>
</tbody>
</table>
<ul><li>Three paragraphs that were printed twice (Esther Wright; Sherman’s library, two) appear once.
</li><li>Curly quotes, dashes, accents and titles in italics.
</li><li>The full list is in the theme’s docs/special-exhibition-corrections/louisiana-for-bibliophiles.md, with the source of every picture.
</li></ul>
<h3>To verify</h3>
<ul><li>Henrietta Brock’s source: the caption says the Thirty-Seventh Annual Session (1916); the scan’s folder says thirty-sixth (1915–1916).
</li><li>The Laussat bookplate is signed “Ph. Favrot”; the lead caption names him.
</li><li>The curators’ and helpers’ titles are as of 2011.
</li></ul>
<h3>New wording to check</h3>
<ul><li>The 45 plate headings and summaries, the tiles’ short titles, the cover label and the 74 alt texts.
</li></ul>
HTML),
]);

$section('Sugar at LSU: A Chronology', [
  $text(NULL, <<<'HTML'
<p>Built at <a href="/v2/special/exhibitions/sugar">/v2/special/exhibitions/sugar</a>, not published yet.</p>
<p>Exhibition: Sugar at LSU: A Chronology, LSU Libraries Special Collections, 1995, for the two hundredth anniversary of commercial sugar production in Louisiana. Curated by Christina Riquelmy and Debra Currie, mounted by Emily Robison. Website by Tatiana Shabelnik and Chuck Thomas, with Elaine Smyth; revised in 2002 by Matt Mullenix.<br>Old site: the Showcase pages /sugar/introduction and nodes 275–281 (History to 1700s, The 1800s, 1900s, Today and Tomorrow, Additional Information, Bibliography, Acknowledgements), with 29 pictures at 600px. The text is from the Showcase pages.</p>
<h3>Changed on the page</h3>
<ul><li>Seven galleries, all walls with a lead picture: Introduction; History to the 1700s; The 1800s; The 1900s; Today and Tomorrow; Also on Display (was Additional Information); About the Exhibition (the bibliography, the acknowledgements and the credits).
</li><li>Each of the thirteen cases’ essays is a text plate with a heading and a Summary, its paragraphs unchanged, then the case’s other items as an “Also in case N” list. The case’s pictures hang after it as entries with short titles.
</li><li>Pictures: 36. Ten come from better copies: nine from Louisiana Digital Library masters (the Mugnier glass negative of the levee and refinery, the 1899 laboratory, the 1907 cane harvester, and six items the cases listed but the old site didn’t picture: the 1905 kettle on the cover, the 1926 Audubon Sugar Factory, the 1970 kettle marker, the first seedling of 1907, sugar students in 1902 and the 1899 chemical laboratory), and the Thornton cane derrick from a 2013 exhibition scan on the R: drive. The other 26 are the old 600px files; no rescans were found. Every lead and the cover is 1280×720 or larger except Also on Display’s, the old 447×600 man shaping sugar cones, which is shown whole (framed over a blur of itself) rather than enlarged.
</li><li>The text is 1995’s, so the credit line says so, and its “today” and “this century” read in time.
</li></ul>
<h3>Corrected</h3>
<table>
<thead><tr><th scope="col">Before</th><th scope="col">After</th></tr></thead>
<tbody>
<tr><td><del>Etienne de Bore</del></td><td><ins>Étienne de Boré</ins> <em>as on the 1970 marker</em></td></tr>
<tr><td><del>Thorton Cane Storage</del></td><td><ins>Thornton</ins> <em>as written on the photograph</em></td></tr>
<tr><td><del>William G. Taggert</del></td><td><ins>Taggart</ins> <em>as on the 1907 seedling photograph</em></td></tr>
<tr><td><del>Deer, Noel</del></td><td><ins>Deerr, Noel</ins> <em>the bibliography, twice</em></td></tr>
<tr><td><del>frost-resistance, and heartiness</del></td><td><ins>hardiness</ins></td></tr>
<tr><td><del>Solenopsis invecta</del></td><td><ins><em>Solenopsis invicta</em></ins></td></tr>
<tr><td><del>Eutheola rugiceps</del></td><td><ins><em>Euetheola rugiceps</em></ins></td></tr>
<tr><td><del>Sugar-Cane Disease</del></td><td><ins>Sugar-Cane Diseases</ins> <em>Koike’s title</em></td></tr>
<tr><td><del>Debbie Currie</del></td><td><ins>Debra Currie</ins> <em>as in the Acknowledgements</em></td></tr>
<tr><td><del>1500’s, 1700’s, 1790’s</del></td><td><ins>1500s, 1700s, 1790s</ins> <em>every decade</em></td></tr>
</tbody>
</table>
<ul><li>French accents restored in the bibliography and case lists (précis, canne à sucre, raisonné, Neufchâtel, Mémoires, Amérique, Traité, Dutrône, Baumé, Espérance).
</li><li>Small fixes in spacing and punctuation, double hyphens as em dashes, titles in italics, en dashes in ranges; sug9b’s caption no longer links to its own image.
</li><li>The full list is in the theme’s docs/special-exhibition-corrections/sugar.md.
</li></ul>
<h3>To verify</h3>
<p>Left as written:</p>
<ul><li>“Dr. Clayton C. Mooreland”: may be Moreland.
</li><li>The present-tense figures (80,000 new varieties a year, two refineries left after Supreme closed) are 1995’s.
</li><li>Two captions name more views than are pictured: the experiment station with the 1894 laboratory interior, and the plantation replica with its kettles and interior.
</li><li>Photographers’ credits (Jim Zietz, John Wozniak, Joey Quebedeaux, Jeff Hoy) are as the old pages gave them.
</li></ul>
<h3>New wording to check</h3>
<ul><li>The title split (“Sugar at LSU” / “A Chronology”), the credit line and the cover’s object label.
</li><li>The gallery title changes, the plate titles and Summaries, the entry titles, the lead captions, the notes in brackets in the case lists, and the alt text for every picture.
</li></ul>
HTML),
]);

$section('Creole Echoes: The Francophone Music and Literature of Nineteenth-Century New Orleans', [
  $text(NULL, <<<'HTML'
<p>Built at <a href="/v2/special/exhibitions/creole">/v2/special/exhibitions/creole</a>, not published yet.</p>
<p>Exhibition: Résonances créoles / Creole Echoes, Hill Memorial Library, August 20 – November 3, 2001, curated by Frédérique Spill and Jean-Marc Allard Duplantier, with an introduction by Salwa Nacouzi.<br>Old site: the Showcase pages under /creole (introduction, case1–case20, three biographies, the lesson plans, questions and credits). The French version under /creole/fr is not migrated.</p>
<h3>Changed on the page</h3>
<ul><li>Seventeen galleries, all walls with a lead picture: Introduction, the twenty cases (grouped where the old site split one subject over several pages: The Doctor, the Priest and the Dandy; Beyond the “Belle Créole”; Crossing the Musical Color Line; Opera in New Orleans) and For Teachers, which takes in the letter to educators, the three lesson plans and the Questions for Comprehension.
</li><li>Each gallery opens with a text plate, a heading and a summary from the case’s own text; the long biographies are plates of their own. Quotations set apart in italics are block quotes, and “See:” lines are citations.
</li><li>Pictures: 147. Eight are new masters from the Louisiana Digital Library: four George François Mugnier stereographs, shown as whole cards (No. 374 is the cover; Nos. 375 and 418 lead Introduction and The Creole Family), and four items from the Sidonie de la Houssaye Papers. The other 139 are the 2001 images at about 300px; there are no larger copies on the R: drive or in the LDL.
</li><li>Leads: four galleries open on an LDL master of 720p or more and fill the screen (Beyond the “Belle Créole” now on the postcard of de la Houssaye’s home, The Creole Family on Mugnier’s No. 418). The other thirteen have only 2001 images, so their leads have “Show whole image” ticked: shown whole over a blurred wash, not enlarged. The cover is large enough, so it is not framed.
</li><li>Free People of Color in Louisiana covers some of the same people (Barès, Desdunes, Lanusse, Roudanez); the two share no pictures.
</li></ul>
<h3>Corrected</h3>
<table>
<thead><tr><th scope="col">Before</th><th scope="col">After</th></tr></thead>
<tbody>
<tr><td><del>a perineal favorite</del></td><td><ins>a perennial favorite</ins></td></tr>
<tr><td><del>Pierre Delcour</del></td><td><ins>Pierre Dalcour</ins></td></tr>
<tr><td><del>Frédérique Chopin</del></td><td><ins>Frédéric Chopin</ins></td></tr>
<tr><td><del>cause of back people</del></td><td><ins>cause of black people</ins></td></tr>
<tr><td><del>Nantes then to Renne</del></td><td><ins>Nantes then to Rennes</ins></td></tr>
<tr><td><del>(New Orleans, 1972)</del></td><td><ins>(New Orleans, 1872)</ins> <em>a caption</em></td></tr>
<tr><td><del>Peychaus &amp; Garcia, 1976</del></td><td><ins>Peychaud &amp; Garcia, 1876</ins> <em>a caption</em></td></tr>
<tr><td><del>Gwendolin Midlo Hall</del></td><td><ins>Gwendolyn Midlo Hall</ins> <em>Lesson 1</em></td></tr>
</tbody>
</table>
<ul><li>About 40 more typos in the text, captions and lessons (Tabacco, Cemetary, L’Ouverature, Craegus, McIlheney and others), italics that started or stopped a letter off, and book and newspaper titles put in italics; the full list is in the theme’s docs/special-exhibition-corrections/creole.md.
</li></ul>
<h3>Links</h3>
<ul><li>The lesson plans keep The Hermitage, the National Park Service’s Chalmette Battlefield and All Things Cherokee. Links that no longer work (the Louisiana State Museum’s and the Secretary of State’s old sites, HistoryNet, Hillsdale College, a GeoCities page) and the lessons’ links to other exhibition pages are gone.
</li><li>The lessons and questions use the galleries’ new names.
</li></ul>
<h3>To verify</h3>
<ul><li>“the 1764 transfer” of Louisiana to Spain (ceded 1762, completed 1769).
</li><li>Sarah Bernhardt called a “singer” (she was an actress).
</li><li>L’Athénée louisianais founded in 1875, while the declaration shown is dated 1882.
</li><li>“beyond the confident veneer”, and Dear Educator’s mention of links in the lesson plans, some of which are gone.
</li></ul>
<h3>New wording to check</h3>
<ul><li>Every plate title and summary, the picture titles, the alt text, the cover label, the caption for the five Mugnier stereographs and the summaries of the lessons.
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
