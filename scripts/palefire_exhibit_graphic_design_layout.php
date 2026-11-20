<?php

/**
 * @file
 * History of Graphic Design in South Louisiana: say what each clip is.
 *
 * On the live page (/v2/oralhistory/exhibitions/graphicdesign), so the
 * wall reads like Mossville’s (a clip’s headline over its speaker) instead
 * of each narrator’s name twice:
 *
 * - The Interviews: each entry’s title becomes its clip’s headline (the
 *   tile’s label; the speaker’s name is under it), and the old site’s
 *   menu summary of the clip opens the entry as a text block (shown in
 *   the object view). Credits read “Name, interviewed in 2010” (or “…
 *   interviewed for this project” where no year is known), so the object
 *   view finds the speaker’s name for the Biography button. A short
 *   opening text says what the tiles are.
 * - The Interviews loses its lead (the Borne letterhead, mostly blank
 *   paper, blown up full screen: a wall always opens its lead full
 *   screen), so it opens on its title, the opening text and the tiles.
 * - About the Project’s lead (it had none): the cover of Upton’s Series of
 *   New Orleans Places and Things, 1968, as its cover art: Opening “Cover”,
 *   full screen behind the heading (not Show whole image).
 * - About the Project loses the poster at its end (“The exhibition’s
 *   poster.”, palefire_oral_history_exhibit_cards.php): the poster is the
 *   exhibition’s cover. That script won’t put it back: the card picture
 *   it read the poster from is empty now.
 *
 * - The cover (the poster, a small photograph) is framed: shown whole over
 *   a blurred wash of itself (Frame the cover; run
 *   palefire_exhibit_cover_framed.php first, or it says so and skips it).
 * - Each clip opens with its speaker’s biography beside the transcript
 *   (Open biographies with each clip; run palefire_exhibit_bios_open.php
 *   first, or it says so and skips it): most clips run a minute or two.
 *
 * Anchors (and so links to entries) don’t change. Safe to run again: it
 * only sets what differs. A dry run unless apply=1.
 *
 *   drush php:script scripts/palefire_exhibit_graphic_design_layout.php
 *   drush php:script scripts/palefire_exhibit_graphic_design_layout.php -- apply=1
 *   drush cr
 *
 * Undo: revert the page to its previous revision (Revisions tab).
 */

declare(strict_types=1);

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\node\NodeInterface;
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
$log = [];

$alias = $args['path'] ?? '/v2/oralhistory/exhibitions/graphicdesign';
$path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
$node = preg_match('#^/node/(\d+)$#', $path, $m) ? \Drupal::entityTypeManager()->getStorage('node')->load($m[1]) : NULL;
if (!$node instanceof NodeInterface || $node->label() !== 'History of Graphic Design in South Louisiana') {
  throw new \RuntimeException("No History of Graphic Design page at $alias. Pass path=<alias>.");
}

// Headline, summary and credit per entry, by its anchor.
$entries = [
  'mary-borne' => [
    'Business school in the Great Depression',
    'Mary Borne, known as Miss Weenie, tells how her father paid for her education during the Great Depression. She was 94 when this was recorded in 2010, and her quiet voice reflects her age.',
    'Mary Borne, interviewed in 2010',
  ],
  'tom-varisco' => [
    '“My art guy”: designing political campaigns',
    'Tom Varisco, originally from Lafayette, calls himself a “design director.” In this 2010 recording he describes the projects and political campaigns that helped him open his studio in New Orleans more than thirty years earlier.',
    'Tom Varisco, interviewed in 2010',
  ],
  'don-smith' => [
    '“I want to work here”',
    'Don Smith recalls what drove him to take an entry-level job at the Fitzgerald Agency, where he worked until retirement and rose to art director.',
    'Don Smith, interviewed for this project',
  ],
  'kenny-harrison' => [
    'Failing every subject except art',
    'Kenny Harrison recounts his love of art, which began in elementary school.',
    'Kenny Harrison, interviewed for this project',
  ],
  'gus-levy' => [
    'Retouching for the newsroom',
    'Gus Levy remembers the afternoon-to-night shift in the art department of the Times-Picayune, where he retouched photographs with an airbrush, cut and pasted layouts by hand, and more than once retouched the eyes of the dead open for a front-page mugshot.',
    'Gus Levy, interviewed for this project',
  ],
  'michael-deas' => [
    'Faster and cheaper wins: the move to digital',
    'Michael Deas, who still paints by hand, shares his view of the move to digital media.',
    'Michael Deas, interviewed for this project',
  ],
  'justin-shiels' => [
    'Clashing stripes and Andy Warhol',
    'Justin Shiels shares his earliest memory of fashion, stripes worn against stripes, and the influence of Andy Warhol.',
    'Justin Shiels, interviewed for this project',
  ],
  'kathy-cain-and-paulette-hurdlik' => [
    'Designers are problem solvers',
    'Kathy Cain and Paulette Hurdlik, who ran the first female-run design studio in New Orleans, on their favorite question for a new designer: how much of design is engineering, and how much is art.',
    'Kathy Cain and Paulette Hurdlik, interviewed in 2009',
  ],
  'byron-levy' => [
    'From engineering to the paper business',
    'Byron Levy briefly describes his career, from engineering and wartime service to the family paper business, in his 2009 interview.',
    'Byron Levy, interviewed in 2009',
  ],
  'peter-a-mayer' => [
    'Proving television, winning Popeyes',
    'In these 2012 clips, Peter A. Mayer tells how he proved television to a doubtful client, and takes credit for the demise of the Fitzgerald Agency.',
    'Peter A. Mayer, interviewed in 2012',
  ],
];
$intro = '<p>Ten voices from south Louisiana’s commercial art world: art directors, illustrators, designers, an ad man, a paper salesman, and a banker with a mimeograph. Each picture opens its clip, with the transcript and the speaker’s biography.</p>';
$leads = [
  'about' => ['Graphic Design: upton_guslevy_01', '<p>Cover of <em>Upton’s Series of New Orleans Places and Things by New Orleans Graphic Artists</em>, E. S. Upton Printing Company, New Orleans, 1968.</p>'],
];

