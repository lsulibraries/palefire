/**
 * @file
 * The full-screen viewer: one <dialog> for whatever a page shows large (an
 * exhibition's entries and images, js/exhibition.js; a newspaper family
 * tree, js/family-tree-viewer.js). _viewer.scss sets it.
 *
 * The stage holds the thing itself, with Zoom, a button (or I) that folds
 * the text panel away, Close, and the previous / next arrows (also the
 * arrow keys and a swipe). The panel beside it (below it on phones) has a
 * pinned row (count, Close) over a title, a label and the text.
 *
 * A page drives it with a handler (Drupal.palefireViewer.show()):
 * - step(delta): the arrows, arrow keys and swipe. Without it, none.
 * - close(): Close, the stage's ×, Esc. Usually viewer.close(); a page can
 *   go back a level instead (an image opened from an entry).
 * - closed(): after the dialog has closed (address, focus).
 * - zoom(): the Zoom button, when the page zooms its own way; otherwise
 *   the stage's .pf-viewer__zoomable zooms 2.5× where clicked, and a
 *   finger drags it around.
 * The page fills the parts (view.media, view.title …) in show()'s fill
 * callback, then calls sync() to set the tools for what it put there.
 *
 * Where the browser has view transitions, show() and close() grow the
 * stage's frame (.pf-viewer__frame) out of an element on the page and back
 * into it. Reduced motion (the OS setting or Settings > Reduce motion)
 * makes every change instant.
 */
