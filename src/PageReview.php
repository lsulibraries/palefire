<?php

declare(strict_types=1);

namespace Drupal\palefire;

use Drupal\node\NodeInterface;

/**
 * Review mode: notes and thumbs on any Palefire page, section by section.
 *
 * Any page under /v2 opened with ?review at the end of its address (anyone
 * with the link; no sign-in) gets a Review this page panel
 * (includes/page-review.html.twig, js/page-review.js): a pin beside each
 * section heading, the Webform "page_review"
 * (scripts/palefire_page_review_webform.php) with the page's address and
 * revision filled in, and the thumbs and approved notes already on the page.
 * Links to other /v2 pages keep ?review, so a reviewer can walk the site.
 * A section is a heading in the page's main content, named by its words:
 * reword a heading and its notes move to "the page as a whole".
 *
 * The hub, a guide text block with a "[page-review-hub]" paragraph and a
 * list of links after it (scripts/palefire_page_review_hub.php), shows each
 * listed page's thumbs and approved notes, with a link into review mode,
 * then any other page that has some.
 */
final class PageReview {

  /**
   * The Webform readers answer (scripts/palefire_page_review_webform.php).
   */
  public const WEBFORM = 'page_review';

  /**
   * The query argument that turns review mode on.
   */
  public const QUERY = 'review';

  /**
   * The hub's paragraph.
   */
  public const HUB_MARKER = '#<p>\s*\[page-review-hub\]\s*</p>#i';

  /**
   * Whether this request is a Palefire page in review mode.
   */
  public static function applies(): bool {
    $request = \Drupal::request();
    return $request->query->has(self::QUERY) && preg_match('#^/v2(/|$)#i', $request->getPathInfo());
  }

