<?php

/**
 * @file
 * Program pages for Special Collections: Research Grants, the Faculty
 * Fellowship and the Summer Project, as unpublished Modular pages in guide
 * mode. Run palefire_guide_setup.php first.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_special_programs.php -- prefix=/v2/special
 *   drush cr
 *
 * Optional arguments after "--":
 *   format=<text format>  Text format for the text (default: the WYSIWYG
 *                         format).
 *   prefix=<alias>        URL alias prefix: the pages get <prefix>/research-
 *                         grants, <prefix>/faculty-fellowship and
 *                         <prefix>/summer-project. None: no aliases.
 *
 * Drafted from LSU's public announcements (October 2026), reworded:
 * - Research Grants: "LSU Libraries Awards 2025–26 Special Collections
 *   Research Grants" (lib.lsu.edu/250918/scresearchgrants).
 * - Faculty Fellowship: "LSU Libraries' Archive-Centered Teaching Initiative
 *   Empowers Educators" (2023, lib.lsu.edu/node/6142) and "LSU Libraries
 *   Special Collections 2024 Faculty Fellowship Spotlight"
 *   (lib.lsu.edu/250328/facultyfellows).
 * - Summer Project: the Office of Undergraduate Research's page
 *   (lsu.edu/our/funding/special_collections.php).
 * The announcements report past cycles, so current amounts, dates,
 * eligibility and contacts are marked "[Editor: …]" for Special Collections
 * to confirm. Past figures are given as past ("in 2023").
 *
 * Creates up to three Modular pages, UNPUBLISHED, with no guide owner
 * (set it on each page). A page whose title already exists is skipped, so
 * the script is safe to re-run after deleting one page to rebuild it.
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

if (!\Drupal::entityTypeManager()->getStorage('paragraphs_type')->load('guide_section')) {
  throw new \RuntimeException("'guide_section' not found. Run palefire_guide_setup.php first.");
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

// Block helpers (same shapes as palefire_guide_research_help.php). --------

$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];
$keep = fn (array $values) => array_filter($values, fn ($v) => $v !== NULL);

$text = fn (?string $heading, string $body, string $style = 'default') => Paragraph::create($keep([
  'type' => 'guide_text',
  'field_block_title' => $heading,
  'field_block_body' => $html($body),
  'field_text_style' => $style,
]));

$has_theme = (bool) FieldConfig::loadByName('paragraph', 'guide_section', 'field_section_theme');
$section = function (string $heading, array $blocks) use ($has_theme): Paragraph {
  $values = [
    'type' => 'guide_section',
    'field_section_title' => $heading,
    'field_guide_blocks' => array_values(array_filter($blocks)),
  ];
  if ($has_theme) {
    $values['field_section_theme'] = 'light';
  }
  return Paragraph::create($values);
};

$ask = '<p><a href="https://askus.lib.lsu.edu#special">Ask Special Collections</a></p>';

// The pages. ---------------------------------------------------------------
// Built in closures, so a page that already exists creates no paragraphs.

$pages = [
  'research-grants' => [
    'title' => 'Special Collections Research Grants',
    'intro' => '<p>Each year LSU Libraries awards research grants to scholars whose work depends on the collections at Hill Memorial Library. The grants pay for travel, lodging and other costs of a research stay in Baton Rouge.</p>',
    'source' => 'the 2025–26 award announcement',
    'sections' => fn () => [
      $section('About the grants', [
        $text(NULL, <<<'HTML'
<p>Research Grants bring scholars to Baton Rouge to work with materials found only in LSU Special Collections: manuscripts, rare books, university records and oral histories. Recipients use the funds for travel, lodging and other essentials while they research at Hill Memorial Library.</p>
<p>The grants are awarded annually. For 2025–26, four scholars were funded, with projects ranging from francophone culture and identity in the Gulf South to militia service among Black Louisianans, overlooked voices of Louisiana's Civil War, and the ecology of the Jim Crow South.</p>
<p><a href="https://lib.lsu.edu/250918/scresearchgrants">Meet the 2025–26 grant recipients</a></p>
HTML),
      ]),
      $section('Who can apply', [
        $text(NULL, '<p>[Editor: confirm who is eligible, e.g. graduate students, postdoctoral researchers, faculty and independent researchers, and whether applicants must live outside the Baton Rouge area.]</p>'),
      ]),
      $section('Award and how to apply', [
        $text(NULL, <<<'HTML'
<p>[Editor: confirm the number and amount of grants for the next cycle, and what they may be spent on.]</p>
<p>[Editor: describe the application: a research plan naming the collections to be used, a CV, any letters, and where to submit.]</p>
HTML),
        $text(NULL, '<p><strong>Application deadline:</strong> [Editor: date]. <strong>Decisions:</strong> [Editor: date].</p>', 'callout'),
      ]),
      $section('Questions', [
        $text(NULL, '<p>Before you apply, ask us about the collections you plan to use: we can help you find the right finding aids and plan the length of your stay.</p>' . $ask),
      ]),
    ],
  ],
  'faculty-fellowship' => [
    'title' => 'Special Collections Faculty Fellowship',
    'intro' => '<p>A year-long fellowship for LSU teaching faculty who want to build archives and primary sources into their courses, with training, a cohort of colleagues and support from librarians at Hill Memorial Library.</p>',
    'source' => 'the 2023 and 2024 fellowship announcements',
    'sections' => fn () => [
      $section('About the fellowship', [
        $text(NULL, <<<'HTML'
<p>The Faculty Fellowship gives LSU faculty the training, time and support to bring Special Collections materials into their teaching. Fellows design archive-based assignments that help students read primary sources closely and practise historical inquiry.</p>
<p>The program began in 2023 with a cohort of six fellows from six fields, and has run each year since.</p>
HTML),
      ]),
      $section('The year', [
        $text(NULL, <<<'HTML'
<ul>
<li><strong>Orientation:</strong> three days at Hill Memorial Library on archival research, digital pedagogy, digital humanities tools and ways to bring Special Collections materials into a course.</li>
<li><strong>Through the fall:</strong> the cohort meets every two weeks at Hill with librarians and colleagues, works with the collections and develops its course plans.</li>
<li><strong>In the classroom:</strong> fellows teach their archive-based assignments in the following semester.</li>
</ul>
<p>[Editor: confirm the schedule for the next cohort.]</p>
HTML),
      ]),
      $section('Support and eligibility', [
        $text(NULL, <<<'HTML'
<p>In 2023 fellows received a $2,000 professional development stipend for the year. [Editor: confirm the current stipend.]</p>
<p>The fellowship is open to LSU teaching faculty at any rank. [Editor: confirm.]</p>
HTML),
        $text(NULL, '<p><strong>Applications:</strong> [Editor: dates; in 2024 the window was March 25 to June 1].</p>', 'callout'),
      ]),
      $section('Past fellows', [
        $text(NULL, <<<'HTML'
<p>Read how past fellows brought the archives into courses across campus.</p>
<ul>
<li><a href="https://lib.lsu.edu/250328/facultyfellows">2024 Faculty Fellowship Spotlight</a></li>
<li><a href="https://lib.lsu.edu/node/6142">The first cohort, 2023</a></li>
</ul>
HTML),
      ]),
      $section('Questions', [
        $text(NULL, '<p>[Editor: name the program\'s contact, e.g. the Digital Engagement and Pedagogy Librarian, with an email address.]</p>' . $ask),
      ]),
    ],
  ],
  'summer-project' => [
    'title' => 'Special Collections Summer Project',
    'intro' => '<p>A paid summer research or creative project for LSU undergraduates, built on the collections at Hill Memorial Library and mentored by an archivist or librarian. Run with the Office of Undergraduate Research.</p>',
    'source' => 'the Office of Undergraduate Research\'s program page',
    'sections' => fn () => [
      $section('About the project', [
        $text(NULL, <<<'HTML'
<p>Each summer, Special Collections and the LSU Office of Undergraduate Research invite undergraduates to spend the summer on a research or creative project of their own, using one of the library's archival collections. Each student works with an archivist or librarian as a mentor.</p>
<p>Projects can draw on anything at Hill Memorial Library, including the Louisiana and Lower Mississippi Valley Collections, the rare books, University Archives, the T. Harry Williams Center for Oral History and the Louisiana newspapers.</p>
HTML),
      ]),
      $section('Pay and eligibility', [
        $text(NULL, <<<'HTML'
<p>Students are paid for the hours they work on the project, up to $3,000. Two students are selected each summer. [Editor: confirm for the next summer.]</p>
<p>To apply, you must:</p>
<ul>
<li>be enrolled full time as an undergraduate in the following fall;</li>
<li>have a college GPA of at least 3.0;</li>
<li>be in good standing with the university.</li>
</ul>
<p>You can't be paid by this program and another source for the same hours on a project.</p>
HTML),
      ]),
      $section('How to apply', [
        $text(NULL, '<p>Applications go through the Office of Undergraduate Research.</p><p><a href="https://www.lsu.edu/our/funding/special_collections.php">Apply on the Office of Undergraduate Research site</a></p>'),
        $text(NULL, '<p><strong>Deadline:</strong> [Editor: date; for Summer 2026 it was February 15, with decisions on March 30].</p>', 'callout'),
      ]),
      $section('Questions', [
        $text(NULL, '<p>Not sure which collection suits your idea? Ask us before you apply.</p>' . $ask),
      ]),
    ],
  ],
];

$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');
$subjects = [];
if (isset($node_fields['field_guide_subjects'])) {
  $vocabs = array_keys($node_fields['field_guide_subjects']->getSetting('handler_settings')['target_bundles'] ?? []);
  $subjects = $vocabs ? array_values(\Drupal::entityQuery('taxonomy_term')->accessCheck(FALSE)
    ->condition('vid', $vocabs, 'IN')->condition('name', 'Special Collections')->execute()) : [];
}
$prefix = isset($args['prefix']) ? '/' . trim($args['prefix'], '/') : NULL;

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
    'field_scroll_section' => $page['sections'](),
  ];
  if (isset($node_fields['field_modular_type'])) {
    $values['field_modular_type'] = 'guide';
  }
  if (isset($node_fields['field_intro'])) {
    $values['field_intro'] = $html($page['intro']);
  }
  if ($subjects) {
    $values['field_guide_subjects'] = array_map(fn ($tid) => ['target_id' => $tid], $subjects);
  }
  if ($prefix) {
    $values['path'] = ['alias' => "$prefix/$slug"];
  }

  $node = Node::create($values);
  $node->setRevisionLogMessage("Program page: {$page['title']}, drafted from {$page['source']} (palefire_special_programs.php).");
  $node->save();
  echo 'Created unpublished page: node/' . $node->id() . ' (' . $node->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
}

if (isset($node_fields['field_guide_subjects']) && !$subjects) {
  echo 'Skipped: Subjects: no "Special Collections" term was found. Set it on each page.' . PHP_EOL;
}
if (isset($node_fields['field_guide_owner'])) {
  echo 'Skipped: Guide owner: set it on each page.' . PHP_EOL;
}
echo 'Fill in the [Editor: …] notes, review the pages, then publish. Run: drush cr' . PHP_EOL;
