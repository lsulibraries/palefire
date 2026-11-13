<?php

declare(strict_types=1);

namespace Drupal\palefire;

use Drupal\Core\Cache\Cache;

/**
 * A review page for an email newsletter drafted in Constant Contact.
 *
 * A guide text paragraph that is only
 * "[newsletter-review:<account>/<campaign activity id>]"
 * (palefire_preprocess_paragraph__guide_text()) draws the draft as Constant
 * Contact's public preview shows it, with a Comment button on each story and
 * a feedback form beside it (includes/newsletter-review.html.twig,
 * js/newsletter-review.js). scripts/palefire_newsletter_review.php builds the
 * page.
 *
 * The draft is fetched live, so reviewers always read what is in Constant
 * Contact now, and kept five minutes. Nothing of it is stored in the theme's
 * repository. Only campaignlp.constantcontact.com is asked, at an address
 * built from the two ids, so the marker can't point the server elsewhere.
 * The email is shown in a sandboxed frame with no scripts, its scripts are
 * removed, and its links open in a new tab.
 *
 * Each fetch is labeled with a short fingerprint of the email, saved with
 * every comment, so a comment can be matched to the version it was about.
 *
 * The same previews draw the public archive (scripts/palefire_newsletter_archive.php):
 * a block holding "[newsletter-archive]" turns its list of issue links into a
 * shelf of front pages (includes/newsletter-archive.html.twig), and one holding
 * "[newsletter-compare]" sets its first two issues side by side under a
 * slider (includes/newsletter-compare.html.twig). Editors add an issue by
 * adding a link to the list.
 */
final class NewsletterReview {

  /**
   * The Webform reviewers answer (scripts/palefire_newsletter_webform.php).
   */
  public const WEBFORM = 'newsletter_review';

  /**
   * How long a fetched draft is kept, in seconds.
   */
  public const MAX_AGE = 300;

  /**
   * The marker: "[newsletter-review:<account>/<activity uuid>]".
   */
  public const MARKER = '#<p>\s*\[newsletter-review:(\d{6,15})/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\]\s*</p>#i';

  private const PREVIEW = 'https://campaignlp.constantcontact.com/em/%s/%s';

  /**
   * What the template needs, from the marker's two ids.
   *
   * @return array{html: ?string, version: ?string, fetched: ?int, preview: string, form: ?array, issue: string}
   *   The email (NULL when it can't be fetched), its fingerprint, when it was
   *   fetched, the public preview address, the Webform (NULL without it), and
   *   the issue key saved with comments.
   */
  public static function view(string $account, string $activity): array {
    $preview = sprintf(self::PREVIEW, $account, strtolower($activity));
    $draft = self::draft($preview);
    return [
      'html' => $draft['html'] ?? NULL,
      'version' => $draft['version'] ?? NULL,
      'fetched' => $draft['fetched'] ?? NULL,
      'preview' => $preview,
      'form' => self::form($draft['version'] ?? '', strtolower($activity)),
      'issue' => strtolower($activity),
    ];
  }

  /**
   * A sent email doesn't change: kept a day.
   */
  public const SENT_MAX_AGE = 86400;

  /**
   * "[newsletter-archive]": the block's list of issues becomes a gallery.
   */
  public const ARCHIVE_MARKER = '#<p>\s*\[newsletter-archive\]\s*</p>#i';

  /**
   * "[newsletter-compare]": the block's first two issues, before and after.
   */
  public const COMPARE_MARKER = '#<p>\s*\[newsletter-compare\]\s*</p>#i';

  /**
   * A link to an issue's public preview, with its words.
   */
  /**
   * A sent date ending an issue's line: "· July 7, 2026" or ", sent July 7, 2026".
   * Never a bare date, which may belong to the line ("Opening March 3, 2027").
   */
  private const SENT = '/\s*(?:[·|–—]\s*(?:sent\s+)?|,\s*sent\s+)((?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},\s*\d{4})\s*$/iu';

