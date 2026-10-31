/**
 * @file
 * The Covers shelf's Previous / Next buttons (Tile showcase, "covers").
 *
 * The shelf scrolls by itself (swipe, trackpad, Tab); the buttons are for a
 * mouse. Shown only while the shelf overflows, each moves it most of its
 * width, and is disabled at its end. Keeps data-pf-at on .pf-tiles__shelf
 * (start, middle, end or none), which shows the frosted glass at the side
 * with more books. Smooth unless motion is reduced (OS
 * setting or the Settings drawer's html.pf-a11y-motion). Without JS the
 * buttons stay hidden. Styles: _tile-showcase.scss.
 */
(function (Drupal, once) {
  'use strict';

  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const reduceMotion = () => motion.matches || document.documentElement.classList.contains('pf-a11y-motion');

  Drupal.behaviors.palefireBookShelf = {
    attach(context) {
      once('pf-book-shelf', '.pf-tiles--covers', context).forEach((shelf) => {
        const list = shelf.querySelector('.pf-tiles__list');
        const nav = shelf.querySelector('.pf-tiles__shelf-nav');
        if (!list || !nav) {
          return;
        }
        const shelfEl = list.closest('.pf-tiles__shelf');
        const prev = nav.querySelector('[data-pf-shelf="prev"]');
        const next = nav.querySelector('[data-pf-shelf="next"]');

        const update = () => {
          const max = list.scrollWidth - list.clientWidth;
          const atStart = list.scrollLeft <= 1;
          const atEnd = list.scrollLeft >= max - 1;
          nav.hidden = max <= 1;
          prev.disabled = atStart;
          next.disabled = atEnd;
          if (shelfEl) {
            shelfEl.dataset.pfAt = max <= 1 ? 'none' : atStart ? 'start' : atEnd ? 'end' : 'middle';
          }
        };

        const move = (dir) => {
          list.scrollBy({
            left: dir * list.clientWidth * 0.8,
            behavior: reduceMotion() ? 'auto' : 'smooth',
          });
        };
        prev.addEventListener('click', () => move(-1));
        next.addEventListener('click', () => move(1));

        let frame = 0;
        list.addEventListener('scroll', () => {
          cancelAnimationFrame(frame);
          frame = requestAnimationFrame(update);
        }, { passive: true });
        if ('ResizeObserver' in window) {
          new ResizeObserver(update).observe(list);
        }
        update();
      });
    },
  };
})(Drupal, once);
