/**
 * @file
 * Landing with a lead panel and a building map (.pf-landing--lead-screen,
 * includes/landing.html.twig): from md up the lead fills the first screen
 * and the page snaps between the lead and the map.
 *
 * - Down (wheel, PageDown, Space, ArrowDown) from above the map: smooth
 *   scroll to the map, under the header (the map's scroll-margin-top).
 * - Up (wheel, PageUp, Shift+Space, ArrowUp) from the map's top or above:
 *   back to the top of the page.
 *
 * CSS scroll-snap can't do this: with the map a screen away, Chromium snaps
 * each wheel step back to the top (_landing-swiss.scss). Wheel events that
 * arrive while the page is moving, and the trackpad's trailing inertia, are
 * swallowed, so one gesture is one step.
 *
 * Left to the browser: touch, below md, a lead taller than the window (its
 * end would be skipped), and wheel or keys inside anything that scrolls on
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
    // A lead taller than the window: its end would be scrolled past.
    if (lead.getBoundingClientRect().height > window.innerHeight) {
      return null;
    }
    // The header is compact at the map, so aim under the compact bar from
    // the start. Aiming under the current (full) header and following the
    // header as it shrank restarted the glide on every frame of its
    // transition, and the page stalled for about .2s on the way down.
    const pad = (Drupal.palefireCompactHeaderHeight && Drupal.palefireCompactHeaderHeight())
      || parseFloat(getComputedStyle(document.documentElement).scrollPaddingTop) || 0;
    const margin = parseFloat(getComputedStyle(map).scrollMarginTop) || 0;
    return { map: Math.round(map.getBoundingClientRect().top + window.scrollY - pad - margin) };
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
    if (down && y < at.map - 1) {
      top = at.map;
    }
    else if (!down && y > 0 && y <= at.map + 1) {
      top = 0;
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
      const now = ended && top && stops();
      if (now && Math.abs(now.map - window.scrollY) > 2) {
        window.scrollTo({ top: now.map, behavior });
      }
      moving = false;
      quiet();
    };
    if ('onscrollend' in window) {
      window.addEventListener('scrollend', () => finish(true), { once: true });
    }
    setTimeout(() => finish(false), DONE);
    window.scrollTo({ top, behavior });
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
