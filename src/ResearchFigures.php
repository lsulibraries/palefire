<?php

declare(strict_types=1);

namespace Drupal\palefire;

use Drupal\Core\Cache\Cache;

/**
 * LSU research figures from OpenAlex (scripts/palefire_research_pull.php).
 *
 * The page "LSU Research, by the Numbers" (scripts/palefire_research_dashboard.php)
 * presents Colton Ray's OpenAlex dashboard (lsuopenalex.lsusites.org). Its
 * figures are the same kind of counts, drawn by Palefire: each night a drush
 * script asks the OpenAlex API for a handful of grouped counts (about ten
 * requests, $0.0001 each) and keeps them in Drupal's State. A guide text
 * paragraph that is only "[research:<part>]"
 * (palefire_preprocess_paragraph__guide_text()) draws one part
 * (includes/research-figures.html.twig) with the Figures, Progress chart and
 * guide table styles, so it adds no CSS or JS and visitors' browsers never
 * call OpenAlex.
 *
 * Works counted: at least one author linked to LSU (OpenAlex I121820613,
 * ROR 05ect4e57), published from January 1, 2020 through today, as on the
 * dashboard. No authors' names: a public ranking of colleagues is the
 * dashboard's to make, with its own context.
 */
final class ResearchFigures {

  public const STATE = 'palefire.research_figures';

  public const CACHE_TAG = 'palefire_research_figures';

  public const INSTITUTION = 'I121820613';

  public const FROM_YEAR = 2020;

  public const DASHBOARD = 'https://lsuopenalex.lsusites.org/';

  /**
   * The parts a "[research:<part>]" marker can draw.
   */
  public const PARTS = ['glance', 'years', 'access', 'kinds', 'journals', 'publishers', 'funders', 'topics', 'cited'];

  private const API = 'https://api.openalex.org/';

  /**
   * Open access statuses, most open first, in plain words.
   */
  private const ACCESS = [
    'diamond' => 'Diamond: open journal, no author fee',
    'gold' => 'Gold: open access journal',
    'hybrid' => 'Hybrid: open in a subscription journal',
    'green' => 'Green: free copy in a repository',
    'bronze' => 'Bronze: free on the publisher’s site, no open license',
    'closed' => 'Closed: subscription only',
  ];

  /**
   * Kinds of work, in plain words; anything else is shown as sent.
   */
  private const KINDS = [
    'article' => 'Articles',
    'review' => 'Reviews',
    'book-chapter' => 'Book chapters',
    'book' => 'Books',
    'dissertation' => 'Dissertations and theses',
    'conference-paper' => 'Conference papers',
    'conference-abstract' => 'Conference abstracts',
    'preprint' => 'Preprints',
    'dataset' => 'Datasets',
    'letter' => 'Letters',
    'editorial' => 'Editorials',
    'erratum' => 'Corrections',
    'report' => 'Reports',
    'other' => 'Other',
  ];

