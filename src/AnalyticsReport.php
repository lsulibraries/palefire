<?php

declare(strict_types=1);

namespace Drupal\palefire;

use Drupal\Core\Cache\Cache;

/**
 * The staff analytics report (scripts/palefire_analytics_pull.php).
 *
 * Each night a drush script reads the GA4 property through the Analytics Data
 * API, signed in as a read-only service account whose key file lives on the
 * server, never in the repo. It keeps the figures in Drupal's State. A guide
 * text paragraph that is only "[analytics:report]"
 * (palefire_preprocess_paragraph__guide_text()) draws them
 * (includes/analytics-report.html.twig) for visitors who can see unpublished
 * content. The page itself stays unpublished
 * (scripts/palefire_analytics_page.php). Search terms are people's own words,
 * so the report never reaches a public page.
 *
 * Pages are grouped by the labels Palefire puts on <html> (data-pf-section,
 * data-pf-type, sent as pf_section and pf_type), not by URL, so the report
 * keeps working after /v2 is dropped. Pages without them are the old site.
 * No PHP library: the service account's token is a JWT signed with OpenSSL,
 * and the requests go through Drupal's HTTP client.
 */
final class AnalyticsReport {

  /**
   * The GA4 property (LSU Libraries, "Library Website").
   */
  public const PROPERTY = '377746797';

  public const STATE = 'palefire.analytics_report';

  public const CACHE_TAG = 'palefire_analytics_report';

  /**
   * Who sees the figures: the same people who can open an unpublished page.
   */
  public const PERMISSION = 'view any unpublished content';

  private const API = 'https://analyticsdata.googleapis.com/v1beta/properties/';

  private const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

  /**
   * Labels for data-pf-type values; anything else is shown as sent.
   */
  private const TYPES = [
    'search' => 'Combined Search',
    'guide' => 'Guides',
    'exhibition' => 'Exhibitions',
    'landing' => 'Landing pages',
    'location' => 'Locations',
    'staff-profile' => 'Staff profiles',
    'staff-directory' => 'Staff directory',
    'not-found' => 'Page not found',
    'other' => 'Other',
  ];

  /**
   * Reads GA4 and returns the figures to keep in State.
   *
   * @param string $key_file
   *   The service account's JSON key.
   *
   * @throws \RuntimeException
   *   When the key can't be read or Google refuses a request.
   */
  public static function pull(string $key_file): array {
    $token = self::token($key_file);
    $now = ['startDate' => '28daysAgo', 'endDate' => 'yesterday'];
    $before = ['startDate' => '56daysAgo', 'endDate' => '29daysAgo'];
    $palefire = ['filter' => ['fieldName' => 'customEvent:pf_section', 'inListFilter' => ['values' => ['special', 'library']]]];
    $searches = ['filter' => ['fieldName' => 'eventName', 'stringFilter' => ['value' => 'search']]];

    $reports = self::run($token, [
      // 0: page views and users by section, this period and the one before.
      [
        'dateRanges' => [$now, $before],
        'dimensions' => [['name' => 'customEvent:pf_section']],
        'metrics' => [['name' => 'screenPageViews'], ['name' => 'activeUsers']],
      ],
      // 1: Palefire page views by kind of page.
      [
        'dateRanges' => [$now],
        'dimensions' => [['name' => 'customEvent:pf_type']],
        'metrics' => [['name' => 'screenPageViews']],
        'dimensionFilter' => $palefire,
        'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => TRUE]],
        'limit' => 12,
      ],
      // 2: the most viewed Palefire pages.
      [
        'dateRanges' => [$now],
        'dimensions' => [['name' => 'pagePath'], ['name' => 'pageTitle']],
        'metrics' => [['name' => 'screenPageViews']],
        'dimensionFilter' => $palefire,
        'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => TRUE]],
        'limit' => 15,
      ],
      // 3: searches, this period and the one before.
      [
        'dateRanges' => [$now, $before],
        'metrics' => [['name' => 'eventCount']],
        'dimensionFilter' => $searches,
      ],
      // 4: what people searched for.
      [
        'dateRanges' => [$now],
        'dimensions' => [['name' => 'customEvent:search_term']],
        'metrics' => [['name' => 'eventCount']],
        'dimensionFilter' => $searches,
        'orderBys' => [['metric' => ['metricName' => 'eventCount'], 'desc' => TRUE]],
        'limit' => 25,
      ],
      // 5: searches by mode (All, Special Collections...).
      [
        'dateRanges' => [$now],
        'dimensions' => [['name' => 'customEvent:search_mode']],
        'metrics' => [['name' => 'eventCount']],
        'dimensionFilter' => $searches,
        'orderBys' => [['metric' => ['metricName' => 'eventCount'], 'desc' => TRUE]],
      ],
      // 6: page views each week, by section: twelve whole weeks from a
      // Monday, so the first bar isn't a partial week, then this week so far.
      [
        'dateRanges' => [['startDate' => (new \DateTimeImmutable('monday this week'))->modify('-12 weeks')->format('Y-m-d'), 'endDate' => 'yesterday']],
        'dimensions' => [['name' => 'isoYearIsoWeek'], ['name' => 'customEvent:pf_section']],
        'metrics' => [['name' => 'screenPageViews']],
        'orderBys' => [['dimension' => ['dimensionName' => 'isoYearIsoWeek']]],
      ],
    ]);

    $data = [
      'updated' => \Drupal::time()->getRequestTime(),
      'sections' => [],
      'types' => [],
      'pages' => [],
      'searches' => [0, 0],
      'terms' => [],
      'modes' => [],
      'weeks' => [],
    ];
    foreach (self::rows($reports[0]) as $row) {
      $section = self::section($row['d'][0]);
      // With two date ranges GA4 adds a dateRange dimension last.
      $range = ($row['d'][1] ?? 'date_range_0') === 'date_range_0' ? 0 : 1;
      $data['sections'][$section][$range]['views'] = ($data['sections'][$section][$range]['views'] ?? 0) + (int) $row['m'][0];
      $data['sections'][$section][$range]['users'] = ($data['sections'][$section][$range]['users'] ?? 0) + (int) $row['m'][1];
    }
    foreach (self::rows($reports[1]) as $row) {
      $data['types'][] = ['type' => $row['d'][0], 'views' => (int) $row['m'][0]];
    }
    foreach (self::rows($reports[2]) as $row) {
      $data['pages'][] = ['path' => $row['d'][0], 'title' => $row['d'][1], 'views' => (int) $row['m'][0]];
    }
    foreach (self::rows($reports[3]) as $row) {
      $range = ($row['d'][0] ?? 'date_range_0') === 'date_range_0' ? 0 : 1;
      $data['searches'][$range] += (int) $row['m'][0];
    }
    foreach (self::rows($reports[4]) as $row) {
      $data['terms'][] = ['term' => $row['d'][0], 'count' => (int) $row['m'][0]];
    }
    foreach (self::rows($reports[5]) as $row) {
      $data['modes'][] = ['mode' => $row['d'][0], 'count' => (int) $row['m'][0]];
    }
    foreach (self::rows($reports[6]) as $row) {
      $section = self::section($row['d'][1]);
      $data['weeks'][$row['d'][0]][$section] = ($data['weeks'][$row['d'][0]][$section] ?? 0) + (int) $row['m'][0];
    }
    return $data;
  }

