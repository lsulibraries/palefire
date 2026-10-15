/**
 * @file
 * Palefire live table of contents for modular_page.
 *
 * Rewrite of the Origami TOC in bento.js. Changes from that version:
 * - Top-level links are rendered on the server (palefire_modular TOC
 *   block), so they exist without JS and the ids are stable. Only the
 *   h3 sub-links are added here: every h3 on modes the block lists under
 *   "Include h3 sub-links on these modes" (depth 2), and on any other mode
 *   just the h3s that opt in with data-toc (the bento search and building
 *   map headings), so those show up whatever the page's mode.
 * - The active section is whichever one crosses a fixed "activation line"
 *   (ACTIVATION_LINE of the viewport below the sticky header). Origami
 *   used threshold 0.6, which a section taller than ~1.67 viewports can
 *   never reach, so tall sections never became active.
 * - No jQuery, no Bootstrap Collapse, no MutationObserver. Sub-lists
 *   expand with CSS, and the indicator moves only when the active link
 *   changes or the list resizes, not on every scroll event.
 * - Indicator: as in Origami, the gold bar covers the whole active item,
 *   meaning the section link plus its open sub-list, so it slides between
 *   sections and grows or shrinks as sub-lists open and close. Origami
 *   re-measured on every scroll and class change, so the bar trailed the
 *   Bootstrap collapse animation. This version works out where the item
 *   will be once the open/close finishes and animates there with the same
 *   duration and easing as the sub-list (--pf-toc-speed / --pf-toc-ease),
 *   so the two move together.
 * - Section changes are announced with a `palefire:sectionchange` event
 *   rather than body-class rewriting, so dark mode, background crossfades
 *   and similar features can subscribe on their own.
 * - Dark chrome (Origami body.dark-mode): while a dark section is active
 *   the TOC sidebar AND the top bar switch to their dark palette. See
 *   applyChromeTheme() at the bottom of this file.
 * - Initial state is set directly. Origami's DOMContentLoaded listener
 *   was registered inside jQuery-ready, after that event had already
 *   fired, so it never ran.
 * - Swup: the TOC detaches cleanly on 'unload' (observers and window
 *   listeners are removed), so a page that has been swapped out can no
 *   longer fire section changes or leave the header dark.
 */
