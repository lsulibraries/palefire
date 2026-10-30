<?php

/**
 * @file
 * Program pages for Special Collections: Teaching with Special Collections
 * (class visits), Research Grants, the Faculty Fellowship and the Summer
 * Project, as unpublished Modular pages in exhibition mode. Run palefire_exhibit_setup.php first (it adds the title
 * page's label, key facts and main button these use).
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_special_programs.php -- prefix=/v2/special/programs
 *   drush cr
 *
 * Optional arguments after "--":
 *   format=<text format>  Text format for the text (default: the WYSIWYG
 *                         format).
 *   prefix=<alias>        URL alias prefix: the pages get <prefix>/teaching,
 *                         <prefix>/research-grants, <prefix>/faculty-
 *                         fellowship and <prefix>/summer-project. None: no
 *                         aliases (and Teaching's link to the Fellowship
 *                         is plain text).
 *
 * Exhibitions rather than guides: these pages invite people in, so each
 * opens on a title page (label, title, subtitle, intro, key facts and an
 * Apply button) and shows the people who have taken part, one entry each,
 * before the practical details. Galleries: about the program, its people
 * (Research Grants and the Fellowship), how to apply, and questions.
 * Teaching shows what a class visit can be instead: its sample activities
 * as entries, past classes in an accordion, and the request form.
 *
 * Drafted from LSU's own pages (October 2026), reworded:
 * - Teaching: the "Teaching with Special Collections" research guide
 *   (guides.lib.lsu.edu/c.php?g=1544354): Home, Sample Activities and
 *   Example Classes. Item names in the example classes are as the guide
 *   gives them, with four spellings corrected: Bartram, Archimedes,
 *   Labyrinte de Versailles and Ovid's Metamorphoses.
 * - Research Grants: "LSU Libraries Awards 2025–26 Special Collections
 *   Research Grants" (lib.lsu.edu/250918/scresearchgrants).
 * - Faculty Fellowship: "LSU Libraries' Archive-Centered Teaching Initiative
 *   Empowers Educators" (2023, lib.lsu.edu/node/6142) and "LSU Libraries
 *   Special Collections 2024 Faculty Fellowship Spotlight"
 *   (lib.lsu.edu/250328/facultyfellows).
 * - Summer Project: the Office of Undergraduate Research's page
 *   (lsu.edu/our/funding/special_collections.php).
 * They report past cycles, so current amounts, dates, eligibility and
 * contacts are "[Editor: …]" notes, on the title page's key facts too, for
 * Special Collections to confirm. Past figures are given as past.
 *
 * No title images: the pages print without one until an editor adds an
 * Exhibition title image (Media). Each needs one before it is published:
 * the fellows at Hill, or an item a recipient worked with. The Apply
 * buttons go to each page's own "How to apply" gallery until an editor
 * points them at the application form; the Summer Project's goes to the
 * Office of Undergraduate Research, and Teaching's to the class request
 * form.
 *
 * Creates up to four Modular pages, UNPUBLISHED. A page whose title already
 * exists is skipped (delete an earlier guide-mode draft to rebuild it).
 */

declare(strict_types=1);

use Drupal\Component\Utility\Html;
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

foreach (['exhibit_page', 'exhibit_entry', 'guide_text'] as $id) {
  if (!\Drupal::entityTypeManager()->getStorage('paragraphs_type')->load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_exhibit_setup.php first.");
  }
}

// Text uses the WYSIWYG format (machine name or label "WYSIWYG"), never
// full_html.
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
$skipped = [];

// Block helpers (same shapes as palefire_exhibit_redesign.php). ------------

$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];
$keep = fn (array $values) => array_filter($values, fn ($v) => $v !== NULL);

$text = fn (?string $heading, string $body, string $style = 'default') => Paragraph::create($keep([
  'type' => 'guide_text',
  'field_block_title' => $heading,
  'field_block_body' => $html($body),
  'field_text_style' => $style,
]));

