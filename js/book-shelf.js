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
 * A mouse can also grab the shelf and fling it. The release speed (the last
 * 100ms of the drag) carries it on, slowing like a real flick, and it comes
 * to rest exactly on the nearest book: the glide aims at that book's snap
 * point instead of letting snapping jerk it there. Snapping is off while
 * dragging and gliding. Touch and trackpads keep the OS's own momentum. A
 * drag of more than 5px doesn't open the book it started on. With reduced
 * motion there is no glide: it settles on the nearest book at once. The
 * buttons stay the non-drag way to scroll (WCAG 2.5.7).
 */
(function (Drupal, once) {
  'use strict';

  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const reduceMotion = () => motion.matches || document.documentElement.classList.contains('pf-a11y-motion');

  // How far past the pointer a drag must go before it is a drag (px), how
  // much of the drag counts toward the release speed (ms), and the glide's
  // time constant (ms): it covers 63% of the way in this long, 95% in 3x.
  // The fastest fling counted (px/ms) carries about 1000px, five books.
  const DRAG_START = 5;
  const VELOCITY_WINDOW = 100;
  const GLIDE_TAU = 325;
  const MAX_VELOCITY = 3;

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
        const maxScroll = () => list.scrollWidth - list.clientWidth;

        // Mouse drag state. Touch and pens scroll natively.
        let pointer = null;
        let dragging = false;
        let justDragged = false;
        let startX = 0;
        let startScroll = 0;
        let samples = [];

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
        const setMoving = (on) => {
          set('moving', on, (v) => {
            for (const item of items) {
              item.style.pointerEvents = v ? 'none' : '';
            }
          });
        };

        // The glide: an exponential ease toward a fixed target, so it lands
        // on the book's snap point and snapping has nothing left to do.
        let glide = 0;
        const stopGlide = () => {
          cancelAnimationFrame(glide);
          glide = 0;
          list.style.scrollSnapType = '';
          setMoving(false);
        };

        // Where each book snaps to: its start, less the scroll padding (the
        // fade), within the shelf's range. The end counts too, so a fling
        // can reach the last books when they can't snap to their start.
        const nearestSnap = (x) => {
          const max = maxScroll();
          const pad = parseFloat(getComputedStyle(list).scrollPaddingLeft) || 0;
          const base = list.getBoundingClientRect().left - list.scrollLeft;
          let best = max;
          let bestDist = Math.abs(x - max);
          list.querySelectorAll('.pf-tiles__item').forEach((item) => {
            const snap = Math.min(max, Math.max(0, item.getBoundingClientRect().left - base - pad));
            const dist = Math.abs(x - snap);
            if (dist < bestDist) {
              best = snap;
              bestDist = dist;
            }
          });
          return best;
        };

        const glideTo = (target) => {
          const from = list.scrollLeft;
          const distance = target - from;
          const start = performance.now();
          const step = (now) => {
            // The decay, scaled to arrive exactly at 5 time constants, so
            // its tail neither creeps nor jumps.
            const t = Math.min(1, (now - start) / (GLIDE_TAU * 5));
            if (t >= 1) {
              list.scrollLeft = target;
              stopGlide();
              return;
            }
            list.scrollLeft = from + distance * (1 - Math.exp(-5 * t)) / (1 - Math.exp(-5));
            glide = requestAnimationFrame(step);
          };
          list.style.scrollSnapType = 'none';
          glide = requestAnimationFrame(step);
        };

        const move = (dir) => {
          stopGlide();
          list.scrollBy({
            left: dir * list.clientWidth * 0.8,
            behavior: reduceMotion() ? 'auto' : 'smooth',
          });
        };
        prev.addEventListener('click', () => move(-1));
        next.addEventListener('click', () => move(1));

        list.addEventListener('pointerdown', (event) => {
          if (event.pointerType !== 'mouse' || event.button !== 0 || maxScroll() <= 1) {
            return;
          }
          stopGlide();
          pointer = event.pointerId;
          startX = event.clientX;
          startScroll = list.scrollLeft;
          samples = [{ t: event.timeStamp, x: event.clientX }];
        });

        let dragTo = 0;
        let dragFrame = 0;
        const applyDrag = () => {
          dragFrame = 0;
          list.scrollLeft = dragTo;
        };

        list.addEventListener('pointermove', (event) => {
          if (event.pointerId !== pointer) {
            return;
          }
          const dx = event.clientX - startX;
          if (!dragging) {
            if (Math.abs(dx) < DRAG_START) {
              return;
            }
            dragging = true;
            list.setPointerCapture(pointer);
            list.style.scrollSnapType = 'none';
            list.style.userSelect = 'none';
            setMoving(true);
            update();
            window.getSelection()?.removeAllRanges();
          }
          // One scroll write a frame: a high-rate mouse sends several moves
          // a frame, and each write would scroll and run the scroll handler.
          dragTo = startScroll - dx;
          if (!dragFrame) {
            dragFrame = requestAnimationFrame(applyDrag);
          }
          samples.push({ t: event.timeStamp, x: event.clientX });
          while (samples.length > 2 && event.timeStamp - samples[0].t > VELOCITY_WINDOW) {
            samples.shift();
          }
        });

        const release = (event) => {
          if (event.pointerId !== pointer) {
            return;
          }
          pointer = null;
          if (!dragging) {
            return;
          }
          dragging = false;
          // The last move's position, if its frame hasn't come yet.
          if (dragFrame) {
            cancelAnimationFrame(dragFrame);
            applyDrag();
          }
          // Only until the click this release makes, which comes first, so
          // a later Enter on a book still opens it.
          justDragged = true;
          setTimeout(() => {
            justDragged = false;
          });
          list.style.userSelect = '';
          if (list.hasPointerCapture(event.pointerId)) {
            list.releasePointerCapture(event.pointerId);
          }
          update();

          // Release speed in scroll px per ms. A pause before letting go
          // (no move in the last window) means no fling.
          const first = samples[0];
          const last = samples[samples.length - 1];
          const dt = last.t - first.t;
          const still = event.timeStamp - last.t > VELOCITY_WINDOW / 2;
          const raw = dt > 0 && !still && event.type === 'pointerup' ? -(last.x - first.x) / dt : 0;
          const velocity = Math.max(-MAX_VELOCITY, Math.min(MAX_VELOCITY, raw));

          // Where a free glide would stop (velocity x tau), then the book
          // nearest to that.
          const target = nearestSnap(list.scrollLeft + (reduceMotion() ? 0 : velocity * GLIDE_TAU));
          if (reduceMotion()) {
            list.scrollLeft = target;
            list.style.scrollSnapType = '';
            setMoving(false);
          }
          else {
            glideTo(target);
          }
        };
        list.addEventListener('pointerup', release);
        list.addEventListener('pointercancel', release);

        // The click that ends a drag doesn't follow the book's link.
        list.addEventListener('click', (event) => {
          if (justDragged) {
            event.preventDefault();
            event.stopPropagation();
          }
        }, true);
        // No native drag of the cover image or the link.
        list.addEventListener('dragstart', (event) => event.preventDefault());
        // A wheel or trackpad takes over from a glide.
        list.addEventListener('wheel', () => {
          if (glide) {
            stopGlide();
          }
        }, { passive: true });

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
