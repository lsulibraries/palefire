/**
 * @file
 * Modular pages: things that animate in as they scroll into view, once per
 * page load: revealed, they stay (as the Collections tiles do,
 * js/tile-reveal.js).
 * - Section headings (h2: .pf-section__title) slide in from the left as
 *   they come up from $pf-reveal-from (.85) to full opacity. Where the
 *   browser has scroll-driven animations (Chrome, Edge, Safari 26), the
 *   slide follows the scroll in CSS (.pf-reveal--scroll) and no observer
 *   runs; elsewhere it plays once, as before. Not on guides
 *   (<html data-pf-mode="guide">): their headings stay still.
 * - The Events carousel (libcal_gantt_carousel, inside .pf-events-carousel):
 *   once a fifth of it is in view, its day strip, then each day's label,
 *   date, weather, hours and image card brighten and rise into place one
 *   after another.
 * - Request paths (includes/guide-request-paths.html.twig): as each option
 *   comes into view, its stops rise one after another and the line
 *   draws between them (.pf-paths--reveal, _guide.scss).
 *
 * One IntersectionObserver for each kind. The observed element never moves
 * itself: headings slide sideways only (their vertical place, which is all
 * the observer reads, stays put), and the carousel's parts move inside the
 * observed carousel. Off (everything simply shown) for
 * prefers-reduced-motion, the Settings drawer's "Reduce motion"
 * (html.pf-a11y-motion) and without IntersectionObserver; without JS
 * nothing waits: the waiting states (at $pf-reveal-from, never hidden) need .pf-reveal / .pf-ev-reveal,
 * which only this script adds. Anything that receives keyboard focus, or
 * that a TOC link jumps to, is shown at once. Styles: _modular.scss,
 * _events-carousel.scss.
 */
(function (Drupal, once) {
  'use strict';

  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const reduceMotion = () => motion.matches || document.documentElement.classList.contains('pf-a11y-motion');
  // Not the hero's map heading: there only the map's drawing reveals itself.
  const HEADINGS = '.pf-section__title:not(.visually-hidden)';
  const scrollTimelines = window.CSS && CSS.supports && CSS.supports('animation-timeline: view()');

  Drupal.behaviors.palefireSectionReveal = {
    attach(context) {
      if (!('IntersectionObserver' in window) || reduceMotion()) {
        return;
      }

      // Not on guides, nor on exhibitions, whose galleries bring their
      // heading in with the gallery (js/exhibition.js, _exhibition.scss).
      const still = ['guide', 'exhibition'].includes(document.documentElement.dataset.pfMode);
      const headings = still ? [] : once('pf-section-reveal', HEADINGS, context);
      // Where the browser runs scroll-driven animations, the heading's slide
      // is tied to the scroll itself (.pf-reveal--scroll, _modular.scss): no
      // observer, and it follows the page exactly as it comes up.
      if (headings.length && scrollTimelines) {
        headings.forEach((heading) => heading.classList.add('pf-reveal--scroll'));
      }
      else if (headings.length) {
        const observer = new IntersectionObserver((entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              entry.target.classList.add('is-revealed');
              observer.unobserve(entry.target);
            }
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
            observer.disconnect();
          }
        }, { threshold: [0, 0.2] });
        observer.observe(carousel);
        carousel.addEventListener('focusin', () => carousel.classList.add('is-revealed'));
        carousel.classList.add('pf-ev-reveal');
      });

      once('pf-paths-reveal', '.pf-paths', context).forEach((paths) => {
        const lines = Array.from(paths.querySelectorAll('.pf-paths__line'));
        const observer = new IntersectionObserver((entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              entry.target.classList.add('is-revealed');
              observer.unobserve(entry.target);
            }
          });
        }, { threshold: 0.2 });
        lines.forEach((line) => {
          observer.observe(line);
          line.addEventListener('focusin', () => line.classList.add('is-revealed'));
        });
        paths.classList.add('pf-paths--reveal');
      });
    },
  };
})(Drupal, once);
