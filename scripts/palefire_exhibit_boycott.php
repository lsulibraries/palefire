<?php

/**
 * @file
 * Exhibition page: "The Baton Rouge Bus Boycott" (T. Harry Williams Center
 * for Oral History; made in 1998 by McKinley High School students and the
 * LSU class EDCI 5880, revised in 2002, last online as the five Showcase
 * pages under /boycott, nodes 283–287, with its clips and transcripts in
 * /sites/all/files/sc/exhibitions/e-exhibits/boycott) as an unpublished
 * Modular page in exhibition mode. Run palefire_exhibit_setup.php and
 * palefire_exhibit_bio_setup.php first.
 *
 * Copy the "boycott-web" folder’s masters/ (28 JPEGs) and audio/ (8 M4A
 * clips) to the server first, then:
 *
 *   drush php:script scripts/palefire_exhibit_boycott.php -- images=$HOME/boycott-web/masters audio=$HOME/boycott-web/audio path=/v2/oralhistory/exhibitions/boycott
 *   drush php:script scripts/palefire_exhibit_boycott.php -- images=$HOME/boycott-web/masters audio=$HOME/boycott-web/audio path=/v2/oralhistory/exhibitions/boycott apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it lists what it would import and build, and
 * any file that is missing, and saves nothing.
 *
 * Arguments after "--":
 *   images=<folder>       Required. The JPEG masters.
 *   audio=<folder>        Required. The M4A clips (AAC 64 kbps mono, -16 LUFS).
 *   path=<alias>          URL alias, e.g. path=/v2/oralhistory/exhibitions/boycott
 *   format=<text format>  Text format for the text (default: WYSIWYG).
 *   apply=1               Save.
 *
 * The text, in data/boycott.json, is the old pages’ (corrections in
 * docs/special-exhibition-corrections/boycott.md). Four galleries, all
 * walls (Display "wall"), each with a lead picture:
 * - Chronology, 1950–1955: one dated entry per event, read in place as
 *   wall labels; the drivers’ strike with its newspaper headline.
 * - Background: Mary Price’s essay as five text plates (an imageless,
 *   undated entry with a Summary, opening in the reading view) hung
 *   between the eleven free ride photographs and three of Horatio
 *   Thompson’s gas stations, as the essay placed them.
 * - The People: one entry per narrator (portrait, clip and transcript,
 *   with the old page’s biography as the entry’s biography and their role),
 *   Johnnie A. Jones’s portrait and biography, and the three student
 *   interviews (Eddie Johnson, Patricia A. Robinson, James Batiste) as
 *   text plates.
 * - Our Thanks: the thanks as a text plate, the class and the McKinley
 *   High School project as their photographs, and the 2002 edition.
 *
 * Images: each JPEG is imported once as an Image media item (named
 * "Bus Boycott: <file name>", reused on a re-run) under
 * public://exhibitions/boycott. Clips go to its audio/ folder, each with
 * its read-along timing from data/boycott-vtt/ beside it (same name,
 * .vtt). Missing files are listed at the end.
 *
 * With path= and the Redirect module on, /v2/special/exhibitions/boycott
 * gets a 301 to the page, as the other oral history exhibitions have.
 *
 * Creates one Modular page, UNPUBLISHED. It stops if a Modular page with
 * the same title exists.
 */

declare(strict_types=1);

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;

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
$prefix = $apply ? '' : '[dry run] ';

foreach (['exhibit_page', 'exhibit_entry', 'exhibit_figure', 'exhibit_audio', 'guide_text'] as $id) {
  if (!ParagraphsType::load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_exhibit_setup.php first.");
  }
}

$images_dir = rtrim($args['images'] ?? '', '/\\');
if ($images_dir === '' || !is_dir($images_dir)) {
  throw new \RuntimeException('Pass images=<folder>: the boycott-web/masters folder copied to the server.');
}
$audio_dir = rtrim($args['audio'] ?? '', '/\\');
if ($audio_dir === '' || !is_dir($audio_dir)) {
  throw new \RuntimeException('Pass audio=<folder>: the boycott-web/audio folder copied to the server.');
}
$timings_dir = __DIR__ . '/data/boycott-vtt';

$data_file = __DIR__ . '/data/boycott.json';
$data = json_decode((string) file_get_contents($data_file), TRUE);
if (!is_array($data) || count($data['pages'] ?? []) !== 4) {
  throw new \RuntimeException("Couldn’t read the 4 galleries from $data_file.");
}

