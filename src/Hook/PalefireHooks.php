<?php

declare(strict_types=1);

namespace Drupal\palefire\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for the Palefire theme.
 *
 * Modular pages (node type "modular_page") store their sections in the
 * paragraph reference field "field_sections". Any paragraph bundle that has a
 * "field_section_title" field is treated as a section: it gets a stable anchor
 * ID, is rendered as a <section> landmark, and (optionally) appears in the
 * "On this page" table of contents printed in the Sidebar region.
 */
class PalefireHooks {

  use StringTranslationTrait;

  /**
   * Machine name of the modular content type.
   */
  public const MODULAR_BUNDLE = 'modular_page';

  /**
   * Paragraph reference field on the modular content type.
   */
  public const SECTIONS_FIELD = 'field_sections';

  /**
   * IDs already used by the theme; section anchors must never collide.
   */
  private const RESERVED_IDS = ['main-content', 'pf-toc', 'pf-primary-menu'];

  /**
   * Per-request cache of computed section data, keyed by host entity.
   *
   * @var array<string, array<string, array{anchor: string, label: string, toc: bool}>>
   */
  private static array $sectionData = [];

  /**
   * Implements hook_preprocess_image_widget().
   */
  #[Hook('preprocess_image_widget')]
  public function preprocessImageWidget(array &$variables): void {
    $data = &$variables['data'];
    // This prevents image widget templates from rendering preview container
    // HTML to users that do not have permission to access these previews.
    // @todo revisit in https://drupal.org/node/953034
    // @todo revisit in https://drupal.org/node/3114318
    if (isset($data['preview']['#access']) && $data['preview']['#access'] === FALSE) {
      unset($data['preview']);
    }
  }

  /**
   * Implements hook_preprocess_page().
   *
   * Flags modular pages and builds the table of contents render array.
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    $variables['is_modular_page'] = FALSE;
    $variables['palefire_toc'] = [];

    $node = $variables['node'] ?? NULL;
    if (!$node instanceof NodeInterface || $node->bundle() !== self::MODULAR_BUNDLE) {
      return;
    }
    $variables['is_modular_page'] = TRUE;

    // Only build the TOC on routes that display the node itself.
    $route = \Drupal::routeMatch()->getRouteName();
    if (!in_array($route, ['entity.node.canonical', 'entity.node.latest_version'], TRUE)) {
      return;
    }

    $cacheability = CacheableMetadata::createFromObject($node)->addCacheContexts(['route']);
    $items = [];
    foreach ($this->visibleSections($node, $cacheability) as $section) {
      if ($section['toc']) {
        $items[] = ['anchor' => $section['anchor'], 'label' => $section['label']];
      }
    }

    if ($items) {
      $variables['palefire_toc'] = [
        '#type' => 'component',
        '#component' => 'palefire:toc',
        '#props' => [
          'heading' => (string) $this->t('On this page'),
          'toc_id' => 'pf-toc',
          'items' => $items,
        ],
      ];
      $cacheability->applyTo($variables['palefire_toc']);
    }
  }

  /**
   * Implements hook_preprocess_paragraph().
   *
   * Exposes section variables consumed by paragraph.html.twig.
   */
  #[Hook('preprocess_paragraph')]
  public function preprocessParagraph(array &$variables): void {
    $variables['is_section'] = FALSE;

    /** @var \Drupal\paragraphs\ParagraphInterface|null $paragraph */
    $paragraph = $variables['paragraph'] ?? NULL;
    if (!$paragraph instanceof FieldableEntityInterface || !$paragraph->hasField('field_section_title')) {
      return;
    }

    $title = trim((string) $paragraph->get('field_section_title')->value);

    // Resolve the anchor from the host so IDs match the TOC exactly (including
    // de-duplication across sibling sections).
    $anchor = NULL;
    $parent = method_exists($paragraph, 'getParentEntity') ? $paragraph->getParentEntity() : NULL;
    if ($parent instanceof FieldableEntityInterface) {
      $anchor = self::sectionData($parent)[$paragraph->uuid()]['anchor'] ?? NULL;
      // Anchors depend on sibling sections, so vary with the host entity.
      $variables['#cache']['tags'] = array_merge($variables['#cache']['tags'] ?? [], $parent->getCacheTags());
    }

    $variables['is_section'] = TRUE;
    $variables['section_title'] = $title;
    $variables['section_id'] = $anchor ?? (self::slug($title) ?: 'section-' . substr((string) $paragraph->uuid(), 0, 8));
    $variables['section_full_page'] = self::boolValue($paragraph, 'field_full_page');
    $variables['section_style'] = self::stringValue($paragraph, 'field_section_style') ?: 'default';
    $variables['image_position'] = self::stringValue($paragraph, 'field_image_position') ?: 'end';
  }

