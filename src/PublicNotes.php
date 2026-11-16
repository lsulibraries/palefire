<?php

declare(strict_types=1);

namespace Drupal\palefire;

/**
 * Notes and thumbs from a Webform, for showing on the page that collects them.
 *
 * Used by the PR timeline (PrTimeline, Webform pr_timeline) and the
 * newsletter review pages (NewsletterReview, Webform newsletter_review).
 * Both forms share three keys: comment (the note), approved (a private box
 * only staff see and tick) and verdict ('good' for thumbs up, 'changes' for
 * thumbs down). A note shows once it is approved; a thumb counts as soon as
 * it is sent, since it carries no words to check.
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
   *
   * @return array<string, list<array{name: string, comment: string, verdict: string, created: int}>>
   *   Oldest first, keyed by the note's $key value ('' for none).
   */
  public static function approved(string $webform, string $key, array $match = []): array {
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
      $notes[trim((string) ($data[$key] ?? ''))][] = [
        'name' => trim((string) ($data['name'] ?? '')),
        'comment' => $comment,
        'verdict' => (string) ($data['verdict'] ?? ''),
        'created' => (int) $submission->getCreatedTime(),
      ];
    }
    return $notes;
  }

  /**
   * Thumbs up and down, counted by what they're about.
   *
   * @param string $webform
   *   The Webform's id.
   * @param string $key
   *   The element that says what a thumb is about (item, section).
   * @param array $match
   *   Other values a submission must have, element => value.
   *
   * @return array<string, array{good: int, changes: int}>
   *   Keyed by the $key value ('' for none).
   */
  public static function votes(string $webform, string $key, array $match = []): array {
    if (!\Drupal::entityTypeManager()->hasDefinition('webform_submission')) {
      return [];
    }
    try {
      $query = self::query($webform, $match);
      $query->join('webform_submission_data', 'v', 'v.sid = d.sid AND v.name = :verdict', [':verdict' => 'verdict']);
      $query->leftJoin('webform_submission_data', 'k', 'k.sid = d.sid AND k.name = :key', [':key' => $key]);
      $query->addField('v', 'value', 'verdict');
      $query->addField('k', 'value', 'about');
      $rows = $query->execute()->fetchAll();
    }
    catch (\Throwable $e) {
      return [];
    }
    $votes = [];
    foreach ($rows as $row) {
      if (!in_array($row->verdict, ['good', 'changes'], TRUE)) {
        continue;
      }
      $about = trim((string) $row->about);
      $votes[$about] ??= ['good' => 0, 'changes' => 0];
      $votes[$about][$row->verdict]++;
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
