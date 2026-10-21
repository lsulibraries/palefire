/**
 * @file
 * Palefire live table of contents for modular_page.
 *
 * Rewrite of the Origami TOC in bento.js. Changes from that version:
 * - Top-level links are rendered on the server (palefire_modular TOC
 *   block), so they exist without JS and the ids are stable. Only the
 *   h3 sub-links are added here: every h3 on modes the block lists under
 *   "Include h3 sub-links on these modes" (depth 2), and on any other mode
 *   just the h3s that opt in with data-toc (the landing search and building
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

  // How far down the viewport (below the header) a section must reach to
  // turn active: three-quarters, on every screen. A section (its TOC link,
  // and on guides its lit heading) comes on as its heading arrives in the
  // lower part of the screen, not once it has climbed to the middle. (Was
  // 0.3, then 0.5 with 0.75 on portrait screens only.)
  const ACTIVATION_LINE = 0.75;
  // Depth 2: every h3, plus anything marked data-toc.
  const SUB_HEADING_SELECTOR = 'h3, [data-toc]';
  // Depth 1: only what asks to be listed (h3[data-toc], or any element with
  // data-toc such as the LibCal day carousel's next events).
  const OPT_IN_SELECTOR = '[data-toc]';
  const SKIP_SELECTOR = '[data-toc-skip]';
  // On the heading of the current section and sub-section, so the page can
  // mirror the TOC (guides colour it like the active link, _guide.scss).
  const CURRENT_HEADING = 'is-toc-current';

  // nav element -> teardown function (see initToc()).
  const instances = new WeakMap();

  // Below lg the TOC is an offcanvas (#pf-modular-sidebar). While it is
  // open, <html data-pf-panel="toc"> gives its backdrop the same page-colour
  // wash as the menu and utility drawers (_utility-panels.scss) instead of
  // Bootstrap's black. iOS Safari tints its bottom toolbar from the fixed
  // layer at the bottom of the screen and ignores that layer's opacity, so
  // the black backdrop painted the toolbar solid black (2026-10-17).
  // Delegated from the document: the offcanvas sits inside Swup's
  // container and is replaced on every visit; Bootstrap's events bubble.
  const TOC_DRAWER = 'pf-modular-sidebar';
  const ROOT = document.documentElement;

  function isTocDrawer(event) {
    return event.target instanceof Element && event.target.id === TOC_DRAWER;
  }

  document.addEventListener('show.bs.offcanvas', (event) => {
    if (isTocDrawer(event)) {
      ROOT.dataset.pfPanel = 'toc';
    }
  });

  document.addEventListener('hidden.bs.offcanvas', (event) => {
    if (isTocDrawer(event) && ROOT.dataset.pfPanel === 'toc') {
      delete ROOT.dataset.pfPanel;
    }
  });

  // A Swup visit can swap the drawer out while it is open, so no
  // hidden.bs.offcanvas fires: drop a flag no open drawer backs up.
  function clearStaleTocFlag() {
    if (ROOT.dataset.pfPanel === 'toc' && !document.querySelector('#' + TOC_DRAWER + '.show, #' + TOC_DRAWER + '.showing')) {
      delete ROOT.dataset.pfPanel;
    }
  }

  Drupal.behaviors.palefireModularToc = {
    attach(context) {
      clearStaleTocFlag();
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
      const entry = { link: link, li: li, section: section, title: section.querySelector('.pf-section__title'), subs: [], subwrap: null, sublist: null };
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
        if (active.title) {
          active.title.classList.remove(CURRENT_HEADING);
        }
        if (active.subwrap) {
          active.subwrap.inert = true;
        }
      }
      active = entry;
      entry.li.classList.add('is-active');
      entry.link.setAttribute('aria-current', 'location');
      if (entry.title) {
        entry.title.classList.add(CURRENT_HEADING);
      }
      if (entry.subwrap) {
        entry.subwrap.inert = false;
      }
      setActiveSub(null);
      queueIndicator();

      // A link can point inside a section (the landing's "Home" and "Map",
      // PalefireHooks::heroTocIds()): the chrome follows the section that
      // holds it, and the index is that section's place on the page.
      const host = entry.section.closest('.pf-section') || entry.section;
      const sections = Array.from(document.querySelectorAll('.pf-sections > .pf-section'));
      const index = sections.includes(host) ? sections.indexOf(host) : entries.indexOf(entry);
      document.dispatchEvent(new CustomEvent('palefire:sectionchange', {
        detail: { id: host.id, index: index, section: host, nav: nav },
      }));
    }

    function setActiveSub(sub) {
      if (sub === activeSub) {
        return;
      }
      if (activeSub) {
        activeSub.link.classList.remove('is-active');
        activeSub.link.removeAttribute('aria-current');
        activeSub.heading.classList.remove(CURRENT_HEADING);
      }
      activeSub = sub;
      if (sub) {
        sub.link.classList.add('is-active');
        sub.link.setAttribute('aria-current', 'location');
        sub.heading.classList.add(CURRENT_HEADING);
      }
      queueIndicator();
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
      // data-toc-passive items (side-by-side carousel events) are listed
      // but never highlighted by scroll position.
      for (const sub of active.subs) {
        if (sub.passive) {
          continue;
        }
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

    // The bar covers only the link for what is on screen: the active h3
    // sub-link when there is one, otherwise the section link. It never
    // spans a whole open sub-list (2026-10-16), which made a tall,
    // unreadable bar on guide pages with many h3s.
    //
    // Its target is where that link will sit once every sub-list has
    // finished opening or closing, worked out from the current layout:
    //   section link: its current top, minus the sub-lists above it that
    //                 are still open or closing (they will end at 0).
    //   sub-link:     the same, plus the sub-list's start below the section
    //                 link, plus the sub-link's offset inside the list. The
    //                 list lays out at full height even while its grid row
    //                 is still opening, so that offset is already final.
    //   height:       the link's own height (one or two lines).
    // The bar's transform/height transitions share the sub-list's duration
    // and easing, so a jump into a newly opened list lands with the list.
    // Re-measuring partway through gives the same answer, so
    // ResizeObserver calls during the animation don't change its course.
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
      const linkRect = active.link.getBoundingClientRect();
      let top = linkRect.top - railRect.top;
      for (const entry of entries) {
        if (entry === active) {
          break;
        }
        if (entry.subwrap) {
          top -= entry.subwrap.getBoundingClientRect().height;
        }
      }
      let height = linkRect.height;
      const sub = activeSub && active.subs.includes(activeSub) ? activeSub : null;
      if (sub && active.sublist) {
        const subRect = sub.link.getBoundingClientRect();
        top += active.subwrap.getBoundingClientRect().top - linkRect.top
          + subRect.top - active.sublist.getBoundingClientRect().top;
        height = subRect.height;
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

    // The links are plain anchors, so without JS they jump as usual. With
    // it, a click doesn't add a history entry: the address still gets the
    // section's #hash (to share or reload), but replaced, so Back leaves the
    // page instead of stepping back through every jump. scrollIntoView()
    // keeps what the anchor did: the CSS smooth scroll (instant with
    // reduced motion) and each section's scroll-margin. JS also marks the
    // link active right away and holds tracking until the scroll settles,
    // so the highlight doesn't flicker through every section in between.
    // Modified clicks (new tab, new window) are left to the browser.
    // The first link (Home on the hero) and "Back to top" do the same
    // thing: the page's very top, and the address without a #hash.
    nav.addEventListener('click', (event) => {
      const link = event.target.closest('a[href^="#"]');
      if (!link) {
        return;
      }
      const plain = event.button === 0 && !(event.metaKey || event.ctrlKey || event.shiftKey || event.altKey);
      if (link.classList.contains('pf-toc__top') || link === entries[0].link) {
        if (plain) {
          event.preventDefault();
          history.replaceState(history.state, '', location.pathname + location.search);
        }
        setActive(entries[0]);
        setActiveSub(null);
        closeOffcanvas(nav);
        if (plain) {
          glideTo(0, () => {});
        }
        else {
          lockUntilScrollEnds(() => {});
        }
        return;
      }
      const entry = entries.find((e) => e.link === link || e.subs.some((s) => s.link === link));
      if (!entry) {
        return;
      }
      const target = document.getElementById(decodeURIComponent(link.hash.slice(1)));
      const jump = target && plain;
      if (jump) {
        event.preventDefault();
        history.replaceState(history.state, '', link.hash);
      }
      setActive(entry);
      setActiveSub(entry.subs.find((s) => s.link === link) || null);
      const arrived = () => {
        const heading = target && (target.matches('h1, h2, h3') ? target : target.querySelector('h2'));
        if (heading) {
          heading.focus({ preventScroll: true });
        }
      };
      if (!jump) {
        lockUntilScrollEnds(arrived);
      }
      closeOffcanvas(nav);
      // After the phone drawer has started closing, as the anchor's own
      // jump did. Worked out here rather than with scrollIntoView(), so
      // every section lands the same way:
      // - Under the header as it will be on arrival. The scroll-padding
      //   is the header's height now: from the top of the page that is the
      //   full header, which then shrinks on the way down, so a section
      //   that isn't a snap point (nothing re-aligns it) stopped lower by
      //   the difference. Past the point where the header compacts
      //   (.pf-header-sentinel, palefire.js) it aims under the compact one.
      // - At its place: a target inside the hero's map (its heading) may be
      //   drawn higher while the map reveals itself (_landing-hero.scss).
      // - An event (Events carousel) lands on its day: its date, weather
      //   and image card, not its row, which sits at the card's foot.
      // Each target's scroll-margin still applies; the scroll itself is the
      // eased glide (glideTo).
      if (jump) {
        const map = target.closest('.pf-landing__visit > .pf-map');
        const by = map ? map.getBoundingClientRect().top - map.parentElement.getBoundingClientRect().top : 0;
        const margin = parseFloat(getComputedStyle(target).scrollMarginTop) || 0;
        // (A day that isn't the slide on screen has no box: the carousel then.)
        const day = target.closest('.libcal-day');
        const place = day ? (day.getClientRects().length ? day : day.closest('.libcal-day-carousel')) : target;
        const at = (head) => window.scrollY + place.getBoundingClientRect().top - by - head - margin;
        const now = parseFloat(getComputedStyle(document.documentElement).scrollPaddingTop) || 0;
        const compact = (Drupal.palefireCompactHeaderHeight && Drupal.palefireCompactHeaderHeight()) || 0;
        const sentinel = document.querySelector('.pf-header-sentinel');
        let top = at(now);
        if (compact && sentinel && at(compact) > sentinel.offsetHeight) {
          top = at(compact);
        }
        glideTo(Math.max(0, top), arrived);
      }
    }, { signal });

    // TOC jumps glide like the landing's snap (Drupal.palefireGlide,
    // palefire.js): one calm ease-in-out instead of the browser's
    // front-loaded smooth scroll, stopped by a wheel, touch or key press.
    // Tracking holds until the glide ends (scrollend would fire after each
    // of its frames), then `done` runs. Without the helper, the browser's
    // smooth scroll and the usual lock.
    function glideTo(top, done) {
      if (!Drupal.palefireGlide) {
        lockUntilScrollEnds(done);
        window.scrollTo({ top });
        return;
      }
      locked = true;
      Drupal.palefireGlide(top, () => {
        locked = false;
        if (!destroyed) {
          done();
        }
      }, { interruptible: true });
    }

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

    // Sub-links are clamped to two lines. Show the full heading as a
    // tooltip only where the text is actually cut off.
    const titleTruncated = () => entries.forEach((entry) => entry.subs.forEach((sub) => {
      const box = sub.link.querySelector('.pf-toc__sublabel') || sub.link;
      const cut = box.scrollWidth > box.clientWidth + 1
        || box.scrollHeight > box.clientHeight + 1;
      sub.link.title = cut ? sub.label : '';
    }));
    requestAnimationFrame(titleTruncated);

    // Line the TOC title up with the breadcrumb (lg and up, where the
    // sidebar sits beside the content). Both start 2rem down in CSS, but
    // the sidebar and <main> don't start at exactly the same height (header
    // spacer, region wrappers), so measure once at the top of the page and
    // nudge the title (and the hide/show button, which reads the same
    // property) by the difference. Re-measured on resize and font load,
    // only while the page is at the top, where the sticky sidebar is in
    // its own place.
    const sidebar = nav.closest('.pf-sidebar');
    const title = nav.querySelector('.pf-toc__heading');
    const wide = window.matchMedia('(min-width: 992px)');
    let nudge = 0;
    const alignTitle = () => {
      // The head's breadcrumb, or the Landing hero's sub-header bar
      // (includes/landing.html.twig), which has it instead.
      const crumb = document.querySelector('.pf-modular__head .breadcrumb-item, .pf-landing__bar .breadcrumb-item')
        || document.querySelector('.pf-modular__head > :first-child');
      if (!sidebar || !title || !crumb || !wide.matches || window.scrollY > 1) {
        return;
      }
      const lineHeight = parseFloat(getComputedStyle(title).lineHeight) || title.getBoundingClientRect().height;
      const crumbBox = crumb.getBoundingClientRect();
      const crumbLine = crumb.matches('h1') ? parseFloat(getComputedStyle(crumb).lineHeight) || crumbBox.height : crumbBox.height;
      const delta = (crumbBox.top + crumbLine / 2) - (title.getBoundingClientRect().top + lineHeight / 2);
      if (Math.abs(delta) < 0.5) {
        return;
      }
      nudge = Math.max(-48, Math.min(48, nudge + delta));
      sidebar.style.setProperty('--pf-toc-title-nudge', nudge.toFixed(1) + 'px');
    };
    requestAnimationFrame(alignTitle);
    if (document.fonts && document.fonts.ready) {
      document.fonts.ready.then(alignTitle);
    }
    window.addEventListener('resize', alignTitle, { passive: true, signal });
    wide.addEventListener('change', alignTitle, { signal });

    // `palefire:tocrefresh`: a component that builds its content after the
    // TOC (the LibCal day carousel fetches its days) asks for its section's
    // sub-list again. Dispatch it on (or inside) the section, bubbling. The
    // new list opens from closed, the same way a list opens on a section
    // change, and the bar grows with it.
    document.addEventListener('palefire:tocrefresh', (event) => {
      const source = event.target instanceof Element ? event.target : null;
      entries.filter((entry) => !source || entry.section.contains(source)).forEach((entry) => {
        if (activeSub && entry.subs.includes(activeSub)) {
          setActiveSub(null);
        }
        if (entry.subwrap) {
          entry.subwrap.remove();
        }
        entry.subs = [];
        entry.subwrap = null;
        entry.sublist = null;
        buildSubList(entry, depth > 1 ? SUB_HEADING_SELECTOR : OPT_IN_SELECTOR);
        const wrap = entry.subwrap;
        if (!wrap) {
          return;
        }
        wrap.inert = entry !== active;
        if (entry === active && nav.classList.contains('is-ready')) {
          wrap.classList.add('is-entering');
          // eslint-disable-next-line no-unused-expressions
          wrap.offsetHeight;
          requestAnimationFrame(() => wrap.classList.remove('is-entering'));
        }
      });
      requestAnimationFrame(titleTruncated);
      trackSubHeadings();
      queueIndicator();
    }, { signal });
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
    // The last match: a target inside another (the landing's "Map" inside
    // its section) wins over the one around it.
    const hits = entries.filter((entry) => {
      const rect = entry.section.getBoundingClientRect();
      return rect.top <= line && rect.bottom > line;
    });
    return hits[hits.length - 1];
  }

  /**
   * One sub-link per matching item in the section (all h3s at depth 2,
   * [data-toc] items at depth 1), ignoring headings in embedded components
   * (landing tiles, carousel slides, the map legend) marked data-toc-skip.
   * An element with data-toc is listed even inside a data-toc-skip area.
   *
   * Optional attributes on a listed element:
   * - data-toc-label: link text (default: the element's text).
   * - data-toc-detail: extra text for screen readers, e.g. a date.
   * - data-toc-passive: listed, but not highlighted by scroll position.
   */
  function tocLabel(el) {
    return (el.dataset.tocLabel || el.textContent).replace(/\s+/g, ' ').trim();
  }

  function buildSubList(entry, selector) {
    const headings = Array.from(entry.section.querySelectorAll(selector))
      .filter((h) => (h.hasAttribute('data-toc') || !h.closest(SKIP_SELECTOR)) && tocLabel(h) !== '');
    if (!headings.length) {
      return;
    }

    const wrap = document.createElement('div');
    wrap.className = 'pf-toc__subwrap';
    wrap.inert = true;
    const sub = document.createElement('ol');
    sub.className = 'pf-toc__sub';

    headings.forEach((heading) => {
      const label = tocLabel(heading);
      if (!heading.id) {
        heading.id = uniqueId(entry.section.id + '-' + slug(label));
      }
      if (!heading.hasAttribute('tabindex')) {
        heading.setAttribute('tabindex', '-1');
      }
      const li = document.createElement('li');
      const link = document.createElement('a');
      link.className = 'pf-toc__sublink';
      link.href = '#' + heading.id;
      // The label gets its own span so the two-line clamp (_toc.scss)
      // doesn't touch the link's padding or the visually-hidden detail.
      const text = document.createElement('span');
      text.className = 'pf-toc__sublabel';
      text.textContent = label;
      link.appendChild(text);
      if (heading.dataset.tocDetail) {
        const detail = document.createElement('span');
        detail.className = 'visually-hidden';
        detail.textContent = ', ' + heading.dataset.tocDetail;
        link.appendChild(detail);
      }
      li.appendChild(link);
      sub.appendChild(li);
      entry.subs.push({ link: link, heading: heading, label: label, passive: heading.hasAttribute('data-toc-passive') });
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
    // With a reading swatch (<html data-pf-theme>, and every guide) each
    // section is the swatch's colour, so the chrome follows the swatch
    // (dark ones: html.pf-reading-dark) instead of the section.
    const root = document.documentElement;
    const themed = root.hasAttribute('data-pf-theme') || root.getAttribute('data-pf-mode') === 'guide';
    const dark = themed ? root.classList.contains('pf-reading-dark') && theme !== null : theme === 'dark';
    const body = document.body;
    if (theme) {
      body.dataset.sectionTheme = dark ? 'dark' : (themed ? 'light' : theme);
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
  // "light-map" sections (field_section_theme "Light, dark at the map") are
  // light until their building map is in view, then dark: building-map.js
  // adds .is-map-dark to the section and sends palefire:mapview, the same
  // signal that dims the landing's background video.
  function sectionTheme(section) {
    const theme = section.dataset.sectionTheme || 'light';
    if (theme === 'light-map') {
      return section.classList.contains('is-map-dark') ? 'dark' : 'light';
    }
    return theme;
  }
  let activeNav = null;

  document.addEventListener('palefire:sectionchange', (event) => {
    const section = event.detail.section;
    activeNav = event.detail.nav;
    document.body.dataset.sectionIndex = String(event.detail.index + 1);
    document.body.dataset.activeSection = event.detail.id;
    applyChromeTheme(sectionTheme(section), event.detail.nav);
  });

  document.addEventListener('palefire:mapview', (event) => {
    const section = event.detail.section;
    if (section && section.id && document.body.dataset.activeSection === section.id) {
      applyChromeTheme(sectionTheme(section), activeNav);
    }
  });

  // Reading colours changed in Settings (dark on or off): re-apply to the active
  // section.
  document.addEventListener('palefire:a11ychange', () => {
    const id = document.body.dataset.activeSection;
    const section = id ? document.getElementById(id) : null;
    if (section) {
      applyChromeTheme(sectionTheme(section), activeNav);
    }
  });
})(Drupal, once);
