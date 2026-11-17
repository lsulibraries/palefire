<?php

declare(strict_types=1);

namespace Drupal\palefire_notes\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Shows the chosen notes on their page (ticks Approved).
 */
#[Action(
  id: 'palefire_notes_show_publicly',
  label: new TranslatableMarkup('Show publicly'),
  type: 'webform_submission'
)]
final class ShowPublicly extends ApprovedActionBase {

  protected const VALUE = '1';

}
