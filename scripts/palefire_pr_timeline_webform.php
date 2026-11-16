<?php

/**
 * @file
 * Creates the public relations timeline Webform, pr_timeline.
 *
 * The timeline page (scripts/palefire_pr_timeline.php) shows this form beside
 * the timeline once it exists. js/pr-timeline.js fills in the item when a
 * reader chooses Comment beside one, and js/public-notes.js sends a thumb
 * through it (item and verdict, no note). Drupal\palefire\PublicNotes counts
 * the thumbs at once and shows a note once staff tick its Approved box.
 * Rename a key below and that stops.
 *
 *   drush php:script scripts/palefire_pr_timeline_webform.php
 *   drush php:script scripts/palefire_pr_timeline_webform.php -- apply=1
 *   drush php:script scripts/palefire_pr_timeline_webform.php -- apply=1 email=group@example.edu
 *   drush cr
 *
 * A dry run unless apply=1. Made before thumbs were added (2026-11-16)? Run
 * it with replace=1 to add Your take and the new thank-you. email= also sends each note to that address
 * (several, comma-separated), so staff know there is one to approve. Notes
 * are kept until deleted; purge_days=N deletes them after N days. If the
 * form already exists it is left alone, so edits made in the UI are kept.
 * replace=1 resets its fields and settings to these (submissions are kept).
 *
 * To approve a note: the Webform's Results tab, open the note, Edit, tick
 * "Approved to show on the timeline", Save. Untick it to take the note down
 * again. The box is private: readers never see it on the form, and a note
 * can't arrive approved.
 *
 * The form has no page of its own: it lives on the timeline page, which is
 * published but unlisted, so anyone with the link can send a note, with a
 * name or without, and Honeypot or Antibot guard it when either module is on.
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
$purge_days = (int) ($args['purge_days'] ?? 0);
$prefix = $apply ? '' : '[dry run] ';

$id = 'pr_timeline';

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

// The keys are the ones Drupal\palefire\PrTimeline and js/pr-timeline.js
// read and fill in: name, item, comment, verdict, approved.
$elements = [
  'name' => [
    '#type' => 'textfield',
    '#title' => 'Your name',
    '#description' => 'Optional. Shown with your note if it is approved; leave it empty to send it anonymously.',
    '#autocomplete' => 'name',
    '#maxlength' => 120,
  ],
  'item' => [
    '#type' => 'textfield',
    '#title' => 'Item',
    '#description' => 'Filled in when you choose Comment beside an item. Leave it empty for the timeline as a whole.',
    '#maxlength' => 255,
  ],
  'comment' => [
    '#type' => 'textarea',
    '#title' => 'Your note',
    // Needed unless a thumb is chosen: a thumb alone is a whole answer.
    '#states' => ['required' => [':input[name="verdict"]' => ['empty' => TRUE]]],
    '#required_error' => 'Write a note, or choose Like or Dislike.',
    '#rows' => 5,
    '#maxlength' => 4000,
  ],
  // Thumbs up and down; the timeline's thumbs fill it in.
  'verdict' => [
    '#type' => 'radios',
    '#title' => 'Your take',
    '#options' => [
      'good' => 'Like',
      'changes' => 'Dislike',
    ],
    '#options_display' => 'side_by_side',
  ],
  // Staff only: a private element is left off the form readers see and
  // shown to those who can see the results; no role may set it when sending.
  'approved' => [
    '#type' => 'checkbox',
    '#title' => 'Approved to show on the timeline',
    '#private' => TRUE,
    '#access_create_roles' => [],
  ],
  'actions' => [
    '#type' => 'webform_actions',
    '#submit__label' => 'Send note',
  ],
];

$settings = [
  // Only on the timeline page, never at /form/pr-timeline.
  'page' => FALSE,
  // Sent without leaving the page, so the reader keeps their place.
  'ajax' => TRUE,
  'ajax_scroll_top' => '',
  'form_remote_addr' => FALSE,
  'form_previous_submissions' => FALSE,
  // A message over a fresh form, so the next note or thumb can follow.
  'confirmation_type' => 'message',
  'confirmation_title' => '',
  'confirmation_message' => '<p>Thank you, it’s in. Thumbs count right away; a note shows on the timeline once staff approve it.</p>',
  'purge' => $purge_days > 0 ? 'all' : 'none',
  'purge_days' => $purge_days > 0 ? $purge_days : NULL,
];

$modules = \Drupal::moduleHandler();
$spam = array_values(array_filter(['honeypot', 'antibot'], [$modules, 'moduleExists']));

echo "{$prefix}" . ($existing ? 'Resetting' : 'Creating') . " $id (no page of its own; shown on /v2/internal/pr-timeline)\n";
echo "{$prefix}Fields: " . implode(', ', array_keys(array_diff_key($elements, ['actions' => 1]))) . " (approved is staff only)\n";
echo "{$prefix}Spam protection: " . ($spam ? implode(', ', $spam) : 'none (neither Honeypot nor Antibot is enabled)') . "\n";
echo "{$prefix}Deleted after: " . ($purge_days > 0 ? "$purge_days days" : 'never') . "\n";
echo "{$prefix}Email: " . ($email !== '' ? $email : 'none (notes are in the Webform\'s Results tab)') . "\n";

if (!$apply) {
  echo "Dry run. Add apply=1 to make it.\n";
  return;
}

$webform = $existing ?: Webform::create(['id' => $id]);
$webform->set('title', 'PR timeline notes');
$webform->set('description', 'Notes on the public relations timeline at /v2/internal/pr-timeline. Tick Approved on a note to show it there. Created by the Palefire theme\'s scripts/palefire_pr_timeline_webform.php.');
$webform->set('category', 'Palefire');
$webform->setStatus(WebformInterface::STATUS_OPEN);
$webform->setElements($elements);
foreach ($settings as $key => $value) {
  $webform->setSetting($key, $value);
}
// Anyone with the link may send a note, without signing in.
$access = $webform->getAccessRules();
$access['create']['roles'] = array_values(array_unique(array_merge($access['create']['roles'] ?? [], ['anonymous', 'authenticated'])));
$webform->setAccessRules($access);
if (in_array('honeypot', $spam, TRUE)) {
  $webform->setThirdPartySetting('honeypot', 'honeypot', TRUE);
  // No time limit: a one-line note is a real answer.
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
      'subject' => 'PR timeline note to approve: [webform_submission:values:item]',
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
