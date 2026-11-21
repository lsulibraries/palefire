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
 *
 * An exhibition's view (kind 'exhibit', not a side panel) is a stage
 * (.is-staged, _exhibition.scss): the page blurred behind a centered
 * frame, the top row (count, gallery, Close) across its head over a rule,
 * the picture and the text in two columns from lg with a divider between,
 * and the arrows at the foot of the text (.pf-viewer__steps). The rules
 * draw as it opens (.is-framed), and a step fades the text out and draws
 * it in again (.is-in, leave()).
 *
 * show(…, { side: true }) opens the panel alone (.is-side), beside the page
 * from lg and as a sheet at the foot of the screen below it, and without
 * the modal: the page stays in reach and scrolls, so a reader can go on
 * through it with the panel open. Esc still closes it from inside. Shown
 * the other way while open (a picture opened from the page), the dialog
 * reopens in that mode, without the handler hearing a close.
 *
 * sync({ hide: true }) offers the reader the same switch: Hide image on the
 * stage folds the stage away and leaves the panel open beside the page
 * (whatever plays in it plays on), and Show image in the panel's top row
 * brings the stage back. sync({ extra }) puts a page's own buttons in that
 * row, before Close (.pf-viewer__act). Beside them Close is a square too:
 * icons in a window's own language (minimize, maximize, close), each
 * named in a small label on hover and focus (.pf-viewer__name).
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
    hide: '<path d="M2.5 3.5h11v9h-11z"/><path d="M9.5 3.5v9M4.5 8h3M6 6.5 7.5 8 6 9.5"/>',
    image: '<path d="M3.5 3.5h9v9h-9z"/>',
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
      '<div class="pf-viewer__head"><p class="pf-viewer__gallery"></p></div>'
      + '<div class="pf-viewer__stage">'
      + '<div class="pf-viewer__media"></div>'
      + '<div class="pf-viewer__tools">'
      + '<button type="button" class="pf-viewer__tool pf-viewer__hide" hidden>' + icon('hide') + '<span>' + Drupal.t('Hide image') + '</span></button>'
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
      + '<div class="pf-viewer__actions">'
      + '<span class="pf-viewer__extra"></span>'
      + '<button type="button" class="pf-viewer__act pf-viewer__unhide" hidden>' + icon('image') + '<span class="pf-viewer__name">' + Drupal.t('Show image') + '</span></button>'
      + '<button type="button" class="pf-viewer__close">' + icon('close') + '<span class="pf-viewer__name">' + Drupal.t('Close') + '</span></button>'
      + '</div>'
      + '</div>'
      + '<h2 class="pf-viewer__title" id="pf-viewer-title"></h2>'
      + '<div class="pf-viewer__label pf-guide-prose"></div>'
      + '<div class="pf-viewer__body"></div>'
      + '</div>'
      + '<div class="pf-viewer__steps"><span class="pf-viewer__hint" aria-hidden="true">' + Drupal.t('← → to step') + '</span><span class="pf-viewer__pair"></span></div>';
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
      hide: dialog.querySelector('.pf-viewer__hide'),
      unhide: dialog.querySelector('.pf-viewer__unhide'),
      extra: dialog.querySelector('.pf-viewer__extra'),
      steps: dialog.querySelectorAll('.pf-viewer__step'),
      prev: dialog.querySelector('.pf-viewer__prev'),
      next: dialog.querySelector('.pf-viewer__next'),
      top: dialog.querySelector('.pf-viewer__top'),
      head: dialog.querySelector('.pf-viewer__head'),
      gallery: dialog.querySelector('.pf-viewer__gallery'),
      foot: dialog.querySelector('.pf-viewer__steps'),
      pair: dialog.querySelector('.pf-viewer__pair'),
    };
    const step = (delta) => {
      if (handler.step) {
        leave(() => handler.step(delta));
      }
    };
    const shut = () => (handler.close ? handler.close() : close(null));
    dialog.querySelector('.pf-viewer__prev').addEventListener('click', () => step(-1));
    dialog.querySelector('.pf-viewer__next').addEventListener('click', () => step(1));
    dialog.querySelector('.pf-viewer__close').addEventListener('click', shut);
    dialog.querySelector('.pf-viewer__shut').addEventListener('click', shut);
    parts.info.addEventListener('click', () => setFull(!dialog.classList.contains('is-full'), true));
    parts.hide.addEventListener('click', () => setSide(true));
    parts.unhide.addEventListener('click', () => setSide(false));
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
      // A side panel is no modal, so no "cancel": Esc closes it here.
      if (event.key === 'Escape' && parts.side) {
        event.preventDefault();
        shut();
        return;
      }
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
    // transition is skipped). "close" comes a task later, so a dialog open
    // again by then was only reopened the other way (setSide(), show()).
    dialog.addEventListener('close', () => {
      if (!dialog.open) {
        closed();
      }
    });
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

  // The stage folded away, the panel left open beside the page (on), or
  // the stage back (off): the dialog reopens the other way, its content
  // untouched, so a clip in the panel plays on. Focus goes to the button
  // that switches back.
  function setSide(on) {
    if (!view || !view.dialog.open || !!view.side === on) {
      return;
    }
    view.dialog.close();
    view.side = on;
    view.switched = on;
    view.dialog.classList.toggle('is-side', on);
    place(view);
    if (on) {
      ROOT.classList.remove('pf-viewer-open');
      view.dialog.show();
    }
    else {
      ROOT.classList.add('pf-viewer-open');
      view.dialog.showModal();
    }
    view.hide.hidden = on || !view.canHide;
    view.unhide.hidden = !on;
    setFull(!on && (view.bare || savedFull()), false);
    (on ? view.unhide : view.hide).focus();
  }

  // After the page has filled the parts. steps: how many things the arrows
  // step through (under 2, no arrows). bare: nothing in the panel worth a
  // column, so it stays folded and its button goes. zoom: the Zoom button
  // shows (default: when the stage has something zoomable). hide: Hide
  // image shows (the reader may want the panel alone). extra: the page's
  // own buttons for the panel's top row. text: the panel is the thing (an
  // exhibition's text plate), so it is never folded away and has no fold.
  function sync({ steps = 0, bare = false, zoom: zoomable, hide = false, extra = [], text = false } = {}) {
    view.steps.forEach((button) => {
      button.hidden = steps < 2;
    });
    view.zoom.hidden = zoomable === undefined ? !view.media.querySelector('.pf-viewer__zoomable') : !zoomable;
    view.zoom.setAttribute('aria-pressed', 'false');
    // On the stage the text is a column of its own, not a fold.
    const staged = view.dialog.classList.contains('is-staged');
    view.info.hidden = bare || text || staged;
    view.bare = bare;
    view.canHide = hide;
    view.hide.hidden = !hide || view.side;
    view.unhide.hidden = !(view.side && view.switched);
    view.extra.replaceChildren(...extra);
    // A side panel is the text alone: never folded away.
    setFull(!view.side && !text && (bare || (!staged && savedFull())), false);
    view.panel.scrollTop = 0;
  }

  // Where the top row and the arrows go: on an exhibition's stage, the row
  // across the head and the arrows at the text's foot; anywhere else, the
  // row atop the panel and the arrows at the stage's sides. Moved only
  // when they are elsewhere, so a focused button keeps its focus.
  function place(parts) {
    const staged = parts.kind === 'exhibit' && !parts.side;
    parts.dialog.classList.toggle('is-staged', staged);
    if (staged && parts.top.parentElement !== parts.head) {
      parts.head.append(parts.top);
      parts.pair.append(parts.prev, parts.next);
    }
    else if (!staged && parts.top.parentElement !== parts.panel) {
      parts.panel.prepend(parts.top);
      parts.stage.append(parts.prev, parts.next);
    }
  }

  // A step on the stage: the text fades and its rules draw back to the
  // right (.is-in off), then fill() puts the next thing in and enter()
  // draws it in from the left. A switch between a picture and a text
  // entry (.is-reading) draws the head's rule and the divider again.
  // Steps asked for meanwhile are dropped. Anywhere else, fill() at once.
  let leaving = false;
  function leave(fill) {
    const dialog = view && view.dialog;
    if (!dialog || !dialog.classList.contains('is-staged')) {
      fill();
      return;
    }
    if (leaving) {
      return;
    }
    leaving = true;
    const was = dialog.classList.contains('is-reading');
    dialog.classList.remove('is-in');
    window.setTimeout(() => {
      leaving = false;
      fill();
      enter(dialog.classList.contains('is-reading') !== was);
    }, reduceMotion() ? 120 : 260);
  }

  // Draws the stage in: reframe draws its rules again first. On opening,
  // the rules draw a moment before the text rises in.
  function enter(reframe, opening) {
    const dialog = view.dialog;
    if (!dialog.classList.contains('is-staged')) {
      return;
    }
    if (reframe) {
      dialog.classList.remove('is-framed', 'is-in');
    }
    // Laid out undrawn first, so the drawing plays.
    void dialog.offsetWidth;
    dialog.classList.add('is-framed');
    if (opening && !reduceMotion()) {
      window.setTimeout(() => dialog.classList.add('is-in'), 320);
    }
    else {
      dialog.classList.add('is-in');
    }
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
  // side: the panel alone, beside the page and without the modal.
  function show(next, fill, { from = null, kind = '', side = false } = {}) {
    // Another kind of page (Swup keeps the dialog between pages): a fresh
    // one, without the last page's own parts.
    if (view && view.kind && view.kind !== kind && !view.dialog.open) {
      destroy();
    }
    const parts = get();
    parts.kind = kind;
    handler = next || {};
    // Open the other way: closed quietly (shown cleared first, so the
    // handler hears no close) and opened again in this mode below. Focus
    // goes back where it was if that is still to be seen (an arrow), or
    // else to the panel, not to Close: the reader is stepping on, not
    // leaving, and a ring on Close after a scroll reads as a selection.
    let keep = null;
    if (parts.dialog.open && !!parts.side !== side) {
      const was = document.activeElement;
      keep = parts.dialog.contains(was) && !was.matches('.pf-viewer__close, .pf-viewer__shut') ? was : parts.panel;
      shown = false;
      ROOT.classList.remove('pf-viewer-open');
      parts.dialog.close();
    }
    parts.side = side;
    parts.switched = false;
    // Still open (filled again): drawn as it was.
    const carry = ['is-full'].concat(parts.dialog.open ? ['is-framed', 'is-in'] : []).filter((name) => parts.dialog.classList.contains(name));
    parts.dialog.className = 'pf-viewer' + (kind ? ' pf-viewer--' + kind : '') + (side ? ' is-side' : '') + carry.map((name) => ' ' + name).join('');
    place(parts);
    const open = () => {
      fill(parts);
      if (!parts.dialog.open) {
        if (side) {
          parts.dialog.show();
        }
        else {
          ROOT.classList.add('pf-viewer-open');
          parts.dialog.showModal();
        }
        shown = true;
        enter(true, true);
        if (!keep) {
          focusClose();
        }
        else if (keep !== parts.panel && keep.isConnected && keep.getClientRects().length) {
          keep.focus({ preventScroll: true });
        }
        else {
          parts.panel.tabIndex = -1;
          parts.panel.focus({ preventScroll: true });
        }
      }
    };
    if (parts.dialog.open || side || !canMorph() || !inSight(from)) {
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
    setSide: setSide,
    inSight: inSight,
    isOpen: () => !!view && view.dialog.open,
    isSide: () => !!view && view.dialog.open && !!view.side,
  };
})(Drupal);
