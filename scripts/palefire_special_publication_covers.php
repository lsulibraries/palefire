<?php

/**
 * @file
 * Adds the book covers to "Written at Hill" on the Special Collections
 * homepage (palefire_special_publications.php), replacing the typeset
 * jackets.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_special_publication_covers.php
 *   drush php:script themes/custom/palefire/scripts/palefire_special_publication_covers.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it reports what it would do and saves nothing.
 *
 * Optional arguments after "--":
 *   apply=1          Save.
 *   images=<folder>  Read the covers from this folder (the files named below,
 *                    e.g. kingfish-u.jpg) instead of downloading them. Use it
 *                    when the server can't reach the sources.
 *   page=<alias>     The homepage (default /v2/special).
 *   replace=1        Replace covers a book already has (default: leave them).
 *
 * Sources: the research guide's own covers where they are full size (Amazon's
 * originals, 1707 x 2560), else the largest found: LSU Press and Bookshop.org
 * (Ingram), at least 660 wide; for the rest of the guide's list (added
 * 2026-10-31), the largest of the publisher, Bookshop.org and Amazon. Four
 * older books have nothing over 323-432 wide (Reconstruction in the Cane
 * Fields, The Louisiana Field Guide, Louisiana Women, Coolies and Cane),
 * still sharp at the shelf's 176px. Old South Baton Rouge has none and
 * keeps its typeset jacket. Each file becomes an Image media item named
 * "Cover: <title>" (alt "Cover of <title>"), in public://publications, and
 * is reused on a re-run. The card shows covers at most about 176px wide, so
 * publication_cover (480 wide) is what visitors download.
 *
 * It stops, saving nothing, when "Written at Hill" isn't on the page, or the
 * homepage has a newer draft than its published revision.
 */

declare(strict_types=1);

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

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
$replace = ($args['replace'] ?? '') === '1';
$images_dir = isset($args['images']) ? rtrim($args['images'], '/') : NULL;
$alias = '/' . trim($args['page'] ?? '/v2/special', '/');
$section_title = 'Written at Hill';

