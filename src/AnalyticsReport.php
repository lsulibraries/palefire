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
 * keeps working after /v2 is dropped. For now it counts the new site only:
 * pages without the labels (the old site) are left out while v2 is tested.
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
   * Labels for the data-pf-track names of controls (js/palefire.js).
   */
  private const CONTROLS = [
    'section-tabs' => 'Section tabs',
    'ask-tabs' => 'Ask Us tabs',
    'ask-form' => 'Ask Us form',
    'settings' => 'Reading settings',
    'dock' => 'Phone toolbar',
    'map' => 'Building map',
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
    // A GA4 event on new-site pages.
    $event = fn (string $name) => ['andGroup' => ['expressions' => [
      ['filter' => ['fieldName' => 'eventName', 'stringFilter' => ['value' => $name]]],
      $palefire,
    ]]];
    $most = fn (string $metric) => [['metric' => ['metricName' => $metric], 'desc' => TRUE]];

    $reports = self::run($token, [
      // 0: page views and users by section, this period and the one before.
      [
        'dateRanges' => [$now, $before],
        'dimensions' => [['name' => 'customEvent:pf_section']],
        'metrics' => [['name' => 'screenPageViews'], ['name' => 'activeUsers']],
        'dimensionFilter' => $palefire,
      ],
      // 1: page views by kind of page.
      [
        'dateRanges' => [$now],
        'dimensions' => [['name' => 'customEvent:pf_type']],
        'metrics' => [['name' => 'screenPageViews']],
        'dimensionFilter' => $palefire,
        'orderBys' => $most('screenPageViews'),
        'limit' => 12,
      ],
      // 2: the most viewed pages.
      [
        'dateRanges' => [$now],
        'dimensions' => [['name' => 'pagePath'], ['name' => 'pageTitle']],
        'metrics' => [['name' => 'screenPageViews']],
        'dimensionFilter' => $palefire,
        'orderBys' => $most('screenPageViews'),
        'limit' => 15,
      ],
      // 3: searches, this period and the one before.
      [
        'dateRanges' => [$now, $before],
        'metrics' => [['name' => 'eventCount']],
        'dimensionFilter' => $event('search'),
      ],
      // 4: what people searched for.
      [
        'dateRanges' => [$now],
        'dimensions' => [['name' => 'customEvent:search_term']],
        'metrics' => [['name' => 'eventCount']],
        'dimensionFilter' => $event('search'),
        'orderBys' => $most('eventCount'),
        'limit' => 25,
      ],
      // 5: searches by mode (All, Special Collections...).
      [
        'dateRanges' => [$now],
        'dimensions' => [['name' => 'customEvent:search_mode']],
        'metrics' => [['name' => 'eventCount']],
        'dimensionFilter' => $event('search'),
        'orderBys' => $most('eventCount'),
      ],
      // 6: page views each week, by section: twelve whole weeks from a
      // Monday, so the first bar isn't a partial week, then this week so far.
      [
        'dateRanges' => [['startDate' => (new \DateTimeImmutable('monday this week'))->modify('-12 weeks')->format('Y-m-d'), 'endDate' => 'yesterday']],
        'dimensions' => [['name' => 'isoYearIsoWeek'], ['name' => 'customEvent:pf_section']],
        'metrics' => [['name' => 'screenPageViews']],
        'dimensionFilter' => $palefire,
        'orderBys' => [['dimension' => ['dimensionName' => 'isoYearIsoWeek']]],
      ],
      // 7: searches that found nothing anywhere (js/index-search.js), with
      // the total beyond the 25 listed.
      [
        'dateRanges' => [$now],
        'dimensions' => [['name' => 'customEvent:search_term']],
        'metrics' => [['name' => 'eventCount']],
        'dimensionFilter' => $event('pf_search_empty'),
        'orderBys' => $most('eventCount'),
        'metricAggregations' => ['TOTAL'],
        'limit' => 25,
      ],
      // 8: the controls people use (data-pf-track, js/palefire.js).
      [
        'dateRanges' => [$now],
        'dimensions' => [['name' => 'customEvent:pf_component'], ['name' => 'customEvent:pf_action']],
        'metrics' => [['name' => 'eventCount']],
        'dimensionFilter' => $event('pf_interaction'),
        'orderBys' => $most('eventCount'),
        'limit' => 30,
      ],
      // 9: Combined Search results opened, by source.
      [
        'dateRanges' => [$now],
        'dimensions' => [['name' => 'customEvent:result_source']],
        'metrics' => [['name' => 'eventCount']],
        'dimensionFilter' => $event('pf_search_result'),
        'orderBys' => $most('eventCount'),
        'metricAggregations' => ['TOTAL'],
        'limit' => 12,
      ],
      // 10: ...and by place in their list.
      [
        'dateRanges' => [$now],
        'dimensions' => [['name' => 'customEvent:result_position']],
        'metrics' => [['name' => 'eventCount']],
        'dimensionFilter' => $event('pf_search_result'),
        'limit' => 100,
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
      'empty' => [],
      'empty_total' => self::total($reports[7]),
      'controls' => [],
      'sources' => [],
      'opened_total' => self::total($reports[9]),
      'positions' => [],
    ];
    foreach (self::rows($reports[0]) as $row) {
      $section = $row['d'][0];
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
      $data['weeks'][$row['d'][0]][$row['d'][1]] = ($data['weeks'][$row['d'][0]][$row['d'][1]] ?? 0) + (int) $row['m'][0];
    }
    foreach (self::rows($reports[7]) as $row) {
      $data['empty'][] = ['term' => $row['d'][0], 'count' => (int) $row['m'][0]];
    }
    foreach (self::rows($reports[8]) as $row) {
      $data['controls'][] = ['component' => $row['d'][0], 'action' => $row['d'][1], 'count' => (int) $row['m'][0]];
    }
    foreach (self::rows($reports[9]) as $row) {
      $data['sources'][] = ['source' => $row['d'][0], 'count' => (int) $row['m'][0]];
    }
    foreach (self::rows($reports[10]) as $row) {
      // The first ten places, then the rest together (11).
      $place = ctype_digit($row['d'][0]) && (int) $row['d'][0] >= 1 && (int) $row['d'][0] <= 10 ? (int) $row['d'][0] : 11;
      $data['positions'][$place] = ($data['positions'][$place] ?? 0) + (int) $row['m'][0];
    }
    ksort($data['positions']);
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
    $palefire = ['special', 'library'];
    $sum = fn (int $range, string $key, array $sections) => array_sum(array_map(fn ($s) => (int) ($data['sections'][$s][$range][$key] ?? 0), $sections));
    $figure = function (string $label, int $now, ?int $before = NULL): array {
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
    $counted = fn (array $rows) => array_map(fn ($row) => $row + ['count_text' => number_format((int) $row['count'])], $rows);

    $weeks = [];
    // The week the pull ran in is only partly counted (through yesterday).
    $pulled = (new \DateTimeImmutable('@' . (int) $data['updated']))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    $pulled_week = $pulled->modify('-1 day')->format('oW');
    foreach ($data['weeks'] as $week => $sections) {
      $weeks[] = [
        'current' => (string) $week === $pulled_week && $pulled->format('N') !== '1',
        'week' => self::weekLabel((string) $week),
        'special' => (int) ($sections['special'] ?? 0),
        'library' => (int) ($sections['library'] ?? 0),
      ];
    }
    $week_max = max(array_merge([1], array_map(fn ($w) => $w['special'] + $w['library'], $weeks)));
    foreach ($weeks as &$week) {
      $week['special_width'] = round($week['special'] / $week_max * 100, 1);
      $week['library_width'] = round($week['library'] / $week_max * 100, 1);
      $week['special'] = number_format($week['special']);
      $week['library'] = number_format($week['library']);
    }
    unset($week);
    // Weeks before the new site's first page view say nothing.
    while (count($weeks) > 1 && $weeks[0]['special_width'] + $weeks[0]['library_width'] == 0) {
      array_shift($weeks);
    }

    $positions = [];
    foreach ($data['positions'] ?? [] as $place => $count) {
      $positions[] = ['place' => $place > 10 ? 'Below 10th' : self::ordinal((int) $place), 'count' => $count];
    }
    $searches = (int) $data['searches'][0];
    $opened = (int) ($data['opened_total'] ?? 0);

    return [
      'updated' => $formatter->format((int) $data['updated'], 'custom', 'F j, Y, g:i a'),
      'through' => $formatter->format((int) $data['updated'] - 86400, 'custom', 'F j'),
      'figures' => [
        $figure('Page views', $sum(0, 'views', $palefire), $sum(1, 'views', $palefire)),
        $figure('Visitors', $sum(0, 'users', $palefire), $sum(1, 'users', $palefire)),
        $figure('Combined Searches', $searches, (int) $data['searches'][1]),
        $figure('Searches that found nothing', (int) ($data['empty_total'] ?? 0)),
      ],
      'sections' => $bars([
        ['name' => 'Special Collections', 'views' => $sum(0, 'views', ['special'])],
        ['name' => 'Rest of the library', 'views' => $sum(0, 'views', ['library'])],
      ], 'name', 'views'),
      'types' => $bars($data['types'], 'type', 'views', fn (string $type) => self::TYPES[$type] ?? $type),
      'pages' => array_map(fn ($page) => $page + ['views_text' => number_format($page['views'])], $data['pages']),
      'modes' => $bars($data['modes'], 'mode', 'count', fn (string $mode) => ['all' => 'All', '(not set)' => 'All', 'special' => 'Special Collections'][$mode] ?? ucfirst($mode)),
      'terms' => $counted($data['terms']),
      'empty' => $counted($data['empty'] ?? []),
      'controls' => $counted(array_map(fn ($row) => [
        'control' => self::CONTROLS[$row['component']] ?? $row['component'],
        'action' => $row['action'],
        'count' => $row['count'],
      ], $data['controls'] ?? [])),
      'opened' => number_format($opened),
      // Results opened per hundred searches: some open several, so it can
      // pass 100.
      'opened_share' => $searches > 0 ? (int) round($opened / $searches * 100) : NULL,
      'sources' => $bars($data['sources'] ?? [], 'source', 'count', fn (string $source) => ucfirst(str_replace('_', ' ', $source))),
      'positions' => $bars($positions, 'place', 'count'),
      'weeks' => $weeks,
    ];
  }

  /**
   * 1 as "1st", 2 as "2nd"...
   */
  private static function ordinal(int $n): string {
    $suffix = in_array($n % 100, [11, 12, 13], TRUE) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th');
    return $n . $suffix;
  }

  /**
   * A report's first metric over all its rows (metricAggregations TOTAL).
   */
  private static function total(array $report): int {
    return (int) ($report['totals'][0]['metricValues'][0]['value'] ?? 0);
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