  /**
   * Implements hook_preprocess_paragraph() for bento background videos.
   *
   * Exposes bg_videos (ordered MP4 URLs) and bg_poster to the bento_search
   * paragraph template. The template attaches palefire/bento-video itself.
   */
  #[Hook('preprocess_paragraph')]
  public function preprocessBentoVideo(array &$variables): void {
    $paragraph = $variables['paragraph'] ?? NULL;
    if (!$paragraph instanceof FieldableEntityInterface
      || $paragraph->bundle() !== 'bento_search'
      || !$paragraph->hasField('field_bg_videos')
      || $paragraph->get('field_bg_videos')->isEmpty()) {
      return;
    }

    $generator = \Drupal::service('file_url_generator');
    $cache = CacheableMetadata::createFromRenderArray($variables);
    $cache->addCacheableDependency($paragraph);

    $urls = [];
    foreach ($paragraph->get('field_bg_videos')->referencedEntities() as $media) {
      $source_field = $media->getSource()->getConfiguration()['source_field'] ?? NULL;
      if (!$source_field || !$media->hasField($source_field)) {
        continue;
      }
      $file = $media->get($source_field)->entity;
      if ($file) {
        $urls[] = $generator->generateString($file->getFileUri());
        $cache->addCacheableDependency($media)->addCacheableDependency($file);
      }
    }

    if ($paragraph->hasField('field_bg_poster') && ($poster = $paragraph->get('field_bg_poster')->entity)) {
      $variables['bg_poster'] = $generator->generateString($poster->getFileUri());
      $cache->addCacheableDependency($poster);
    }

    $variables['bg_videos'] = $urls;
    $cache->applyTo($variables);
  }

  /**
   * Implements hook_preprocess_block().
   */
  #[Hook('preprocess_block')]
  public function preprocessBlock(array &$variables): void {
    // Always provide the site name to the branding block so the logo link has
    // a meaningful accessible name even when "Site name" display is disabled.
    // The block already carries the config:system.site cache tag.
    if (($variables['plugin_id'] ?? '') === 'system_branding_block') {
      $variables['site_name_plain'] = (string) \Drupal::config('system.site')->get('name');
    }
  }

  /**
   * Returns sections the current user may view, in editor-defined order.
   */
  private function visibleSections(NodeInterface $node, CacheableMetadata $cacheability): array {
    $data = self::sectionData($node);
    if (!$data || !$node->hasField(self::SECTIONS_FIELD)) {
      return [];
    }
    $visible = [];
    foreach ($node->get(self::SECTIONS_FIELD)->referencedEntities() as $paragraph) {
      $uuid = $paragraph->uuid();
      if (!isset($data[$uuid])) {
        continue;
      }
      $access = $paragraph->access('view', NULL, TRUE);
      $cacheability->addCacheableDependency($access)->addCacheableDependency($paragraph);
      if ($access->isAllowed()) {
        $visible[] = $data[$uuid];
      }
    }
    return $visible;
 