  /**
   * Review mode on <html> and the panel at the foot of the page.
   *
   * Called from PalefireHooks::preprocessHtml() (a theme may implement a
   * hook only once). Pages load in full from page to page (data-no-swup:
   * js/palefire-swup.js stays off), so each draws its own panel. Themes
   * can't implement hook_page_bottom(), so the panel joins page_bottom here.
   */
  public static function preprocessHtml(array &$variables): void {
    if (!self::applies()) {
      return;
    }
    $variables['html_attributes']->setAttribute('data-no-swup', '');
    $variables['html_attributes']->addClass('pf-review-mode');
    $tags = self::cacheTags();
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof NodeInterface) {
      $tags = array_values(array_unique(array_merge($tags, $node->getCacheTags())));
    }
    $variables['page_bottom'] = (array) ($variables['page_bottom'] ?? []);
    $variables['page_bottom']['palefire_page_review'] = [
      '#type' => 'inline_template',
      '#template' => "{% include '@palefire/includes/page-review.html.twig' %}",
      '#context' => self::panel(),
      '#cache' => [
        'contexts' => ['url.path', 'url.query_args:' . self::QUERY],
        'tags' => $tags,
      ],
    ];
  }

  /**
   * A page's address as notes keep it: no trailing slash, no query.
   */
  public static function path(string $path): string {
    $path = '/' . trim(preg_replace('#[?\#].*$#s', '', $path), '/');
    return mb_substr($path, 0, 255);
  }

  /**
   * Everything the panel needs, for the page being drawn.
   *
   * @return array{path: string, exit: string, form: ?array, groups: list<array>, notes: int, thumbs: int, counts: array}
   *   The page's address; the same without ?review; the Webform (NULL until
   *   it is created); thumbs and notes by section (about, votes, notes), the
   *   page as a whole first; how many notes and thumbs in all; and per
   *   section, the same counts for the pins.
   */
  public static function panel(): array {
    $request = \Drupal::request();
    $path = self::path($request->getPathInfo());
    $query = $request->query->all();
    unset($query[self::QUERY]);
    $exit = $request->getBasePath() . $path . ($query ? '?' . http_build_query($query) : '');

    $node = \Drupal::routeMatch()->getParameter('node');
    $revision = $node instanceof NodeInterface ? (string) $node->getRevisionId() : '';

    $notes = PublicNotes::approved(self::WEBFORM, 'section', ['page' => $path]);
    $votes = PublicNotes::votes(self::WEBFORM, 'section', ['page' => $path]);
    $groups = self::groups($notes, $votes);
    $counts = [];
    $total_notes = 0;
    $total_thumbs = 0;
    foreach ($groups as $group) {
      $thumbs = $group['votes']['good'] + $group['votes']['changes'];
      $counts[$group['about']] = ['notes' => count($group['notes']), 'thumbs' => $thumbs];
      $total_notes += count($group['notes']);
      $total_thumbs += $thumbs;
    }

    return [
      'path' => $path,
      'exit' => $exit,
      'form' => self::form(['page' => $path, 'revision' => $revision]),
      'groups' => $groups,
      'notes' => $total_notes,
      'thumbs' => $total_thumbs,
      'counts' => $counts,
    ];
  }

  /**
   * Takes the hub's list of pages out of a text that had the marker.
   *
   * @param string $text
   *   The text, the marker already taken out.
   *
   * @return array{0: list<array{path: string, label: string}>, 1: string}
   *   The pages (only links to /v2 pages count), and the text without the
   *   list.
   */
  public static function links(string $text): array {
    if (!preg_match('#<(ol|ul)\b[^>]*>(.*?)</\1>#is', $text, $list, PREG_OFFSET_CAPTURE)) {
      return [[], $text];
    }
    $pages = [];
    preg_match_all('#<a\b[^>]*\bhref="([^"]*)"[^>]*>(.*?)</a>#is', $list[2][0], $matches, PREG_SET_ORDER);
    foreach ($matches as [, $href, $inner]) {
      $href = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $href = preg_replace('#^https?://(?:www\.)?lib\.lsu\.edu(?=/)#i', '', $href);
      if (!preg_match('#^/v2(/|$|\?|\#)#i', $href)) {
        continue;
      }
      $path = self::path($href);
      $label = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
      $pages[$path] ??= ['path' => $path, 'label' => $label !== '' ? $label : $path];
    }
    $text = substr_replace($text, '', $list[0][1], strlen($list[0][0]));
    return [array_values($pages), $text];
  }

  /**
   * Everything the hub needs.
   *
   * @param array $pages
   *   From links().
   *
   * @return array{pages: list<array>, others: list<array>, notes: int, thumbs: int, ready: bool}
   *   The listed pages and then any other page with notes or thumbs, each
   *   with path, label, review (its address in review mode), votes, notes
   *   (how many) and groups (by section, as panel() has them); how many
   *   notes and thumbs in all; whether the Webform exists.
   */
  public static function hub(array $pages): array {
    $notes = PublicNotes::approved(self::WEBFORM, 'page', [], ['section']);
    $votes = PublicNotes::votes(self::WEBFORM, 'section', [], 'page');
    $base = \Drupal::request()->getBasePath();
    $build = function (string $path, string $label) use (&$notes, &$votes, $base): array {
      $by_section = [];
      foreach ($notes[$path] ?? [] as $note) {
        $by_section[$note['section']][] = $note;
      }
      $page = [
        'path' => $path,
        'label' => $label,
        'review' => $base . $path . '?' . self::QUERY,
        'votes' => $votes[$path] ?? ['good' => 0, 'changes' => 0],
        'notes' => count($notes[$path] ?? []),
        'groups' => self::groups($by_section, []),
      ];
      unset($notes[$path], $votes[$path]);
      return $page;
    };
    $listed = [];
    foreach ($pages as $page) {
      $listed[] = $build($page['path'], $page['label']);
    }
    $others = [];
    foreach (array_unique(array_merge(array_keys($notes), array_keys($votes))) as $path) {
      $path = (string) $path;
      if ($path !== '' && preg_match('#^/v2(/|$)#i', $path)) {
        $others[] = $build($path, $path);
      }
    }
    usort($others, fn (array $a, array $b): int => strcmp($a['path'], $b['path']));
    $total_notes = 0;
    $total_thumbs = 0;
    foreach (array_merge($listed, $others) as $page) {
      $total_notes += $page['notes'];
      $total_thumbs += $page['votes']['good'] + $page['votes']['changes'];
    }
    return [
      'pages' => $listed,
      'others' => $others,
      'notes' => $total_notes,
      'thumbs' => $total_thumbs,
      'ready' => self::form([]) !== NULL,
    ];
  }

  /**
   * Notes and thumbs by section, the page as a whole ('') first.
   *
   * @return list<array{about: string, notes: list<array>, votes: array{good: int, changes: int}}>
   *   In the order sections first had a note or a thumb; js/page-review.js
   *   puts them in the page's order.
   */
  private static function groups(array $notes, array $votes): array {
    $keys = array_map('strval', array_unique(array_merge([''], array_keys($notes), array_keys($votes))));
    $groups = [];
    foreach ($keys as $about) {
      $groups[] = [
        'about' => $about,
        'notes' => $notes[$about] ?? [],
        'votes' => $votes[$about] ?? ['good' => 0, 'changes' => 0],
      ];
    }
    // The page as a whole stays even when empty, for its thumbs.
    return array_values(array_filter($groups, fn (array $g): bool => $g['about'] === '' || $g['notes'] || $g['votes']['good'] || $g['votes']['changes']));
  }

  /**
   * The Webform with the page's details; NULL until it is created.
   */
  private static function form(array $data): ?array {
    $types = \Drupal::entityTypeManager();
    if (!$types->hasDefinition('webform') || !$types->getStorage('webform')->load(self::WEBFORM)) {
      return NULL;
    }
    return ['#type' => 'webform', '#webform' => self::WEBFORM, '#default_data' => $data];
  }

  /**
   * Redrawn when a note or a thumb is sent, approved or deleted, or the form
   * changes.
   */
  public static function cacheTags(): array {
    return PublicNotes::cacheTags(self::WEBFORM);
  }

}
