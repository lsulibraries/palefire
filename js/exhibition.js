/**
 * @file
 * Exhibition pages (<html data-pf-mode="exhibition">): one gallery at a
 * time, the title page, the object view, long biographies and audio.
 *
 * - Galleries: each exhibit_page section is a "gallery". Only the current
 *   one is shown; the rest are [hidden]. The TOC links, "Begin", the
 *   previous / next links added at each gallery's foot, and any #link in
 *   the text (an entry on another gallery, a source) fade the current
 *   gallery out and the target's gallery in, then glide to the target.
 *   Gallery changes add a history entry (#gallery), so Back steps back
 *   through them; jumps inside a gallery replace it.
 * - Title page: the page head (includes/exhibit-cover.html.twig). While a
 *   gallery is open it folds to a sticky running head (.is-folded, the
 *   breadcrumb bar) that keeps the
 *   <h1>, and is marked data-toc-inactive so the TOC doesn't light "Title
 *   page" (js/modular-toc.js).
 * - Object view: one full-screen <dialog> for every image. A wall's entry
 *   (a gallery set to Display "wall") shows its image beside its text; an
 *   image in the text shows beside its caption, stepping through the
 *   gallery's images. Zoom and a button (or I) that folds the text away
 *   are on the stage; Esc or Close closes it and focus goes back.
 * - Biographies longer than about ten lines show their opening lines and
 *   a "Read the full biography" button.
 * - Audio: the browser's controls are swapped for a play button, a seek
 *   slider and the time.
 *
 * Without this script every gallery shows, one after another, and image
 * links open the image itself. Reduced motion (the OS setting or Settings
 * > Reduce motion) makes every change instant.
 */
