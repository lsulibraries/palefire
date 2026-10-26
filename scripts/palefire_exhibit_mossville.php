<?php

/**
 * @file
 * Exhibition page: "Mossville History Project" (T. Harry Williams Center
 * for Oral History and the Imperial Calcasieu Museum; a listening station
 * from 2017, online at /sites/all/files/oralhistory/presentations/
 * mossvilleexhibit) as an unpublished Modular page in exhibition mode. Run
 * palefire_exhibit_setup.php first.
 *
 * Copy the "mossvilleexhibit-web" folder's masters/ (60 JPEGs, 2560 px)
 * and audio/ (70 M4A clips) to the server first, then:
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_mossville.php -- images=/path/to/masters audio=/path/to/audio path=/v2/exhibitions/mossville
 *   drush cr
 *
 * Optional arguments after "--":
 *   images=<folder>       Required. The 2560 px JPEG masters.
 *   audio=<folder>        Required. The M4A clips (AAC 64 kbps mono, -18 LUFS).
 *   format=<text format>  Text format for the text (default: WYSIWYG).
 *   path=<alias>          URL alias, e.g. path=/v2/exhibitions/mossville
 *
 * The text is carried over from the old site's pages, in data/mossville.json:
 * one gallery per sub-group of its History, Culture and Economy menus, one
 * entry per clip (photo, player and transcript), then About the Project from
 * its About page. Changes: typos in titles and interviewers' names, speaker
 * labels in bold on their own paragraphs, new alt text and named captions,
 * and the right audio for three clips the old pages linked wrongly (c34,
 * c48, c52). The clip without a transcript (c52) is built unpublished.
 *
 * Images: each JPEG is imported once as an Image media item (named
 * "Mossville: <file name>", reused on a re-run) under
 * public://exhibitions/mossville. Audio files go to
 * public://exhibitions/mossville/audio, one file per clip, reused when a
 * clip appears twice. Missing files are listed at the end.
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

foreach (['exhibit_page', 'exhibit_entry', 'exhibit_figure', 'exhibit_audio', 'guide_text'] as $id) {
  if (!ParagraphsType::load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_exhibit_setup.php first.");
  }
}

$images_dir = rtrim($args['images'] ?? '', '/\\');
if ($images_dir === '' || !is_dir($images_dir)) {
  throw new \RuntimeException('Pass images=<folder>: the 2560 px masters copied to the server.');
}
$audio_dir = rtrim($args['audio'] ?? '', '/\\');
if ($audio_dir === '' || !is_dir($audio_dir)) {
  throw new \RuntimeException('Pass audio=<folder>: the M4A clips copied to the server.');
}

$data_file = __DIR__ . '/data/mossville.json';
$data = json_decode((string) file_get_contents($data_file), TRUE);
if (!is_array($data) || count($data['pages'] ?? []) !== 11) {
  throw new \RuntimeException("Couldn't read the 11 galleries from $data_file.");
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

$skipped = [];
$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];

/** @var \Drupal\Core\File\FileSystemInterface $fs */
$fs = \Drupal::service('file_system');
$destination = 'public://exhibitions/mossville';
$audio_destination = "$destination/audio";
foreach ([$destination, $audio_destination] as $dir) {
  $fs->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
}

// Images. --------------------------------------------------------------------

$media_ids = [];

// One media item per file, reused when it exists ("Mossville: <file>").
$media = function (?string $relative) use (&$media_ids, $images_dir, $fs, $destination, $media_type, $source_field, $data, &$skipped): ?int {
  if (!$relative) {
    return NULL;
  }
  if (isset($media_ids[$relative])) {
    return $media_ids[$relative];
  }
  $name = 'Mossville: ' . pathinfo($relative, PATHINFO_FILENAME);
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

// One file per clip, reused when it is already in place (same URI).
$audio_file = function (string $relative) use (&$file_ids, $audio_dir, $fs, $audio_destination, &$skipped): ?int {
  if (isset($file_ids[$relative])) {
    return $file_ids[$relative];
  }
  $safe = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(pathinfo($relative, PATHINFO_FILENAME))), '-') . '.m4a';
  $existing = \Drupal::entityQuery('file')->accessCheck(FALSE)
    ->condition('uri', "$audio_destination/$safe")->range(0, 1)->execute();
  if ($existing) {
    return $file_ids[$relative] = (int) reset($existing);
  }
  $path = $audio_dir . '/' . $relative;
  if (!is_file($path)) {
    $skipped[] = "Audio not found: $path";
    return $file_ids[$relative] = NULL;
  }
  $uri = $fs->copy($path, "$audio_destination/$safe", FileExists::Rename);
  $file = File::create(['uri' => $uri, 'status' => 1, 'filemime' => 'audio/mp4']);
  $file->save();
  echo "Imported $relative" . PHP_EOL;
  return $file_ids[$relative] = (int) $file->id();
};