(function (Drupal) {
  'use strict';

  const ROOT = document.documentElement;
  // The text panel folded away (the image full width), kept across visits.
  const FULL_KEY = 'pf-object-full';
  const ZOOM = 2.5;
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');

  const ICON = {
    close: '<path d="M3.5 3.5l9 9m0-9-9 9"/>',
    prev: '<path d="M10 3.5 5.5 8l4.5 4.5"/>',
    next: '<path d="M6 3.5 10.5 8 6 12.5"/>',
    zoom: '<circle cx="7" cy="7" r="4.25"/><path d="M10.2 10.2 13.5 13.5M5 7h4"/><path class="pf-viewer__plus" d="M7 5v4"/>',
    panel: '<path d="M2.5 3.5h11v9h-11z"/><path class="pf-viewer__side" d="M9.5 3.5v9"/><path class="pf-viewer__foot" d="M2.5 9.5h11"/>',
  };

  function icon(name) {
    return '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="square" aria-hidden="true" focusable="false">' + ICON[name] + '</svg>';
  }

  function reduceMotion() {
    return motion.matches || ROOT.classList.contains('pf-a11y-motion');
  }

  function canMorph() {
    return !reduceMotion() && typeof document.startViewTransition === 'function';
  }

  function savedFull() {
    try {
      return window.localStorage.getItem(FULL_KEY) === '1';
    }
    catch (e) {
      return false;
    }
  }

  let view = null;
  let handler = {};

  function build() {
    const dialog = document.createElement('dialog');
    dialog.className = 'pf-viewer';
    dialog.setAttribute('aria-labelledby', 'pf-viewer-title');
    dialog.innerHTML =
      '<div class="pf-viewer__stage">'
      + '<div class="pf-viewer__media"></div>'
      + '<div class="pf-viewer__tools">'
      + '<button type="button" class="pf-viewer__tool pf-viewer__zoom" aria-pressed="false">' + icon('zoom') + '<span class="visually-hidden">' + Drupal.t('Zoom') + '</span></button>'
      + '<button type="button" class="pf-viewer__tool pf-viewer__info" aria-controls="pf-viewer-panel">' + icon('panel') + '<span class="visually-hidden">' + Drupal.t('Text beside the image') + '</span></button>'
      + '<button type="button" class="pf-viewer__tool pf-viewer__shut">' + icon('close') + '<span class="visually-hidden">' + Drupal.t('Close') + '</span></button>'
      + '</div>'
      + '<button type="button" class="pf-viewer__step pf-viewer__prev">' + icon('prev') + '<span class="visually-hidden">' + Drupal.t('Previous') + '</span></button>'
      + '<button type="button" class="pf-viewer__step pf-viewer__next">' + icon('next') + '<span class="visually-hidden">' + Drupal.t('Next') + '</span></button>'
      + '</div>'
      + '<div class="pf-viewer__panel" id="pf-viewer-panel">'
      + '<div class="pf-viewer__top">'
      + '<p class="pf-viewer__count" aria-live="polite"></p>'
      + '<button type="button" class="pf-viewer__close">' + icon('close') + '<span>' + Drupal.t('Close') + '</span></button>'
      + '</div>'
      + '<h2 class="pf-viewer__title" id="pf-viewer-title"></h2>'
      + '<div class="pf-viewer__label pf-guide-prose"></div>'
      + '<div class="pf-viewer__body"></div>'
      + '</div>';
    document.body.append(dialog);
    const parts = {
      dialog: dialog,
      stage: dialog.querySelector('.pf-viewer__stage'),
      media: dialog.querySelector('.pf-viewer__media'),
      count: dialog.querySelector('.pf-viewer__count'),
      title: dialog.querySelector('.pf-viewer__title'),
      label: dialog.querySelector('.pf-viewer__label'),
      body: dialog.querySelector('.pf-viewer__body'),
      panel: dialog.querySelector('.pf-viewer__panel'),
      zoom: dialog.querySelector('.pf-viewer__zoom'),
      info: dialog.querySelector('.pf-viewer__info'),
      steps: dialog.querySelectorAll('.pf-viewer__step'),
    };
    const step = (delta) => {
      if (handler.step) {
        handler.step(delta);
      }
    };
    const shut = () => (handler.close ? handler.close() : close(null));
    dialog.querySelector('.pf-viewer__prev').addEventListener('click', () => step(-1));
    dialog.querySelector('.pf-viewer__next').addEventListener('click', () => step(1));
    dialog.querySelector('.pf-viewer__close').addEventListener('click', shut);
    dialog.querySelector('.pf-viewer__shut').addEventListener('click', shut);
    parts.info.addEventListener('click', () => setFull(!dialog.classList.contains('is-full'), true));
    parts.zoom.addEventListener('click', () => {
      if (handler.zoom) {
        handler.zoom();
        return;
      }
      const target = parts.media.querySelector('.pf-viewer__zoomable');
      if (target) {
        zoom(target, null);
      }
    });
    dialog.addEventListener('cancel', (event) => {
      event.preventDefault();
      shut();
    });
    dialog.addEventListener('keydown', (event) => {
      if (event.target.matches('input')) {
        return;
      }
      if (event.key === 'ArrowLeft' && handler.step) {
        step(-1);
      }
      else if (event.key === 'ArrowRight' && handler.step) {
        step(1);
      }
      else if ((event.key === 'i' || event.key === 'I') && !event.ctrlKey && !event.metaKey && !event.altKey && !parts.info.hidden) {
        setFull(!dialog.classList.contains('is-full'), true);
      }
    });
    // Click the thing to look closer where you clicked; move to look around
    // (a mouse) or drag (touch).
    let drag = null;
    let dragged = false;
    parts.media.addEventListener('click', (event) => {
      const target = event.target.closest('.pf-viewer__zoomable');
      if (!target || dragged) {
        dragged = false;
        return;
      }
      zoom(target, event);
    });
    parts.media.addEventListener('pointermove', (event) => {
      const target = event.target.closest('.is-zoomed .pf-viewer__zoomable');
      if (target && event.pointerType === 'mouse') {
        aim(target, event);
      }
      // Dragging moves the zoom's origin the opposite way, as far as the
      // image moves under the finger: at 2.5× that is 1 / 1.5 of the drag.
      if (drag && drag.target === target) {
        const r = target.getBoundingClientRect();
        const dx = event.clientX - drag.x;
        const dy = event.clientY - drag.y;
        if (Math.abs(dx) + Math.abs(dy) > 6) {
          dragged = true;
        }
        const x = Math.min(100, Math.max(0, drag.ox - dx / (r.width / ZOOM * (ZOOM - 1)) * 100));
        const y = Math.min(100, Math.max(0, drag.oy - dy / (r.height / ZOOM * (ZOOM - 1)) * 100));
        target.style.transformOrigin = x + '% ' + y + '%';
      }
    });
    // Swipe on touch screens (not while zoomed, on the slider, or on
    // something that scrolls itself).
    let startX = null;
    parts.media.addEventListener('pointerdown', (event) => {
      startX = event.pointerType === 'touch' && handler.step && !event.target.closest('.is-zoomed, input, [data-pf-viewer-scroll]') ? event.clientX : null;
      const target = event.pointerType !== 'mouse' && event.target.closest('.is-zoomed .pf-viewer__zoomable');
      dragged = false;
      drag = null;
      if (target) {
        const origin = getComputedStyle(target).transformOrigin.split(' ').map(parseFloat);
        const r = target.getBoundingClientRect();
        drag = { target: target, x: event.clientX, y: event.clientY, ox: origin[0] / (r.width / ZOOM) * 100, oy: origin[1] / (r.height / ZOOM) * 100 };
      }
    });
    parts.media.addEventListener('pointerup', (event) => {
      if (startX !== null && Math.abs(event.clientX - startX) > 50) {
        step(event.clientX < startX ? 1 : -1);
      }
      startX = null;
      drag = null;
    });
    parts.media.addEventListener('pointercancel', () => {
      startX = null;
      drag = null;
    });
    // Also run straight after close(): a dialog closed inside a view
    // transition's update doesn't always fire "close" (Chrome, when the
    // transition is skipped).
    dialog.addEventListener('close', closed);
    return parts;
  }

  // Once per opening: the page unlocks and the handler hears it.
  let shown = false;
  function closed() {
    if (!shown) {
      return;
    }
    shown = false;
    ROOT.classList.remove('pf-viewer-open');
    const done = handler.closed;
    handler = {};
    if (done) {
      done();
    }
  }

  function get() {
    view = view || build();
    return view;
  }

  function aim(target, event) {
    const r = target.getBoundingClientRect();
    target.style.transformOrigin = ((event.clientX - r.left) / r.width * 100) + '% ' + ((event.clientY - r.top) / r.height * 100) + '%';
  }

  // Zooms in where clicked (or the centre, from the Zoom button); again
  // zooms out. The first zoom swaps in the largest size (data-full, on the
  // target or the images in it).
  function zoom(target, event) {
    const frame = target.closest('.pf-viewer__frame');
    const swap = (el) => {
      el.srcset = '';
      el.src = el.dataset.full;
      delete el.dataset.full;
    };
    if (target.dataset.full) {
      swap(target);
      frame.classList.add('is-zoomed');
    }
    else if (target.querySelector('img[data-full]')) {
      target.querySelectorAll('img[data-full]').forEach(swap);
      frame.classList.add('is-zoomed');
    }
    else {
      frame.classList.toggle('is-zoomed');
    }
    if (event) {
      aim(target, event);
    }
    else {
      target.style.transformOrigin = '';
    }
    view.zoom.setAttribute('aria-pressed', String(frame.classList.contains('is-zoomed')));
  }

  // The text panel folded away or back. save: the reader's own choice,
  // kept for next time.
  function setFull(full, save) {
    // Focus in the panel (I pressed there) moves to the button first.
    if (full && view.panel.contains(document.activeElement)) {
      (view.info.hidden ? view.dialog.querySelector('.pf-viewer__shut') : view.info).focus();
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

  // After the page has filled the parts. steps: how many things the arrows
  // step through (under 2, no arrows). bare: nothing in the panel worth a
  // column, so it stays folded and its button goes. zoom: the Zoom button
  // shows (default: when the stage has something zoomable).
  function sync({ steps = 0, bare = false, zoom: zoomable } = {}) {
    view.steps.forEach((button) => {
      button.hidden = steps < 2;
    });
    view.zoom.hidden = zoomable === undefined ? !view.media.querySelector('.pf-viewer__zoomable') : !zoomable;
    view.zoom.setAttribute('aria-pressed', 'false');
    view.info.hidden = bare;
    setFull(bare || savedFull(), false);
    view.panel.scrollTop = 0;
  }

  // Focus on opening: whichever Close shows, in the panel or on the stage
  // (phones, or the panel folded).
  function focusClose() {
    const shown = Array.from(view.dialog.querySelectorAll('.pf-viewer__close, .pf-viewer__shut')).find((button) => button.getClientRects().length);
    if (shown) {
      shown.focus();
    }
  }

  // Whether an element is on screen (between the top and foot of the
  // window, below top).
  function inSight(el, top = 0) {
    const r = el && el.isConnected ? el.getBoundingClientRect() : null;
    return !!r && r.width > 0 && r.bottom > top && r.top < window.innerHeight;
  }

  // Opens the viewer with handler, after fill() has filled it; already
  // open, just fills it again (the handler changes). from: an element on
  // the page to grow the stage's frame out of, when it is on screen.
  // kind: a modifier for the page's own styles (.pf-viewer--<kind>).
  function show(next, fill, { from = null, kind = '' } = {}) {
    // Another kind of page (Swup keeps the dialog between pages): a fresh
    // one, without the last page's own parts.
    if (view && view.kind && view.kind !== kind && !view.dialog.open) {
      destroy();
    }
    const parts = get();
    parts.kind = kind;
    handler = next || {};
    parts.dialog.className = 'pf-viewer' + (kind ? ' pf-viewer--' + kind : '') + (parts.dialog.classList.contains('is-full') ? ' is-full' : '');
    const open = () => {
      fill(parts);
      if (!parts.dialog.open) {
        ROOT.classList.add('pf-viewer-open');
        parts.dialog.showModal();
        shown = true;
        focusClose();
      }
    };
    if (parts.dialog.open || !canMorph() || !inSight(from)) {
      open();
      return;
    }
    from.style.viewTransitionName = 'pf-object';
    document.startViewTransition(() => {
      from.style.viewTransitionName = '';
      open();
    });
  }

  // Closes the viewer, shrinking the frame into to when it is on screen.
  function close(to) {
    if (!view || !view.dialog.open) {
      return;
    }
    if (!canMorph() || !inSight(to)) {
      view.dialog.close();
      closed();
      return;
    }
    document.startViewTransition(() => {
      view.dialog.close();
      closed();
      to.style.viewTransitionName = 'pf-object';
    }).finished.finally(() => {
      to.style.viewTransitionName = '';
    });
  }

  // A page leaving (Swup): the dialog goes with it.
  function destroy() {
    if (view) {
      view.dialog.remove();
      view = null;
      handler = {};
      shown = false;
      ROOT.classList.remove('pf-viewer-open');
    }
  }

  Drupal.palefireViewer = {
    get: get,
    show: show,
    close: close,
    sync: sync,
    focusClose: focusClose,
    destroy: destroy,
    icon: icon,
    canMorph: canMorph,
    inSight: inSight,
    isOpen: () => !!view && view.dialog.open,
  };
})(Drupal);