// Short title (as field_pub_title starts) => file, source.
$covers = [
  'You Are My Sunshine' => ['you-are-my-sunshine.jpg', 'https://m.media-amazon.com/images/I/91UWfltXSvL.jpg'],
  'The Carceral City' => ['the-carceral-city.jpg', 'https://images-us.bookshop.org/ingram/9781469678184.jpg?height=2000&v=v2'],
  'Mad with Freedom' => ['mad-with-freedom.jpg', 'https://m.media-amazon.com/images/I/81df41aQBQL.jpg'],
  'Freedom’s Crescent' => ['freedoms-crescent.jpg', 'https://images-us.bookshop.org/ingram/9781108424097.jpg?height=2000&v=v2'],
  'Into the Bright Sunshine' => ['into-the-bright-sunshine.jpg', 'https://images-us.bookshop.org/ingram/9780197535196.jpg?height=2000&v=v2'],
  'The 272' => ['the-272.jpg', 'https://images-us.bookshop.org/ingram/9780399590887.jpg?height=2000&v=v2'],
  'Kingfish U' => ['kingfish-u.jpg', 'https://m.media-amazon.com/images/I/81xjSdETuQL.jpg'],
  'Lost Causes' => ['lost-causes.jpg', 'https://lsu-press-us.imgix.net/covers/9780807177167.jpg'],
  // The rest of the guide's list (palefire_special_publications_shelf.php).
  'The New Era in American Mathematics' => ['the-new-era-in-american-mathematics.jpg', 'https://pup-assets.imgix.net/onix/images/9780691197555.jpg'],
  'Escape to the City' => ['escape-to-the-city.jpg', 'https://images-us.bookshop.org/ingram/9781469671079.jpg?height=2000&v=v2'],
  'Rough Tactics' => ['rough-tactics.jpg', 'https://m.media-amazon.com/images/I/71ZVk9i3ZyS.jpg'],
  'The Ledger and the Chain' => ['the-ledger-and-the-chain.jpg', 'https://images-us.bookshop.org/ingram/9781541616592.jpg?height=2000&v=v2'],
  'Carville’s Cure' => ['carvilles-cure.jpg', 'https://images-us.bookshop.org/ingram/9781631495045.jpg?height=2000&v=v2'],
  'Louisiana State University and Agricultural and Mechanical College' => ['lsu-1860-1919.jpg', 'https://lsu-press-us.imgix.net/covers/9780807170717.jpg'],
  'A Kingdom of Water' => ['a-kingdom-of-water.jpg', 'https://unebraskapress-us.imgix.net/covers/9781496218797.jpg'],
  'The Frescoes of Conrad Albrizio' => ['the-frescoes-of-conrad-albrizio.jpg', 'https://lsu-press-us.imgix.net/covers/9780807171028.jpg'],
  'Maryland, My Maryland' => ['maryland-my-maryland.jpg', 'https://m.media-amazon.com/images/I/81fDMGE7lGL.jpg'],
  'Accounting for Slavery' => ['accounting-for-slavery.jpg', 'https://images-us.bookshop.org/ingram/9780674241657.jpg?height=2000&v=v2'],
  'Native Flora of Louisiana' => ['native-flora-of-louisiana.jpg', 'https://lsu-press-us.imgix.net/covers/9780807170236.jpg'],
  'Pistols and Politics' => ['pistols-and-politics.jpg', 'https://lsu-press-us.imgix.net/covers/9780807169278.jpg'],
  'Beyond Racism and Poverty' => ['beyond-racism-and-poverty.jpg', 'https://images-us.bookshop.org/ingram/9789004447172.jpg?height=2000&v=v2'],
  'The Vietnam War' => ['the-vietnam-war.jpg', 'https://images-us.bookshop.org/ingram/9780307700254.jpg?height=2000&v=v2'],
  'Spiritualism in Nineteenth-Century New Orleans' => ['spiritualism-in-nineteenth-century-new-orleans.jpg', 'https://images-us.bookshop.org/ingram/9781496810090.jpg?height=2000&v=v2'],
  'Win the Race or Die Trying' => ['win-the-race-or-die-trying.jpg', 'https://images-us.bookshop.org/ingram/9781496807649.jpg?height=2000&v=v2'],
  'The Louisiana Field Guide' => ['the-louisiana-field-guide.jpg', 'https://m.media-amazon.com/images/I/517rHtsMKoL.jpg'],
  'Imagining the Creole City' => ['imagining-the-creole-city.jpg', 'https://lsu-press-us.imgix.net/covers/9780807158234.jpg'],
  'The Architecture of LSU' => ['the-architecture-of-lsu.jpg', 'https://lsu-press-us.imgix.net/covers/9780807149768.jpg'],
  'Louisiana Women' => ['louisiana-women.jpg', 'https://m.media-amazon.com/images/I/41C7znatzAL.jpg'],
  'Coolies and Cane' => ['coolies-and-cane.jpg', 'https://m.media-amazon.com/images/I/41fe2QFsG3L.jpg'],
  'Kingfish: The Reign' => ['kingfish-the-reign-of-huey-p-long.jpg', 'https://m.media-amazon.com/images/I/6198hWY38YL.jpg'],
  'The Sugar Masters' => ['the-sugar-masters.jpg', 'https://lsu-press-us.imgix.net/covers/9780807132470.jpg'],
  'Reconstruction in the Cane Fields' => ['reconstruction-in-the-cane-fields.jpg', 'https://lsu-press-us.imgix.net/covers/9780807127285.jpg'],
];

// The Image media type, as palefire_publications_setup.php picks it. ---------
$media_type = NULL;
foreach (\Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple() as $type) {
  if ($type->getSource()->getPluginId() === 'image' && (!$media_type || $type->id() === 'image')) {
    $media_type = $type;
  }
}
if (!$media_type) {
  throw new \RuntimeException('No Image media type found (Structure > Media types, source "Image").');
}
$source_field = $media_type->getSource()->getSourceFieldDefinition($media_type)->getName();

