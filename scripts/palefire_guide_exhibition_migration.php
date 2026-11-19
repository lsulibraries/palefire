<?php

/**
 * @file
 * Guide page: "Exhibition Migration Review", every change made to the text,
 * audio and images of the online exhibitions when they moved to Palefire,
 * for curators to check: what was corrected (old → new), what to verify,
 * and what was written new. As a Modular page in guide mode. Run
 * palefire_guide_setup.php and palefire_guide_blocks_setup.php first.
 *
 *   drush php:script scripts/palefire_guide_exhibition_migration.php
 *   drush php:script scripts/palefire_guide_exhibition_migration.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. Built as palefire_guide_palefire_redesign.php:
 * this script is the page's source and can be run again. A new page is
 * created UNPUBLISHED; after that each build replaces the intro and
 * sections in a new revision, and stops if the page was edited in Drupal or
 * has a newer draft (force=1 overrides the first).
 *
 * Optional arguments after "--": apply=1, force=1, format=<text format>,
 * path=<alias> (default /v2/guides/exhibition-migration-review).
 *
 * The exhibitions' sections come from docs/exhibition-corrections/*.md,
 * written by the sessions that built each exhibition (tables there are
 * lists here, so no text format has to allow <table>).
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

$title = 'Exhibition Migration Review';
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

$intro = '<p>Every change made to the online exhibitions when they moved to the new site: corrections to the old text, things to check, wording written new, and how the audio and images were prepared. For curators and editors to review, one exhibition at a time.</p>';

// Sections. ----------------------------------------------------------------

$section('How to use this page', [
  $text(NULL, <<<'HTML'
<p>Each exhibition below has three lists:</p>
<ul>
<li><strong>Corrected:</strong> changes made to the old text, written as old → new: typos, names, dates, punctuation, captions and speaker labels.</li>
<li><strong>To verify:</strong> things left as written but in doubt, or that couldn’t be confirmed. These need a curator’s answer.</li>
<li><strong>New wording to check:</strong> text with no old counterpart, such as alt text, captions written new, credit lines and gallery lead captions.</li>
</ul>
<p>Changes made the same way everywhere are described once, in How the exhibitions were moved, and not listed per exhibition: curly quotes and apostrophes, dashes in date ranges, and speaker labels in bold.</p>
<p>To report a mistake, or to answer a To verify item, note the exhibition and the gallery or clip, and send it to the web team. A fix is made in the exhibition’s data and then on the page, so a rebuild keeps it.</p>
HTML),
]);

$section('How the exhibitions were moved', [
  $text(NULL, <<<'HTML'
<h3>Where they came from</h3>
<ul>
<li><strong>Oral history exhibitions</strong> (eleven, from the T. Harry Williams Center for Oral History): the old listening stations at lib.lsu.edu/sites/all/files/oralhistory/presentations/&lt;folder&gt;. Each now lives at /v2/oralhistory/exhibitions/&lt;folder&gt;, the old folder’s name in lowercase, so the old and new addresses are easy to match.</li>
<li><strong>Special Collections exhibitions</strong>: AVES, Occult Science and the Audubon exhibition from the Wayback Machine’s copies of the old exhibitions blog and pages; the LSU Union Art Gallery from its showcase on the staging site. They live under /v2/special/exhibitions.</li>
</ul>
<h3>Text</h3>
<ul>
<li>Carried over word for word, then corrected only where listed in each exhibition’s Corrected list.</li>
<li>Text the old pages had saved in the wrong character encoding (letters such as “DufourÃÂ¢s”) was decoded back to the original characters exactly.</li>
<li>Straight quotes and apostrophes became curly ones (’ “ ”), and date ranges use an en dash (1939–1942). These are not listed one by one.</li>
<li>Oral history transcripts: each speaker’s turn starts a new paragraph, with the speaker’s full name in bold capitals (as on the Mossville History Project), not a first name or initials.</li>
<li>Captions come from each exhibition’s own Pictures or credits pages where they had them, since those give fuller citations than the slides did.</li>
<li>Every image has new alt text written for people who use screen readers. Alt text is listed under New wording to check.</li>
</ul>
<h3>Audio</h3>
<p>Every clip was prepared the same way, so all the exhibitions sound alike and none is noticeably quieter than another (scripts/palefire_exhibit_media.py):</p>
<ul>
<li><strong>Mono.</strong> Speech needs only one channel, and a mono file is half the size. A stereo file whose second channel is silent or much quieter (20 dB or more) keeps its live channel instead of being mixed down at half volume.</li>
<li><strong>Loudness −16 LUFS</strong> (integrated), measured and corrected in two passes, with peaks held under −1.5 dBTP and a loudness range of 11 LU. This is close to what podcasts use. It was −18 LUFS until Mon 16 Nov 2026, when it was raised so the clips aren’t quieter than other sites’ audio. Browsers don’t level volume between sites.</li>
<li><strong>AAC at 64 kbps, 44.1 kHz, in .m4a files.</strong> This is plenty for speech, plays in every current browser, and keeps files small, so clips start quickly on a phone.</li>
<li><strong>Read-along timing.</strong> Each clip has a WebVTT timing file beside it, so its transcript lights up phrase by phrase as it plays and a click on a phrase plays from there. The timing was matched to the published transcript, whose words are always the ones shown. There are 230 files for the ten newer exhibitions, plus Mossville’s.</li>
<li>A few old pages played the wrong recording for a clip. Those are noted in their exhibition’s list, with the clip that now plays.</li>
</ul>
<h3>Images</h3>
<ul>
<li><strong>Oral history exhibitions:</strong> masters at 2,560 pixels on the long side (never enlarged), sRGB JPEG at quality 85, with camera and editing metadata removed.</li>
<li><strong>Special Collections exhibitions:</strong> made from the 4K scans where they exist (at most 3,840 pixels on the long side), showing the whole sheet where the old pages showed a crop. Each exhibition’s list notes where an image couldn’t be found at full size.</li>
<li>On the page, each image is served as WebP at the size the screen needs (640, 1,280, 1,920 or 2,560 pixels wide) and loads only as it comes into view.</li>
</ul>
HTML),
]);

$section('Cooperative Extension at LSU', [
  $text(NULL, <<<'HTML'
<p>Now at <a href="/v2/oralhistory/exhibitions/lsuag">/v2/oralhistory/exhibitions/lsuag</a>.</p>
<p>Old site: <a href="https://lib.lsu.edu/sites/all/files/oralhistory/presentations/LSUAG/lsuag.html">https://lib.lsu.edu/sites/all/files/oralhistory/presentations/LSUAG/lsuag.html</a></p>
<p>Galleries: <strong>Oral Histories</strong> (12 clips), <strong>Narrators</strong> (8 bios, from the old per-person pages), <strong>Pictures</strong> (18 photos from pictures.html), <strong>About the Exhibition</strong> (About and Acknowledgements pages).</p>
<p>Throughout, apostrophes and quotation marks became typographic (’ “ ”), and date ranges use an en dash (1939–1942). These aren't listed one by one.</p>
<h3>Corrected</h3>
<h4>Clip titles (Oral Histories)</h4>
<ul>
<li><strong>C5 (Eubanks)</strong>: 4-H Gave Gina First Sewing Machine → 4-H Gave Gina <strong>Her</strong> First Sewing Machine</li>
<li><strong>C6 (Loupe)</strong>: Incentive for Sheep and Swine Building → Incentive for <strong>the</strong> Sheep and Swine Building</li>
<li><strong>C10 (Roberts)</strong>: Increase in Vietnamese Fisherman (page heading) → Increase in Vietnamese Fishermen (as in the old menu)</li>
<li><strong>C11 (Coreil)</strong>: Disaster Recovery (page heading) / On Disaster Recovery (menu) → On Disaster Recovery</li>
</ul>
<h4>Speaker labels (all transcripts)</h4>
<ul><li>"<strong>Pamela Dean</strong>: … <strong>Dean</strong>:" (full name on the first turn, then the surname) → every turn labeled with the full name in bold capitals, as on Mossville: <strong>PAMELA DEAN:</strong>, <strong>ROBERT BADON:</strong>, and so on.
</li><li>C3: "<strong>Jen Cramer</strong>" → <strong>JENNIFER A. CRAMER</strong>
</li></ul>
<h4>Transcripts</h4>
<ul>
<li><strong>C1 Badon</strong>: a ten-thousand acre sugar cane farm → a ten-thousand-acre sugarcane farm</li>
<li><strong>C3 Richardson</strong>: the old wives tale → the old wives’ tale</li>
<li><strong>C4 Loupe</strong>: You had a plow-you followed a mule → You had a plow—you followed a mule</li>
<li><strong>C4 Loupe</strong>: during World Wwar II → during World War II</li>
<li><strong>C4 Loupe</strong>: managing farms.Then that brought in → managing farms. Then that brought in</li>
<li><strong>C4 Loupe</strong>: Oh completely. → Oh, completely.</li>
<li><strong>C4 Loupe</strong>: Well number one, telephones. → Well, number one, telephones.</li>
<li><strong>C4 Loupe</strong>: it was alright for me to call a county agent in x parish → it was all right for me to call a county agent in X Parish</li>
<li><strong>C5 Eubanks</strong>: Oh it's been long. → Oh, it’s been long.</li>
<li><strong>C5 Eubanks</strong>: Made mostly ladies clothes. → Made mostly ladies’ clothes.</li>
<li><strong>C7 Cochran</strong>: heated air applied to the sugar cane → heated air applied to the sugarcane</li>
<li><strong>C7 Cochran</strong>: some of the farmer . . .the extension people → some of the farmer . . . the extension people</li>
<li><strong>C9 Coreil</strong>: the sea turtles population was crashing → the sea turtle population was crashing</li>
<li><strong>C9 Coreil</strong>: turtle exclusive devices → turtle excluder devices (TEDs, the devices the rule required)</li>
<li><strong>C9 Coreil</strong>: But when I go there, it was just scary → But when I got there, it was just scary (transcription slip; check against the audio)</li>
<li><strong>C9 Coreil</strong>: these VHS radios → these VHF radios (marine radio)</li>
<li><strong>C9 Coreil</strong>: Governor [Buddy]Roemer, and he asked Roemier "Pull them back. → Governor [Buddy] Roemer, and he asked Roemer, “Pull them back.</li>
<li><strong>C10 Roberts</strong>: they were artisanal fisherman → they were artisanal fishermen</li>
<li><strong>C12 Loupe</strong>: my specialty as an agronomist, in sugar cane … my PhD actually in sugar cane → … in sugarcane … in sugarcane</li>
</ul>
<h4>Narrators (biographies, from badon/howell/richardson/loupe/eubanks/cochran/coreil/roberts.html)</h4>
<ul>
<li><strong>Richardson</strong>: earned a B.S degree in 1966 → earned a B.S. degree in 1966</li>
<li><strong>Richardson</strong>: recognized twice as and Outstanding Teacher → recognized twice as an Outstanding Teacher</li>
<li><strong>Richardson</strong>: He currently serves as Director of Louisiana Cooperative Extension Service and LSU Vice-President for Agriculture and Dean for the College of Agriculture. → At the time of the interview, he served as Director of the Louisiana Cooperative Extension Service, LSU Vice President for Agriculture, and Dean of the College of Agriculture.</li>
<li><strong>Loupe</strong>: In 1978, he was selected as director of Louisiana Cooperative Extension Services. → … director of the Louisiana Cooperative Extension Service.</li>
<li><strong>Loupe</strong>: American Society of Sugarcane Technologies → American Society of Sugar Cane Technologists</li>
<li><strong>Eubanks</strong>: L.E. Eubanks → L. E. Eubanks</li>
<li><strong>Cochran</strong>: born on December 10, 1933 in Darbun → born on December 10, 1933, in Darbun</li>
<li><strong>Cochran</strong>: a bachelor's of science in agricultural engineering → a bachelor of science in agricultural engineering</li>
<li><strong>Coreil</strong>: a PhD in vocation education → a PhD in vocational education</li>
<li><strong>Coreil</strong>: involved in Louisiana Cooperative Extension Services → involved in the Louisiana Cooperative Extension Service</li>
<li><strong>Coreil</strong>: Director of Louisiana Cooperative Extension Services → Director of the Louisiana Cooperative Extension Service</li>
<li><strong>All eight</strong>: "[Name]'s interview is housed in …" plus the citation, as two paragraphs → Moved into the entry's catalog-record block, after the citation: "[Surname]’s interview is housed in …"</li>
</ul>
<h4>Pictures (captions from pictures.html)</h4>
<ul>
<li><strong>Visitors from Indonesia</strong>: Agent has noted on back of photograph → Agent has noted on the back of the photograph</li>
<li><strong>William Rufus Dodson</strong>: c.1916 … he was soon appointed the first dean the College of Agriculture → c. 1916 … He was soon appointed the first dean of the College of Agriculture</li>
<li><strong>W. H. Balis</strong>: Balis served as … and supervised the boys' pig clubs. → <strong>W. H.</strong> Balis served as … and supervised the boys’ pig clubs. (initials read from the portrait's inscription; see list 2)</li>
<li><strong>Eat Right and Be Physically Fit</strong>: c.1936 → c. 1936</li>
<li><strong>Pickles and Preserves</strong>: Woman displaying cabinet of pickled … → Woman displaying a cabinet of pickled …</li>
<li><strong>Mary Mims</strong>: Image from McGinty, Garnie William. Mary Williams Mims: … Ruston, La.: G. McGinty, 1978. S544.3 .L7 M33 1978 Middleton → Image from Garnie William McGinty, <em>Mary Williams Mims: …</em> (Ruston, La.: G. McGinty, 1978). Middleton Library, S544.3 .L7 M33 1978.</li>
<li><strong>E. S. Richardson</strong>: Richardson was the Supervisor of … → <strong>E. S.</strong> Richardson was the Supervisor of … (initials from the inscription)</li>
<li><strong>Clyde Mobley</strong>: c. 1925 … in Bienville Parish. by 1917, she … LSU Photographic Collection → … in Bienville Parish. By 1917, she … LSU Photograph Collection</li>
<li><strong>Health Checks</strong>: c.1942 → c. 1942</li>
<li><strong>Future Office of Agricultural Extension</strong>: Sign stating ''Future Office of Agricultural Extension,'' c.1946 → Sign stating “Future Office of Agricultural Extension,” c. 1946</li>
<li><strong>Hog Cholera</strong>: Image include pigs grazing in a field and entrails of animals afflicted with hog cholera/swine typhoid, n.d. → Pigs grazing in a field and the entrails of animals with hog cholera and swine typhoid, n.d.</li>
<li><strong>Captions with a line break (Zola Cosby)</strong>: "…cabbages, 1927.⏎Rapides Parish" → one line</li>
</ul>
<h4>Intro and About (about.html; about2.html is an unlinked duplicate)</h4>
<ul>
<li><strong>Intro</strong>: featuring oral histories from LSU Libraries T. Harry Williams Center → … from LSU Libraries’ T. Harry Williams Center</li>
<li><strong>Intro</strong>: is presented in conjunction with → was presented in conjunction with (past tense; the exhibition has closed)</li>
<li><strong>Intro</strong>: The oral history listening station includes → The listening station includes</li>
<li><strong>Intro</strong>: earning money by sewing ladies clothing → … sewing ladies’ clothing</li>
<li><strong>About</strong>: The gallery exhibit examines … Covering two floors, this exhibition includes … from every major collection including: … Selected items highlight … and cover topics → The gallery exhibition examined … Covering two floors, it included … from every major collection: … Selected items highlighted … and covered topics</li>
<li><strong>About</strong>: socio-economic groups → socioeconomic groups</li>
<li><strong>About</strong>: Links page: six icon/QR links (Interviews, Williams Center, Exhibitions, LSU Libraries, LSU AgCenter, LSU College of Agriculture) → Three text links: the Cooperative Extension interviews (lib.lsu.edu/oralhistory/collections?field_series_value=13), LSU AgCenter, LSU College of Agriculture (www.coa.lsu.edu now redirects to <a href="https://www.lsu.edu/agriculture/">https://www.lsu.edu/agriculture/</a>, so it links there). The QR codes are dropped.</li>
</ul>
<h4>Acknowledgments (acknowledgements.html)</h4>
<ul>
<li><strong>Acknowledgements:</strong>: Acknowledgments (American spelling)</li>
<li><strong>Co-Curator: Leah Wood Jewett, … / Co-Curator: Michelle Melancon, … (separate lines)</strong>: Co-Curators: Leah Wood Jewett, …; and Michelle Melancon, …</li>
<li><strong>Oral History Listening Station - LSU T. Harry Williams Center for Oral History</strong>: Heading "Oral History Listening Station", with "LSU T. Harry Williams Center for Oral History." opening the paragraph</li>
<li><strong>Listening Station Design, Audio Engineer: Kyle Tanglao</strong>: Listening Station Design and Audio Engineer: Kyle Tanglao</li>
<li><strong>Chelsea Arsenault, Graduate Assistant</strong>: Chelsea Arseneault (spelled as on the Mossville exhibition)</li>
<li><strong>Graphic Design and Publicity - LSU AgCenter</strong>: Heading "Graphic Design and Publicity", with "LSU AgCenter." opening the paragraph</li>
<li><strong>Director &amp; Professor … Associate Director &amp; Professor … Editor of Louisiana Agriculture</strong>: Director and Professor … Associate Director and Professor … Editor of <em>Louisiana Agriculture</em></li>
</ul>
<h4>Photos</h4>
<ul><li>The C6 photo (young men with dairy cattle, c6.png at 1900 px) was replaced by its higher-resolution scan in the same folder (Incentive_Loupe.tif, 5272 px). The scan's crop shows a little more at the top.
</li><li>The C2 clip photo and the "Home Demonstration Class" picture are the same file in both places; it's imported once and used twice.
</li></ul>
<h3>To verify</h3>
<p><strong>Identity and credits of photos</strong></p>
<ul><li><strong>Robert Badon portrait</strong> (badon.png): a young soldier in a World War I uniform. The old bio page showed it unlabeled; the caption now says "Robert Badon in uniform." Is it Badon?
</li><li><strong>Dorothy Howell portrait</strong> (dorothy.jpg): a yearbook-style portrait, captioned "Dorothy Colvin Howell." It's only 227 × 272 px, so it looks soft enlarged. Is there a better scan?
</li><li><strong>William Richardson photo</strong> (c3.jpg): he's speaking at a hearing table beside an unnamed woman. Source, date and photographer are unknown; the caption is his name only. 660 px wide.
</li><li><strong>Kenneth Roberts portrait</strong> (roberts.jpg): a studio portrait, probably an AgCenter headshot. No credit given.
</li><li><strong>"Photo by Kyle Tanglao"</strong> on the Cochran, Coreil, Eubanks and Loupe photos (all 2014) is inferred from "Interview photography by Kyle Tanglao" in the acknowledgments. Please confirm.
</li><li><strong>Balis and Richardson initials</strong>: "W. H. Balis" and "E. S. Richardson" are read from the handwriting on the portraits; the old captions gave surnames only.
</li><li><strong>C8 "Agents Genuinely Helpful"</strong> uses the Train of Champions photo (1949, Colfax) on the old site too, not a photo of Eubanks. It's kept, with the Pictures caption.
</li><li><strong>Clip photos without old captions</strong> (pig.png, Loupe_C4.png, eubanks_children.png, c6, shrimpingboats.jpg, disaster.jpg): no date, place or collection is known. See list 3 for the new captions.
</li><li><strong>Small sources</strong>: pig.jpg 629 px, loupe_c4.jpg 811 px, c3.jpg 660 px, dorothy.jpg 227 px. No larger copies in the folder.
</li></ul>
<p><strong>Transcripts: left as spoken or as transcribed, but doubtful</strong></p>
<ul><li><strong>C3</strong>: "The Farmer’s Almanac is one of best predictors": possibly "<em>The Old Farmer’s Almanac</em>" or "Farmers’ Almanac". The words "one of [the] best" were left as spoken.
</li><li><strong>C9</strong>: "when I go there" → "when I got there" is my reading. Confirm on the audio.
</li><li><strong>C9</strong>: "talking to the national fisheries services": the agency is the National Marine Fisheries Service. Left as spoken.
</li><li><strong>C9</strong>: "Randy Roach, who’s now mayor of Lake Charles" is true as of the 2014 interview. Left as spoken.
</li><li><strong>C9</strong>: "It’s something that I hoped to never to have to do again" was left as spoken.
</li><li><strong>C2</strong>: "We were the Julia Childs of the day" was left as spoken.
</li><li><strong>C11</strong>: "the internet don’t work in a disaster" was left as spoken.
</li><li><strong>C8</strong>: "Tommy [?]" is an unknown surname, kept from the old transcript.
</li><li><strong>C12</strong>: "If they could get the [?] of the school board" is an unknown word, kept.
</li><li><strong>C6</strong>: "John A. Cox", the director Loupe succeeded, is unverified.
</li><li><strong>C10</strong>: "Asian oriented" is the speaker's wording, kept.
</li></ul>
<p><strong>Bios and records</strong></p>
<ul><li><strong>Richardson bio</strong>: "He attended Dearing public schools". The town near Kennett, Missouri, is spelled <strong>Deering</strong>; probably a typo, but it was left as written.
</li><li><strong>Roberts bio</strong>: "Lydia Leblanc" (LeBlanc?) was left as written.
</li><li><strong>Howell bio</strong>: "graduate degree in Agriculture Industry" and "the Helen Carter House" were left as written.
</li><li><strong>Accession numbers</strong> (4700.0555, .1418, .2384–.2389) and interview years are copied from the old pages, not checked against the catalog.
</li><li><strong>Interviewers</strong>: intro and acknowledgments list Wyatt Winnie, Jennifer A. Cramer and Cristina Caminita for the 2014 interviews. Per the records, Coreil's interviewer was Caminita and Richardson's was Cramer; the other four were Winnie.
</li><li><strong>Ken vs. Kenneth Roberts</strong>: the old intro says "Ken Roberts", his bio and record say "Kenneth". The intro keeps "Ken".
</li><li><strong>Badon bio</strong>: "school teacher" (often "schoolteacher") was left as written.
</li></ul>
<p><strong>Exhibition facts</strong></p>
<ul><li><strong>Year 2014</strong> and the note "Shown in Hill Memorial Library in 2014 beside the gallery exhibition of the same name": the exact dates of the gallery exhibition weren't on the old site.
</li><li><strong>Gallery Exhibition credits</strong>: titles such as "Agriculture/Information Literacy Librarian" are as of 2014.
</li></ul>
<p><strong>Not carried over</strong></p>
<ul><li>The <strong>home-screen video</strong> (video/cowgrasspig.mp4, 90 s, ambient background loop of cows, grass and pigs) is left out as decorative.
</li><li><strong>Unused files in the old folder</strong>: Loupe_Denver_C3.mp3, Loupe_Denver_C4.mp3, Loupe_Denver_C4_Unedited.mp3 and Loupe_Denver_C8.mp3 were never linked from any page. Unused photos include Eubanks_Sewing.png (National Home Demonstration Week window) and dorothy_canning_demonstrations.jpg. Curators may want any of them.
</li><li><strong>about2.html</strong> (unlinked) differs slightly: "Louisianians", "Gina Eubanks", "Bill Richardson", "Spring of 2014". The linked about.html wording was used.
</li><li>The DISCOVER and MOHP folders (separate projects sharing the title tag) were ignored.
</li></ul>
<h3>New wording to check</h3>
<p><strong>Exhibition</strong></p>
<ul><li><strong>Credit</strong>: "An oral history listening station from the T. Harry Williams Center for Oral History, LSU Libraries, presented with the Hill Memorial Library gallery exhibition of the same name. Curated by Jennifer Abraham Cramer and Wyatt Winnie, with the LSU AgCenter and the LSU College of Agriculture."
</li><li><strong>Physical note</strong>: "Shown in Hill Memorial Library in 2014 beside the gallery exhibition of the same name."
</li><li><strong>Gallery titles</strong>: Oral Histories, Narrators, Pictures, About the Exhibition.
</li><li><strong>Pictures entry titles</strong> (new; the old page had no titles): Visitors from Indonesia, Eggs on KNOE-TV, Mattress Demonstration, Train of Champions, William Rufus Dodson, W. H. Balis, Eat Right and Be Physically Fit, A Car Loaded with Cabbages, Home Demonstration Class, On the Air at KLIC, Pickles and Preserves, Mary Mims, E. S. Richardson, Girl with Winning Cow, Clyde Mobley, Health Checks, Future Office of Agricultural Extension, Hog Cholera.
</li><li><strong>About</strong>: the line "Browse the Cooperative Extension interviews in the T. Harry Williams Center for Oral History collections." and "More from the partners: LSU AgCenter and the LSU College of Agriculture."
</li></ul>
<p><strong>Lead captions</strong></p>
<ul><li><strong>Cover</strong>: the Train of Champions photo (public_04).
</li><li><strong>Oral Histories</strong>: "Home demonstration class, photograph by Jasper Ewing, c. 1930s. LSU Photograph Collection, Louisiana Cooperative Extension Service, University Archives."
</li><li><strong>Narrators</strong>: "Gina E. Eubanks, 2014. Photo by Kyle Tanglao."
</li><li><strong>Pictures</strong>: "Girl with winning cow, 1946. Rapides Parish."
</li></ul>
<p><strong>Clip credit lines</strong> (the old pages showed only the name)</p>
<ul><li>C1 "Robert Badon, interviewed by Pamela Dean, 1995"
</li><li>C2 "Dorothy Howell, interviewed by Jennifer Abraham, 2002"
</li><li>C3 "William Richardson, interviewed by Jennifer A. Cramer, 2014"
</li><li>C4, C6, C12 "Denver Loupe, interviewed by Wyatt Winnie, 2014"
</li><li>C5, C8 "Gina E. Eubanks, interviewed by Wyatt Winnie, 2014"
</li><li>C7 "Bill Cochran, interviewed by Wyatt Winnie, 2014"
</li><li>C9, C11 "Paul Coreil, interviewed by Cristina Caminita, 2014"
</li><li>C10 "Kenneth Roberts, interviewed by Wyatt Winnie, 2014"
</li></ul>
<p><strong>Clip photo captions</strong> (the old clip pages had none)</p>
<ul><li>C1 "Agents vaccinate a hog, undated."
</li><li>C2 the Jasper Ewing caption (from Pictures)
</li><li>C3 "William Richardson."
</li><li>C4 "A tractor pulls field machinery, undated."
</li><li>C5 "Girls with their certificates around a sewing machine, undated."
</li><li>C6 "Young men with dairy cattle at a livestock exhibit, undated."
</li><li>C7 "Bill Cochran, 2014. Photo by Kyle Tanglao."
</li><li>C8 the Train of Champions caption (from Pictures)
</li><li>C9 "Paul Coreil, 2014. Photo by Kyle Tanglao."
</li><li>C10 "Shrimp boats at the dock."
</li><li>C11 "An LSU AgCenter Disaster Information sheet: “There’s a hurricane forming . . .”"
</li><li>C12 "Denver Loupe, 2014. Photo by Kyle Tanglao."
</li></ul>
<p><strong>Narrator portrait captions</strong></p>
<ul><li>Badon: "Robert Badon in uniform."
</li><li>Howell: "Dorothy Colvin Howell."
</li><li>Richardson: "William Richardson."
</li><li>Roberts: "Kenneth Roberts."
</li><li>Loupe, Eubanks, Cochran, Coreil: "[Name], 2014. Photo by Kyle Tanglao."
</li></ul>
<p><strong>Alt text</strong> (all new; every old image had empty or "image-1" alt)</p>
<ul><li>pig.jpg: Three men in hats crouch over a hog on bare ground beside a board fence, holding it down to vaccinate it.
</li><li>c2.jpg: A woman in a white coat points to a chart on a board as a class of women at tables takes notes in a wood-paneled room.
</li><li>c3.jpg: William Richardson, in a suit and glasses, speaks into a microphone at a hearing table beside a woman.
</li><li>loupe_c4.jpg: A man drives an early tractor pulling a large harvesting machine with a tall blower pipe across a field.
</li><li>eubanks_children.jpg: Black-and-white photo of nine Black girls and a woman gathered around a sewing machine, the girls holding up certificates.
</li><li>incentive_loupe.jpg: Young men in work clothes lean on a rail beside dairy cows bedded in straw inside a long exhibit car.
</li><li>cochran.jpg: Bill Cochran, in a striped green polo shirt, smiles at a table holding a pen.
</li><li>c8.jpg / public_04.jpg: A crowd of Black children and adults gathers beside a railroad car with a sign reading “Louisiana Train of Champions, 4-H Clubs.”
</li><li>coreil.jpg: Paul Coreil, in a dark suit and patterned tie, sits in an armchair in an office, gesturing as he speaks.
</li><li>shrimpingboats.jpg: A row of shrimp boats with tall rigging and nets moored at a dock under a blue sky.
</li><li>disaster.jpg: Close view of an LSU AgCenter Disaster Information sheet headed “There’s a hurricane forming,” with a checklist.
</li><li>loupe.jpg: Denver Loupe, an older man in glasses and a blue shirt, gestures as he talks at his office desk.
</li><li>badon.jpg: Black-and-white photo of a young soldier in a World War I uniform and campaign hat, standing by a building.
</li><li>dorothy.jpg: Black-and-white yearbook portrait of Dorothy Howell as a young woman with dark curled hair.
</li><li>eubanks.jpg: Gina E. Eubanks, in a pink blouse and white jacket, laughs at her office desk.
</li><li>roberts.jpg: Studio portrait of Kenneth Roberts in a tan jacket and patterned tie, smiling.
</li><li>public_01.jpg: Two men and a woman look on as a seated woman points to cards pinned to a board in a home parlor.
</li><li>public_02.jpg: A cameraman films a man in a suit at a kitchen counter with a scale and cartons of eggs, a KNOE-TV camera in front.
</li><li>public_03.jpg: A group of Black women and men stand around a long cotton mattress on sawhorses, working on it outdoors.
</li><li>portrait_dodson_1.jpg: Oval portrait of a mustached man in a bow tie, signed “W. R. Dodson, A.B., B.S.”
</li><li>portrait_balis_1.jpg: Oval portrait of a young man in a high collar and tie, signed “W. H. Balis, B.S.”
</li><li>lsupcagextb159f009p001.jpg: An exhibit board headed “Eat Right and Be Physically Fit” with height and weight tables and trays of foods for a normal diet.
</li><li>lsupcagextb159f002p001.jpg: A young woman in a white dress holds a cabbage beside an open car piled with cabbages in front of a house.
</li><li>lsupcagextb158f012p001.jpg: A woman in glasses pours from a coffee pot at a studio microphone below a “Mutual KLIC” banner.
</li><li>lsupcagextb158f011p001.jpg: A woman in an apron points to shelves of a tall white cabinet filled with jars of preserves.
</li><li>portrait_richardson_1.jpg: Oval portrait of a man in round glasses and a bow tie, signed “E. S. Richardson, B.S.”
</li><li>lsupcagextb160f007p002.jpg: A girl in a checked dress holds a lead on a large Brahman cow on a showground lawn.
</li><li>lsupcagextb003f004p001.jpg: Sepia portrait of Clyde Mobley, a woman with waved short hair in a dark jacket and pale blouse.
</li><li>public_06.jpg: Teenagers line up in a doorway; one is measured for height while a girl looks into a boy’s mouth.
</li><li>public_05.jpg: Signs on a building site with stacks of block read “Future Office of Agricultural Extension” and the builders’ names.
</li><li>public_07.jpg: A scrapbook page with a photo of pigs grazing in a field above two photos of diseased entrails, numbered by hand.
</li><li>public_08.jpg: Black-and-white portrait of Mary Mims, a woman with dark wavy hair, a beaded necklace and a lace collar.
</li></ul>
<p>Things to check in the alt text: the count of girls in eubanks_children (nine), "Brahman" for the cow, and "the inside of an exhibit car" for the C6 photo (it may be a barn).</p>
HTML),
]);

$section('Do Not Tear Up My Earth', [
  $text(NULL, <<<'HTML'
<p>Now at <a href="/v2/oralhistory/exhibitions/dntume">/v2/oralhistory/exhibitions/dntume</a>.</p>
<p>Old presentation: <a href="https://lib.lsu.edu/sites/all/files/oralhistory/presentations/DNTUME/dntume.html">https://lib.lsu.edu/sites/all/files/oralhistory/presentations/DNTUME/dntume.html</a> (one page, 2001, updated later; the live page matches the copy in <code>_reference/</code>).</p>
<p>The old page put its title, subtitle and chapter names in images. Those are now real text: "Do Not Tear Up My Earth" / "Documenting women’s voices in Louisiana’s grassroots environmental movement, 1970–1990", and the chapters Awakening, Hysterical Housewives, Why Women? and About (now "About the Project").</p>
<h3>Corrected</h3>
<p>Each transcript is the old page's quote, which is the whole clip (5–32 seconds). The words are unchanged; only spelling, punctuation and quotation marks were fixed.</p>
<h4>Transcripts</h4>
<ul>
<li><strong>Awakening / The same family (Vinton)</strong>: I guess I've always thought of us all belonging to the same family, really." → I guess I’ve always thought of us all belonging to the same family, really. <em>(stray closing quotation mark removed)</em></li>
<li><strong>Awakening / We deserve better (Bourg)</strong>: You know for a state that I love so much and to be proud to be from it's probably one of the most polluted… And the people of Louisiana really, we deserve better than that and we need to get better than that. → You know, for a state that I love so much, and to be proud to be from, it’s probably one of the most polluted… And the people of Louisiana really, we deserve better than that, and we need to get better than that. <em>(commas added)</em></li>
<li><strong>Awakening / Running from the plant (Ramirez)</strong>: We've been having problems with the plant early on because I remember when it would explode, we didn't have an alarm but the whistle to go off, but the police would come in, I say police but the sheriffs would come and they would tell us… barefooted and we were running Old Spanish Trail where gators were, they had gators in there… swamp land.” → We’ve been having problems with the plant early on, because I remember when it would explode, we didn’t have an alarm but the whistle to go off. But the police would come in, I say police but the sheriffs would come, and they would tell us… barefooted, and we were running Old Spanish Trail where gators were. They had gators in there… swamp land. <em>(sentences split, commas added, stray closing quotation mark removed)</em></li>
<li><strong>Hysterical Housewives / Hysterical housewives, raving idiots (Subra)</strong>: …not a clue about what was going on.‘How dare these people come in here… when we're trying to do our job? → …not a clue about what was going on. “How dare these people come in here… when we’re trying to do our job?” <em>(missing space added; the unclosed single quotation mark is now a closed pair of double quotation marks)</em></li>
<li><strong>Hysterical Housewives / A meddlesome housewife (Tutweiler)</strong>: …and I kind of liked it because that's what I was. I was a housewife, I had a baby on my hip and I was certainly going to meddle if they were going to meddle with me which is, you know… → …and I kind of liked it, because that’s what I was. I was a housewife, I had a baby on my hip, and I was certainly going to meddle if they were going to meddle with me, which is, you know… <em>(commas added)</em></li>
<li><strong>Why Women? / A mother’s child (Jackson)</strong>: …more stronger than a man and once you mess with a mother's child… → …more stronger than a man, and once you mess with a mother’s child… <em>(comma added; "more stronger" kept as spoken)</em></li>
<li><strong>Why Women? / I wasn’t backing off (Baudoin)</strong>: I think a woman is going to take an issue and it's not going away because when you get . . . I wasn't backing off and so certainly I think… cleaned up or straightened up, or closed… → I think a woman is going to take an issue and it’s not going away, because when you get . . . I wasn’t backing off. And so certainly I think… cleaned up, or straightened up, or closed… <em>(new sentence and commas added)</em></li>
<li><strong>Why Women? / Don’t tear up My Earth (Goldsmith)</strong>: …for us to take care of and we're not the ones… I have a very strong belief in God and this was God's message to me that you don't tear up My Earth, it was given to you. → …for us to take care of, and we’re not the ones… I have a very strong belief in God, and this was God’s message to me: that you don’t tear up My Earth, it was given to you. <em>(commas and colon added)</em></li>
<li><strong>All transcripts</strong>: Straight apostrophes (') → Curly apostrophes (’)</li>
</ul>
<h4>Speaker labels</h4>
<p>The old italic label after each quote is now split. Name and place go in the audio credit and the portrait caption. Their work goes in a sentence in the portrait caption. "LA" was dropped from places, since every place is in Louisiana.</p>
<ul>
<li><strong>Vinton</strong>: Sister Helen Vinton, New Iberia, LA: Implemented Buffer Zone for Pesticides → Sister Helen Vinton, New Iberia. Put a buffer zone for pesticides in place.</li>
<li><strong>Bourg</strong>: Lorna Bourg, New Iberia, LA: Established Buffer Zones for Pesticides → Lorna Bourg, New Iberia. Established buffer zones for pesticides.</li>
<li><strong>Robert</strong>: Theresa Robert, Burnside, LA: Responsible for the IT Decision → Theresa Robert, Burnside. Responsible for the IT decision.</li>
<li><strong>Ramirez</strong>: Debra Ramirez, Lake Charles, LA: Home Adjacent to Petro Chemical Plants; Fought Several Petro Chemical Companies Regarding Ground Water Contamination → Debra Ramirez, Lake Charles. Lived next to petrochemical plants and fought several petrochemical companies over groundwater contamination. <em>("Petro Chemical" → "petrochemical", "Ground Water" → "groundwater")</em></li>
<li><strong>Subra</strong>: Wilma Subra, New Iberia, LA: Responsible for Organizing and Representing, Furnished Technical Data to Numerous Groups → Wilma Subra, New Iberia. Organized and represented communities, and furnished technical data to numerous groups. <em>("communities" added: the old label left "organizing and representing" without an object)</em></li>
<li><strong>Tutweiler</strong>: Mary Tutweiler, New Iberia, LA: Prevented Creation of a Commercial Solid Waste Landfill → Mary Tutweiler, New Iberia. Prevented the creation of a commercial solid waste landfill.</li>
<li><strong>Jackson</strong>: Rose Jackson, Belle Chasse, LA: Fights Issues in Chemical Corridor, Battled Hazardous Waste Landfill → Rose Jackson, Belle Chasse. Fights issues in the chemical corridor; battled a hazardous waste landfill.</li>
<li><strong>Baudoin</strong>: Clara Baudoin, House of Representatives, Carencro, LA: Baudoin and Flo Gossen were Responsible for the Closure of a Commercial Solid Waste Landfill → Clara Baudoin, Carencro. Member of the Louisiana House of Representatives. With Flo Gossen, she brought about the closure of a commercial solid waste landfill.</li>
<li><strong>Goldsmith</strong>: Shirley Goldsmith, Founded CLEAN; One of First Activists to Emerge in LA; Stopped Ocean Incinerator; Closed Willow Springs Landfill → Shirley Goldsmith. Founded CLEAN and was one of the first activists to emerge in Louisiana. Stopped an ocean incinerator and closed the Willow Springs Landfill.</li>
</ul>
<h4>About text</h4>
<ul>
<li><strong>About / contact link</strong>: mailto subject "Do Not Tear Up This Earth" → "Do Not Tear Up My Earth"</li>
<li><strong>About / book</strong>: Recently, the University Press of Mississippi published… → The University Press of Mississippi has since published… <em>("Recently" was dated; the book came out in 2013)</em></li>
<li><strong>About / opening</strong>: …an updated presentation originally created by the T. Harry Williams Center (THWCOH) in 2001, that features oral history excerpts… → …an updated presentation, first created by the T. Harry Williams Center for Oral History in 2001, of excerpts… <em>(abbreviation dropped, comma splice fixed)</em></li>
<li><strong>About / book</strong>: …authored by Peggy Frankland with Susan Tucker. The book provides insights on the roots of this important social movement amidst Louisiana's growing oil boom… → …by Peggy Frankland with Susan Tucker. The book traces the roots of this social movement amid Louisiana’s growing oil boom…</li>
<li><strong>About / holdings</strong>: All of the oral histories used in the book are housed with The T. Harry Williams Center… In addition, LSU Libraries Special Collections is the repository for… → …are held by the T. Harry Williams Center… LSU Libraries Special Collections is also the repository for…</li>
<li><strong>Intro (title page)</strong>: Frankland's ultimate goal was to gather stories to eventually write a book on the subject to document the valuable historical and cultural information that is often left out of mainstream history… the majority of whom were women. Frankland's work was aided by a grant… → Her goal was to gather stories for a book that would document the historical and cultural information so often left out of mainstream history… most of them women. The work was aided by a grant… <em>(tightened; the facts are unchanged)</em></li>
</ul>
<h4>Photos</h4>
<p>The old page's photos had a 6 px black frame (black lines with a transparent gap) baked into the image. It was cropped off; nothing else in the pictures changed.</p>
<h3>To verify</h3>
<ul><li><strong>"Responsible for the IT decision"</strong> (Theresa Robert): kept as written. It likely means the 1984 Louisiana Supreme Court ruling <em>Save Ourselves v. Louisiana Environmental Control Commission</em>, about the IT Corporation hazardous waste facility near Burnside. That isn't confirmed, so nothing was added. A short explanation would help visitors.
</li><li><strong>Clara Baudoin, "House of Representatives"</strong>: the old label didn't say whether she served in the House before, during or after the landfill fight. It now reads "Member of the Louisiana House of Representatives".
</li><li><strong>"Flo Gossen"</strong>: the spelling isn't confirmed.
</li><li><strong>Shirley Goldsmith</strong>: the old page gives no place for her, and CLEAN isn't spelled out. Possibly Calcasieu League for Environmental Action Now, but that isn't confirmed.
</li><li><strong>"Willow Springs Landfill"</strong> and <strong>"ocean incinerator"</strong>: kept as written.
</li><li><strong>Interview years and interviewers</strong>: per clip, unknown. Audio credits give only name and place. The old page says the interviews began in 1999 and the photos date from about 2000.
</li><li><strong>"1970–1990"</strong> in the subtitle: from the old title image. The clips themselves don't give dates.
</li><li><strong>Exhibition year 2001</strong>: from "originally created… in 2001". When the "updated presentation" was made is unknown.
</li><li><strong>Book year 2013</strong> (University Press of Mississippi): from general knowledge, not from the old page.
</li><li><strong>Book link</strong>: <a href="https://www.upress.state.ms.us/Books/W/Women-Pioneers-of-the-Louisiana-Environmental-Movement">https://www.upress.state.ms.us/Books/W/Women-Pioneers-of-the-Louisiana-Environmental-Movement</a> still answers, but it wasn't checked that it lands on the book's own page.
</li><li><strong>Contact</strong>: Jennifer A. Cramer's email (jabrah1@lsu.edu) is kept from the old page. Is she still the right contact?
</li><li><strong>Photographer</strong>: "Photography by Gabriella Mills ca. 2000, courtesy of Peggy Frankland" is applied to all the photos. It likely doesn't cover the editorial cartoon, and it may not cover the landfill sign.
</li><li><strong>Editorial cartoon</strong> (Robert entry): signed "Norwood". The newspaper line under the signature is unreadable at this size (possibly <em>The Daily Advertiser</em>). The rights are unknown. The label on David's shirt is also unreadable.
</li><li><strong>High-five photo</strong> (Baudoin entry, <code>picture16.jpg</code>): the two women aren't named on the old page, so the photo has no caption. Perhaps Baudoin and Flo Gossen.
</li><li><strong>Cover image</strong> (hands holding a globe, <code>earth_in_hands.jpg</code>): the full version of the old title art, taken from a hosted stock image the user believes is royalty-free. The source and license weren't confirmed.
</li><li><strong>Photo size</strong>: the photos are small (the largest is 1,450 px; Robert's is 166 × 287), so leads may look soft on large screens. Better scans may be available from Peggy Frankland.
</li></ul>
<h3>New wording to check</h3>
<h4>Clip titles</h4>
<p>All new, each taken from the speaker's own words; the old page had none:</p>
<ul><li>Awakening: The same family (Vinton); We deserve better (Bourg); David and the goliaths (Robert); Running from the plant (Ramirez)
</li><li>Hysterical Housewives: Hysterical housewives, raving idiots (Subra); A meddlesome housewife (Tutweiler)
</li><li>Why Women?: A mother’s child (Jackson); I wasn’t backing off (Baudoin); Don’t tear up My Earth (Goldsmith)
</li></ul>
<h4>Title page</h4>
<ul><li><strong>Credit line</strong>: "Presented by the T. Harry Williams Center for Oral History, LSU Libraries. A project directed by Peggy Frankland, with interviews by Peggy Frankland, Jennifer A. Cramer, and Sharon Dyer. Photography by Gabriella Mills, courtesy of Peggy Frankland."
</li><li><strong>Intro, first paragraph</strong>: "Oral history excerpts from a project documenting women’s involvement in Louisiana’s grassroots environmental movement, first presented by the T. Harry Williams Center for Oral History in 2001."
</li></ul>
<h4>Gallery lead captions</h4>
<ul><li>Awakening: "Debra Ramirez, Lake Charles. Photo by Gabriella Mills, courtesy of Peggy Frankland."
</li><li>Hysterical Housewives: "Mary Tutweiler, New Iberia. Photo by Gabriella Mills, courtesy of Peggy Frankland."
</li><li>Why Women?: "Clara Baudoin, Carencro. Photo by Gabriella Mills, courtesy of Peggy Frankland."
</li></ul>
<h4>Other captions</h4>
<ul><li>Cartoon: "David and Goliath: an editorial cartoon signed “Norwood,” with the giant labeled “Waste Management.”"
</li><li>Landfill sign: "The sign on the fence: Lafayette Municipal Landfill, final closure, May 31, 1988."
</li><li>Book cover (About the Project): "<em>Women Pioneers of the Louisiana Environmental Movement</em>, by Peggy Frankland with Susan Tucker (University Press of Mississippi, 2013)."
</li><li>Portrait captions: the speaker-label sentences in section 1.
</li></ul>
<h4>About the Project: Credits block</h4>
<p>The old page had these lines at the end of its About text; they are now regrouped:</p>
<p>"Interviews by Peggy Frankland, Jennifer A. Cramer, and Sharon Dyer, beginning in 1999, with a grant from the Louisiana Endowment for the Humanities. Photography by Gabriella Mills, ca. 2000, courtesy of Peggy Frankland. <em>This presentation is copyrighted by LSU Libraries Special Collections.</em>"</p>
<h4>Alt text</h4>
<p>All new; the old page had empty alt text except for two names:</p>
<ul><li>Cover, <code>earth_in_hands.jpg</code>: Cupped hands, in black and white, hold a small globe of the Earth in color against a black background.
</li><li><code>vinton.jpg</code>: Sister Helen Vinton, an older woman with short gray hair, looks down as she speaks.
</li><li><code>bourg.jpg</code>: Lorna Bourg, in wire-rimmed glasses, turns to her left in mid-sentence.
</li><li><code>robert.jpg</code>: Theresa Robert, with short dark hair and small earrings, looks down thoughtfully.
</li><li><code>comic.jpg</code>: Editorial cartoon: a giant warrior labeled “Waste Management” leans on his sword over a small David aiming a slingshot.
</li><li><code>ramirez.jpg</code>: Debra Ramirez speaks outdoors, with the towers of an industrial plant on the skyline behind her.
</li><li><code>subra.jpg</code>: Wilma Subra, with short curled hair and a slight smile, stands in front of a stone building.
</li><li><code>tutweiler.jpg</code>: Mary Tutweiler laughs on a set of wooden steps beside a teenage girl, who holds a speckled chicken.
</li><li><code>jackson.jpg</code>: Rose Jackson smiles, in a dark suit jacket and white blouse.
</li><li><code>baudoin.jpg</code>: Clara Baudoin beside the seal of the Louisiana House of Representatives on a glass door.
</li><li><code>picture15.jpg</code>: A sign on a chain-link fence: “Lafayette Municipal Landfill, Final Closure, May 31, 1988.”
</li><li><code>picture16.jpg</code>: Two women share a high five in a field of tall grass.
</li><li><code>goldsmith.jpg</code>: Shirley Goldsmith, with curly gray hair and a gingham shirt, speaks during her interview.
</li><li><code>bookcover.jpg</code>: Cover of Women Pioneers of the Louisiana Environmental Movement: a smokestack against an orange, smoky sky.
</li></ul>
<p>The teenage girl and the speckled chicken in the Tutweiler photo are my reading of a small picture.</p>
HTML),
]);

$section('Georgetown 272', [
  $text(NULL, <<<'HTML'
<p>Now at <a href="/v2/oralhistory/exhibitions/gu272">/v2/oralhistory/exhibitions/gu272</a>.</p>
<p>Exhibition: <em>Connecting Maryland’s Past to Louisiana’s Present: The Georgetown 272</em><br>Old site: <a href="https://lib.lsu.edu/sites/all/files/oralhistory/presentations/GU272/">https://lib.lsu.edu/sites/all/files/oralhistory/presentations/GU272/</a> (pages c1–c24, about.html, biographies menu on every page)<br>New: <code>scripts/data/georgetown-272.json</code>, built by <code>scripts/palefire_exhibit_georgetown_272.php</code></p>
<p>Clip numbers (c1–c24) are the old page names. Galleries: <strong>History</strong> (Enslaved and Segregated: c1–c4; Civil Rights: c5–c8; Growing Up: c9–c10), <strong>Descendants’ Discovery</strong> (Finding Out: c11–c13; Jesuits and Georgetown: c14–c18; Future Hopes: c19–c24), then <strong>About the Project</strong>.</p>
<p>Items marked <strong>(audio)</strong> change words the narrator or interviewer is heard saying. Check them against the recording.</p>
<h3>Corrected</h3>
<h4>House style, applied everywhere (not listed one by one below)</h4>
<ul><li>Straight quotes and apostrophes changed to curly ones: <code>'</code> → <code>’</code>, <code>"</code> → <code>“ ”</code>.
</li><li><code>--</code> and <code>-</code> used as dashes → <code>—</code> (em dash). <code>...</code> → <code>. . .</code> (spaced ellipsis, as in the Mossville exhibition).
</li><li>Commas added after introductory words and before “because” or “and” clauses where the sense needs a pause (“Well when” → “Well, when”, “You know it’s” → “You know, it’s”). Every entry except c1 has some of these.
</li><li>“Civil Rights” / “Civil Rights Movement” → “civil rights” / “civil rights movement” (lowercase) in titles and transcripts.
</li><li>Clip titles in sentence case, with no closing period.
</li><li>Speaker labels in bold on their own paragraphs: the full name the first time (“KARLY JOHNSON:”, “MARY WILLIAMS WAGNER:”), the surname after that.
</li><li>Tape timestamps removed from transcripts: [36:11] (c11), [18:33] (c14), [36:08] (c15), [02:12] (c18).
</li><li>The old pages’ broken paragraph-break characters (<code>&lt;br /&gt;?&lt;br /&gt;</code> in c11, <code>&lt;/br?&gt;</code> in c23) became normal paragraph breaks.
</li><li>“Link to Biography” links dropped. Each narrator’s biography now sits on their entry. On the old site every link but c1’s pointed nowhere, and c3’s opened Maxine Crump’s bio.
</li><li>Credit lines: “Name by Interviewer, Year” → “Name, interviewed by Interviewer, Year”. All-caps names are now written normally (“TONY CLAYTON” → “Tony Clayton”).
</li><li>Captions: “X being interviewed by Y” → “X is interviewed by Y”. Dates moved before “Photo by”. “LA” → “Louisiana”. “Jennifer Cramer” → “Jennifer A. Cramer” (c7, c22).
</li></ul>
<h4>Menus and gallery names</h4>
<ul>
<li><strong>Menu</strong>: DESCENDANTS’ DISCOVERY (all caps, with a stray trailing space) → Descendants’ Discovery</li>
<li><strong>Gallery</strong>: Enslaved/Segregated → Enslaved and Segregated</li>
<li><strong>Gallery</strong>: Jesuits &amp; Georgetown → Jesuits and Georgetown</li>
</ul>
<h4>Clip titles</h4>
<ul>
<li><strong>c2</strong>: The reality of the American Chattel System of Slavery. → The reality of the American chattel system of slavery</li>
<li><strong>c4</strong>: Priest who tried to integrate church seating → The priest who tried to integrate church seating</li>
<li><strong>c5</strong>: Sister In Law punished for Civil Rights Activities → Sister-in-law punished for civil rights activities</li>
<li><strong>c6</strong>: The attempted lynching and escape of Civil Rights Speaker, James Farmer, in Plaquemine, Louisiana → The attempted lynching and escape of civil rights speaker James Farmer in Plaquemine, Louisiana</li>
<li><strong>c9</strong>: …boundaries of their Catholic Church → …boundaries of their Catholic church</li>
<li><strong>c13</strong>: I'm more American than you! → “I’m more American than you!”</li>
<li><strong>c14</strong>: The frustration of not knowing your history. → The frustration of not knowing your history</li>
<li><strong>c15</strong>: Looking for acknowledgement, not apologies → Looking for acknowledgment, not apologies</li>
<li><strong>c18</strong>: Need to involve descendants on what reconcilliation looks like → Involve descendants in what reconciliation looks like</li>
<li><strong>c19</strong>: How can we look with new eyes at our own deed? → How can we look with new eyes at our own deeds? (the transcript says “deeds”)</li>
<li><strong>c22</strong>: We need to open up for critical research into where we are going as a nation → We need to open up critical research into where we are going as a nation</li>
</ul>
<h4>Credit lines (beyond the format change)</h4>
<ul>
<li><strong>c2, c8</strong>: Mélisande Short-Colomb by Hannah Richards, <strong>2017</strong> → …interviewed by Hannah Richards, <strong>2018</strong> (c21 and every photo of this interview say 2018; see To verify)</li>
<li><strong>c10</strong>: JESSICA TILSON by T, 2017 4700.2740 Tape 4712 → Jessica Tilson, interviewed by Thailandria Daniels, 2017 (the interviewer’s name was cut off; the accession and tape numbers were dropped)</li>
<li><strong>c14</strong>: CLARA CLAYTON by Russell Wilder, 2017 → Clara Ann Clayton, interviewed by Russell Wilder, 2017</li>
<li><strong>c17</strong>: RASHAAD JACKSON by Staci Shelby on April 5, 2017 → Rashaad Jackson, interviewed by Staci Shelby, April 5, 2017</li>
<li><strong>c18</strong>: KARRAN HARPER-ROYAL by Hannah Richards, 2018 → Karran Harper Royal, interviewed by Hannah Richards, 2018 (no hyphen, as in her biography heading)</li>
</ul>
<h4>Photos and captions the old pages had wrong</h4>
<ul>
<li><strong>c18 (Jesuits and Georgetown)</strong>: Photo DSC00952 captioned “Rashaad Jackson being interviewed by Staci Shelby at LSU. Photo by Tia Jones, April 5, 2017.” (c17’s caption) → “Karran Harper Royal, 2018.” The photo shows her, not Rashaad Jackson.</li>
<li><strong>c23 (Future Hopes)</strong>: c22’s Whitney Plantation photo, captioned “Rev. Leroy Baker and Onita Estes-Hicks, Georgetown University, 2017. Photo by Adelle M. Banks. From: Religious News Service April 18, 2017” → The photo that caption describes (<code>Leroy Baker webRNS-GEORGETOWN-SLAVERY5-041817.jpg</code>, in the old photos folder but unused), captioned “Rev. Leroy Baker and Onita Estes-Hicks at Georgetown University, April 18, 2017. Photo by Adelle M. Banks, Religion News Service.” (“Religious News Service” → “Religion News Service”)</li>
<li><strong>c24 (Future Hopes)</strong>: Photo of Jessica Tilson and Jonathan Earle, captioned with c23’s Leroy Baker caption → “Jessica Tilson and Jonathan Earle, dean of the LSU Honors College.”</li>
<li><strong>c5 (Civil Rights)</strong>: MorrisInterview_1 (491 px, also used on c3) → MorrisInterview_2 (1024 px, same sitting), same caption</li>
<li><strong>c10</strong>: “Jessica Tilson being interview by…” → “Jessica Tilson is interviewed by…”</li>
<li><strong>c4</strong>: “…at Southern University. Photos by Shaya Khorsandi, April, 2017” → “…at Southern University, April 2017. Photo by Shaya Khorsandi.”</li>
<li><strong>c11, c16</strong>: “Mary Williams Wagner speaking to Karly Johnson . Photo by Bret Fulmer, 2017” → “Mary Williams Wagner speaks with Karly Johnson, 2017. Photo by Bret Fulmer.”</li>
<li><strong>c13</strong>: “…at LSU’s Laville Honors House home by Hannah Richards…” → “…by Hannah Richards at LSU’s Laville Honors House…” (“home” dropped)</li>
<li><strong>c14</strong>: “Clara Clayton, 2017.” → “Clara Ann Clayton, 2017.”</li>
<li><strong>c7</strong>: “Maxine Crump guest lectures LSU and Georgetown Students, 2017.” → “Maxine Crump gives a guest lecture to LSU and Georgetown students, 2017.”</li>
<li><strong>c22</strong>: “Dr. Ibrahima Seck gives tour of Whitney Plantation to LSU and Georgetown students” → “Dr. Ibrahima Seck gives LSU and Georgetown students a tour of Whitney Plantation”</li>
<li><strong>c20</strong>: “Tony Clayton sitting in his home office” → “Tony Clayton in his home office”</li>
<li><strong>c8, c21</strong>: “…in her Georgetown University dorm…” → “…in her Georgetown University dorm room…”</li>
</ul>
<h4>Audio</h4>
<ul>
<li><strong>c11 Is this really us?</strong>: Played <code>Wagner_Mary_Williams_1.mp3</code>, the same file as c16 → <code>Wagner_Mary_Williams_correct.mp3</code> (unused on the old site; its 71 s fits c11’s longer transcript). <strong>Not checked by ear.</strong></li>
<li><strong>c18</strong>: <code>&lt;audio src="#"&gt;</code>: no recording → Entry built unpublished, its transcript kept in a text block (see To verify)</li>
</ul>
<h4>Transcript wording</h4>
<ul>
<li><strong>c1</strong>: It’s as almost as if → It’s almost as if</li>
<li><strong>c2</strong>: We as descendants. While → We as descendants . . . While</li>
<li><strong>c2</strong>: on the whole and individuals as well → on the whole, and as individuals as well <strong>(audio)</strong></li>
<li><strong>c4</strong>: mausoleum type deal → mausoleum-type deal</li>
<li><strong>c4</strong>: Look y’all need → Look, y’all need</li>
<li><strong>c5</strong>: [Evelyn’s sister in law] → [Evelyn’s sister-in-law]</li>
<li><strong>c5</strong>: sit in at Woolworth Store → sit in at the Woolworth store <strong>(audio)</strong></li>
<li><strong>c5</strong>: they were sitting-in → they were sitting in</li>
<li><strong>c6</strong>: then president John Kennedy → then-president John Kennedy</li>
<li><strong>c6</strong>: etcetera → et cetera</li>
<li><strong>c6</strong>: who’s my grandfather name → who’s my grandfather’s name <strong>(audio)</strong></li>
<li><strong>c6</strong>: they were looking in house to house → they were looking house to house <strong>(audio)</strong></li>
<li><strong>c6</strong>: CBS Evening News → <em>CBS Evening News</em> (italic)</li>
<li><strong>c7</strong>: Wrong finger pointing. → Wrong finger-pointing.</li>
<li><strong>c7</strong>: the ones that need to take ownership of. → the ones that need to take ownership. <strong>(audio)</strong></li>
<li><strong>c8</strong>: American born woman → American-born woman</li>
<li><strong>c8</strong>: any conversations about anybody about my Americanness → any conversations with anybody about my Americanness <strong>(audio)</strong></li>
<li><strong>c10</strong>: We still had to still use the back door → We still had to use the back door <strong>(audio)</strong></li>
<li><strong>c10</strong>: my friends in the north → my friends in the North</li>
<li><strong>c10</strong>: So why didn’t yall → So why didn’t y’all</li>
<li><strong>c11</strong>: Earle in Atlanta, was sending → Earle in Atlanta was sending</li>
<li><strong>c11</strong>: and I stared reading it → and I started reading it</li>
<li><strong>c12</strong>: Do you think finding that your direct descendant has impacted your feelings towards America’s foundation? → Do you think finding that you’re a direct descendant has impacted your feelings toward America’s foundation? <strong>(audio)</strong></li>
<li><strong>c12</strong>: ya know → you know <strong>(audio)</strong></li>
<li><strong>c12</strong>: how mentally and physically strong these 272 people had to be to not just to make it → …had to be, not just to make it</li>
<li><strong>c13</strong>: I used have my white friends—I'm more American than you! Your family came in the 1800's and I was already here. → I used to tell my white friends, “I’m more American than you! Your family came in the 1800s and I was already here.” <strong>(audio)</strong></li>
<li><strong>c14</strong>: I can't go b Looack and say → I can’t go back and say</li>
<li><strong>c14</strong>: great-great grandfathers → great-great-grandfathers</li>
<li><strong>c14</strong>: You just have to kinda shake it off → You just have to kind of shake it off <strong>(audio)</strong></li>
<li><strong>c14</strong>: But, it just give you → But it just give you</li>
<li><strong>c15</strong>: rename one of their buildings Isaac Hawkins → rename one of their buildings for Isaac Hawkins <strong>(audio)</strong></li>
<li><strong>c15</strong>: one of the things I asked when the president came → one of the things I asked for when the president came <strong>(audio)</strong></li>
<li><strong>c15</strong>: Being a dedication , dedicating → Being a dedication, dedicating</li>
<li><strong>c15</strong>: there’s a part of what’s going to happen on the eighteenth is there’s going to be → part of what’s going to happen on the eighteenth is there’s going to be <strong>(audio)</strong></li>
<li><strong>c15</strong>: acknowledgement (twice) → acknowledgment</li>
<li><strong>c16</strong>: tenants of the Catholic church → tenets of the Catholic Church</li>
<li><strong>c16</strong>: it kind of rocks the center of your core because who you think you are → …because [of] who you think you are</li>
<li><strong>c17</strong>: Protestant churches of all denomination, non-religious people → Protestant churches of all denominations, nonreligious people</li>
<li><strong>c18</strong>: the school, society of Jesus, and the Pope → the school, the Society of Jesus, and the Pope</li>
<li><strong>c18</strong>: ROYAL: (speaker label) → label dropped (one speaker)</li>
<li><strong>c19</strong>: one of the reason we study history → one of the reasons we study history</li>
<li><strong>c19</strong>: What are we doing - who are we sacrificing → What are we doing—who are we sacrificing</li>
<li><strong>c20</strong>: Do I subscribe and support that? → Do I subscribe to and support that? <strong>(audio)</strong></li>
<li><strong>c20</strong>: great-great-great-great-great grandfather → great-great-great-great-great-grandfather</li>
<li><strong>c21</strong>: communities where there are large compilations of descendants → …large concentrations of descendants <strong>(audio)</strong></li>
<li><strong>c21</strong>: for me to come here and paid to do this → for me to come here and [be] paid to do this</li>
<li><strong>c21</strong>: this opportunity [attend university] → this opportunity [to attend university]</li>
<li><strong>c21</strong>: descendant communities; especially → descendant communities, especially</li>
<li><strong>c22</strong>: Jim Crowe (twice) → Jim Crow</li>
<li><strong>c22</strong>: KKK era intimidation → KKK-era intimidation</li>
<li><strong>c22</strong>: a few moments of reconstruction → a few moments of Reconstruction</li>
<li><strong>c22</strong>: Plessy/Ferguson → Plessy v. Ferguson</li>
<li><strong>c22</strong>: hold on into the time Jim Crowe → hold on into the time of Jim Crow <strong>(audio)</strong></li>
<li><strong>c22</strong>: who we are as hand beings → who we are as human beings</li>
<li><strong>c22</strong>: So that, we continue → So that we continue</li>
<li><strong>c23</strong>: the transparency--Let us communicate → the transparency—let us communicate</li>
<li><strong>c23</strong>: research and archives that, we're not coming → research and archives, that we’re not coming</li>
<li><strong>c23</strong>: non-academic → nonacademic</li>
<li><strong>c24</strong>: [background noise of river barge traffic]. → [background noise of river barge traffic]</li>
</ul>
<h4>Introduction (from about.html)</h4>
<ul>
<li><strong>Georgetown University in Washington D.C.</strong>: Georgetown University in Washington, D.C.</li>
<li><strong>founded as Georgetown College by the Jesuit Order</strong>: …by the Jesuit order</li>
<li><strong>Priests administering the college in 1838 sold 272 people – enslaved individuals – in order to pay off debts</strong>: In 1838, the priests administering the college sold 272 people—enslaved men, women, and children—to pay off debts</li>
<li><strong>These men, women and children were sent to New Orleans</strong>: They were sent to New Orleans</li>
<li><strong>Grosse-Tête</strong>: Grosse Tête</li>
<li><strong>researchers uncovered details about the sale digging through the Georgetown University archives</strong>: details of the sale were uncovered by researchers digging through the Georgetown University archives</li>
<li><strong>African-American ancestry</strong>: African American ancestry</li>
<li><strong>Information regarding the Georgetown sale … a significant trove of archival data</strong>: Information about the Georgetown sale … a significant trove of archival records</li>
<li><strong>In Spring 2017</strong>: In spring 2017</li>
<li><strong>Students enrolled in the class</strong>: Students in the class</li>
<li><strong>Two students from that class were awarded the Roger Hadfield Ogden Leaders Scholarship this past year to continue the work they began in class. As part of their work Hannah Richards and Kaitryana Leinbach have conducted additional oral histories regarding the GU272 … and co-curated this exhibit.</strong>: Two students from that class, Hannah Richards and Kaitryana Leinbach, were awarded the Roger Hadfield Ogden Leaders Scholarship to continue the work they began in class. As part of that work they conducted additional oral histories about the GU272 … and co-curated this exhibition.</li>
</ul>
<h4>About the Project (from about.html)</h4>
<ul>
<li><strong>Rodger Hadfield Ogden Honors College</strong>: Roger Hadfield Ogden Honors College</li>
<li><strong>T. Harry William Center for Oral History as well as for making this project possible</strong>: T. Harry Williams Center for Oral History for making this project possible</li>
<li><strong>- Co-Curators, Kaitryana M. Leinbach &amp; Hannah N. Richards</strong>: Co-curators Kaitryana M. Leinbach and Hannah N. Richards</li>
<li><strong>Acknowledgements</strong>: Acknowledgments</li>
<li><strong>Russell Wilder, Alexis Baptiste and Cindy Sonbuchner … for assistance</strong>: Russell Wilder, Alexis Baptiste, and Cindy Sonbuchner … for their assistance</li>
<li><strong>Curators also thank LSU Honors College Dean Jonathan Earle and the students from the 2017 Spring Honors 2030 as well as the narrators who shared their stories.</strong>: The curators also thank LSU Honors College Dean Jonathan Earle, the students of the Spring 2017 Honors 2030 class, and the narrators who shared their stories.</li>
<li><strong>Biographies of narrators were written by the students who interviewed them for their final project in the Honors 2030 Class, 2017.</strong>: The narrators’ biographies were written by the students who interviewed them, as their final project in the Honors 2030 class, 2017.</li>
</ul>
<h4>Biographies (from the Biographies menu)</h4>
<p>Applied to every bio: “currently” dropped where it adds nothing, numbers spelled out in running text (“13-year” → “thirteen-year”, “16th” → “sixteenth”), “Month Nth, Year” → “Month N, Year”, and light rewording for grammar. The changes:</p>
<ul>
<li><strong>Leroy Baker</strong>: heading “Leroy Bakery” → Leroy Baker</li>
<li><strong>Leroy Baker</strong>: And is the descendant of great, great, great grandson of Nace and Biby Butler sold by Jesuit priest, Thomas F. Mulledy, to Jesse Batey → He is the great-great-great-grandson of Nace and Biby Butler, sold by the Jesuit priest Thomas F. Mulledy to Jesse Batey</li>
<li><strong>Leroy Baker</strong>: to pursue his priesthood → to pursue the priesthood</li>
<li><strong>Leroy Baker</strong>: Baker aas the first African-American allowed in the New Orleans Athletic Club in the 1980s. Spent most of working career → Baker was the first African American allowed in the New Orleans Athletic Club, in the 1980s. He spent most of his working career</li>
<li><strong>Leroy Baker</strong>: Lee is currently in 16th year of teaching at Archbishop Rummel High School as a theology teacher, and will soon start teaching higher learning at Tulane University. Found out in 2004 family reunion → Lee is in his sixteenth year of teaching theology at Archbishop Rummel High School and will soon start teaching at Tulane University. He found out at a 2004 family reunion</li>
<li><strong>Clara Ann Clayton</strong>: Valero, California → Vallejo, California (see To verify)</li>
<li><strong>Clara Ann Clayton</strong>: moved up to Port Allen … where she still resides to this day → moved to Port Allen … where she still lives</li>
<li><strong>Clara Ann Clayton</strong>: multiple masonic societies, including Eastern Star → several Masonic societies, including the Eastern Star</li>
<li><strong>Tony Clayton</strong>: June 6th in 1963 → June 6, 1963</li>
<li><strong>Tony Clayton</strong>: B.S in Political Science → B.S. in political science</li>
<li><strong>Tony Clayton</strong>: his curre¬¬nt wife, Paula Clayton → his wife, Paula Clayton (stray characters removed)</li>
<li><strong>Tony Clayton</strong>: serves on Southern University’s Board of Advisors, a position he has held for twenty years → has served on Southern University’s Board of Advisors for twenty years</li>
<li><strong>Maxine Crump</strong>: the Dialogue on Race Series Louisiana → the Dialogue on Race Louisiana series</li>
<li><strong>Maxine Crump</strong>: one of the 272 slaves sold by Georgetown University → one of the 272 enslaved people sold by Georgetown</li>
<li><strong>Maxine Crump</strong>: the New York Times article that initially broke the story → the <em>New York Times</em> article that first broke the story</li>
<li><strong>Maxine Crump</strong>: the Vice President of → the vice president of</li>
<li><strong>Michelle Harrington</strong>: Catholic High in Pointe Coupe Parish → Catholic High in Pointe Coupee Parish</li>
<li><strong>Michelle Harrington</strong>: started as a part-time receptionist with then WRBT-TV → started as a part-time receptionist at what was then WRBT-TV</li>
<li><strong>Michelle Harrington</strong>: Program Director, Station Manager → program director, station manager</li>
<li><strong>Rashaad Jackson</strong>: Katherine Jackson Nee Coleman → Katherine Jackson (née Coleman)</li>
<li><strong>Rashaad Jackson</strong>: New Orleans Center of Science and Mathematics → New Orleans Center for Science and Mathematics</li>
<li><strong>Delores Johnson</strong>: working with her sister, Mary, and others → working with her sister Mary and others</li>
<li><strong>Delores Johnson</strong>: Johnson has several children, including one son, and grandchildren. → Johnson has several children and grandchildren. (“including one son” dropped; see To verify)</li>
<li><strong>Delores Johnson</strong>: non-profit → nonprofit</li>
<li><strong>Carolyn Morris</strong>: Master’s in Music Education → master’s in music education</li>
<li><strong>Carolyn Morris</strong>: vice-principal → vice principal</li>
<li><strong>Ethel Murphy</strong>: Shady Brook high school → Shady Brook High School</li>
<li><strong>Bernadine Poole</strong>: a building that has since been turned into a bar room → a building that has since become a bar</li>
<li><strong>Bernadine Poole</strong>: T.A. Levy High School → T. A. Levy High School</li>
<li><strong>Bernadine Poole</strong>: researching her lineage with regards to the Georgetown 272 → researching her lineage and its ties to the Georgetown 272</li>
<li><strong>Bernadine Poole</strong>: IT Support Spec 2 → IT Support Specialist 2</li>
<li><strong>Bernadine Poole</strong>: Poole is unmarried, and she has no children. → sentence dropped (see To verify)</li>
<li><strong>Adam Rothman</strong>: 19th century U.S history → nineteenth-century U.S. history</li>
<li><strong>Adam Rothman</strong>: Beyond Freedom's Reach… → <em>Beyond Freedom’s Reach…</em> (italic)</li>
<li><strong>Adam Rothman</strong>: Government Documents Roundtable → Government Documents Round Table</li>
<li><strong>Adam Rothman</strong>: Adam served on → Rothman served on</li>
<li><strong>Karran Harper Royal</strong>: August 2nd 1963 → August 2, 1963</li>
<li><strong>Karran Harper Royal</strong>: the New Orleans Projects → the New Orleans projects</li>
<li><strong>Karran Harper Royal</strong>: Batten’s Disease → Batten disease</li>
<li><strong>Karran Harper Royal</strong>: a Special Educational advocate in the New Orleans School Area → a special education advocate in New Orleans</li>
<li><strong>Karran Harper Royal</strong>: Executive Director → executive director</li>
<li><strong>Karran Harper Royal</strong>: to reconnect her and the families torn apart → to reconnect the families torn apart</li>
<li><strong>Mélisande Short-Colomb</strong>: St. Elmo Bauduit Short Senior → St. Elmo Bauduit Short Sr.</li>
<li><strong>Mélisande Short-Colomb</strong>: She received her high school diploma and graduated from college. → She graduated from high school and college.</li>
<li><strong>Mélisande Short-Colomb</strong>: has been a working as a professional culinarian → has worked as a professional culinarian</li>
<li><strong>Mélisande Short-Colomb</strong>: Judy Riffel from the Georgetown memory project → Judy Riffel of the Georgetown Memory Project</li>
<li><strong>Mélisande Short-Colomb</strong>: Her three times maternal great-grandparents Mary-Ellen Queen and Abraham Mahoney → Her great-great-great-grandparents on her mother’s side, Mary Ellen Queen and Abraham Mahoney (see To verify)</li>
<li><strong>Mélisande Short-Colomb</strong>: arrived in Louisiana abroad a boat called the Uncas → arrived in Louisiana aboard a ship called the <em>Uncas</em></li>
<li><strong>Mélisande Short-Colomb</strong>: She participates in geological seminars → She takes part in genealogical seminars</li>
<li><strong>Mélisande Short-Colomb</strong>: the ‘GU 272 Descendants Association → the GU272 Descendants Association</li>
<li><strong>Mélisande Short-Colomb</strong>: granted Legacy Status → granted legacy status</li>
<li><strong>Debra Tilson</strong>: Mrs. Debra Tilson → Debra Tilson</li>
<li><strong>Debra Tilson</strong>: aided by the amateur genealogist work of Millie → aided by the amateur genealogy of her daughter Jessica (“Millie”) (see To verify)</li>
<li><strong>Jessica Tilson</strong>: November 19th, 1981 → November 19, 1981</li>
<li><strong>Jessica Tilson</strong>: the flood in the Summer of 2016 → the flood of summer 2016</li>
<li><strong>Jessica Tilson</strong>: dropped out to assist her daughter with her gymnastic career → left to support her daughter’s gymnastics career</li>
<li><strong>Jessica Tilson</strong>: aspires to enter law school → hopes to go to law school</li>
<li><strong>Jessica Tilson</strong>: early 2000’s → early 2000s</li>
<li><strong>Jessica Tilson</strong>: working with the GU 272 to help discover more descendants → working with GU272 descendants to find more of their kin (see To verify)</li>
<li><strong>Mary Williams Wagner</strong>: lead her to Hawaii → led her to Hawaii</li>
<li><strong>Mary Williams Wagner</strong>: joined Oahu’s police department … Maui’s police department → joined the Oahu police department … the Maui police department</li>
<li><strong>Mary Williams Wagner</strong>: After divorcing her husband of five years → After divorcing (“of five years” dropped; see To verify)</li>
<li><strong>Mary Williams Wagner</strong>: Records and Identification Section Manager → Records and Identification Section manager</li>
<li><strong>Mary Williams Wagner</strong>: democratic campaigns → Democratic campaigns</li>
</ul>
<h3>To verify</h3>
<p><strong>Recordings</strong></p>
<ul><li><strong>c18, Karran Harper Royal.</strong> There is no recording, either in the old audio folder or on the live server; the old page’s player is <code>&lt;audio src="#"&gt;</code>. The entry is built unpublished. Does the center have this clip?
</li><li><strong>c11 / c16, Mary Williams Wagner.</strong> The old site played one file for both. c11 now uses <code>Wagner_Mary_Williams_correct.mp3</code> (71 s) and c16 uses <code>Wagner_Mary_Williams_1.mp3</code> (54 s). This was chosen by length only. Listen to both.
</li><li><strong>c10, Segregation in the 1990s.</strong> The transcript has 168 words but the clip is only 39 s (4.3 words a second; the others run 1.4 to 3.5). The clip may be cut short, or the transcript may cover more than the clip. An unused <code>Tilson_Jessica_1.mp3</code> (33 s) may be related.
</li><li><code>Short-Colomb_Melisande_3.mp3</code> on the old site is empty (0.06 s). Is a clip missing?
</li><li>Every transcript line marked <strong>(audio)</strong> in section 1.
</li></ul>
<p><strong>Dates, names, credits</strong></p>
<ul><li><strong>Short-Colomb interview year.</strong> c2 and c8 said 2017; c21 and every photo of the interview (Georgetown dorm, January 2018) say 2018. Changed to 2018 throughout.
</li><li><strong>c18 photo (DSC00952).</strong> I identified Karran Harper Royal from the photo itself. Date (2018) and photographer are unknown.
</li><li><strong>c24 photo.</strong> No date or photographer. Jonathan Earle’s title is given as “dean of the LSU Honors College”, as on the About page.
</li><li><strong>Cover photo</strong> (Healy Hall, Georgetown University, <code>DSC00739_.JPG</code>). No credit in the source.
</li><li><strong>c5 photo.</strong> MorrisInterview_2 is assumed to be by the same photographer as MorrisInterview_1 (Sophia Spring).
</li><li><strong>c13 photo.</strong> It shows Jessica Tilson at the Laville Honors House in 2018, while the clip is from her 2017 interview with Thailandria Daniels. The caption is as the old site had it.
</li><li><strong>Harper Royal / Harper-Royal.</strong> The c18 credit had a hyphen; her bio heading does not. No hyphen is used.
</li><li><strong>Ethel Murphy / Murphey.</strong> The bio says Murphy; the old photo files are named <code>Murphey_Ethel</code>.
</li><li><strong>Clara Ann Clayton’s birthplace.</strong> The old bio said “Valero, California”. There is no Valero in California; changed to Vallejo.
</li><li><strong>Rashaad Jackson’s school.</strong> Changed “New Orleans Center of Science and Mathematics” to “for”. Confirm the name.
</li><li><strong>Bernadine Poole’s title.</strong> “IT Support Spec 2” expanded to “IT Support Specialist 2”.
</li><li><strong>Short-Colomb’s ancestors.</strong> “three times maternal great-grandparents” is read as great-great-great-grandparents on her mother’s side. Also “Mary-Ellen” is written “Mary Ellen”.
</li><li><strong>Debra Tilson’s bio</strong> said “the amateur genealogist work of Millie”. Millie is taken to be her daughter Jessica, whose own bio gives that nickname.
</li><li><strong>Jessica Tilson’s bio.</strong> “working with the GU 272” is rendered as “working with GU272 descendants”. It may have meant the GU272 Descendants Association.
</li><li><strong>Details dropped from bios.</strong> Poole: “Poole is unmarried, and she has no children.” Johnson: “including one son”. Wagner: “husband of five years”. Restore them if the curators want them.
</li><li><strong>No biography for Elizabeth Thomas</strong> (c12) on the old site.
</li><li><strong>Evelyn Georgetown Pugh’s bio</strong> has no student author.
</li><li><strong>c5 bracket “[Evelyn’s sister-in-law]”.</strong> The speaker is Carolyn Morris. Whose sister-in-law is meant?
</li></ul>
<p><strong>Left as spoken, but factually off (no change made)</strong></p>
<ul><li>c6: Tony Clayton calls the speaker “Spivey Gordon” and the transcript adds [James Farmer]. He places Walter Cronkite’s Kennedy interview in Nantucket (it was at Hyannis Port). He says “cattle prongs” (likely cattle prods).
</li><li>c22: “Plessy v. Ferguson in 1898” (decided 1896).
</li><li>c13: “1770”, Jessica Tilson’s earliest ancestor.
</li><li>c4, c7, c14, c17, c20, c23: nonstandard grammar as spoken (“you not going to find”, “the three fingers that’s pointing”, “it just give you”, “make people feel bad and better”, “Isaac Hawkins’ relatives who have somewhat worked hard”, “let us communicate with us”).
</li></ul>
<p><strong>Rights</strong></p>
<ul><li>c23’s photo is Religion News Service (Adelle M. Banks). Do we have permission to use it on the web?
</li><li>c6 is a 1963 newspaper clipping from Chronicling America (public domain).
</li></ul>
<p><strong>Image size.</strong> These sources are small, and the masters are never enlarged: MorrisInterview_1 (491 px), Clayton_Tony (620 px), Detroit Tribune clipping (631 px wide), Jackson (767 px), Leroy Baker RNS (800 px). Larger originals would help.</p>
<h3>New wording to check</h3>
<p><strong>Title page</strong></p>
<ul><li>Subtitle: “Oral histories of the descendants of the 272 people Georgetown sold in 1838”
</li><li>Credit line: “An LSU Honors College project with the T. Harry Williams Center for Oral History, LSU Libraries. Co-curated by Kaitryana M. Leinbach and Hannah N. Richards, Roger Hadfield Ogden Leaders Scholarship recipients, with Jennifer A. Cramer.”
</li><li>Year tile: 2018. Cover: Healy Hall (decorative, no caption).
</li></ul>
<p><strong>Gallery lead captions</strong> (each gallery’s lead image is its first clip’s photo, with that photo’s caption)</p>
<ul><li>Enslaved and Segregated: “Maxine Crump and Dominique Angibeau, 2017. Photo by Hannah Richards.”
</li><li>Civil Rights: “Carolyn Morris in her home in Zachary, Louisiana, 2017. Photo by Sophia Spring.”
</li><li>Growing Up: “Michelle Crump Harrington, April 6, 2017. Photo by Zoe Williamson.”
</li><li>Finding Out: “Mary Williams Wagner speaks with Karly Johnson, 2017. Photo by Bret Fulmer.”
</li><li>Jesuits and Georgetown: “Clara Ann Clayton, 2017. Photo by Russell Wilder.”
</li><li>Future Hopes: “Adam Rothman is interviewed in his office by Kaitryana Leinbach, 2018. Photo by Hannah Richards.”
</li></ul>
<p><strong>Captions written new</strong></p>
<ul><li>c6: “‘Vote Workers Jailed in South,’ <em>Detroit Tribune</em>, August 24, 1963, reporting the arrest of James Farmer in Plaquemine. Chronicling America, Library of Congress.” (old: “Detroit Tribune, August 24, 1963. ChroniclingAmerica.loc.gov”)
</li><li>c18: “Karran Harper Royal, 2018.”
</li><li>c23: “Rev. Leroy Baker and Onita Estes-Hicks at Georgetown University, April 18, 2017. Photo by Adelle M. Banks, Religion News Service.”
</li><li>c24: “Jessica Tilson and Jonathan Earle, dean of the LSU Honors College.”
</li></ul>
<p><strong>Other new text</strong></p>
<ul><li>c18 note above the transcript (editors only while unpublished): “Karran Harper Royal, interviewed by Hannah Richards, 2018. Recording not found.”
</li><li>About › More narrators intro: “The narrators’ biographies were written by the students who interviewed them, as their final project in the Honors 2030 class, 2017. These narrators were also interviewed for the project.” It is followed by the bios of Evelyn Georgetown Pugh, Delores Johnson, Ethel Murphy and Bernadine Poole, who have no clip in the exhibition.
</li></ul>
<p><strong>Alt text (all new; the old site had none or reused the caption)</strong></p>
<ul>
<li><strong>c1 Crump_Maxine_1</strong>: Dominique Angibeau stands smiling beside Maxine Crump, who is seated in an armchair in a living room under a framed painting of a New Orleans street.</li>
<li><strong>c2 DSC00808</strong>: Hannah Richards, a laptop on her lap, sits in a rocking chair facing Mélisande Short-Colomb, who sits cross-legged on a dorm bed and gestures as she talks.</li>
<li><strong>c3 MorrisInterview_1</strong>: Carolyn Morris, in a red shirt, leans on the arm of a leather recliner beside a side table with a microphone on it.</li>
<li><strong>c4 Tilson_Debra_1</strong>: Debra Tilson talks with Taylor Stirling across a table with a microphone and recorder, in a cinder-block room with an empty bookcase.</li>
<li><strong>c5 MorrisInterview_2</strong>: Carolyn Morris rests in a leather recliner across from an interviewer holding notes, a microphone on the side table between them.</li>
<li><strong>c6 Detroit Tribune</strong>: Newspaper column headlined “Vote Workers Jailed in South,” datelined Plaquemine, Louisiana, ending with a note that James Farmer was arrested there on August 19.</li>
<li><strong>c7 Crump guest lecture</strong>: Maxine Crump speaks to a row of students seated at tables in a wood-paneled classroom; one student holds a boom microphone toward her.</li>
<li><strong>c8, c21 DSC00839</strong>: Mélisande Short-Colomb, in black with reading glasses, sits cross-legged on a patterned bedspread and talks with open hands.</li>
<li><strong>c9 Harrington_3</strong>: Michelle Crump Harrington, in black, sits in a gray armchair with her hands folded, looking off to the side as she speaks.</li>
<li><strong>c10 Tilson_Jessica_2</strong>: Thailandria Daniels and Jessica Tilson laugh at a stone picnic table set with a microphone, a bridge over the Mississippi River behind them.</li>
<li><strong>c11 Wagner_Mary_1</strong>: Mary Williams Wagner speaks across a conference table to Karly Johnson, who has a laptop open, a microphone on a stand between them.</li>
<li><strong>c12 DSC00019</strong>: Hannah Richards, holding a printed outline, listens to Elizabeth Thomas across a dining table with a recorder and phone on it.</li>
<li><strong>c13 DSC00571</strong>: Jessica Tilson, in a Georgetown sweatshirt, talks with her hands clasped at a table beside a window while Hannah Richards listens, a laptop and recorder between them.</li>
<li><strong>c14 Clayton_Clara_Ann_4</strong>: Clara Ann Clayton, in glasses and a gray T-shirt, sits at a kitchen counter with her hands folded, smiling.</li>
<li><strong>c15 Harrington_2</strong>: Michelle Harrington gestures from an armchair in her living room as Rainey Charbonnet, a small white dog on her lap, takes notes on the sofa.</li>
<li><strong>c16 Wagner_Mary_3</strong>: Mary Williams Wagner talks at the far end of a conference table while Karly Johnson looks down at her laptop.</li>
<li><strong>c17 Jackson_Rashaad</strong>: Rashaad Jackson talks across a conference table, with a microphone and cassette recorder on it, to Staci Shelby in an LSU shirt.</li>
<li><strong>c18 DSC00952</strong>: Karran Harper Royal, in a blue scarf and gray cardigan, smiles with her arms resting on a table in a bright meeting room.</li>
<li><strong>c19 DSC00863</strong>: Adam Rothman, in a plaid shirt, gestures from a green office chair beside crowded bookshelves.</li>
<li><strong>c20 Clayton_Tony</strong>: Tony Clayton reads at a desk covered with papers, an antique map of Italy framed on the wall behind him.</li>
<li><strong>c22 Whitney Plantation</strong>: Ibrahima Seck speaks to students gathered in front of black granite walls engraved with the names of enslaved people at Whitney Plantation.</li>
<li><strong>c23 Leroy Baker RNS</strong>: Leroy Baker and Onita Estes-Hicks, in a white hat and suit, stand at a Georgetown University lectern before the university seal.</li>
<li><strong>c24 Tilson and Earle</strong>: Jessica Tilson, in a Georgetown sweatshirt, stands arm in arm with Jonathan Earle, in a shirt and tie, on the steps of a campus building.</li>
<li><strong>Cover DSC00739</strong>: Black-and-white photo of people walking past the steps of Healy Hall at Georgetown University.</li>
</ul>
<p>Points in the alt text that rest on reading the photo, to confirm: who is who in c4, c12 and c13 (the interviewer is named from the caption); the bridge in c10 (taken to be over the Mississippi, at Southern University); c17 (the interviewer’s shirt reads LSU); c22 (granite walls with names, taken to be Whitney’s Wall of Honor).</p>
HTML),
]);

$section('History of Graphic Design in South Louisiana', [
  $text(NULL, <<<'HTML'
<p>Now at <a href="/v2/oralhistory/exhibitions/graphicdesign">/v2/oralhistory/exhibitions/graphicdesign</a>.</p>
<p>Source: <code>_reference/Oralhistory Exhibitions/presentations/graphicdesign/</code> (c1–c10.html, about.html; live at <a href="https://lib.lsu.edu/sites/all/files/oralhistory/presentations/graphicdesign/c1.html">https://lib.lsu.edu/sites/all/files/oralhistory/presentations/graphicdesign/c1.html</a>).</p>
<p>Layout: gallery <strong>The Interviews</strong> (one entry per narrator; the old Biographies menu became each entry's biography), then gallery <strong>About the Project</strong> (from about.html). The old pages' sidebar teaser text was folded into the biographies where it added something.</p>
<p>Throughout, without listing each one: curly quotes and apostrophes, “. . .” for “…” and “...”, speaker labels in bold capitals (full name first, then surname; “Interviewer:” → “INTERVIEWER:”), titles of publications and films in italics, and small comma fixes.</p>
<h3>Corrected</h3>
<h4>Names (everywhere they appear)</h4>
<ul><li>“Micheal Deas” → “Michael Deas” (clip title on c6, biography heading).
</li><li>“Justin Sheals”, “Shiel’s”, “Justin Shiels” → “Justin Shiels” (menu, biography).
</li><li>“Peter A. Meyer” / “Meyer” → “Peter A. Mayer” / “Mayer” (clip title, transcripts, biography, caption). The agency, its website (peteramayer.com), the audio file name and the photographed clipping all read Mayer.
</li><li>“Popeye’s” → “Popeyes” (Peter A. Mayer, second clip).
</li></ul>
<h4>The Interviews: Mary Borne</h4>
<ul><li>Biography: “Mary Serena Gibbons A/K/A Ms. Weenie was born in 1916 in planes countryside Louisiana near Baton Rouge” → “Mary Serena Gibbons, known as Miss Weenie, was born in 1916 in the countryside near Baton Rouge” (see To verify).
</li><li>Biography: “life long nickname came from her brother’s pronouncement of her as teeny weeny” → “lifelong nickname came from her brother, who called her “teeny weeny””.
</li><li>Biography: “Mrs. Borne A/K/A Miss Weenie, was included in this … project” → “Mrs. Borne was included in the … project”.
</li><li>Biography, added from the sidebar teaser (which had the typos “educataion” and “dificulties”): “This recording is from her 2010 oral history session, when she was 94 years old; her quiet voice reflects her age at the time.”
</li><li>Caption: “literally down the bayou in South West Louisiana” → “literally down the bayou in south Louisiana” (Thibodaux is not in southwest Louisiana).
</li><li>Transcript: “Interviewer: And after you graduated from twelfth grade what did you do?” → comma added after “grade”; “He and mama” → “He and Mama”.
</li></ul>
<h4>The Interviews: Tom Varisco</h4>
<ul><li>Transcript: “the Institution of Politics at Loyola” → “the Institute of Politics at Loyola”.
</li><li>Transcript: “a republican or a democrat” → “a Republican or a Democrat”.
</li><li>Transcript: “this huge gathering of fundraising. People that were going to take part” → “this huge fundraising gathering, people that were going to take part”.
</li><li>Biography: “a well known regional branding company” → “a well-known regional branding company”; “The New Orleans Jazz &amp; Heritage Festival and Foundation, Inc. logo” → “the logo of the New Orleans Jazz &amp; Heritage Festival and Foundation, Inc.”; “8 ½, Annie Hall, Citizen Kane, Godfather I and II” → “8½, Annie Hall, Citizen Kane, and The Godfather I and II”.
</li><li>Biography, added from the sidebar: originally from Lafayette, calls himself a “design director”, opened his New Orleans studio more than thirty years before the 2010 recording.
</li><li>Caption: “And they have things to Say.” → “And they have things to say.”; “Artist Book Collection @ Paper Machine” → “Artist Book Collection at Paper Machine”.
</li></ul>
<h4>The Interviews: Don Smith</h4>
<ul><li>Transcript: “Don Smith:I said look, I’m a college graduate but” → “DON SMITH: I said, look, I’m a college graduate, but”.
</li><li>Biography: “drew a P-38, WWII fighter plane on his school looseleaf binder” → “drew a P-38, a World War II fighter plane, on his school loose-leaf binder”.
</li><li>Biography: “the Famous Artist School” → “the Famous Artists School”.
</li><li>Biography: “his fine art and long-advertising career … Such as the February 1962 magazine illustration, “Waterhole”.” → “his fine art and long advertising career … such as the February 1962 cover illustration “Waterhole.””
</li><li>Caption: “Photograph of Don Smith in his studio, ca 1961.” → “Don Smith in his studio, ca. 1961.”
</li></ul>
<h4>The Interviews: Kenny Harrison</h4>
<ul><li>Transcript: “Went to the School of Visual Arts. In Manhattan studying first fine arts.” → “Went to the School of Visual Arts in Manhattan, studying first fine arts.”
</li><li>Transcript: “like an arts geared school” → “like an arts-geared school”.
</li><li>Biography: “developed lots of techniques”, “lots of rejections” → “many techniques”, “many rejections”; “He ascribes his background in drawing to being able to adapt to the computer” → “he credits his background in drawing with his ability to adapt to the computer”; “up and coming” → “up-and-coming”; “Pulitzer Prize-winning” → “Pulitzer Prize–winning”.
</li></ul>
<h4>The Interviews: Gus Levy</h4>
<ul><li>Transcript: “I could remember on a couple of occasions” → “I can remember on a couple of occasions”; “I had to open the deceased eyes” → “the deceased’s eyes”; “you couldn’t do that in these days” → “you couldn’t do that these days”; “[Times] Picayune” → “[Times-]Picayune”.
</li><li>Biography: “I would align characters (set type)” (first person in a third-person biography) → “he would align characters (set type)”.
</li><li>Biography: “at Fortier High School in 1936-1940” → “from 1936 to 1940”; “divided-up between advertising, promotion, and editorial art staff” → “divided between the advertising, promotion, and editorial art staffs”; “uptown” → “Uptown”; “cast-type” → “cast type”.
</li><li>Biography: “Levy got a job as the art director at the New Orleans Godwin Advertising firm” → “Levy became art director at the Godwin Advertising firm in New Orleans”; “Their big clients include” → “included”.
</li><li>Caption: “the 250thAnniversary” (run-together) and the quoted series title reworded (see New wording).
</li></ul>
<h4>The Interviews: Michael Deas</h4>
<ul><li>Transcript: “its gonna stay put because its basic capitalism” → “it’s gonna stay put, because it’s basic capitalism”; “good old fashioned” → “good old-fashioned”; “(laughs)” → “[laughs]”.
</li><li>Biography: “the Ogden Museum of Art” → “the Ogden Museum of Southern Art”; “where he currently resides” → “where he lives today”; “His painting and drawing methods are not reliant on digital technology” → “do not rely on digital technology”.
</li><li>Caption: “oil on paper, 6 x 9” → “oil on paper, 6 x 9 in.”
</li></ul>
<h4>The Interviews: Justin Shiels</h4>
<ul><li>Biography: “Shiel’s black race is integral to his work” → “Shiels’s identity as a Black man is integral to his work”.
</li><li>Biography: “He moved to attend Loyola University New Orleans and graduated in 2007 with a major in graphic design and video concentration” → “He moved to New Orleans to attend Loyola University and graduated in 2007, majoring in graphic design with a concentration in video”.
</li><li>Biography: “Their church was a Southern Baptist, but not traditional because it was pro-gay” → “Their church was Southern Baptist, but not traditional: it was pro-gay”.
</li></ul>
<h4>The Interviews: Kathy Cain and Paulette Hurdlik</h4>
<ul><li>Transcript speaker: “Kathy Cain:” → “PAULETTE HURDLIK:” (the speaker says “based on what you just heard Kathy describe”; see To verify).
</li><li>Transcript: the question’s quotation marks didn’t match (“"what would you say … designer”) → “What would you say the percentage of engineer and the percentage of art is in being a designer?”; “80%” → “80 percent”.
</li><li>Biography: “were recording in December of 2009” → “were recorded in December 2009”; “To their memory, they were the first” → “To their knowledge, theirs was the first”; “Paulette and Kathy wound up co-president of ADDA” → “became co-presidents of ADDA”; “The New Orleans chapter of AIGA New Orleans Chapter was chartered in 1997” → “AIGA New Orleans was chartered in 1997”.
</li><li>Caption: “Kenny Harrisonand Tom Varisco”, “roster.Item 22.” (run-together) → fixed; “the Caine/Hurdlik recording” → “the Cain and Hurdlik recording”; “other HGD-SL subject memberships” → “two other narrators in this exhibition”.
</li></ul>
<h4>The Interviews: Byron Levy</h4>
<ul><li>Transcript: “British Guinea South America” → “British Guiana, South America”.
</li><li>Biography: “the 1961 studio/visual slideshow presentation” → “the 1961 audio/visual slide show” (as about.html describes it); “Ted Drell, and, Gus Levy” → “Ted Drell, and Gus Levy”.
</li><li>Biography: “technically-minded salespersons” → “technically minded salespeople”.
</li><li>Caption: “watercolor drawings” → “art” (one image is a print signed 1/1); run-together words (“taken.He”, “organizedand”) fixed; “nursing facility/community” → “retirement community”.
</li></ul>
<h4>The Interviews: Peter A. Mayer</h4>
<ul><li>Transcript: “Regional managers says” → “Regional manager says”; “sixty nine” → “sixty-nine”; “society editor of [Times] Picayune” → “of the [Times-]Picayune”.
</li><li>Transcript, second clip: “Okay, that a big one.” → “Okay, that’s a big one.”; “they had no agents” → “they had no agency”; “I said can we talk that was the only-” → “I said, “Can we talk?” That was the only—”; “I said, got to visit every Popeye’s location” → “I said, “Got to visit every Popeyes location””.
</li><li>Biography: “Fauche Highschool” → “Fauche High School” (see To verify); “the new field of television in where he worked” → “television, where he worked”; “founded the Peter Meyer advertising agency” → “founded Peter A. Mayer Advertising”; “at their job” → “at their jobs”; “the destruction of a primary competitor” → “the downfall of a primary competitor”.
</li><li>Caption: “the ca. 1969opening” → the year dropped (see To verify).
</li></ul>
<h4>About the Project</h4>
<ul><li>“AIGA New Orleans Chapter” → “AIGA New Orleans”; “south Louisiana–AIGA” (en dash used as a colon) → “south Louisiana: AIGA”.
</li><li>“Ca. 2006” → “Around 2006”; “terminated ca. 2017” → “taken down around 2017”; “The project captured fifteen oral histories” → “recorded fifteen oral histories”.
</li><li>“re-mastered” → “remastered”; “slide show” used throughout.
</li><li>Heading “Acknowledgements” → “Acknowledgments”.
</li><li>Thanks list: “Elizabeth Kelly” appeared twice → once.
</li><li>PRINT link updated to the magazine’s current address (printmag.com/design-culture/graphic-design-history-new-orleans/).
</li><li>The old Introduction (“History seems to know a lot …”, “Meet the fathers and daughters …”) is now the page intro.
</li></ul>
<h4>Links removed</h4>
<ul><li>Michael Deas biography: michaeldeas.com (no longer answers).
</li><li>Kenny Harrison biography: plaidpup.com (no longer answers).
</li></ul>
<h3>To verify</h3>
<ul><li><strong>Speaker in the Cain and Hurdlik clip.</strong> Relabeled Paulette Hurdlik from the words alone (“what you just heard Kathy describe”); confirm by listening. The old sidebar also credits the quote to Kathy Cain.
</li><li><strong>Mayer vs. Meyer.</strong> Changed to Mayer everywhere; confirm with the Center’s catalog record.
</li><li><strong>“Fauche High School”</strong> (Mayer biography): possibly a mishearing of Fortier High School. The clipping also calls him a University of Missouri journalism graduate, which the biography doesn’t mention.
</li><li><strong>Mayer agency founding year:</strong> the biography says 1967; the old caption said the clipping was from ca. 1969. The year is left out of the caption.
</li><li><strong>AIGA New Orleans charter year:</strong> 1997 in the Cain and Hurdlik biography, 1999 on the About page. Both kept.
</li><li><strong>ADDA founding:</strong> “founded in 1961” (About) vs. “In about 1960, Levy was instrumental in organizing” (Byron Levy biography).
</li><li><strong>Byron Levy interview year:</strong> 2009 (old sidebar, now his clip credit and biography) vs. “07/2008 … the day his oral history was taken” (caption). The caption’s closing line, “would have been 95 on his next birthday,” is kept but unclear.
</li><li><strong>“My career was of Tulane University”</strong> (Byron Levy transcript): kept word for word; possibly “out of”.
</li><li><strong>Mary Borne’s bank:</strong> “First National Bank of Thibodaux” (biography) vs. “Lafourche National Bank of Thibodaux” (her letterhead).
</li><li><strong>Mary Borne’s birthplace:</strong> the old “in planes countryside Louisiana” may name a place (Plains, near Zachary). Changed to “in the countryside near Baton Rouge”.
</li><li><strong>Kenny Harrison’s image:</strong> captioned as a detail from a 1995 ADDA Design Salon invitation, but the image is a modern digital illustration of an elephant on a bicycle. The image or the caption may be wrong.
</li><li><strong>“So we had, here’s Nancy Nation, for National Food”</strong> (Mayer, first clip): unclear in the old transcript; kept.
</li><li><strong>“[Ed Redwink?]”</strong> (Varisco transcript): the old transcript’s guess, kept.
</li><li><strong>“Charles Steinball”</strong> (Gus Levy biography) and <strong>“Dale Rhodies”</strong> (Acknowledgments): spellings not confirmed.
</li><li><strong>Biloxi as “nearby”</strong> (Byron Levy biography): kept.
</li><li><strong>Clip credits</strong> carry only the narrator and the year where the old site gave one; no interviewer is named anywhere in the source.
</li><li><strong>Kept links that block automated checks:</strong> tomvariscodesigns.com, justinshiels.com, and the T. Harry Williams Center link (www.lib.lsu.edu/oralhistory). Open them by hand.
</li><li><strong>Strong language kept as recorded:</strong> “asshole” (Justin Shiels, Andy Warhol clip), “bitched” (Mayer, second clip).
</li></ul>
<h3>New wording to check</h3>
<h4>Page</h4>
<ul><li>Subtitle: “Oral histories of commercial art in New Orleans and south Louisiana”.
</li><li>Credit line: “A listening station of the T. Harry Williams Center for Oral History, LSU Libraries, from the History of Graphic Design in South Louisiana project. Curated by Nancy Sharon Collins, with Jennifer A. Cramer. Made possible by the AIGA Katrina Relief Grant program, AIGA New Orleans, the Louisiana Cultural Economy Foundation, and Loyola University New Orleans Special Collections and Archives.”
</li><li>Intro, sentence added after the old Introduction: “… this project preserved the real thing: art directors, illustrators, printers, and designers who shaped the look of New Orleans and south Louisiana, in their own words.”
</li><li>Gallery titles: “The Interviews”, “About the Project”. About sections headed “Background”, “The Project”, “Acknowledgments”.
</li><li>Lead caption (The Interviews): “Engraved letterhead for Mrs. I. J. Borne, Vice President, Lafourche National Bank of Thibodaux.”
</li><li>Cover image: Byron Levy’s <em>Memories of Preservation Hall</em>. Gallery lead: the Borne letterhead.
</li></ul>
<h4>Clip titles (the old pages titled every clip with the narrator’s name)</h4>
<ul><li>Mary Borne: “Business school in the Great Depression”
</li><li>Tom Varisco: “‘My art guy’: designing political campaigns”
</li><li>Don Smith: “‘I want to work here’”
</li><li>Kenny Harrison: “Failing every subject except art”
</li><li>Gus Levy: “Retouching for the newsroom”
</li><li>Michael Deas: “Faster and cheaper wins: the move to digital”
</li><li>Justin Shiels: “Stripes against stripes”, “Andy Warhol”
</li><li>Kathy Cain and Paulette Hurdlik: “Designers are problem solvers”
</li><li>Byron Levy: “From engineering to the paper business”
</li><li>Peter A. Mayer: “Proving television”, “Winning Popeyes”
</li></ul>
<h4>Clip credits</h4>
<p>“Mary Borne, 2010”; “Tom Varisco, 2010”; “Don Smith”; “Kenny Harrison”; “Gus Levy”; “Michael Deas”; “Justin Shiels” (both clips); “Kathy Cain and Paulette Hurdlik, 2009”; “Byron Levy, 2009”; “Peter A. Mayer, 2012” (both clips).</p>
<h4>Captions rewritten</h4>
<ul><li>Gus Levy (all six images): “From a 1968 monograph on Gus Levy, one of *Upton’s Series of New Orleans Places and Things by New Orleans Graphic Artists*, “a series commissioned on the occasion of the 250th anniversary of the South’s most colorful city.” The over-size promotional brochures were produced by E. S. Upton Printing Company, later Upton Printing Co., in New Orleans.”
</li><li>Byron Levy (both images): “Byron Levy’s art, photographed in July 2008 while visiting Levy at his retirement community the day his oral history was taken. …”
</li><li>Justin Shiels: shortened to his website’s self-introduction (accessed October 13, 2020), without its opening “ALLOW ME TO INTRODUCE MYSELF / WHAT’S UP INTERNET!”.
</li><li>Peter A. Mayer: “Framed newspaper article announcing the opening of Peter A. Mayer Advertising. It includes Mayer’s professional history, including his time at the Fitzgerald Agency, at one time the largest advertising agency in the South. In the second clip, Mayer takes credit for its demise.”
</li></ul>
<h4>Alt text (all new)</h4>
<ul><li>Borne letterhead: “Blank sheet of letterhead with an embossed gold eagle, printed “Lafourche National Bank of Thibodaux” and “Mrs. I. J. Borne, Vice President.””
</li><li><em>Spoiled</em>: “Cover of the paperback Spoiled: a photo of a refrigerator door taped shut, with “SPOILED” spray-painted across it.”
</li><li>Don Smith: “Black-and-white photo of Don Smith standing at a worktable by a large window, painting, with jars of brushes beside him.”
</li><li>Kenny Harrison: “Colorful illustration of a smiling elephant riding a red bicycle with a horn along a white path through dense jungle leaves and flowers.”
</li><li>Upton brochure cover: “Orange brochure cover reading “Upton’s Series of New Orleans Places and Things by New Orleans Graphic Artists,” with an engraved seal in a yellow square.”
</li><li>Upton seal: “Engraved circular seal printed in black on yellow: two classical figures flank a landscape under a ring of stars, with an alligator below.”
</li><li>Upton title page: “Brochure page titled “Gus Levy” with a photo of Levy in a pith helmet and cigar, painting at an easel by the river, a ship behind him.”
</li><li>Upton text page: “Brochure page with “Gus Levy” over a yellow thumbprint and a short text about his topic, the Smoky Mary train.”
</li><li>Upton biography page: “Brochure page with a small photo of Gus Levy painting and a biography that closes “Upton Printing Company is proud to reproduce the work of Gus Levy.””
</li><li>Upton watercolor: “Watercolor titled “A Day With Smoky Mary”: a family in early 1900s dress hurries along a lakefront platform beside a smoking steam locomotive.”
</li><li>James Dean stamp: “Oil portrait of James Dean in a dark cable-knit turtleneck against a stormy sky.”
</li><li>Justin Shiels: “Justin Shiels, smiling, tips his hat in a yellow shirt and blue pants in front of a bright blue wooden building.”
</li><li>ADDA roster: “Typed list headed “ADDA Membership,” numbering 27 individual and corporate members with dates from 1989 and 1990.”
</li><li>Preservation Hall print: “Print in orange, blue and black of a jazz band, a pianist, trumpeter, trombonist and bassist, titled “Memories of Preservation Hall” and signed Byron Levy.”
</li><li>Byron Levy sketchbook: “Open sketchbook: a pencil sketch of a restaurant interior labeled “Black Bean” and a watercolor street scene labeled “Main Street, Rockland.””
</li><li>Mayer clipping: “Newspaper clipping headlined “Mayer Advertising Opens September 5th,” with a photo of Peter A. Mayer on the telephone.”
</li></ul>
HTML),
]);

$section('History of KLSU', [
  $text(NULL, <<<'HTML'
<p>Now at <a href="/v2/oralhistory/exhibitions/klsu">/v2/oralhistory/exhibitions/klsu</a>.</p>
<p>For the curators’ review. Source: the old listening-station exhibition at <a href="https://lib.lsu.edu/sites/all/files/oralhistory/presentations/KLSU/home.html">https://lib.lsu.edu/sites/all/files/oralhistory/presentations/KLSU/home.html</a> (local copy in <code>_reference/Oralhistory Exhibitions/presentations/KLSU/</code>). New page: <code>/v2/special/exhibitions/klsu</code>, built by <code>scripts/palefire_exhibit_klsu.php</code> from <code>scripts/data/klsu.json</code>.</p>
<p>Where: <em>Gallery / Entry (old slide file)</em>. Galleries are the call signs; entries are the dated slides.</p>
<h3>Corrected</h3>
<h4>Throughout</h4>
<ul><li>Straight quotes and apostrophes → typographic ones (“ ” ‘ ’), double hyphens (<code>--</code>) → em dashes, number ranges in captions (1886-1926, 1972-73) → en dashes.
</li><li>Speaker labels: each turn now starts its own paragraph with the label in bold (e.g. <strong>RON ROSS:</strong>, then <strong>ROSS:</strong>). The labels’ wording is unchanged; on the old slides turns ran together, split only by line breaks.
</li><li>Each clip’s citation (“Ralph Steetle Oral History Interview, Mss. 4700.0660, LLMVC, LSU Libraries.”) moved out of the description into the clip’s credit line (see section 3); the description sentence is kept as written, with the fixes below.
</li><li>Captions now use the fuller citations from the exhibition’s own Pictures page (<code>pictures.html</code>) instead of the short labels under each slide image. Where the slide and the Pictures page disagreed, the Pictures page won; the cases where that changed the source, not just the wording, are marked <strong>(different source)</strong>.
</li></ul>
<h4>Structure</h4>
<ul><li>KFGC / November 27, 1924 (C11): the old slide (also live) showed a Jazz Fest clip (Jim Olander, “by Joey O’Keefe, 2015,” audio/jim.mp3), not KLSU material. Replaced with the slide’s three KLSU clippings from the Pictures page (c11_1, c11_2, c11_3) and a new line of text (see section 3). The Jazz Fest clip is not carried over.
</li><li>Three slides had text but no image, and a wall entry needs an image, so each is folded into its neighbor as a dated paragraph:
<ul><li>KFGC / 1926–1931: “January 15, 1926” (C14) + “1931” (C15).
</li><li>KLSU / Fall 1994: “Fall 1994” (C80) + “September 1994” (C81).
</li><li>KLSU / 2015: “March 2015” (C85) + “October 2015” (C86).
</li></ul>
</li><li>KLSU / October 21, 1988 (C79): added c79_1.jpg (“Martini Mix,” Advocate, January 16, 1989), which is on the Pictures page but on no slide.
</li><li>Not carried over: draft slides linked from nowhere (C3_m, C4_m, C7_m, C7_m2, C26a, C80b, C82b); <code>acknowledgements.html</code> and <code>lsuag.html</code> (pages from the Tempests exhibition); the unused audio/BlueDucks_FourFlossFiveSix and photos 38_1.jpg, c82_1f.jpg, squarelogo.jpg and “1950 Oct 30 Chemistry Series Starts on WLSU.jpg” (a copy of c47_1).
</li></ul>
<h4>Entry titles (dates)</h4>
<ul><li>KFGC, 1915–1934 / Fall 1915 (C1.html): entry title "FALL 1915" → "Fall 1915"
</li><li>KFGC, 1915–1934 / January 4, 1924, 8:15 p.m. (C8.html): entry title "January 4, 1924 8:15 PM" → "January 4, 1924, 8:15 p.m."
</li><li>KFGC, 1915–1934 / November 27, 1924 (C11.html): entry title "Jim Olander" → "November 27, 1924"
</li><li>KFGC, 1915–1934 / 1926–1931 (C14.html): entry title "January 15, 1926" → "1926–1931"
</li><li>WJBO, 1934–1947 / November 5, 1941–May 6, 1942 (C29.html): entry title "November 5, 1941 - May 6, 1942" → "November 5, 1941–May 6, 1942"
</li><li>WLSU, 1947–1973 / Winter 1947–1948 (C42.html): entry title "Winter 1947-1948" → "Winter 1947–1948"
</li><li>WLSU, 1947–1973 / November 15, 1965, 3:00 p.m. (C55.html): entry title "November 15, 1965 3:00PM" → "November 15, 1965, 3:00 p.m."
</li><li>KLSU, 1983–2015 / 2015 (C86.html): entry title "October 2015" → "2015"
</li></ul>
<h4>Event text</h4>
<ul><li>KFGC, 1915–1934 / July 19, 1923 (C7.html): "KFGC granted a" → "KFGC is granted a"
</li><li>KFGC, 1915–1934 / July 19, 1923 (C7.html): "from Department of" → "from the Department of"
</li><li>KFGC, 1915–1934 / February 1924 (C10.html): "KFGC broadcasts the election of governor Hewitt Bouanchaud, who ran against Henry Fuqua and Huey P. Long." → "KFGC broadcasts the returns of the governor’s race between Hewitt Bouanchaud, Henry Fuqua and Huey P. Long."
</li><li>KFGC, 1915–1934 / March 15, 1934 (C16.html): "lead by" → "led by"
</li><li>WJBO, 1934–1947 / February 15, 1937 (C20.html): "broadcast first show" → "broadcast their first show"
</li><li>WJBO, 1934–1947 / November 6, 1939 (C26.html): "newscasts began" → "newscasts begin"
</li><li>WJBO, 1934–1947 / November 6, 1939 (C26.html): "Sponsored by Daily Reveille" → "Sponsored by the Daily Reveille"
</li><li>WJBO, 1934–1947 / July 1, 1942 (C30.html): "Completion of two" → "LSU completes two"
</li><li>WJBO, 1934–1947 / Fall 1946 (C36.html): "Lucille Ruby Woodard" → "Lucile Ruby Woodard"
</li><li>WLSU, 1947–1973 / October 16, 1947 (C39.html): "WLSU on air at 91.7 megacycles with 1,000 watt frequency." → "WLSU goes on the air at 91.7 megacycles with 1,000 watts of power."
</li><li>WLSU, 1947–1973 / October 18, 1947 (C40.html): "WLSU staff raised" → "WLSU staff raise"
</li><li>WLSU, 1947–1973 / October 18, 1947 (C40.html): "which was delayed" → "which is delayed"
</li><li>WLSU, 1947–1973 / March 15, 1948 (C43.html): "WLSU first college FM station" → "WLSU becomes the first college FM station"
</li><li>WLSU, 1947–1973 / September 17, 1950 (C46.html): "Weekly program "WLSU speaks" and daily programs" → "The programs are the weekly "WLSU Speaks" and the daily"
</li><li>WLSU, 1947–1973 / Fall 1955 (C51.html): "By this time WLSU had many more student workers and broadcasting hours had doubled." → "By this time WLSU has many more student workers, and its broadcasting hours have doubled."
</li><li>WLSU, 1947–1973 / November 15, 1965, 3:00 p.m. (C55.html): "donated equipment which allowed" → "donated the equipment that allowed"
</li><li>WLSU, 1947–1973 / November 15, 1965, 3:00 p.m. (C55.html): "closed circuit" → "closed-circuit"
</li><li>WLSU, 1947–1973 / 1973 (C61.html): "Edwards and the Louisiana Educational Television Authority (LETA) requesting" → "Edwards and the Louisiana Educational Television Authority (LETA), requesting"
</li><li>WLSU, 1947–1973 / 1973 (C61.html): "denies university funding and support citing" → "denies university funding and support, citing"
</li><li>KLSU, 1983–2015 / October 24, 1983 (C70.html): "Power increases from 8 watts to 5,000 watts and changed from 107.3 FM to 91.1 FM." → "Power increases from 8 watts to 5,000 watts, and the station moves from 107.3 FM to 91.1 FM."
</li><li>KLSU, 1983–2015 / November 29, 1984 (C72.html): "to The Manship School" → "to the Manship School"
</li><li>KLSU, 1983–2015 / Fall 1994 (C80.html): "controversial format change" → "a controversial format change"
</li><li>KLSU, 1983–2015 / 1997 (C83.html): "reel-to-reel technology" → "reel-to-reel technology."
</li></ul>
<h4>Clip descriptions</h4>
<ul><li>WJBO, 1934–1947 / Fall 1946 (C36.html), clip "Lucile Ruby Woodard on the beginnings of LSU radio" description: "LSU Professor Emeritus of Speech" → "LSU Professor Emerita of Speech"
</li><li>WLSU, 1947–1973 / December 1947 (C41.html), clip "Lucile Ruby Woodard on WLSU’s programming" description: "Program Director details" → "Program Director, details"
</li><li>WLSU, 1947–1973 / December 1947 (C41.html), clip "Lucile Ruby Woodard on WLSU’s programming" description: "LSU Professor Emeritus of Speech" → "LSU Professor Emerita of Speech"
</li><li>WLSU, 1947–1973 / June 1957 (C52.html), clip "Lucile Ruby Woodard on the end of WLSU" description: "Lucille Ruby" → "Lucile Ruby"
</li><li>KLSU, 1983–2015 / Fall 1983 (C69.html), clip "Dez Crawford on building the tower" description: "she and and a" → "she and a"
</li><li>KLSU, 1983–2015 / Fall 1994 (C80.html), clip "Andrew Nackley on the shift to alternative programming" description: "a KLSU alumni" → "a KLSU alumnus"
</li><li>KLSU, 1983–2015 / February 8, 2008 (C84.html), clip "Jenni Lawson on Dr. Jules d’Hemecourt" description: "Christmas,"and" → "Christmas," and"
</li></ul>
<h4>Transcripts</h4>
<ul><li>WJBO / May 1947 (C38), clip "Ralph Steetle on building WLSU" transcript: "So the building is gone,or the station?" → "So the building is gone, or the station?" (fixed 2026-11-19)
</li><li>WJBO, 1934–1947 / Fall 1938 (C22.html), clip "Ralph Steetle on his early influences" transcript: "'What's the purpose of this?''" → "'What's the purpose of this?'"
</li><li>WJBO, 1934–1947 / Fall 1938 (C22.html), clip "Ralph Steetle on his early influences" transcript: "a lot easier to do.""" → "a lot easier to do.""
</li><li>WJBO, 1934–1947 / Fall 1938 (C22.html), clip "Ralph Steetle on his early influences" transcript: "Music and Dramatics Arts building" → "Music and Dramatic Arts building"
</li><li>WLSU, 1947–1973 / Summer 1949 (C45.html), clip "Lucile Ruby Woodard on discs and magnetic tape" transcript: "fifteen-inch wax disks" → "fifteen-inch wax discs"
</li><li>KLSU, 1983–2015 / May 31, 1983 (C68.html), clip "Dez Crawford on winning back the KLSU call letters" transcript: "Lacrosse" → "La Crosse"
</li><li>KLSU, 1983–2015 / December 1987 (C77.html), clip "Jenni Lawson on the move to Hodges Hall" transcript: "to-do built around the," → "to-do built around the"
</li><li>KLSU, 1983–2015 / December 1987 (C77.html), clip "Jenni Lawson on the move to Hodges Hall" transcript: "bullet proof" → "bulletproof"
</li><li>KLSU, 1983–2015 / Fall 1994 (C80.html), clip "Andrew Nackley on the shift to alternative programming" transcript: "school of Journalism" → "School of Journalism"
</li><li>KLSU, 1983–2015 / Fall 1994 (C80.html), clip "Andrew Nackley on the shift to alternative programming" transcript: "from the 80s" → "from the ’80s"
</li><li>KLSU, 1983–2015 / Fall 1994 (C80.html), clip "Laura Blereau on the format change and the backlash" transcript: ""I'm sitting right in it"." → ""I'm sitting right in it.""
</li><li>KLSU, 1983–2015 / February 8, 2008 (C84.html), clip "Jenni Lawson on Dr. Jules d’Hemecourt" transcript: "Night before Christmas" → "Night Before Christmas"
</li><li>KLSU, 1983–2015 / February 8, 2008 (C84.html), clip "Dez Crawford on Dr. d’Hemecourt’s mentorship" transcript: "when you hit the. . . .when" → "when you hit the . . . when"
</li><li>KLSU, 1983–2015 / February 8, 2008 (C84.html), clip "Dez Crawford on Dr. d’Hemecourt’s mentorship" transcript: "barbeque" → "barbecue"
</li><li>KLSU, 1983–2015 / February 8, 2008 (C84.html), clip "Dez Crawford on Dr. d’Hemecourt’s mentorship" transcript: "Youtube" → "YouTube"
</li></ul>
<h4>Captions</h4>
<p>Old = the label under the image on the slide (or the lightbox title where the slide had none).</p>
<ul><li>KFGC, 1915–1934 / Fall 1915 (C1.html), fall1915_1.jpg: "Dr. David Vance Guthrie" → "David Vance Guthrie, M.A., Ph.D., a50000186, LSU Photograph Collection, 1886–1926, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA."
</li><li>KFGC, 1915–1934 / Fall 1915 (C1.html), fall1915_2.jpg: "Reveille Oct 6, 1915." → "“Wireless Station to Be Erected at L.S.U.,” The Reveille, October 6, 1915. Louisiana and Lower Mississippi Valley Collections, LSU Libraries, Baton Rouge, LA."
</li><li>KFGC, 1915–1934 / February 1916 (C2.html), c2_1.jpg: "The first chemistry building 1899" → "The first chemistry building, L.S.U., old campus, 1899, a50000007, LSU Photograph Collection, 1886–1926, LSU Archives, LSU Libraries."
</li><li>KFGC, 1915–1934 / February 1916 (C2.html), c2_2.jpg: "Physics Laboratory Heard Hall 1903" → "Physics Laboratory, Heard Hall, 1903, a50000030, LSU Photograph Collection, 1886–1926, LSU Archives, LSU Libraries."
</li><li>KFGC, 1915–1934 / March 1916 (C3.html), c3_1.jpg: "Wireless Station Receives Messages From New Orleans. The Reveille, March 2, 1916. LLMVC, LSU Libraries." → "“Wireless Station Receives Messages From New Orleans,” The Reveille, March 2, 1916. LLMVC, LSU Libraries."
</li><li>KFGC, 1915–1934 / April 1917 (C4.html), c4_1.jpg: "The Reveille, March 31, 1938." → "“First Campus Radio Station Forgotten in Current Growth,” The Reveille, March 31, 1938. LLMVC, LSU Libraries."
</li><li>KFGC, 1915–1934 / 1923 (C5.html), c5_1.jpg: "The Reveille, March 31, 1938." → "“First Campus Radio Station Forgotten in Current Growth,” The Reveille, March 31, 1938. LLMVC, LSU Libraries."
</li><li>KFGC, 1915–1934 / April 20, 1923 (C6.html), c6_1.jpg: "The Reveille, March 31, 1938." → "“First Campus Radio Station Forgotten in Current Growth,” The Reveille, March 31, 1938. LLMVC, LSU Libraries."
</li><li>KFGC, 1915–1934 / January 4, 1924, 8:15 p.m. (C8.html), c8_1.jpg: "Radio Station KFGC, Box 53, Folder 909, Louisiana State University Office of the Chancellor Records, A0001, LSUA, LSU Libraries." → "Radio Station KFGC, Box 53, Folder 909, Louisiana State University Office of the Chancellor Records, A0001, LSU Archives, LSU Libraries."
</li><li>WJBO, 1934–1947 / 1936 (C19.html), c19_1.jpg: ""L.S.U. Goes on the Air With Expanded Program," Louisiana Leader, October 1938. Louisiana Vertical File: Radio. LLMVC, LSU Libraries." → "“First Campus Radio Station Forgotten in Current Growth,” The Reveille, March 31, 1938. LLMVC, LSU Libraries." <strong>(different source)</strong>
</li><li>WJBO, 1934–1947 / August 1, 1937 (C21.html), c21_2.jpg: "Colbert Thesis page with Program Schedule" → "Program schedule. Nellie Graham Colbert, “A History of Radio Station WJBO, Baton Rouge, Louisiana, with Emphasis on Programming Policies and Practices, 1934–1952,” thesis, Louisiana State University, 1960. LSU Libraries."
</li><li>WJBO, 1934–1947 / August 1, 1937 (C21.html), c21_3.jpg: "Program schedule from Wednesday Nov. 3, 1937" → "Program schedule for Wednesday, November 3, 1937. Nellie Graham Colbert, “A History of Radio Station WJBO, Baton Rouge, Louisiana, with Emphasis on Programming Policies and Practices, 1934–1952,” thesis, Louisiana State University, 1960. LSU Libraries."
</li><li>WJBO, 1934–1947 / November 6, 1939 (C26.html), c26_1.jpg: ""Making an Aerial Deadline,"" The Reveille, November 3, 1939. LLMVC, LSU Libraries." → "“Making an Aerial Deadline,” The Reveille, November 3, 1939. LLMVC, LSU Libraries."
</li><li>WLSU, 1947–1973 / March 15, 1948 (C43.html), c43_2.jpg: "Tales of Hoffman: Gumbo, 1948. LSU Archives, LSU Libraries." → "“Tales of Hoffmann,” Gumbo, 1948. LSU Archives, LSU Libraries."
</li><li>WLSU, 1947–1973 / January 1966 (C56.html), c56_1.jpg: "LSU Radio Station Now On Air 24 Hours a Day," State Times, January 4, 1966. Louisiana Vertical File: Radio. LLMVC, LSU Libraries." → "“LSU Radio Station Now On Air 24 Hours a Day,” State Times, January 4, 1966. Louisiana Vertical File: Radio. LLMVC, LSU Libraries."
</li><li>WLSU, 1947–1973 / 1973 (C61.html), c61_2.jpg: "Louisiana Educational Television Authority Louisiana Educational Television Authority to George Waldroup, 14 March 1973, Correspondence, unpublished data." → "Louisiana Educational Television Authority to George Waldroup, 14 March 1973, Correspondence, unpublished data."
</li><li>KLSU, 1983–2015 / October 21, 1988 (C79.html), c78_1.jpg: ""LSU students record firm promotes La. musicians," Advocate, Nov 12, 1989. LSU Libraries" → "“LSU Students’ Record Firm Promotes La. Musicians,” Advocate, November 12, 1989. LSU Libraries."
</li><li>KLSU, 1983–2015 / December 1994 (C82.html), c82_1.jpg: "Free KLSU [part 1 of 2]" → "Free KLSU, December 1994 (page 1 of 2)."
</li><li>KLSU, 1983–2015 / December 1994 (C82.html), c82_2.jpg: "Free KLSU [part 2 of 2]" → "Free KLSU, December 1994 (page 2 of 2)."
</li><li>KLSU, 1983–2015 / 2015 (C86.html), c86_1.jpg: "Transmitter in box. Photo courtesy of John Friscia." → "The new transmitter in its crate, 2015. Photo courtesy of John Friscia."
</li><li>KLSU, 1983–2015 / 2015 (C86.html), c86_2.jpg: "Transmitter out of box. Photo courtesy of John Friscia." → "The new transmitter unpacked, 2015. Photo courtesy of John Friscia."
</li></ul>
<h4>About the Exhibition (from about.html)</h4>
<ul><li>"This exhibit walks through a century of campus radio evolution" → "This exhibition walks through a century of campus radio"
</li><li>"university archives materials, and oral histories-all of which" → "university archives materials and oral histories, all of which"
</li><li>"housed with the Williams Center for Oral History" → "housed with the T. Harry Williams Center for Oral History"
</li><li>"For specific citation information regarding each interview, please contact Center staff at <a href="http://www.lib.lsu.edu/oralhistory">http://www.lib.lsu.edu/oralhistory</a> ." → "For citation information for each interview, please contact Center staff through the Williams Center website." (linked to <a href="https://www.lib.lsu.edu/oralhistory">https://www.lib.lsu.edu/oralhistory</a>)
</li><li>"All other citations for materials are contained within the exhibit." → "All other citations for materials are given in the exhibition."
</li><li>"on staff with the Williams Center for Oral History" → "on staff with the T. Harry Williams Center for Oral History"
</li><li>"Jennifer Abraham , Center Director, was responsible for concept, oversight, and editing." → "Jennifer Abraham, Center Director, was responsible for concept, oversight and editing."
</li><li>"Erin Hess, the Center's Manuscript Processor copy edited text." → "Erin Hess, the Center’s Manuscript Processor, copy edited the text."
</li><li>"We appreciate the help of many other staff members for their assistance throughout various stages of planning and installation." → "We appreciate the help of many other staff members throughout the planning and installation."
</li><li>The About text is split into the About section and an “Acknowledgments” heading (curator and credits).
</li></ul>
<h3>To verify</h3>
<p>Left as written (or chosen without a source) and worth a curator’s check:</p>
<ul><li><strong>KFGC / November 27, 1924 (C11)</strong>: the old slide’s own text is lost (the page shows a Jazz Fest clip, locally and live). The new line (section 3) is written from the State Times clippings c11_1 and c11_2. Is the date, and “Thanksgiving Day,” right?
</li><li><strong>KFGC / February 1924 (C10)</strong>: the old text said KFGC broadcast “the election of governor Hewitt Bouanchaud.” Henry L. Fuqua won the 1924 race (Bouanchaud lost the runoff), so it now says “the returns of the governor’s race between Hewitt Bouanchaud, Henry Fuqua and Huey P. Long.” Was the race in February 1924 the January 15 primary or the February 19 runoff?
</li><li><strong>WJBO / 1936 (C19), c19_1.jpg</strong>: the slide credited the clipping to “L.S.U. Goes on the Air With Expanded Program,” Louisiana Leader, October 1938; the Pictures page credits “First Campus Radio Station Forgotten in Current Growth,” The Reveille, March 31, 1938. The page follows the Pictures page (the type matches the other Reveille clipping, c18_2). Please confirm.
</li><li><strong>WJBO / April 12, 1945 (C33), c33_1.jpg</strong>: captioned (slide and Pictures page) “Board Plans Revised Housing and Radio Station,” The Reveille, April 10, 1945, but the clipping’s headline reads “Radio Guild’s First FM Play Is Thursday.” Probably the same page; check the citation.
</li><li><strong>KFGC / Summer 1925 (C13), c13_1.jpg</strong> and <strong>WLSU / October 18, 1947 (C40), c40_1.jpg</strong>: text-only clippings with no headline, credited to the same articles as the slides beside them. Kept.
</li><li><strong>KFGC / November 27, 1924, c11_3.jpg</strong>: a letter from J. A. Busse, Doherty Hardware Company, to KFGC (November 28, 1924). The Pictures page credits it to the 1977 Morning Advocate article; the new caption says the letter was reproduced there. Check.
</li><li><strong>KLSU / November 29, 1984 (C72), c72_1.jpg</strong>: the citation names no newspaper (“LSU board agrees to study Reveille advertising policy,” December 1, 1984).
</li><li><strong>WPRG / Fall 1980 (C65), c65_1.jpg</strong>: “Tigers in Stereo,” Advocate, September 17, <strong>1982</strong>, for a fall <strong>1980</strong> event (the clipping says it is WPRG’s third year of stereo football). Kept.
</li><li><strong>WPRG / December 22, 1982 (C66), c66_1.jpg</strong>: the clipping is dated December 21, <strong>1983</strong>, a year after the event. Kept.
</li><li><strong>KLSU / October 21, 1988 (C79), c78_1.jpg</strong>: dated November 12, <strong>1989</strong>. Kept. The slide reused c78_1 here, and the December 1987 photo c77_1 on January 17, 1988 (C78); both kept as on the slides.
</li><li><strong>WJBO / February 15, 1946 (C35)</strong>: reuses the January 16, 1946 Brouillette letter (c34_1) from the previous slide. Kept.
</li><li><strong>Clip credits for Mss. 4700.2591–2594</strong> (Jenni Lawson, Andrew Nackley, Dez Crawford, Laura Blereau): the source gives no interview years, and names an interviewer only in Lawson’s transcript (Charlotte Willcox). Those credits read “…, interviewed for Mss. 4700.259x.” Please supply interviewers and years.
</li><li><strong>“Professor Emerita”</strong>: the old descriptions called Lucile Ruby Woodard “Professor Emeritus of Speech”; changed to Emerita. Revert if LSU’s title was Emeritus.
</li><li><strong>Lucile Ruby Woodard</strong>: the old text spelled her first name both Lucile and Lucille; now Lucile everywhere (her interview and the 1955 Reveille headline use Lucile).
</li><li><strong>WLSU / October 18, 1947 (C40)</strong>: “damage from Hurricane George.” Kept as written; please confirm the storm’s name, since 1947 Atlantic hurricanes weren’t officially named.
</li><li><strong>Transcripts</strong>: kept as spoken (“y’all broadcasted,” “all the sudden,” “tampered with wire recorders,” “cut digital video”). Also Andrew Nackley: “bought into the School of Journalism and there was a push” has no comma before “and”; kept.
</li><li><strong>Dez Crawford (C68)</strong>: “Wisconsin La Crosse State University” (the speaker’s name for UW–La Crosse; only the city’s spelling was corrected).
</li><li><strong>Entry dates</strong>: kept as on the slides, including approximate ones (Fall 1915, Spring 1936). The nav menu and slide labels sometimes differed only by commas; the slide label was used.
</li><li><strong>Not carried over</strong> (section 1, Structure): the unused BlueDucks audio and four unused photos. Confirm none were meant for the exhibition.
</li></ul>
<h3>New wording to check</h3>
<p>Text written for the new page, with no counterpart on the old site:</p>
<h4>Page</h4>
<ul><li>Subtitle: "A century of campus radio at LSU, 1915–2015"
</li><li>Credit line: "T. Harry Williams Center for Oral History, LSU Libraries. Curated by Charlotte Willcox."
</li><li>Introduction: "In 1915, LSU physics professor David Vance Guthrie and his students began building a wireless station from spare parts. It became KFGC, the university’s first broadcast station, and the start of a century of campus radio: programs over WJBO, the FM station WLSU, WPRG and, from 1983, KLSU. Told in newspaper clippings, university records, yearbook pages and the voices of the people who ran the stations, the exhibition follows each call sign in turn." (the old home page had only the KLSU logo; the About text is kept in About the Exhibition)
</li><li>Gallery titles: the call signs with their years: "KFGC, 1915–1934", "WJBO, 1934–1947", "WLSU, 1947–1973", "WPRG, 1979–1982", "KLSU, 1983–2015", and "About the Exhibition". The years are the first and last slide in each group.
</li><li>About the Exhibition: an "Acknowledgments" heading over the curator and credit paragraph.
</li></ul>
<h4>Entry text</h4>
<ul><li>KFGC / November 27, 1924 (C11): "KFGC broadcasts a play-by-play account of the Thanksgiving Day football game between Tulane and LSU." (old text lost; see section 2)
</li><li>KFGC / February 1924 (C10): the reworded first sentence (see sections 1 and 2).
</li><li>Folded entries (KFGC / 1926–1931, KLSU / Fall 1994, KLSU / 2015): each date is now a bold lead-in ("<strong>January 15, 1926.</strong> The federal government…").
</li></ul>
<h4>Captions written new</h4>
<ul><li>KFGC / November 27, 1924, c11_1.jpg, c11_2.jpg: from the Pictures page (the slide had none).
</li><li>c11_3.jpg: "J. A. Busse, Doherty Hardware Company, to Station KFGC, November 28, 1924. Reproduced in Jaynie K. Reed, “Station KFGC Part of the Early Radio Craze,” Morning Advocate, February 27, 1977. Louisiana Vertical File: Radio. LLMVC, LSU Libraries."
</li><li>c21_2.jpg, c21_3.jpg (Colbert thesis): the thesis title set in title case with its 1960 date; see section 1.
</li><li>c79_1.jpg: "“Martini Mix,” Advocate, January 16, 1989. LSU Libraries." (from the Pictures page)
</li><li>c82_1.jpg, c82_2.jpg, c86_1.jpg, c86_2.jpg: see section 1, Captions.
</li></ul>
<h4>Alt text (all new)</h4>
<ul><li>c2_1.jpg: The first chemistry building on LSU’s old downtown campus, a two-story building with an outside staircase.
</li><li>c2_2.jpg: Heard Hall, the physics laboratory, a two-story brick building with a tiled roof, in 1903.
</li><li>c3_1.jpg: Newspaper column headlined “Wireless Station Receives Messages from New Orleans.”
</li><li>c4_1.jpg: Newspaper page headlined “First Campus Radio Station Forgotten in Current Growth,” with photos of early radio equipment.
</li><li>c5_1.jpg: Newspaper text on the amateur station W5YW and the two university radio stations.
</li><li>c6_1.jpg: Newspaper text on LSU’s federal license of April 20, 1923, to operate a broadcast transmitter.
</li><li>c7_1.jpg: Newspaper magazine page headlined “Part of the Early Radio Craze,” with reproduced telegrams and applause cards.
</li><li>c8_1.jpg: Typed program schedule of Radio Station KFGC, Louisiana State University, for January and February 1924.
</li><li>c9_1.jpg: Newspaper column headlined “Band Concert to Be Broadcasted at Physics Bldg. Fri.”
</li><li>c9_2.jpg: Western Union telegram to Louisiana State University dated January 11, 1924.
</li><li>c10_1.jpg: Newspaper text quoting Dr. Guthrie on getting election returns by courier in 1924.
</li><li>c11_1.jpg: Newspaper headline “Football Fans Throughout State Prepare to Listen in on Returns.”
</li><li>c11_2.jpg: Newspaper column headlined “Broadcasting Football Game Proves Success.”
</li><li>c11_3.jpg: Typed letter on Doherty Hardware Company letterhead thanking Station KFGC for broadcasting the Tulane–LSU football game.
</li><li>c12_1.jpg: Newspaper column headlined “KFGC Loses Right to Broadcast; No Station Here Now.”
</li><li>c13_1.jpg: Newspaper text on the station’s dismantling during the move to the new campus.
</li><li>c14_1.jpg: Newspaper headline “KFGC Loses Right to Broadcast; No Station Here Now.”
</li><li>c16_1.jpg: Newspaper column headlined “Guthrie Soon to Revive Old Amateur Radio Station.”
</li><li>c17_1.jpg: Newspaper article headlined “L.S.U. Goes on the Air with Expanded Program.”
</li><li>c18_1.jpg: Newspaper article headlined “L.S.U. Radio Broadcast Series Now in Process Bring Enthusiastic Response from Alumni, Others.”
</li><li>c18_2.jpg: Newspaper text on campus broadcasts through station WJBO and the studios in the Music and Dramatic Arts building.
</li><li>c19_1.jpg: Newspaper text on Dr. Harley A. Smith organizing a Louisiana Radio Guild in 1936.
</li><li>c20_1.jpg: Newspaper column headlined “Radio Class Gives News.”
</li><li>c21_1.jpg: Newspaper article headlined “L.S.U. Goes on the Air with Expanded Program.”
</li><li>c21_2.jpg: Typed thesis page listing WJBO’s first day of network programs.
</li><li>c21_3.jpg: Typed thesis pages listing WJBO’s program schedule for November 3, 1937.
</li><li>c23_1.jpg: Newspaper article headlined “L.S.U. Goes on the Air with Expanded Program.”
</li><li>c24_1.jpg: Newspaper column headlined “University Officials Inspect, Dedicate Radio Equipment.”
</li><li>c25_1.jpg: Newspaper column headlined “Plan Series to Spur Interest in French.”
</li><li>c26_1.jpg: Newspaper page headlined “Making an Aerial Deadline,” with photos of two students at microphones.
</li><li>c27_1.jpg: Newspaper column headlined “L.S.U. Scheduled on NBC Broadcast.”
</li><li>c29_1.jpg: Newspaper column headlined “Begin Radio Forum on World at War,” listing the series’ talks.
</li><li>c30_1.jpg: Newspaper column headlined “Construction Is Started on Two Radio Studios.”
</li><li>c31_1.jpg: Program pages for the faculty radio forum series “The Shape of Things to Come.”
</li><li>c32_1.jpg: Newspaper article headlined “Board Plans Revised Housing and Radio Station.”
</li><li>c33_1.jpg: Newspaper column headlined “Radio Guild’s First FM Play Is Thursday.”
</li><li>c34_1.jpg: Typed letter to President W. B. Hatcher recommending members for a radio committee.
</li><li>c36_1.jpg: Newspaper column headlined “LSU’s New Radio Head of Programs Arrives.”
</li><li>c37_1.jpg: Newspaper column headlined “Radio Play Series Opens with ‘Mirage.’”
</li><li>c38_1.jpg: Newspaper column headlined “Foundation Laid for Radio Station.”
</li><li>c39_1.jpg: Newspaper article headlined “FM Station to Use UP Wire Service and Associated Transcription Library,” with a photo of a man at transcription equipment.
</li><li>c39_2.jpg: The new single-story WLSU radio station building on Dalrymple Drive, among pine trees.
</li><li>c40_1.jpg: Newspaper text on WLSU’s temporary antenna and its new studios.
</li><li>c40_2.jpg: Newspaper column headlined “Antenna Raised for Station WLSU.”
</li><li>c41_1.jpg: Newspaper article headlined “WLSU, Pioneer FM Unit in South, Extends University Services to Off-Campus Group.”
</li><li>c42_1.jpg: Newspaper column headlined “Guthrie Soon to Revive Old Amateur Radio Station.”
</li><li>c43_1.jpg: Newspaper article headlined “WLSU Is First College Station to Broadcast a Campus Opera.”
</li><li>c43_2.jpg: Yearbook spread titled “The Opera,” with photos of costumed singers in “Tales of Hoffmann.”
</li><li>c44_1.jpg: Newspaper page headlined “Progress Marks Station WLSU’s First Anniversary,” with a photo of students at the station.
</li><li>c45_1.jpg: Newspaper article headlined “New Tape Recorder Will Permit WLSU to Rebroadcast Any Campus Activities.”
</li><li>c46_1.jpg: Newspaper column headlined “WLSU Programs Relayed by WNOE in New Orleans.”
</li><li>c47_1.jpg: Newspaper column headlined “Chemistry Series Starts on WLSU.”
</li><li>c48_1.jpg: Newspaper article headlined “WLSU Renovated to Meet Changes in Fall Operation.”
</li><li>c49_1.jpg: Newspaper article headlined “University FM Station to Tape Record Program Showing Phases of LSU Life.”
</li><li>c50_1.jpg: Newspaper column headlined “LSU Will Have Radio Instruction in Night Classes,” with a photo of a student at a tape recorder.
</li><li>c51_1.jpg: Newspaper article headlined “Lucile Ruby Helped Guide WLSU to New Importance.”
</li><li>c52_1.jpg: Newspaper column headlined “WLSU Closed 3 Years, But News Work Continues.”
</li><li>c53_1.jpg: Newspaper column headlined “WLSU Closed 3 Years, But News Work Continues.”
</li><li>c54_1.jpg: Newspaper obituary headlined “Dr. D. V. Guthrie Dies in Virginia; Services Slated,” with his photo.
</li><li>c55_1.jpg: Newspaper article headlined “From ‘Ham Shack’ to Wireless; History of WLSU Radio Related.”
</li><li>c55_2.jpg: Yearbook page titled “WLSU Radio,” with photos of students at the broadcast console and the station manager with advisers.
</li><li>c56_1.jpg: Newspaper column headlined “LSU Radio Station Now on Air 24 Hours a Day.”
</li><li>c57_1.jpg: Newspaper article headlined “Taylor Defends Administrators on WLSU,” with a photo of the chancellor at a microphone with students.
</li><li>c58_1.jpg: Yearbook page titled “WLSU,” with photos of students at turntables, microphones and the record library.
</li><li>c59_1.jpg: Typed campus memo from Chancellor C. G. Taylor, dated July 8, 1970, on Radio Station WLSU.
</li><li>c60_1.jpg: Typed pages of WLSU sales contract procedures.
</li><li>c60_2.jpg: Typed WLSU Campus Radio rate card of commercial time rates for 1972–73.
</li><li>c61_1.jpg: Typed letter on WLSU 660 letterhead from George M. Waldroup to Chancellor Cecil G. Taylor.
</li><li>c61_2.jpg: Typed letter on Louisiana Educational Television Authority letterhead to George M. Waldroup.
</li><li>c63_1.jpg: Federal Communications Commission notice to Louisiana State University granting its application.
</li><li>c64_1.jpg: Newspaper article headlined “WPRG Begins Broadcasting,” with a photo of three men at the station’s console.
</li><li>c65_1.jpg: Newspaper column beginning “Tigers in Stereo.”
</li><li>c66_1.jpg: Newspaper column headlined “Station’s Rights to LSU Events Challenged in Court.”
</li><li>c68_1.jpg: Yearbook page with the WPRG and KLSU logos and the word “Becomes,” and a photo of a deejay at the console.
</li><li>c69_1.jpg: Students pose on a rooftop at the base of the KLSU antenna tower.
</li><li>c70_1.jpg: Newspaper column headlined “BR Real Estate Will Soon Be Reel Estate.”
</li><li>c72_1.jpg: Newspaper column headlined “LSU Board Agrees to Study Reveille Advertising Policy.”
</li><li>c73_1.jpg: Yearbook page titled “Juke in the Box,” with photos of bands and the crowd at Alex Box Stadium.
</li><li>c76_1.jpg: Newspaper page headlined “Reveille Moves to Hodges, KLSU and Gumbo to Follow,” with a photo of a newsroom.
</li><li>c77_1.jpg: Yearbook page titled “KLSU (Spring Staff),” with a large group photo of the staff and a photo of the studio.
</li><li>c78_1.jpg: Newspaper article headlined “LSU Students’ Record Firm Promotes La. Musicians.”
</li><li>c79_1.jpg: Newspaper column headlined “Martini Mix.”
</li><li>c82_1.jpg: Pages of the photocopied zine “Free KLSU,” with an editorial and comic panels.
</li><li>c82_2.jpg: More pages of the zine “Free KLSU,” with articles and contact forms.
</li><li>c86_1.jpg: A tall wooden crate and a plastic-wrapped cabinet on a pallet in a hallway.
</li><li>c86_2.jpg: The new gray transmitter cabinet, unpacked, on its pallet beside a pallet jack.
</li><li>fall1915_1.jpg: Studio portrait of a young David Vance Guthrie in a suit and high collar.
</li><li>fall1915_2.jpg: Newspaper column headlined “Wireless Station to Be Erected at L.S.U.”
</li></ul>
<h4>Clip titles and credit lines (new)</h4>
<p>The old slides had no clip titles; credits were the citation at the end of each description. Format: “Speaker, interviewed by Interviewer, Mss. number, year” (the wall reads the speaker and year from it).</p>
<ul><li>WJBO, 1934–1947 / Fall 1938: <strong>Ralph Steetle on his early influences</strong>. Credit: Ralph Steetle, interviewed by Ron Ross, Mss. 4700.0660, 1996
</li><li>WJBO, 1934–1947 / November 6, 1939: <strong>Sig Mickelson on teaching radio journalism</strong>. Credit: Sig Mickelson, interviewed by Melisse Campbell, Mss. 4700.0220, 1993
</li><li>WJBO, 1934–1947 / Fall 1946: <strong>Lucile Ruby Woodard on the beginnings of LSU radio</strong>. Credit: Lucile Ruby Woodard, interviewed by John W. “Jack” Fiser, Mss. 4700.0826, 1997
</li><li>WJBO, 1934–1947 / May 1947: <strong>Ralph Steetle on building WLSU</strong>. Credit: Ralph Steetle, interviewed by Ron Ross, Mss. 4700.0660, 1996
</li><li>WLSU, 1947–1973 / December 1947: <strong>Lucile Ruby Woodard on WLSU’s programming</strong>. Credit: Lucile Ruby Woodard, interviewed by John W. “Jack” Fiser, Mss. 4700.0826, 1997
</li><li>WLSU, 1947–1973 / Summer 1949: <strong>Lucile Ruby Woodard on discs and magnetic tape</strong>. Credit: Lucile Ruby Woodard, interviewed by John W. “Jack” Fiser, Mss. 4700.0826, 1997
</li><li>WLSU, 1947–1973 / June 1957: <strong>Lucile Ruby Woodard on the end of WLSU</strong>. Credit: Lucile Ruby Woodard, interviewed by John W. “Jack” Fiser, Mss. 4700.0826, 1997
</li><li>KLSU, 1983–2015 / May 31, 1983: <strong>Dez Crawford on winning back the KLSU call letters</strong>. Credit: Dez Crawford, interviewed for Mss. 4700.2593
</li><li>KLSU, 1983–2015 / Fall 1983: <strong>Dez Crawford on building the tower</strong>. Credit: Dez Crawford, interviewed for Mss. 4700.2593
</li><li>KLSU, 1983–2015 / December 1987: <strong>Jenni Lawson on the move to Hodges Hall</strong>. Credit: Jennifer “Jenni” Lawson, interviewed by Charlotte Willcox, Mss. 4700.2591
</li><li>KLSU, 1983–2015 / Fall 1994: <strong>Andrew Nackley on the shift to alternative programming</strong>. Credit: Andrew Nackley, interviewed for Mss. 4700.2592
</li><li>KLSU, 1983–2015 / Fall 1994: <strong>Laura Blereau on the format change and the backlash</strong>. Credit: Laura Blereau, interviewed for Mss. 4700.2594
</li><li>KLSU, 1983–2015 / 1997: <strong>Laura Blereau on moving from reel-to-reel to SAW</strong>. Credit: Laura Blereau, interviewed for Mss. 4700.2594
</li><li>KLSU, 1983–2015 / February 8, 2008: <strong>Jenni Lawson on Dr. Jules d’Hemecourt</strong>. Credit: Jennifer “Jenni” Lawson, interviewed by Charlotte Willcox, Mss. 4700.2591
</li><li>KLSU, 1983–2015 / February 8, 2008: <strong>Dez Crawford on Dr. d’Hemecourt’s mentorship</strong>. Credit: Dez Crawford, interviewed for Mss. 4700.2593
</li></ul>
<h4>Gallery lead images and captions</h4>
<ul><li>KFGC, 1915–1934: fall1915_1.jpg, caption "David Vance Guthrie, M.A., Ph.D., a50000186, LSU Photograph Collection, 1886–1926, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA."
</li><li>WJBO, 1934–1947: c26_1.jpg, caption "“Making an Aerial Deadline,” The Reveille, November 3, 1939. LLMVC, LSU Libraries."
</li><li>WLSU, 1947–1973: c39_2.jpg, caption "“Station WLSU,” L.S.U. Alumni News, September 1947. Louisiana Vertical File: Radio. LLMVC, LSU Libraries."
</li><li>WPRG, 1979–1982: c64_1.jpg, caption "“WPRG Begins Broadcasting,” Advocate, January 16, 1980. LSU Libraries."
</li><li>KLSU, 1983–2015: c77_1.jpg, caption "KLSU: Gumbo 1988. LSU Archives, LSU Libraries."
</li><li>Cover image: c69_1.jpg (KLSU antenna, Gumbo 1984).
</li></ul>
HTML),
]);

$section('A Lady in the South', [
  $text(NULL, <<<'HTML'
<p>Now at <a href="/v2/oralhistory/exhibitions/lady">/v2/oralhistory/exhibitions/lady</a>.</p>
<p>Exhibition: “A Lady in the South”: LSU Home Management Residence, 1935–1945 (T. Harry Williams Center for Oral History with Hill Memorial Library, 2014 listening station).<br>Old site: <a href="https://lib.lsu.edu/sites/all/files/oralhistory/presentations/LADY/main/">https://lib.lsu.edu/sites/all/files/oralhistory/presentations/LADY/main/</a> (one page: Home, About, History, Oral History, Photos).</p>
<p>New structure: two walls of clips, <strong>Dorothy Colvin Howell</strong> (8) and <strong>A.B. Clark</strong> (7), then <strong>History</strong>, <strong>From the Scrapbook</strong> and <strong>About the Exhibition</strong> as stories. The old “Photos” grid is folded into these galleries.</p>
<p>Changes made throughout, not listed one by one below:</p>
<ul><li>Straight quotes became typographic quotes and apostrophes (’ “ ”).
</li><li>Hyphen dates became en-dash ranges (1935-1945 → 1935–1945).
</li><li>Sign-offs at the end of transcripts (“- Dorothy Howell”, “– A.B. Clark”) were dropped, because each player has a credit line.
</li><li>Speaker labels are in bold, one paragraph per turn, followed by a colon (“<strong>Dorothy Howell:</strong>”).
</li><li>Long transcripts are split into paragraphs at natural breaks.
</li><li>“Home Management House(s)/Residence(s)” is in lowercase when it is generic (“the home management houses”). It keeps its capitals in names (“the Helen Carter House”) and in the bios’ “the Home Management House”.
</li></ul>
<h3>Corrected</h3>
<h4>Dorothy Colvin Howell wall</h4>
<ul>
<li><strong>A lady in the South</strong>: “Well, women in my generation--there weren’t” → “Well, women in my generation . . . there weren’t”</li>
<li><strong>More than cooking and sewing</strong>: “Didn't you have to take chemistry” → “Didn’t you have to take chemistry”</li>
<li><strong>Orange-crate furniture</strong>: “you had to learn about food preparation was a big part of the expense” → “you had to learn about food preparation, [which] was a big part of the expense”</li>
<li><strong>Orange-crate furniture</strong>: “And that’s when home economics developed was to be able to make” → “And that’s when home economics developed, was to be able to make”</li>
<li><strong>Orange-crate furniture</strong>: “men’s worn out trousers” → “men’s worn-out trousers”</li>
<li><strong>Orange-crate furniture</strong>: “we upholstered. We padded it” → “we upholstered [it]. We padded it”</li>
<li><strong>Orange-crate furniture</strong>: “slip covers” → “slipcovers”</li>
<li><strong>Cooking within your income</strong>: “Oh, here’s one; Cooking Within Your Income.” → “Oh, here’s one: <em>Cooking Within Your Income</em>.”</li>
<li><strong>Cooking within your income</strong>: “how you managed the money at the end of the preparation you had to stay in the budget.” → “how you managed the money. At the end of the preparation you had to stay in the budget.”</li>
<li><strong>The rolling ironer</strong>: “you put your sheets through it all the sheets were ironed.” → “you put your sheets through it; all the sheets were ironed.”</li>
<li><strong>Company once a week</strong>: “All linen table cloths.” → “All linen tablecloths.”</li>
<li><strong>Company once a week</strong>: “All china, the china’s still in the Human Ecology building” → “All china; the china’s still in the Human Ecology building”</li>
<li><strong>Company once a week</strong>: “I helped Miss [?] , , , it was not my job” → “I helped Miss [?] . . . it was not my job”</li>
<li><strong>Part of LSU’s history</strong>: “the skills that I had learned at LSU that were useful all my life” → “the skills that I had learned at LSU were useful all my life”</li>
<li><strong>Part of LSU’s history</strong>: “I think they're [the Home Management Houses] involved” → “I think they’re [the home management houses] involved”</li>
<li><strong>Part of LSU’s history</strong>: Signed “- A.B. Clark” on the old page → Credited to <strong>Dorothy Colvin Howell</strong>. The audio file is <code>Howell.significance.mp3</code>, and the About essay quotes these words as Howell’s.</li>
</ul>
<h4>A.B. Clark wall</h4>
<ul>
<li><strong>Catching a ride to campus</strong>: “hold up two fingers or three fingers, they could take two people or three people and we always hoped” → “hold up two fingers or three fingers, [meaning] they could take two people or three people, and we always hoped”</li>
<li><strong>The home ec table was closer</strong>: “Junior or senior year, I can’t remember which,.” → “Junior or senior year, I can’t remember which.”</li>
<li><strong>Grating carrots in wartime</strong>: “was the head of the department then and it was over in Prescott Hall” → “was the head of the department then, and it was over in Prescott Hall”</li>
<li><strong>Grating carrots in wartime</strong>: “When Clara Tucker, came, just things popped” → “When Clara Tucker came, things just popped”</li>
<li><strong>Grating carrots in wartime</strong>: “It was Smith Hall at that time they had it in the basement” → “It was Smith Hall at that time; they had it in the basement”</li>
<li><strong>My father’s advice</strong>: “an eleven year school” → “an eleven-year school”</li>
<li><strong>Babies in the house at Tennessee</strong>: “at [University of] Tennessee” → “at [the University of] Tennessee”</li>
<li><strong>Babies in the house at Tennessee</strong>: “LSU's program” → “LSU’s program”</li>
<li><strong>Babies in the house at Tennessee</strong>: “difficult . . . but” (broken across a line as “difficult . / . . but”) → “difficult . . . but”</li>
<li><strong>Couples in the Morris House</strong>: “I lived as an instructor” → “I lived [in the house] as an instructor”</li>
<li><strong>Couples in the Morris House</strong>: “home management residence.And it was difficult” → “home management residence. And it was difficult”</li>
<li><strong>Couples in the Morris House</strong>: “because the looked at it” → “because they looked at it”</li>
<li><strong>Couples in the Morris House</strong>: “this boy drying — we didn’t have dishwashers then — drying the dishes and he had” → “this boy drying (we didn’t have dishwashers then), drying the dishes, and he had”</li>
<li><strong>Couples in the Morris House</strong>: “Oh this is a quicker way” → “Oh, this is a quicker way”</li>
</ul>
<h4>History</h4>
<ul>
<li><strong>1st text</strong>: “The national importance of vocational education in general grew” → “The national importance of vocational education grew”</li>
<li><strong>1st text</strong>: “Agricultural Extension agencies” → “agricultural extension agencies”</li>
<li><strong>2nd text</strong>: “The history of Home Management at LSU” → “The history of home management at LSU”</li>
<li><strong>2nd text</strong>: “black and white” → “Black and white”</li>
<li><strong>2nd text</strong>: “home-making skills” → “homemaking skills”</li>
<li><strong>PWA text</strong>: “provided federal funds that assisted to replace dilapidated schools and expand one-room school houses” → “provided federal funds that helped replace dilapidated schools and expand one-room schoolhouses”</li>
<li><strong>PWA text</strong>: “the addition and repair of new buildings” → “the addition and repair of buildings”</li>
<li><strong>PWA text</strong>: “served as a laboratory for teaching” → “served as laboratories for teaching”</li>
<li><strong>Southern University text</strong>: “offers a promising future research avenue.” → “offers a promising avenue for future research.”</li>
<li><strong>1915 text</strong>: “LSU Women's Home Economics Program” → “LSU Women’s Home Economics Program”</li>
<li><strong>1915 text</strong>: “LSU's historic Home Economics Department’s goals were later modified” → “The goals of LSU’s historic Home Economics Department were later modified”</li>
<li><strong>Postwar text</strong>: “the Home Ec program” → “the home economics program”</li>
<li><strong>Postwar text</strong>: “a campus parking garage, which is located in that part of campus which is still comprised of historic buildings” → “a campus parking garage, in a part of campus that still holds historic buildings”</li>
<li><strong>Huey Long text</strong>: “LSU Home Economics graduate student, Katherine Pierce Green, noted” → “LSU home economics graduate student Katherine Pierce Green noted”</li>
<li><strong>Huey Long text</strong>: “Notably absent southern states are” → “Notably absent southern states were”</li>
<li><strong>Huey Long text</strong>: “Franklin Roosevelt's New Deal and his massive public works programs” → “Franklin Roosevelt’s New Deal and its massive public works programs”</li>
<li><strong>Long’s vision text</strong>: “Seed money received from revenue gained from the sale of the capital grounds” → “Seed money from the sale of the Capitol grounds”</li>
<li><strong>Long’s vision text</strong>: “Music and Dramatic Arts building” → “Music and Dramatic Arts Building”</li>
<li><strong>Leche text</strong>: “Richard Leche became governor and he repaired the relationship” → “Richard Leche became governor and repaired the relationship”</li>
<li><strong>Leche text</strong>: “The WPA also aided campus growth, and their contributions include” → “The WPA also aided campus growth, and its contributions include”</li>
<li><strong>State funds text</strong>: “several facilities including … the two Home Management Houses: Helen Carter and Agnes Morris, to replace” → “several facilities, including … the two home management houses, Helen Carter and Agnes Morris, to replace”</li>
<li><strong>1930s–40s women text</strong>: “LSU Law School.)” → “LSU Law School).”</li>
<li><strong>1930s–40s women text</strong>: “a curriculum of domestic science to take on nutrition” → “a curriculum of domestic science taking on nutrition”</li>
<li><strong>1930s–40s women text</strong>: “child care and development” → “childcare and development”</li>
<li><strong>Last text</strong>: “Dr. Pamela Rabelais Vinci” → “Dr. Pamela Rabalais Vinci”, the spelling used in the old Acknowledgments</li>
<li><strong>Last text</strong>: “please peruse the oral history portion of this kiosk … You can find out more about the individual photographs and images here.” → “listen to Dorothy Colvin Howell and A.B. Clark.” The links now go to the two walls. The “kiosk” and “here” links pointed to old page anchors.</li>
</ul>
<h4>Page intro and About the Exhibition</h4>
<ul>
<li><strong>Intro</strong>: “Founded in 1915, LSU's Women’s Home Economics Program” → “Founded in 1915, LSU’s Women’s Home Economics Program”</li>
<li><strong>Intro</strong>: “school teachers” → “schoolteachers”</li>
<li><strong>Intro</strong>: “a reform measure to improve the economic hardships of the Great Depression” → “a reform measure to ease the economic hardships of the Great Depression”</li>
<li><strong>Intro</strong>: “Franklin Roosevelt's New Deal” → “Franklin Roosevelt’s New Deal”</li>
<li><strong>Howell bio</strong>: “(pictured top left)”, “enrolled in School of Home Economics at LSU from 1939-1942” → “(pictured …)” removed; “enrolled in the School of Home Economics at LSU from 1939 to 1942”</li>
<li><strong>Clark bio</strong>: “(pictured bottom left)” → Removed</li>
<li><strong>Clark bio</strong>: “Teaching Certification”, “Home Management and Child Development”, “a Home Residence instructor” → “teaching certification”, “home management and child development”, “a home residence instructor”</li>
<li><strong>Clark bio</strong>: “School of Home Economics; first as an instructor” → “School of Home Economics, first as an instructor”</li>
<li><strong>Clark bio</strong>: “lived in Pakistan for a total of four years where she helped with the founding of and instructed in a women's college in Karachi” → “lived in Pakistan for a total of four years, where she helped found and taught at a women’s college in Karachi”</li>
<li><strong>In their words</strong>: “point of view."" (double closing quote) → “point of view.”</li>
<li><strong>In their words</strong>: “a group of couples that lived in the Morris House — three couples.” → “a group of couples that lived in the Morris House, three couples.”</li>
<li><strong>In their words</strong>: “in a situation like that. . . .Now that was” → “in a situation like that. . . . Now that was”</li>
<li><strong>In their words</strong>: “The Home Economics students continued internships” → “Home economics students continued internships”</li>
<li><strong>In their words</strong>: “Oral history excerpts in this exhibit” → “Oral history excerpts in this exhibition”</li>
<li><strong>In their words</strong>: “please contact Jennifer A. Cramer, Director, … jabrah1@lsu.edu” (mailto link broken: <code>Home%Management</code>) → “contact the T. Harry Williams Center for Oral History”; the name, email and link were dropped</li>
<li><strong>Acknowledgments</strong>: “Angie Juban, Interviewer (2004) THWCOH” → “Angie Juban, Interviewer (2004), THWCOH”</li>
<li><strong>Acknowledgments</strong>: Home page: “Lecture Hall Exhibition: COOPERATIVE EXTENSION: CELEBRATING 100 YEARS: A Preview of the Fall 2014 Exhibition curated by …” → “Presented in conjunction with the Hill Memorial Library Lecture Hall exhibition <em>Cooperative Extension: Celebrating 100 Years</em>, a preview of the fall 2014 exhibition curated by …” (moved to Acknowledgments)</li>
</ul>
<h4>Captions (old lightbox titles)</h4>
<ul>
<li><strong>hist_17</strong>: “Original Home manamgement Cottage, … sale of Capital Grounds, 1934” (alt: “… 1939”) → “The original home management cottage, on today’s Infirmary Road, built with funds from the sale of the Capitol grounds, 1934.”</li>
<li><strong>LoC photos</strong>: “FSA-OWI Collection: LC-USF34-053820-D ” (trailing space, colon) → “FSA/OWI Collection, LC-USF34-053820-D.” (the same for all five)</li>
<li><strong>Scrapbook photos</strong>: “Scrapbook Photo Courtesy of Human Ecology Department” / “Picture from Scrapbook, courtesy of Human Ecology Department.” → “Scrapbook photo courtesy of the Human Ecology Department.”</li>
<li><strong>Gumbo photos</strong>: “LSU Gumbo, 1939” / “Gumbo, 1939” → “<em>LSU Gumbo</em>, 1939.” (yearbook title in italics, the same form throughout)</li>
<li><strong>hist_19</strong>: “University Bulletin, Vol. 31, April, 1939.” → “Campus map. <em>University Bulletin</em>, vol. 31, April 1939.”</li>
<li><strong>hist_24</strong>: “Contemporary photo of Helen Carter House. Photograph by A. Juban.” → “The Helen Carter House before it was razed in 2011. Photo by Angie Juban.”</li>
<li><strong>hist_22</strong>: “Hanging laundry outside of the Helen Carter House, ca. 1943.” → “Hanging laundry outside the Helen Carter House, ca. 1943. Scrapbook photo courtesy of the Human Ecology Department.”</li>
</ul>
<h3>To verify</h3>
<ul><li><strong>Howell interview year.</strong> It is unknown, so her credit reads “Dorothy Colvin Howell, interviewed by Jennifer Abraham” with no year. Jennifer Abraham is Jennifer A. Cramer’s earlier name, and the old transcripts label her “Jennifer Abraham”.
</li><li><strong>Clark interviewer and year.</strong> The credit “A.B. Clark, interviewed by Angie Juban, 2004” comes from “Angie Juban, Interviewer (2004)” in the Acknowledgments, plus Juban asking the questions in the Tennessee clip. Please confirm that all seven Clark clips come from that interview.
</li><li><strong>“Significance” clip speaker.</strong> I credited it to Howell, from the file name and the About quote. The old page signed it A.B. Clark. Please confirm by listening.
</li><li><strong>Cottage photo year (hist_17).</strong> The old caption says 1934 and the old alt text says 1939. I kept 1934.
</li><li><strong>“Capital grounds” → “Capitol grounds”.</strong> I read this as the old State Capitol grounds, the downtown campus that was sold. Please confirm.
</li><li><strong>Curriculum image.</strong> I used <code>curriculum.JPG</code>, which matches <code>hist_21.png</code>, captioned as “<em>LSU Bulletin</em>, 1937, from the Human Ecology Department scrapbook”. The old page captioned it both “Home Economics Curriculum and Courses, LSU Bulletin, 1937” (History) and “Scrapbook Photo” (Oral History).
</li><li><strong>Menu page date (menu.png).</strong> The handwritten date read as “March 7, 1938”. The last digit is faint.
</li><li><strong>Names on group1.jpg.</strong> Transcribed from handwriting: “Rose Hewes, Bettye Leigh, “Toinette” Henderson, Miss Elizabeth Tucker (housemother) and Ethel Schenck”. Please check the spellings against the scrapbook.
</li><li><strong>Phi Upsilon Omicron page.</strong> Its year, <em>LSU Gumbo</em> 1941, comes from the old caption.
</li><li><strong>Clip audio choice.</strong> I kept the files the old page played: <code>Howell. ironing prep.mp3</code> (75 s), not the longer “ironing entertaining flowers” takes, and <code>Howell.significance.mp3</code> (48 s), not <code>Howell significance.mp3</code> (91 s). Please check that the transcripts match the audio from start to end.
</li><li><strong>Clips left out.</strong> The source folder has 14 audio files the old page never played, none with a transcript: alternate takes, “Howell merging of several at end” (5:38), “clark teaching in the house” (3:30), “howell becky homeccy”, “clark … less people”, and “we’ll meet again” (music). Are any of them wanted?
</li><li><strong>Spoken forms left as said.</strong> “Julia Childs of the day”, “how’d we work them out”, “general ed[ucation]”, “Requisites included” (History, perhaps “Prerequisites”), and “Miss [?]” (a name inaudible in the original).
</li><li><strong>Images reused for photo-less clips.</strong> Three Clark clips had no image on the old page and now carry one:
<ul><li>“Grating carrots in wartime”: a second lab photo (<code>lab.jpg</code>, the unused twin of <code>lab.png</code>);
</li><li>“My father’s advice”: <code>sit4.png</code>, scrapbook, women on the steps;
</li><li>“Couples in the Morris House”: the <code>hist_13</code> “Husbands Learn Housework” clipping. Please confirm these pairings.
</li></ul>
</li><li><strong>Images left out.</strong>
<ul><li><code>hist_6.png</code> (“Washday”) and <code>juban3.png</code> (“Household Duties!!”) are only handwritten scrapbook labels.
</li><li><code>about_title.png</code> is a crop of <code>corner.png</code>, <code>clark.jpg</code> duplicates <code>clark2.jpg</code>, and <code>test.png</code> duplicates <code>hist_18.png</code>.
</li><li><code>lady.png</code> is the title graphic and <code>smithlever.png</code> is the Smith-Lever centennial logo.
</li></ul>
</li><li><strong>Lab photos.</strong> The caption “LSU Photograph Collection, University Archives, LSU Libraries Special Collections” comes from the old <code>lab.png</code> caption. I applied it to <code>lab.jpg</code> as well.
</li><li><strong>Image quality.</strong> Every image is a small scan, 170–1379 px wide. The two yearbook portraits are about 170 px. Better scans would help, especially for the cover (<code>hist_23</code>, the Agnes Morris House) and the gallery leads.
</li></ul>
<h3>New wording to check</h3>
<h4>Page fields</h4>
<ul><li><strong>Title:</strong> A Lady in the South
</li><li><strong>Subtitle:</strong> LSU Home Management Residence, 1935–1945
</li><li><strong>Credit:</strong> “Presented by the T. Harry Williams Center for Oral History with Hill Memorial Library, LSU Libraries. Listening station curated by Jennifer A. Cramer.”
</li><li><strong>Intro:</strong> the first two paragraphs of the old About essay (corrected as above).
</li><li><strong>Physical note:</strong> “Shown as a listening station in the Hill Memorial Library Lecture Hall exhibition Cooperative Extension: Celebrating 100 Years, 2014.”
</li><li><strong>Year:</strong> 2014. <strong>Cover:</strong> <code>hist_23</code> (Agnes Morris House).
</li></ul>
<h4>Gallery titles and lead captions</h4>
<ul><li><strong>Dorothy Colvin Howell:</strong> lead <code>corner.png</code>, shown whole. Caption: “Scrapbook photo courtesy of the Human Ecology Department.”
</li><li><strong>A.B. Clark:</strong> lead <code>lab.png</code>. Caption: “Students in a laboratory. LSU Photograph Collection, University Archives, LSU Libraries Special Collections.”
</li><li><strong>History:</strong> lead <code>hist_15</code>. Caption: “LSU campus. <em>LSU Gumbo</em>, 1939.”
</li><li><strong>From the Scrapbook:</strong> new gallery, with the intro “More photographs from the Human Ecology Department’s scrapbook of the home management houses.” It holds <code>sit1</code>–<code>sit3</code>, from the old Photos grid.
</li><li><strong>About the Exhibition:</strong> new headings “Dorothy Colvin Howell”, “A.B. Clark”, “In their words” and “Acknowledgments”.
</li></ul>
<h4>Clip titles (all new; the old page had none)</h4>
<ul><li><strong>Howell:</strong> A lady in the South · More than cooking and sewing · Blueprints, home nursing and demonstrations · Orange-crate furniture · Cooking within your income · The rolling ironer · Company once a week · Part of LSU’s history
</li><li><strong>Clark:</strong> Catching a ride to campus · The home ec table was closer · Grating carrots in wartime · My father’s advice · Babies in the house at Tennessee · Couples in the Morris House · A wonderful way to teach
</li></ul>
<h4>Credit lines</h4>
<ul><li>Dorothy Colvin Howell, interviewed by Jennifer Abraham
</li><li>A.B. Clark, interviewed by Angie Juban, 2004
</li></ul>
<h4>New or rewritten captions</h4>
<ul><li><strong>phi:</strong> “Phi Upsilon Omicron, the national home economics honor fraternity, lists Dorothy Colvin as treasurer. <em>LSU Gumbo</em>, 1941.”
</li><li><strong>curriculum:</strong> “Curriculum in Homemaking and Personal Living and Curriculum in Home Demonstration. <em>LSU Bulletin</em>, 1937, from the Human Ecology Department scrapbook.”
</li><li><strong>menu:</strong> “Menu, guests and duties for a buffet supper given for the housemothers, March 7, 1938. Scrapbook photo …”
</li><li><strong>company:</strong> “Students entertain their dates in the living room of the home economics cottage. <em>LSU Gumbo</em>, 1939.” This draws on the yearbook text in <code>hist_12</code>.
</li><li><strong>clark2:</strong> “A.B. Clark. <em>LSU Gumbo</em>, 1942.” <strong>dorothy:</strong> “Dorothy Colvin. <em>LSU Gumbo</em>, 1941.”
</li><li><strong>lab / lab_alt:</strong> “Students in a laboratory. LSU Photograph Collection, …”
</li><li><strong>juban1 / juban2:</strong> “A scrapbook page labeled ‘Laundress.’ / ‘Downstairs Maid.’ Scrapbook photo …”
</li><li><strong>hist_13:</strong> “‘Husbands Learn Housework in LSU Home Economics’: married couples live in the home management houses for the required internship, ca. 1950s. Newspaper clipping from the Human Ecology Department scrapbook.”
</li><li><strong>hist_14:</strong> “‘All-Feminine Reign Ends at Morris House With Advent of Two GIs and Their Wives.’ Married couples …, ca. 1950s. Newspaper clipping …”
</li><li><strong>hist_18:</strong> “LSU campus; the arrow points to the Agnes Morris House, a home management residence. <em>LSU Gumbo</em>, 1939.”
</li><li><strong>hist_23:</strong> “The Agnes Morris House. Scrapbook photo …”
</li><li><strong>group1:</strong> the names as transcribed (see To verify), followed by “Scrapbook photo …”
</li><li><strong>hist_7, hist_8:</strong> “LSU home management houses, ca. 1939–43. Photo courtesy of the Human Ecology Department.”
</li><li><strong>hist_9–12:</strong> “LSU home management houses. <em>LSU Gumbo</em>, 1939.”
</li></ul>
<h4>Alt text (all new; the old alt text was “image-1” or a wrong repeated caption)</h4>
<ul>
<li><strong>phi</strong>: Yearbook page for Phi Upsilon Omicron, National Honorary and Professional Home Economics Fraternity, listing its officers, with Dorothy L. Colvin as treasurer, and its members.</li>
<li><strong>curriculum</strong>: Printed bulletin page of course requirements by class year for the Curriculum in Homemaking and Personal Living and the Curriculum in Home Demonstration.</li>
<li><strong>working1</strong>: Two young women in aprons work at a kitchen counter by a bright window.</li>
<li><strong>working2</strong>: A young woman sews at an electric sewing machine.</li>
<li><strong>mops</strong>: Two young women lean against a brick wall beside a rack of mops, one holding a broom.</li>
<li><strong>menu</strong>: Handwritten scrapbook page: the housemothers are entertained at a buffet supper, March 7, 1938, with the menu, the guests and each student’s duties.</li>
<li><strong>flower1</strong>: A young woman in a light dress picks greenery from a tall shrub.</li>
<li><strong>flower2</strong>: A bowl of cut flowers on a sideboard beneath a framed landscape print.</li>
<li><strong>company</strong>: Three young women and two young men sit talking and reading newspapers on a sofa and chairs in a living room.</li>
<li><strong>linen</strong>: Seen from behind, a young woman sits at a table by a curtained window.</li>
<li><strong>corner</strong>: Five smiling young women pose among banana plants beside a brick house.</li>
<li><strong>clark2</strong>: Yearbook portrait of A.B. Clark, smiling, in a dark top with a white collar.</li>
<li><strong>dorothy</strong>: Yearbook portrait of Dorothy Colvin, with dark curled hair, in a checked dress.</li>
<li><strong>lab</strong>: Women students work at long laboratory benches lined with glassware and burners.</li>
<li><strong>lab_alt</strong>: Women students in white coats work at long laboratory benches lined with glassware.</li>
<li><strong>sit4</strong>: Six young women stand together on the steps of a brick house.</li>
<li><strong>juban1</strong>: Scrapbook page labeled “Laundress”: a young woman hangs sheets on a clothesline outside a brick house.</li>
<li><strong>juban2</strong>: Scrapbook page labeled “Downstairs Maid”: a young woman sweeps a walk in front of a house.</li>
<li><strong>hist_13</strong>: Newspaper clipping headlined “Husbands Learn Housework in LSU Home Economics,” with a photo of a man vacuuming and another dusting while three women sit on a sofa.</li>
<li><strong>group1</strong>: Scrapbook page: five women sit together on a garden bench, with their names handwritten beneath.</li>
<li><strong>group2</strong>: Four young women sit close together, talking, in a room with a paneled door.</li>
<li><strong>hist_1</strong>: Two Black women at a kitchen stove, one tending a pressure canner, with jars of preserves on the counter.</li>
<li><strong>hist_2</strong>: Three white women in aprons stand around a wood stove with pressure canners and jars of preserves.</li>
<li><strong>hist_3</strong>: Two women in aprons work at a kitchen stove and sink, canning preserves.</li>
<li><strong>hist_4</strong>: Two women stand in a field of crops, one in a white dress and hat working the rows with a hoe.</li>
<li><strong>hist_5</strong>: A Black family of women and children seated in a row, eating from plates in their laps.</li>
<li><strong>hist_7</strong>: A Black woman feeds linens through a large rolling ironer.</li>
<li><strong>hist_8</strong>: A young woman irons at an ironing board in a sunlit room.</li>
<li><strong>hist_9</strong>: A young woman in a dark dress irons linens at an ironing board.</li>
<li><strong>hist_10</strong>: Two women sit by a sewing machine, one in a rocking chair holding fabric.</li>
<li><strong>hist_11</strong>: Three young women and two young men sit talking and reading newspapers in a living room.</li>
<li><strong>hist_12</strong>: Yearbook text headed “Agriculture”: scenes in the home economics cottage, where girls majoring in domestic science are trained in home management and entertain their dates in the living room.</li>
<li><strong>hist_14</strong>: Newspaper clipping headlined “All-Feminine Reign Ends at Morris House With Advent of Two GIs and Their Wives,” with a photo of two men hanging curtains while a woman watches.</li>
<li><strong>hist_15</strong>: Aerial view of the LSU campus with its oval parade ground and the Memorial Tower.</li>
<li><strong>hist_16</strong>: Huey Long, in a light suit and holding a hat, stands arm in arm with young women in white before a crowd.</li>
<li><strong>hist_17</strong>: A one-story house with a hipped roof and screened porch, captioned “Home” on the scrapbook page.</li>
<li><strong>hist_18</strong>: Aerial view of the LSU campus; a red arrow points to the Agnes Morris House.</li>
<li><strong>hist_19</strong>: Line map of the Louisiana State University campus, with its buildings, roads, parade ground and lake.</li>
<li><strong>hist_20</strong>: Yearbook page of oval portraits of the members of the Home Economics Club, 1933.</li>
<li><strong>hist_22</strong>: A young woman hangs white laundry on a line in a yard beside a brick house.</li>
<li><strong>hist_23</strong>: The Agnes Morris House, a two-story brick house with a tile roof.</li>
<li><strong>hist_24</strong>: The Helen Carter House, a one-story brick house with a tile roof and a sign by its door.</li>
<li><strong>sit1</strong>: Six young women sit talking on wicker furniture on a sunporch.</li>
<li><strong>sit2</strong>: Three young women lie on the grass, laughing together.</li>
<li><strong>sit3</strong>: Five young women in long dresses stand in front of a brick house.</li>
</ul>
HTML),
]);

$section('Mossville History Project', [
  $text(NULL, <<<'HTML'
<p>Now at <a href="/v2/oralhistory/exhibitions/mossvilleexhibit">/v2/oralhistory/exhibitions/mossvilleexhibit</a>.</p>
<p>Old pages: <a href="https://lib.lsu.edu/sites/all/files/oralhistory/presentations/mossvilleexhibit/">https://lib.lsu.edu/sites/all/files/oralhistory/presentations/mossvilleexhibit/</a>. Migrated: /v2/oralhistory/exhibitions/mossvilleexhibit.</p>
<p>Compared on 2026-11-19: the old index, About page and all 70 clip pages (c1–c76) against <code>scripts/data/mossville.json</code>. Every old page loaded. All 70 clips are in the same galleries and the same order as the old History, Culture and Economy menus.</p>
<p>Not listed: markup, whitespace, double spaces and curly vs straight quotes. Speaker labels are now bold on their own paragraphs, but no label text changed. Apart from c52, every transcript matches the old one word for word.</p>
<h3>Corrected</h3>
<h4>Credit lines (all clips)</h4>
<ul><li>All 70 clips › credit: "&lt;Narrator&gt; by &lt;Interviewer&gt;, &lt;year&gt;." → "&lt;Narrator&gt;, interviewed by &lt;Interviewer&gt;, &lt;year&gt;" (format only, 70×; the stray comma in "Arthur Kenneth Lee, by" (c38) and "LaSalle Williams, by" (c27) is gone)
</li><li>Interviewer name: "Chelsea Areseneault" → "Chelsea Arseneault" (4×: Heritage › c31, Politics &amp; Integration › c22, The Old Ways › c14 credits; Education › c12 caption)
</li><li>Interviewer name: "Chelsea Aresneault" → "Chelsea Arseneault" (3×: The Old Ways › c15, c49, c50)
</li><li>Interviewer name: "Chelsea Arsenault" → "Chelsea Arseneault" (The Old Ways › c53)
</li><li>Interviewer name: "Chelsea Arseneualt" → "Chelsea Arseneault" (Mossville Moving › c36)
</li><li>Interviewer name: "Stpehanie Dragoon" → "Stephanie Dragoon" (Mossville Moving › c57)
</li><li>Interviewer name: "Douglas Mungin" → "Doug Mungin" (2×: Family &amp; Community › c58, Health &amp; Environment › c46)
</li><li>Interviewer name: "Jennifer Cramer" → "Jennifer A. Cramer" (Heritage › c3)
</li><li>Narrator name: "Joesph Charles" → "Joseph Charles" (Education › c11 credit)
</li><li>Narrator name: "Elaine Robison" → "Elaine Robinson" (Politics &amp; Integration › c22: credit, old menu line and caption, 3×)
</li><li>Narrator name: "Huber “Mickey” Smith, Sr." → "Huber “Mickey” Smith Sr." (comma removed: c17 and c20 credits, plus old menu lines for c17, c20, c24, c6; 6×)
</li></ul>
<h4>Clip titles</h4>
<ul><li>Places › c17: "Mossville’s post office." (clip page) → "Mossville’s post office"
</li><li>Places › c37: "…remembering Pete Moss' honecomb in the school chimney" (old menu) → "…honeycomb…"
</li><li>Politics &amp; Integration › c51: "Jacob Rigmaidenand Josh Rigmaiden as Mayors of Mossville" (old menu) → "Jacob Rigmaiden and Josh Rigmaiden as Mayors of Mossville"
</li><li>The Old Ways › c14: "Subsistence Living" (old menu) → "Subsistence living"
</li><li>The Old Ways › c23: "Home Remedies" (old menu) → "Home remedies"
</li><li>The Old Ways › c18: "Selling Pecans" (old menu) → "Selling pecans"
</li><li>The Old Ways › c50: "Folk Remedies for ailments and injuries." (old menu) → "Folk Remedies for ailments and injuries"
</li><li>Education › c52: "Sharing lunches underneath the two-room School house" (clip page) → "Sharing lunches underneath the two-room schoolhouse"
</li><li>Education › c62: "Attending all black school aided her education and confidence" (old menu) → "Attending an all-black school aided her education and confidence"
</li><li>Education › c12: "The diference between teaching at Mossville and Westwood Elementary…" → "The difference…" (old menu and clip page, 2×)
</li><li>Education › c40: "…inspired him to stay on the Honor Roll" (old menu) → "…honor roll"
</li><li>Church › c47: "Mount Zion, Willow Springs Churches" (old menu) → "Mount Zion and Willow Springs Churches"
</li><li>Church › c44: "Her Mother kept the Morning Star Cemetery in good condition" (old menu) → "Her mother kept…"
</li><li>Church › c28: "Grandparents’ home, a holy place" (old menu) → "His grandparents’ home was a holy place"
</li><li>Church › c74: "Pew Rallies" (old menu) → "Pew rallies"
</li><li>Family &amp; Community › c75: "Kinship and Community" (old menu) → "Kinship and community"
</li><li>Family &amp; Community › c67: "It takes a village…" / "It takes a village..." (old menu / clip page) → "It takes a village"
</li><li>Work › c59: "Plants as part of the community." (old menu) → "Plants as part of the community"
</li><li>Health &amp; Environment › c29: "Running down Old Spanish Trail at night during plant explosions" (old menu) → "…after plant explosions" (to match the clip page)
</li><li>Health &amp; Environment › c61: "Incidents at the nearby plants" (old menu) → "Incidents at the nearby plant" (to match the clip page)
</li><li>Health &amp; Environment › c46: "The perserverence of Mossvillians" → "The perseverance of Mossvillians" (old menu and clip page, 2×)
</li></ul>
<h4>Photo captions</h4>
<ul><li>"Photo. Photo by" → "Photo by" (fixed 2026-11-19; the migration had doubled "Photo." where the old caption had two spaces before its credit). Clip captions, 17×: Places › c16, c27; Politics &amp; Integration › c8; The Old Ways › c15, c18, c49, c50; Education › c52, c66, c26; Church › c70; Family &amp; Community › c48; Work › c19, c55, c43; Health &amp; Environment › c65; Mossville Moving › c36. Gallery lead captions, 4×: Politics &amp; Integration, Education, Family &amp; Community, Work.
</li><li>Family &amp; Community › c48 and the gallery’s lead caption: "Lenoria Ambrose interviewed by Chelsea Arseneault and Teresha Ussin, 2015." → "Vera Payne, 2015." (fixed 2026-11-19). The photo is of Vera Payne; the old caption named the wrong person.
</li><li>Work › c55: "Photo by Kyle Tanglao." → "John Bernard. Photo by Kyle Tanglao."; Work › c43: → "Delma and Christine Bennett. Photo by Kyle Tanglao." (fixed 2026-11-19)
</li><li>"By Kyle Tanglao" → "Photo by Kyle Tanglao" (4×: Heritage › c1, c2; Places › c5; Church › c71)
</li><li>Education › c25: "Della Dotson, 2015. By Stephanie Dragoon." → "Della Dotson, 2015. Photo by Stephanie Dragoon."
</li><li>Heritage › c4: "George Braxton, 2015. By Kyle Tanglao." (the caption belonged to a different photo) → "Old Spanish Trail map: the Southern Borderland Trunkline from California to Florida."
</li><li>Heritage › c31: "Mossville, 1932 Map. LSU Cartographic Information Center" → "Map of Mossville, 1932. LSU Cartographic Information Center."
</li><li>Places › c17: "Memory Map of Mossville drawn by Huber " Mickey" Smith, 2015." (broken quote, so the old page showed only "Memory Map of Mossville drawn by Huber") → "Memory map of Mossville drawn by Huber “Mickey” Smith Sr."
</li><li>Politics &amp; Integration › c9: "A segregated cafe Durham, North Carolina. Photo by Jack Delano, 1940. Library of Congress." → "A segregated café in Durham, North Carolina, 1940. Photo by Jack Delano, Library of Congress."
</li><li>Politics &amp; Integration › c10: "Jackie Robinson, baseball card, 1953. Robinson was 1st African American to play MLB, 1947. National Museum of American History, coll.545." → "Jackie Robinson’s 1953 Topps baseball card. In 1947 Robinson became the first African American to play in Major League Baseball. National Museum of American History."
</li><li>Politics &amp; Integration › c7: "Christine Bennet, 2015." → "Christine Bennett, 2015."
</li><li>Politics &amp; Integration › c76: "Butch and Leona Lemelle, 2015." → "Butch and Leona Lemelle with interviewer Jennifer A. Cramer, 2015."
</li><li>Politics &amp; Integration › c20 and The Old Ways › c24: "Huber “Mickey” Smith, 2015, by Kyle Tanglao" → "Huber “Mickey” Smith Sr., 2015. Photo by Kyle Tanglao." (2×)
</li><li>Mossville Moving › c6: "Huber Smith, 2015. Photo by Kyle Tanglao." → "Huber “Mickey” Smith Sr., 2015. Photo by Kyle Tanglao."
</li><li>The Old Ways › c53: "Photo by Kyle Tanglao." → "Lillian Washington. Photo by Kyle Tanglao."
</li><li>The Old Ways › c23: "Common home remedy" → "A bottle of castor oil, a common home remedy."
</li><li>Education › c11: "Photo by Warren K. Leffler, 1957. Library of Congress, Prints and Photograph Division." → "An integrated high school classroom, 1957. Photo by Warren K. Leffler, Library of Congress, Prints and Photographs Division."
</li><li>Education › c40: "Kenneth Lee being interviewe by Doug Mungin. Photo by Kat Donner." → "Arthur Kenneth Lee being interviewed by Doug Mungin. Photo by Kat Donner."
</li><li>Education › c73: "Lenoria Ambrose, 2015." → "Lenoria Ambrose with her interviewers, 2015."
</li><li>Church › c44: "Gravesite at Mt. Zion Baptist Church. Photo by Lane Lefort." → "A grave at Mt. Zion Baptist Church. Photo by Lane Lefort."
</li><li>Church › c28: "Butch Lemelle, 2016." → "Butch and Leona Lemelle with interviewer Jennifer A. Cramer, 2015." (year changed, see To verify)
</li><li>Church › c74: "Lenoria Ambrose interviewed by Chelsea Arseneault and Teresha Ussin, 2015. Photo by Kyle Tanglao." → "Lenoria Ambrose, interviewed by Chelsea Arseneault and Teresha Ussin, 2015." (photo credit dropped, see To verify)
</li><li>Family &amp; Community › c75: "Photo by Lane Lefort, 2015." → "Braxton Lane, Mossville, 2015. Photo by Lane Lefort."
</li><li>Family &amp; Community › c67: "Photo by Lane Lefort, 2015." → "The Jacob Rigmaiden Center, 2015. Photo by Lane Lefort."
</li><li>Captions that were only a photo credit now name the subject and year ("Photo by X." → "&lt;Name&gt;, &lt;year&gt;. Photo by X."), 9×: Family &amp; Community › c58 (Kevin Fondel Sr., 2015), c35 (Theresa Lee, 2016), c60 (Brenda Cole Jones, 2016); Work › c59 (Kevin Fondel Sr., 2015); Health &amp; Environment › c39 (Arthur Kenneth Lee, 2015), c69 (Haki Vincent, 2016); Mossville Moving › c63 (Arthur Kenneth Lee, 2015), c64 (Theresa Lee, 2017), c54 (Shirley Andrus and Carolyn Marshall, 2015)
</li><li>Work › c45: "Photo by Kyle Tanglao" → "Photo by Kyle Tanglao, 2015."
</li><li>Health &amp; Environment › c30: "Mossville satellite image" → "Satellite view of Mossville and the plants beside it, 2017. Imagery © Google."
</li><li>Health &amp; Environment › c29: "Debra Ramirez, Lake Charles, LA: Home Adjacent to Petro Chemical Plants; Fought Several Petro Chemical Companies Regarding Ground Water Contamination" → "Debra Ramirez of Lake Charles, Louisiana, whose home adjoined petrochemical plants. She fought several companies over groundwater contamination."
</li><li>Mossville Moving › c41: "Slide presentation from Wilma Subra. Toxic Release Inventory Report. <a href="http://www.dscej.org/images/pdfs/2015TRIRegionalWorkshop/WilmaSubraPresentation.pdf"">http://www.dscej.org/images/pdfs/2015TRIRegionalWorkshop/WilmaSubraPresentation.pdf"</a> → "Dioxin levels in blood samples, from Wilma Subra’s 2015 Toxic Release Inventory presentation." (URL dropped)
</li><li>A final period was added to 2 captions that were otherwise unchanged: Places › c38 ("…Photo by Jamie Digilormo"), Church › c47 ("…Photo by Lane Lefort")
</li></ul>
<h4>Transcript</h4>
<ul><li>Education › c52: "Coming soon." → "Transcript to come."
</li></ul>
<h4>About the Project</h4>
<ul><li>The old About page's opening paragraph is now the exhibition intro, split into two paragraphs. Its sentences "By 2017, the majority…" and "The purpose of the collection…" moved from the second old paragraph into the intro.
</li><li>Intro: "Calcasieu Parish, Louisiana and many residents" → "Calcasieu Parish, Louisiana, and many residents"; "“Shoat’s Prairie” when it was renamed" → "“Shoat’s Prairie”, when it was renamed"
</li><li>"Patrons can access the interviews here at the Listening Station, online via the LSU Digital Library site, through the Reading Room at the LSU Libraries Special Collections, and by visiting the Imperial Calcasieu Museum and McNeese University Archives, where copies are also available." → "The full interviews are available through the LSU Digital Library, the Reading Room at LSU Libraries Special Collections, the Imperial Calcasieu Museum and the McNeese State University Archives."
</li><li>Removed: the "Navigation of the Listening Station…" paragraph ("…Drag two fingers to scroll."), the paragraph beginning "History, Culture, and Economy are comprised of over 70 brief, diverse clips…", and the "Digital Library section…" paragraph. That paragraph repeated the 59 collections / 67 people / 90 hours figures and ended "About provides information on the background and scope of the project."
</li><li>Removed: "We appreciate the help of many other staff members for their assistance throughout various stages of planning and installation."
</li><li>"Imperial Calcasieu Museum under the direction of Susan Reed and the Williams Center" → "Imperial Calcasieu Museum, under the direction of Susan Reed, and the Williams Center" (this sentence moved into the Acknowledgments, after the curators sentence)
</li><li>"Center Graduate Assistants who engineered the audio." (a sentence fragment) → "Center Graduate Assistants, engineered the audio."
</li><li>"Special thanks, in particular to Chelsea, … collections processing made this entire endeavor possible." → "Special thanks, in particular, to Chelsea, … collections processing, made this entire endeavor possible."
</li><li>"Acknowledgments:" run-in label → "Acknowledgments" heading
</li><li>Gallery name: "About" → "About the Project"
</li></ul>
<h3>To verify</h3>
<h4>Introduced in migration</h4>
<ul><li>Work › c45: the caption "Photo by Kyle Tanglao, 2015." doesn’t name the narrator.
</li><li>Church › c74: the new caption drops the old photo credit "Photo by Kyle Tanglao". Work › c33 now uses the same caption, with no credit (it had no old caption).
</li><li>Places › c17: the old caption's "2015" was dropped. The new caption has no date.
</li><li>Church › c28: the caption year changed from 2016 to 2015 (now the same caption as c76), but the c28 credit still says 2016.
</li></ul>
<h4>Kept from the old text</h4>
<ul><li>Interviewers and years that don't agree across clips:
<ul><li>Vera Payne: c48 credits Chelsea Arseneault, while c2 and c47 credit Doug Mungin (all 2015). The c47 and c48 audio files are both "vera_payne_update".
</li><li>Butch Lemelle, interviewed by Jennifer A. Cramer: c3 and c28 say 2016, c76 says 2015.
</li><li>Hal McMillin: c65 says 2016, c36 says 2015, and the c65 caption says 2015.
</li><li>George Braxton: c9 says 2016, while c4, c71 and c75 say 2015.
</li><li>Judy Montgomery-Gauthia and Elaine Robinson: c16 says 2016, c22 says 2015.
</li></ul>
</li><li>c53 (Lillian Washington), c52 (Evelyn Gasaway Shelton) and c43 (Christine Bennett) credits have no year.
</li><li>Family &amp; Community › c35: the credit names "Theresa Lee and Dawn Holman", but the caption names only Theresa Lee.
</li><li>Work › c45: the narrator's name "Edward Julia Lemelle Gordwin" is unusual. Please confirm it.
</li><li>Work › c43: the title still reads "the proxminity of plants and people" (typo for "proximity"). Please also confirm "Cit-Con".
</li><li>Places › c34: the caption still has a comma where a period belongs: "McKenneth Edwards, 2015, Photo by Charlotte Willcox."
</li><li>Title capitalization is mixed: some titles use sentence case, others title case. Title-case examples: "Mossville's Early Families" (c2), "School Integration" (c8), "Sketchy Politicking" (c20), "Voting Rights" (c21), "…Mossville School Building…" (c37), "Folk Remedies for…" (c50).
</li><li>Spelling varies between pages:
<ul><li>"African-American" (c10 title) vs "African American" (c10 caption, About).
</li><li>"Shoats Prairie" (c27, c31 titles) vs "Shoat’s Prairie" (intro).
</li><li>"Mount Zion" (c47 title) vs "Mt. Zion" (captions).
</li><li>"majorettes sweater" (c26 caption) vs "majorette sweater" (alt text).
</li></ul>
</li><li>Transcripts were kept verbatim, including 14 bracketed uncertain readings in 9 clips:
<ul><li>c1 "[Sanky?]"
</li><li>c3 "[1908?]"
</li><li>c15 "[Lanine?]"
</li><li>c50 "[tin?]"
</li><li>c25 "[Billups?]"
</li><li>c11 "[?]", "[Meyers?]" ×2, "[Danny Cupid’s?]"
</li><li>c44 "[Prior?]"
</li><li>c55 "[like I said?]", "[?]"
</li><li>c30 "[Verdine?]", "[Lonnie?]"
</li></ul>
</li><li>Transcript slips were also kept, such as c16 "I think its Route One" and c30 "drawing ,umm umm,".
</li><li>In the old menu, two speaker lines were malformed ("John Bernard by Doug Mungin" for c55, "Debra Ramirez by Peggy Frankland, 2000." for c29). The migrated credit lines replace them.
</li><li>Education gallery: the lead photo and caption are c52's (Evelyn Gasaway Shelton), but c52 is built unpublished.
</li></ul>
<h4>Audio relinked</h4>
<p>The old audio couldn't be listened to here. A curator should play c34 and c48 against their transcripts.</p>
<ul><li>Places › c34 (McKenneth Edwards): the old page played "audio/27_LaSalle_Williams.mp3", which is the c27 clip. It now plays "34_mckenneth_edwards.m4a".
</li><li>Family &amp; Community › c48 (Vera Payne): the old page played "audio/71_George_Braxton.mp3", which is the c71 clip. It now plays "48_vera_payne_update.m4a".
</li><li>Education › c52 (Evelyn Gasaway Shelton): the old page's player pointed at "audio/.mp3" (no file). It now plays "52_evelyn_gasaway_shelton.m4a". The clip has no transcript ("Transcript to come.") and is built unpublished.
</li></ul>
<h4>Credits and facts added in new captions</h4>
<p>Please check the sources for these:</p>
<ul><li>c4: "Southern Borderland Trunkline from California to Florida". The map has no source credit.
</li><li>c30: "2017. Imagery © Google."
</li><li>c10: "Topps", with "coll.545" dropped.
</li><li>c41: the source URL was dropped.
</li><li>About: "McNeese University" → "McNeese State University".
</li></ul>
<h3>New wording to check</h3>
<ul><li>Exhibition subtitle: "Oral histories of a Calcasieu Parish community"
</li><li>Exhibition credit line: "A collaboration between the Imperial Calcasieu Museum and the T. Harry Williams Center for Oral History, LSU Libraries. Curated by Jennifer A. Cramer and Erin Hess. Grant funding provided by Sasol."
</li><li>About the Project:
<ul><li>Link text "Browse the full Mossville interviews". It points to the old Mossville directory on lib.lsu.edu.
</li><li>The sentence "The full interviews are available through…" (listed above).
</li></ul>
</li><li>Captions written where the old page had none (3):
<ul><li>Old Ways › c14: "Myrtle Marie Rosamore with her interviewer, 2015."
</li><li>Work › c33: "Lenoria Ambrose, interviewed by Chelsea Arseneault and Teresha Ussin, 2015."
</li><li>Health &amp; Environment › c46: "Daren Dotson Sr. with interviewer Doug Mungin, 2015."
</li></ul>
</li><li>Gallery lead captions (10). Each one repeats the caption of the gallery's lead photo:
<ul><li>Heritage: c1
</li><li>Places: c5
</li><li>Politics &amp; Integration: c8
</li><li>The Old Ways: c53
</li><li>Education: c52, which is unpublished
</li><li>Church: c47
</li><li>Family &amp; Community: c48
</li><li>Work: c19
</li><li>Health &amp; Environment: c30
</li><li>Mossville Moving: c6
</li><li>About the Project has no lead caption.
</li></ul>
</li><li>Audio credit lines (70): new "interviewed by" format, listed under Corrected.
</li><li>Alt text, 61 new, one for every image. The old alt text was short labels, photo credits or empty, and all of it was replaced.
<ul><li>46 narrator portraits and interview scenes. These describe clothing and setting and name the people. The c12 and c38 alt texts also name Chelsea Arseneault. c45's alt text ("A woman in glasses and a red necklace…") doesn't name the narrator.
</li><li>5 maps and data images: the 1932 map (c31), the hand-drawn memory map (c17), the Old Spanish Trail map (c4, "from San Diego to St. Augustine, Florida"), the satellite view (c30) and the dioxin table (c41). The c41 alt text quotes figures ("68.3 parts per trillion in 1997–98 and 61.0 in 2001, about three times the national control group"), so check them against the slide.
</li><li>10 places, objects and historical photos:
<ul><li>the About cover (project sign)
</li><li>the segregated café (c9)
</li><li>the integrated classroom (c11)
</li><li>the castor oil bottle (c23), whose alt text names "Williams &amp; Carleton, wholesale druggists of Hartford, Connecticut"
</li><li>the Jackie Robinson card (c10)
</li><li>Mt. Zion Baptist Church (c47)
</li><li>a grave at Mt. Zion (c44)
</li><li>the Jacob Rigmaiden Center sign (c67)
</li><li>the Braxton Ln sign (c75)
</li><li>a man and horse (c57)
</li></ul>
</li></ul>
</li></ul>
HTML),
]);

$section('New Orleans Jazz & Heritage Festival', [
  $text(NULL, <<<'HTML'
<p>Now at <a href="/v2/oralhistory/exhibitions/jf">/v2/oralhistory/exhibitions/jf</a>.</p>
<p>Old site: <code>/sites/all/files/oralhistory/presentations/jf/</code> (entry <code>audio.html</code>; listed on the old exhibitions page as "Jazz Fest: 2014 Student Exhibit"). Note: this exhibition is about the Jazz &amp; Heritage Festival, not jazz funerals.</p>
<p>The page has three galleries: <strong>Voices of the Festival</strong> (7 clip entries), <strong>Memory Maps</strong> (3 drawings) and <strong>In the Field</strong> (3 text blocks).</p>
<h3>Corrected</h3>
<h4>Exhibition title</h4>
<ul><li>Page <code>&lt;title&gt;</code> on the old About, Home, Pictures and Acknowledgements pages: "TEMPESTS: Storms in the Archives" → "New Orleans Jazz &amp; Heritage Festival". Those four pages were copies of the Tempests exhibition's pages and are <strong>not carried over</strong>.
</li><li>Old clip pages C14–C41 (Tempests clips left in the folder, not linked from the jf menu) and the orphan page <code>I1.html</code> (broken image link <code>../photos/Memory1.jpg</code>) were <strong>not carried over</strong>.
</li></ul>
<h4>Voices of the Festival: speaker labels</h4>
<p>In every clip, speaker labels are now bold, with the narrator's full name on first use and the surname after that. Interviewers keep the surname only, because only surnames are known.</p>
<ul><li>Geraldine Vaucresson: "VAUCRESSON:" (first) → "GERALDINE VAUCRESSON:"
</li><li>Patrick Gallagher: "GALLAGHER:" (first) → "PATRICK GALLAGHER:". The second "GALLAGHER:" label, in the very next paragraph with no one speaking in between, was removed.
</li><li>Joseph "Cool" Davis: "DAVIS:" → "JOSEPH “COOL” DAVIS:"
</li><li>Tanya Huang: "HUANG:" → "TANYA HUANG:"
</li><li>Philip Giroir: "GIROIR:" (first) → "PHILIP GIROIR:"
</li><li>Terrence Osborne: "OSBORNE:" → "TERRENCE OSBORNE:"
</li><li>Wynoka Boudreaux: "BOUDREAX:" (misspelled) → "WYNOKA BOUDREAUX:"
</li></ul>
<h4>Voices of the Festival: transcript text</h4>
<ul><li>Geraldine Vaucresson: "my great grandmother" → "my great-grandmother"
</li><li>Patrick Gallagher: "Guillain-Barre syndrome" → "Guillain-Barré syndrome"
</li><li>Joseph "Cool" Davis: "come down south. . . to see" → "come down south . . . to see"
</li><li>Joseph "Cool" Davis: "People come and look at to you" → "People come and look at you"
</li><li>Terrence Osborne: missing closing quote added: "…so, can I get two weekends? [laughs]" → "…so, can I get two weekends?” [laughs]"
</li><li>Terrence Osborne: "I had to - even if I did the poster - I had to" → "I had to—even if I did the poster—I had to"
</li><li>All clips: straight quotes → typographic (curly) quotes.
</li></ul>
<h4>Voices of the Festival: captions</h4>
<ul><li>Gallagher: "Patrick Gallagher." → "Patrick Gallagher, Tape No. 4298." Every caption now follows the pattern "Name, Tape No. ####." (see section 3).
</li></ul>
<h4>In the Field: Field Notes (old C12.html)</h4>
<ul><li>Heading: "Reflections on Oral History with Jo "Cool" Davis" → "Field Notes: Reflections on Oral History with Jo “Cool” Davis" (the "Field Notes" label came from the old menu)
</li><li>"it struck both Charlotte and I" → "it struck both Charlotte and me"
</li><li>"shook both my and Charlotte's hands, motioned for us" → "…hands, and motioned for us"
</li><li>"t-shirt" / "t-shirts" → "T-shirt" / "T-shirts"
</li><li>"Jo "Cool" Davis" (nested double quotes) → "“Jo ‘Cool’ Davis”"
</li><li>"tell his tale - all of it" → "tell his tale—all of it"
</li><li>"Jazzfest" (twice) → "Jazz Fest"
</li><li>"Potential follow-up questions:" with typed numbers "1. 2. 3." → a bold label followed by a numbered list
</li></ul>
<h4>In the Field: Questions for Dwayne Breashears (old C11.html)</h4>
<ul><li>"childhood/adolescence" → "childhood or adolescence"
</li><li>"Program Director" (twice) → "program director"
</li><li>"What kind of genres" → "What kinds of genres"
</li><li>"OZ's place" → "WWOZ’s place"
</li><li>The question "Did you have any influential family members… radio and music? Did you have a mentor figure…" was split into two list items.
</li></ul>
<h4>In the Field: review of <em>Number Our Days</em> (old C13.html)</h4>
<ul><li>Heading: "Review of Number Our Days" → "Interview Reflections: A Review of <em>Number Our Days</em>" (label from the old menu; book title in italics throughout)
</li><li>"not only to the strength of the Jewish people, but as a testament also to the enduring nature" → "…but also to the enduring nature"
</li><li>"As Myerhoff takes one of her main informant, Schmuel's, advice, "Let people surprise you," (41)" → "As Myerhoff takes the advice of one of her main informants, Schmuel, “Let people surprise you” (41),"
</li><li>"experience. She nimbly weaves" → "experience. Myerhoff nimbly weaves"
</li><li>"Each other the elder's individual personalities are allowed to shine" → "Each of the elders’ individual personalities is allowed to shine"
</li><li>"not merely 'for color,'" → "not merely “for color,”"
</li><li>"these people lives" → "these people’s lives"
</li><li>"would have lack authenticity" → "would have lacked authenticity"
</li><li>"the loving eye in which she gazed as her subjects" → "the loving eye with which she gazed at her subjects"
</li><li>"the elder's monologues" → "the elders’ monologues"
</li><li>"the elder's community" → "the elders’ community"
</li><li>"veritable goldmines" → "veritable gold mines"
</li><li>"the context of the lives' struggles - from poverty" → "the context of their lives’ struggles—from poverty"
</li></ul>
<h3>To verify</h3>
<ul><li><strong>Interviewers' full names.</strong> Only surnames appear in the transcripts: Nguyen (Vaucresson), Bauer (Gallagher), Donner (Huang), Pontiff (Giroir) and Roux (Osborne). The Davis interviewers appear only as "Charlotte" and the unnamed author of the field notes. The Boudreaux clip has no interviewer at all. Because the names are incomplete, clip credits read "Name, Tape No. ####" instead of "interviewed by…".
</li><li><strong>Authors of the fieldwork texts.</strong> The questions are credited in the old menu only as "Wilcox and Breashears", which reads as Wilcox (student) and Breashears (narrator). The field notes and the review have no named author.
</li><li><strong>Dwayne Breashears / WWOZ.</strong> I inferred from the questions that he was WWOZ's program director; confirm the name spelling and title.
</li><li><strong>Interview year.</strong> The exhibition is dated 2014 from the old exhibitions list, which is also the page's year tile. The interviews look like 2013 (the memory maps are dated 2013), but I couldn't confirm it, so no clip shows a year.
</li><li><strong>"Joseph" vs. "Jo" Davis.</strong> The old menu says Joseph "Cool" Davis, but the field notes, his van and his shirt say Jo "Cool" Davis. Both are kept as written.
</li><li><strong>"Mark Courbet"</strong> (Gallagher clip): spelling unconfirmed.
</li><li><strong>"Folks Village"</strong> (Boudreaux clip): the festival's area is officially the Folklife Village. Left as spoken.
</li><li><strong>Left as spoken but possibly mistranscribed:</strong> "we did my colors some pine cones with glitter" and "things I bought he would take it all loose" (Boudreaux); "They had a lot of New Orleans artists got used that would not come down south" (Davis). Worth checking against the audio.
</li><li><strong>"Schmuel"</strong> (review): the book spells the name Shmuel. Kept as the student wrote it.
</li><li><strong>Giroir photo.</strong> It is an old snapshot from a photo album (a bearded man in a hat with a child), not a photo of the interview. The old caption just said "Philip Giroir". Confirm who is pictured.
</li><li><strong>Who is pictured with the narrators.</strong> The young women in the Vaucresson, Gallagher, Davis and Huang photos are probably the student interviewers, and the man in the Boudreaux photo is perhaps her father. None of them is named.
</li><li><strong>Memory Map 2</strong> has no author written on it. Memory Map 1 is signed only with an illegible signature.
</li></ul>
<h3>New wording to check</h3>
<h4>Title page</h4>
<ul><li>Subtitle: "Doing oral history at Jazz Fest" (adapted from the old page title "Doing Oral History at New Orleans Jazz &amp; Heritage Festival")
</li><li>Credit: "A student exhibition from the T. Harry Williams Center for Oral History, LSU Libraries, 2014."
</li><li>Intro (entirely new, because the old exhibition had none of its own): &gt; For more than four decades, the New Orleans Jazz &amp; Heritage Festival has drawn musicians, artists, &gt; cooks, and music lovers to the city each spring. In this 2014 student exhibition, students working &gt; with the T. Harry Williams Center for Oral History recorded the people who make the festival what it &gt; is: crew members who work it year after year, a poster artist, a street musician, a second line &gt; culture bearer, and New Orleanians who remember what the festival meant for a segregated city. &gt; &gt; Alongside their excerpts are memory maps that festivalgoers drew of their own Jazz Fest, and pages &gt; from the students’ fieldwork: interview questions, field notes, and a reflection on oral history &gt; method.
</li><li>Year tile: 2014. Cover image: Memory Map 3.
</li><li>Gallery names (new): "Voices of the Festival", "Memory Maps" (from the old menu), "In the Field".
</li></ul>
<h4>Captions (lead captions repeat the first entry's caption)</h4>
<ul><li>"Geraldine Vaucresson, Tape No. 4300." (also the Voices of the Festival lead caption)
</li><li>"Patrick Gallagher, Tape No. 4298."
</li><li>"Joseph “Cool” Davis, Tape No. 4303."
</li><li>"Tanya Huang, Tape No. 4307."
</li><li>"Philip Giroir, Tape No. 4311."
</li><li>"Terrence Osborne, Tape No. 4305."
</li><li>"Wynoka Boudreaux, Tape No. 4308."
</li><li>"Memory Map 1: Studio Inferno, Jazz Fest vendor, 1991–2013." (also the Memory Maps lead caption)
</li><li>"Memory Map 2: Old friends getting older." (a phrase written on the drawing)
</li><li>"Memory Map 3: Mary Ann Caffery, a day in her 2013 Jazz Fest."
</li></ul>
<h4>Clip credit lines</h4>
<ul><li>"&lt;Name&gt;, Tape No. ####" for all seven clips (same names and numbers as the captions).
</li></ul>
<h4>In the Field</h4>
<ul><li>New line under "Questions for Dwayne Breashears": "Prepared by Wilcox for an interview with Dwayne Breashears of WWOZ, New Orleans’ community radio station."
</li></ul>
<h4>Alt text (all new)</h4>
<ul><li>vauc.jpg: Geraldine Vaucresson, in a striped top, stands arm in arm with a young woman in the doorway of a home.
</li><li>gallagher.jpg: Patrick Gallagher, long-haired and bearded, points to an aerial photograph of the festival grounds on a dining table as a young woman looks on; a 1979 Jazz Fest poster hangs behind them.
</li><li>davis.jpg: Joseph “Cool” Davis, seated in a wheelchair in a shirt printed “Jo Cool,” beside a young woman holding an orange folder.
</li><li>huang.jpg: Tanya Huang laughs with another young woman on a green couch, a black and white dog sitting between them.
</li><li>giroir.jpg: An older snapshot in a photo album: a bearded man in a dark hat and sunglasses with a smiling young child.
</li><li>osborne.jpg: Terrence Osborne in his studio points to a large painting in progress, sketched in colored lines on a shaped wooden panel; a finished painting of a blue shotgun house hangs above it.
</li><li>boudreaux.jpg: Wynoka Boudreaux, smiling, stands with an older man in a white bucket hat who has his arm around her shoulder, in front of a house and a parked car.
</li><li>memory1.jpg: Hand-drawn marker map of the festival grounds titled “Studio Inferno, 1991–2013, Jazz Fest Vendor,” tracing a route from home and Bayou Sauvage to the vendor’s booth locations, with food stops, a red pepper, and a small figure holding a pitchfork.
</li><li>memory2.jpg: Hand-drawn map of the festival grounds crowded with stick figures, stages, VIP areas, food booths, bikes, and notes such as “Jazz,” “Lagniappe Stage,” “Food Exhibits,” and “Old friends getting older.”
</li><li>memory3.jpg: Colored-pencil map titled “Mary Ann Caffery, a Day in My 2013 Jazzfest, My Path,” a looping arrowed route past music tents, food stands, and crafts booths, with notes like “Tasty!” and “Yum Yum.”
</li></ul>
HTML),
]);

$section('The Relentless Pursuit of “Equal”: Integrating LSU', [
  $text(NULL, <<<'HTML'
<p>Now at <a href="/v2/oralhistory/exhibitions/rpoe">/v2/oralhistory/exhibitions/rpoe</a>.</p>
<p>Old presentation: <a href="https://lib.lsu.edu/sites/all/files/oralhistory/presentations/RPOE/index.html">https://lib.lsu.edu/sites/all/files/oralhistory/presentations/RPOE/index.html</a> (the 2014 listening station: TimelineJS timelines in <code>main/*.jsonp</code> and an About page; the copy is in <code>_reference/Oralhistory Exhibitions/presentations/RPOE/</code>).</p>
<p>How the old station maps to the new page:</p>
<ul><li>The overview timeline (<code>main</code>) became <strong>The Nation</strong> (its national events) and the opening <strong>Timeline</strong> gallery (every dated event in one list).
</li><li>The LSU, 1953 bus boycott and East Baton Rouge timelines became the walls <strong>Integrating LSU</strong>, <strong>The 1953 Bus Boycott</strong> and <strong>East Baton Rouge</strong>, each split into periods.
</li><li>The About page became <strong>About the Exhibition</strong>.
</li><li>The separate 1953 timeline (<code>1953_jsonp</code>) was not used. It retells the bus boycott timeline with different headlines, and the bus timeline has the clips.
</li></ul>
<h3>Corrected</h3>
<h4>Typos and spelling (in the event text, unless noted)</h4>
<ul>
<li><strong>Integrating LSU / LSU Stands on Segregation (headline)</strong>: LSU Stands on Segretation → LSU Stands on Segregation</li>
<li><strong>Integrating LSU / First Black Graduate</strong>: a Master’s Degree in Education → a master’s degree in education</li>
<li><strong>Integrating LSU / Tureaud Jr. Sues LSU</strong>: A. P. Tureaud, Junior successfully sues → A. P. Tureaud, Jr., successfully sues</li>
<li><strong>Integrating LSU / Tureaud Jr. Sues LSU (transcript)</strong>: We were playing racquet ball → We were playing racquetball</li>
<li><strong>Integrating LSU / Wilson Sues LSU Law</strong>: Here, Tureaud, Jr. recalls his father’s relationships → Here, A. P. Tureaud, Jr., recalls his father’s relationships</li>
<li><strong>Integrating LSU / Undergraduates Sue to Integrate LSU (transcript)</strong>: about two weeks we after we had been in school → about two weeks after we had been in school</li>
<li><strong>Integrating LSU / Undergraduates Sue to Integrate LSU (transcript)</strong>: who’s only choice might be LSU → whose only choice might be LSU</li>
<li><strong>Integrating LSU / Kerry Pourciau Elected SGA President (transcript)</strong>: well over twenty-years ago → well over twenty years ago</li>
<li><strong>Integrating LSU / Kerry Pourciau Elected SGA President</strong>: In 1972, Kerry Louis Pourciau was the first… → Kerry Louis Pourciau was the first… <em>(the year is now the entry’s date)</em></li>
<li><strong>The 1953 Bus Boycott / Compliance Ordered</strong>: A bus driver ’man handled’ a black woman → A bus driver manhandled a black woman</li>
<li><strong>The 1953 Bus Boycott / Boycott Announced</strong>: the United Defense Leage (UDL) → the United Defense League (UDL)</li>
<li><strong>The 1953 Bus Boycott / Strike Ends, Boycott Begins (transcript label)</strong>: Alemenia Freeman → Almenia Freeman</li>
<li><strong>The 1953 Bus Boycott / Struggle Continues (credit)</strong>: Almenia Freeman, activitst → Almenia Freeman, activist</li>
<li><strong>The 1953 Bus Boycott / Mass Meetings</strong>: June 20th: … June 21st: … June 22nd: → June 20: … June 21: … June 22:</li>
<li><strong>The 1953 Bus Boycott / King Learns from Baton Rouge (transcript)</strong>: always, everytime, anytime in violation → always, every time, anytime in violation</li>
<li><strong>The 1953 Bus Boycott / King Learns from Baton Rouge (transcript)</strong>: saw that difference That the church house → saw that difference. That the church house</li>
<li><strong>East Baton Rouge / Integrating Recreation (transcript)</strong>: rather than have us integrate that pool, they closed it → …they closed it. <em>(period added)</em></li>
<li><strong>East Baton Rouge / White-Only Golf Courses</strong>: in a predominately black neighborhood → in a predominantly black neighborhood</li>
<li><strong>East Baton Rouge / Kress Sit-In</strong>: nine more students sat-in at various locations → nine more students sat in at various locations</li>
<li><strong>East Baton Rouge / CORE Sit-Ins</strong>: In Decemeber, members → In December, members</li>
<li><strong>East Baton Rouge / Cox v. Louisiana</strong>: National CORE represntative → National CORE representative</li>
<li><strong>East Baton Rouge / Backlashes Against Activists (transcript)</strong>: There was a hole it the picture glass window → There was a hole in the picture glass window</li>
<li><strong>East Baton Rouge / Backlashes Against Activists (transcript)</strong>: Every time he was involved in , like when → Every time he was involved in, like when</li>
<li><strong>East Baton Rouge / Police Shoot Youth</strong>: Their deaths lead to marches → Their deaths led to marches</li>
<li><strong>The Nation / Vietnam War</strong>: the first fully-integrated deployment → the first fully integrated deployment</li>
<li><strong>The Nation / World War II, Korean War, Vietnam War</strong>: (1939 – 1945), (1950 –1953), (1956 – 1975) → (1939–1945), (1950–1953), (1956–1975)</li>
</ul>
<h4>Headlines (entry titles)</h4>
<ul>
<li><strong>SU Law School Opens</strong>: Southern University Law School Opens</li>
<li><strong>LSU Law Admits Collins, Morial</strong>: LSU Law Admits Collins and Morial</li>
<li><strong>Payne Sues LSU Grad School</strong>: Payne Sues LSU Graduate School</li>
<li><strong>Foister Sues LSU Med School</strong>: Foister Sues LSU Medical School</li>
<li><strong>Alpha Kappa Alpha established at LSU / Harambe established at LSU / Kerry Pourciau elected SGA President</strong>: …Established at LSU / …Elected SGA President <em>(title case like the others)</em></li>
<li><strong>LSU Names Building for A.P. Tureaud, Sr.</strong>: LSU Names a Building for A. P. Tureaud, Sr.</li>
<li><strong>MLK Learns from Baton Rouge</strong>: King Learns from Baton Rouge</li>
<li><strong>Discrimination in WWII</strong>: Discrimination in World War II</li>
<li><strong>MLK Assassination</strong>: King Assassinated</li>
<li><strong>Garner v. Louisiana, Cox v. Louisiana, Plessy v. Ferguson</strong>: The case names in italics</li>
</ul>
<h4>Dates</h4>
<p>Every event now has its own date, shown under its heading and used for the Timeline gallery’s order.</p>
<ul>
<li><strong>East Baton Rouge / SU Students Killed</strong>: November 17, 1972 → November 16, 1972 <em>(the day of the shooting; the newspaper front page shown is from the 17th)</em></li>
<li><strong>The Nation / Plessy v. Ferguson</strong>: April 13 – May 18, 1896 → May 18, 1896 <em>(the decision)</em></li>
<li><strong>East Baton Rouge / Kress Sit-In</strong>: March 28–29, 1960 → March 28, 1960</li>
<li><strong>East Baton Rouge / Capitol March</strong>: March 29–30, 1960 → March 29, 1960</li>
<li><strong>East Baton Rouge / 25 CORE Arrests</strong>: December 13–14, 1961 → December 13, 1961</li>
<li><strong>East Baton Rouge / Garner v. Louisiana</strong>: October 18 – December 11, 1961 → October–December 1961</li>
<li><strong>East Baton Rouge / SU Students Expelled</strong>: January 16–18, 1962 → January 1962</li>
<li><strong>East Baton Rouge / Backlashes Against Activists</strong>: August 1, 1963 → August 1963</li>
<li><strong>Integrating LSU / Undergraduates Sue to Integrate LSU</strong>: 1964–1968 → 1964</li>
<li><strong>The Nation / Vietnam War</strong>: December 1956 – April 30, 1975 → 1956–1975</li>
<li><strong>Integrating LSU / Harambe Established at LSU</strong>: 1964–1968 → no date <em>(see To verify)</em></li>
<li><strong>The 1953 Bus Boycott / King Learns from Baton Rouge</strong>: July 1953 → no date <em>(it describes a comparison, not an event)</em></li>
</ul>
<h4>Events merged or reordered</h4>
<ul><li><strong>Kept once:</strong> these events were on both the overview timeline and a local one. Each now appears once, on the local wall. The texts were the same unless noted.
<ul><li>Wilson Sues LSU Law
</li><li>Capitol March (the East Baton Rouge text is kept; it is longer and names Felton Clark)
</li><li>1953 Bus Boycott (the same as Strike Ends, Boycott Begins)
</li><li>Department Store Integration
</li><li>MLK Assassination
</li></ul>
</li><li><strong>Civil Rights Act Becomes Law</strong> (East Baton Rouge) and <strong>Civil Rights Act</strong> (overview) had the same text. It is kept once, in The Nation.
</li><li><strong>Date order:</strong> each wall now runs in date order. The old timelines listed these events out of order:
<ul><li>Law Tested (June 13) now comes before Drivers Suspended (June 14) and Drivers Strike (June 15).
</li><li>Ordinance 251 (June 24) now comes before Boycott Ends (June 26).
</li><li>Undergraduates Sue to Integrate LSU now comes before First African American Dorm Resident.
</li><li>Kerry Pourciau (1972) now comes before Alpha Kappa Alpha (1972–1973).
</li></ul>
</li></ul>
<h4>Speaker labels and transcripts</h4>
<ul><li>In a transcript with more than one speaker, each label is in bold. A speaker gets their full name the first time, then only their surname:
<ul><li>"A.P. Tureaud, Jr.:" → "<strong>A. P. TUREAUD, JR.:</strong>", then "<strong>TUREAUD:</strong>"
</li><li>Freya Anderson Rivers’ clip: "RIVERS:" / "CRUMP:" → "<strong>FREYA ANDERSON RIVERS:</strong>" / "<strong>MAXINE CRUMP:</strong>" the first time
</li></ul>
</li><li>In a transcript with only one speaker, the label is dropped, because the credit names them (for example "Dupuy Anderson: The night before the boycott…").
</li><li>A picture placed inside Maxine Crump’s transcript was taken out. It is the entry’s figure instead.
</li><li><strong>Initials:</strong> "A.P.", "T.J.", "W.K." → "A. P.", "T. J.", "W. K."
</li><li><strong>Quotation marks:</strong> doubled single quotes ('' '') and straight quotes became curly double quotes, and apostrophes became ’. A quotation left open at the end of a paragraph is closed there.
</li><li><strong>Dashes:</strong> a spaced hyphen (" - " or " -- ") became an em dash.
</li><li><strong>Ellipses:</strong> ". . .It" → ". . . It". In Tureaud’s transcript, "?'. . ." → "?” . . .".
</li><li>"[sic]" after "A. P. Tureaud, Jr." in Freya Anderson Rivers’ transcript is kept as it was.
</li></ul>
<h4>Credits</h4>
<ul>
<li><strong>A. P. Tureaud Jr., LSU alumni</strong>: A. P. Tureaud, Jr., LSU alumnus</li>
<li><strong>Ernest 'Dutch' Morial, LSU alumni</strong>: Ernest “Dutch” Morial, LSU alumnus, interviewed by Nina Pugh</li>
<li><strong>Joanne Lamotte, LSU Alumna / Demetris 'Dee' Howard, LSU Alumna</strong>: …LSU alumna</li>
<li><strong>Reverend T.J. Jemison / Reverend Lionel Lee</strong>: Rev. T. J. Jemison / Rev. Lionel Lee</li>
<li><strong>Two names in one credit (e.g. "Reverend T.J. Jemison and Olivia Huey")</strong>: One credit per clip, each naming its own speaker</li>
<li><strong>Pearl Payne / Freya Anderson Rivers / Johnnie Jones (King clip) / A. P. Tureaud, Jr. (two clips)</strong>: The interviewer added where the transcript names one: "interviewed by Jennifer Abraham", "by Maxine Crump", "by Beth Smith", "by Rachel Emanuel-Wallace"</li>
<li><strong>"Esso photo courtesy of Ernest Richie" (in the clip credit)</strong>: Moved to the Esso photo’s caption</li>
</ul>
<h3>To verify</h3>
<ul><li><strong>Harambe Established at LSU:</strong> the old timeline dated it 1964–1968, the same span it gave the dorm and the undergraduate suit, so it looked copied. It now has no date and stays off the Timeline. What year was Harambe founded?
</li><li><strong>Struggle Continues:</strong> dated July 1953 on the bus timeline, but October 1953 on the separate 1953 timeline. July is kept.
</li><li><strong>SU Students Killed:</strong> moved from November 17 to November 16, 1972, the day Denver Smith and Leonard Brown were shot. Please confirm.
</li><li><strong>Undergraduates Sue to Integrate LSU:</strong> 1964 is kept, the year of the suit. The old range, 1964–1968, may have been meant for something else.
</li><li><strong>First African American Dorm Resident:</strong> the old 1964–1968 range is kept. Should it be a single year?
</li><li><strong>Kerry Pourciau’s text:</strong> it says Lora Hinton was the first African American football player recruited for the LSU Tigers. This is left as written.
</li><li><strong>Charles J. Hatfield, III:</strong> Hatfield Sues LSU Law has no clip or picture. It is kept as written.
</li><li><strong>Newspaper names and dates in captions:</strong> some were read off the clippings or taken from the event’s date, and need a check against the originals:
<ul><li><em>The Louisiana Weekly</em> on the 1958 LSU Board clipping
</li><li><em>The Daily Reveille</em>, 1990, on the building article
</li><li>"Newspaper photo by Bob Wall, March 30, 1960" on the march photo
</li><li>The dates on the strike headline (June 15, 1953), the pool clipping (August 2, 1946), the tear gas front page (December 15, 1961) and the City Park pool clipping (July 24, 1963)
</li></ul>
</li><li><strong>Tureaud photo, 1953:</strong> the caption calls it a newspaper photo of A. P. Tureaud, Jr., and his father waiting in the LSU registrar’s office.
</li><li><strong>Burns newsletter:</strong> the School of Library and Information Science newsletter is dated fall 2002 from its masthead.
</li></ul>
<h3>New wording to check</h3>
<h4>The page</h4>
<ul><li><strong>Subtitle:</strong> "Oral histories of civil rights at LSU and in Baton Rouge"
</li><li><strong>Credit:</strong> "T. Harry Williams Center for Oral History and LSU Libraries Special Collections. Curated by Leah Wood Jewett, Barry Cowan and Jennifer A. Cramer."
</li><li><strong>Intro</strong>, two paragraphs. The first quotes the old About page. The second is new: "Oral history clips from those who integrated LSU sit beside timelines of the 1953 Baton Rouge bus boycott and of civil rights activities in East Baton Rouge from the 1920s through the 1970s, in the voices of those who lived them."
</li><li><strong>Exhibition note:</strong> "Shown in the Hill Memorial Library Lecture Hall from January 21 to March 29, 2014, for the 50th anniversary of the Civil Rights Act of 1964."
</li><li><strong>Timeline gallery intro:</strong> "The exhibition’s four galleries side by side, from <em>Plessy v. Ferguson</em> in 1896 to the building LSU named for A. P. Tureaud, Sr., in 1990: the suits and students who integrated LSU, the 1953 bus boycott, civil rights in East Baton Rouge, and the nation."
</li><li><strong>Integrating LSU intro:</strong> "LSU was integrated one school at a time, through separate suits for the law school, the graduate school and the undergraduate college. Many of the cases were argued by A. P. Tureaud, Sr."
</li><li><strong>Period headings on the walls:</strong>
<ul><li>Integrating LSU: The lawsuits, 1946–1953; Segregation reaffirmed, 1958; Undergraduates on campus, 1964–1972; Recognition, 1990
</li><li>The 1953 Bus Boycott: February: Ordinance 222; June: The strike and the boycott; The compromise and after
</li><li>East Baton Rouge: Before 1960; Sit-ins and marches, 1960–1962; Pools, stores and backlash, 1963–1964; Grief and violence, 1968–1972
</li></ul>
</li><li><strong>About the Exhibition:</strong> reworded from the old About page, with the headings "Old Ways No More" and "Acknowledgments". It also has a new paragraph inviting donations of materials from the era.
</li></ul>
<h4>Event text written new (the old timeline had none, or had only a transcript)</h4>
<ul><li><strong>First African American Dorm Resident:</strong> "Maxine Crump becomes the first African American to live in an LSU dormitory." <em>(The old text was the clip’s transcript, which is now under the clip.)</em>
</li><li><strong>Harambe Established at LSU:</strong> "Black students at LSU establish Harambe, a support group with a room of its own on campus."
</li><li><strong>Alpha Kappa Alpha Established at LSU:</strong> "With help from the Gamma Eta Omega chapter of Alpha Kappa Alpha Sorority, Black women at LSU establish a chapter of their own."
</li><li><strong>LSU Names a Building for A. P. Tureaud, Sr.:</strong> "The former Classroom Building, at South Stadium Drive and Forestry Lane, is named for civil rights lawyer A. P. Tureaud, Sr.: the first building on LSU’s campus named for a Black person." <em>(Taken from the clipping; please check the building’s old name, its location and the "first building" claim.)</em>
</li></ul>
<h4>Clip titles (all new; the old station had none)</h4>
<ul><li><strong>Integrating LSU:</strong>
<ul><li>Leander Perez at the deposition
</li><li>More than just their lawyer
</li><li>The first Black law students
</li><li>The first credit from the graduate school
</li><li>It was like I didn’t exist
</li><li>We just kept saying equal
</li><li>Who was comfortable being where with me
</li><li>A bonding group
</li><li>A new era
</li><li>Founding the chapter
</li></ul>
</li><li><strong>The 1953 Bus Boycott:</strong>
<ul><li>Standing over empty seats
</li><li>It was humiliating
</li><li>First come, first served
</li><li>The lady on the bus
</li><li>The night before the boycott
</li><li>Driving the bus route
</li><li>They rode free in the day
</li><li>A show of unity
</li><li>Separate but equal was wrong
</li><li>My daughter sat in the front
</li><li>The church house and the court house
</li></ul>
</li><li><strong>East Baton Rouge:</strong>
<ul><li>The hub of education
</li><li>Ryan Army Air Field
</li><li>Home from the war
</li><li>Tax time
</li><li>A black golf course
</li><li>At the lunch counters
</li><li>We were willing
</li><li>The swimming pools were not integrated
</li><li>They shot in our house
</li><li>The summer of 1969
</li><li>The deputies lined up
</li></ul>
</li></ul>
<h4>Credits written new (no credit in the old station)</h4>
<ul><li>Maxine Crump, interviewed by Pamela Dean
</li><li>Kerry Pourciau, interviewed by Maxine Crump
</li></ul>
<h4>Captions (all new except the Plessy marker’s, which is shortened from the old photo credit)</h4>
<ul><li><strong>Plessy marker:</strong> "The <em>Plessy v. Ferguson</em> marker in New Orleans, written by historian Keith Weldon Medley for the Crescent City Peace Alliance and placed at Press Street and the railroad yards on February 12, 2009." <em>(Also the lead caption for The Nation.)</em>
</li><li><strong>Tureaud:</strong> "A. P. Tureaud, Jr., and his father, his attorney in the suit, waiting in the LSU registrar’s office. Newspaper photo, 1953." <em>(Also the lead caption for Integrating LSU.)</em>
</li><li><strong>Esso station:</strong> "An Esso station in Baton Rouge, 1953. Photo courtesy of Ernest Richie." <em>(Also the lead caption for The 1953 Bus Boycott.)</em>
</li><li><strong>March, second photo:</strong> "Marchers on their way to the State Capitol, April 5, 1968." <em>(Also the lead caption for East Baton Rouge.)</em>
</li><li><strong>March, first photo:</strong> "The march to the State Capitol, April 5, 1968."
</li><li><strong>Portraits:</strong>
<ul><li>Reverend T. J. Jemison.
</li><li>Willis Reed.
</li><li>Martin Luther King, Jr.
</li><li>Maxine Crump.
</li><li>Kerry Pourciau.
</li><li>Ernest “Dutch” Morial, later mayor of New Orleans.
</li></ul>
</li><li><strong>Groups and places:</strong>
<ul><li>Members of Alpha Kappa Alpha at LSU.
</li><li>Members of Harambe at LSU.
</li><li>McKinley High School.
</li></ul>
</li><li><strong>Telegram:</strong> "Telegram from L. A. Payne to Pearl Payne, June 14, 1951: “Registration completed all is well.”"
</li><li><strong>Foister:</strong> "Newspaper report of Daryle E. Foister’s admission."
</li><li><strong>Burns:</strong> "Ollie H. Burns, profiled in the School of Library and Information Science newsletter, fall 2002."
</li><li><strong>LSU Board:</strong> "<em>The Louisiana Weekly</em> reports the LSU Board of Supervisors’ vote, 1958."
</li><li><strong>Building:</strong> "<em>The Daily Reveille</em> reports the building’s dedication, 1990."
</li><li><strong>Newspaper front pages and clippings:</strong> each is captioned with its paper and date (see To verify):
<ul><li>March 30, 1960
</li><li>Bob Wall’s photo, March 30, 1960
</li><li><em>Morning Advocate</em>, April 5, 1968
</li><li>June 15, 1953
</li><li>August 2, 1946
</li><li>December 15, 1961
</li><li>July 24, 1963
</li><li><em>Morning Advocate</em>, January 11, 1972
</li><li><em>Morning Advocate</em>, November 17, 1972
</li></ul>
</li></ul>
<h4>Alt text (all new; 28 images)</h4>
<p>Each describes what the picture shows and, for a clipping, gives its headline word for word (for example "A Morning Advocate front page headlined “King Shot to Death in Memphis,” with a portrait of Martin Luther King, Jr."). The full list is under <code>alt</code> in <code>scripts/data/integrating-lsu.json</code>. Please check the headlines read off the clippings:</p>
<ul><li>Nurse Admitted to LSU School of Nursing Here
</li><li>LSU Board Says ‘No’ to Negro Applicants; Vote to Deny Admission to 12
</li><li>Building renamed after black lawyer, by Molly Thibodeaux
</li><li>City Planners to Determine Site for Negro Pool
</li><li>Tear Gas Is Used As Negro Groups March Downtown
</li><li>Negro Group Tries to Mix Pool in Park, by Jim Crain
</li><li>Violence Leaves 4 Dead Here
</li><li>Clash Leaves 2 Dead; SU Campus Shut Down
</li><li>Negroes March Downtown; Grand Jury Begins Inquiry
</li><li>Bus Drivers Strike Over Race Issue
</li></ul>
HTML),
]);

$section('Saturday Night in Tiger Stadium', [
  $text(NULL, <<<'HTML'
<p>Now at <a href="/v2/oralhistory/exhibitions/football">/v2/oralhistory/exhibitions/football</a>.</p>
<p>Old exhibition: <a href="https://lib.lsu.edu/sites/all/files/oralhistory/presentations/football/index.html">https://lib.lsu.edu/sites/all/files/oralhistory/presentations/football/index.html</a> (local copy in <code>_reference/Oralhistory Exhibitions/presentations/football/</code>, identical to the live pages). New page: built by <code>scripts/palefire_exhibit_tiger_stadium.php</code> from <code>scripts/data/tiger-stadium.json</code>.</p>
<p>Locations are <em>gallery / entry title (old page)</em>. The Narrators are the old Biographies and Citations pages.</p>
<h3>Corrected</h3>
<h4>Applied throughout</h4>
<ul><li>Straight quotes and apostrophes → curly ones (“ ” ‘ ’) in all text.
</li><li>Speaker labels in transcripts: title case → capitals, as on the other oral history exhibitions, e.g. “John Ferguson:” → “JOHN FERGUSON:”, “Ferguson:” → “FERGUSON:”, “C. Barney:” → “C. BARNEY:”. The old pages used 74 different label forms; each keeps its wording.
</li><li>Image citations: “Name: citation” → “Name. Citation.”, with “Stave Franz” → “Steve Franz” (the Carl Maddox photo, two entries), “LD3118.G8” and “LD 3118 .G8” → “LD3118 .G8”, “LLMVC” spelled out, and a missing final period added.
</li><li>Biography citations: the “∙” between John Ferguson’s two citations, and between Tyler LaFauci’s, → “; ”.
</li><li>Hyphens used as dashes (“ - ”, “ -- ”) → em dashes; number ranges (“41 - 0”, “1893 - 1993”) → en dashes.
</li></ul>
<h4>Speaker labels</h4>
<ul><li>Facilities / Tiger Stadium’s early design (faalbertstdizier.html): “Hebert” → “HEBERT:” (colon was missing).
</li><li>Facilities / Tiger Stadium’s early design (faalbertstdizier.html): “St. Dizier” → “ST. DIZIER:” (colon was missing).
</li><li>Big Games / 1944 Orange Bowl (biggamecharlesbarney.html): “Abraham:” started mid-paragraph; it now starts its own paragraph.
</li><li>Players &amp; Teams / Ready for an integrated team (ptkerrypourciau.html): “Maxine Crump:” started mid-paragraph; it now starts its own paragraph.
</li><li>Players &amp; Teams / Ready for an integrated team (ptkerrypourciau.html): “Pourciau:” started mid-paragraph; it now starts its own paragraph.
</li><li>Players &amp; Teams / Finishing as a player despite injury (ptlorahinton1.html): “Hinton:” appeared twice in a row (“As a player, yeah.” then “So that’s kind of the way…”); the second label removed.
</li></ul>
<h4>Wording and spelling</h4>
<ul><li><strong>Big Games / 1944 Orange Bowl (biggamecharlesbarney.html)</strong>
<ul><li>“…And we beat ‘em good. [laug…” → “…And we beat ’em good. [laug…”
</li></ul>
</li><li><strong>Big Games / LSU v. Tulane, 1949 (biggamegaynelltinsley.html)</strong>
<ul><li>“As it say, it’s just” → “As I say, it’s just”
</li></ul>
</li><li><strong>Big Games / LSU v. Miami, 1958 (biggamebudjohnson.html)</strong>
<ul><li>“41 - 0.” → “41–0.”
</li></ul>
</li><li><strong>Big Games / The 1958 Championship (biggamecarlmaddox.html)</strong>
<ul><li>“bigger now then it was” → “bigger now than it was”
</li></ul>
</li><li><strong>Big Games / LSU v. USC, 1979 (biggamejohnferguson.html)</strong>
<ul><li>“times -- in” → “times—in”
</li><li>“ways -- for” → “ways—for”
</li></ul>
</li><li><strong>Big Games / The 1988 Earthquake Game (biggametomhodson.html)</strong>
<ul><li>“Geology department” → “Geology Department”
</li></ul>
</li><li><strong>Players &amp; Teams / Scholarships and jobs for players (ptgaynelltinsley.html)</strong>
<ul><li>“job would be back at home” → “job would be back at home.”
</li></ul>
</li><li><strong>Players &amp; Teams / Pursuing an education (ptabemickal.html)</strong>
<ul><li>“high top leather shoes” → “high-top leather shoes”
</li></ul>
</li><li><strong>Players &amp; Teams / Free football tickets for students (ptalfredglassell.html)</strong>
<ul><li>“…might hurt ‘em. And so, he…” → “…might hurt ’em. And so, he…”
</li></ul>
</li><li><strong>Players &amp; Teams / Playing Georgia twice (ptthomasruffin.html)</strong>
<ul><li>“I forgot was it was” → “I forgot what it was”
</li><li>“cancelled” → “canceled”
</li><li>“sort of duel thing” → “sort of dual thing”
</li></ul>
</li><li><strong>Players &amp; Teams / Why he came to LSU (ptmerleschexnaildre1.html)</strong>
<ul><li>“four year letterman” → “four-year letterman”
</li></ul>
</li><li><strong>Players &amp; Teams / The origin of the Chinese Bandits (ptmerleschexnaildre2.html)</strong>
<ul><li>“were description of” → “were descriptions of”
</li><li>“played like Chinese Bandits”.” → “played like Chinese Bandits.””
</li><li>“1893 - 1993*],” → “1893–1993*],”
</li></ul>
</li><li><strong>Players &amp; Teams / Players who were fun to watch (ptjohnferguson.html)</strong>
<ul><li>“years - though” → “years—though”
</li><li>“[1993] - made” → “[1993]—made”
</li></ul>
</li><li><strong>Players &amp; Teams / His junior season and the offensive line (ptcharlesalexander.html)</strong>
<ul><li>“two quarterback system” → “two-quarterback system”
</li><li>“all of sudden it turns” → “all of a sudden it turns”
</li></ul>
</li><li><strong>Players &amp; Teams / Ready for an integrated team (ptkerrypourciau.html)</strong>
<ul><li>“twenty-years ago” → “twenty years ago”
</li></ul>
</li><li><strong>Coaching &amp; Practice / Huey Long and Coach Biff Jones butt heads (cpjackgremillion.html)</strong>
<ul><li>“paid ...?” → “paid . . . ?”
</li><li>“Kansas. . . He” → “Kansas . . . He”
</li><li>“…ed up there ‘til his death.…” → “…ed up there ’til his death.…”
</li></ul>
</li><li><strong>Coaching &amp; Practice / Choosing LSU over other offers (cpabemickal.html)</strong>
<ul><li>“kind of lead me” → “kind of led me”
</li><li>“a close knit” → “a close-knit”
</li></ul>
</li><li><strong>Coaching &amp; Practice / Coach Dietzel’s discipline (cpcarlmaddox1.html)</strong>
<ul><li>“assigned. . . to” → “assigned . . . to”
</li></ul>
</li><li><strong>Coaching &amp; Practice / Position coach Jerry Stovall (cpcharlesalexander.html)</strong>
<ul><li>“I’LL write” → “I’ll write”
</li></ul>
</li><li><strong>Facilities / Where Mike came from, and a prank by Tulane (faoscarrichard.html)</strong>
<ul><li>“a dime a piece?” → “a dime apiece?”
</li><li>“About five-thousand” → “About five thousand”
</li><li>“Five-thousand students” → “Five thousand students”
</li><li>“…probably in ‘34. I think [C…” → “…probably in ’34. I think [C…”
</li><li>“Tulane Students” → “Tulane students”
</li><li>“tiger when in there” → “tiger went in there”
</li></ul>
</li><li><strong>Facilities / How weight training changed LSU football (fabudjohnson.html)</strong>
<ul><li>“Bob Petit” → “Bob Pettit”
</li><li>“195 pound back to the basket center” → “195-pound back-to-the-basket center”
</li><li>“here -- he’s” → “here—he’s”
</li><li>“business -- I” → “business—I”
</li></ul>
</li><li><strong>Culture / Celebrating a win in Baton Rouge (cujessecoates02.html)</strong>
<ul><li>“…out 1925 or ‘26 or somethin…” → “…out 1925 or ’26 or somethin…”
</li></ul>
</li><li><strong>Culture / Traveling to games by rail and car (cubillyheroman.html)</strong>
<ul><li>“when they went the [1944] Orange Bowl [played in Miami, FL, against Texas A&amp;M.” → “when they went to the [1944] Orange Bowl [played in Miami, Florida, against Texas A&amp;M].”
</li></ul>
</li><li><strong>Culture / Huey Long tries to make Mickal a state senator (cuabemickal.html)</strong>
<ul><li>“benefitting” → “benefiting”
</li><li>“over an hour; telling” → “over an hour, telling”
</li><li>“never on out schedule” → “never on our schedule”
</li><li>“hit the campus; he’d” → “hit the campus, he’d”
</li></ul>
</li><li><strong>Culture / ROTC in pajamas at Homecoming (cujackgremillion.html)</strong>
<ul><li>“days - it's” → “days—it’s”
</li><li>“now - used” → “now—used”
</li></ul>
</li><li><strong>Culture / The notoriety that came with winning (cuguskinchen01.html)</strong>
<ul><li>“front of Life Magazine” → “front of <em>Life</em> magazine”
</li></ul>
</li><li><strong>Culture / The genesis of “Hey, Fightin’ Tigers!” (cubudjohnson.html)</strong>
<ul><li>“called Wildcat.” → “called <em>Wildcat</em>.”
</li></ul>
</li><li><strong>Culture / Broadcasts that marketed LSU (cujohnferguson02.html)</strong>
<ul><li>“1960s and 70s” → “1960s and ’70s”
</li></ul>
</li><li><strong>Culture / Fans who motivate the team (cucharlesalexander.html)</strong>
<ul><li>“days -- which” → “days—which”
</li><li>“back -- the” → “back—the”
</li></ul>
</li><li><strong>Culture / A new dean looks for tickets (cumargaretjameson.html)</strong>
<ul><li>“biggest to do” → “biggest to-do”
</li></ul>
</li><li><strong>Culture / Game nights and meeting Billy Cannon (curogerogden.html)</strong>
<ul><li>“Louisiana -- you” → “Louisiana—you”
</li><li>“then -- was” → “then—was”
</li><li>“…ed going in ‘55 or ‘56. So…” → “…ed going in ’55 or ’56. So…”
</li><li>“…g in ‘55 or ‘56. So I was l…” → “…g in ’55 or ’56. So I was l…”
</li><li>“people -- except” → “people—except”
</li><li>“fans -- act” → “fans—act”
</li></ul>
</li><li><strong>The Narrators / Charles Alexander (biocharlesalexander.html)</strong>
<ul><li>“twenty seven” → “twenty-seven”
</li><li>“first round draft” → “first-round draft”
</li></ul>
</li><li><strong>The Narrators / Charles Barney (biocharlesbarney.html)</strong>
<ul><li>“Pacific Theatre” → “Pacific Theater”
</li></ul>
</li><li><strong>The Narrators / Alfred Glassell (bioalfredglassell.html)</strong>
<ul><li>“theatres” → “theaters”
</li><li>“was a also a” → “was also a”
</li><li>“First City Nation Bank” → “First City National Bank”
</li></ul>
</li><li><strong>The Narrators / Tom Hodson (biotomhodson.html)</strong>
<ul><li>“Tiger’s success” → “Tigers’ success”
</li></ul>
</li><li><strong>The Narrators / Bud Johnson (biobudjohnson.html)</strong>
<ul><li>“Prior that that” → “Prior to that”
</li><li>“Athletics ,” → “Athletics,”
</li></ul>
</li><li><strong>The Narrators / Tyler LaFauci (biotylerlafauci.html)</strong>
<ul><li>“Lafauci” → “LaFauci”
</li><li>“three year career” → “three-year career”
</li></ul>
</li><li><strong>The Narrators / Oliver Mack (bioolivermack.html)</strong>
<ul><li>“parents moved Baton Rouge” → “parents moved to Baton Rouge”
</li></ul>
</li><li><strong>The Narrators / Abe Mickal (bioabemickal.html)</strong>
<ul><li>“was all SEC” → “was All-SEC”
</li></ul>
</li><li><strong>The Narrators / Roger &amp; Carrie Richardson (biorcrichardson.html)</strong>
<ul><li>“interview by John Fiser” → “interview by Jack Fiser”
</li></ul>
</li></ul>
<h4>Credit lines and names</h4>
<ul><li>Players &amp; Teams / Finishing as a player despite injury and Teammates standing up to racism (ptlorahinton1.html, ptlorahinton2.html): “interviewed by Mary Price, 1993” → “interviewed by Mary Hebert, 1993”. The transcripts’ labels (HEBERT) and Hinton’s biography citation both say Mary Hebert.
</li><li>The Narrators / Roger &amp; Carrie Richardson: citation “interview by John Fiser” → “interview by Jack Fiser”, as the clip credit and John Hunter’s citation have it.
</li><li>Big Games / 1944 Orange Bowl: “Charles Barney, with wife, Frances, and daughter, Merilyn interviewed by…” → “Charles Barney, with his wife, Frances, and daughter, Merilyn, interviewed by…”.
</li><li>Coaching &amp; Practice / The character of Coach Moore and Facilities / Eating well during wartime rationing: “Charles Barney and wife Frances” / “Charles Barney, and wife Frances” → “Charles Barney and his wife, Frances”.
</li><li>Facilities / Hamilton Johnston and the design of Tiger Stadium: “Roger &amp; Carrie Richardson” → “Roger and Carrie Richardson”.
</li><li>Coaching &amp; Practice / Choosing LSU over other offers (cpabemickal.html): the old credit’s bold ran on past the name (“<strong>Abe Mickal, interviewed by…</strong>”); now plain like the others.
</li><li>Biographies index: “Shexnaildre, Merle” → Schexnaildre; Players &amp; Teams tile alt “Tyler La Fauci” → Tyler LaFauci.
</li></ul>
<h4>About the Exhibition (old About and Acknowledgements pages)</h4>
<ul><li>“Tiger Stadium from its completion in 1924 to the 1970.” → “…to the 1970s.”
</li><li>“Bert Jones, “the Ruston Rifle;” among others” → “Bert Jones, “the Ruston Rifle”; among others”.
</li><li>“whether it pertains to The Game, Facilities, and/or football Culture” → “whether it pertains to the game, the facilities, or football culture”.
</li><li>“tell the story of LSU History” → “tell the story of LSU history”.
</li><li>“Click to hear:” list → “Listen for”, each item capitalized; “winning the 1958 National championship” → “national championship”.
</li><li>“please visit /special/williams/” → a link named “T. Harry Williams Center for Oral History”.
</li><li>The Louisiana Digital Library address <a href="http://cdm16313.contentdm.oclc.org/cdm/landingpage/collection/LSU_UAP">http://cdm16313.contentdm.oclc.org/cdm/landingpage/collection/LSU_UAP</a> → a link to <a href="https://louisianadigitallibrary.org/">https://louisianadigitallibrary.org/</a> (the old CONTENTdm address is retired).
</li><li>“ACKNOWLEDGEMENTS” → “Acknowledgments”; “Katie Blanchard, Public Services Student Assistant, and Rebecca Stephens” → “…Student Assistant; and Rebecca Stephens” (the list uses semicolons).
</li></ul>
<h3>To verify</h3>
<ul><li>Players &amp; Teams / Ready for an integrated team: clip credit says “Kerry Pourciau, interviewed by Maxine Crump, 1994”; his biography citation says 1993. Left as 1994.
</li><li>Players &amp; Teams / Finishing as a player… and Teammates standing up to racism: Mary Price → Mary Hebert (see list 1). Confirm the interviewer.
</li><li>The Narrators / Jesse Coates: “head of the department from 1955 through 1967 and again from 1960-70” overlaps. Left as written.
</li><li>The Narrators / Tom Hodson: says Hodson led LSU to two SEC Championships, “first as a freshman in 1986 and then as a junior in 1988.” Left as written.
</li><li>The Narrators / Bud Johnson: “served as president of SEC Sports Publicity Director’s Association” (apostrophe placement) and “Louisiana Sports Writer’s Association’s”. Left as written.
</li><li>The Narrators / Margaret Jameson: citation “Jameson, M. Margaret”. Left as written.
</li><li>The Narrators / Alfred Glassell: “has led marine expeditions” (present tense for someone who died in 2008). Left as written.
</li><li>Culture / Fan support after a loss (cuguskinchen02.html): the image file is named mikeiiarriving (Mike II arriving), but the clip is about fans greeting the team at the airport. The image and its new caption follow the file name; confirm what the photo shows.
</li><li>Culture / ROTC in pajamas at Homecoming: photo file pajamafootball shows students cheering at night; whether they are in pajamas can’t be seen at this size.
</li><li>Facilities / Hamilton Johnston and the design of Tiger Stadium: the photo (file tigerstadium1936) is an aerial of a full stadium with letters on the field; no citation or date in the old exhibition.
</li><li>Coaching &amp; Practice / Coach Moore’s scrimmages: photo file moorecoates shows Bernie Moore (left, per the old title) with an older man the old exhibition doesn’t name (the file name suggests a Coates); no citation.
</li><li>Culture / Huey Long tries to make Mickal a state senator: photo file hueyhouston (Huey Long leading a band on a city street, perhaps Houston); no citation.
</li><li>Players &amp; Teams / Finishing as a player despite injury: the photo is an action shot at the line of scrimmage, credited “Lora Hinton”; which player is Hinton isn’t clear.
</li><li>Coaching &amp; Practice / McClendon’s small but aggressive players: “[football coach at Miami University]” is the old bracket note for Bo Schembechler (Miami University, Ohio). Left as written.
</li><li>Spoken grammar left as said throughout (e.g. “might of”, “had have been”, “Texas University”, “was million dollar band”, “a great integral in the university”).
</li><li>Exhibition year 2013: inferred from the title (“120 Years of LSU Football”, 1893 + 120) and the latest interview (Bud Johnson, 2013). The old site gives no date.
</li><li>Physical exhibition note: “First shown in LSU Libraries Special Collections with a touchscreen listening station.” The old site gives no venue or dates.
</li><li>17 clip photos had no citation in the old exhibition: Charles Barney, Bud Johnson, Alfred Glassell, Kerry Pourciau, Moore with Coates (?), Martin Broussard, the 1936 aerial, Mike’s keeper, the second Alvin Roy ad, Huey Long with the band, the night-game crowd, the 1958 champions, Mike II arriving, the placekick, game-day attire, the homecoming display, Margaret Jameson. The 14 Lagniappe photos had no captions or sources either.
</li><li>Merle Schexnaildre’s cited portrait (schexnaildre_dream, Gumbo 1959) is only 302 px; a better scan would help.
</li></ul>
<h3>New wording to check</h3>
<h4>Page</h4>
<ul><li>Title: “Saturday Night in Tiger Stadium” (from the old title graphic).
</li><li>Subtitle: “Reflections on 120 years of LSU football” (old graphic: “Reflections on 120 Years of LSU Football”).
</li><li>Credit: “A University Archives and T. Harry Williams Center for Oral History exhibition, LSU Libraries Special Collections. Curated by Barry Cowan, Jennifer A. Cramer and Erin Hess, from the Center’s University History, LSU Athletics Collection.”
</li><li>Introduction: “For more than a century, LSU football has drawn Louisiana together on fall Saturday nights. In over sixty brief clips from the T. Harry Williams Center for Oral History’s University History, LSU Athletics Collection, former players, coaches, broadcasters, administrators, students and fans remember the big games, the players and teams, coaching and practice, Tiger Stadium itself, and the culture that grew up around the game.”
</li><li>Introduction: “Photographs from the University Archives show LSU’s first All-Americans, Gaynell Tinsley and Marvin “Moose” Stewart; “Miracle” Abe Mickal; Gus Kinchen and Merle Schexnaildre of the legendary Chinese Bandits; Bert Jones, “the Ruston Rifle”; and the stadium from its completion in 1924 to the 1970s.”
</li><li>Year: 2013. Physical note: “First shown in LSU Libraries Special Collections with a touchscreen listening station.”
</li><li>Cover image: 1944team01.jpg (the game photo behind the old title graphic).
</li></ul>
<h4>Gallery names</h4>
<ul><li>Big Games, Players &amp; Teams, Coaching &amp; Practice (old “The Game” menu), Facilities, Culture: as on the old site. Lagniappe: old Photos section’s Lagniappe page. New: “The Narrators” (old “Biographies and Citations”), “About the Exhibition” (old “About”).
</li><li>Lagniappe introduction: “A little something extra: photographs of the band, the cheerleaders, Mike the Tiger and the Tulane rivalry, chosen from the hundred the touchscreen offered as lagniappe.”
</li></ul>
<h4>Entry titles (old page header → new title)</h4>
<ul><li><strong>Big Games</strong>
<ul><li>“Marvin Stewart, Former Player, on the dramatic game against TCU” → “1936 Sugar Bowl”
</li><li>“Charles Barney, Former Player, on the close game against Texas A&amp;M” → “1944 Orange Bowl”
</li><li>“Bud Johnson, Director of the Andonie Museum, on Y.A. Tittle's (pictured) fabled interception” → “LSU v. Ole Miss, 1947”
</li><li>“Gaynell Tinsley, Former Player &amp; Later Head Coach, on an unexpected win for LSU” → “LSU v. Tulane, 1949”
</li><li>“Bud Johnson, Director of the Andonie Museum, on fan base growing quickly after this big win” → “LSU v. Miami, 1958”
</li><li>“Carl Maddox, Former Coaching Staff &amp; Athletic Director, on the personal impact of winning the Championship” → “The 1958 Championship”
</li><li>“Bert Jones, Former Player, on the final plays of this historic game” → “LSU v. Ole Miss, 1972”
</li><li>“John Ferguson, Former Voice of the Tigers, on a favorite game” → “LSU v. USC, 1979”
</li><li>“Tom Hodson and David Browndyke, Former Players, on the famous LSU v. Auburn game” → “The 1988 Earthquake Game”
</li></ul>
</li><li><strong>Players &amp; Teams</strong>
<ul><li>“Marvin Stewart, Former Player, playing both offense and defense” → “Playing both offense and defense”
</li><li>“Gaynell Tinsley, Former Player &amp; Later Head Coach, on scholarship coverage and jobs for players” → “Scholarships and jobs for players”
</li><li>“Abe Mickal, Former Player, on pursuing an education” → “Pursuing an education”
</li><li>“Alfred Glassell, Former SGA President, on free football tickets for students” → “Free football tickets for students”
</li><li>“Thomas Ruffin, Alumnus, on LSU's team (pictured) playing Georgia twice” → “Playing Georgia twice”
</li><li>“Merle Schexnaildre, Former Player, on football being his primary motivation to attend LSU” → “Why he came to LSU”
</li><li>“Merle Schexnaildre, Former Player, on the origin of the Chinese Bandits (pictured)” → “The origin of the Chinese Bandits”
</li><li>“Gus Kinchen, Former Player, on playing defensive end for the Chinese Bandits” → “Defensive end for the Chinese Bandits”
</li><li>“John Ferguson, Former Voice of the Tigers, on fun to watch players” → “Players who were fun to watch”
</li><li>“Charles Alexander, Former Player, on his junior year season and the o-line” → “His junior season and the offensive line”
</li><li>“Tyler LaFauci, Former Player, on the importance of a calm demeanor” → “The importance of a calm demeanor”
</li><li>“Lora Hinton, Former Player, on wanting to finish as a player despite injury” → “Finishing as a player despite injury”
</li><li>“Kerry Pourciau, Former SGA President, on LSU being ready for an integrated team” → “Ready for an integrated team”
</li><li>“Lora Hinton, Former Player, on teammates standing up to racism” → “Teammates standing up to racism”
</li></ul>
</li><li><strong>Coaching &amp; Practice</strong>
<ul><li>“Jack Gremillion, Alumnus, on Huey Long and Coach Biff Jones (pictured) butting heads” → “Huey Long and Coach Biff Jones butt heads”
</li><li>“Marvin Stewart, Former Player, on player preparation changing over the years” → “How player preparation has changed”
</li><li>“Abe Mickal, Former Player, on choosing LSU over other schools' offers” → “Choosing LSU over other offers”
</li><li>“Marvin Stewart, Former Player, on Coach Moore's (pictured left) scrimmages” → “Coach Moore’s scrimmages”
</li><li>“Charles Barney, Former Player, on the character of Coach Moore (pictured)” → “The character of Coach Moore”
</li><li>“Carl Maddox, Former Coaching Staff &amp; Athletic Director, on Coach Dietzel's (pictured) discipline” → “Coach Dietzel’s discipline”
</li><li>“Carl Maddox, Former Coaching Staff &amp; Athletic Director, on recruiting and a talented walk-on player” → “Recruiting, and a talented walk-on”
</li><li>“Merle Schexnaildre, Former Player, on the importance of every team member” → “Every team member matters”
</li><li>“John Hunter, 14th President of LSU; 1st Head of LSU System, on hiring Coach McClendon (pictured right) before Kentucky could” → “Hiring Coach McClendon before Kentucky could”
</li><li>“Bud Johnson, Director of the Andonie Museum, on Charles McClendon’s small but aggressive players (pictured)” → “McClendon’s small but aggressive players”
</li><li>“Charles Alexander, Former Player, on position coach Jerry Stovall (pictured as player)” → “Position coach Jerry Stovall”
</li><li>“Martin Broussard, Former Athletic Trainer, on rule changes being beneficial” → “Rule changes for the better”
</li></ul>
</li><li><strong>Facilities</strong>
<ul><li>“Albert St. Dizier, Alumnus, on Tiger Stadium's early design” → “Tiger Stadium’s early design”
</li><li>“Roger &amp; Carrie Richardson, Alumni, on Carrie's father, Hamilton Johnston, helping design Tiger Stadium” → “Hamilton Johnston and the design of Tiger Stadium”
</li><li>“Oscar Richard, Alumnus, on Mike's origin and a prank by Tulane” → “Where Mike came from, and a prank by Tulane”
</li><li>“Charles Barney, Former Player, on players eating well even during WWII rationing” → “Eating well during wartime rationing”
</li><li>“Bud Johnson, Director of the Andonie Museum, on how weight training impacted LSU football” → “How weight training changed LSU football”
</li><li>“Ray Roy, Personal Trainer, on his brother, Alvin, getting LSU players weight training” → “Alvin Roy brings weight training to LSU”
</li><li>“John Ferguson, Former Voice of the Tigers, on capturing the audible atmosphere of the stadium” → “Capturing the sound of the stadium”
</li><li>“Sheryl Campbell, Alumna, on the roar of the stadium” → “The roar of the stadium”
</li><li>“Charles Alexander, Former Player, on being close with his college teammates” → “Close with his college teammates”
</li><li>“Martin Broussard, Former Athletic Trainer, on fan support being there, even in a losing season” → “Fans who stay, even in a losing season”
</li></ul>
</li><li><strong>Culture</strong>
<ul><li>“Jesse Coates, Longtime Professor, an origin story about the team's colors” → “How LSU got its colors”
</li><li>“Jesse Coates, Longtime Professor, on excitement in Baton Rouge after a win” → “Celebrating a win in Baton Rouge”
</li><li>“Cecil Taylor, First Chancellor of LSU, on pomp and circumstance on game day” → “Pomp and circumstance on game day”
</li><li>“Billy Heroman, Alumnus, on traveling to games by rail and car” → “Traveling to games by rail and car”
</li><li>“Abe Mickal, Former Player, on Huey Long's attempt to elect Mickal state senator” → “Huey Long tries to make Mickal a state senator”
</li><li>“Jack Gremillion, Alumnus, on ROTC groups wearing pajamas to Homecoming” → “ROTC in pajamas at Homecoming”
</li><li>“Bautista Martinez, LSU Fan, on sneaking out of Carville Leprosarium to attend games” → “Slipping out of Carville to see the Tigers”
</li><li>“Oliver Mack, Alumnus, on listening to LSU games on the radio” → “Listening to LSU on the radio”
</li><li>“Gus Kinchen, Former Player, on the notoriety that came with LSU's success” → “The notoriety that came with winning”
</li><li>“Gus Kinchen, Former Player, on fan support after a loss” → “Fan support after a loss”
</li><li>“John Ferguson, Former Voice of the Tigers, on becoming the Voice of the Tigers” → “Becoming the Voice of the Tigers”
</li><li>“Bud Johnson, Director of the Andonie Museum, on the genesis of "Hey, Fightin' Tigers!"” → “The genesis of “Hey, Fightin’ Tigers!””
</li><li>“Sheryl Campbell, Alumna, on rivalries with Ole Miss and Alabama” → “Rivalries with Ole Miss and Alabama”
</li><li>“Sheryl Campbell, Alumna, on football players taking a ballroom dance class” → “Football players in ballroom dance class”
</li><li>“John Ferguson, Former Voice of the Tigers, on broadcasts as a way to market LSU” → “Broadcasts that marketed LSU”
</li><li>“Sheryl Campbell, Alumna, on proper attire for game day” → “Proper attire for game day”
</li><li>“Charles Alexander, Former Player, on fans' support being motivating” → “Fans who motivate the team”
</li><li>“Margaret Jameson, Former Dean of Women, on the difficulty of obtaining tickets upon arrival as Dean” → “A new dean looks for tickets”
</li><li>“Roger Ogden, Former SGA President, on the excitement of attending games and meeting Billy Cannon” → “Game nights and meeting Billy Cannon”
</li></ul>
</li></ul>
<p>Big Games titles are the game names on the old tiles (the old tile for Bud Johnson’s 1958 clip reads “LSU v. U of Miami, 1958”).</p>
<h4>Clip credit lines</h4>
<p>Old “-- Name, interviewed by …” lines → “Name, interviewed by …” (no dash, no bold); changes are in list 1.</p>
<h4>Captions (old citation → new caption) and alt text (all new)</h4>
<ul><li><strong>marvinstewart01.jpg</strong> (Big Games / 1936 Sugar Bowl; Players &amp; Teams / Playing both offense and defense; Coaching &amp; Practice / How player preparation has changed)
<ul><li>Old caption: “Marvin Stewart: LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>New caption: “Marvin “Moose” Stewart. LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>Alt text: “Marvin Stewart, in a dark LSU jersey, crouches over the ball in a center’s stance in the stadium.”
</li></ul>
</li><li><strong>charlesbarney.jpg</strong> (Big Games / 1944 Orange Bowl)
<ul><li>Old caption: none
</li><li>New caption: “Charles Barney.”
</li><li>Alt text: “Charles Barney as a young man in a Navy sailor’s uniform.”
</li></ul>
</li><li><strong>yatittle01.jpg</strong> (Big Games / LSU v. Ole Miss, 1947)
<ul><li>Old caption: “Y.A. Tittle: Steve Franz, LSU Sports Information.”
</li><li>New caption: “Y.A. Tittle. Steve Franz, LSU Sports Information.”
</li><li>Alt text: “Y.A. Tittle, number 25, poses about to throw a pass in front of empty stands.”
</li></ul>
</li><li><strong>gaynelltinsley01.jpg</strong> (Big Games / LSU v. Tulane, 1949; Players &amp; Teams / Scholarships and jobs for players)
<ul><li>Old caption: “Gaynell Tinsley: LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>New caption: “Gaynell Tinsley. LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>Alt text: “Gaynell Tinsley in a lineman’s three-point stance on the practice field; the photo is signed.”
</li></ul>
</li><li><strong>budjohnson.jpg</strong> (Big Games / LSU v. Miami, 1958)
<ul><li>Old caption: none
</li><li>New caption: “Bud Johnson.”
</li><li>Alt text: “Bud Johnson stands in front of a museum wall celebrating LSU’s 1958 national champions.”
</li></ul>
</li><li><strong>carlmaddox01.jpg</strong> (Big Games / The 1958 Championship; Coaching &amp; Practice / Recruiting, and a talented walk-on)
<ul><li>Old caption: “Carl Maddox: Stave Franz, LSU Sports Information.”
</li><li>New caption: “Carl Maddox. Steve Franz, LSU Sports Information.”
</li><li>Alt text: “Carl Maddox, in a suit and tie, smiles in a wood-paneled office.”
</li></ul>
</li><li><strong>bertjones01.jpg</strong> (Big Games / LSU v. Ole Miss, 1972)
<ul><li>Old caption: “Bert Jones: LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>New caption: “Bert Jones. LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>Alt text: “Bert Jones in his LSU jersey on the sideline.”
</li></ul>
</li><li><strong>johnferguson01.jpg</strong> (Big Games / LSU v. USC, 1979; Players &amp; Teams / Players who were fun to watch; Facilities / Capturing the sound of the stadium; Culture / Becoming the Voice of the Tigers; Culture / Broadcasts that marketed LSU)
<ul><li>Old caption: “John Ferguson: Steve Franz, LSU Sports Information.”
</li><li>New caption: “John Ferguson. Steve Franz, LSU Sports Information.”
</li><li>Alt text: “John Ferguson in a headset at the microphone in the press box, beside other broadcasters.”
</li></ul>
</li><li><strong>tomhodson01.jpg</strong> (Big Games / The 1988 Earthquake Game)
<ul><li>Old caption: “Tommy Hodson: Steve Franz, LSU Sports Information.”
</li><li>New caption: “Tommy Hodson. Steve Franz, LSU Sports Information.”
</li><li>Alt text: “Tommy Hodson, number 13 in purple and gold, sets to pass in front of a packed crowd.”
</li></ul>
</li><li><strong>abemickal01.jpg</strong> (Players &amp; Teams / Pursuing an education)
<ul><li>Old caption: “Abe Mickal: LSU Photograph Collection, RG #A5000, Louisiana State University Archives, Baton Rouge, LA.”
</li><li>New caption: “Abe Mickal. LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>Alt text: “Portrait of Abe Mickal in a suit and tie, signed in the corner.”
</li></ul>
</li><li><strong>alfredglassell.jpg</strong> (Players &amp; Teams / Free football tickets for students)
<ul><li>Old caption: none
</li><li>New caption: “Alfred Glassell.”
</li><li>Alt text: “Yearbook portrait of Alfred Glassell in an oval frame.”
</li></ul>
</li><li><strong>1944team01.jpg</strong> (Players &amp; Teams / Playing Georgia twice; Facilities / Eating well during wartime rationing; Culture / Traveling to games by rail and car)
<ul><li>Old caption: “1944 Team: <em>Gumbo</em>, 1944. LD3118.G8 University Archives.”
</li><li>New caption: “The 1944 team in action. <em>Gumbo</em>, 1944. LD3118 .G8, University Archives.”
</li><li>Alt text: “An LSU ball carrier, number 17, cuts upfield past defenders in front of a full stadium.”
</li></ul>
</li><li><strong>schexnaildre_dream.jpg</strong> (Players &amp; Teams / Why he came to LSU; Coaching &amp; Practice / Every team member matters)
<ul><li>Old caption: “Merle Schexnaildre: <em>Gumbo</em>, 1959. LD3118 .G8 University Archives”
</li><li>New caption: “Merle Schexnaildre. <em>Gumbo</em>, 1959. LD3118 .G8, University Archives.”
</li><li>Alt text: “Merle Schexnaildre in jersey number 43.”
</li></ul>
</li><li><strong>merleschexnaildre02.jpg</strong> (Players &amp; Teams / The origin of the Chinese Bandits)
<ul><li>Old caption: “Chinese Bandits: Sugar Bowl Program, 1959 GV 958 .L65 L66 LLMVC”
</li><li>New caption: “The Chinese Bandits. Sugar Bowl program, 1959. GV958 .L65 L66, Louisiana and Lower Mississippi Valley Collections, LSU Libraries.”
</li><li>Alt text: “A program page of twelve portraits headed “LSU Chinese Bandits,” Merle Schexnaildre among them.”
</li></ul>
</li><li><strong>guskinchen01.jpg</strong> (Players &amp; Teams / Defensive end for the Chinese Bandits; Culture / The notoriety that came with winning)
<ul><li>Old caption: “Gus Kinchen: Steve Franz, LSU Sports Information.”
</li><li>New caption: “Gus Kinchen. Steve Franz, LSU Sports Information.”
</li><li>Alt text: “Gus Kinchen, jersey number 81, looks off to the side.”
</li></ul>
</li><li><strong>charlesalexander01.jpg</strong> (Players &amp; Teams / His junior season and the offensive line; Facilities / Close with his college teammates)
<ul><li>Old caption: “Charles Alexander: LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>New caption: “Charles Alexander. LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>Alt text: “Charles Alexander, number 4, runs with the ball through a gap in the line.”
</li></ul>
</li><li><strong>tylerlafauci01.jpg</strong> (Players &amp; Teams / The importance of a calm demeanor)
<ul><li>Old caption: “Tyler LaFauci: LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>New caption: “Tyler LaFauci. LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>Alt text: “Tyler LaFauci, number 64, poses charging forward in the stadium.”
</li></ul>
</li><li><strong>lorahinton02.jpg</strong> (Players &amp; Teams / Finishing as a player despite injury)
<ul><li>Old caption: “Lora Hinton: LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>New caption: “Lora Hinton. LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>Alt text: “LSU runs a play at the line of scrimmage in front of a crowded sideline.”
</li></ul>
</li><li><strong>kerrypourciau.jpg</strong> (Players &amp; Teams / Ready for an integrated team)
<ul><li>Old caption: none
</li><li>New caption: “Kerry Pourciau.”
</li><li>Alt text: “Kerry Pourciau, in a leather jacket, speaks to a crowd of students.”
</li></ul>
</li><li><strong>lorahinton01.jpg</strong> (Players &amp; Teams / Teammates standing up to racism)
<ul><li>Old caption: “Lora Hinton: LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>New caption: “Lora Hinton. LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>Alt text: “Portrait of Lora Hinton in a suit and tie.”
</li></ul>
</li><li><strong>biffjones.jpg</strong> (Coaching &amp; Practice / Huey Long and Coach Biff Jones butt heads)
<ul><li>Old caption: “Biff Jones: <em>Gumbo</em>, 1934. LD 3118 .G8 University Archives.”
</li><li>New caption: “Coach Biff Jones. <em>Gumbo</em>, 1934. LD3118 .G8, University Archives.”
</li><li>Alt text: “Coach Biff Jones in a sweater on the practice field.”
</li></ul>
</li><li><strong>abemickal02.jpg</strong> (Coaching &amp; Practice / Choosing LSU over other offers)
<ul><li>Old caption: “Abe Mickal: LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>New caption: “Abe Mickal. LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>Alt text: “Abe Mickal, in a leather-patched practice jersey, throws a pass in front of a campus building.”
</li></ul>
</li><li><strong>moorecoates.jpg</strong> (Coaching &amp; Practice / Coach Moore’s scrimmages)
<ul><li>Old caption: none
</li><li>New caption: “Coach Bernie Moore, left.”
</li><li>Alt text: “Coach Bernie Moore, left, in a suit, talks with an older man.”
</li></ul>
</li><li><strong>berniemoore01.jpg</strong> (Coaching &amp; Practice / The character of Coach Moore)
<ul><li>Old caption: “Bernie Moore: LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>New caption: “Coach Bernie Moore. LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>Alt text: “Coach Bernie Moore in a hat and tie sits on a wooden chair at practice.”
</li></ul>
</li><li><strong>pauldietzel01.jpg</strong> (Coaching &amp; Practice / Coach Dietzel’s discipline)
<ul><li>Old caption: “Paul Dietzel: LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>New caption: “Coach Paul Dietzel. LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>Alt text: “Portrait of Coach Paul Dietzel in a plaid jacket and tie.”
</li></ul>
</li><li><strong>mcclendondietzel01.jpg</strong> (Coaching &amp; Practice / Hiring Coach McClendon before Kentucky could)
<ul><li>Old caption: “McClendon and Dietzel: Charles East Papers, Mss. 3471, Louisiana and Lower Mississippi Valley Collections, LSU Libraries, Baton Rouge, LA.”
</li><li>New caption: “Charles McClendon, right, and Paul Dietzel. Charles East Papers, Mss. 3471, Louisiana and Lower Mississippi Valley Collections, LSU Libraries, Baton Rouge, LA.”
</li><li>Alt text: “Two coaches in white shirts stand on the sideline in front of the crowd.”
</li></ul>
</li><li><strong>1968team01.jpg</strong> (Coaching &amp; Practice / McClendon’s small but aggressive players)
<ul><li>Old caption: “1968 Team: Office of Public Relations Records, RG #A0020, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>New caption: “The 1968 team. Office of Public Relations Records, RG #A0020, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>Alt text: “The 1968 team in rows on the field of Tiger Stadium, the empty stands behind them.”
</li></ul>
</li><li><strong>jerrystovall01.jpg</strong> (Coaching &amp; Practice / Position coach Jerry Stovall)
<ul><li>Old caption: “Jerry Stovall: LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>New caption: “Jerry Stovall as a player. LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>Alt text: “Jerry Stovall, number 21, carries the ball past a tackler.”
</li></ul>
</li><li><strong>martinbroussard.jpg</strong> (Coaching &amp; Practice / Rule changes for the better; Facilities / Fans who stay, even in a losing season)
<ul><li>Old caption: none
</li><li>New caption: “Martin Broussard.”
</li><li>Alt text: “Martin Broussard smiles, looking off camera.”
</li></ul>
</li><li><strong>stadium1925.jpg</strong> (Facilities / Tiger Stadium’s early design)
<ul><li>Old caption: “Stadium 1925: LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>New caption: “Tiger Stadium, 1925. LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>Alt text: “A tinted postcard of the new stadium’s two open stands on a green field, labeled “Louisiana State University Stadium, Baton Rouge, La.””
</li></ul>
</li><li><strong>tigerstadium1936.jpg</strong> (Facilities / Hamilton Johnston and the design of Tiger Stadium)
<ul><li>Old caption: none
</li><li>New caption: “Tiger Stadium from the air, full for a game.”
</li><li>Alt text: “Aerial view of the horseshoe stadium packed with spectators, letters marked on the field.”
</li></ul>
</li><li><strong>miketrainer.jpg</strong> (Facilities / Where Mike came from, and a prank by Tulane)
<ul><li>Old caption: none
</li><li>New caption: “A keeper reaches into Mike the Tiger’s cage.”
</li><li>Alt text: “A man in a pith helmet crouches beside a cage, reaching in to a tiger lying behind the bars.”
</li></ul>
</li><li><strong>alvinroy01.jpg</strong> (Facilities / How weight training changed LSU football)
<ul><li>Old caption: “Ad for Alvin Roy: <em>Daily Reveille</em>, September 11, 1958. Microfilm 632 University Archives.”
</li><li>New caption: “An advertisement for Alvin Roy’s Health Studios. <em>Daily Reveille</em>, September 11, 1958. Microfilm 632, University Archives.”
</li><li>Alt text: “Newspaper advertisement headed “You’ll Enjoy Being Handsome!” with a drawing of a bodybuilder, offering one week free at Alvin Roy’s.”
</li></ul>
</li><li><strong>alvinroy02.jpg</strong> (Facilities / Alvin Roy brings weight training to LSU)
<ul><li>Old caption: none
</li><li>New caption: “Another Alvin Roy advertisement.”
</li><li>Alt text: “Narrow newspaper advertisement: “Girls go for men with lifeguard shoulders!” from Alvin Roy’s.”
</li></ul>
</li><li><strong>stadium60s.jpg</strong> (Facilities / The roar of the stadium; Culture / Slipping out of Carville to see the Tigers)
<ul><li>Old caption: “Stadium roar (60s): LSU Police Department Records, RG #A0205, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>New caption: “Tiger Stadium in the 1960s. LSU Police Department Records, RG #A0205, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>Alt text: “Aerial view of the full stadium surrounded by acres of parked cars.”
</li></ul>
</li><li><strong>1902team.jpg</strong> (Culture / How LSU got its colors)
<ul><li>Old caption: “1902 Team: LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>New caption: “The 1902 team. LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>Alt text: “The 1902 team, in striped jerseys, poses in front of an ivy wall above a mount signed with the players’ names.”
</li></ul>
</li><li><strong>cadetstiger.jpg</strong> (Culture / Celebrating a win in Baton Rouge)
<ul><li>Old caption: “Cadets loading tiger onto train: Department of Military Science Records, RG #A0602, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>New caption: “Cadets loading a tiger onto a train. Department of Military Science Records, RG #A0602, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>Alt text: “Uniformed cadets gathered on a railroad platform beside a passenger car.”
</li></ul>
</li><li><strong>hullabaloo.jpg</strong> (Culture / Pomp and circumstance on game day)
<ul><li>Old caption: “Hullabaloo in the ‘30s: Office of Public Relations Records, RG #A0020, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>New caption: “Hullabaloo in the ’30s. Office of Public Relations Records, RG #A0020, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>Alt text: “A smiling drum major and two majorettes in white uniforms and plumed hats.”
</li></ul>
</li><li><strong>hueyhouston.jpg</strong> (Culture / Huey Long tries to make Mickal a state senator)
<ul><li>Old caption: none
</li><li>New caption: “Huey P. Long at the head of the band.”
</li><li>Alt text: “Huey P. Long, in a light suit and holding his hat, strides ahead of uniformed band members on a city street.”
</li></ul>
</li><li><strong>pajamafootball.jpg</strong> (Culture / ROTC in pajamas at Homecoming)
<ul><li>Old caption: none
</li><li>New caption: “Students cheering at a night game.”
</li><li>Alt text: “Students in the stands at night, arms raised, cheering.”
</li></ul>
</li><li><strong>1958champs.jpg</strong> (Culture / Listening to LSU on the radio)
<ul><li>Old caption: none
</li><li>New caption: “LSU Tigers, 1958 national champions.”
</li><li>Alt text: “Team photo of the 1958 squad in the stadium, captioned “LSU Tigers, 1958 National Champions.””
</li></ul>
</li><li><strong>mikeiiarriving.jpg</strong> (Culture / Fan support after a loss)
<ul><li>Old caption: none
</li><li>New caption: “A crowd gathers as Mike II arrives on campus.”
</li><li>Alt text: “A crowd of men surrounds a truck on the campus lawn, Memorial Tower behind.”
</li></ul>
</li><li><strong>majortiger.jpg</strong> (Culture / The genesis of “Hey, Fightin’ Tigers!”)
<ul><li>Old caption: “Drum Major with tiger: Office of Public Relations Records, RG #A0020, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>New caption: “The drum major and a costumed tiger lead the band. Office of Public Relations Records, RG #A0020, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>Alt text: “A majorette, two drum majors and a costumed tiger lead the band down a road.”
</li></ul>
</li><li><strong>olemiss.jpg</strong> (Culture / Rivalries with Ole Miss and Alabama)
<ul><li>Old caption: “Go to Hell Ole Miss: Gumbo, 1971. LD 3118 .G8 University Archives.”
</li><li>New caption: “A “Go to Hell Ole Miss” banner. <em>Gumbo</em>, 1971. LD3118 .G8, University Archives.”
</li><li>Alt text: “Fans in the stands hold up a Confederate battle flag painted “Go to Hell Ole Miss.””
</li></ul>
</li><li><strong>moreaukick.jpg</strong> (Culture / Football players in ballroom dance class)
<ul><li>Old caption: none
</li><li>New caption: “A placekick.”
</li><li>Alt text: “A placekick from the hold, players blocking at the line.”
</li></ul>
</li><li><strong>attire.jpg</strong> (Culture / Proper attire for game day)
<ul><li>Old caption: none
</li><li>New caption: “Dressed for game day.”
</li><li>Alt text: “Five young women in 1960s suits and dresses stand among trees.”
</li></ul>
</li><li><strong>homecomingdecorations.jpg</strong> (Culture / Fans who motivate the team)
<ul><li>Old caption: none
</li><li>New caption: “A homecoming display.”
</li><li>Alt text: “A homecoming display in front of a house: a giant tiger beside a “Tiger Gumbo” recipe board listing the season’s opponents.”
</li></ul>
</li><li><strong>margaretjamesonhr.jpg</strong> (Culture / A new dean looks for tickets)
<ul><li>Old caption: none
</li><li>New caption: “Margaret Jameson in her office.”
</li><li>Alt text: “Margaret Jameson at her desk, talking with a young man seated across from her.”
</li></ul>
</li><li><strong>cannondietzel01.jpg</strong> (Culture / Game nights and meeting Billy Cannon)
<ul><li>Old caption: “Cannon, Dietzel, etc: Charles East Papers, Mss. 3471, Louisiana and Lower Mississippi Valley Collections, LSU Libraries, Baton Rouge, LA.”
</li><li>New caption: “Billy Cannon, Coach Paul Dietzel and teammates. Charles East Papers, Mss. 3471, Louisiana and Lower Mississippi Valley Collections, LSU Libraries, Baton Rouge, LA.”
</li><li>Alt text: “Four players in practice jerseys talk with Coach Dietzel, the stadium behind them.”
</li></ul>
</li><li><strong>lagn001.jpg</strong> (Lagniappe)
<ul><li>Old caption: none (Lagniappe photo)
</li><li>New caption: “Four students pose with a stuffed tiger.”
</li><li>Alt text: “Four young women sit on a stone ledge beside a large stuffed tiger.”
</li></ul>
</li><li><strong>lagn003.jpg</strong> (Lagniappe)
<ul><li>Old caption: none (Lagniappe photo)
</li><li>New caption: “Cheerleaders run an LSU–Tulane banner down the field.”
</li><li>Alt text: “Two cheerleaders in white carry an LSU and Tulane banner along the sideline before a full stadium.”
</li></ul>
</li><li><strong>lagn008.jpg</strong> (Lagniappe)
<ul><li>Old caption: none (Lagniappe photo)
</li><li>New caption: “A rivalry display: the tiger and the Tulane goat.”
</li><li>Alt text: “A papier-mâché tiger faces a goat marked “TU” under a palm tree.”
</li></ul>
</li><li><strong>lagn032.jpg</strong> (Lagniappe)
<ul><li>Old caption: none (Lagniappe photo)
</li><li>New caption: “Raising money for a new cage for Mike the Tiger.”
</li><li>Alt text: “Two students sit at a table outside the stadium gates beside a sign: “Help Build a New Cage for Mike the Tiger. Please give what you can.””
</li></ul>
</li><li><strong>lagn036.jpg</strong> (Lagniappe)
<ul><li>Old caption: none (Lagniappe photo)
</li><li>New caption: “Tiger Stadium after its enclosure.”
</li><li>Alt text: “The stadium’s exterior, a continuous wall of arches, across an open field.”
</li></ul>
</li><li><strong>lagn038.jpg</strong> (Lagniappe)
<ul><li>Old caption: none (Lagniappe photo)
</li><li>New caption: “Mike the Tiger’s cage.”
</li><li>Alt text: “A barred enclosure beside a small building with a weathervane.”
</li></ul>
</li><li><strong>lagn050.jpg</strong> (Lagniappe)
<ul><li>Old caption: none (Lagniappe photo)
</li><li>New caption: “LSU v. Tulane from the air.”
</li><li>Alt text: “Aerial view of the stadium crowded for an LSU–Tulane game, cars parked all around it.”
</li></ul>
</li><li><strong>lagn051.jpg</strong> (Lagniappe)
<ul><li>Old caption: none (Lagniappe photo)
</li><li>New caption: “The band in front of Memorial Tower.”
</li><li>Alt text: “The uniformed band seated with its instruments on the steps of Memorial Tower.”
</li></ul>
</li><li><strong>lagn054.jpg</strong> (Lagniappe)
<ul><li>Old caption: none (Lagniappe photo)
</li><li>New caption: “A kick line in Tiger Stadium.”
</li><li>Alt text: “A line of dancers high-kicking on the field of the empty stadium.”
</li></ul>
</li><li><strong>lagn078.jpg</strong> (Lagniappe)
<ul><li>Old caption: none (Lagniappe photo)
</li><li>New caption: “The band spells out a formation at halftime.”
</li><li>Alt text: “The band in formation on the field between packed stands.”
</li></ul>
</li><li><strong>lagn087.jpg</strong> (Lagniappe)
<ul><li>Old caption: none (Lagniappe photo)
</li><li>New caption: “Mike the Tiger.”
</li><li>Alt text: “Close-up of a tiger lying down, tongue out.”
</li></ul>
</li><li><strong>lagn094.jpg</strong> (Lagniappe)
<ul><li>Old caption: none (Lagniappe photo)
</li><li>New caption: “A birthday cake for Mike.”
</li><li>Alt text: “People gather around a large cake in front of a cage spelling “MIKE.””
</li></ul>
</li><li><strong>lagn095.jpg</strong> (Lagniappe)
<ul><li>Old caption: none (Lagniappe photo)
</li><li>New caption: “Mike’s cage on the field.”
</li><li>Alt text: “Students ride on top of Mike the Tiger’s cage wagon as it is towed around the stadium field.”
</li></ul>
</li><li><strong>lagn099.jpg</strong> (Lagniappe)
<ul><li>Old caption: none (Lagniappe photo)
</li><li>New caption: ““Here lies Tulane’s goat.””
</li><li>Alt text: “A student in a sailor’s collar holds a book beside a mock grave cross reading “Here lies Tulane’s goat.””
</li></ul>
</li></ul>
<h4>Lead images (gallery headers; caption = that image’s caption above)</h4>
<ul><li>Big Games: tomhodson01.jpg — “Tommy Hodson. Steve Franz, LSU Sports Information.”
</li><li>Players &amp; Teams: charlesalexander01.jpg — “Charles Alexander. LSU Photograph Collection, RG #A5000, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>Coaching &amp; Practice: mcclendondietzel01.jpg — “Charles McClendon, right, and Paul Dietzel. Charles East Papers, Mss. 3471, Louisiana and Lower Mississippi Valley Collections, LSU Libraries, Baton Rouge, LA.”
</li><li>Facilities: stadium60s.jpg — “Tiger Stadium in the 1960s. LSU Police Department Records, RG #A0205, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>Culture: majortiger.jpg — “The drum major and a costumed tiger lead the band. Office of Public Relations Records, RG #A0020, Louisiana State University Archives, LSU Libraries, Baton Rouge, LA.”
</li><li>Lagniappe: lagn050.jpg — “LSU v. Tulane from the air.”
</li><li>The Narrators and About the Exhibition have no lead image.
</li></ul>
<h4>The Narrators</h4>
<ul><li>Each biography now starts with the name as a heading and the role from the old page header in bold, e.g. “Former Voice of the Tigers”. The old pages’ portrait images are not repeated here.
</li></ul>
<h4>About the Exhibition</h4>
<ul><li>Two paragraphs of the old touchscreen text now open with bold run-in heads: “The University Archives collections.” and “The T. Harry Williams Center for Oral History’s University History, LSU Athletics Collection.” (old: headings “ON THE TOUCHSCREEN”, “The University Archives Collections:” …).
</li></ul>
HTML),
]);

$section('Tempests: Storms in the Archives', [
  $text(NULL, <<<'HTML'
<p>Now at <a href="/v2/oralhistory/exhibitions/storm">/v2/oralhistory/exhibitions/storm</a>.</p>
<p>For curators’ review. Old exhibition: <code>_reference/Oralhistory Exhibitions/presentations/storm/</code> (live at <a href="https://lib.lsu.edu/sites/all/files/oralhistory/presentations/storm/home.html">https://lib.lsu.edu/sites/all/files/oralhistory/presentations/storm/home.html</a>). New text: <code>scripts/data/tempests.json</code>, built by <code>scripts/palefire_exhibit_tempests.php</code>. The old site was one list of 42 clips; the new page groups 41 of them into five galleries by era, and the 42nd (the Lower Ninth Ward video) became text in About the Exhibition. Where a line below says “gallery / entry”, the entry is named by its new title.</p>
<p>Applied everywhere, not itemized below: straight apostrophes in titles, captions and About became ’; speaker labels changed from “<strong>Name</strong>:” at the start of a line to a bold label in capitals on its own paragraph (“<strong>EARL ROBICHEAUX:</strong>”), with line breaks (<code>&lt;br&gt;&lt;br&gt;</code>) turned into paragraphs; ALL-CAPS titles in the old side menu became sentence case.</p>
<h3>Corrected</h3>
<h4>Entry titles</h4>
<p>Old title is the clip page’s heading; where the old side menu said something different, that is given too. Rewordings that go beyond a typo or case fix are also listed under 3.</p>
<ul><li>The Old Storms, 1893–1926: “a woman and her three children perish in the 1926 storm” → “A woman and her three children perish in the 1926 storm”
</li><li>The Old Storms, 1893–1926: “1926 hurricane and the demise of one family's shrimping platforms” → “The 1926 hurricane and the end of one family’s shrimping platforms”
</li><li>Audrey and the Marsh, 1950s: “Hurricanes Flossy and Betsy; Riding out storms in the Coast Guard station” → “Hurricanes Flossy and Betsy; riding out storms in the Coast Guard station”
</li><li>Audrey and the Marsh, 1950s: “Impact of Hurricane Audrey on marsh grass, erosion” → “The impact of Hurricane Audrey on marsh grass and erosion”
</li><li>Audrey and the Marsh, 1950s: “Remembers her Red Cross relief effort experiences after Hurricane Audrey” → “Red Cross relief work after Hurricane Audrey”
</li><li>Betsy, 1965: “Long term effects of Betsy on Lower Ninth Ward” (side menu: “Long term effects of Betsy on the Lower Ninth Ward”) → “Long-term effects of Betsy on the Lower Ninth Ward”
</li><li>Betsy, 1965: “Hurricanes in the 1960s; Experiencing the eye wall in Betsy” → “Hurricanes in the 1960s; experiencing the eye wall in Betsy”
</li><li>Betsy, 1965: “Surviving Hurricane Betsy; The impacts of Camille and Katrina” → “Surviving Hurricane Betsy; the impacts of Camille and Katrina”
</li><li>Betsy, 1965: “Riding out Hurricane Besty in Westwego” → “Riding out Hurricane Betsy in Westwego”
</li><li>Living with the Coast: “Terrebonne Parish drainage issues during storms over last 150 years” → “Terrebonne Parish drainage issues during storms over the last 150 years”
</li><li>Living with the Coast: “Danger of slow moving storms to barrier islands” → “The danger of slow-moving storms to barrier islands”
</li><li>Living with the Coast: “Importance of coastal restoration for preserving Louisiana's culture” → “The importance of coastal restoration for preserving Louisiana’s culture”
</li><li>Living with the Coast: “Grand Isle Mayor on convincing people to evacuate for a hurricane” → “Grand Isle’s mayor on convincing people to evacuate for a hurricane”
</li><li>Living with the Coast: “Improved oyster harvest after Hurricane Andrew” → “An improved oyster harvest after Hurricane Andrew”
</li><li>Living with the Coast: “Coastal restoration projects; Transformation of the coastline by Katrina” → “Coastal restoration projects; the transformation of the coastline by Katrina”
</li><li>Katrina and Rita, 2005: “Emotional trauma of being displaced” → “The emotional trauma of being displaced”
</li><li>Katrina and Rita, 2005: “Evacuation of Riverbend Nursing Home in Kentwood, LA, during Katrina” (side menu: “Evacuation of Riverbend Nursing Home in Kentwood”) → “Evacuating Riverbend Nursing Home to Kentwood, Louisiana, during Katrina”
</li><li>Katrina and Rita, 2005: “Hospital's preparedness for Hurricane Katrina” → “A hospital’s preparedness for Hurricane Katrina”
</li><li>Katrina and Rita, 2005: “Loss of livestock after Hurricanes Rita and Ike” → “The loss of livestock after Hurricanes Rita and Ike”
</li><li>About the Exhibition: “Lower 9th Ward Oral History Archive Clips” (side menu: “LOWER 9TH WARD ORAL HISTORY ARCHIVE”) → heading “Lower Ninth Ward Oral History Archive”
</li></ul>
<h4>Captions</h4>
<ul><li>The Old Storms, 1893–1926 / “Rigaud’s grandmother survived the storm of 1893, but lost her parents” (old page audio.html): “Grand Isle, center, "General Reconnaissance of the Gulf Coast'" 1853. NOAA.” → “Grand Isle, center, “General Reconnaissance of the Gulf Coast,” 1853. NOAA.”
</li><li>The Old Storms, 1893–1926 / “The 1926 hurricane and the end of one family’s shrimping platforms” (old page C3.html): “Seabreeze Pass, "Barataria Bay to Terrebonne LA," 1908. NOAA.” → “Seabreeze Pass, “Barataria Bay to Terrebonne LA,” 1908. NOAA.”
</li><li>Audrey and the Marsh, 1950s / “The impact of Hurricane Audrey on marsh grass and erosion” (old page C5.html): “Marsh die-back observed in Terrebonne Basin, LA. USGS.” → “Marsh die-back observed in Terrebonne Basin, Louisiana. USGS.”
</li><li>Audrey and the Marsh, 1950s / “Drought, fire, and storms in the marshlands around Grand Chenier” (old page C6.html): “Grand Chenier, Mermentau River, "Constance Bayou to Calcasieu Pass," 1928. NOAA.” → “Grand Chenier, Mermentau River, “Constance Bayou to Calcasieu Pass,” 1928. NOAA.”
</li><li>Betsy, 1965 / “The seriousness of Betsy, as men prayed” (old page C9.html): “Archbishop Philip Hannan after Hurricane Betsy hits Lousiana. CNS photo by Frank J. Methe, Clarion Herald, 1965.” → “Archbishop Philip Hannan after Hurricane Betsy hit Louisiana. CNS photo by Frank J. Methe, Clarion Herald, 1965.”
</li><li>Betsy, 1965 / “Grand Isle rebuilds after Hurricanes Betsy and Katrina” (old page C10.html): “Hurricane Betsy damage in Grand Isle, LA, 1965. U.S. Army Corps of Engineers, Louisiana Digital Library.” → “Hurricane Betsy damage in Grand Isle, Louisiana, 1965. U.S. Army Corps of Engineers, Louisiana Digital Library.”
</li><li>Betsy, 1965 / “National Guard relief efforts after Betsy; President Johnson helping evacuees” (old page C19.html): “President Lyndon Johnson speaks with William Marshall who rode out Betsy on St. Claude Avenue. Times Picayune, September 11, 1965.” → “President Lyndon Johnson speaks with William Marshall, who rode out Betsy on St. Claude Avenue. Times-Picayune, September 11, 1965.”
</li><li>Betsy, 1965 / “Grandfather rode out Betsy with his dog in the car” (old page C30.html): “Mathew Thiel.” → “Matthew Thiel.”
</li><li>Living with the Coast / “After the storm, moving away or elevating camps in Cocodrie” (old page C23.html): “Elevated camp in Cocodrie, LA. 2004. Louisiana State Archives” → “Elevated camp in Cocodrie, Louisiana, 2004. Louisiana State Archives.”
</li><li>Living with the Coast / “The danger of slow-moving storms to barrier islands” (old page C24.html): “Storm tracks, 1957-2008. NOAA.” → “Storm tracks, 1957–2008. NOAA.”
</li><li>Living with the Coast / “Small storms that hit Grand Isle seem to intensify” (old page C25.html): “Grand Isle 1939 nautical chart from the US Coast &amp; Geodetic Survey. NOAA.” → “Grand Isle, 1939 nautical chart from the U.S. Coast and Geodetic Survey. NOAA.”
</li><li>Living with the Coast / “Grand Isle’s mayor on convincing people to evacuate for a hurricane” (old page C28.html): “Post-Katrina Grande Isle, 2005. NOAA.” → “Grand Isle after Katrina, 2005. NOAA.”
</li><li>Katrina and Rita, 2005 / “Human kindness kept him from giving up after losing so much in Katrina” (old page C39.html): “Victor Cusher.Photo by David Breidenbach.” → “Victor Cusher. Photo by David Breidenbach.” (the portrait now comes first, the FEMA photo second; that caption is unchanged)
</li></ul>
<p>The same captions are reused as the gallery lead captions (see 3).</p>
<h4>Names and credit lines</h4>
<ul><li>Hurricanes Flossy and Betsy: “Russel Crosby” (old credit line and side menu) → “Russell Crosby”, as in the transcript’s own speaker label and the audio file name.
</li><li>Evacuating Riverbend Nursing Home: speaker label “Cathy Chauvin” → “KATHY CHAUVIN”, to match the credit line and caption (“Daryl and Kathy Chauvin”).
</li><li>Grandfather rode out Betsy: caption “Mathew Thiel.” → “Matthew Thiel.”, to match the credit line and speaker label.
</li><li>Returning to New Orleans after Katrina: speaker label “Dr. Frank Minyard” → “FRANK MINYARD” (the credit line keeps “Dr. Frank Minyard”).
</li><li>Importance of coastal restoration: “Kerry St. P&amp;#233;” (an HTML code shown in the old text) → “Kerry St. Pé”.
</li><li>Learning to evacuate (George Miller): the old credit line repeated the photo credit (“George Miller. Photo by David Breidenbach.”) → credit is now just “George Miller” (see 2).
</li></ul>
<h4>Transcripts</h4>
<ul><li>The Old Storms, 1893–1926 / “A woman and her three children perish in the 1926 storm” (old page C2.html): “"Oh no," she said. I can't go,"” → “"Oh no," she said. "I can't go,"”
</li><li>Audrey and the Marsh, 1950s / “Drought, fire, and storms in the marshlands around Grand Chenier” (old page C6.html): “trapping.There” → “trapping. There”
</li><li>Audrey and the Marsh, 1950s / “Red Cross relief work after Hurricane Audrey” (old page C7.html): “- that were so poor that they couldn't afford a funeral -” → “—that were so poor that they couldn't afford a funeral—”
</li><li>Betsy, 1965 / “Long-term effects of Betsy on the Lower Ninth Ward” (old page C11.html): “We sent . . We sent” → “We sent . . . We sent”
</li><li>Betsy, 1965 / “Surviving in the floodwaters of Hurricane Betsy” (old page C12.html): “a little bit of a flooding. So” → “a little bit of a flooding." So”
</li><li>Betsy, 1965 / “Surviving Hurricane Betsy; the impacts of Camille and Katrina” (old page C16.html): “Florida has a hurricanes” → “Florida has hurricanes”
</li><li>Betsy, 1965 / “Riding out Hurricane Betsy in Westwego” (old page C18.html): “five of six of them” → “five or six of them”
</li><li>Betsy, 1965 / “Riding out Hurricane Betsy in Westwego” (old page C18.html): “memories - I was” → “memories—I was”
</li><li>Betsy, 1965 / “Grandfather rode out Betsy with his dog in the car” (old page C30.html): “something bad in in 1927” → “something bad in 1927”
</li><li>Living with the Coast / “Terrebonne Parish drainage issues during storms over the last 150 years” (old page C22.html): “drainage districts formed. and” → “drainage districts formed, and”
</li><li>Living with the Coast / “Terrebonne Parish drainage issues during storms over the last 150 years” (old page C22.html): “Gulf of Mexico.Love when” → “Gulf of Mexico. Love when”
</li><li>Living with the Coast / “Terrebonne Parish drainage issues during storms over the last 150 years” (old page C22.html): “Gulf of Mexicom hoss!” → “Gulf of Mexico, hoss!”
</li><li>Living with the Coast / “After the storm, moving away or elevating camps in Cocodrie” (old page C23.html): “elevate you camp” → “elevate your camp”
</li><li>Living with the Coast / “After the storm, moving away or elevating camps in Cocodrie” (old page C23.html): “- if you want to build, you got to elevate your camp up -” → “—if you want to build, you got to elevate your camp up—”
</li><li>Living with the Coast / “The importance of coastal restoration for preserving Louisiana’s culture” (old page C27.html): “St. P&amp;#233;” → “St. Pé” (speaker label)
</li><li>Living with the Coast / “The importance of coastal restoration for preserving Louisiana’s culture” (old page C27.html): “things we could use if we weren't restored” → “things we could lose if we weren't restored”
</li><li>Living with the Coast / “Grand Isle’s mayor on convincing people to evacuate for a hurricane” (old page C28.html): “we lost everything".” → “we lost everything."”
</li><li>Katrina and Rita, 2005 / “Escaping through the roof in Katrina and being rescued by a Good Samaritan” (old page C20.html): “that was way passed” → “that was way past”
</li><li>Katrina and Rita, 2005 / “Evacuating Riverbend Nursing Home to Kentwood, Louisiana, during Katrina” (old page C35.html): “came though” → “came through”
</li><li>Katrina and Rita, 2005 / “Evacuating Riverbend Nursing Home to Kentwood, Louisiana, during Katrina” (old page C35.html): “I never though ice” → “I never thought ice”
</li></ul>
<h4>About the Exhibition</h4>
<ul><li>“Peoples' memories extend” → “People’s memories extend”.
</li><li>“their consequences--rural and urban”, “rapid change--sometimes”, “turn up the volume--make some noise” → em dashes (“—”).
</li><li>“For more information about the exhibition, visit <a href="https://lib.lsu.edu/exhibitions.”">https://lib.lsu.edu/exhibitions.”</a> → “… visit LSU Libraries Exhibitions.” (linked).
</li><li>“please contact Center staff at <a href="http://www.lib.lsu.edu/oralhistory/.”">http://www.lib.lsu.edu/oralhistory/.”</a> → “please contact Center staff.” (“Center staff” links to <a href="https://lib.lsu.edu/oralhistory">https://lib.lsu.edu/oralhistory</a>).
</li><li>“For details, see : <a href="https://lib.lsu.edu/oralhistory/collections”">https://lib.lsu.edu/oralhistory/collections”</a> → “For details, see the Williams Center collections.” (linked).
</li><li>Collections list: “Pointe Coupee : Voices in the Storm” → “Pointe Coupee: Voices in the Storm”; “"I Remember: An Art Show of Environmental Significance,"" CWPPRA” (doubled closing quote) → ““I Remember: An Art Show of Environmental Significance,” CWPPRA”. The list is now a bulleted list.
</li><li>Lower Ninth Ward text: the link on “Lower Ninth Ward Living Museum” (<a href="http://l9livingmuseum.org/wp/?page_id=131">http://l9livingmuseum.org/wp/?page_id=131</a>, now a domain for sale) was removed, and the sentence “If you are interested in gaining access to the database please email contact@livingmuseum.org.” was dropped (see 2).
</li><li>The About and Acknowledgements pages are now one gallery, About the Exhibition, with the headings “The collections”, “Lower Ninth Ward Oral History Archive” and “Acknowledgments”.
</li></ul>
<h3>To verify</h3>
<ul><li><strong>Betsy, 1965 / “Learning to evacuate after getting caught once in Hurricane Betsy”.</strong> The old page gives no interviewer, year or accession number for George Miller: its credit line is the photo credit. His photo is by David Breidenbach like the 2007 Pointe Coupee interviews (accession 4700.19xx), but that’s a guess. Credit is the name only until someone supplies the rest.
</li><li><strong>Audrey and the Marsh / “Red Cross relief work after Hurricane Audrey”.</strong> The photo (09_Ida_Turcan.jpg) is captioned “Ida Turcan” on the old page, but it shows a child playing on the checkered tile floor of a house that is gone, with debris and a surviving house behind. The caption was left off; the alt text describes the photo. Who and where is it, and who took it?
</li><li><strong>Katrina and Rita / “Bonding with fellow New Orleans residents at City Hall after Katrina”.</strong> The old page had no photo for Sharon Bart Normand, so this entry is audio and transcript only. Is there a photo of her?
</li><li><strong>The Old Storms / “A woman and her three children perish in the 1926 storm”.</strong> Caption kept as written: “Flood, one year later, in rural Louisiana.” The photo is cropped from a page headed “The Flood of 1927 … A graveyard of farm homes” (the uncropped 8_Foret.jpg), so it shows the 1927 flood, not the 1926 storm. Is the caption clear enough, and what is the source to credit?
</li><li><strong>Name spellings (all three left as chosen in 1).</strong> “Russell” vs “Russel” Crosby; “Kathy” vs “Cathy” Chauvin (the audio file name also says Cathy); “Matthew” vs “Mathew” Thiel. Also: Gwendolyn Ridgley (the audio file says “Gwen Ridgely”), Barbara Terance (the audio file says “Terrance”), Clovis Rigaud (the audio file says “Rigaurd”), and Evelyn Thiel Boreros, labeled “Evelyn Anna Thiel Boreros” in her transcript. These were left as the page text has them.
</li><li><strong>Living with the Coast / “The importance of coastal restoration for preserving Louisiana’s culture”.</strong> “There’s a long list of things we could use if we weren’t restored” was changed to “lose”, since “use” doesn’t make sense. Please check against the recording (42_kerry_st_pe).
</li><li><strong>Living with the Coast / “Terrebonne Parish drainage issues during storms over the last 150 years”.</strong> Credited to “Clifford Smith and Skippy”, but only Clifford Smith speaks. Who is Skippy (full name)? The transcript also keeps a profanity (“what the shit you’re doing to me”); confirm it stays.
</li><li><strong>Living with the Coast / “Small storms that hit Grand Isle seem to intensify”.</strong> Credited to “Roger and Noel Camardelle”, but only Noel Camardelle speaks in the clip.
</li><li><strong>Betsy, 1965 / “Hurricanes in the 1960s; experiencing the eye wall in Betsy”.</strong> Kept as written: “Beulah [1967] and Hilda happened pre-1965.” Hilda was 1964 but Beulah was 1967, so the bracket contradicts the sentence.
</li><li><strong>Katrina and Rita / “Father’s experience in storms gave her strength to survive Katrina”.</strong> Kept as said: “My father lived through the 1900s storm in Galveston” (the Galveston hurricane was 1900).
</li><li><strong>Katrina and Rita / “Returning to New Orleans after Katrina…”.</strong> Kept as transcribed: “we’re not just going to scuff them off” (perhaps “scoff” or “shuffle”).
</li><li><strong>Unresolved brackets in transcripts.</strong> Left as transcribed: “So then the [?] start getting worse” (Dolores Parker); “Francis [?], look at the water” (Lucy Thomas); “what they called a [?] storm” and “come along [at their time?]” (Matthew Thiel); “a house on [Renee?] Street” (Evelyn Thiel Boreros).
</li><li><strong>Credit lines.</strong> Interviewers, years and accession numbers are copied from the old pages and weren’t checked against the catalog. Two old lines had small format slips (no comma before the year for David Richard and Kent Ledoux; a comma instead of a semicolon for Angel Aucoin); the new format is the same for every clip.
</li><li><strong>Audio choice.</strong> Russell Crosby and Robert Gerdes each also have a noise-reduced copy (“_NR”) in the old audio folder that the old pages didn’t use; the new page uses the same files the old pages played.
</li><li><strong>Videos.</strong> Three clips were videos (Yancy Welch; Kerry St. Pé; Buddy Daisy and Earl Melancon). The new page plays their sound only, with a still from each video as the photo. The Lower Ninth Ward clip video (YouTube D17jageqC1k, featuring Karen Frank, Ward “Mack” McClendon, Deborah Harris, Percy Robinson and Elijah Johnson) is now private on YouTube, so it isn’t embedded. Is there a public copy?
</li><li><strong>About the Exhibition.</strong> The Lower Ninth Ward Living Museum’s web address and contact email no longer work. Is there a current address or contact to add?
</li><li><strong>About the Exhibition.</strong> The About text still speaks of the physical 2015 listening station (“Visitors are encouraged to listen to the excerpts and to turn up the volume—make some noise! We also encourage you to speak with staff…”). Kept as written; it may want rewording for the web.
</li></ul>
<h3>New wording to check</h3>
<h4>Page</h4>
<ul><li>Subtitle: “Two centuries of Louisiana hurricanes in oral history, for the tenth anniversary of Katrina and Rita”
</li><li>Credit: “T. Harry Williams Center for Oral History, LSU Libraries Special Collections. Curated by Jennifer A. Cramer and Erin Hess.”
</li><li>Year: 2015. Cover image: “Hurricane Betsy flooding as seen from Air Force One, 1965. LBJ Library photo by Yoichi R. Okamoto.” (12_thomas.jpg). The intro is the old About page’s first two paragraphs, unchanged.
</li></ul>
<h4>Gallery titles and lead captions</h4>
<p>Each gallery’s lead image reuses one of its clip photos with that photo’s caption.</p>
<ul><li><strong>The Old Storms, 1893–1926</strong> (3 clips). Lead: 08_foret.jpg, “Flood, one year later, in rural Louisiana.”
</li><li><strong>Audrey and the Marsh, 1950s</strong> (5 clips). Lead: 5_erosion.jpg, “Marsh die-back observed in Terrebonne Basin, Louisiana. USGS.”
</li><li><strong>Betsy, 1965</strong> (12 clips). Lead: 1_doucet.jpg, “Archbishop Philip Hannan after Hurricane Betsy hit Louisiana. CNS photo by Frank J. Methe, Clarion Herald, 1965.”
</li><li><strong>Living with the Coast</strong> (9 clips). Lead: 3_camardelle.jpg, “Grand Isle after Katrina, 2005. NOAA.”
</li><li><strong>Katrina and Rita, 2005</strong> (12 clips). Lead: 39_cusher.jpg, “Lower Ninth Ward, October 1, 2005. FEMA.”
</li><li><strong>About the Exhibition</strong> (text only)
</li></ul>
<h4>Reworded entry titles</h4>
<ul><li>“1926 hurricane and the demise of one family's shrimping platforms” → “The 1926 hurricane and the end of one family’s shrimping platforms”
</li><li>“Remembers her Red Cross relief effort experiences after Hurricane Audrey” → “Red Cross relief work after Hurricane Audrey”
</li><li>“Grand Isle Mayor on convincing people to evacuate for a hurricane” → “Grand Isle’s mayor on convincing people to evacuate for a hurricane”
</li><li>“Evacuation of Riverbend Nursing Home in Kentwood, LA, during Katrina” → “Evacuating Riverbend Nursing Home to Kentwood, Louisiana, during Katrina”
</li><li>“Hospital's preparedness for Hurricane Katrina” → “A hospital’s preparedness for Hurricane Katrina”
</li><li>Articles added at the start: “The impact of…”, “The danger of…”, “The importance of…”, “The emotional trauma of…”, “The loss of livestock…”, “An improved oyster harvest…”.
</li></ul>
<h4>New captions</h4>
<ul><li>Stills from the three videos: “Yancy Welch, 2012. From the video interview.”; “Kerry St. Pé, 2012. From the video interview.”; “Buddy Daisy and Earl Melancon, 2012. From the video interview.”
</li></ul>
<h4>Credit lines</h4>
<p>Every clip’s credit was reformatted from “Name by Interviewer, year; accession” to “Name, interviewed by Interviewer, year (accession)”:</p>
<ul><li>Rigaud’s grandmother survived the storm of 1893, but lost her parents: Clovis Rigaud, interviewed by Earl Robicheaux, 2009 (4700.2499)
</li><li>A woman and her three children perish in the 1926 storm: Houston Foret, interviewed by Don Davis and Carl Brasseaux, 2009 (4700.2072)
</li><li>The 1926 hurricane and the end of one family’s shrimping platforms: Louis Blum, interviewed by Don Davis and Carl Brasseaux, 2009 (4700.2067)
</li><li>Hurricanes Flossy and Betsy; riding out storms in the Coast Guard station: Russell Crosby, interviewed by Earl Robicheaux, 2009 (4700.2025)
</li><li>The impact of Hurricane Audrey on marsh grass and erosion: Allen Ensminger, interviewed by Don Davis and Carl Brasseaux, 2009 (4700.2036)
</li><li>Drought, fire, and storms in the marshlands around Grand Chenier: David Richard and Kent Ledoux, interviewed by Don Davis and Carl Brasseaux, 2009 (4700.2070)
</li><li>Red Cross relief work after Hurricane Audrey: Ida Turcan, interviewed by Adele Foster, 1997 (4700.0829)
</li><li>Surviving storms by seeking shelter in resilient oak trees: Yancy Welch, interviewed by Susan Testroet-Bergeron, 2012 (4700.2568)
</li><li>The seriousness of Betsy, as men prayed: John Doucet, interviewed by Ryan Branton, 2010 (4700.2099)
</li><li>Grand Isle rebuilds after Hurricanes Betsy and Katrina: David Camardelle, interviewed by Earl Robicheaux, 2009 (4700.2034)
</li><li>Long-term effects of Betsy on the Lower Ninth Ward: Ida Belle Joshua, interviewed by Nilima Mwendo, 2003 (4700.1684)
</li><li>Surviving in the floodwaters of Hurricane Betsy: Dolores Parker, interviewed by Nilima Mwendo, 2003 (4700.1688)
</li><li>Evacuating in Betsy to the New Orleans Municipal Auditorium: Lucy Thomas, interviewed by Nilima Mwendo, 2003 (4700.1687)
</li><li>Learning to evacuate after getting caught once in Hurricane Betsy: George Miller
</li><li>Hurricanes in the 1960s; experiencing the eye wall in Betsy: Robert Gerdes, interviewed by Caroline Gerdes, 2012 (4700.2441)
</li><li>Surviving Hurricane Betsy; the impacts of Camille and Katrina: Jane Sedgebeer, interviewed by Caroline Gerdes, 2012 (4700.2452)
</li><li>Storms and political representation in Plaquemines Parish: Rose Jackson, interviewed by Jennifer Abraham, 2000 (4700.1359)
</li><li>Riding out Hurricane Betsy in Westwego: David Camardelle, interviewed by Earl Robicheaux, 2009 (4700.2034)
</li><li>National Guard relief efforts after Betsy; President Johnson helping evacuees: Erbon Wise, interviewed by Tara Zachary, 1995 (4700.0636)
</li><li>Grandfather rode out Betsy with his dog in the car: Matthew Thiel and Evelyn Thiel Boreros, interviewed by Caroline Gerdes, 2012 (4700.2457)
</li><li>Terrebonne Parish drainage issues during storms over the last 150 years: Clifford Smith and Skippy, interviewed by Don Davis and Carl Brasseaux, 2009 (4700.2081)
</li><li>After the storm, moving away or elevating camps in Cocodrie: Houston Foret, interviewed by Don Davis and Carl Brasseaux, 2009 (4700.2072)
</li><li>The danger of slow-moving storms to barrier islands: Sylvia Landry, interviewed by Earl Robicheaux, 2009 (4700.2029)
</li><li>Small storms that hit Grand Isle seem to intensify: Roger and Noel Camardelle, interviewed by Earl Robicheaux, 2009 (4700.2026)
</li><li>Constructing homes to handle flooding: Don Gagnon, interviewed by Caroline Gerdes, 2012 (4700.2440)
</li><li>The importance of coastal restoration for preserving Louisiana’s culture: Kerry St. Pé, interviewed by Susan Testroet-Bergeron, 2012 (4700.2567)
</li><li>Grand Isle’s mayor on convincing people to evacuate for a hurricane: David Camardelle, interviewed by Earl Robicheaux, 2009 (4700.2034)
</li><li>An improved oyster harvest after Hurricane Andrew: Buddy Daisy and Earl Melancon, interviewed by Susan Testroet-Bergeron, 2012 (4700.2561)
</li><li>Coastal restoration projects; the transformation of the coastline by Katrina: Donald Clement, interviewed by Mark Gibson, 2013 (4700.2355)
</li><li>Escaping through the roof in Katrina and being rescued by a Good Samaritan: Gwendolyn Ridgley, interviewed by Myrna Matherne, 2007 (4700.1955)
</li><li>Losing everything after Katrina in Lakeview: Patsy Ashton, interviewed by Caroline Gerdes, 2012 (4700.2427)
</li><li>The emotional trauma of being displaced: Malane Collins, interviewed by Betty Wooddy, 2007 (4700.1951)
</li><li>Returning to New Orleans after Katrina and performing his duties as coroner: Dr. Frank Minyard, interviewed by Angie Juban, 2007 (4700.1956)
</li><li>Evacuating with her elderly mother in the days after Hurricane Katrina: Barbara Terance, interviewed by Tatiana Clay, 2007 (4700.1962)
</li><li>Evacuating Riverbend Nursing Home to Kentwood, Louisiana, during Katrina: Daryl and Kathy Chauvin, interviewed by Blanche Jewell, 2007 (4700.1950)
</li><li>Bonding with fellow New Orleans residents at City Hall after Katrina: Sharon Bart Normand, interviewed by Tatiana Clay, 2006 (4700.1964)
</li><li>After being rescued with her dog, getting a ride to safety from kind strangers: Victoria Webb, interviewed by Jennifer Abraham, 2007 (4700.1957)
</li><li>A hospital’s preparedness for Hurricane Katrina: Angel Aucoin, interviewed by Mary Price, 2007 (4700.1949)
</li><li>Human kindness kept him from giving up after losing so much in Katrina: Victor Cusher, interviewed by Blanche Jewell, 2007 (4700.1952)
</li><li>Father’s experience in storms gave her strength to survive Katrina: Joan Bostick, interviewed by Caroline Gerdes, 2012 (4700.2431)
</li><li>The loss of livestock after Hurricanes Rita and Ike: David Richard and Kent Ledoux, interviewed by Don Davis and Carl Brasseaux, 2009 (4700.2070)
</li></ul>
<h4>Alt text (new for every image)</h4>
<ul><li>Rigaud’s grandmother survived the storm of 1893, but lost her parents (35_rigaud.jpg): Detail of an 1853 survey chart of the Louisiana coast, with Barataria Bay and Grand Isle picked out in gold on black.
</li><li>A woman and her three children perish in the 1926 storm (08_foret.jpg): Wooden houses standing in floodwater up to their windows, with trees behind.
</li><li>The 1926 hurricane and the end of one family’s shrimping platforms (25_blum.jpg): A 1908 nautical chart of the marsh and bays between Barataria Bay and Terrebonne.
</li><li>Hurricanes Flossy and Betsy; riding out storms in the Coast Guard station (26_crosby.jpg): Aerial photograph of the Grand Isle Coast Guard station, a large two-story building with a lookout tower, among houses and trees.
</li><li>The impact of Hurricane Audrey on marsh grass and erosion (5_erosion.jpg): Aerial view from a small plane over broken marsh, with patches of green grass separated by open water.
</li><li>Drought, fire, and storms in the marshlands around Grand Chenier (6_richard_ledoux.jpg): A 1928 nautical chart of the coast at Grand Chenier and the mouth of the Mermentau River.
</li><li>Red Cross relief work after Hurricane Audrey (09_ida_turcan.jpg): Black-and-white photograph of a child playing on the checkered tile floor of a house that is gone, with debris and a surviving house behind.
</li><li>Surviving storms by seeking shelter in resilient oak trees (40_yancy_welch_still.jpg): Yancy Welch, in a cap and work shirt, gestures as he talks, seated on a leather couch in a kitchen.
</li><li>The seriousness of Betsy, as men prayed (1_doucet.jpg): Archbishop Philip Hannan and two other clergymen look out over a collapsed, flooded house from a boat.
</li><li>Grand Isle rebuilds after Hurricanes Betsy and Katrina (2_camardelle.jpg): A sepia photograph of a damaged house on bare, sandy ground littered with debris and leaning poles.
</li><li>Long-term effects of Betsy on the Lower Ninth Ward (10_joshua.jpg): Newspaper photograph of a National Guard truck driving through deep floodwater past a small boat.
</li><li>Surviving in the floodwaters of Hurricane Betsy (11_parker.jpg): A flooded New Orleans street, with cars under water to their roofs and a helicopter overhead.
</li><li>Evacuating in Betsy to the New Orleans Municipal Auditorium (12_thomas.jpg): Aerial view of flooded city blocks seen from an airplane, with the plane’s wing in the foreground.
</li><li>Learning to evacuate after getting caught once in Hurricane Betsy (19_miller.jpg): George Miller, smiling, in a patterned shirt, standing in a living room.
</li><li>Hurricanes in the 1960s; experiencing the eye wall in Betsy (31_gerdes.jpg): Robert Gerdes, in glasses and a plaid shirt, seated outdoors on a porch among trees.
</li><li>Surviving Hurricane Betsy; the impacts of Camille and Katrina (32_sedgebeer.jpg): Jane Sedgebeer, arms folded, seated at a kitchen table.
</li><li>Storms and political representation in Plaquemines Parish (jackson.jpg): Black-and-white portrait of Rose Jackson, smiling, in a dark jacket.
</li><li>Riding out Hurricane Betsy in Westwego (4_camardelle.jpg): Satellite photograph of Hurricane Betsy’s spiral of clouds over the curve of the Earth.
</li><li>National Guard relief efforts after Betsy; President Johnson helping evacuees (38_wise.jpg): Newspaper photograph of President Lyndon Johnson leaning close to talk with an older man.
</li><li>Grandfather rode out Betsy with his dog in the car (30_thiel.jpg): Matthew Thiel, an older man in glasses and a blue shirt, seated with his arms folded.
</li><li>Terrebonne Parish drainage issues during storms over the last 150 years (24_smith_skippy.jpg): Map of the Mississippi River and its tributaries, from Minnesota to the Gulf of Mexico.
</li><li>After the storm, moving away or elevating camps in Cocodrie (23_foret.jpg): Camps raised on tall pilings over gray water in Cocodrie.
</li><li>The danger of slow-moving storms to barrier islands (28_landry.jpg): Map of the Louisiana coast crossed by colored lines tracing hurricane paths, with a legend by category.
</li><li>Small storms that hit Grand Isle seem to intensify (27_camardelle.jpg): A 1939 nautical chart of Grand Isle and Caminada Bay.
</li><li>Constructing homes to handle flooding (34_gagnon.jpg): Don Gagnon, seated on a striped couch, holding a framed old photograph.
</li><li>The importance of coastal restoration for preserving Louisiana’s culture (42_kerry_st_pe_still.jpg): Kerry St. Pé, seated on a wooden dock with a bayou and cypress trees behind him.
</li><li>Grand Isle’s mayor on convincing people to evacuate for a hurricane (3_camardelle.jpg): Aerial view of Grand Isle after Katrina, with wrecked buildings, boats and debris along the water.
</li><li>An improved oyster harvest after Hurricane Andrew (41_buddy_daisy_and_earl_melancon_still.jpg): Buddy Daisy and Earl Melancon seated in leather office chairs by a desk, in a wood-paneled office.
</li><li>Coastal restoration projects; the transformation of the coastline by Katrina (29_clement.jpg): Two USGS satellite images side by side comparing the marsh at South Pass before and after Hurricanes Katrina and Rita.
</li><li>Escaping through the roof in Katrina and being rescued by a Good Samaritan (13_ridgley.jpg): Gwendolyn Ridgley, in a white blouse, seated in a dining room beside a china cabinet.
</li><li>Losing everything after Katrina in Lakeview (36_ashton.jpg): Patsy Ashton, smiling, seated by a backyard pool, holding an old photograph of a young woman in a gown.
</li><li>The emotional trauma of being displaced (39_collins.jpg): Malane Collins, in a white jacket, standing beside a large colorful painting.
</li><li>Returning to New Orleans after Katrina and performing his duties as coroner (14_minyard.jpg): Dr. Frank Minyard seated in front of a round painting of himself playing the trumpet.
</li><li>Evacuating with her elderly mother in the days after Hurricane Katrina (17_terance.jpg): Map of the United States showing how many Katrina evacuees each state took in, from 239,000 in Texas to a few hundred in others.
</li><li>Evacuating Riverbend Nursing Home to Kentwood, Louisiana, during Katrina (18_chauvin.jpg): Daryl and Kathy Chauvin seated at their dining table, with a vase of flowers and a brick fireplace behind them.
</li><li>After being rescued with her dog, getting a ride to safety from kind strangers (21_webb.jpg): Victoria Webb, in a white blouse, seated in an armchair beside a table of framed photographs.
</li><li>A hospital’s preparedness for Hurricane Katrina (22_aucoin.jpg): Angel Aucoin, leaning on a carved chair in a room full of antique furniture and lamps.
</li><li>Human kindness kept him from giving up after losing so much in Katrina (victor.jpg): Victor Cusher, smiling, gesturing with open hands on a porch.
</li><li>Human kindness kept him from giving up after losing so much in Katrina (39_cusher.jpg): A house in the Lower Ninth Ward crushed and torn apart, with a bicycle beside the front steps.
</li><li>Father’s experience in storms gave her strength to survive Katrina (33_bostick.jpg): Joan Bostick, in a striped top, standing in a hallway holding a cup.
</li><li>The loss of livestock after Hurricanes Rita and Ike (7_richard_ledoux.jpg): Aerial view of cattle crowded onto a narrow strip of high ground surrounded by floodwater.
</li></ul>
<h4>About the Exhibition</h4>
<ul><li>New headings: “The collections”, “Lower Ninth Ward Oral History Archive”, “Acknowledgments”.
</li><li>Link text: “LSU Libraries Exhibitions”, “Center staff”, “Williams Center collections”.
</li><li>Lower Ninth Ward: “Included in the video are Karen Frank, Ward "Mack" McClendon, Deborah Harris, Percy Robinson, and Elijah Johnson.” → “The listening station’s video of clips from the archive featured Karen Frank, Ward “Mack” McClendon, Deborah Harris, Percy Robinson, and Elijah Johnson.” (the video itself is no longer shown).
</li></ul>
HTML),
]);

$section('AVES', [
  $text(NULL, <<<'HTML'
<p>Migrated from the Wayback Machine's copy of exhibitions.blogs.lib.lsu.edu/?p=5206 and its 53 "Read more" pop-ups. Live page: /v2/special/exhibitions/aves. Fixed in <code>scripts/data/aves.json</code> and on the live page by <code>scripts/palefire_exhibit_aves_typos.php</code>.</p>
<h3>Corrected</h3>
<p>Names (entry titles, so also the contents and "In this gallery"):</p>
<ul><li>"Alcide d’Orbigny(1802-1857)" → "Alcide d’Orbigny (1802-1857)"
</li><li>"John Gould 1804-1881)" → "John Gould (1804-1881)"
</li><li>"Robert Schomburgk 1804-1865)" → "Robert Schomburgk (1804-1865)"
</li><li>"William Phelps, Jr.(1902-1988)" → "William Phelps, Jr. (1902-1988)"
</li><li>"Louis Jean Pierre Vieillot (1610-1644)" → "Louis Jean Pierre Vieillot (1748-1831)"
</li><li>"Francis L. De LaPorte" → "Francis L. de Laporte"
</li></ul>
<p>Text:</p>
<ul><li>"tyrant- flycatchers" → "tyrant-flycatchers"; "fast- growing" → "fast-growing"; "ninety- three" → "ninety-three"
</li><li>"Markgraf’s <em>Historia naturalis Brasiliae,</em> (Leiden" → "Markgraf’s <em>Historia naturalis Brasiliae</em> (Leiden"
</li><li>"hummingbirds;and", "rooms,artificial", "mammals,and", "setting,and" → a space added after the punctuation
</li><li>"*…habitudes.*Rio" → a space before "Rio"
</li><li>"to the base camp," (end of paragraph) → "to the base camp."
</li><li>"passes rapidly from from blossom" → "passes rapidly from blossom"
</li><li>Dash spacing: "jail– “a putrid", "of the world –that", "and others– who", "ornithologists– Ridgway", "perfect mentor– I" → a space on both sides of each dash
</li><li>"(Weimer, 1825-33)" and "(Weimer, 1822-1831)" → "Weimar"
</li><li>"Charles Chubbs’s" → "Charles Chubb’s"
</li></ul>
<h3>New wording to check</h3>
<ul><li>17 exhibition images had no 4K file and were left for editors to add. Unused 4K plates and photographs became the galleries' decorative lead images.
</li></ul>
HTML),
]);

$section('Occult Science and Philosophy in the Renaissance', [
  $text(NULL, <<<'HTML'
<p>Migrated from the Wayback Machine's copy of exhibitions.blogs.lib.lsu.edu/?p=1257&amp;all=1 (the 2011 online exhibition). Live page: /v2/special/exhibitions/occult.</p>
<h3>Corrected</h3>
<ul><li>"Amboise Paré" → "Ambroise Paré"
</li><li>Missing spaces and line breaks in the catalog records and the Index
</li><li>Felgenhauer filed under F in the Index
</li><li>"Middleton" → "Main Library" (as on AVES)
</li></ul>
<h3>To verify</h3>
<ul><li>Defoe: the catalog record says Oxford: D. A. Talboys, 1840, but the scan (and its folder, BF 1601 D3 1728) is the London, 1728 title page.
</li></ul>
<h3>New wording to check</h3>
<ul><li>Image captions, gallery lead captions, alt text, the credit line and the physical exhibition note.
</li></ul>
HTML),
]);

$section('John James Audubon in Louisiana', [
  $text(NULL, <<<'HTML'
<p>Migrated from the Wayback Machine (lib.lsu.edu/exhibitions/john-james-audubon) and, for the 78 bird and manuscript pages it never captured, the students' copy (/audubon, nodes 437-520). Live page: /v2/special/exhibitions/audubon.</p>
<h3>Corrected</h3>
<ul><li>Spacing and punctuation: spaces around "[now" and "vol. 1"; missing periods and commas; " : " and " ;" in the readings; straight quotes and spaced hyphens set as curly quotes and dashes.
</li><li>"FIeld" → "Field"; "Sociey" → "Society"
</li><li>Headings use the 2021 menu names. The students' titles had "Boneparte", "Prarie" and "Flamigo".
</li><li>Agreed with the editors: six Latin names (Psittacus, Sylvia childrenii, Sylvia roscoe, Haematopus palliatus, Ictinia mississippiensis, Picus querulus), "Susanne Low", and Heitman's pages 44-53.
</li></ul>
<h3>To verify</h3>
<ul><li>Three octavo citations give "Philadelphia: J. B. Chevalier, 1856" (Pine Creeping Wood-warbler, Wood Duck, Yellow-throated Vireo). The 1856 edition was not Chevalier's.
</li></ul>
<h3>New wording to check</h3>
<ul><li>Gallery lead captions, alt text, the credit line and the physical exhibition note.
</li></ul>
HTML),
]);

$section('LSU Union Art Gallery: Building Community Through Art, 1964–2016', [
  $text(NULL, <<<'HTML'
<p>Migrated from the six-page showcase at /lsu-unionartgallery (on libstage only).</p>
<h3>Corrected</h3>
<ul><li>Text encoding: apostrophes and quotes saved wrongly up to four times over were decoded exactly to ’ “ ”.
</li><li>"Clyde Connel" → "Clyde Connell" (as in its own captions)
</li><li>"Judi Stalh" → "Judi Stahl" (as elsewhere)
</li><li>"gallery talk aby" → "gallery talk by"
</li><li>The missing closing quotation mark on "Humor in the Jugular Vein"
</li><li>Trailing periods on some captions
</li></ul>
<h3>To verify (left as written)</h3>
<ul><li>One artist appears as "Claude Roberts Trevithick", "Claudia Roberts" and "Claudia Robert Trevnthick".
</li><li>"Pamela Micheal"
</li><li>The "Transparent Motifs" heading sits over a "Transparent Motives" flyer.
</li></ul>
<h3>New wording to check</h3>
<ul><li>The credit line, and the cover's caption and alt text (Janice Sachse, <em>Noon in My Garden</em>). The other gallery leads use the old covers' alt text as their caption.
</li></ul>
HTML),
]);

// End of sections. ---------------------------------------------------------

$block_count = 0;
foreach ($sections as $item) {
  $block_count += count($item['blocks']);
}
$fingerprint = substr(sha1(json_encode([$intro, $sections], JSON_THROW_ON_ERROR)), 0, 12);
$log_prefix = 'palefire_guide_exhibition_migration.php build';
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
  'path' => ['alias' => '/' . ltrim($args['path'] ?? '/v2/guides/exhibition-migration-review', '/')],
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