$title = $data['title'];
$found = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', $title)
  ->range(0, 1)->execute();
if ($found) {
  throw new \RuntimeException("A page titled '$title' already exists (node " . reset($found) . '). Delete it first to rebuild it.');
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
echo "Text format: $format" . PHP_EOL;

// The Image media type and its source field.
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

// Entry fields from palefire_exhibit_setup.php and palefire_exhibit_bio_setup.php.
$entry_field = fn (string $name) => (bool) FieldConfig::loadByName('paragraph', 'exhibit_entry', $name);
$has = [
  'summary' => $entry_field('field_entry_summary'),
  'date' => $entry_field('field_entry_date'),
  'bio' => $entry_field('field_entry_bio'),
  'role' => $entry_field('field_entry_role'),
];
foreach ($has as $name => $present) {
  if (!$present) {
    throw new \RuntimeException("field_entry_$name is not on Entry. Run palefire_exhibit_setup.php and palefire_exhibit_bio_setup.php first.");
  }
}

$skipped = [];
$counts = ['images' => 0, 'clips' => 0, 'timings' => 0];
$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];

/** @var \Drupal\Core\File\FileSystemInterface $fs */
$fs = \Drupal::service('file_system');
$destination = 'public://exhibitions/boycott';
$audio_destination = "$destination/audio";
if ($apply) {
  foreach ([$destination, $audio_destination] as $dir) {
    $fs->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  }
}

// Images. --------------------------------------------------------------------

$media_ids = [];

// One media item per file, reused when it exists ("Bus Boycott: <file>").
// A dry run only checks that the file is there.
$media = function (?string $relative) use (&$media_ids, &$counts, $apply, $images_dir, $fs, $destination, $media_type, $source_field, $data, &$skipped): ?int {
  if (!$relative) {
    return NULL;
  }
  if (array_key_exists($relative, $media_ids)) {
    return $media_ids[$relative];
  }
  $name = 'Bus Boycott: ' . pathinfo($relative, PATHINFO_FILENAME);
  $existing = \Drupal::entityQuery('media')->accessCheck(FALSE)
    ->condition('bundle', $media_type->id())->condition('name', $name)->range(0, 1)->execute();
  if ($existing) {
    return $media_ids[$relative] = (int) reset($existing);
  }
  $path = $images_dir . '/' . $relative;
  if (!is_file($path)) {
    $skipped[] = "Image not found: $path";
    return $media_ids[$relative] = NULL;
  }
  if (empty($data['alt'][$relative])) {
    $skipped[] = "No alt text for $relative in data/boycott.json.";
  }
  $counts['images']++;
  if (!$apply) {
    return $media_ids[$relative] = NULL;
  }
  $safe = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(pathinfo($relative, PATHINFO_FILENAME))), '-') . '.jpg';
  $uri = $fs->copy($path, "$destination/$safe", FileExists::Rename);
  $file = File::create(['uri' => $uri, 'status' => 1]);
  $file->save();
  [$width, $height] = getimagesize($path) ?: [NULL, NULL];
  $item = Media::create([
    'bundle' => $media_type->id(),
    'name' => $name,
    'status' => 1,
    $source_field => [
      'target_id' => $file->id(),
      'alt' => $data['alt'][$relative] ?? '',
      'width' => $width,
      'height' => $height,
    ],
  ]);
  $item->save();
  echo "Imported $relative" . PHP_EOL;
  return $media_ids[$relative] = (int) $item->id();
};

// Audio. ---------------------------------------------------------------------

$file_ids = [];

