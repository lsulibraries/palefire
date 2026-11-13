<?php

/**
 * @file
 * "LSU Libraries Newsletter": the public archive of the email newsletter. A
 * shelf of past issues' front pages, each opened on the page, and a before/after slider
 * for the newest redesign. A Modular page in guide mode at /v2/newsletters,
 * saved UNPUBLISHED: the slider shows the Fall 2026 issue, so publish the
 * page once that issue is sent. Run palefire_guide_setup.php first;
 * palefire_guide_wide_setup.php gives the gallery and slider the full width.
 *
 *   drush php:script scripts/palefire_newsletter_archive.php
 *   drush php:script scripts/palefire_newsletter_archive.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1.
 *
 * The issues are links in ordinary text blocks, so editors keep the page
 * current in Drupal:
 * - Past issues: a block holding "[newsletter-archive]" and a list, newest
 *   first. Each item is a link to the issue's Constant Contact preview
 *   (campaignlp.constantcontact.com/em/<account>/<id>, the address behind
 *   "View This Email in Your Browser") with the issue's name as its words,
 *   then a colon, its line, and " · " and the day it was sent
 *   (<a>July 2026</a>: The Awards Issue · July 7, 2026). To add an issue
 *   once sent, add a list item at the top. Its front page is a capture kept
 *   in the theme, images/newsletters/<id>.webp (README, Newsletter front pages);
 *   until it is added the issue shows a cover set in type
 *   (Drupal\palefire\NewsletterReview::archive()).
 * - The new look: a block holding "[newsletter-compare]" and two such links,
 *   before then after.
 *
 * Issues as of 2026-11-13, from the campaigns sent in Constant Contact: the
 * 35 newsletters from February 2018 to July 2026 (giving, event, Audubon Day
 * and holiday emails left out; of two sends of one issue, the corrected one).
 * Constant Contact has no public preview of its older "legacy" emails, the
 * newsletters of 2013 to 2017, so they can't be listed here. The issue's
 * name is the one on its masthead, which is sometimes a month after the
 * campaign's (the campaign "January 2021" went out as February 2021).
 *
 * This script owns the page's words until it is edited in Drupal: a run
 * replaces its sections in a new revision and stops if the page was saved
 * outside it (force=1 overrides). Publishing state and alias stay as they
 * are on a rebuild.
 *
 * Optional arguments after "--": apply=1, force=1, path=<alias> (default
 * /v2/newsletters), format=<text format>.
 */

declare(strict_types=1);

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

$title = 'LSU Libraries Newsletter';
$path = '/' . ltrim($args['path'] ?? '/v2/newsletters', '/');

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

$preview = fn (string $id) => 'https://campaignlp.constantcontact.com/em/1114771561534/' . $id;
$issue = fn (string $id, string $name, string $line, string $sent = '') => '<li><a href="' . $preview($id) . '">' . $name . '</a>: ' . $line . ($sent !== '' ? ' · ' . $sent : '') . '</li>';

$intro = '<p>News from LSU Libraries, a few times a year: new collections and acquisitions, research and teaching, events and the people behind them.</p>';

