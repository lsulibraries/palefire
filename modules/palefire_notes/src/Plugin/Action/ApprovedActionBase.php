<?php

declare(strict_types=1);

namespace Drupal\palefire_notes\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Ticks or unticks a note's private Approved box.
 *
 * The Palefire note forms (newsletter_review, page_review, pr_timeline) show
 * a note on the page once staff tick Approved (Drupal\palefire\PublicNotes).
 * These actions do the same from a list of notes, such as the Newsletter
 * notes View, through Views Bulk Operations. A submission whose form has no
 * Approved element is left alone. Saving a submission redraws the pages
 * that show it (the webform_submission_list cache tag).
 */
abstract class ApprovedActionBase extends ActionBase {

  /**
   * The value saved: '1' shown publicly, '0' hidden.
   */
  protected const VALUE = '1';

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL): void {
    if (!$entity instanceof WebformSubmissionInterface || !$entity->getWebform()->getElement('approved')) {
      return;
    }
    if ((string) $entity->getElementData('approved') === static::VALUE) {
      return;
    }
    $entity->setElementData('approved', static::VALUE);
    $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $object instanceof WebformSubmissionInterface
      ? $object->access('update', $account, TRUE)
      : AccessResult::forbidden();
    return $return_as_object ? $result : $result->isAllowed();
  }

}
