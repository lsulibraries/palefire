<?php

/**
 * @file
 * Exhibition page: "Free People of Color in Louisiana: Revealing an Unknown
 * Past" (LSU Libraries Special Collections and four partners, an NEH grant
 * project, 2013–2015; the static site at /sites/all/files/sc/fpoc and the
 * twelve Showcase pages under /fpoc) as an unpublished Modular page in
 * exhibition mode. Run palefire_exhibit_setup.php first.
 *
 * Copy the "fpoc-web/masters" folder (12 JPEGs, up to 2560 px) to the
 * server first, then:
 *
 *   drush php:script scripts/palefire_exhibit_fpoc.php -- images=$HOME/fpoc-masters path=/v2/special/exhibitions/fpoc
 *   drush cr
 *
 * Optional arguments after "--":
 *   images=<folder>       Required. The JPEG masters.
 *   format=<text format>  Text format for the text (default: WYSIWYG).
 *   path=<alias>          URL alias, e.g. path=/v2/special/exhibitions/fpoc
 *
 * The text, in data/fpoc.json, is word for word from the 2015 static
 * pages (the Showcase copies have mis-encoded characters): the history in
 * seven galleries, by Michael Taylor; the Collections, one entry per
 * partner; Terminology; Resources (the bibliography as Sources); and About
 * the Project with its grant team. Changes: curly quotes, en dashes in
 * number ranges, eight typos (README), the grant team without its 2015
 * e-mail addresses, partner links that no longer work replaced by each
 * partner's research center, LSU catalog links sent to Combined Search.
 *
 * Images: the old pages had four, at 250 px, three of them from The
 * Historic New Orleans Collection and not in the digital collection. These
 * twelve are new, all from the project's own collection in the Louisiana
 * Digital Library, made from its 4,000 px JP2 masters (the LDL export on
 * L:), each captioned with its source and a link to its LDL record. Each
 * is imported once as an Image media item (named "FPOC: <file name>",
 * reused on a re-run) under public://exhibitions/fpoc. Missing files are
 * listed at the end.
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

foreach (['exhibit_page', 'exhibit_entry', 'exhibit_figure', 'guide_text'] as $id) {
  if (!ParagraphsType::load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_exhibit_setup.php first.");
  }
}

$images_dir = rtrim($args['images'] ?? '', '/\\');
if ($images_dir === '' || !is_dir($images_dir)) {
  throw new \RuntimeException('Pass images=<folder>: the fpoc-web/masters folder copied to the server.');
}

$data_file = __DIR__ . '/data/fpoc.json';
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

// Images. --------------------------------------------------------------------

/** @var \Drupal\Core\File\FileSystemInterface $fs */
$fs = \Drupal::service('file_system');
$destination = 'public://exhibitions/fpoc';
$fs->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
$media_ids = [];

// One media item per file, reused when it exists ("FPOC: <file>").
$media = function (?string $file) use (&$media_ids, $images_dir, $fs, $destination, $media_type, $source_field, $data, &$skipped): ?int {
  if (!$file) {
    return NULL;
  }
  if (array_key_exists($file, $media_ids)) {
    return $media_ids[$file];
  }
  $name = 'FPOC: ' . pathinfo($file, PATHINFO_FILENAME);
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
  echo "Imported $file" . PHP_EOL;
  return $media_ids[$file] = (int) $entity->id();
};

// Blocks. --------------------------------------------------------------------

$ref = fn (?int $id) => $id ? ['target_id' => $id] : NULL;
$keep = fn (array $values) => array_filter($values, fn ($v) => $v !== NULL && $v !== '');

$block = function (array $b) use (&$block, $media, $ref, $keep, $html, &$skipped): ?Paragraph {
  switch ($b['type']) {
    case 'text':
      return Paragraph::create([
        'type' => 'guide_text',
        'field_block_body' => $html($b['body']),
        'field_text_style' => $b['style'] ?? 'default',
      ]);

    case 'figure':
      $id = $media($b['image']);
      if (!$id) {
        $skipped[] = 'No image for "' . $b['image'] . '": the figure keeps its caption; add the image in the editor.';
      }
      return Paragraph::create($keep([
        'type' => 'exhibit_figure',
        'field_figure_image' => $ref($id),
        'field_figure_caption' => $html($b['caption']),
        'field_figure_layout' => $b['layout'] ?? 'side_end',
      ]));

    case 'entry':
      return Paragraph::create($keep([
        'type' => 'exhibit_entry',
        'field_block_title' => $b['title'],
        'field_section_anchor' => $b['anchor'],
        'field_guide_blocks' => array_values(array_filter(array_map($block, $b['blocks']))),
      ]));
  }
  return NULL;
};

// Each lead is one of the collection's documents, shown whole (a page of
// handwriting cropped to a band reads as texture), with its caption.
$has = fn (string $name) => (bool) FieldConfig::loadByName('paragraph', 'exhibit_page', $name);
$sections = [];
foreach ($data['pages'] as $page) {
  $lead = $ref($media($page['lead'] ?? NULL));
  $sections[] = Paragraph::create($keep([
    'type' => 'exhibit_page',
    'field_section_title' => $page['title'],
    'field_section_anchor' => $page['anchor'],
    'field_exhibit_lead' => $lead,
    'field_exhibit_lead_focus' => $lead ? ($page['focus'] ?? 'center') : NULL,
    'field_exhibit_lead_whole' => $lead && $has('field_exhibit_lead_whole') ? 1 : NULL,
    'field_exhibit_lead_caption' => $lead && !empty($page['lead_caption']) && $has('field_exhibit_lead_caption') ? $html($page['lead_caption']) : NULL,
    'field_exhibit_display' => $has('field_exhibit_display') ? 'story' : NULL,
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
  'field_exhibit_subtitle' => $data['subtitle'],
  'field_exhibit_credit' => $html($data['credit']),
  'field_exhibit_cover' => $ref($media($data['cover'])),
  'field_exhibit_year' => $data['year'],
  // The cover is a letter: shown whole over a blur, with its object label.
  'field_exhibit_cover_framed' => 1,
  'field_exhibit_cover_maker' => 'A. Sampson',
  'field_exhibit_cover_title' => 'Letter introducing the sculptor Eugène Warburg',
  'field_exhibit_cover_date' => '1852',
  'field_exhibit_cover_medium' => 'Ink on paper. The Historic New Orleans Collection',
  'field_exhibit_cover_url' => 'https://louisianadigitallibrary.org/islandora/object/fpoc-p16313coll51:32095',
];
$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');
foreach (array_keys($values) as $name) {
  if (str_starts_with($name, 'field_') && !isset($node_fields[$name])) {
    $skipped[] = "$name is not on Modular page; run its setup script, or fill it in the editor.";
    unset($values[$name]);
  }
}
if (!empty($args['path'])) {
  $values['path'] = ['alias' => '/' . ltrim($args['path'], '/')];
}

$node = Node::create(array_filter($values, fn ($v) => $v !== NULL));
$node->setRevisionLogMessage('Exhibition: Free People of Color in Louisiana, from the 2015 site (palefire_exhibit_fpoc.php).');
$node->save();

echo 'Created unpublished exhibition page: node/' . $node->id() . ' (' . $node->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
foreach (array_unique($skipped) as $line) {
  echo 'Skipped: ' . $line . PHP_EOL;
}
echo 'Review it, then publish. Run: drush cr' . PHP_EOL;