$sections = [
  [
    'title' => 'The new look',
    'wide' => TRUE,
    'blocks' => [
      [NULL, '<p>With the Fall 2026 issue the newsletter takes on LSU’s brand colors: a Night Game purple band for each story, gold rules between them, and section headings and buttons in purple. Slide to compare it with the issue before.</p>'
        . '<p>[newsletter-compare]</p>'
        . '<ul>'
        . $issue('b6748903-9540-4a50-ade8-a48cab890ee6', 'July 2026', 'The Awards Issue')
        . $issue('9e92c0f7-dc6b-4701-b751-8731705cddc1', 'Fall 2026', 'Faculty authors, fall highlights and upcoming events')
        . '</ul>'],
    ],
  ],
  [
    'title' => 'Past issues',
    'wide' => TRUE,
    'blocks' => [
      [NULL, '<p>[newsletter-archive]</p>'
        . '<ul>'
        . $issue('b6748903-9540-4a50-ade8-a48cab890ee6', 'July 2026', 'The Awards Issue', 'July 7, 2026')
        . $issue('70df0842-0b91-419d-b694-dd5ce5818670', 'April 2026', 'A rare first edition of Audubon’s Quadrupeds of North America', 'April 8, 2026')
        . $issue('15ea6191-b5f5-496e-85ed-bc8296c2cb03', 'January 2026', '50 Years of Book Bazaars', 'February 2, 2026')
        . $issue('2b549e7a-fcc8-4a17-95d2-014a39d37ba8', 'October 2025', 'Public Pathogens: Louisiana’s historical struggle with disease', 'October 8, 2025')
        . $issue('99cd96c7-42d1-4a07-b301-a32428303f22', 'August 2025 Special Edition', 'Enhancing LSU research and scholarship, and a student guide to the fall semester', 'August 27, 2025')
        . $issue('de84829b-ff71-441b-8116-da466d524001', 'July 2025', 'From LSU to France: a Fulbright award for Bozza research abroad', 'July 10, 2025')
        . $issue('629060ac-fe5b-432d-8e37-2e8220813b32', 'April 2025', 'A milestone for the new Main Library building, and ten years of free e-textbooks', 'April 7, 2025')
        . $issue('4efc43c2-5645-48ac-9f9d-6c248d8429fb', 'January 2025', 'Remembering Phil Kitchens, a legacy of generosity and intellectual curiosity', 'January 14, 2025')
        . $issue('71c40ebb-a4cf-4903-8e8e-510ccc5fe3b5', 'October 2024', 'Woven Louisiana History: Acadian Textiles', 'October 11, 2024')
        . $issue('f20b85fc-bbaa-487d-a290-21ba5f4c7001', 'August 2024 Special Edition', 'LSU Libraries’ guide to the fall semester', 'August 21, 2024')
        . $issue('fb1bb182-6203-4d5c-a74c-aa0065fb8048', 'July 2024', 'Nicollette Davis named a 2024 Mover &amp; Shaker', 'July 8, 2024')
        . $issue('6cccaf0a-5c1d-428f-a32d-ad1b79558cdc', 'April 2024', '“Literary Layers,” a new permanent display', 'April 4, 2024')
        . $issue('a8ab8fa7-b9fa-497e-ad8f-1f6d0427bfd6', 'January 2024', 'A design team for the new Main Library building', 'January 17, 2024')
        . $issue('35f774c6-fbe9-47a5-b876-b70006601442', 'October 2023', 'The Legacies They Built: Pinkie Gordon Lane, Lutrill and Pearl Payne, and Julian T. White', 'October 5, 2023')
        . $issue('cdb53997-5e82-4ef3-a6cd-fb4c0a700c43', 'August 2023 Special Edition', 'Progress on a new Main Library building', 'August 30, 2023')
        . $issue('228a49d8-f8c3-4993-b0f2-23526849c2a9', 'July 2023', 'A record-breaking LSU Giving Day', 'July 6, 2023')
        . $issue('d9c55af3-89d7-4618-9a68-29ec9e554faa', 'April 2023', 'Descendants of an African American poet find their roots in Special Collections', 'April 4, 2023')
        . $issue('5363d236-e503-4ade-bb5a-2b56f5edde09', 'January 2023', 'Voices of Louisiana Veterans', 'January 6, 2023')
        . $issue('1de07ff9-18df-4036-952a-40735fd6e06d', 'November 2022', 'Water, Water Everywhere: Louisiana’s coastal wetlands', 'November 2, 2022')
        . $issue('42607b72-37a4-4601-b3c7-3e8479653eb9', 'March 2022', 'The Wyatt Houston Day Collection of Poetry by African Americans', 'February 24, 2022')
        . $issue('ca1941ca-0296-4c0c-9020-665f012bbb71', 'December 2021', 'A semester back on campus, and the Free e-Textbooks Initiative', 'December 9, 2021')
        . $issue('fdf91816-cebe-46f2-8d24-449fb1993244', 'May 2021', 'An 1863 muster roll of the Louisiana Volunteer Corps d’Afrique', 'May 3, 2021')
        . $issue('a46fca3f-d434-4a03-830e-117c5e33e956', 'February 2021', 'Laptops for students, and new arrivals of African American literature', 'February 8, 2021')
        . $issue('f3e48b74-6321-4ed3-804d-8b44db2d083a', 'December 2020', 'Congratulations to our graduating employees', 'December 21, 2020')
        . $issue('0a033758-fedb-49f4-aa8a-9dce6914ad30', 'September 2020', 'The Main Library at LSU takes a new name', 'September 15, 2020')
        . $issue('cbb85b00-356d-428d-8bb6-df5b86031d16', 'May 2020', 'Library services during the COVID-19 closures', 'May 11, 2020')
        . $issue('08dc2603-9fea-40c1-8d05-cd395924478f', 'February 2020', 'Special Collections acquires a monumental work of medieval printing', 'February 10, 2020')
        . $issue('4ddcdaca-35b3-474c-925f-3fb2b0bb8aa0', 'December 2019', 'The Mobile Digitization Lab', 'December 10, 2019')
        . $issue('3d90678f-54aa-45d7-a380-088c4112db47', 'September 2019', 'A first-floor transformation for Middleton Library', 'September 9, 2019')
        . $issue('63282cca-c530-4258-9cb1-f09bf9e76a6c', 'May 2019', 'Hill Memorial Library’s roof renovation', 'May 13, 2019')
        . $issue('484ad67f-fe6e-4c3e-a9c4-11ce6ec8be01', 'January 2019', 'Ebony McDonald joins LSU Libraries in its diversity residency program', 'January 22, 2019')
        . $issue('59ea98a1-ffa5-403f-8171-6e9cce31e86b', 'December 2018', '“We Built This City,” an exhibition on urban systems', 'December 17, 2018')
        . $issue('2a8a0690-40ff-4360-9476-1bb488fc0ae6', 'August 2018', 'Digitizing The Daily Reveille, and a new catalog', 'August 27, 2018')
        . $issue('1ca5a846-57e9-48b7-9ce0-f6016d05d1ff', 'May 2018', 'Congratulations, class of 2018', 'May 17, 2018')
        . $issue('3c922394-2843-46f0-a6bd-fc9753647d07', 'February 2018', 'The LSU Libraries Strategic Plan, 2017–2022', 'February 5, 2018')
        . '</ul>'],
    ],
  ],
];

