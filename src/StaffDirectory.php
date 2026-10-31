<?php

declare(strict_types=1);

namespace Drupal\palefire;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\node\NodeInterface;

/**
 * The Palefire staff directory and profiles (scripts/palefire_staff_setup.php).
 *
 * One View, palefire_staff, under /v2 so the pages get this theme and
 * leave the live /staff and /<name> pages alone:
 * - /v2/staff: every published Staff node in one list, filtered in the
 *   browser (js/staff-directory.js; ?department=<slug>&q=<words>).
 * - /v2/staff/<slug>: one person. The slug is the node's own alias without
 *   its slash ("/lilibedoya" gives /v2/staff/lilibedoya, the same name the
 *   old /staff#lilibedoya links use), or its node ID when it has none.
 *
 * The View only provides the routes; these helpers read the Staff nodes
 * (views-view--palefire-staff--*.html.twig, palefire_preprocess_views_view()).
 * Fields missing from the Staff type are skipped.
 */
final class StaffDirectory {

  public const VIEW = 'palefire_staff';

  public const PATH = '/v2/staff';

  public const BUNDLE = 'staff';

  /**
   * The CV sections of a profile, in order: field => heading.
   *
   * field_staff_responsibilites is spelled as the field was created.
   */
  public const SECTIONS = [
    'field_staff_responsibilites' => 'Responsibilities',
    'field_staff_experience' => 'Experience',
    'field_staff_education' => 'Education',
    'field_staff_research_interests' => 'Research interests',
    'field_staff_publications' => 'Publications',
    'field_staff_presentations' => 'Presentations',
    'field_staff_service' => 'Service',
    'field_staff_awards' => 'Awards',
  ];

  /**
   * Whether the current route is one of the View's pages.
   *
   * @param string|null $display
   *   'directory' or 'profile'; NULL for either.
   */
  public static function isRoute(?string $display = NULL): bool {
    $route = (string) \Drupal::routeMatch()->getRouteName();
    return $display ? $route === 'view.' . self::VIEW . '.' . $display : str_starts_with($route, 'view.' . self::VIEW . '.');
  }

  /**
   * The person on the current profile page, or NULL.
   *
   * NULL when the slug matches no published Staff node the visitor can see.
   */
  public static function current(CacheableMetadata $cache): ?NodeInterface {
    $cache->addCacheContexts(['url.path']);
    if (!self::isRoute('profile')) {
      return NULL;
    }
    return self::fromSlug((string) \Drupal::routeMatch()->getRawParameter('arg_0'), $cache);
  }

  /**
   * The Staff node for a slug, or NULL.
   */
  public static function fromSlug(string $slug, CacheableMetadata $cache): ?NodeInterface {
    $slug = trim($slug, '/');
    if ($slug === '' || str_contains($slug, '/')) {
      return NULL;
    }
    $nid = ctype_digit($slug) ? $slug : NULL;
    if ($nid === NULL) {
      $path = \Drupal::service('path_alias.manager')->getPathByAlias('/' . $slug);
      $nid = preg_match('#^/node/(\d+)$#', $path, $m) ? $m[1] : NULL;
    }
    $node = $nid ? \Drupal::entityTypeManager()->getStorage('node')->load($nid) : NULL;
    if (!$node instanceof NodeInterface || $node->bundle() !== self::BUNDLE) {
      return NULL;
    }
    $cache->addCacheableDependency($node);
    $access = $node->access('view', NULL, TRUE);
    $cache->addCacheableDependency($access);
    if (!$access->isAllowed() || !$node->isPublished()) {
      return NULL;
    }
    return \Drupal::service('entity.repository')->getTranslationFromContext($node);
  }

  /**
   * A person's slug: their alias without the slash, else the node ID.
   */
  public static function slug(NodeInterface $node): string {
    $path = '/node/' . $node->id();
    $alias = trim(\Drupal::service('path_alias.manager')->getAliasByPath($path), '/');
    return ($alias === '' || $alias === trim($path, '/') || str_contains($alias, '/')) ? (string) $node->id() : $alias;
  }

  /**
   * A person's profile URL.
   */
  public static function url(NodeInterface $node): string {
    return self::PATH . '/' . self::slug($node);
  }

  /**
   * A department's slug for ?department=: "Research & Instruction Services"
   * gives "research-and-instruction-services".
   */
  public static function departmentSlug(string $label): string {
    $text = \Drupal::transliteration()->transliterate(mb_strtolower($label));
    return trim(preg_replace('/[^a-z0-9]+/', '-', str_replace('&', ' and ', $text)), '-');
  }

  /**
   * The directory URL filtered to one department.
   */
  public static function departmentUrl(string $label): string {
    return self::PATH . '?department=' . self::departmentSlug($label);
  }

  /**
   * Sort key: family name first (the last word, past Jr., Sr., II…).
   */
  public static function sortKey(string $name): string {
    $words = preg_split('/\s+/u', trim(\Drupal::transliteration()->transliterate(mb_strtolower($name))));
    while (count($words) > 1 && preg_match('/^(jr|sr|i{2,3}|iv)\.?$/', (string) end($words))) {
      array_pop($words);
    }
    return end($words) . ' ' . implode(' ', $words);
  }