  /**
   * Keeps fresh figures and redraws the report.
   */
  public static function save(array $data): void {
    \Drupal::state()->set(self::STATE, $data);
    Cache::invalidateTags([self::CACHE_TAG]);
  }

  /**
   * The template's variables, or NULL before the first pull.
   */
  public static function view(): ?array {
    $data = \Drupal::state()->get(self::STATE);
    if (!is_array($data) || empty($data['updated'])) {
      return NULL;
    }
    $formatter = \Drupal::service('date.formatter');
    $sum = fn (int $range, string $key, array $sections) => array_sum(array_map(fn ($s) => (int) ($data['sections'][$s][$range][$key] ?? 0), $sections));
    $palefire = ['special', 'library'];
    $figure = function (string $label, int $now, int $before): array {
      $change = $before > 0 ? (int) round(($now - $before) / $before * 100) : NULL;
      return [
        'label' => $label,
        'value' => number_format($now),
        'change' => $change,
      ];
    };
    $bars = function (array $rows, string $label, string $count, ?callable $name = NULL): array {
      $max = max(array_merge([1], array_column($rows, $count)));
      return array_map(fn ($row) => [
        'label' => $name ? $name((string) $row[$label]) : (string) $row[$label],
        'value' => number_format((int) $row[$count]),
        'width' => round((int) $row[$count] / $max * 100, 1),
      ], $rows);
    };
    $weeks = [];
    // The week the pull ran in is only partly counted (through yesterday).
    $pulled = (new \DateTimeImmutable('@' . (int) $data['updated']))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    $pulled_week = $pulled->modify('-1 day')->format('oW');
    foreach ($data['weeks'] as $week => $sections) {
      $weeks[] = [
        'current' => (string) $week === $pulled_week && $pulled->format('N') !== '1',
        'week' => self::weekLabel((string) $week),
        'palefire' => array_sum(array_intersect_key($sections, array_flip($palefire))),
        'old' => (int) ($sections['old'] ?? 0),
      ];
    }
    $week_max = max(array_merge([1], array_map(fn ($w) => $w['palefire'] + $w['old'], $weeks)));
    foreach ($weeks as &$week) {
      $week['palefire_width'] = round($week['palefire'] / $week_max * 100, 1);
      $week['old_width'] = round($week['old'] / $week_max * 100, 1);
      $week['palefire'] = number_format($week['palefire']);
      $week['old'] = number_format($week['old']);
    }
    unset($week);
    $total_terms = array_sum(array_column($data['terms'], 'count'));

    return [
      'updated' => $formatter->format((int) $data['updated'], 'custom', 'F j, Y, g:i a'),
      'through' => $formatter->format((int) $data['updated'] - 86400, 'custom', 'F j'),
      'figures' => [
        $figure('Page views, new site', $sum(0, 'views', $palefire), $sum(1, 'views', $palefire)),
        $figure('Page views, old site', $sum(0, 'views', ['old']), $sum(1, 'views', ['old'])),
        $figure('Combined Searches', (int) $data['searches'][0], (int) $data['searches'][1]),
        $figure('Visitors, new site', $sum(0, 'users', $palefire), $sum(1, 'users', $palefire)),
      ],
      'sections' => $bars([
        ['name' => 'Special Collections', 'views' => $sum(0, 'views', ['special'])],
        ['name' => 'Rest of the library', 'views' => $sum(0, 'views', ['library'])],
        ['name' => 'Old site', 'views' => $sum(0, 'views', ['old'])],
      ], 'name', 'views'),
      'types' => $bars($data['types'], 'type', 'views', fn (string $type) => self::TYPES[$type] ?? $type),
      'pages' => array_map(fn ($page) => $page + ['views_text' => number_format($page['views'])], $data['pages']),
      'modes' => $bars($data['modes'], 'mode', 'count', fn (string $mode) => ['all' => 'All', '(not set)' => 'All', 'special' => 'Special Collections'][$mode] ?? ucfirst($mode)),
      'terms' => array_map(fn ($term) => $term + ['count_text' => number_format($term['count'])], $data['terms']),
      'terms_total' => $total_terms,
      'weeks' => $weeks,
    ];
  }