(function (Drupal, once) {
  'use strict';

  const ROOT = document.documentElement;
  const FADE_OUT = 280;
  const FADE_IN = 700;

  function reduceMotion() {
    return ROOT.classList.contains('pf-a11y-motion') || window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function isExhibition() {
    return ROOT.dataset.pfMode === 'exhibition';
  }

  /* ---------------------------------------------------------------------
   * Galleries
   * ------------------------------------------------------------------ */

  const state = { head: null, pages: [], current: null, busy: false, crumb: null, zoom: null };

  // The gallery (or the title page, null) that holds an element.
  function galleryOf(el) {
    return el ? state.pages.find((page) => page.contains(el)) || null : null;
  }

  function targetOf(hash) {
    if (!hash || hash.length < 2) {
      return null;
    }
    try {
      return document.getElementById(decodeURIComponent(hash.slice(1)));
    }
    catch (e) {
      return null;
    }
  }

  function setFolded(folded) {
    state.head.classList.toggle('is-folded', folded);
    state.head.toggleAttribute('data-toc-inactive', folded);
    measureHead();
  }

  /* ---------------------------------------------------------------------
   * Breadcrumb: Home / AVES on the title page, Home / AVES / Preface while
   * a gallery is open, with AVES then a link back to the title page. The
   * page's crumb shows the full title (title, colon, subtitle) on hover.
   * ------------------------------------------------------------------ */

  function initCrumb() {
    const list = state.head.querySelector('.pf-landing__bar .breadcrumb, .pf-breadcrumb .breadcrumb');
    const page = list ? list.lastElementChild : null;
    if (!page) {
      return;
    }
    const h1 = state.head.querySelector('h1');
    const full = h1 ? h1.textContent.replace(/\s+/g, ' ').trim() : '';
    const gallery = document.createElement('li');
    gallery.className = 'breadcrumb-item active';
    gallery.setAttribute('aria-current', 'page');
    state.crumb = {
      list: list,
      page: page,
      html: page.innerHTML,
      label: page.textContent.replace(/\s+/g, ' ').trim(),
      full: full,
      gallery: gallery,
    };
    if (full) {
      page.title = full;
    }
  }

  function updateCrumb(gallery) {
    const c = state.crumb;
    if (!c) {
      return;
    }
    if (!gallery) {
      // The title page: the page's crumb is the current one again.
      c.gallery.remove();
      c.page.innerHTML = c.html;
      c.page.classList.add('active');
      c.page.setAttribute('aria-current', 'page');
      return;
    }
    if (!c.page.querySelector('a[data-pf-crumb-home]')) {
      const link = document.createElement('a');
      link.href = '#' + state.head.id;
      link.setAttribute('data-pf-crumb-home', '');
      link.textContent = c.label;
      c.page.replaceChildren(link);
      c.page.classList.remove('active');
      c.page.removeAttribute('aria-current');
    }
    const heading = gallery.querySelector('.pf-section__title');
    // In a <span>, like the page's own crumb, so a long title can end in an
    // ellipsis on one line (_layout.scss).
    const name = document.createElement('span');
    name.textContent = heading ? heading.textContent.replace(/\s+/g, ' ').trim() : '';
    c.gallery.replaceChildren(name);
    if (!c.gallery.isConnected) {
      c.list.append(c.gallery);
    }
  }

  // The folded head's height, so a gallery's full-screen opening fills the
  // screen under it (--pf-hero-h, _exhibition.scss).
  function measureHead() {
    if (!state.head) {
      return;
    }
    const folded = state.head.classList.contains('is-folded');
    ROOT.style.setProperty('--pf-exhibit-head-h', folded ? Math.round(state.head.getBoundingClientRect().height) + 'px' : '0px');
  }

  /* ---------------------------------------------------------------------
   * Zoom button: at the end of the breadcrumb bar, opens the image that
   * opens the view (the title page's cover, a gallery's lead) full screen
   * in the lightbox, with its caption when it has one. It shows while that
   * image is in sight under the bar and fades once it has scrolled away
   * or the text has covered it.
   * ------------------------------------------------------------------ */

  function initZoom() {
    const row = state.head.querySelector('.pf-landing__bar-inner');
    if (!row || state.zoom) {
      return;
    }
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'pf-exhibit-zoom';
    button.innerHTML = '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="square" aria-hidden="true" focusable="false"><path d="M9.5 2.5h4v4M13.5 2.5 9 7M6.5 13.5h-4v-4M2.5 13.5 7 9"/></svg>'
      + '<span class="visually-hidden">' + Drupal.t('View the image full screen') + '</span>';
    button.addEventListener('click', () => {
      const source = zoomSource();
      if (source) {
        openPictures(source.link, source.group, button);
      }
    });
    row.append(button);
    state.zoom = button;
    window.addEventListener('scroll', queueZoom, { passive: true });
  }

  // The image the view opens with: [the element to watch, its <img>].
  function openingImage() {
    const page = state.current;
    const frame = page
      ? page.querySelector('.pf-exhibit-hero__stage, .pf-exhibit-lead__frame, .pf-exhibit-wall-hero')
      : state.head.querySelector('.pf-exhibit-cover__media');
    const img = frame ? frame.querySelector('img') : null;
    return img ? [frame, img] : null;
  }

  // A lightbox link for the opening image: the image's own link when it has
  // one (a captioned lead), else one made for the purpose, with the largest
  // size in its srcset. The group is the gallery's images (none on the title
  // page).
  function zoomSource() {
    const found = openingImage();
    if (!found) {
      return null;
    }
    const img = found[1];
    let link = img.closest('a[data-pf-lightbox]');
    if (!link) {
      link = document.createElement('a');
      const sizes = (img.getAttribute('srcset') || '').split(',').map((part) => part.trim().split(/\s+/));
      const largest = sizes.reduce((best, part) => (parseInt(part[1], 10) || 0) > (parseInt(best[1], 10) || 0) ? part : best, [img.currentSrc || img.src, '0w']);
      link.href = largest[0];
      if (img.getAttribute('width')) {
        link.dataset.width = img.getAttribute('width');
        link.dataset.height = img.getAttribute('height');
      }
      link.append(img.cloneNode(false));
    }
    return { link: link, group: state.current ? null : [link] };
  }

  let zoomQueued = false;
  function queueZoom() {
    if (zoomQueued) {
      return;
    }
    zoomQueued = true;
    requestAnimationFrame(() => {
      zoomQueued = false;
      updateZoom();
    });
  }

  // In sight: more than about two lines' worth of the image shows between
  // the bar and whichever comes first, the image's foot or the top of the
  // text sliding over it.
  function updateZoom() {
    const button = state.zoom;
    if (!button) {
      return;
    }
    const found = openingImage();
    let shown = false;
    if (found && found[0].getClientRects().length) {
      const bar = button.closest('.pf-landing__bar').getBoundingClientRect().bottom;
      let bottom = Math.min(found[0].getBoundingClientRect().bottom, window.innerHeight);
      const layer = state.current ? state.current.querySelector('.pf-gallery__body') : null;
      if (layer && found[0].matches('.pf-exhibit-hero__stage')) {
        bottom = Math.min(bottom, layer.getBoundingClientRect().top);
      }
      // The title page's cover runs past the head, which clips it.
      if (!state.current) {
        bottom = Math.min(bottom, state.head.getBoundingClientRect().bottom);
      }
      shown = bottom - bar > 96;
    }
    button.classList.toggle('is-shown', shown);
    button.tabIndex = shown ? 0 : -1;
  }

  // The full-screen opening's pan (_exhibition.scss): how far the page
  // scrolls while the image stays pinned and tours itself. It is how much
  // the image overflows the screen when it covers it (a tall plate at full
  // width, a wide photograph at full height), at least a third of a screen
  // so the heading has room to leave, and at most 1.6 screens. Only from
  // lg, with motion and scroll-driven animations; otherwise no pin.
  const wide = window.matchMedia('(min-width: 992px)');
  const canPan = () => wide.matches && !reduceMotion() && window.CSS && CSS.supports('animation-timeline: scroll()');

  function sizeHero(page) {
    const hero = page && page.querySelector('[data-pf-hero]');
    const inner = hero && hero.closest('.pf-section__inner');
    if (!inner) {
      return;
    }
    const ratio = parseFloat(hero.dataset.ratio) || 0;
    if (!ratio || !canPan()) {
      inner.style.removeProperty('--pf-pan');
      return;
    }
    const w = hero.getBoundingClientRect().width;
    const h = hero.querySelector('.pf-exhibit-hero__stage').getBoundingClientRect().height;
    const overflow = Math.max(w * ratio - h, h / ratio - w);
    inner.style.setProperty('--pf-pan', Math.round(Math.min(Math.max(overflow, h / 3), h * 1.6)) + 'px');
  }

  function onResize() {
    measureHead();
    sizeHero(state.current);
    if (state.current) {
      foldIntro(state.current);
    }
    queueZoom();
  }

  // Show one gallery (or the title page) at once, without animation.
  function place(page) {
    state.pages.forEach((p) => {
      p.hidden = p !== page;
    });
    setFolded(page !== null);
    updateCrumb(page);
    state.current = page;
    if (page) {
      prepareGallery(page);
      sizeHero(page);
    }
    queueZoom();
  }

  function headerOffset() {
    const css = getComputedStyle(ROOT);
    return (parseFloat(css.scrollPaddingTop) || parseFloat(css.getPropertyValue('--pf-header-h')) || 0) + 16;
  }

  function glideTo(el, done) {
    const at = () => Math.max(0, window.scrollY + el.getBoundingClientRect().top - headerOffset());
    if (reduceMotion() || !Drupal.palefireGlide) {
      window.scrollTo({ top: at(), behavior: 'instant' });
      done();
      return;
    }
    // The header compacts on the way down (palefire.js), which moves the
    // page under it: measure again on arrival and finish the last stretch.
    Drupal.palefireGlide(at(), () => {
      if (Math.abs(at() - window.scrollY) > 3) {
        Drupal.palefireGlide(at(), done, { interruptible: true });
        return;
      }
      done();
    }, { interruptible: true });
  }

  function focusOn(el) {
    const heading = el.matches('h1, h2, h3, h4, li, .pf-exhibit-head')
      ? el
      : el.querySelector('.pf-section__title') || el;
    if (heading.matches('.pf-exhibit-head')) {
      const h1 = heading.querySelector('h1');
      if (h1) {
        h1.setAttribute('tabindex', '-1');
        h1.focus({ preventScroll: true });
      }
      return;
    }
    if (!heading.hasAttribute('tabindex') && !heading.matches('a, button, input, select, textarea')) {
      heading.setAttribute('tabindex', '-1');
    }
    heading.focus({ preventScroll: true });
  }

  // A heading or a source a link went to flashes once it is reached.
  function flash(el) {
    if (reduceMotion() || !el.matches('h3, h4, li')) {
      return;
    }
    el.classList.remove('is-target');
    // eslint-disable-next-line no-unused-expressions
    el.offsetWidth;
    el.classList.add('is-target');
    el.addEventListener('animationend', () => el.classList.remove('is-target'), { once: true });
  }

  function tellToc() {
    // modular-toc.js re-reads the page edges on scroll.
    window.dispatchEvent(new Event('scroll'));
  }

  /**
   * Goes to an element: switches gallery if needed, then glides to it.
   *
   * @param {Element} target
   *   The element to show (a gallery, an entry heading, the head...).
   * @param {Object} options
   *   - history: 'push', 'replace' or 'none'.
   *   - then: runs on arrival instead of the glide (opening a wall's entry).
   */
  function go(target, { history: mode = 'push', then = null } = {}) {
    if (state.busy) {
      return;
    }
    // An entry on a wall opens in the object view, in its gallery.
    const entry = then ? null : wallEntry(target);
    if (entry) {
      const gallery = galleryOf(entry);
      if (gallery === state.current) {
        openObject(entry, true);
        return;
      }
      closeObject(false, true);
      go(gallery, { history: mode, then: () => openObject(entry, false) });
      return;
    }
    closeObject(false, true);
    const page = galleryOf(target);
    const top = target === state.head || target === page;
    const url = urlFor(target);

    if (page === state.current) {
      if (mode !== 'none') {
        history.replaceState(history.state, '', url);
      }
      if (top) {
        glideTo(page || state.head, () => focusOn(target));
        return;
      }
      glideTo(target, () => {
        focusOn(target);
        flash(target);
      });
      return;
    }

    if (mode === 'push') {
      history.pushState({ pfExhibit: url }, '', url);
    }
    else if (mode === 'replace') {
      history.replaceState({ pfExhibit: url }, '', url);
    }

    const arrive = () => {
      if (then) {
        then();
        tellToc();
        return;
      }
      if (top) {
        focusOn(target);
        tellToc();
        return;
      }
      glideTo(target, () => {
        focusOn(target);
        flash(target);
        tellToc();
      });
    };

    if (reduceMotion()) {
      place(page);
      window.scrollTo({ top: 0, behavior: 'instant' });
      arrive();
      return;
    }

    // Fade the current view out, swap while nothing shows, fade in.
    state.busy = true;
    const leaving = state.current || state.head;
    leaving.classList.add('is-leaving');
    window.setTimeout(() => {
      leaving.classList.remove('is-leaving');
      const entering = page || state.head;
      entering.classList.add('is-entering');
      place(page);
      window.scrollTo({ top: 0, behavior: 'instant' });
      // Two frames: the hidden → shown change paints first, so the
      // transition (and the lead's curtain) run from their start.
      requestAnimationFrame(() => requestAnimationFrame(() => {
        entering.classList.remove('is-entering');
        entering.classList.add('is-revealing');
        window.setTimeout(() => entering.classList.remove('is-revealing'), FADE_IN + 900);
        state.busy = false;
        arrive();
      }));
    }, FADE_OUT);
  }

  // The previous / next links at a gallery's foot, built once.
  function addPager(page) {
    const index = state.pages.indexOf(page);
    const nav = document.createElement('nav');
    nav.className = 'pf-exhibit-pager';
    nav.setAttribute('aria-label', Drupal.t('Galleries'));
    const link = (to, rel, label) => {
      const a = document.createElement('a');
      a.className = 'pf-exhibit-pager__link pf-exhibit-pager__link--' + rel;
      a.href = '#' + to.id;
      a.rel = rel;
      const kicker = document.createElement('span');
      kicker.className = 'pf-exhibit-pager__kicker';
      kicker.textContent = label;
      const title = document.createElement('span');
      title.className = 'pf-exhibit-pager__title';
      const heading = to.querySelector('.pf-section__title, h1');
      title.textContent = heading ? heading.textContent.replace(/\s+/g, ' ').trim() : '';
      a.append(kicker, title);
      return a;
    };
    nav.append(index > 0
      ? link(state.pages[index - 1], 'prev', Drupal.t('Previous gallery'))
      : link(state.head, 'prev', Drupal.t('Home')));
    if (index < state.pages.length - 1) {
      nav.append(link(state.pages[index + 1], 'next', Drupal.t('Next gallery')));
    }
    page.querySelector('.pf-section__inner').append(nav);
  }

  function prepareGallery(page) {
    if (page.dataset.pfExhibitReady) {
      return;
    }
    page.dataset.pfExhibitReady = '1';
    addPager(page);
    // Measured now that the gallery is shown (hidden ones have no size).
    page.querySelectorAll('[data-pf-exhibit-bio]').forEach(initBio);
    foldIntro(page);
  }

  function onClick(event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }
    const link = event.target instanceof Element ? event.target.closest('a[href^="#"], a[data-pf-exhibit-begin]') : null;
    if (!link) {
      return;
    }
    if (link.hasAttribute('data-pf-exhibit-begin')) {
      event.preventDefault();
      go(state.pages[0]);
      return;
    }
    const target = targetOf(link.hash);
    if (!target) {
      return;
    }
    // Tiles, TOC sub-links and cross references to a wall's entries.
    if (wallEntry(target)) {
      event.preventDefault();
      event.stopPropagation();
      closeToc();
      go(target, { history: galleryOf(target) === state.current ? 'replace' : 'push' });
      return;
    }
    const page = galleryOf(target);
    const toHead = target === state.head;
    // Same gallery: the TOC scrolls as on other pages, except the folded
    // running head, which means "open the title page". Other links in the
    // gallery (the opening's entry links, "View the whole image", "Skip to
    // the text", cross references) glide there too.
    if (page === state.current && !(toHead && state.current)) {
      if (page && !link.closest('[data-pf-toc]')) {
        event.preventDefault();
        go(target, { history: 'replace' });
      }
      return;
    }
    if (!page && !toHead && !state.head.contains(target)) {
      return;
    }
    event.preventDefault();
    event.stopPropagation();
    closeToc();
    go(toHead || !page ? state.head : target);
  }

  function closeToc() {
    const panel = document.querySelector('#pf-modular-sidebar.show');
    const Offcanvas = window.bootstrap && window.bootstrap.Offcanvas;
    if (panel && Offcanvas) {
      Offcanvas.getOrCreateInstance(panel).hide();
    }
  }

  // The address for a target: the page, plus #anchor for a gallery or
  // anything in one.
  function urlFor(target) {
    return location.pathname + location.search + (target === state.head ? '' : '#' + target.id);
  }

  function onPopState() {
    const target = targetOf(location.hash);
    go(target && (galleryOf(target) || state.head.contains(target)) ? target : state.head, { history: 'none' });
  }

  // Before the first paint where possible: this runs as the script loads,
  // ahead of every behavior (the TOC reads what is shown when it starts).
  function start() {
    const head = document.querySelector('.pf-exhibit-head');
    if (!isExhibition() || !head || state.head === head) {
      return;
    }
    state.head = head;
    state.pages = Array.from(document.querySelectorAll('.pf-sections > .pf-section'))
      .filter((section) => section.querySelector(':scope > .pf-section__inner > [data-pf-exhibit-page]'));
    if (!state.pages.length) {
      return;
    }
    ROOT.classList.add('pf-exhibit-paged');
    // An address for a gallery: its opening image first and at once (it is
    // lazy in a hidden gallery, so it would wait until shown), ahead of the
    // set-up below.
    const target = targetOf(location.hash);
    const page = galleryOf(target);
    const lead = page && page.querySelector('.pf-exhibit-wall-hero__img, .pf-exhibit-hero__img, .pf-exhibit-lead__img');
    if (lead) {
      // Priority first: the request starts as soon as it is eager.
      lead.fetchPriority = 'high';
      lead.loading = 'eager';
    }
    // "06 / 19" above each gallery's heading (_exhibition.scss).
    state.pages.forEach((page, i) => {
      page.style.setProperty('--pf-gallery-index', String(i + 1));
      page.style.setProperty('--pf-gallery-total', String(state.pages.length));
    });
    initCrumb();
    initZoom();
    initWalls();
    initCovers();
    initEntryLinks();
    place(page);
    // The title page's cover waited for this (exhibit-cover.html.twig):
    // folded away for a gallery, it loads lazily if the title page opens.
    ROOT.classList.remove('pf-exhibit-deep');
    const entry = wallEntry(target);
    if (entry) {
      // An address for a wall's entry: its gallery, with the entry open.
      requestAnimationFrame(() => openObject(entry, false));
    }
    else if (page && target !== page) {
      // A link to an entry or a source: there once the page has laid out.
      requestAnimationFrame(() => target.scrollIntoView({ block: 'start' }));
    }
    const actions = head.querySelector('[data-pf-exhibit-actions]');
    if (actions) {
      actions.hidden = false;
    }
    // Capture, ahead of the TOC's own click handler.
    document.addEventListener('click', onClick, true);
    window.addEventListener('popstate', onPopState);
    window.addEventListener('resize', onResize, { passive: true });
    window.addEventListener('scroll', queueCover, { passive: true });
    // Settings > Reduce motion turns the pin off and on.
    document.addEventListener('palefire:a11ychange', onResize);
  }

  /* ---------------------------------------------------------------------
   * Wall and object view
   *
   * A gallery set to Display "wall" (paragraph--exhibit-page.html.twig)
   * prints its entries as usual and a [hidden] wall of tiles after them.
   * Here the wall shows and the entries fold away (.is-walled): they stay
   * in the page as the object view's text. A tile, a link to an entry (TOC,
   * cross reference, Back) or the address opens the entry in the object
   * view: a full-screen <dialog> with the image (or a drawing and its proof
   * on a slider) beside the entry's caption and text. Arrows, swipe and
   * the buttons step through the wall in its current order; Esc or Close
   * returns to the tile. Where the browser has view transitions, the image
   * grows out of its tile and back, and re-sorting slides the tiles.
   * ------------------------------------------------------------------ */

  // quiet: closing for another route (Back, a TOC jump), which sets the
  // address itself. loose: the view is showing images from the text
  // (openPictures) rather than a wall's entry.
  const wallState = { view: null, tiles: [], index: 0, tile: null, quiet: false, compare: 'wipe', loose: null };

  // The text panel folded away (the image full width), kept across visits.
  const FULL_KEY = 'pf-object-full';
  function savedFull() {
    try {
      return window.localStorage.getItem(FULL_KEY) === '1';
    }
    catch (e) {
      return false;
    }
  }

  function canMorph() {
    return !reduceMotion() && typeof document.startViewTransition === 'function';
  }

  // The entry an element belongs to, when that entry is on a wall.
  function wallEntry(el) {
    const entry = el && el.closest ? el.closest('.pf-exhibit-entry') : null;
    return entry && entry.closest('.pf-gallery.is-walled') ? entry : null;
  }

  function tileFor(entry) {
    const heading = entry.querySelector('.pf-exhibit-entry__title');
    const page = galleryOf(entry);
    return heading && page ? page.querySelector('[data-pf-tile="' + CSS.escape(heading.id) + '"]') : null;
  }

  // A cover opening: the gallery's opening text (its untitled text blocks
  // before anything else) moves onto the image under the entry links,
  // folded like a wall's intro.
  function initCovers() {
    state.pages.forEach((page) => {
      const body = page.querySelector('.pf-gallery--cover > .pf-gallery__body');
      if (!body) {
        return;
      }
      const blocks = [];
      for (const el of body.children) {
        if (!el.matches('.pf-guide-block--text') || el.querySelector(':scope > .pf-guide-block__title')) {
          break;
        }
        blocks.push(el);
      }
      if (blocks.length) {
        const text = document.createElement('div');
        text.className = 'pf-exhibit-wall__text';
        const intro = document.createElement('div');
        intro.className = 'pf-exhibit-wall__intro pf-guide';
        intro.id = page.id + '-intro';
        intro.append(...blocks);
        text.append(intro);
        body.before(text);
      }
    });
  }

  // "In this gallery": the entries after the sixth fold behind "Show more"
  // (the template prints them all, for a page without the script).
  function initEntryLinks() {
    document.querySelectorAll('.pf-exhibit-opener__links').forEach((list) => {
      const more = list.querySelectorAll('[data-pf-more]');
      if (!more.length) {
        return;
      }
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'pf-exhibit-bio__toggle pf-exhibit-opener__toggle';
      button.setAttribute('aria-controls', list.id);
      const sync = (open) => {
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        button.textContent = open ? Drupal.t('Show less') : Drupal.t('Show @count more', { '@count': more.length });
        more.forEach((item) => {
          item.hidden = !open;
        });
      };
      sync(false);
      button.addEventListener('click', () => {
        sync(button.getAttribute('aria-expanded') !== 'true');
        // A cover's text moved down or up: its fade's place follows.
        const page = list.closest('.pf-section');
        if (page) {
          foldIntro(page);
        }
      });
      list.after(button);
    });
  }

  function initWalls() {
    state.pages.forEach((page) => {
      const wall = page.querySelector('[data-pf-wall]');
      if (!wall) {
        return;
      }
      page.querySelector('.pf-gallery').classList.add('is-walled');
      wall.hidden = false;
      // The gallery's own text (not its entries) as one block: the wall
      // text beside the title (_exhibition.scss), folded once shown.
      const body = wall.parentElement;
      const blocks = Array.from(body.children).filter((el) => el !== wall && !el.matches('.pf-exhibit-entry, .pf-exhibit-body-title'));
      if (blocks.length) {
        // .pf-exhibit-wall__text holds the intro and, once folded, its
        // "Read more" button.
        const text = document.createElement('div');
        text.className = 'pf-exhibit-wall__text';
        const intro = document.createElement('div');
        intro.className = 'pf-exhibit-wall__intro';
        intro.id = page.id + '-intro';
        blocks[0].before(text);
        intro.append(...blocks);
        text.append(intro);
      }
      // Not in the TOC: the wall is the gallery's index, and dozens of
      // entries made the TOC a long scroll (js/modular-toc.js skips them).
      page.querySelectorAll('.pf-exhibit-entry').forEach((entry) => entry.setAttribute('data-toc-skip', ''));
      const buttons = wall.querySelectorAll('[data-pf-sort]');
      if (buttons.length) {
        sortWall(wall, 'plate', false);
        buttons.forEach((button) => button.addEventListener('click', () => sortWall(wall, button.dataset.pfSort, true)));
      }
    });
  }

  // How much of a wall's or cover's intro shows folded: about four lines,
  // or from lg over a lead, as many whole lines as fill the image's screen
  // (about 78% of it, under the title, facts or links, and button).
  function introLimit(page, intro) {
    const text = intro.querySelector('.pf-guide-prose') || intro;
    const line = parseFloat(getComputedStyle(text).lineHeight) || 26;
    const frame = page.querySelector('.pf-exhibit-wall-hero__frame');
    if (!frame || !wide.matches) {
      return line * 4;
    }
    const outer = (el) => {
      if (!el) {
        return 0;
      }
      const cs = getComputedStyle(el);
      return el.getBoundingClientRect().height + (parseFloat(cs.marginTop) || 0) + (parseFloat(cs.marginBottom) || 0);
    };
    const wrap = intro.parentElement;
    // The rule of thirds: the heading sits on the lower third line and the
    // stats, the text and "Read more" fill the third below it.
    const room = frame.getBoundingClientRect().height / 3
      - outer(page.querySelector('.pf-exhibit-wall__stats, .pf-exhibit-cover__nav'))
      - (parseFloat(getComputedStyle(wrap).paddingBottom) || 0)
      - line * 2.25;
    return Math.max(4, Math.floor(room / line)) * line;
  }

  // A wall's intro longer than its fold shows that much, with "Read more",
  // so the wall starts soon after. Measured once shown, again on resize.
  function foldIntro(page) {
    const intro = page.querySelector('.pf-exhibit-wall__intro');
    if (!intro) {
      return;
    }
    if (page.querySelector('.pf-gallery--cover')) {
      measureCover(page, intro);
      return;
    }
    if (intro.dataset.pfFoldReady) {
      // A resize: a new fold for a folded intro.
      if (intro.classList.contains('is-folded')) {
        intro.style.setProperty('--pf-intro-clip', Math.round(introLimit(page, intro)) + 'px');
      }
      return;
    }
    intro.dataset.pfFoldReady = '1';
    const limit = introLimit(page, intro);
    if (intro.scrollHeight < limit * 1.3) {
      return;
    }
    intro.style.setProperty('--pf-intro-clip', Math.round(limit) + 'px');
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'pf-exhibit-bio__toggle pf-exhibit-wall__more';
    button.setAttribute('aria-controls', intro.id);
    const sync = (open) => {
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
      button.textContent = open ? Drupal.t('Show less') : Drupal.t('Read more');
      intro.classList.toggle('is-folded', !open);
    };
    sync(false);
    button.addEventListener('click', () => {
      const open = intro.classList.contains('is-folded');
      intro.style.maxHeight = intro.getBoundingClientRect().height + 'px';
      sync(open);
      if (reduceMotion()) {
        intro.style.maxHeight = '';
        return;
      }
      requestAnimationFrame(() => {
        intro.style.maxHeight = (open ? intro.scrollHeight : introLimit(page, intro)) + 'px';
        const end = () => {
          intro.style.maxHeight = '';
          intro.removeEventListener('transitionend', end);
        };
        intro.addEventListener('transitionend', end);
      });
    });
    intro.after(button);
  }

  // A cover's text is never folded: all of it is there, the lines at the
  // first screen's foot faded (--pf-fold, in the intro's own coordinates)
  // until scrolling brings them up to read (--pf-read, 0 to 1 over 40% of a
  // screen; below lg it also veils the image under the text).
  function measureCover(page, intro) {
    const hero = page.querySelector('.pf-gallery--cover > .pf-exhibit-wall-hero');
    const frame = hero && hero.querySelector('.pf-exhibit-wall-hero__frame');
    if (!frame) {
      return;
    }
    const fold = frame.getBoundingClientRect().height - (intro.getBoundingClientRect().top - hero.getBoundingClientRect().top);
    intro.style.setProperty('--pf-fold', Math.round(fold) + 'px');
    intro.classList.toggle('is-long', intro.scrollHeight > fold);
    readCover();
  }

  function readCover() {
    const hero = state.current && state.current.querySelector('.pf-gallery--cover > .pf-exhibit-wall-hero');
    if (!hero) {
      return;
    }
    const moved = hero.querySelector('.pf-exhibit-wall-hero__frame').getBoundingClientRect().top - hero.getBoundingClientRect().top;
    const read = Math.min(Math.max(moved / (window.innerHeight * 0.4), 0), 1);
    hero.closest('.pf-section__inner').style.setProperty('--pf-read', read.toFixed(3));
  }

  let coverQueued = false;
  function queueCover() {
    if (coverQueued) {
      return;
    }
    coverQueued = true;
    requestAnimationFrame(() => {
      coverQueued = false;
      readCover();
    });
  }

  function sortWall(wall, key, animate) {
    const list = wall.querySelector('.pf-exhibit-wall__list');
    const tiles = Array.from(list.children);
    const title = (tile) => tile.querySelector('.pf-exhibit-wall__name').textContent.trim();
    const plate = (tile) => parseInt(tile.dataset.plate, 10) || Infinity;
    const sorted = tiles.slice().sort(key === 'plate'
      ? (a, b) => plate(a) - plate(b) || tiles.indexOf(a) - tiles.indexOf(b)
      : (a, b) => title(a).localeCompare(title(b)));
    wall.querySelectorAll('[data-pf-sort]').forEach((b) => b.setAttribute('aria-pressed', b.dataset.pfSort === key ? 'true' : 'false'));
    const apply = () => list.append(...sorted);
    if (!animate || !canMorph()) {
      apply();
      return;
    }
    // Tiles on screen slide to their new places; the rest just move.
    const shown = sorted.filter((tile) => {
      const r = tile.getBoundingClientRect();
      return r.bottom > 0 && r.top < window.innerHeight;
    });
    shown.forEach((tile, i) => {
      tile.style.viewTransitionName = 'pf-tile-' + i;
    });
    document.startViewTransition(apply).finished.finally(() => {
      shown.forEach((tile) => {
        tile.style.viewTransitionName = '';
      });
    });
  }

  function objectView() {
    if (wallState.view) {
      return wallState.view;
    }
    const dialog = document.createElement('dialog');
    dialog.className = 'pf-exhibit-object';
    dialog.setAttribute('aria-labelledby', 'pf-exhibit-object-title');
    dialog.innerHTML =
      '<div class="pf-exhibit-object__stage">'
      + '<div class="pf-exhibit-object__media"></div>'
      + '<div class="pf-exhibit-object__tools">'
      + '<button type="button" class="pf-exhibit-object__tool pf-exhibit-object__zoom" aria-pressed="false">' + svg('zoom') + '<span class="visually-hidden">' + Drupal.t('Zoom') + '</span></button>'
      + '<button type="button" class="pf-exhibit-object__tool pf-exhibit-object__info" aria-controls="pf-exhibit-object-panel">' + svg('panel') + '<span class="visually-hidden">' + Drupal.t('Text beside the image') + '</span></button>'
      + '<button type="button" class="pf-exhibit-object__tool pf-exhibit-object__shut">' + svg('close') + '<span class="visually-hidden">' + Drupal.t('Close') + '</span></button>'
      + '</div>'
      + '<button type="button" class="pf-exhibit-object__step pf-exhibit-object__prev">' + svg('prev') + '<span class="visually-hidden">' + Drupal.t('Previous') + '</span></button>'
      + '<button type="button" class="pf-exhibit-object__step pf-exhibit-object__next">' + svg('next') + '<span class="visually-hidden">' + Drupal.t('Next') + '</span></button>'
      + '</div>'
      + '<div class="pf-exhibit-object__panel" id="pf-exhibit-object-panel">'
      + '<div class="pf-exhibit-object__top">'
      + '<p class="pf-exhibit-object__count" aria-live="polite"></p>'
      + '<button type="button" class="pf-exhibit-object__close" autofocus>' + svg('close') + '<span>' + Drupal.t('Close') + '</span></button>'
      + '</div>'
      + '<h2 class="pf-exhibit-object__title" id="pf-exhibit-object-title"></h2>'
      + '<div class="pf-exhibit-object__label pf-guide-prose"></div>'
      + '<div class="pf-exhibit-object__body"></div>'
      + '<div class="pf-exhibit-object__scale" hidden></div>'
      + '</div>';
    document.body.append(dialog);
    const view = {
      dialog: dialog,
      media: dialog.querySelector('.pf-exhibit-object__media'),
      count: dialog.querySelector('.pf-exhibit-object__count'),
      title: dialog.querySelector('.pf-exhibit-object__title'),
      label: dialog.querySelector('.pf-exhibit-object__label'),
      body: dialog.querySelector('.pf-exhibit-object__body'),
      scale: dialog.querySelector('.pf-exhibit-object__scale'),
      panel: dialog.querySelector('.pf-exhibit-object__panel'),
      zoom: dialog.querySelector('.pf-exhibit-object__zoom'),
      info: dialog.querySelector('.pf-exhibit-object__info'),
    };
    dialog.querySelector('.pf-exhibit-object__prev').addEventListener('click', () => stepObject(-1));
    dialog.querySelector('.pf-exhibit-object__next').addEventListener('click', () => stepObject(1));
    dialog.querySelector('.pf-exhibit-object__close').addEventListener('click', () => closeObject(true));
    dialog.querySelector('.pf-exhibit-object__shut').addEventListener('click', () => closeObject(true));
    view.info.addEventListener('click', () => setFull(!dialog.classList.contains('is-full'), true));
    view.zoom.addEventListener('click', () => {
      const target = view.media.querySelector('.pf-exhibit-object__img, .pf-exhibit-compare__stage');
      if (target) {
        zoom(target, null);
      }
    });
    dialog.addEventListener('cancel', (event) => {
      event.preventDefault();
      closeObject(true);
    });
    dialog.addEventListener('keydown', (event) => {
      if (event.target.matches('input')) {
        return;
      }
      if (event.key === 'ArrowLeft') {
        stepObject(-1);
      }
      else if (event.key === 'ArrowRight') {
        stepObject(1);
      }
      else if ((event.key === 'i' || event.key === 'I') && !event.ctrlKey && !event.metaKey && !event.altKey && !view.info.hidden) {
        setFull(!dialog.classList.contains('is-full'), true);
      }
    });
    // Click the image to look closer where you clicked; move to look around
    // (a mouse) or drag (touch). A comparison zooms both images together
    // (its stage).
    let drag = null;
    let dragged = false;
    view.media.addEventListener('click', (event) => {
      const img = event.target.closest('.pf-exhibit-object__img, .pf-exhibit-compare__stage');
      if (!img || dragged) {
        dragged = false;
        return;
      }
      zoom(img, event);
    });
    view.media.addEventListener('pointermove', (event) => {
      const img = event.target.closest('.is-zoomed .pf-exhibit-object__img, .is-zoomed .pf-exhibit-compare__stage');
      if (img && event.pointerType === 'mouse') {
        aim(img, event);
      }
      // Dragging moves the zoom's origin the opposite way, as far as the
      // image moves under the finger: at 2.5× that is 1 / 1.5 of the drag.
      if (drag && drag.img === img) {
        const r = img.getBoundingClientRect();
        const dx = event.clientX - drag.x;
        const dy = event.clientY - drag.y;
        if (Math.abs(dx) + Math.abs(dy) > 6) {
          dragged = true;
        }
        const x = Math.min(100, Math.max(0, drag.ox - dx / (r.width / 2.5 * 1.5) * 100));
        const y = Math.min(100, Math.max(0, drag.oy - dy / (r.height / 2.5 * 1.5) * 100));
        img.style.transformOrigin = x + '% ' + y + '%';
      }
    });
    // Swipe on touch screens (not while zoomed or on the slider).
    let startX = null;
    view.media.addEventListener('pointerdown', (event) => {
      startX = event.pointerType === 'touch' && !event.target.closest('.is-zoomed, input') ? event.clientX : null;
      const img = event.pointerType !== 'mouse' && event.target.closest('.is-zoomed .pf-exhibit-object__img, .is-zoomed .pf-exhibit-compare__stage');
      dragged = false;
      drag = null;
      if (img) {
        const origin = getComputedStyle(img).transformOrigin.split(' ').map(parseFloat);
        const r = img.getBoundingClientRect();
        drag = { img: img, x: event.clientX, y: event.clientY, ox: origin[0] / (r.width / 2.5) * 100, oy: origin[1] / (r.height / 2.5) * 100 };
      }
    });
    view.media.addEventListener('pointerup', (event) => {
      if (startX !== null && Math.abs(event.clientX - startX) > 50) {
        stepObject(event.clientX < startX ? 1 : -1);
      }
      startX = null;
      drag = null;
    });
    view.media.addEventListener('pointercancel', () => {
      startX = null;
      drag = null;
    });
    // Images in the text open on the stage, with their captions; Close
    // returns to the entry.
    view.body.addEventListener('click', (event) => {
      const link = event.target.closest('a[data-pf-lightbox]');
      if (link && event.button === 0) {
        event.preventDefault();
        openPictures(link, Array.from(view.body.querySelectorAll('a[data-pf-lightbox]')), null);
      }
    });
    dialog.addEventListener('close', () => {
      ROOT.classList.remove('pf-object-open');
      const loose = wallState.loose;
      wallState.loose = null;
      if (loose) {
        const quiet = wallState.quiet;
        wallState.quiet = false;
        if (!quiet && loose.opener && loose.opener.isConnected) {
          loose.opener.focus({ preventScroll: true });
        }
        return;
      }
      if (wallState.quiet) {
        wallState.quiet = false;
        return;
      }
      const page = galleryOf(wallState.tile);
      if (page) {
        history.replaceState(history.state, '', urlFor(page));
      }
      const link = wallState.tile && wallState.tile.querySelector('a');
      if (link && link.isConnected) {
        link.focus({ preventScroll: true });
      }
    });
    wallState.view = view;
    return view;
  }

  function aim(img, event) {
    const r = img.getBoundingClientRect();
    img.style.transformOrigin = ((event.clientX - r.left) / r.width * 100) + '% ' + ((event.clientY - r.top) / r.height * 100) + '%';
  }

  // Zooms in on the image (or comparison stage) where it was clicked, or
  // its centre from the Zoom button; again zooms out. The first zoom swaps
  // in the largest size.
  function zoom(img, event) {
    const frame = img.closest('.pf-exhibit-object__frame');
    const swap = (el) => {
      el.srcset = '';
      el.src = el.dataset.full;
      delete el.dataset.full;
    };
    if (img.dataset.full) {
      swap(img);
      frame.classList.add('is-zoomed');
    }
    else if (img.querySelector('img[data-full]')) {
      img.querySelectorAll('img[data-full]').forEach(swap);
      frame.classList.add('is-zoomed');
    }
    else {
      frame.classList.toggle('is-zoomed');
    }
    if (event) {
      aim(img, event);
    }
    else {
      img.style.transformOrigin = '';
    }
    wallState.view.zoom.setAttribute('aria-pressed', String(frame.classList.contains('is-zoomed')));
  }

  // The text panel folded away or back. save: the reader's own choice,
  // kept for next time. A view with nothing to read is always full.
  function setFull(full, save) {
    const view = wallState.view;
    // Focus in the panel (I pressed there) moves to the button first.
    if (full && view.panel.contains(document.activeElement)) {
      (view.info.hidden ? view.dialog.querySelector('.pf-exhibit-object__shut') : view.info).focus();
    }
    view.dialog.classList.toggle('is-full', full);
    view.info.setAttribute('aria-expanded', String(!full));
    if (save) {
      try {
        window.localStorage.setItem(FULL_KEY, full ? '1' : '0');
      }
      catch (e) {
        // Private browsing: the choice lasts until the page closes.
      }
    }
  }

  // After the stage is filled: the Zoom button follows the new image, and
  // the panel shows unless the reader folded it away or it has nothing in it.
  function syncTools(bare) {
    const view = wallState.view;
    view.zoom.hidden = !view.media.querySelector('img');
    view.zoom.setAttribute('aria-pressed', 'false');
    view.info.hidden = bare;
    setFull(bare || savedFull(), false);
  }

  // The entry's first figure (or image group): its images and its caption.
  function entryMedia(entry) {
    const block = entry.querySelector('.pf-exhibit-group, .pf-exhibit-figure');
    if (!block) {
      return { block: null, links: [], caption: null };
    }
    const links = Array.from(block.querySelectorAll('a.pf-exhibit-figure__link')).slice(0, 2);
    const caption = block.querySelector(':scope > figcaption')
      || (links.length === 1 ? links[0].closest('figure').querySelector(':scope > figcaption') : null);
    return { block: block, links: links, caption: caption };
  }

  // The stage's image: at once the tile's own (already loaded), then the
  // size the stage needs from the figure's srcset once it has loaded.
  function mediaImg(link, cls, shown) {
    const thumb = link.querySelector('img');
    const img = document.createElement('img');
    img.className = cls;
    img.alt = thumb ? thumb.alt : '';
    if (link.dataset.width) {
      img.width = parseInt(link.dataset.width, 10);
      img.height = parseInt(link.dataset.height, 10);
    }
    img.src = shown && shown.currentSrc ? shown.currentSrc : (thumb ? thumb.getAttribute('src') : link.href);
    img.decoding = 'async';
    if (thumb && thumb.getAttribute('srcset')) {
      const large = new Image();
      large.decoding = 'async';
      large.sizes = '(min-width: 62rem) 62vw, 100vw';
      large.onload = () => {
        if (img.isConnected && !img.closest('.is-zoomed')) {
          img.src = large.currentSrc;
        }
      };
      large.srcset = thumb.getAttribute('srcset');
    }
    return img;
  }

  function fillObject(index) {
    const view = objectView();
    const n = wallState.tiles.length;
    wallState.index = (index + n) % n;
    const tile = wallState.tiles[wallState.index];
    wallState.tile = tile;
    const heading = document.getElementById(tile.dataset.pfTile);
    const entry = heading ? heading.closest('.pf-exhibit-entry') : null;
    if (!entry) {
      return;
    }
    wallState.loose = null;
    view.dialog.removeAttribute('aria-label');
    view.dialog.setAttribute('aria-labelledby', 'pf-exhibit-object-title');
    view.title.hidden = false;
    const media = entryMedia(entry);
    const shown = tile.querySelectorAll('.pf-exhibit-wall__img');

    // The image, or the first two as a comparison.
    const frame = document.createElement('div');
    frame.className = 'pf-exhibit-object__frame';
    if (media.links.length === 2) {
      const [a, b] = media.links;
      const names = media.links.map((link) => {
        const caption = link.closest('figure').querySelector('figcaption');
        return caption ? caption.textContent.trim() : '';
      });
      frame.classList.add('pf-exhibit-compare');
      // "k rot tx ty" on the drawing lays it over the proof: the frame takes
      // the proof's shape and the drawing is scaled, turned and moved onto
      // it (k and the offsets in proof widths), in a layer the slider wipes
      // or fades.
      const align = (a.dataset.pfAlign || '').split(' ').map(Number);
      const aligned = align.length === 4 && align.every(Number.isFinite) && b.dataset.width;
      const sized = aligned ? b : a;
      if (sized.dataset.width) {
        frame.style.setProperty('--pf-r', sized.dataset.width / sized.dataset.height);
      }
      if (aligned) {
        const [k, rot, tx, ty] = align;
        const layer = document.createElement('div');
        layer.className = 'pf-exhibit-compare__layer';
        const drawing = mediaImg(a, 'pf-exhibit-compare__drawing', shown[0]);
        drawing.style.cssText = 'left:' + (tx * 100) + '%;top:' + (ty * b.dataset.width / b.dataset.height * 100) + '%;width:' + (k * 100) + '%;rotate:' + rot + 'deg';
        layer.append(drawing);
        frame.classList.add('pf-exhibit-compare--aligned');
        frame.append(mediaImg(b, 'pf-exhibit-compare__img', shown[1]), layer);
      }
      else {
        frame.append(mediaImg(a, 'pf-exhibit-compare__img', shown[0]), mediaImg(b, 'pf-exhibit-compare__img pf-exhibit-compare__after', shown[1]));
      }
      const line = document.createElement('span');
      line.className = 'pf-exhibit-compare__line';
      line.setAttribute('aria-hidden', 'true');
      frame.append(line);
      // Zoomed as one, like a single image: both images (and the cut) in a
      // stage, each swapping to its largest size on the first zoom.
      const stage = document.createElement('div');
      stage.className = 'pf-exhibit-compare__stage';
      stage.append(...frame.childNodes);
      frame.append(stage);
      stage.querySelectorAll('img').forEach((img) => {
        img.dataset.full = (img.classList.contains('pf-exhibit-compare__after') || (aligned && !img.closest('.pf-exhibit-compare__layer')) ? b : a).href;
      });
      const control = document.createElement('div');
      control.className = 'pf-exhibit-compare__control';
      const range = document.createElement('input');
      range.type = 'range';
      range.min = '0';
      range.max = '100';
      range.value = '50';
      range.setAttribute('aria-label', names[0] && names[1]
        ? Drupal.t('Compare the @a and the @b', { '@a': names[0].toLowerCase(), '@b': names[1].toLowerCase() })
        : Drupal.t('Compare the two images'));
      range.addEventListener('input', () => {
        frame.style.setProperty('--pf-cut', range.value + '%');
        frame.style.setProperty('--pf-mix', range.value / 100);
      });
      const end = (text) => {
        const span = document.createElement('span');
        span.setAttribute('aria-hidden', 'true');
        span.textContent = text;
        return span;
      };
      control.append(end(names[0]), range, end(names[1]));
      // Lined up, the two can also be faded one into the other.
      if (aligned) {
        const modes = document.createElement('div');
        modes.className = 'pf-exhibit-compare__modes';
        modes.setAttribute('role', 'group');
        modes.setAttribute('aria-label', Drupal.t('Comparison'));
        [['wipe', Drupal.t('Wipe')], ['fade', Drupal.t('Fade')]].forEach(([mode, label]) => {
          const button = document.createElement('button');
          button.type = 'button';
          button.textContent = label;
          button.setAttribute('aria-pressed', String(mode === wallState.compare));
          button.addEventListener('click', () => {
            wallState.compare = mode;
            frame.classList.add('is-easing');
            frame.classList.toggle('is-fading', mode === 'fade');
            modes.querySelectorAll('button').forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
            window.setTimeout(() => frame.classList.remove('is-easing'), 600);
          });
          modes.append(button);
        });
        frame.classList.toggle('is-fading', wallState.compare === 'fade');
        control.append(modes);
      }
      // The frame is sized to fit inside this box (container units), not by
      // aspect-ratio alone: iOS Safari gives an aspect-ratio box whose
      // children are all absolutely placed no width in the grid.
      const fit = document.createElement('div');
      fit.className = 'pf-exhibit-compare__fit';
      fit.append(frame);
      view.media.replaceChildren(fit, control);
    }
    else if (media.links.length) {
      const img = mediaImg(media.links[0], 'pf-exhibit-object__img', shown[0]);
      img.dataset.full = media.links[0].href;
      frame.append(img);
      view.media.replaceChildren(frame);
    }
    else {
      view.media.replaceChildren();
    }

    // The entry's heading, caption and the rest of its content.
    view.title.textContent = heading.textContent.trim();
    view.label.replaceChildren(...(media.caption ? Array.from(media.caption.cloneNode(true).childNodes) : []));
    view.label.hidden = !media.caption;
    const copy = entry.cloneNode(true);
    copy.querySelector('.pf-exhibit-entry__title').remove();
    if (media.block) {
      // The image is on the stage: drop its figure (and its field wrapper).
      const twin = copy.querySelector('.pf-exhibit-group, .pf-exhibit-figure');
      const wrap = twin.parentElement;
      (wrap && wrap !== copy && wrap.classList.contains('field__item') && wrap.children.length === 1 ? wrap : twin).remove();
    }
    copy.querySelectorAll('[id]').forEach((el) => el.removeAttribute('id'));
    copy.querySelectorAll('.pf-exhibit-bio__toggle').forEach((el) => el.remove());
    copy.querySelectorAll('.pf-exhibit-bio').forEach((el) => el.classList.remove('is-folded'));
    view.body.replaceChildren(...Array.from(copy.childNodes));
    // Copied before the page's links were marked (an entry opened from the
    // address at load): mark them here.
    view.dialog.querySelectorAll('.pf-exhibit-object__panel a[href]:not([target])').forEach(markOutside);
    view.panel.scrollTop = 0;

    view.count.textContent = Drupal.t('@n of @total', { '@n': wallState.index + 1, '@total': n });
    view.dialog.querySelectorAll('.pf-exhibit-object__step').forEach((button) => {
      button.hidden = n < 2;
    });
    fillScale(tile);
    syncTools(false);
  }

  /* ---------------------------------------------------------------------
   * Pictures: images in the text (a figure, a group, a gallery's lead, the
   * bar's zoom button) open in the same view, each with its caption in the
   * panel, and step through the gallery's images. Opened from an entry in
   * the view, Close goes back to that entry.
   * ------------------------------------------------------------------ */

  // group: the images to step through (default: the link's gallery's);
  // opener: where focus returns (default: the link).
  function openPictures(link, group, opener) {
    const view = objectView();
    const scope = galleryOf(link) || (state.current || document);
    const links = group || Array.from(scope.querySelectorAll('a[data-pf-lightbox]'));
    if (!links.includes(link)) {
      links.unshift(link);
    }
    const open = view.dialog.open;
    wallState.loose = {
      links: links,
      index: 0,
      opener: opener || link,
      entry: open && !wallState.loose ? wallState.index : null,
    };
    const thumb = link.querySelector('img');
    const show = () => {
      fillLoose(links.indexOf(link));
      if (!view.dialog.open) {
        ROOT.classList.add('pf-object-open');
        view.dialog.showModal();
        focusClose();
      }
    };
    const r = thumb && thumb.isConnected ? thumb.getBoundingClientRect() : null;
    if (open || !canMorph() || !r || r.bottom < 0 || r.top > window.innerHeight) {
      show();
      return;
    }
    thumb.style.viewTransitionName = 'pf-object';
    document.startViewTransition(() => {
      thumb.style.viewTransitionName = '';
      show();
    });
  }

  function fillLoose(index) {
    const view = wallState.view;
    const loose = wallState.loose;
    const n = loose.links.length;
    loose.index = (index + n) % n;
    const link = loose.links[loose.index];
    const frame = document.createElement('div');
    frame.className = 'pf-exhibit-object__frame';
    const img = mediaImg(link, 'pf-exhibit-object__img', link.querySelector('img'));
    img.dataset.full = link.href;
    frame.append(img);
    view.media.replaceChildren(frame);

    // A figure's own caption, or the one a link names (a gallery's hero,
    // whose caption follows its pan).
    const figure = link.closest('figure');
    const caption = link.dataset.pfCaption
      ? document.getElementById(link.dataset.pfCaption)
      : (figure ? figure.querySelector(':scope > figcaption') : null);
    view.dialog.removeAttribute('aria-labelledby');
    view.dialog.setAttribute('aria-label', Drupal.t('Image viewer'));
    view.title.hidden = true;
    view.label.replaceChildren(...(caption ? Array.from(caption.cloneNode(true).childNodes) : []));
    view.label.querySelectorAll('a[href]:not([target])').forEach(markOutside);
    view.label.hidden = !caption;
    view.body.replaceChildren();
    view.scale.hidden = true;
    view.scale.replaceChildren();
    view.panel.scrollTop = 0;
    view.count.textContent = n > 1 ? Drupal.t('Image @n of @total', { '@n': loose.index + 1, '@total': n }) : '';
    view.dialog.querySelectorAll('.pf-exhibit-object__step').forEach((button) => {
      button.hidden = n < 2;
    });
    syncTools(!caption && n < 2);
  }

  // Focus on opening: whichever Close shows, in the panel or on the stage
  // (phones, or the panel folded).
  function focusClose() {
    const view = wallState.view;
    const shown = Array.from(view.dialog.querySelectorAll('.pf-exhibit-object__close, .pf-exhibit-object__shut')).find((button) => button.getClientRects().length);
    if (shown) {
      shown.focus();
    }
  }

  // "Actual size": this gallery's sheet beside the page's other sheets.
  function fillScale(tile) {
    const view = wallState.view;
    const wall = tile.closest('[data-pf-wall]');
    let sheets = [];
    try {
      sheets = JSON.parse(wall.dataset.pfSheets || '[]');
    }
    catch (e) {
      sheets = [];
    }
    view.scale.hidden = sheets.length < 2;
    if (view.scale.hidden) {
      view.scale.replaceChildren();
      return;
    }
    const tallest = Math.max(...sheets.map((s) => s.h));
    const thumb = tile.querySelector('img');
    view.scale.innerHTML = '<p class="pf-exhibit-object__scale-title">' + Drupal.t('Actual size') + '</p>'
      + '<ul class="pf-exhibit-object__sheets"></ul>';
    const row = view.scale.querySelector('.pf-exhibit-object__sheets');
    // Each sheet over its own label, at its size against the tallest.
    sheets.forEach((s) => {
      const li = document.createElement('li');
      li.className = s.current ? 'is-current' : '';
      const sheet = document.createElement('span');
      sheet.className = 'pf-exhibit-object__sheet';
      sheet.setAttribute('aria-hidden', 'true');
      sheet.style.setProperty('--pf-w', s.w / tallest);
      sheet.style.setProperty('--pf-h', s.h / tallest);
      if (s.current && thumb) {
        const img = document.createElement('img');
        img.src = thumb.currentSrc || thumb.src;
        img.alt = '';
        sheet.append(img);
      }
      const label = document.createElement('span');
      label.className = 'pf-exhibit-object__sheet-label';
      label.textContent = s.label;
      const size = document.createElement('span');
      size.className = 'pf-exhibit-object__sheet-label';
      size.textContent = s.size;
      li.append(sheet, label, size);
      // Which sheet this plate is on, in words: an outline round the sheet
      // read as part of its size.
      if (s.current) {
        const mark = document.createElement('span');
        mark.className = 'pf-exhibit-object__sheet-mark';
        mark.textContent = Drupal.t('This plate');
        li.append(mark);
      }
      row.append(li);
    });
  }

  function stepObject(delta) {
    if (wallState.loose) {
      if (wallState.loose.links.length > 1) {
        fillLoose(wallState.loose.index + delta);
      }
    }
    else if (wallState.tiles.length > 1) {
      fillObject(wallState.index + delta);
      history.replaceState(history.state, '', location.pathname + location.search + '#' + wallState.tile.dataset.pfTile);
    }
  }

  // Opens an entry on a wall. animate: grow out of its tile.
  function openObject(entry, animate) {
    const tile = tileFor(entry);
    if (!tile) {
      return;
    }
    const view = objectView();
    wallState.tiles = Array.from(tile.parentElement.children);
    const index = wallState.tiles.indexOf(tile);
    const show = () => {
      fillObject(index);
      if (!view.dialog.open) {
        ROOT.classList.add('pf-object-open');
        view.dialog.showModal();
        focusClose();
      }
    };
    history.replaceState(history.state, '', location.pathname + location.search + '#' + tile.dataset.pfTile);
    const thumb = tile.querySelector('.pf-exhibit-wall__img');
    const r = thumb.getBoundingClientRect();
    if (!animate || !canMorph() || view.dialog.open || r.bottom < 0 || r.top > window.innerHeight) {
      show();
      return;
    }
    thumb.style.viewTransitionName = 'pf-object';
    document.startViewTransition(() => {
      thumb.style.viewTransitionName = '';
      show();
    });
  }

  // animate: shrink back into the tile (scrolled into view first), or the
  // picture into its image when that is on screen. quiet: leave the address
  // and focus to the caller. Pictures opened from an entry go back to it.
  function closeObject(animate, quiet) {
    const view = wallState.view;
    if (!view || !view.dialog.open) {
      return;
    }
    const loose = wallState.loose;
    if (loose && loose.entry !== null && !quiet) {
      fillObject(loose.entry);
      focusClose();
      return;
    }
    wallState.quiet = !!quiet;
    const tile = wallState.tile;
    let thumb = tile && tile.querySelector('.pf-exhibit-wall__img');
    if (loose) {
      thumb = loose.links[loose.index].querySelector('img');
      const box = thumb && thumb.isConnected ? thumb.getBoundingClientRect() : null;
      if (!box || box.bottom < headerOffset() || box.top > window.innerHeight) {
        thumb = null;
      }
    }
    if (!animate || !canMorph() || !thumb) {
      view.dialog.close();
      return;
    }
    const r = thumb.getBoundingClientRect();
    if (!loose && (r.top < headerOffset() || r.bottom > window.innerHeight)) {
      window.scrollTo({ top: window.scrollY + r.top - window.innerHeight / 3, behavior: 'instant' });
    }
    document.startViewTransition(() => {
      view.dialog.close();
      thumb.style.viewTransitionName = 'pf-object';
    }).finished.finally(() => {
      thumb.style.viewTransitionName = '';
    });
  }

  /* ---------------------------------------------------------------------
   * Title page tooltip (the physical exhibition's note)
   *
   * CSS shows it on hover and keyboard focus; a tap or click keeps it open
   * until the next tap elsewhere, and Esc hides it until the pointer or
   * focus leaves (WCAG 1.4.13).
   * ------------------------------------------------------------------ */

  function initTip(wrap) {
    const close = () => wrap.classList.remove('is-open');
    wrap.querySelector('button').addEventListener('click', () => {
      wrap.classList.remove('is-dismissed');
      wrap.classList.toggle('is-open');
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && (wrap.classList.contains('is-open') || wrap.matches(':hover, :focus-within'))) {
        close();
        wrap.classList.add('is-dismissed');
      }
    });
    document.addEventListener('click', (event) => {
      if (!wrap.contains(event.target)) {
        close();
      }
    });
    wrap.addEventListener('mouseleave', () => {
      if (!wrap.matches(':focus-within')) {
        wrap.classList.remove('is-dismissed');
      }
    });
    wrap.addEventListener('focusout', (event) => {
      if (!wrap.contains(event.relatedTarget)) {
        close();
        wrap.classList.remove('is-dismissed');
      }
    });
  }

  /* ---------------------------------------------------------------------
   * Biographies
   * ------------------------------------------------------------------ */

  function initBio(bio) {
    if (bio.dataset.pfBioReady) {
      return;
    }
    bio.dataset.pfBioReady = '1';
    const text = bio.querySelector('.pf-exhibit-bio__text');
    const clip = parseFloat(getComputedStyle(bio).getPropertyValue('--pf-bio-clip')) || 0;
    const lineHeight = parseFloat(getComputedStyle(text).lineHeight) || 24;
    const limit = clip * parseFloat(getComputedStyle(ROOT).fontSize);
    // Only worth folding when at least four more lines hide.
    if (!limit || text.scrollHeight < limit + lineHeight * 4) {
      return;
    }
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'pf-exhibit-bio__toggle';
    button.setAttribute('aria-controls', text.id);
    const sync = (open) => {
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
      button.textContent = open ? Drupal.t('Show less') : Drupal.t('Read the full biography');
      bio.classList.toggle('is-folded', !open);
    };
    sync(false);
    button.addEventListener('click', () => {
      const open = bio.classList.contains('is-folded');
      // Animate between the clip and the full height, then let it be auto.
      text.style.maxHeight = text.getBoundingClientRect().height + 'px';
      sync(open);
      if (reduceMotion()) {
        text.style.maxHeight = '';
        return;
      }
      requestAnimationFrame(() => {
        text.style.maxHeight = (open ? text.scrollHeight : limit) + 'px';
        const end = () => {
          text.style.maxHeight = '';
          text.removeEventListener('transitionend', end);
        };
        text.addEventListener('transitionend', end);
      });
      if (!open) {
        // Folding: keep the biography's start in view.
        const rect = bio.getBoundingClientRect();
        if (rect.top < headerOffset()) {
          glideTo(bio, () => {});
        }
      }
    });
    bio.append(button);
  }

  /* ---------------------------------------------------------------------
   * Icons
   * ------------------------------------------------------------------ */

  const ICON = {
    close: '<path d="M3.5 3.5l9 9m0-9-9 9"/>',
    prev: '<path d="M10 3.5 5.5 8l4.5 4.5"/>',
    next: '<path d="M6 3.5 10.5 8 6 12.5"/>',
    zoom: '<circle cx="7" cy="7" r="4.25"/><path d="M10.2 10.2 13.5 13.5M5 7h4"/><path class="pf-exhibit-object__plus" d="M7 5v4"/>',
    panel: '<path d="M2.5 3.5h11v9h-11z"/><path class="pf-exhibit-object__side" d="M9.5 3.5v9"/><path class="pf-exhibit-object__foot" d="M2.5 9.5h11"/>',
  };

  function svg(name) {
    return '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="square" aria-hidden="true" focusable="false">' + ICON[name] + '</svg>';
  }

  /* ---------------------------------------------------------------------
   * Outside links: in an exhibition's text and captions, a link to another
   * site opens in a new tab, so the exhibition (its gallery, an open image)
   * stays where it was. Each says so: an arrow after its last word, kept on
   * that word's line, and "opens in a new tab" for screen readers. A field
   * guide row (ExhibitFieldGuide) has its own arrow.
   * ------------------------------------------------------------------ */

  function markOutside(link) {
    let url;
    try {
      url = new URL(link.href, location.href);
    }
    catch (e) {
      return;
    }
    if (!/^https?:$/.test(url.protocol) || url.host === location.host) {
      return;
    }
    link.target = '_blank';
    link.relList.add('noopener');
    const note = document.createElement('span');
    note.className = 'visually-hidden';
    note.textContent = ' ' + Drupal.t('(opens in a new tab)');
    if (link.classList.contains('pf-exhibit-fieldguide__link')) {
      link.append(note);
      return;
    }
    const arrow = '<svg class="pf-exhibit-out" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="square" aria-hidden="true" focusable="false"><path d="M5 11 11 5M6 5h5v5"/></svg>';
    // The last word and the arrow in one unbreakable span. The last word
    // may be inside an <em> (a book's title), and spaces after it (a title
    // typed with one) would be an underlined gap before the arrow.
    const texts = [];
    const walker = document.createTreeWalker(link, NodeFilter.SHOW_TEXT);
    while (walker.nextNode()) {
      texts.push(walker.currentNode);
    }
    const last = texts.findLastIndex((node) => node.textContent.trim());
    texts.slice(last + 1).forEach((node) => {
      node.textContent = '';
    });
    const text = texts[last];
    const tail = document.createElement('span');
    tail.className = 'pf-exhibit-out-tail';
    if (text) {
      const words = text.textContent.replace(/\s+$/, '');
      const cut = words.search(/\S+$/);
      text.textContent = words.slice(0, cut);
      tail.textContent = words.slice(cut);
      text.after(tail);
    }
    else {
      link.append(tail);
    }
    tail.insertAdjacentHTML('beforeend', arrow);
    link.append(note);
  }

  /* ---------------------------------------------------------------------
   * Audio
   * ------------------------------------------------------------------ */

  function clock(seconds) {
    if (!isFinite(seconds)) {
      return '0:00';
    }
    const s = Math.floor(seconds % 60);
    return Math.floor(seconds / 60) + ':' + (s < 10 ? '0' : '') + s;
  }

  function initAudio(wrap) {
    const audio = wrap.querySelector('audio');
    if (!audio) {
      return;
    }
    const label = audio.getAttribute('aria-labelledby');
    audio.controls = false;
    wrap.classList.add('is-custom');

    const play = document.createElement('button');
    play.type = 'button';
    play.className = 'pf-audio__play';
    play.innerHTML = '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path class="pf-audio__icon-play" d="M5 3.2v9.6L12.6 8z"/><path class="pf-audio__icon-pause" d="M4.5 3h2.5v10H4.5zM9 3h2.5v10H9z"/></svg><span class="visually-hidden"></span>';
    const playText = play.querySelector('.visually-hidden');
    if (label) {
      play.setAttribute('aria-describedby', label);
    }

    const seek = document.createElement('input');
    seek.type = 'range';
    seek.className = 'pf-audio__seek';
    seek.min = '0';
    seek.max = '1000';
    seek.step = '1';
    seek.value = '0';
    seek.setAttribute('aria-label', Drupal.t('Seek'));

    const time = document.createElement('span');
    time.className = 'pf-audio__time';
    time.setAttribute('aria-hidden', 'true');

    const sync = () => {
      const paused = audio.paused;
      wrap.classList.toggle('is-playing', !paused);
      playText.textContent = paused ? Drupal.t('Play') : Drupal.t('Pause');
      const duration = audio.duration || 0;
      if (!seek.matches(':active')) {
        seek.value = duration ? String(Math.round(audio.currentTime / duration * 1000)) : '0';
      }
      seek.style.setProperty('--pf-audio-progress', (Number(seek.value) / 10) + '%');
      const text = clock(audio.currentTime) + ' / ' + clock(duration);
      time.textContent = text;
      seek.setAttribute('aria-valuetext', Drupal.t('@now of @total', { '@now': clock(audio.currentTime), '@total': clock(duration) }));
    };

    play.addEventListener('click', () => {
      if (audio.paused) {
        // One clip at a time on the page.
        document.querySelectorAll('[data-pf-audio] audio').forEach((other) => {
          if (other !== audio) {
            other.pause();
          }
        });
        audio.play();
      }
      else {
        audio.pause();
      }
    });
    seek.addEventListener('input', () => {
      if (audio.duration) {
        audio.currentTime = Number(seek.value) / 1000 * audio.duration;
      }
      sync();
    });
    ['play', 'pause', 'timeupdate', 'loadedmetadata', 'ended'].forEach((type) => audio.addEventListener(type, sync));
    wrap.append(play, seek, time);
    sync();
  }

  /* ---------------------------------------------------------------------
   * Behavior
   * ------------------------------------------------------------------ */

  start();

  Drupal.behaviors.palefireExhibition = {
    attach(context) {
      // A Swup visit from another page: the script is already loaded.
      start();
      once('pf-lightbox', 'a[data-pf-lightbox]', context).forEach((link) => {
        link.addEventListener('click', (event) => {
          if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || !window.HTMLDialogElement || link.closest('.pf-exhibit-object')) {
            return;
          }
          event.preventDefault();
          openPictures(link, null, null);
        });
      });
      once('pf-exhibit-out', '.pf-main a[href]', context).forEach(markOutside);
      once('pf-audio', '[data-pf-audio]', context).forEach(initAudio);
      once('pf-exhibit-tip', '[data-pf-exhibit-tip]', context).forEach(initTip);
      // Biographies outside galleries (a page without the head's script).
      if (!state.pages.length) {
        once('pf-exhibit-bio', '[data-pf-exhibit-bio]', context).forEach(initBio);
      }
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload' || !state.head || !context.contains(state.head)) {
        return;
      }
      document.removeEventListener('click', onClick, true);
      window.removeEventListener('popstate', onPopState);
      window.removeEventListener('resize', onResize);
      document.removeEventListener('palefire:a11ychange', onResize);
      ROOT.style.removeProperty('--pf-exhibit-head-h');
      ROOT.classList.remove('pf-exhibit-paged', 'pf-object-open');
      if (wallState.view) {
        wallState.view.dialog.remove();
        wallState.view = null;
        wallState.tile = null;
      }
      state.head = null;
      state.pages = [];
      state.current = null;
      state.crumb = null;
      if (state.zoom) {
        window.removeEventListener('scroll', queueZoom);
        state.zoom = null;
      }
    },
  };
})(Drupal, once);
