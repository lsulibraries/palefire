<?php

/**
 * @file
 * AVES: fills the draft lead image captions on the existing AVES page.
 *
 * For a site where palefire_exhibit_aves.php ran before lead captions
 * existed. Run palefire_exhibit_setup.php first (it adds the field), then:
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_exhibit_aves_lead_captions.php
 *   drush cr
 *
 * Optional after "--": format=<text format> (default: WYSIWYG); force=1 to
 * fill them even though the page is published (it stops otherwise).
 *
 * The captions are drafts from data/aves.json ("lead_caption"): the online
 * exhibition never captioned these images. Nine of the 16 lead images come
 * from books the exhibition describes, so their citations follow its own
 * catalogue records; each starts with a bold "[Editor: please verify …]"
 * line to check and delete. The other seven (Greenfield, Red capped manakin,
 * the Andes, Buenaventura, Chubb paddling, the ongoing expedition and the
 * Solitary Eagle) need a source and are left decorative.
 *
 * Only fills empty captions, so an editor's text is never overwritten and
 * it is safe to re-run. Saves the page as a new revision.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;

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

if (!FieldConfig::loadByName('paragraph', 'exhibit_page', 'field_exhibit_lead_caption')) {
  throw new \RuntimeException('exhibit_page has no field_exhibit_lead_caption. Run palefire_exhibit_setup.php first.');
}

$data = json_decode((string) file_get_contents(__DIR__ . '/data/aves.json'), TRUE);
$captions = [];
foreach ($data['pages'] ?? [] as $page) {
  if (!empty($page['lead_caption'])) {
    $captions[$page['title']] = $page['lead_caption'];
  }
}
if (!$captions) {
  throw new \RuntimeException("Couldn't read the lead captions from data/aves.json.");
}

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

$ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', 'AVES')
  ->execute();
if (count($ids) !== 1) {
  throw new \RuntimeException('Expected one Modular page titled "AVES", found ' . count($ids) . '.');
}
$node = Node::load(reset($ids));
// The drafts carry visible "[Editor: …]" lines: not on a public page unless
// asked for.
if ($node->isPublished() && empty($args['force'])) {
  throw new \RuntimeException('AVES is published, so the drafts and their "[Editor: …]" lines would be public. Unpublish it first, or re-run with -- force=1.');
}

$filled = 0;
foreach ($node->get('field_scroll_section')->referencedEntities() as $gallery) {
  if ($gallery->bundle() !== 'exhibit_page') {
    continue;
  }
  $title = (string) $gallery->get('field_section_title')->value;
  if (!isset($captions[$title])) {
    continue;
  }
  if (trim(strip_tags((string) $gallery->get('field_exhibit_lead_caption')->value)) !== '') {
    echo "Kept (already has a caption): $title" . PHP_EOL;
    continue;
  }
  $gallery->set('field_exhibit_lead_caption', ['value' => $captions[$title], 'format' => $format]);
  $gallery->setNeedsSave(TRUE);
  $filled++;
  echo "Caption added: $title" . PHP_EOL;
}

if ($filled) {
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage("AVES: $filled draft lead image captions (palefire_exhibit_aves_lead_captions.php).");
  $node->save();
}
echo "Done: $filled caption(s) added. Search the page for \"[Editor:\" to review them. Run: drush cr" . PHP_EOL;
