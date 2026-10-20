<?php

/**
 * @file
 * Moves every guide page's text to the WYSIWYG text format.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_wysiwyg.php
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_wysiwyg.php -- apply=1
 *   drush cr
 *
 * Optional arguments after "--":
 *   apply=1               Save the changes. Without it the script only lists
 *                         what it would change.
 *   format=<text format>  Target format (default: the format whose machine
 *                         name is "wysiwyg" or whose label is "WYSIWYG").
 *   force=1               Convert even if the format's allowed HTML seems to
 *                         be missing tags guides use.
 *
 * Covers every Modular page in guide mode: its own formatted text fields
 * (the intro) and every paragraph under it, nested accordion items
 * included. Each changed page gets a new revision, so the old formats can
 * be restored from the Revisions tab. Text is not rewritten, only its
 * format, so a format that strips tags (tables, Heading 4) would hide
 * them: the script checks the allowed HTML first.
 */

declare(strict_types=1);

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\ParagraphInterface;

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
$apply = !empty($args['apply']);

// Same lookup as the guide scripts.
$formats = filter_formats();
$format = $args['format'] ?? NULL;
foreach ($format === NULL ? $formats : [] as $id => $candidate) {
  if ($id === 'wysiwyg' || strcasecmp((string) $candidate->label(), 'WYSIWYG') === 0) {
    $format = $id;
    break;
  }
}
if ($format === NULL) {
  throw new \RuntimeException('No WYSIWYG text format found. Pass format=<machine name>.');
}
if (!isset($formats[$format])) {
  throw new \RuntimeException("Text format '$format' not found.");
}
echo "Target format: $format (" . $formats[$format]->label() . ')' . PHP_EOL;

// The tags guide text uses (README: Guide pages > Text format).
$html_filter = $formats[$format]->filters('filter_html');
if ($html_filter->status) {
  $allowed = (string) ($html_filter->settings['allowed_html'] ?? '');
  $missing = array_filter(
    ['<table', '<caption', '<th', '<h4', '<ol', '<ul', '<code', '<a'],
    fn ($tag) => !preg_match('/' . preg_quote($tag, '/') . '[\s>]/', $allowed),
  );
  if ($missing) {
    $list = implode('>, ', $missing) . '>';
    if (empty($args['force'])) {
      throw new \RuntimeException("'$format' doesn't allow $list, which guides use. Allow them in the format, or re-run with force=1.");
    }
    echo "Warning: '$format' doesn't allow $list; that markup will be stripped on display." . PHP_EOL;
  }
}

/**
 * Sets $format on an entity's formatted text and on the paragraphs under it.
 *
 * Returns how many field items changed. Changed paragraphs, and every
 * paragraph above them, are marked to be saved with their host.
 */
$convert = function (FieldableEntityInterface $entity) use (&$convert, $format): int {
  $changed = 0;
  foreach ($entity->getFields() as $field) {
    $type = $field->getFieldDefinition()->getType();
    if (in_array($type, ['text', 'text_long', 'text_with_summary'], TRUE)) {
      foreach ($field as $item) {
        if ($item->value !== NULL && $item->format !== $format) {
          $item->format = $format;
          $changed++;
        }
      }
    }
    elseif ($type === 'entity_reference_revisions') {
      foreach ($field as $item) {
        if ($item->entity instanceof ParagraphInterface) {
          $changed += $convert($item->entity);
        }
      }
    }
  }
  if ($changed && $entity instanceof ParagraphInterface) {
    $entity->setNeedsSave(TRUE);
  }
  return $changed;
};

$query = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page');
$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');
if (isset($node_fields['field_modular_type'])) {
  $query->condition('field_modular_type', 'guide');
}
$nids = $query->execute();

$total = 0;
foreach (Node::loadMultiple($nids) as $node) {
  $changed = $convert($node);
  if (!$changed) {
    echo "  node/{$node->id()} {$node->label()}: already $format" . PHP_EOL;
    continue;
  }
  $total++;
  echo "  node/{$node->id()} {$node->label()}: $changed text field(s)" . ($apply ? ' converted' : ' to convert') . PHP_EOL;
  if ($apply) {
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage("Guide text moved to the $format text format (palefire_guide_wysiwyg.php).");
    $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $node->save();
  }
}

echo count($nids) . " guide page(s), $total to change." . PHP_EOL;
if ($total && !$apply) {
  echo 'Nothing saved. Re-run with: -- apply=1' . PHP_EOL;
}
elseif ($total) {
  echo 'Run: drush cr' . PHP_EOL;
}
