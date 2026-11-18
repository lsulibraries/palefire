/**
 * @file
 * The tape deck beside Oral History's series (landing.html.twig,
 * .pf-series__deck; styled in _landing-features.scss).
 *
 * On wide screens (64rem and up) the section becomes two columns: the
 * heading, its intro and the deck held on the left, the series on the
 * right (.has-deck). The deck's drawing (images/exhibit-deck.svg, the one
 * the exhibitions' object view plays clips on) is fetched once, as the
 * section comes near. Pointing at or focusing a series puts it on the
 * deck: the counter shows its interview count, the caption its name,
 * description and count, and the reels run for a moment. At rest it shows
 * the largest series. Reduced motion (or the Settings drawer's "Reduce
 * motion"): the caption changes, nothing runs.
 *
 * Decorative throughout: the deck and its caption are aria-hidden, and each
 * card already says its name and count and is described by its
 * description. Without this script, or on narrow screens, the section is
 * the heading over the series, as before.
 */
(function (Drupal, once) {
  'use strict';

  const wide = window.matchMedia('(min-width: 64rem)');
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const still = () => motion.matches || document.documentElement.classList.contains('pf-a11y-motion');
  let markup = null;

  const fetchDeck = (url) => {
    markup = markup || fetch(url).then((response) => {
      if (!response.ok) {
        throw new Error(response.statusText);
      }
      return response.text();
    });
    return markup;
  };

  // A card's series: its name, count, description and link.
  const seriesOf = (link) => {
    const text = link.getAttribute('aria-describedby');
    const desc = text ? document.getElementById(text) : null;
    return {
      name: (link.querySelector('.pf-series-reel__name') || {}).textContent || '',
      count: (link.querySelector('.pf-series-reel__count') || {}).textContent || '',
      text: desc ? desc.textContent.trim() : '',
      url: link.href,
    };
  };

  const digits = (count) => String(parseInt(count.replace(/\D/g, ''), 10) || 0).padStart(4, '0').slice(-4);

  function start(section) {
    const deck = section.querySelector('[data-pf-series-deck]');
    const links = Array.from(section.querySelectorAll('.pf-series__link'));
    if (!deck || !links.length || !window.fetch || !window.DOMParser) {
      return;
    }
    const machine = deck.querySelector('.pf-series__machine');
    const now = deck.querySelector('.pf-series__now');
    const name = now.querySelector('.pf-series__now-name');
    const text = now.querySelector('.pf-series__now-text');
    const count = now.querySelector('.pf-series__now-count');
    const open = now.querySelector('.pf-series__now-open');
    let svg = null;
    let current = null;
    let run = 0;
    let swap = 0;

    const counter = () => {
      if (svg && current) {
        const shown = svg.querySelector('.count');
        if (shown) {
          shown.textContent = digits(current.count);
        }
      }
    };

    const put = (link, quiet) => {
      const series = seriesOf(link);
      if (current && current.url === series.url) {
        return;
      }
      current = series;
      const fill = () => {
        name.textContent = series.name;
        text.textContent = series.text;
        count.textContent = series.count;
        open.href = series.url;
        now.classList.remove('is-swapping');
      };
      counter();
      window.clearTimeout(swap);
      if (quiet || still()) {
        fill();
        return;
      }
      now.classList.add('is-swapping');
      swap = window.setTimeout(fill, 160);
      deck.classList.add('is-playing');
      window.clearTimeout(run);
      run = window.setTimeout(() => deck.classList.remove('is-playing'), 1400);
    };

    const load = () => {
      fetchDeck(deck.dataset.pfSeriesDeck).then((source) => {
        const drawing = new DOMParser().parseFromString(source, 'image/svg+xml').documentElement;
        if (drawing.localName !== 'svg' || svg) {
          return;
        }
        svg = document.importNode(drawing, true);
        const kind = svg.querySelector('.kind');
        const year = svg.querySelector('.year');
        if (kind) {
          kind.textContent = Drupal.t('Interviews').toLocaleUpperCase();
        }
        if (year) {
          year.textContent = '';
        }
        machine.append(svg);
        counter();
      }).catch(() => {
        // No drawing: the caption still follows the series.
      });
    };

    const largest = links.reduce((best, link) => (parseInt(seriesOf(link).count.replace(/\D/g, ''), 10) > parseInt(seriesOf(best).count.replace(/\D/g, ''), 10) ? link : best), links[0]);
    put(largest, true);

    let loaded = false;
    const layout = () => {
      const on = wide.matches;
      section.classList.toggle('has-deck', on);
      deck.hidden = !on;
      if (on && !loaded) {
        loaded = true;
        if ('IntersectionObserver' in window) {
          const near = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
              near.disconnect();
              load();
            }
          }, { rootMargin: '600px 0px' });
          near.observe(section);
        }
        else {
          load();
        }
      }
    };
    layout();
    wide.addEventListener('change', layout);

    const hand = (event) => {
      const link = event.target.closest('.pf-series__link');
      if (link && section.classList.contains('has-deck')) {
        put(link, false);
      }
    };
    section.addEventListener('mouseover', hand);
    section.addEventListener('focusin', hand);
  }

  Drupal.behaviors.palefireSeriesDeck = {
    attach(context) {
      once('pf-series-deck', '.pf-series', context).forEach(start);
    },
  };
})(Drupal, once);
