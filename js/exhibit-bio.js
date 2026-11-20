/**
 * @file
 * Biography cards in an exhibition (paragraph--exhibit-entry.html.twig,
 * pf-exhibit-entry--bio): "Read the biography" opens the whole life in the
 * viewer's side panel (js/viewer.js, show(…, { side: true })), beside the
 * gallery from lg and as a sheet at the foot of the screen below it. The
 * page stays in reach and scrolls; Esc or Close shuts it.
 *
 * - The panel: "Biography · 2 of 5" and Previous / Next in its top row
 *   (also the arrow keys), stepping through the gallery's cards, the page
 *   following along; the name; Life and Role; the summary as a lede; the
 *   biography; and its Sources, from the exhibition's own bibliography.
 * - A citation in the biography (a link to #src-…) shows its source in a
 *   note under its paragraph instead of leaving for the Sources gallery; a
 *   second click hides it.
 * - The address names the person while the panel is open (#schauensee),
 *   and goes back to what it was on close. Arriving at that address opens
 *   it.
 * - Without the script, or in a gallery hung as a wall (its entries open in
 *   the object view, js/exhibition.js), the biography shows in full under
 *   the card.
 * _exhibit-bio.scss sets the card and the panel's contents.
 */
(function (Drupal, once) {
  'use strict';

  const viewer = Drupal.palefireViewer;
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  // The open card's entry, the button that opened it, and the address
  // before opening.
  const state = { entry: null, opener: null, hash: null };

  const reduced = () => motion.matches || document.documentElement.classList.contains('pf-a11y-motion');

  function buttonOf(entry) {
    return entry.querySelector('.pf-exhibit-card__open');
  }

  // The gallery's ready cards, in page order.
  function siblings(entry) {
    const gallery = entry.closest('[data-pf-exhibit-page]') || document;
    return Array.from(gallery.querySelectorAll('.pf-exhibit-entry--bio.is-bio-ready'));
  }

  function cleanCopy(node) {
    const copy = node.cloneNode(true);
    copy.querySelectorAll('[id]').forEach((el) => el.removeAttribute('id'));
    return copy;
  }

  // A source from the exhibition's bibliography (#src-…), or null.
  function sourceFor(link) {
    const id = decodeURIComponent((link.getAttribute('href') || '').slice(1));
    const item = id ? document.getElementById(id) : null;
    return item && !item.closest('.pf-viewer') ? item : null;
  }

  function actButton(name, label, onClick) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'pf-viewer__act pf-viewer__' + name;
    button.innerHTML = viewer.icon(name) + '<span class="pf-viewer__name">' + label + '</span>';
    button.addEventListener('click', onClick);
    return button;
  }

  function fill(view) {
    const entry = state.entry;
    const list = siblings(entry);
    const index = list.indexOf(entry);
    const card = entry.querySelector('[data-pf-bio-card]');
    const full = entry.querySelector(':scope > [data-pf-bio-full] .pf-exhibit-bio__text');
    const summary = card.querySelector('.pf-exhibit-card__summary:not(.is-opening)');
    const facts = card.querySelector('.pf-exhibit-card__facts');

    view.dialog.classList.add('pf-viewer--bio');
    view.media.replaceChildren();
    view.count.textContent = list.length > 1
      ? Drupal.t('Biography · @n of @total', { '@n': index + 1, '@total': list.length })
      : Drupal.t('Biography');
    view.title.textContent = entry.querySelector('.pf-exhibit-entry__title').textContent.trim();
    view.label.replaceChildren(...(facts ? [cleanCopy(facts)] : []));
    view.label.hidden = !facts;

    // New each time, so its fade-in (CSS) plays on every step.
    const wrap = document.createElement('div');
    wrap.className = 'pf-exhibit-bio-panel';
    if (summary) {
      const lede = document.createElement('p');
      lede.className = 'pf-exhibit-bio-panel__lede';
      lede.textContent = summary.textContent.trim();
      wrap.append(lede);
    }
    const text = cleanCopy(full);
    wrap.append(text);
    const seen = new Set();
    const sources = document.createElement('ul');
    text.querySelectorAll('a[href^="#src-"]').forEach((link) => {
      link.setAttribute('aria-expanded', 'false');
      link.classList.add('pf-exhibit-bio-panel__cite');
      const item = sourceFor(link);
      if (item && !seen.has(item)) {
        seen.add(item);
        const li = document.createElement('li');
        li.append(...Array.from(cleanCopy(item).childNodes));
        sources.append(li);
      }
    });
    if (sources.children.length) {
      const section = document.createElement('section');
      section.className = 'pf-exhibit-bio-panel__sources';
      section.setAttribute('aria-label', Drupal.t('Sources'));
      section.innerHTML = '<h3 class="pf-exhibit-bio-panel__label" aria-hidden="true">' + Drupal.t('Sources') + '</h3>';
      section.append(sources);
      wrap.append(section);
    }
    view.body.replaceChildren(wrap);
    view.panel.scrollTop = 0;

    const prev = actButton('prev', Drupal.t('Previous'), () => step(-1));
    const next = actButton('next', Drupal.t('Next'), () => step(1));
    prev.disabled = index <= 0;
    next.disabled = index >= list.length - 1;
    viewer.sync({ extra: list.length > 1 ? [prev, next] : [] });

    document.querySelectorAll('.pf-exhibit-card__open[aria-expanded="true"]').forEach((b) => b.setAttribute('aria-expanded', 'false'));
    const button = buttonOf(entry);
    button.setAttribute('aria-expanded', 'true');
    button.setAttribute('aria-controls', view.panel.id);
  }

  const handler = {
    step: (delta) => step(delta),
    close: () => viewer.close(null),
    closed: () => {
      const opener = state.opener;
      state.entry = null;
      document.querySelectorAll('.pf-exhibit-card__open[aria-expanded="true"]').forEach((b) => b.setAttribute('aria-expanded', 'false'));
      if (state.hash !== null) {
        try {
          history.replaceState(history.state, '', location.pathname + location.search + state.hash);
        }
        catch (e) {
          // The address is a nicety.
        }
        state.hash = null;
      }
      // Back to the card, unless the reader has gone elsewhere on the page.
      const active = document.activeElement;
      if (opener && opener.isConnected && (!active || active === document.body || !active.isConnected || active.closest('.pf-viewer'))) {
        opener.focus({ preventScroll: true });
      }
    },
  };

  function open(entry, opener) {
    // The panel left open by another page part (a wall's object): ours now.
    if (state.entry && !viewer.isSide()) {
      state.entry = null;
    }
    if (state.entry === null && state.hash === null) {
      state.hash = location.hash;
    }
    state.entry = entry;
    state.opener = opener || buttonOf(entry);
    viewer.show(handler, fill, { kind: 'exhibit', side: true });
    try {
      history.replaceState(history.state, '', location.pathname + location.search + '#' + entry.querySelector('.pf-exhibit-entry__title').id);
    }
    catch (e) {
      // The address is a nicety.
    }
  }

  // Previous / Next: the next card in the gallery, the page following it.
  function step(delta) {
    if (!state.entry) {
      return;
    }
    const list = siblings(state.entry);
    const target = list[list.indexOf(state.entry) + delta];
    if (!target) {
      return;
    }
    open(target, buttonOf(target));
    target.scrollIntoView({ block: 'start', behavior: reduced() ? 'auto' : 'smooth' });
  }

  // A citation in the panel: its source in a note under the paragraph. On
  // the window, ahead of js/exhibition.js's capture on the document (which
  // would glide to the Sources gallery and skips a handled click).
  function onCite(event) {
    const link = event.target instanceof Element ? event.target.closest('.pf-viewer--bio .pf-exhibit-bio-panel a[href^="#src-"]') : null;
    if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }
    event.preventDefault();
    const shown = link.getAttribute('aria-expanded') === 'true';
    const noteId = link.getAttribute('aria-controls');
    const existing = noteId ? document.getElementById(noteId) : null;
    if (existing) {
      existing.remove();
    }
    link.setAttribute('aria-expanded', String(!shown));
    if (shown) {
      link.removeAttribute('aria-controls');
      return;
    }
    const item = sourceFor(link);
    const note = document.createElement('p');
    note.className = 'pf-exhibit-bio-panel__note';
    note.id = 'pf-bio-note-' + Math.random().toString(36).slice(2, 8);
    if (item) {
      note.append(...Array.from(cleanCopy(item).childNodes));
    }
    else {
      note.textContent = Drupal.t('See the exhibition’s Sources.');
    }
    link.setAttribute('aria-controls', note.id);
    const block = link.closest('p, li, blockquote') || link;
    let after = block;
    while (after.nextElementSibling && after.nextElementSibling.classList.contains('pf-exhibit-bio-panel__note')) {
      after = after.nextElementSibling;
    }
    after.after(note);
  }

  function init(entry) {
    // A wall's entries open in the object view, with the biography in full.
    if (!window.HTMLDialogElement || !viewer || entry.closest('.pf-gallery.is-walled')) {
      return;
    }
    const button = buttonOf(entry);
    if (!button || !entry.querySelector(':scope > [data-pf-bio-full]')) {
      return;
    }
    button.parentElement.hidden = false;
    entry.classList.add('is-bio-ready');
    button.addEventListener('click', () => {
      if (state.entry === entry && viewer.isSide()) {
        viewer.close(null);
        return;
      }
      open(entry, button);
    });
  }

  Drupal.behaviors.palefireExhibitBio = {
    attach(context) {
      const entries = once('pf-exhibit-bio-card', '.pf-exhibit-entry--bio', context);
      entries.forEach(init);
      if (once('pf-exhibit-bio-cite', 'html').length) {
        window.addEventListener('click', onCite, true);
      }
      // Arriving at a person's address: the panel open, once the page has
      // settled on the entry.
      const hash = location.hash.length > 1 ? decodeURIComponent(location.hash.slice(1)) : '';
      const target = hash ? document.getElementById(hash) : null;
      const entry = target && target.matches('.pf-exhibit-entry__title') ? target.closest('.pf-exhibit-entry--bio.is-bio-ready') : null;
      if (entry && entries.includes(entry)) {
        requestAnimationFrame(() => open(entry, buttonOf(entry)));
      }
    },
    detach(context, settings, trigger) {
      if (trigger === 'unload' && state.entry && context.contains(state.entry)) {
        state.entry = null;
        state.hash = null;
      }
    },
  };
})(Drupal, once);