// One file per clip, reused when it is already in place (same URI), with
// its WebVTT read-along timing beside it under the same name.
$audio_file = function (string $relative) use (&$file_ids, &$counts, $apply, $audio_dir, $timings_dir, $fs, $audio_destination, &$skipped): ?int {
  if (array_key_exists($relative, $file_ids)) {
    return $file_ids[$relative];
  }
  $stem = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(pathinfo($relative, PATHINFO_FILENAME))), '-');
  $timing = $timings_dir . '/' . pathinfo($relative, PATHINFO_FILENAME) . '.vtt';
  if (is_file($timing)) {
    $counts['timings']++;
    if ($apply) {
      $fs->copy($timing, "$audio_destination/$stem.vtt", FileExists::Replace);
    }
  }
  else {
    $skipped[] = "No read-along timing for $relative (data/boycott-vtt/): the transcript plays without it.";
  }
  $existing = \Drupal::entityQuery('file')->accessCheck(FALSE)
    ->condition('uri', "$audio_destination/$stem.m4a")->range(0, 1)->execute();
  if ($existing) {
    return $file_ids[$relative] = (int) reset($existing);
  }
  $path = $audio_dir . '/' . $relative;
  if (!is_file($path)) {
    $skipped[] = "Audio not found: $path";
    return $file_ids[$relative] = NULL;
  }
  $counts['clips']++;
  if (!$apply) {
    // Stands in for the file a run with apply=1 imports.
    return $file_ids[$relative] = 0;
  }
  $uri = $fs->copy($path, "$audio_destination/$stem.m4a", FileExists::Rename);
  $file = File::create(['uri' => $uri, 'status' => 1, 'filemime' => 'audio/mp4']);
  $file->save();
  echo "Imported $relative" . PHP_EOL;
  return $file_ids[$relative] = (int) $file->id();
};

// Blocks. --------------------------------------------------------------------

$ref = fn (?int $id) => $id ? ['target_id' => $id] : NULL;
$keep = fn (array $values) => array_filter($values, fn ($v) => $v !== NULL && $v !== '');
$built = ['entries' => 0, 'plates' => 0, 'notes' => 0];

$block = function (array $b) use (&$block, &$built, $apply, $media, $audio_file, $ref, $keep, $html, &$skipped): ?Paragraph {
  switch ($b['type']) {
    case 'text':
      return Paragraph::create([
        'type' => 'guide_text',
        'field_block_body' => $html($b['body']),
        'field_text_style' => $b['style'] ?? 'default',
      ]);

    case 'figure':
      $id = $media($b['image'] ?? NULL);
      if (!$id && $apply) {
        $skipped[] = 'No image for "' . $b['source'] . '": the figure keeps its caption; add the image in the editor.';
      }
      return Paragraph::create($keep([
        'type' => 'exhibit_figure',
        'field_figure_image' => $ref($id),
        'field_figure_caption' => ($b['caption'] ?? '') !== '' ? $html($b['caption']) : NULL,
        'field_figure_layout' => $b['layout'] ?? 'side_start',
      ]));

    case 'audio':
      $id = $audio_file($b['file']);
      if ($id === NULL) {
        // The file is required: leave the clip out, listed below.
        $skipped[] = 'No audio for "' . $b['title'] . '": add an Audio block with ' . $b['file'] . ' in the editor.';
        return NULL;
      }
      return Paragraph::create($keep([
        'type' => 'exhibit_audio',
        'field_block_title' => $b['title'],
        'field_audio_file' => $id ? ['target_id' => $id] : NULL,
        'field_audio_credit' => $b['credit'],
        'field_audio_transcript' => $html($b['transcript']),
      ]));

    case 'entry':
      $built[!empty($b['summary']) ? 'plates' : (!empty($b['date']) ? 'notes' : 'entries')]++;
      $entry = Paragraph::create($keep([
        'type' => 'exhibit_entry',
        'field_block_title' => $b['title'],
        'field_section_anchor' => $b['anchor'],
        'field_entry_date' => $b['date'] ?? NULL,
        'field_entry_summary' => $b['summary'] ?? NULL,
        'field_entry_role' => $b['role'] ?? NULL,
        'field_entry_bio' => ($b['bio'] ?? '') !== '' ? $html($b['bio']) : NULL,
        'field_guide_blocks' => array_values(array_filter(array_map($block, $b['blocks']))),
      ]));
      if (empty($b['published'])) {
        $entry->setUnpublished();
        $skipped[] = 'Unpublished: "' . $b['title'] . '" (' . $b['source'] . ').';
      }
      return $entry;
  }
  return NULL;
};

