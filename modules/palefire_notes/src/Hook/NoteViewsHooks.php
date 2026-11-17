<?php

declare(strict_types=1);

namespace Drupal\palefire_notes\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\palefire_notes\NoteOptions;

/**
 * Views data: the Palefire note forms' shared elements, as one set of columns.
 *
 * Webform Views gives each form its own columns (the newsletter's Your note,
 * the page review's Your note, and so on), so a list of notes from several
 * forms would show the same thing in many half-empty columns, and a filter
 * would match one form only. These read an element by its key from whichever
 * form a submission belongs to: newsletter_review, page_review and
 * pr_timeline share comment, name, verdict and approved, and the newsletter
 * adds status and reply (the theme's scripts/palefire_*_webform.php). A form
 * without the element simply shows nothing there.
 */
final class NoteViewsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_views_data().
   */
  #[Hook('views_data')]
  public function viewsData(): array {
    $group = $this->t('Palefire note');
    $elements = [
      'comment' => [$this->t('Note'), 'text'],
      'section' => [$this->t('Section'), 'text'],
      'item' => [$this->t('Timeline item'), 'text'],
      'page' => [$this->t('Page'), 'text'],
      'name' => [$this->t('Name'), 'text'],
      'verdict' => [$this->t('Take'), 'verdict'],
      'approved' => [$this->t('Shown publicly'), 'boolean'],
      'status' => [$this->t('Status'), 'status'],
      'reply' => [$this->t('Our reply'), 'text'],
      'version' => [$this->t('Draft version'), 'text'],
    ];
    $data = [];
    foreach ($elements as $key => [$title, $kind]) {
      $table = 'palefire_note_' . $key;
      $data[$table]['table'] = [
        'group' => $group,
        // An alias of webform_submission_data, joined on the element's key.
        // Delta 0: these are all single values.
        'join' => [
          'webform_submission' => [
            'table' => 'webform_submission_data',
            'left_field' => 'sid',
            'field' => 'sid',
            'extra' => [
              ['field' => 'name', 'value' => $key],
              ['field' => 'delta', 'value' => 0],
            ],
          ],
        ],
      ];
      $value = [
        'title' => $title,
        'help' => $this->t('The “@key” element of a Palefire note form, from whichever form the submission belongs to.', ['@key' => $key]),
        'field' => ['id' => 'standard'],
        'filter' => ['id' => 'string'],
        'sort' => ['id' => 'standard'],
        'argument' => ['id' => 'string'],
      ];
      if ($kind === 'boolean') {
        $value['field'] = ['id' => 'boolean'];
        // Unticked is saved as 0 or left out, so No includes empty.
        $value['filter'] = ['id' => 'boolean', 'label' => $title, 'type' => 'yes-no', 'accept null' => TRUE];
      }
      elseif ($kind === 'status' || $kind === 'verdict') {
        $callback = $kind === 'status' ? NoteOptions::class . '::statuses' : NoteOptions::class . '::verdicts';
        $value['field'] = ['id' => 'machine_name', 'options callback' => $callback];
        $value['filter'] = ['id' => 'in_operator', 'options callback' => $callback];
      }
      $data[$table]['value'] = $value;
    }
    return $data;
  }

}
