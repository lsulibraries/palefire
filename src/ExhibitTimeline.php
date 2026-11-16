<?php

declare(strict_types=1);

namespace Drupal\palefire;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\paragraphs\ParagraphInterface;

/**
 * An exhibition's timeline: every dated entry in date order.
 *
 * Entries get a date from field_entry_date (exhibit_entry, made by
 * palefire_exhibit_setup.php): "1953", "1953-06", "1953-06-20", or a range
 * with a slash ("1953-06-20/1953-06-22", "1941/1945"). A gallery set to
 * Display "timeline" draws the dated entries of all its sibling galleries,
 * whatever gallery each is in (includes/exhibit-timeline.html.twig,
 * scss/exhibit-timeline.scss). Each event links to its entry's heading, so
 * js/exhibition.js opens that gallery, or the entry in a wall's object view.
 *
 * One list, read top to bottom: events in date order on a hairline spine,
 * grouped by decade, a filled dot for an entry with a picture or clip and a
 * ring for text only, each naming its gallery. From a 48rem container the
 * decade stands in a column at the left (scss/exhibit-timeline.scss).
 */
final class ExhibitTimeline {

  private const MONTHS = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

  /**
   * Reads a date value.
   *
   * @return array{start: int[], end: ?int[], iso: string, label: string}|null
   *   start and end as [year, month, day] (0 for a missing part), iso the
   *   start for <time datetime>, label as shown ("June 20–22, 1953").
   */
  public static function parse(?string $raw): ?array {
    $raw = trim((string) $raw);
    if ($raw === '') {
      return NULL;
    }
    $parts = array_map('trim', explode('/', $raw, 2));
    $start = self::point($parts[0]);
    if (!$start) {
      return NULL;
    }
    $end = isset($parts[1]) ? self::point($parts[1]) : NULL;
    if ($end && self::decimal($end, TRUE) <= self::decimal($start)) {
      $end = NULL;
    }
    $iso = sprintf('%04d', $start[0]) . ($start[1] ? sprintf('-%02d', $start[1]) . ($start[2] ? sprintf('-%02d', $start[2]) : '') : '');
    return ['start' => $start, 'end' => $end, 'iso' => $iso, 'label' => self::label($start, $end)];
  }

  /**
   * "1953", "1953-06" or "1953-06-20" as [year, month, day].
   */
  private static function point(string $value): ?array {
    if (!preg_match('/^(\d{4})(?:-(\d{1,2})(?:-(\d{1,2}))?)?$/', $value, $m)) {
      return NULL;
    }
    $y = (int) $m[1];
    $mo = isset($m[2]) ? (int) $m[2] : 0;
    $d = isset($m[3]) ? (int) $m[3] : 0;
    if ($mo > 12 || $d > 31 || ($d && !$mo)) {
      return NULL;
    }
    return [$y, $mo, $d];
  }

  /**
   * A point as a decimal year; $end takes the end of a month or year.
   */
  private static function decimal(array $p, bool $end = FALSE): float {
    [$y, $m, $d] = $p;
    if (!$m) {
      return $y + ($end ? 0.999 : 0);
    }
    if (!$d) {
      return $y + ($m - 1 + ($end ? 0.999 : 0)) / 12;
    }
    return $y + ($m - 1) / 12 + ($d - 1) / 372;
  }

  /**
   * The shown date: "1953", "June 1953", "June 20, 1953", "June 20–22,
   * 1953", "October–December 1961", "1941–1945", "March 28 – April 2, 1960".
   */
  private static function label(array $s, ?array $e): string {
    $one = function (array $p, bool $year = TRUE): string {
      [$y, $m, $d] = $p;
      $text = $m ? self::MONTHS[$m] . ($d ? " $d" : '') : '';
      if ($year) {
        $text .= $m ? ($d ? ", $y" : " $y") : (string) $y;
      }
      return $text;
    };
    if (!$e) {
      return $one($s);
    }
    if ($s[0] !== $e[0] || !$s[1] || !$e[1]) {
      // Across years, or years only.
      return ($s[1] && $e[1]) ? $one($s) . ' – ' . $one($e) : $s[0] . '–' . $e[0];
    }
    if ($s[1] === $e[1] && $s[2] && $e[2]) {
      return self::MONTHS[$s[1]] . " {$s[2]}–{$e[2]}, {$s[0]}";
    }
    if (!$s[2] && !$e[2]) {
      return self::MONTHS[$s[1]] . '–' . self::MONTHS[$e[1]] . " {$s[0]}";
    }
    return $one($s, FALSE) . ' – ' . $one($e);
  }

