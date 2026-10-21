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
   * Paragraph types drawn as a landing: background video, the page <h1> when
   * first, building map and Visit card. landing_hero is bento_search
   * without the search form (paragraph--landing-hero.html.twig);
   * landing_department is bento_search plus the hero's tiles
   * (paragraph--landing-department.html.twig).
   */
  public const LANDING_BUNDLES = ['bento_search', 'landing_hero', 'landing_department'];

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
   * Implements hook_preprocess_html().
   *
   * Puts the page mode (field_modular_type: page, guide, form) on <html> as
   * data-pf-mode, for CSS and for scripts that run before <body> exists
   * (Settings > Dark guides, html.html.twig). The page cache entry already
   * carries the node's cache tags, so editing the node updates it.
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    $route_match = \Drupal::routeMatch();
    if (!in_array($route_match->getRouteName(), ['entity.node.canonical', 'entity.node.latest_version'], TRUE)) {
      return;
    }
    $node = $route_match->getParameter('node');
    if (!$node instanceof NodeInterface || $node->bundle() !== self::MODULAR_BUNDLE) {
      return;
    }
    $variables['html_attributes']->setAttribute('data-pf-mode', self::stringValue($node, 'field_modular_type') ?: 'page');
  }

  /**
   * Implements hook_preprocess_page().
   *
   * Flags modular pages and builds the table of contents render array.
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    $variables['is_modular_page'] = FALSE;
    $variables['modular_title_in_landing'] = FALSE;
    $variables['palefire_toc'] = [];
    $variables['landing_toc'] = NULL;
    $variables['guide_head'] = NULL;
    $variables['exhibit_head'] = NULL;

    $node = $variables['node'] ?? NULL;
    if (!$node instanceof NodeInterface || $node->bundle() !== self::MODULAR_BUNDLE) {
      return;
    }
    $variables['is_modular_page'] = TRUE;

    // The landing search prints the page <h1> above its intro when it is the
    // first section (includes/landing.html.twig); page.html.twig then
    // leaves its own <h1> out, so there is always exactly one.
    $variables['modular_title_in_landing'] = self::titleLandingUuid($node) !== NULL;
    // A Landing hero with its lead panel prints the breadcrumb at the top of
    // the panel instead (crumbInLanding()), so the head leaves it out.
    $variables['modular_crumb_in_landing'] = self::crumbInLanding($node);

    // Guide mode: intro, owner, updated date and subjects under the <h1>.
    if (!$variables['modular_title_in_landing'] && self::stringValue($node, 'field_modular_type') === 'guide') {
      $variables['guide_head'] = $this->guideHead($node);
    }
    // Exhibition mode: the title page (includes/exhibit-cover.html.twig).
    if (!$variables['modular_title_in_landing'] && self::stringValue($node, 'field_modular_type') === 'exhibition') {
      $variables['exhibit_head'] = $this->exhibitHead($node);
    }

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

    // The TOC for a page whose only section is its landing (Oral History).
    // palefire_modular's TOC block lists titled sections and renders
    // nothing without one, so the landing's Home and Map links
    // (heroTocIds(), added in preprocessPalefireModularToc()) never showed.
    // The same theme hook as the block; page.html.twig prints it only when
    // the block is empty. Not on Form pages, where the block hides the TOC.
    $mode = self::stringValue($node, 'field_modular_type') ?: 'page';
    if ($mode !== 'form' && self::heroTocIds($node)) {
      $variables['landing_toc'] = [
        '#theme' => 'palefire_modular_toc',
        '#items' => [],
        '#heading' => (string) $this->t('On this page'),
        '#depth' => 1,
        '#mode' => $mode,
      ];
      $cacheability->applyTo($variables['landing_toc']);
    }
  }

  /**
   * Implements hook_preprocess_paragraph().
   *
   * Exposes section variables consumed by paragraph.html.twig, plus landing
   * background video variables for the landing paragraphs (LANDING_BUNDLES).
   */
  #[Hook('preprocess_paragraph')]
  public function preprocessParagraph(array &$variables): void {
    $variables['is_section'] = FALSE;

    // Runs before the section early-return so landings always get it.
    $this->landingVideoVariables($variables);

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

    // Modular pages print their own <h1> (page.html.twig, or the landing
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

    // A landing that opens the page has no section title, so palefire_modular
    // leaves it out of the TOC, and the map heading inside it with it. Add
    // "Home" (the lead panel) and "Map" (the building map) at the top. The
    // landing prints the same ids (palefire_preprocess_paragraph__bento_search()).
    $ids = $node ? self::heroTocIds($node) : [];
    $extra = [];
    if (!empty($ids['home'])) {
      $extra[] = ['id' => $ids['home'], 'label' => $this->t('Home')];
    }
    if (!empty($ids['map'])) {
      $extra[] = ['id' => $ids['map'], 'label' => $this->t('Map')];
    }
    // Exhibitions: "Title page" first (the head, includes/exhibit-cover),
    // and every gallery lists its entries (h3s) whatever the block's
    // "Include h3 sub-links" setting says. js/exhibition.js shows one
    // gallery at a time.
    if ($node && self::stringValue($node, 'field_modular_type') === 'exhibition') {
      $extra[] = ['id' => 'exhibit-title', 'label' => $this->t('Title page')];
      $variables['depth'] = 2;
    }
    if ($extra) {
      $variables['items'] = array_merge($extra, $variables['items'] ?? []);
    }
  }

  /**
   * Whether the landing that opens the page prints the breadcrumb itself.
   *
   * TRUE for a landing (Landing hero or the older Bento search) that shows
   * its lead panel: the breadcrumb and the video button share the sub-header
   * bar above the panel (includes/landing.html.twig), and page.html.twig
   * leaves the head's breadcrumb out. The lead is left out ("Hide lead
   * panel" with a map), so then the head keeps it.
   */
  public static function crumbInLanding(NodeInterface $node): bool {
    $uuid = self::titleLandingUuid($node);
    if ($uuid === NULL) {
      return FALSE;
    }
    $paragraph = \Drupal::service('entity.repository')->loadEntityByUuid('paragraph', $uuid);
    if (!$paragraph instanceof FieldableEntityInterface) {
      return FALSE;
    }
    $map_only = self::boolValue($paragraph, 'field_bento_hide_lead')
      && $paragraph->hasField('field_bento_map') && !$paragraph->get('field_bento_map')->isEmpty();
    return !$map_only;
  }

  /**
   * TOC targets for the landing that opens a Modular page.
   *
   * home: the lead panel's id, when the landing has no section title of its
   * own (with one, palefire_modular already lists it). map: the building
   * map's wrapper, when the landing has a map. Fixed ids, so the links read
   * #home and #map; a section anchored "home" or "map" keeps it, and the
   * landing then gets none.
   *
   * @return array{home?: string, map?: string}
   */
  public static function heroTocIds(NodeInterface $node): array {
    $uuid = self::titleLandingUuid($node);
    if ($uuid === NULL) {
      return [];
    }
    $paragraph = \Drupal::service('entity.repository')->loadEntityByUuid('paragraph', $uuid);
    if (!$paragraph instanceof FieldableEntityInterface) {
      return [];
    }
    $taken = array_column(self::sectionData($node), 'anchor');
    $ids = [];
    if (self::stringValue($paragraph, 'field_section_title') === '' && !in_array('home', $taken, TRUE)) {
      $ids['home'] = 'home';
    }
    if ($paragraph->hasField('field_bento_map') && !$paragraph->get('field_bento_map')->isEmpty() && !in_array('map', $taken, TRUE)) {
      $ids['map'] = 'map';
    }
    return $ids;
  }

  /**
   * Variables for includes/guide-head.html.twig on a guide-mode page.
   *
   * @return array{intro: ?array, owner: ?array, editors: array, updated: array, published: ?array, subjects: array, cache: array}
   */
  private function guideHead(NodeInterface $node): array {
    $cache = CacheableMetadata::createFromObject($node);
    $repository = \Drupal::service('entity.repository');

    $intro = NULL;
    if ($node->hasField('field_intro') && !$node->get('field_intro')->isEmpty()) {
      $intro = $node->get('field_intro')->view(['label' => 'hidden', 'type' => 'text_default']);
    }

    $owner = NULL;
    if ($node->hasField('field_guide_owner') && ($person = $node->get('field_guide_owner')->entity) instanceof NodeInterface) {
      $access = $person->access('view', NULL, TRUE);
      $cache->addCacheableDependency($person)->addCacheableDependency($access);
      if ($access->isAllowed()) {
        $person = $repository->getTranslationFromContext($person);
        $owner = ['name' => $person->label(), 'url' => $person->toUrl()->toString()];
      }
    }

    // Guide editors (optional, any number): those the visitor can see, in
    // the field's order, leaving out the owner if listed again.
    $editors = [];
    if ($node->hasField('field_guide_editors')) {
      $owner_id = $node->hasField('field_guide_owner') ? (int) $node->get('field_guide_owner')->target_id : 0;
      foreach ($node->get('field_guide_editors') as $item) {
        $person = $item->entity;
        if (!$person instanceof NodeInterface || (int) $person->id() === $owner_id || isset($editors[$person->id()])) {
          continue;
        }
        $access = $person->access('view', NULL, TRUE);
        $cache->addCacheableDependency($person)->addCacheableDependency($access);
        if ($access->isAllowed()) {
          $person = $repository->getTranslationFromContext($person);
          $editors[$person->id()] = ['name' => $person->label(), 'url' => $person->toUrl()->toString()];
        }
      }
    }

    $subjects = [];
    if ($node->hasField('field_guide_subjects')) {
      foreach ($node->get('field_guide_subjects') as $item) {
        $term = $item->entity;
        if (!$term) {
          continue;
        }
        $access = $term->access('view', NULL, TRUE);
        $cache->addCacheableDependency($term)->addCacheableDependency($access);
        if ($access->isAllowed()) {
          $term = $repository->getTranslationFromContext($term);
          $subjects[] = ['name' => $term->label(), 'url' => $term->toUrl()->toString()];
        }
      }
    }

    $changed = (int) $node->getChangedTime();
    $created = (int) $node->getCreatedTime();
    $formatter = \Drupal::service('date.formatter');
    $render = [];
    $cache->applyTo($render);
    return [
      'intro' => $intro,
      'owner' => $owner,
      'editors' => array_values($editors),
      'updated' => [
        'label' => $formatter->format($changed, 'custom', 'M j, Y'),
        'iso' => date('c', $changed),
      ],
      // Only when it says something the Updated date doesn't.
      'published' => $formatter->format($created, 'custom', 'Y-m') === $formatter->format($changed, 'custom', 'Y-m') ? NULL : [
        'label' => $formatter->format($created, 'custom', 'M Y'),
        'iso' => date('c', $created),
      ],
      'subjects' => $subjects,
      'cache' => $render,
    ];
  }

  /**
   * Variables for includes/exhibit-cover.html.twig on an exhibition page.
   *
   * @return array{base: string, paths: bool, title: string, subtitle: string, credit: ?array, intro: ?array, prompt: string, cover: ?array, cache: array}
   */
  private function exhibitHead(NodeInterface $node): array {
    $cache = CacheableMetadata::createFromObject($node);
    $text = fn (string $field): ?array => $node->hasField($field) && !$node->get($field)->isEmpty()
      ? $node->get($field)->view(['label' => 'hidden', 'type' => 'text_default'])
      : NULL;

    $cover = NULL;
    if ($node->hasField('field_exhibit_cover') && !$node->get('field_exhibit_cover')->isEmpty()) {
      $item = _palefire_media_image_item($node->get('field_exhibit_cover')->first(), $cache);
      // The whole screen at any width; it is the page's first image, so it
      // loads eagerly (_palefire_exhibit_image() sets lazy otherwise).
      $cover = $item ? _palefire_exhibit_image($item, '100vw', $cache, TRUE) : NULL;
    }

    // Galleries at their own paths (<page>/<gallery anchor>) once
    // palefire_exhibit_paths.php has made the aliases: js/exhibition.js then
    // shows those paths instead of #anchors. Checked on the first gallery
    // with an anchor; any alias change clears the page (path_alias_list).
    $paths = FALSE;
    $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $node->id(), $node->language()->getId());
    if ($node->hasField('field_scroll_section') && !str_starts_with($alias, '/node/')) {
      foreach ($node->get('field_scroll_section')->referencedEntities() as $gallery) {
        $anchor = $gallery->bundle() === 'exhibit_page' && $gallery->hasField('field_section_anchor') ? trim((string) $gallery->get('field_section_anchor')->value) : '';
        if ($anchor !== '') {
          $paths = (bool) \Drupal::service('path_alias.repository')->lookupByAlias($alias . '/' . $anchor, $node->language()->getId());
          break;
        }
      }
    }
    $cache->addCacheTags(['path_alias_list']);

    $render = [];
    $cache->applyTo($render);
    return [
      'base' => $node->toUrl()->toString(),
      'paths' => $paths,
      'title' => $node->label(),
      'subtitle' => self::stringValue($node, 'field_exhibit_subtitle'),
      'credit' => $text('field_exhibit_credit'),
      'intro' => $text('field_intro'),
      'prompt' => self::stringValue($node, 'field_exhibit_prompt') ?: (string) $this->t('Choose a gallery from the table of contents to begin.'),
      'cover' => $cover,
      'cache' => $render,
    ];
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
   * UUID of the landing (LANDING_BUNDLES) that prints the page <h1>, or NULL.
   *
   * That is the first section the current user can see, when it is a
   * landing. Used by preprocessPage() (page.html.twig skips its own
   * <h1>) and palefire_preprocess_paragraph__bento_search() (the landing
   * prints it above the intro), so the two always agree.
   */
  public static function titleLandingUuid(NodeInterface $node): ?string {
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
        return in_array($paragraph->bundle(), self::LANDING_BUNDLES, TRUE) ? $paragraph->uuid() : NULL;
      }
    }
    return NULL;
  }

  /**
   * Adds landing background video variables (bg_videos, bg_poster).
   *
   * Called from preprocessParagraph(). The bento_search template attaches
   * palefire/landing-video itself.
   */
  private function landingVideoVariables(array &$variables): void {
    $paragraph = $variables['paragraph'] ?? NULL;
    if (!$paragraph instanceof FieldableEntityInterface
      || !in_array($paragraph->bundle(), self::LANDING_BUNDLES, TRUE)
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
