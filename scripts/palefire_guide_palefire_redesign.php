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
</ul>
HTML),
]);

$section('Status at a glance', [
  $text(NULL, <<<'HTML'
<p><strong>Status as of Thu 12 Nov 2026:</strong> the Special Collections section at /v2/special is built and live, with its landing, guides, exhibitions, location pages, Ask us form and mobile dock. Combined Search is running at /v2/search and the header's Search drawer uses it, while the site's other search forms still go to the old page. The staff directory is at /v2/staff. The style guide records the palette and components and grows with them. Analytics now label every page by its section and kind, so reports carry over when /v2 is dropped. Still ahead: pointing the remaining searches at Combined Search, the desktop booking dock, the Alma connection, and the move from /v2/special to /special.</p>
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
    ['running', 'Analytics', 'GA4 through Google Tag Manager, with every page labeled by section and kind rather than by address. Live since Thu 12 Nov; key event, annotation and reports to come. See Analytics below.'],
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
</ul>
HTML),
  $status('Analytics checklist', [
    ['done', 'Page labels', 'data-pf-section and data-pf-type on every Palefire page, kept in step between pages.'],
    ['done', 'Palefire tags', 'Published in GTM version 10, with custom dimensions Page section, Page type and Search mode in GA4.'],
    ['done', 'No double counting', 'GA4\'s history-based page views turned off; Palefire sends its own.'],
    ['done', 'Old site page views', 'GTM version 11 (Thu 12 Nov) counts the old site\'s pop-up pages.'],
    ['todo', 'Search as a key event', 'Star search as a key event once GA4 lists it, a day or so after the first searches arrive.'],
    ['todo', 'Annotation', 'Add a GA4 annotation for Thu 12 Nov 2026, so the change in counting is explained in every report.'],
    ['todo', 'Reports', 'Build reports or explorations by Page section and Page type, and searches by Search mode.'],
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
<li>Star search as a key event in GA4, add the 12 Nov annotation, and build the first reports by Page section and Page type.</li>
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
  $text('Thu Nov 12: Analytics live, one guide for copies', <<<'HTML'
<ul>
<li>Analytics went live: GTM version 10 added the Palefire tags, GA4's own history page views were turned off, and version 11 counts the old site's pop-up pages.</li>
<li>Merged Ordering Copies into How to Use Special Collections.</li>
<li>The Ask us form became a carved sheet with a Send key, and the search buttons were restyled on both searches.</li>
<li>The style guide now explains the palette, and started this project guide.</li>
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
