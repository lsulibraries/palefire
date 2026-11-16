<?php

declare(strict_types=1);

namespace Drupal\palefire;

/**
 * A timeline of public relations events, with notes anyone can leave.
 *
 * A guide text paragraph that is only "[pr-timeline]" turns the first list
 * after it in the same block into a vertical timeline
 * (includes/pr-timeline.html.twig): one item per list item, typed as
 *
 *   <strong>Date</strong> Title — an optional short note, links allowed
 *
 * (the note can also follow a line break instead of the dash). Each item has
 * a Comment link that ties a note to it; the notes go to the Webform
 * "pr_timeline" (scripts/palefire_pr_timeline_webform.php), with a name or
 * without. A note shows on the page only once staff tick its Approved box
 * in the Webform's results, and then only when the reader turns on Show
 * notes (js/pr-timeline.js).
 */
final class PrTimeline {

  /**
   * The Webform readers answer (scripts/palefire_pr_timeline_webform.php).
   */
  public const WEBFORM = 'pr_timeline';

  /**
   * The paragraph that draws the timeline.
   */
  public const MARKER = '#<p>\s*\[pr-timeline\]\s*</p>#i';

  /**
   * The most approved notes read at once (oldest first).
   */
  private const MAX_NOTES = 500;

  /**
   * Takes the timeline's list out of a text that had the marker.
   *
   * @param string $text
   *   The text, the marker already taken out.
   * @param string $format
   *   Its text format: each item's note is filtered by it, as the rest of
   *   the text is.
   *
   * @return array{0: array, 1: string}
   *   The items (date, title, label, note as a render array or NULL), and
   *   the text without the list.
   */
  public static function items(string $text, string $format): array {
    if (!preg_match('#<(ol|ul)\b[^>]*>(.*?)</\1>#is', $text, $list, PREG_OFFSET_CAPTURE)) {
      return [[], $text];
    }
    $items = [];
    preg_match_all('#<li\b[^>]*>(.*?)</li>#is', $list[2][0], $matches);
    foreach ($matches[1] as $inner) {
      $date = '';
      if (preg_match('#^\s*<(strong|b)\b[^>]*>(.*?)</\1>#is', $inner, $m)) {
        $date = self::plain($m[2]);
        $inner = substr($inner, strlen($m[0]));
      }
      // The title runs to a line break or a spaced dash; the rest is the note.
      $parts = preg_split('#<br\s*/?>|\s(?:—|–|&mdash;|&ndash;)\s#iu', $inner, 2);
      $title = trim(self::plain($parts[0]), " \t\n\r\0\x0B:–—");
      $note = trim($parts[1] ?? '');
      if ($title === '') {
        continue;
      }
      $items[] = [
        'date' => $date,
        'title' => $title,
        // What the note's Item field holds, and how a note finds its item
        // again: change an item's date or title and its notes move to "the
        // timeline as a whole".
        'label' => mb_substr($date !== '' ? "$date · $title" : $title, 0, 255),
        'note' => $note !== '' ? ['#type' => 'processed_text', '#text' => $note, '#format' => $format] : NULL,
      ];
    }
    $text = substr_replace($text, '', $list[0][1], strlen($list[0][0]));
    return [$items, $text];
  }

  /**
   * Everything the template needs.
   *
   * @param array $items
   *   From items().
   *
   * @return array{items: array, general: array, count: int, form: ?array}
   *   The items, each with its approved notes; notes on the timeline as a
   *   whole (or on an item since renamed); how many notes in all; the
   *   Webform (NULL until it is created).
   */
  public static function view(array $items): array {
    $notes = self::notes();
    $count = 0;
    foreach ($items as &$item) {
      $item['notes'] = $notes[$item['label']] ?? [];
      $count += count($item['notes']);
      unset($notes[$item['label']]);
    }
    unset($item);
    $general = $notes ? array_merge(...array_values($notes)) : [];
    usort($general, fn (array $a, array $b): int => $a['created'] <=> $b['created']);
    return [
      'items' => $items,
      'general' => $general,
      'count' => $count + count($general),
      'form' => self::form(),
    ];
  }

  /**
   * Approved notes by item label, oldest first.
   *
   * @return array<string, list<array{name: string, comment: string, created: int}>>
   *   Keyed by the label the note was left on ('' for the whole timeline).
   */
  private static function notes(): array {
    $types = \Drupal::entityTypeManager();
    if (!$types->hasDefinition('webform_submission')) {
      return [];
    }
    try {
      $ids = \Drupal::database()->select('webform_submission_data', 'd')
        ->fields('d', ['sid'])
        ->condition('d.webform_id', self::WEBFORM)
        ->condition('d.name', 'approved')
        ->condition('d.value', '1')
        ->orderBy('d.sid')
        ->range(0, self::MAX_NOTES)
        ->execute()
        ->fetchCol();
    }
    catch (\Throwable $e) {
      return [];
    }
    $notes = [];
    foreach ($ids ? $types->getStorage('webform_submission')->loadMultiple($ids) : [] as $submission) {
      $data = $submission->getData();
      $comment = trim((string) ($data['comment'] ?? ''));
      if (empty($data['approved']) || $comment === '') {
        continue;
      }
      $notes[trim((string) ($data['item'] ?? ''))][] = [
        'name' => trim((string) ($data['name'] ?? '')),
        'comment' => $comment,
        'created' => (int) $submission->getCreatedTime(),
      ];
    }
    return $notes;
  }

  /**
   * The Webform; NULL until it is created.
   */
  private static function form(): ?array {
    $types = \Drupal::entityTypeManager();
    if (!$types->hasDefinition('webform') || !$types->getStorage('webform')->load(self::WEBFORM)) {
      return NULL;
    }
    return ['#type' => 'webform', '#webform' => self::WEBFORM];
  }

  /**
   * Redrawn when a note is sent, approved or deleted, or the form changes.
   */
  public static function cacheTags(): array {
    return ['webform_submission_list', 'config:webform.webform.' . self::WEBFORM];
  }

  /**
   * Text from a bit of HTML.
   */
  private static function plain(string $html): string {
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
  }

}
