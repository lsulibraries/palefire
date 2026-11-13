<?php

/**
 * @file
 * Faculty Fellowship: brings the existing program page up to date.
 *
 * For a site where palefire_special_programs.php already built
 * /v2/special/programs/faculty-fellowship. This script is now that page's
 * source, and it can be run again:
 *
 *   drush php:script scripts/palefire_faculty_fellowship_update.php
 *   drush php:script scripts/palefire_faculty_fellowship_update.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. It replaces the intro, key facts, prompt and
 * galleries in a new revision. The title, subtitle, title image, main
 * button, alias and publishing state stay as they are.
 *
 * Drafted from the program's own files (Special Collections, Faculty
 * Fellows Program folder, November 2026), reworded:
 * - FacFellows_MasterList.xlsx: every fellow, cohort, department and the
 *   classes they have taught with the collections.
 * - FF_Introductions.docx and FF26-Cohort.docx: the 2026 fellows.
 * - The 2026 orientation run of show and debrief: the four-day orientation.
 * - Application Draft.docx: what the application asks for.
 * Internal notes (who has left LSU, orientation feedback) are left out.
 * Stipend, application dates, eligibility and the contact's email are still
 * "[Editor: …]" notes for Special Collections to confirm.
 *
 * Each build records a fingerprint in the revision log. It stops, saving
 * nothing, when the page has a newer draft, or when its current revision
 * wasn't saved by this script or palefire_special_programs.php (an edit in
 * Drupal, or publishing through the edit form). Copy anything worth keeping
 * into this file, then run with force=1.
 *
 * Optional arguments after "--":
 *   apply=1               Save.
 *   force=1               Replace even if the page was edited in Drupal.
 *   format=<text format>  Text format (default: the WYSIWYG format).
 *   path=<alias>          The page's alias (default
 *                         /v2/special/programs/faculty-fellowship).
 */

declare(strict_types=1);

use Drupal\Component\Utility\Html;
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
$alias = '/' . trim($args['path'] ?? '/v2/special/programs/faculty-fellowship', '/');
$programs = dirname($alias);

foreach (['exhibit_page', 'exhibit_entry', 'guide_text', 'guide_accordion'] as $id) {
  if (!\Drupal::entityTypeManager()->getStorage('paragraphs_type')->load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_exhibit_setup.php first.");
  }
}

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

// Content: plain arrays, so the fingerprint covers exactly what the page
// will say and a dry run creates nothing. ----------------------------------

$text = fn (?string $heading, string $body, string $style = 'default') => ['kind' => 'text', 'heading' => $heading, 'body' => $body, 'style' => $style];
$entry = fn (string $name, string $bio) => ['kind' => 'entry', 'name' => $name, 'bio' => $bio];
$accordion = fn (array $items) => ['kind' => 'accordion', 'items' => $items];
$gallery = fn (string $title, string $anchor, array $blocks) => ['title' => $title, 'anchor' => $anchor, 'blocks' => $blocks];

$intro = '<p>A year-long fellowship for LSU teaching faculty: four days of training at Hill Memorial Library, a cohort of colleagues and a librarian at your side, to bring Special Collections into your courses.</p>';
$keyfacts = [
  '2026 cohort: Five fellows',
  'For: LSU teaching faculty',
  'Orientation: Four days in August',
];
$prompt = 'Meet the 2026 fellows, see what past fellows have taught, or go straight to how to apply.';