  /**
   * One person as a directory row (also the start of a profile).
   *
   * @return array
   *   name, url, slug, title, initials, departments (label, slug, url),
   *   subjects (labels), email, phone, phone_href, photo (render array or
   *   NULL), search (lower-case text the filter matches).
   */
  public static function row(NodeInterface $node, CacheableMetadata $cache, array $photo_styles = ['pf_staff_thumb']): array {
    $cache->addCacheableDependency($node);
    $name = $node->label();
    $words = preg_split('/\s+/u', trim($name));
    $initials = mb_strtoupper(mb_substr($words[0] ?? '', 0, 1) . (count($words) > 1 ? mb_substr((string) end($words), 0, 1) : ''));

    $departments = [];
    foreach (self::terms($node, 'field_department', $cache) as $label) {
      $departments[] = ['label' => $label, 'slug' => self::departmentSlug($label), 'url' => self::departmentUrl($label)];
    }
    $subjects = self::terms($node, 'field_subject_area', $cache);

    $phone = self::text($node, 'field_phone_number');
    $digits = preg_replace('/\D+/', '', $phone);
    $photo = NULL;
    if ($node->hasField('field_staff_photo') && !$node->get('field_staff_photo')->isEmpty()) {
      $photo = _palefire_image_build($node->get('field_staff_photo')->first(), $photo_styles, $cache);
    }
    $title = self::text($node, 'field_title');

    return [
      'name' => $name,
      'url' => self::url($node),
      'slug' => self::slug($node),
      'title' => $title,
      'initials' => $initials,
      'departments' => $departments,
      'subjects' => $subjects,
      'email' => self::text($node, 'field_staff_email'),
      'phone' => $phone,
      'phone_href' => strlen($digits) === 10 ? 'tel:+1' . $digits : ($digits !== '' ? 'tel:' . $digits : ''),
      'photo' => $photo,
      'search' => mb_strtolower(\Drupal::transliteration()->transliterate(implode(' ', [$name, $title, ...array_column($departments, 'label'), ...$subjects]))),
    ];
  }

  /**
   * One person's profile: a row, plus portrait, note, links and sections.
   *
   * - portrait: the photo at pf_staff_portrait, loaded at once (it is the
   *   page's main image), or NULL.
   * - note: the Contact note (processed text) or NULL.
   * - links: url, text; each Link field (a LibGuides profile reads
   *   "Research guides").
   * - sections: id, label, body (processed text), in SECTIONS order, the
   *   empty ones left out.
   */
  public static function profile(NodeInterface $node, CacheableMetadata $cache): array {
    $profile = self::row($node, $cache, ['pf_staff_portrait']);
    $profile['portrait'] = $profile['photo'];
    if ($profile['portrait']) {
      $profile['portrait']['#attributes']['loading'] = 'eager';
      $profile['portrait']['#attributes']['fetchpriority'] = 'high';
    }
    $profile['note'] = self::processed($node->hasField('field_contact_note') ? $node->get('field_contact_note') : NULL);

    $profile['links'] = [];
    foreach ($node->getFieldDefinitions() as $name => $definition) {
      if ($definition->getType() !== 'link' || !str_starts_with($name, 'field_')) {
        continue;
      }
      foreach ($node->get($name) as $item) {
        $url = $item->getUrl();
        $generated = $url->toString(TRUE);
        $cache->addCacheableDependency($generated);
        $text = trim((string) $item->title);
        if ($text === '') {
          $text = str_contains($generated->getGeneratedUrl(), 'guides.lib.lsu.edu') ? (string) t('Research guides') : (string) $definition->getLabel();
        }
        $profile['links'][] = ['url' => $generated->getGeneratedUrl(), 'text' => $text, 'external' => $url->isExternal()];
      }
    }

    $profile['sections'] = [];
    foreach (self::SECTIONS as $name => $label) {
      $body = self::processed($node->hasField($name) ? $node->get($name) : NULL);
      if ($body) {
        $profile['sections'][] = ['id' => 'pf-staff-' . str_replace('_', '-', substr($name, 6)), 'label' => $label, 'body' => $body];
      }
    }
    return $profile;
  }

  /**
   * The labels of a term reference field, in order.
   *
   * @return string[]
   *   Labels, unpublished terms left out.
   */
  private static function terms(NodeInterface $node, string $field, CacheableMetadata $cache): array {
    if (!$node->hasField($field)) {
      return [];
    }
    $labels = [];
    foreach ($node->get($field)->referencedEntities() as $term) {
      $cache->addCacheableDependency($term);
      if (!method_exists($term, 'isPublished') || $term->isPublished()) {
        $labels[] = $term->label();
      }
    }
    return $labels;
  }

  /**
   * A plain field's first value, trimmed ('' when missing or empty).
   */
  private static function text(NodeInterface $node, string $field): string {
    return $node->hasField($field) ? trim((string) $node->get($field)->value) : '';
  }

  /**
   * A formatted text field as processed text (every value), or NULL.
   */
  private static function processed(?FieldItemListInterface $items): ?array {
    $build = [];
    foreach ($items ?? [] as $item) {
      if (trim(strip_tags((string) $item->value, '<img>')) !== '') {
        $build[] = ['#type' => 'processed_text', '#text' => $item->value, '#format' => $item->format];
      }
    }
    return $build ?: NULL;
  }

}