  /**
   * "special", "library", or "old" for a page with no Palefire label.
   */
  private static function section(string $value): string {
    return in_array($value, ['special', 'library'], TRUE) ? $value : 'old';
  }

  /**
   * "202645" as "Nov 2", the Monday the ISO week starts.
   */
  private static function weekLabel(string $week): string {
    if (!preg_match('/^(\d{4})(\d{2})$/', $week, $m)) {
      return $week;
    }
    $monday = (new \DateTimeImmutable())->setISODate((int) $m[1], (int) $m[2]);
    return $monday->format('M j');
  }

  /**
   * An access token for the service account in a JSON key file.
   */
  private static function token(string $key_file): string {
    $key = is_readable($key_file) ? json_decode((string) file_get_contents($key_file), TRUE) : NULL;
    if (!is_array($key) || empty($key['client_email']) || empty($key['private_key'])) {
      throw new \RuntimeException("Can't read a service account key at $key_file.");
    }
    $now = \Drupal::time()->getCurrentTime();
    $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b64(json_encode([
      'iss' => $key['client_email'],
      'scope' => self::SCOPE,
      'aud' => $key['token_uri'] ?? 'https://oauth2.googleapis.com/token',
      'iat' => $now,
      'exp' => $now + 3600,
    ]));
    if (!openssl_sign($unsigned, $signature, $key['private_key'], OPENSSL_ALGO_SHA256)) {
      throw new \RuntimeException("Can't sign with the key at $key_file.");
    }
    $response = \Drupal::httpClient()->post($key['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
      'form_params' => [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $unsigned . '.' . $b64($signature),
      ],
      'timeout' => 30,
    ]);
    $body = json_decode((string) $response->getBody(), TRUE);
    if (empty($body['access_token'])) {
      throw new \RuntimeException('Google gave no access token.');
    }
    return $body['access_token'];
  }

  /**
   * Runs up to five reports per request (batchRunReports' limit).
   */
  private static function run(string $token, array $requests): array {
    $reports = [];
    foreach (array_chunk($requests, 5) as $chunk) {
      $response = \Drupal::httpClient()->post(self::API . self::PROPERTY . ':batchRunReports', [
        'headers' => ['Authorization' => 'Bearer ' . $token],
        'json' => ['requests' => $chunk],
        'timeout' => 60,
      ]);
      $body = json_decode((string) $response->getBody(), TRUE);
      $reports = array_merge($reports, $body['reports'] ?? []);
    }
    if (count($reports) !== count($requests)) {
      throw new \RuntimeException('GA4 returned ' . count($reports) . ' of ' . count($requests) . ' reports.');
    }
    return $reports;
  }

  /**
   * A report's rows as ['d' => dimension values, 'm' => metric values].
   */
  private static function rows(array $report): array {
    return array_map(fn ($row) => [
      'd' => array_column($row['dimensionValues'] ?? [], 'value'),
      'm' => array_column($row['metricValues'] ?? [], 'value'),
    ], $report['rows'] ?? []);
  }

}
