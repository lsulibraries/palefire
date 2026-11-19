<?php

/**
 * @file
 * Adds "The Full Interviews" to the Mossville History Project exhibition:
 * every interview the project recorded, in full, as a gallery before About
 * the Project. It takes the place of the old directory page
 * (/sites/all/files/oralhistory/presentations/mossvilledirectory), made in
 * 2017 when the exhibition couldn’t be edited fast enough.
 *
 * The gallery is one text block: a short opening and "[interviews:mossville]",
 * which draws the narrators from data/mossville-interviews.json
 * (src/ExhibitInterviews.php, includes/exhibit-interviews.html.twig). The
 * recordings, transcripts and summaries stay where they are on the server
 * and stream from there.
 *
 * Portraits: copy the "mossvilledirectory-web/portraits" folder (46 JPEGs,
 * 1600 px, made by build/preview/resize-portraits.js) to the server and
 * pass images=<folder>. Each is imported once as an Image media item named
 * "Mossville interviews: <file>" under public://exhibitions/mossville/
 * interviews, reused on a re-run. Without images= the gallery is still
 * added; a narrator without a portrait shows initials until it is imported.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_mossville_interviews.php -- images=/path/to/portraits
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_mossville_interviews.php -- images=/path/to/portraits apply=1
 *   drush cr
 *
 * Optional arguments after "--":
 *   images=<folder>       The resized portraits.
 *   format=<text format>  Text format for the text (default: WYSIWYG).
 *   apply=1               Save. Without it, a dry run that lists what it would do.
 *
 * The page is saved as a new revision. Safe to re-run: the gallery is added
 * only when the page has none with the anchor "interviews".
 */

declare(strict_types=1);

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\palefire\ExhibitInterviews;
use Drupal\paragraphs\Entity\Paragraph;

if (PHP_SAPI !== 'cli') {
  return;
}

$args = [];
foreach ((isset($extra) && is_array($extra)) ? $extra : [] as $arg) {
  if (str_contains((string) $arg, '=')) {
    [$k, $v] = explode('=', (string) $arg, 2);
    $args[trim($k)] = trim($v, " \t\"'");
  }
}
$apply = ($args['apply'] ?? '') === '1';
echo $apply ? 'Applying.' . PHP_EOL : 'Dry run: nothing is saved. Add apply=1 to save.' . PHP_EOL;

if (!class_exists(ExhibitInterviews::class)) {
  throw new \RuntimeException('src/ExhibitInterviews.php is missing: git pull, then drush cr, first.');
}
$data_file = dirname(__DIR__) . '/data/mossville-interviews.json';
$data = json_decode((string) file_get_contents($data_file), TRUE);
if (!is_array($data) || empty($data['narrators'])) {
  throw new \RuntimeException("Couldn’t read the narrators from $data_file.");
}

$title = 'Mossville History Project';
$ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', $title)
  ->execute();
if (count($ids) !== 1) {
  throw new \RuntimeException("Expected one Modular page titled ‘{$title}’, found " . count($ids) . '.');
}
$node = Node::load(reset($ids));
echo "Page: $title (node {$node->id()})" . PHP_EOL;

// Portraits. -----------------------------------------------------------------

$images_dir = rtrim($args['images'] ?? '', '/\\');
if ($images_dir !== '' && !is_dir($images_dir)) {
  throw new \RuntimeException("images=$images_dir is not a folder.");
}

$media_type = NULL;
foreach (\Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple() as $candidate) {
  if ($candidate->getSource()->getPluginId() === 'image' && ($media_type === NULL || $candidate->id() === 'image')) {
    $media_type = $candidate;
  }
}
if (!$media_type) {
  throw new \RuntimeException('No Image media type found.');
}
$source_field = $media_type->getSource()->getConfiguration()['source_field'];

/** @var \Drupal\Core\File\FileSystemInterface $fs */
$fs = \Drupal::service('file_system');
$destination = 'public://exhibitions/mossville/interviews';
if ($apply && $images_dir !== '') {
  $fs->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
}

