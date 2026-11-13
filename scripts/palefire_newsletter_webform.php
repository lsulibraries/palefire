<?php

/**
 * @file
 * Creates the newsletter review Webform, newsletter_review.
 *
 * The review pages (scripts/palefire_newsletter_review.php) show this form
 * beside the Constant Contact draft once it exists; until then they ask
 * readers to reply to the email instead. Drupal\palefire\NewsletterReview
 * renders it with the issue and the draft's version filled in, and
 * js/newsletter-review.js fills in the section when a reader chooses
 * Comment beside a story. Rename a key below and that stops.
 *
 *   drush php:script scripts/palefire_newsletter_webform.php
 *   drush php:script scripts/palefire_newsletter_webform.php -- apply=1
 *   drush php:script scripts/palefire_newsletter_webform.php -- apply=1 email=group@example.edu
 *   drush cr
 *
 * A dry run unless apply=1. email= also sends each note to that address
 * (several, comma-separated). Notes are deleted after 365 days;
 * purge_days=N changes that, and purge_days=0 keeps them. If the form already
 * exists it is left alone, so edits made in the UI are kept. replace=1 resets
 * its fields and settings to these (submissions are kept).
 *
 * The form has no page of its own: it lives on the review pages, which are
 * published but unlisted, so anyone with the link can send a note. Every
 * note says who sent it (a name is required), and Honeypot or Antibot guard
 * it when either module is on.
 */

declare(strict_types=1);

use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformInterface;

if (PHP_SAPI !== 'cli') {
  return;
}

$args = [];
foreach ((isset($extra) && is_array($extra)) ? $extra : [] as $arg) {
  if (str_contains((string) $arg, '=')) {
    [$k, $v] = explode('=', (string) $arg, 2);
    $args[trim($k)] = trim($v);
  }
}
$apply = ($args['apply'] ?? '') === '1';
$replace = ($args['replace'] ?? '') === '1';
$email = $args['email'] ?? '';
$purge_days = (int) ($args['purge_days'] ?? 365);
$prefix = $apply ? '' : '[dry run] ';

$id = 'newsletter_review';

if (!\Drupal::moduleHandler()->moduleExists('webform')) {
  echo "The Webform module isn't enabled. Nothing done.\n";
  return;
}
foreach ($email !== '' ? array_map('trim', explode(',', $email)) : [] as $address) {
  if (!\Drupal::service('email.validator')->isValid($address)) {
    echo "email= isn't an email address: $address. Nothing done.\n";
    return;
  }
}

$existing = Webform::load($id);
if ($existing && !$replace) {
  echo "$id already exists. Left as it is; replace=1 resets it.\n";
  return;
}

// The keys are the ones NewsletterReview::form() and js/newsletter-review.js
// fill in: section, issue, version.
$elements = [
  'name' => [
    '#type' => 'textfield',
    '#title' => 'Your name',
    '#required' => TRUE,
    '#required_error' => 'Add your name, so we know whose note it is.',
    '#autocomplete' => 'name',
    '#maxlength' => 120,
  ],
  'section' => [
    '#type' => 'textfield',
    '#title' => 'Section',
    '#description' => 'Filled in when you choose Comment beside a story. Leave it empty for the whole issue.',
    '#maxlength' => 255,
  ],
  'comment' => [
    '#type' => 'textarea',
    '#title' => 'Your note',
    '#description' => 'What to fix or change: names, titles, dates, figures, wording, photos.',
    '#required' => TRUE,
    '#required_error' => 'Write your note before sending.',
    '#rows' => 5,
    '#maxlength' => 4000,
  ],
  'verdict' => [
    '#type' => 'radios',
    '#title' => 'Overall',
    '#options' => [
      'good' => 'Looks good',
      'changes' => 'Needs changes',
    ],
    '#options_display' => 'side_by_side',
  ],
  // Filled in by the review page, never shown.
  'issue' => ['#type' => 'hidden', '#title' => 'Issue (Constant Contact campaign)'],
  'version' => ['#type' => 'hidden', '#title' => 'Draft version'],
  'actions' => [
    '#type' => 'webform_actions',
    '#submit__label' => 'Send note',
  ],
];

