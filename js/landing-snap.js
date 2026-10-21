/**
 * @file
 * Landing with a lead panel and a building map (.pf-landing--lead-screen,
 * includes/landing.html.twig): from md up the lead fills the first screen
 * and the page snaps between the lead and the map.
 *
 * - Down (wheel, PageDown, Space, ArrowDown) from above the map, once the
 *   lead's end is halfway up the window: smooth scroll to the map, under
 *   the header (the map's scroll-margin-top).
 * - Up (wheel, PageUp, Shift+Space, ArrowUp) from the map's top: back to
 *   where the lead's end is halfway up the window (the top of the page
 *   when the lead fits the window).
 *
 * A lead taller than the window (the Quick Guide) scrolls freely until its
 * end is halfway up, so its last lines can be read; the snap is only the
 * step from there to the map.
 *
 * CSS scroll-snap can't do this: with the map a screen away, Chromium snaps
 * each wheel step back to the top (_landing-swiss.scss). Wheel events that
 * arrive while the page is moving, and the trackpad's trailing inertia, are
 * swallowed, so one gesture is one step.
 *
 * Left to the browser: touch, below md, any step inside the lead (down
 * while its end is still below the middle of the window, up once it has
 * gone below it), and wheel or keys inside anything that scrolls on
 * its own (drawers, menus) or in a form field. Instant for
 * prefers-reduced-motion and the Settings drawer's "Reduce motion"
 * (html.pf-a11y-motion). Listeners are set once for the document, since
 * Swup swaps the page under them; each event looks up the current landing.
 */
(function (Drupal) {
  'use strict';

  const QUIET = 200; // ms without wheel events that ends a gesture
  const DONE = 1500; // ms fallback where there is no scrollend event
  const wide = window.matchMedia('(min-width: 768px)'); // $pf-snap-breakpoint
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const reduceMotion = () => motion.matches || document.documentElement.classList.contains('pf-a11y-motion');
  const DOWN = ['PageDown', 'ArrowDown', ' '];
  const UP = ['PageUp', 'ArrowUp'];

  let moving = false;
  let gesture = false;
  let quietTimer = 0;
  let started = false;

  const quiet = () => {
    clearTimeout(quietTimer);
    quietTimer = setTimeout(() => { gesture = false; }, QUIET);
  };

  // The two stops, or null when this page has no snapping landing.
  function stops() {
    const landing = document.querySelector('.pf-landing--lead-screen');
    const lead = landing && landing.querySelector(':scope > .pf-landing__lead');
    const map = landing && landing.querySelector(':scope > .pf-landing__visit');
    if (!lead || !map || !wide.matches) {
      return null;
    }
    // The header is compact at the map, so aim under the compact bar from
    // the start. Aiming under the current (full) header and following the
    // header as it shrank restarted the glide on every frame of its
    // transition, and the page stalled for about .2s on the way down.
    const pad = (Drupal.palefireCompactHeaderHeight && Drupal.palefireCompactHeaderHeight())
      || parseFloat(getComputedStyle(document.documentElement).scrollPaddingTop) || 0;
    const margin = parseFloat(getComputedStyle(map).scrollMarginTop) || 0;
    const mapTop = Math.round(map.getBoundingClientRect().top + window.scrollY - pad - margin);
    // Where the lead's end has come halfway up the window, so its last
    // lines get read before the step: 0 when the lead fits the window (the
    // snap starts at the top, as before). Above it, the page scrolls freely
    // through the lead. Never past the map's own stop.
    const leadBottom = lead.getBoundingClientRect().bottom + window.scrollY;
    const leadEnd = leadBottom > window.innerHeight + 1
      ? Math.min(mapTop, Math.round(leadBottom - window.innerHeight / 2))
      : 0;
    return {
      map: mapTop,
      leadEnd,
    };
  }

  // Anything between the target and the page that scrolls by itself.
  function inScroller(el) {
    for (let node = el; node && node !== document.body && node !== document.documentElement; node = node.parentElement) {
      if (node.scrollHeight > node.clientHeight && /auto|scroll/.test(getComputedStyle(node).overflowY)) {
        return true;
      }
    }
    return false;
  }

  // Returns true when it took the step.
  function step(down) {
    const at = stops();
    if (!at) {
      return false;
    }
    const y = window.scrollY;
    let top = null;
    // Between the lead's end (halfway up) and the map: a step skips none
    // of the lead.
    if (down && y < at.map - 1 && y >= at.leadEnd - 1) {
      top = at.map;
    }
    else if (!down && y > at.leadEnd + 1 && y <= at.map + 1) {
      top = at.leadEnd;
    }
    if (top === null) {
      return false;
    }
    const behavior = reduceMotion() ? 'instant' : 'smooth';
    moving = true;
    gesture = true;
    let finished = false;
    // ended: the page really stopped (scrollend), not the fallback timer.
    const finish = (ended) => {
      if (finished) {
        return;
      }
      finished = true;
      // One correction at most, if the page moved under the glide (a late
      // font or image): never mid-flight, which restarts it from rest.
      const now = ended && stops();
      const want = now && (down ? now.map : now.leadEnd);
      if (now && Math.abs(want - window.scrollY) > 2) {
        window.scrollTo({ top: want, behavior });
      }
      moving = false;
      quiet();
    };
    if (behavior === 'instant') {
      if ('onscrollend' in window) {
        window.addEventListener('scrollend', () => finish(true), { once: true });
      }
      setTimeout(() => finish(false), DONE);
      window.scrollTo({ top, behavior });
    }
    else {
      // One calm glide (Drupal.palefireGlide, palefire.js), not the
      // browser's front-loaded smooth scroll, which made the map's reveal
      // a flash and then a crawl. It calls finish itself: scrollend would
      // fire after each of its frames. The fallback timer still covers a
      // hidden tab, where frames stop.
      setTimeout(() => finish(false), DONE);
      Drupal.palefireGlide(top, () => finish(true));
    }
    return true;
  }

  function onWheel(event) {
    if (moving || gesture) {
      if (stops()) {
        event.preventDefault();
        if (!moving) {
          quiet();
        }
      }
      return;
    }
    if (event.ctrlKey || Math.abs(event.deltaY) <= Math.abs(event.deltaX) || inScroller(event.target)) {
      return;
    }
    if (step(event.deltaY > 0)) {
      event.preventDefault();
    }
  }

  function onKey(event) {
    if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) {
      return;
    }
    const target = event.target;
    if (target.closest && target.closest('input, textarea, select, button, [contenteditable], [role="slider"], [role="listbox"], [role="menu"]')) {
      return;
    }
    let down = null;
    if (DOWN.includes(event.key)) {
      down = !(event.key === ' ' && event.shiftKey);
    }
    else if (UP.includes(event.key)) {
      down = false;
    }
    if (down === null || inScroller(target)) {
      return;
    }
    if (moving) {
      if (stops()) {
        event.preventDefault();
      }
      return;
    }
    if (step(down)) {
      event.preventDefault();
    }
  }

  Drupal.behaviors.palefireLandingSnap = {
    attach() {
      if (started) {
        return;
      }
      started = true;
      window.addEventListener('wheel', onWheel, { passive: false });
      document.addEventListener('keydown', onKey);
    },
  };
})(Drupal);
