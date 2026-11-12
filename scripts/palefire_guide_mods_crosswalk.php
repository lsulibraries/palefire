<?php

/**
 * @file
 * Guide page: "MODS Field Crosswalk", which MODS elements become which
 * columns of the Quartex ingest CSV, institution by institution, as a
 * Modular page in guide mode. Run palefire_guide_setup.php and
 * palefire_guide_crosswalk_setup.php first.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_mods_crosswalk.php -- csv=/path/to/mapping.csv
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_mods_crosswalk.php -- csv=/path/to/mapping.csv apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it reports what it would do and saves nothing.
 *
 * The chart itself needs no code to keep current. It is drawn in the browser
 * from the mapping spreadsheet uploaded to the "Guide: Field crosswalk" block,
 * so an editor updates it by uploading a new CSV on the page's Edit form
 * (the "Keeping this page current" section says how). The text around it is
 * ordinary WYSIWYG blocks, also editable on the page.
 *
 * This script is the guide's first source and can be run again, as the
 * LDL Quartex Migration script can:
 * - No page with this title: creates one, UNPUBLISHED, owned by Kyle Tanglao
 *   when a Staff node with that name exists (owner=<First Last> to change).
 * - The page exists: replaces its intro and all its sections in a new
 *   revision. Publishing state, alias, owner and subjects stay as they are.
 *   Without csv=, the spreadsheet already on the page is kept.
 * - Nothing changed since the last build: saves nothing.
 *
 * Each build records a fingerprint of the content (and of the spreadsheet)
 * in the revision log. It stops, saving nothing, when:
 * - the current revision wasn't saved by this script, so editing in Drupal
 *   (uploading a new CSV counts) isn't lost by accident. Copy any edit worth
 *   keeping into this file, then run with force=1;
 * - the guide has a newer draft than its current revision.
 *
 * Arguments after "--":
 *   csv=<path>            The master mapping saved as CSV (UTF-8). Needed the
 *                         first time; copied to public://guides/crosswalk/.
 *   apply=1               Save.
 *   force=1               Replace the page even if it was edited in Drupal.
 *   format=<text format>  Text format for the guide's text (default: the
 *                         WYSIWYG format).
 *   path=<alias>          URL alias for a new page (default
 *                         /v2/special/guides/mods-field-crosswalk).
 *   owner=<First Last>    Staff node to set as the guide owner of a new page.
 *
 * "The crosswalk" is a Wide section when palefire_guide_wide_setup.php has
 * run, so the chart and its panel sit side by side.
 *
 * Text bodies follow the guide-mode rules in palefire_guide_ldl_migration.php:
 * no bullets inside bullets, and every table that isn't a price list has a
 * <thead>. Long bodies are nowdocs, so quotes and brackets are safe.
 */

declare(strict_types=1);

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\file\Entity\File;
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

foreach (['guide_section', 'guide_text'] as $id) {
  if (!\Drupal::entityTypeManager()->getStorage('paragraphs_type')->load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_guide_setup.php first.");
  }
}
if (!FieldConfig::loadByName('paragraph', 'guide_crosswalk', 'field_crosswalk_file')) {
  throw new \RuntimeException("'guide_crosswalk' not found. Run palefire_guide_crosswalk_setup.php first.");
}

$title = 'MODS Field Crosswalk';
$default_institution = 'Louisiana State University';

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

// The spreadsheet. ---------------------------------------------------------
// Checked the way js/guide-crosswalk.js reads it, so a bad file fails here
// rather than on the page.

$read_mapping = function (string $data): array {
  $data = preg_replace('/^\xEF\xBB\xBF/', '', $data);
  $handle = fopen('php://memory', 'r+');
  fwrite($handle, $data);
  rewind($handle);
  $head = array_map(fn ($h) => strtolower(trim((string) $h)), fgetcsv($handle, NULL, ',', '"', '') ?: []);
  $col = array_flip($head);
  $missing = array_diff(['xpaths', 'needed', 'att_needed', 'field_name', 'att_field_name', 'institution_list'], $head);
  if ($missing) {
    throw new \RuntimeException('The CSV has no ' . implode(', ', $missing) . ' column. Save the master mapping as "CSV UTF-8 (Comma delimited)".');
  }
  $rows = 0;
  $used = 0;
  $institutions = [];
  while (($r = fgetcsv($handle, NULL, ',', '"', '')) !== FALSE) {
    $rows++;
    $needed = strtolower(trim((string) ($r[$col['needed']] ?? ''))) === 'yes';
    $att = strtolower(trim((string) ($r[$col['att_needed']] ?? ''))) === 'yes';
    if (!$needed && !$att) {
      continue;
    }
    $used++;
    foreach (explode(';', (string) ($r[$col['institution_list']] ?? '')) as $name) {
      if (($name = trim($name)) !== '') {
        $institutions[$name] = TRUE;
      }
    }
  }
  fclose($handle);
  if (!mb_check_encoding($data, 'UTF-8')) {
    throw new \RuntimeException('The CSV isn\'t UTF-8, so curly quotes and accents would break. In Excel, save it as "CSV UTF-8 (Comma delimited)".');
  }
  return ['rows' => $rows, 'used' => $used, 'institutions' => array_keys($institutions)];
};

