<?php

declare(strict_types=1);

namespace Drupal\palefire;

use Drupal\Core\Cache\Cache;
use Drupal\paragraphs\ParagraphInterface;

/**
 * What Endures, the Williams Center's podcast, from its SoundCloud feed.
 *
 * SoundCloud publishes the account's tracks as a public podcast feed (the
 * one podcast apps read): title, date, length, MP3, cover and description
 * for each, with no key. scripts/palefire_podcast_pull.php reads it nightly
 * (cron) into State; the Oral History homepage draws the episodes from
 * there: a Guide section whose text is "[podcast]" (includes/podcast.html.twig)
 * and, in the hero's column, the newest episode in place of the Podcast
 * banner (heroBanner()). Play uses the page's own player and listening bar
 * (js/podcast.js), so nothing loads from SoundCloud until Play.
 *
 * Episodes only: the staff introduction clips (about three minutes, named
 * for the speaker) are left out by length, and a track posted twice is kept
 * once.
 */
final class PodcastFeed {

  public const STATE = 'palefire.podcast';

  public const CACHE_TAG = 'palefire_podcast';

  public const FEED = 'https://feeds.soundcloud.com/users/soundcloud:users:124715622/sounds.rss';

  public const PROFILE = 'https://soundcloud.com/lsu-oralhistory';

  /**
   * A guide text paragraph that is only this draws the episodes.
   */
  public const MARKER = '#<p>\s*\[podcast\]\s*</p>#i';

  /**
   * Shorter than this (seconds) is a clip, not an episode.
   */
  private const MIN_LENGTH = 240;

  /**
   * Episodes kept in State, newest first.
   */
  private const KEEP = 40;

