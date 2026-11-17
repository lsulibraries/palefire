<?php

declare(strict_types=1);

namespace Drupal\palefire_notes;

/**
 * Labels for the note elements saved as keys (palefire_notes.views.inc).
 *
 * The same options as the forms themselves
 * (scripts/palefire_newsletter_webform.php and the other note forms).
 */
final class NoteOptions {

  /**
   * Status, set by staff on the newsletter review form.
   *
   * @return array<string, string>
   *   Keyed by the saved value.
   */
  public static function statuses(): array {
    return [
      'open' => (string) t('Open'),
      'changed' => (string) t('Changed'),
      'kept' => (string) t('Kept as is'),
      'waiting' => (string) t('Waiting on someone'),
    ];
  }

  /**
   * Your take, sent with a note.
   *
   * @return array<string, string>
   *   Keyed by the saved value.
   */
  public static function verdicts(): array {
    return [
      'good' => (string) t('Thumbs up'),
      'changes' => (string) t('Thumbs down'),
    ];
  }

}
