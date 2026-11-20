<?php

/**
 * @file
 * Exhibition page: "Creole Echoes: The Francophone Music and Literature of
 * Nineteenth-Century New Orleans" (Résonances créoles, Hill Memorial
 * Library, August 20 – November 3, 2001; the web version's thirty
 * Showcase pages under /creole) as an unpublished Modular page in
 * exhibition mode. Run palefire_exhibit_setup.php first.
 *
 * Copy the "creole-masters" folder (147 JPEGs) to the server first, then:
 *
 *   drush php:script scripts/palefire_exhibit_creole.php -- images=$HOME/creole-masters
 *   drush php:script scripts/palefire_exhibit_creole.php -- images=$HOME/creole-masters path=/v2/special/exhibitions/creole apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it reads data/creole.json, checks every
 * picture is in the folder and lists the galleries, and saves nothing.
 *
 * Optional arguments after "--":
 *   images=<folder>       Required. The JPEG masters.
 *   format=<text format>  Text format for the text (default: WYSIWYG).
 *   path=<alias>          URL alias, e.g. path=/v2/special/exhibitions/creole
 *   apply=1               Save.
 *
 * The text, in data/creole.json, is the 2001 exhibition's word for word
 * (the Showcase copies have mis-encoded characters, mended): Salwa
 * Nacouzi's introduction, the twenty cases in fifteen galleries, and the
 * lesson plans as For Teachers. Every gallery is a wall: each case's essay
 * is a text plate (a heading, a Summary and the text), its objects hang
 * after it as entries, and objects of several pages (a manuscript and its
 * printing, five photographs, a run of opera programs) are one entry each.
 * Changes: curly quotes, en and em dashes, typos (the corrections doc,
 * docs/special-exhibition-corrections/creole.md), the "See:" lines set as
 * citations, dead links in the lesson plans removed.
 *
 * Images: the old pages' 139 pictures, at about 300px, and eight rescans
 * from the Louisiana Digital Library's masters (four Mugnier stereographs
 * of the French Quarter, four items from the Sidonie de la Houssaye
 * Collection), each linked to its LDL record. Each is imported once as an
 * Image media item (named "Creole Echoes: <file name>", reused on a
 * re-run) under public://exhibitions/creole.
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

foreach (['exhibit_page', 'exhibit_entry', 'exhibit_figure', 'exhibit_gallery', 'guide_text'] as $id) {
  if (!ParagraphsType::load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_exhibit_setup.php first.");
  }
}
if (!FieldConfig::loadByName('paragraph', 'exhibit_entry', 'field_entry_summary')) {
  throw new \RuntimeException('Exhibit entries have no Summary field. Run palefire_exhibit_bio_setup.php first.');
}

$images_dir = rtrim($args['images'] ?? '', '/\\');
if ($images_dir === '' || !is_dir($images_dir)) {
  throw new \RuntimeException('Pass images=<folder>: the creole-masters folder copied to the server.');
}

$data_file = __DIR__ . '/data/creole.json';
$data = json_decode((string) file_get_contents($data_file), TRUE);
if (!is_array($data) || count($data['pages'] ?? []) !== 17) {
  throw new \RuntimeException("Couldn't read the 17 galleries from $data_file.");
}

$title = $data['title'];
$found = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', $title)
  ->range(0, 1)->execute();
if ($found) {
  echo "A page titled “{$title}” already exists (node " . reset($found) . '). Delete it first to rebuild it. Nothing saved.' . PHP_EOL;
  return;
}

// Every picture the data names, and whether it is in the folder.
$files = [$data['cover']];
$collect = function (array $b) use (&$collect, &$files): void {
  if (!empty($b['image'])) {
    $files[] = $b['image'];
  }
  foreach (array_merge($b['blocks'] ?? [], $b['figures'] ?? []) as $child) {
    $collect($child);
  }
};
foreach ($data['pages'] as $page) {
  $files[] = $page['lead'];
  foreach ($page['blocks'] as $b) {
    $collect($b);
  }
}
$files = array_values(array_unique($files));
$missing = array_values(array_filter($files, fn ($f) => !is_file("$images_dir/$f")));

// A picture of 720p or more (1280 × 720, either way up) fills the screen;
// a smaller one is shown whole over a blurred wash of itself: “Frame the
// cover” on the node, “Show whole image” on a gallery’s lead.
$usable = function (?string $file) use ($images_dir): bool {
  $size = $file ? @getimagesize($images_dir . '/' . $file) : FALSE;
  return $size && max($size[0], $size[1]) >= 1280 && min($size[0], $size[1]) >= 720;
};

$entries = 0;
$plates = 0;
foreach ($data['pages'] as $n => $page) {
  $count = count(array_filter($page['blocks'], fn ($b) => $b['type'] === 'entry'));
  $own = count(array_filter($page['blocks'], fn ($b) => $b['type'] === 'entry' && !empty($b['summary'])));
  $entries += $count;
  $plates += $own;
  echo sprintf('%02d %s: %d text plates, %d objects (lead %s, %s)', $n + 1, $page['title'], $own, $count - $own, $page['lead'], $usable($page['lead']) ? 'fills the screen' : 'shown whole') . PHP_EOL;
}
echo 'Cover ' . $data['cover'] . ($usable($data['cover']) ? ': fills the screen' : ': framed') . PHP_EOL;
echo count($files) . ' pictures, ' . count($missing) . ' missing from ' . $images_dir . PHP_EOL;
foreach ($missing as $file) {
  echo "  Missing: $file" . PHP_EOL;
}
if (!$apply) {
  echo "Dry run: “{$title}” would be one unpublished page, 17 galleries (walls), $plates text plates, " . ($entries - $plates) . ' objects. Nothing saved. Add apply=1 to save.' . PHP_EOL;
  return;
}
if ($missing) {
  echo 'Copy the missing pictures first. Nothing saved.' . PHP_EOL;
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
$destination = 'public://exhibitions/creole';
$fs->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
$media_ids = [];

// One media item per file, reused when it exists ("Creole Echoes: <file>").
$media = function (?string $file) use (&$media_ids, $images_dir, $fs, $destination, $media_type, $source_field, $data, &$skipped): ?int {
  if (!$file) {
    return NULL;
  }
  if (array_key_exists($file, $media_ids)) {
    return $media_ids[$file];
  }
  $name = 'Creole Echoes: ' . pathinfo($file, PATHINFO_FILENAME);
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
      'alt' => mb_substr($data['alt'][$file] ?? '', 0, 512),
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

$figure = function (array $f, string $layout) use ($media, $ref, $keep, $html, &$skipped): Paragraph {
  $id = $media($f['image']);
  if (!$id) {
    $skipped[] = 'No image for "' . $f['image'] . '": the figure keeps its caption; add the image in the editor.';
  }
  return Paragraph::create($keep([
    'type' => 'exhibit_figure',
    'field_figure_image' => $ref($id),
    'field_figure_caption' => ($f['caption'] ?? '') !== '' ? $html($f['caption']) : NULL,
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
      return $figure($b, $b['layout'] ?? 'side_start');

    case 'gallery':
      // Pages of one object: shown in turn in the object view.
      return Paragraph::create($keep([
        'type' => 'exhibit_gallery',
        'field_gallery_items' => array_map(fn ($f) => $figure($f, 'center'), $b['figures']),
        'field_figure_caption' => ($b['caption'] ?? '') !== '' ? $html($b['caption']) : NULL,
      ]));

    case 'entry':
      // A text plate has a Summary and no picture; an object has its
      // picture and no Summary (palefire.theme, _palefire_exhibit_wall()).
      return Paragraph::create($keep([
        'type' => 'exhibit_entry',
        'field_block_title' => $b['title'],
        'field_entry_summary' => $b['summary'] ?? NULL,
        'field_guide_blocks' => array_values(array_filter(array_map($block, $b['blocks']))),
      ]));
  }
  return NULL;
};

// Every gallery is a wall, opening on a lead picture with its caption, shown
// whole when it is under 720p (the 2001 images are about 300px).
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
    'field_exhibit_lead_whole' => $lead && !$usable($page['lead']) && $has('field_exhibit_lead_whole') ? 1 : NULL,
    'field_exhibit_lead_caption' => $lead && !empty($page['lead_caption']) && $has('field_exhibit_lead_caption') ? $html($page['lead_caption']) : NULL,
    'field_exhibit_display' => $has('field_exhibit_display') ? ($page['display'] ?? 'wall') : NULL,
    'field_guide_blocks' => array_values(array_filter(array_map($block, $page['blocks']))),
  ]));
  echo 'Built gallery: ' . $page['title'] . PHP_EOL;
}

// The page. ------------------------------------------------------------------

$label = $data['cover_label'];
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
  // The cover is a stereograph card with its object label, framed only when
  // under 720p (the LDL master is 2560 × 1477, so it fills the screen).
  // Like every node field here, dropped below if the node type lacks it.
  'field_exhibit_cover_framed' => $usable($data['cover']) ? 0 : 1,
  'field_exhibit_cover_maker' => $label['maker'],
  'field_exhibit_cover_title' => $label['title'],
  'field_exhibit_cover_date' => $label['date'],
  'field_exhibit_cover_medium' => $label['medium'],
  'field_exhibit_cover_url' => $label['url'],
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
$node->setRevisionLogMessage('Exhibition: Creole Echoes, from the 2001 web exhibition (palefire_exhibit_creole.php).');
$node->save();

echo 'Imported ' . count(array_filter($media_ids)) . ' pictures.' . PHP_EOL;
echo 'Created unpublished exhibition page: node/' . $node->id() . ' (' . $node->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
foreach (array_unique($skipped) as $line) {
  echo 'Skipped: ' . $line . PHP_EOL;
}
echo 'Review it, then publish. Run: drush cr' . PHP_EOL;
