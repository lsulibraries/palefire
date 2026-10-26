/**
 * @file
 * Exhibition pages (<html data-pf-mode="exhibition">): one scrolled
 * document, the title page, the object view, long biographies and audio.
 *
 * - Galleries: each exhibit_page section is a "gallery", all of them shown
 *   one after another under the title page (2026-10-26: was one gallery
 *   at a time). "Begin" and any #link in the text (an entry, a source)
 *   glide to the target and focus it; the address follows without a
 *   history entry. The TOC scrolls as on other pages. Each gallery but the
 *   last ends with a placard for the next one ("Next · 03 / 07", its title,
 *   an arrow pointing down: scrolling on is the way there).
 * - Title page: the page head (includes/exhibit-cover.html.twig), with a
 *   button in its breadcrumb bar that opens the cover full screen.
 * - Object view: every image in the full-screen viewer (js/viewer.js). A
 *   wall's entry
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

  function reduceMotion() {
    return ROOT.classList.contains('pf-a11y-motion') || window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function isExhibition() {
    return ROOT.dataset.pfMode === 'exhibition';
  }

  /* ---------------------------------------------------------------------
   * Galleries
   * ------------------------------------------------------------------ */

  const state = { head: null, pages: [], zoom: null };

  // The gallery that holds an element (null: the title page, or neither).
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

  /* ---------------------------------------------------------------------
   * Zoom button: at the end of the breadcrumb bar, opens the title page's
   * cover full screen in the lightbox. It shows while the cover is in sight
   * under the bar and fades once it has scrolled away.
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

  // The cover: [the element to watch, its <img>].
  function openingImage() {
    const frame = state.head.querySelector('.pf-exhibit-cover__media');
    const img = frame ? frame.querySelector('img') : null;
    return img ? [frame, img] : null;
  }

  // A lightbox link for the cover, made for the purpose, with the largest
  // size in its srcset, on its own (no images to step through).
  function zoomSource() {
    const found = openingImage();
    if (!found) {
      return null;
    }
    const img = found[1];
    const link = document.createElement('a');
    const sizes = (img.getAttribute('srcset') || '').split(',').map((part) => part.trim().split(/\s+/));
    const largest = sizes.reduce((best, part) => (parseInt(part[1], 10) || 0) > (parseInt(best[1], 10) || 0) ? part : best, [img.currentSrc || img.src, '0w']);
    link.href = largest[0];
    if (img.getAttribute('width')) {
      link.dataset.width = img.getAttribute('width');
      link.dataset.height = img.getAttribute('height');
    }
    link.append(img.cloneNode(false));
    return { link: link, group: [link] };
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

  // In sight: more than about two lines' worth of the cover shows between
  // the bar and the head's foot (the cover runs past it, the head clips it).
  function updateZoom() {
    const button = state.zoom;
    if (!button) {
      return;
    }
    const found = openingImage();
    let shown = false;
    if (found && found[0].getClientRects().length) {
      const bar = button.closest('.pf-landing__bar').getBoundingClientRect().bottom;
      const bottom = Math.min(found[0].getBoundingClientRect().bottom, window.innerHeight, state.head.getBoundingClientRect().bottom);
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
  const canPan = () => wide.matches && !reduceMotion() && window.CSS && CSS.supports('animation-timeline: view()');

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
    state.pages.forEach((page) => {
      sizeHero(page);
      foldIntro(page);
    });
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

  /**
   * Goes to an element on the page.
   *
   * An entry on a wall opens in the object view, its tile brought into
   * sight first, so the image grows out of it and focus has somewhere to
   * return. Anything else glides into place and takes focus. The address
   * follows, without a history entry.
   *
   * @param {Element} target
   *   The element to show (a gallery, an entry heading, the head...).
   */
  function go(target) {
    const entry = wallEntry(target);
    if (entry) {
      const tile = tileFor(entry);
      if (tile && !viewer.inSight(tile, headerOffset())) {
        tile.scrollIntoView({ block: 'center', behavior: 'instant' });
      }
      openObject(entry, true);
      return;
    }
    closeObject(false, true);
    history.replaceState(history.state, '', urlFor(target));
    const top = target === state.head || state.pages.includes(target);
    glideTo(target, () => {
      focusOn(target);
      if (!top) {
        flash(target);
      }
    });
  }

  // The placard at a gallery's foot (.pf-exhibit-next, _exhibition.scss):
  // "Next · 03 / 07", the next gallery's title and an arrow pointing down,
  // since scrolling on is the way there; the link glides there too (onClick).
  // After the section's content box, so it sits on the page colour past any
  // pinned image. None after the last gallery.
  function addNext(page) {
    const index = state.pages.indexOf(page);
    const to = state.pages[index + 1];
    if (!to || !to.id) {
      return;
    }
    const heading = to.querySelector('.pf-section__title');
    const nav = document.createElement('nav');
    nav.className = 'pf-exhibit-next';
    nav.setAttribute('aria-label', Drupal.t('Next gallery'));
    const link = document.createElement('a');
    link.className = 'pf-exhibit-next__link';
    link.href = '#' + to.id;
    const kicker = document.createElement('span');
    kicker.className = 'pf-exhibit-next__kicker';
    kicker.append(Drupal.t('Next'));
    const number = document.createElement('span');
    number.className = 'pf-exhibit-next__number';
    number.textContent = String(index + 2).padStart(2, '0') + ' / ' + String(state.pages.length).padStart(2, '0');
    kicker.append(' ', number);
    const title = document.createElement('span');
    title.className = 'pf-exhibit-next__title';
    title.textContent = heading ? heading.textContent.replace(/\s+/g, ' ').trim() : '';
    // A pause between the number and the title for screen readers
    // ("Next 02 / 07: Audubon at Oakley").
    const pause = document.createElement('span');
    pause.className = 'visually-hidden';
    pause.textContent = ': ';
    link.append(kicker, pause, title);
    link.insertAdjacentHTML('beforeend', '<svg class="pf-exhibit-next__arrow" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="square" aria-hidden="true" focusable="false"><path d="M8 2.5v11M3.5 9 8 13.5 12.5 9"/></svg>');
    nav.append(link);
    page.append(nav);
  }

  function prepareGallery(page) {
    if (page.dataset.pfExhibitReady) {
      return;
    }
    page.dataset.pfExhibitReady = '1';
    page.querySelectorAll('[data-pf-exhibit-bio]').forEach(initBio);
    foldIntro(page);
    sizeHero(page);
    addNext(page);
  }

  // "Begin", and #links in the exhibition outside the TOC (the opening's
  // entry links, "View the whole image", "Skip to the text", cross
  // references): a glide. The TOC scrolls to its own targets as on other
  // pages (js/modular-toc.js), except a wall's entries, which open.
  function onClick(event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }
    const link = event.target instanceof Element ? event.target.closest('a[href^="#"], a[data-pf-exhibit-begin]') : null;
    if (!link) {
      return;
    }
    const target = link.hasAttribute('data-pf-exhibit-begin') ? state.pages[0] : targetOf(link.hash);
    if (!target || !(galleryOf(target) || state.head.contains(target))) {
      return;
    }
    if (link.closest('[data-pf-toc]') && !wallEntry(target)) {
      return;
    }
    event.preventDefault();
    event.stopPropagation();
    closeToc();
    go(target);
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

  // Back and Forward: an address for a wall's entry opens it; any other
  // closes the object view (the browser scrolls to the anchor itself).
  function onPopState() {
    const entry = wallEntry(targetOf(location.hash));
    if (entry) {
      openObject(entry, false);
    }
    else {
      closeObject(false, true);
    }
  }

  // Before the first paint where possible: this runs as the script loads,
  // ahead of every behavior.
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
    // "06 / 19" above each gallery's heading (_exhibition.scss).
    state.pages.forEach((page, i) => {
      page.style.setProperty('--pf-gallery-index', String(i + 1));
      page.style.setProperty('--pf-gallery-total', String(state.pages.length));
    });
    initZoom();
    initWalls();
    initCovers();
    initEntryLinks();
    state.pages.forEach(prepareGallery);
    queueZoom();
    // The title page's cover waited for this (exhibit-cover.html.twig):
    // with an address for a gallery it is above, and loads lazily.
    ROOT.classList.remove('pf-exhibit-deep');
    const target = targetOf(location.hash);
    const entry = wallEntry(target);
    if (entry) {
      // An address for a wall's entry: the entry open, over its wall.
      requestAnimationFrame(() => {
        const tile = tileFor(entry);
        if (tile) {
          tile.scrollIntoView({ block: 'center', behavior: 'instant' });
        }
        openObject(entry, false);
      });
    }
    else if (target && galleryOf(target)) {
      // An address for a gallery or anything in one: there again once the
      // walls and folds above have changed the page's length.
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
  // The full-screen viewer (js/viewer.js) the object view is shown in.
  const viewer = Drupal.palefireViewer;

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

  // Every cover on the page (the frame sticks, so how far it has held is
  // how far the text has come up over it).
  function readCover() {
    state.pages.forEach((page) => {
      const hero = page.querySelector('.pf-gallery--cover > .pf-exhibit-wall-hero');
      if (!hero) {
        return;
      }
      const moved = hero.querySelector('.pf-exhibit-wall-hero__frame').getBoundingClientRect().top - hero.getBoundingClientRect().top;
      const read = Math.min(Math.max(moved / (window.innerHeight * 0.4), 0), 1);
      hero.closest('.pf-section__inner').style.setProperty('--pf-read', read.toFixed(3));
    });
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

  // The viewer's parts, with the object view's own: the entry's text set
  // for the narrow panel, "Actual size" under it, and images in that text
  // opening on the stage (Close goes back to the entry).
  function objectView() {
    const view = viewer.get();
    if (!view.scale) {
      view.body.classList.add('pf-exhibit-object__body');
      view.scale = document.createElement('div');
      view.scale.className = 'pf-exhibit-object__scale';
      view.scale.hidden = true;
      view.panel.append(view.scale);
      // The pinned top row's height, under which a clip's player pins:
      // measured when a clip is shown (panelAudio()) and as it resizes.
      if (window.ResizeObserver) {
        new ResizeObserver(() => topHeight(view)).observe(view.panel.querySelector('.pf-viewer__top'));
      }
      view.body.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-pf-lightbox]');
        if (link && event.button === 0) {
          event.preventDefault();
          openPictures(link, Array.from(view.body.querySelectorAll('a[data-pf-lightbox]')), null);
        }
      });
    }
    wallState.view = view;
    return view;
  }

  // An oral history clip's stage: its photo blurred into a wash behind it
  // (_exhibition.scss), fading in after the image and crossfading as the
  // view steps. src: the tile's loaded image, or null for none (objects).
  function setWash(view, src) {
    view.stage.querySelectorAll('.pf-exhibit-wash:not(.is-leaving)').forEach((layer) => {
      layer.classList.add('is-leaving');
      layer.classList.remove('is-shown');
      window.setTimeout(() => layer.remove(), 800);
    });
    if (!src) {
      return;
    }
    const layer = document.createElement('div');
    layer.className = 'pf-exhibit-wash';
    layer.setAttribute('aria-hidden', 'true');
    layer.style.backgroundImage = 'url("' + src.replace(/"/g, '%22') + '")';
    view.stage.prepend(layer);
    // Its hidden style read first, so the opacity transitions rather than
    // starts shown (animation frames may not run in a background tab).
    window.getComputedStyle(layer).opacity;
    layer.classList.add('is-shown');
  }

  // A clip in the panel's copy of its entry: the copied player has no
  // listeners, so it is set up again, labelled by the view's title. The
  // transcript is the panel's text: shown under a label, not folded in a
  // <details>, while the player stays pinned (_exhibition.scss).
  function panelAudio(view) {
    if (view.body.querySelector('[data-pf-audio]')) {
      // Again once the dialog is open: on the first opening it is filled
      // before it shows.
      topHeight(view);
      window.setTimeout(() => topHeight(view), 50);
    }
    view.body.querySelectorAll('[data-pf-audio]').forEach((wrap) => {
      wrap.querySelectorAll('.pf-audio__play, .pf-audio__seek, .pf-audio__time, .pf-audio__mute').forEach((el) => el.remove());
      wrap.classList.remove('is-custom', 'is-playing', 'is-muted');
      const audio = wrap.querySelector('audio');
      if (audio) {
        audio.setAttribute('aria-labelledby', 'pf-viewer-title');
        initAudio(wrap);
      }
    });
    view.body.querySelectorAll('.pf-exhibit-audio').forEach((clip) => {
      clip.setAttribute('aria-labelledby', 'pf-viewer-title');
      const folded = clip.querySelector('.pf-exhibit-audio__transcript');
      const text = folded && folded.querySelector('.pf-exhibit-audio__text');
      if (text) {
        const transcript = document.createElement('div');
        transcript.className = 'pf-exhibit-audio__read';
        const label = document.createElement('p');
        label.className = 'pf-exhibit-label__kicker';
        label.textContent = Drupal.t('Transcript');
        transcript.append(label, text);
        folded.replaceWith(transcript);
      }
    });
  }

  // --pf-viewer-top-h for the pinned player (_exhibition.scss); kept
  // while the dialog is closed (no height then).
  function topHeight(view) {
    const height = view.panel.querySelector('.pf-viewer__top').offsetHeight;
    if (height) {
      view.panel.style.setProperty('--pf-viewer-top-h', height + 'px');
    }
  }

  // A clip stops when the view steps on or closes (a detached <audio>
  // would play on).
  function stopAudio(view) {
    view.body.querySelectorAll('audio').forEach((audio) => audio.pause());
  }

  // What the viewer's arrows, Close and Esc do here.
  const objectHandler = {
    step: (delta) => stepObject(delta),
    close: () => closeObject(true),
    closed: objectClosed,
  };

  // Closed: focus back where the view was opened from, and (an entry) the
  // address back to its gallery. quiet: the caller does both.
  function objectClosed() {
    if (wallState.view) {
      stopAudio(wallState.view);
      setWash(wallState.view, null);
    }
    const loose = wallState.loose;
    const quiet = wallState.quiet;
    wallState.loose = null;
    wallState.quiet = false;
    if (loose) {
      if (!quiet && loose.opener && loose.opener.isConnected) {
        loose.opener.focus({ preventScroll: true });
      }
      return;
    }
    if (quiet) {
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
    view.dialog.setAttribute('aria-labelledby', 'pf-viewer-title');
    view.title.hidden = false;
    const media = entryMedia(entry);
    const shown = tile.querySelectorAll('.pf-exhibit-wall__img');

    // The image, or the first two as a comparison.
    const frame = document.createElement('div');
    frame.className = 'pf-viewer__frame';
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
      stage.className = 'pf-exhibit-compare__stage pf-viewer__zoomable';
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
      const img = mediaImg(media.links[0], 'pf-viewer__img pf-viewer__zoomable', shown[0]);
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
    stopAudio(view);
    view.body.replaceChildren(...Array.from(copy.childNodes));
    panelAudio(view);
    setWash(view, entry.querySelector('.pf-exhibit-audio') && shown[0] ? (shown[0].currentSrc || shown[0].getAttribute('src')) : null);
    // Copied before the page's links were marked (an entry opened from the
    // address at load): mark them here.
    view.dialog.querySelectorAll('.pf-viewer__panel a[href]:not([target])').forEach(markOutside);

    view.count.textContent = Drupal.t('@n of @total', { '@n': wallState.index + 1, '@total': n });
    fillScale(tile);
    viewer.sync({ steps: n });
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
    objectView();
    const scope = galleryOf(link) || document;
    const links = group || Array.from(scope.querySelectorAll('a[data-pf-lightbox]'));
    if (!links.includes(link)) {
      links.unshift(link);
    }
    wallState.loose = {
      links: links,
      index: 0,
      opener: opener || link,
      entry: viewer.isOpen() && !wallState.loose ? wallState.index : null,
    };
    viewer.show(objectHandler, () => fillLoose(links.indexOf(link)), { from: link.querySelector('img'), kind: 'exhibit' });
  }

  function fillLoose(index) {
    const view = objectView();
    const loose = wallState.loose;
    const n = loose.links.length;
    loose.index = (index + n) % n;
    const link = loose.links[loose.index];
    const frame = document.createElement('div');
    frame.className = 'pf-viewer__frame';
    const img = mediaImg(link, 'pf-viewer__img pf-viewer__zoomable', link.querySelector('img'));
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
    stopAudio(view);
    setWash(view, null);
    view.body.replaceChildren();
    view.scale.hidden = true;
    view.scale.replaceChildren();
    view.count.textContent = n > 1 ? Drupal.t('Image @n of @total', { '@n': loose.index + 1, '@total': n }) : '';
    viewer.sync({ steps: n, bare: !caption && n < 2 });
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
    objectView();
    wallState.tiles = Array.from(tile.parentElement.children);
    const index = wallState.tiles.indexOf(tile);
    history.replaceState(history.state, '', location.pathname + location.search + '#' + tile.dataset.pfTile);
    viewer.show(objectHandler, () => fillObject(index), { from: animate ? tile.querySelector('.pf-exhibit-wall__img') : null, kind: 'exhibit' });
  }

  // animate: shrink back into the tile (scrolled into view first), or the
  // picture into its image when that is on screen. quiet: leave the address
  // and focus to the caller. Pictures opened from an entry go back to it.
  function closeObject(animate, quiet) {
    if (!wallState.view || !viewer.isOpen()) {
      return;
    }
    const loose = wallState.loose;
    if (loose && loose.entry !== null && !quiet) {
      fillObject(loose.entry);
      viewer.focusClose();
      return;
    }
    wallState.quiet = !!quiet;
    let thumb = wallState.tile && wallState.tile.querySelector('.pf-exhibit-wall__img');
    if (loose) {
      thumb = loose.links[loose.index].querySelector('img');
      if (!viewer.inSight(thumb, headerOffset())) {
        thumb = null;
      }
    }
    else if (animate && thumb && viewer.canMorph()) {
      const r = thumb.getBoundingClientRect();
      if (r.top < headerOffset() || r.bottom > window.innerHeight) {
        window.scrollTo({ top: window.scrollY + r.top - window.innerHeight / 3, behavior: 'instant' });
      }
    }
    viewer.close(animate ? thumb : null);
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

  // The listener's level, shared by every player on the page, so the next
  // clip (a new player in the object view) keeps it. iOS ignores a page's
  // volume (it reads back as 1): there only Mute shows and the device's
  // buttons set the level.
  const level = { volume: 1, muted: false };
  const canSetVolume = (() => {
    const probe = document.createElement('audio');
    probe.volume = 0.5;
    return probe.volume === 0.5;
  })();

  function setLevel(volume, muted) {
    level.volume = volume;
    level.muted = muted;
    document.querySelectorAll('[data-pf-audio] audio').forEach((audio) => {
      audio.muted = muted;
      if (canSetVolume) {
        audio.volume = volume;
      }
    });
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

    // Mute (a toggle: aria-pressed), and the volume where it can be set.
    const mute = document.createElement('button');
    mute.type = 'button';
    mute.className = 'pf-audio__mute';
    mute.innerHTML = '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="square" aria-hidden="true" focusable="false"><path d="M2.5 6h2.5L8.5 3v10L5 10H2.5z"/><path class="pf-audio__waves" d="M10.5 5.75a3 3 0 0 1 0 4.5M12.25 4a5.5 5.5 0 0 1 0 8"/><path class="pf-audio__cross" d="M10.5 6l4 4m0-4-4 4"/></svg><span class="visually-hidden">' + Drupal.t('Mute') + '</span>';
    let volume = null;
    if (canSetVolume) {
      volume = document.createElement('input');
      volume.type = 'range';
      volume.className = 'pf-audio__seek pf-audio__volume';
      volume.min = '0';
      volume.max = '100';
      volume.step = '5';
      volume.setAttribute('aria-label', Drupal.t('Volume'));
      volume.addEventListener('input', () => setLevel(Number(volume.value) / 100, false));
    }
    mute.addEventListener('click', () => {
      // Unmuting at no volume would stay silent: back to half.
      setLevel(level.volume || 0.5, !audio.muted);
    });
    const syncLevel = () => {
      wrap.classList.toggle('is-muted', audio.muted || (canSetVolume && audio.volume === 0));
      mute.setAttribute('aria-pressed', String(audio.muted));
      if (volume) {
        const percent = audio.muted ? 0 : Math.round(audio.volume * 100);
        volume.value = String(percent);
        volume.style.setProperty('--pf-audio-progress', percent + '%');
        volume.setAttribute('aria-valuetext', audio.muted ? Drupal.t('Muted') : percent + '%');
      }
    };
    audio.addEventListener('volumechange', syncLevel);
    audio.muted = level.muted;
    if (canSetVolume) {
      audio.volume = level.volume;
    }

    wrap.append(play, seek, time, mute);
    if (volume) {
      wrap.append(volume);
    }
    sync();
    syncLevel();
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
          if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || !window.HTMLDialogElement || link.closest('.pf-viewer')) {
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
      window.removeEventListener('scroll', queueCover);
      document.removeEventListener('palefire:a11ychange', onResize);
      if (wallState.view) {
        viewer.destroy();
        wallState.view = null;
        wallState.tile = null;
      }
      state.head = null;
      state.pages = [];
      if (state.zoom) {
        window.removeEventListener('scroll', queueZoom);
        state.zoom = null;
      }
    },
  };
})(Drupal, once);