  /**
   * Reads the feed. Throws when it can't, so the last good pull stays.
   */
  public static function pull(): array {
    $response = \Drupal::httpClient()->get(self::FEED, [
      'timeout' => 30,
      'headers' => ['Accept' => 'application/rss+xml, application/xml'],
    ]);
    $previous = libxml_use_internal_errors(TRUE);
    $xml = simplexml_load_string((string) $response->getBody(), 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
    libxml_use_internal_errors($previous);
    if (!$xml || !isset($xml->channel)) {
      throw new \RuntimeException('The podcast feed is not RSS.');
    }
    $itunes = 'http://www.itunes.com/dtds/podcast-1.0.dtd';
    $show = self::cover((string) ($xml->channel->children($itunes)->image?->attributes()['href'] ?? ''));

    $episodes = [];
    $seen = [];
    foreach ($xml->channel->item as $item) {
      $it = $item->children($itunes);
      $length = self::seconds((string) $it->duration);
      $mp3 = (string) ($item->enclosure?->attributes()['url'] ?? '');
      $title = self::plain((string) $item->title);
      if ($length < self::MIN_LENGTH || !self::trusted($mp3) || $title === '') {
        continue;
      }
      $key = mb_strtolower($title) . '|' . $length;
      if (isset($seen[$key])) {
        continue;
      }
      $seen[$key] = TRUE;
      // "Episode 21 - Jazz Fest Oral History Project": the number apart.
      $number = NULL;
      if (preg_match('/^Episode\s+(\d+)\s*[-–—:]?\s*(.+)$/iu', $title, $m)) {
        $number = (int) $m[1];
        $title = trim($m[2]);
      }
      $link = (string) $item->link;
      $episodes[] = [
        'id' => substr(hash('sha256', $mp3), 0, 12),
        'title' => $title,
        'number' => $number,
        'date' => (int) strtotime((string) $item->pubDate),
        'length' => $length,
        'mp3' => $mp3,
        'cover' => self::cover((string) ($it->image?->attributes()['href'] ?? '')) ?: $show,
        'link' => str_starts_with($link, 'https://soundcloud.com/') ? $link : self::PROFILE,
        'summary' => self::summary((string) ($item->description ?? $it->summary)),
      ];
    }
    if (!$episodes) {
      throw new \RuntimeException('The podcast feed has no episodes.');
    }
    usort($episodes, static fn (array $a, array $b): int => $b['date'] <=> $a['date']);
    return ['updated' => time(), 'episodes' => array_slice($episodes, 0, self::KEEP)];
  }

  /**
   * Keeps a pull and redraws what shows it.
   */
  public static function save(array $data): void {
    \Drupal::state()->set(self::STATE, $data);
    Cache::invalidateTags([self::CACHE_TAG]);
  }

  /**
   * The section's variables, or NULL before the first pull.
   *
   * latest: the newest episode; more: the next ones; profile: the account.
   * Each episode as pull() keeps it, plus kicker ("Episode 21 · May 2025"),
   * time ("5:49") and duration (ISO 8601, for <time>).
   */
  public static function view(int $more = 5): ?array {
    $data = \Drupal::state()->get(self::STATE);
    if (!is_array($data) || empty($data['episodes'])) {
      return NULL;
    }
    $episodes = array_map([self::class, 'shown'], array_slice($data['episodes'], 0, $more + 1));
    return [
      'latest' => array_shift($episodes),
      'more' => $episodes,
      'profile' => self::PROFILE,
    ];
  }

  /**
   * The hero column's Podcast banner becomes the newest episode.
   *
   * On Oral History's homepage: the first banner whose title or address
   * says "podcast" (the Oral History Podcast tile) shows the latest episode,
   * its cover as the picture, linking to the episode list (#podcast), with
   * a Play button beside it (landing.html.twig). Without a pull, or with no
   * such banner, the banners stay as they are.
   */
  public static function heroBanner(array &$variables): void {
    $view = self::view(0);
    if (!$view || empty($variables['hero_banners'])) {
      return;
    }
    $variables['#cache']['tags'] = Cache::mergeTags($variables['#cache']['tags'] ?? [], [self::CACHE_TAG]);
    foreach ($variables['hero_banners'] as &$banner) {
      if (!preg_match('/podcast/i', ($banner['title'] ?? '') . ' ' . ($banner['url'] ?? ''))) {
        continue;
      }
      $episode = $view['latest'];
      $banner = [
        'title' => $episode['title'],
        'url' => '#podcast',
        'label' => t('Latest episode · @time', ['@time' => $episode['time']]),
        'image' => [
          '#theme' => 'image',
          '#uri' => $episode['cover'],
          '#width' => 500,
          '#height' => 500,
          '#alt' => '',
          '#attributes' => ['loading' => 'lazy', 'decoding' => 'async'],
        ],
        'episode' => $episode,
      ];
      break;
    }
    unset($banner);
  }

  /**
   * Whether a section is the podcast's: a Guide section with the marker.
   */
  public static function isSection(ParagraphInterface $paragraph): bool {
    if ($paragraph->bundle() !== 'guide_section' || !$paragraph->hasField('field_guide_blocks')) {
      return FALSE;
    }
    foreach ($paragraph->get('field_guide_blocks')->referencedEntities() as $block) {
      if ($block->hasField('field_block_body') && preg_match(self::MARKER, (string) $block->get('field_block_body')->value)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * An episode with its words for the page.
   */
  private static function shown(array $episode): array {
    $date = \Drupal::service('date.formatter')->format($episode['date'], 'custom', 'M Y');
    $episode['time'] = self::clock($episode['length']);
    $episode['duration'] = 'PT' . intdiv($episode['length'], 60) . 'M' . ($episode['length'] % 60) . 'S';
    $episode['kicker'] = $episode['number'] ? t('Episode @n · @date', ['@n' => $episode['number'], '@date' => $date]) : $date;
    return $episode;
  }

  /**
   * "00:05:49" or "5:49" as seconds.
   */
  private static function seconds(string $duration): int {
    $parts = array_map('intval', explode(':', trim($duration)));
    return (int) array_reduce($parts, static fn (int $sum, int $part): int => $sum * 60 + $part, 0);
  }

  /**
   * Seconds as "5:49" or "1:02:10".
   */
  private static function clock(int $seconds): string {
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    return $h ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
  }

  /**
   * The cover at 500px, on SoundCloud's image host only.
   */
  private static function cover(string $url): string {
    if (!preg_match('#^https://i\d\.sndcdn\.com/#', $url)) {
      return '';
    }
    return preg_replace('/-(original|t\d+x\d+)\.(jpe?g|png)$/i', '-t500x500.$2', $url);
  }

  /**
   * An MP3 from SoundCloud's feed host only.
   */
  private static function trusted(string $url): bool {
    return (bool) preg_match('#^https://feeds\.soundcloud\.com/stream/[\w.-]+\.mp3$#', $url);
  }

  /**
   * Feed text as plain text: entities decoded, tags and runs of space gone.
   */
  private static function plain(string $text): string {
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $text));
  }

  /**
   * The description's first sentences, up to about 220 characters.
   */
  private static function summary(string $text): string {
    $text = self::plain($text);
    if (mb_strlen($text) <= 220) {
      return $text;
    }
    $cut = mb_substr($text, 0, 220);
    $end = max((int) mb_strrpos($cut, '. '), (int) mb_strrpos($cut, '? '), (int) mb_strrpos($cut, '! '));
    return $end > 80 ? mb_substr($cut, 0, $end + 1) : rtrim(mb_substr($cut, 0, (int) mb_strrpos($cut, ' '))) . '…';
  }

}
