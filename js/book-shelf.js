/**
 * @file
 * The Covers shelf's Previous / Next buttons and mouse drag (Tile showcase,
 * "covers").
 *
 * The shelf scrolls by itself (swipe, trackpad, Tab); the buttons are for a
 * mouse. Shown only while the shelf overflows, each moves it most of its
 * width, and is disabled at its end. Keeps data-pf-at on .pf-tiles__shelf
 * (start, middle, end or none), which shows the fade at the side with more
 * books. Smooth unless motion is reduced (OS
 * setting or the Settings drawer's html.pf-a11y-motion). Without JS the
 * buttons stay hidden. Styles: _tile-showcase.scss.
 *
 * A mouse can also grab the shelf and fling it, coming to rest exactly on
 * the nearest book (Drupal.palefireDragGlide, js/drag-glide.js, shared
 * with the exhibitions band). Touch and trackpads keep the OS's own
 * momentum. The buttons stay the non-drag way to scroll (WCAG 2.5.7).
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
        if (!list || !nav || !Drupal.palefireDragGlide) {
          return;
        }
        const shelfEl = list.closest('.pf-tiles__shelf');
        const prev = nav.querySelector('[data-pf-shelf="prev"]');
        const next = nav.querySelector('[data-pf-shelf="next"]');
        const maxScroll = () => list.scrollWidth - list.clientWidth;
        let dragging = false;

        // Runs on every scroll frame, so it only writes what changed: a
        // rewritten cursor restyles every book (it is inherited, and the
        // dimming rule uses :has()), and a rewritten data-pf-at restyles
        // the fades, even when the value is the same.
        const shown = {};
        const set = (key, value, apply) => {
          if (shown[key] !== value) {
            shown[key] = value;
            apply(value);
          }
        };
        const update = () => {
          const max = maxScroll();
          const atStart = list.scrollLeft <= 1;
          const atEnd = list.scrollLeft >= max - 1;
          set('hidden', max <= 1, (v) => {
            nav.hidden = v;
          });
          set('prev', atStart, (v) => {
            prev.disabled = v;
          });
          set('next', atEnd, (v) => {
            next.disabled = v;
          });
          if (shelfEl) {
            set('at', max <= 1 ? 'none' : atStart ? 'start' : atEnd ? 'end' : 'middle', (v) => {
              shelfEl.dataset.pfAt = v;
            });
          }
          // A hand where there is something to grab.
          set('cursor', max > 1 ? (dragging ? 'grabbing' : 'grab') : '', (v) => {
            list.style.cursor = v;
          });
        };

        // While dragging or gliding, the books ignore the pointer: books
        // sliding under a still pointer would otherwise each set off the
        // hover dimming across the whole shelf. The list still takes the
        // pointer, so a new grab stops a glide.
        const items = list.getElementsByClassName('pf-tiles__item');
        const glider = Drupal.palefireDragGlide(list, {
          item: '.pf-tiles__item',
          onDrag: (on) => {
            dragging = on;
            update();
          },
          onMoving: (on) => {
            set('moving', on, (v) => {
              for (const item of items) {
                item.style.pointerEvents = v ? 'none' : '';
              }
            });
          },
        });

        const move = (dir) => {
          glider.stop();
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
