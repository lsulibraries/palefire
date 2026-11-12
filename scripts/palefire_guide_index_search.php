<?php

/**
 * @file
 * Guide page: "Combined Search Project" (was "Index Search Project"), the
 * living status of the new search page at /v2/search, as a Modular page in
 * guide mode. Run
 * palefire_guide_setup.php and palefire_guide_blocks_setup.php first.
 *
 *   drush php:script scripts/palefire_guide_index_search.php
 *   drush php:script scripts/palefire_guide_index_search.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. Built the same way as
 * palefire_guide_ldl_migration.php: this script is the page's source and can
 * be run again. A new page is created UNPUBLISHED; after that each build
 * replaces the intro and sections in a new revision, and stops if the page
 * was edited in Drupal or has a newer draft (force=1 overrides the first).
 *
 * Optional arguments after "--": apply=1, force=1, format=<text format>,
 * path=<alias> (default /v2/guides/index-search-project).
 *
 * Status changes go in the "Status at a glance" section below, and each working
 * day's work goes in "Day by day" at the end. This page may be published, so
 * security findings and server details stay out of it.
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

$title = 'Combined Search Project';
// Its title until 12 Nov 2026: the page is found by either, and renamed.
$old_titles = ['Index Search Project'];

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
$code = fn (?string $heading, string $text, string $caption = '') => [
  'kind' => 'code',
  'heading' => $heading,
  'text' => $text,
  'caption' => $caption,
];

$intro = '<p>What the new search page, Combined Search, covers, what\'s finished, what we found along the way, and what comes next.</p>';

// Sections. ----------------------------------------------------------------

$section('Status at a glance', [
  $text(NULL, <<<'HTML'
<p><strong>Status as of Thu 12 Nov 2026:</strong> the first build is running on production at /v2/search and searches real content in all four modes. The Special Collections hero search now opens it, with All sources: the Catalog (set to Special Collections), Special Collections Guides and PDF Finding Aids. The header's Search drawer opens it too, starting on the section you're in. A search for hours starts with today's hours. The site's other search forms still go to the old page. Catalog rows now show where each copy is, and a shelf with several boxes or volumes lists each one with its own request. The look of the result rows may still change: a second round of designs is waiting for a decision. Research now has a Journals section, and a search for a journal by name shows that journal first. Oral histories is a source of its own beside PDF Finding Aids, with a list of every series under it, and its own section in Special Collections. An interview no longer shows a second time as its Catalog record. Results now arrive with some motion, the term searched is set large over them, and a search that finds nothing suggests searches to try and shows a piece from the collections.</p>
HTML, 'callout'),
  $status('Where each stage stands', [
    ['done', 'Review of the current search', 'The one_search module, its 11 Views, the search index and the connector modules are read.'],
    ['done', 'Names', 'Combined Search is the name for visitors (Index was its working name until Tue 10 Nov), with four modes: All, Research, Special Collections and Website. The address is /v2/search.'],
    ['done', 'Page design', 'Layout and rows are styled, with no sideways scrolling at 320, 768, 1024 and 1440 pixels on sample data.'],
    ['done', 'Module first run', 'palefire_search is enabled on production. All four modes, source filters, paging, Discovery, Research Guides, Staff, Gear, Databases and E-Textbooks return real results.'],
    ['running', 'Filters and sorting', 'Choose several filters at once, remove them one at a time, and sort News and Discovery by date. Results change in place, without reloading the page. Filters and sorting are checked on production for logged-out visitors; changing in place still needs a check in a browser, logged in and out.'],
    ['done', 'Search operators', 'AND, OR, NOT, a minus sign and parentheses, read the same way in every source that can honor them. Research Guides can\'t, so the page says what it searched for instead. Exact phrases in the site\'s own content are still read as separate words, because its index matches single words only.'],
    ['done', 'Power searches', 'Word endings (econom*), author:, title: and subject: searches, year: ranges and a Search tips panel, checked on production. The Catalog takes one field to a search, and year: reaches only the Catalog and Discovery.'],
    ['done', 'Pictures in results', 'Staff, News, Gear and Pages show their picture, and Discovery shows a cover when Discovery has one. A result with no picture keeps its text at full width.'],
    ['running', 'E-Textbook covers and details', 'Every E-Textbook shows its cover, with a Book details toggle for its class, instructor, section and term, carrying over what worked in the old search.'],
    ['running', 'One row per E-Textbook', 'Each book shows once, however many class sections use it, and Book details lists every section. Copies are merged on display only: nodes and the index are unchanged.'],
    ['running', 'Links to Discovery and the Catalog', 'Discovery says it uses Discovery, and links to the same search in Discovery and the Catalog.'],
    ['running', 'Catalog results', 'A Catalog section in All and Research, read from the public results feed with no login. The feed gives up to 300 records and no total, and it is replaced when the Catalog moves to Alma.'],
    ['running', 'Catalog filters', 'Type, Library, Language and Year, one choice to a filter because that is all the service takes. Totals and counts come from the catalog\'s results page, and the filters still work without them.'],
    ['running', 'Catalog holdings', 'Catalog rows say whether a copy is available and where, and open to list each library, shelf, floor, material type, call number and what LSU has, with links to the shelf map, holds and Special Collections requests. Special Collections can search the Catalog too, set to its own library until changed. The feed still orders the rows, and the catalog\'s web service adds copies just after, and takes over the search, newest first, if the feed goes down.'],
    ['running', 'Each box and volume', 'A shelf with several items lists each one, up to 20, with its own status and request, so a request names the box wanted. What LSU has appears on the row of the call number it belongs to, or of the box it names. Requests still need a check in a browser, to confirm the form receives the right box.'],
    ['running', 'Special Collections Guides', 'Research guides tagged Special Collections, as their own source in Special Collections mode, from the guides site search with no key. A guide joins when it is tagged. Needs a check on production.'],
    ['running', 'Catalog and finding aids linked', 'A Catalog record that links a finding aid or oral history abstract gets an Open PDF button, and a PDF Finding Aids result gets its Catalog record\'s copies, matched by the PDF\'s address. Archive and manuscript copies head their numbers Stack location. Needs a check on production.'],
    ['running', 'Reading room wording', 'Special Collections copies say Available to view and Request to view, with one note per record that they are viewed in the Hill Memorial reading room and a link to how requests work. Sign-in wording waits until requests move to Alma.'],
    ['running', 'Special Collections hero search', 'The search on /v2/special is switched on and opens Combined Search in Special Collections mode, with All sources and the Catalog set to Special Collections. Landing searches now show the loading mark while results load, so the wait no longer looks as if nothing happened.'],
    ['running', 'Result row design', 'Rows keep their hairlines and boxed type labels, with the Show location button to the left of the availability text, saying Hide once open. An open result\'s table sits on a soft panel. Hatched rows were tried and withdrawn as confusing. Four more designs are drafted, and none is chosen yet.'],
    ['running', 'Catalog first', 'Catalog comes first in Research and Special Collections. In Special Collections it sits above the Special Collections Guides and Oral histories sections and the PDF Finding Aids list, set to Special Collections, until a source is chosen.'],
    ['running', 'Show more results', 'Long lists have a button that adds the next results under the current ones, and the pager stays for jumping ahead. A shared link, a reload or Back brings back every result shown, not only the last batch, and a Copy link button beside Go to page copies that link (Share on phones). Needs a check on production.'],
    ['running', 'Results bar', 'The count and the words searched stay in view while scrolling, under the page bar.'],
    ['running', 'Your own source order', 'Source options can be dragged or moved up with a button, and the sections follow. Each browser keeps its own order, with a Reset order button.'],
    ['running', 'Source definitions', 'Section headings and a What are these? button in the Source filter say what each source searches, in the glossary\'s cards.'],
    ['running', 'Fuller Discovery search', 'Discovery searches Discovery directly, with Full text online, Peer reviewed, In the Libraries’ collection, EPUB e-books, Type, Language and Year published filters. Its order matches Discovery\'s own once Discovery\'s catalog records are set aside: those come from a database our Discovery connection doesn\'t search, and show in the Catalog section instead. Rows say what each result is and link to full text. Paging stops at the 300 results the service returns.'],
    ['running', 'Loading indicator and current styles', 'The vertical LSU Libraries mark shows while a search runs, at the logo\'s own weight, its rule moving end to end, and the line in the search field moves with it. The page follows the latest Special Collections styles.'],
    ['running', 'Alma placeholder', 'Research and Special Collections list Alma (coming soon) as a dimmed source that can\'t be chosen, with a line in What are these? It searches nothing yet.'],
    ['running', 'Journals', 'The homepage\'s e-journal search, brought into Research as its own section: journals, newspapers, newsletters and reports the Libraries can read online, each with where to read it and which years, and a Type filter. A search that is a journal\'s name (Nature, The New York Times) shows that journal first, above Discovery\'s results. It uses Discovery\'s own connection, so it moves to Alma with Discovery. Needs a check on production.'],
    ['running', 'Finishing touches', 'Results rise into place in turn, counts count up, the term searched is set large over the results and rises in letter by letter, and in Special Collections the empty field types out searches to try. Ctrl+K jumps to the search field and the arrow keys move between results. A search with no results offers searches to try and a picture from the exhibitions. Nothing moves for anyone who has asked for reduced motion. Needs a check on production.'],
    ['running', 'Scholarly Repository', 'Its own section again in All and Research, with highlighted abstracts, authors, type and year, a Year published filter and newest or oldest sorting. The repository\'s search turns away requests from our server, so the site keeps its own copy of the repository\'s records, refreshed daily. It searches titles, authors, abstracts and keywords; a note under the heading and a Go to link send people to the repository for the full text of each document. Needs the first copy made on production.'],
    ['todo', 'Staff comparison of Discovery and Alma', 'A staff-only page showing both results side by side, with room for notes. Waiting on the Alma sandbox details.'],
    ['running', 'Feedback question', 'Under the results, Did you find what you needed? Yes, Partly or No opens a short feedback form with the search already filled in. It appears once the form is made on production. Needs a check on production before testers get the link.'],
    ['todo', 'More sorting and filters', 'A date range, sorting by title, and a clean year for Archive Records. These need changes to the search index and a reindex.'],
    ['todo', 'Palefire pages in search', 'modular_page isn\'t in the search index, so Palefire pages can\'t appear in results until it\'s added and the site is reindexed.'],
    ['todo', 'Point the search forms here', 'The Special Collections hero search has moved. The header panel, other landing and 404 searches still go to /bento, and they move once the page has been tested.'],
    ['todo', 'Move Discovery and the Catalog to Alma', 'Waiting on the migration. See Alma below.'],
  ]),
]);

$section('What it searches', [
  $text(NULL, <<<'HTML'
<table>
<thead><tr><th scope="col">Mode</th><th scope="col">Sources</th><th scope="col">Shown as</th></tr></thead>
<tbody>
<tr><td>All</td><td>Every source</td><td>Labelled sections, top three of each</td></tr>
<tr><td>Research</td><td>Catalog, Discovery, Journals, Databases, E-Textbooks, Research Guides, Scholarly Repository, PDF Finding Aids, Oral histories</td><td>Labelled sections</td></tr>
<tr><td>Special Collections</td><td>Catalog, set to Special Collections, then PDF Finding Aids and Oral histories</td><td>The Catalog above one ranked list with filters</td></tr>
<tr><td>Website</td><td>Pages, Questions &amp; Answers, Gear, News and Notes, Staff</td><td>One ranked list with filters</td></tr>
</tbody>
</table>
<p>Sources that live in the site's own index can share one ranked list. Outside services can't be ranked against them, so they stay in their own sections.</p>
HTML),
]);

$section('How it works', [
  $flow(NULL, [
    $step('A visitor searches', 'A normal web address, /v2/search?q=, so any search can be shared.'),
    $step('Local results come first', 'One direct query to the site\'s search index, matching all the words.'),
    $step('Outside services follow', 'Each loads on its own after the page, so a slow service can\'t hold it up.'),
    $step('Failures say so', 'A service that can\'t be reached says that, instead of showing no results.'),
  ]),
]);

$section('Before and after', [
  $text(NULL, <<<'HTML'
<table>
<thead><tr><th scope="col">Before: the old search</th><th scope="col">After: Combined Search</th></tr></thead>
<tbody>
<tr><td>Some sources match any word, others all words. "huey long" finds 1,267 Archive Records, more than twice the 630 for "huey" alone.</td><td>Every local source matches all the words. "huey long" finds 611 Archive Records, and "huey" finds 630.</td></tr>
<tr><td>Words under three letters are ignored, which seems to turn off the filter. "ai" and "r&amp;b" return identical counts.</td><td>Those words aren't sent to the site's index, and the page says why. Outside services still search for them.</td></tr>
<tr><td>The search term goes into outside addresses unencoded, which is likely why "r&amp;b" returns about 51.9 million results.</td><td>The term is encoded first. "r&amp;b" finds about 490,000 results in Discovery.</td></tr>
<tr><td>Errors from outside services look like zero results.</td><td>They're reported as unavailable, and the failure isn't kept.</td></tr>
<tr><td>Result descriptions only appear in hover tooltips.</td><td>They're shown in the result.</td></tr>
</tbody>
</table>
<p>Combined Search's counts were checked on Fri 6 Nov 2026, and they change as records are added.</p>
HTML),
]);

$section('Why Discovery shows different results here', [
  $text(NULL, <<<'HTML'
<p>The same search can give different results and counts here and on EBSCO's own Discovery site. Both search the same Discovery index, but each asks it with different settings. Neither is wrong: they are tuned for different jobs.</p>
HTML, 'callout'),
  $text(NULL, <<<'HTML'
<table>
<thead><tr><th scope="col">Setting</th><th scope="col">EBSCO's Discovery site</th><th scope="col">Combined Search</th></tr></thead>
<tbody>
<tr><td>How words match</td><td>Proximity: the words must appear within five words of each other.</td><td>All words: every word must appear somewhere in the record, as in every other source on the page.</td></tr>
<tr><td>What it includes</td><td>Set by the library's profile for that site in EBSCOadmin. It mixes in Catalog records, such as books and DVDs with call numbers.</td><td>Set by the profile the site's connection uses, which can include different databases and default options. Its results are mostly articles and reference entries; the Catalog has its own section, and Discovery's results link to it.</td></tr>
<tr><td>Extras at the top</td><td>An encyclopedia card pinned above the results, and an AI-assisted search option.</td><td>None: the ranked results only.</td></tr>
<tr><td>Signed in</td><td>Through the library proxy.</td><td>Through the site's connection. A guest on either one can see fewer records.</td></tr>
</tbody>
</table>
<p>In short: same collection, different search settings. EBSCO's site looks for the words near each other and mixes in the Catalog. Combined Search requires all the words and shows the Catalog as its own section.</p>
<p>Still to confirm: which EBSCOadmin profile the site's connection uses, and how its databases and default options compare with the Discovery site's. If the counts should match more closely, that is where to align them.</p>
HTML),
]);

$section('Alma', [
  $text(NULL, <<<'HTML'
<p>The Libraries are moving from EDS and the current catalog to Alma. A sandbox may be available, but it isn't fully populated yet.</p>
<p>The page is ready for this. Results stay under "Discovery" and "Catalog", not a vendor's name, and each service is one definition in the module, so moving it won't change the page's addresses or design. Research and Special Collections already list Alma (coming soon) as a dimmed source.</p>
HTML),
]);

$section('Next steps', [
  $text(NULL, <<<'HTML'
<ol>
<li>Test the new filters, sorting and in-place results on production, logged in and logged out.</li>
<li>Click Request to view on a record with several boxes, and confirm the form receives the right box.</li>
<li>Review the page's design together, and choose a design for the result rows.</li>
<li>Add Palefire pages to the search index and reindex, along with the date range, title sorting and exact phrases.</li>
<li>Make the first copy of the Scholarly Repository on production, and ask the repository team whether our server can use its search, for full-text matches.</li>
<li>Move the site's search forms to Combined Search and redirect /bento.</li>
<li>Decide what requesters sign in with once requests move to Alma, and add that to the request note. Visitors see what they sign in with, not the system's name.</li>
<li>Connect Alma when the sandbox is ready.</li>
</ol>
HTML),
]);

$section('Day by day', [
  $text(NULL, <<<'HTML'
<p>What was done on each working day, newest last.</p>
HTML),
  $text('Thu Nov 5: First build', <<<'HTML'
<ul>
<li>Put the first build on production at /v2/search, searching real content in all four modes, and started this status page.</li>
<li>Added filters that combine, chips to remove them, sorting, and results that change without a page reload.</li>
<li>Added search operators and small pictures in results.</li>
<li>E-Textbooks got covers and Book details, and each book now shows once, with every class section listed under it.</li>
<li>Discovery now searches Discovery directly, with its own filters, labels for each type of result, and a tag on links that need an LSU login.</li>
<li>Added a Catalog section from the public results feed, with filters, real totals, and links to the same search in Discovery and the Catalog.</li>
<li>Added definitions of each source, and a fixed order for tied results so paging doesn't repeat rows.</li>
<li>Added the loading mark and the current Special Collections styles. Tried alternating bands on results, then settled on hairlines.</li>
</ul>
HTML),
  $text('Fri Nov 6: Catalog holdings and first public link', <<<'HTML'
<ul>
<li>Catalog rows now say whether a copy is available and where, and open to a table with shelf maps, holds and Special Collections requests. If the feed goes down, the catalog's web service takes over the search.</li>
<li>The Catalog comes first in Research and Special Collections, and Special Collections can search it, set to its own library. The Special Collections tab moved between Research and Website.</li>
<li>Sources can be put in your own order. Added Show more results, and a results bar that keeps the count and the search in view.</li>
<li>Filters have clearer hover styles, and empty ones are dimmed instead of hidden.</li>
<li>Switched on the Special Collections hero search, the first link to Combined Search from the site, with the Catalog chosen by default, and landing searches now show the loading mark while results load.</li>
<li>The loading mark became the vertical LSU Libraries mark, with a moving rule, and the line in the search field now moves with it. The line tracing its letters now fades once they fill, so the Libraries text matches the logo's weight.</li>
<li>Tried hatched result rows, then withdrew them as confusing. Rows are back to their hairlines and boxed labels, with the Show location button on the left. Four more designs are drafted for review.</li>
<li>A serial with a long run (Green Lantern) was pushing Status and the request button out of view. What LSU has now wraps, each box has its own row, contents and request, and the button says Show 3 items.</li>
<li>Special Collections copies now say Available to view and Request to view, with a note about the reading room.</li>
<li>Compared the Russell B. Long papers with the catalog. No items were missing, but holdings had lost their link to call numbers, so each item now shows what it holds, and the summary row no longer names one call number for all of them.</li>
<li>Compared Huey Long with the catalog and found a copy shown as 4 that the catalog calls 7. The catalog service gives no copy numbers, so these were list positions, and item rows now leave them out.</li>
<li>An open result's table now sits on a soft panel, and its button says Hide while open.</li>
<li>Added Alma (coming soon) as a dimmed source in Research and Special Collections, and turned this page's findings into a before and after, with Combined Search's own counts.</li>
</ul>
HTML),
  $text('Mon Nov 9: Results bar', <<<'HTML'
<ul>
<li>Searched words in results are now marked with a soft tint instead of an underline, which looked like a second link inside the title.</li>
<li>Chosen filters and Clear all moved into the results bar, so they stay in view while scrolling.</li>
<li>The reading room note's How requests work link opens in a new tab, with an arrow, so the search results stay open.</li>
<li>Item rows in a holdings table now repeat their library, shelf and material type in a lighter color, so the table matches the catalog's. Full record became a larger link, Full record in the catalog.</li>
<li>The reading room note moved under the Special Collections row it describes, with an information icon.</li>
<li>Call number and Status can sort a holdings table, as in the catalog, but only within each library, so the libraries stay together.</li>
<li>Holdings tables now have the catalog's columns in the catalog's order, including Copy. The catalog's web service has no copy numbers, so they're read from the record's catalog page when someone opens the table. Libraries are spaced apart as groups, with tighter rows for their items.</li>
<li>Mocked up three layouts for the holdings table and four styles for its note and Full record link, and built the chosen one: each library has a heading with its buttons and a short table of its copies, the reading room note sits in a soft band under its library, and the panel ends with the copy count and a Full record in the catalog button. The catalog's per-copy Place hold links can't be used from outside the catalog, so each library keeps one Place hold.</li>
<li>The Search drawer in the header (the magnifier at the top right) now searches Combined Search, with All, Research, Special Collections and Website to choose from. It starts on the section you're in.</li>
<li>Fixed: signed-in staff saw "Show results from Catalog" links instead of Catalog, Discovery and Guides results. Drupal filling in the staff toolbar was cancelling those searches; now only a search whose section is going away is cancelled.</li>
<li>Mocked up six ways to tell which source you're looking at on a long page of results, and built the chosen two: each source now opens with a proper heading (its name, how many it found, and what it holds), and the bar that follows you down the page names the source you're in.</li>
<li>A search for hours (or "library hours", "is the library open" and the like) now starts with today's hours for the Main Library and Hill Memorial, the same as the Hours drawer. Other services can get a short answer like this later.</li>
<li>The Source list starts with "All sources" checked, so it's clear every source is searched until you pick some.</li>
<li>The search box's border is softer, like the page's other lines.</li>
<li>In Special Collections, taking the Catalog's Special Collections filter off (with no finding aids, oral histories or news chosen) moves the search to Research, keeping its other filters.</li>
<li>Oral histories that have no PDF yet are left out of results.</li>
<li>Fixed: a short source description could sit beside the result count instead of under it, and large counts were missing their commas.</li>
<li>The hours answer now looks like the mockup: a quiet band with each library's hours on one line.</li>
<li>When a search finds results in only one or two sources, each shows more of them: 10 rows, or 8 for Discovery and the Catalog.</li>
<li>The header's Search drawer now explains its four choices: All, Research, Special Collections and Website are rows, each with a line on what it searches.</li>
<li>Questions & Answers results show the whole answer, paragraph by paragraph, instead of the first couple of lines.</li>
<li>Results no longer show through the bar that follows you down the page: the strip above it is filled, and results fade as they pass under it.</li>
<li>Found the real cause of the thin line beside that bar: a searched word's tint and a button's hover fill stand just outside the results column, past the bar's edge. The bar now covers a little beyond the column, and results go under a clear band before reaching it.</li>
</ul>
HTML),
  $text('Tue Nov 10: Finding aid excerpts', <<<'HTML'
<ul>
<li>The Special Collections hero search now opens with both the Catalog and PDF Finding Aids chosen.</li>
<li>In Special Collections mode the breadcrumb reads Home / Special Collections / Combined Search, so there is a way back to the Special Collections page after a search.</li>
<li>Archive Records became PDF Finding Aids: it searches only finding aids and oral history abstracts, all PDFs, and Oral histories is a checkbox under it in place of the Record type list. Breaux Data, Louisiana Folklife, Manuscript Collections and University Archives records left the search.</li>
<li>Archive records that are PDFs got an Open PDF button, in the style of Show location, opening in a new tab, with the file's pages and size beside it (18 pages · 296 KB), so it's plain what opening it means.</li>
<li>Finding Aids searches already read the text of each PDF, but results didn't show it, so a match could look like a mistake. Archive records can now show the words around the match, labelled Found in the PDF, once the index makes excerpts. Turned on the same day: all 20 results for "cotton gin" now show the words from the PDF.</li>
<li>On phones the Filters button spans the screen and shows how many filters are chosen, and its word sits in the middle (the bar below had been covering its bottom edge). The bar of chosen filters runs edge to edge, with Clear all beside the search. The line under that bar is gone; while it follows you down the page it rises off the page with a soft shadow instead. Open PDF buttons show a PDF icon.</li>
<li>The bar that follows you down the results is now frosted glass, like the menu bar and the breadcrumb above it: results blur as they pass under all three. A soft shadow sits under it.</li>
<li>On wide screens, that bar now moves up into the breadcrumb's row as you scroll: the search and its filters sit beside Home / Special Collections / Combined Search, leaving more of the screen for results.</li>
<li>The source named in that bar (Catalog, PDF Finding Aids…) is now gold, like its heading on the page.</li>
<li>When that bar joins the breadcrumb row, the row grows so the filters and Clear all are full size and easy to press, and its shadow now darkens everything passing under it.</li>
<li>The search page is now called Combined Search. Its search term in the results bar turns gold once searched, so you can see which words are in use, and the search box has an × to clear it in one press.</li>
<li>Show more results is now a full-width button you can see at a glance, and the loading animation moves more calmly.</li>
<li>Special Collections searches no longer include News and Notes: just the Catalog and PDF Finding Aids. News is still in All and Website.</li>
<li>Printing a search now gives a clean black-on-white list of the results, with what was searched at the top, and no menus, filters or buttons.</li>
<li>Show more is now a thin line under the last result that fills in gold as you load more, with how many are shown and a Show 20 more button. The page numbers fold away behind Go to page, so there is one way forward instead of two. On phones the button spans the screen.</li>
<li>On phones the chosen filters now sit in their own line under the results bar instead of inside it, so the bar that follows you down the page stays one line. The Filters button above still shows how many are chosen.</li>
<li>Online records now have an Open online button, like Open PDF, with Available online beside it, the way print records show their status beside Show location. It replaces the small Full text link.</li>
<li>Alma (coming soon) now shows in the source list under All, as it does under Research and Special Collections.</li>
<li>The guide now explains why Discovery's results here differ from EBSCO's own Discovery site: the same index, asked with different word matching and settings.</li>
<li>Added Journals to Research: the homepage's e-journal search, as a section of its own. Each journal lists up to three places to read it, with the years each covers and any delay on the newest issues ("1946–present · except the latest 4 years"), best first. A Type filter picks journals, newspapers, newsletters or reports. Books are left out, as on the homepage, since the Catalog and Discovery cover them.</li>
<li>A search that is a journal's name now shows that journal in a card above Discovery's results, with where to read it. Only an exact title counts: "climate change" doesn't bring up WIREs Climate Change.</li>
<li>Open online, Open PDF and Show location buttons are now all the same width, so a column of buttons lines up.</li>
<li>Catalog records and PDF finding aids now point to each other. A Catalog record whose full record links a finding aid shows the same Open PDF button as the finding aid itself, and a finding aid shows its Catalog record's Show items button, with the same boxes, status and Request to view buttons. They are matched by the PDF's own address, so a finding aid with no Catalog record simply has no button.</li>
<li>For archives and manuscripts, the copies table now heads its first column Stack location instead of Call number, since those numbers say where the boxes stand.</li>
<li>Oral histories are linked to their Catalog records too: a Catalog record with an oral history abstract gets Open PDF, and the oral history result gets the record's Show location button. They had not matched, because only finding aids were looked up.</li>
<li>A new search from the search box now keeps the filters, years and sort already chosen. Before, it started over with none.</li>
<li>A chosen filter's tick is now a deeper shade of its gold box instead of black, like the two tones of a duotone icon.</li>
<li>Research Guides and Special Collections Guides now show the words around the match in each guide page, labelled Found in the guide, as finding aids show Found in the PDF. They come from the guides site's own search, so each result is a guide page.</li>
<li>After an update, searches no longer show sections saved from before it: each update gives the late-loading sections new addresses, and browsers check for a fresh results page on every visit instead of keeping it for a day.</li>
<li>The header's Search drawer, set to Special Collections (as it is on every Special Collections page), now ticks the Catalog, Special Collections Guides and PDF Finding Aids, like the search on the Special Collections page.</li>
<li>Searches from the Special Collections page and from the header's Search drawer now start with All sources, instead of choosing sources: they still show the Catalog set to Special Collections, Special Collections Guides and PDF Finding Aids.</li>
<li>On a Catalog result, the PDF button now says PDF Finding Aid (or PDF Abstract for an oral history) instead of Open PDF, so it isn't mistaken for a digital copy of the item.</li>
<li>Added Special Collections Guides to Special Collections mode, between the Catalog and PDF Finding Aids: the research guides tagged Special Collections, searched through the guides site's own search, so a guide shows up as soon as it is tagged. The hero search on the Special Collections page now includes it.</li>
<li>In Special Collections mode the page now ends with the Special Collections footer (Hill Memorial Library's hours, phone and question form), as its breadcrumb already says Special Collections. The other modes keep the Main Library footer.</li>
<li>In a search of several sources, the Source filter now marks the source you are reading, as a table of contents does: a short bar beside it and its name in the headings' color. It moves on when the bar above the results names the next source. The sources under All sources are set in a little, so they read as its parts.</li>
<li>The line under the page title now names the catalog and ebooks too: Search the catalog, articles and ebooks, databases, archives and library pages at once.</li>
<li>A Catalog record's Show location panel now ends with About this item: the record's summary, contents, publication, physical description, notes and local notes, subjects and more, from the same request as the copies. Subjects, series, earlier and later titles and other names each link to a Catalog search. Long texts show four lines until Read more.</li>
<li>On narrower screens, a box's copy number no longer runs down the side one letter a line: the call number and what the box holds take the row, Copy 1 sits at its end, and Request to view goes on its own line.</li>
</ul>
HTML),
  $text('Wed Nov 11: Exact phrases and short words', <<<'HTML'
<ul>
<li>Found why "exact phrases" in quotes still matched the words anywhere in finding aids and other site content: the site's own index matches single words only, so it reads "barry allen" as barry and allen. The Catalog and Discovery already honor phrases.</li>
<li>The index's Stopwords setting was turned off, so phrases such as "battle of new orleans" keep their small words, and the shortest word it reads went from three letters to two, so searches such as WW II or St. Joseph keep their two-letter words. The search now passes two-letter words on too. Both take effect after a reindex.</li>
<li>Checked what the Catalog and Discovery can do for power searches: both read word endings (econom* finds economics and economy). Discovery reads author, title and subject searches in any mix. The Catalog reads one of them per search.</li>
<li>The Show location panel is tidier: Copy and Status sit right after the call number instead of at the far edge, About this item lines up with them and is headed like the libraries, and a library with one copy no longer shows column headings over its single row.</li>
<li>About this item now looks like a museum object label, set apart from the copies: a small-capitals heading with a short gold rule, small-capitals field names, quieter text, and more space above it. The call numbers, statuses and request buttons stay the brightest things in the panel.</li>
<li>Each box of a comic or magazine run now starts with its years and issue numbers (1961–1968 · nos. 118–180), so you can see which box to request for a given year. The boxes' Request to view buttons are lighter, a long panel has a second Hide at its end, and sorting only appears for five or more copies. Fixed: dates the catalog leaves open ("<Jan. 1977>") were dropped, and some contents lists ran together on one line.</li>
<li>Power searches. A word ending finds every word that starts with it: econom* finds economics and economy, in every source except the research guides. author:faulkner, title:"light in august" and subject:mississippi search one field: Discovery takes any mix of them, the Catalog one to a search, and on library pages author finds oral histories by the person interviewed or interviewing. year:1862 or year:1860-1865 fills in the Year published filter for the Catalog and Discovery. A Search tips button beside the modes lists all of it. A source that can't use part of a search says what it searched for instead, and its Go to link carries the same search to the outside site.</li>
<li>An oral history now opens with what that interview is about, from its catalog record (Green describes her life as a Louisiana resident…), not the description of the project it belongs to, which was the same for every interview in it. The project is still named under Part of in About this item.</li>
<li>Part of in About this item is now a link: for an oral history it searches the catalog for the project, which lists its other interviews (21 for the Louisiana Sea Grant coastal change project). The project's description itself stays off the interview's row and panel.</li>
<li>Oral histories show their series under what they're about, as a link to the same search within that series. Choosing Oral histories in the Source filter now lists its series under it, with how many match, so you can narrow to one or more. Series come from each record's Series field.</li>
<li>The search index held 41,676 items, mostly archive records the search never shows, such as Breaux Data. A new setting in the index leaves them out, keeping only finding aids and oral histories, so reindexing is much quicker.</li>
<li>What are these? now opens inside the Source filter and pushes the sources down, so the descriptions no longer cover the checkboxes they describe. Search tips lines up with the right edge of the page, puts each example on its own line beside what it does, fits the screen without a scroll box of its own on most screens, and has a Close button.</li>
<li>The oral history series are now one dropdown under Oral histories (All series, then each with its count) instead of a long list of checkboxes whose names wrapped in the sidebar. Choosing one updates the results straight away.</li>
<li>About 130 cataloged oral history interviews had no record on the site, so the search never showed them, including the newer Sicilian-Italian and 272 Slaves (Georgetown) series. A new import adds a record for each from the catalog: its abstract PDF, series, summary and interviewer. The module now keeps the list of series by the folder their abstracts are in.</li>
<li>Oral History confirmed the names of the seven series added from the catalog. Robert Ray Cox Series now has a capital S, and a new script keeps every record's series in line with the module's list, so a rename is one edit and one command.</li>
<li>Oral histories became a source of its own beside PDF Finding Aids, which now holds finding aids only. Its Series list shows every series, graying out those with no match, so it reads as the whole collection. Old links to the Oral histories filter still work.</li>
<li>The Series list is now as wide as its row, so long series names fit, and its border is softer until you point at it or tab to it.</li>
</ul>
HTML),
  $text('Thu Nov 12: Oral histories in Special Collections', <<<'HTML'
<ul>
<li>In Special Collections, oral histories now have their own section instead of sharing one list with the finding aids, which is now called PDF Finding Aids. All and Research already worked this way.</li>
<li>An interview showed twice: as an oral history and again as its catalog record, with the same abstract PDF. When the Oral histories section is on the page, the Catalog section leaves those records out and fills its rows with others. Choosing the Catalog on its own, or narrowing Oral histories to one series, still shows them.</li>
<li>Search tips now starts with a lightbulb, and What are these? with an information circle, both in gold, so they look like the helpers they are rather than plain links.</li>
<li>The Catalog and other outside sources now show how many they found in the Source filter, like the site's own sources, as soon as their results arrive. Until then, or if one doesn't answer, the space stays empty rather than showing 0.</li>
<li>The results bar no longer jumps while you scroll. On wide screens, when it moves up into the breadcrumb row, that row grows smoothly to fit and shrinks back at the top, and the bar's words rise into place. The name of the source you're reading fades in and out, and the words beside it slide over instead of jumping. With reduced motion, it all changes at once as before.</li>
<li>The shadow under the results bar is softer and fades out gradually, instead of ending in a dark band.</li>
<li>The Search button now shows only the word Search, and only the magnifier on narrow phones, so it no longer sits as a second small icon beside the clear button.</li>
<li>The page and this guide now use the name Combined Search instead of the working name Index.</li>
<li>Some flair: results rise into place one after another, counts count up, the Source filter draws each source's share as a gold bar, pictures fade up as they load, and the term searched is set large over the results like a gallery label. The results bar's own words appear once it sticks to the top. In Special Collections, the empty search field types out example searches. Ctrl+K (⌘K on a Mac) jumps to the search field, and the up and down arrows move between result titles.</li>
<li>A search that finds nothing now says so plainly, suggests a few searches to try, and shows a picture from one of the exhibitions with a link to it. Nothing moves for anyone who has asked their device for reduced motion.</li>
<li>The gold bars under each source in the Source filter are gone: they looked like underlines or progress bars and were more confusing than useful.</li>
<li>The Catalog's count was missing from the Source filter on production: the script that fills it in hadn't gone out with the rest. It has now. The numbers in the filter count up as they arrive, like the section counts, a little slower so the count-up is seen.</li>
<li>The Search button is solid gold again, here and on the Special Collections page, with just the word Search in the middle. A flatter version tried earlier today made the search hard to spot on the Special Collections photo. On phones both buttons show only the magnifier, from the same width.</li>
<li>The Search button is no longer a solid gold block. It now sits inside the search box behind a thin line, the word Search in gold capitals like the tabs below it, and fills with gold when you point at it or tab to it. The search on the Special Collections page matches. The gold block was the brightest thing on the page and drew the eye away from the title and the results.</li>
<li>A tester likes sharing results by their address, but after Show more the address pointed only at the last batch, so whoever opened it (or anyone who reloaded or came Back from a result) lost the results above. The address now keeps where the list starts and how far it was shown, and those results load again on arrival, up to ten pages.</li>
<li>A Copy link button now sits beside Go to page under a long list, so the link is easy to hand on; on phones it says Share and opens the phone's own share menu. Mockups of a button in the results bar looked crowded, so it went in the footer, where someone who has been browsing ends up anyway.</li>
<li>A question came up: Discovery's own site orders results differently. Checked side by side, the order of articles is the same. Both search for the words within five words of each other. The difference is Discovery's own site puts LSU catalog books first, and our Discovery connection doesn't search the catalog database, so those books show in our Catalog section instead. Adding the catalog to the connection is a setting at EBSCO, not a change here, and is on hold for now.</li>
<li>Discovery has three more filters: In the Libraries’ collection and EPUB e-books under Limit to, and a Language filter with the same twelve languages as the Catalog's. Choosing two languages finds either; a language with a type finds that type in that language. Each language shows how many results it has until one is chosen.</li>
<li>The words searched, set large over the results, now rise in letter by letter, each sharpening as it lands, the whole phrase in about a second and a half however long it is. Words stay whole on a line. Nothing moves for anyone who has asked for reduced motion.</li>
<li>The Scholarly Repository is back as a section in All and Research. The old search asked the repository's own search, which now turns away requests from our server, so it had become only a link. The repository's record feed still answers, so the site now keeps its own copy of its 82,000 or so records (titles, authors, abstracts, keywords, type and date), refreshed daily. Results show the part of the abstract where the words were found, highlighted, which the old search never did. It can't search inside the documents, so a note under the heading says so and links to the repository's own search. We're asking the repository team whether our server can be let through, which would bring full-text matches back.</li>
<li>Before the link goes out to testers, the page now asks for feedback. Under the results, and under a search that finds nothing, it asks "Did you find what you needed?" with Yes, Partly and No. Each opens a short form, every question optional, that already knows the search, the tab, how many results there were and which sources showed, so a note arrives with what it is about. The question only appears once the form exists on the site.</li>
</ul>
HTML),
]);

// End of sections. ---------------------------------------------------------

$block_count = 0;
foreach ($sections as $item) {
  $block_count += count($item['blocks']);
}
$fingerprint = substr(sha1(json_encode([$intro, $sections], JSON_THROW_ON_ERROR)), 0, 12);
$log_prefix = 'palefire_guide_index_search.php build';
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
  'path' => ['alias' => '/' . ltrim($args['path'] ?? '/v2/guides/index-search-project', '/')],
];
if (isset($node_fields['field_modular_type'])) {
  $values['field_modular_type'] = 'guide';
}
if (isset($node_fields['field_intro'])) {
  $values['field_intro'] = $html($intro);
}

// Owner: Kyle Tanglao's Staff node. Subjects are left empty on purpose: this
// is a project record, not a research guide.
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
