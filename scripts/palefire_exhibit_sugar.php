<?php

/**
 * @file
 * Exhibition page: "Sugar at LSU: A Chronology" (LSU Libraries Special
 * Collections, 1995, for the bicentennial of commercial sugar production
 * in Louisiana; online at /sugar, Showcase nodes 274–281) as an
 * unpublished Modular page in exhibition mode, every gallery a wall.
 * Run palefire_exhibit_setup.php and palefire_exhibit_bio_setup.php first.
 *
 * Copy the "sugar-web/masters" folder (36 JPEGs) to the server first, then:
 *
 *   drush php:script scripts/palefire_exhibit_sugar.php -- images=$HOME/sugar-masters
 *   drush php:script scripts/palefire_exhibit_sugar.php -- images=$HOME/sugar-masters apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it checks the fields, the images and the
 * alias, and lists what it would build.
 *
 * Optional arguments after "--":
 *   images=<folder>       Required. The JPEG masters.
 *   format=<text format>  Text format for the text (default: WYSIWYG).
 *   path=<alias>          URL alias (default /v2/special/exhibitions/sugar).
 *
 * The text, in data/sugar.json, is the 1995 exhibition’s, word for word
 * but for the fixes in docs/special-exhibition-corrections/sugar.md:
 * each case’s essay is a text plate (heading, written Summary, its
 * paragraphs, then the case’s other items as a list), and its pictures
 * hang after it as entries, so the object view reads plate, pictures,
 * next plate. Bibliography is a Sources list.
 *
 * Images: the old pages had 29, at 600 px. Three are replaced by better
 * scans of the same items in the Louisiana Digital Library (the levee,
 * the 1899 laboratory, the 1907 harvester), made from the LDL export’s
 * JP2 masters; seven items the cases showed without a picture online
 * are added from LDL masters and one from the 2013 Of Kin and Cane scans
 * on R:. Each is imported once as an Image media item (named
 * "Sugar at LSU: <file name>", reused on a re-run) under
 * public://exhibitions/sugar.
 *
 * Creates one Modular page, UNPUBLISHED. It stops if a Modular page with
 * the same title exists, or the alias is taken.
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

foreach (['exhibit_page', 'exhibit_entry', 'exhibit_figure', 'guide_text'] as $id) {
  if (!ParagraphsType::load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_exhibit_setup.php first.");
  }
}
foreach ([['exhibit_entry', 'field_entry_summary'], ['exhibit_page', 'field_exhibit_display']] as [$bundle, $name]) {
  if (!FieldConfig::loadByName('paragraph', $bundle, $name)) {
    throw new \RuntimeException("$bundle has no $name. Run the exhibition setup scripts first.");
  }
}

$images_dir = rtrim($args['images'] ?? '', '/\\');
if ($images_dir === '' || !is_dir($images_dir)) {
  throw new \RuntimeException('Pass images=<folder>: the sugar-web/masters folder copied to the server.');
}

$data_file = __DIR__ . '/data/sugar.json';
$data = json_decode((string) file_get_contents($data_file), TRUE);
if (!is_array($data) || count($data['pages'] ?? []) !== 7) {
  throw new \RuntimeException("Couldn’t read the 7 galleries from $data_file.");
}

$title = $data['title'];
$found = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', $title)
  ->range(0, 1)->execute();
if ($found) {
  throw new \RuntimeException("A page titled '$title' already exists (node " . reset($found) . '). Delete it first to rebuild it.');
}
$alias = '/' . ltrim($args['path'] ?? '/v2/special/exhibitions/sugar', '/');
$taken = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
if ($taken !== $alias) {
  throw new \RuntimeException("The alias $alias is already used by $taken.");
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

// Every image the page uses, checked before anything is made.
$files = [$data['cover']];
foreach ($data['pages'] as $page) {
  $files[] = $page['lead'];
  foreach ($page['blocks'] as $b) {
    if ($b['type'] === 'picture') {
      $files[] = $b['image'];
    }
  }
}
$missing = array_values(array_filter(array_unique($files), fn ($f) => !is_file("$images_dir/$f")));
if ($missing) {
  echo 'Missing from ' . $images_dir . ': ' . implode(', ', $missing) . '. Nothing saved.' . PHP_EOL;
  return;
}
echo count(array_unique($files)) . " images found in $images_dir" . PHP_EOL;

$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];

// Images. --------------------------------------------------------------------

/** @var \Drupal\Core\File\FileSystemInterface $fs */
$fs = \Drupal::service('file_system');
$destination = 'public://exhibitions/sugar';
$media_ids = [];

