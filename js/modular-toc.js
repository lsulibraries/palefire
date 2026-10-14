/**
 * @file
 * Palefire live table of contents for modular_page.
 *
 * Rewrite of the Origami TOC in bento.js. Changes from that version:
 * - Top-level links are rendered on the server (palefire_modular TOC
 *   block), so they exist without JS and the ids are stable. Only the
 *   Guide-mode h3 sub-links are added here.
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
 * - Initial state is set directly. Origami's DOMContentLoaded listener
 *   was registered inside jQuery-ready, after that event had already
 *   fired, so it never ran.
 */
(function (Drupal, once) {
  'use strict';

  const ACTIVATION_LINE = 0.3;
  const SUB_HEADING_SELECTOR = 'h3';
  const SKIP_SELECTOR = '[data-toc-skip]';

  Drupal.behaviors.palefireModularToc = {
    attach(context) {
      once('pf-toc', '[data-pf-toc]', context).forEach(initToc);
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
      if (depth > 1) {
        buildSubList(entry);
      }
      entries.push(entry);
    });
    if (!entries.length) {
      return;
    }

    let active = null;
    let activeSub = null;
    let locked = false;

    /* ---------------- Active state ---------------- */

    function setActive(entry) {
      if (!entry || entry === active) {
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
        detail: { id: entry.section.id, index: entries.indexOf(entry), section: entry.section },
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
        if (locked) {
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
    let indicatorQueued = false;
    function queueIndicator() {
      if (indicatorQueued || !indicator) {
        return;
      }
      indicatorQueued = true;
      requestAnimationFrame(() => {
        indicatorQueued = false;
        placeIndicator();
      });
    }

    function placeIndicator() {
      if (!active) {
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
    });

    function lockUntilScrollEnds(done) {
      locked = true;
      let timer = null;
      const finish = () => {
        window.removeEventListener('scrollend', finish);
        window.removeEventListener('scroll', debounce);
        clearTimeout(timer);
        locked = false;
        done();
      };
      // `scrollend` where supported, otherwise a scroll debounce.
      const debounce = () => {
        clearTimeout(timer);
        timer = setTimeout(finish, 150);
      };
      if ('onscrollend' in window) {
        window.addEventListener('scrollend', finish, { once: true });
      }
      else {
        window.addEventListener('scroll', debounce, { passive: true });
      }
      timer = setTimeout(finish, 1200);
    }

    /* ---------------- Wire up ---------------- */

    observe();
    window.addEventListener('scroll', onScroll, { passive: true });
    let resizeTimer = null;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(observe, 150);
    });
    if ('ResizeObserver' in window) {
      new ResizeObserver(queueIndicator).observe(list);
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
        titleTruncated();
        queueIndicator();
      });
    }
  }

  function entryAtLine(entries) {
    const line = headerHeight() + (window.innerHeight - headerHeight()) * ACTIVATION_LINE;
    return entries.find((entry) => {
      const rect = entry.section.getBoundingClientRect();
      return rect.top <= line && rect.bottom > line;
    });
  }

  /**
   * Guide mode: one sub-link per h3 in the section, ignoring headings in
   * embedded components (bento tiles, carousel slides) marked
   * data-toc-skip.
   */
  function buildSubList(entry) {
    const headings = Array.from(entry.section.querySelectorAll(SUB_HEADING_SELECTOR))
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
   * Default subscriber: expose the active section to CSS on <body>, the
   * same role Origami's [slug][n] / firstSlide / dark-mode classes played.
   *   body[data-section-index="1"]        (was .firstSlide)
   *   body[data-section-theme="dark"]     (was .dark-mode)
   * ---------------------------------------------------------------- */
  document.addEventListener('palefire:sectionchange', (event) => {
    const section = event.detail.section;
    document.body.dataset.sectionIndex = String(event.detail.index + 1);
    document.body.dataset.sectionTheme = section.dataset.sectionTheme || 'light';
    document.body.dataset.activeSection = event.detail.id;
  });
})(Drupal, once);