  /**
   * Asks OpenAlex for everything the page shows.
   *
   * Throws on any failed request, so the last good pull stays in State.
   *
   * @param string|null $api_key
   *   An OpenAlex API key, or NULL to use the free shared budget.
   */
  public static function pull(?string $api_key = NULL): array {
    $today = date('Y-m-d');
    $works = sprintf('institutions.id:%s,from_publication_date:%d-01-01,to_publication_date:%s', self::INSTITUTION, self::FROM_YEAR, $today);
    $get = function (string $path, array $query) use ($api_key): array {
      if ($api_key) {
        $query['api_key'] = $api_key;
      }
      $response = \Drupal::httpClient()->get(self::API . $path, [
        'query' => $query,
        'timeout' => 30,
        'headers' => ['Accept' => 'application/json'],
      ]);
      $body = json_decode((string) $response->getBody(), TRUE, 64, JSON_THROW_ON_ERROR);
      if (!is_array($body)) {
        throw new \RuntimeException("OpenAlex sent no data for $path.");
      }
      return $body;
    };
    // One grouped count: [display name => count], largest first.
    $group = function (string $by, string $filter = '') use ($get, $works): array {
      $body = $get('works', ['filter' => $works . $filter, 'group_by' => $by]);
      $rows = [];
      foreach ($body['group_by'] ?? [] as $row) {
        $name = self::plain((string) ($row['key_display_name'] ?? ''));
        if ($name !== '' && strtolower($name) !== 'unknown') {
          $rows[$name] = ($rows[$name] ?? 0) + (int) ($row['count'] ?? 0);
        }
      }
      arsort($rows);
      return $rows;
    };

    $body = $get('works', ['filter' => $works, 'group_by' => 'publication_year']);
    $years = [];
    foreach ($body['group_by'] ?? [] as $row) {
      $years[(int) $row['key']] = (int) $row['count'];
    }
    ksort($years);
    $total = (int) ($body['meta']['count'] ?? array_sum($years));
    if ($total < 1) {
      throw new \RuntimeException('OpenAlex counted no LSU works. Nothing kept.');
    }

    $cited = [];
    $top = $get('works', [
      'filter' => $works,
      'sort' => 'cited_by_count:desc',
      'per_page' => 10,
      'select' => 'id,doi,display_name,publication_year,cited_by_count,primary_location',
    ]);
    foreach ($top['results'] ?? [] as $work) {
      $cited[] = [
        'title' => self::plain((string) ($work['display_name'] ?? '')),
        'url' => (string) (($work['doi'] ?? '') ?: ($work['id'] ?? '')),
        'year' => (int) ($work['publication_year'] ?? 0),
        'venue' => self::plain((string) ($work['primary_location']['source']['display_name'] ?? '')),
        'citations' => (int) ($work['cited_by_count'] ?? 0),
      ];
    }

    $institution = $get('institutions/' . self::INSTITUTION, ['select' => 'counts_by_year']);
    $citations = [];
    foreach ($institution['counts_by_year'] ?? [] as $row) {
      $citations[(int) $row['year']] = (int) $row['cited_by_count'];
    }

    return [
      'updated' => \Drupal::time()->getRequestTime(),
      'total' => $total,
      'years' => $years,
      'access' => $group('open_access.oa_status'),
      'kinds' => $group('type'),
      'journals' => array_slice($group('primary_location.source.id', ',primary_location.source.type:journal'), 0, 10, TRUE),
      'publishers' => array_slice($group('primary_location.source.publisher_lineage'), 0, 8, TRUE),
      'funders' => array_slice($group('funders.id'), 0, 10, TRUE),
      'topics' => array_slice($group('primary_topic.id'), 0, 10, TRUE),
      'cited' => $cited,
      'citations' => $citations,
    ];
  }

  /**
   * Keeps a pull and redraws the parts.
   */
  public static function save(array $data): void {
    \Drupal::state()->set(self::STATE, $data);
    Cache::invalidateTags([self::CACHE_TAG]);
  }

  /**
   * The template's variables for one part, or NULL before the first pull.
   */
  public static function view(string $part): ?array {
    $data = \Drupal::state()->get(self::STATE);
    if (!is_array($data) || empty($data['total']) || !in_array($part, self::PARTS, TRUE)) {
      return NULL;
    }
    $year = (int) date('Y');
    $view = [
      'updated' => \Drupal::service('date.formatter')->format((int) $data['updated'], 'custom', 'F j, Y'),
      'from' => self::FROM_YEAR,
    ];
    switch ($part) {
      case 'glance':
        $closed = (int) ($data['access']['closed'] ?? 0);
        $last = $year - 1;
        $view['figures'] = [
          ['label' => t('Works with an LSU author since @year', ['@year' => self::FROM_YEAR]), 'value' => number_format($data['total'])],
          ['label' => t('Free to read (open access)'), 'value' => round(($data['total'] - $closed) / $data['total'] * 100) . '%'],
          ['label' => t('New works in @year', ['@year' => $last]), 'value' => number_format($data['years'][$last] ?? 0)],
        ];
        if (!empty($data['citations'][$last])) {
          $view['figures'][] = ['label' => t('Citations to LSU research in @year', ['@year' => $last]), 'value' => self::short($data['citations'][$last])];
        }
        break;

      case 'years':
        $rows = [];
        foreach ($data['years'] as $y => $count) {
          $rows[] = [$y == $year ? t('@year, so far', ['@year' => $y]) : (string) $y, $count];
        }
        $view['rows'] = self::bars($rows);
        break;

      case 'access':
        $rows = [];
        foreach (self::ACCESS as $key => $label) {
          if (isset($data['access'][$key])) {
            $rows[] = [$label, $data['access'][$key]];
          }
        }
        $view['rows'] = self::bars($rows, $data['total']);
        break;

      case 'kinds':
        $rows = [];
        foreach (array_slice($data['kinds'], 0, 8, TRUE) as $kind => $count) {
          $rows[] = [self::KINDS[$kind] ?? ucfirst(str_replace('-', ' ', (string) $kind)), $count];
        }
        $view['rows'] = self::bars($rows);
        break;

      case 'journals':
      case 'publishers':
      case 'funders':
      case 'topics':
        $view['rows'] = self::bars(array_map(fn ($name, $count) => [(string) $name, $count], array_keys($data[$part]), $data[$part]));
        break;

      case 'cited':
        $view['works'] = array_map(fn (array $work) => $work + ['count' => number_format($work['citations'])], $data['cited']);
        break;
    }
    return $view;
  }

