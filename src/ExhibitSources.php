<?php

declare(strict_types=1);

namespace Drupal\palefire;

use Drupal\Component\Utility\Html;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Link targets in an exhibition's Sources list (guide_text, Style: Sources).
 *
 * Each <li> gets id="src-<key>", the key being the text before the first
 * comma or full stop ("Bates, Henry Walter. 1863." gives src-bates),
 * transliterated and lowercased. A second "Bates" gets src-bates-2, and so
 * on. scripts/data/aves.json was made with the same rule, so its citation
 * links (#src-bates) land here; js/exhibition.js opens the Sources gallery.
 * An id the text already has is kept.
 */
final class ExhibitSources implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['addIds'];
  }

  /**
   * #post_render callback: adds the ids to the rendered list items.
   */
  public static function addIds(string $markup, array $element): string {
    if (!str_contains($markup, '<li')) {
      return $markup;
    }
    $dom = Html::load($markup);
    $used = [];
    $transliteration = \Drupal::transliteration();
    foreach ($dom->getElementsByTagName('li') as $li) {
      if ($li->hasAttribute('id')) {
        continue;
      }
      $text = trim(preg_replace('/\s+/u', ' ', $li->textContent));
      $lead = preg_split('/[,.]/u', $text, 2)[0] ?? '';
      $key = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($transliteration->transliterate($lead, 'en', ''))), '-') ?: 'source';
      $base = 'src-' . $key;
      $used[$base] = ($used[$base] ?? 0) + 1;
      $li->setAttribute('id', $used[$base] === 1 ? $base : $base . '-' . $used[$base]);
    }
    return Html::serialize($dom);
  }

}