$csv_path = $args['csv'] ?? '';
$csv_data = NULL;
if ($csv_path !== '') {
  if (!is_readable($csv_path)) {
    throw new \RuntimeException("Can't read csv=$csv_path.");
  }
  $csv_data = (string) file_get_contents($csv_path);
}

// What's there now. --------------------------------------------------------

$storage = \Drupal::entityTypeManager()->getStorage('node');
$found = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', $title)
  ->range(0, 1)->execute();
$node = $found ? $storage->load((int) reset($found)) : NULL;

// The spreadsheet already on the page, kept when no csv= is given.
$existing_file = NULL;
if ($node && $node->hasField('field_scroll_section')) {
  foreach ($node->get('field_scroll_section')->referencedEntities() as $section_entity) {
    if (!$section_entity->hasField('field_guide_blocks')) {
      continue;
    }
    foreach ($section_entity->get('field_guide_blocks')->referencedEntities() as $block_entity) {
      if ($block_entity->bundle() === 'guide_crosswalk' && !$block_entity->get('field_crosswalk_file')->isEmpty()) {
        $existing_file = $block_entity->get('field_crosswalk_file')->entity;
        break 2;
      }
    }
  }
}
if ($csv_data === NULL) {
  if (!$existing_file) {
    throw new \RuntimeException('No spreadsheet yet. Pass csv=<path to the master mapping saved as CSV UTF-8>.');
  }
  $csv_data = (string) file_get_contents($existing_file->getFileUri());
}
$mapping = $read_mapping($csv_data);
$csv_sha = substr(sha1($csv_data), 0, 12);
if (!in_array($default_institution, $mapping['institutions'], TRUE)) {
  echo "Note: '$default_institution' isn't in the CSV's institution_list, so the chart opens on the institution with the most rows." . PHP_EOL;
}

// Content helpers: plain arrays, so a dry run creates nothing and the
// fingerprint covers exactly what the page will say. ------------------------

$sections = [];
// $wide: tick "Wide section" (palefire_guide_wide_setup.php) when it exists.
$section = function (string $heading, array $blocks, bool $wide = FALSE) use (&$sections): void {
  $sections[] = ['title' => $heading, 'blocks' => $blocks, 'wide' => $wide];
};
$text = fn (?string $heading, string $body, string $style = 'default') => [
  'kind' => 'text',
  'heading' => $heading,
  'body' => $body,
  'style' => $style,
];
$crosswalk = fn (?string $heading, string $intro, string $caption) => [
  'kind' => 'crosswalk',
  'heading' => $heading,
  'intro' => $intro,
  'caption' => $caption,
  'default' => $default_institution,
  'csv' => $csv_sha,
];

$intro = '<p>Every column in a Quartex ingest CSV comes from somewhere in an item\'s MODS record. This guide shows where, for each institution in the Louisiana Digital Library: pick an institution and follow the lines from each MODS element to the column it becomes.</p>';

// The spreadsheet's own date, so the fingerprint only changes with it.
$built = date('M j, Y', (int) filemtime($csv_path !== '' ? $csv_path : $existing_file->getFileUri()));
$n_inst = count($mapping['institutions']);

// Sections. ----------------------------------------------------------------

$section('The crosswalk', [
  $text(NULL, <<<HTML
<p><strong>One mapping, applied differently by each institution.</strong> All {$n_inst} institutions share one master mapping, but each uses only the MODS elements its records actually contain. The chart shows the {$mapping['used']} mapping rows that produce output, filtered to the institution you choose.</p>
HTML, 'callout'),
  $crosswalk(
    NULL,
    '',
    "<p>Drawn from the shared master mapping (mapping.xlsx), saved on $built. A row counts when it is marked needed or att_needed, and its institution_list names the institution.</p>",
  ),
], TRUE);

