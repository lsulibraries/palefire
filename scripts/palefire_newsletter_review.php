<?php

/**
 * @file
 * A review page for an email newsletter drafted in Constant Contact: the
 * draft as it is now, a Comment button beside each story, and a feedback
 * form that follows the reader down the page. A Modular page in guide mode,
 * PUBLISHED so the people featured can open it without signing in, at an
 * address nothing links to, and never indexed (palefire_page_attachments_alter()).
 * Run palefire_guide_setup.php first; palefire_guide_wide_setup.php gives the
 * draft and the form the full width.
 *
 *   drush php:script scripts/palefire_newsletter_review.php
 *   drush php:script scripts/palefire_newsletter_review.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. Send people the page's address; add
 * ?for=<words from their headline or name> (for example ?for=lipsey or
 * ?for=paolo-chirumbolo) to land each person on their own story, marked.
 *
 * The draft itself is never saved here: a text block holding
 * "[newsletter-review:<account>/<campaign activity id>]" draws Constant
 * Contact's public preview of that campaign live, kept five minutes
 * (Drupal\palefire\NewsletterReview). Edit the campaign in Constant Contact
 * and the page follows.
 *
 * Comments go to the Webform "newsletter_review", created by hand in the
 * Webform UI (fields in docs/styleguide.md, Newsletter review). Until it
 * exists the page asks readers to reply to the email instead.
 *
 * Run it again for the next issue with its own issue=, campaign= and path=.
 * Each issue is its own page. A page is rebuilt in a new revision when this
 * script's text changes; it stops if the page was edited in Drupal
 * (force=1 overrides).
 *
 * Optional arguments after "--":
 *   apply=1               Save.
 *   force=1               Replace the page even if it was edited in Drupal.
 *   issue=<name>          Default "Fall 2026".
 *   campaign=<id>         The campaign's activity id, from the address of
 *                         its editor in Constant Contact (.../edit/activity/<id>).
 *   account=<number>      The Constant Contact account in the preview address.
 *   path=<alias>          Default /v2/special/internal/newsletter-review/<issue>.
 *   format=<text format>  Default: the WYSIWYG format.
 */

declare(strict_types=1);

use Drupal\Component\Utility\Html;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

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
$force = ($args['force'] ?? '') === '1';

foreach (['guide_section', 'guide_text'] as $id) {
  if (!\Drupal::entityTypeManager()->getStorage('paragraphs_type')->load($id)) {
    throw new \RuntimeException("'$id' not found. Run palefire_guide_setup.php first.");
  }
}

$issue = $args['issue'] ?? 'Fall 2026';
$account = $args['account'] ?? '1114771561534';
$campaign = strtolower($args['campaign'] ?? '9e92c0f7-dc6b-4701-b751-8731705cddc1');
$marker = "[newsletter-review:$account/$campaign]";
if (!preg_match(\Drupal\palefire\NewsletterReview::MARKER, "<p>$marker</p>")) {
  throw new \RuntimeException("account= must be digits and campaign= a Constant Contact activity id (8-4-4-4-12 hex).");
}
$slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($issue)), '-');
$path = '/' . ltrim($args['path'] ?? "/v2/special/internal/newsletter-review/$slug", '/');
$title = "Newsletter Review: $issue";
$name = Html::escape($issue);

$formats = filter_formats();
$format = $args['format'] ?? NULL;
foreach ($format === NULL ? $formats : [] as $id => $candidate) {
  if ($id === 'wysiwyg' || strcasecmp((string) $candidate->label(), 'WYSIWYG') === 0) {
    $format = $id;
    break;
  }
}
if ($format === NULL || !isset($formats[$format])) {
  throw new \RuntimeException('No WYSIWYG text format found. Pass format=<machine name>.');
}

$intro = "<p>A draft of the LSU Libraries Newsletter, $name, for the people in it and those who wrote for it. Read your part and anything else you like, and tell us what to fix before it goes out.</p>";

$sections = [
  [
    'title' => 'The draft',
    'wide' => TRUE,
    'blocks' => [
      [NULL, <<<HTML
<p>This is the email as it stands in Constant Contact right now; changes appear here within five minutes. Choose <strong>Comment</strong> beside a story to send a note about it, or write about the whole issue. Your name and notes go only to the newsletter team.</p>
<p>$marker</p>
HTML, 'default'],
    ],
  ],
  [
    'title' => 'About this review',
    'wide' => FALSE,
    'blocks' => [
      [NULL, <<<'HTML'
<ul>
<li>Check names, titles, dates and figures first: those are the hardest to fix once the email is sent.</li>
<li>Each note is saved with the draft's version, shown above the email, so we can see what you were looking at.</li>
<li>Links in the email open in a new tab. The look of the email in your own inbox may differ slightly.</li>
<li>This page isn’t listed anywhere or found by search engines. Please don’t pass the link on beyond the people asked to review.</li>
</ul>
HTML, 'default'],
    ],
  ],
];

