/**
 * @file
 * Modular pages: things that animate in as they scroll into view, and hide
 * again once they have left it, so they replay on the way back (as the
 * Collections tiles do, js/tile-reveal.js).
 * - Section headings (h2: .pf-section__title, and the landing hero's map
 *   heading) slide in from the left as they fade in.
 * - The Events carousel (libcal_gantt_carousel, inside .pf-events-carousel):
 *   once a fifth of it is in view, its day strip, then each day's label,
 *   date, weather, hours and image card fade in and rise into place one
 *   after another; it resets once it has left the window entirely.
 *
 * One IntersectionObserver for each kind. The observed element never moves
 * itself: headings slide sideways only (their vertical place, which is all
 * the observer reads, stays put), and the carousel's parts move inside the
 * observed carousel. Off (everything simply shown) for
 * prefers-reduced-motion, the Settings drawer's "Reduce motion"
 * (html.pf-a11y-motion) and without IntersectionObserver; without JS
 * nothing is hidden: the hidden states need .pf-reveal / .pf-ev-reveal,
 * which only this script adds. Anything that receives keyboard focus, or
 * that a TOC link jumps to, is shown at once. Styles: _modular.scss,
 * _events-carousel.scss.
 */
(function (Drupal, once) {
  'use strict';

  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const reduceMotion = () => motion.matches || document.documentElement.classList.contains('pf-a11y-motion');
  const HEADINGS = '.pf-section__title:not(.visually-hidden), .pf-landing--hero .pf-map__heading';

  Drupal.behaviors.palefireSectionReveal = {
    attach(context) {
      if (!('IntersectionObserver' in window) || reduceMotion()) {
        return;
      }

      const headings = once('pf-section-reveal', HEADINGS, context);
      if (headings.length) {
        const observer = new IntersectionObserver((entries) => {
          entries.forEach((entry) => {
            entry.target.classList.toggle('is-revealed', entry.isIntersecting);
          });
        }, { threshold: 0 });
        headings.forEach((heading) => {
          heading.classList.add('pf-reveal');
          observer.observe(heading);
          heading.addEventListener('focus', () => heading.classList.add('is-revealed'));
        });
      }

      once('pf-ev-reveal', '.pf-events-carousel .libcal-day-carousel', context).forEach((carousel) => {
        const observer = new IntersectionObserver(([entry]) => {
          if (entry.isIntersecting && entry.intersectionRatio >= 0.2) {
            carousel.classList.add('is-revealed');
          }
          else if (!entry.isIntersecting) {
            carousel.classList.remove('is-revealed');
          }
        }, { threshold: [0, 0.2] });
        observer.observe(carousel);
        carousel.addEventListener('focusin', () => carousel.classList.add('is-revealed'));
        carousel.classList.add('pf-ev-reveal');
      });
    },
  };
})(Drupal, once);