// The page’s text format: the one its biographies use.
$format = NULL;
$text_of = fn (string $html) => trim((string) preg_replace('/\s+/u', ' ', strip_tags($html)));

$changed = function (FieldableEntityInterface $p, string $field, $value, string $line) use (&$log): bool {
  $current = $p->get($field)->getValue()[0] ?? [];
  $now = is_array($value) ? $value : ['value' => $value];
  $same = TRUE;
  foreach ($now as $k => $v) {
    if ((string) ($current[$k] ?? '') !== (string) $v) {
      $same = FALSE;
    }
  }
  if ($same) {
    return FALSE;
  }
  $p->set($field, $value);
  $p->setNeedsSave(TRUE);
  $log[] = $line;
  return TRUE;
};

$media_id = function (string $name): ?int {
  $ids = \Drupal::entityQuery('media')->accessCheck(FALSE)->condition('name', $name)->range(0, 1)->execute();
  return $ids ? (int) reset($ids) : NULL;
};

// The cover (the poster, a small photograph blown up) framed.
if (!$node->hasField('field_exhibit_cover_framed')) {
  echo 'Skipped: Frame the cover is not on Modular page (run palefire_exhibit_cover_framed.php first).' . PHP_EOL;
}
elseif (!$node->get('field_exhibit_cover_framed')->value) {
  $node->set('field_exhibit_cover_framed', 1);
  $log[] = 'Cover: framed.';
}

// Short clips, long lives: each clip opens with its speaker’s biography.
if (!$node->hasField('field_exhibit_bios_open')) {
  echo 'Skipped: Open biographies with each clip is not on Modular page (run palefire_exhibit_bios_open.php first).' . PHP_EOL;
}
elseif (!$node->get('field_exhibit_bios_open')->value) {
  $node->set('field_exhibit_bios_open', 1);
  $log[] = 'Biographies: open with each clip.';
}