$galleries = [
  $gallery('The fellowship', 'the-fellowship', [
    $text(NULL, <<<'HTML'
<p>The Faculty Fellowship gives LSU faculty the training, time and support to teach with Special Collections. Each fellow designs archive-based assignments that put primary sources in students’ hands and teach them to question the past.</p>
<p>Since the first cohort in 2023, 24 fellows from across campus have taken part, and they have taught more than 35 classes built on the collections, from Slavery in Baton Rouge, whose students built <a href="https://slaverybr.org/">a public history site</a>, to Forest Insects and Diseases.</p>
HTML),
    $text('The year', <<<'HTML'
<ol>
<li><strong>Orientation.</strong> Four days at Hill in August, before classes begin. Fellows meet the people who run Special Collections, tour the building, read the same sources through different disciplines, learn to read finding aids and search the catalog, try digital tools, request items in the Reading Room and plan how to teach a source. Former fellows share what worked in their classes.</li>
<li><strong>The fall.</strong> The cohort meets every two weeks to go deeper into topics from orientation, share course plans and get feedback. Each fellow is paired with a librarian or archivist who helps from planning through teaching.</li>
<li><strong>The classroom.</strong> Fellows teach their archive-based assignments, and many keep teaching them in the years after.</li>
</ol>
HTML),
  ]),
  $gallery('2026 fellows', 'fellows', [
    $text(NULL, '<p>Five fellows from five fields began the fellowship in August 2026.</p>'),
    $entry('William Hunter', <<<'HTML'
<p><strong>School of Architecture, College of Art & Design</strong></p>
<p>Hunter, Assistant Professor of Practice in Architecture, works on urban design and community engagement, with a focus on affordable housing and grassroots action for social change in the United States and the Global South. Before LSU he was program director of Open House New York, a nonprofit that connects New Yorkers with their built environment. He holds a Master of Science from University College London. His fellowship design studio looks at the history of segregation, neighborhood identity and urban development in Baton Rouge.</p>
HTML),
    $entry('Touria Khannous', <<<'HTML'
<p><strong>Department of World Languages, Literatures & Cultures</strong></p>
<p>Khannous, Associate Professor of Arabic, studies women and gender in the cultures of North Africa. She earned her PhD at Brown University and has held a Fulbright, among other fellowships. Her fellowship project is an honors course on hurricanes in Louisiana: climate change, coastal erosion and flooding, and how communities respond to environmental catastrophe.</p>
HTML),
    $entry('Wonik Kim', <<<'HTML'
<p><strong>Department of Political Science</strong></p>
<p>Kim, Associate Professor of Political Science, works in comparative politics with an emphasis on East Asia: political economy, globalization and the politics of capitalism. He earned his PhD at New York University. His fellowship course is on race and capitalism, drawing on records of slavery, plantation economies, the Black Codes, Jim Crow and racial hierarchy.</p>
HTML),
    $entry('Scott Madere', <<<'HTML'
<p><strong>International Studies</strong></p>
<p>Madere, Instructor of International Studies, earned his PhD in history at LSU. His research is on eighteenth-century military history, and he teaches courses on contemporary global issues and war. In his senior seminar, students start from events as Louisiana and LSU students lived them, such as the French Revolution, filibustering expeditions, the Second World War and the Cold War, and widen out to international themes.</p>
HTML),
    $entry('Sarah Rosser', <<<'HTML'
<p><strong>Department of English</strong></p>
<p>Rosser, Senior Instructor of English, holds an MA in English literature from California State University, Dominguez Hills. In her composition course, students use <em>The Reveille</em> and other student voices on major events in LSU’s history as the basis for issue-analysis papers.</p>
HTML),
  ]),
  $gallery('Past fellows', 'past-fellows', [
    $text(NULL, '<p>Each cohort, with the classes its fellows have taught with the collections so far.</p>'),
    $accordion([
      ['2025 cohort', <<<'HTML'
<ul>
<li><strong>Nolde Alexius</strong>, English: LSU Literary Tour</li>
<li><strong>Joy Blanchard</strong>, Education: Foundations of Higher Education</li>
<li><strong>Maribel Dietz</strong>, History: Premodern Information Transmission</li>
<li><strong>Sherri Johnson</strong>, History</li>
<li><strong>Todd Johnson</strong>, Entomology: Forest Insects and Diseases</li>
<li><strong>Nolonda Jones</strong>, Interior Design</li>
<li><strong>Yong-Ha Kim</strong>, Environmental Sciences: Mathematical Methods in Science</li>
<li><strong>Jessica Valdez</strong>, English: Victorian True Crime; Feeling Like a Victorian (graduate seminar)</li>
</ul>
HTML],
      ['2024 cohort', <<<'HTML'
<ul>
<li><strong>Dorota K. Heneghan</strong>, World Languages, Literatures & Cultures: digital humanities methods for reading Sherlock Holmes and detective fiction, with Elizabeth Allen</li>
<li><strong>William Ma</strong>, Art History: Japanese Art; Asian Art; an honors seminar, with research on the Judith Ann Schiebout Chinese Communist Poster Collection, with Kelly Larson</li>
<li><strong>Will Mari</strong>, Mass Communication: Propaganda of the Cold War (Honors College); American Media History, with Zach Tompkins</li>
<li><strong>Heather O’Connell</strong>, Sociology: Racial Inequality, on the Confederate monument once on the State Capitol grounds, with Narcissa Haskins</li>
<li><strong>Cynthia Sampson</strong>, Communication Studies: Public Speaking; a graduate seminar on the papers of François Delsarte, with John Miles and Leah Wood Jewett</li>
</ul>
<p><a href="https://lib.lsu.edu/250328/facultyfellows">2024 Faculty Fellowship Spotlight</a></p>
HTML],
      ['2023 cohort', <<<'HTML'
<ul>
<li><strong>John Bardes</strong>, History: Slavery in Baton Rouge, with the student site <a href="https://slaverybr.org/">slaverybr.org</a></li>
<li><strong>Michael Bibler</strong>, English: Queer American Literature</li>
<li><strong>Fabio Capra-Ribeiro</strong>, Architecture: Urban Renewal Catalysts (<a href="https://www.lib.lsu.edu/24/03/22/facultyfellowsribeirohighlight">read about the class</a>)</li>
<li><strong>Johanna Warwick</strong>, Photography: New Archive</li>
<li><strong>Carla White</strong>, Art History: Art of the Book; Netherlandish Art</li>
<li><strong>Anne Stuckey Williams</strong>, Social Work: History of Social Welfare and Policy</li>
</ul>
<p><a href="https://lib.lsu.edu/node/6142">The first cohort, 2023</a></p>
HTML],
    ]),
  ]),
  $gallery('How to apply', 'how-to-apply', [
    $text('Who can apply', '<p>LSU teaching faculty at any rank, in any field. [Editor: confirm.]</p>'),
    $text('Support', '<p>Fellows receive a professional development stipend for the year: $2,000 in 2023. [Editor: confirm the current stipend.]</p>'),
    $text('Your application', <<<'HTML'
<p>The application asks for:</p>
<ul>
<li>the course you’d like to build around Special Collections: new or existing, its topic and roughly how many students;</li>
<li>the collections or kinds of material you imagine using (it’s fine not to know yet: browse the finding aids and digital collections for ideas);</li>
<li>how the course might use digital tools, such as building a digital collection, annotating a text or visualizing data;</li>
<li>whether you can attend the August orientation and the fall meetings;</li>
<li>a one- to two-page statement of your teaching philosophy, and your CV.</li>
</ul>
<p>If you are offered a fellowship, your department chair is told. [Editor: confirm against this year’s form and link to it.]</p>
HTML),
    $text(NULL, '<p><strong>Applications:</strong> [Editor: dates for the 2027 cohort; in 2024 they ran from March 25 to June 1]. Orientation is planned for mid-August, the week before classes begin.</p>', 'callout'),
  ]),
  $gallery('Questions', 'questions', [
    $text(NULL, '<p>Brandon Layton coordinates the fellowship. [Editor: title and email address.]</p><p>Not ready for a year-long fellowship? <a href="' . $programs . '/teaching">Bring a class to Hill</a> to try the collections with your students first.</p><p><a href="https://askus.lib.lsu.edu#special">Ask Special Collections</a></p>'),
  ]),
];

