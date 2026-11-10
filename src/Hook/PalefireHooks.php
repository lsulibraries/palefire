<?php

declare(strict_types=1);

namespace Drupal\palefire\Hook;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\palefire\StaffDirectory;
use Drupal\paragraphs\ParagraphInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
   * Paragraph types that print the page <h1> (and the breadcrumb) when they
   * open a Modular page: the landings, and a room's Location section
   * (paragraph--location-space.html.twig), whose title sits beside its map.
   */
  public const TITLE_BUNDLES = [...self::LANDING_BUNDLES, 'location_space'];

  /**
   * Opening sections that name a page's kind (pageKind()).
   */
  private const KIND_BUNDLES = [
    'landing_hero' => 'landing',
    'landing_department' => 'department',
    'location_space' => 'location',
  ];

  /**
   * Paragraph reference field on the modular content type.
   */
  public const SECTIONS_FIELD = 'field_sections';

  /**
   * IDs already used by the theme; section anchors must never collide.
   */
  private const RESERVED_IDS = ['main-content', 'pf-toc', 'pf-primary-menu'];

  /**
   * Alias of the Special Collections home page.
   *
   * Its pages get the Special Collections footer, and the footer's Special
   * Collections links start here. "v2" is part of the alias on lib.lsu.edu
   * (the base path is "/"); change this when the pages move.
   */
  public const SPECIAL_ROOT = '/v2/special';

  /**
   * Other aliases that get the Special Collections footer, with their pages.
   */
  public const SPECIAL_EXTRA = ['/v2/exhibitions'];

  /**
   * Alias of a page drawn as the 404 page, to preview it with this theme.
   *
   * Real 404s only get this theme under /v2, and say "Error 404" in a
   * 404 status; this page shows the same thing at an address you can open
   * (scripts/palefire_404_preview.php makes it).
   */
  public const NOT_FOUND_PREVIEW = '/v2/404';

  /**
   * LibCal location the mobile dock shows when a page has no
   * field_hours_location: Hill Memorial Library.
   */
  public const DOCK_LID = '236';

  /**
   * Hill Memorial's address, for the dock's Directions when a page has no
   * field_location_query.
   */
  private const DOCK_ADDRESS = 'Hill Memorial Library, Baton Rouge, LA 70803';

  /**
   * Every icon a dock action can use (dockActions()), printed once in the
   * dock for js/dock.js: FA 5 light SVGs.
   */
  public const DOCK_ICONS = ['copy', 'map-marker-alt', 'calendar-alt', 'door-open', 'external-link-alt', 'phone'];

  /**
   * The missing address the preview pretends to have been asked for.
   */
  private const NOT_FOUND_SAMPLE = '/v2/special/exhibitons';

  /**
   * The 404 page's quips, when its page (NOT_FOUND_PREVIEW) has none.
   *
   * Editors keep the pool in that page's Intro field, one quip a line
   * (notFoundQuips()).
   */
  private const NOT_FOUND_QUIPS = [
    'Please do not touch the glass',
    'Temporarily off view',
    'Out for conservation',
    'Provenance unknown',
    'Gallery closed for reinstallation',
    'Possibly misshelved',
    'Last seen in the reading room',
  ];

  /**
   * Routes that show a node as its page.
   *
   * The node's own page, its latest revision, and the edit form's Preview
   * (/node/preview/{uuid}/{view_mode}), whose node is the "node_preview"
   * parameter, not "node".
   */
  public const NODE_PAGE_ROUTES = ['entity.node.canonical', 'entity.node.latest_version', 'entity.node.preview'];

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
   * (Settings > Dark guides, html.html.twig), the page's kind as
   * data-pf-page (pageKind()), and data-pf-hero when the page opens with a
   * Landing hero or is the Exhibitions showcase (exhibition.js,
   * _landing-features.scss). The page cache entry already carries the
   * node's cache tags, so editing the node updates it.
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    // The mobile dock (includes/dock.html.twig): its reserved space and
    // scroll padding are in the first paint, so nothing shifts.
    if (self::isSpecialPage(self::pageAlias()) && !self::isNotFound()) {
      $variables['html_attributes']->setAttribute('data-pf-dock', '');
    }
    // A staff profile's <title> is the person's name: the View's own title
    // can only repeat the slug.
    if (StaffDirectory::isRoute('profile')) {
      $cache = CacheableMetadata::createFromRenderArray($variables);
      if ($person = StaffDirectory::current($cache)) {
        $variables['head_title']['title'] = $person->label();
      }
      $cache->applyTo($variables);
      return;
    }
    $node = self::pageNode();
    if (!$node || $node->bundle() !== self::MODULAR_BUNDLE) {
      return;
    }
    $variables['html_attributes']->setAttribute('data-pf-mode', self::stringValue($node, 'field_modular_type') ?: 'page');
    $kind = self::pageKind($node);
    $variables['html_attributes']->setAttribute('data-pf-page', $kind);
    // The cover's own rules: opens with a Landing hero (the Special
    // Collections homepage), or is the Exhibitions showcase, which shares
    // its title and list headings.
    if ($kind === 'landing' || $kind === 'showcase') {
      $variables['html_attributes']->setAttribute('data-pf-hero', '');
    }
  }

  /**
   * The kind of Modular page, for <html data-pf-page> ($pf-system and
   * $pf-banded in scss/_variables.scss).
   *
   * The mode when it has its own layout (guide, form, exhibition,
   * showcase); otherwise the section that opens the page (KIND_BUNDLES:
   * landing, department, location), or "page".
   */
  public static function pageKind(NodeInterface $node): string {
    $mode = self::stringValue($node, 'field_modular_type');
    if (in_array($mode, ['guide', 'form', 'exhibition', 'showcase'], TRUE)) {
      return $mode;
    }
    return self::KIND_BUNDLES[self::titleLanding($node)?->bundle()] ?? 'page';
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
    // Guides keep the breadcrumb in their head; every other page has it in
    // the sub-header bar (page.html.twig).
    $variables['modular_guide'] = FALSE;

    // Footer: Special Collections on SPECIAL_ROOT, SPECIAL_EXTRA and the
    // pages under them, and on Combined Search in its Special Collections
    // mode, whose breadcrumb names Special Collections too; the Main Library
    // everywhere else (includes/footer.html.twig).
    $alias = self::pageAlias();
    $special_page = self::isSpecialPage($alias);
    $variables['footer_special'] = $special_page || self::isSpecialSearch();
    $variables['footer_special_root'] = self::SPECIAL_ROOT;
    // Printed by the footer, so the cache varies by path, and by the search
    // page's mode.
    $variables['footer_cache'] = ['#cache' => ['contexts' => ['url.path', 'url.query_args:mode']]];

    // The 404 page (page--404.html.twig): a real 404, or the preview page.
    $variables['not_found'] = self::isNotFound() ? $this->notFound($alias) : NULL;

    // The mobile dock, on the Special Collections pages (not the search,
    // whose own bar holds the foot of the screen).
    $variables['dock'] = $special_page && !$variables['not_found'] ? $this->dock($alias) : NULL;

    // Core sets "node" from the route's "node" parameter only, so a Preview
    // has none: take it from pageNode(), for page.html.twig too.
    $node = $variables['node'] ?? self::pageNode();
    if (!$node instanceof NodeInterface || $node->bundle() !== self::MODULAR_BUNDLE) {
      return;
    }
    $variables['node'] = $node;
    $variables['is_modular_page'] = TRUE;

    // The landing search prints the page <h1> above its intro when it is the
    // first section (includes/landing.html.twig); page.html.twig then
    // leaves its own <h1> out, so there is always exactly one.
    $variables['modular_title_in_landing'] = self::titleLandingUuid($node) !== NULL;
    // A Landing hero with its lead panel prints the breadcrumb at the top of
    // the panel instead (crumbInLanding()), so the head leaves it out.
    $variables['modular_crumb_in_landing'] = self::crumbInLanding($node);

    $variables['modular_guide'] = self::stringValue($node, 'field_modular_type') === 'guide';

    // Guide mode: intro, owner, updated date and subjects under the <h1>.
    if (!$variables['modular_title_in_landing'] && self::stringValue($node, 'field_modular_type') === 'guide') {
      $variables['guide_head'] = $this->guideHead($node);
    }
    // Exhibition mode: the title page (includes/exhibit-cover.html.twig).
    if (!$variables['modular_title_in_landing'] && self::stringValue($node, 'field_modular_type') === 'exhibition') {
      $variables['exhibit_head'] = $this->exhibitHead($node);
    }
    // Showcase mode: every exhibition, under the <h1>
    // (includes/exhibit-showcase.html.twig).
    if (self::stringValue($node, 'field_modular_type') === 'showcase') {
      $variables['showcase'] = $this->showcase($node);
    }

    // Only build the TOC on routes that display the node itself.
    if (!self::pageNode()) {
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
    // A showcase page always has one: Browse and All exhibitions.
    $mode = self::stringValue($node, 'field_modular_type') ?: 'page';
    if ($mode !== 'form' && (self::heroTocIds($node) || $mode === 'showcase')) {
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
   * Whether this page is drawn as the 404 page.
   *
   * A 404 (the exception the error page is rendered for, as core's
   * page__404 suggestion checks), or the preview page (NOT_FOUND_PREVIEW).
   */
  public static function isNotFound(): bool {
    $exception = \Drupal::request()->attributes->get('exception');
    if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 404) {
      return TRUE;
    }
    return self::pageAlias() === self::NOT_FOUND_PREVIEW;
  }

  /**
   * Variables for includes/not-found.html.twig.
   *
   * The path that was asked for (on the preview, NOT_FOUND_SAMPLE), its
   * last segment as words for the search field ("/v2/special/exhibitons"
   * searches "exhibitons"), the quips (notFoundQuips()), and the links
   * out. Every page with this template varies by path already
   * (footer_cache).
   */
  private function notFound(string $alias): array {
    $path = $alias === self::NOT_FOUND_PREVIEW
      ? self::NOT_FOUND_SAMPLE
      : rawurldecode(\Drupal::requestStack()->getMainRequest()->getPathInfo());
    $last = pathinfo(basename($path), PATHINFO_FILENAME);
    $words = trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $last));
    if (ctype_digit(str_replace(' ', '', $words))) {
      $words = '';
    }
    [$quips, $quips_cache] = self::notFoundQuips();
    return [
      'path' => Unicode::truncate($path, 120, FALSE, TRUE),
      'quips' => $quips,
      'quips_cache' => $quips_cache,
      'query' => Unicode::truncate($words, 60, TRUE),
      'links' => [
        ['label' => (string) $this->t('Special Collections'), 'note' => (string) $this->t('Hill Memorial Library'), 'url' => self::SPECIAL_ROOT],
        ['label' => (string) $this->t('Exhibitions'), 'url' => self::SPECIAL_EXTRA[0]],
        ['label' => (string) $this->t('Plan a research visit'), 'url' => self::SPECIAL_ROOT . '/guides/special-collections'],
        ['label' => (string) $this->t('Ask a librarian'), 'url' => 'https://askus.lib.lsu.edu/'],
      ],
    ];
  }

  /**
   * The quips over the 404 page's <h1>, and their cache render array.
   *
   * Editors write them in the Intro field of the page at NOT_FOUND_PREVIEW,
   * one a line (or a paragraph each), published or not; NOT_FOUND_QUIPS
   * when it has none. js/not-found.js shows one at random on each visit,
   * as the page itself is cached. The page's cache tag goes with them, so
   * saving it updates every 404.
   *
   * @return array
   *   The quips (at most 50, 80 characters each), and a render array that
   *   only carries the cache tag.
   */
  private static function notFoundQuips(): array {
    $quips = [];
    $cache = new CacheableMetadata();
    $path = \Drupal::service('path_alias.manager')->getPathByAlias(self::NOT_FOUND_PREVIEW);
    if (preg_match('#^/node/(\d+)$#', $path, $m)) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($m[1]);
      if ($node instanceof NodeInterface) {
        $cache->addCacheableDependency($node);
        if ($node->hasField('field_intro')) {
          $text = (string) $node->get('field_intro')->value;
          $text = (string) preg_replace('#<(br|/p|/li|/h\d|/div)\b[^>]*>#i', "\n", $text);
          $text = Html::decodeEntities(strip_tags($text));
          foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim((string) preg_replace('/\s+/u', ' ', $line));
            if ($line !== '') {
              $quips[] = Unicode::truncate($line, 80, TRUE, TRUE);
            }
          }
        }
      }
    }
    else {
      // Made later (scripts/palefire_404_preview.php): look again then.
      $cache->addCacheTags(['node_list']);
    }
    $render = [];
    $cache->applyTo($render);
    return [array_slice(array_values(array_unique($quips)) ?: self::NOT_FOUND_QUIPS, 0, 50), $render];
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
    // So do the staff directory and profiles (views-view--palefire-staff--*)
    // and the Index (the palefire_search module's page).
    if (($variables['plugin_id'] ?? '') === 'page_title_block') {
      $variables['#cache']['contexts'][] = 'route';
      if ($this->currentModularNode() || StaffDirectory::isRoute() || \Drupal::routeMatch()->getRouteName() === 'palefire_search.page') {
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
    if (!empty($ids['start'])) {
      $extra[] = ['id' => $ids['start'], 'label' => $this->t('Start your research')];
    }
    if (!empty($ids['map'])) {
      // With Hours and directions beside it, the map is the rooms' section.
      $extra[] = ['id' => $ids['map'], 'label' => !empty($ids['hours']) ? $this->t('Rooms and access') : $this->t('Map')];
    }
    if (!empty($ids['hours'])) {
      $extra[] = ['id' => $ids['hours'], 'label' => $this->t('Hours and directions')];
    }
    // Exhibitions: "Home" first (the title page: the head,
    // includes/exhibit-cover), and every gallery lists its entries (h3s)
    // whatever the block's
    // "Include h3 sub-links" setting says. The galleries are one scrolled
    // document (js/exhibition.js).
    if ($node && self::stringValue($node, 'field_modular_type') === 'exhibition') {
      $extra[] = ['id' => 'exhibit-title', 'label' => $this->t('Home')];
      $variables['depth'] = 2;
    }
    // Showcase: the carousel (digital exhibitions) and the table first (includes/exhibit-showcase
    // prints the ids), then the page's own sections.
    if ($node && self::stringValue($node, 'field_modular_type') === 'showcase') {
      $extra[] = ['id' => 'pf-showcase-browse', 'label' => $this->t('Digital exhibitions')];
      $extra[] = ['id' => 'pf-showcase-all', 'label' => $this->t('All exhibitions')];
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
   * own (with one, palefire_modular already lists it). start: the Quick
   * Start, when a Landing hero shows it. map: the building
   * map's wrapper, when the landing has a map. hours: the Hours and directions
   * section, when the page has a location (a Landing hero only; other
   * landings have no such section). Fixed ids, so the links read #home, #map
   * and #hours; a section anchored with one of them keeps it, and the landing
   * then gets none.
   *
   * @return array{home?: string, start?: string, map?: string, hours?: string}
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
    if ($paragraph instanceof ParagraphInterface && $paragraph->bundle() === 'landing_hero' && !in_array('start', $taken, TRUE) && in_array('guide', \_palefire_hero_rules($paragraph), TRUE)) {
      $ids['start'] = 'start';
    }
    if ($paragraph->hasField('field_bento_map') && !$paragraph->get('field_bento_map')->isEmpty() && !in_array('map', $taken, TRUE)) {
      $ids['map'] = 'map';
    }
    $located = self::stringValue($node, 'field_hours_location') !== ''
      || self::stringValue($node, 'field_location_name') !== ''
      || self::stringValue($node, 'field_location_query') !== '';
    if ($paragraph->bundle() === 'landing_hero' && $located && !in_array('hours', $taken, TRUE)) {
      $ids['hours'] = 'hours';
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
   * @return array{title: string, subtitle: string, title_style: string, credit: ?array, intro: ?array, prompt: string, year: ?int, physical: ?string, kicker: string, keyfacts: array, action: ?array, cover: ?array, cache: array}
   */
  private function exhibitHead(NodeInterface $node): array {
    $cache = CacheableMetadata::createFromObject($node);
    $text = fn (string $field): ?array => $node->hasField($field) && !$node->get($field)->isEmpty()
      ? $node->get($field)->view(['label' => 'hidden', 'type' => 'text_default'])
      : NULL;

    $cover = NULL;
    if ($node->hasField('field_exhibit_cover') && !$node->get('field_exhibit_cover')->isEmpty()) {
      $item = _palefire_media_image_item($node->get('field_exhibit_cover')->first(), $cache);
      // The whole screen at any width. The page's first image: preloaded
      // from <head> unless the address opens a gallery (below).
      $cover = $item ? _palefire_exhibit_image($item, '100vw', $cache, TRUE) : NULL;
    }

    // Title style: "serif" (the default, also when the field is empty or
    // not yet added) or "sans".
    $title_style = self::stringValue($node, 'field_exhibit_title_style') === 'sans' ? 'sans' : 'serif';

    // Program pages: "Label: value" lines, split at a colon and a space
    // within the first 40 characters, so "Hours: 9:00" and a URL in a value
    // stay whole; any other line is a value with no label. Then the main
    // button.
    $keyfacts = [];
    if ($node->hasField('field_exhibit_keyfacts')) {
      foreach ($node->get('field_exhibit_keyfacts') as $item) {
        $line = trim((string) $item->value);
        if ($line === '') {
          continue;
        }
        $keyfacts[] = preg_match('/^([^:]{1,40}):\s+(.+)$/u', $line, $parts)
          ? ['label' => trim($parts[1]), 'value' => trim($parts[2])]
          : ['label' => '', 'value' => rtrim($line, ': ')];
      }
    }
    $action = NULL;
    if ($node->hasField('field_exhibit_action') && !$node->get('field_exhibit_action')->isEmpty()) {
      $link = $node->get('field_exhibit_action')->first();
      $url = $link->getUrl()->toString(TRUE);
      $cache->addCacheableDependency($url);
      $action = [
        'url' => $url->getGeneratedUrl(),
        'text' => trim((string) $link->title) ?: (string) $this->t('Apply'),
      ];
    }

    $render = [];
    $cache->applyTo($render);
    // The cover title's serif (base/_fonts.scss): preloaded so the <h1>
    // draws in it with the first paint rather than swapping in after the
    // stylesheet asks for it. Only serif titles use the font, so a sans
    // title never downloads it.
    if ($title_style === 'serif') {
      $render['#attached']['html_head_link'][] = [[
        'rel' => 'preload',
        'as' => 'font',
        'type' => 'font/woff2',
        'href' => base_path() . \Drupal::service('extension.list.theme')->getPath('palefire') . '/fonts/playfair-display/playfair-display-latin-500-normal.woff2',
        'crossorigin' => 'anonymous',
      ], FALSE];
    }
    if ($cover) {
      // The cover is lazy in the markup, so an address for a gallery (#…)
      // never fetches it. This runs in <head>, before the stylesheets: with
      // no #, it preloads the cover as early as an eager image would be
      // found; with one, .pf-exhibit-deep keeps the cover unrendered (so
      // unfetched) until js/exhibition.js has set the page up, or 4s pass
      // (exhibit-cover.html.twig); it is above the address, so stays lazy.
      $json = fn ($value) => json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
      $render['#attached']['html_head'][] = [[
        '#type' => 'html_tag',
        '#tag' => 'script',
        '#value' => Markup::create('(function (root, src, srcset, sizes) {'
          . 'if (location.hash.length > 1) {'
          . 'root.classList.add("pf-exhibit-deep");'
          . 'setTimeout(function () { root.classList.remove("pf-exhibit-deep"); }, 4000);'
          . 'return;'
          . '}'
          . 'var link = document.createElement("link");'
          . 'link.rel = "preload"; link.as = "image"; link.href = src;'
          . 'if (srcset) { link.setAttribute("imagesrcset", srcset); link.setAttribute("imagesizes", sizes); }'
          . 'link.setAttribute("fetchpriority", "high");'
          . 'document.head.appendChild(link);'
          . '})(document.documentElement, ' . $json($cover['src']) . ', ' . $json($cover['srcset'] ?? '') . ', ' . $json($cover['sizes'] ?? '100vw') . ');'),
      ], 'palefire_exhibit_cover'];
    }
    return [
      'title' => $node->label(),
      'subtitle' => self::stringValue($node, 'field_exhibit_subtitle'),
      'title_style' => $title_style,
      'credit' => $text('field_exhibit_credit'),
      'intro' => $text('field_intro'),
      // An editor's own line only: Begin, Contents and the Scroll cue say
      // what the old default did.
      'prompt' => self::stringValue($node, 'field_exhibit_prompt'),
      // Optional tiles under the credit.
      'year' => $node->hasField('field_exhibit_year') && !$node->get('field_exhibit_year')->isEmpty() ? (int) $node->get('field_exhibit_year')->value : NULL,
      'physical' => self::stringValue($node, 'field_exhibit_physical_note') ?: NULL,
      'kicker' => self::stringValue($node, 'field_exhibit_kicker') ?: (string) $this->t('Exhibition'),
      'keyfacts' => $keyfacts,
      'action' => $action,
      'cover' => $cover,
      'cache' => $render,
    ];
  }

  /**
   * Variables for includes/exhibit-showcase.html.twig on a showcase page.
   *
   * Two kinds of item, sorted together:
   * - Online exhibitions: every exhibition-mode page the visitor may see.
   *   field_exhibit_showcase decides each one: "featured" first, "hidden"
   *   left out, empty listed, except a page with a main button (a program
   *   page), which needs "listed" or "featured". Its physical exhibition
   *   post (field_exhibit_physical_post), if any, is a second link.
   * - Physical exhibitions: Articles in the page's category
   *   (field_showcase_category, "Exhibitions"), except those already a
   *   second link. Read about it, not Enter.
   * Editors also get unpublished ones, marked.
   *
   * "On view" (an Article's field_exhibit_on_view, days, inclusive):
   * while today is in it, an item left empty in field_exhibit_showcase is
   * featured, with "On view in <Where> through <end>" unless a note is
   * typed. An exhibition takes its post's dates. The render cache expires
   * at the next midnight that changes this (max-age; Internal Page Cache
   * ignores max-age, so there it changes on the next save or cache clear).
   *
   * Order: featured first (latest update first), then the newest year,
   * then by title.
   *
   * @return array{items: array, featured: int, cache: array}
   */
  private function showcase(NodeInterface $page): array {
    $cache = (new CacheableMetadata())
      ->addCacheTags(['node_list:' . self::MODULAR_BUNDLE, 'node_list:article'])
      ->addCacheContexts(['user.permissions']);
    $user = \Drupal::currentUser();
    $editor = $user->hasPermission('bypass node access') || $user->hasPermission('view any unpublished content');
    $nodes = \Drupal::entityTypeManager()->getStorage('node');
    $repository = \Drupal::service('entity.repository');
    $plain = fn (NodeInterface $node, string $field): string => $node->hasField($field) && !$node->get($field)->isEmpty()
      ? trim(preg_replace('/\s+/', ' ', Html::decodeEntities(strip_tags((string) $node->get($field)->value))))
      : '';
    $visible = function (NodeInterface $node) use ($cache, $editor): bool {
      $access = $node->access('view', NULL, TRUE);
      $cache->addCacheableDependency($node)->addCacheableDependency($access);
      return $access->isAllowed() && ($editor || $node->isPublished());
    };
    $image = function (NodeInterface $node, string $field) use ($cache): ?array {
      if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
        return NULL;
      }
      $item = _palefire_media_image_item($node->get($field)->first(), $cache);
      // Sizes are set per use in the template.
      return $item ? _palefire_exhibit_image($item, '', $cache) : NULL;
    };

    // On view: the dates as words, and whether today is in them. Each
    // future midnight that changes the answer caps the cache's max-age.
    $zone = new \DateTimeZone(date_default_timezone_get());
    $today = (new \DateTimeImmutable('now', $zone))->format('Y-m-d');
    $max_age = Cache::PERMANENT;
    $until = function (string $day) use ($zone, &$max_age): void {
      $seconds = (new \DateTimeImmutable($day . ' 00:00', $zone))->getTimestamp() - \Drupal::time()->getRequestTime();
      if ($seconds > 0) {
        $max_age = $max_age === Cache::PERMANENT ? $seconds : min($max_age, $seconds);
      }
    };
    $on_view = function (NodeInterface $post) use ($today, $until): ?array {
      if (!$post->hasField('field_exhibit_on_view') || $post->get('field_exhibit_on_view')->isEmpty()) {
        return NULL;
      }
      $range = $post->get('field_exhibit_on_view')->first();
      $start = substr((string) $range->value, 0, 10);
      $end = substr((string) ($range->end_value ?: $range->value), 0, 10);
      $from = \DateTimeImmutable::createFromFormat('!Y-m-d', $start);
      $to = \DateTimeImmutable::createFromFormat('!Y-m-d', $end);
      if (!$from || !$to) {
        return NULL;
      }
      $now = $start <= $today && $today <= $end;
      if ($today < $start) {
        $until($start);
      }
      elseif ($now) {
        $until($to->modify('+1 day')->format('Y-m-d'));
      }
      $dates = $from->format('Y') === $to->format('Y')
        ? $from->format('M j') . ' – ' . $to->format('M j, Y')
        : $from->format('M j, Y') . ' – ' . $to->format('M j, Y');
      return [
        'now' => $now,
        'year' => (int) $from->format('Y'),
        'dates' => $start === $end ? $from->format('M j, Y') : $dates,
        'end' => $to->format('F j'),
      ];
    };
    // "On view in Hill Memorial Library through March 6".
    $on_view_note = fn (string $where, array $view): string => $where !== ''
      ? (string) $this->t('On view in @where through @end', ['@where' => $where, '@end' => $view['end']])
      : (string) $this->t('On view through @end', ['@end' => $view['end']]);
    $when = fn (string $where, ?array $view): string => implode(' · ', array_filter([$where, $view['dates'] ?? '']));

    $items = [];
    $paired = [];

    // Online exhibitions.
    $query = $nodes->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', self::MODULAR_BUNDLE)
      ->condition('field_modular_type', 'exhibition');
    foreach ($nodes->loadMultiple($query->execute()) as $node) {
      if (!$node instanceof NodeInterface || !$visible($node)) {
        continue;
      }
      $node = $repository->getTranslationFromContext($node);
      $choice = self::stringValue($node, 'field_exhibit_showcase');
      $program = $node->hasField('field_exhibit_action') && !$node->get('field_exhibit_action')->isEmpty();
      if ($choice === 'hidden' || ($choice === '' && $program)) {
        continue;
      }

      // Its physical show: a second link, and its dates.
      $post = NULL;
      $view = NULL;
      if ($node->hasField('field_exhibit_physical_post') && ($article = $node->get('field_exhibit_physical_post')->entity) instanceof NodeInterface && $visible($article)) {
        $article = $repository->getTranslationFromContext($article);
        $paired[$article->id()] = TRUE;
        $view = $on_view($article);
        $where = self::stringValue($article, 'field_exhibit_where');
        $post = [
          'url' => $article->toUrl()->toString(),
          'when' => $when($where, $view),
        ];
      }
      $featured = $choice === 'featured' || ($choice === '' && $view && $view['now']);
      $note = self::stringValue($node, 'field_exhibit_feature_note');
      if ($featured && $note === '' && $view && $view['now']) {
        $note = $on_view_note(self::stringValue($article, 'field_exhibit_where'), $view);
      }

      // The summary: the credit line as text (it names the collection and
      // curator), else the start of the intro.
      $blurb = $plain($node, 'field_exhibit_credit') ?: $plain($node, 'field_intro');

      $items[] = [
        'title' => $node->label(),
        'url' => $node->toUrl()->toString(),
        'cta' => (string) $this->t('Enter the exhibition'),
        'digital' => TRUE,
        'subtitle' => self::stringValue($node, 'field_exhibit_subtitle'),
        'kicker' => self::stringValue($node, 'field_exhibit_kicker') ?: (string) $this->t('Exhibition'),
        'year' => $node->hasField('field_exhibit_year') && !$node->get('field_exhibit_year')->isEmpty() ? (int) $node->get('field_exhibit_year')->value : ($view['year'] ?? NULL),
        'blurb' => Unicode::truncate($blurb, 240, TRUE, TRUE),
        'when' => '',
        'post' => $post,
        'featured' => $featured,
        'note' => $featured ? $note : '',
        'unpublished' => !$node->isPublished(),
        // Its title page's face, as in exhibitHead().
        'title_style' => self::stringValue($node, 'field_exhibit_title_style') === 'sans' ? 'sans' : 'serif',
        'cover' => $image($node, 'field_exhibit_cover'),
        'changed' => (int) $node->getChangedTime(),
      ];
    }

    // Physical exhibitions: the category's posts not already paired.
    $terms = $page->hasField('field_showcase_category') ? $page->get('field_showcase_category')->referencedEntities() : [];
    $storages = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('node');
    if ($terms && isset($storages['field_article_category'])) {
      $cache->addCacheableDependency($terms[0]);
      $query = $nodes->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'article')
        ->condition('field_article_category', $terms[0]->id());
      foreach ($nodes->loadMultiple($query->execute()) as $post) {
        if (!$post instanceof NodeInterface || isset($paired[$post->id()]) || !$visible($post)) {
          continue;
        }
        $post = $repository->getTranslationFromContext($post);
        $choice = self::stringValue($post, 'field_exhibit_showcase');
        if ($choice === 'hidden') {
          continue;
        }
        $view = $on_view($post);
        $where = self::stringValue($post, 'field_exhibit_where');
        $featured = $choice === 'featured' || ($choice === '' && $view && $view['now']);
        $note = self::stringValue($post, 'field_exhibit_feature_note');
        if ($featured && $note === '' && $view && $view['now']) {
          $note = $on_view_note($where, $view);
        }

        // Excerpt: About Text, else the body's summary or text, as on the
        // landing's blog card (_palefire_blog_posts()).
        $blurb = '';
        foreach ([['field_about_text', 'value'], ['body', 'summary'], ['body', 'value']] as [$field, $property]) {
          if ($blurb === '' && $post->hasField($field) && !$post->get($field)->isEmpty()) {
            $blurb = _palefire_plain_excerpt((string) $post->get($field)->first()->{$property}, 240);
          }
        }
        $year = $view['year'] ?? NULL;
        if (!$year && $post->hasField('field_article_date') && $post->get('field_article_date')->date) {
          $year = (int) $post->get('field_article_date')->date->format('Y');
        }

        $items[] = [
          'title' => $post->label(),
          'url' => $post->toUrl()->toString(),
          'cta' => (string) $this->t('Read about it'),
        'digital' => FALSE,
          'subtitle' => '',
          'kicker' => (string) $this->t('Physical exhibition'),
          'year' => $year ?: (int) date('Y', (int) $post->getCreatedTime()),
          'blurb' => $blurb,
          'when' => $when($where, $view),
          'post' => NULL,
          'featured' => $featured,
          'note' => $featured ? $note : '',
          'unpublished' => !$post->isPublished(),
          'title_style' => 'serif',
          'cover' => $image($post, 'field_image'),
          'changed' => (int) $post->getChangedTime(),
        ];
      }
    }

    usort($items, fn (array $a, array $b): int =>
      [$b['featured'], $a['featured'] ? -$a['changed'] : 0, $b['year'] ?? 0, $a['title']]
      <=> [$a['featured'], $b['featured'] ? -$b['changed'] : 0, $a['year'] ?? 0, $b['title']]);

    $cache->mergeCacheMaxAge($max_age);
    $render = [];
    $cache->applyTo($render);
    return [
      'items' => $items,
      'featured' => count(array_filter($items, fn (array $item): bool => $item['featured'])),
      'cache' => $render,
    ];
  }

  /**
   * The Modular page node shown on the current route, if any.
   */
  private function currentModularNode(): ?NodeInterface {
    $node = \Drupal::routeMatch()->getParameter('node') ?? self::pageNode();
    return $node instanceof NodeInterface && $node->bundle() === self::MODULAR_BUNDLE ? $node : NULL;
  }

  /**
   * The node the current route shows as its page (NODE_PAGE_ROUTES), or NULL.
   *
   * A Preview counts only in the Full view mode: in another (Teaser) the
   * node is drawn as a teaser on an ordinary page.
   */
  public static function pageNode(): ?NodeInterface {
    $route_match = \Drupal::routeMatch();
    $route = $route_match->getRouteName();
    if (!in_array($route, self::NODE_PAGE_ROUTES, TRUE)) {
      return NULL;
    }
    if ($route === 'entity.node.preview') {
      $node = $route_match->getRawParameter('view_mode_id') === 'full' ? $route_match->getParameter('node_preview') : NULL;
    }
    else {
      $node = $route_match->getParameter('node');
    }
    return $node instanceof NodeInterface ? $node : NULL;
  }

  /**
   * Whether an alias is in the Special Collections section: SPECIAL_ROOT,
   * SPECIAL_EXTRA and the pages under them. They get the Special
   * Collections footer and the mobile dock.
   */
  public static function isSpecialPage(string $alias): bool {
    foreach ([self::SPECIAL_ROOT, ...self::SPECIAL_EXTRA] as $root) {
      if ($alias === $root || str_starts_with($alias, $root . '/')) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * The mobile dock's settings for this page (includes/dock.html.twig).
   *
   * - lid: the LibCal location (field_hours_location, else DOCK_LID).
   * - icons: DOCK_ICONS, for the template.
   * - json: what js/dock.js reads from inside #swup on each visit (the dock
   *   itself sits outside it): the location, the page's action, and the
   *   sections' actions by TOC anchor.
   */
  private function dock(string $alias): array {
    $node = self::pageNode();
    $lid = ($node ? self::stringValue($node, 'field_hours_location') : '') ?: self::DOCK_LID;
    $query = ($node ? self::stringValue($node, 'field_location_query') : '') ?: self::DOCK_ADDRESS;
    $actions = $this->dockActions($query);
    return [
      'lid' => $lid,
      'icons' => self::DOCK_ICONS,
      'json' => Json::encode([
        'lid' => $lid,
        'action' => $actions['pages'][$alias] ?? NULL,
        'sections' => (object) ($actions['sections'][$alias] ?? []),
      ]),
    ];
  }

  /**
   * The dock's actions: by page alias, and by alias then TOC anchor.
   *
   * Most pages have none: the dock shows hours and Contents only. A
   * section's action wins over the page's while it is current. Keys: label,
   * icon (DOCK_ICONS), href, external (opens a new tab), style (filled, or
   * secondary). One label per destination: never reuse a label for another
   * URL (Siteimprove, WCAG 3.2.4).
   *
   * @param string $query
   *   The address for Directions (field_location_query).
   */
  private function dockActions(string $query): array {
    $directions = [
      'label' => (string) $this->t('Directions'),
      'icon' => 'map-marker-alt',
      'href' => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($query),
      'external' => TRUE,
    ];
    return [
      'pages' => [
        '/v2/special/guides/duplication' => [
          'label' => (string) $this->t('Order copies'),
          'icon' => 'copy',
          'href' => 'https://specialcollections.lib.lsu.edu/logon',
        ],
        '/v2/special/guides/parking-and-travel-hill-memorial-library' => $directions,
        // The guide's old alias, kept by palefire_guide_hill_rename.php.
        '/v2/special/guides/parking-hill-memorial-library' => $directions,
        // The page's own booking button goes to the same form.
        '/v2/special/lecturehall' => [
          'label' => (string) $this->t('Request a date'),
          'icon' => 'calendar-alt',
          'href' => 'https://lsu.formstack.com/forms/lecture_hall_reservations',
        ],
      ],
      'sections' => [
        self::SPECIAL_ROOT => [
          // The map's room list (includes/map-key.html.twig).
          'map' => [
            'label' => (string) $this->t('Find a room'),
            'icon' => 'door-open',
            'href' => '#rooms-and-access',
          ],
          'written-at-hill' => [
            'label' => (string) $this->t('All publications'),
            'icon' => 'external-link-alt',
            'href' => 'https://guides.lib.lsu.edu/c.php?g=1091798&p=9443015',
            'external' => TRUE,
          ],
          // Secondary, so it doesn't compete with the question form's
          // own button. Hill Memorial's number, as in the footer.
          'ask-special-collections' => [
            'label' => (string) $this->t('Call desk'),
            'icon' => 'phone',
            'href' => 'tel:+12255786544',
            'style' => 'secondary',
          ],
        ],
      ],
    ];
  }

  /**
   * Whether the page is Combined Search in its Special Collections mode.
   */
  public static function isSpecialSearch(): bool {
    if (\Drupal::routeMatch()->getRouteName() !== 'palefire_search.page') {
      return FALSE;
    }
    // all(), not get(): a ?mode[]= array would make get() throw.
    $mode = \Drupal::request()->query->all()['mode'] ?? NULL;
    return $mode === 'special';
  }

  /**
   * The current page's path alias.
   *
   * On a Preview, the alias in the edit form (else the node's saved one), so
   * the page gets the same footer as when published. A new node with no
   * alias yet (Pathauto makes it on save) gets its /node/preview path.
   */
  public static function pageAlias(): string {
    $aliases = \Drupal::service('path_alias.manager');
    $node = \Drupal::routeMatch()->getRouteName() === 'entity.node.preview' ? self::pageNode() : NULL;
    if ($node) {
      $alias = $node->hasField('path') ? (string) $node->get('path')->alias : '';
      if ($alias === '' && !$node->isNew()) {
        $alias = $aliases->getAliasByPath('/node/' . $node->id());
      }
      if ($alias !== '') {
        return $alias;
      }
    }
    return $aliases->getAliasByPath(\Drupal::service('path.current')->getPath());
  }

  /**
   * Paragraph fields that hold a Modular page's sections, newest first.
   *
   * field_scroll_section is palefire_modular's field; field_sections is the
   * older one this class was written for.
   */
  public const SECTION_FIELDS = ['field_scroll_section', self::SECTIONS_FIELD];

  /**
   * UUID of the landing (TITLE_BUNDLES) that prints the page <h1>, or NULL.
   *
   * That is the first section the current user can see, when it is a
   * landing. Used by preprocessPage() (page.html.twig skips its own
   * <h1>) and palefire_preprocess_paragraph__bento_search() (the landing
   * prints it above the intro), so the two always agree.
   */
  public static function titleLandingUuid(NodeInterface $node): ?string {
    return self::titleLanding($node)?->uuid();
  }

  /**
   * The landing (TITLE_BUNDLES) that opens the page, or NULL.
   *
   * The first section the current user can see, when it is a landing: the
   * one titleLandingUuid() names. pageKind() reads its bundle.
   */
  public static function titleLanding(NodeInterface $node): ?FieldableEntityInterface {
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
        return in_array($paragraph->bundle(), self::TITLE_BUNDLES, TRUE) ? $paragraph : NULL;
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