$settings = [
  // Only on the review pages, never at /form/newsletter-review.
  'page' => FALSE,
  // Sent without leaving the page, so the reader keeps their place in the
  // email and can send the next note.
  'ajax' => TRUE,
  'ajax_scroll_top' => '',
  'form_remote_addr' => FALSE,
  'form_previous_submissions' => FALSE,
  'confirmation_type' => 'inline',
  'confirmation_title' => '',
  'confirmation_message' => '<p>Thank you, your note is in. Choose Comment beside another story, or send another below.</p>',
  'confirmation_back' => TRUE,
  'confirmation_back_label' => 'Send another note',
  'purge' => $purge_days > 0 ? 'all' : 'none',
  'purge_days' => $purge_days > 0 ? $purge_days : NULL,
];

$modules = \Drupal::moduleHandler();
$spam = array_values(array_filter(['honeypot', 'antibot'], [$modules, 'moduleExists']));

echo "{$prefix}" . ($existing ? 'Resetting' : 'Creating') . " $id (no page of its own; shown on /v2/special/internal/newsletter-review/<issue>)\n";
echo "{$prefix}Fields: " . implode(', ', array_keys(array_diff_key($elements, ['actions' => 1]))) . "\n";
echo "{$prefix}Spam protection: " . ($spam ? implode(', ', $spam) : 'none (neither Honeypot nor Antibot is enabled)') . "\n";
echo "{$prefix}Deleted after: " . ($purge_days > 0 ? "$purge_days days" : 'never') . "\n";
echo "{$prefix}Email: " . ($email !== '' ? $email : 'none (notes are in the Webform\'s Results tab)') . "\n";

if (!$apply) {
  echo "Dry run. Add apply=1 to make it.\n";
  return;
}

$webform = $existing ?: Webform::create(['id' => $id]);
$webform->set('title', 'Newsletter review');
$webform->set('description', 'Notes on a newsletter draft from the review pages under /v2/special/internal/newsletter-review. Created by the Palefire theme\'s scripts/palefire_newsletter_webform.php.');
$webform->set('category', 'Palefire');
$webform->setStatus(WebformInterface::STATUS_OPEN);
$webform->setElements($elements);
foreach ($settings as $key => $value) {
  $webform->setSetting($key, $value);
}
// Anyone with the review link may send a note: the people featured in an
// issue often have no Drupal account.
$access = $webform->getAccessRules();
$access['create']['roles'] = array_values(array_unique(array_merge($access['create']['roles'] ?? [], ['anonymous', 'authenticated'])));
$webform->setAccessRules($access);
if (in_array('honeypot', $spam, TRUE)) {
  $webform->setThirdPartySetting('honeypot', 'honeypot', TRUE);
  // No time limit: a quick "Looks good" is a real answer.
  $webform->setThirdPartySetting('honeypot', 'time_restriction', FALSE);
}
if (in_array('antibot', $spam, TRUE)) {
  $webform->setThirdPartySetting('antibot', 'antibot', TRUE);
}
$webform->save();

if ($email !== '') {
  $handler_id = 'email_note';
  $configuration = [
    'id' => 'email',
    'handler_id' => $handler_id,
    'label' => 'Email each note',
    'status' => TRUE,
    'weight' => 0,
    'settings' => [
      'to_mail' => $email,
      'from_mail' => '_default',
      'subject' => 'Newsletter note from [webform_submission:values:name]: [webform_submission:values:section]',
      'body' => '_default',
    ],
  ];
  if ($webform->getHandlers()->has($handler_id)) {
    $webform->deleteWebformHandler($webform->getHandler($handler_id));
  }
  $handler = \Drupal::service('plugin.manager.webform.handler')->createInstance('email', $configuration);
  $webform->addWebformHandler($handler);
}

echo "Done: $id. Results: " . $webform->toUrl('results-submissions', ['absolute' => TRUE])->toString() . "\n";
echo "Run: drush cr\n";