  /**
   * The timeline for a gallery set to Display "timeline".
   *
   * @return array|null
   *   NULL when no sibling gallery has a dated entry. Else:
   *   - strands: ['title', 'anchor'] per gallery with dated entries, in
   *     page order.
   *   - events: in date order, each ['anchor', 'title', 'label', 'iso',
   *     'strand' (the gallery's title), 'row' (its index in strands),
   *     'media' (a picture or clip), 'audio', 'thumb'
   *     (_palefire_exhibit_image() values or NULL), 'index'].
   *   - decades: ['label' ("1950s"), 'events'].
   */
  public static function build(ParagraphInterface $page, CacheableMetadata $cache): ?array {
    $node = $page->getParentEntity();
    if (!$node || !$node->hasField('field_scroll_section')) {
      return NULL;
    }
    $cache->addCacheableDependency($node);
    $strands = [];
    $events = [];
    foreach ($node->get('field_scroll_section')->referencedEntities() as $gallery) {
      if ($gallery->id() === $page->id() || $gallery->bundle() !== 'exhibit_page' || !$gallery->isPublished() || !$gallery->hasField('field_guide_blocks')) {
        continue;
      }
      $row = NULL;
      foreach ($gallery->get('field_guide_blocks')->referencedEntities() as $entry) {
        if ($entry->bundle() !== 'exhibit_entry' || !$entry->isPublished() || !$entry->hasField('field_entry_date')) {
          continue;
        }
        $date = self::parse($entry->get('field_entry_date')->value);
        if (!$date) {
          continue;
        }
        if ($row === NULL) {
          $title = trim((string) $gallery->get('field_section_title')->value);
          $anchor = $gallery->hasField('field_section_anchor') ? trim((string) $gallery->get('field_section_anchor')->value) : '';
          $row = count($strands);
          $strands[] = ['title' => $title, 'anchor' => $anchor];
        }
        [$media, $audio, $thumb] = self::media($entry, $cache);
        $events[] = [
          'anchor' => _palefire_exhibit_entry_anchor($entry),
          'title' => trim((string) $entry->get('field_block_title')->value),
          'label' => $date['label'],
          'iso' => $date['iso'],
          'strand' => $strands[$row]['title'],
          'row' => $row,
          'start' => self::decimal($date['start']),
          'media' => $media,
          'audio' => $audio,
          'thumb' => $thumb,
        ];
      }
    }
    if (!$events) {
      return NULL;
    }
    // Date order; a tie keeps the page order.
    $order = array_keys($events);
    usort($order, fn ($a, $b) => [$events[$a]['start'], $a] <=> [$events[$b]['start'], $b]);

    $out = [];
    $decades = [];
    foreach ($order as $i => $key) {
      $e = $events[$key];
      $decade = (int) (floor($e['start'] / 10) * 10) . 's';
      unset($e['start']);
      $e['index'] = $i;
      $out[] = $e;
      if (!$decades || end($decades)['label'] !== $decade) {
        $decades[] = ['label' => $decade, 'events' => []];
      }
      $decades[array_key_last($decades)]['events'][] = $e;
    }

    return [
      'strands' => $strands,
      'events' => $out,
      'decades' => $decades,
    ];
  }

  /**
   * Whether an entry has a picture or a clip, whether a clip, and its first
   * picture (for the card), as [media, audio, thumb].
   */
  private static function media(ParagraphInterface $entry, CacheableMetadata $cache): array {
    $audio = FALSE;
    $item = NULL;
    foreach ($entry->get('field_guide_blocks')->referencedEntities() as $block) {
      if (!$block->isPublished()) {
        continue;
      }
      if ($block->bundle() === 'exhibit_audio') {
        $audio = TRUE;
      }
      if ($item === NULL && $block->bundle() === 'exhibit_figure' && !$block->get('field_figure_image')->isEmpty()) {
        $item = $block->get('field_figure_image')->first();
      }
      if ($item === NULL && $block->bundle() === 'exhibit_gallery') {
        foreach ($block->get('field_gallery_items')->referencedEntities() as $figure) {
          if (!$figure->get('field_figure_image')->isEmpty()) {
            $item = $figure->get('field_figure_image')->first();
            break;
          }
        }
      }
    }
    $thumb = NULL;
    if ($item) {
      $image = _palefire_media_image_item($item, $cache);
      $thumb = $image ? _palefire_exhibit_image($image, '5rem', $cache) : NULL;
    }
    return [$audio || $thumb !== NULL, $audio, $thumb];
  }

}
