<?php

/**
 * @file
 * Audubon: turns Suggested Reading's list of citations into readings
 * (Exhibition: Reading, typeset covers), on the page that
 * palefire_exhibit_audubon.php built. Run palefire_exhibit_reading_setup.php
 * first, then from the theme folder:
 *
 *   drush php:script scripts/palefire_exhibit_audubon_readings.php -- dry-run=1
 *   drush php:script scripts/palefire_exhibit_audubon_readings.php
 *   drush cr
 *
 * dry-run=1 lists what would change and saves nothing.
 *
 * In the Suggested Reading gallery:
 * - Adds two Image groups after its text, "Selected works" (23 readings:
 *   18 books, 5 articles) and "Fiction" (2 books), with the citations
 *   below: author, title, publisher or journal, details, and the catalog
 *   record or article as the link. Places of publication are left out
 *   (the fields have none); an edition's editor joins the author.
 * - Cuts the text block's list of citations, from "Selected works:" on,
 *   so only its two opening paragraphs (the catalog search and the guide)
 *   stay. If that heading isn't found, the text is left as it is.
 *
 * Saves the page as a new revision: a published page changes at once (the
 * revision can be reverted under the page's Revisions). Stops without
 * changes if the gallery already holds readings, so it is safe to re-run.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;
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
$dry = !empty($args['dry-run']);

foreach (['field_reading_kind', 'field_reading_author', 'field_reading_title', 'field_reading_source', 'field_reading_details', 'field_figure_link'] as $name) {
  if (!FieldConfig::loadByName('paragraph', 'exhibit_reading', $name)) {
    throw new \RuntimeException("$name is not on Exhibition: Reading: run palefire_exhibit_reading_setup.php first.");
  }
}
$items_field = FieldConfig::loadByName('paragraph', 'exhibit_gallery', 'field_gallery_items');
$targets = $items_field ? ($items_field->getSetting('handler_settings')['target_bundles'] ?? []) : NULL;
if ($targets === NULL || ($targets && !isset($targets['exhibit_reading']))) {
  throw new \RuntimeException('Image groups don\'t allow Exhibition: Reading: run palefire_exhibit_reading_setup.php first.');
}

$catalog = fn (int $id): string => "https://lsu.ent.sirsi.net/client/en_US/lsu/search/detailnonmodal/ent:\$002f\$002fSD_LSU\$002f0\$002fSD_LSU:$id/one";

// [kind, author, title, publisher or journal, details, link]: the page's
// citations as of 2026-10-30, in its order.
$groups = [
  'Selected works' => [
    ['book', 'Audubon, John James; Daniel Patterson, ed.', 'John James Audubon’s Journal of 1826: The Voyage to The Birds of America', 'University of Nebraska Press', '2011', $catalog(3687069)],
    ['book', 'Audubon, John James; Daniel Patterson, ed.', 'The Missouri River Journals of John James Audubon', 'University of Nebraska Press', '2016', $catalog(4565388)],
    ['book', 'Blaugrund, Annette', 'The Watercolors for The Birds of America', 'Villard Books', '1993', $catalog(1225694)],
    ['book', 'Butler, Charles T., ed.', 'Audubon’s Last Wilderness Journey: The Viviparous Quadrupeds of North America', 'D Giles Limited; Jule Collins Smith Museum of Fine Art, Auburn University', '2018', $catalog(5743463)],
    ['book', 'Corning, Howard, ed.', 'Letters of John James Audubon, 1826–1840', 'The Club of Odd Volumes', '1930', $catalog(1284522)],
    ['book', 'Davies, Jacqueline', 'The Boy Who Drew Birds: A Story of John James Audubon', 'Houghton Mifflin', '2004', $catalog(2168862)],
    ['book', 'Forkner, Ben, ed.', 'Audubon on Louisiana: Selected Writings of John James Audubon', 'Louisiana State University Press', '2018', $catalog(5570166)],
    ['book', 'Fries, Waldemar H.', 'The Double Elephant Folio: The Story of Audubon’s Birds of America', 'Zenaida Publishing', '2006', $catalog(3260088)],
    ['book', 'Hart-Davis, Duff', 'Audubon’s Elephant', 'Weidenfeld & Nicolson', '2003', $catalog(2289234)],
    ['book', 'Heitman, Danny', 'A Summer of Birds: John James Audubon at Oakley House', 'Louisiana State University Press', '2008', $catalog(2804744)],
    ['article', 'Heitman, Danny', 'John James Audubon and the American Presidency', 'White House History', '34 (Fall 2013): 44–53', 'https://www.whitehousehistory.org/john-james-audubon-and-the-american-presidency'],
    ['article', '', 'John James Audubon, the Naturalist', 'The Illustrated Magazine of Art', '3, no. 17 (1854): 305–07', NULL],
    ['book', 'Lewis, Andrew', 'A Democracy of Facts: Natural History in the Early Republic', 'University of Pennsylvania Press', '2011', NULL],
    ['book', 'Lindsay, Debra', 'Maria Martin’s World: Art & Science, Faith & Family in Audubon’s America', 'The University of Alabama Press', '2018', $catalog(5557953)],
    ['book', 'Low, Susanne M.', 'A Guide to Audubon’s Birds of America: A Concordance Containing Current Names of the Birds, Plate Names with Descriptions of Plate Variants, a Description of the Bien Edition, and Corresponding Indexes', 'William Reese Co. & Donald A. Heald', '2002', $catalog(5338208)],
    ['book', 'Low, Susanne M.', 'An Index and Guide to Audubon’s Birds of America', 'Abbeville Press', '1988', $catalog(647301)],
    ['book', 'Nobles, Gregory', 'John James Audubon: The Nature of the American Woodsman', 'University of Pennsylvania Press', '2017', $catalog(4617987)],
    ['article', 'Olson, Roberta J. M.', 'The ‘Early Birds’ of John James Audubon', 'Master Drawings', '50, no. 4 (2012): 439–94', 'http://www.jstor.org/stable/41703397'],
    ['book', 'Olson, Roberta J. M.', 'Audubon’s Aviary: The Original Watercolors for The Birds of America', 'New-York Historical Society', '2012', $catalog(4045402)],
    ['article', 'Partridge, Linda Dugan', 'By the Book: Audubon and the Tradition of Ornithological Illustration', 'Huntington Library Quarterly', '59, no. 2/3 (1996): 269–301', NULL],
    ['book', 'Prince, Sue Ann, ed.', 'Stuffing Birds, Pressing Plants, Shaping Knowledge: Natural History in North America, 1730–1860', 'American Philosophical Society', '2003', NULL],
    ['article', 'Riely, Elizabeth Gawthrop', 'John James Audubon’s Tastes of America', 'Gastronomica', '11, no. 2 (2011): 29–37', 'https://online.ucpress.edu/gastronomica/article/11/2/29/29907/John-James-Audubon-s-Tastes-of-America'],
    ['book', 'Tyler, Ronnie C.', 'Audubon’s Great National Work: The Royal Octavo Edition of The Birds of America', 'W. Thomas Taylor', '1993', $catalog(1205354)],
  ],
  'Fiction' => [
    ['book', 'Brown, John Gregory', 'Audubon’s Watch: A Novel', 'Houghton Mifflin', '2001', $catalog(1889886)],
    ['book', 'Grolleau, Fabien, and Jérémie Royer', 'Audubon: On the Wings of the World', 'Nobrow', '2016', $catalog(5748227)],
  ],
];

$ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', 'John James Audubon in Louisiana')
  ->execute();
if (count($ids) !== 1) {
  throw new \RuntimeException('Expected one Modular page titled "John James Audubon in Louisiana", found ' . count($ids) . '.');
}
$node = Node::load(reset($ids));

$gallery = NULL;
foreach ($node->get('field_scroll_section')->referencedEntities() as $section) {
  if ($section->bundle() === 'exhibit_page' && trim((string) $section->get('field_section_title')->value) === 'Suggested Reading') {
    $gallery = $section;
    break;
  }
}
if (!$gallery) {
  throw new \RuntimeException('No "Suggested Reading" gallery on the Audubon page.');
}

$blocks = $gallery->get('field_guide_blocks')->referencedEntities();
foreach ($blocks as $block) {
  if ($block->bundle() !== 'exhibit_gallery') {
    continue;
  }
  foreach ($block->get('field_gallery_items')->referencedEntities() as $item) {
    if ($item->bundle() === 'exhibit_reading') {
      echo 'Suggested Reading already has readings: nothing changed.' . PHP_EOL;
      return;
    }
  }
}

$log = [];

// The text block with the citations: keep what comes before "Selected
// works:", and add the groups after it.
$at = count($blocks);
foreach ($blocks as $i => $block) {
  if ($block->bundle() !== 'guide_text') {
    continue;
  }
  $body = $block->get('field_block_body')->first();
  $html = (string) ($body?->value ?? '');
  if (!preg_match('#<p>\s*<strong>\s*Selected works:?\s*</strong>\s*:?\s*</p>#iu', $html, $m, PREG_OFFSET_CAPTURE)) {
    continue;
  }
  $at = $i + 1;
  $kept = rtrim(substr($html, 0, $m[0][1]));
  $block->set('field_block_body', ['value' => $kept, 'format' => $body->format]);
  $block->setNeedsSave(TRUE);
  $log[] = 'Text: cut the list from "Selected works:" on (' . mb_strlen(strip_tags(substr($html, $m[0][1]))) . ' characters), kept ' . substr_count($kept, '<p') . ' paragraph(s)';
  break;
}
if ($at === count($blocks) && !$log) {
  $log[] = 'Text: "Selected works:" not found; the text is unchanged and the groups go at the end';
}

$new = [];
foreach ($groups as $heading => $readings) {
  $items = [];
  foreach ($readings as [$kind, $author, $title, $source, $details, $link]) {
    $items[] = Paragraph::create([
      'type' => 'exhibit_reading',
      'field_reading_kind' => $kind,
      'field_reading_author' => $author,
      'field_reading_title' => $title,
      'field_reading_source' => $source,
      'field_reading_details' => $details,
      'field_figure_link' => $link ? ['uri' => $link] : [],
    ]);
  }
  $new[] = Paragraph::create([
    'type' => 'exhibit_gallery',
    'field_block_title' => $heading,
    'field_gallery_items' => $items,
  ]);
  $kinds = array_count_values(array_column($readings, 0));
  $log[] = "Image group \"$heading\": " . implode(', ', array_map(fn ($k, $n) => "$n {$k}" . ($n > 1 ? 's' : ''), array_keys($kinds), $kinds))
    . ', ' . count(array_filter(array_column($readings, 5))) . ' linked';
}
array_splice($blocks, $at, 0, $new);

foreach ($log as $line) {
  echo ($dry ? '[dry run] ' : '') . $line . PHP_EOL;
}
if ($dry) {
  echo 'Dry run: nothing saved. Run again without dry-run=1 to save.' . PHP_EOL;
  return;
}

$gallery->set('field_guide_blocks', $blocks);
$gallery->setNeedsSave(TRUE);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('Audubon: Suggested Reading as typeset covers, Selected works and Fiction (palefire_exhibit_audubon_readings.php).');
$node->save();
echo 'Saved a new revision of "' . $node->label() . '". Run: drush cr' . PHP_EOL;
