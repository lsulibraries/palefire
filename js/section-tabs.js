/**
 * @file
 * Section tabs (paragraph--section-tabs.html.twig, _section-tabs.scss).
 *
 * ARIA tabs with a sliding key:
 * - Arrow keys, Home and End move between tabs and select them; Tab moves
 *   into the panel.
 * - The key can be dragged (an extra: tapping and the keys do everything,
 *   WCAG 2.5.7). It starts only on the selected tab, after 6px sideways; a
 *   mostly vertical move is left to page scrolling. Held, it follows the
 *   finger (rubber-banding past the ends) and the slot under it lights;
 *   let go, it snaps to the nearest slot.
 * - Panels follow the key: the old one leaves the way the key left (.12s),
 *   the new one comes in from the side it travels toward, then its parts
 *   rise one after another, 60ms apart. A tap during the hand-off finishes
 *   it at once. Sections with their own reveal (Ask Special Collections,
 *   js/ask-reveal.js) keep it and skip the parts'.
 * - Links: a panel's id (#visit) opens it, on arrival, on hashchange, and
 *   for any same-page link to it or to something inside it (#ask-question,
 *   a TOC sub-link), before the browser or Swup scrolls there. Choosing a
 *   tab puts its id in the address (replaceState: no history entries).
 * - Find in page opens a hidden panel where the browser supports
 *   hidden="until-found" (its beforematch event).
 *
 * - Haptics (Android Chrome and others with navigator.vibrate; not iOS): a
 *   10ms tap when a tab is chosen by tap, key or drag, and a 5ms tick as a
 *   dragged key passes each slot, like a detent. Never for a link or Find.
 *
 * Off for prefers-reduced-motion and Reduce motion (html.pf-a11y-motion):
 * everything switches at once, without haptics. Without JS the template shows every panel
 * under its own heading; this script adds .is-ready and hides the rest.
 */
