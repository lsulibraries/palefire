<?php

declare(strict_types=1);

namespace Drupal\palefire;

use Drupal\Component\Utility\Html;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Links in an FAQ answer imported from AskUs (_palefire_ask_faqs()).
 *
 * Most answers hold bare URLs, which the text format's URL filter links, so
 * this runs on the rendered answer, after the filters: a link repeated
 * straight after itself in brackets is dropped, and a link whose text is
 * its URL shows it without "https://", "www." or a final slash.
 */
final class AskAnswer implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['links'];
  }

  /**
   * #post_render callback: tidies the rendered answer's links.
   */
  public static function links(string $markup, array $element): string {
    if (!str_contains($markup, '<a')) {
      return $markup;
    }
    $markup = preg_replace('~(<a\b[^>]*\bhref="([^"]+)"[^>]*>.*?</a>)\s*\(<a\b[^>]*\bhref="\2"[^>]*>.*?</a>\)~isu', '$1', $markup);
    return preg_replace_callback('~(<a\b[^>]*\bhref="([^"]+)"[^>]*>)\s*(https?://[^<\s]+)\s*</a>~i', static function (array $m): string {
      if (rtrim(Html::decodeEntities($m[3]), '/') !== rtrim(Html::decodeEntities($m[2]), '/')) {
        return $m[0];
      }
      return $m[1] . preg_replace(['~^https?://(?:www\.)?~i', '~/$~'], '', $m[3]) . '</a>';
    }, $markup);
  }

}