foreach ($node->get('field_scroll_section') as $section) {
  $gallery = $section->entity;
  if (!$gallery || $gallery->bundle() !== 'exhibit_page') {
    continue;
  }
  $anchor = (string) $gallery->get('field_section_anchor')->value;
  foreach ($gallery->get('field_guide_blocks') as $item) {
    $entry = $item->entity;
    if ($entry && $entry->bundle() === 'exhibit_entry' && !$entry->get('field_entry_bio')->isEmpty()) {
      $format = $format ?? $entry->get('field_entry_bio')->format;
    }
  }
  if ($format === NULL) {
    $format = 'wysiwyg';
  }

  // The leads.
  if ($anchor === 'interviews' && !$gallery->get('field_exhibit_lead')->isEmpty()) {
    $gallery->set('field_exhibit_lead', NULL);
    if ($gallery->hasField('field_exhibit_lead_caption')) {
      $gallery->set('field_exhibit_lead_caption', NULL);
    }
    $gallery->setNeedsSave(TRUE);
    $log[] = 'Lead of The Interviews: none (was the Borne letterhead).';
  }
  if (isset($leads[$anchor])) {
    [$name, $caption] = $leads[$anchor];
    $id = $media_id($name);
    if (!$id) {
      $log[] = "Skipped: no media item “$name” for {$anchor}’s lead.";
    }
    else {
      $changed($gallery, 'field_exhibit_lead', ['target_id' => $id], "Lead of “{$gallery->get('field_section_title')->value}”: $name");
      foreach (['field_exhibit_lead_caption' => ['value' => $caption, 'format' => $format], 'field_exhibit_opener' => 'full', 'field_exhibit_lead_whole' => 0] as $field => $value) {
        if ($gallery->hasField($field)) {
          $changed($gallery, $field, $value, "  $field");
        }
        else {
          $log[] = "Skipped: $field is not on Gallery (run palefire_exhibit_setup.php).";
        }
      }
    }
  }

  // No poster at the end of About: it is the exhibition’s cover.
  if ($anchor === 'about') {
    $keep = [];
    foreach ($gallery->get('field_guide_blocks') as $item) {
      $block = $item->entity;
      if ($block && $block->bundle() === 'exhibit_figure' && $text_of((string) $block->get('field_figure_caption')->value) === 'The exhibition’s poster.') {
        $log[] = 'About the Project: the poster at its end removed (it is the cover).';
        continue;
      }
      $keep[] = $item->getValue();
    }
    if (count($keep) !== $gallery->get('field_guide_blocks')->count()) {
      $gallery->set('field_guide_blocks', $keep);
      $gallery->setNeedsSave(TRUE);
    }
  }

  if ($anchor !== 'interviews') {
    continue;
  }

  // The wall’s opening text, once.
  $blocks = [];
  $has_intro = FALSE;
  foreach ($gallery->get('field_guide_blocks') as $item) {
    $blocks[] = $item->entity;
    if ($item->entity && $item->entity->bundle() === 'guide_text' && str_starts_with($text_of((string) $item->entity->get('field_block_body')->value), 'Ten voices')) {
      $has_intro = TRUE;
    }
  }
  if (!$has_intro) {
    array_unshift($blocks, Paragraph::create([
      'type' => 'guide_text',
      'field_block_body' => ['value' => $intro, 'format' => $format],
      'field_text_style' => 'default',
    ]));
    $gallery->set('field_guide_blocks', $blocks);
    $gallery->setNeedsSave(TRUE);
    $log[] = 'Opening text of The Interviews.';
  }

  foreach ($gallery->get('field_guide_blocks') as $item) {
    $entry = $item->entity;
    if (!$entry || $entry->bundle() !== 'exhibit_entry') {
      continue;
    }
    $key = (string) $entry->get('field_section_anchor')->value;
    if (!isset($entries[$key])) {
      $log[] = "Left alone: entry #$key (not one of the ten).";
      continue;
    }
    [$headline, $summary, $credit] = $entries[$key];
    if ($changed($entry, 'field_block_title', $headline, "#$key title: $headline")) {
      $gallery->setNeedsSave(TRUE);
    }

    // The summary first, once.
    $inner = [];
    $has_summary = FALSE;
    foreach ($entry->get('field_guide_blocks') as $b) {
      $inner[] = $b->entity;
      if ($b->entity && $b->entity->bundle() === 'guide_text' && $text_of((string) $b->entity->get('field_block_body')->value) === $text_of("<p>$summary</p>")) {
        $has_summary = TRUE;
      }
    }
    if (!$has_summary) {
      array_unshift($inner, Paragraph::create([
        'type' => 'guide_text',
        'field_block_body' => ['value' => "<p>$summary</p>", 'format' => $format],
        'field_text_style' => 'default',
      ]));
      $entry->set('field_guide_blocks', $inner);
      $entry->setNeedsSave(TRUE);
      $gallery->setNeedsSave(TRUE);
      $log[] = "#$key summary.";
    }

    foreach ($entry->get('field_guide_blocks') as $b) {
      $block = $b->entity;
      if ($block && $block->bundle() === 'exhibit_audio' && $changed($block, 'field_audio_credit', $credit, "#$key credit: $credit")) {
        $entry->setNeedsSave(TRUE);
        $gallery->setNeedsSave(TRUE);
      }
    }
  }
}

foreach ($log as $line) {
  echo ($apply ? '' : '[dry run] ') . $line . PHP_EOL;
}
if (!$log) {
  echo 'Nothing to change.' . PHP_EOL;
}
elseif ($apply) {
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage('Graphic Design: clip headlines and summaries, credits, no wall lead, About lead as cover art, no poster at the end, framed cover, biographies open (palefire_exhibit_graphic_design_layout.php).');
  $node->save();
  echo 'Saved node/' . $node->id() . '. Run: drush cr' . PHP_EOL;
}
else {
  echo 'Dry run: nothing saved. Run again with apply=1.' . PHP_EOL;
}
