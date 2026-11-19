<?php

/**
 * @file
 * Guide page: "Palefire Redesign Project", the living project record for the
 * whole lib.lsu.edu redesign (the Palefire theme), as a Modular page in guide
 * mode. Run palefire_guide_setup.php and palefire_guide_blocks_setup.php
 * first.
 *
 *   drush php:script scripts/palefire_guide_palefire_redesign.php
 *   drush php:script scripts/palefire_guide_palefire_redesign.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. Built the same way as
 * palefire_guide_ldl_migration.php and palefire_guide_index_search.php: this
 * script is the page's source and can be run again. A new page is created
 * UNPUBLISHED; after that each build replaces the intro and sections in a new
 * revision, and stops if the page was edited in Drupal or has a newer draft
 * (force=1 overrides the first).
 *
 * Optional arguments after "--": apply=1, force=1, format=<text format>,
 * path=<alias> (default /v2/guides/palefire-redesign-project).
 *
 * Project guides with their own pages (Combined Search, the LDL Quartex
 * migration, the style guide) are linked, not copied: their detail stays on
 * their own pages. Status changes go in "Status at a glance" and
 * "Workstreams", and each working day's work goes in "Day by day" at the end.
 * This page may be published, so security findings and server details stay
 * out of it.
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

$title = 'Palefire Redesign Project';
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

$intro = '<p>The project record for the new LSU Libraries website, built on the Palefire theme: what it covers, where each part stands, what was decided, what is still open, and what was done each day.</p>';

// Sections. ----------------------------------------------------------------

$section('Purpose', [
  $text(NULL, <<<'HTML'
<p>Palefire is the new design for lib.lsu.edu: a Drupal 11 theme with the calm of a museum website, Swiss type and grids, reading colors chosen for long research sessions, and WCAG 2.2 AA throughout. It is being built in the open on the live site, under /v2, beside the old pages, one section at a time.</p>
<p>This page is the overview. It says what each part of the redesign is, where it stands and what comes next, and links to each part's own guide where there is one. It does not repeat them.</p>
HTML),
  $text('Project guides', <<<'HTML'
<ul>
<li><a href="/node/133022">Combined Search Project</a>: the new search page, with its own status board and Day by day log.</li>
<li><a href="/v2/special/guides/ldl-quartex-migration">LDL Quartex Migration</a>: the Louisiana Digital Library's move to Quartex, which runs beside the redesign.</li>
<li><a href="/v2/special/internal/styleguide">Palefire style guide</a>: the palette, type, components and the rules behind them, for editors and developers.</li>
<li><a href="/v2/internal/analytics">Website Analytics</a>: the last 28 days of visits, searches and Combined Search feedback, for staff only.</li>
</ul>
HTML),
]);

$section('Status at a glance', [
  $text(NULL, <<<'HTML'
<p><strong>Status as of Thu 12 Nov 2026:</strong> the Special Collections section at /v2/special is built and live, with its landing, guides, exhibitions, location pages, Ask us form and mobile dock. Combined Search is running at /v2/search and the header's Search drawer uses it, while the site's other search forms still go to the old page. The staff directory is at /v2/staff. The style guide records the palette and components and grows with them. Analytics label every page by its section and kind, so reports carry over when /v2 is dropped, and a staff-only Website Analytics page shows the figures. Combined Search asks visitors whether they found what they needed, ahead of sharing it for testing. Still ahead: pointing the remaining searches at Combined Search, the desktop booking dock, the Alma connection, and the move from /v2/special to /special.</p>
HTML, 'callout'),
  $stats(NULL, [
    ['30', 'Days since the first Modular page, Tue 13 Oct'],
    ['900+', 'Changes to the theme'],
    ['3', 'Project guides linked here'],
  ]),
]);

$section('Workstreams', [
  $status('Where each part stands', [
    ['done', 'Theme foundations', 'Modular pages with scroll sections, the table of contents and reading rail, the header, menus and drawers, page transitions, reading colors (Twilight by default) and text size, print styles, and cache busting.'],
    ['done', 'Special Collections landing', '/v2/special: the hero with its search, Highlights and today\'s hours, the Quick Start Guide with request paths and section tabs (Research, Study, Ask us), the Hill Memorial building map, From the stacks, Written at Hill, partnerships and program pages, and the Special Collections footer.'],
    ['running', 'Mobile dock', 'Below 768px on Special Collections pages: Hill\'s hours, the section\'s next step and Contents, one thumb away. A desktop booking dock for rooms booked through LibCal is designed and waits until the location pages are done.'],
    ['done', 'Exhibitions', 'Exhibition pages with title pages, galleries, walls, an object viewer, readings and oral history clips with transcripts. AVES, Audubon, Occult Science, Mossville, the Union Art Gallery and A New Look for LSU Libraries are built, with an Exhibitions page listing digital and physical shows.'],
    ['running', 'Combined Search', 'The new search page at /v2/search, replacing /bento. Running on production in four modes, opened by the Special Collections hero and the header\'s Search drawer. Detail in the <a href="/node/133022">Combined Search Project</a> guide.'],
    ['running', 'Locations and hours', 'Location pages led by the building map, with today\'s hours, policy marks, facts, house rules, photos and booking. The Lecture Hall and Quiet Study pages are the first two. Hours come from LibCal in the hero, footer, dock, Hours drawer and Combined Search.'],
    ['done', 'Staff directory', '/v2/staff: the directory, profiles, and a Who to ask section on Special Collections pages.'],
    ['running', 'Guides', 'Guide pages in the LibGuides model, with flow charts, status boards, figures, code, field crosswalks and glossary terms. How to Use Special Collections (now holding copy prices), Research Help, Louisiana Newspapers, Parking and Travel, Donating Materials, the Glossary and the MODS crosswalk are built, plus the project guides above.'],
    ['running', 'Style guide', 'A living style guide at <a href="/v2/special/internal/styleguide">/v2/special/internal/styleguide</a>, with plates drawn from the theme\'s own tokens and a written source of rules. It is updated with every new component or token.'],
    ['running', 'Analytics', 'GA4 through Google Tag Manager, with every page labeled by section and kind rather than by address. Live since Thu 12 Nov, with a staff-only Website Analytics page. Key event and annotation to come. See Analytics below.'],
    ['todo', 'Move from /v2/special to /special', 'Drop /v2 from the addresses once the section is ready to replace the old pages, with redirects so old links and bookmarks still work.'],
    ['todo', 'Alma', 'The Libraries are moving from Discovery and the current catalog to Alma. Combined Search is ready for it, with vendor names kept out of labels and each source swappable. Waiting on the sandbox.'],
  ]),
  $accordion('Notes on each workstream', [
    ['Special Collections', <<<'HTML'
<p>The first section rebuilt, and the model for the rest. The landing went through several hero designs (a video cover, banners, a split cover with Highlights) before settling on the current one, and its Quick Start became section tabs for Research, Study and Ask us. The Ask us form posts questions through LibAnswers. Ordering Copies was merged into How to Use Special Collections on Thu 12 Nov, so copy requests live in the guide patrons already use.</p>
HTML],
    ['Combined Search', <<<'HTML'
<p>Searches the catalog, Discovery, journals, databases, e-textbooks, research guides, finding aids, oral histories and the site in one page, in All, Research, Special Collections and Website modes. Its status board, findings and daily log are in the <a href="/node/133022">Combined Search Project</a> guide. Still to do there: point the other search forms at it, add Palefire pages to the search index, and connect Alma.</p>
HTML],
    ['Locations and hours', <<<'HTML'
<p>Each room or space gets a page built like /v2/special: one section per part (the room, photos, hours and directions, house rules, booking), numbered and banded, with the map at the top. The Lecture Hall (booked by request) and Quiet Study (walk-in) were built first to test both kinds. The desktop booking dock waits until these pages settle.</p>
HTML],
    ['Staff directory', <<<'HTML'
<p>Staff profiles and a directory at /v2/staff, and Who to ask contact rows on Special Collections pages. Combined Search's Staff source reads the same records.</p>
HTML],
    ['Guides', <<<'HTML'
<p>Guides keep LibGuides' content model so editors can move pages across, and add blocks LibGuides can't do: request paths, flow charts, status boards, figures with progress bars, code boxes, the field crosswalk, glossary terms and related guide cards. Guide text uses the WYSIWYG format. Project guides like this one are built by scripts so they can be rebuilt as the work moves.</p>
HTML],
    ['Style guide', <<<'HTML'
<p>Started Sat 31 Oct. Records the palette and why it is softer than the university brand, type, buttons, hairlines, motion and each component, with notes for editors. Every change to a component or token updates it in the same change.</p>
HTML],
    ['Move from /v2/special to /special', <<<'HTML'
<p>The new pages live under /v2 so they can be built beside the old site. Moving them means changing the section's root address in the theme, the page aliases, links written into templates and guide scripts, and adding redirects from every /v2 address. Analytics already label pages by kind rather than address, so reports continue across the move. No date yet.</p>
HTML],
  ]),
]);

$section('Analytics', [
  $text(NULL, <<<'HTML'
<p><strong>Live since Thu 12 Nov 2026.</strong> The site reports to the GA4 property "LSU Libraries" (G-LDZDEE43H3) through the Google Tag Manager container GTM-NWRXN8P4, loaded by Drupal's Google Tag module.</p>
HTML, 'callout'),
  $text('Pages are labeled by what they are', <<<'HTML'
<p>Reports group pages by what they are, not by their address, so they keep working when /v2 is dropped. Every Palefire page's <code>&lt;html&gt;</code> element carries two labels:</p>
<ul>
<li><code>data-pf-section</code>: <em>special</em> (Special Collections) or <em>library</em> (everything else).</li>
<li><code>data-pf-type</code>: the kind of page, such as search, guide, exhibition, landing, location, staff-profile or not-found.</li>
</ul>
<p>Both go to GA4 as <code>pf_section</code> and <code>pf_type</code>, with matching custom dimensions: Page section, Page type and Search mode.</p>
HTML),
  $text('Events', <<<'HTML'
<ul>
<li><strong>Page views between pages:</strong> Palefire moves between pages without a full reload, so it sends a <code>pf_page_view</code> event once each new page is in. GA4's own history-based page views are turned off, so nothing is counted twice.</li>
<li><strong>Searches:</strong> each new Combined Search sends <code>pf_search</code> with the words searched (email addresses removed) and the mode. Filters, sorting, paging, Back and reloads don't count again.</li>
<li><strong>The old site:</strong> its pages open some content in pop-ups that change the address. Since GTM version 11 (Thu 12 Nov), the "Old site - GA4 page_view (history)" tag counts those, on pages without the Palefire labels only.</li>
<li><strong>Controls:</strong> <code>pf_interaction</code> records which tabs, buttons and settings are used in the section tabs, Ask us, reading settings, the phone dock and the building map. Text that visitors type is never sent.</li>
<li><strong>Filters:</strong> <code>pf_search_filter</code> each time a source, filter, year range or sort order is added to a Combined Search, with the mode.</li>
<li><strong>Search outcomes:</strong> <code>pf_search_empty</code> when every source comes back empty, and <code>pf_search_result</code> with the source and position when a result is opened.</li>
<li><strong>Feedback:</strong> <code>pf_search_feedback</code> when someone opens Combined Search's feedback form, and <code>pf_search_feedback_sent</code> when it is sent.</li>
</ul>
HTML),
  $text('Website Analytics page', <<<'HTML'
<p>A staff-only guide page, <a href="/v2/internal/analytics">Website Analytics</a>, shows the last 28 days: page views and visitors with the change since the 28 days before, views by part of the site and kind of page, twelve weeks of views, the most viewed pages, Combined Searches by mode, the most frequent search terms with the mode each was searched in, the filters chosen, searches that found nothing, results opened, the most used controls, and the Combined Search feedback with its latest comments. Figures are fetched from GA4 each night. While /v2 is being tested it counts the new site only, and its figures show only to signed-in staff, even if the page is published by mistake.</p>
HTML),
  $status('Analytics checklist', [
    ['done', 'Page labels', 'data-pf-section and data-pf-type on every Palefire page, kept in step between pages.'],
    ['done', 'Palefire tags', 'Published in GTM version 10, with custom dimensions Page section, Page type and Search mode in GA4.'],
    ['done', 'No double counting', 'GA4\'s history-based page views turned off; Palefire sends its own.'],
    ['done', 'Old site page views', 'GTM version 11 (Thu 12 Nov) counts the old site\'s pop-up pages.'],
    ['done', 'Controls and search outcomes', 'GTM version 12 (Thu 12 Nov) sends controls used, searches that found nothing and results opened.'],
    ['running', 'Filters', 'Combined Search sends each filter chosen (Fri 13 Nov). Waiting on a GTM tag and the Search filter custom dimension in GA4.'],
    ['done', 'Feedback events', 'GTM version 13 (Thu 12 Nov) sends Combined Search feedback opened and sent.'],
    ['done', 'Website Analytics page', 'A staff-only page with the last 28 days, updated each night.'],
    ['todo', 'Search as a key event', 'Star search as a key event once GA4 lists it, a day or so after the first searches arrive.'],
    ['todo', 'Annotation', 'Add a GA4 annotation for Thu 12 Nov 2026, so the change in counting is explained in every report.'],
    ['todo', 'More reports', 'Explorations in GA4 by Page section, Page type and Search mode, for questions the Website Analytics page can\'t answer.'],
  ]),
]);

$section('Decisions', [
  $text(NULL, <<<'HTML'
<ul>
<li><strong>Build in place.</strong> The new pages are built on the live site under /v2, beside the old ones, and reach visitors section by section.</li>
<li><strong>Accessibility is not optional.</strong> WCAG 2.2 AA everywhere: contrast measured for every reading color, visible focus, reduced motion respected, and pages checked at 320, 768, 1024 and 1440 pixels with no sideways scrolling.</li>
<li><strong>A calm palette.</strong> Palefire is the Libraries' coordinated design package, with soft contrast for long reading sessions, Twilight as the default and twelve reading colors to choose from. Purple and gold are kept for meaning. The reasons are in the style guide.</li>
<li><strong>Hairlines.</strong> Every line and border uses the same soft 20% hairline.</li>
<li><strong>Combined Search</strong> is the search page's name for visitors (Index was its working name). Nothing new is named "bento".</li>
<li><strong>No vendor names in labels.</strong> Visitors see Discovery and Catalog, not the products behind them, so the move to Alma changes nothing they see.</li>
<li><strong>Analytics by page kind, not address.</strong> So reports survive the move from /v2.</li>
<li><strong>Configuration by hand.</strong> Drupal settings are changed in the production interface; the theme and its scripts come through the repository.</li>
<li><strong>Scripts as the source of project pages.</strong> Guides like this one are rebuilt by a script, so their history is in the repository.</li>
<li><strong>One guide for copies.</strong> Ordering Copies was merged into How to Use Special Collections.</li>
<li><strong>American spelling</strong> in all theme copy: color, gray, center, catalog.</li>
<li><strong>Desktop booking dock waits</strong> until the location pages are finished.</li>
</ul>
HTML),
]);

$section('Open questions', [
  $text(NULL, <<<'HTML'
<ul>
<li>When does /v2/special become /special, and does the rest of the site follow at the same time or section by section?</li>
<li>When will the Alma sandbox be ready to connect, and what will requesters sign in with once requests move to Alma?</li>
<li>Which design will Combined Search's result rows use?</li>
<li>When do the header, landing and 404 searches move to Combined Search, and when does /bento get its redirect?</li>
<li>Which location pages come next after the Lecture Hall and Quiet Study, and when are they settled enough for the desktop booking dock?</li>
<li>Which reports do staff need first from the new analytics?</li>
<li>Should this guide be published, or stay a staff page?</li>
</ul>
HTML),
]);

$section('Next steps', [
  $text(NULL, <<<'HTML'
<ol>
<li>Star search as a key event in GA4 and add the 12 Nov annotation.</li>
<li>Share Combined Search with testers and read their feedback on the Website Analytics page.</li>
<li>Finish testing Combined Search and move the site's other search forms to it.</li>
<li>Build the next location pages, then the desktop booking dock.</li>
<li>Plan the move from /v2/special to /special: a list of addresses, redirects and the date.</li>
<li>Connect Alma when the sandbox is ready.</li>
</ol>
HTML),
]);

$section('Day by day', [
  $text(NULL, <<<'HTML'
<p>What was done on each working day, newest last. Combined Search and the LDL migration keep fuller logs in their own guides.</p>
HTML),
  $text('Sun Sep 27: The theme starts', <<<'HTML'
<ul>
<li>Started the Palefire theme for Drupal 11, with Font Awesome icons and the current page in the breadcrumb.</li>
</ul>
HTML),
  $text('Tue Oct 13 to Wed Oct 14: Modular pages', <<<'HTML'
<ul>
<li>Added Modular pages: full-width scroll sections with a table of contents beside them, and video in sections.</li>
</ul>
HTML),
  $text('Thu Oct 15: Sections, sidebar and the first building map', <<<'HTML'
<ul>
<li>Sections got full-page backgrounds under a frosted header and sidebar, and dark sections got dark chrome.</li>
<li>Drew the Hill Memorial building map: pick a room in the legend and it lights up on the drawing.</li>
<li>Started the Special Collections landing, with a Visit card showing hours and directions.</li>
</ul>
HTML),
  $text('Fri Oct 16: Header, guides and reading colors', <<<'HTML'
<ul>
<li>New header, logo and drawers for Hours, Search and Settings, and Swiss styling for the events carousel and hero.</li>
<li>Started guide pages, using LibGuides' content model so guides can move across.</li>
<li>Added reading colors and a reader font setting, and versioned the theme's files so updates reach visitors at once.</li>
</ul>
HTML),
  $text('Sat Oct 17 to Sun Oct 18: Phones and Twilight', <<<'HTML'
<ul>
<li>Built the phone header and full-screen menu, with drawers that open over it.</li>
<li>Twilight became the default reading color, and every hover uses the same timing.</li>
<li>Soft hairlines replaced solid lines, the landing hero got its first lead panel, and the tile showcase arrived.</li>
</ul>
HTML),
  $text('Mon Oct 19 to Tue Oct 20: The landing hero', <<<'HTML'
<ul>
<li>The Special Collections hero filled the first screen and drew back like a curtain to reveal the map.</li>
<li>Built the first draft of How to Use Special Collections, with request paths drawn as subway lines, and switched guides to the WYSIWYG text format.</li>
<li>Added the Quick Guide to the landing.</li>
</ul>
HTML),
  $text('Wed Oct 21: Exhibitions and Louisiana Newspapers', <<<'HTML'
<ul>
<li>Built exhibition pages, and rebuilt AVES, Occult Science and Audubon as the first three.</li>
<li>Built the Louisiana Newspapers guide with a clickable parish map and family trees.</li>
<li>Reorganized the README as a reference with a short changelog.</li>
</ul>
HTML),
  $text('Thu Oct 22 to Fri Oct 23: A Swiss homepage', <<<'HTML'
<ul>
<li>The Special Collections footer appears on Special Collections pages, and the Main Library footer elsewhere.</li>
<li>Reworked the hero on a sixteen-column grid with hero banners, this week's hours and the Quick Start as a timetable.</li>
<li>One full-screen viewer for exhibition images and family trees, From the stacks, and the A New Look for LSU Libraries exhibition with before and after pairs.</li>
</ul>
HTML),
  $text('Mon Oct 26: Parking, glossary and Mossville', <<<'HTML'
<ul>
<li>Added parking guides and the Special Collections Glossary, and calmer page transitions.</li>
<li>Built the Mossville History Project exhibition, with clips that play with transcripts.</li>
</ul>
HTML),
  $text('Wed Oct 28 to Thu Oct 29: Gallery openings', <<<'HTML'
<ul>
<li>Audubon's manuscripts as whole pages with transcriptions, exhibition titles in Playfair Display, and the reading rail for the table of contents.</li>
</ul>
HTML),
  $text('Fri Oct 30: Ask us and program pages', <<<'HTML'
<ul>
<li>Added the Ask Special Collections form with its FAQs, sending questions through LibAnswers.</li>
<li>Added program pages for research grants and fellowships, and exhibition readings drawn as book covers.</li>
<li>Switched theme copy to American spelling, and tidied the edit forms for editors.</li>
</ul>
HTML),
  $text('Sat Oct 31: Staff directory, style guide and location pages', <<<'HTML'
<ul>
<li>Built the staff directory at /v2/staff and Who to ask.</li>
<li>Started the Palefire style guide.</li>
<li>Built the first location page, the Lecture Hall, and the Exhibitions page.</li>
<li>Room photos in the building map's tooltips, Written at Hill as a shelf of books, oral history transcripts that light up with the audio, and the 404 page as an empty display case.</li>
</ul>
HTML),
  $text('Sun Nov 1 to Mon Nov 2: Mobile dock and Quiet Study', <<<'HTML'
<ul>
<li>Added the mobile dock on Special Collections pages: hours, the next step and Contents at the bottom of the screen.</li>
<li>Built the Quiet Study page as the second location page, and one button system for the whole site.</li>
<li>Agreed a design for a desktop booking dock, on hold until the location pages are done.</li>
</ul>
HTML),
  $text('Tue Nov 3 to Wed Nov 4: Section tabs and a new hero', <<<'HTML'
<ul>
<li>Section tabs (Research, Study, Ask us) head the Quick Start, and glossary terms can be tapped for a definition in guide text.</li>
<li>Rebuilt the Special Collections hero as a split cover with Highlights, and gave the landing a numbered contents index.</li>
</ul>
HTML),
  $text('Thu Nov 5: Project guides and Combined Search', <<<'HTML'
<ul>
<li>Added guide blocks for flow charts, status boards, figures and code, and built the LDL Quartex Migration guide with them.</li>
<li>Put the first build of the new search page on production. Its daily log continues in the <a href="/node/133022">Combined Search Project</a> guide.</li>
</ul>
HTML),
  $text('Fri Nov 6: Search on the landing', <<<'HTML'
<ul>
<li>The Special Collections hero search opens the new search page, its first link from the site.</li>
<li>Started the Combined Search guide's status board and Day by day log.</li>
</ul>
HTML),
  $text('Mon Nov 9: Header search and the Union Art Gallery', <<<'HTML'
<ul>
<li>The header's Search drawer searches the new page, and the phone menu got search and described sections.</li>
<li>Built the LSU Union Art Gallery exhibition, and reworked the Cream and Lavender reading colors.</li>
</ul>
HTML),
  $text('Tue Nov 10: Combined Search and print', <<<'HTML'
<ul>
<li>The search page took its name, Combined Search.</li>
<li>Printing now gives clean black-on-white pages across the site, and copy prices are shown per item.</li>
</ul>
HTML),
  $text('Wed Nov 11: Crosswalk guide and analytics', <<<'HTML'
<ul>
<li>Built the MODS Field Crosswalk guide, with a wide section option for guides.</li>
<li>Combined Search: About this item, oral history series, and Oral histories as its own source.</li>
<li>Added the analytics labels and events: page section and kind on every page, page views between pages and Combined Search searches.</li>
</ul>
HTML),
  $text('Thu Nov 12: Analytics live, feedback and a staff report', <<<'HTML'
<ul>
<li>Analytics went live: GTM version 10 added the Palefire tags, GA4's own history page views were turned off, and version 11 counts the old site's pop-up pages.</li>
<li>Merged Ordering Copies into How to Use Special Collections.</li>
<li>The Ask us form became a carved sheet with a Send key, and the search buttons were restyled on both searches.</li>
<li>The style guide now explains the palette, and started this project guide.</li>
<li>New favicons: the official LSU letters, gold in dark mode.</li>
<li>Combined Search: a Show more list can be shared or reloaded without losing its results, with a Copy link button, and the first result shows without scrolling. The Search drawer can send the typed words to Discovery or the Catalog.</li>
<li>Combined Search asks "Did you find what you needed?" under the results. An answer opens a feedback form in a drawer, with the search filled in, so visitors keep their results.</li>
<li>Smoother scrolling: the page restyles about half as much while scrolling, and the Written at Hill shelf can be dragged with the mouse.</li>
<li>Analytics now also count controls used, searches that found nothing, results opened and feedback, and a staff-only Website Analytics page shows the last 28 days, updated each night.</li>
</ul>
HTML),
  $text('Fri Nov 13: Analytics catch-up', <<<'HTML'
<ul>
<li>This guide caught up with the afternoon of Nov 12, and the Website Analytics figures now refresh every night.</li>
<li>The Website Analytics page shows the mode of each search (All, Research, Special Collections or Website) beside its words, and Combined Search now sends each filter chosen, for a Filters chosen table on the same page.</li>
<li>Combined Search: each section now ends in a full-width row leading to its full list (Show all Discovery results, with the total and a gold arrow) on the same gray slab as the Open online buttons, easier to find than the small link at the far right it replaces.</li>
<li>Buttons: the gray buttons site-wide (Quick Start Guide and Visit links, account buttons, FAQ buttons, Combined Search's row buttons) now answer hover and focus with a slightly deeper gray and a thin accent line drawn along the bottom, instead of turning fully white or black with the words reversed. Main actions such as Search and Plan a research visit keep the full fill.</li>
<li>Newsletter review page: the round Comment buttons on narrow screens now show the same feedback icon as /v2/search, instead of a blank circle; the close buttons show an X again.</li>
<li>Newsletter review page: the feedback form now says a name is optional, so reviewers can leave notes anonymously.</li>
<li>Newsletter review page: the past issues are now a plain catalog grid, with small flat front pages and no movement, so the draft stays the focus. Review pages now live at /v2/internal/newsletter-review/.</li>
<li>Newsletter review page: the past issues now sit below the draft and the feedback form, the same grid as the newsletters page, so reviewers can compare the new issue with earlier ones. It reads the newsletters page’s list, so there’s only one list to keep up to date.</li>
<li>Newsletters page: on wide screens the list of years now sits under the introduction and stays in view, marking the year you’re looking at. The newest issue in the before-and-after slider now always shows its latest draft. On the newsletter review page the feedback form now stays beside the email on more screens, including 1440-pixel laptops, where it had dropped to a button at the bottom.</li>
<li>Newsletter archive: past issues are now a grid, a row for each year with the year beside it and a list of years at the top to jump to one, instead of a shelf to scroll sideways. On wide screens the page’s title and introduction stay in view in a column of their own.</li>
<li>Newsletter archive now reaches back to February 2018: all 35 newsletters sent since then, each with its front page and send date. The 2013 to 2017 issues can’t be included, because Constant Contact no longer offers a public view of them.</li>
<li>Newsletter archive: past issues now sit on a shelf like Written at Hill, each shown as the front page of the email (masthead, date and lead story) with its issue number and the day it was sent. Drag it or use the arrows; choosing an issue still opens it on the page.</li>
<li>Newsletter archive, LSU Libraries Newsletter (/v2/newsletters, unpublished until Fall 2026 is sent): the five issues sent since August 2025 as a gallery of covers, each opening on the page, and a slider that wipes between the July 2026 issue and the new Fall 2026 look. Editors add an issue by adding a link to its list.</li>
<li>Newsletter review page: an unlisted page per issue (first, Fall 2026) that shows the Constant Contact draft as it is now, with a Comment button beside each story and a feedback form that follows the reader down the page (a sheet on phones). A link ending in ?for= and a name or headline words takes each featured person straight to their own story, marked "Your story". Comments are saved with the draft's version.</li>
<li>Drafted an unpublished page, LSU Research, by the Numbers, that introduces the Libraries’ OpenAlex research dashboard and links to it. Its figures and charts (works each year, open access, top journals, funders and topics, the most cited works) refresh from OpenAlex each night. It is now a dashboard on first load: four headline figures, then one grid of panels with every chart and list, and a short About the data section.</li>
<li>On plain pages the page title now lines up with the sections below it on wide screens.</li>
<li>LSU Research, by the Numbers: the most cited works are now cards set like a journal offprint, with the journal, title, first authors and year, the citation count and the DOI.</li>
<li>LSU Research, by the Numbers now carries what the old dashboard showed: total and average citations, open access and the top publishers year by year, the most published LSU authors, the latest works, and the 20 most cited.</li>
<li>On the same page, pointing at or tapping a band in the open access bars shows its count, and highlights that kind of access everywhere on the page, so nobody has to match shades of color. The most cited works also say whether each is open access.</li>
<li>LSU Research, by the Numbers also estimates the open access publishing fees (APCs) on works led by LSU authors each year, about $9.8 million since 2020, with a note on what the estimate can’t know. The publishers table no longer needs a scroll bar.</li>
<li>Faculty Fellowship page brought up to date from the program's own files: the five 2026 fellows, earlier cohorts with the classes they have taught, and the four-day orientation.</li>
<li>Exhibitions: people with a biography and no images, such as the fellows and grant recipients, now sit side by side in two or three columns on wider screens.</li>
<li>Newsletter review pages no longer show a table of contents: each is one draft to read from top to bottom.</li>
</ul>
HTML),
  $text('Mon Nov 16: Smaller map labels, Ask Us drawer, room pages', <<<'HTML'
<ul>
<li>Building map: the label that opens over a room (floor, access, name, a line about the room and its link) is about half its old size, narrower and with smaller type, so it no longer covers neighboring rooms and the map is easier to keep exploring.</li>
<li>Building map: the room label no longer repeats the room's link (the room itself is the link), and the floor now sits beside the access on one line (Floor 2 | Walk in), so the label is shorter again.</li>
<li>Building map: a small stacked-floors mark before the floor in each room label, the room’s floor lit in gold, like a building directory. It draws one layer for each floor of the building, so a five-floor Middleton map will get five layers without new work.</li>
<li>Building map: the floors mark’s outlines now use the same faint line as the hatch swatches beside them, and the McIlhenny Room’s label text is down to two short sentences, so it no longer covers half the map.</li>
<li>Ask Us drawer: a Main Library | Special Collections switch at the top, starting on the section you are in. Main Library offers Live Chat and email as before. Special Collections now shows the same question slip as the Ask us section, in place of the old AskUs widget, so a question sent from any page reaches Special Collections the same way. The form loads only when that side is shown.</li>
<li>Ask Us drawer: the Main Library's email form is now the same question slip, in place of the plain white AskUs form. It asks what you need help with, and for research help, whether it's for a course and which one, as the old form did.</li>
<li>Room pages now read alike: every section is numbered, and the Reading Room shows its full rules in a House rules section, with just the essentials at the top, so its facts and photos are no longer pushed down. Photos no longer show file names as captions, and a section without a title gets a default heading.</li>
<li>The McIlhenny Room is now marked For classes, not Book online: it isn’t booked by the public, it’s where faculty hold classes with Special Collections. The building map lists it under its own heading.</li>
<li>Building map on room pages: the room you are looking at now has its name in a clean band across the room, instead of a cut-out that looked torn against the room's stripes.</li>
<li>The Lecture Hall page no longer has a Reserve button at the top, like the McIlhenny Room: the request form and the person to ask are in its Book section, after the house rules.</li>
<li>On the Lecture Hall page, the line with the person to ask now sits at the end of House rules, instead of at the top or beside the Reserve button.</li>
<li>Building map on room pages: the room's name now sits on a short plate shaped to the room's floor, instead of a band running the room's whole length.</li>
<li>Building map on room pages: back to the box around the room's name, which now also hides the walls and room edges it overlaps, so no line runs through the name.</li>
<li>Lecture Hall and McIlhenny Room pages: no more opening hours under the title, since neither room is open to walk into. The same spot now says By request, with a link down to how to request it (Request a date, Plan a class). The Reading Room and Quiet Study keep their hours.</li>
<li>Quiet Study page: a Need to know at the top, like the other rooms. It has no requirements, so it lists what isn’t allowed (No food or drink).</li>
<li>New internal PR timeline page: the main public relations events in a vertical timeline. Anyone with the link can leave a note on an item without signing in; notes show on the page only after staff approve them, and readers turn them on with Show notes.</li>
<li>Drafted two unpublished collection pages: the Louisiana and Lower Mississippi Valley Collections (manuscripts, books, photographs, political papers, newspapers and giving) and the Book Collections (every named collection, from the Rare Book Collection to the Bowlus comic books, with links to the Rare Books guide’s subject checklists). The text comes from the fuller 2023 pages, and ten pictures from the Louisiana Digital Library. Once they’re published, a short Collections section, What Hill Holds, lists them on the Special Collections homepage with University Archives and Oral History, and old links to the LLMVC tile land on its row.</li>
<li>Collection pages: every gallery now opens on a full-width picture, as Manuscripts did, with a caption: an early map of Louisiana, the Capitol from the air, Huey Long’s inauguration, a 1909 Baton Rouge newspaper and Natchez’s Main Street on the LLMVC page, and pages from the medieval manuscript facsimiles on Book Collections.</li>
<li>Collection pages no longer link to the old site’s Special Collections pages (Manuscript Subject Guides, Tips for Finding Books, Audubon Day); Combined Search and the new guides cover them.</li>
<li>Exhibition transcripts (Mossville and the other oral history clips): as a clip plays, everything already heard stays gold, not only the phrase being spoken, so the gold fills the transcript like a progress bar. What’s still to come stays gray, and going back in the clip turns the later text gray again.</li>
<li>Drafted an Oral History homepage at /v2/oralhistory, built like the Special Collections one. Its right-hand column opens on the Hill building map with the Oral History room lit; its search looks in the oral histories only; its Quick Start gives three ways in (listen online, order a copy, meet with the Center’s staff); and a new Browse by series lists every series with how many interviews it holds, each opening that series in Combined Search. The videos already chosen for the old draft play as a faint background behind the whole intro, rather than in the right-hand column, which keeps the map. Below come the collection, the Center’s services, the digital exhibitions and the podcast, and who to ask.</li>
<li>Thumbs up and down on the PR timeline’s items and on the newsletter review page’s stories, counted right away; the review page now also shows the notes the team approves, under the draft, behind the same Show notes switch. Sample notes show what it looks like.</li>
<li>A thumb can now be changed: choose the other one to switch, or the same one again to take it back.</li>
<li>Exhibition titles are now in the site’s plain sans type by default, on the title page and in the Exhibitions list. An exhibition keeps the elegant serif only when its editor picks Serif under Exhibition title style.</li>
<li>Drafted nine more oral history exhibitions from the old Oral History site, as unpublished pages like Mossville’s: History of Graphic Design in South Louisiana, Georgetown 272, History of KLSU, Tempests, the New Orleans Jazz &amp; Heritage Festival, Cooperative Extension at LSU, A Lady in the South, Integrating LSU and Saturday Night in Tiger Stadium. Every clip was evened out the same way, in one channel and at one comfortable loudness, so no interview is much quieter or louder than the next.</li>
<li>All exhibition audio, Mossville’s included, is now a little louder (-16 LUFS, the level podcasts use), so it doesn’t sound quiet next to other sites.</li>
<li>The oral history exhibitions, Mossville and the ten new ones, now live under Oral History rather than Special Collections, at addresses named like the old site’s folders: /v2/oralhistory/exhibitions/jf for the Jazz Fest exhibition, gu272 for Georgetown 272, storm for Tempests, and so on. Old addresses forward to the new ones.</li>
<li>On the Oral History homepage, the map now marks the room the way the location pages do, with the gold arrow and a clear plate behind the name; the exhibition cards show their whole picture; and they link to the new exhibition pages. In Combined Search, chosen filters no longer sit over a scrollbar: they wrap, and long series names are shortened.</li>
<li>On the Oral History homepage, the exhibition cards open the new exhibitions and show their whole pictures, and the small map at the top is drawn as on a location page, every room named, with the Oral History room in gold stripes, a clear label and a bobbing arrow. Landing heroes have a new Spotlight box for the warm light beside the film; Oral History has it off.</li>
<li>The oral history exhibition cards use the same pictures as the old Oral History page’s Showcase, so they’re recognizable at a glance. The exhibitions’ own title pages keep their large covers.</li>
<li>The Oral History homepage has tabs under its intro, Listen, Record and Explore, like the old page’s three parts, and its series are shown as tape reels in their boxes, each rolling out when you point at it. Explore shows the online exhibitions across the full width as film stills, each with its title underneath.</li>
<li>On Special Collections and Oral History, the Quick Start’s diamonds light up in gold again, one after another, every time you come back to their tab, not only the first time.</li>
<li>Oral History’s Ask us tab has the same speech-bubbles icon as Special Collections’.</li>
<li>The sliding key on the tab bars now covers the other icons as it moves, like a real key on top of its track.</li>
<li>The tab you choose now lights up just as the key lands on it, like a switch clicking into place.</li>
<li>The Oral History homepage loads faster: the tape recorder drawing behind the Record tab now loads only when you open that tab, and it’s about half as complex as before, with no visible difference.</li>
<li>On Oral History’s Record tab, Workshops, Seminars, Create your own, Collaborate and Consultations now open their details right on the page, like the Special Collections Quick Start, instead of sending you to the old site.</li>
<li>The faded film behind the Oral History intro now sinks into the dark page like ink, its light parts the page’s own color and its dark parts soft shadows, instead of sitting on top as a gray panel.</li>
<li>The Oral History homepage has a podcast section, What Endures, with the newest episode and the five before it, played in the same small player at the foot of the screen as the exhibitions’ clips. The episodes come from the Center’s SoundCloud each night, so a new one appears the next morning with nothing for staff to do, and the podcast picture in the top column is now the newest episode with its own Play button.</li>
<li>The Oral History homepage no longer repeats the whole Hill map and its rooms; its Find us map in the top right now jumps to the hours and directions.</li>
<li>What Endures: the hand-drawn cassette is now the podcast’s title (screen readers still hear “What Endures”), and the episodes line up with the exhibition cards above: the newest episode’s picture in the first column, its text in the next two, the earlier episodes in the last two. The short summaries no longer stop at “Dr.”, and the small Play buttons in the list are easier to see.</li>
<li>What Endures has its own mark: a hand-drawn cassette beside the heading, with the title written on its label in gold marker. It draws itself when you scroll to it, and its reels turn while an episode plays. The tape deck that replaced the episode picture is gone.</li>
<li>Oral History’s exhibition cards: every picture now fills its card, with no empty band above and below.</li>
<li>Oral History’s pages have their own footer, in the same style as the Main Library and Special Collections footers: the Williams Center’s hours at Hill, phone, Ask, and links to listen online, the FAQs and the podcast.</li>
<li>Oral History’s exhibitions are shown in the site’s usual five-across grid of cards again, like every other showcase.</li>
<li>What Endures: while an episode plays, the newest episode’s picture turns into the exhibitions’ reel-to-reel tape deck, labeled What Endures, its reels turning; the picture comes back when the player closes.</li>
<li>Quick Start buttons are plain again (no underline); What Hill Holds’ picture now meets the top of the page’s first screen with no dark band; What Endures has one link to every episode instead of two.</li>
<li>The Pause button for a page’s background film works wherever you click it, icon included.</li>
<li>Oral History’s map: the room’s gold outline no longer runs through its name.</li>
<li>Oral History’s Pause button for the background film works again.</li>
<li>Oral History’s page title is now set as type, without a frame, so it reads as the page’s name rather than as a logo.</li>
<li>Oral History’s map: the room’s name sits on a clean plate inside the room, without cutting into the room next door. “Read the FAQs” now opens the page’s own Ask us tab instead of another website.</li>
<li>The Center’s mark has more room inside its frame and sits in an even field of space, like a picture in a mat.</li>
<li>The Center’s mark on the Oral History homepage now has equal, roomier space above and below it, more on wide screens.</li>
<li>The Oral History homepage opens with the T. Harry Williams Center’s own mark, the name in a white frame as on the Center’s earlier site, in place of the plain page title.</li>
<li>Oral History’s series: after “Show all”, a “Show less” button folds them back to two rows, keeping your place on the page.</li>
<li>Oral History’s series start with two rows and a “Show all 39 series” button, so the exhibitions below aren’t pushed far down the page.</li>
<li>Oral History’s series can be shown as a grid of tape reels, five across like the other sections, or as a plain list of names and counts, with the same Grid / List switch as the rest of the page.</li>
<li>The Oral History homepage’s tabs are now Research, Record and Ask us. Ask us has Oral History’s common questions and a question form that goes straight to the Williams Center’s Director by email. The exhibitions have their own Showcase section under the series, in wide 21:9 frames.</li>
<li>The Oral History exhibitions are listed like a gallery catalog: each picture in a wide frame with its title underneath, instead of dark gradient cards.</li>
<li>Second try: the Oral History film is back to a plain faded film that melts into the page at its foot, with no edge, and the series reels are larger, at most four to a row.</li>
<li>Oral History’s Browse by series is now a grid of tape reels in their boxes, like the audio-only clips in the exhibitions: each box names a series and how many interviews it holds, and the reel rolls out when you point at it.</li>
<li>Exhibition title pages: the Contents button now sits right under the title, before the introduction, so it’s always on the first screen. Where the Scroll cue shows, the Begin button is gone, since the cue does the same job.</li>
<li>Exhibition walls: the Audio only boxes for clips without a picture are now as tall as the photographs beside them.</li>
<li>All the Oral History exhibitions are now labeled Digital Exhibition like Mossville, their cards show each exhibition’s own title photo instead of the poster, and the posters now appear at the end of each exhibition’s last section.</li>
<li>The end of each exhibition now shows all the other exhibitions as a full-width grid of photos, starting with the next one, so you can pick any of them; the links back to Oral History or Special Collections stay underneath.</li>
<li>Moving between exhibitions: the exhibition’s name in the breadcrumb now opens a list of the other exhibitions in its group (Oral History’s, or Special Collections’), and after the last section a Next exhibition panel shows the next one with its picture, then a link back to Oral History or Special Collections (and, for Special Collections, to all exhibitions). Every section also has a Contents button by its title, so you can jump to another section without scrolling back to the top.</li>
<li>All ten new oral history exhibitions now have read-along transcripts like Mossville’s: as a clip plays, the words light up phrase by phrase, and clicking a phrase plays from there.</li>
<li>The last section of an exhibition, when it opens with a large photo (like Mossville’s About the Project), now keeps that photo behind its text all the way down to the footer, instead of fading it out above a plain band.</li>
<li>Mossville’s About the Project now opens with a large photo of the project’s “Come Tell Your Story” sign on an interview table.</li>
<li>Special Collections’ exhibitions section is back to its usual picture cards; the gallery-style list with titles under the pictures is only on the Oral History pages.</li>
<li>On a computer screen, an exhibition section with no introduction keeps its photo full screen, with a softer shadow at the bottom behind the title. The title and the clips now scroll up together, with no pause, and the top of the first row of clips shows under the title from the start.</li>
<li>In an exhibition clip’s panel, Keep listening, Show image and Close are now small icons, like a window’s minimize, maximize and close buttons; point at one (or tab to it) to see its name.</li>
<li>Exhibition clips with a photo now have a Hide image button on the photo: the photo goes away, the clip’s transcript stays open beside the exhibition, and it keeps playing while you scroll. Show image brings the photo back. The little minimize icon is now a Keep listening button next to Close.</li>
<li>Oral history clips keep playing while you browse. A clip without a photo opens as a panel beside the exhibition (a sheet at the bottom on phones), so you can keep scrolling while you listen. Any clip can be minimized to a small player bar at the bottom of the screen; its title opens the clip again right where it is, with the transcript still following along.</li>
<li>Review mode: add ?review to the end of any Palefire page’s address to leave notes and thumbs on it, section by section, with a pin beside each heading. No sign-in. Approved notes show on the page and on a hub at /v2/internal/page-review. The panel also points out that text size and colors can be changed in Settings.</li>
<li>Approved notes now show right away on the PR timeline, the newsletter review and in review mode. Show notes is on to start; turn it off to hide them.</li>
<li>Newsletter review: each story’s notes and thumbs open from a small chip under its Comment button, with Show more when there are more than fit before the next story.</li>
<li>Reviewers who have opened a review link now see a small Review this page button on every Palefire page, so they never need to remember ?review. Nobody else sees it.</li>
<li>Past newsletters on the site no longer have working links, so nobody is sent to outdated pages. The draft under review keeps its links for checking.</li>
<li>The Reading Room page no longer has a booking button at the top: it is a walk-in room.</li>
<li>Room pages: the photos of a room open full screen, the same way exhibition pictures do, with arrows to step through them.</li>
<li>Exhibitions can now have a Timeline gallery: every dated event from the other galleries in one list, in date order and grouped by decade, read top to bottom on phones and computers alike. Each event says which gallery it belongs to and opens it. Entries can carry a date, shown under their heading, and on a wall an event with no picture hangs as a card with its date. Integrating LSU opens with one, like the old listening station’s timeline.</li>
<li>Exhibition timelines: events with only text are no longer clickable. Their few lines are shown right on the timeline, so nothing opens just to show a sentence; events with a picture or clip still open them.</li>
<li>Exhibition walls: the same events now hang beside the pictures as wall labels, with their date, heading and text, instead of date cards that opened a nearly empty window. A link to one scrolls to its label.</li>
<li>Newsletter reviewers now get answers: staff can reply to an approved note and mark it Open, Changed, Kept as is or Waiting on someone. The reply, the status and the drafts it went through show under the note, and resolved notes gather under Resolved, shown open.</li>
<li>Staff have one Feedback list of every note from the newsletter, page and timeline reviews, with filters for type, status and whether it shows publicly, and can show or hide several notes at once with one click.</li>
<li>Staff can reply to a newsletter note right from the review page: a Reply button under each note, seen only by staff, opens the reply form and brings them back to the note.</li>
<li>Newsletter review notes show at a glance on computers: each story’s notes sit open in a margin beside the email, two at a time before Show more, with the feedback form still beside them on a laptop-size screen or larger. Replies are signed Kyle.</li>
</ul>
HTML),
  $text('Tue Nov 17: Live Chat and What are these?', <<<'HTML'
<ul>
<li>Ask Us drawer: Live Chat’s form can be read again. The chat service draws white labels for a dark page, but the drawer showed it on white, so only empty boxes and the Start Chat button could be seen. It now sits on the drawer’s own dark glass, or on a dark panel when a light reading color is chosen.</li>
<li>Combined Search: What are these? works again. It had started opening an empty box. Now it shows a short line under each source saying what it searches, and hides them again; the page remembers the choice. Long filter names in the bar above the results are shortened with an ellipsis, and the filters wrap onto a second line instead of scrolling sideways.</li>
<li>Combined Search: those lines first showed up all together at the top of the Source list, apart from their sources. Each now sits under its own source.</li>
<li>Ask Us drawer: Live Chat now looks like the email forms beside it, with the same gold rule, place and date, cells with fine lines, small capital labels, fields drawn as a single line and a gold Send bar. The forms in the drawer no longer have the words Question slip at the top.</li>
<li>Live Chat also follows light reading colors such as Cream: dark ink on the page’s own paper, instead of a black box.</li>
<li>Combined Search: Databases searches the A–Z Databases list, so subjects count: nutrition finds 18 databases instead of 2. Show all for Research Guides and Databases now stays on the page, filtered to that source, like the other sections.</li>
<li>Combined Search: Place hold opens the catalog in a new tab, so someone placing holds on several items keeps their results open behind it.</li>
<li>Special Collections: in What Hill Holds, pointing at a collection (or tabbing to it) now shows a picture from that collection behind the heading on the left, faded under the page color so the words stay easy to read, the way the map shows a room’s photo.</li>
<li>Special Collections: that picture now fills the whole section, and a short credit for the item (who made it, what it is, when) appears at the bottom left while it shows. Time in the Archive works the same way once its program pages have pictures.</li>
<li>Special Collections: the picture now covers the section from edge to edge on wide screens too. Time in the Archive does the same, with a picture from the collections for each program: Byrne’s colorful Euclid for Teaching, Kircher’s book on music for the Faculty Fellowship, an 1850 map of Louisiana’s roads and steamboat routes for Research Grants, and Viela Spell’s photograph of two young women on a tractor for the Summer Project.</li>
<li>Louisiana and Lower Mississippi Valley Collections: the three photographs in Photographs now hang on a wall, one for each studio (Lytle, Mugnier, Donn Young), whole instead of cropped, each opening full screen.</li>
<li>Special Collections: What Hill Holds and Time in the Archive have more room above and below the words, and the picture now stays while you move around the section, so you can click View record under its credit to see the item on the Louisiana Digital Library.</li>
<li>Special Collections Quick Start Guide: “sign in or register” under Before you visit now opens the request sign-in page in a new tab, where visitors can register as a Guest.</li>
<li>Special Collections, Study tab: the placeholder about the Quiet Study room’s hours is gone. The room keeps the building’s hours, and visitors are asked to pack up 15 minutes before closing.</li>
<li>Special Collections, Written at Hill: each book now takes one column of the same grid as the guide cards above it, five across on a wide screen, so the covers line up under the cards. Next and Previous, and a flick of the shelf, stop with the books back on those columns. The fade at the shelf’s edge is narrower, so it only softens the outer edge of the end books.</li>
<li>Special Collections, Study tab: the facts under the Quiet Study photo now come from the room’s own entry in Building spaces, the same as the room’s page, so both always agree. Seats, Wi-Fi, outlets and lighting show as soon as they are filled in there; until then nothing is shown in their place.</li>
<li>Special Collections, Ask tab: the note about reply times now says we usually reply within two business days.</li>
<li>Special Collections: the search at the top of the page now types out searches to try, like the Special Collections search on the search page, and Ctrl+K (⌘K on a Mac) jumps to it from anywhere on the page.</li>
</ul>
HTML),
  $text('Wed Nov 18: Room page photo filters', <<<'HTML'
<ul>
<li>Room pages: each page can choose how the room’s photo behind the top looks, with a new Photo filter on its Location section. Faded (the default, how every room page looks now) shows it in color, darkened. Black and white darkens it and takes the color out, as room pages looked before Nov 9. No filter shows the photo as it is on dark reading colors, for a photo already picked to be dark enough for white text over it; light reading colors still fade it.</li>
<li>Special Collections: its video now plays behind the title and search, faded under the page color, the way Oral History’s does, and the right-hand column shows the highlights from the start. Any landing page can choose this with a new Video setting: beside in the column (as before), or in the main column faded, in black and white, or with no filter.</li>
<li>Landing pages: the faint gold light on the title side, which stood for light from the video beside it, now turns off by itself when the video plays in the main column.</li>
<li>Phones: when a landing page’s video plays in the main column (Special Collections, Oral History), it now fills the whole screen like a poster, with the title, hours and search at the bottom of the screen. The hours bar at the bottom stays out of the way while the search is on screen, comes up once you scroll past it, and tucks away again when you scroll back to the top.</li>
<li>Phones: tapping Contents at the top of Special Collections or Oral History now opens the list of sections right there; before, the list stayed hidden and the page could jump far down. On every screen, the Log in or register and Contents buttons over the video now blur the picture behind them, so their labels are easier to read.</li>
<li>Phones: on Special Collections and Oral History the highlights are back right under the full-screen video, before the Quick Start Guide.</li>
<li>Phones: those highlights are now a numbered list, like the Quick Start Guide’s steps. Each row shows its number, its category, its title and a small picture, so all five items get a picture and none is hidden. Each row draws in gently as it scrolls into view.</li>
<li>Phones: in that list the blog post now fades back with the others when another item is pointed at or focused, and the Research / Study / Ask Us tabs follow the list more closely.</li>
<li>What Endures has a new cassette for its title: a smoked plastic one with the title printed on it in gold, seen a little from the side so you can see its thickness, its window, screws and the engraved A in relief. It sits centered between two thin lines that fade out to the edges, a little smaller than before, with more room before the episodes. The lines draw outward and the title writes itself when you scroll to it; the reels turn while an episode plays.</li>
<li>The tape deck that appears when an oral history clip has no picture is drawn more accurately. The block in the middle with the counter and the name plate is now a real three-dimensional shape measured from photos of the Tascam machine, so its corners meet and its sides line up with the angle of the deck. Buttons, feet and the meter frames show only the sides you would actually see, and lines where two surfaces meet are no longer drawn twice as bright.</li>
<li>Service lists in a landing page’s Quick Start: when you point at a service with a photo, the photo now fills the whole band behind the list, from edge to edge. Before, it went behind the band instead, so it only peeked out above the tabs and below the panel.</li>
<li>A new drawing joins the cassette and the tape deck: the Sony PCM-D10 field recorder we use for oral histories, drawn as a solid object lying on a table, seen from the end with its two microphone sockets, so its face, side and end all show with their buttons, switches and sockets standing out or set in, its body with sides rolled nearly half round and the screen flat in the middle, the low fins on either side that shelter its two microphone sockets, its two-ring level dial with its numbers sitting in the smooth molded guard that swells out of the side, and its two microphones angled toward each other. It is shown recording, with its level meters moving, its red record light pulsing and its counter ticking, over and over. There is also a plain line version with no shading, drawn like the tape deck, where only the outlines and edges show. The line version now sits on the Oral History page, in the Record your own tab, rising faintly from the bottom of the band behind the services.</li>
<li>Oral History, Record your own tab: rebuilt like the Research tab’s Quick Start Guide. The Williams Center’s services are three ways in the order a newcomer goes, lettered on from Research’s A, B and C: D Learn how (workshops and seminars), E Start a project (create your own or collaborate), F Get help (reach out or book a consultation). A short “Before you record” list now fills the left column, and the recorder drawing rises from the bottom of the band behind them.</li>
<li>The recorder drawing’s screen: the lines across it (the dividers, the level meters, the REC badge) used to break or jump where the screen curves. They now run unbroken over the curve.</li>
<li>Oral History, Record your own tab: the Hours button and the other buttons in that column now frost the drawing behind them, like glass, so they stay easy to read. They do this only where there’s a drawing or photo behind them; over a plain background the effect would look the same but slow scrolling.</li>
<li>What Endures has a new layout. The title is now a normal heading like the other sections, with a short line under it, and the cassette sits tilted at the right, running off the edge. On phones the cassette is centered under the title. The episodes are one numbered list, newest first, with the date, length and a Play button on each line; the newest is in gold and marked Latest.</li>
<li>What Endures: point at an episode and the section fades to that episode’s own photo, in gray behind the text. The cassette’s tape now shows how long the episode is (the longest one fills the reel), and winds from one reel to the other while it plays. The cassette looks like smoked plastic with the photo blurred behind it.</li>
<li>Oral History’s series now sit beside the tape deck on wide screens. The heading, its short introduction and the deck stay in place on the left while you scroll the series on the right, with a thin line running the full height between them. Point at or tab to a series and it goes on the deck: its name, a short description and how many interviews it holds appear under “On the deck” and the reels turn for a moment. Each series card is now a reel seen through a lightly frosted case, fuller for bigger series, with the number of interviews on a faint band across the top; the frost clears when you point at it. On phones and tablets the series stay as they were, without the deck. Eleven series still need a description.</li>
<li>Oral History’s series and their tape deck, a second pass. The deck now winds its tape to the series you point at: a big series fills the right-hand reel, a small one leaves most of the tape on the left. The deck’s column now fits on the screen, so it stays fully in view while you scroll the series, and the thin line between them runs the full length of the series. Instead of showing all 39 at once, the button now says Show more and adds a few rows each time, then Show less.</li>
<li>The tape deck beside Oral History’s series no longer grows and shrinks as you move between series: the caption under it now keeps the same height for every series.</li>
<li>What Endures: point at an episode and the heading area now tells it, with its date, its opening paragraph and who made it, and the photo’s credit appears under the cassette. Every episode is listed now, in a list that scrolls in place under the heading, so the heading stays in view. The cassette blurs the photo behind it more strongly.</li>
<li>Under the tape deck, a series’ description now follows its name closely even when the name fits on one line. The reading rail at the left edge of the page now reads straight down on Oral History: the Series part no longer makes the next part of the rail look already read.</li>
<li>What Endures: the cassette is see-through again, with the photo showing through its smoked plastic and a lighter tape window.</li>
<li>Under Oral History’s series, the Show more button now says how many you are seeing, for example “Showing 12 of 39”. Under the tape deck, the link now says what it opens, for example “View 60 interviews”, instead of “Open series”.</li>
<li>What Endures: the episode list is shorter (about five rows, fewer on a small screen) so the heading and its description stay on screen while you browse. Rows are a little tighter, the photo credit sits under the episode length, and the date column shows just the month.</li>
<li>Ask us forms on Special Collections and Oral History: the words Question slip are gone from the top of the form, as in the Ask Us drawer. The place and today’s date stay.</li>
<li>On the tape deck beside Oral History’s series, only the left reel (the tape being loaded) now changes with the series you point at; the right reel, the deck’s own, stays as it is.</li>
<li>On large screens, the tape deck beside Oral History’s series now stays fully in view until the last row of series has scrolled past, instead of sliding off the top early.</li>
<li>Pointing at a series card now pulls focus to its reel: as the frosted case clears, the name and the number of interviews go soft and faint, like a camera focusing on the reel. Tabbing to a card with the keyboard keeps its name sharp.</li>
<li>Hill Memorial map: when the map shows a room on Floor 2 highlighted (Oral History’s page, a room’s own page), the words Floor 2 are now in the same highlight color, so you can tell at a glance which floor the room is on.</li>
<li>The soft-focus effect on series cards now happens only on wide screens, where the tape deck names the series; on smaller screens, where the card is the only place to read its name, the name stays sharp.</li>
<li>Oral History series cards: the thin line around each reel stays the same soft gray when you point at it; only the frosted case clears.</li>
<li>The tape deck beside Oral History’s series now runs like a real one: when you point at a series it fast-winds (forward or back) to that series’ amount of tape, then plays at normal speed for as long as you stay on the series, and winds down to a stop when you leave.</li>
<li>The tape deck no longer rewinds: pointing at a bigger series, the reels pause while the fuller reel settles in, and winding forward to a smaller one is gentler, keeping the page calm.</li>
<li>Beside the series, the Series heading and its intro now scroll away, so the tape deck and its caption alone stay in view, larger, with the drawing centered in its column.</li>
<li>On a series card, the reel now waits a moment before it turns, so the name softening out of focus comes first and the two don’t happen at once.</li>
<li>What Endures: the cassette’s window is lighter and shows its works: the tape running from each reel round the guide rollers, the wound tape on the reels, a printed scale and a glint on the plastic.</li>
<li>Every series now has a description (the fuller ones from the old series page, and new short and fuller lines for the eleven that had none). The caption under the tape deck shows one when it fits (Insurance reads in full), screen readers hear it on every card, the series’ name lights gold while you’re on it, and the deck’s darkest slots are left clear so they no longer stand out.</li>
<li>Hill Memorial map: a small north arrow now lies on the drawing, in the empty space at the lower right, so you can match the building to campus. North is toward the Reading Room end; the entrance faces east.</li>
<li>Oral History’s series: Show more is now a full-width button across the grid, with how many are showing at its end, and View interviews under the tape deck is a matching button, both in the same style as the site’s other buttons.</li>
<li>What Endures: the heading area is shorter (a slightly smaller cassette, a little less room kept for episode descriptions), so the episode list starts higher, and small laptop screens show more of it.</li>
<li>Oral History’s series: after Show more, a Show less button now sits right beside it, so you can fold the list back at any point, not only once every series is showing.</li>
<li>Oral History’s series: the list view now shows more at a time (twelve beside the tape deck) with a tiny reel at the start of each row, and the Show more and Show less buttons line up with the columns of the cards above them.</li>
<li>What Endures: a longer introduction under the heading (more than 6,000 hours of interviews, Louisiana’s hidden stories), and a Listen on SoundCloud button under the episode list.</li>
<li>Oral History’s series: the thin line between the tape deck and the cards is gone, so the space between them does the separating, and the cards line up with the map below.</li>
<li>What Endures: fixed the few gold dots that showed on the cassette before its handwritten title began to draw.</li>
<li>What Endures: the reels inside the cassette sit a little deeper behind the window, and the episodes are numbered from the first, so the newest has the highest number.</li>
<li>Oral History’s homepage: the Plan your visit section is gone; the Find us highlight and today’s hours under the title already say where and when. Pointing at Find us now shows Hill Memorial’s front under the floor plan, the floors frosted over the photo as on the room pages.</li>
<li>Oral History’s series: the tape deck now fills its column; on shorter screens its bottom fades to a faint trace behind the series name and description instead of the whole drawing shrinking.</li>
<li>Oral History’s series: the tape deck no longer fast-forwards when you move to a smaller series; the tape simply settles to its new amount while the reels keep their calm playing speed.</li>
<li>Oral History: the empty band under the footer is gone. Hidden text in the podcast’s episode list was spilling out of its scrolling box and making the page longer than its content.</li>
<li>What Endures: the tape inside the cassette now has depth: each run of tape is a ribbon seen at the cassette’s angle, and the reels show their sides, so they read as discs of tape.</li>
<li>What Endures: the tape now curves smoothly onto each reel and fades into the reel’s color as it winds on, following the reels as they fill and empty.</li>
<li>What Endures: the cassette and recorder drawings no longer use pure black; their darkest shades are a warm near-black, and the cassette’s two holes are now open.</li>
<li>What Endures: the cassette’s two holes now go right through it, and its reels turn as the tape winds on or off for each episode you point at.</li>
<li>What Endures: while an episode plays, the cassette stays on it; pointing at other episodes no longer changes the tape.</li>
<li>What Endures: the cassette is now drawn like a real one, in real proportions: one tape runs from reel to reel along the bottom past the felt pad, the spools and tape packs are full size and show faintly through the smoked plastic beyond the window, the circles around the reels are gone, and all four holes in the foot now go right through.</li>
<li>What Endures: the cassette’s window is now clear glass: the episode photo shows sharp through it, while the plastic around it stays frosted.</li>
<li>Oral History’s series: the tape deck’s two level meters now light up softly and their needles drift gently while a series plays, then settle back when you move away.</li>
<li>Oral History’s series: the tape deck’s level meters now glow softly from a hidden bulb at the bottom of each window instead of being tinted all over.</li>
<li>The tape deck’s level meters: the needles no longer poke out below their windows, and the round caps they pivot from are quieter.</li>
<li>What Endures: the highlight on a pointed-at episode now starts a little before its number, and the episode photos behind the section show their own color, faded under the page color like the location pages’ photos.</li>
<li>The tape deck now threads the tape properly: it passes over the capstan, and the rubber pinch roller closes on it when a series plays. The series name under the deck is always gold.</li>
<li>Oral History’s series: a small surprise on the tape deck. Its PLAY, PAUSE and STOP keys can be clicked, and PLAY keeps the reels turning after you move away.</li>
<li>What Endures: the episodes now come five to a page instead of in a small scrolling box. Arrows under the list step through the pages, and the episode you point at still shows at the top of the section.</li>
<li>The tape deck is now set up the way archives digitize: the LINE and REPRO switches are pressed in with softly lit lamps, and the record switches are off. Its small labels are also straighter.</li>
<li>The tape deck’s lit lamps no longer show a dark square around them.</li>
<li>The tape deck’s switch lamps now turn off with the meters when it stops.</li>
<li>What Endures: three episodes to a page now, so the list stays short enough to see together with the cassette and the episode’s description.</li>
</ul>
HTML),
  $text('Thu Nov 19: Scholarly Repository full text', <<<'HTML'
<ul>
<li>Combined Search: the Scholarly Repository is searched through the full text of each document again, now that the repository lets our website through its firewall. The site’s own copy of its records still adds abstracts and stands in if the repository doesn’t answer.</li>
</ul>
HTML),
  $text('Thu Nov 19: Oral history locations', <<<'HTML'
<ul>
<li>Combined Search: oral histories searched on their own keep their Show location panels. When the catalog is slow to answer on a first search, the page asks again for the rows it missed instead of leaving them without one.</li>
</ul>
HTML),
  $text('Thu Nov 19: News and Notes newest first', <<<'HTML'
<ul>
<li>Combined Search: News and Notes results start with the newest story, in its own list and in its section under All. Relevance and Oldest first are still in the Sort menu.</li>
</ul>
HTML),
  $text('Thu Nov 19: Recording formats behind the Quick Start', <<<'HTML'
<ul>
<li>Oral History’s Quick Start: a faint hairline drawing of the formats the interviews were recorded on (a tape reel, a cassette, two clear MiniDiscs and the memory cards) now lies behind the foot of the Research panel, under options A, B and C, as the recorder lies behind the Record tab. It has no labels, since the recordings are digitized and not handed out, and it rises gently into place as it scrolls into view unless motion is reduced.</li>
<li>Oral History’s Quick Start: the formats drawing now stands on a corner of a table instead of in a row, on a tidy grid at the table’s angle (the cassette and MiniDiscs side by side, the reel behind them, the cards in front, one even gap between all), larger, rising out of the panel’s bottom right corner and cropped by the band’s edges, so it reads as a background and not as another row of content.</li>
</ul>
HTML),
  $text('Thu Nov 19: Series descriptions editable in Drupal', <<<'HTML'
<ul>
<li>Oral History’s series descriptions (the short line on each card and in the tape deck’s caption, and the fuller one) can now be edited by content editors in Drupal, as terms in an Oral history series vocabulary, one per series, seeded with the current text. Until a term is edited, or if it is cleared, the site keeps showing the text built into the theme. Editors can be assigned to series so they show in My Content.</li>
</ul>
HTML),
  $text('Thu Nov 19: Background video saves battery and data', <<<'HTML'
<ul>
<li>The opening video on landing pages now also pauses while you’re on another browser tab and picks up again when you come back, and the next clip in a playlist only starts downloading once the video has been on screen. It already paused when scrolled out of view.</li>
</ul>
HTML),
  $text('Thu Nov 19: Oral History hero column lists the page', <<<'HTML'
<ul>
<li>The column beside the title on Oral History’s homepage now works as the page’s contents: under the Find us map, numbered rows for the Quick Start, the Series, the exhibitions and What Endures (with the newest episode and its Play button), each jumping down to its section with the same number its heading carries. The map stays up while you move down the list; only the podcast row brings up a picture of its own. Find us now opens directions, since it used to link back to the page itself. A new box on the Landing hero, Contents in the column, switches any hero between this and the usual highlights; Oral History has it ticked.</li>
<li>The page’s Contents menu (in the breadcrumb and beside the search) now lists Browse by series as 02, between the Quick Start and the exhibitions; it used to skip from 01 to 03.</li>
<li>The table of contents on the left no longer has an “Oral History” link that went nowhere (it pointed at the tabs the Quick Start already shows).</li>
</ul>
HTML),
  $text('Thu Nov 19: Tape deck lamps restored', <<<'HTML'
<ul>
<li>Oral History’s tape deck: the lit LINE and REPRO lamps and the sharper small print are back. Two changes saved seconds apart had left them out of the site’s stylesheet.</li>
</ul>
HTML),
  $text('Thu Nov 19: Oral History opening screen on phones', <<<'HTML'
<ul>
<li>On a phone, the dark shading behind the Center’s name is lighter and starts more gradually, so the film shows through instead of sitting behind a gray slab. The small label above the name is now full white so it stays easy to read.</li>
<li>The Center’s name no longer runs from edge to edge of the screen; it has room on the right.</li>
</ul>
HTML),
]);

// End of sections. ---------------------------------------------------------

$block_count = 0;
foreach ($sections as $item) {
  $block_count += count($item['blocks']);
}
$fingerprint = substr(sha1(json_encode([$intro, $sections], JSON_THROW_ON_ERROR)), 0, 12);
$log_prefix = 'palefire_guide_palefire_redesign.php build';
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
  'path' => ['alias' => '/' . ltrim($args['path'] ?? '/v2/guides/palefire-redesign-project', '/')],
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