$section('How to read the chart', [
  $text(NULL, <<<'HTML'
<h4>The two sides</h4>
<ul>
<li><strong>Left:</strong> a MODS element in the source XML, written as its path without attribute filters, such as <code>name/namePart</code>. Elements are grouped by the part of the record they belong to: titleInfo, name, subject, originInfo and so on.</li>
<li><strong>Right:</strong> the column it becomes in the ingest CSV, such as <code>field_creator</code>.</li>
<li><strong>Click any box</strong> to light up its lines and list the exact XPaths behind it, with their display labels and any rules.</li>
</ul>
<h4>Lines</h4>
<ul>
<li><strong>A solid line</strong> carries the element's text.</li>
<li><strong>A dashed line</strong> carries one of its attributes instead, such as a date's <code>@qualifier</code> becoming <code>att_date_qualifier</code>.</li>
<li><strong>Color</strong> only shows which part of the record a line starts from. It means nothing on its own.</li>
</ul>
<h4>Boxes on the right</h4>
<ul>
<li><strong>A plain box</strong> is a column in the final CSV.</li>
<li><strong>A dashed box</strong> is a helper value, whose name starts with temp_, nonsort_ or att_. It is merged into the column named after the arrow and isn't a column of its own. For example, <code>temp_date_created_end</code> joins <code>field_date_created</code> to make a date range.</li>
<li><strong>A highlighted box marked TBD</strong> hasn't been given a destination in the mapping yet.</li>
<li><strong>RULE</strong> means the column is built with a concat or hyperlink rule, explained in the next section.</li>
</ul>
<h4>Narrowing it down</h4>
<ul>
<li><strong>Section buttons</strong> above the chart hide or show a part of the record, which helps with large institutions such as LSU.</li>
<li><strong>Filter</strong> searches paths, column names, display labels and rules: try "date", "Photographer" or "hyperlink".</li>
<li><strong>The table</strong> under the chart lists every mapping row for the institution, with its full XPath.</li>
<li><strong>Linking to one institution:</strong> choosing an institution changes the page address, so you can copy it and send it.</li>
</ul>
HTML),
]);

$section('How values are combined', [
  $text(NULL, <<<'HTML'
<p>Some columns are built from more than one value. The mapping's Notes column says how, in a short rule such as <code>concat: [field_date_created]-[temp_date_created_end]</code>. The names in square brackets are values from the same record, and anything between them is kept as written.</p>
<table>
<thead><tr><th scope="col">Rule pattern</th><th scope="col">What it does</th><th scope="col">Example result</th></tr></thead>
<tbody>
<tr><td><code>[a]: [b]</code></td><td>Joins with a colon and a space</td><td>Current scientific name: Pinguinus impennis</td></tr>
<tr><td><code>[a] ([b])</code></td><td>Puts the second value in parentheses</td><td>1821 (approximate)</td></tr>
<tr><td><code>[a]-[b]</code></td><td>Joins a range with a dash</td><td>1821-1825</td></tr>
<tr><td><code>[a]. [b]</code></td><td>Joins with a period and a space</td><td>3. Correspondence</td></tr>
<tr><td><code>[a] [b]</code></td><td>Joins with a space</td><td>The Great Auk</td></tr>
<tr><td><code>["Box number"]: [a]</code></td><td>Adds fixed text, written in quotes</td><td>Box number: 3</td></tr>
<tr><td><code>hyperlink: [a] links to [b]</code></td><td>Makes the first value a link to the second</td><td>A rights statement linked to its URI</td></tr>
</tbody>
</table>
<h4>What happens when a value is missing</h4>
<p>The empty piece and the punctuation around it are dropped, so a date with no qualifier reads 1821, not 1821 (). A rule is applied to each occurrence of an element on its own, so a record with two notes gets each label in front of its own note.</p>
<h4>The order rules run in</h4>
<ol>
<li>Non-sorting articles, such as "The", go in front of titles.</li>
<li>Date ranges are joined.</li>
<li>Other joins.</li>
<li>Qualifiers are added in parentheses, once, at the end.</li>
<li>Links are made last.</li>
</ol>
HTML),
]);

$section('Keeping this page current', [
  $text(NULL, <<<'HTML'
<p>The chart is drawn from a copy of the master mapping uploaded to the page, so it changes only when a new copy is uploaded. No code is needed.</p>
<ol>
<li>Open <strong>mapping.xlsx</strong> in Excel.</li>
<li>Choose <strong>File &gt; Save a copy</strong>, and for the file type pick <strong>CSV UTF-8 (Comma delimited)</strong>. Plain "CSV" loses curly quotes and accented letters.</li>
<li>On this page, choose <strong>Edit</strong>, open the <strong>The crosswalk</strong> section, then the <strong>Guide: Field crosswalk</strong> block.</li>
<li>Under <strong>Mapping spreadsheet (CSV)</strong>, choose <strong>Remove</strong>, then upload the new file.</li>
<li>Update the date in the <strong>Caption</strong>, then <strong>Save</strong>.</li>
</ol>
<p>The same block has a field for the institution the chart opens on, and checkboxes to hide the table of rows or the columns still marked TBD.</p>
HTML, 'callout'),
]);