// One media item per file, reused when it exists ("Sugar at LSU: <file>").
// Only called when saving.
$media = function (string $file) use (&$media_ids, $images_dir, $fs, $destination, $media_type, $source_field, $data): int {
  if (isset($media_ids[$file])) {
    return $media_ids[$file];
  }
  $name = 'Sugar at LSU: ' . pathinfo($file, PATHINFO_FILENAME);
  $existing = \Drupal::entityQuery('media')->accessCheck(FALSE)
    ->condition('bundle', $media_type->id())->condition('name', $name)->range(0, 1)->execute();
  if ($existing) {
    return $media_ids[$file] = (int) reset($existing);
  }
  $path = "$images_dir/$file";
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

// A plate: an imageless, undated entry with a heading and a Summary, its
// paragraphs as text blocks (a text plate on the wall, palefire.theme
// _palefire_exhibit_wall(); it opens in the reading view). A picture: an
// entry with one figure, its title the tile’s label.
$entry = function (array $b) use ($media, $html): Paragraph {
  if ($b['type'] === 'plate') {
    $texts = [];
    foreach ($b['body'] as $part) {
      $part = is_array($part) ? $part : ['html' => $part];
      $texts[] = Paragraph::create([
        'type' => 'guide_text',
        'field_block_body' => $html($part['html']),
        'field_text_style' => $part['style'] ?? 'default',
      ]);
    }
    return Paragraph::create([
      'type' => 'exhibit_entry',
      'field_block_title' => $b['title'],
      'field_section_anchor' => $b['anchor'],
      'field_entry_summary' => $b['summary'],
      'field_guide_blocks' => $texts,
    ]);
  }
  return Paragraph::create([
    'type' => 'exhibit_entry',
    'field_block_title' => $b['title'],
    'field_section_anchor' => $b['anchor'],
    'field_guide_blocks' => [
      Paragraph::create([
        'type' => 'exhibit_figure',
        'field_figure_image' => ['target_id' => $media($b['image'])],
        'field_figure_caption' => $html($b['caption']),
        'field_figure_layout' => 'side_start',
      ]),
    ],
  ]);
};

$has = fn (string $name) => (bool) FieldConfig::loadByName('paragraph', 'exhibit_page', $name);
$plates = 0;
$pictures = 0;
foreach ($data['pages'] as $n => $page) {
  echo sprintf('%d. %s (wall, lead %s%s)', $n + 1, $page['title'], $page['lead'], empty($page['lead_whole']) ? '' : ', shown whole') . PHP_EOL;
  foreach ($page['blocks'] as $b) {
    if ($b['type'] === 'plate') {
      $plates++;
      echo '     plate: ' . $b['title'] . PHP_EOL;
    }
    else {
      $pictures++;
      echo '     picture: ' . $b['title'] . ' (' . $b['image'] . ')' . PHP_EOL;
    }
  }
}
echo "Cover: {$data['cover']}. Alias: $alias" . PHP_EOL;
if (!$apply) {
  echo "Dry run: 7 galleries, $plates text plates, $pictures pictures. Nothing saved. Add apply=1 to save." . PHP_EOL;
  return;
}

$fs->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
$sections = [];
foreach ($data['pages'] as $page) {
  $values = [
    'type' => 'exhibit_page',
    'field_section_title' => $page['title'],
    'field_section_anchor' => $page['anchor'],
    'field_exhibit_lead' => ['target_id' => $media($page['lead'])],
    'field_exhibit_lead_focus' => $page['focus'] ?? 'center',
    'field_exhibit_display' => 'wall',
    'field_guide_blocks' => array_map($entry, $page['blocks']),
  ];
  // Only a lead under 1280×720 is shown whole, framed over a blur of itself;
  // larger leads fill the screen.
  if (!empty($page['lead_whole']) && $has('field_exhibit_lead_whole')) {
    $values['field_exhibit_lead_whole'] = 1;
  }
  if ($has('field_exhibit_lead_caption')) {
    $values['field_exhibit_lead_caption'] = $html($page['lead_caption']);
  }
  $sections[] = Paragraph::create($values);
}

$label = $data['cover_label'];
$values = [
  'type' => 'modular_page',
  'title' => $title,
  'status' => 0,
  'field_scroll_section' => $sections,
  'field_modular_type' => 'exhibition',
  'field_exhibit_subtitle' => $data['subtitle'],
  'field_exhibit_credit' => $html($data['credit']),
  'field_exhibit_cover' => ['target_id' => $media($data['cover'])],
  'field_exhibit_year' => $data['year'],
  // The cover is the LDL master at 2560px, so it fills the screen (not
  // framed), with its object label.
  'field_exhibit_cover_maker' => $label['maker'],
  'field_exhibit_cover_title' => $label['title'],
  'field_exhibit_cover_date' => $label['date'],
  'field_exhibit_cover_medium' => $label['medium'],
  'field_exhibit_cover_url' => $label['url'],
  'path' => ['alias' => $alias],
];
$skipped = [];
$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');
foreach (array_keys($values) as $name) {
  if (str_starts_with($name, 'field_') && !isset($node_fields[$name])) {
    $skipped[] = "$name is not on Modular page; run its setup script, or fill it in the editor.";
    unset($values[$name]);
  }
}

$node = Node::create($values);
$node->setRevisionLogMessage('Exhibition: Sugar at LSU: A Chronology, from the 1995 exhibition’s pages (palefire_exhibit_sugar.php).');
$node->save();

echo 'Created unpublished exhibition page: node/' . $node->id() . ' (' . $node->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
foreach ($skipped as $line) {
  echo 'Skipped: ' . $line . PHP_EOL;
}
echo 'Review it, then publish. Run: drush cr' . PHP_EOL;