  /**
   * Where a sent issue's front page is kept: a WebP 480 by 640 pixels, the
   * top 600 by 800 of the email captured in a browser and scaled to 80%,
   * named by its Constant Contact id. The server can't capture it, so it is
   * made once, when the issue is sent, and added to the theme (README,
   * Newsletter front pages).
   */
  public const FRONT = 'images/newsletters/%s.webp';

  private const LINK = '#<a\b[^>]*\bhref="https://campaignlp\.constantcontact\.com/em/(\d{6,15})/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/?"[^>]*>(.*?)</a>#is';

  /**
   * The issues linked in a block's text, in order, and the text without them.
   *
   * Each issue is a link to its Constant Contact preview, its words the
   * issue's name (July 2026); words after the link in the same list item or
   * paragraph, past a colon or dash, are its line (The Awards Issue). A date
   * at the end of the line, after a middle dot ("· July 7, 2026"), is
   * the day it was sent. The lists and paragraphs that held them are taken
   * out of the text.
   *
   * @return array{0: list<array{account: string, id: string, name: string, line: string, sent: ?string, sent_iso: ?string}>, 1: string}
   */
  public static function issues(string $text): array {
    $issues = [];
    $text = preg_replace_callback('#<(li|p)\b[^>]*>(.*?)</\1>#is', function (array $m) use (&$issues): string {
      if (!preg_match(self::LINK, $m[2], $link)) {
        return $m[0];
      }
      $line = trim(html_entity_decode(strip_tags(str_replace($link[0], '', $m[2])), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
      // No-break spaces from the editor count as spaces.
      $line = trim(preg_replace(['/[\s\x{00A0}]+/u', '/^[\s:–—-]+/u'], [' ', ''], $line));
      $sent = NULL;
      $sent_iso = NULL;
      if (preg_match(self::SENT, $line, $date)) {
        $day = \DateTimeImmutable::createFromFormat('!F j, Y', preg_replace('/\s+/', ' ', $date[1]));
        if ($day) {
          $sent = $day->format('F j, Y');
          $sent_iso = $day->format('Y-m-d');
          $line = trim(substr($line, 0, -strlen($date[0])));
        }
      }
      $issues[] = [
        'account' => $link[1],
        'id' => strtolower($link[2]),
        'name' => trim(html_entity_decode(strip_tags($link[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        'line' => $line,
        'sent' => $sent,
        'sent_iso' => $sent_iso,
      ];
      return '';
    }, $text);
    // Lists left empty.
    $text = preg_replace('#<(ul|ol)\b[^>]*>\s*</\1>#i', '', $text);
    return [$issues, $text];
  }

  /**
   * The shelf: each issue with its front page, newest first as listed.
   *
   * The front page is the theme's capture of the email's top (FRONT). An
   * issue without one, newly added, is a cover set in type with its name
   * and number until its capture is added. Nothing is fetched: the issues
   * open from Constant Contact itself.
   */
  public static function archive(array $issues): array {
    $count = count($issues);
    $theme = \Drupal::service('extension.list.theme')->getPath('palefire');
    $urls = \Drupal::service('file_url_generator');
    $items = [];
    foreach (array_values($issues) as $i => $issue) {
      $front = $theme . '/' . sprintf(self::FRONT, $issue['id']);
      $items[] = $issue + [
        'number' => sprintf('%02d', $count - $i),
        'preview' => sprintf(self::PREVIEW, $issue['account'], $issue['id']),
        'front' => is_file(DRUPAL_ROOT . '/' . $front) ? $urls->generateString($front) : NULL,
      ];
    }
    return ['items' => $items];
  }

  /**
   * Before and after: the first two issues, each drawn whole.
   *
   * The second is often a draft, so both are kept only MAX_AGE.
   */
  public static function compare(array $issues): ?array {
    if (count($issues) < 2) {
      return NULL;
    }
    $sides = [];
    foreach (array_slice(array_values($issues), 0, 2) as $issue) {
      $preview = sprintf(self::PREVIEW, $issue['account'], $issue['id']);
      $draft = self::draft($preview);
      $sides[] = ['name' => $issue['name'], 'line' => $issue['line'], 'preview' => $preview, 'html' => $draft['html'] ?? NULL];
    }
    return ['before' => $sides[0], 'after' => $sides[1]];
  }

  /**
   * The draft from the preview, cached for MAX_AGE; NULL if it can't be had.
   *
   * A failed fetch is kept for a minute, so a slow Constant Contact doesn't
   * hold up every view.
   */
  private static function draft(string $preview, int $max_age = self::MAX_AGE): ?array {
    $cid = 'palefire:newsletter_review:' . sha1($preview);
    $cache = \Drupal::cache();
    if ($hit = $cache->get($cid)) {
      return $hit->data ?: NULL;
    }
    $now = \Drupal::time()->getRequestTime();
    $draft = NULL;
    try {
      $response = \Drupal::httpClient()->get($preview, [
        'timeout' => 8,
        'allow_redirects' => FALSE,
        'headers' => ['Accept' => 'text/html'],
      ]);
      $body = (string) $response->getBody();
      if ($response->getStatusCode() === 200 && stripos($body, '<html') !== FALSE && strlen($body) < 2000000) {
        $html = self::clean($body);
        $draft = ['html' => $html, 'version' => substr(sha1($html), 0, 7), 'fetched' => $now];
      }
    }
    catch (\Throwable $e) {
      \Drupal::logger('palefire')->warning('Newsletter review: could not fetch @url: @message', ['@url' => $preview, '@message' => $e->getMessage()]);
    }
    $cache->set($cid, $draft ?? FALSE, $now + ($draft ? $max_age : 60), [self::cacheTag()]);
    return $draft;
  }

  /**
   * The email, made safe to show in the frame.
   *
   * No scripts or inline handlers; links open in a new tab; the "view in
   * your browser" bar keeps its link. The frame's sandbox stops scripts as
   * well: this is a second lock, not the only one.
   */
  private static function clean(string $html): string {
    $html = preg_replace('#<script\b[^>]*>.*?</script\s*>#is', '', $html);
    $html = preg_replace('#<(iframe|object|embed|form)\b[^>]*>.*?</\1\s*>#is', '', $html);
    // Constant Contact's note over every preview ("based on the last saved
    // version"): the page says which version it shows.
    $html = preg_replace('#<div\b[^>]*>\s*The content in this preview is based on the last saved version[^<]*</div>#i', '', $html);
    $html = preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
    $html = preg_replace('#(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2#i', '$1="#"', $html);
    $base = '<base target="_blank"><meta name="referrer" content="no-referrer">';
    $html = preg_replace('#<head\b[^>]*>#i', '$0' . $base, $html, 1, $count);
    return $count ? $html : $base . $html;
  }

  /**
   * The Webform, with the issue and version filled in; NULL without it.
   */
  private static function form(string $version, string $issue): ?array {
    $types = \Drupal::entityTypeManager();
    if (!$types->hasDefinition('webform') || !$types->getStorage('webform')->load(self::WEBFORM)) {
      return NULL;
    }
    return [
      '#type' => 'webform',
      '#webform' => self::WEBFORM,
      '#default_data' => ['issue' => $issue, 'version' => $version],
    ];
  }

  /**
   * Cleared with drush cr; named so a fetch can be redone on its own.
   */
  public static function cacheTag(): string {
    return 'palefire_newsletter_review';
  }

  /**
   * Cache tags for the paragraph: the Webform's, so editing it redraws.
   */
  public static function cacheTags(): array {
    return Cache::mergeTags([self::cacheTag()], ['config:webform.webform.' . self::WEBFORM]);
  }

}
