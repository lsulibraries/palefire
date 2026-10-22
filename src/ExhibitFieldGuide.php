<?php

declare(strict_types=1);

namespace Drupal\palefire;

use Drupal\Component\Utility\Html;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Field guide links in an exhibition's text (guide_text in an entry).
 *
 * A paragraph that is only a link to a bird in the National Audubon
 * Society's field guide ("View bird in <a>National Audubon Society Guide
 * …</a>.") becomes a ruled row: a kicker, the bird's common name and its
 * scientific name, and an arrow (_exhibition.scss, .pf-exhibit-fieldguide).
 * The common name is the one the link's address spells: the entry's title
 * or a phrase in its caption when one matches it ("Black-bellied Plover"),
 * else the address's words, hyphens and possessives put back (Audubon's
 * titles are often the old names: "Indigo Bird" is the Indigo Bunting).
 * The scientific name is the current one in the entry's figure caption
 * ("[now Setophaga magnolia]"), else its first italic two-word name. js/exhibition.js opens it in a new tab, as every
 * outside link in an exhibition.
 */
final class ExhibitFieldGuide implements TrustedCallbackInterface {

  /**
   * The paragraph to replace: a link to the field guide and nothing else
   * but a few words before it and the full stop after it.
   */
  private const PATTERN = '#<p>[^<]{0,40}<a href="(https?://(?:www\.)?audubon\.org/field-guide/bird/([a-z0-9-]+))"[^>]*>[^<]*</a>[\s.]*</p>#i';

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['rows'];
  }

  /**
   * Whether the text has a field guide link (so the callback is worth it).
   */
  public static function applies(string $text): bool {
    return str_contains($text, 'audubon.org/field-guide/bird/');
  }

  /**
   * The names to show, from the text's entry: [the title and the caption's
   * phrases (candidates for the common name), the scientific name, the
   * cache tags of the paragraphs they come from].
   */
  public static function names(ParagraphInterface $text): array {
    $entry = $text->getParentEntity();
    if (!$entry instanceof ParagraphInterface || $entry->bundle() !== 'exhibit_entry') {
      return [[], '', []];
    }
    $tags = $entry->getCacheTags();
    $phrases = [trim((string) ($entry->get('field_block_title')->value ?? ''))];
    $latin = '';
    foreach ($entry->get('field_guide_blocks')->referencedEntities() as $block) {
      $figures = $block->bundle() === 'exhibit_gallery' && $block->hasField('field_guide_blocks')
        ? $block->get('field_guide_blocks')->referencedEntities()
        : [$block];
      foreach ($figures as $figure) {
        $tags = array_merge($tags, $figure->getCacheTags());
        if ($figure->bundle() === 'exhibit_figure' && !$figure->get('field_figure_caption')->isEmpty()) {
          $caption = (string) $figure->get('field_figure_caption')->value;
          $plain = strip_tags((string) preg_replace('#<br\s*/?>#i', "\n", $caption));
          $phrases = array_merge($phrases, preg_split('/[\n\[\]“”"]+/u', Html::decodeEntities($plain)) ?: []);
          $latin = $latin !== '' ? $latin : self::latin($caption);
        }
      }
    }
    return [array_values(array_filter(array_map('trim', $phrases))), $latin, $tags];
  }

  /**
   * The scientific name in a caption: "[now X y]", else the first italic
   * "Genus species".
   */
  private static function latin(string $caption): string {
    $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags($caption)));
    if (preg_match('/\[now\s+([A-Z][a-z]+ [a-z-]+)/u', $plain, $m)) {
      return $m[1];
    }
    foreach (Html::load($caption)->getElementsByTagName('em') as $em) {
      $name = trim($em->textContent);
      if (preg_match('/^[A-Z][a-z]+ [a-z-]+$/u', $name)) {
        return $name;
      }
    }
    return '';
  }

  /**
   * A name as the field guide's address spells it: "Bewick’s Wren" gives
   * bewicks-wren.
   */
  private static function key(string $name): string {
    return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(str_replace(['’', "'"], '', $name))), '-');
  }

  /**
   * A name from the address's words: capitalized, with a two-word
   * descriptor hyphenated as the field guide writes it ("yellow-throated"
   * then "Warbler" gives Yellow-throated Warbler) and a first word ending in
   * s made possessive when more words follow (bewicks-wren gives
   * Bewick’s Wren, harriss-sparrow gives Harris’s Sparrow).
   */
  private static function spell(string $slug): string {
    $words = explode('-', $slug);
    $out = [];
    foreach ($words as $i => $word) {
      if ($i === 0 && count($words) > 1 && preg_match('/s$/', $word) && !preg_match('/(ous|ess)$/', $word)) {
        $word = substr($word, 0, -1) . '’s';
      }
      if ($i > 0 && $out && $i < count($words) - 1 && str_ends_with($word, 'ed')) {
        $out[count($out) - 1] .= '-' . $word;
        continue;
      }
      $out[] = ucfirst($word);
    }
    return implode(' ', $out);
  }

  /**
   * #post_render callback: the field guide paragraphs as rows.
   *
   * $element['#pf_fieldguide'] holds names() for the text's entry.
   */
  public static function rows(string $markup, array $element): string {
    [$phrases, $latin] = $element['#pf_fieldguide'] ?? [[], '', []];
    return (string) preg_replace_callback(self::PATTERN, static function (array $m) use ($phrases, $latin): string {
      $slug = strtolower($m[2]);
      $name = '';
      foreach ($phrases as $phrase) {
        if (self::key($phrase) === $slug) {
          $name = $phrase;
          break;
        }
      }
      $name = $name !== '' ? $name : self::spell($slug);
      $arrow = '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="square" aria-hidden="true" focusable="false"><path d="M5 11 11 5M6 5h5v5"/></svg>';
      return '<p class="pf-exhibit-fieldguide">'
        . '<a class="pf-exhibit-fieldguide__link" href="' . Html::escape($m[1]) . '">'
        . '<span class="pf-exhibit-fieldguide__kicker" aria-hidden="true">' . t('Field guide') . ' · audubon.org</span>'
        . '<span class="pf-exhibit-fieldguide__name">' . Html::escape($name) . '</span>'
        . ($latin !== '' ? '<i class="pf-exhibit-fieldguide__latin" lang="la">' . Html::escape($latin) . '</i>' : '')
        . '<span class="visually-hidden">, ' . t('in the National Audubon Society field guide') . '</span>'
        . '<span class="pf-exhibit-fieldguide__go">' . $arrow . '</span>'
        . '</a></p>';
    }, $markup);
  }

}
