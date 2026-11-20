<?php

/**
 * @file
 * Exhibition page: "Woven Louisiana History: Acadian Textiles" (LSU
 * Libraries Special Collections; two interviews with makers for LSU’s
 * Acadian Handicraft Project, 1942–1962, online at /woven/clark and
 * /woven/romero) as an unpublished Modular page in exhibition mode, at
 * /v2/oralhistory/exhibitions/woven with the oral history exhibitions.
 * Run palefire_exhibit_setup.php first.
 *
 * Copy the "woven-web" folder’s masters/ (3 JPEGs) and audio/ (2 M4A
 * clips) to the server first, then:
 *
 *   drush php:script scripts/palefire_exhibit_woven.php -- images=$HOME/woven-web/masters audio=$HOME/woven-web/audio
 *   drush php:script scripts/palefire_exhibit_woven.php -- images=$HOME/woven-web/masters audio=$HOME/woven-web/audio apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it checks the files and the fields and lists
 * what it would build, saving nothing.
 *
 * Optional arguments after "--":
 *   images=<folder>       Required. The JPEG masters.
 *   audio=<folder>        Required. The M4A clips (AAC 64 kbps mono, -16 LUFS).
 *   format=<text format>  Text format for the text (default: WYSIWYG).
 *   path=<alias>          URL alias (default /v2/oralhistory/exhibitions/woven,
 *                         the old site’s folder name).
 *
 * The text is in data/woven.json: one gallery, In Their Words, a wall
 * (Display "wall") of the two interviews, each a picture tile (the old
 * page’s photograph, the clip and its transcript, and a short biography,
 * which open in the object view), with two text plates between and after
 * them: The Acadian Handicraft Project (from the project records’ finding
 * aid, Mss. 1880) and About the Interviews (the old pages’ source lines).
 * Changes: speaker labels in bold on their own paragraphs, a few
 * mis-heard words and typos fixed, curly quotes, the newspaper’s name
 * (Abbeville Meridional); listed in
 * docs/special-exhibition-corrections/woven.md.
 *
 * Images: Gladys Clark’s portrait from its 2,750 × 4,200 scan for the
 * 2024 Hill exhibition (the old page’s was 750 px), the coton jaune
 * photograph from the same files as the gallery’s lead, and Laura Mae
 * Romero’s 1945 newspaper photograph at the old page’s 233 px (no better
 * copy found). Each is imported once as an Image media item (named
 * "Woven: <file name>", reused on a re-run) under
 * public://exhibitions/woven; the clips go to its audio/ folder.
 *
 * Creates one Modular page, UNPUBLISHED, and with the Redirect module on,
 * a 301 from /v2/special/exhibitions/woven. It stops if a Modular page
 * with the same title exists. Then run
 * palefire_oral_history_exhibit_cards.php for its Category.
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
  throw new \RuntimeException('Pass images=<folder>: the woven-web/masters folder copied to the server.');
}
$audio_dir = rtrim($args['audio'] ?? '', '/\\');
if ($audio_dir === '' || !is_dir($audio_dir)) {
  throw new \RuntimeException('Pass audio=<folder>: the woven-web/audio folder copied to the server.');
}

$data_file = __DIR__ . '/data/woven.json';
$data = json_decode((string) file_get_contents($data_file), TRUE);
if (!is_array($data) || count($data['pages'] ?? []) !== 1) {
  throw new \RuntimeException("Couldn’t read the gallery from $data_file.");
}

$title = $data['title'];
$found = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', $title)
  ->range(0, 1)->execute();
if ($found) {
  throw new \RuntimeException("A page titled '$title' already exists (node " . reset($found) . '). Delete it first to rebuild it.');
}

$alias = '/' . ltrim($args['path'] ?? '/v2/oralhistory/exhibitions/woven', '/');
if (\Drupal::entityTypeManager()->getStorage('path_alias')->loadByProperties(['alias' => $alias])) {
  throw new \RuntimeException("$alias is already taken. Pass path=<alias>.");
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
$destination = 'public://exhibitions/woven';
$audio_destination = "$destination/audio";
if ($apply) {
  foreach ([$destination, $audio_destination] as $dir) {
    $fs->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  }
}

// Images. --------------------------------------------------------------------

$media_ids = [];

// One media item per file, reused when it exists ("Woven: <file>"). A dry
// run only checks the file is there.
$media = function (?string $file) use (&$media_ids, $apply, $prefix, $images_dir, $fs, $destination, $media_type, $source_field, $data, &$skipped): ?int {
  if (!$file) {
    return NULL;
  }
  if (array_key_exists($file, $media_ids)) {
    return $media_ids[$file];
  }
  $name = 'Woven: ' . pathinfo($file, PATHINFO_FILENAME);
  $existing = \Drupal::entityQuery('media')->accessCheck(FALSE)
    ->condition('bundle', $media_type->id())->condition('name', $name)->range(0, 1)->execute();
  if ($existing) {
    echo "{$prefix}Reusing media " . reset($existing) . " for $file" . PHP_EOL;
    return $media_ids[$file] = (int) reset($existing);
  }
  $path = $images_dir . '/' . $file;
  if (!is_file($path)) {
    $skipped[] = "Image not found: $path";
    return $media_ids[$file] = NULL;
  }
  [$width, $height] = getimagesize($path) ?: [NULL, NULL];
  if (!$apply) {
    echo "{$prefix}Would import $file ({$width} × {$height})" . PHP_EOL;
    return $media_ids[$file] = NULL;
  }
  $uri = $fs->copy($path, "$destination/$file", FileExists::Rename);
  $item = File::create(['uri' => $uri, 'status' => 1]);
  $item->save();
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

// Audio. ---------------------------------------------------------------------

$file_ids = [];

// One file per clip, reused when it is already in place (same URI).
$audio_file = function (string $file) use (&$file_ids, $apply, $prefix, $audio_dir, $fs, $audio_destination, &$skipped): ?int {
  if (array_key_exists($file, $file_ids)) {
    return $file_ids[$file];
  }
  $existing = \Drupal::entityQuery('file')->accessCheck(FALSE)
    ->condition('uri', "$audio_destination/$file")->range(0, 1)->execute();
  if ($existing) {
    return $file_ids[$file] = (int) reset($existing);
  }
  $path = $audio_dir . '/' . $file;
  if (!is_file($path)) {
    $skipped[] = "Audio not found: $path";
    return $file_ids[$file] = NULL;
  }
  if (!$apply) {
    echo "{$prefix}Would import $file (" . round(filesize($path) / 1048576, 1) . ' MB)' . PHP_EOL;
    return $file_ids[$file] = NULL;
  }
  $uri = $fs->copy($path, "$audio_destination/$file", FileExists::Rename);
  $item = File::create(['uri' => $uri, 'status' => 1, 'filemime' => 'audio/mp4']);
  $item->save();
  echo "Imported $file" . PHP_EOL;
  return $file_ids[$file] = (int) $item->id();
};

// Blocks. --------------------------------------------------------------------

$ref = fn (?int $id) => $id ? ['target_id' => $id] : NULL;
$keep = fn (array $values) => array_filter($values, fn ($v) => $v !== NULL && $v !== '');
$has_entry = fn (string $name) => (bool) FieldConfig::loadByName('paragraph', 'exhibit_entry', $name);

$block = function (array $b) use (&$block, $apply, $media, $audio_file, $ref, $keep, $html, $has_entry, &$skipped): ?Paragraph {
  switch ($b['type']) {
    case 'text':
      return Paragraph::create([
        'type' => 'guide_text',
        'field_block_body' => $html($b['body']),
        'field_text_style' => $b['style'] ?? 'default',
      ]);

    case 'figure':
      $id = $media($b['image']);
      if (!$id && $apply) {
        $skipped[] = 'No image for "' . $b['image'] . '": the figure keeps its caption; add the image in the editor.';
      }
      return Paragraph::create($keep([
        'type' => 'exhibit_figure',
        'field_figure_image' => $ref($id),
        'field_figure_caption' => $html($b['caption']),
        'field_figure_layout' => $b['layout'] ?? 'side_start',
      ]));

    case 'audio':
      $id = $audio_file($b['file']);
      if (!$id) {
        // The file is required: leave the clip out, listed below.
        if ($apply) {
          $skipped[] = 'No audio for "' . $b['title'] . '": add an Audio block with ' . $b['file'] . ' in the editor.';
        }
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
      foreach (['bio' => 'field_entry_bio', 'summary' => 'field_entry_summary'] as $key => $field) {
        if (!empty($b[$key]) && !$has_entry($field)) {
          $skipped[] = "$field is not on Entry; run palefire_exhibit_bio_setup.php. \"" . $b['title'] . '" opens without it.';
        }
      }
      return Paragraph::create($keep([
        'type' => 'exhibit_entry',
        'field_block_title' => $b['title'],
        'field_section_anchor' => $b['anchor'],
        'field_entry_bio' => !empty($b['bio']) && $has_entry('field_entry_bio') ? $html($b['bio']) : NULL,
        // A text plate’s words on a wall (palefire.theme, _palefire_exhibit_wall()).
        'field_entry_summary' => !empty($b['summary']) && $has_entry('field_entry_summary') ? $b['summary'] : NULL,
        'field_guide_blocks' => array_values(array_filter(array_map($block, $b['blocks']))),
      ]));
  }
  return NULL;
};

// The gallery is a wall with a lead and its caption.
$has = fn (string $name) => (bool) FieldConfig::loadByName('paragraph', 'exhibit_page', $name);
if (!$has('field_exhibit_display')) {
  $skipped[] = 'field_exhibit_display is not on Gallery; run palefire_exhibit_setup.php. The gallery reads as a story.';
}
$sections = [];
foreach ($data['pages'] as $page) {
  $lead = $ref($media($page['lead'] ?? NULL));
  $sections[] = Paragraph::create($keep([
    'type' => 'exhibit_page',
    'field_section_title' => $page['title'],
    'field_section_anchor' => $page['anchor'],
    'field_exhibit_lead' => $lead,
    'field_exhibit_lead_focus' => $page['focus'] ?? 'center',
    'field_exhibit_lead_caption' => !empty($page['lead_caption']) && $has('field_exhibit_lead_caption') ? $html($page['lead_caption']) : NULL,
    'field_exhibit_display' => $has('field_exhibit_display') ? ($page['display'] ?? 'story') : NULL,
    'field_guide_blocks' => array_values(array_filter(array_map($block, $page['blocks']))),
  ]));
  echo "{$prefix}Built gallery: " . $page['title'] . ' (' . count($page['blocks']) . ' entries)' . PHP_EOL;
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
  // The cover is a portrait: shown whole over a blur, with its object label.
  'field_exhibit_cover_framed' => 1,
  'field_exhibit_cover_title' => 'Gladys Clark with handwoven placemats',
  'field_exhibit_cover_date' => '1980s',
  'field_exhibit_cover_medium' => 'Photograph. Gladys Clark Papers, Mss. 4953',
  'path' => ['alias' => $alias],
];
$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');
foreach (array_keys($values) as $name) {
  if (str_starts_with($name, 'field_') && !isset($node_fields[$name])) {
    $skipped[] = "$name is not on Modular page; run palefire_exhibit_setup.php.";
    unset($values[$name]);
  }
}

if (!$apply) {
  echo "{$prefix}Would create the unpublished exhibition page '$title' at $alias." . PHP_EOL;
  foreach (array_unique($skipped) as $line) {
    echo "{$prefix}Problem: $line" . PHP_EOL;
  }
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$node = Node::create(array_filter($values, fn ($v) => $v !== NULL));
$node->setRevisionLogMessage('Exhibition: Woven Louisiana History, from /woven (palefire_exhibit_woven.php).');
$node->save();

// The Special Collections address leads here, as for the other oral
// history exhibitions (palefire_oral_history_exhibit_paths.php).
if (\Drupal::moduleHandler()->moduleExists('redirect')) {
  $redirects = \Drupal::entityTypeManager()->getStorage('redirect');
  $old = 'v2/special/exhibitions/woven';
  if (!$redirects->loadByProperties(['redirect_source__path' => $old])) {
    $redirects->create([
      'redirect_source' => ['path' => $old, 'query' => []],
      'redirect_redirect' => ['uri' => 'internal:/node/' . $node->id()],
      'status_code' => 301,
      'language' => $node->language()->getId(),
    ])->save();
    echo "Redirect: /$old → $alias (301)" . PHP_EOL;
  }
}

echo 'Created unpublished exhibition page: node/' . $node->id() . ' (' . $node->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
foreach (array_unique($skipped) as $line) {
  echo 'Skipped: ' . $line . PHP_EOL;
}
echo 'Review it, then publish. Run: drush cr' . PHP_EOL;