(function (Drupal, once) {
  'use strict';

  const ACTIVATION_LINE = 0.3;
  const SUB_HEADING_SELECTOR = 'h3';
  // Depth 1: only h3s that ask to be listed.
  const OPT_IN_SELECTOR = 'h3[data-toc]';
  const SKIP_SELECTOR = '[data-toc-skip]';

  // nav element -> teardown function (see initToc()).
  const instances = new WeakMap();

  Drupal.behaviors.palefireModularToc = {
    attach(context) {
      once('pf-toc', '[data-pf-toc]', context).forEach((nav) => {
        const teardown = initToc(nav);
        if (teardown) {
          instances.set(nav, teardown);
        }
      });
      // A page without a live TOC (not a modular_page, Form mode, or no
      // titled sections) must not inherit the previous page's dark chrome
      // after a Swup visit. The header lives outside #swup, so nothing else
      // resets it.
      if (!document.querySelector('[data-pf-toc]')) {
        applyChromeTheme(null);
      }
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once.remove('pf-toc', '[data-pf-toc]', context).forEach((nav) => {
        const teardown = instances.get(nav);
        if (teardown) {
          teardown();
        }
        instances.delete(nav);
      });
      // The chrome theme is not reset here: the incoming page sets it on
      // attach, so the header never flashes light between two dark pages.
    },
  };

  function headerHeight() {
    const value = getComputedStyle(document.documentElement).getPropertyValue('--pf-header-h');
    return parseFloat(value) || 0;
  }

  function initToc(nav) {
    const depth = parseInt(nav.dataset.depth || '1', 10);
    const list = nav.querySelector('.pf-toc__list');
    const rail = nav.querySelector('.pf-toc__rail') || list;
    const indicator = nav.querySelector('.pf-toc__indicator');
    const entries = [];

    nav.querySelectorAll('.pf-toc__link[href^="#"]').forEach((link) => {
      const section = document.getElementById(decodeURIComponent(link.hash.slice(1)));
      const li = link.closest('li');
      if (!section) {
        li.hidden = true;
        return;
      }
      const entry = { link: link, li: li, section: section, subs: [], subwrap: null, sublist: null };
      buildSubList(entry, depth > 1 ? SUB_HEADING_SELECTOR : OPT_IN_SELECTOR);
      entries.push(entry);
    });
    if (!entries.length) {
      applyChromeTheme(null);
      return null;
    }

    let active = null;
    let activeSub = null;
    let locked = false;
    let destroyed = false;
    // Removes every window listener added below in one go on teardown.
    const listeners = new AbortController();
    const { signal } = listeners;

    /* ---------------- Active state ---------------- */

    function setActive(entry) {
      if (destroyed || !entry || entry === active) {
        return;
      }
      if (active) {
        active.li.classList.remove('is-active');
        active.link.removeAttribute('aria-current');
        if (active.subwrap) {
          active.subwrap.inert = true;
        }
      }
      active = entry;
      entry.li.classList.add('is-active');
      entry.link.setAttribute('aria-current', 'location');
      if (entry.subwrap) {
        entry.subwrap.inert = false;
      }
      setActiveSub(null);
      queueIndicator();

      document.dispatchEvent(new CustomEvent('palefire:sectionchange', {
        detail: { id: entry.section.id, index: entries.indexOf(entry), section: entry.section, nav: nav },
      }));
    }

    function setActiveSub(sub) {
      if (sub === activeSub) {
        return;
      }
      if (activeSub) {
        activeSub.link.classList.remove('is-active');
        activeSub.link.removeAttribute('aria-current');
      }
      activeSub = sub;
      if (sub) {
        sub.link.classList.add('is-active');
        sub.link.setAttribute('aria-current', 'location');
      }
    }

    /* ---------------- Section tracking ---------------- */

    // A zero-height root at the activation line. A section is active
    // while it spans the line, however tall it is.
    let observer = null;
    function observe() {
      if (destroyed) {
        return;
      }
      if (observer) {
        observer.disconnect();
      }
      const top = headerHeight() + (window.innerHeight - headerHeight()) * ACTIVATION_LINE;
      const bottom = window.innerHeight - top - 1;
      observer = new IntersectionObserver((records) => {
        if (locked) {
          return;
        }
        records.forEach((record) => {
          if (record.isIntersecting) {
            setActive(entries.find((e) => e.section === record.target));
          }
        });
      }, { rootMargin: -top + 'px 0px ' + -bottom + 'px 0px', threshold: 0 });
      entries.forEach((entry) => observer.observe(entry.section));
    }

    // Covers the page edges the line can't reach (a short last section,
    // the very top) and tracks h3s in the active section. A few rect reads
    // per frame for one section's headings is cheap.
    let ticking = false;
    function onScroll() {
      if (ticking) {
        return;
      }
      ticking = true;
      requestAnimationFrame(() => {
        ticking = false;
        if (locked || destroyed) {
          return;
        }
        const doc = document.documentElement;
        if (window.scrollY <= 2) {
          setActive(entries[0]);
        }
        else if (window.innerHeight + window.scrollY >= doc.scrollHeight - 2) {
          setActive(entries[entries.length - 1]);
        }
        trackSubHeadings();
      });
    }

    function trackSubHeadings() {
      if (!active || !active.subs.length) {
        return;
      }
      const line = headerHeight() + (window.innerHeight - headerHeight()) * ACTIVATION_LINE;
      let current = null;
      for (const sub of active.subs) {
        if (sub.heading.getBoundingClientRect().top <= line) {
          current = sub;
        }
        else {
          break;
        }
      }
      setActiveSub(current);
    }

    /* ---------------- Indicator ---------------- */

    // The bar covers the active item (link + open sub-list). Its target
    // is where that item will sit once every sub-list has finished opening
    // or closing, worked out from the current layout:
    //   top    = the item's current top, minus the sub-lists above it that
    //            are still open or closing (they will end at 0).
    //   height = the item without its sub-list, plus the sub-list's full
    //            content height (where it ends once open).
    // The sub-list's grid-rows transition and the bar's transform/height
    // transitions share a duration and easing, so the bar keeps pace with
    // the list. Re-measuring partway through gives the same answer, so
    // ResizeObserver calls during the animation don't change its course.
    //
    // The bar only grows and shrinks when sections have sub-lists: every h3
    // at depth 2 ("Include h3 sub-links on these modes"), only data-toc h3s
    // at depth 1. Without sub-lists every item is one row tall, so the bar
    // only slides.
    let indicatorQueued = false;
    function queueIndicator() {
      if (indicatorQueued || !indicator || destroyed) {
        return;
      }
      indicatorQueued = true;
      requestAnimationFrame(() => {
        indicatorQueued = false;
        placeIndicator();
      });
    }

    function placeIndicator() {
      if (!active || destroyed) {
        return;
      }
      const railRect = rail.getBoundingClientRect();
      if (!railRect.height) {
        // Hidden (display: none). Start over without animating once the
        // TOC is shown again.
        nav.classList.remove('is-ready');
        return;
      }
      const itemRect = active.li.getBoundingClientRect();
      let top = itemRect.top - railRect.top;
      for (const entry of entries) {
        if (entry === active) {
          break;
        }
        if (entry.subwrap) {
          top -= entry.subwrap.getBoundingClientRect().height;
        }
      }
      let height = itemRect.height;
      if (active.subwrap) {
        height += active.sublist.scrollHeight - active.subwrap.getBoundingClientRect().height;
      }

      indicator.style.transform = 'translateY(' + Math.round(top) + 'px)';
      indicator.style.height = Math.round(height) + 'px';

      if (!nav.classList.contains('is-ready')) {
        // First placement: snap into place, then enable transitions.
        // eslint-disable-next-line no-unused-expressions
        indicator.offsetHeight;
        nav.classList.add('is-ready');
      }
    }

    /* ---------------- Clicks ---------------- */

    // The jump itself is a native anchor (CSS smooth scrolling and
    // scroll-margin do the work), so it also works without JS and plays
    // well with scroll-snap. JS marks the link active right away and holds
    // tracking until the scroll settles, so the highlight doesn't flicker
    // through every section in between.
    nav.addEventListener('click', (event) => {
      const link = event.target.closest('a[href^="#"]');
      if (!link || link.classList.contains('pf-toc__top')) {
        return;
      }
      const entry = entries.find((e) => e.link === link || e.subs.some((s) => s.link === link));
      if (!entry) {
        return;
      }
      setActive(entry);
      setActiveSub(entry.subs.find((s) => s.link === link) || null);
      lockUntilScrollEnds(() => {
        const target = document.getElementById(decodeURIComponent(link.hash.slice(1)));
        const heading = target && (target.matches('h2, h3') ? target : target.querySelector('h2'));
        if (heading) {
          heading.focus({ preventScroll: true });
        }
      });
      closeOffcanvas(nav);
    }, { signal });

    function lockUntilScrollEnds(done) {
      locked = true;
      let timer = null;
      const finish = () => {
        window.removeEventListener('scrollend', finish);
        window.removeEventListener('scroll', debounce);
        clearTimeout(timer);
        locked = false;
        if (!destroyed) {
          done();
        }
      };
      // `scrollend` where supported, otherwise a scroll debounce.
      const debounce = () => {
        clearTimeout(timer);
        timer = setTimeout(finish, 150);
      };
      if ('onscrollend' in window) {
        window.addEventListener('scrollend', finish, { once: true, signal });
      }
      else {
        window.addEventListener('scroll', debounce, { passive: true, signal });
      }
      timer = setTimeout(finish, 1200);
    }

    /* ---------------- Wire up ---------------- */

    observe();
    window.addEventListener('scroll', onScroll, { passive: true, signal });
    let resizeTimer = null;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(observe, 150);
    }, { signal });
    let resizeObserver = null;
    if ('ResizeObserver' in window) {
      resizeObserver = new ResizeObserver(queueIndicator);
      resizeObserver.observe(list);
    }

    // Initial state: the URL hash if there is one, otherwise whatever is
    // under the line now. Transitions stay off (no .is-ready on the nav)
    // until the first placement, so the page loads with the bar already
    // in place and the active sub-list already open.
    const hashed = location.hash && entries.find((e) => '#' + e.section.id === location.hash);
    setActive(hashed || entryAtLine(entries) || entries[0]);
    trackSubHeadings();

    // Sub-links are cut to one line, as in Origami. Show the full heading
    // as a tooltip only where the text is actually cut off.
    const titleTruncated = () => entries.forEach((entry) => entry.subs.forEach((sub) => {
      const cut = sub.link.scrollHeight > sub.link.clientHeight + 1;
      sub.link.title = cut ? sub.link.textContent : '';
    }));
    requestAnimationFrame(titleTruncated);
    if (document.fonts && document.fonts.ready) {
      document.fonts.ready.then(() => {
        if (destroyed) {
          return;
        }
        titleTruncated();
        queueIndicator();
      });
    }

    // Swup 'unload': stop tracking a page that is being swapped out.
    return function teardown() {
      destroyed = true;
      listeners.abort();
      clearTimeout(resizeTimer);
      if (observer) {
        observer.disconnect();
      }
      if (resizeObserver) {
        resizeObserver.disconnect();
      }
    };
  }

  function entryAtLine(entries) {
    const line = headerHeight() + (window.innerHeight - headerHeight()) * ACTIVATION_LINE;
    return entries.find((entry) => {
      const rect = entry.section.getBoundingClientRect();
      return rect.top <= line && rect.bottom > line;
    });
  }

  /**
   * One sub-link per matching h3 in the section (all h3s at depth 2,
   * h3[data-toc] at depth 1), ignoring headings in embedded components
   * (bento tiles, carousel slides, the map legend) marked data-toc-skip.
   */
  function buildSubList(entry, selector) {
    const headings = Array.from(entry.section.querySelectorAll(selector))
      .filter((h) => !h.closest(SKIP_SELECTOR) && h.textContent.trim() !== '');
    if (!headings.length) {
      return;
    }

    const wrap = document.createElement('div');
    wrap.className = 'pf-toc__subwrap';
    wrap.inert = true;
    const sub = document.createElement('ol');
    sub.className = 'pf-toc__sub';

    headings.forEach((heading) => {
      if (!heading.id) {
        heading.id = uniqueId(entry.section.id + '-' + slug(heading.textContent));
      }
      if (!heading.hasAttribute('tabindex')) {
        heading.setAttribute('tabindex', '-1');
      }
      const li = document.createElement('li');
      const link = document.createElement('a');
      link.className = 'pf-toc__sublink';
      link.href = '#' + heading.id;
      link.textContent = heading.textContent.trim();
      li.appendChild(link);
      sub.appendChild(li);
      entry.subs.push({ link: link, heading: heading });
    });

    wrap.appendChild(sub);
    entry.li.appendChild(wrap);
    entry.subwrap = wrap;
    entry.sublist = sub;
  }

  function closeOffcanvas(nav) {
    const panel = nav.closest('.offcanvas-lg.show, .offcanvas.show');
    const Offcanvas = window.bootstrap && window.bootstrap.Offcanvas;
    if (panel && Offcanvas) {
      Offcanvas.getOrCreateInstance(panel).hide();
    }
  }

  function slug(text) {
    return String(text).toLowerCase().trim().replace(/[^\w]+/g, '-').replace(/^-+|-+$/g, '') || 'part';
  }

  function uniqueId(base) {
    let id = base;
    for (let i = 2; document.getElementById(id); i++) {
      id = base + '-' + i;
    }
    return id;
  }

  /* ------------------------------------------------------------------
   * Dark chrome: the Origami body.dark-mode port.
   *
   * Origami added .dark-mode to <body> while a dark section was active,
   * and #header plus .modularSidebar restyled themselves from that class.
   * Palefire splits the same job in two:
   *
   *   body[data-section-theme="dark"]   TOC + sidebar colours, through the
   *                                     --pf-toc-* / --pf-sidebar-bg tokens
   *                                     (_modular-tokens.scss).
   *   .pf-header[data-bs-theme="dark"]  Top bar. Bootstrap 5.3's own dark
   *   .pf-modular__sidebar[...]         palette, so menu text, dropdowns,
   *                                     the navbar toggler icon and the
   *                                     mobile offcanvas (and its close
   *                                     button) all switch with no
   *                                     hand-written colours. Glass alpha
   *                                     and hover colour: _chrome-dark.scss.
   *
   * Pass null to reset everything to the light default.
   * ---------------------------------------------------------------- */
  function applyChromeTheme(theme, nav) {
    const dark = theme === 'dark';
    const body = document.body;
    if (theme) {
      body.dataset.sectionTheme = theme;
    }
    else {
      delete body.dataset.sectionTheme;
      delete body.dataset.sectionIndex;
      delete body.dataset.activeSection;
    }
    const sidebar = nav ? nav.closest('.pf-modular__sidebar') : null;
    document.querySelectorAll('.pf-header').forEach((el) => setBsTheme(el, dark));
    if (sidebar) {
      setBsTheme(sidebar, dark);
    }
  }

  function setBsTheme(el, dark) {
    if (dark) {
      el.setAttribute('data-bs-theme', 'dark');
    }
    else {
      el.removeAttribute('data-bs-theme');
    }
  }

  /* ------------------------------------------------------------------
   * Default subscriber: expose the active section to CSS on <body>, the
   * same role Origami's [slug][n] / firstSlide / dark-mode classes played.
   *   body[data-section-index="1"]        (was .firstSlide)
   *   body[data-section-theme="dark"]     (was .dark-mode)
   * ---------------------------------------------------------------- */
  document.addEventListener('palefire:sectionchange', (event) => {
    const section = event.detail.section;
    document.body.dataset.sectionIndex = String(event.detail.index + 1);
    document.body.dataset.activeSection = event.detail.id;
    applyChromeTheme(section.dataset.sectionTheme || 'light', event.detail.nav);
  });
})(Drupal, once);