// End of sections. ---------------------------------------------------------

$block_count = 0;
foreach ($sections as $item) {
  $block_count += count($item['blocks']);
}
$fingerprint = substr(sha1(json_encode([$intro, $sections], JSON_THROW_ON_ERROR)), 0, 12);
$log_prefix = 'palefire_guide_mods_crosswalk.php build';
$log_message = "$log_prefix $fingerprint";
$prefix = $apply ? '' : '[dry run] ';

echo "Text format: $format" . PHP_EOL;
echo "Spreadsheet: {$mapping['rows']} rows, {$mapping['used']} needed, $n_inst institutions (" . ($csv_path !== '' ? $csv_path : 'the copy already on the page') . ")." . PHP_EOL;
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
    throw new \RuntimeException("'$title' (node/{$node->id()}) was last saved outside this script (log: \"$last_log\"), and a build would replace any edits, including a newly uploaded spreadsheet. Check its Revisions tab, copy anything worth keeping into this script, then run with force=1.");
  }
  $state = $node->isPublished() ? 'published' : 'unpublished';
  echo "{$prefix}Replace the intro and " . count($node->get('field_scroll_section')) . " sections of node/{$node->id()}, in a new revision. It stays $state." . PHP_EOL;
}
else {
  echo "{$prefix}Create '$title' as an unpublished guide page." . PHP_EOL;
}
foreach ($sections as $item) {
  echo "{$prefix}  {$item['title']} (" . count($item['blocks']) . ' blocks' . ($item['wide'] ? ', wide' : '') . ')' . PHP_EOL;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

// Build. -------------------------------------------------------------------

$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];
$keep = fn (array $values) => array_filter($values, fn ($v) => $v !== NULL);

// The spreadsheet as a managed file: a new copy when csv= is given (and
// differs from what's there), otherwise the one already on the page.
$file = $existing_file;
if ($csv_path !== '' && (!$file || sha1_file($file->getFileUri()) !== sha1($csv_data))) {
  $dir = 'public://guides/crosswalk';
  \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  $file = \Drupal::service('file.repository')->writeData($csv_data, "$dir/mods-crosswalk-mapping.csv", FileExists::Rename);
  echo 'Saved the spreadsheet as ' . $file->getFileUri() . PHP_EOL;
}
if (!$file instanceof File) {
  throw new \RuntimeException('No spreadsheet to attach.');
}

$to_paragraph = function (array $block) use ($html, $keep, $file): Paragraph {
  if ($block['kind'] === 'crosswalk') {
    return Paragraph::create($keep([
      'type' => 'guide_crosswalk',
      'field_block_title' => $block['heading'],
      'field_block_intro' => $block['intro'] !== '' ? $html($block['intro']) : NULL,
      'field_crosswalk_file' => ['target_id' => $file->id()],
      'field_crosswalk_default' => $block['default'],
      'field_crosswalk_caption' => $block['caption'] !== '' ? $html($block['caption']) : NULL,
      'field_crosswalk_table' => 1,
      'field_crosswalk_tbd' => 1,
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
$has_wide = (bool) FieldConfig::loadByName('paragraph', 'guide_section', 'field_section_wide');
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
  if ($has_wide) {
    $values['field_section_wide'] = $item['wide'] ? 1 : 0;
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
  'path' => ['alias' => '/' . ltrim($args['path'] ?? '/v2/special/guides/mods-field-crosswalk', '/')],
];
if (isset($node_fields['field_modular_type'])) {
  $values['field_modular_type'] = 'guide';
}
if (isset($node_fields['field_intro'])) {
  $values['field_intro'] = $html($intro);
}

// Owner: a Staff node, Kyle Tanglao unless owner= says otherwise. Subjects
// are left empty on purpose: this is a project record, not a research guide.
$skipped = [];
$owner_name = $args['owner'] ?? 'Kyle Tanglao';
$owner_field = $node_fields['field_guide_owner'] ?? NULL;
if ($owner_field) {
  $staff_bundles = array_keys($owner_field->getSetting('handler_settings')['target_bundles'] ?? []);
  $query = $staff_bundles ? \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', $staff_bundles, 'IN') : NULL;
  foreach ($query ? preg_split('/\s+/', trim($owner_name)) : [] as $part) {
    $query->condition('title', $part, 'CONTAINS');
  }
  $match = $query ? $query->range(0, 1)->execute() : [];
  if ($match) {
    $values['field_guide_owner'] = ['target_id' => (int) reset($match)];
  }
  else {
    $skipped[] = "Guide owner: no Staff node titled like \"$owner_name\" was found. Set it on the page.";
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
