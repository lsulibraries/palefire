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
 * script asks the OpenAlex API for grouped counts and a few short lists
 * (about 65 requests, $0.0001 each, well inside the free daily $0.10) and
 * keeps them in Drupal's State. A guide text
 * paragraph that is only "[research:<part>]"
 * (palefire_preprocess_paragraph__guide_text()) draws one part
 * (includes/research-figures.html.twig) with the Figures, Progress chart and
 * guide table styles, so it adds no CSS or JS and visitors' browsers never
 * call OpenAlex.
 *
 * Works counted: at least one author linked to LSU (OpenAlex I121820613,
 * ROR 05ect4e57), published from January 1, 2020 through today, as on the
 * dashboard. Top authors are those OpenAlex lists at LSU since 2020, as the
 * dashboard does.
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
  public const PARTS = ['glance', 'years', 'access', 'kinds', 'journals', 'publishers', 'funders', 'topics', 'authors', 'apc', 'cited', 'dashboard'];

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
        // A long author list is a few MB.
        'timeout' => 60,
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
    // Works each year for one more filter: [year => count], oldest first.
    $by_year = function (string $filter) use ($get, $works): array {
      $body = $get('works', ['filter' => $works . $filter, 'group_by' => 'publication_year']);
      $rows = [];
      foreach ($body['group_by'] ?? [] as $row) {
        $rows[(int) $row['key']] = (int) $row['count'];
      }
      ksort($rows);
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
      'per_page' => 20,
      'select' => 'id,doi,display_name,publication_year,cited_by_count,primary_location,open_access',
    ]);
    foreach ($top['results'] ?? [] as $work) {
      // The byline: the first two authors and how many there are, asked for
      // one work at a time, as a collaboration's list runs to thousands of
      // names (a few MB). Only those three values are kept. A failure leaves
      // the work without a byline rather than losing the pull.
      $authors = [];
      $author_count = 0;
      try {
        $one = $get('works/' . basename((string) ($work['id'] ?? '')), ['select' => 'authorships']);
        $author_count = count($one['authorships'] ?? []);
        foreach (array_slice($one['authorships'] ?? [], 0, 2) as $authorship) {
          $name = self::plain((string) ($authorship['author']['display_name'] ?? ''));
          if ($name !== '') {
            $authors[] = $name;
          }
        }
        unset($one);
      }
      catch (\Throwable $e) {
        $authors = [];
      }
      $doi = (string) ($work['doi'] ?? '');
      $cited[] = [
        'title' => self::plain((string) ($work['display_name'] ?? '')),
        'url' => $doi ?: (string) ($work['id'] ?? ''),
        'doi' => $doi ? preg_replace('#^https?://(dx\.)?doi\.org/#i', '', $doi) : '',
        'year' => (int) ($work['publication_year'] ?? 0),
        'venue' => self::plain((string) ($work['primary_location']['source']['display_name'] ?? '')),
        'citations' => (int) ($work['cited_by_count'] ?? 0),
        'access' => (string) ($work['open_access']['oa_status'] ?? ''),
        'authors' => $authors,
        'author_count' => $author_count,
      ];
    }

    $institution = $get('institutions/' . self::INSTITUTION, ['select' => 'counts_by_year']);
    $citations = [];
    foreach ($institution['counts_by_year'] ?? [] as $row) {
      $citations[(int) $row['year']] = (int) $row['cited_by_count'];
    }

    // Each year's works by open access status: one count per status.
    $access_years = [];
    foreach (array_keys(self::ACCESS) as $status) {
      $access_years[$status] = $by_year(",open_access.oa_status:$status");
    }

    // The top five publishers' works each year. A publisher counts its
    // imprints (publisher_lineage), as in the Top publishers panel.
    $publisher_years = [];
    $body = $get('works', ['filter' => $works, 'group_by' => 'primary_location.source.publisher_lineage']);
    foreach (array_slice($body['group_by'] ?? [], 0, 5) as $row) {
      $name = self::plain((string) ($row['key_display_name'] ?? ''));
      $id = basename((string) ($row['key'] ?? ''));
      if ($name !== '' && preg_match('/^P\d+$/', $id)) {
        $publisher_years[$name] = $by_year(",primary_location.source.publisher_lineage:$id");
      }
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
      'cited_total' => self::citedTotal($get, $works, $total),
      'access_years' => $access_years,
      'publisher_years' => $publisher_years,
      'authors' => self::authors($get, $works),
      'recent' => self::recent($get, $works),
      'apc' => self::apc($get, $works, array_keys($years)),
    ];
  }

  /**
   * Estimated publishing fees (APCs) each year, for works whose
   * corresponding author is at LSU: the institution that usually pays.
   *
   * Each work's apc_paid is the fee reported to OpenAPC, or else the
   * journal's list price. OpenAlex has no sum, so a year's works are grouped
   * by fee and each fee times its works is added up. A grouped count stops at
   * 200 fees; a range that hits that is split in two until every work is
   * counted. A year that still does not add up is left out (NULL).
   *
   * @return array
   *   [year => ['usd' => total, 'works' => works with a fee, 'of' => works
   *   with an LSU corresponding author, or NULL]].
   */
  private static function apc(callable $get, string $works, array $years): array {
    $scope = $works . ',corresponding_institution_ids:' . self::INSTITUTION;
    $body = $get('works', ['filter' => $scope, 'group_by' => 'publication_year']);
    $of = [];
    foreach ($body['group_by'] ?? [] as $row) {
      $of[(int) $row['key']] = (int) $row['count'];
    }

    // One fee range: [sum, works] when complete, NULL when it can't be.
    $range = function (int $year, int $low, int $high, int $depth) use (&$range, $get, $scope): ?array {
      $body = $get('works', [
        'filter' => "$scope,publication_year:$year,apc_paid.value_usd:>" . ($low - 1) . ",apc_paid.value_usd:<" . ($high + 1),
        'group_by' => 'apc_paid.value_usd',
        'per_page' => 200,
      ]);
      $sum = $counted = 0;
      foreach ($body['group_by'] ?? [] as $row) {
        if (is_numeric($row['key'] ?? NULL)) {
          $sum += (int) round((float) $row['key']) * (int) $row['count'];
          $counted += (int) $row['count'];
        }
      }
      if ($counted === (int) ($body['meta']['count'] ?? -1)) {
        return [$sum, $counted];
      }
      if ($depth >= 4 || $high - $low < 2) {
        return NULL;
      }
      $middle = intdiv($low + $high, 2);
      $lower = $range($year, $low, $middle, $depth + 1);
      $upper = $lower ? $range($year, $middle + 1, $high, $depth + 1) : NULL;
      return $lower && $upper ? [$lower[0] + $upper[0], $lower[1] + $upper[1]] : NULL;
    };

    $out = [];
    foreach ($years as $year) {
      // Fees run to about $13,000; split at $2,000 from the start, as most
      // fall below it.
      $low = $range((int) $year, 1, 1999, 1);
      $high = $low ? $range((int) $year, 2000, 100000, 1) : NULL;
      $out[(int) $year] = $low && $high
        ? ['usd' => $low[0] + $high[0], 'works' => $low[1] + $high[1], 'of' => $of[(int) $year] ?? 0]
        : NULL;
    }
    return $out;
  }

  /**
   * Every citation to the works counted, without fetching 30,000 works.
   *
   * One grouped count by citation count gives up to 200 groups, the lowest
   * counts, and each group's number of works; the few works cited more than
   * the highest group are then fetched (just their counts, 200 a page). The
   * groups and the rest must add up to the total, or the figure is left out
   * (NULL) rather than shown short.
   */
  private static function citedTotal(callable $get, string $works, int $total): ?int {
    $body = $get('works', ['filter' => $works, 'group_by' => 'cited_by_count', 'per_page' => 200]);
    $sum = $counted = 0;
    $top = -1;
    foreach ($body['group_by'] ?? [] as $row) {
      $sum += (int) $row['key'] * (int) $row['count'];
      $counted += (int) $row['count'];
      $top = max($top, (int) $row['key']);
    }
    if ($top < 0) {
      return NULL;
    }
    for ($page = 1; $counted < $total && $page <= 10; $page++) {
      $rest = $get('works', [
        'filter' => "$works,cited_by_count:>$top",
        'select' => 'cited_by_count',
        'per_page' => 200,
        'page' => $page,
      ]);
      if (empty($rest['results'])) {
        break;
      }
      foreach ($rest['results'] as $work) {
        $sum += (int) ($work['cited_by_count'] ?? 0);
        $counted++;
      }
    }
    return $counted === $total ? $sum : NULL;
  }

  /**
   * The ten people who appear on the most works counted, LSU people only.
   *
   * OpenAlex counts every author on a work, so a collaboration's outside
   * members rank too. The top 60 are looked up, and only those OpenAlex lists
   * at LSU in FROM_YEAR or later are kept: [name => works].
   */
  private static function authors(callable $get, string $works): array {
    $body = $get('works', ['filter' => $works, 'group_by' => 'authorships.author.id', 'per_page' => 60]);
    $counts = [];
    foreach ($body['group_by'] ?? [] as $row) {
      $id = basename((string) ($row['key'] ?? ''));
      if (preg_match('/^A\d+$/', $id)) {
        $counts[$id] = (int) $row['count'];
      }
    }
    if (!$counts) {
      return [];
    }
    $found = $get('authors', [
      'filter' => 'openalex:' . implode('|', array_keys($counts)),
      'select' => 'id,display_name,affiliations',
      'per_page' => 60,
    ]);
    $lsu = [];
    foreach ($found['results'] ?? [] as $author) {
      foreach ($author['affiliations'] ?? [] as $affiliation) {
        if (str_ends_with((string) ($affiliation['institution']['id'] ?? ''), self::INSTITUTION)
          && max(array_merge([0], $affiliation['years'] ?? [])) >= self::FROM_YEAR) {
          $lsu[basename((string) $author['id'])] = self::plain((string) ($author['display_name'] ?? ''));
          break;
        }
      }
    }
    $out = [];
    foreach ($counts as $id => $count) {
      if (!empty($lsu[$id]) && count($out) < 10) {
        $out[$lsu[$id]] = $count;
      }
    }
    return $out;
  }

  /**
   * The twelve latest works, leaving out corrections and front matter.
   */
  private static function recent(callable $get, string $works): array {
    $body = $get('works', [
      'filter' => "$works,type:!erratum,type:!paratext,type:!retraction",
      'sort' => 'publication_date:desc',
      'per_page' => 12,
      'select' => 'id,doi,display_name,publication_date,open_access,primary_location',
    ]);
    $out = [];
    foreach ($body['results'] ?? [] as $work) {
      $out[] = [
        'title' => self::plain((string) ($work['display_name'] ?? '')),
        'url' => (string) (($work['doi'] ?? '') ?: ($work['id'] ?? '')),
        'date' => (string) ($work['publication_date'] ?? ''),
        'venue' => self::plain((string) ($work['primary_location']['source']['display_name'] ?? '')),
        'access' => (string) ($work['open_access']['oa_status'] ?? ''),
      ];
    }
    return $out;
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
        ];
        // Citations to those works, and per work (pulls from 2026-11-13 on).
        if (!empty($data['cited_total'])) {
          $view['figures'][] = ['label' => t('Citations to those works'), 'value' => number_format($data['cited_total'])];
          $view['figures'][] = ['label' => t('Citations per work, on average'), 'value' => number_format($data['cited_total'] / $data['total'], 1)];
        }
        $view['figures'][] = ['label' => t('Free to read (open access)'), 'value' => round(($data['total'] - $closed) / $data['total'] * 100) . '%'];
        $view['figures'][] = ['label' => t('New works in @year', ['@year' => $last]), 'value' => number_format($data['years'][$last] ?? 0)];
        if (!empty($data['citations'][$last])) {
          $view['figures'][] = ['label' => t('Citations to LSU research in @year', ['@year' => $last]), 'value' => self::short($data['citations'][$last])];
        }
        break;

      case 'years':
        $rows = $marks = [];
        foreach ($data['years'] as $y => $count) {
          $rows[] = [$y == $year ? t('@year, so far', ['@year' => $y]) : (string) $y, $count];
          $marks[] = ['year' => (int) $y, 'current' => $y == $year];
        }
        // year and current: the dashboard's columns print the year alone and
        // mark the year in progress.
        $view['rows'] = array_map(fn ($row, $mark) => $row + $mark, self::bars($rows), $marks);
        break;

      case 'access':
        $rows = $keys = [];
        foreach (self::ACCESS as $key => $label) {
          if (isset($data['access'][$key])) {
            $rows[] = [$label, $data['access'][$key]];
            $keys[] = $key;
          }
        }
        // Each row's key names its tone in the dashboard's stacked bar.
        // tip: the segment's own readout on hover or tap ("Gold · 6,090 (18%)").
        $view['rows'] = array_map(fn ($row, $key) => $row + ['key' => $key, 'tip' => ucfirst($key) . ' · ' . $row['value']], self::bars($rows, $data['total']), $keys);
        break;

      case 'dashboard':
        // Every part at once, for the dashboard grid.
        foreach (array_diff(self::PARTS, ['dashboard']) as $each) {
          $view[$each] = self::view($each);
        }
        $view += self::trends($data, $year);
        break;

      case 'apc':
        $rows = $marks = [];
        $usd = $fee_works = $of = 0;
        foreach ($data['apc'] ?? [] as $y => $row) {
          if (!$row) {
            continue;
          }
          $rows[] = [(string) $y, $row['usd']];
          $marks[] = [
            'year' => (int) $y,
            'current' => $y == $year,
            'money' => self::money($row['usd']),
            'text' => (string) t('@year: about @money in fees on @works of @of works', ['@year' => $y, '@money' => self::money($row['usd']), '@works' => number_format($row['works']), '@of' => number_format($row['of'])]),
          ];
          $usd += $row['usd'];
          $fee_works += $row['works'];
          $of += $row['of'];
        }
        $view['rows'] = array_map(fn ($row, $mark) => $row + $mark, self::bars($rows), $marks);
        $view['total'] = self::money($usd);
        $view['works'] = number_format($fee_works);
        $view['of'] = number_format($of);
        break;

      case 'authors':
        $view['rows'] = self::bars(array_map(fn ($name, $count) => [(string) $name, $count], array_keys($data['authors'] ?? []), $data['authors'] ?? []));
        break;

      case 'kinds':
        $rows = [];
        foreach (array_slice($data['kinds'], 0, 8, TRUE) as $kind => $count) {
          $rows[] = [self::KINDS[$kind] ?? ucfirst(str_replace('-', ' ', (string) $kind)), $count];
        }
        // Shares of every work, as the old dashboard labeled them.
        $view['rows'] = self::bars($rows, (int) $data['total']);
        break;

      case 'journals':
      case 'publishers':
      case 'funders':
      case 'topics':
        $view['rows'] = self::bars(array_map(fn ($name, $count) => [(string) $name, $count], array_keys($data[$part]), $data[$part]));
        break;

      case 'cited':
        $top = max(array_merge([1], array_column($data['cited'], 'citations')));
        $view['works'] = array_map(fn (array $work) => $work + [
          'count' => number_format($work['citations']),
          'share' => round($work['citations'] / $top * 100, 1),
          'byline' => self::byline($work['authors'] ?? [], (int) ($work['author_count'] ?? 0)),
          'access_label' => self::accessLabel($work['access'] ?? ''),
        ], $data['cited']);
        break;
    }
    return $view;
  }

  /**
   * The dashboard's own parts, from pulls since 2026-11-13 (empty before).
   *
   * - opentrend: each year as one stacked bar of open access statuses, its
   *   segments as shares of that year, and the share open in words.
   * - publishertrend: the top publishers' works each year, a table; each
   *   cell's share is of that publisher's best year (its shading).
   * - recent: the latest works, dated, with venue and access in words.
   */
  private static function trends(array $data, int $year): array {
    $out = ['opentrend' => [], 'publishertrend' => [], 'recent' => []];
    $years = array_keys($data['years']);

    foreach ($years as $y) {
      $whole = 0;
      foreach (array_keys(self::ACCESS) as $key) {
        $whole += (int) ($data['access_years'][$key][$y] ?? 0);
      }
      if ($whole < 1) {
        continue;
      }
      $segments = [];
      foreach (array_keys(self::ACCESS) as $key) {
        if (!empty($data['access_years'][$key][$y])) {
          $count = (int) $data['access_years'][$key][$y];
          $segments[] = [
            'key' => $key,
            'width' => round($count / $whole * 100, 1),
            // text for the row's spoken breakdown; tip, with the year, for
            // the readout, which floats over the row above.
            'text' => (string) t('@status @count (@percent%)', ['@status' => ucfirst($key), '@count' => number_format($count), '@percent' => round($count / $whole * 100)]),
            'tip' => (string) t('@year · @status · @count (@percent%)', ['@year' => $y, '@status' => ucfirst($key), '@count' => number_format($count), '@percent' => round($count / $whole * 100)]),
          ];
        }
      }
      $closed = (int) ($data['access_years']['closed'][$y] ?? 0);
      $out['opentrend'][] = [
        'year' => $y,
        'current' => $y == $year,
        'segments' => $segments,
        'open' => round(($whole - $closed) / $whole * 100) . '%',
      ];
    }

    if (!empty($data['publisher_years'])) {
      $out['publishertrend'] = ['years' => array_map(fn ($y) => ['year' => $y, 'current' => $y == $year], $years), 'rows' => []];
      foreach ($data['publisher_years'] as $name => $counts) {
        $best = max(array_merge([1], $counts));
        $out['publishertrend']['rows'][] = [
          'name' => (string) $name,
          'cells' => array_map(fn ($y) => [
            'value' => number_format($counts[$y] ?? 0),
            'share' => round(($counts[$y] ?? 0) / $best * 100),
          ], $years),
        ];
      }
    }

    foreach ($data['recent'] ?? [] as $work) {
      $time = strtotime($work['date'] . ' 12:00');
      $out['recent'][] = $work + [
        'when' => $time ? date('M j, Y', $time) : '',
        'access_label' => self::accessLabel($work['access']),
      ];
    }
    return $out;
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
   * "Gold open access", "Subscription only", or '' when unknown.
   */
  private static function accessLabel(string $status): string {
    return match ($status) {
      'closed' => (string) t('Subscription only'),
      '' => '',
      default => (string) t('@status open access', ['@status' => ucfirst($status)]),
    };
  }

  /**
   * "Smith", "Smith and Jones", or "Smith et al." (three or more), as in a
   * citation. '' when the pull had no authors for the work.
   */
  private static function byline(array $authors, int $count): string {
    if (!$authors) {
      return '';
    }
    if ($count > 2) {
      return (string) t('@first et al.', ['@first' => $authors[0]]);
    }
    return count($authors) === 2
      ? (string) t('@first and @second', ['@first' => $authors[0], '@second' => $authors[1]])
      : $authors[0];
  }

  /**
   * Dollars at a glance: $1.6M, $850k, $9,200.
   */
  private static function money(int $usd): string {
    if ($usd >= 1000000) {
      return '$' . number_format($usd / 1000000, 1) . 'M';
    }
    return $usd >= 10000 ? '$' . round($usd / 1000) . 'k' : '$' . number_format($usd);
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