$fingerprint = substr(sha1(json_encode([$intro, $sections], JSON_THROW_ON_ERROR)), 0, 12);
$log_prefix = 'palefire_newsletter_archive.php build';
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
    throw new \RuntimeException("node/{$node->id()} was last saved outside this script (log: \"$last_log\"), perhaps to add an issue. Copy anything worth keeping into this script, then run with force=1.");
  }
  echo "{$prefix}Rebuild node/{$node->id()} at $path in a new revision; it stays " . ($node->isPublished() ? 'published' : 'unpublished') . '.' . PHP_EOL;
}
else {
  echo "{$prefix}Create '$title', unpublished, at $path. Publish it once Fall 2026 is sent." . PHP_EOL;
}
if (!$apply) {
  echo 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
  return;
}

$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];
$has_theme = (bool) FieldConfig::loadByName('paragraph', 'guide_section', 'field_section_theme');
$has_wide = (bool) FieldConfig::loadByName('paragraph', 'guide_section', 'field_section_wide');
$has_style = (bool) FieldConfig::loadByName('paragraph', 'guide_text', 'field_text_style');
$paragraphs = [];
foreach ($sections as $item) {
  $blocks = [];
  foreach ($item['blocks'] as [$heading, $body]) {
    $values = ['type' => 'guide_text', 'field_block_body' => $html($body)];
    if ($heading !== NULL) {
      $values['field_block_title'] = $heading;
    }
    if ($has_style) {
      $values['field_text_style'] = 'default';
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
  'status' => 0,
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
echo "Created '$title', unpublished: node/{$page->id()} (" . $page->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
echo 'Run: drush cr' . PHP_EOL;
