<?php

/**
 * @file
 * Exhibition page: "LSU Union Art Gallery: Building Community Through Art,
 * 1964-2016" (the six-page showcase at /lsu-unionartgallery on libstage) as
 * an unpublished Modular page in exhibition mode. Run
 * palefire_exhibit_setup.php first.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_unionartgallery.php -- path=/v2/special/exhibitions/unionartgallery
 *   drush cr
 *
 * Optional arguments after "--":
 *   format=<text format>  Text format for the text (default: WYSIWYG).
 *   path=<alias>          URL alias, e.g. path=/v2/special/exhibitions/unionartgallery
 *   from=<site>           Where to download an image the server doesn't have
 *                         on disk (default: https://lib.lsu.edu).
 *
 * The text, in data/unionartgallery.json, is word for word from the
 * showcase: the introduction, then one gallery per page (Studio Art,
 * Louisiana Artists, Interdisciplinary Collaboration, Celebration of Books,
 * Visiting Artists) of one entry per show, each with its image group.
 * Changes: the old pages' mis-encoded characters decoded (UTF-8 saved as
 * Latin-1 up to four times over: "DufourÃÂÃÂ¢ÃÂÃÂÃÂÃÂs" is "Dufour’s"),
 * curly apostrophes and quotes, no trailing periods on captions, and four
 * typos (README).
 *
 * Images: the showcase's 91 files are already on the server, under
 * sites/all/files/sc/exhibitions/undergrad_projects/Blandino_Project and
 * sites/default/files/2025-12 (the page covers). Each is imported once as
 * an Image media item (named "Union Art Gallery: <file name>", reused on a
 * re-run) under public://exhibitions/unionartgallery, with the old page's
 * alt text. A file missing on disk is downloaded from from=; any still
 * missing are listed at the end.
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

foreach (['exhibit_page', 'exhibit_entry', 'exhibit_figure', 'exhibit_gallery', 'guide_text'] as $id) {
  if (!ParagraphsType::load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_exhibit_setup.php first.");
  }
}

$from = rtrim($args['from'] ?? 'https://lib.lsu.edu', '/');

$data_file = __DIR__ . '/data/unionartgallery.json';
$data = json_decode((string) file_get_contents($data_file), TRUE);
if (!is_array($data) || count($data['pages'] ?? []) !== 6) {
  throw new \RuntimeException("Couldn't read the 6 galleries from $data_file.");
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

// Alt text for every file, from the figures and the leads.
$alts = [$data['cover'] => $data['cover_alt']];
$collect = function (array $blocks) use (&$collect, &$alts): void {
  foreach ($blocks as $b) {
    foreach ($b['type'] === 'gallery' ? $b['figures'] : [$b] as $f) {
      if (!empty($f['image'])) {
        $alts[$f['image']] = $f['alt'];
      }
    }
    if ($b['type'] === 'entry') {
      $collect($b['blocks']);
    }
  }
};
foreach ($data['pages'] as $page) {
  $alts[$page['lead']] = $page['lead_alt'];
  $collect($page['blocks']);
}

// Images. --------------------------------------------------------------------

/** @var \Drupal\Core\File\FileSystemInterface $fs */
$fs = \Drupal::service('file_system');
$destination = 'public://exhibitions/unionartgallery';
$fs->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
$media_ids = [];

