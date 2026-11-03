<?php

/**
 * @file
 * Glossary: the vocabulary behind the tap-to-define terms in guide text.
 *
 * Run from the Drupal root on production:
 *   drush php:script themes/custom/palefire/scripts/palefire_glossary_setup.php
 *   drush cr
 *
 * Optional arguments after "--":
 *   import=1   Also lists the terms of the "Special Collections Glossary"
 *              guide (palefire_guide_special_collections_glossary.php) that
 *              the vocabulary doesn't have yet: each accordion item's title
 *              and text. A dry run: add apply=1 to create them.
 *   apply=1    With import=1, creates the listed terms (published).
 *   title=<t>  The guide's title (default: Special Collections Glossary).
 *
 * What it does:
 * - Creates the vocabulary glossary ("Glossary"). A term's Name is the word
 *   as it reads in text ("Finding aid"); its Description is the definition,
 *   one or two sentences, shown on the card. src/GlossaryTerms.php marks the
 *   first use of each published term in every guide text block (guides,
 *   exhibitions, Section tabs); not in Sources lists or transcriptions.
 * - Adds Other forms (field_glossary_forms, any number): other words for
 *   the same term ("Mss.", "manuscript collection"). A plain plural is
 *   matched without one ("finding aids").
 * - With import=1 and apply=1: imports the guide's terms. Text marked
 *   "[Editor: …]" is left out of a definition.
 *
 * Safe to re-run: anything that already exists is left as it is, and a
 * term whose name is already in the vocabulary is never imported twice.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

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

$vid = 'glossary';

// 1. Vocabulary. ----------------------------------------------------------------
if (!Vocabulary::load($vid)) {
  Vocabulary::create([
    'vid' => $vid,
    'name' => 'Glossary',
    'description' => 'Archive terms patrons meet (finding aid, call number, Reading Room). The first use of each in guide text opens its definition on a tap.',
  ])->save();
  echo "Created vocabulary $vid\n";
}

// 2. Other forms. -----------------------------------------------------------------
if (!FieldStorageConfig::loadByName('taxonomy_term', 'field_glossary_forms')) {
  FieldStorageConfig::create([
    'field_name' => 'field_glossary_forms',
    'entity_type' => 'taxonomy_term',
    'type' => 'string',
    'cardinality' => -1,
    'settings' => ['max_length' => 128],
  ])->save();
  echo "Created storage taxonomy_term.field_glossary_forms\n";
}
if (!FieldConfig::loadByName('taxonomy_term', $vid, 'field_glossary_forms')) {
  FieldConfig::create([
    'field_name' => 'field_glossary_forms',
    'entity_type' => 'taxonomy_term',
    'bundle' => $vid,
    'label' => 'Other forms',
    'description' => 'Other words for this term that should open the same definition ("Mss.", "manuscript collection"). A plain plural ("finding aids") is matched without being listed. Whole words only; capitals don\'t matter.',
    'required' => FALSE,
  ])->save();
  echo "Added field_glossary_forms to taxonomy_term.$vid\n";
}
$repo = \Drupal::service('entity_display.repository');
$form = $repo->getFormDisplay('taxonomy_term', $vid, 'default');
if (!$form->getComponent('field_glossary_forms')) {
  $form->setComponent('field_glossary_forms', ['type' => 'string_textfield', 'weight' => 2, 'settings' => ['size' => 40]])->save();
}
$repo->getViewDisplay('taxonomy_term', $vid, 'default')->removeComponent('field_glossary_forms')->save();

// 3. Import from the glossary guide. ----------------------------------------------
if (($args['import'] ?? '') !== '1') {
  echo "Glossary setup complete. Run: drush cr (import=1 lists the glossary guide's terms to import)\n";
  return;
}
$apply = ($args['apply'] ?? '') === '1';
$title = $args['title'] ?? 'Special Collections Glossary';
$nids = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', $title)
  ->sort('nid', 'DESC')
  ->range(0, 1)
  ->execute();
$page = $nids ? \Drupal::entityTypeManager()->getStorage('node')->load(reset($nids)) : NULL;
if (!$page instanceof NodeInterface || !$page->hasField('field_scroll_section')) {
  echo "No Modular page titled \"$title\" (palefire_guide_special_collections_glossary.php). Nothing to import.\n";
  return;
}

$have = [];
$ids = \Drupal::entityQuery('taxonomy_term')->accessCheck(FALSE)->condition('vid', $vid)->execute();
foreach (Term::loadMultiple($ids) as $term) {
  $have[mb_strtolower(trim($term->getName()))] = TRUE;
}

// Every accordion item in the guide, in page order.
$items = [];
$walk = static function (ParagraphInterface $paragraph) use (&$walk, &$items): void {
  if ($paragraph->bundle() === 'accordion_item') {
    $items[] = $paragraph;
    return;
  }
  foreach (['field_guide_blocks', 'field_accordion_items'] as $field) {
    if ($paragraph->hasField($field)) {
      foreach ($paragraph->get($field)->referencedEntities() as $child) {
        if ($child instanceof ParagraphInterface) {
          $walk($child);
        }
      }
    }
  }
};
foreach ($page->get('field_scroll_section')->referencedEntities() as $section) {
  if ($section instanceof ParagraphInterface) {
    $walk($section);
  }
}

$made = 0;
foreach ($items as $item) {
  $name = trim((string) $item->get('field_item_title')->value);
  if ($name === '' || isset($have[mb_strtolower($name)])) {
    continue;
  }
  $body = $item->hasField('field_block_body') ? $item->get('field_block_body')->first() : NULL;
  $text = trim((string) preg_replace('/\s*\[Editor:[^\]]*\]/u', '', (string) ($body?->value ?? '')));
  if (trim(strip_tags($text)) === '') {
    echo "Skipped $name: no definition.\n";
    continue;
  }
  $have[mb_strtolower($name)] = TRUE;
  echo ($apply ? 'Created' : 'Would create') . " $name\n";
  if ($apply) {
    Term::create([
      'vid' => $vid,
      'name' => $name,
      'description' => ['value' => $text, 'format' => $body->format],
      'status' => 1,
    ])->save();
    $made++;
  }
}
echo $apply ? "Imported $made terms. Run: drush cr\n" : "Dry run: add apply=1 to create them.\n";
