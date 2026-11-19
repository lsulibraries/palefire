/**
 * @file
 * Grab-and-fling for a sideways scrolling row: a mouse can grab the row
 * and fling it. Shared by the Covers shelf (js/book-shelf.js) and the
 * exhibitions' canvas rail (js/exhibit-showcase.js).
 *
 * The release speed (the last 100ms of the drag) carries the row on,
 * slowing like a real flick, and it comes to rest exactly on the nearest
 * item: the glide aims at that item's snap point instead of letting
 * snapping jerk it there. Snapping is off while dragging and gliding.
 * Touch and trackpads keep the OS's own momentum. A drag of more than 5px
 * doesn't follow the link (or run the click) it started on. With reduced
 * motion (the OS setting or the Settings drawer's html.pf-a11y-motion)
 * there is no glide: it settles on the nearest item at once. Each user
 * keeps its own buttons as the non-drag way to scroll (WCAG 2.5.7).
 *
 * Drupal.palefireDragGlide(list, {
 *   item: '.pf-tiles__item',      // the items it snaps to
 *   onDrag(on) {},                // a drag starts (true) or ends (false)
 *   onMoving(on) {},              // dragging or gliding starts / stops
 * }) returns { glideTo(x), stop(), dragging() }.
 */
(function (Drupal) {
  'use strict';

  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const reduceMotion = () => motion.matches || document.documentElement.classList.contains('pf-a11y-motion');

  // How far past the pointer a drag must go before it is a drag (px), how
  // much of the drag counts toward the release speed (ms), and the glide's
  // time constant (ms): it covers 63% of the way in this long, 95% in 3x.
  // The fastest fling counted (px/ms) carries about 1000px.
  const DRAG_START = 5;
  const VELOCITY_WINDOW = 100;
  const GLIDE_TAU = 325;
  const MAX_VELOCITY = 3;

  Drupal.palefireDragGlide = (list, options = {}) => {
    const noop = () => {};
    const onDrag = options.onDrag || noop;
    const onMoving = options.onMoving || noop;
    const maxScroll = () => list.scrollWidth - list.clientWidth;

    let pointer = null;
    let dragging = false;
    let justDragged = false;
    let startX = 0;
    let startScroll = 0;
    let samples = [];

    // The glide: an exponential ease toward a fixed target, so it lands on
    // the item's snap point and snapping has nothing left to do.
    let glide = 0;
    const stop = () => {
      cancelAnimationFrame(glide);
      glide = 0;
      list.style.scrollSnapType = '';
      onMoving(false);
    };

    // Where each item snaps to: its start, less the scroll padding, within
    // the row's range. The end counts too, so a fling can reach the last
    // items when they can't snap to their start.
    const nearestSnap = (x) => {
      const max = maxScroll();
      const pad = parseFloat(getComputedStyle(list).scrollPaddingInlineStart) || 0;
      const base = list.getBoundingClientRect().left - list.scrollLeft;
      let best = max;
      let bestDist = Math.abs(x - max);
      list.querySelectorAll(options.item).forEach((item) => {
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
      cancelAnimationFrame(glide);
      if (reduceMotion()) {
        list.scrollLeft = target;
        stop();
        return;
      }
      const from = list.scrollLeft;
      const distance = target - from;
      const start = performance.now();
      const step = (now) => {
        // The decay, scaled to arrive exactly at 5 time constants, so its
        // tail neither creeps nor jumps.
        const t = Math.min(1, (now - start) / (GLIDE_TAU * 5));
        if (t >= 1) {
          list.scrollLeft = target;
          stop();
          return;
        }
        list.scrollLeft = from + distance * (1 - Math.exp(-5 * t)) / (1 - Math.exp(-5));
        glide = requestAnimationFrame(step);
      };
      list.style.scrollSnapType = 'none';
      onMoving(true);
      glide = requestAnimationFrame(step);
    };

    list.addEventListener('pointerdown', (event) => {
      if (event.pointerType !== 'mouse' || event.button !== 0 || maxScroll() <= 1) {
        return;
      }
      stop();
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
        onMoving(true);
        onDrag(true);
        window.getSelection()?.removeAllRanges();
      }
      // One scroll write a frame: a high-rate mouse sends several moves a
      // frame, and each write would scroll and run the scroll handlers.
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
      // Only until the click this release makes, which comes first, so a
      // later Enter still works.
      justDragged = true;
      setTimeout(() => {
        justDragged = false;
      });
      list.style.userSelect = '';
      if (list.hasPointerCapture(event.pointerId)) {
        list.releasePointerCapture(event.pointerId);
      }
      onDrag(false);

      // Release speed in scroll px per ms. A pause before letting go (no
      // move in the last window) means no fling.
      const first = samples[0];
      const last = samples[samples.length - 1];
      const dt = last.t - first.t;
      const still = event.timeStamp - last.t > VELOCITY_WINDOW / 2;
      const raw = dt > 0 && !still && event.type === 'pointerup' ? -(last.x - first.x) / dt : 0;
      const velocity = Math.max(-MAX_VELOCITY, Math.min(MAX_VELOCITY, raw));

      // Where a free glide would stop (velocity x tau), then the item
      // nearest to that.
      glideTo(nearestSnap(list.scrollLeft + (reduceMotion() ? 0 : velocity * GLIDE_TAU)));
    };
    list.addEventListener('pointerup', release);
    list.addEventListener('pointercancel', release);

    // The click that ends a drag doesn't follow a link or run a click.
    list.addEventListener('click', (event) => {
      if (justDragged) {
        event.preventDefault();
        event.stopPropagation();
      }
    }, true);
    // No native drag of an image or a link.
    list.addEventListener('dragstart', (event) => event.preventDefault());
    // A wheel or trackpad takes over from a glide.
    list.addEventListener('wheel', () => {
      if (glide) {
        stop();
      }
    }, { passive: true });

    return {
      glideTo,
      stop,
      dragging: () => dragging,
    };
  };
})(Drupal);