// One media item per file, reused when it exists. $relative is the file's
// path under the web root, as the old pages linked it.
$media = function (?string $relative) use (&$media_ids, $fs, $destination, $media_type, $source_field, $alts, $from, &$skipped): ?int {
  if (!$relative) {
    return NULL;
  }
  if (array_key_exists($relative, $media_ids)) {
    return $media_ids[$relative];
  }
  $base = pathinfo($relative, PATHINFO_FILENAME);
  $name = 'Union Art Gallery: ' . $base;
  $existing = \Drupal::entityQuery('media')->accessCheck(FALSE)
    ->condition('bundle', $media_type->id())->condition('name', $name)->range(0, 1)->execute();
  if ($existing) {
    return $media_ids[$relative] = (int) reset($existing);
  }
  $safe = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($base)), '-') . '.jpg';
  $path = DRUPAL_ROOT . '/' . $relative;
  if (is_file($path)) {
    $uri = $fs->copy($path, "$destination/$safe", FileExists::Rename);
  }
  else {
    $url = $from . '/' . implode('/', array_map('rawurlencode', explode('/', $relative)));
    try {
      $bytes = (string) \Drupal::httpClient()->get($url, ['timeout' => 30])->getBody();
    }
    catch (\Throwable $e) {
      $bytes = '';
    }
    if ($bytes === '' || @getimagesizefromstring($bytes) === FALSE) {
      $skipped[] = "Image not found on disk or at $url";
      return $media_ids[$relative] = NULL;
    }
    $uri = $fs->saveData($bytes, "$destination/$safe", FileExists::Rename);
  }
  $file = File::create(['uri' => $uri, 'status' => 1]);
  $file->save();
  [$width, $height] = getimagesize($fs->realpath($uri)) ?: [NULL, NULL];
  $item = Media::create([
    'bundle' => $media_type->id(),
    'name' => $name,
    'status' => 1,
    $source_field => [
      'target_id' => $file->id(),
      'alt' => $alts[$relative] ?? '',
      'width' => $width,
      'height' => $height,
    ],
  ]);
  $item->save();
  echo "Imported $relative" . PHP_EOL;
  return $media_ids[$relative] = (int) $item->id();
};

// Blocks. --------------------------------------------------------------------

$ref = fn (?int $id) => $id ? ['target_id' => $id] : NULL;
$keep = fn (array $values) => array_filter($values, fn ($v) => $v !== NULL && $v !== '');

$figure = function (array $b, string $layout) use ($media, $ref, $keep, $html, &$skipped): Paragraph {
  $id = $media($b['image'] ?? NULL);
  if (!$id) {
    $skipped[] = 'No image for "' . $b['image'] . '": the figure keeps its caption; add the image in the editor.';
  }
  return Paragraph::create($keep([
    'type' => 'exhibit_figure',
    'field_figure_image' => $ref($id),
    'field_figure_caption' => ($b['caption'] ?? '') !== '' ? $html($b['caption']) : NULL,
    'field_figure_layout' => $layout,
  ]));
};

$block = function (array $b) use (&$block, $figure, $keep, $html): ?Paragraph {
  switch ($b['type']) {
    case 'text':
      return Paragraph::create([
        'type' => 'guide_text',
        'field_block_body' => $html($b['body']),
        'field_text_style' => $b['style'] ?? 'default',
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
      return Paragraph::create($keep([
        'type' => 'exhibit_entry',
        'field_block_title' => $b['title'],
        'field_section_anchor' => $b['anchor'],
        'field_entry_bio' => $b['bio'] !== '' ? $html($b['bio']) : NULL,
        'field_guide_blocks' => array_values(array_filter(array_map($block, $b['blocks']))),
      ]));
  }
  return NULL;
};

// Each lead is the old page's cover, with its alt text as the caption.
// Display "wall" for the five galleries of shows (data: "display").
$has_caption = (bool) FieldConfig::loadByName('paragraph', 'exhibit_page', 'field_exhibit_lead_caption');
$has_display = (bool) FieldConfig::loadByName('paragraph', 'exhibit_page', 'field_exhibit_display');
$sections = [];
foreach ($data['pages'] as $page) {
  $sections[] = Paragraph::create($keep([
    'type' => 'exhibit_page',
    'field_section_title' => $page['title'],
    'field_section_anchor' => $page['anchor'],
    'field_exhibit_lead' => $ref($media($page['lead'] ?? NULL)),
    'field_exhibit_lead_focus' => $page['focus'] ?? 'center',
    'field_exhibit_lead_caption' => !empty($page['lead_caption']) && $has_caption ? $html($page['lead_caption']) : NULL,
    'field_exhibit_display' => $has_display ? ($page['display'] ?? 'story') : NULL,
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
  'field_exhibit_year' => $data['year'],
  'field_exhibit_physical_note' => $data['physical_note'] ?: NULL,
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
$node->setRevisionLogMessage('Exhibition: LSU Union Art Gallery, from the libstage showcase (palefire_exhibit_unionartgallery.php).');
$node->save();

echo 'Created unpublished exhibition page: node/' . $node->id() . ' (' . $node->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
foreach (array_unique($skipped) as $line) {
  echo 'Skipped: ' . $line . PHP_EOL;
}
echo 'Review it, then publish. Run: drush cr' . PHP_EOL;
