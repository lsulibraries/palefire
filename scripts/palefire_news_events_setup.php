<?php

/**
 * @file
 * Creates the "News and events" paragraph type and adds it to Special.
 *
 * News and events (news_events): the next events, the newest blog post and
 * the latest Instagram post (paragraph--news-events.html.twig, js/news-events.js).
 * Events and Instagram come from the libcal_gantt module (its News and
 * events settings and its Instagram pull); the blog post from the
 * section's Blog categories, as the Landing hero's highlights did.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_news_events_setup.php
 *   drush php:script themes/custom/palefire/scripts/palefire_news_events_setup.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it reports what it would do and saves nothing.
 *
 * Optional arguments after "--":
 *   apply=1         Save.
 *   page=<alias>    The page to add the section to (default /v2/special).
 *                   page=none creates the type only.
 *
 * What it does:
 * - Creates the paragraph type news_events.
 * - Gives it Tile showcase's section fields (Title, Eyebrow, Lede, Link,
 *   anchor, snap, theme, "Show in TOC" and the like: every field but its
 *   tile references and its other field_tile_* settings) and the Landing
 *   hero's Blog categories (field_landing_blog_category). The storages are
 *   shared, so no new tables. Widgets and displays are copied from those
 *   types, and so are Tile showcase's form field groups.
 * - Allows news_events wherever tile_showcase is allowed (the Modular
 *   page's sections).
 * - Adds one News and events section to the page, right after its Landing
 *   hero: Eyebrow "News and events", Title "Happening at Hill", a Lede, the
 *   Link "All events" (LibCal, Hill Memorial), and the hero's Blog
 *   categories.
 * - Ticks "Contents in the column" (field_hero_toc, from
 *   palefire_hero_toc_setup.php) on that Landing hero, so its column lists
 *   the page's sections in place of the Highlights; the News and events row
 *   names the next event. Saves a new revision.
 *
 * It adds no section, but still sets up the type, when:
 * - a News and events section is already on the page;
 * - the page has a newer draft than its published revision.
 * Safe to re-run: anything that already exists is left as it is.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldConfigInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;

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
$page = trim($args['page'] ?? '/v2/special');

$type = 'news_events';
$showcase = 'tile_showcase';
$hero = 'landing_hero';
$say = static function (string $line) use ($apply): void {
  echo ($apply ? '' : '[dry run] ') . $line . PHP_EOL;
};

foreach ([$showcase, $hero] as $needed) {
  if (!ParagraphsType::load($needed)) {
    throw new \RuntimeException("Paragraph type $needed not found.");
  }
}
$blog_field = FieldConfig::loadByName('paragraph', $hero, 'field_landing_blog_category');
if (!$blog_field) {
  throw new \RuntimeException("paragraph.$hero.field_landing_blog_category not found.");
}

// The type. -----------------------------------------------------------------
if (!ParagraphsType::load($type)) {
  $say("Create paragraph type $type.");
  if ($apply) {
    ParagraphsType::create([
      'id' => $type,
      'label' => 'News and events',
      'description' => 'The next events (LibCal, through the LibCal Gantt module) with the day’s forecast, the newest blog post and the latest Instagram post.',
    ])->save();
  }
}

// Fields. -------------------------------------------------------------------
// Tile showcase's section fields: not its tile references, and of its
// field_tile_* settings only the three the section wrapper prints.
$keep_tile = ['field_tile_eyebrow', 'field_tile_lede', 'field_tile_link'];
$sources = [$showcase => [], $hero => ['field_landing_blog_category']];
foreach (\Drupal::service('entity_field.manager')->getFieldDefinitions('paragraph', $showcase) as $name => $definition) {
  if (!$definition instanceof FieldConfigInterface
    || in_array($definition->getType(), ['entity_reference_revisions'], TRUE)
    || (str_starts_with($name, 'field_tile_') && !in_array($name, $keep_tile, TRUE))) {
    continue;
  }
  $sources[$showcase][] = $name;
}
foreach ($keep_tile as $name) {
  if (!in_array($name, $sources[$showcase], TRUE)) {
    throw new \RuntimeException("paragraph.$showcase.$name not found.");
  }
}

foreach ($sources as $bundle => $names) {
  foreach ($names as $name) {
    if (FieldConfig::loadByName('paragraph', $type, $name)) {
      continue;
    }
    $source = FieldConfig::loadByName('paragraph', $bundle, $name);
    $say("Add $name to paragraph.$type (from $bundle).");
    if ($apply) {
      FieldConfig::create([
        'field_name' => $name,
        'entity_type' => 'paragraph',
        'bundle' => $type,
        'label' => $source->getLabel(),
        'description' => $name === 'field_landing_blog_category'
          ? 'The newest article in any of these categories is the section’s blog post.'
          : $source->getDescription(),
        'required' => $source->isRequired(),
        'settings' => $source->getSettings(),
        'default_value' => $source->getDefaultValueLiteral(),
      ])->save();
    }
  }
}

// Widgets, displays, field groups. ------------------------------------------
if ($apply) {
  $repo = \Drupal::service('entity_display.repository');
  foreach (['getFormDisplay', 'getViewDisplay'] as $method) {
    $target = $repo->$method('paragraph', $type, 'default');
    foreach ($sources as $bundle => $names) {
      foreach ($repo->$method('paragraph', $bundle, 'default')->getComponents() as $name => $component) {
        if (in_array($name, $names, TRUE) && !$target->getComponent($name)) {
          $target->setComponent($name, $component);
        }
      }
    }
    if ($method === 'getViewDisplay') {
      // The template builds the post itself.
      $target->removeComponent('field_landing_blog_category');
    }
    elseif (!$target->getThirdPartySettings('field_group')) {
      foreach ($repo->getFormDisplay('paragraph', $showcase, 'default')->getThirdPartySettings('field_group') as $key => $group) {
        // Only the groups' fields this type has.
        $group['children'] = array_values(array_filter($group['children'] ?? [], static fn ($child): bool => in_array($child, $sources[$showcase], TRUE) || str_starts_with((string) $child, 'group_')));
        $target->setThirdPartySetting('field_group', $key, $group);
      }
    }
    $target->save();
  }
}
else {
  $say('Copy widgets and displays from tile_showcase and landing_hero.');
}

// Allowed wherever tile_showcase is. ------------------------------------------
foreach (FieldConfig::loadMultiple() as $config) {
  if (!in_array($config->getType(), ['entity_reference_revisions', 'entity_reference'], TRUE)
    || $config->getSetting('target_type') !== 'paragraph') {
    continue;
  }
  $handler = $config->getSetting('handler_settings') ?: [];
  $bundles = $handler['target_bundles'] ?? NULL;
  // "Exclude" mode lists bundles that are NOT allowed; the new one already is.
  if (($handler['negate'] ?? FALSE) || !is_array($bundles) || !isset($bundles[$showcase]) || isset($bundles[$type])) {
    continue;
  }
  $say("Allow $type in " . $config->id() . '.');
  if ($apply) {
    $handler['target_bundles'][$type] = $type;
    if (isset($handler['target_bundles_drag_drop'])) {
      $weight = $handler['target_bundles_drag_drop'][$showcase]['weight'] ?? 0;
      $handler['target_bundles_drag_drop'][$type] = ['enabled' => TRUE, 'weight' => $weight + 1];
    }
    $config->setSetting('handler_settings', $handler)->save();
  }
}

// The section on the page. ----------------------------------------------------
$finish = static function () use ($apply): void {
  echo $apply ? 'Done. Run: drush cr' . PHP_EOL : 'Dry run: nothing saved. Run again with apply=1 to save.' . PHP_EOL;
};
if ($page === 'none') {
  $finish();
  return;
}
$alias = '/' . trim($page, '/');
$path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
if (!preg_match('#^/node/(\d+)$#', $path, $m)) {
  throw new \RuntimeException("No node found at $alias.");
}
$storage = \Drupal::entityTypeManager()->getStorage('node');
$node = $storage->load($m[1]);
if (!$node || !$node->hasField('field_scroll_section')) {
  throw new \RuntimeException("Node {$m[1]} has no field_scroll_section.");
}
$latest = $storage->getLatestRevisionId($node->id());
if ($latest && (int) $latest !== (int) $node->getRevisionId()) {
  $say("$alias has a newer draft (revision $latest) than its published revision: no section added. Publish or discard it, then re-run.");
  $finish();
  return;
}

$sections = $node->get('field_scroll_section')->referencedEntities();
$position = NULL;
$hero_delta = NULL;
$blog = [];
foreach ($sections as $delta => $section) {
  if ($section->bundle() === $type) {
    $say("A News and events section is already on $alias (section " . ($delta + 1) . '): no section added.');
    $finish();
    return;
  }
  if ($position === NULL && $section->bundle() === $hero) {
    $position = $delta + 1;
    $hero_delta = $delta;
    if ($section->hasField('field_landing_blog_category')) {
      $blog = array_column($section->get('field_landing_blog_category')->getValue(), 'target_id');
    }
  }
}
$position ??= count($sections);

$values = [
  'type' => $type,
  'field_section_title' => 'Happening at Hill',
  'field_tile_eyebrow' => 'News and events',
  'field_tile_lede' => 'Talks, exhibitions and open houses at Hill Memorial Library, with the latest from our blog and Instagram.',
  'field_tile_link' => [
    'uri' => 'https://lsu.libcal.com/calendar/eventsandprogramming?cid=-1&t=m&d=0000-00-00&cal=-1&inc=0&cm=262',
    'title' => 'All events',
  ],
  'field_landing_blog_category' => array_map(static fn ($id): array => ['target_id' => $id], $blog),
];
$say('Insert "Happening at Hill" as section ' . ($position + 1) . ' of ' . (count($sections) + 1) . " on $alias, after the Landing hero.");
$say($blog ? 'Blog categories from the hero: term ' . implode(', ', $blog) . '.' : 'The hero has no Blog categories: pick them in the section, or it shows no blog post.');
$tick = $hero_delta !== NULL && $sections[$hero_delta]->hasField('field_hero_toc') && !$sections[$hero_delta]->get('field_hero_toc')->value;
if ($hero_delta !== NULL && !$sections[$hero_delta]->hasField('field_hero_toc')) {
  $say('The Landing hero has no field_hero_toc: run palefire_hero_toc_setup.php first to turn its Highlights into contents.');
}
elseif ($tick) {
  $say('Tick "Contents in the column" on the Landing hero (Highlights become the page’s contents).');
}
foreach ($sections as $section) {
  if ($section->bundle() === 'landing_stacks' && $section->isPublished()) {
    $say('Note: "From the stacks" (landing_stacks) is also on the page and lists upcoming events and blog posts too.');
    break;
  }
}

if ($apply) {
  $items = $node->get('field_scroll_section')->getValue();
  if ($tick) {
    $sections[$hero_delta]->set('field_hero_toc', 1);
    $sections[$hero_delta]->setNeedsSave(TRUE);
    $items[$hero_delta] = ['entity' => $sections[$hero_delta]];
  }
  array_splice($items, $position, 0, [['entity' => Paragraph::create($values)]]);
  $node->set('field_scroll_section', $items);
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage('Special Collections: News and events section (palefire_news_events_setup.php).');
  $node->save();
  echo "Saved a new revision of \"{$node->label()}\"." . PHP_EOL;
}
$finish();
