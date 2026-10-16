<?php

declare(strict_types=1);

namespace Drupal\palefire\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for the Palefire theme.
 *
 * Modular pages (node type "modular_page") store their sections in the
 * paragraph reference field "field_sections". Any paragraph bundle that has a
 * "field_section_title" field is treated as a section: it gets a stable anchor
 * ID, is rendered as a <section> landmark, and (optionally) appears in the
 * "On this page" table of contents printed in the Sidebar region.
 *
 * Note: a theme may implement each hook only once, so every
 * preprocess_paragraph concern is routed through preprocessParagraph().
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
    $variables['modular_title_in_bento'] = FALSE;
    $variables['palefire_toc'] = [];

    $node = $variables['node'] ?? NULL;
    if (!$node instanceof NodeInterface || $node->bundle() !== self::MODULAR_BUNDLE) {
      return;
    }
    $variables['is_modular_page'] = TRUE;

    // The bento search prints the page <h1> above its intro when it is the
    // first section (paragraph--bento-search.html.twig); page.html.twig then
    // leaves its own <h1> out, so there is always exactly one.
    $variables['modular_title_in_bento'] = self::titleBentoUuid($node) !== NULL;

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
   * Exposes section variables consumed by paragraph.html.twig, plus bento
   * background video variables for the bento_search paragraph.
   */
  #[Hook('preprocess_paragraph')]
  public function preprocessParagraph(array &$variables): void {
    $variables['is_section'] = FALSE;

    // Runs before the section early-return so bento_search always gets it.
    $this->bentoVideoVariables($variables);

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

    // Modular pages print their own <h1> (page.html.twig, or the bento
    // search when it is the first section), so a placed Page title block
    // would be a second one.
    if (($variables['plugin_id'] ?? '') === 'page_title_block') {
      $variables['#cache']['contexts'][] = 'route';
      if ($this->currentModularNode()) {
        $variables['content'] = [];
        $variables['attributes']['hidden'] = 'hidden';
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for palefire_modular_toc.
   *
   * The TOC's visible title is the page title (palefire-modular-toc.html.twig).
   * The block already varies by route and carries the node's cache tags.
   */
  #[Hook('preprocess_palefire_modular_toc')]
  public function preprocessPalefireModularToc(array &$variables): void {
    $node = $this->currentModularNode();
    $variables['page_title'] = $node ? $node->label() : NULL;
    $variables['#cache']['contexts'][] = 'route';
  }

  /**
   * The Modular page node shown on the current route, if any.
   */
  private function currentModularNode(): ?NodeInterface {
    $node = \Drupal::routeMatch()->getParameter('node');
    return $node instanceof NodeInterface && $node->bundle() === self::MODULAR_BUNDLE ? $node : NULL;
  }

  /**
   * Paragraph fields that hold a Modular page's sections, newest first.
   *
   * field_scroll_section is palefire_modular's field; field_sections is the
   * older one this class was written for.
   */
  public const SECTION_FIELDS = ['field_scroll_section', self::SECTIONS_FIELD];

  /**
   * UUID of the bento_search that prints the page <h1>, or NULL.
   *
   * That is the first section the current user can see, when it is a
   * bento_search. Used by preprocessPage() (page.html.twig skips its own
   * <h1>) and palefire_preprocess_paragraph__bento_search() (the bento
   * prints it above the intro), so the two always agree.
   */
  public static function titleBentoUuid(NodeInterface $node): ?string {
    foreach (self::SECTION_FIELDS as $field) {
      if (!$node->hasField($field)) {
        continue;
      }
      foreach ($node->get($field)->referencedEntities() as $paragraph) {
        if (method_exists($paragraph, 'isPublished') && !$paragraph->isPublished()) {
          continue;
        }
        if (!$paragraph->access('view')) {
          continue;
        }
        return $paragraph->bundle() === 'bento_search' ? $paragraph->uuid() : NULL;
      }
    }
    return NULL;
  }

  /**
   * Adds bento background video variables (bg_videos, bg_poster).
   *
   * Called from preprocessParagraph(). The bento_search template attaches
   * palefire/bento-video itself.
   */
  private function bentoVideoVariables(array &$variables): void {
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
    foreach ($paragraph->get('field_bg_videos')->referencedEntities() as $item) {
      if ($file = self::fileFromReference($item)) {
        $urls[] = $generator->generateString($file->getFileUri());
        $cache->addCacheableDependency($item)->addCacheableDependency($file);
      }
    }

    // field_bg_poster may be a Media (image) reference or a plain image field.
    if ($paragraph->hasField('field_bg_poster') && !$paragraph->get('field_bg_poster')->isEmpty()) {
      $poster = $paragraph->get('field_bg_poster')->entity;
      if ($file = self::fileFromReference($poster)) {
        $variables['bg_poster'] = $generator->generateString($file->getFileUri());
        $cache->addCacheableDependency($poster)->addCacheableDependency($file);
      }
    }

    $variables['bg_videos'] = $urls;
    $cache->applyTo($variables);
  }

  /**
   * Returns the file behind a Media item or File entity, or NULL.
   */
  private static function fileFromReference(?object $entity): ?FileInterface {
    if ($entity instanceof FileInterface) {
      return $entity;
    }
    if ($entity instanceof MediaInterface) {
      $field = $entity->getSource()->getConfiguration()['source_field'] ?? NULL;
      if ($field && $entity->hasField($field)) {
        $file = $entity->get($field)->entity;
        return $file instanceof FileInterface ? $file : NULL;
      }
    }
    return NULL;
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
  }

  /**
   * Computes anchor/label/TOC data for every section on a host entity.
   *
   * @return array<string, array{anchor: string, label: string, toc: bool}>
   *   Section data keyed by paragraph UUID.
   */
  public static function sectionData(FieldableEntityInterface $host): array {
    $key = implode(':', [
      $host->getEntityTypeId(),
      $host->id() ?? 'new-' . $host->uuid(),
      method_exists($host, 'getRevisionId') ? (string) $host->getRevisionId() : '',
      $host->language()->getId(),
    ]);
    if (isset(self::$sectionData[$key])) {
      return self::$sectionData[$key];
    }

    $data = [];
    if ($host->hasField(self::SECTIONS_FIELD)) {
      $repository = \Drupal::service('entity.repository');
      $used = array_fill_keys(self::RESERVED_IDS, TRUE);
      foreach ($host->get(self::SECTIONS_FIELD) as $delta => $item) {
        $paragraph = $item->entity;
        if (!$paragraph instanceof FieldableEntityInterface || !$paragraph->hasField('field_section_title')) {
          continue;
        }
        $paragraph = $repository->getTranslationFromContext($paragraph);
        $label = trim((string) $paragraph->get('field_section_title')->value);
        $custom = self::stringValue($paragraph, 'field_section_anchor');

        $base = self::slug($custom !== '' ? $custom : $label) ?: 'section-' . ($delta + 1);
        $anchor = $base;
        $i = 2;
        while (isset($used[$anchor])) {
          $anchor = $base . '-' . $i++;
        }
        $used[$anchor] = TRUE;

        $data[$paragraph->uuid()] = [
          'anchor' => $anchor,
          'label' => $label,
          'toc' => $label !== '' && (!$paragraph->hasField('field_show_in_toc') || self::boolValue($paragraph, 'field_show_in_toc', TRUE)),
        ];
      }
    }
    return self::$sectionData[$key] = $data;
  }

  /**
   * Converts text to a URL-fragment-friendly, valid HTML ID.
   */
  public static function slug(string $text): string {
    $text = mb_strtolower(trim($text));
    if ($text === '') {
      return '';
    }
    $text = \Drupal::transliteration()->transliterate($text, 'en', '');
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim(substr($text, 0, 64), '-');
    return $text === '' ? '' : Html::cleanCssIdentifier($text);
  }

  /**
   * Reads a boolean field value with a default.
   */
  private static function boolValue(FieldableEntityInterface $entity, string $field, bool $default = FALSE): bool {
    if (!$entity->hasField($field) || $entity->get($field)->isEmpty()) {
      return $default;
    }
    return (bool) $entity->get($field)->value;
  }

  /**
   * Reads a string field value, or '' when missing/empty.
   */
  private static function stringValue(FieldableEntityInterface $entity, string $field): string {
    if (!$entity->hasField($field) || $entity->get($field)->isEmpty()) {
      return '';
    }
    return trim((string) $entity->get($field)->value);
  }

}