$counts = ['have' => 0, 'import' => 0, 'missing' => 0];
foreach ($data['narrators'] as $narrator) {
  $portrait = (string) ($narrator['portrait'] ?? '');
  if ($portrait === '') {
    continue;
  }
  $name = ExhibitInterviews::mediaName('mossville', $portrait);
  $existing = \Drupal::entityQuery('media')->accessCheck(FALSE)
    ->condition('bundle', $media_type->id())->condition('name', $name)->range(0, 1)->execute();
  if ($existing) {
    $counts['have']++;
    continue;
  }
  $path = $images_dir . '/' . $portrait;
  if ($images_dir === '' || !is_file($path)) {
    $counts['missing']++;
    if ($images_dir !== '') {
      echo "  Portrait not found: $path" . PHP_EOL;
    }
    continue;
  }
  $counts['import']++;
  if (!$apply) {
    continue;
  }
  $uri = $fs->copy($path, "$destination/$portrait", FileExists::Rename);
  $file = File::create(['uri' => $uri, 'status' => 1]);
  $file->save();
  [$width, $height] = getimagesize($path) ?: [NULL, NULL];
  Media::create([
    'bundle' => $media_type->id(),
    'name' => $name,
    'status' => 1,
    $source_field => [
      'target_id' => $file->id(),
      // Shown beside the name, so the page prints alt=""; this is for the
      // media library and anywhere else it is reused.
      'alt' => $narrator['name'] . ', narrator in the Mossville History Project',
      'width' => $width,
      'height' => $height,
    ],
  ])->save();
}
echo "Portraits: {$counts['have']} already in place, {$counts['import']} to import, {$counts['missing']} not imported" . ($images_dir === '' ? ' (no images= given)' : '') . '.' . PHP_EOL;

// The gallery. ---------------------------------------------------------------

$galleries = $node->get('field_scroll_section')->referencedEntities();
$anchors = array_map(fn ($p) => $p->hasField('field_section_anchor') ? (string) $p->get('field_section_anchor')->value : '', $galleries);
// Already there: only its opening text brought up to date (the PDFs gave
// way to the transcript in the object view, 2026-11-19).
$iv_old = 'Here is each one in full, with its summary and transcript.';
$iv_new = 'Here is each one in full, with its transcript.';
if (in_array('interviews', $anchors, TRUE)) {
  $gallery = $galleries[array_search('interviews', $anchors, TRUE)];
  foreach ($gallery->get('field_guide_blocks')->referencedEntities() as $block) {
    if ($block->bundle() !== 'guide_text' || !$block->hasField('field_block_body')) {
      continue;
    }
    $value = (string) $block->get('field_block_body')->value;
    if (!str_contains($value, $iv_old)) {
      continue;
    }
    echo 'The Full Interviews is on the page; its opening text says “' . $iv_new . '” in place of “' . $iv_old . '”.' . PHP_EOL;
    if ($apply) {
      $block->set('field_block_body', ['value' => str_replace($iv_old, $iv_new, $value), 'format' => $block->get('field_block_body')->format]);
      $block->setNewRevision(TRUE);
      $block->save();
      $node->setNewRevision(TRUE);
      $node->setRevisionLogMessage('The Full Interviews: opening text without the PDFs (palefire_exhibit_mossville_interviews.php).');
      $node->save();
      echo 'Saved.' . PHP_EOL;
    }
    return;
  }
  echo 'The Full Interviews is already on the page, its text up to date; left as it is.' . PHP_EOL;
  return;
}

// Text uses the WYSIWYG format, never full_html.
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

$body = '<p>The clips in this exhibition come from these interviews, recorded from 2015 to 2017 by the T. Harry Williams Center for Oral History with the Imperial Calcasieu Museum. Here is each one in full, with its transcript.</p>'
  . '<p>[interviews:mossville]</p>';

$at = array_search('about', $anchors, TRUE);
$at = $at === FALSE ? count($galleries) : $at;
echo 'Adds The Full Interviews as gallery ' . ($at + 1) . ' of ' . (count($galleries) + 1) . ($at < count($galleries) ? ', before About the Project' : '') . '.' . PHP_EOL;
if (!$apply) {
  return;
}

$gallery = Paragraph::create([
  'type' => 'exhibit_page',
  'field_section_title' => 'The Full Interviews',
  'field_section_anchor' => 'interviews',
  'field_guide_blocks' => [
    Paragraph::create([
      'type' => 'guide_text',
      'field_block_body' => ['value' => $body, 'format' => $format],
      'field_text_style' => 'default',
    ]),
  ],
]);
if ($gallery->hasField('field_exhibit_display')) {
  $gallery->set('field_exhibit_display', 'story');
}
array_splice($galleries, $at, 0, [$gallery]);
$node->set('field_scroll_section', $galleries);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('The Full Interviews gallery (palefire_exhibit_mossville_interviews.php).');
$node->save();
echo 'Saved.' . PHP_EOL;