// A person or an activity: its name (a sub-link in the TOC), then a line
// on who or what it is for and a paragraph on it.
$entry = fn (string $name, string $bio) => Paragraph::create([
  'type' => 'exhibit_entry',
  'field_block_title' => $name,
  'field_section_anchor' => Html::getClass($name),
  'field_entry_bio' => $html($bio),
]);

$has_display = (bool) FieldConfig::loadByName('paragraph', 'exhibit_page', 'field_exhibit_display');
$gallery = fn (string $title, string $anchor, array $blocks) => Paragraph::create($keep([
  'type' => 'exhibit_page',
  'field_section_title' => $title,
  'field_section_anchor' => $anchor,
  'field_exhibit_display' => $has_display ? 'story' : NULL,
  'field_guide_blocks' => array_values(array_filter($blocks)),
]));

// Collapsed lists: [title, body HTML] each.
$accordion = fn (array $items) => Paragraph::create([
  'type' => 'guide_accordion',
  'field_accordion_items' => array_map(fn ($item) => Paragraph::create([
    'type' => 'accordion_item',
    'field_item_title' => $item[0],
    'field_block_body' => $html($item[1]),
  ]), $items),
]);
$items = fn (array $names) => '<ul><li>' . implode('</li><li>', $names) . '</li></ul>';

$ask = '<p><a href="https://askus.lib.lsu.edu#special">Ask Special Collections</a></p>';
$prefix = isset($args['prefix']) ? '/' . trim($args['prefix'], '/') : NULL;
$fellowship = $prefix
  ? '<a href="' . $prefix . '/faculty-fellowship">Special Collections Faculty Fellowship</a>'
  : 'Special Collections Faculty Fellowship';

// The pages. ---------------------------------------------------------------
// Galleries are built in closures, so a page that already exists creates no
// paragraphs.