$fingerprint = substr(sha1(json_encode([$intro, $sections], JSON_THROW_ON_ERROR)), 0, 12);
$log_prefix = 'palefire_newsletter_review.php build';
$log_message = "$log_prefix $fingerprint";
$prefix = $apply ? '' : '[dry run] ';

$repository = \Drupal::service('path_alias.repository');
$alias = $repository->lookupByAlias($path, 'en') ?? $repository->lookupByAlias($path, \Drupal::languageManager()->getDefaultLanguage()->getId());
$storage = \Drupal::entityTypeManager()->getStorage('node');
$node = NULL;
if ($alias && preg_match('#^/node/(\d+)$#', $alias['path'], $m)) {
  $node = $storage->load((int) $m[1]);
  if (!$node || $node->bundle() !== 'modular_page') {
    throw new \RuntimeException("$path belongs to {$alias['path']}, which is not a Modular page. Choose another path=.");
  }
}

echo "Issue: $issue. Draft: https://campaignlp.constantcontact.com/em/$account/$campaign" . PHP_EOL;
$types = \Drupal::entityTypeManager();
if (!$types->hasDefinition('webform') || !$types->getStorage('webform')->load(\Drupal\palefire\NewsletterReview::WEBFORM)) {
  echo "No Webform 'newsletter_review' yet: the page will ask readers to reply by email until it is created (docs/styleguide.md, Newsletter review)." . PHP_EOL;
}
if ($node) {
  $latest = $storage->getLatestRevisionId($node->id());
  if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
    throw new \RuntimeException("'{$node->label()}' has a newer draft (revision $latest). Publish or discard it first.");
  }
  $last_log = (string) $node->getRevisionLogMessage();
  if ($last_log === $log_message) {
    echo "node/{$node->id()} at $path already has this build. Nothing to do." . PHP_EOL;
    return;
  }
  if (!str_starts_with($last_log, $log_prefix) && !$force) {
    throw new \RuntimeException("node/{$node->id()} was last saved outside this script (log: \"$last_log\"). Copy anything worth keeping into this script, then run with force=1.");
  }
  echo "{$prefix}Rebuild node/{$node->id()} at $path in a new revision; it stays " . ($node->isPublished() ? 'published' : 'unpublished') . '.' . PHP_EOL;
}
else {
  echo "{$prefix}Create '$title', published, at $path." . PHP_EOL;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];
$has_style = (bool) FieldConfig::loadByName('paragraph', 'guide_text', 'field_text_style');
$has_theme = (bool) FieldConfig::loadByName('paragraph', 'guide_section', 'field_section_theme');
$has_wide = (bool) FieldConfig::loadByName('paragraph', 'guide_section', 'field_section_wide');
$paragraphs = [];
foreach ($sections as $item) {
  $blocks = [];
  foreach ($item['blocks'] as [$heading, $body, $style]) {
    $values = ['type' => 'guide_text', 'field_block_body' => $html($body)];
    if ($heading !== NULL) {
      $values['field_block_title'] = $heading;
    }
    if ($has_style) {
      $values['field_text_style'] = $style;
    }
    $blocks[] = Paragraph::create($values);
  }
  $values = [
    'type' => 'guide_section',
    'field_section_title' => $item['title'],
    'field_guide_blocks' => $blocks,
  ];
  if ($has_theme) {
    $values['field_section_theme'] = 'light';
  }
  if ($has_wide) {
    $values['field_section_wide'] = $item['wide'] ? 1 : 0;
  }
  $paragraphs[] = ['entity' => Paragraph::create($values)];
}

$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');
if ($node) {
  $node->setTitle($title);
  $node->set('field_scroll_section', $paragraphs);
  if (isset($node_fields['field_intro'])) {
    $node->set('field_intro', $html($intro));
  }
  $node->setNewRevision(TRUE);
  $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
  $node->setRevisionLogMessage($log_message);
  $node->save();
  echo "Saved a new revision: node/{$node->id()} at $path. Run: drush cr" . PHP_EOL;
  return;
}

$values = [
  'type' => 'modular_page',
  'title' => $title,
  'status' => 1,
  'promote' => 0,
  'field_scroll_section' => $paragraphs,
  'path' => ['alias' => $path, 'pathauto' => 0],
];
if (isset($node_fields['field_modular_type'])) {
  $values['field_modular_type'] = 'guide';
}
if (isset($node_fields['field_intro'])) {
  $values['field_intro'] = $html($intro);
}
$page = Node::create($values);
$page->setRevisionLogMessage($log_message);
$page->save();
echo "Created '$title', published: node/{$page->id()} (" . $page->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
echo 'Run: drush cr' . PHP_EOL;