  /**
   * One figure for an exhibition title page's key facts.
   *
   * @param string $name
   *   works (since FROM_YEAR), open (share free to read), recent (works last
   *   year) or citations (to LSU research last year).
   *
   * @return string|null
   *   The figure, or NULL before the first pull or for an unknown name.
   */
  public static function fact(string $name): ?string {
    $data = \Drupal::state()->get(self::STATE);
    if (!is_array($data) || empty($data['total'])) {
      return NULL;
    }
    $last = (int) date('Y') - 1;
    return match ($name) {
      'works' => number_format($data['total']),
      'open' => round(($data['total'] - (int) ($data['access']['closed'] ?? 0)) / $data['total'] * 100) . '%',
      'recent' => isset($data['years'][$last]) ? number_format($data['years'][$last]) : NULL,
      'citations' => isset($data['citations'][$last]) ? self::short($data['citations'][$last]) : NULL,
      default => NULL,
    };
  }

  /**
   * A key fact line with its "[research:<name>]" markers filled in; "{year}"
   * becomes last year. NULL when any of its figures is missing.
   */
  public static function facts(string $line): ?string {
    $missing = FALSE;
    $line = preg_replace_callback('/\[research:([a-z]+)\]/', function (array $m) use (&$missing): string {
      $value = self::fact($m[1]);
      $missing = $missing || $value === NULL;
      return (string) $value;
    }, $line);
    return $missing ? NULL : str_replace('{year}', (string) ((int) date('Y') - 1), $line);
  }

  /**
   * Bar rows from [label, count] pairs: the label, the count in words, and a
   * width (of the largest, or of $whole when given, so shares read true).
   */
  private static function bars(array $rows, ?int $whole = NULL): array {
    $max = $whole ?: max(array_merge([1], array_column($rows, 1)));
    $out = [];
    foreach ($rows as [$label, $count]) {
      $out[] = [
        'label' => (string) $label,
        'value' => $whole ? t('@count (@percent%)', ['@count' => number_format($count), '@percent' => round($count / $whole * 100)]) : number_format($count),
        'width' => round($count / $max * 100, 1),
      ];
    }
    return $out;
  }

  /**
   * 284,793 as "285k": a figure reads at a glance.
   */
  private static function short(int $n): string {
    return $n >= 10000 ? round($n / 1000) . 'k' : number_format($n);
  }

  /**
   * OpenAlex names can carry markup (<i>, <sub>) and entities, and some
   * sources a doubled name ("Physical review. D/Physical review. D.").
   */
  private static function plain(string $text): string {
    $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $halves = explode('/', $text);
    if (count($halves) === 2 && $halves[0] !== '' && strcasecmp(rtrim($halves[0], '. '), rtrim($halves[1], '. ')) === 0) {
      $text = rtrim($halves[0]);
    }
    return $text;
  }

}