// Every gallery is a wall with a lead picture, described in its caption.
// The leads are under 720 px, so each is shown whole and framed ("whole"
// in the data) rather than blown up to fill the screen.
$has_whole = (bool) FieldConfig::loadByName('paragraph', 'exhibit_page', 'field_exhibit_lead_whole');
$sections = [];
foreach ($data['pages'] as $page) {
  $sections[] = Paragraph::create($keep([
    'type' => 'exhibit_page',
    'field_section_title' => $page['title'],
    'field_section_anchor' => $page['anchor'],
    'field_exhibit_lead' => $ref($media($page['lead'] ?? NULL)),
    'field_exhibit_lead_focus' => $page['focus'] ?? 'center',
    'field_exhibit_lead_caption' => !empty($page['lead_caption']) ? $html($page['lead_caption']) : NULL,
    'field_exhibit_display' => $page['display'] ?? 'wall',
    'field_exhibit_lead_whole' => $has_whole && !empty($page['whole']) ? 1 : NULL,
    'field_guide_blocks' => array_values(array_filter(array_map($block, $page['blocks']))),
  ]));
  echo $prefix . 'Built gallery: ' . $page['title'] . ' (' . ($page['display'] ?? 'wall') . ', lead ' . ($page['lead'] ?? 'none') . ')' . PHP_EOL;
}
echo "{$prefix}{$built['entries']} picture and clip entries, {$built['plates']} text plates, {$built['notes']} dated wall labels." . PHP_EOL;
echo "{$prefix}" . ($apply ? 'Imported' : 'Would import') . " {$counts['images']} new images and {$counts['clips']} new clips; {$counts['timings']} read-along timings." . PHP_EOL;

// The page. ------------------------------------------------------------------

$values = [
  'type' => 'modular_page',
  'title' => $title,
  'status' => 0,
  'field_scroll_section' => $sections,
  'field_modular_type' => 'exhibition',
  'field_exhibit_subtitle' => $data['subtitle'] !== '' ? $data['subtitle'] : NULL,
  'field_exhibit_credit' => $html($data['credit']),
  'field_exhibit_cover' => $ref($media($data['cover'])),
  // A 600 px cover: framed, not stretched across the screen.
  'field_exhibit_cover_framed' => 1,
  'field_exhibit_bios_open' => 1,
  'field_intro' => $html($data['intro']),
  'field_exhibit_year' => $data['year'],
  'field_exhibit_physical_note' => $data['physical_note'] !== '' ? $data['physical_note'] : NULL,
];
$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');
foreach (array_keys($values) as $name) {
  if (str_starts_with($name, 'field_') && !isset($node_fields[$name])) {
    $skipped[] = "$name is not on Modular page; run palefire_exhibit_setup.php.";
    unset($values[$name]);
  }
}
$alias = !empty($args['path']) ? '/' . trim($args['path'], '/') : NULL;
if ($alias) {
  $values['path'] = ['alias' => $alias];
  if (\Drupal::entityTypeManager()->getStorage('path_alias')->loadByProperties(['alias' => $alias])) {
    throw new \RuntimeException("$alias is already in use. Pick another path=.");
  }
}

if (!$apply) {
  echo "[dry run] Would create the unpublished page “{$title}”" . ($alias ? " at $alias" : '') . '.' . PHP_EOL;
  foreach (array_unique($skipped) as $line) {
    echo '[dry run] Skipped: ' . $line . PHP_EOL;
  }
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$node = Node::create(array_filter($values, fn ($v) => $v !== NULL));
$node->setRevisionLogMessage('Exhibition: The Baton Rouge Bus Boycott, from the 1998 and 2002 exhibition (palefire_exhibit_boycott.php).');
$node->save();
echo 'Created unpublished exhibition page: node/' . $node->id() . ' (' . $node->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;

// The Special Collections address points to the Oral History one.
$old = 'v2/special/exhibitions/boycott';
if ($alias && '/' . $old !== $alias && \Drupal::moduleHandler()->moduleExists('redirect')) {
  $redirects = \Drupal::entityTypeManager()->getStorage('redirect');
  if (!$redirects->loadByProperties(['redirect_source__path' => $old])) {
    $redirects->create([
      'redirect_source' => ['path' => $old, 'query' => []],
      'redirect_redirect' => ['uri' => 'internal:/node/' . $node->id()],
      'status_code' => 301,
      'language' => $node->language()->getId(),
    ])->save();
    echo "Redirect: /$old → $alias (301)." . PHP_EOL;
  }
}

foreach (array_unique($skipped) as $line) {
  echo 'Skipped: ' . $line . PHP_EOL;
}
echo 'Review it, then publish. Run: drush cr' . PHP_EOL;
