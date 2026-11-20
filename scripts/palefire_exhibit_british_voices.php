<?php

/**
 * @file
 * Exhibition page: "British Voices from South Asia" (Hill Memorial Library,
 * April 8 to August 6, 1996; online at /special/exhibits/india/, then as
 * the thirteen Showcase pages under /BritishVoices, node/315–327) as an
 * unpublished Modular page in exhibition mode. Run palefire_exhibit_setup.php
 * first.
 *
 *   drush php:script scripts/palefire_exhibit_british_voices.php
 *   drush php:script scripts/palefire_exhibit_british_voices.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it lists the galleries, entries and pictures it
 * would make and any file it can’t find, and saves nothing.
 *
 * Optional arguments after "--":
 *   images=<folder>       The JPEGs (default: the site’s own
 *                         public://inline-images, where the Showcase pages
 *                         keep them).
 *   format=<text format>  Text format for the text (default: WYSIWYG).
 *   path=<alias>          URL alias (default:
 *                         /v2/special/exhibitions/british-voices).
 *
 * The content is in data/british-voices.json: thirteen galleries, each a
 * wall. The six history sections each open on their interviewees’ words
 * (the intro over the lead), then the section’s essay as a text plate,
 * then every picture of the old page as an entry, with its 1996 caption
 * and the words that followed it. Each section is followed by its
 * interview excerpts, which the old site kept as six pages at the end:
 * walls of text plates, a few speakers each, under the old pages’ own
 * topic headings (an entry with only a heading starts a sub-gallery).
 * Changes are listed in docs/special-exhibition-corrections/british-voices.md.
 *
 * Images: the 103 JPEGs of the 1996 site (up to 600 px; no larger scans
 * were found on R:, in the Louisiana Digital Library or in the Wayback
 * Machine). Each is imported once as an Image media item (named "British
 * Voices: <file name>", reused on a re-run) under
 * public://exhibitions/british-voices.
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
echo $apply ? 'Applying.' . PHP_EOL : 'Dry run: nothing is saved. Add -- apply=1 to build the page.' . PHP_EOL;

foreach (['exhibit_page', 'exhibit_entry', 'exhibit_figure', 'exhibit_gallery', 'guide_text'] as $id) {
  if (!ParagraphsType::load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_exhibit_setup.php first.");
  }
}

/** @var \Drupal\Core\File\FileSystemInterface $fs */
$fs = \Drupal::service('file_system');
$images_dir = rtrim($args['images'] ?? (string) $fs->realpath('public://inline-images'), '/\\');
if ($images_dir === '' || !is_dir($images_dir)) {
  throw new \RuntimeException("No images folder at '$images_dir'. Pass images=<folder>.");
}
echo "Images: $images_dir" . PHP_EOL;

