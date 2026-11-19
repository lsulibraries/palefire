<?php

declare(strict_types=1);

namespace Drupal\palefire;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\media\MediaInterface;

/**
 * An oral history project's full interviews, under its exhibition.
 *
 * A guide text paragraph that is only "[interviews:<set>]" draws every
 * narrator of data/<set>-interviews.json (includes/exhibit-interviews.html.twig):
 * a portrait, the name, interviewer and accession number, then each tape
 * with its length, the exhibition's player and its summary and transcript
 * (PDFs). The recordings stream from where they already are on the server,
 * and nothing loads until Play.
 *
 * Portraits are Image media named "<Set> interviews: <file>", imported by
 * the set's script (scripts/palefire_exhibit_mossville_interviews.php); a
 * narrator whose portrait isn't there yet gets initials instead.
 *
 * The data file is built from the project's old directory page
 * (scripts/palefire_exhibit_mossville_interviews_build.js).
 */
final class ExhibitInterviews {

  /**
   * A guide text paragraph that is only this draws the set.
   */
  public const MARKER = '#<p>\s*\[interviews:([a-z0-9-]+)\]\s*</p>#i';

  /**
   * The media name for a set's portrait file.
   */
  public static function mediaName(string $set, string $file): string {
    return ucfirst($set) . ' interviews: ' . $file;
  }

  /**
   * The template's values for a set, or NULL when it has no data file.
   */
  public static function view(string $set, CacheableMetadata $cache): ?array {
    $file = DRUPAL_ROOT . '/' . \Drupal::service('extension.list.theme')->getPath('palefire') . '/data/' . $set . '-interviews.json';
    if (!preg_match('/^[a-z0-9-]+$/', $set) || !is_readable($file)) {
      return NULL;
    }
    $data = json_decode((string) file_get_contents($file), TRUE);
    if (!is_array($data) || empty($data['narrators'])) {
      return NULL;
    }

    // Every portrait in one query; a new or replaced one redraws the list.
    $cache->addCacheTags(['media_list']);
    $names = [];
    foreach ($data['narrators'] as $narrator) {
      if (!empty($narrator['portrait'])) {
        $names[] = self::mediaName($set, $narrator['portrait']);
      }
    }
    $media = [];
    if ($names) {
      foreach (\Drupal::entityTypeManager()->getStorage('media')->loadByProperties(['name' => $names]) as $entity) {
        if ($entity instanceof MediaInterface && $entity->isPublished()) {
          $media[$entity->label()] = $entity;
        }
      }
    }

    $narrators = [];
    $seconds = 0;
    $tape_count = 0;
    foreach ($data['narrators'] as $i => $narrator) {
      $photo = NULL;
      $entity = $media[self::mediaName($set, (string) ($narrator['portrait'] ?? ''))] ?? NULL;
      if ($entity) {
        $cache->addCacheableDependency($entity);
        $source = $entity->getSource()->getConfiguration()['source_field'] ?? NULL;
        $item = $source && $entity->hasField($source) ? $entity->get($source)->first() : NULL;
        $photo = $item ? _palefire_exhibit_image($item, '(min-width: 75rem) 20rem, (min-width: 36rem) 45vw, 100vw', $cache) : NULL;
      }

      // One summary for every tape (the usual case) is the narrator's;
      // otherwise each tape links its own.
      $summaries = array_values(array_unique(array_filter(array_column($narrator['tapes'], 'summary'))));
      $shared = count($summaries) === 1 && count(array_filter(array_column($narrator['tapes'], 'summary'))) === count($narrator['tapes']);

      $tapes = [];
      foreach ($narrator['tapes'] as $tape) {
        $tape_count++;
        $seconds += (int) ($tape['seconds'] ?? 0);
        $tapes[] = [
          'number' => $tape['tape'],
          'length' => self::length((int) ($tape['seconds'] ?? 0)),
          'note' => $tape['note'] ?? '',
          'audio' => !empty($tape['audio']) ? $data['files'] . '/audio/' . rawurlencode($tape['audio']) : NULL,
          'transcript' => !empty($tape['transcript']) ? $data['files'] . '/transcript/' . rawurlencode($tape['transcript']) : NULL,
          'summary' => !$shared && !empty($tape['summary']) ? $data['summaries'] . '/' . rawurlencode($tape['summary']) : NULL,
        ];
      }

      $narrators[] = [
        'id' => 'pf-iv-' . $set . '-' . ($i + 1),
        'name' => $narrator['name'],
        'initials' => self::initials($narrator['name']),
        'interviewers' => $narrator['interviewers'],
        'accessions' => $narrator['accessions'],
        'photo' => $photo,
        'summary' => $shared ? $data['summaries'] . '/' . rawurlencode($summaries[0]) : NULL,
        'tapes' => $tapes,
      ];
    }

    return [
      'set' => $set,
      'narrators' => $narrators,
      'tape_count' => $tape_count,
      'hours' => (int) round($seconds / 3600),
    ];
  }

  /**
   * "24 min" or "1 hr 12 min"; empty for an unknown length.
   */
  private static function length(int $seconds): string {
    if ($seconds <= 0) {
      return '';
    }
    $minutes = max(1, (int) round($seconds / 60));
    $hours = intdiv($minutes, 60);
    $rest = $minutes % 60;
    if (!$hours) {
      return $minutes . ' min';
    }
    return $hours . ' hr' . ($rest ? ' ' . $rest . ' min' : '');
  }

  /**
   * Up to two initials: the first name's and the family name's.
   */
  private static function initials(string $name): string {
    $words = preg_split('/\s+/u', trim(preg_replace('/\b(and|Jr\.?|Sr\.?)\b|“[^”]*”/u', ' ', $name)));
    $words = array_values(array_filter($words, fn ($w) => $w !== ''));
    if (!$words) {
      return '';
    }
    $first = mb_substr($words[0], 0, 1);
    $last = count($words) > 1 ? mb_substr(end($words), 0, 1) : '';
    return mb_strtoupper($first . $last);
  }

}
