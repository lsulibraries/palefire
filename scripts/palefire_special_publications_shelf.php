<?php

/**
 * @file
 * Fills "Written at Hill" on the Special Collections homepage with the
 * research guide's whole list, as one shelf of books drawn as deep as their
 * page counts (_tile-showcase.scss, Covers).
 *
 *   drush php:script scripts/palefire_special_publications_shelf.php
 *   drush php:script scripts/palefire_special_publications_shelf.php -- apply=1
 *   drush php:script scripts/palefire_special_publication_covers.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it reports what it would do and saves nothing.
 *
 * What it does:
 * - Adds Pages (field_pub_pages, a whole number) to the Publication
 *   paragraph, after Publisher and year. The theme sets each book's page
 *   edges from it (_palefire_publications()); a book without one is drawn
 *   at 300 pages.
 * - Sets the section's books to the 33 below, the guide's list
 *   (https://guides.lib.lsu.edu/c.php?g=1091798&p=9443015) newest first,
 *   without its one film. A book already in the section is kept (and its
 *   cover with it); the others are added. Each gets its pages and catalog
 *   link from the LSU Libraries catalog record (checked 2026-10-31). Lost
 *   Causes linked to record 7633160, which is The New Era in American
 *   Mathematics; the catalog has no record of Lost Causes, so it and the
 *   other books without one link to a catalog search for them.
 * - Changes the lede's "Recent books by scholars…" (they now go back to
 *   2001) to "Books by scholars…, newest first", unless it was edited.
 * - The covers come next, from palefire_special_publication_covers.php,
 *   which lists them all. Covers already imported ("Cover: <title>" media,
 *   e.g. the three books palefire_special_publications_move.php took out)
 *   are reused.
 *
 * Optional arguments after "--":
 *   apply=1        Save.
 *   page=<alias>   The homepage (default /v2/special).
 *
 * Stops, saving nothing, when the section is missing or the homepage has a
 * newer draft than its published revision.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\paragraphs\Entity\Paragraph;

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
$apply = ($args['apply'] ?? '') === '1';
$alias = '/' . trim($args['page'] ?? '/v2/special', '/');
$title = 'Written at Hill';

$record = 'https://lsu.ent.sirsi.net/client/en_US/lsu/search/detailnonmodal/ent:$002f$002fSD_LSU$002f0$002fSD_LSU:%s/one';
$search = 'https://lsu.ent.sirsi.net/client/en_US/lsu/search/results?qu=%s';

// Title, author, imprint, catalog record (or NULL: a search), pages (or
// NULL), as the catalog record's extent has them.
$books = [
  ['You Are My Sunshine: Jimmie Davis & the Biography of a Song', 'Robert Mann', 'LSU Press, 2025', '8120961', 198],
  ['The Carceral City: Slavery and the Making of Mass Incarceration in New Orleans, 1803–1930', 'John K. Bardes', 'UNC Press, 2024', '7990443', 416],
  ['Mad with Freedom: The Political Economy of Blackness, Insanity, and Civil Rights in the U.S. South, 1840–1940', 'Élodie Edwards-Grossi', 'LSU Press, 2023', '7633226', 228],
  ['Freedom’s Crescent: The Civil War and the Destruction of Slavery in the Lower Mississippi Valley', 'John C. Rodrigue', 'Cambridge University Press, 2023', '7650775', 510],
  ['Into the Bright Sunshine: Young Hubert Humphrey and the Fight for Civil Rights', 'Samuel G. Freedman', 'Oxford University Press, 2023', '7738847', 488],
  ['The 272: The Families Who Were Enslaved and Sold to Build the American Catholic Church', 'Rachel L. Swarns', 'Random House, 2023', '7661748', 330],
  ['Kingfish U: Huey Long and LSU', 'Robert Mann', 'LSU Press, 2023', '7583616', 314],
  ['Lost Causes: Confederate Demobilization and the Making of Veteran Identity', 'Bradley R. Clampitt', 'LSU Press, 2022', NULL, NULL],
  ['The New Era in American Mathematics, 1920–1950', 'Karen Hunger Parshall', 'Princeton University Press, 2022', '7633160', 605],
  ['Escape to the City: Fugitive Slaves in the Antebellum Urban South', 'Viola Franziska Müller', 'UNC Press, 2022', '8236917', 248],
  ['Rough Tactics: Black Performance in Political Spectacles, 1877–1932', 'Mark A. Johnson', 'University Press of Mississippi, 2021', '6797002', 236],
  ['The Ledger and the Chain: How Domestic Slave Traders Shaped America', 'Joshua D. Rothman', 'Basic Books, 2021', '6876126', 491],
  ['Carville’s Cure: Leprosy, Stigma, and the Fight for Justice', 'Pam Fessler', 'Liveright, 2020', NULL, 368],
  ['Louisiana State University and Agricultural and Mechanical College, 1860–1919: A History', 'Paul E. Hoffman', 'LSU Press, 2020', NULL, 832],
  ['A Kingdom of Water: Adaptation and Survival in the Houma Nation', 'J. Daniel d’Oney', 'University of Nebraska Press, 2020', '6535467', 199],
  ['The Frescoes of Conrad Albrizio: Public Murals in the Midcentury South', 'Carolyn A. Bercier', 'LSU Press, 2019', '5734743', 120],
  ['Maryland, My Maryland: Music and Patriotism during the American Civil War', 'James A. Davis', 'University of Nebraska Press, 2019', '5727271', 358],
  ['Accounting for Slavery: Masters and Management', 'Caitlin Rosenthal', 'Harvard University Press, 2019', '5550493', 295],
  ['Native Flora of Louisiana: Watercolor Drawings', 'Margaret Stones', 'LSU Press, 2018', '5570295', 463],
  ['Pistols and Politics: Feuds, Factions, and the Struggle for Order in Louisiana’s Florida Parishes, 1810–1935', 'Samuel C. Hyde Jr.', 'LSU Press, 2018', '5485672', 380],
  ['Beyond Racism and Poverty: The Truck System on Louisiana Plantations and Dutch Peateries, 1865–1920', 'Karin Lurvink', 'Brill, 2018', '5194469', 269],
  ['The Vietnam War: An Intimate History', 'Geoffrey C. Ward', 'Alfred A. Knopf, 2017', '5182815', 612],
  ['Spiritualism in Nineteenth-Century New Orleans: The Life and Times of Henry Louis Rey', 'Melissa Daggett', 'University Press of Mississippi, 2017', '4545573', 201],
  ['Win the Race or Die Trying: Uncle Earl’s Last Hurrah', 'Jack B. McGuire', 'University Press of Mississippi, 2016', '4560052', 285],
  ['The Louisiana Field Guide: Understanding Life in the Pelican State', 'Edited by Ryan Orgera and Wayne Parent', 'LSU Press, 2014', '4152015', 258],
  ['Imagining the Creole City: The Rise of Literary Culture in Nineteenth-Century New Orleans', 'Rien Fertel', 'LSU Press, 2014', '4152571', 203],
  ['The Architecture of LSU', 'J. Michael Desmond', 'LSU Press, 2013', '3875276', 144],
  ['Louisiana Women: Their Lives and Times', 'Edited by Janet Allured and Judith F. Gentry', 'University of Georgia Press, 2009', '3364413', NULL],
  ['Old South Baton Rouge: The Roots of Hope', 'Petra Munro Hendry, Jay D. Edwards and Carolyn Ware', 'University of Louisiana at Lafayette Press, 2009', NULL, 186],
  ['Coolies and Cane: Race, Labor, and Sugar in the Age of Emancipation', 'Moon-Ho Jung', 'Johns Hopkins University Press, 2006', '2294360', 275],
  ['Kingfish: The Reign of Huey P. Long', 'Richard D. White Jr.', 'Random House, 2006', '2289260', 361],
  ['The Sugar Masters: Planters and Slaves in Louisiana’s Cane World, 1820–1860', 'Richard Follett', 'LSU Press, 2005', '2241965', 290],
  ['Reconstruction in the Cane Fields: From Slavery to Free Labor in Louisiana’s Sugar Parishes, 1862–1880', 'John C. Rodrigue', 'LSU Press, 2001', '1871684', 224],
];

$short = static fn (string $full): string => preg_split('/\s*:\s+/u', $full, 2)[0];
$log = [];

// The Pages field. --------------------------------------------------------------
$has_pages = (bool) FieldConfig::loadByName('paragraph', 'publication', 'field_pub_pages');
if (!$has_pages) {
  $log[] = 'Add Pages (field_pub_pages) to the Publication paragraph.';
  if ($apply) {
    if (!FieldStorageConfig::loadByName('paragraph', 'field_pub_pages')) {
      FieldStorageConfig::create([
        'field_name' => 'field_pub_pages',
        'entity_type' => 'paragraph',
        'type' => 'integer',
        'settings' => ['unsigned' => TRUE, 'size' => 'normal'],
      ])->save();
    }
    FieldConfig::create([
      'field_name' => 'field_pub_pages',
      'entity_type' => 'paragraph',
      'bundle' => 'publication',
      'label' => 'Pages',
      'description' => 'Optional. The page count, from the catalog record ("xvi, 198 pages" is 198). The book\'s page edges are drawn as thick as it; without one, as 300 pages.',
      'settings' => ['min' => 1, 'max' => 5000, 'prefix' => '', 'suffix' => ''],
    ])->save();
    $repo = \Drupal::service('entity_display.repository');
    $form = $repo->getFormDisplay('paragraph', 'publication', 'default');
    $imprint = $form->getComponent('field_pub_imprint');
    $form->setComponent('field_pub_pages', [
      'type' => 'number',
      'weight' => ($imprint['weight'] ?? 2) + 1,
      'settings' => ['placeholder' => ''],
    ])->save();
    // The showcase's template reads it itself.
    $repo->getViewDisplay('paragraph', 'publication', 'default')->removeComponent('field_pub_pages')->save();
    $has_pages = TRUE;
    echo 'Added Pages (field_pub_pages) to the Publication paragraph.' . PHP_EOL;
  }
}

// The section. --------------------------------------------------------------------
$path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
if (!preg_match('#^/node/(\d+)$#', $path, $m)) {
  throw new \RuntimeException("No node found at $alias.");
}
$storage = \Drupal::entityTypeManager()->getStorage('node');
$node = $storage->load($m[1]);
if (!$node || !$node->hasField('field_scroll_section')) {
  throw new \RuntimeException("Node {$m[1]} has no field_scroll_section.");
}
$latest = $storage->getLatestRevisionId($node->id());
if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
  throw new \RuntimeException("$alias has a newer draft (revision $latest) than its published revision. Publish or discard it first.");
}
$section = NULL;
foreach ($node->get('field_scroll_section')->referencedEntities() as $candidate) {
  if ($candidate->hasField('field_tile_publications') && $candidate->hasField('field_section_title')
    && $candidate->get('field_section_title')->value === $title) {
    $section = $candidate;
    break;
  }
}
if (!$section) {
  throw new \RuntimeException("No \"$title\" section on $alias. Run palefire_special_publications.php first.");
}

// The books. ------------------------------------------------------------------------
$current = [];
foreach ($section->get('field_tile_publications')->referencedEntities() as $book) {
  $current[$short((string) $book->get('field_pub_title')->value)] = $book;
}

$list = [];
foreach ($books as [$full, $author, $imprint, $id, $pages]) {
  $name = $short($full);
  $last = preg_replace('/^.*\s/u', '', preg_replace('/^Edited by |,.*$| and .*$| Jr\.$/u', '', $author));
  $uri = $id ? sprintf($record, $id) : sprintf($search, rawurlencode("$name $last"));
  $book = $current[$name] ?? NULL;
  if ($book) {
    unset($current[$name]);
    $changes = [];
    if ($book->get('field_pub_link')->uri !== $uri) {
      $changes[] = 'link';
    }
    if ($pages && $has_pages && $book->hasField('field_pub_pages') && (int) $book->get('field_pub_pages')->value !== $pages) {
      $changes[] = 'pages';
    }
    $log[] = "Keep \"$name\"" . ($changes ? ' (' . implode(', ', $changes) . ')' : '') . '.';
    if ($apply) {
      $book->set('field_pub_link', ['uri' => $uri]);
      if ($pages && $book->hasField('field_pub_pages')) {
        $book->set('field_pub_pages', $pages);
      }
      $book->setNeedsSave(TRUE);
    }
  }
  else {
    $log[] = "Add \"$name\", $author ($imprint)" . ($pages ? ", $pages pages" : '') . ($id ? '' : ', catalog search') . '.';
    if ($apply) {
      $values = [
        'type' => 'publication',
        'field_pub_title' => $full,
        'field_pub_author' => $author,
        'field_pub_imprint' => $imprint,
        'field_pub_link' => ['uri' => $uri],
      ];
      if ($pages) {
        $values['field_pub_pages'] = $pages;
      }
      $book = Paragraph::create($values);
    }
  }
  if ($apply) {
    $list[] = ['entity' => $book];
  }
}
foreach (array_keys($current) as $name) {
  $log[] = "Take out \"$name\": not on the guide's list.";
}

// The lede said "Recent books"; the shelf now goes back to 2001.
$lede = 'Books by scholars whose research began in Hill’s reading room, newest first, each linked to the LSU Libraries catalog.';
$old_lede = $section->hasField('field_tile_lede') ? (string) $section->get('field_tile_lede')->value : '';
if (str_starts_with($old_lede, 'Recent books')) {
  $log[] = "Lede: \"$lede\"";
  if ($apply) {
    $section->set('field_tile_lede', $lede);
  }
}

foreach ($log as $line) {
  echo ($apply ? '' : '[dry run] ') . $line . PHP_EOL;
}
echo count($books) . ' books.' . PHP_EOL;
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$section->set('field_tile_publications', $list);
$section->setNeedsSave(TRUE);
$items = $node->get('field_scroll_section')->getValue();
foreach ($items as &$item) {
  if ((int) ($item['target_id'] ?? 0) === (int) $section->id()) {
    $item = ['entity' => $section];
  }
}
unset($item);
$node->set('field_scroll_section', $items);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage("Special Collections: \"$title\", the guide's " . count($books) . ' books with their page counts (palefire_special_publications_shelf.php).');
$node->save();
echo "Saved a new revision of \"{$node->label()}\". Next: palefire_special_publication_covers.php -- apply=1, then drush cr" . PHP_EOL;
