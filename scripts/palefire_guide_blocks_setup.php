<?php

/**
 * @file
 * Creates four more guide blocks: Flow chart, Status board, Figures and Code.
 *
 * Templates: paragraph--guide-flow, -guide-status-board, -guide-stats and
 * -guide-code. Styles: scss/guide-blocks.scss, library palefire/guide-blocks,
 * attached by those templates, so other pages don't load it.
 *
 * Editors need no code: every block is filled in through labelled form fields,
 * with the WYSIWYG editor for notes and captions (bold, links and so on), a
 * menu for each status or kind, and drag handles to reorder. A flow chart is
 * typed step by step; the theme draws the boxes and arrows.
 *
 * Run from the Drupal root on production, after palefire_guide_setup.php:
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_blocks_setup.php
 *   drush cr
 *
 * What it does:
 * - Creates the paragraph types:
 *     guide_flow          Flow chart: Heading, Intro, Steps, Caption.
 *     guide_flow_step     A step on the main line, with an optional branch
 *                         to one side and a label on the arrow to the next.
 *     guide_status_board  Status board: Heading, Intro, Items.
 *     guide_status_item   Status (Done, Running, Needs attention, Not
 *                         started), Title, Note.
 *     guide_stats         Figures: Heading, Intro, Figures.
 *     guide_stat          Value ("5.7 TB") and Label.
 *     guide_code          Code: Heading, Code, Caption.
 * - Allows guide_flow, guide_status_board, guide_stats and guide_code in the
 *   blocks of a guide section and of a Section tabs tab. Exhibition entries
 *   also have field_guide_blocks; tick them there by hand if wanted.
 *
 * Safe to re-run: anything that already exists is left as it is.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\paragraphs\Entity\ParagraphsType;

if (PHP_SAPI !== 'cli') {
  return;
}

foreach (['field_block_title', 'field_block_intro', 'field_item_title'] as $needed) {
  if (!FieldStorageConfig::loadByName('paragraph', $needed)) {
    echo "Field $needed not found: run palefire_guide_setup.php first.\n";
    return;
  }
}
if (!FieldConfig::loadByName('paragraph', 'guide_section', 'field_guide_blocks')) {
  echo "guide_section has no field_guide_blocks: run palefire_guide_setup.php first.\n";
  return;
}

$repo = \Drupal::service('entity_display.repository');

// 1. Paragraph types. -----------------------------------------------------------
$types = [
  'guide_flow' => ['Guide: Flow chart', 'Steps down a main line, each with an optional branch to one side. Reflows on phones and reads as a list to screen readers.'],
  'guide_flow_step' => ['Flow chart step', 'One step of a flow chart: its box, an optional branch, and the label on the arrow to the next step.'],
  'guide_status_board' => ['Guide: Status board', 'Tiles showing where each piece of work stands.'],
  'guide_status_item' => ['Status board item', 'One tile: a status, a title and a short note.'],
  'guide_stats' => ['Guide: Figures', 'A row of large numbers with labels.'],
  'guide_stat' => ['Figure', 'One large number and its label.'],
  'guide_code' => ['Guide: Code', 'Commands or code, kept exactly as typed and scrolling inside the block.'],
];
foreach ($types as $id => [$label, $description]) {
  if (!ParagraphsType::load($id)) {
    ParagraphsType::create(['id' => $id, 'label' => $label, 'description' => $description])->save();
    echo "Created paragraph type $id\n";
  }
}

// 2. Field storages. -------------------------------------------------------------
$storages = [
  'field_flow_steps' => ['entity_reference_revisions', ['target_type' => 'paragraph'], -1],
  'field_flow_caption' => ['text_long', [], 1],
  'field_flow_kind' => ['list_string', ['allowed_values' => ['step' => 'Step', 'start' => 'Start or end', 'question' => 'Question', 'person' => 'A person does this']], 1],
  'field_flow_note' => ['text_long', [], 1],
  'field_flow_group' => ['string', ['max_length' => 128], 1],
  'field_flow_link' => ['string', ['max_length' => 64], 1],
  'field_flow_branch_label' => ['string', ['max_length' => 64], 1],
  'field_flow_branch_title' => ['string', ['max_length' => 255], 1],
  'field_flow_branch_note' => ['text_long', [], 1],
  'field_flow_branch_tone' => ['list_string', ['allowed_values' => ['plain' => 'Plain', 'good' => 'Good outcome', 'attention' => 'Needs attention']], 1],
  'field_status_items' => ['entity_reference_revisions', ['target_type' => 'paragraph'], -1],
  'field_status_state' => ['list_string', ['allowed_values' => ['done' => 'Done', 'running' => 'Running', 'attention' => 'Needs attention', 'todo' => 'Not started']], 1],
  'field_status_note' => ['text_long', [], 1],
  'field_stat_items' => ['entity_reference_revisions', ['target_type' => 'paragraph'], -1],
  'field_stat_value' => ['string', ['max_length' => 32], 1],
  'field_stat_label' => ['string', ['max_length' => 128], 1],
  'field_code_text' => ['string_long', [], 1],
  'field_code_caption' => ['text_long', [], 1],
];
foreach ($storages as $name => [$type, $settings, $cardinality]) {
  if (!FieldStorageConfig::loadByName('paragraph', $name)) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'type' => $type,
      'settings' => $settings,
      'cardinality' => $cardinality,
    ])->save();
    echo "Created storage paragraph.$name\n";
  }
}

// 3. Fields, in form order: [label, required, description, default]. ---------------
$block_title = ['Heading', FALSE, 'Optional. Shows as a sub-link under the section in "On this page".'];
$block_intro = ['Intro (optional)', FALSE, ''];
$fields = [
  'guide_flow' => [
    'field_block_title' => $block_title,
    'field_block_intro' => $block_intro,
    'field_flow_steps' => ['Steps', TRUE, 'From top to bottom. Each step can branch once, to one side.'],
    'field_flow_caption' => ['Caption', FALSE, 'One or two sentences on how to read the chart, or what matters in it.'],
  ],
  'guide_flow_step' => [
    'field_item_title' => ['Title', TRUE, 'The words in the box.'],
    'field_flow_note' => ['Note', FALSE, 'A short line under the title.'],
    'field_flow_kind' => ['Kind', TRUE, '', 'step'],
    'field_flow_group' => ['Group label', FALSE, 'Optional. Starts a labelled group of steps here: "Settled quickly, without a full read".'],
    'field_flow_link' => ['Label on the arrow to the next step', FALSE, 'Optional: "no", "go-ahead".'],
    'field_flow_branch_title' => ['Branch: title', FALSE, 'Optional. Fill this in to add a branch box beside the step.'],
    'field_flow_branch_label' => ['Branch: label on its arrow', FALSE, 'Optional: "yes", "failed".'],
    'field_flow_branch_note' => ['Branch: note', FALSE, ''],
    'field_flow_branch_tone' => ['Branch: tone', TRUE, 'The words must say the outcome too: tone is only a visual aid.', 'plain'],
  ],
  'guide_status_board' => [
    'field_block_title' => $block_title,
    'field_block_intro' => $block_intro,
    'field_status_items' => ['Items', TRUE, ''],
  ],
  'guide_status_item' => [
    'field_status_state' => ['Status', TRUE, '', 'todo'],
    'field_item_title' => ['Title', TRUE, ''],
    'field_status_note' => ['Note', FALSE, 'One to three sentences.'],
  ],
  'guide_stats' => [
    'field_block_title' => $block_title,
    'field_block_intro' => $block_intro,
    'field_stat_items' => ['Figures', TRUE, 'Two to four read best.'],
  ],
  'guide_stat' => [
    'field_stat_value' => ['Value', TRUE, 'Short: "~663k", "5.7 TB".'],
    'field_stat_label' => ['Label', TRUE, 'What it counts.'],
  ],
  'guide_code' => [
    'field_block_title' => $block_title,
    'field_code_text' => ['Code', TRUE, 'Printed exactly as typed. Long lines scroll inside the block.'],
    'field_code_caption' => ['Caption', FALSE, 'Optional: what it does, and where to run it.'],
  ],
];
$children = [
  'field_flow_steps' => 'guide_flow_step',
  'field_status_items' => 'guide_status_item',
  'field_stat_items' => 'guide_stat',
];
foreach ($fields as $bundle => $bundle_fields) {
  foreach ($bundle_fields as $name => $spec) {
    if (FieldConfig::loadByName('paragraph', $bundle, $name)) {
      continue;
    }
    $values = [
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => $bundle,
      'label' => $spec[0],
      'required' => $spec[1],
      'description' => $spec[2],
    ];
    if (isset($spec[3])) {
      $values['default_value'] = [['value' => $spec[3]]];
    }
    if (isset($children[$name])) {
      $child = $children[$name];
      $values['settings'] = [
        'handler' => 'default:paragraph',
        'handler_settings' => [
          'target_bundles' => [$child => $child],
          'negate' => 0,
          'target_bundles_drag_drop' => [$child => ['enabled' => TRUE, 'weight' => 0]],
        ],
      ];
    }
    FieldConfig::create($values)->save();
    echo "Added $name to paragraph.$bundle\n";
  }
}

// 4. Widgets and displays. The templates print everything but the intro. ----------
foreach ($fields as $bundle => $bundle_fields) {
  $form = $repo->getFormDisplay('paragraph', $bundle, 'default');
  $view = $repo->getViewDisplay('paragraph', $bundle, 'default');
  $weight = 0;
  foreach (array_keys($bundle_fields) as $name) {
    $current = $weight++;
    if ($name === 'field_block_intro') {
      if (!$form->getComponent($name)) {
        $form->setComponent($name, ['type' => 'text_textarea', 'weight' => $current, 'settings' => ['rows' => 3, 'placeholder' => '']]);
      }
      $view->setComponent($name, ['type' => 'text_default', 'label' => 'hidden', 'weight' => $current]);
      continue;
    }
    $view->removeComponent($name);
    if ($form->getComponent($name)) {
      continue;
    }
    if (isset($children[$name])) {
      $form->setComponent($name, [
        'type' => 'paragraphs',
        'weight' => $current,
        'settings' => [
          'title' => $types[$children[$name]][0],
          'title_plural' => $bundle_fields[$name][0],
          'edit_mode' => 'closed',
          'closed_mode' => 'summary',
          'autocollapse' => 'none',
          'add_mode' => 'button',
          'form_display_mode' => 'default',
          'default_paragraph_type' => $children[$name],
        ],
      ]);
      continue;
    }
    $widget = match (FieldStorageConfig::loadByName('paragraph', $name)->getType()) {
      'list_string' => ['type' => 'options_select'],
      'text_long' => ['type' => 'text_textarea', 'settings' => ['rows' => 2, 'placeholder' => '']],
      'string_long' => ['type' => 'string_textarea', 'settings' => ['rows' => $name === 'field_code_text' ? 8 : 2, 'placeholder' => '']],
      default => ['type' => 'string_textfield', 'settings' => ['size' => 60, 'placeholder' => '']],
    };
    $form->setComponent($name, ['weight' => $current] + $widget);
  }
  $form->save();
  $view->save();
}

// 5. Allow the blocks in guide sections and Section tabs tabs. ----------------------
foreach (['guide_section', 'section_tab'] as $bundle) {
  $config = FieldConfig::loadByName('paragraph', $bundle, 'field_guide_blocks');
  if (!$config) {
    continue;
  }
  $handler = $config->getSetting('handler_settings') ?: [];
  if (!empty($handler['negate'])) {
    echo "Skipped $bundle: its block list excludes types instead of listing them, so check the new ones are allowed.\n";
    continue;
  }
  $changed = FALSE;
  foreach (['guide_flow', 'guide_status_board', 'guide_stats', 'guide_code'] as $block) {
    if (!isset($handler['target_bundles'][$block])) {
      $handler['target_bundles'][$block] = $block;
      if (isset($handler['target_bundles_drag_drop'])) {
        $handler['target_bundles_drag_drop'][$block] = ['enabled' => TRUE, 'weight' => count($handler['target_bundles_drag_drop'])];
      }
      $changed = TRUE;
    }
  }
  if ($changed) {
    $config->setSetting('handler_settings', $handler)->save();
    echo "Allowed the new blocks in paragraph.$bundle\n";
  }
}

echo "Done. Run: drush cr\n";