$entries = 0;
foreach ($galleries as $item) {
  foreach ($item['blocks'] as $block) {
    $entries += $block['kind'] === 'entry' ? 1 : 0;
  }
}
$fingerprint = substr(sha1(json_encode([$intro, $keyfacts, $prompt, $galleries, $programs], JSON_THROW_ON_ERROR)), 0, 12);
$log_prefix = 'palefire_faculty_fellowship_update.php build';
$log_message = "$log_prefix $fingerprint";

// The page. ----------------------------------------------------------------

$storage = \Drupal::entityTypeManager()->getStorage('node');
$path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
$node = preg_match('#^/node/(\d+)$#', $path, $m) ? $storage->load((int) $m[1]) : NULL;
if (!$node) {
  $found = \Drupal::entityQuery('node')->accessCheck(FALSE)
    ->condition('type', 'modular_page')
    ->condition('title', 'Special Collections Faculty Fellowship')
    ->execute();
  if (count($found) !== 1) {
    throw new \RuntimeException("No single Faculty Fellowship page at $alias. Run palefire_special_programs.php first, or pass path=<alias>.");
  }
  $node = $storage->load((int) reset($found));
}
if (!$node instanceof Node || $node->bundle() !== 'modular_page') {
  throw new \RuntimeException("$alias is not a Modular page.");
}

