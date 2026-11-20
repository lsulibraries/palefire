<?php

/**
 * @file
 * Free People of Color in Louisiana: puts back two kinds of link the 2015
 * site had, on the page palefire_exhibit_fpoc.php already built.
 *
 *   drush php:script scripts/palefire_exhibit_fpoc_links.php
 *   drush php:script scripts/palefire_exhibit_fpoc_links.php -- apply=1
 *   drush cr
 *
 * - The Collections: each collection's "In the Louisiana Digital Library"
 *   link (the 2015 "View the collection online", whose CONTENTdm addresses
 *   no longer work), a search of the project's LDL collection, added to the
 *   end of that collection's paragraph. 58 of the 60; the Jacques passport
 *   and the 1860 registration certificate are not in the LDL.
 * - Terminology: the closing sentence pointing to the encyclopedia entry on
 *   Creoles, now in 64 Parishes (formerly KnowLA).
 *
 * Both are taken from data/fpoc.json. A dry run unless apply=1. A
 * paragraph that already has its link is left alone, so editors' changes
 * stay and it is safe to re-run; a collection it can't find by name is
 * listed. Paragraphs are saved in place, without a new revision.
 */

declare(strict_types=1);

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

$data = json_decode((string) file_get_contents(__DIR__ . '/data/fpoc.json'), TRUE);
if (!is_array($data) || empty($data['pages'])) {
  throw new \RuntimeException("Couldn't read data/fpoc.json.");
}

// A collection's name, as plain text, is its key.
$name_of = function (string $p): string {
  preg_match('#<strong>(.*?)</strong>#s', $p, $m);
  return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($m[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
};
$ldl = 'louisianadigitallibrary.org/islandora/search/';

// The LDL link for each collection, from data/fpoc.json.
$links = [];
$sentence = NULL;
foreach ($data['pages'] as $page) {
  foreach ($page['blocks'] as $block) {
    foreach ($block['blocks'] ?? [$block] as $b) {
      $body = (string) ($b['body'] ?? '');
      if ($page['anchor'] === 'collections') {
        preg_match_all('#<p><strong>.*?</p>#s', $body, $ps);
        foreach ($ps[0] as $p) {
          if (preg_match('#<a href="https://' . preg_quote($ldl, '#') . '[^"]*">In the Louisiana Digital Library</a>#', $p, $a)) {
            $links[$name_of($p)] = $a[0];
          }
        }
      }
      if ($page['anchor'] === 'terminology' && preg_match('#For more on the term .*?\(formerly KnowLA\)\.#s', $body, $s)) {
        $sentence = $s[0];
      }
    }
  }
}
if (count($links) !== 58 || !$sentence) {
  throw new \RuntimeException('Expected 58 collection links and the Creoles sentence in data/fpoc.json, found ' . count($links) . ($sentence ? '' : ' and no sentence') . '.');
}

$ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', $data['title'])
  ->execute();
if (count($ids) !== 1) {
  throw new \RuntimeException('Expected one Modular page titled "' . $data['title'] . '", found ' . count($ids) . '.');
}
$node = \Drupal::entityTypeManager()->getStorage('node')->load(reset($ids));
echo 'Page: ' . $node->label() . ' (node ' . $node->id() . ')' . PHP_EOL;

// The guide_text paragraphs of a gallery, in its entries or directly in it.
$texts = function ($section): array {
  $out = [];
  foreach ($section->get('field_guide_blocks')->referencedEntities() as $block) {
    foreach ($block->bundle() === 'exhibit_entry' ? $block->get('field_guide_blocks')->referencedEntities() : [$block] as $p) {
      if ($p->bundle() === 'guide_text') {
        $out[] = $p;
      }
    }
  }
  return $out;
};

$added = 0;
$had = 0;
$found = [];
$to_save = [];
foreach ($node->get('field_scroll_section')->referencedEntities() as $section) {
  if ($section->bundle() !== 'exhibit_page') {
    continue;
  }
  $anchor = (string) $section->get('field_section_anchor')->value;
  foreach ($anchor === 'collections' || $anchor === 'terminology' ? $texts($section) : [] as $paragraph) {
    $body = (string) $paragraph->get('field_block_body')->value;
    $new = $body;

    if ($anchor === 'collections') {
      $new = preg_replace_callback('#<p><strong>.*?</p>#s', function ($m) use ($name_of, $links, $ldl, &$added, &$had, &$found) {
        $p = $m[0];
        $name = $name_of($p);
        if (!isset($links[$name])) {
          return $p;
        }
        $found[$name] = TRUE;
        if (str_contains($p, $ldl)) {
          $had++;
          return $p;
        }
        $added++;
        echo '  + ' . $name . PHP_EOL;
        $inner = substr($p, 0, -4);
        $last = substr($inner, (int) strrpos($inner, '<br>'));
        return $inner . (str_contains($last, '<a ') ? ' · ' : '<br>') . $links[$name] . '</p>';
      }, $body);
    }

    if ($anchor === 'terminology' && !str_contains($body, '64parishes.org')) {
      $new = preg_replace('#(free black with no European ancestry\.)\s*</p>#', '$1 ' . addcslashes($sentence, '\\$') . '</p>', $body, 1, $count);
      if ($count) {
        echo 'Terminology: + the Creoles sentence (64 Parishes)' . PHP_EOL;
      }
    }

    if ($new !== $body) {
      $paragraph->set('field_block_body', ['value' => $new, 'format' => $paragraph->get('field_block_body')->format]);
      $to_save[] = $paragraph;
    }
  }
}

foreach (array_diff_key($links, $found) as $name => $link) {
  echo "Not found on the page (renamed?): $name" . PHP_EOL;
}
echo "Collections: $added links to add, $had already there." . PHP_EOL;

if (!$apply) {
  echo 'Dry run: ' . count($to_save) . ' paragraphs to update. Nothing saved. Add apply=1 to save.' . PHP_EOL;
  return;
}
foreach ($to_save as $paragraph) {
  // Saved in place, so the page's reference to this revision stays good.
  $paragraph->setNewRevision(FALSE);
  $paragraph->save();
}
\Drupal::entityTypeManager()->getStorage('node')->resetCache([$node->id()]);
echo 'Saved ' . count($to_save) . ' paragraphs. Run drush cr.' . PHP_EOL;