(function (Drupal, once) {
  'use strict';

  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const still = () => motion.matches || document.documentElement.classList.contains('pf-a11y-motion');
  const findable = 'onbeforematch' in document.documentElement;
  const LEAVE_MS = 120;
  const SNAP_MS = 520;
  const PARTS_SKIP = '.pf-inquiry';
  const TAP_MS = 10;
  const TICK_MS = 5;

  // A short vibration where the browser has one; nothing otherwise.
  function buzz(ms) {
    if (still() || typeof navigator.vibrate !== 'function') {
      return;
    }
    try {
      navigator.vibrate(ms);
    }
    catch (e) {
      // Blocked without a user gesture.
    }
  }

  // A panel's parts: the children of its first element with more than one,
  // up to four levels down (a field's wrappers hold one item each).
  function partsOf(panel) {
    let el = panel.querySelector('.pf-tabs__body');
    for (let depth = 0; el && el.children.length === 1 && depth < 4; depth++) {
      el = el.firstElementChild;
    }
    return el ? Array.from(el.children) : [];
  }

  function hide(panel) {
    panel.classList.remove('is-leaving', 'is-entering');
    if (findable) {
      panel.setAttribute('hidden', 'until-found');
    }
    else {
      panel.hidden = true;
    }
  }

  function setup(root) {
    const list = root.querySelector('[role="tablist"]');
    const tabs = Array.from(list.querySelectorAll('[role="tab"]'));
    const panels = tabs.map((tab) => document.getElementById(tab.getAttribute('aria-controls')));
    const key = list.querySelector('.pf-tabs__key');
    if (!tabs.length || panels.includes(null)) {
      return;
    }
    let swap = null;

    root.style.setProperty('--pf-tabs-count', tabs.length);
    if (findable) {
      root.classList.add('has-find');
    }

    panels.forEach((panel) => {
      if (panel.querySelector(PARTS_SKIP)) {
        return;
      }
      partsOf(panel).forEach((part, i) => {
        part.classList.add('pf-tabs__part');
        part.style.setProperty('--pf-tabs-i', i);
      });
    });

    const current = () => tabs.findIndex((tab) => tab.getAttribute('aria-selected') === 'true');

    function placeKey() {
      const tab = tabs[Math.max(0, current())];
      key.style.width = tab.offsetWidth + 'px';
      key.style.transform = 'translateX(' + tab.offsetLeft + 'px)';
    }

    function show(panel, dir) {
      panels.forEach((other) => {
        if (other !== panel) {
          hide(other);
        }
      });
      panel.hidden = false;
      panel.removeAttribute('hidden');
      panel.style.setProperty('--pf-tabs-dir', dir);
      panel.classList.remove('is-leaving', 'is-entering');
      if (!still()) {
        void panel.offsetWidth;
        panel.classList.add('is-entering');
      }
    }

    function select(index, { focus = false, hash = true } = {}) {
      const from = panels.findIndex((panel) => !panel.hasAttribute('hidden'));
      tabs.forEach((tab, i) => {
        tab.setAttribute('aria-selected', String(i === index));
        tab.tabIndex = i === index ? 0 : -1;
      });
      if (swap) {
        clearTimeout(swap);
        swap = null;
      }
      if (from !== index) {
        const dir = from < 0 || index > from ? 1 : -1;
        const old = panels[from];
        if (old && !still()) {
          old.style.setProperty('--pf-tabs-dir', dir);
          old.classList.remove('is-entering');
          old.classList.add('is-leaving');
          swap = setTimeout(() => {
            swap = null;
            show(panels[index], dir);
          }, LEAVE_MS);
        }
        else {
          show(panels[index], dir);
        }
      }
      else if (panels[index].classList.contains('is-leaving')) {
        // Back to the panel on its way out: straight back.
        show(panels[index], 1);
      }
      placeKey();
      if (focus) {
        tabs[index].focus();
      }
      if (hash) {
        try {
          history.replaceState(history.state, '', '#' + panels[index].id);
        }
        catch (e) {
          // Sandboxed or file: pages.
        }
      }
    }

    // Opening from a link or Find: no hand-off, so the browser scrolls to
    // a laid-out panel.
    function open(index) {
      if (current() === index && !panels[index].hasAttribute('hidden')) {
        return;
      }
      tabs.forEach((tab, i) => {
        tab.setAttribute('aria-selected', String(i === index));
        tab.tabIndex = i === index ? 0 : -1;
      });
      if (swap) {
        clearTimeout(swap);
        swap = null;
      }
      panels.forEach((panel, i) => {
        if (i === index) {
          panel.removeAttribute('hidden');
          panel.classList.remove('is-leaving', 'is-entering');
        }
        else {
          hide(panel);
        }
      });
      placeKey();
    }

    tabs.forEach((tab, i) => tab.addEventListener('click', () => {
      if (i !== current()) {
        buzz(TAP_MS);
      }
      select(i);
    }));

    list.addEventListener('keydown', (event) => {
      const i = tabs.indexOf(document.activeElement);
      if (i < 0) {
        return;
      }
      const next = {
        ArrowRight: (i + 1) % tabs.length,
        ArrowLeft: (i - 1 + tabs.length) % tabs.length,
        Home: 0,
        End: tabs.length - 1,
      }[event.key];
      if (next !== undefined) {
        event.preventDefault();
        if (next !== i) {
          buzz(TAP_MS);
        }
        select(next, { focus: true });
      }
    });

    panels.forEach((panel, i) => panel.addEventListener('beforematch', () => open(i)));

    // Drag the key.
    let drag = null;
    let eatClick = false;
    const nearest = (centre) => tabs.reduce((best, tab, i) => {
      const d = Math.abs(tab.offsetLeft + tab.offsetWidth / 2 - centre);
      return d < best.d ? { i, d } : best;
    }, { i: 0, d: Infinity }).i;

    list.addEventListener('pointerdown', (event) => {
      const tab = event.target.closest('[role="tab"]');
      if (!tab || tab.getAttribute('aria-selected') !== 'true' || event.button > 0) {
        return;
      }
      drag = { id: event.pointerId, x0: event.clientX, y0: event.clientY, left: tab.offsetLeft, on: false, near: tabs.indexOf(tab) };
    });

    list.addEventListener('pointermove', (event) => {
      if (!drag || event.pointerId !== drag.id) {
        return;
      }
      const dx = event.clientX - drag.x0;
      const dy = event.clientY - drag.y0;
      if (!drag.on) {
        if (Math.abs(dy) > 10 && Math.abs(dy) > Math.abs(dx)) {
          drag = null;
          return;
        }
        if (Math.abs(dx) < 6) {
          return;
        }
        drag.on = true;
        try {
          list.setPointerCapture(event.pointerId);
        }
        catch (e) {
          // Pointer already gone.
        }
        list.classList.add('is-dragging');
        key.classList.add('is-dragging');
      }
      const last = tabs[tabs.length - 1];
      const min = tabs[0].offsetLeft;
      const max = last.offsetLeft + last.offsetWidth - key.offsetWidth;
      let x = drag.left + dx;
      if (x < min) {
        x = min - (min - x) * 0.25;
      }
      if (x > max) {
        x = max + (x - max) * 0.25;
      }
      key.style.transform = 'translateX(' + x + 'px)';
      const near = nearest(x + key.offsetWidth / 2);
      if (near !== drag.near) {
        buzz(TICK_MS);
      }
      drag.near = near;
      tabs.forEach((tab, i) => tab.classList.toggle('is-near', i === drag.near));
    });

    function endDrag(event) {
      if (!drag || event.pointerId !== drag.id) {
        return;
      }
      const { on, near } = drag;
      drag = null;
      if (!on) {
        return;
      }
      tabs.forEach((tab) => tab.classList.remove('is-near'));
      list.classList.remove('is-dragging');
      key.classList.remove('is-dragging');
      key.classList.add('is-snapping');
      setTimeout(() => key.classList.remove('is-snapping'), SNAP_MS);
      buzz(TAP_MS);
      eatClick = true;
      setTimeout(() => {
        eatClick = false;
      }, 80);
      select(near);
    }
    list.addEventListener('pointerup', endDrag);
    list.addEventListener('pointercancel', endDrag);
    list.addEventListener('click', (event) => {
      if (eatClick) {
        event.stopPropagation();
        event.preventDefault();
        eatClick = false;
      }
    }, true);

    if ('ResizeObserver' in window) {
      new ResizeObserver(placeKey).observe(list);
    }
    else {
      window.addEventListener('resize', placeKey);
    }

    root.pfTabsOpen = (panel) => {
      const i = panels.indexOf(panel);
      if (i >= 0) {
        open(i);
      }
    };

    // Ready: the first panel (or the one the address names) shows, the rest
    // hide, and the key takes its place without sliding there.
    key.classList.add('is-instant');
    root.classList.add('is-ready');
    const start = Math.max(0, current());
    panels.forEach((panel, i) => {
      if (i === start) {
        panel.removeAttribute('hidden');
      }
      else {
        hide(panel);
      }
    });
    placeKey();
    requestAnimationFrame(() => requestAnimationFrame(() => key.classList.remove('is-instant')));
    if (document.fonts && document.fonts.ready) {
      document.fonts.ready.then(placeKey);
    }
  }

  // Opens the panel holding the element with this id, if it is in one.
  function reveal(id) {
    if (!id) {
      return;
    }
    let target = null;
    try {
      target = document.getElementById(decodeURIComponent(id));
    }
    catch (e) {
      return;
    }
    const panel = target && target.closest('.pf-tabs__panel');
    const root = panel && panel.closest('.pf-tabs');
    if (root && typeof root.pfTabsOpen === 'function') {
      root.pfTabsOpen(panel);
    }
  }

  Drupal.behaviors.palefireSectionTabs = {
    attach(context) {
      once('pf-tabs', '[data-pf-tabs]', context).forEach(setup);
      reveal(window.location.hash.slice(1));

      once('pf-tabs-links', 'html').forEach(() => {
        window.addEventListener('hashchange', () => reveal(window.location.hash.slice(1)));
        // Same-page links: open the panel first, so the scroll that follows
        // (the browser's or Swup's) lands on it.
        document.addEventListener('click', (event) => {
          const link = event.target.closest('a[href*="#"]');
          if (!link) {
            return;
          }
          const url = new URL(link.href, window.location.href);
          if (url.pathname === window.location.pathname && url.hash.length > 1) {
            reveal(url.hash.slice(1));
          }
        }, true);
      });
    },
  };
})(Drupal, once);
