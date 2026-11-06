<?php

/**
 * @file
 * Guide page: "LDL Quartex Migration", the living status of the Louisiana
 * Digital Library's move from Islandora 7 to Quartex, as a Modular page in
 * guide mode. Run palefire_guide_setup.php and
 * palefire_guide_blocks_setup.php first.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_ldl_migration.php
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_ldl_migration.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it reports what it would do and saves nothing.
 *
 * This script is the guide's source, and unlike the other guide scripts it
 * can be run again. Change the text below, pull it to production and run it:
 * - No page with this title: creates one, UNPUBLISHED, owned by Kyle Tanglao
 *   when a Staff node with that name exists.
 * - The page exists: replaces its intro and all its sections in a new
 *   revision. Publishing state, alias, owner and subjects stay as they are,
 *   so a guide published later stays published.
 * - Nothing changed since the last build: saves nothing.
 *
 * Each build records a fingerprint of the content in the revision log. It
 * stops, saving nothing, when:
 * - the current revision wasn't saved by this script, so editing in Drupal
 *   isn't lost by accident. Copy any edit worth keeping into this file, then
 *   run with force=1. Publishing through the edit form counts as an edit;
 * - the guide has a newer draft than its current revision, which a save here
 *   would bypass.
 * Earlier builds stay on the page's Revisions tab.
 *
 * Optional arguments after "--":
 *   apply=1               Save.
 *   force=1               Replace the page even if it was edited in Drupal.
 *   format=<text format>  Text format for the guide's text (default: the
 *                         WYSIWYG format).
 *   path=<alias>          URL alias for a new page (default
 *                         /v2/special/guides/ldl-quartex-migration).
 *
 * Blocks used: Text (tables, Heading 4, numbered steps, callouts), Accordion,
 * Flow chart, Status board, Figures and Code. Text formats drop classes and
 * guide-mode styles give some shapes a meaning, so in Text bodies here:
 * - No bullets inside bullets: they would draw as a decision tree. Use a
 *   Heading 4 and a flat list, or a table.
 * - A table with two columns and no <thead> draws as a price list (thing and
 *   a short bold figure). Every other table has a <thead>.
 * - Long Text bodies are nowdocs, so quotes and dollar signs are safe.
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
foreach ([['guide_stat', 'field_stat_progress'], ['guide_stats', 'field_stats_caption']] as [$bundle, $field]) {
  if (!\Drupal\field\Entity\FieldConfig::loadByName('paragraph', $bundle, $field)) {
    throw new \RuntimeException("'$field' not found. Run palefire_guide_blocks_setup.php first.");
  }
}

$title = 'LDL Quartex Migration';

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
$stats = fn (?string $heading, array $items, string $caption = '') => [
  'kind' => 'stats',
  'caption' => $caption,
  'heading' => $heading,
  'items' => $items,
];
$code = fn (?string $heading, string $text, string $caption = '') => [
  'kind' => 'code',
  'heading' => $heading,
  'text' => $text,
  'caption' => $caption,
];

$intro = '<p>What the export from Islandora 7 to Adam Matthew\'s Quartex is doing, what\'s finished, what we found along the way, and what comes next. LSU is the first institution done at scale; the other 41 follow the same process.</p>';

// Sections. ----------------------------------------------------------------

$section('Status at a glance', [
  $text(NULL, <<<'HTML'
<p><strong>Status as of Fri 6 Nov 2026, 11:45 (CST):</strong> Louisiana Newspapers and UNO's refused files are downloading, with Louisiana Newspapers about 15% done by size. Loyola follows it. The short list of duplicates is waiting for approval.</p>
HTML, 'callout'),
  $stats(NULL, [
    ['60%', 'downloaded, by data size: 1.93 TB on L:, 1.25 TB left', 60],
    ['80%', 'downloaded, by number of files', 80],
    ['28 of 39', 'institutions with nothing left to download, as of Nov 6, 11:45', 71],
  ], 'Newspapers make up most of what\'s left: Louisiana Newspapers is about 1.01 TB of the 1.25 TB (81%), and Loyola is about 0.23 TB, as of Nov 6, 11:45. Uploading to Quartex isn\'t counted.'),
  $text('Recent progress', <<<'HTML'
<ul>
<li><strong>Overnight, Nov 5 to 6:</strong> the 14 small institutions, Law Library and Historic New Orleans finished downloading.</li>
<li><strong>LSU and LSUHSC New Orleans:</strong> no TIFFs or JPEGs left in their queues.</li>
<li><strong>Pages with no usable JP2:</strong> 28 converted directly from their TIFFs.</li>
<li><strong>Newspaper Clippings:</strong> images are on L:, as the team decided.</li>
<li><strong>JPEGs from the first audit:</strong> all 45,929 replaced.</li>
<li><strong>Franklin Parish:</strong> images are on L:, and its 24 were converted on Nov 5.</li>
<li><strong>Overnight:</strong> 17,248 more wrong-format images replaced at four institutions. The last first audits found 8,312 more, queued to be replaced.</li>
</ul>
HTML),
  $text('Progress by institution (Nov 6, 11:45)', <<<'HTML'
<p><strong>The two Downloaded columns count files on L: only,</strong> not whether they passed their checks. Images audited shows how far the checks have got. Replacements, review and manifests aren't included in any column.</p>
<p>Sorted with the least finished first. There are two percentages because they tell different stories: by size is mostly images, and by files counts every file. An institution with a low size figure and a higher files figure has much of its metadata on L:, but few of its images.</p>
<table>
<thead><tr><th scope="col">Institution</th><th scope="col">Downloaded by size</th><th scope="col">Downloaded by files</th><th scope="col">Images audited</th><th scope="col">GB left</th><th scope="col">Files left</th><th scope="col">Status</th></tr></thead>
<tbody>
<tr><td>Loyola University New Orleans</td><td>0%</td><td>47%</td><td>0%</td><td>233.2</td><td>131,276</td><td>In progress</td></tr>
<tr><td>Louisiana Newspapers</td><td>15%</td><td>35%</td><td>17%</td><td>1,014.2</td><td>308,122</td><td>In progress</td></tr>
<tr><td>University of New Orleans</td><td>97%</td><td>98%</td><td>99%</td><td>1.9</td><td>830</td><td>In progress</td></tr>
<tr><td>Louisiana State Archives</td><td>98%</td><td>98%</td><td>97%</td><td>0.6</td><td>19</td><td>In progress</td></tr>
<tr><td>LDL Training</td><td>99%</td><td>83%</td><td>0%</td><td>0</td><td>9</td><td>In progress</td></tr>
<tr><td>Louisiana State University Shreveport</td><td>99%</td><td>98%</td><td>100%</td><td>0.1</td><td>86</td><td>In progress</td></tr>
<tr><td>Teaching American History in Louisiana (TAHIL)</td><td>99%</td><td>98%</td><td>100%</td><td>0</td><td>102</td><td>In progress</td></tr>
<tr><td>Amistad Research Center</td><td>99%</td><td>99%</td><td>99%</td><td>0</td><td>1</td><td>In progress</td></tr>
<tr><td>Law Library of Louisiana</td><td>99%</td><td>99%</td><td>100%</td><td>0</td><td>2</td><td>In progress</td></tr>
<tr><td>The Historic New Orleans Collection</td><td>99%</td><td>99%</td><td>100%</td><td>0.3</td><td>83</td><td>In progress</td></tr>
<tr><td>Webster Parish Library</td><td>99%</td><td>99%</td><td>100%</td><td>0</td><td>1</td><td>In progress</td></tr>
<tr><td>Ascension Parish Library</td><td>100%</td><td>100%</td><td>100%</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>Calcasieu Parish Public Library</td><td>100%</td><td>100%</td><td>100%</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>Centenary Placeholder Collection</td><td>100%</td><td>100%</td><td>—</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>Delgado Community College</td><td>100%</td><td>100%</td><td>100%</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>Franklin Parish Library</td><td>100%</td><td>100%</td><td>—</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>Free People of Color in Louisiana</td><td>100%</td><td>100%</td><td>100%</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>Jeanerette Museum</td><td>100%</td><td>100%</td><td>100%</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>Louisiana Purchase Bicentennial Collection</td><td>100%</td><td>100%</td><td>100%</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>Louisiana State Museum</td><td>100%</td><td>100%</td><td>100%</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>Louisiana State University</td><td>100%</td><td>100%</td><td>100%</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>Louisiana State University Health Sciences Center New Orleans</td><td>100%</td><td>100%</td><td>99%</td><td>0</td><td>0</td><td>In audit</td></tr>
<tr><td>Louisiana State University Health Sciences Center Shreveport</td><td>100%</td><td>100%</td><td>79%</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>Louisiana State University of Alexandria</td><td>100%</td><td>100%</td><td>100%</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>Louisiana Tech University</td><td>100%</td><td>100%</td><td>100%</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>McNeese State University</td><td>100%</td><td>100%</td><td>100%</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>Newcomb Institute</td><td>100%</td><td>100%</td><td>—</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>Nicholls State University</td><td>100%</td><td>100%</td><td>100%</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>Northwestern State University of Louisiana</td><td>100%</td><td>100%</td><td>100%</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>Ouachita Parish Public Library</td><td>100%</td><td>100%</td><td>100%</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>SOWELA Technical Community College</td><td>100%</td><td>100%</td><td>100%</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>Southeastern Louisiana University</td><td>100%</td><td>100%</td><td>100%</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>Southern University and A&amp;M College</td><td>100%</td><td>100%</td><td>100%</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>State Library of Louisiana</td><td>100%</td><td>100%</td><td>100%</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>The Robert Bob Hicks Foundation</td><td>100%</td><td>100%</td><td>100%</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>University of Louisiana Lafayette</td><td>100%</td><td>100%</td><td>100%</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>University of Louisiana Monroe</td><td>100%</td><td>100%</td><td>100%</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>Vermilionville Living History Museum and Folklife Park</td><td>100%</td><td>100%</td><td>100%</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>West Feliciana Parish Library</td><td>100%</td><td>100%</td><td>—</td><td>0</td><td>0</td><td>Done</td></tr>
<tr><td>East Baton Rouge Parish Library</td><td>—</td><td>—</td><td>—</td><td>—</td><td>—</td><td>Excluded: not migrating</td></tr>
<tr><td>The New Orleans Jazz &amp; Heritage Foundation Archive</td><td>—</td><td>—</td><td>—</td><td>—</td><td>—</td><td>Excluded: not migrating</td></tr>
<tr><td>Tulane University Digital Collections</td><td>—</td><td>—</td><td>—</td><td>—</td><td>—</td><td>Excluded: not migrating</td></tr>
</tbody>
</table>
<ul>
<li><strong>Few or no images on L: yet:</strong> Loyola has none, and Louisiana Newspapers is about 15% done by size.</li>
<li><strong>In audit means nothing is left to download, but some images have no check on record yet:</strong> 1 of 38,361 at LSUHSC New Orleans.</li>
<li><strong>LSUHSC Shreveport is Done with Images audited at 79%.</strong> 589 Newspaper Clippings PDFs can't get a checksum check: Fedora has checksums turned off for them, and there's no FITS or S3 hash either, so no audit can finish them. All 589 match Fedora's size and have a PDF start and end marker, so they look complete, but that isn't proof their contents are unchanged.</li>
<li><strong>One Amistad recording is empty in Fedora itself:</strong> <code>amistad-ay:148</code>, "Andrew Young's inserts for manuscript, 1984 August 6, Part 2", original file <code>Dent.Young_8.6.1984_Item_5-02.wav</code>. Fedora returns 0 bytes and has only one version. Its technical record from ingest (Nov 11, 2024) already says size 0, so it was empty when it arrived. Its S3 record lists no datastreams, and the 521-byte MP3 looks like it was made from the empty file. Downloading again can't fix it, and the only likely source is Amistad's own copy. It's being set aside in <code>L:\LDL1-datastreams\_empty_other\</code>, outside the export, and nothing is deleted.</li>
<li><strong>Done means nothing is left to download and every image has a check on record, not that the institution is finished.</strong> Review, replacements and manifests come afterward, and are still to do for 18 institutions. Anything with files left shows 99% at most.</li>
<li><strong>New downloads are checked as they arrive:</strong> size, plus a checksum or a full JP2 decode where one is possible. Text and metadata files have no checksum, so they only get a size check.</li>
<li><strong>Images from earlier exports are checked by the audit.</strong> The first audits are finished, but an image that failed still counts as on L: until it's replaced, including those still in the queue.</li>
<li><strong>Images audited (Nov 6, 11:45):</strong> the share of each institution's main files, on L: or still to download, with a checksum or JP2 decode check on record. It includes checks made as files download. It also counts images that failed, so 100% doesn't mean they all passed. Text and metadata files aren't counted.</li>
<li><strong>Empty text files aren't exported.</strong> Some pages and scanned PDFs have OCR or FULL_TEXT with nothing in it. The exporter no longer saves those, and they count as done. The 2,064 already on L: moved to <code>L:\LDL1-datastreams\_empty_text\</code>, outside the export, on Nov 5, and nothing was deleted. Manifests at the six institutions were rebuilt without them. Another 1,159 found empty since then were never saved. Files left with no size on record may turn out empty too.</li>
<li><strong>This replaces the earlier "about 70% of 5.7 TB".</strong> The new figure compares what's on L: with what's left to export, about 3.25 TB in all. The 5.7 TB is everything in Fedora. The 2.45 TB difference hasn't been fully traced. TIFF masters exported as smaller JP2s are likely a large part of it.</li>
</ul>
HTML),
  $status('Where each stage stands', [
    ['attention', 'Short list of duplicates', '533 decisions are waiting for approval, starting with the two leave-out batches (87 decisions, 7,297 objects). Loyola\'s download is queued ahead of these decisions, so any of its duplicate books marked to leave out will need to be moved off L: by hand.'],
    ['running', 'Files the website refuses', 'The website refused 4,197 main files and 822 text files with a 403. University of New Orleans is now coming from Fedora through the tunnel, as Newspaper Clippings did, with 38 main files and 728 text files left as of Nov 6, 11:45. Historic New Orleans (76 and 0), TAHIL (51 and 51) and LSU Shreveport (43 and 43) come after it.'],
    ['running', 'Remaining downloads', 'About 1.25 TB and 440,000 files as of Nov 6, 11:45: Louisiana Newspapers about 1.01 TB and Loyola about 0.23 TB, plus the files the website refused. Loyola is queued to start after Louisiana Newspapers.'],
    ['running', 'Queued image replacements', 'Nearly all of the 8,313 are done. A few need another try: State Archives 6, Ascension 1, Hicks 1 and Amistad 1. The new audits added Historic New Orleans 183 and Law Library 2.'],
    ['todo', 'TIFFs with no usable JP2', 'About 2,000 across all institutions still need a JP2 converted from the TIFF (at most about 98 GB), most of them in Louisiana Newspapers.'],
    ['todo', 'State Museum images marked Unavailable', '2,230 images, not yet looked into.'],
    ['todo', 'The other 18 institutions: review, replacements, manifests', 'The same pattern as the finished institutions, once their downloads are done.'],
    ['todo', 'Cleanup before handoff', 'The cleanup list is done by hand once the runs touching those files are finished. Manifests are then copied into the handoff package.'],
    ['todo', 'Upload to Quartex', 'Isn\'t counted in the percentages above.'],
    ['done', 'LSU downloads', 'Every remaining file across all LSU collections and datastreams. 0 failures, 0 retries.'],
    ['done', 'LSU image audit', '135,432 files checked. 114,073 match their checksum and no corrupt images were found. 9,879 are TIFFs saved as .jp2.'],
    ['done', 'LSU TIFF replacements', 'All 9,879 pages are finished. 9,877 have their real JP2, and 2 CMYK TIFFs with no JP2 in Fedora were converted directly. The Gumbo page that wouldn\'t save is now a valid JP2 on L:.'],
    ['done', 'Next 7 institutions: downloads, audit, replacements', 'About 59,000 text files downloaded with 0 failures. LSUHSC: all 15,062 TIFFs are now JP2 (5 converted directly). All 8,187 Newspaper Clippings images are on L:, fetched from Fedora.'],
    ['done', 'Next 7 institutions: checksum lists and reconcile', 'Five are clean. LSUHSC New Orleans has 34,243 orphaned pages and 140 inactive objects to ask about.'],
    ['done', 'Sugar Bulletin', 'The pilot collection: every datastream exported and verified, TIFF pages replaced.'],
    ['done', '12 clean institutions: downloads and audit', 'Only about 1,270 small text and RDF files were left to download. Re-audit found 27,993 JPEGs saved as .jp2 here (State Library 17,356, Louisiana Purchase 10,392, Louisiana Tech 243, Free People of Color 2). All are replaced. Northwestern, Southern and LSUHSC Shreveport turned up 1,827 more, also replaced.'],
    ['done', 'Remaining institutions: checksum lists and reconcile', '30 institutions compared with the accounting. 12 are clean. The main finding: 105 Franklin Parish newspaper issues (2,607 pages) aren\'t in the accounting.'],
  ]),
  $text(NULL, <<<'HTML'
<p>Compiled from the exporter's run logs, progress database and dry-run summaries. Figures for the running audit will change. The cleanup list is current as of this writing.</p>
HTML),
]);

$section('Day by day', [
  $text(NULL, <<<'HTML'
<p>What was done on each working day. File counts include replacements and small text files, and a run counts on the day it started, so an overnight run can appear under the day before.</p>
HTML),
  $text('Wed Oct 28: Getting the exporter ready', <<<'HTML'
<ul>
<li>Downloads are now checked against the checksums Fedora already stores.</li>
<li>Added a progress database, so interrupted runs can pick up where they stopped.</li>
</ul>
HTML),
  $text('Thu Oct 29: First pilot', <<<'HTML'
<ul>
<li>Tried the exporter on a small institution, Vermilionville.</li>
<li>Added parallel downloads, the OCR, HOCR, FULL_TEXT, transcript and PDF exports, and dry runs that report what a run would do.</li>
<li>The pilot found TIFFs saved under the .jp2 name, so the audit now detects them. This turned out to be the main issue across the migration.</li>
<li>Started comparing the accounting spreadsheets with Fedora's own records.</li>
</ul>
HTML),
  $text('Fri Oct 30: Sugar Bulletin pilot', <<<'HTML'
<ul>
<li>About 68,000 files saved, mostly for LSU, with Sugar Bulletin finished as the pilot collection.</li>
<li>Added a full check of JP2 images that have no checksum to compare against.</li>
</ul>
HTML),
  $text('Mon Nov 2: LSU at scale', <<<'HTML'
<ul>
<li>About 174,000 files saved: LSU about 114,000 with no failures, and about 59,000 text files for the next seven institutions, also with no failures.</li>
<li>Audited all 135,432 files in LSU's image folders. 114,073 matched their checksums, none were corrupt, and 9,879 were TIFFs saved as .jp2.</li>
<li>Replaced 9,767 of those TIFFs with their real JP2s the same evening. Old copies were kept.</li>
<li>Compared Fedora with the accounting for the other 30 institutions.</li>
<li>Started this status page.</li>
</ul>
HTML),
  $text('Tue Nov 3: LSUHSC and the 12 clean institutions', <<<'HTML'
<ul>
<li>About 56,000 files saved, including replacements.</li>
<li>Replaced 15,057 TIFFs at LSUHSC New Orleans with their JP2s.</li>
<li>Downloaded and audited the 12 clean institutions, which only needed about 1,270 small files.</li>
<li>A re-audit found 45,929 JPEGs labelled as JP2, and we started replacing them.</li>
<li>Accounting rows for objects missing from the spreadsheets can now be written to a file.</li>
</ul>
HTML),
  $text('Wed Nov 4: Conversions, Franklin Parish and first audits', <<<'HTML'
<ul>
<li>About 89,000 files saved, including replacements.</li>
<li>All 45,929 JPEGs from the first audit were replaced, and LSU and LSUHSC New Orleans had no TIFFs or JPEGs left in their queues.</li>
<li>Added a step that makes a lossless JP2 from the TIFF when Fedora has none, and used it on 28 images.</li>
<li>All 8,187 Newspaper Clippings images reached L:, fetched directly from Fedora because the website refuses them.</li>
<li>Franklin Parish's 15,755 images reached L:.</li>
<li>First audits finished at the last institutions and found 8,312 more wrong-format images.</li>
<li>17,248 images were replaced at UL Lafayette, the State Museum, UNO and UL Monroe.</li>
<li>Identified 34,243 orphaned pages at LSUHSC New Orleans, whose parent books are gone from Fedora.</li>
</ul>
HTML),
  $text('Thu Nov 5: Replacements, the short list and steadier runs', <<<'HTML'
<ul>
<li>About 71,000 files saved, including replacements. 351 images were converted from TIFFs, including Franklin Parish's 24.</li>
<li>The PC restarted unexpectedly at 12:26 AM, and Windows recorded a fatal hardware error. The cause hasn't been identified. Nothing was lost, because every run had already finished. The PC stayed off Wi-Fi until someone signed in at 8:23 AM, so a restart during a run would leave that run stopped until someone signs in and restarts it.</li>
<li>Nearly all of the 8,313 queued images were replaced, at 10 institutions.</li>
<li>Duplicates and odd namespaces were grouped into a short list of 533 decisions, and the exporter can now skip approved leave-outs.</li>
<li>Loyola was excluded and then put back after the LDL team confirmed it isn't harvested.</li>
<li>2,064 empty text files were moved out of the export.</li>
<li>Dropped connections slowed the State Archives run, so the exporter now cuts the number of downloads by itself when that happens. It first did so that evening.</li>
<li>That evening, the 14 small institutions finished downloading, apart from files the website refuses.</li>
</ul>
HTML),
  $text('Fri Nov 6: Law Library and Historic New Orleans', <<<'HTML'
<ul>
<li>Law Library finished at 4:55 AM: about 130,650 files, including 78.5 GB of images.</li>
<li>Historic New Orleans finished at 6:25 AM: about 40,290 files, including 144.8 GB of images in 88 minutes. That was the fastest stretch of the migration so far.</li>
<li>Audits found 183 more Historic New Orleans images and 2 Law Library images to replace.</li>
<li>The website refused 4,197 main files and 822 text files, most of them at UNO. UNO's started coming directly from Fedora at 8:09 AM, and the rest follow.</li>
<li>Louisiana Newspapers started downloading at 8:09 AM.</li>
<li>Found that one Amistad recording (<code>amistad-ay:148</code>) has been empty in Fedora since ingest, and that LSUHSC Shreveport's 589 unchecked PDFs have no checksum to check against.</li>
<li>By 11:45 AM, UNO had 38 main files left to come from Fedora, and Louisiana Newspapers was about 15% done by size.</li>
</ul>
HTML),
]);

$section('Scope and systems', [
  $text(NULL, <<<'HTML'
<p>The LDL is moving from Islandora 7 / Fedora 3 to Adam Matthew's Quartex platform. The exporter copies each object's files out of Fedora into a folder layout Quartex can ingest.</p>
HTML),
  $stats(NULL, [
    ['~663k', 'objects in Fedora'],
    ['~5.7 TB', 'of content in Fedora'],
    ['42', 'institutions (3 excluded)'],
  ]),
  $text('Where things live', <<<'HTML'
<ul>
<li><strong>Destination:</strong> <code>L:\LDL1-datastreams\&lt;institution&gt;\&lt;collection&gt;\Data\&lt;datastream&gt;\</code></li>
<li><strong>Accounting CSVs</strong> (Milad's spreadsheets, the list of what should exist): <code>L:\LDL1-accounting\</code></li>
<li><strong>Run reports and logs:</strong> <code>L:\datastreams_downloads\</code></li>
<li><strong>Exporter state</strong> (progress database, Fedora checksum lists, audit trail, manifests): the <code>state\</code> folder on the export PC</li>
<li><strong>L: itself</strong> is a Synology NAS share, <code>\\libsynology1.lsu.edu\ldl2_storage</code></li>
</ul>
HTML),
  $text('The LDL servers', <<<'HTML'
<ul>
<li><strong>Backend (Fedora):</strong> AWS c5.4xlarge (16 vCPU, 32 GB, not burstable). Fedora runs on port 8080, which isn't reachable from outside the server.</li>
<li><strong>Website:</strong> <code>louisianadigitallibrary.org</code>, behind the SafeLine WAF (free edition). The export PC's IP is now on its allow list.</li>
</ul>
HTML),
  $text('What gets exported per object', <<<'HTML'
<table>
<thead><tr><th scope="col">Datastream</th><th scope="col">What it is</th><th scope="col">Notes</th></tr></thead>
<tbody>
<tr><td><code>OBJ</code></td><td>The main file: image, PDF, audio or video</td><td>For TIFF masters, the JP2 derivative is exported instead, because Quartex rejects TIFF</td></tr>
<tr><td><code>MODS</code></td><td>Descriptive metadata</td><td>Many newspaper pages have none in Fedora (see LSU results)</td></tr>
<tr><td><code>RDF</code></td><td>Relationships: collection, parent, page order</td><td>Saved as <code>-RELS-EXT</code></td></tr>
<tr><td><code>OCR</code></td><td>Text read from a page image</td><td>One file per page</td></tr>
<tr><td><code>HOCR</code></td><td>OCR text plus each word's position on the page</td><td>Lets a viewer highlight search hits</td></tr>
<tr><td><code>FULL_TEXT</code></td><td>Text pulled from a PDF object</td><td>Empty or near-empty for scanned PDFs with no text layer, such as sheet music</td></tr>
<tr><td><code>PDF</code></td><td>Issue- or book-level PDF</td><td>Page objects aren't expected to have one</td></tr>
<tr><td><code>PDF-TRANSCRIPT</code></td><td>Oral history transcripts</td><td></td></tr>
</tbody>
</table>
<p>Not exported: <code>PROXY_MP3</code>, <code>TN</code>, <code>TECHMD</code> and <code>S3_MANIFEST</code>. These are derivatives or Islandora-specific. <code>sp_remote_resource</code> objects (about 42k, mostly in the excluded institutions) have an OBJ that's just a link to content hosted elsewhere.</p>
HTML),
]);

$section('How the export works', [
  $text(NULL, <<<'HTML'
<p>Each run works through numbered steps, per institution and per datastream. The accounting tree on L: is the source of truth for which collections should exist.</p>
HTML),
  $flow('The steps as a chart', [
    $step('0-checksums', note: 'Fedora server scans its own records', if: 'writes', then: 'Fedora checksum list', then_note: 'Size and checksum info per file, read by every later step so files aren\'t looked up one at a time.'),
    $step('0-reconcile', note: 'Accounting CSVs vs. Fedora'),
    $step('Dry run', note: 'Plans the downloads, writes nothing'),
    $step('1: Download', note: 'Only files not already on L:', if: 'failed', then: 'Retry queue', then_note: 'not_downloaded.csv', tone: 'attention'),
    $step('2-full: Find what\'s missing', note: 'Accounting vs. what\'s on L:', if: 'still missing', then: 'Retry queue', then_note: 'not_downloaded.csv', tone: 'attention'),
    $step('5-fedora: Audit', note: 'Checks every file on L: (next section)', link: 'summary', if: 'flagged', then: 'Retry queue', then_note: 'not_downloaded.csv', tone: 'attention'),
    $step('Review', note: 'A person reads the audit summary', kind: 'person', link: 'go-ahead'),
    $step('3: Retry and replace', note: 'Old copies moved to _replaced', if: 'reads', then: 'Retry queue', then_note: 'Entries clear as files succeed.', tone: 'attention'),
    $step('6-manifests', note: 'Fixity lists for the handoff'),
  ], 'Three steps add to one retry queue, and only step 3 reads it. The audit adds its flagged files at the end of each institution, so step 3 runs after the audit has finished and someone has reviewed it.'),
  $text('The steps, in the order they run', <<<'HTML'
<p>The large numbers below are the running order. The names in bold are what the exporter calls each step, which is why step 5-fedora comes before step 3.</p>
<ol>
<li><strong>0-checksums: build the Fedora checksum list.</strong> A script on the Fedora server reads every object's record once. For each datastream it records size, checksum info and state, plus each object's content models and parents. One CSV per institution comes back to the PC (LSU's has 753,414 rows). Every later step uses this list instead of asking the server about each file.</li>
<li><strong>0-reconcile: compare accounting with Fedora.</strong> Flags objects only Fedora has, objects only the spreadsheet has, wrong file types, wrong content models, collection mismatches, inactive or deleted objects, and orphans whose parent was purged. Objects only Fedora has can be exported with <code>--include-fedora-only</code>.</li>
<li><strong>Dry run: plan the downloads.</strong> <code>--dry-run</code> plans step 1 from the accounting CSVs, the files on L: and the checksum list. It makes no requests and writes nothing to L:. Every institution gets one before its real run.</li>
<li><strong>1: download what's missing.</strong> Files already on L: are skipped without contacting the server. Each new file is downloaded to a temporary name and checked: size, checksum where one exists, and a full decode for JP2s with no checksum. Only then is it saved under its real name, so a partial or corrupt file never appears on L:.</li>
<li><strong>2-full: find what's still missing.</strong> Compares the accounting rows with what's now on L:. The Fedora list says whether each missing datastream really exists, and anything missing for real is queued for step 3.</li>
<li><strong>5-fedora: audit what's on L:.</strong> Reads every file already on L: and checks it against Fedora's records. This catches problems from the earlier exports, which were never verified. Details in the next section.</li>
<li><strong>Review: a person reads the audit summary.</strong> Step 3 waits for their go-ahead.</li>
<li><strong>3: retry and replace.</strong> Re-downloads failures and anything the audit flagged. An existing file being replaced is moved into a dated <code>_replaced</code> folder, never deleted.</li>
<li><strong>6-manifests: write fixity manifests.</strong> Per collection: <code>manifest-md5.txt</code> for files with a verified checksum, and <code>unverified.txt</code> for files that could only be size-checked. These are kept in <code>state\manifests</code> for the handoff.</li>
</ol>
HTML),
]);

$section('How files are verified', [
  $text(NULL, <<<'HTML'
<p>Fedora's own checksums are switched off on the LDL, so the exporter checks each file against whatever other evidence exists, and records which source it used.</p>
HTML),
  $text('Checksum sources', <<<'HTML'
<ul>
<li><strong>FITS MD5:</strong> recorded by Islandora's technical metadata when the file was ingested. Most images have one.</li>
<li><strong>S3 ETag:</strong> the checksum of a backup copy in Amazon S3, where one exists. In Sugar Bulletin, only the TIFF pages had one.</li>
<li><strong>Fedora's checksum:</strong> used whenever present, but disabled on this server, so usually unavailable.</li>
<li><strong>Size only:</strong> MODS, RDF and the text datastreams have no checksum anywhere. They're checked against Fedora's recorded size, which catches empty, truncated or wrong files.</li>
<li><strong>JP2 decode:</strong> a JP2 generated from a TIFF never got its own checksum. The exporter checks its structure and decodes the whole image instead, aiming at the broken JP2s the migration team saw in Quartex.</li>
</ul>
<p>When a file can be checked against both FITS and S3, both are compared. A file that matches one but not the other is reported as <em>"Match, but sources disagree"</em>, for review.</p>
HTML),
  $flow('What the image audit does, file by file', [
    $step('Each file in the OBJ folders', kind: 'start'),
    $step('Is it a .jp2 that isn\'t Fedora\'s JP2 size, and do its first 4 bytes say TIFF?', kind: 'question', link: 'no', group: 'Settled quickly, without a full read', if: 'yes', then: 'TIFF saved as .jp2', then_note: '4 bytes read. Real JP2 queued.', tone: 'attention'),
    $step('Did it pass before, unchanged (size + date), checked against every source?', kind: 'question', link: 'no', if: 'yes', then: 'Match (already verified)', then_note: 'Not read again.', tone: 'good'),
    $step('Is it an -OBJ.pdf that is really the object\'s PDF datastream?', kind: 'question', link: 'no', group: 'Sent to the 16 readers, in parallel', if: 'yes', then: 'Wrong datastream', then_note: 'Not hashed. Listed for cleanup.'),
    $step('Is its size different from what Fedora records?', kind: 'question', link: 'no', if: 'yes', then: 'Size mismatch', then_note: 'Not hashed. Queued.', tone: 'attention'),
    $step('Is there a checksum on record (FITS MD5 or S3 ETag)?', kind: 'question', link: 'no', if: 'yes', then: 'Match, or Mismatch', then_note: 'Read in full. Mismatch queued.'),
    $step('No checksum: Unavailable', note: '.jp2 files are then decoded in full', if: 'then', then: 'Valid JP2, or Invalid JP2', then_note: 'Read in full. Invalid queued.'),
  ], 'Each file stops at the first yes. The two questions at the top cost almost nothing. The audit\'s time goes on the full reads at the bottom, mostly the large good images. The TIFF question comes first, so a TIFF that matched a checksum in an earlier pass is still caught.'),
  $text(NULL, <<<'HTML'
<ul>
<li><strong>Wrong format (TIFF):</strong> a <code>.jp2</code> whose size isn't Fedora's JP2 size has its first 4 bytes read. TIFF signatures (<code>II*</code> or <code>MM *</code>) mark it as a TIFF master saved under a JP2 name, and the real JP2 is queued.</li>
<li><strong>Already verified:</strong> an image that passed before, hasn't changed (same size and date), and was compared against every source now available is skipped without being read.</li>
<li><strong>Not OBJ (PDF datastream):</strong> an <code>-OBJ.pdf</code> file whose size matches the object's PDF datastream is a leftover from the earlier export. It's left alone and listed for cleanup.</li>
<li><strong>Size mismatch:</strong> a file that isn't the size Fedora records is wrong whatever its checksum would say, so it's queued without being read.</li>
<li><strong>Everything else is read in full and checksummed:</strong> the result is Match, Mismatch, or Unavailable when there's no checksum. Unavailable JP2s are then decoded, and failures become <em>Invalid JP2</em>.</li>
<li><strong>Results:</strong> each file's result is saved as it goes, so an interrupted audit resumes where it stopped. A per-institution report is written, and anything bad is queued for step 3. The audit itself never replaces a file.</li>
</ul>
HTML),
  $text(NULL, <<<'HTML'
<p><strong>Why the audit matters.</strong> Step 1 only checks that a file with the right name exists. About 126,000 LSU images came from earlier exports that were never verified, so without the audit a TIFF or corrupt image with the right name would be handed to Quartex unnoticed.</p>
HTML, 'callout'),
]);

$section('LSU results', [
  $text(NULL, <<<'HTML'
<p>LSU was run as one institution on Nov 2, after Sugar Bulletin was finished as a pilot the week before.</p>
HTML),
  $text('Downloaded on Nov 2', <<<'HTML'
<table>
<thead><tr><th scope="col">Datastream</th><th scope="col">Downloaded</th><th scope="col">Checked on L:</th><th scope="col">Notes</th></tr></thead>
<tbody>
<tr><td>OCR</td><td>48,865</td><td>80,676</td><td>Biggest share from the Daily Reveille</td></tr>
<tr><td>HOCR</td><td>47,071</td><td>78,909</td><td></td></tr>
<tr><td>FULL_TEXT</td><td>4,769</td><td>4,769</td><td>Mostly LSU Music Resources Sheet Music</td></tr>
<tr><td>MODS</td><td>2,562</td><td>124,696</td><td>All Civil War Context and Conflict</td></tr>
<tr><td>PDF</td><td>849</td><td>2,847</td><td>Reveille issues, sugar research materials, others</td></tr>
<tr><td>PDF-TRANSCRIPT</td><td>322</td><td>322</td><td>Oral history series</td></tr>
<tr><td>RDF</td><td>21</td><td>138,381</td><td>All other LSU RDF was already on L:</td></tr>
</tbody>
</table>
<p><strong>0 failures and 0 retries.</strong> Every file downloaded on the first attempt, and step 3 had nothing to retry in any run. The dry run planned 17 more PDFs and 25 more FULL_TEXT files than were downloaded. These are most likely datastreams Fedora turned out not to have, which the exporter records as absent rather than failed. That still needs confirming.</p>
HTML),
  $accordion('Run by run', [
    ['Image audit (finished Nov 2, 15:31)', <<<'HTML'
<p>All 135,432 files in LSU's OBJ folders, 289 GB read in 2 h 27 m.</p>
<ul>
<li><strong>114,073 match</strong> their FITS or S3 checksum: 66,133 checked on Nov 2, plus 47,940 verified before the restart. No file matched one source but not the other.</li>
<li><strong>2,373 have no checksum and decode as valid JP2s.</strong> These are Sugar Bulletin's replaced pages. No corrupt or undecodable images were found anywhere in LSU.</li>
<li><strong>9,879 TIFF masters saved as <code>.jp2</code>,</strong> queued for their real JP2 (by collection, below).</li>
<li><strong>9,046 PDF leftovers saved as <code>-OBJ.pdf</code>:</strong> 9,039 in Sugar Bulletin and 7 elsewhere. They're left alone and listed for cleanup.</li>
<li><strong>61 McKinley High School oral history transcripts saved as <code>-OBJ.pdf</code></strong> next to the real audio. The audit first reported them as size mismatches against the audio. The exporter now recognises them (v2.19), so step 3 won't save the audio under a <code>.pdf</code> name.</li>
<li>Some TIFF objects have no JP2 in Fedora (for example <code>lsu-sc-wriboy:46–50</code>). Step 3 will record them as "JP2 missing" for review.</li>
</ul>
<table>
<caption>TIFF masters saved as .jp2</caption>
<tbody>
<tr><th scope="row">Daily Reveille</th><td>5,132</td></tr>
<tr><th scope="row">Lewis Stirling Papers</th><td>1,607</td></tr>
<tr><th scope="row">Gumbo magazine</th><td>1,339</td></tr>
<tr><th scope="row">Nathaniel P Banks copybook</th><td>385</td></tr>
<tr><th scope="row">Sugar Planters Association</th><td>296</td></tr>
<tr><th scope="row">LLMVC</th><td>257</td></tr>
<tr><th scope="row">Sugar Research Materials</th><td>232</td></tr>
<tr><th scope="row">Minnie C Hunt Diary</th><td>116</td></tr>
<tr><th scope="row">University Archives Print Materials<em>plus the same 103 in the lsu-test-2 copy</em></th><td>103</td></tr>
<tr><th scope="row">Abel J Leblanc</th><td>101</td></tr>
<tr><th scope="row">Great Southern Lumber</th><td>92</td></tr>
</tbody>
</table>
<p>The rest are in 11 smaller collections.</p>
HTML],
    ['TIFF replacements (finished Nov 2, 20:28)', <<<'HTML'
<ul>
<li><strong>9,767 pages replaced</strong> with their real JP2. The TIFFs are kept in <code>L:\LDL1-datastreams\_replaced\2026-11-02\</code>. The re-audit decoded every new JP2, and none are corrupt.</li>
<li><strong>The 61 McKinley transcripts were left alone,</strong> as intended, and dropped from the queue.</li>
<li><strong>2 pages have no JP2 in Fedora:</strong> <code>lsu-sc-reveille:41424</code> and <code>lsu-sc-stirling:3974</code>. They're listed in <code>jp2_missing.csv</code> for review.</li>
<li><strong>103 University Archives Print Materials pages are still TIFFs.</strong> The same objects are also in <code>lsu-test-2</code>, and the exporter's queue kept one row per object, not one per copy, so only the <code>lsu-test-2</code> copy was replaced. Fixed in v2.20, and the re-audit has queued them again.</li>
<li><strong>9 more are still TIFFs:</strong> 7 whose download hit a network error (6 Reveille, 1 Gumbo), plus the 2 with no JP2. One more step 3 run should clear the 103 and the 7.</li>
</ul>
HTML],
    ['Second replacement run (finished Nov 3, 08:35)', <<<'HTML'
<ul>
<li><strong>109 more replaced,</strong> including all 103 University Archives pages. This confirms the v2.20 fix.</li>
<li><strong>3 left:</strong> the 2 pages with no JP2 in Fedora, and <code>lsu-ua-gumbo:925</code>. The website serves that page's JP2 (4.3 MB), but saving it to L: fails with a Windows "invalid argument" write error, every time, across 6 attempts. It needs a look by hand.</li>
<li>LSU's audit now reads: 114,073 match, 12,249 have no checksum and decode as valid JP2s (Sugar Bulletin plus the new replacements), 9,107 <code>-OBJ.pdf</code> leftovers (including the 61 McKinley files), and 3 TIFFs left.</li>
</ul>
HTML],
    ['Sugar Bulletin (pilot, complete)', <<<'HTML'
<ul>
<li>OBJ: 29,465 match FITS. 2,373 TIFF pages saved as <code>.jp2</code> were replaced with real JP2s, and all decode. 0 mismatches.</li>
<li>The 2,373 TIFF pages are the 2017–2023 issues ingested in January 2025, PIDs 31366–33821. They are also exactly the pages with no MODS.</li>
<li>9,039 <code>-OBJ.pdf</code> files in the OBJ folders are PDF datastreams from the earlier export: 8,527 page PDFs and 512 issue PDFs. They're listed for cleanup.</li>
<li>OCR 31,839, HOCR 31,838 and issue PDFs 1,981, all downloaded. Some OCR files are tiny: 28 empty, 859 of 1–10 bytes, 321 of 11–100 bytes.</li>
</ul>
HTML],
    ['Daily Reveille', <<<'HTML'
<ul>
<li>28,833 objects: 26,272 pages and 2,559 issues. 198 GB of images on L:, all with FITS checksums. 5,132 pages are TIFF masters.</li>
<li>5,132 pages have no MODS in Fedora.</li>
<li>L: holds 2,006 RDF files (PIDs 43009–45014: 115 issues dated 2010 and 1,891 pages) for objects that no longer exist in Fedora. On Nov 2 Fedora answered 404 for each of them. Why they were purged is unknown.</li>
</ul>
HTML],
    ['Accounting tree findings', <<<'HTML'
<ul>
<li><strong>Colville River Delta:</strong> two accounting folders differing only by a double space, with the same 2,143 PIDs. The exporter would have downloaded the collection a second time into a new folder. It now treats such folders as one collection.</li>
<li><strong>Test folders:</strong> <code>lsu-test-2</code> is a full copy of LSU University Archives Print Materials Collection (339 PIDs), already exported to L: as duplicates. <code>spk test</code> and <code>Test</code> hold one test object each.</li>
<li>A scan of all institutions found no other test or duplicate folders, apart from a <code>spk test</code> under Tulane, which is excluded.</li>
</ul>
HTML],
  ]),
]);

$section('High-priority institutions', [
  $text('Fedora against the accounting', <<<'HTML'
<p>Fedora's records for the next seven institutions, compared with their accounting spreadsheets (Nov 2, 13:50).</p>
<table>
<thead><tr><th scope="col">Institution</th><th scope="col">Objects in Fedora</th><th scope="col">In accounting</th><th scope="col">Images with FITS MD5</th><th scope="col">Result</th></tr></thead>
<tbody>
<tr><td>Franklin Parish Library</td><td>1</td><td>2</td><td>0 of 0</td><td>The extra row is a placeholder-collection object, which is harmless</td></tr>
<tr><td>Southeastern Louisiana University</td><td>274</td><td>274</td><td>273 of 273</td><td>No issues</td></tr>
<tr><td>Calcasieu Parish Public Library</td><td>404</td><td>404</td><td>403 of 403</td><td>No issues</td></tr>
<tr><td>Nicholls State University</td><td>369</td><td>369</td><td>367 of 367</td><td>No issues</td></tr>
<tr><td>SOWELA Technical Community College</td><td>17</td><td>17</td><td>15 of 15</td><td>No issues</td></tr>
<tr><td>LSU Health Sciences Center New Orleans</td><td>74,619</td><td>40,377</td><td>58,703 of 72,597</td><td>See below</td></tr>
<tr><td>McNeese State University</td><td>6,724</td><td>6,724</td><td>6,503 of 6,720</td><td>No issues</td></tr>
</tbody>
</table>
HTML),
  $text('LSU Health Sciences Center New Orleans', <<<'HTML'
<ul>
<li><strong>34,243 orphaned pages:</strong> pages whose book no longer exists in Fedora. Most belong to a few purged books, such as <code>lsuhsc-lsubk01:807</code> with 1,082 pages. They're not in the accounting, so they won't be exported unless the team asks for them. This is the same pattern as the Reveille's purged 2010 issues, but much larger.</li>
<li><strong>10,930 datastreams with no link in the spreadsheet,</strong> in Aristides Agramonte Yellow Fever and Graduation Programs (MODS and RDF). All are already on L: except one RDF file, so nothing is lost.</li>
<li><strong>140 inactive objects:</strong> 139 in Catalog and Bulletin, and 1 in The STAR. Fedora marks them Inactive, but the accounting still lists them.</li>
<li><strong>About 13,900 images have no FITS checksum.</strong> They'll be checked by size, and JP2s by a full decode.</li>
<li>1 newspaper clipping in the accounting doesn't exist in Fedora.</li>
</ul>
HTML),
  $text('Dry runs: what\'s left to download', <<<'HTML'
<p>Across all seven, about <strong>67,500 files (about 10 GB)</strong>. Nearly all of it is LSUHSC New Orleans:</p>
<ul>
<li><strong>Newspaper Clippings images:</strong> 8,187 (8 GB). The earlier export saved this collection's MODS and RDF but not its images.</li>
<li><strong>OCR and HOCR:</strong> about 29,000 each, across seven collections. None were exported before.</li>
<li><strong>Smaller items:</strong> 98 RDF (83 of them in School of Dentistry), 361 FULL_TEXT and 23 PDFs.</li>
<li><strong>Other institutions:</strong> 749 FULL_TEXT files (Calcasieu, McNeese, Southeastern, Nicholls). Everything else is already on L:.</li>
</ul>
<p>Already on L: and due for the audit: about 38,000 images, 42,000 RDF and 28,000 MODS. None of the inactive objects are in the download list.</p>
HTML),
  $text('Downloads and audit (finished Nov 2, 17:40)', <<<'HTML'
<ul>
<li><strong>Text files: 59,312 downloaded, 0 failures.</strong> OCR 29,107, HOCR 28,973, FULL_TEXT 1,110, RDF 99, PDF 23. There were no PDF-TRANSCRIPTs to fetch.</li>
<li><strong>Images, fully matching:</strong> Southeastern 273, Calcasieu 403, Nicholls 367, SOWELA 15. Franklin Parish has none.</li>
<li><strong>McNeese:</strong> 6,503 match. The other 217 have no checksum and all decode as valid JP2s.</li>
<li><strong>LSUHSC New Orleans:</strong> 15,105 match and 1 has no checksum. <strong>15,062 are TIFFs saved as <code>.jp2</code></strong>, queued for their real JP2 (by collection, below).</li>
<li><strong>2 Agramonte MODS files</strong> differ in size from Fedora's and are queued for a fresh copy. All other MODS, RDF and text files have no checksum in Fedora, which is normal for these datastreams.</li>
<li><strong>Newspaper Clippings images: all 8,187 on L: (Nov 4).</strong> The team confirmed this collection is moving. The website refuses these images (403), so they came from Fedora's own API through the tunnel (<code>--direct-fedora</code>). 1,819 JPEGs were replaced with their JP2, and 2,443 were fetched as JP2 from the start. Every 40th file was sampled (204 files) and all are JP2. The audit shows Match 1,574 and Unavailable 6,620, where Fedora has no checksum for the JP2 derivative.</li>
<li><strong>6 extra Clippings files are orphans.</strong> <code>lsuhsc-ncc:180</code>, <code>182</code>, <code>183</code>, <code>254</code>, <code>255</code> and <code>256</code> were copied on Nov 4 to complete the collection, but their parents (<code>lsuhsc-ncc:181</code> and <code>253</code>) no longer exist in Fedora. They're the same kind of object as the 34,243 orphaned pages, so they're on L: ahead of that decision.</li>
</ul>
<table>
<caption>LSUHSC New Orleans: TIFFs saved as .jp2</caption>
<tbody>
<tr><th scope="row">The STAR</th><td>8,346</td></tr>
<tr><th scope="row">Student newspapers of the School of Medicine</th><td>4,812</td></tr>
<tr><th scope="row">US Army 64th General Hospital</th><td>827</td></tr>
<tr><th scope="row">Aristides Agramonte Yellow Fever</th><td>643</td></tr>
<tr><th scope="row">Graduation Programs</th><td>287</td></tr>
<tr><th scope="row">Robert Simmons Photographs</th><td>147</td></tr>
</tbody>
</table>
HTML),
  $text('TIFF replacements (finished Nov 3, 13:24)', <<<'HTML'
<ul>
<li><strong>15,057 replaced</strong> with their real JP2: The STAR 8,345, Student newspapers 4,811, US Army 64th General Hospital 824, Agramonte 643, Graduation Programs 287, Simmons 147. Old copies are in <code>L:\LDL1-datastreams\_replaced\2026-11-03</code>.</li>
<li><strong>The last 5 were converted directly (Nov 4):</strong> <code>lsuhsc-p15140coll16:1527</code>, <code>lsuhsc-p15140coll52:813</code>, and <code>lsuhsc-p15140coll50:1942</code>, <code>1944</code> and <code>1946</code>. They had no JP2 in Fedora, and the Islandora ingest server that makes them is offline (confirmed Nov 4). The saved audit report still lists them as TIFF because it hasn't been rerun for those collections since.</li>
</ul>
HTML),
]);

$section('JPEGs saved as .jp2', [
  $text(NULL, <<<'HTML'
<p>Overnight re-audit (finished Nov 3, 16:24). These passed earlier audits because they match Fedora's checksum: Fedora's OBJ is itself a JPEG labelled <code>image/jp2</code>. Nothing is damaged.</p>
<ul>
<li><strong>45,929 found in the first audit, all replaced:</strong> State Library 17,356, LSU 11,631, Louisiana Purchase 10,392, McNeese 2,478, LSUHSC New Orleans 1,937, LSUHSC Shreveport 733, Northwestern 568 (every image), Southern 526 (every image), Louisiana Tech 243, Nicholls 63, Free People of Color 2.</li>
<li><strong>Found since, not counted above:</strong> 1,819 in Newspaper Clippings and 10,519 at the State Museum, UNO and UL Lafayette (all replaced), and 1,931 in the last 10 audits (queued).</li>
<li><strong>No JPEGs:</strong> Southeastern, Calcasieu, SOWELA, Webster and LSU Alexandria. Centenary, Newcomb, West Feliciana and Franklin Parish Library have no audited images.</li>
</ul>
HTML),
]);

$section('Remaining institutions', [
  $text('Reconcile', <<<'HTML'
<p>Fedora's records for the other 30 institutions, compared with their accounting spreadsheets (Nov 2, 16:32–19:02). Nothing was downloaded.</p>
<ul>
<li><strong>Clean (Fedora and accounting agree exactly):</strong> Centenary placeholder, Newcomb Institute, West Feliciana Parish Library, LSU Alexandria, Louisiana Tech, Northwestern State, Southern University, Webster Parish Library, LSU Health Sciences Center Shreveport, Louisiana Purchase Bicentennial, State Library of Louisiana, and Free People of Color.</li>
<li><strong>Franklin Parish Newspapers (in Louisiana Newspapers): 105 issues and 2,607 pages are in Fedora but not in the accounting.</strong> They're active and belong to the collection, PIDs <code>louisiananewspapers-franklin:5783–8494</code>. The accounting lists PIDs on both sides of that range, so it's likely a gap in the spreadsheet. This is the only large group of real objects missing.</li>
<li><strong>Orphaned pages</strong> (their book or issue was purged, as with LSUHSC): Loyola 1,242, University of New Orleans 1,182, University of Louisiana Monroe 1,022, Ascension Parish 564, Law Library 406, UL Lafayette 412, Historic New Orleans Collection 231, Louisiana Newspapers 215, Delgado 181, State Archives 104, Jeanerette Museum 100, TAHIL 10, Amistad 4.</li>
<li><strong>Objects in no collection:</strong> 298 large images at University of Louisiana Monroe, and 37 audio files at the Louisiana State Museum. They're active, but have no parent collection, so the accounting doesn't list them.</li>
<li><strong>Inactive objects that the accounting still lists:</strong> Law Library 728, LSU Shreveport 44, and 1–2 each at seven others.</li>
<li><strong>East Carroll Parish Newspapers:</strong> 1,290 objects whose collection or parent differs between Fedora and the accounting, and 192 datastreams with no link in the spreadsheet. These need a closer look before the dry run.</li>
<li>Smaller relation differences: Loyola 12, Delgado 10, Robert Bob Hicks Foundation 7, Law Library 5.</li>
</ul>
HTML),
  $text('Downloads left (Nov 6, 11:45)', <<<'HTML'
<ul>
<li><strong>440,531 files left to download, about 1.25 TB.</strong> OBJ 155,253 (1,087 GB), OCR 140,065, HOCR 135,707 (31 GB), PDF 6,348 (130 GB), FULL_TEXT 1,691, RDF 1,448, MODS 11, PDF-TRANSCRIPT 8.</li>
<li><strong>Two institutions are nearly all of it:</strong> Louisiana Newspapers 308,122 files (1,014 GB) and Loyola 131,276 (233 GB).</li>
<li><strong>The other 37 total about 1,130 files and 3 GB.</strong> The progress table under Status at a glance has each institution.</li>
<li><strong>As of Nov 4, 2,002 TIFF images had no JP2 in Fedora</strong> (Louisiana Newspapers 1,998, Law Library 3, Hicks Foundation 1). They need derivatives generated before they can go to Quartex.</li>
<li><strong>As of Nov 4, 69,597 images already on L: hadn't been audited,</strong> so they may include TIFFs or JPEGs saved as .jp2.</li>
</ul>
HTML),
  $text('The 12 clean institutions: downloads and audit (finished Nov 3, 13:17)', <<<'HTML'
<ul>
<li><strong>Almost everything was already on L:.</strong> The dry runs found no images, OCR, HOCR or PDFs to fetch, only about 1,270 small files: 1,263 FULL_TEXT (1,181 of them LSUHSC Shreveport Newspaper Clippings), 6 RDF and 1 collection MODS.</li>
<li><strong>Replaced:</strong> Louisiana Purchase 10,976 (10,392 JPEGs, 584 TIFFs), State Library 17,392 (17,356 JPEGs, 36 TIFFs), Louisiana Tech 442 (243 JPEGs, 199 TIFFs), Free People of Color 3 (2 JPEGs, 1 TIFF) and LSU Alexandria 1. Their queues are empty apart from the pages below. Originals are in <code>_replaced\2026-11-03</code>.</li>
<li><strong>Converted directly (Nov 4):</strong> Louisiana Tech 12 TIFFs with no JP2 in Fedora, and Webster 6 TIFFs (<code>webster-wplcollection:243–248</code>) whose JP2 in Fedora is empty.</li>
</ul>
<h4>Image audit: issues found</h4>
<p>Everything else matches; Free People of Color alone has 29,972 matches.</p>
<ul>
<li><strong>TIFFs saved as <code>.jp2</code>:</strong> Louisiana Purchase 584, Louisiana Tech 211, State Library 36, Webster Parish 6, LSU Alexandria 1, Free People of Color 1.</li>
<li><strong>JPEGs saved as <code>.jp2</code>:</strong> State Library 17,356, Louisiana Purchase 10,392, Louisiana Tech 243 (all in Camp Ruston Collection), Free People of Color 2. The first audit only caught the 1,232 with no checksum, as invalid JP2s; the rest matched their checksum, so they passed. They're not damaged: Fedora's OBJ itself is a JPEG labelled <code>image/jp2</code>, and the file on L: is that OBJ byte for byte. Each object also has a real JP2 in Fedora.</li>
<li><strong>No issues:</strong> Centenary, Newcomb, West Feliciana, Northwestern State, Southern, LSUHSC Shreveport.</li>
</ul>
HTML),
  $text('First audits of the next four institutions', <<<'HTML'
<p>All 17,270 wrong-format files were on L: and in the accounting (215 GB). None were missing. 17,248 were replaced with JP2s on Nov 4; 22 at UL Lafayette hit network errors and are queued again.</p>
<ul>
<li><strong>State Museum, 23,385 files:</strong> Match 16,189; JPEG 4,957; TIFF 9 (3 already converted); Unavailable 2,230, not yet looked into.</li>
<li><strong>UL Lafayette, 12,039 files:</strong> TIFF 6,275; JPEG 3,761; Match 2,003.</li>
<li><strong>UNO, 13,541 files:</strong> Match 11,437; JPEG 1,801; TIFF 303.</li>
<li><strong>UL Monroe, 3,907 files:</strong> Match 3,740; TIFF 167.</li>
</ul>
HTML),
  $text('First audits of the last 10 institutions (finished Nov 4, 21:30)', <<<'HTML'
<p>8,312 wrong-format images are on L: and queued (6,381 TIFFs, 1,931 JPEGs).</p>
<ul>
<li>Ascension Parish 2,314 TIFFs and 9 JPEGs; Ouachita Parish 1,316 TIFFs; Jeanerette Museum 1,117 TIFFs; Delgado 1,103 TIFFs and 6 JPEGs; State Archives 436 TIFFs; Hicks Foundation 95 TIFFs and 1 invalid JP2.</li>
<li>TAHIL 1,666 JPEGs; LSU Shreveport 250 JPEGs.</li>
<li>About 328 have no usable JP2 in Fedora, 323 of them at Ascension, so they'll be converted from the TIFFs.</li>
<li>Amistad: 3 files are cut short on L: (<code>amistad-pie:36</code>, <code>amistad-sj:119</code>, <code>amistad-sj:268</code>) and <code>amistad-sj:65</code> doesn't match its checksum. All 4 are queued to download again. Vermilionville has nothing queued.</li>
</ul>
HTML),
]);

$section('Accounting spreadsheets', [
  $text(NULL, <<<'HTML'
<p>How the accounting spreadsheets compare with Fedora's own records, from the comparison run on Nov 2 and 3. Differences don't always mean a spreadsheet is wrong.</p>
<ul>
<li><strong>No spreadsheet has been changed.</strong> None of the 663 accounting spreadsheets on L: has changed since Jul 23, 2026, and the exporter never writes to them.</li>
<li><strong>57,737 differences in all,</strong> at 21 of the 38 institutions compared. 41,315 are orphaned objects, 11,122 are files with no link, 3,050 are other objects not listed, 1,324 have a different collection or parent, 923 are inactive or deleted, and 3 aren't in Fedora.</li>
<li><strong>The one large likely gap is Franklin Parish Newspapers:</strong> 2,712 objects. Their rows are written to <code>reports\reconciliation\Louisiana Newspapers-fedora-only.csv</code>, ready to add to a spreadsheet, and the images are already on L:.</li>
<li><strong>Likely cause is a reading of the pattern,</strong> not something confirmed for each object. Each institution's full list is in <code>L:\datastreams_downloads\reports\reconciliation\</code>.</li>
<li><strong>No differences (17):</strong> Calcasieu Parish Public Library, Centenary Placeholder Collection, Free People of Color in Louisiana, Louisiana Purchase Bicentennial Collection, Louisiana State University Health Sciences Center Shreveport, Louisiana State University of Alexandria, Louisiana Tech University, McNeese State University, Newcomb Institute, Nicholls State University, Northwestern State University of Louisiana, SOWELA Technical Community College, Southeastern Louisiana University, Southern University and A&amp;M College, State Library of Louisiana, Webster Parish Library, West Feliciana Parish Library.</li>
</ul>
<table>
<thead><tr><th scope="col">Institution</th><th scope="col">Collection</th><th scope="col">Spreadsheet</th><th scope="col">Difference from Fedora</th><th scope="col">Likely cause</th><th scope="col">Status</th></tr></thead>
<tbody>
<tr><td>Louisiana State University Health Sciences Center New Orleans</td><td>None (no parent collection)</td><td>None</td><td>34,243 orphaned objects, not listed</td><td>Their parent was purged from Fedora, so likely not a spreadsheet error</td><td>Not changed</td></tr>
<tr><td>Louisiana State University Health Sciences Center New Orleans</td><td>Aristides Agramonte Yellow Fever Collection</td><td><code>lsuhsc-lsubk01.csv</code></td><td>9,722 files with no link</td><td>Fedora has the file, but the spreadsheet gives no link to it</td><td>Not changed</td></tr>
<tr><td>Louisiana Newspapers</td><td>Franklin Parish Newspapers</td><td><code>louisiananewspapers-franklin.csv</code></td><td>2,712 in Fedora, not listed</td><td>Likely a gap in the spreadsheet</td><td>Missing rows written to a file; spreadsheet not changed</td></tr>
<tr><td>Louisiana Newspapers</td><td>East Carroll Parish Newspapers</td><td><code>louisiananewspapers-eastbatonrouge.csv</code>, <code>louisiananewspapers-eastcarroll.csv</code></td><td>192 files with no link; 1,290 with a different collection or parent</td><td>Fedora has the file, but the spreadsheet gives no link to it; The spreadsheet and Fedora disagree on where it belongs</td><td>Not changed</td></tr>
<tr><td>Louisiana State University</td><td>None (no parent collection)</td><td>None</td><td>1,399 orphaned objects, not listed; 1 in no collection, not listed</td><td>Their parent was purged from Fedora, so likely not a spreadsheet error; No parent collection in Fedora, so no spreadsheet lists them</td><td>Not changed</td></tr>
<tr><td>University of Louisiana Monroe</td><td>None (no parent collection)</td><td>None</td><td>1,022 orphaned objects, not listed; 298 in no collection, not listed</td><td>Their parent was purged from Fedora, so likely not a spreadsheet error; No parent collection in Fedora, so no spreadsheet lists them</td><td>Not changed</td></tr>
<tr><td>Loyola University New Orleans</td><td>None (no parent collection)</td><td>None</td><td>1,242 orphaned objects, not listed</td><td>Their parent was purged from Fedora, so likely not a spreadsheet error</td><td>Not changed</td></tr>
<tr><td>Louisiana State University Health Sciences Center New Orleans</td><td>Graduation Programs of the LSU Health Sciences Center New Orleans</td><td><code>lsuhsc-p120701coll26.csv</code></td><td>1,208 files with no link</td><td>Fedora has the file, but the spreadsheet gives no link to it</td><td>Not changed</td></tr>
<tr><td>University of New Orleans</td><td>None (no parent collection)</td><td>None</td><td>1,182 orphaned objects, not listed</td><td>Their parent was purged from Fedora, so likely not a spreadsheet error</td><td>Not changed</td></tr>
<tr><td>Law Library of Louisiana</td><td>Original and Unique Documents from the Law Library Collection</td><td><code>lasc-records.csv</code></td><td>721 inactive or deleted, still listed</td><td>Changed in Fedora after the spreadsheet was made, most likely</td><td>Not changed</td></tr>
<tr><td>Ascension Parish Library</td><td>None (no parent collection)</td><td>None</td><td>564 orphaned objects, not listed</td><td>Their parent was purged from Fedora, so likely not a spreadsheet error</td><td>Not changed</td></tr>
<tr><td>University of Louisiana Lafayette</td><td>None (no parent collection)</td><td>None</td><td>412 orphaned objects, not listed</td><td>Their parent was purged from Fedora, so likely not a spreadsheet error</td><td>Not changed</td></tr>
<tr><td>Law Library of Louisiana</td><td>None (no parent collection)</td><td>None</td><td>406 orphaned objects, not listed</td><td>Their parent was purged from Fedora, so likely not a spreadsheet error</td><td>Not changed</td></tr>
<tr><td>The Historic New Orleans Collection</td><td>None (no parent collection)</td><td>None</td><td>231 orphaned objects, not listed</td><td>Their parent was purged from Fedora, so likely not a spreadsheet error</td><td>Not changed</td></tr>
<tr><td>Louisiana Newspapers</td><td>None (no parent collection)</td><td>None</td><td>215 orphaned objects, not listed</td><td>Their parent was purged from Fedora, so likely not a spreadsheet error</td><td>Not changed</td></tr>
<tr><td>Delgado Community College</td><td>None (no parent collection)</td><td>None</td><td>181 orphaned objects, not listed</td><td>Their parent was purged from Fedora, so likely not a spreadsheet error</td><td>Not changed</td></tr>
<tr><td>Louisiana State University Health Sciences Center New Orleans</td><td>LSU Health Sciences Center New Orleans - Catalog and Bulletin</td><td><code>lsuhsc-p15140coll49.csv</code></td><td>139 inactive or deleted, still listed</td><td>Changed in Fedora after the spreadsheet was made, most likely</td><td>Not changed</td></tr>
<tr><td>Louisiana State Archives</td><td>None (no parent collection)</td><td>None</td><td>104 orphaned objects, not listed</td><td>Their parent was purged from Fedora, so likely not a spreadsheet error</td><td>Not changed</td></tr>
<tr><td>Jeanerette Museum</td><td>None (no parent collection)</td><td>None</td><td>100 orphaned objects, not listed</td><td>Their parent was purged from Fedora, so likely not a spreadsheet error</td><td>Not changed</td></tr>
<tr><td>Louisiana State University Shreveport</td><td>Theses and Dissertations</td><td><code>lsus-graddocs.csv</code></td><td>44 inactive or deleted, still listed</td><td>Changed in Fedora after the spreadsheet was made, most likely</td><td>Not changed</td></tr>
<tr><td>Louisiana State Museum</td><td>None (no parent collection)</td><td>None</td><td>37 in no collection, not listed</td><td>No parent collection in Fedora, so no spreadsheet lists them</td><td>Not changed</td></tr>
<tr><td>Loyola University New Orleans</td><td>Lafcadio Hearn Correspondence</td><td><code>loyno-p120701coll9.csv</code></td><td>12 with a different collection or parent</td><td>The spreadsheet and Fedora disagree on where it belongs</td><td>Not changed</td></tr>
<tr><td>Teaching American History in Louisiana (TAHIL)</td><td>None (no parent collection)</td><td>None</td><td>10 orphaned objects, not listed</td><td>Their parent was purged from Fedora, so likely not a spreadsheet error</td><td>Not changed</td></tr>
<tr><td>Law Library of Louisiana</td><td>Publications by and about the Louisiana Supreme Court</td><td><code>lasc-pub.csv</code></td><td>5 with a different collection or parent</td><td>The spreadsheet and Fedora disagree on where it belongs</td><td>Not changed</td></tr>
<tr><td>Law Library of Louisiana</td><td>Louisiana Supreme Court Portrait Collection-- Louisiana Judges Legislators Lawyers and Government Officials</td><td><code>lasc-nonjusticesportraits.csv</code></td><td>5 inactive or deleted, still listed</td><td>Changed in Fedora after the spreadsheet was made, most likely</td><td>Not changed</td></tr>
<tr><td>Amistad Research Center</td><td>None (no parent collection)</td><td>None</td><td>4 orphaned objects, not listed</td><td>Their parent was purged from Fedora, so likely not a spreadsheet error</td><td>Not changed</td></tr>
<tr><td>The Robert Bob Hicks Foundation</td><td>Peter Jan Honigsberg Collection</td><td><code>hicks-hon.csv</code></td><td>3 with a different collection or parent</td><td>The spreadsheet and Fedora disagree on where it belongs</td><td>Not changed</td></tr>
<tr><td>Delgado Community College</td><td>Delgado Publications</td><td><code>dcc-publications.csv</code></td><td>1 with a different collection or parent; 1 inactive or deleted, still listed</td><td>The spreadsheet and Fedora disagree on where it belongs; Changed in Fedora after the spreadsheet was made, most likely</td><td>Not changed</td></tr>
<tr><td>The Robert Bob Hicks Foundation</td><td>The Bogalusa Daily News Collection</td><td><code>hicks-news.csv</code></td><td>2 with a different collection or parent</td><td>The spreadsheet and Fedora disagree on where it belongs</td><td>Not changed</td></tr>
<tr><td>Ascension Parish Library</td><td>Ascension Parish Library Scrapbooks</td><td><code>apl-scrapbooks.csv</code></td><td>1 in Fedora, not listed</td><td>Likely a gap in the spreadsheet</td><td>Not changed</td></tr>
<tr><td>Delgado Community College</td><td>Higgins Industries Site</td><td><code>dcc-Higgins.csv</code></td><td>1 with a different collection or parent</td><td>The spreadsheet and Fedora disagree on where it belongs</td><td>Not changed</td></tr>
<tr><td>Delgado Community College</td><td>The Isaac Delgado Central Trades School and Its Founder</td><td><code>dcc-IsaacDelgadoFounder.csv</code></td><td>1 with a different collection or parent</td><td>The spreadsheet and Fedora disagree on where it belongs</td><td>Not changed</td></tr>
<tr><td>Delgado Community College</td><td>The Delgado Printer Annual</td><td><code>dcc-dccprinterannual.csv</code></td><td>1 with a different collection or parent</td><td>The spreadsheet and Fedora disagree on where it belongs</td><td>Not changed</td></tr>
<tr><td>Delgado Community College</td><td>JJ Deckermann Graduation 1940</td><td><code>dcc-deckermanngrad1940.csv</code></td><td>1 with a different collection or parent</td><td>The spreadsheet and Fedora disagree on where it belongs</td><td>Not changed</td></tr>
<tr><td>Delgado Community College</td><td>Delgado Graduation Pictures</td><td><code>dcc-grad70.csv</code></td><td>1 with a different collection or parent</td><td>The spreadsheet and Fedora disagree on where it belongs</td><td>Not changed</td></tr>
<tr><td>Delgado Community College</td><td>Joseph Quinn Coursework</td><td><code>dcc-josephquinn.csv</code></td><td>1 with a different collection or parent</td><td>The spreadsheet and Fedora disagree on where it belongs</td><td>Not changed</td></tr>
<tr><td>Delgado Community College</td><td>Moss Memorial Library</td><td><code>dcc-mml.csv</code></td><td>1 with a different collection or parent</td><td>The spreadsheet and Fedora disagree on where it belongs</td><td>Not changed</td></tr>
<tr><td>Delgado Community College</td><td>Library Images</td><td><code>dcc-mmlimages.csv</code></td><td>1 inactive or deleted, still listed</td><td>Changed in Fedora after the spreadsheet was made, most likely</td><td>Not changed</td></tr>
<tr><td>Delgado Community College</td><td>Moss Memorial Media Center Expansion 1970</td><td><code>dcc-mmlmcexpansion70.csv</code></td><td>1 with a different collection or parent</td><td>The spreadsheet and Fedora disagree on where it belongs</td><td>Not changed</td></tr>
<tr><td>Delgado Community College</td><td>The Tool Kit_V2</td><td><code>dcc-toolkitdcc.csv</code></td><td>1 with a different collection or parent</td><td>The spreadsheet and Fedora disagree on where it belongs</td><td>Not changed</td></tr>
<tr><td>Franklin Parish Library</td><td>West Feliciana Parish Library Placeholder Collection</td><td><code>wfpl-placeholder.csv</code></td><td>1 listed, not in Fedora</td><td>The spreadsheet lists an object Fedora doesn&#x27;t have</td><td>Not changed</td></tr>
<tr><td>Law Library of Louisiana</td><td>Louisiana Supreme Court Portrait Collection-- Louisiana Supreme Court Justices</td><td><code>lasc-justicesportraits.csv</code></td><td>1 inactive or deleted, still listed</td><td>Changed in Fedora after the spreadsheet was made, most likely</td><td>Not changed</td></tr>
<tr><td>Law Library of Louisiana</td><td>Law Library of Louisiana Publications</td><td><code>lasc-publications.csv</code></td><td>1 inactive or deleted, still listed</td><td>Changed in Fedora after the spreadsheet was made, most likely</td><td>Not changed</td></tr>
<tr><td>Louisiana Newspapers</td><td>Ouachita Parish Newspapers</td><td><code>louisiananewspapers-ouachita.csv</code></td><td>1 in Fedora, not listed</td><td>Likely a gap in the spreadsheet</td><td>Not changed</td></tr>
<tr><td>Louisiana Newspapers</td><td>Tangipahoa Parish Newspapers</td><td><code>louisiananewspapers-tangipahoa.csv</code></td><td>1 inactive or deleted, still listed</td><td>Changed in Fedora after the spreadsheet was made, most likely</td><td>Not changed</td></tr>
<tr><td>Louisiana State Museum</td><td>Records of the French Superior Council 1714-1769</td><td><code>lsm-p15140coll60.csv</code></td><td>1 inactive or deleted, still listed</td><td>Changed in Fedora after the spreadsheet was made, most likely</td><td>Not changed</td></tr>
<tr><td>Louisiana State University Health Sciences Center New Orleans</td><td>LSU Health Sciences Center New Orleans Newspaper Clippings Collection</td><td><code>lsuhsc-ncc.csv</code></td><td>1 listed, not in Fedora</td><td>The spreadsheet lists an object Fedora doesn&#x27;t have</td><td>Not changed</td></tr>
<tr><td>Louisiana State University Health Sciences Center New Orleans</td><td>The STAR- Radiating the Light of Truth on Hansens Disease</td><td><code>lsuhsc-p15140coll52.csv</code></td><td>1 inactive or deleted, still listed</td><td>Changed in Fedora after the spreadsheet was made, most likely</td><td>Not changed</td></tr>
<tr><td>Louisiana State University</td><td>lsu-test-2</td><td><code>lsu-test-2.csv</code>, <code>lsu-ua-p120701coll24.csv</code></td><td>1 listed, not in Fedora</td><td>The spreadsheet lists an object Fedora doesn&#x27;t have</td><td>Not changed</td></tr>
<tr><td>Louisiana State University</td><td>spk test</td><td><code>lsu-tulane-spk.csv</code></td><td>1 inactive or deleted, still listed</td><td>Changed in Fedora after the spreadsheet was made, most likely</td><td>Not changed</td></tr>
<tr><td>Loyola University New Orleans</td><td>Loyola University New Orleans Electronic Theses</td><td><code>loyno-etd.csv</code></td><td>1 inactive or deleted, still listed</td><td>Changed in Fedora after the spreadsheet was made, most likely</td><td>Not changed</td></tr>
<tr><td>Ouachita Parish Public Library</td><td>Ouachita Parish Public Library Archives_V2</td><td><code>oplib-archives.csv</code></td><td>1 inactive or deleted, still listed</td><td>Changed in Fedora after the spreadsheet was made, most likely</td><td>Not changed</td></tr>
<tr><td>Ouachita Parish Public Library</td><td>Fifth District Works Progress Administration Collection</td><td><code>oplib-wpa.csv</code></td><td>1 inactive or deleted, still listed</td><td>Changed in Fedora after the spreadsheet was made, most likely</td><td>Not changed</td></tr>
<tr><td>The Historic New Orleans Collection</td><td>Designing Carnival- Mardi Gras Costume and Float Designs</td><td><code>hnoc-p15140coll61.csv</code></td><td>1 inactive or deleted, still listed</td><td>Changed in Fedora after the spreadsheet was made, most likely</td><td>Not changed</td></tr>
<tr><td>The Robert Bob Hicks Foundation</td><td>Rabbi George Driesen Collection</td><td><code>hicks-driesen.csv</code></td><td>1 with a different collection or parent</td><td>The spreadsheet and Fedora disagree on where it belongs</td><td>Not changed</td></tr>
<tr><td>The Robert Bob Hicks Foundation</td><td>Robert Hicks Foundation placeholder collection</td><td><code>hicks-placeholder.csv</code></td><td>1 inactive or deleted, still listed</td><td>Changed in Fedora after the spreadsheet was made, most likely</td><td>Not changed</td></tr>
<tr><td>The Robert Bob Hicks Foundation</td><td>Richard Sobol Collection</td><td><code>hicks-sobol.csv</code></td><td>1 with a different collection or parent</td><td>The spreadsheet and Fedora disagree on where it belongs</td><td>Not changed</td></tr>
<tr><td>Vermilionville Living History Museum and Folklife Park</td><td>Made by Hand</td><td><code>vville-mbh.csv</code></td><td>1 inactive or deleted, still listed</td><td>Changed in Fedora after the spreadsheet was made, most likely</td><td>Not changed</td></tr>
</tbody>
</table>
HTML),
]);

$section('Duplicates and odd namespaces', [
  $text(NULL, <<<'HTML'
<p>Some objects in Fedora are duplicates or have an unusual namespace, and the export copies them as they are. Nov 5's review list covers all 661,489 active objects. Every row is a candidate for someone to check, and nothing has been removed or renamed.</p>
HTML),
  $text('The case that started this', <<<'HTML'
<p>Winnie found <code>lsu-sc-jjad-10-OBJ.jp2</code> in the Audubon collection, next to <code>lsu-sc-jjadp-10-OBJ.jp2</code>, with a different image. <code>lsu-sc-jjad:10</code> is a real object in Fedora whose namespace is missing the "p", most likely a typo. It's an exact copy of <code>lsu-sc-jjadp:14</code> (Black throated Wax-wing or Bohemian Chatterer): the image and MODS files on L: are identical. It's unrelated to <code>lsu-sc-jjadp:10</code> (Tennessee Swamp Warbler). The file on L: is dated Oct 1, 2025, from an earlier export, but that export didn't cause this: the wrong name is in Fedora itself.</p>
HTML),
  $text('What the review list found', <<<'HTML'
<table>
<thead><tr><th scope="col">Priority</th><th scope="col">Finding</th><th scope="col">Rows</th><th scope="col">What it means</th></tr></thead>
<tbody>
<tr><td>1</td><td>Same file, different namespaces</td><td>166</td><td>40 groups within one institution, with at least two copies in the accounting</td></tr>
<tr><td>1</td><td>Malformed namespace</td><td>114</td><td>In the accounting, with no collection name in the namespace</td></tr>
<tr><td>1</td><td>Namespace differs from its parent</td><td>55</td><td>In the accounting, with no parent in the object's own namespace; many may be fine</td></tr>
<tr><td>2</td><td>Same file at another institution</td><td>186</td><td>May be deliberate shared content</td></tr>
<tr><td>2</td><td>Same file inside one namespace</td><td>16,480</td><td>Not reviewed yet</td></tr>
<tr><td>3</td><td>Unlisted duplicate copy</td><td>17,676</td><td>At most one copy is in the accounting, so the others won't export</td></tr>
<tr><td>3</td><td>Malformed or differing namespace, not in the accounting</td><td>295</td><td>Won't export as things stand</td></tr>
</tbody>
</table>
<ul>
<li><strong>The Audubon mistake looks rare.</strong> Of the 40 groups, only 2 also share a title: <code>lsu-sc-jjad:10</code> / <code>lsu-sc-jjadp:14</code> and <code>state-lhp:8296</code> / <code>state-lwp:862</code>. The second pair could be a deliberate cross-listing. The other 38 have different titles, or no title in the accounting, and 32 of them are at LSUHSC New Orleans.</li>
<li><strong>Malformed namespaces:</strong> <code>lsm-</code> 108 objects, including its own collection object (at least some are in Joseph W Pops Whitesell), and <code>oplib-</code> 6, all in the accounting. <code>apl-</code> 288 pages and <code>lasc-</code> 1 aren't in the accounting. The <code>oplib-</code> images have no duplicates elsewhere, so they aren't redundant copies.</li>
<li><strong>A Tulane parent:</strong> 6 <code>dcc-toolkit</code> objects point to <code>tulane-jrphil-193050:collection</code>, which isn't an active object in Fedora.</li>
</ul>
HTML),
  $text(NULL, <<<'HTML'
<p><strong>How far to trust this.</strong> Duplicates are matched on the MD5 and size Fedora has on record, without opening the files. 25,430 objects with a main file have no recorded MD5, so they couldn't be compared. 923 inactive or deleted objects were left out. Confirm a match before acting on it.</p>
HTML, 'callout'),
  $text('The short list to approve (Nov 5)', <<<'HTML'
<p>The exporter's new triage step groups those rows into <strong>533 decisions</strong>, so a book ingested twice is one line. Nothing has been left out yet. A person still has to approve each batch.</p>
<table>
<thead><tr><th scope="col">Proposal</th><th scope="col">Batch</th><th scope="col">Decisions</th><th scope="col">Objects</th></tr></thead>
<tbody>
<tr><td>Leave out one copy</td><td>Standalone objects: same file, title, parent and MODS</td><td>43</td><td>43</td></tr>
<tr><td>Leave out one copy</td><td>Whole books or issues: every page the same file, book MODS matches</td><td>44</td><td>7,254</td></tr>
<tr><td>Review</td><td>Same file and title, but MODS differs or is missing, or the object has children</td><td>137</td><td>289</td></tr>
<tr><td>Review</td><td>Same file with different titles</td><td>131</td><td>407</td></tr>
<tr><td>Review</td><td>Some pages match between books, but not all</td><td>71</td><td>736</td></tr>
<tr><td>Review</td><td>Same file as both a page and a standalone object</td><td>54</td><td>110</td></tr>
<tr><td>Review</td><td>Same image twice inside one book, possibly a scanning error</td><td>25</td><td>65</td></tr>
<tr><td>Review</td><td>All pages match, but the MODS differs or the copies are in different collections</td><td>14</td><td>158</td></tr>
<tr><td>Review</td><td>Same file at two institutions (State Museum and TAHIL; State Museum and Louisiana Purchase)</td><td>2</td><td>186</td></tr>
<tr><td>Review</td><td>Same title in different places (<code>state-lhp:8296</code> / <code>state-lwp:862</code>)</td><td>1</td><td>2</td></tr>
<tr><td>Keep</td><td>Odd namespace, nothing duplicated</td><td>10</td><td>166</td></tr>
<tr><td>No action</td><td>Not in the accounting, so won't export</td><td>1</td><td>17,971</td></tr>
</tbody>
</table>
<ul>
<li><strong>Loyola is most of the proposed leave-outs:</strong> 41 books, 7,231 of the 7,297 objects, all in the Joseph-Aurelien Cornet FSC Collection. Their sampled page images aren't on L: yet, so those matches rest on Fedora's records and the MODS. If they're approved before Loyola's download, the exporter can now skip them. A rehearsal showed 7,190 fewer images (about 9.4 GB of Loyola's 218 GB of images), and their text and metadata files would be skipped as well. The bigger gain is keeping those duplicates out of Quartex.</li>
<li><strong>Spot check:</strong> in the proposals whose files are on L:, 48 of 48 sampled pairs were identical when the actual files were hashed, including <code>lsu-sc-jjad:10</code> and <code>lsu-sc-jjadp:14</code>.</li>
<li><strong>Not compared:</strong> a book's PDF, OCR and other files. Only the main page images and MODS were checked.</li>
</ul>
HTML),
  $text('Recommendation', <<<'HTML'
<p>Don't rename objects or files. The PID is what links an object back to Fedora and the accounting. Decide what to keep or leave out. Approved objects that aren't on L: yet are never downloaded. Those already on L:, like <code>lsu-sc-jjad:10</code>, stay there and still need removing from the Quartex handoff. The full list is at <code>L:\datastreams_downloads\reports\duplicates\duplicates-and-namespaces.csv</code>. The short list, <code>triage.csv</code>, and its batch summary, <code>triage-batches.csv</code>, are in the same folder.</p>
HTML),
]);

$section('Speed and hardware', [
  $text('What changed', <<<'HTML'
<p>The export is limited by the network path, not by the server or the PC's processor.</p>
<table>
<thead><tr><th scope="col">Area</th><th scope="col">Change</th><th scope="col">Effect</th></tr></thead>
<tbody>
<tr><td>WAF</td><td>SafeLine allow rule for the export PC.</td><td>Before: ~800 files/min, with throttling pauses. After: ~2,000+ files/min.</td></tr>
<tr><td>Step 2</td><td>Folders are listed once instead of every file being checked separately over the share.</td><td>800 files: 21.5 s → 0.18 s. Saved about 2 hours across LSU's datastreams.</td></tr>
<tr><td>Audit</td><td>The image audit reads 16 files at a time instead of one.</td><td>Measured on L: while another job was also reading the share: 1 reader 5 MB/s, 4 → 17, 8 → 20, 16 → 29–30, 32 → 33.</td></tr>
<tr><td>Route</td><td>Website vs. straight from Fedora through an SSH tunnel. The website, with the allow rule, is the default route. The direct-Fedora option stays available.</td><td>Small files take about 0.22 s each on both routes. Large files are much slower through the tunnel (about 150 KB/s per download, versus 6–9 MB/s per download through the website), probably because Windows' SSH forwarding is weak.</td></tr>
<tr><td>HTTPS</td><td>HTTPS now works through the WAF. Plan: switch to <code>--keep-https</code> from the next institution, so downloads are encrypted and restricted objects can authenticate.</td><td>About the same speed as the old http:// workaround.</td></tr>
</tbody>
</table>
HTML),
  $text('Where the bottleneck is', <<<'HTML'
<ul>
<li><strong>Wi-Fi on the export PC</strong> is most likely: a 960 Mbps link, with audit reads levelling off around 30 MB/s. Wired Ethernet is the biggest improvement available.</li>
<li><strong>The Synology NAS</strong> comes next: a shared campus box, probably spinning disks behind a 1 Gbit link.</li>
<li><strong>Not the PC's CPU</strong> (Core Ultra 9, 16 threads, about 10% busy) and <strong>not the server</strong> (c5.4xlarge).</li>
<li>Worker count doesn't need to match CPU cores. Workers spend nearly all their time waiting on the network.</li>
<li>More workers don't add speed on large files. On Nov 5, runs without long pauses stayed near 3 to 4 MB/s at both 3 and 16 workers. State Archives' 50 MB images went from long pauses at 16 and 8 workers to none at 3, and total speed more than doubled. What causes the dropped connections isn't known yet.</li>
<li>Speed varies a lot. Early on Nov 6, Historic New Orleans images came down at about 27.6 MB/s, while Law Library averaged 4.6 MB/s overnight with smaller files, many needing a full decode check. Why the speed changes isn't known yet.</li>
</ul>
HTML),
]);

$section('Exporter changes this phase', [
  $text(NULL, <<<'HTML'
<p>Every change was tested against a simulated Islandora/Fedora before use, across 16 test suites that all pass. Applied is when the change was committed to the exporter, in Central time (2026).</p>
<table>
<thead><tr><th scope="col">Version</th><th scope="col">Applied</th><th scope="col">Change</th></tr></thead>
<tbody>
<tr><td>v2.11</td><td>Oct 30, 09:56</td><td><strong>Reconcile:</strong> model names compared case-insensitively, the Islandora root collection ignored, pages placed through their parent book, orphans whose parent was purged flagged.</td></tr>
<tr><td>v2.12</td><td>Oct 30, 10:57</td><td><strong>FITS and S3 both compared</strong>, with disagreements reported. <code>--collections</code> now limits the audit too. <code>-OBJ.pdf</code> leftovers are recognised instead of being queued for download.</td></tr>
<tr><td>v2.13</td><td>Oct 30, 12:05</td><td><strong>JP2 validation:</strong> a structure check plus a full decode for any JP2 with no checksum, on download and in the audit.</td></tr>
<tr><td>v2.14</td><td>Oct 30, 14:15</td><td><strong>Compressed responses:</strong> the site gzips text, which made every OCR file fail the size check. The size Fedora records is used instead. Nothing bad had been saved.</td></tr>
<tr><td>v2.15</td><td>Nov 2, 08:33</td><td><strong>Accounting folders that differ only in spacing</strong> are treated as one collection (the Colville case).</td></tr>
<tr><td>v2.16</td><td>Nov 2, 09:04</td><td><strong><code>--direct-fedora</code>:</strong> download from Fedora's own API through an SSH tunnel, skipping the WAF and Drupal.</td></tr>
<tr><td>v2.17</td><td>Nov 2, 09:38</td><td><strong>Faster step 2:</strong> one listing per folder instead of a check per file over the share.</td></tr>
<tr><td>v2.18</td><td>Nov 2, 12:54</td><td><strong>Parallel audit:</strong> <code>--workers</code> sets how many files are read at once. The report is identical to a single-reader run.</td></tr>
<tr><td>v2.19</td><td>Nov 2, 15:46</td><td><strong>Oral history transcripts saved as <code>-OBJ.pdf</code></strong> are recognised as leftovers. Step 3 won't replace them with the audio.</td></tr>
<tr><td>v2.20</td><td>Nov 3, 08:17</td><td><strong>Objects in two collections</strong> keep a queue row for each copy, so replacing one copy no longer drops the other from the queue (the <code>lsu-test-2</code> case).</td></tr>
<tr><td>v2.21</td><td>Nov 3, 14:54</td><td><strong>Missing accounting rows as a file:</strong> the reconcile step also writes <code>{institution}-fedora-only.csv</code>, the objects Fedora has but the accounting lacks, in the accounting's own columns. The accounting can be patched from it, and <code>--include-fedora-only</code> downloads the same rows (the Franklin Parish case). The accounting itself is never changed.</td></tr>
<tr><td>v2.22</td><td>Nov 3, 14:54</td><td><strong>JPEGs labelled as JP2</strong> are queued for the object's real JP2, the same way as TIFFs under the .jp2 name (the State Library WPA case).</td></tr>
<tr><td>v2.23</td><td>Nov 4, 12:38</td><td><strong>Parallel runs wait for the state database</strong> for up to 5 minutes instead of crashing. Step 3 also fetches the JP2 for a queued OBJ that turns out not to be a JP2.</td></tr>
<tr><td>v2.24</td><td>Nov 4, 15:11</td><td><strong>Step <code>3-convert</code></strong> makes a lossless JP2 from the TIFF when Fedora has no usable JP2. It compares every pixel before placing the file, keeps the old file in <code>_replaced</code>, converts CMYK to RGB, and writes <code>converted_jp2.csv</code>. DPI and colour profiles are not carried over. So far 28 pages, 26 in the main run and LSU's 2.</td></tr>
<tr><td>v2.25</td><td>Nov 5, 08:56</td><td><strong>Step <code>0-duplicates</code></strong> writes a review list of duplicate objects and odd namespaces across all institutions. It only reads Fedora's lists and the accounting, and it never runs by default.</td></tr>
<tr><td>v2.26</td><td>Nov 5, 09:45</td><td><strong>Step <code>0-triage</code></strong> groups that list into one row per decision, proposes what to keep, and sorts everything into batches for a person to approve. It also reads MODS files on L:, changes nothing, and never runs by default.</td></tr>
<tr><td>v2.27</td><td>Nov 5, 10:00</td><td><strong>Step <code>0-leave-out</code></strong> turns the rows approved in <code>triage.csv</code> into a list that downloads, retries and dry runs skip automatically, for every datastream. A book's pages go with it. Files already on L: aren't touched, and the audit and manifests still include them.</td></tr>
<tr><td>v2.28</td><td>Nov 5, 11:02</td><td><strong>Loyola is now an excluded institution,</strong> because it's harvested and isn't part of the migration. Runs by size skip it. Files already on L: aren't touched, and the review lists still read its Fedora list.</td></tr>
<tr><td>v2.29</td><td>Nov 5, 11:13</td><td><strong>Loyola is back in the migration.</strong> The LDL team confirmed it isn't harvested, so it's off the excluded list and runs by size include it again. Nothing had been removed for it.</td></tr>
<tr><td>v2.30</td><td>Nov 5, 11:45</td><td><strong>Empty text files aren't exported.</strong> An OCR, HOCR or FULL_TEXT download that comes back with nothing in it isn't saved, and it counts as done. Steps <code>0-empty-list</code> and <code>0-empty-move</code> move the ones already on L: to <code>L:\LDL1-datastreams\_empty_text\</code>, outside the export.</td></tr>
<tr><td>v2.31</td><td>Nov 5, 13:55</td><td><strong>Fewer downloads at once when connections drop.</strong> <code>--workers</code> is now the most that run at once. When 5 downloads in a row lose their connection, the exporter halves the number running, down to 2, and pauses 30 seconds. After 20 successful downloads it adds one back. These pauses now stop at 2 minutes instead of 10. Firewall blocks still pause for up to 10 minutes.</td></tr>
<tr><td>v2.32</td><td>Nov 6, 12:05</td><td><strong>Files confirmed empty in Fedora are set aside.</strong> Steps <code>0-empty-list</code> and <code>0-empty-move</code> also cover files on a short hand-checked list, moving them to <code>L:\LDL1-datastreams\_empty_other\</code>. Later steps don't ask for them again, and manifests leave them out. Other empty files stay until they've been checked. The first on the list is the empty Amistad recording.</td></tr>
</tbody>
</table>
HTML),
]);

$section('Cleanup list before handoff', [
  $text(NULL, <<<'HTML'
<p>The exporter never deletes from L:. These need doing by hand once the runs touching them are finished. Moving rather than deleting is the safe default.</p>
<ul>
<li><strong>Sugar Bulletin:</strong> 9,039 <code>-OBJ.pdf</code> files in the OBJ folders. Seven more are in LLMVC, Great Southern Lumber and Chaney Papers.</li>
<li><strong>McKinley High School Oral History Project:</strong> 61 transcripts saved as <code>-OBJ.pdf</code> in the OBJ folder. The real copies are in PDF-TRANSCRIPT.</li>
<li><strong>Daily Reveille:</strong> 2,006 RDF files for purged objects (PIDs 43009–45014).</li>
<li><strong>Accounting tree:</strong> move the double-space Colville folder, <code>lsu-test-2</code>, <code>spk test</code> and <code>Test</code> out of <code>L:\LDL1-accounting\Louisiana State University\</code>, to somewhere outside the accounting tree.</li>
<li><strong>Destination:</strong> move the <code>lsu-test-2</code> and <code>spk test</code> folders out of <code>L:\LDL1-datastreams\Louisiana State University\</code>. The real copies are in LSU University Archives Print Materials Collection.</li>
<li><strong>Abel J Leblanc and other TIFF pages:</strong> handled by the replacement step after the audit review. Old copies go to <code>_replaced</code>.</li>
<li><strong>Vermilionville:</strong> leftovers from the earlier run.</li>
<li><strong>Manifests:</strong> copy from <code>state\manifests</code> into the handoff package.</li>
</ul>
HTML),
]);

$section('Next steps', [
  $text('Done Nov 4', <<<'HTML'
<p>LSU's JPEG replacements and audit, LSUHSC's Newspaper Clippings images, 28 JP2s converted from TIFFs, and Franklin Parish's OCR, HOCR and PDF.</p>
HTML),
  $text('Done Nov 5', <<<'HTML'
<p>The 2,064 empty text files moved out of the export, manifests rebuilt at the six institutions that had them, and most of the queued image replacements.</p>
HTML),
  $text('Next', <<<'HTML'
<ul>
<li>Finish fetching the files the website refused directly from Fedora, then run the replacements the new audits queued. Finish Louisiana Newspapers, then download Loyola. Look into Amistad's one checksum failure and the State Museum's 2,230 Unavailable.</li>
<li>Run <code>0-empty-move</code> to set the empty Amistad recording aside, then rerun <code>6-manifests</code> for Amistad.</li>
<li>Run <code>3-convert</code> as TIFFs with no usable JP2 turn up: about 2,000 left across all institutions (at most about 98 GB), most of them in Louisiana Newspapers. The original count of 2,387 included the 28 already converted and 16 orphaned Agramonte pages.</li>
<li>Franklin Parish: 15,755 images on L: (finished Nov 4, 21:30). Earlier runs stopped at a PC standby (Nov 3) and a network drop (Nov 4, 14:55). Its 24 pages with no JP2 in Fedora were converted on Nov 5. This collection's images were never exported, so the run covers the whole collection, not only the 2,712 missing objects. The missing-rows file for the accounting is written: <code>reports\reconciliation\Louisiana Newspapers-fedora-only.csv</code>.</li>
<li>Download Loyola after Louisiana Newspapers. It was queued on Nov 6 ahead of the short list, so any of its objects later marked to leave out will need to be moved off L: by hand.</li>
<li>Get the short list in <code>triage.csv</code> approved, ideally the Loyola books before Loyola's download, then run <code>0-leave-out</code>.</li>
</ul>
HTML),
  $text('Then', <<<'HTML'
<p>The remaining institutions, in batches, on the same pattern: dry run, downloads, audit, review, replace, manifests.</p>
HTML),
]);

$section('Operator notes', [
  $text('Running the exporter', <<<'HTML'
<p>For whoever runs the exporter from the export PC (Windows PowerShell 5.1).</p>
<ul>
<li>Each terminal pane is its own shell. Activate the venv and set the variables again in every new one.</li>
<li>PowerShell 5.1 has no <code>&amp;&amp;</code>. Chain commands with <code>;</code>. Each step starts when the previous one finishes.</li>
<li>Ctrl+C stops the whole chain. Click away from a running terminal so a stray copy shortcut can't stop it. Interrupted runs resume safely: downloads skip finished files, and the audit skips verified ones.</li>
<li>Pass <code>--workers</code> for downloads and the audit. The default is 4. For downloads it's the most that run at once: 16 has worked for files under about 10 MB, and 3 to 5 suits large files. The exporter runs fewer by itself if connections keep dropping.</li>
<li>If <code>FEDORA_BASE_URL</code> is set in <code>.env</code>, keep the SSH tunnel open, because missing checksums are looked up through it.</li>
<li>For long runs: no sleep, Windows updates paused, on the campus network, ideally wired.</li>
<li>The <code>--note</code> text is only a label in the run log. "Stage" in a note isn't the pipeline step number.</li>
<li>Runs given several institutions at once share one log folder, <code>...\institutions\multiple_institutions</code>, for each datastream. Run their replacements with the same institution list so step 3 reads the same queue.</li>
<li>Step 3 doesn't retry "403 permission denied". To retry those, rerun step 1 (it fetches anything not on disk), for example with <code>--direct-fedora</code>.</li>
</ul>
HTML),
  $text('Overnight checklist', <<<'HTML'
<ul>
<li>Turn off sleep while plugged in: <code>powercfg /change standby-timeout-ac 0</code>. Set it back to 30 in the morning.</li>
<li>Plug in the laptop's own charger and leave the lid open. The OLED monitor may stop charging the laptop when it goes to standby, and shouldn't be kept on all night.</li>
<li>Lock the screen with Win+L. Don't sign out or close any terminal pane.</li>
<li>Leave the SSH tunnel window open. <code>.env</code> has <code>FEDORA_BASE_URL=http://localhost:8080/fedora</code>, so any checksum missing from the list is looked up through the tunnel. Its loop reconnects on its own if the connection drops.</li>
<li>Pause Windows updates if a restart is pending.</li>
</ul>
HTML),
  $code('Example audit command', <<<'HTML'
.\.venv\Scripts\Activate.ps1; $inst = "Louisiana State University"
python download_datastreams.py --institution $inst --datastream OBJ --steps 5-fedora --workers 16 --note "..."
HTML),
]);

// End of sections. ---------------------------------------------------------

$block_count = 0;
foreach ($sections as $item) {
  $block_count += count($item['blocks']);
}
$fingerprint = substr(sha1(json_encode([$intro, $sections], JSON_THROW_ON_ERROR)), 0, 12);
$log_prefix = 'palefire_guide_ldl_migration.php build';
$log_message = "$log_prefix $fingerprint";

// What's there now. --------------------------------------------------------

$storage = \Drupal::entityTypeManager()->getStorage('node');
$found = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', $title)
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
    foreach ($block['items'] as $item) {
      $children[] = Paragraph::create([
        'type' => 'guide_stat',
        'field_stat_value' => $item[0],
        'field_stat_label' => $item[1],
        'field_stat_progress' => $item[2] ?? NULL,
      ]);
    }
    return Paragraph::create($keep([
      'type' => 'guide_stats',
      'field_block_title' => $block['heading'],
      'field_stat_items' => $children,
      'field_stats_caption' => $rich($block['caption']),
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
  'path' => ['alias' => '/' . ltrim($args['path'] ?? '/v2/special/guides/ldl-quartex-migration', '/')],
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