$pages = [
  'teaching' => [
    'title' => 'Teaching with Special Collections',
    'kicker' => 'Class visits',
    'subtitle' => 'Put the past in your students’ hands',
    'intro' => '<p>Bring your class to Hill Memorial Library. A librarian will build a session around your course, from medieval manuscripts to Cold War propaganda, so your students can handle, question and write about the real thing.</p>',
    'keyfacts' => [
      'Classes: 100+ a year',
      'Students: 2,500+ a year',
      'For: Instructors in any field',
    ],
    'action' => ['uri' => 'https://lsu.libwizard.com/f/class_request', 'title' => 'Request a class'],
    'prompt' => 'See what a class visit can look like, or go straight to the request form.',
    'source' => 'the Teaching with Special Collections research guide',
    'galleries' => fn () => [
      $gallery('Classes at Hill', 'classes-at-hill', [
        $text(NULL, <<<'HTML'
<p>Special Collections hosts more than a hundred class visits a year, for over 2,500 students from across the university. A visit lets students:</p>
<ul>
<li>learn hands-on, with the material in front of them;</li>
<li>meet objects from other centuries and cultures directly;</li>
<li>find ideas for creative work;</li>
<li>learn how archives work and how to research with primary sources;</li>
<li>sharpen their thinking, writing and speaking by researching and presenting historical objects;</li>
<li>build investigative skills they will use in later study and work.</li>
</ul>
HTML),
        $text('Built around your course', <<<'HTML'
<p>Every visit is shaped to the class. Some instructors lecture from the materials; others run hands-on exercises such as document analysis worksheets, and some classes end in student presentations or performances.</p>
<p>Library staff bring expertise in primary source literacy, the history of books and printing, literary history, and rare books as material culture. Hill holds something for nearly every subject, from science and engineering to art and history.</p>
HTML),
      ]),
      $gallery('Sample activities', 'sample-activities', [
        $text(NULL, '<p>Two activities we often run. Each can be adapted to your course.</p>'),
        $entry('Object Identification', <<<'HTML'
<p><strong>For classes new to primary sources and special collections</strong></p>
<p>Students work in pairs on an object they have never seen, with no context: a sorority dance card, a cryptic telegram, a true crime scrapbook, a brochure for canning frogs. A worksheet leads them from the basics (what kind of source it is, who made it, when and why) to the research questions it could answer.</p>
<p><em>Builds:</em> archival and visual literacy; material and textual analysis.</p>
HTML),
        $entry('Reading Maps', <<<'HTML'
<p><strong>For classes with a theme that want to work with visual sources</strong></p>
<p>After a short introduction to reading maps, from cartouches and scales to rhumb lines and depth soundings, small groups work through a worksheet on a map’s geography, detail, accuracy and imagery. Then comes the hard question: who was it for, and how did they use it? Grand sea charts were often bought by landlocked elites, and nineteenth-century railroad maps did as much to sell western land as to guide travelers.</p>
<p>Hill holds nearly 2,000 wall maps, hundreds of manuscript maps, architectural drawings and historical atlases, so the activity can follow your topic: medieval and early modern mapping, exploration, westward expansion and land surveys, military plans, urban planning and Sanborn fire insurance maps, Louisiana’s waterways, floods and coast, plantations, timber and oil, tourism, even fictional worlds.</p>
<p><em>Builds:</em> visual literacy; visual and material analysis; historical context.</p>
HTML),
      ]),
      $gallery('Example classes', 'example-classes', [
        $text(NULL, '<p>The collections support classes in almost any field. A sampling of past classes, with some of the materials pulled for them:</p>'),
        $accordion([
          ['Medieval Literature', $items(['The Hours of Henry VIII', '<em>Sermones Meffreth alias Ortulus regine de tempore</em>', '<em>Apocalipsis</em>', 'Ovid’s <em>Metamorphoses</em> (1505)'])],
          ['Photography', $items(['Jane McCowan Photographs and Papers', 'Andrew D. Lytle Album Photograph Collection', '<em>The Burr McIntosh Monthly</em>', 'Ian Van Coller, <em>Winter Quarters…Cape Evans, Antarctica</em>'])],
          ['Food in History', $items(['An 1867 recipe book', 'William Turner’s <em>Herbal</em>', '<em>The Picayune Creole Cookbook</em>'])],
          ['Shakespeare', $items(['Shakespeare’s Second Folio (two copies)', 'Boydell’s illustrated Shakespeare', 'The Trevelyon Miscellany of 1608'])],
          ['Cold War Propaganda', $items(['<em>Stalin Hates Baton Rouge</em>', '<em>Facts to Fight Communism</em>', 'USSR Photo-70 Exhibition', 'John C. Chase cartoons'])],
          ['Sports Rhetoric', $items(['Simeon A. “Alex” Box Scrapbook', '1981 LSU Tiger NCAA Tournament Guide', 'LSU Athletics Policies and Procedures Manual', '<em>LSU Football Magazine</em>'])],
          ['Slavery in Baton Rouge', $items(['Turnbull-Allain Papers', 'Eliza L. Magruder Diary', 'William Harris poster', 'James Monette Daybook and Diary'])],
          ['Landscape Architecture', $items(['Walter Horn’s <em>The Plan of St. Gall</em>', 'Aesop, <em>Labyrinte de Versailles</em>', 'William Bartram’s <em>Travels</em>'])],
          ['Music Theory', $items(['Euclid’s <em>Les septieme</em>', 'The Eton Choirbook', 'Spanish music on vellum', 'Mozart’s Requiem'])],
          ['Disability Studies', $items(['Louisiana Leper Home records', 'Insane Asylum of the State of Louisiana', '<em>Cripple Punk Mag</em>', 'William Hay’s <em>Deformity: An Essay</em>'])],
          ['History of Mathematics', $items(['<em>Sphaera Mundi</em>', 'Euclid', 'Archimedes', 'Newton'])],
          ['Literary LSU', $items(['<em>Southern Review</em> records', 'A Robert Penn Warren letter', 'Robert Penn Warren, <em>All the King’s Men</em>', 'John Kennedy Toole, <em>A Confederacy of Dunces</em>'])],
          ['Victorian True Crime', $items(['<em>The Police Code for the British Empire</em>', 'Provosty Arthur Dayries Papers', 'Clarence John Laughlin scrapbooks', 'Mary Elizabeth Braddon, <em>Ralph the Bailiff</em>'])],
          ['Spanish Literature', $items(['<em>Cantigas de Santa Maria</em>', 'Cristóbal de las Casas', 'Bernardo Aldrete', 'Cervantes'])],
          ['British Literature', $items(['Martin Gregory’s Bible', '<em>Paradise Lost</em>', '<em>The Faerie Queene</em>', 'Chaucer'])],
          ['Horror', $items(['<em>Frankenstein</em>', 'Letters of Mary Shelley', '<em>The Vampyre</em>', '<em>Vampirella</em> annual'])],
        ]),
      ]),
      $gallery('Request a class', 'request-a-class', [
        $text(NULL, <<<'HTML'
<p>Tell us about your course, your students and what you hope they take away. A librarian will work with you to choose materials and plan the session.</p>
<p><a href="https://lsu.libwizard.com/f/class_request">Request a class</a></p>
<p>Class visits are led by John Miles, Curator of Books and Head of Instruction. [Editor: confirm, with an email address.]</p>
HTML),
        $text(NULL, '<p><strong>Plan ahead:</strong> [Editor: how far in advance to request a visit.]</p>', 'callout'),
        $text('Going further', '<p>Want to build the archive into a course over a whole year, with a cohort and a stipend? See the ' . $fellowship . '.</p>' . $ask),
      ]),
    ],
  ],
  'research-grants' => [
    'title' => 'Special Collections Research Grants',
    'kicker' => 'Research grant',
    'subtitle' => 'Time and travel to work with Hill’s collections',
    'intro' => '<p>Each year LSU Libraries funds scholars whose research depends on materials found only at Hill Memorial Library, paying for travel, lodging and other costs of a stay in Baton Rouge.</p>',
    'keyfacts' => [
      'Award: [Editor: amount]',
      'For: [Editor: who can apply]',
      'Deadline: [Editor: date]',
    ],
    'action' => ['uri' => 'internal:#how-to-apply', 'title' => 'How to apply'],
    'prompt' => 'Read about the grants and this year’s scholars, or go straight to how to apply.',
    'source' => 'the 2025–26 award announcement',
    'galleries' => fn () => [
      $gallery('The grants', 'the-grants', [
        $text(NULL, <<<'HTML'
<p>Research Grants bring scholars to Baton Rouge to work in person with collections held only at LSU: manuscripts and family papers, rare books, university records and oral histories. Recipients use the award for travel, lodging and the other essentials of a research stay at Hill Memorial Library.</p>
<p>The grants are awarded every year to researchers at any stage, from graduate students to senior faculty, whose projects need what Hill holds.</p>
HTML),
      ]),
      $gallery('2025–26 scholars', 'scholars', [
        $text(NULL, '<p>Four scholars were funded for 2025–26. Their projects range from francophone identity in the Gulf South to Black militia service, the Civil War home front, and the forests of the Jim Crow South.</p>'),
        $entry('Catherine La Prairie', <<<'HTML'
<p><strong>Master’s student, Global, International, and Comparative History, Georgetown University</strong></p>
<p>In <em>Peasants of the New World</em>, La Prairie studies how French and American writers portrayed Louisiana’s Acadians in the decades after the Louisiana Purchase, and how those portraits carried their own worries about national unity and imperial decline. At Hill she is reading manuscripts and personal papers to trace how the stories shaped ideas of governance and belonging on both sides of the Atlantic.</p>
HTML),
        $entry('Owen James Hyman', <<<'HTML'
<p><strong>Instructional associate professor of African American Studies, University of Mississippi; co-editor of the <em>Journal of Mississippi History</em></strong></p>
<p>Hyman’s monograph, <em>Cut Over Color Lines: Black Forests in the Jim Crow South</em>, follows the African Americans who watched the forests of the Lake Pontchartrain Basin and the Lower Pearl River Valley fall in the century after emancipation. Hill’s forest history collections, covering the region’s coast, its lumber firms and their workers, show how racial violence and exclusion changed with the land, and how Black communities organized along its waterways.</p>
HTML),
        $entry('A. J. Blaylock', <<<'HTML'
<p><strong>PhD candidate, University of North Carolina at Chapel Hill</strong></p>
<p>Blaylock’s dissertation, “The Sons of Chalmette: Black State Militiamen and the Battle for Citizenship in Nineteenth-Century Louisiana,” asks why Louisiana, alone among slave states, at times let Black men serve in its militia before the Civil War, and how that service became part of their fight for full citizenship across the century.</p>
HTML),
        $entry('John Sacher', <<<'HTML'
<p><strong>Professor of history, University of Central Florida</strong></p>
<p>Sacher is writing a new history of the Civil War in Louisiana, tentatively titled <em>Louisiana’s Civil War</em>, the first in more than sixty years. He is adding voices from the home front found in Special Collections: women, ordinary soldiers, and enslaved and freed African Americans.</p>
HTML),
        $text(NULL, '<p><a href="https://lib.lsu.edu/250918/scresearchgrants">Read the 2025–26 announcement</a></p>'),
      ]),
      $gallery('How to apply', 'how-to-apply', [
        $text('Who can apply', '<p>[Editor: confirm eligibility, e.g. graduate students, postdoctoral researchers, faculty and independent scholars, and whether applicants must live outside the Baton Rouge area.]</p>'),
        $text('The award', '<p>[Editor: the number and amount of grants for the next cycle, and what they may be spent on.]</p>'),
        $text('Your application', <<<'HTML'
<p>[Editor: what to send, e.g. a research plan naming the collections you will use, a CV and any letters, and where to send it.]</p>
<p>Before you apply, ask us about the collections you plan to use. We can help you find the right finding aids and plan the length of your stay.</p>
HTML),
        $text(NULL, '<p><strong>Deadline:</strong> [Editor: date]. <strong>Decisions:</strong> [Editor: date].</p>', 'callout'),
      ]),
      $gallery('Questions', 'questions', [
        $text(NULL, $ask),
      ]),
    ],
  ],
  'faculty-fellowship' => [
    'title' => 'Special Collections Faculty Fellowship',
    'kicker' => 'Faculty fellowship',
    'subtitle' => 'A year to build the archive into your teaching',
    'intro' => '<p>A year-long fellowship for LSU teaching faculty: training, a cohort of colleagues and a librarian at your side, to bring Special Collections into your courses.</p>',
    'keyfacts' => [
      'Stipend: [Editor: confirm; $2,000 in 2023]',
      'For: LSU teaching faculty, any rank',
      'Applications: [Editor: dates]',
    ],
    'action' => ['uri' => 'internal:#how-to-apply', 'title' => 'How to apply'],
    'prompt' => 'See how the year works and meet past fellows, or go straight to how to apply.',
    'source' => 'the 2023 and 2024 fellowship announcements',
    'galleries' => fn () => [
      $gallery('The fellowship', 'the-fellowship', [
        $text(NULL, <<<'HTML'
<p>The Faculty Fellowship gives LSU faculty the training, time and support to teach with Special Collections. Each fellow designs archive-based assignments that put primary sources in students’ hands and teach them to question the past.</p>
<p>The fellowship began in 2023 with six fellows from six fields and has welcomed a new cohort every year since.</p>
HTML),
        $text('The year', <<<'HTML'
<ol>
<li><strong>Orientation.</strong> Three days at Hill Memorial Library on archival research, digital pedagogy, digital humanities tools and ways to build Special Collections into a course.</li>
<li><strong>The fall.</strong> The cohort meets at Hill every two weeks to work with the collections, alongside librarians and each other, and shapes its course plans.</li>
<li><strong>The classroom.</strong> Fellows teach their archive-based assignments, with their librarian collaborators.</li>
</ol>
HTML),
      ]),
      $gallery('2024 fellows', 'fellows', [
        $text(NULL, '<p>Five fellows from across campus, each paired with a librarian or archivist, brought the archives into their courses.</p>'),
        $entry('Dorota K. Heneghan', <<<'HTML'
<p><strong>Department of World Languages, Literatures & Cultures</strong></p>
<p>Heneghan brought digital humanities methods, such as mapping word frequencies and tracing objects through a text, into her courses in Comparative Literature, Women’s and Gender Studies, and Screen Arts. Her students read Sherlock Holmes and detective fiction for what they reveal about gender norms and Victorian society. She worked with Humanities and Social Sciences Librarian Elizabeth Allen.</p>
HTML),
        $entry('William Ma', <<<'HTML'
<p><strong>College of Art & Design</strong></p>
<p>Ma built a research seminar on the Judith Ann Schiebout Chinese Communist Poster Collection, early-1980s posters that show China’s changing politics and culture after the 1978 reforms. Ma helped bring the collection to LSU after Professor Emerita Schiebout’s death. His students research the posters and curate an online exhibition. He worked with Kelly Larson, Head of Research and Public Services.</p>
HTML),
        $entry('Will Mari', <<<'HTML'
<p><strong>Manship School of Mass Communication</strong></p>
<p>Mari created a new Honors College class, “Propaganda of the Cold War,” focused on NATO and the Warsaw Pact. By spring 2025 his students were working directly with LSU’s archives. He worked with University Archivist Zach Tompkins.</p>
HTML),
        $entry('Heather O’Connell', <<<'HTML'
<p><strong>Department of Sociology</strong></p>
<p>O’Connell added archival research to her upper-level course on racial inequality. Students study the building and removal of a Confederate monument that once stood on the State Capitol grounds, through newspapers, letters from the 1890s, records of the United Daughters of the Confederacy and oral histories from the Civil Rights era. She worked with African and African American Studies Librarian Narcissa Haskins.</p>
HTML),
        $entry('Cynthia Sampson', <<<'HTML'
<p><strong>Department of Communication Studies</strong></p>
<p>Sampson is designing a graduate seminar on the papers of François Delsarte (1811–1871), the French teacher of voice and movement whose ideas shaped American dance, acting and physical education. The papers have been at LSU for nearly a century. She worked with John Miles, Curator of Books and Head of Instruction, and Leah Wood Jewett, Exhibition Manager.</p>
HTML),
        $text(NULL, <<<'HTML'
<ul>
<li><a href="https://lib.lsu.edu/250328/facultyfellows">2024 Faculty Fellowship Spotlight</a></li>
<li><a href="https://lib.lsu.edu/node/6142">The first cohort, 2023</a></li>
</ul>
HTML),
      ]),
      $gallery('How to apply', 'how-to-apply', [
        $text('Who can apply', '<p>LSU teaching faculty at any rank. [Editor: confirm.]</p>'),
        $text('Support', '<p>Fellows receive a professional development stipend for the year: $2,000 in 2023. [Editor: confirm the current stipend.]</p>'),
        $text('Your application', '<p>[Editor: what to send and where, or a link to the form.]</p>'),
        $text(NULL, '<p><strong>Applications:</strong> [Editor: dates; in 2024 they ran from March 25 to June 1].</p>', 'callout'),
      ]),
      $gallery('Questions', 'questions', [
        $text(NULL, '<p>[Editor: the program’s contact, e.g. the Digital Engagement and Pedagogy Librarian, with an email address.]</p>' . $ask),
      ]),
    ],
  ],
  'summer-project' => [
    'title' => 'Special Collections Summer Project',
    'kicker' => 'Undergraduate research',
    'subtitle' => 'A paid summer in the archive',
    'intro' => '<p>LSU undergraduates spend the summer on a research or creative project of their own, built on a collection at Hill Memorial Library and mentored by an archivist or librarian. Run with the Office of Undergraduate Research.</p>',
    'keyfacts' => [
      'Pay: Up to $3,000',
      'For: LSU undergraduates',
      'Deadline: [Editor: date; February 15 in 2026]',
    ],
    'action' => ['uri' => 'https://www.lsu.edu/our/funding/special_collections.php', 'title' => 'Apply'],
    'prompt' => 'Read about the project, or apply through the Office of Undergraduate Research.',
    'source' => 'the Office of Undergraduate Research\'s program page',
    'galleries' => fn () => [
      $gallery('The project', 'the-project', [
        $text(NULL, <<<'HTML'
<p>Each summer, Special Collections and the LSU Office of Undergraduate Research choose students to spend the summer on a project of their own, built on one of Hill’s archival collections. Each student works with an archivist or librarian as a mentor.</p>
<p>Projects can draw on anything at Hill: the Louisiana and Lower Mississippi Valley Collections, rare books, University Archives, the T. Harry Williams Center for Oral History, the Louisiana newspapers and more.</p>
HTML),
        $text(NULL, '<p>[Editor: a past student and their project, with a photo, would show what a summer here looks like.]</p>'),
      ]),
      $gallery('Who can apply', 'who-can-apply', [
        $text(NULL, <<<'HTML'
<p>Students are paid for the hours they work on the project, up to $3,000. Two students are chosen each summer. [Editor: confirm for the next summer.]</p>
<p>To apply, you must:</p>
<ul>
<li>be enrolled full time as an undergraduate in the following fall;</li>
<li>have a college GPA of at least 3.0;</li>
<li>be in good standing with the university.</li>
</ul>
<p>You can’t be paid by this program and another source for the same hours on a project.</p>
HTML),
      ]),
      $gallery('How to apply', 'how-to-apply', [
        $text(NULL, '<p>Applications go through the Office of Undergraduate Research.</p><p><a href="https://www.lsu.edu/our/funding/special_collections.php">Apply on the Office of Undergraduate Research site</a></p>'),
        $text(NULL, '<p><strong>Deadline:</strong> [Editor: date; for Summer 2026 it was February 15, with decisions on March 30].</p>', 'callout'),
      ]),
      $gallery('Questions', 'questions', [
        $text(NULL, '<p>Not sure which collection suits your idea? Ask us before you apply.</p>' . $ask),
      ]),
    ],
  ],
];

