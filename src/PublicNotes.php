<?php

declare(strict_types=1);

namespace Drupal\palefire;

/**
 * Notes and thumbs from a Webform, for showing on the page that collects them.
 *
 * Used by the PR timeline (PrTimeline, Webform pr_timeline) and the
 * newsletter review pages (NewsletterReview, Webform newsletter_review).
 * Both forms share these keys: comment (the note), approved (a private box
 * only staff see and tick), verdict ('good' for thumbs up, 'changes' for
 * thumbs down, chosen with a note), vote (the same, or 'none', sent by the
 * thumbs buttons) and voter (a random token per browser). A note shows once
 * it is approved; a thumb counts as soon as it is sent, since it carries no
 * words to check.
 */
final class PublicNotes {

  /**
   * The most rows read at once.
   */
  private const MAX = 2000;

  /**
   * Approved notes, grouped by what they're about.
   *
   * @param string $webform
   *   The Webform's id.
   * @param string $key
   *   The element that says what a note is about (item, section).
   * @param array $match
   *   Other values a note must have, element => value (issue => <id>).
   * @param string[] $fields
   *   Other elements to carry in each note (section, on the page review
   *   hub, where notes are keyed by page).
   *
   * @return array<string, list<array{name: string, comment: string, verdict: string, created: int}>>
   *   Oldest first, keyed by the note's $key value ('' for none).
   */
  public static function approved(string $webform, string $key, array $match = [], array $fields = []): array {
    $types = \Drupal::entityTypeManager();
    if (!$types->hasDefinition('webform_submission')) {
      return [];
    }
    try {
      $ids = self::query($webform, ['approved' => '1'] + $match)->execute()->fetchCol();
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
      $note = [
        'name' => trim((string) ($data['name'] ?? '')),
        'comment' => $comment,
        'verdict' => (string) ($data['verdict'] ?? ''),
        'created' => (int) $submission->getCreatedTime(),
      ];
      foreach ($fields as $field) {
        $note[$field] = trim((string) ($data[$field] ?? ''));
      }
      $notes[trim((string) ($data[$key] ?? ''))][] = $note;
    }
    return $notes;
  }

  /**
   * Thumbs up and down, counted by what they're about.
   *
   * A thumb is a submission's vote (sent by a thumbs button: good, changes,
   * or none to take it back) or, without one, its verdict (Your take, sent
   * with a note). A submission with a voter (the browser's own random token,
   * js/public-notes.js) replaces that voter's earlier thumb on the same
   * thing, so a thumb can be changed or taken back; one without (sent with
   * no script, or a sample) simply counts.
   *
   * @param string $webform
   *   The Webform's id.
   * @param string $key
   *   The element that says what a thumb is about (item, section).
   * @param array $match
   *   Other values a submission must have, element => value.
   * @param string $by
   *   Count by this element instead of $key (page, on the page review hub:
   *   a voter's thumbs on different sections of a page all count).
   *
   * @return array<string, array{good: int, changes: int}>
   *   Keyed by the $key value, or $by's ('' for none).
   */
  public static function votes(string $webform, string $key, array $match = [], string $by = ''): array {
    if (!\Drupal::entityTypeManager()->hasDefinition('webform_submission')) {
      return [];
    }
    try {
      $query = self::query($webform, $match);
      $joins = ['verdict' => 'v', 'vote' => 't', 'voter' => 'w', $key => 'k'];
      if ($by !== '' && $by !== $key) {
        $joins[$by] = 'b';
      }
      foreach ($joins as $name => $alias) {
        $query->leftJoin('webform_submission_data', $alias, "$alias.sid = d.sid AND $alias.name = :name_$alias", [":name_$alias" => $name]);
        $query->addField($alias, 'value', $alias);
      }
      $rows = $query->execute()->fetchAll();
    }
    catch (\Throwable $e) {
      return [];
    }
    // Oldest first, so a voter's later thumb wins.
    $latest = [];
    $loose = [];
    foreach ($rows as $row) {
      $vote = (string) $row->t;
      $thumb = $vote !== '' ? $vote : (string) $row->v;
      if (!in_array($thumb, ['good', 'changes', 'none'], TRUE)) {
        continue;
      }
      $about = trim((string) $row->k);
      $group = property_exists($row, 'b') ? trim((string) $row->b) : $about;
      $voter = (string) $row->w;
      if ($voter !== '') {
        $latest[$voter . "\n" . $group . "\n" . $about] = [$group, $thumb];
      }
      else {
        $loose[] = [$group, $thumb];
      }
    }
    $votes = [];
    foreach (array_merge($loose, array_values($latest)) as [$about, $thumb]) {
      if ($thumb === 'none') {
        continue;
      }
      $votes[$about] ??= ['good' => 0, 'changes' => 0];
      $votes[$about][$thumb]++;
    }
    return $votes;
  }

  /**
   * Submission ids of a Webform with the given values.
   */
  private static function query(string $webform, array $match) {
    $query = \Drupal::database()->select('webform_submission', 'd')
      ->fields('d', ['sid'])
      ->condition('d.webform_id', $webform)
      ->orderBy('d.sid')
      ->range(0, self::MAX);
    $n = 0;
    foreach ($match as $name => $value) {
      $alias = 'm' . $n++;
      $query->join('webform_submission_data', $alias, "$alias.sid = d.sid AND $alias.name = :n$alias AND $alias.value = :v$alias", [":n$alias" => $name, ":v$alias" => (string) $value]);
    }
    return $query;
  }

  /**
   * Redrawn when a note is sent, approved or deleted, or the form changes.
   */
  public static function cacheTags(string $webform): array {
    return ['webform_submission_list', 'config:webform.webform.' . $webform];
  }

}