$data_file = __DIR__ . '/data/british-voices.json';
$data = json_decode((string) file_get_contents($data_file), TRUE);
if (!is_array($data) || count($data['pages'] ?? []) !== 13) {
  throw new \RuntimeException("Couldn't read the 13 galleries from $data_file.");
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

// Images. --------------------------------------------------------------------

$destination = 'public://exhibitions/british-voices';
if ($apply) {
  $fs->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
}
$media_ids = [];
$imported = 0;

// One media item per file, reused when it exists ("British Voices: <file>").
// A dry run only checks the file is there; it returns 0 for a picture it
// would import.
$media = function (?string $file) use (&$media_ids, &$imported, $apply, $images_dir, $fs, $destination, $media_type, $source_field, $data, &$skipped): ?int {
  if (!$file) {
    return NULL;
  }
  if (array_key_exists($file, $media_ids)) {
    return $media_ids[$file];
  }
  $name = 'British Voices: ' . pathinfo($file, PATHINFO_FILENAME);
  $existing = \Drupal::entityQuery('media')->accessCheck(FALSE)
    ->condition('bundle', $media_type->id())->condition('name', $name)->range(0, 1)->execute();
  if ($existing) {
    return $media_ids[$file] = (int) reset($existing);
  }
  $path = $images_dir . '/' . $file;
  if (!is_file($path)) {
    $skipped[] = "Image not found: $path";
    return $media_ids[$file] = NULL;
  }
  $imported++;
  if (!$apply) {
    return $media_ids[$file] = 0;
  }
  $uri = $fs->copy($path, "$destination/$file", FileExists::Rename);
  $item = File::create(['uri' => $uri, 'status' => 1]);
  $item->save();
  [$width, $height] = getimagesize($path) ?: [NULL, NULL];
  $entity = Media::create([
    'bundle' => $media_type->id(),
    'name' => $name,
    'status' => 1,
    $source_field => [
      'target_id' => $item->id(),
      'alt' => $data['alt'][$file] ?? '',
      'width' => $width,
      'height' => $height,
    ],
  ]);
  $entity->save();
  return $media_ids[$file] = (int) $entity->id();
};

// Blocks. --------------------------------------------------------------------

$ref = fn (?int $id) => $id ? ['target_id' => $id] : NULL;
$keep = fn (array $values) => array_filter($values, fn ($v) => $v !== NULL && $v !== '');
$counts = ['entries' => 0, 'plates' => 0, 'pictures' => 0, 'headings' => 0];

$figure = function (array $f, string $layout) use ($media, $ref, $keep, $html, &$skipped, &$counts): Paragraph {
  $id = $media($f['image']);
  if ($id === NULL) {
    $skipped[] = 'No image for "' . $f['image'] . '": the figure keeps its caption; add the image in the editor.';
  }
  $counts['pictures']++;
  return Paragraph::create($keep([
    'type' => 'exhibit_figure',
    'field_figure_image' => $ref($id),
    'field_figure_caption' => ($f['caption'] ?? '') !== '' ? $html($f['caption']) : NULL,
    'field_figure_layout' => $layout,
  ]));
};

$block = function (array $b) use (&$block, $figure, $keep, $html, &$counts): ?Paragraph {
  switch ($b['type']) {
    case 'text':
      return Paragraph::create([
        'type' => 'guide_text',
        'field_block_body' => $html($b['body']),
        'field_text_style' => 'default',
      ]);

    case 'figure':
      return $figure($b, $b['layout'] ?? 'center');

    case 'gallery':
      return Paragraph::create($keep([
        'type' => 'exhibit_gallery',
        'field_gallery_items' => array_map(fn ($f) => $figure($f, 'center'), $b['figures']),
        'field_figure_caption' => ($b['caption'] ?? '') !== '' ? $html($b['caption']) : NULL,
      ]));

    case 'entry':
      $counts['entries']++;
      if (!empty($b['summary'])) {
        $counts['plates']++;
      }
      elseif (!$b['blocks']) {
        $counts['headings']++;
      }
      return Paragraph::create($keep([
        'type' => 'exhibit_entry',
        'field_block_title' => $b['title'],
        'field_section_anchor' => $b['anchor'],
        // A text plate’s words on a wall (palefire.theme, _palefire_exhibit_wall()).
        'field_entry_summary' => !empty($b['summary']) && FieldConfig::loadByName('paragraph', 'exhibit_entry', 'field_entry_summary') ? $b['summary'] : NULL,
        'field_guide_blocks' => array_values(array_filter(array_map($block, $b['blocks']))),
      ]));
  }
  return NULL;
};

// Every gallery is a wall with a lead picture, shown whole (the 1996 files
// are at most 600 px), with its caption.
$has = fn (string $name) => (bool) FieldConfig::loadByName('paragraph', 'exhibit_page', $name);
$sections = [];
foreach ($data['pages'] as $page) {
  $before = $counts;
  $lead_id = $media($page['lead'] ?? NULL);
  $lead = $ref($lead_id);
  $sections[] = Paragraph::create($keep([
    'type' => 'exhibit_page',
    'field_section_title' => $page['title'],
    'field_section_anchor' => $page['anchor'],
    'field_exhibit_lead' => $lead,
    'field_exhibit_lead_focus' => $lead ? ($page['focus'] ?? 'center') : NULL,
    'field_exhibit_lead_whole' => $lead && $has('field_exhibit_lead_whole') ? 1 : NULL,
    'field_exhibit_lead_caption' => $lead && !empty($page['lead_caption']) && $has('field_exhibit_lead_caption') ? $html($page['lead_caption']) : NULL,
    'field_exhibit_display' => $has('field_exhibit_display') ? ($page['display'] ?? 'wall') : NULL,
    'field_guide_blocks' => array_values(array_filter(array_map($block, $page['blocks']))),
  ]));
  printf('Gallery: %s (%d entries: %d text plates, %d sub-gallery headings; %d pictures)' . PHP_EOL,
    $page['title'],
    $counts['entries'] - $before['entries'],
    $counts['plates'] - $before['plates'],
    $counts['headings'] - $before['headings'],
    $counts['pictures'] - $before['pictures']);
}

// The page. ------------------------------------------------------------------

$cover_id = $media($data['cover']);
$label = $data['cover_label'];
$values = [
  'type' => 'modular_page',
  'title' => $title,
  'status' => 0,
  'field_scroll_section' => $sections,
  'field_modular_type' => 'exhibition',
  'field_exhibit_subtitle' => $data['subtitle'],
  'field_exhibit_credit' => $html($data['credit']),
  'field_exhibit_cover' => $ref($cover_id),
  'field_exhibit_year' => $data['year'],
  // The cover is a 600 px photograph: shown whole over a blur, with its
  // object label.
  'field_exhibit_cover_framed' => 1,
  'field_exhibit_cover_maker' => $label['maker'],
  'field_exhibit_cover_title' => $label['title'],
  'field_exhibit_cover_date' => $label['date'],
  'field_exhibit_cover_medium' => $label['medium'],
];
$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');
foreach (array_keys($values) as $name) {
  if (str_starts_with($name, 'field_') && !isset($node_fields[$name])) {
    $skipped[] = "$name is not on Modular page; run its setup script, or fill it in the editor.";
    unset($values[$name]);
  }
}
$alias = '/' . ltrim($args['path'] ?? '/v2/special/exhibitions/british-voices', '/');
$taken = \Drupal::service('path_alias.repository')->lookupByAlias($alias, 'en');
if ($taken) {
  $skipped[] = "The alias $alias is already used by {$taken['path']}; the page gets none. Set it in the editor.";
}
else {
  $values['path'] = ['alias' => $alias];
}

printf('%d galleries, %d entries (%d text plates), %d pictures; %d new images to import.' . PHP_EOL,
  count($sections), $counts['entries'], $counts['plates'], $counts['pictures'], $imported);

if ($apply) {
  $node = Node::create(array_filter($values, fn ($v) => $v !== NULL));
  $node->setRevisionLogMessage('Exhibition: British Voices from South Asia, from the 1996 site (palefire_exhibit_british_voices.php).');
  $node->save();
  echo 'Created unpublished exhibition page: node/' . $node->id() . ' (' . $node->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
}
foreach (array_unique($skipped) as $line) {
  echo 'Skipped: ' . $line . PHP_EOL;
}
echo $apply ? 'Review it, then publish. Run: drush cr' . PHP_EOL : 'Nothing saved.' . PHP_EOL;