$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');
foreach (['field_exhibit_kicker', 'field_exhibit_keyfacts', 'field_exhibit_action'] as $name) {
  if (!isset($node_fields[$name])) {
    $skipped[] = "$name is not on Modular page: re-run palefire_exhibit_setup.php, then fill it in on each page.";
  }
}

foreach ($pages as $slug => $page) {
  $found = \Drupal::entityQuery('node')->accessCheck(FALSE)
    ->condition('type', 'modular_page')
    ->condition('title', $page['title'])
    ->range(0, 1)->execute();
  if ($found) {
    echo "Skipped '{$page['title']}': it already exists (node " . reset($found) . ').' . PHP_EOL;
    continue;
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
  ];
  foreach (array_keys($values) as $name) {
    if (str_starts_with($name, 'field_') && !isset($node_fields[$name])) {
      unset($values[$name]);
    }
  }
  if ($prefix) {
    $values['path'] = ['alias' => "$prefix/$slug"];
  }

  $node = Node::create($values);
  $node->setRevisionLogMessage("Program page: {$page['title']}, drafted from {$page['source']} (palefire_special_programs.php).");
  $node->save();
  echo 'Created unpublished page: node/' . $node->id() . ' (' . $node->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
}

foreach (array_unique($skipped) as $line) {
  echo 'Skipped: ' . $line . PHP_EOL;
}
echo 'Add a title image to each page, fill in the [Editor: …] notes, point the Apply buttons at the forms, then publish. Run: drush cr' . PHP_EOL;