$latest = $storage->getLatestRevisionId($node->id());
if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
  throw new \RuntimeException("node/{$node->id()} has a newer draft (revision $latest). Publish or discard it first.");
}
$last_log = (string) $node->getRevisionLogMessage();
if ($last_log === $log_message) {
  echo "node/{$node->id()} already has this build. Nothing to do." . PHP_EOL;
  return;
}
$ours = str_starts_with($last_log, $log_prefix) || str_contains($last_log, 'palefire_special_programs.php');
if (!$ours && !$force) {
  $when = date('Y-m-d H:i', (int) $node->getRevisionCreationTime());
  $who = $node->getRevisionUser() ? $node->getRevisionUser()->getDisplayName() : 'unknown';
  throw new \RuntimeException("node/{$node->id()} was last saved in Drupal ($who, $when, log: \"$last_log\"), and this would replace any edits to its text. Check its Revisions tab, copy anything worth keeping into this script, then run with force=1.");
}

$prefix = $apply ? '' : '[dry run] ';
$state = $node->isPublished() ? 'published' : 'unpublished';
echo "Text format: $format" . PHP_EOL;
echo "{$prefix}Replace the intro, key facts, prompt and " . count($node->get('field_scroll_section')) . " galleries of node/{$node->id()} with build $fingerprint, in a new revision. It stays $state." . PHP_EOL;
foreach ($galleries as $item) {
  echo "{$prefix}  {$item['title']} (" . count($item['blocks']) . ' blocks)' . PHP_EOL;
}
echo "{$prefix}  $entries fellow entries in all." . PHP_EOL;
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

// Build. -------------------------------------------------------------------

$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];
$keep = fn (array $values) => array_filter($values, fn ($v) => $v !== NULL);
$fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('paragraph', 'exhibit_page');

$to_paragraph = function (array $block) use ($html, $keep): Paragraph {
  if ($block['kind'] === 'entry') {
    return Paragraph::create([
      'type' => 'exhibit_entry',
      'field_block_title' => $block['name'],
      'field_section_anchor' => Html::getClass($block['name']),
      'field_entry_bio' => $html($block['bio']),
    ]);
  }
  if ($block['kind'] === 'accordion') {
    return Paragraph::create([
      'type' => 'guide_accordion',
      'field_accordion_items' => array_map(fn ($item) => Paragraph::create([
        'type' => 'accordion_item',
        'field_item_title' => $item[0],
        'field_block_body' => $html($item[1]),
      ]), $block['items']),
    ]);
  }
  return Paragraph::create($keep([
    'type' => 'guide_text',
    'field_block_title' => $block['heading'],
    'field_block_body' => $html($block['body']),
    'field_text_style' => $block['style'],
  ]));
};

$paragraphs = [];
foreach ($galleries as $item) {
  $paragraphs[] = Paragraph::create($keep([
    'type' => 'exhibit_page',
    'field_section_title' => $item['title'],
    'field_section_anchor' => $item['anchor'],
    'field_exhibit_display' => isset($fields['field_exhibit_display']) ? 'story' : NULL,
    'field_guide_blocks' => array_map($to_paragraph, $item['blocks']),
  ]));
}

$node->set('field_scroll_section', $paragraphs);
$node->set('field_intro', $html($intro));
if ($node->hasField('field_exhibit_keyfacts')) {
  $node->set('field_exhibit_keyfacts', array_map(fn ($line) => ['value' => $line], $keyfacts));
}
if ($node->hasField('field_exhibit_prompt')) {
  $node->set('field_exhibit_prompt', $prompt);
}
$node->setNewRevision(TRUE);
$node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
$node->setRevisionLogMessage($log_message);
$node->save();
echo "Saved a new revision of node/{$node->id()} ($alias). It stays $state. Run: drush cr" . PHP_EOL;
