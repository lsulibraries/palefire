<?php

declare(strict_types=1);

namespace Drupal\palefire_notes\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Takes the chosen notes off their page (unticks Approved).
 */
#[Action(
  id: 'palefire_notes_hide_from_public',
  label: new TranslatableMarkup('Hide from public'),
  type: 'webform_submission'
)]
final class HideFromPublic extends ApprovedActionBase {

  protected const VALUE = '0';

}
