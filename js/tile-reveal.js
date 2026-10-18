/**
 * @file
 * Tile showcase cards animate in as they scroll into view, as on the
 * Origami concept page (/concept/special, ScrollReveal in Origami's
 * custom.js):
 * - stagger: odd cards rise from 50px below, even cards drop from 50px
 *   above, 100ms after entering, 50ms apart;
 * - grid: cards rise from 30px below, 50ms apart.
 * 500ms, ease-out, from opacity 0. Like the concept (reset: true), a card
 * that leaves the viewport hides again and replays when it comes back.
 *
 * No ScrollReveal: one IntersectionObserver per card list. Cards that enter
 * together are staggered in source order. The observer watches the <li>,
 * while the transform moves the card inside it (.pf-tile), so a card at the
 * edge of the screen can't move itself out of view and flicker.
 *
 * Off entirely (cards simply shown) for prefers-reduced-motion, for the
 * Settings drawer's "Reduce motion" (html.pf-a11y-motion), and without
 * IntersectionObserver. Without JS nothing is hidden: the hidden state needs
 * .pf-tiles--reveal, which only this script adds. A card that receives
 * keyboard focus is shown at once (WCAG 2.4.7). Styles: _tile-showcase.scss.
 */
(function (Drupal, once) {
  'use strict';

  const INTERVAL = 50;
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const reduceMotion = () => motion.matches || document.documentElement.classList.contains('pf-a11y-motion');

  Drupal.behaviors.palefireTileReveal = {
    attach(context) {
      if (!('IntersectionObserver' in window) || reduceMotion()) {
        return;
      }
      once('pf-tile-reveal', '.pf-tiles', context).forEach((list) => {
        const items = Array.from(list.querySelectorAll('.pf-tiles__item'));
        if (!items.length) {
          return;
        }
        const base = list.classList.contains('pf-tiles--stagger') ? 100 : 0;

        const observer = new IntersectionObserver((entries) => {
          let n = 0;
          entries
            .sort((a, b) => items.indexOf(a.target) - items.indexOf(b.target))
            .forEach((entry) => {
              const item = entry.target;
              if (entry.isIntersecting) {
                item.style.setProperty('--pf-reveal-delay', (base + INTERVAL * n++) + 'ms');
                item.classList.add('is-revealed');
              }
              else {
                item.classList.remove('is-revealed');
              }
            });
        }, { threshold: 0 });

        items.forEach((item) => {
          observer.observe(item);
          item.addEventListener('focusin', () => {
            item.style.setProperty('--pf-reveal-delay', '0ms');
            item.classList.add('is-revealed');
          });
        });
        list.classList.add('pf-tiles--reveal');
      });
    },
  };
})(Drupal, once);
