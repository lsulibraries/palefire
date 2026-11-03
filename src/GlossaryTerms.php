<?php

declare(strict_types=1);

namespace Drupal\palefire;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Tap-to-define archive terms in guide text (scripts/palefire_glossary_setup.php).
 *
 * The first time a Glossary term (or one of its Other forms) appears in a
 * text block, it becomes a button that opens a small card with the term's
 * definition (its Description) and, when the glossary guide exists, a link
 * to it. Native popover (popovertarget): keyboard, Escape and light dismiss
 * come with the browser. The card is placed under the word with CSS anchor
 * positioning where supported, and by js/glossary.js elsewhere.
 *
 * Never inside a link, heading, button, code, an abbreviation or a term
 * already marked; whole words only, longest form first ("finding aid"
 * before "aid"), case-insensitive. Each term once per block. Styles:
 * _glossary.scss.
 */
final class GlossaryTerms implements TrustedCallbackInterface {

  public const VOCABULARY = 'glossary';

  /**
   * The glossary guide's alias, linked from every card when it exists.
   */
  public const GUIDE_ALIAS = '/v2/guides/special-collections-glossary';

  /**
   * Ancestors a term is never marked inside.
   */
  private const SKIP = ['a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'button', 'code', 'pre', 'abbr', 'dfn', 'script', 'style', 'figcaption', 'cite'];

  /**
   * Cards made on this request, for unique ids.
   */
  private static int $count = 0;

  /**
   * Terms, loaded once per request: 'forms' => [lowercase form => tid],
   * 'terms' => [tid => [name, definition]], 'pattern' => regex.
   */
  private static ?array $glossary = NULL;

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['mark'];
  }

  /**
   * Whether the Glossary vocabulary exists and has a published term.
   */
  public static function available(): bool {
    return !empty(self::glossary()['terms']);
  }

  /**
   * The cache tag that changes when a Glossary term does.
   */
  public static function cacheTags(): array {
    return ['taxonomy_term_list:' . self::VOCABULARY];
  }

  /**
   * #post_render callback: marks the block's first use of each term.
   */
  public static function mark(string $markup, array $element): string {
    $glossary = self::glossary();
    if (empty($glossary['pattern']) || !preg_match($glossary['pattern'], strip_tags($markup))) {
      return $markup;
    }
    $dom = Html::load($markup);
    $xpath = new \DOMXPath($dom);
    $skip = implode(' or ', array_map(static fn (string $tag): string => 'ancestor::' . $tag, self::SKIP));
    $nodes = [];
    foreach ($xpath->query('//body//text()[not(' . $skip . ') and not(ancestor::*[contains(concat(" ", @class, " "), " pf-term ")])]') as $node) {
      $nodes[] = $node;
    }
    $done = [];
    $guide = self::guideUrl();
    foreach ($nodes as $node) {
      $text = $node->nodeValue;
      if (trim((string) $text) === '' || !preg_match_all($glossary['pattern'], $text, $matches, PREG_OFFSET_CAPTURE)) {
        continue;
      }
      // Split this text node at each new term, from the end backwards so the
      // earlier offsets stay right.
      $hits = [];
      foreach ($matches[0] as [$word, $offset]) {
        $tid = $glossary['forms'][mb_strtolower(preg_replace('/\s+/u', ' ', $word))] ?? NULL;
        if ($tid === NULL || isset($done[$tid])) {
          continue;
        }
        $done[$tid] = TRUE;
        $hits[] = [$word, $offset, $tid];
      }
      foreach (array_reverse($hits) as [$word, $offset, $tid]) {
        // preg offsets are bytes; splitText counts characters.
        $after = $node->splitText(mb_strlen(substr($text, 0, $offset)));
        $rest = $after->splitText(mb_strlen($word));
        [$name, $definition] = $glossary['terms'][$tid];
        $id = 'pf-term-' . $tid . '-' . (++self::$count);
        $button = $dom->createElement('button');
        $button->setAttribute('type', 'button');
        $button->setAttribute('class', 'pf-term');
        $button->setAttribute('popovertarget', $id);
        $button->setAttribute('style', 'anchor-name: --' . $id);
        $button->appendChild($dom->createTextNode($word));
        $card = $dom->createElement('span');
        $card->setAttribute('id', $id);
        $card->setAttribute('class', 'pf-term__card');
        $card->setAttribute('popover', 'auto');
        $card->setAttribute('style', 'position-anchor: --' . $id);
        $title = $dom->createElement('span');
        $title->setAttribute('class', 'pf-term__name');
        $title->appendChild($dom->createTextNode($name));
        $card->appendChild($title);
        $body = $dom->createElement('span');
        $body->setAttribute('class', 'pf-term__definition');
        $body->appendChild($dom->createTextNode($definition));
        $card->appendChild($body);
        if ($guide) {
          $link = $dom->createElement('a');
          $link->setAttribute('class', 'pf-term__more');
          $link->setAttribute('href', $guide);
          $link->appendChild($dom->createTextNode((string) t('Glossary')));
          $card->appendChild($link);
        }
        $after->parentNode->replaceChild($button, $after);
        $rest->parentNode->insertBefore($card, $rest);
      }
    }
    return $done ? Html::serialize($dom) : $markup;
  }