// Blocks. --------------------------------------------------------------------

$ref = fn (?int $id) => $id ? ['target_id' => $id] : NULL;
$keep = fn (array $values) => array_filter($values, fn ($v) => $v !== NULL && $v !== '');

$block = function (array $b) use (&$block, $media, $audio_file, $ref, $keep, $html, &$skipped): ?Paragraph {
  switch ($b['type']) {
    case 'text':
      return Paragraph::create([
        'type' => 'guide_text',
        'field_block_body' => $html($b['body']),
        'field_text_style' => $b['style'] ?? 'default',
      ]);

    case 'figure':
      $id = $media($b['image'] ?? NULL);
      if (!$id) {
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
      if (!$id) {
        // The file is required: leave the clip out, listed below.
        $skipped[] = 'No audio for "' . $b['title'] . '": add an Audio block with ' . $b['file'] . ' in the editor.';
        return NULL;
      }
      return Paragraph::create($keep([
        'type' => 'exhibit_audio',
        'field_block_title' => $b['title'],
        'field_audio_file' => ['target_id' => $id],
        'field_audio_credit' => $b['credit'],
        'field_audio_transcript' => $html($b['transcript']),
      ]));

    case 'entry':
      $entry = Paragraph::create($keep([
        'type' => 'exhibit_entry',
        'field_block_title' => $b['title'],
        'field_section_anchor' => $b['anchor'],
        'field_guide_blocks' => array_values(array_filter(array_map($block, $b['blocks']))),
      ]));
      if (empty($b['published'])) {
        // No transcript yet: editors see it, visitors don't (WCAG 1.2.1).
        $entry->setUnpublished();
        $skipped[] = 'Unpublished until it has a transcript: "' . $b['title'] . '" (' . $b['source'] . ').';
      }
      return $entry;
  }
  return NULL;
};

// Each lead is its gallery's first clip photo, credited in its caption.
$has_caption = (bool) FieldConfig::loadByName('paragraph', 'exhibit_page', 'field_exhibit_lead_caption');
$sections = [];
foreach ($data['pages'] as $page) {
  $sections[] = Paragraph::create($keep([
    'type' => 'exhibit_page',
    'field_section_title' => $page['title'],
    'field_section_anchor' => $page['anchor'],
    'field_exhibit_lead' => $ref($media($page['lead'] ?? NULL)),
    'field_exhibit_lead_focus' => $page['focus'] ?? 'center',
    'field_exhibit_lead_caption' => !empty($page['lead_caption']) && $has_caption ? $html($page['lead_caption']) : NULL,
    'field_guide_blocks' => array_values(array_filter(array_map($block, $page['blocks']))),
  ]));
  echo 'Built gallery: ' . $page['title'] . PHP_EOL;
}

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
if (!empty($args['path'])) {
  $values['path'] = ['alias' => '/' . ltrim($args['path'], '/')];
}

$node = Node::create(array_filter($values, fn ($v) => $v !== NULL));
$node->setRevisionLogMessage('Exhibition: Mossville History Project, from the 2017 listening station (palefire_exhibit_mossville.php).');
$node->save();

echo 'Created unpublished exhibition page: node/' . $node->id() . ' (' . $node->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
foreach (array_unique($skipped) as $line) {
  echo 'Skipped: ' . $line . PHP_EOL;
}
echo 'Review it, then publish. Run: drush cr' . PHP_EOL;