// The section. -----------------------------------------------------------------
$path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
if (!preg_match('#^/node/(\d+)$#', $path, $m)) {
  throw new \RuntimeException("No node found at $alias.");
}
$storage = \Drupal::entityTypeManager()->getStorage('node');
$node = $storage->load($m[1]);
$latest = $storage->getLatestRevisionId($node->id());
if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
  throw new \RuntimeException("$alias has a newer draft (revision $latest) than its published revision. Publish or discard it first.");
}
$section = NULL;
foreach ($node->get('field_scroll_section')->referencedEntities() as $candidate) {
  if ($candidate->hasField('field_tile_publications') && $candidate->hasField('field_section_title')
    && $candidate->get('field_section_title')->value === $section_title) {
    $section = $candidate;
    break;
  }
}
if (!$section) {
  throw new \RuntimeException("No \"$section_title\" section on $alias. Run palefire_special_publications.php first.");
}

// Which books get which cover. ---------------------------------------------------
$plan = [];
foreach ($section->get('field_tile_publications')->referencedEntities() as $book) {
  $title = (string) $book->get('field_pub_title')->value;
  $match = NULL;
  foreach ($covers as $short => $cover) {
    if (str_starts_with($title, $short)) {
      $match = $cover;
      break;
    }
  }
  if (!$match) {
    echo "No cover listed for \"$title\": left as it is." . PHP_EOL;
    continue;
  }
  if (!$book->get('field_pub_cover')->isEmpty() && !$replace) {
    echo "\"$title\" already has a cover: left as it is (replace=1 to replace)." . PHP_EOL;
    continue;
  }
  $plan[] = [$book, $title, ...$match];
  echo ($apply ? '' : '[dry run] ') . "Cover for \"$title\": " . ($images_dir ? "$images_dir/{$match[0]}" : $match[1]) . PHP_EOL;
}
if (!$plan) {
  echo 'Nothing to do.' . PHP_EOL;
  return;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

// Import. ---------------------------------------------------------------------------
/** @var \Drupal\Core\File\FileSystemInterface $fs */
$fs = \Drupal::service('file_system');
$destination = 'public://publications';
$fs->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
$changed = 0;

foreach ($plan as [$book, $title, $file_name, $url]) {
  $short = preg_split('/\s*:\s+/u', $title, 2)[0];
  $name = "Cover: $short";
  $existing = \Drupal::entityQuery('media')->accessCheck(FALSE)
    ->condition('bundle', $media_type->id())->condition('name', $name)->range(0, 1)->execute();
  if ($existing) {
    $media_id = (int) reset($existing);
  }
  else {
    if ($images_dir) {
      $data = is_file("$images_dir/$file_name") ? file_get_contents("$images_dir/$file_name") : FALSE;
    }
    else {
      try {
        $data = (string) \Drupal::httpClient()->get($url, ['timeout' => 30, 'headers' => ['User-Agent' => 'Mozilla/5.0 (LSU Libraries)']])->getBody();
      }
      catch (\Throwable $e) {
        $data = FALSE;
      }
    }
    $size = $data ? @getimagesizefromstring($data) : FALSE;
    if (!$size) {
      echo "Could not get the cover for \"$title\" (" . ($images_dir ? "$images_dir/$file_name" : $url) . '). Copy _reference/publication-covers to the server and pass images=<folder>.' . PHP_EOL;
      continue;
    }
    $uri = $fs->saveData($data, "$destination/$file_name", FileExists::Rename);
    $file = File::create(['uri' => $uri, 'status' => 1]);
    $file->save();
    $media = Media::create([
      'bundle' => $media_type->id(),
      'name' => $name,
      'status' => 1,
      $source_field => [
        'target_id' => $file->id(),
        'alt' => "Cover of $short",
        'width' => $size[0],
        'height' => $size[1],
      ],
    ]);
    $media->save();
    $media_id = (int) $media->id();
    echo "Imported $file_name ({$size[0]} x {$size[1]})" . PHP_EOL;
  }
  $book->set('field_pub_cover', ['target_id' => $media_id]);
  $book->setNeedsSave(TRUE);
  $changed++;
}

if (!$changed) {
  echo 'No covers imported: nothing saved.' . PHP_EOL;
  return;
}
$section->setNeedsSave(TRUE);
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage("Special Collections: $changed covers for \"$section_title\" (palefire_special_publication_covers.php).");
$node->save();
echo "Saved $changed covers on \"{$node->label()}\". Run: drush cr" . PHP_EOL;