  /**
   * Loads the published Glossary terms once per request.
   */
  private static function glossary(): array {
    if (self::$glossary !== NULL) {
      return self::$glossary;
    }
    self::$glossary = ['forms' => [], 'terms' => [], 'pattern' => ''];
    $manager = \Drupal::entityTypeManager();
    if (!$manager->getStorage('taxonomy_vocabulary')->load(self::VOCABULARY)) {
      return self::$glossary;
    }
    $storage = $manager->getStorage('taxonomy_term');
    $ids = $storage->getQuery()->accessCheck(TRUE)
      ->condition('vid', self::VOCABULARY)
      ->condition('status', 1)
      ->execute();
    foreach ($storage->loadMultiple($ids) as $term) {
      if (!$term instanceof TermInterface) {
        continue;
      }
      $name = trim($term->getName());
      $definition = trim(preg_replace('/\s+/u', ' ', Html::decodeEntities(strip_tags((string) $term->getDescription()))));
      if ($name === '' || $definition === '') {
        continue;
      }
      $tid = (int) $term->id();
      self::$glossary['terms'][$tid] = [$name, Unicode::truncate($definition, 320, TRUE, TRUE)];
      $forms = [$name];
      if ($term->hasField('field_glossary_forms')) {
        foreach ($term->get('field_glossary_forms') as $item) {
          $forms[] = trim((string) $item->value);
        }
      }
      // A plain plural for free: "finding aids", "call numbers".
      if (!preg_match('/s$/iu', $name)) {
        $forms[] = $name . 's';
      }
      foreach (array_filter($forms) as $form) {
        self::$glossary['forms'][mb_strtolower($form)] ??= $tid;
      }
    }
    if (self::$glossary['forms']) {
      $forms = array_keys(self::$glossary['forms']);
      usort($forms, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
      $quoted = array_map(static fn (string $form): string => str_replace(' ', '\s+', preg_quote($form, '/')), $forms);
      self::$glossary['pattern'] = '/(?<![\p{L}\p{N}])(?:' . implode('|', $quoted) . ')(?![\p{L}\p{N}])/iu';
      // Whitespace in a match may be a line break: key forms by single spaces.
      foreach (self::$glossary['forms'] as $form => $tid) {
        self::$glossary['forms'][preg_replace('/\s+/u', ' ', $form)] = $tid;
      }
    }
    return self::$glossary;
  }

  /**
   * The glossary guide's URL, or '' when it isn't on this site.
   */
  private static function guideUrl(): string {
    static $url = NULL;
    if ($url === NULL) {
      $path = \Drupal::service('path_alias.repository')->lookupByAlias(self::GUIDE_ALIAS, \Drupal::languageManager()->getCurrentLanguage()->getId());
      $url = $path ? self::GUIDE_ALIAS : '';
    }
    return $url;
  }

}
