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
 *   slider and the time. A clip with a WebVTT file lights its transcript
 *   phrase by phrase as it plays.
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
      briefWash(page);
    });
    queueZoom();
  }

  function headerOffset() {
    const css = getComputedStyle(ROOT);
    return (parseFloat(css.scrollPaddingTop) || parseFloat(css.getPropertyValue('--pf-header-h')) || 0) + 16;
  }

  // How long a gallery's opening holds its photograph while its text comes
  // in (_exhibition.scss --pf-reveal, in px; 0 without the reveal).
  function revealOf(el) {
    return parseFloat(getComputedStyle(el).getPropertyValue('--pf-reveal')) || 0;
  }

  // Top in the document, transforms aside (the reveal sets the text back).
  function layoutTop(el) {
    let top = 0;
    for (let node = el; node; node = node.offsetParent) {
      top += node.offsetTop;
    }
    return top;
  }

  function glideTo(el, done) {
    // A gallery: to its reveal's end, where its text is in.
    const reveal = state.pages.includes(el) ? revealOf(el) : 0;
    const at = () => Math.max(0, window.scrollY + el.getBoundingClientRect().top - headerOffset() + reveal);
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
    const note = noteFor(target);
    if (note) {
      // A wall's note (an event with no picture): read in place.
      closeObject(false, true);
      history.replaceState(history.state, '', location.pathname + location.search + '#' + note.dataset.pfNote);
      glideTo(note, () => {
        focusOn(note);
        flash(note);
      });
      return;
    }
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
  // After the section's content box, so it sits on the page color past any
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
    briefWash(page);
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

  // The title page's "Scroll" cue (exhibit-cover.html.twig), as the
  // landing's (js/hero-highlights.js): shown when the cover's foot is on
  // the first screen, so it never sits below the fold. A click glides to
  // the first gallery, as Begin. Once the page moves it fades and leaves
  // the tab order (.is-away), unless the keyboard is on it; after ten quiet
  // seconds at the top it runs its line once more (.is-replaying, the
  // CSS's, none under reduced motion).
  function cue(head) {
    const button = head.querySelector('[data-pf-exhibit-cue]');
    if (!button || !state.pages[0] || window.scrollY > 8 || head.getBoundingClientRect().bottom > window.innerHeight + 2) {
      return;
    }
    button.hidden = false;
    const stop = new AbortController();
    let idle = 0;
    const retire = () => {
      window.clearTimeout(idle);
      stop.abort();
      button.classList.add('is-away');
    };
    const wait = () => {
      window.clearTimeout(idle);
      idle = window.setTimeout(() => {
        if (document.hidden || window.scrollY > 8 || getComputedStyle(button).display === 'none') {
          wait();
        }
        else {
          button.classList.add('is-replaying');
        }
      }, 10000);
    };
    window.addEventListener('scroll', () => {
      if (window.scrollY > 8 && state.pages[0].getBoundingClientRect().top <= window.innerHeight / 2) {
        retire();
        return;
      }
      button.classList.toggle('is-away', window.scrollY > 8);
      if (!button.classList.contains('is-replaying')) {
        wait();
      }
    }, { passive: true, signal: stop.signal });
    ['wheel', 'keydown', 'pointerdown'].forEach((type) => {
      window.addEventListener(type, () => {
        if (!button.classList.contains('is-replaying')) {
          wait();
        }
      }, { passive: true, signal: stop.signal });
    });
    button.addEventListener('click', () => {
      retire();
      go(state.pages[0]);
    });
    wait();
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
    const note = noteFor(target);
    if (note) {
      requestAnimationFrame(() => note.scrollIntoView({ block: 'center' }));
    }
    else if (entry) {
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
    cue(head);
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

  // The entry an element belongs to, when that entry is on a wall and
  // has a tile (a note is read on the wall instead: noteFor()).
  function wallEntry(el) {
    const entry = el && el.closest ? el.closest('.pf-exhibit-entry') : null;
    return entry && entry.closest('.pf-gallery.is-walled') && !noteFor(entry) ? entry : null;
  }

  // The wall note (.pf-exhibit-wall__tile--note) for an element in an
  // entry that hangs as one: a dated event with no picture or clip.
  function noteFor(el) {
    const entry = el && el.closest ? el.closest('.pf-exhibit-entry') : null;
    const heading = entry ? entry.querySelector('.pf-exhibit-entry__title') : null;
    const page = entry ? galleryOf(entry) : null;
    return heading && heading.id && page ? page.querySelector('[data-pf-note="' + CSS.escape(heading.id) + '"]') : null;
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
          briefWash(page, true);
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
      // A sub-gallery's heading takes its folded entry's id, so a link to
      // the show (#pure-vision) lands on the heading over its tiles.
      wall.querySelectorAll('[data-pf-group]').forEach((heading) => {
        const folded = document.getElementById(heading.dataset.pfGroup);
        if (folded && page.contains(folded)) {
          folded.removeAttribute('id');
          heading.id = heading.dataset.pfGroup;
        }
      });
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
      // A resize: a new fold, and an open intro's title row held at it.
      intro.style.setProperty('--pf-intro-clip', Math.round(introLimit(page, intro)) + 'px');
      if (!intro.classList.contains('is-folded')) {
        holdWallTop(page, intro, true);
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
      const from = intro.getBoundingClientRect().height;
      const to = open ? intro.scrollHeight : introLimit(page, intro);
      if (open) {
        holdWallTop(page, intro, true);
      }
      // The browser's own scroll anchoring would move the page as well.
      ROOT.style.overflowAnchor = 'none';
      intro.style.maxHeight = from + 'px';
      sync(open);
      briefWash(page, true);
      // Closing, the button keeps its place on screen (the page scrolls up
      // as the text above it folds), never taken up past the header.
      let last = from;
      const follow = () => {
        const now = intro.getBoundingClientRect().height;
        if (!open) {
          window.scrollBy({ top: now - last, behavior: 'instant' });
        }
        last = now;
      };
      const done = () => {
        follow();
        ROOT.style.overflowAnchor = '';
        if (!open) {
          holdWallTop(page, intro, false);
        }
      };
      if (reduceMotion() || Math.abs(from - to) < 1) {
        intro.style.maxHeight = '';
        done();
        return;
      }
      let frame = 0;
      const step = () => {
        follow();
        frame = requestAnimationFrame(step);
      };
      requestAnimationFrame(() => {
        intro.style.maxHeight = to + 'px';
        frame = requestAnimationFrame(step);
        const end = (event) => {
          if (event.target !== intro) {
            return;
          }
          cancelAnimationFrame(frame);
          intro.style.maxHeight = '';
          intro.removeEventListener('transitionend', end);
          done();
        };
        intro.addEventListener('transitionend', end);
      });
    });
    intro.after(button);
  }

  // From lg, a lead whose text is short (title to foot within BRIEF of the
  // image's screen) has a lighter wash, lifted above the text (.is-brief,
  // _exhibition.scss), settling back as the text opens. An intro with
  // "Read more" is short while folded (its fold keeps it to the lower
  // third) and long open.
  // The wash moves (.is-wash-live) only for `byReader` ("Read more", "Show
  // more"): set on load, an opening off screen was restyled only as it
  // came near, so it turned in view from the long wash.
  const BRIEF = 0.42;
  function briefWash(page, byReader) {
    const hero = page.querySelector('.pf-exhibit-wall-hero');
    const title = page.querySelector('.pf-section__title');
    const intro = page.querySelector('.pf-exhibit-wall__intro');
    if (!hero || !title) {
      return;
    }
    let brief = false;
    if (intro && wide.matches) {
      const toggle = intro.parentElement.querySelector('.pf-exhibit-wall__more');
      const stack = layoutTop(hero) + hero.offsetHeight - layoutTop(title);
      brief = toggle ? intro.classList.contains('is-folded') : stack <= hero.querySelector('.pf-exhibit-wall-hero__frame').offsetHeight * BRIEF;
    }
    const inner = title.closest('.pf-section__inner');
    inner.classList.toggle('is-wash-live', !!byReader);
    inner.classList.toggle('is-brief', brief);
  }

  // With a lead, the title's row takes what the text leaves of the first
  // screen (_exhibition.scss), so opened lines pushed the title and the text
  // above them up the screen. While open, the row keeps its height with the
  // intro folded (measured folded, again on resize): the text opens
  // downward and the image grows with it.
  function holdWallTop(page, intro, on) {
    const inner = page.querySelector('.pf-exhibit-wall-hero') && intro.closest('.pf-section__inner');
    if (!inner) {
      return;
    }
    inner.style.removeProperty('--pf-wall-top');
    if (!on) {
      return;
    }
    const folded = intro.classList.contains('is-folded');
    intro.classList.add('is-folded');
    inner.style.setProperty('--pf-wall-top', parseFloat(getComputedStyle(inner).gridTemplateRows) + 'px');
    intro.classList.toggle('is-folded', folded);
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
    // Where the text rests while the photograph holds: the reveal's length
    // above its place in the layout.
    const fold = frame.getBoundingClientRect().height - (layoutTop(intro) - layoutTop(hero) - revealOf(page));
    intro.style.setProperty('--pf-fold', Math.round(fold) + 'px');
    intro.classList.toggle('is-long', intro.scrollHeight > fold);
    readCover();
  }

  // Scroll timelines do it in CSS below lg (the veil) and from lg with
  // motion (the reveal, no fold fade): writing --pf-read on the gallery
  // every frame made the wash flicker on phones (_exhibition.scss).
  const readInCss = () => !!(window.CSS && CSS.supports('animation-timeline: view()')) && (!wide.matches || !reduceMotion());

  // Every cover on the page (the frame sticks, so how far it has held is
  // how far the text has come up over it).
  function readCover() {
    const css = readInCss();
    state.pages.forEach((page) => {
      const hero = page.querySelector('.pf-gallery--cover > .pf-exhibit-wall-hero');
      if (!hero) {
        return;
      }
      if (css) {
        hero.closest('.pf-section__inner').style.removeProperty('--pf-read');
        return;
      }
      // Less the reveal, while the text holds still.
      const moved = hero.querySelector('.pf-exhibit-wall-hero__frame').getBoundingClientRect().top - hero.getBoundingClientRect().top - revealOf(page);
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
      phoneParts(view);
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

  // An oral history clip on a phone or tablet (below lg, _exhibition.scss):
  // the transcript takes the whole screen over the photo's wash, the photo
  // a thumbnail beside the title that shows it whole (is-photo), with a
  // way back to the transcript; the arrows, gone with the stage, beside
  // the count. Hidden from lg, where the photo stands beside the panel.
  const ICON_PREV = '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="square" aria-hidden="true" focusable="false"><path d="M10 3 5 8l5 5"/></svg>';
  const ICON_NEXT = '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="square" aria-hidden="true" focusable="false"><path d="m6 3 5 5-5 5"/></svg>';
  const ICON_WHOLE = '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="square" aria-hidden="true" focusable="false"><path d="M9.5 2.5h4v4M13.5 2.5l-5 5M6.5 13.5h-4v-4M2.5 13.5l5-5"/></svg>';

  function phoneParts(view) {
    const top = view.panel.querySelector('.pf-viewer__top');
    const steps = document.createElement('span');
    steps.className = 'pf-exhibit-phone-steps';
    [[-1, ICON_PREV, Drupal.t('Previous')], [1, ICON_NEXT, Drupal.t('Next')]].forEach(([delta, svg, name]) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'pf-exhibit-phone-step';
      button.innerHTML = svg + '<span class="visually-hidden">' + name + '</span>';
      button.addEventListener('click', () => view.steps[delta < 0 ? 0 : 1].click());
      steps.append(button);
    });
    steps.insertBefore(view.count, steps.lastChild);
    top.prepend(steps);
    view.phoneSteps = steps;

    const thumb = document.createElement('button');
    thumb.type = 'button';
    thumb.className = 'pf-exhibit-thumb';
    thumb.hidden = true;
    thumb.innerHTML = '<img alt="" width="72" height="92" decoding="async"><span class="pf-exhibit-thumb__name" aria-hidden="true">' + ICON_WHOLE + Drupal.t('Photo') + '</span><span class="visually-hidden">' + Drupal.t('Show photo') + '</span>';
    thumb.addEventListener('click', () => showPhoto(view, true));
    view.title.before(thumb);
    view.thumb = thumb;

    const back = document.createElement('button');
    back.type = 'button';
    back.className = 'pf-viewer__tool pf-exhibit-back';
    back.innerHTML = ICON_PREV + '<span>' + Drupal.t('Transcript') + '</span>';
    back.setAttribute('aria-label', Drupal.t('Back to the transcript'));
    back.addEventListener('click', () => showPhoto(view, false));
    view.stage.querySelector('.pf-viewer__tools').prepend(back);
    view.back = back;
  }

  function showPhoto(view, on) {
    view.dialog.classList.toggle('is-photo', on);
    (on ? view.back : view.thumb).focus({ preventScroll: true });
  }

  // An oral history clip's stage: its photo blurred into a wash behind it
  // (_exhibition.scss), fading in after the image and crossfading as the
  // view steps. src: the tile's loaded image, or null for none (objects).
  // Below lg the same photo is the panel's thumbnail (phoneParts()).
  function setWash(view, src) {
    view.dialog.classList.toggle('is-clip', !!src);
    view.dialog.classList.remove('is-photo');
    if (view.thumb) {
      view.thumb.hidden = !src;
      if (src) {
        view.thumb.querySelector('img').src = src;
      }
    }
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
      // The title and its credit, small, over the pinned player once the
      // view's own has scrolled away, running by as a marquee while the
      // clip plays (_exhibition.scss): two copies, so the loop is
      // seamless. Decorative: the <h2> and the credit name it.
      const credit = wrap.parentElement.querySelector('.pf-exhibit-audio__credit');
      const label = [view.title.textContent, credit && credit.textContent]
        .map((part) => (part || '').trim())
        .filter(Boolean)
        .join(' · ');
      const now = document.createElement('p');
      now.className = 'pf-audio__now';
      now.setAttribute('aria-hidden', 'true');
      const track = document.createElement('span');
      track.className = 'pf-audio__now-track';
      track.style.setProperty('--pf-now-length', label.length);
      for (let i = 0; i < 2; i++) {
        const copy = document.createElement('span');
        copy.textContent = label;
        track.append(copy);
      }
      now.append(track);
      wrap.prepend(now);
    });
    // Marked already when copied from the page, unless an address opened
    // the view before the behaviors attached.
    view.body.querySelectorAll('.pf-exhibit-audio__text').forEach(markSpeakers);
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

  // A clip with no picture opens as a side panel beside the page (no
  // stage, so no arrows): the reader can go on through the exhibition.
  const sideHandler = {
    close: () => closeObject(true),
    closed: objectClosed,
  };

  /* ---------------------------------------------------------------------
   * Listening bar
   *
   * A clip in the object view can be minimized (Keep listening, in the
   * view's top row):
   * the view closes and the clip plays on in a slim bar at the foot of the
   * screen (.pf-listen, _exhibition.scss), so the reader goes on through
   * the exhibition. The bar holds the view's own player, moved there whole
   * (an <audio> moved within one task keeps playing), with the entry's
   * title as its Expand button and a Close that stops it. Expand opens the
   * entry again and puts the same player and transcript back, so it plays
   * on without a gap and the read-along stays where it was. Another clip
   * starting anywhere ends the bar: one clip at a time.
   *
   * A clip with a timed transcript (data-pf-timings) has captions on the
   * bar, as the podcast's bar has (js/podcast.js): a band over it with the
   * phrase heard and the one before it muted above, and CC to turn the
   * band off and on, remembered in this browser (the podcast's own
   * setting, so one choice serves both). The band is the eyes' copy of
   * the voice, so screen readers skip it; Expand brings back the
   * transcript.
   * ------------------------------------------------------------------ */

  const listen = { bar: null, wrap: null, saved: null, cues: [], shown: null, index: -1, frame: 0, stop: null };
  const CC_KEY = 'pf-podcast-captions';
  const ccOn = () => {
    try {
      return window.localStorage.getItem(CC_KEY) !== 'off';
    }
    catch (e) {
      return true;
    }
  };

  // The view's player for this entry into the bar; the view closes.
  function minimize(wrap) {
    const view = wallState.view;
    if (!view || !wrap || !wallState.tile) {
      return;
    }
    dismissBar();
    const credit = wrap.parentElement && wrap.parentElement.querySelector('.pf-exhibit-audio__credit');
    // Where the player sat in the view's text, to put it back on Expand.
    const mark = document.createComment('pf-listen');
    wrap.before(mark);
    listen.saved = {
      tile: wallState.tile,
      nodes: Array.from(view.body.childNodes),
      mark: mark,
    };
    const bar = buildBar(view.title.textContent.trim(), credit ? credit.textContent.trim() : '');
    bar.querySelector('.pf-listen__player').append(wrap);
    listen.bar = bar;
    listen.wrap = wrap;
    document.body.append(bar);
    barCaptions(bar, wrap);
    closeObject(false, true);
    const page = galleryOf(listen.saved.tile);
    if (page) {
      history.replaceState(history.state, '', urlFor(page));
    }
    // Its hidden style read first, so it slides in rather than appears.
    window.getComputedStyle(bar).opacity;
    bar.classList.add('is-shown');
    bar.querySelector('.pf-listen__expand').focus({ preventScroll: true });
  }

  function buildBar(title, credit) {
    const bar = document.createElement('section');
    bar.className = 'pf-listen pf-listen--clip';
    bar.setAttribute('aria-label', Drupal.t('Now playing'));
    bar.innerHTML = '<div class="pf-listen__caption" aria-hidden="true">'
      + '<p class="pf-listen__caption-prev pf-listen__caption-older"></p>'
      + '<p class="pf-listen__caption-prev"></p>'
      + '<p class="pf-listen__caption-text"></p>'
      + '</div>'
      + '<div class="pf-listen__inner">'
      + '<button type="button" class="pf-listen__expand">'
      + '<span class="pf-listen__kicker">' + Drupal.t('Now playing') + '</span>'
      + '<span class="pf-listen__title"></span>'
      + '<span class="visually-hidden">' + Drupal.t(', open with its transcript') + '</span>'
      + '<svg class="pf-listen__icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="square" aria-hidden="true" focusable="false"><path d="M9.5 2.5h4v4M13.5 2.5 8.5 7.5M6.5 13.5h-4v-4M2.5 13.5l5-5"/></svg>'
      + '</button>'
      + '<div class="pf-listen__player"></div>'
      + '<button type="button" class="pf-listen__cc" aria-pressed="true" hidden>'
      + '<span class="pf-listen__cc-mark" aria-hidden="true">CC</span>'
      + '<span class="visually-hidden">' + Drupal.t('Captions') + '</span>'
      + '</button>'
      + '<button type="button" class="pf-listen__close">'
      + '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="square" aria-hidden="true" focusable="false"><path d="M3.5 3.5l9 9m0-9-9 9"/></svg>'
      + '<span class="visually-hidden">' + Drupal.t('Stop and close the player') + '</span>'
      + '</button>'
      + '</div>';
    bar.querySelector('.pf-listen__title').textContent = credit ? title + ' · ' + credit : title;
    bar.querySelector('.pf-listen__expand').addEventListener('click', expandBar);
    const cc = bar.querySelector('.pf-listen__cc');
    cc.setAttribute('aria-pressed', ccOn() ? 'true' : 'false');
    bar.classList.toggle('is-cc-off', !ccOn());
    cc.addEventListener('click', () => {
      const on = cc.getAttribute('aria-pressed') !== 'true';
      try {
        window.localStorage.setItem(CC_KEY, on ? 'on' : 'off');
      }
      catch (e) {
        // Private windows: for this page only.
      }
      cc.setAttribute('aria-pressed', on ? 'true' : 'false');
      bar.classList.toggle('is-cc-off', !on);
    });
    bar.querySelector('.pf-listen__close').addEventListener('click', () => {
      const tile = listen.saved && listen.saved.tile;
      dismissBar();
      const link = tile && tile.querySelector('a');
      if (link && link.isConnected) {
        link.focus({ preventScroll: true });
      }
    });
    bar.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        bar.querySelector('.pf-listen__close').click();
      }
    });
    return bar;
  }

  // The entry again, with the bar's player and its transcript back in it.
  function expandBar() {
    const saved = listen.saved;
    const wrap = listen.wrap;
    if (!saved || !wrap) {
      return;
    }
    const heading = document.getElementById(saved.tile.dataset.pfTile);
    const entry = heading ? heading.closest('.pf-exhibit-entry') : null;
    if (!entry || !saved.tile.isConnected) {
      dismissBar();
      return;
    }
    const bar = listen.bar;
    stopCaptions();
    listen.bar = null;
    listen.wrap = null;
    listen.saved = null;
    openObject(entry, false);
    const view = wallState.view;
    // The fresh copy out, the saved one (and its player) in.
    stopAudio(view);
    view.body.replaceChildren(...saved.nodes);
    saved.mark.replaceWith(wrap);
    topHeight(view);
    bar.remove();
    viewer.focusClose();
  }

  // Stops the bar's clip and takes the bar away.
  function dismissBar() {
    if (!listen.bar) {
      return;
    }
    const audio = listen.wrap && listen.wrap.querySelector('audio');
    if (audio) {
      audio.pause();
    }
    stopCaptions();
    listen.bar.remove();
    listen.bar = null;
    listen.wrap = null;
    listen.saved = null;
  }

  // The clip's cues (the read-along's, already fetched if its transcript
  // showed) on the bar's band; CC shows once they've loaded.
  function barCaptions(bar, wrap) {
    const url = wrap.dataset.pfTimings;
    const audio = wrap.querySelector('audio');
    if (!url || !audio) {
      return;
    }
    const stop = new AbortController();
    listen.stop = stop;
    loadTimings(url).then((cues) => {
      if (stop.signal.aborted || !cues.length) {
        return;
      }
      listen.cues = cues;
      listen.shown = null;
      listen.index = -2;
      bar.classList.add('has-captions');
      bar.querySelector('.pf-listen__cc').hidden = false;
      // Per frame while playing, as the read-along (timeupdate is late).
      const tick = () => {
        syncCaption(audio);
        listen.frame = audio.paused ? 0 : window.requestAnimationFrame(tick);
      };
      ['timeupdate', 'seeked', 'pause', 'ended'].forEach((type) => audio.addEventListener(type, () => syncCaption(audio), { signal: stop.signal }));
      audio.addEventListener('play', () => {
        if (!listen.frame) {
          tick();
        }
      }, { signal: stop.signal });
      if (audio.paused) {
        syncCaption(audio);
      }
      else {
        tick();
      }
    });
  }

  function stopCaptions() {
    if (listen.stop) {
      listen.stop.abort();
    }
    window.cancelAnimationFrame(listen.frame);
    Object.assign(listen, { cues: [], shown: null, index: -1, frame: 0, stop: null });
  }

  // The cue heard (-1 in a pause or before the first).
  function cueIndex(time) {
    const cues = listen.cues;
    let lo = 0;
    let hi = cues.length - 1;
    let found = -1;
    while (lo <= hi) {
      const mid = (lo + hi) >> 1;
      if (cues[mid].start <= time) {
        found = mid;
        lo = mid + 1;
      }
      else {
        hi = mid - 1;
      }
    }
    return found >= 0 && time < cues[found].end ? found : -1;
  }

  // Rolling, as broadcast captions: the phrase heard, the one just before
  // it muted above (so a phrase cut at a comma still reads), and on large
  // screens the one before that. In a pause the words stay, dimmed; a new
  // line rises in unless motion is reduced.
  function syncCaption(audio) {
    const band = listen.bar && listen.bar.querySelector('.pf-listen__caption');
    const index = band ? cueIndex(audio.currentTime) : -1;
    if (!band || index === listen.index) {
      return;
    }
    listen.index = index;
    band.classList.toggle('is-idle', index < 0);
    const cue = index < 0 ? null : listen.cues[index];
    if (!cue || cue === listen.shown) {
      return;
    }
    const before = listen.cues[index - 1];
    const older = band.querySelector('.pf-listen__caption-older');
    const prev = band.querySelector('.pf-listen__caption-prev:not(.pf-listen__caption-older)');
    const now = band.querySelector('.pf-listen__caption-text');
    // The lines before only as they were just shown (none after a seek).
    const rolled = before && before === listen.shown;
    older.textContent = rolled && prev.textContent ? prev.textContent : '';
    prev.textContent = rolled ? before.words : '';
    now.textContent = cue.words;
    listen.shown = cue;
    band.classList.add('is-shown');
    if (!reduceMotion() && now.animate) {
      now.animate([{ opacity: 0, transform: 'translateY(.35rem)' }, { opacity: 1, transform: 'none' }], { duration: 260, easing: 'cubic-bezier(.2, .7, .2, 1)' });
    }
  }

  // One clip at a time: another starting ends the bar.
  document.addEventListener('play', (event) => {
    if (listen.wrap && event.target.tagName === 'AUDIO' && !listen.wrap.contains(event.target)) {
      dismissBar();
    }
  }, true);

  // Keep listening, in the view's top row beside Close: the playing clip
  // (else the entry's first) into the bar.
  function keepButton(view) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'pf-viewer__act pf-viewer__keep';
    button.innerHTML = '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="square" aria-hidden="true" focusable="false"><path d="M3.5 12.5h9"/></svg><span class="pf-viewer__name">' + Drupal.t('Keep listening') + '</span>';
    button.addEventListener('click', () => {
      const wraps = Array.from(view.body.querySelectorAll('[data-pf-audio]'));
      minimize(wraps.find((wrap) => wrap.classList.contains('is-playing')) || wraps[0]);
    });
    return button;
  }

  /* ---------------------------------------------------------------------
   * A clip's biography
   *
   * In the object view a clip's biography would push its player and
   * transcript far down the panel, so it waits behind a button where it
   * stood: "Biography", the speaker's name, its length. The button opens a
   * second panel (.pf-viewer__bio, _exhibition.scss) with the name, Life
   * and Role and the summary when the entry has a biography card
   * (js/exhibit-bio.js), and the biography. From lg it slides in over the
   * stage beside the panel, so the transcript stays in view as the clip
   * plays; below lg (and beside a side panel) it covers the view, with
   * "Back to the clip". The clip plays on either way. Close, Back or Esc
   * shuts it; stepping to another clip shows that one's speaker in it (or
   * shuts it, a clip with no biography). The name comes from the clip's
   * credit ("Name, interviewed by …"), else the biography's opening words
   * ("Name was born …").
   * ------------------------------------------------------------------ */

  const bio = { aside: null, opener: null, timer: 0 };

  function speakerOf(copy, text) {
    const credit = copy.querySelector('.pf-exhibit-audio__credit');
    const said = credit && credit.textContent.trim().match(/^(.{2,80}?),?\s+(?:is\s+)?interviewed\b/i);
    if (said) {
      return said[1].trim();
    }
    const opening = text.match(/^((?:\S+\s+){0,4}?\S+)\s+(?:was|is)\s/);
    return opening ? opening[1] : '';
  }

  function bioAside(view) {
    if (bio.aside && view.dialog.contains(bio.aside)) {
      return bio.aside;
    }
    const aside = document.createElement('section');
    aside.className = 'pf-viewer__bio';
    aside.id = 'pf-viewer-bio';
    aside.hidden = true;
    aside.setAttribute('aria-labelledby', 'pf-viewer-bio-name');
    aside.innerHTML = '<div class="pf-viewer__bio-top">'
      + '<p class="pf-viewer__bio-kicker">' + Drupal.t('Biography') + '</p>'
      + '<button type="button" class="pf-viewer__bio-close">'
      + '<svg class="pf-viewer__bio-back" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="square" aria-hidden="true" focusable="false"><path d="M13.5 8h-11M6.5 4 2.5 8l4 4"/></svg>'
      + '<svg class="pf-viewer__bio-x" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="square" aria-hidden="true" focusable="false"><path d="M3.5 3.5l9 9m0-9-9 9"/></svg>'
      + '<span class="pf-viewer__bio-label">' + Drupal.t('Back to the clip') + '</span>'
      + '</button>'
      + '</div>'
      + '<h3 class="pf-viewer__bio-name" id="pf-viewer-bio-name" tabindex="-1"></h3>'
      + '<div class="pf-viewer__bio-body"></div>';
    aside.querySelector('.pf-viewer__bio-close').addEventListener('click', () => closeBio(true));
    // Esc shuts the biography, not the view.
    aside.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        closeBio(true);
      }
    });
    view.dialog.append(aside);
    bio.aside = aside;
    return aside;
  }

  function fillBio(aside, data) {
    aside.querySelector('.pf-viewer__bio-name').textContent = data.name || Drupal.t('About the speaker');
    const wrap = document.createElement('div');
    wrap.className = 'pf-exhibit-bio-panel';
    if (data.facts) {
      wrap.append(data.facts.cloneNode(true));
    }
    if (data.summary) {
      const lede = document.createElement('p');
      lede.className = 'pf-exhibit-bio-panel__lede';
      lede.textContent = data.summary.textContent.trim();
      wrap.append(lede);
    }
    wrap.append(data.text.cloneNode(true));
    aside.querySelector('.pf-viewer__bio-body').replaceChildren(wrap);
    aside.scrollTop = 0;
  }

  function openBio(view, data, button, focus) {
    const aside = bioAside(view);
    window.clearTimeout(bio.timer);
    fillBio(aside, data);
    if (bio.opener && bio.opener !== button) {
      bio.opener.setAttribute('aria-expanded', 'false');
    }
    bio.opener = button;
    button.setAttribute('aria-expanded', 'true');
    if (aside.hidden) {
      aside.hidden = false;
      // Its hidden style read first, so it slides in rather than appears.
      window.getComputedStyle(aside).opacity;
    }
    aside.classList.add('is-open');
    if (focus) {
      aside.querySelector('.pf-viewer__bio-name').focus({ preventScroll: true });
    }
  }

  // back: focus to the button that opened it (Close, Back, Esc).
  function closeBio(back) {
    const aside = bio.aside;
    const opener = bio.opener;
    bio.opener = null;
    if (opener) {
      opener.setAttribute('aria-expanded', 'false');
    }
    if (!aside || aside.hidden) {
      return;
    }
    aside.classList.remove('is-open');
    window.clearTimeout(bio.timer);
    if (!back || reduceMotion()) {
      aside.hidden = true;
    }
    else {
      bio.timer = window.setTimeout(() => {
        aside.hidden = true;
      }, 400);
    }
    if (back && opener && opener.isConnected) {
      opener.focus({ preventScroll: true });
    }
  }

  // The view's copy of an entry: a clip's biography (and its card) out,
  // the button in their place.
  function clipBio(view, copy) {
    const block = copy.querySelector('.pf-exhibit-bio');
    const text = block && block.querySelector('.pf-exhibit-bio__text');
    const open = !!(bio.aside && !bio.aside.hidden && bio.aside.classList.contains('is-open'));
    if (!text || !copy.querySelector('.pf-exhibit-audio')) {
      closeBio(false);
      return;
    }
    const card = copy.querySelector('[data-pf-bio-card]');
    const words = text.textContent.trim().split(/\s+/).length;
    const data = {
      name: speakerOf(copy, text.textContent.trim()),
      text: text,
      facts: card && card.querySelector('.pf-exhibit-card__facts'),
      summary: card && card.querySelector('.pf-exhibit-card__summary:not(.is-opening)'),
    };
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'pf-exhibit-bio-open';
    button.setAttribute('aria-expanded', 'false');
    button.setAttribute('aria-controls', 'pf-viewer-bio');
    button.innerHTML = '<span class="pf-exhibit-bio-open__kicker">' + Drupal.t('Biography') + '</span>'
      + '<span class="pf-exhibit-bio-open__name"></span>'
      + '<span class="pf-exhibit-bio-open__time">' + Drupal.t('@words words · @minutes min', { '@words': words, '@minutes': Math.max(1, Math.round(words / 238)) }) + '</span>'
      + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="square" aria-hidden="true" focusable="false"><path d="M3 12h17.5M14 5.5l6.5 6.5-6.5 6.5"/></svg>';
    button.querySelector('.pf-exhibit-bio-open__name').textContent = data.name || Drupal.t('About the speaker');
    button.addEventListener('click', () => {
      if (bio.opener === button) {
        closeBio(true);
      }
      else {
        openBio(view, data, button, true);
      }
    });
    block.before(button);
    block.remove();
    if (card) {
      card.closest('.pf-exhibit-card').remove();
    }
    // Open while stepping through the clips: the next speaker's life in it.
    if (open) {
      openBio(view, data, button, false);
    }
  }


  // Closed: focus back where the view was opened from, and (an entry) the
  // address back to its gallery. quiet: the caller does both.
  function objectClosed() {
    closeBio(false);
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
  // An aligned group of two is compared; any other group, its pages are
  // turned (fillObject()).
  function entryMedia(entry) {
    const block = entry.querySelector('.pf-exhibit-group, .pf-exhibit-figure');
    if (!block) {
      return { block: null, links: [], caption: null };
    }
    const links = Array.from(block.querySelectorAll('a.pf-exhibit-figure__link'));
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

  // A manuscript's pages, one at a time in the frame, with Previous page /
  // Next page under them (the viewer's own arrows and keys still step
  // through the wall). Each turn crossfades and leaves any zoom; shown: the
  // tile's loaded image, for the first page; onTurn(index), if given, is
  // told of each turn.
  function pageControls(view, frame, links, shown, onTurn) {
    const n = links.length;
    const control = document.createElement('div');
    control.className = 'pf-exhibit-pages';
    control.setAttribute('role', 'group');
    control.setAttribute('aria-label', Drupal.t('Pages'));
    const button = (label, path) => {
      const el = document.createElement('button');
      el.type = 'button';
      el.className = 'pf-exhibit-pages__step';
      el.innerHTML = '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="square" aria-hidden="true" focusable="false"><path d="' + path + '"/></svg>';
      const text = document.createElement('span');
      text.className = 'visually-hidden';
      text.textContent = label;
      el.append(text);
      return el;
    };
    const prev = button(Drupal.t('Previous page'), 'M10 3.5 5.5 8l4.5 4.5');
    const next = button(Drupal.t('Next page'), 'M6 3.5 10.5 8 6 12.5');
    const status = document.createElement('p');
    status.className = 'pf-exhibit-pages__status';
    status.setAttribute('aria-live', 'polite');
    control.append(prev, status, next);

    let index = -1;
    const turn = (to) => {
      if (to < 0 || to >= n || to === index) {
        return;
      }
      const first = index < 0;
      index = to;
      const img = mediaImg(links[to], 'pf-viewer__img pf-viewer__zoomable', to === 0 ? shown : null);
      img.dataset.full = links[to].href;
      frame.classList.remove('is-zoomed');
      view.zoom.setAttribute('aria-pressed', 'false');
      if (first) {
        frame.append(img);
      }
      else {
        frame.querySelectorAll('.pf-viewer__img:not(.is-leaving)').forEach((old) => {
          old.classList.add('is-leaving');
          window.setTimeout(() => old.remove(), 600);
        });
        img.classList.add('is-entering');
        frame.append(img);
        // Its hidden style read first, so the opacity transitions.
        window.getComputedStyle(img).opacity;
        img.classList.remove('is-entering');
        markPage(view, to, n, true);
      }
      status.textContent = Drupal.t('Page @n of @total', { '@n': to + 1, '@total': n });
      prev.disabled = to === 0;
      next.disabled = to === n - 1;
      if (onTurn) {
        onTurn(to);
      }
      // The next page's stage size, ready for the next turn.
      const after = links[to + 1] && links[to + 1].querySelector('img');
      if (after && after.getAttribute('srcset')) {
        const ahead = new Image();
        ahead.sizes = '(min-width: 62rem) 62vw, 100vw';
        ahead.srcset = after.getAttribute('srcset');
      }
    };
    prev.addEventListener('click', () => turn(index - 1));
    next.addEventListener('click', () => turn(index + 1));
    turn(0);
    return [frame, control];
  }

  // The transcription follows the page: its last headings, one per page,
  // the shown page's marked and, on a turn (scroll), brought to the top of
  // the panel under its pinned row. It glides only within a screen: a page
  // of text further on is jumped to (a long smooth scroll blurs past it).
  function markPage(view, index, n, scroll) {
    const heads = Array.from(view.body.querySelectorAll('.pf-guide-prose h4')).slice(-n);
    if (heads.length !== n) {
      return;
    }
    heads.forEach((head, i) => head.classList.toggle('is-current', i === index));
    if (scroll) {
      const panel = view.panel;
      const top = panel.querySelector('.pf-viewer__top');
      const y = Math.max(0, panel.scrollTop + heads[index].getBoundingClientRect().top - panel.getBoundingClientRect().top - (top ? top.offsetHeight : 0) - 16);
      const near = Math.abs(y - panel.scrollTop) <= panel.clientHeight;
      panel.scrollTo({ top: y, behavior: near && !reduceMotion() ? 'smooth' : 'auto' });
    }
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

    // The image, or the first two as a comparison: a pair is compared only
    // when its first image is aligned over the second (Audubon's drawing
    // over its proof); any other pair (a title page and a plate) is turned
    // like a manuscript's pages.
    const frame = document.createElement('div');
    frame.className = 'pf-viewer__frame';
    const turned = media.links.length > 2 || (media.links.length === 2 && !media.links[0].dataset.pfAlign);
    // Turned pages without a caption for the group: the label under the
    // image is the shown page's own caption (Occult's "Title page.").
    const pageLabel = (to) => {
      if (media.caption) {
        return;
      }
      const own = media.links[to].closest('figure').querySelector(':scope > figcaption');
      view.label.replaceChildren(...(own ? Array.from(own.cloneNode(true).childNodes) : []));
      view.label.hidden = !own;
    };
    if (media.links.length === 2 && !turned) {
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
    else if (turned) {
      view.media.replaceChildren(...pageControls(view, frame, media.links, shown[0], pageLabel));
    }
    else if (media.links.length) {
      const img = mediaImg(media.links[0], 'pf-viewer__img pf-viewer__zoomable', shown[0]);
      img.dataset.full = media.links[0].href;
      frame.append(img);
      view.media.replaceChildren(frame);
    }
    else if (tile.querySelector('.pf-exhibit-reel')) {
      // A clip with no picture: a tape deck (stageDeck()), or, where it
      // cannot be had, its tile's reel, out of its box, turning while the
      // clip plays (_exhibition.scss).
      const reel = tile.querySelector('.pf-exhibit-reel');
      frame.classList.add('pf-viewer__frame--reel');
      if (reel.dataset.pfDeck && window.fetch && window.DOMParser) {
        stageDeck(view, frame, reel);
      }
      else {
        frame.append(reel.cloneNode(true));
      }
      view.media.replaceChildren(frame);
    }
    else {
      view.media.replaceChildren();
    }

    // The entry's heading, caption and the rest of its content.
    view.title.textContent = heading.textContent.trim();
    view.label.replaceChildren(...(media.caption ? Array.from(media.caption.cloneNode(true).childNodes) : []));
    view.label.hidden = !media.caption;
    if (turned) {
      pageLabel(0);
    }
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
    clipBio(view, copy);
    stopAudio(view);
    view.body.replaceChildren(...Array.from(copy.childNodes));
    if (turned) {
      markPage(view, 0, media.links.length, false);
    }
    panelAudio(view);
    setWash(view, entry.querySelector('.pf-exhibit-audio') && shown[0] ? (shown[0].currentSrc || shown[0].getAttribute('src')) : null);
    // Copied before the page's links were marked (an entry opened from the
    // address at load): mark them here.
    view.dialog.querySelectorAll('.pf-viewer__panel a[href]:not([target])').forEach(markOutside);

    view.count.textContent = Drupal.t('@n of @total', { '@n': wallState.index + 1, '@total': n });
    fillScale(tile);
    // A clip: the reader may minimize it (Keep listening) and go on through
    // the exhibition while it plays.
    const clip = !!view.body.querySelector('[data-pf-audio] audio');
    viewer.sync({ steps: n, extra: clip ? [keepButton(view)] : [] });
    view.phoneSteps.querySelectorAll('button').forEach((b) => {
      b.hidden = n < 2;
    });
  }

  /* ---------------------------------------------------------------------
   * Full interviews (includes/exhibit-interviews.html.twig): a card opens
   * its interview in the object view as a clip opens, a tape at a time:
   * the portrait on the stage (on phones the thumbnail over its wash), the
   * name, the tape, the player, and the typed transcript, lit phrase by
   * phrase as it plays. The transcript is the tape's WebVTT file
   * (data-pf-timings, scripts/palefire_interviews_vtt.py), whose cues are
   * its words in order, each paragraph's first marked with its speaker
   * (<v NAME>) or <c.p>. The arrows step through every tape on the page.
   * ------------------------------------------------------------------ */

  const interviews = { list: null, index: 0, opener: null };

  const interviewHandler = {
    step: (delta) => fillInterview(interviews.index + delta),
    close: () => viewer.close(interviewPhoto(true)),
    closed: interviewClosed,
  };

  // The card's portrait, or null; on screen only, for the morph back.
  function interviewPhoto(onScreen) {
    const link = interviews.list && interviews.list[interviews.index];
    const img = link && link.closest('[data-pf-interview]').querySelector('.pf-interview__photo img');
    return img && (!onScreen || viewer.inSight(img, headerOffset())) ? img : null;
  }

  function openInterview(tape, opener) {
    objectView();
    interviews.list = Array.from(document.querySelectorAll('[data-pf-interview] a[data-pf-tape]'));
    interviews.opener = opener;
    const photo = tape.closest('[data-pf-interview]').querySelector('.pf-interview__photo img');
    viewer.show(interviewHandler, () => fillInterview(interviews.list.indexOf(tape)), { from: photo, kind: 'exhibit' });
  }

  function interviewClosed() {
    if (wallState.view) {
      stopAudio(wallState.view);
      setWash(wallState.view, null);
    }
    const opener = interviews.opener;
    interviews.list = null;
    interviews.opener = null;
    if (opener && opener.isConnected) {
      opener.focus({ preventScroll: true });
    }
  }

  // The transcript's paragraphs from the tape's cues: [{who, words}].
  const interviewTexts = new Map();

  function interviewText(url) {
    if (!interviewTexts.has(url)) {
      const entities = { amp: '&', lt: '<', gt: '>', nbsp: ' ' };
      const decode = (s) => s.replace(/&(amp|lt|gt|nbsp);/g, (match, name) => entities[name]);
      interviewTexts.set(url, fetch(url)
        .then((response) => (response.ok ? response.text() : ''))
        .then((vtt) => {
          const paras = [];
          vtt.replace(/\r\n?/g, '\n').split(/\n{2,}/).forEach((block) => {
            const lines = block.split('\n');
            const at = lines.findIndex((line) => line.includes('-->'));
            if (at < 0) {
              return;
            }
            const raw = lines.slice(at + 1).join(' ');
            const voice = raw.match(/^<v ([^>]*)>/);
            const words = decode(raw.replace(/<[^>]*>/g, '')).trim();
            if (voice || raw.startsWith('<c.p>') || !paras.length) {
              paras.push({ who: voice ? decode(voice[1]).trim() : '', words: [] });
            }
            if (words) {
              paras[paras.length - 1].words.push(words);
            }
          });
          return paras;
        })
        .catch(() => []));
    }
    return interviewTexts.get(url);
  }

  function fillInterview(index) {
    const view = objectView();
    const list = interviews.list;
    const n = list.length;
    interviews.index = (index + n) % n;
    const tape = list[interviews.index];
    const card = tape.closest('[data-pf-interview]');
    const tapes = Array.from(card.querySelectorAll('a[data-pf-tape]'));
    const name = card.querySelector('.pf-interview__name').textContent.trim();
    const photo = card.querySelector('.pf-interview__photo img');
    stopAudio(view);
    view.dialog.removeAttribute('aria-label');
    view.dialog.setAttribute('aria-labelledby', 'pf-viewer-title');
    view.title.hidden = false;

    // The portrait, or the card's initials, on the stage.
    const frame = document.createElement('div');
    frame.className = 'pf-viewer__frame';
    if (photo) {
      const img = mediaImg(photo.parentElement, 'pf-viewer__img pf-viewer__zoomable', photo);
      img.alt = Drupal.t('Photo of @name', { '@name': name });
      if (photo.getAttribute('width')) {
        img.width = parseInt(photo.getAttribute('width'), 10);
        img.height = parseInt(photo.getAttribute('height'), 10);
      }
      frame.append(img);
    }
    else {
      const initials = card.querySelector('.pf-interview__initials');
      if (initials) {
        frame.append(initials.cloneNode(true));
      }
    }
    view.media.replaceChildren(frame);

    // Which tape, under the name.
    view.title.textContent = name;
    const which = [tape.dataset.pfTape, tape.dataset.pfLength].filter(Boolean).join(' · ');
    const label = document.createElement('p');
    label.textContent = tapes.length > 1
      ? Drupal.t('Tape @n of @total', { '@n': tapes.indexOf(tape) + 1, '@total': tapes.length }) + ' · ' + which
      : which;
    view.label.replaceChildren(label);
    view.label.hidden = false;

    // A clip's own parts, so the panel sets them as it sets a clip's.
    const clip = document.createElement('div');
    clip.className = 'pf-exhibit-audio';
    clip.setAttribute('role', 'group');
    const kicker = document.createElement('p');
    kicker.className = 'pf-exhibit-label__kicker';
    kicker.textContent = Drupal.t('Listen');
    clip.append(kicker);
    const credit = [card.dataset.pfBy, card.dataset.pfAccession].filter(Boolean).join(' · ');
    if (credit) {
      const p = document.createElement('p');
      p.className = 'pf-exhibit-audio__credit';
      p.textContent = credit;
      clip.append(p);
    }
    const wrap = document.createElement('div');
    wrap.className = 'pf-audio';
    wrap.dataset.pfAudio = '';
    const audio = document.createElement('audio');
    audio.className = 'pf-audio__native';
    audio.controls = true;
    audio.preload = 'none';
    const source = document.createElement('source');
    source.src = tape.href;
    source.type = 'audio/mpeg';
    audio.append(source);
    wrap.append(audio);
    clip.append(wrap);
    const url = tape.dataset.pfTimings;
    const folded = document.createElement('details');
    folded.className = 'pf-exhibit-audio__transcript';
    const text = document.createElement('div');
    text.className = 'pf-exhibit-audio__text pf-guide-prose';
    folded.append(text);
    if (url) {
      wrap.dataset.pfTimings = url;
      clip.append(folded);
    }
    else {
      const none = document.createElement('p');
      none.className = 'pf-exhibit-audio__credit';
      none.textContent = Drupal.t('This tape has no transcript.');
      clip.append(none);
    }

    const show = () => {
      view.body.replaceChildren(clip);
      panelAudio(view);
      view.panel.scrollTop = 0;
    };
    view.body.replaceChildren();
    if (url) {
      const at = interviews.index;
      view.panel.setAttribute('aria-busy', 'true');
      interviewText(url).then((paras) => {
        if (interviews.list !== list || interviews.index !== at) {
          return;
        }
        paras.forEach((para) => {
          const p = document.createElement('p');
          if (para.who) {
            const who = document.createElement('strong');
            who.textContent = para.who + ':';
            p.append(who, ' ');
          }
          p.append(para.words.join(' '));
          text.append(p);
        });
        view.panel.removeAttribute('aria-busy');
        show();
      });
    }
    else {
      show();
    }

    setWash(view, photo ? (photo.currentSrc || photo.getAttribute('src')) : null);
    if (!photo) {
      // No portrait: still the text over the screen on phones, no thumbnail.
      view.dialog.classList.add('is-clip');
    }
    view.scale.hidden = true;
    view.scale.replaceChildren();
    view.count.textContent = Drupal.t('@n of @total', { '@n': interviews.index + 1, '@total': n });
    viewer.sync({ steps: n });
    view.phoneSteps.querySelectorAll('button').forEach((b) => {
      b.hidden = n < 2;
    });
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
    // Every tile on the wall, across its sub-galleries' lists.
    wallState.tiles = Array.from(tile.closest('[data-pf-wall]').querySelectorAll('[data-pf-tile]'));
    const index = wallState.tiles.indexOf(tile);
    history.replaceState(history.state, '', location.pathname + location.search + '#' + tile.dataset.pfTile);
    const side = !!tile.querySelector('.pf-exhibit-reel');
    viewer.show(side ? sideHandler : objectHandler, () => fillObject(index), { from: animate && !side ? tile.querySelector('.pf-exhibit-wall__img') : null, kind: 'exhibit', side: side });
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
    else if (viewer.isSide()) {
      // A side panel: the reader has gone on down the page; no jump back.
      thumb = null;
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
    // A figure's link to another site (a book cover) is marked in its
    // template, with the arrow in the image's corner.
    if (!/^https?:$/.test(url.protocol) || url.host === location.host || link.classList.contains('pf-exhibit-figure__out')) {
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

  // A clip with no picture plays on a tape deck on the object view's stage
  // (images/exhibit-deck.svg, fetched the first time one is shown, inline
  // so the theme colors it). Its reels, PLAY key and pinch roller follow
  // the player (_exhibition.scss); here its badge takes the tile's year,
  // its counter the clip's time, PAUSE lights while the clip waits
  // part-way, and its needles follow the sound. Where the deck cannot be
  // had, the tile's reel stands in.
  let deckMarkup = null;

  function stageDeck(view, frame, reel) {
    deckMarkup = deckMarkup || fetch(reel.dataset.pfDeck).then((response) => {
      if (!response.ok) {
        throw new Error(response.statusText);
      }
      return response.text();
    });
    deckMarkup.then((markup) => {
      const svg = new DOMParser().parseFromString(markup, 'image/svg+xml').documentElement;
      if (svg.localName !== 'svg') {
        throw new Error('exhibit-deck.svg');
      }
      // Stepped on to another entry meanwhile.
      if (!frame.isConnected) {
        return;
      }
      const deck = document.createElement('div');
      deck.className = 'pf-deck';
      deck.append(document.importNode(svg, true));
      frame.replaceChildren(deck);
      const head = reel.querySelectorAll('.pf-exhibit-reel__head > span');
      const kind = deck.querySelector('.kind');
      kind.textContent = head[0].textContent.trim().toLocaleUpperCase();
      deck.querySelector('.year').textContent = head[1].textContent.trim();
      // A longer word for "Audio only" is pressed to its box.
      if (kind.getComputedTextLength() > 88) {
        kind.setAttribute('textLength', '88');
        kind.setAttribute('lengthAdjust', 'spacingAndGlyphs');
      }
      deckAudio(view, deck);
    }).catch(() => {
      deckMarkup = null;
      if (frame.isConnected && !frame.firstChild) {
        frame.append(reel.cloneNode(true));
      }
    });
  }

  function deckAudio(view, deck) {
    const audio = view.body.querySelector('[data-pf-audio] audio');
    if (!audio) {
      return;
    }
    const count = deck.querySelector('.count');
    const sync = () => {
      const time = clock(audio.currentTime);
      count.textContent = time.length < 5 ? '0' + time : time;
      deck.classList.toggle('is-paused', audio.paused && audio.currentTime > 0 && !audio.ended);
    };
    ['play', 'pause', 'timeupdate', 'seeked', 'loadedmetadata', 'ended'].forEach((type) => audio.addEventListener(type, sync));
    sync();
    audio.addEventListener('play', () => deckMeter(audio, deck));
    if (!audio.paused) {
      deckMeter(audio, deck);
    }
  }

  // The needles, on a desktop with motion: the clip is led through Web
  // Audio and each channel's loudness read every frame, with a VU meter's
  // 300ms swing (0 VU at -18 dBFS). Clips from this site only: another
  // host's would go silent through it. Once led through, a clip stays
  // so (one source per <audio>), so it waits for the context to run.
  let deckContext = null;
  const deckMeters = new WeakMap();
  // dB on the meter's scale, and the needle's turn there.
  const VU_SCALE = [[-26, -38], [-20, -36], [-10, -25], [-7, -17], [-5, -10], [-3, -3], [0, 16], [3, 34]];

  function deckMeter(audio, deck) {
    if (reduceMotion() || !window.AudioContext || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
      return;
    }
    let source;
    try {
      source = new URL(audio.currentSrc || audio.src, window.location.href);
    }
    catch (e) {
      return;
    }
    if (source.origin !== window.location.origin) {
      return;
    }
    deckContext = deckContext || new AudioContext();
    deckContext.resume().then(() => {
      if (deckContext.state !== 'running' || !deck.isConnected) {
        return;
      }
      let meter = deckMeters.get(audio);
      if (!meter) {
        const input = deckContext.createMediaElementSource(audio);
        const split = deckContext.createChannelSplitter(2);
        input.connect(deckContext.destination);
        input.connect(split);
        const sides = [0, 1].map((channel) => {
          const analyser = deckContext.createAnalyser();
          analyser.fftSize = 1024;
          split.connect(analyser, channel);
          return analyser;
        });
        meter = { sides, data: new Float32Array(1024), level: [-30, -30], running: false };
        deckMeters.set(audio, meter);
      }
      const needles = deck.querySelectorAll('.needle');
      if (meter.running) {
        return;
      }
      meter.running = true;
      let last = performance.now();
      const step = (now) => {
        const dt = Math.min(0.1, (now - last) / 1000);
        last = now;
        const db = meter.sides.map((analyser) => {
          analyser.getFloatTimeDomainData(meter.data);
          let sum = 0;
          for (let i = 0; i < meter.data.length; i++) {
            sum += meter.data[i] * meter.data[i];
          }
          return audio.paused ? -30 : Math.max(-30, 20 * Math.log10(Math.sqrt(sum / meter.data.length) || 1e-6) + 18);
        });
        // A mono clip feeds its one channel to both.
        if (db[1] <= -30 && db[0] > -30) {
          db[1] = db[0];
        }
        meter.level = meter.level.map((level, i) => level + (db[i] - level) * (1 - Math.exp(-dt / 0.3)));
        needles.forEach((needle, i) => {
          needle.style.rotate = deckTurn(meter.level[i]) + 'deg';
        });
        if (deck.isConnected && (!audio.paused || meter.level.some((level) => level > -29.5))) {
          window.requestAnimationFrame(step);
        }
        else {
          meter.running = false;
        }
      };
      window.requestAnimationFrame(step);
    }).catch(() => {});
  }

  function deckTurn(db) {
    if (db <= VU_SCALE[0][0]) {
      return -38;
    }
    for (let i = 1; i < VU_SCALE.length; i++) {
      const [d1, a1] = VU_SCALE[i];
      if (db <= d1) {
        const [d0, a0] = VU_SCALE[i - 1];
        return a0 + (a1 - a0) * (db - d0) / (d1 - d0);
      }
    }
    return 38;
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

  // A transcript's speaker, "<p><strong>PAYNE:</strong> …", as a label
  // over what they said (.pf-speaker, _exhibition.scss): a <strong> that
  // opens its paragraph and ends in a colon. The colon stays for screen
  // readers (the pause before the words), out of sight.
  function markSpeakers(text) {
    text.querySelectorAll('p > strong:first-child').forEach((name) => {
      const before = name.previousSibling;
      const label = name.textContent.trim();
      if ((before && before.textContent.trim()) || !label.endsWith(':') || name.classList.contains('pf-speaker')) {
        return;
      }
      const colon = document.createElement('span');
      colon.className = 'visually-hidden';
      colon.textContent = ':';
      name.textContent = label.slice(0, -1).trim();
      name.append(colon);
      name.classList.add('pf-speaker');
    });
  }

  function initAudio(wrap) {
    const audio = wrap.querySelector('audio');
    // Once only: an address for a wall's entry opens the object view
    // before the behaviors attach, and their once() would set up the
    // view's player (already set up by panelAudio()) a second time.
    if (!audio || wrap.classList.contains('is-custom')) {
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
    initTimings(wrap, audio);
  }

  // Timed transcripts: a clip whose player names a WebVTT file
  // (data-pf-timings, palefire.theme) lights the phrase being heard. Each
  // cue's text is the transcript's own wording
  // (scripts/palefire_oral_history_vtt.py), found in the page and wrapped
  // in a span. Once the clip has played, the phrases heard so far are lit
  // and the rest muted (_exhibition.scss); a click on a phrase plays from it,
  // and the text follows the voice while the reader is following it.
  const timings = new Map();

  function vttSeconds(stamp) {
    return stamp.split(':').reduce((sum, part) => sum * 60 + Number(part), 0);
  }

  function loadTimings(url) {
    if (!timings.has(url)) {
      const entities = { amp: '&', lt: '<', gt: '>', nbsp: ' ' };
      timings.set(url, fetch(url)
        .then((response) => (response.ok ? response.text() : ''))
        .then((text) => text.replace(/\r\n?/g, '\n').split(/\n{2,}/).map((block) => {
          const lines = block.split('\n');
          const at = lines.findIndex((line) => line.includes('-->'));
          if (at < 0) {
            return null;
          }
          const [start, end] = lines[at].split('-->').map((part) => vttSeconds(part.trim().split(/\s/)[0]));
          const words = lines.slice(at + 1).join(' ')
            .replace(/<[^>]*>/g, '')
            .replace(/&(amp|lt|gt|nbsp);/g, (match, name) => entities[name])
            .trim();
          return words ? { start, end, words } : null;
        }).filter(Boolean))
        .catch(() => []));
    }
    return timings.get(url);
  }

  // Wraps each cue's words in the transcript, in order: [{start, els}]. A
  // cue whose words aren't found (the text edited since) is left out. The
  // object view's copy of an entry arrives already wrapped.
  function phrasesOf(text, cues) {
    const phrases = cues.map((cue) => ({ start: cue.start, els: [] }));
    if (!text.querySelector('.pf-phrase')) {
      const nodes = [];
      let full = '';
      const walker = document.createTreeWalker(text, NodeFilter.SHOW_TEXT);
      while (walker.nextNode()) {
        nodes.push({ node: walker.currentNode, at: full.length });
        full += walker.currentNode.data;
      }
      const ranges = [];
      let from = 0;
      cues.forEach((cue, index) => {
        const pattern = new RegExp(cue.words.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/\s+/g, '\\s+'), 'g');
        pattern.lastIndex = from;
        const match = pattern.exec(full);
        if (match) {
          ranges.push({ index, start: match.index, end: match.index + match[0].length });
          from = pattern.lastIndex;
        }
      });
      // Last first, so splitting a text node keeps the earlier offsets.
      ranges.reverse().forEach((range) => {
        nodes.filter((n) => n.at < range.end && n.at + n.node.data.length > range.start).reverse().forEach((n) => {
          let piece = n.node;
          const end = Math.min(range.end - n.at, piece.data.length);
          const start = Math.max(range.start - n.at, 0);
          if (end < piece.data.length) {
            piece.splitText(end);
          }
          if (start > 0) {
            piece = piece.splitText(start);
          }
          if (!piece.data.trim()) {
            return;
          }
          const span = document.createElement('span');
          span.className = 'pf-phrase';
          span.dataset.pfCue = String(range.index);
          piece.replaceWith(span);
          span.append(piece);
        });
      });
    }
    text.querySelectorAll('.pf-phrase').forEach((span) => {
      span.classList.remove('is-current', 'is-past');
      const phrase = phrases[Number(span.dataset.pfCue)];
      if (phrase) {
        phrase.els.push(span);
      }
    });
    text.classList.remove('is-following');
    return phrases;
  }

  // The nearest box that scrolls the transcript (the object view's panel),
  // or null: the page.
  function scrollerOf(el) {
    for (let parent = el.parentElement; parent && parent !== document.body; parent = parent.parentElement) {
      if (/(auto|scroll)/.test(getComputedStyle(parent).overflowY) && parent.scrollHeight > parent.clientHeight) {
        return parent;
      }
    }
    return null;
  }

  // reading: wholly within the frame's upper four fifths (eye level, not
  // the foot of the screen); otherwise any part showing. Below the
  // object view's pinned player (cover), not behind it.
  function inView(el, scroller, cover, reading) {
    const box = el.getBoundingClientRect();
    if (!box.height) {
      return false;
    }
    const frame = scroller ? scroller.getBoundingClientRect() : { top: 0, bottom: window.innerHeight };
    const top = cover && getComputedStyle(cover).position === 'sticky' ? Math.max(frame.top, cover.getBoundingClientRect().bottom) : frame.top;
    return reading
      ? box.top >= top && box.bottom <= top + (frame.bottom - top) * 0.8
      : box.bottom > top && box.top < frame.bottom;
  }

  function initTimings(wrap, audio) {
    const url = wrap.dataset.pfTimings;
    const clip = wrap.closest('.pf-exhibit-audio');
    const text = clip && clip.querySelector('.pf-exhibit-audio__text');
    if (!url || !text || !window.IntersectionObserver) {
      return;
    }
    let phrases = null;
    let current = -1;
    let following = false;
    let frame = 0;
    let ready = null;
    const load = () => {
      ready = ready || loadTimings(url).then((cues) => {
        phrases = cues.length ? phrasesOf(text, cues) : null;
      });
      return ready;
    };

    const light = () => {
      if (!phrases || !following) {
        return;
      }
      const now = audio.currentTime;
      let index = -1;
      phrases.forEach((phrase, i) => {
        if (phrase.start <= now && phrase.els.length) {
          index = i;
        }
      });
      if (index === current) {
        return;
      }
      const was = current >= 0 ? phrases[current].els : [];
      current = index;
      // What has been heard stays lit, as in a lyrics view; a seek back
      // unlights what comes after.
      phrases.forEach((phrase, i) => phrase.els.forEach((el) => {
        el.classList.toggle('is-past', i < index);
        el.classList.toggle('is-current', i === index);
      }));
      if (index < 0) {
        return;
      }
      const els = phrases[index].els;
      // Follow on only from a phrase the reader could see, so text they
      // scrolled away to read stays put.
      const scroller = scrollerOf(text);
      if (was.length && inView(was[was.length - 1], scroller, wrap, false) && !inView(els[0], scroller, wrap, true)) {
        els[0].scrollIntoView({ block: 'center', behavior: reduceMotion() ? 'auto' : 'smooth' });
      }
    };

    // Per frame while playing: timeupdate comes only about four times a
    // second, late for a phrase's first word. It still counts where frames
    // stop (a tab in the background).
    const tick = () => {
      light();
      frame = audio.paused ? 0 : window.requestAnimationFrame(tick);
    };
    audio.addEventListener('play', () => {
      load().then(() => {
        following = Boolean(phrases);
        text.classList.toggle('is-following', following);
        if (!frame) {
          tick();
        }
      });
    });
    audio.addEventListener('timeupdate', light);
    audio.addEventListener('seeked', light);
    audio.addEventListener('ended', () => {
      following = false;
      current = -1;
      text.classList.remove('is-following');
      text.querySelectorAll('.pf-phrase').forEach((el) => el.classList.remove('is-current', 'is-past'));
    });

    // A phrase plays from its start. Mouse only and only once the clip has
    // played: the seek slider is the keyboard's way, and a click while
    // selecting text, or on a link, does what it always does.
    text.addEventListener('click', (event) => {
      const phrase = event.target.closest('.pf-phrase');
      if (!following || !phrase || event.target.closest('a') || !window.getSelection().isCollapsed) {
        return;
      }
      audio.currentTime = phrases[Number(phrase.dataset.pfCue)].start;
      light();
    });

    // The cues load once the transcript shows (an open <details>, the
    // object view), not for every clip on the page.
    const observer = new IntersectionObserver((entries) => {
      if (entries.some((entry) => entry.isIntersecting)) {
        observer.disconnect();
        load();
      }
    }, { rootMargin: '200px' });
    observer.observe(text);
  }

  // The breadcrumb's exhibition switcher (navigation/breadcrumb.html.twig):
  // a native popover, which the browser opens, closes and returns focus
  // from. Set under its button, kept inside the screen, and following the
  // button while open (the header's bar moves as the page scrolls).
  function initFamily(list) {
    const button = document.querySelector('[popovertarget="' + list.id + '"]');
    if (!button || typeof list.showPopover !== 'function') {
      return;
    }
    let frame = 0;
    const place = () => {
      frame = 0;
      const box = button.getBoundingClientRect();
      const gap = 8;
      // Its CSS width (_exhibition.scss): it is not laid out yet when opening.
      const rem = parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
      const width = list.offsetWidth || Math.min(24 * rem, window.innerWidth - 2 * rem);
      const start = Math.max(gap, Math.min(box.left, window.innerWidth - width - gap));
      list.style.setProperty('--pf-family-top', Math.round(box.bottom + gap) + 'px');
      list.style.setProperty('--pf-family-left', Math.round(start) + 'px');
    };
    const queue = () => {
      if (!frame) {
        frame = requestAnimationFrame(place);
      }
    };
    let controller = null;
    list.addEventListener('beforetoggle', (event) => {
      if (event.newState === 'open') {
        place();
        controller = new AbortController();
        window.addEventListener('scroll', queue, { passive: true, signal: controller.signal });
        window.addEventListener('resize', queue, { passive: true, signal: controller.signal });
        // Open on the current exhibition, so the list starts where the
        // reader is.
        requestAnimationFrame(() => {
          const current = list.querySelector('[aria-current="page"]');
          if (current) {
            current.scrollIntoView({ block: 'nearest' });
          }
        });
      }
      else if (controller) {
        controller.abort();
        controller = null;
      }
    });
    button.setAttribute('aria-controls', list.id);
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
      once('pf-interview', '[data-pf-interview]', context).forEach((card) => {
        card.addEventListener('click', (event) => {
          const link = event.target.closest('a');
          if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || !window.HTMLDialogElement) {
            return;
          }
          const tape = link.matches('[data-pf-tape]') ? link : card.querySelector('a[data-pf-tape]');
          if (tape) {
            event.preventDefault();
            openInterview(tape, link);
          }
        });
      });
      once('pf-exhibit-out', '.pf-main a[href]', context).forEach(markOutside);
      once('pf-speakers', '.pf-exhibit-audio__text', context).forEach(markSpeakers);
      once('pf-audio', '[data-pf-audio]', context).forEach(initAudio);
      once('pf-exhibit-tip', '[data-pf-exhibit-tip]', context).forEach(initTip);
      once('pf-exhibit-family', '.pf-exhibit-family[popover]', context).forEach(initFamily);
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
      dismissBar();
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
