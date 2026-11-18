/**
 * @file
 * The ledger's hover photos (paragraph--tile-showcase.html.twig): while a
 * row with a cover is hovered or focused, its cover fills the section
 * under the swatch's photo veil (_tile-showcase.scss), as the map's room
 * photos fill #map (building-map.js), and its citation (the cover's object
 * label, when the page has one) shows at the foot of the left column. The
 * photos are fetched once a mouse first enters the ledger (or a row takes
 * focus) and shown once decoded. A short wait
 * before the swap keeps a sweep across the rows from flashing photos, and
 * the photo stays while the pointer or focus is in the section (so the
 * citation's link can be reached), lingering a moment after both leave.
 * Two slots crossfade.
 * Nothing happens where CSS hides the layer (under lg, more contrast,
 * forced colors) or on touch, where a tap simply follows the link.
 *
 * In a landing's Quick Start (a Section tabs panel, .pf-quick-guide) the
 * photo fills the guide's band instead, edge to edge like the band itself:
 * the landing's section is the whole hero, where the band covered it and
 * only the strips above and below showed it. The band runs past the guide's
 * column on a shadow, so the layer's left edge and width are set here from
 * the page's width (clientWidth, without the scrollbar), so it never adds
 * a sideways scroll.
 */
(function (Drupal, once) {
  'use strict';

  const WAIT = 120; // ms a row is hovered before its photo is asked for
  const LINGER = 400; // ms the photo stays after the last row goes out

  function init(groups) {
    const inner = groups.closest('.pf-section__inner');
    const lines = Array.from(groups.querySelectorAll('.pf-tile-line'));
    if (!inner || !lines.length) return;
    // The photo fills the whole section, edge to edge, which can be wider
    // and taller than its inner box (wide screens); in a Quick Start, the
    // guide's band.
    const guide = groups.closest('.pf-quick-guide');
    const section = guide || inner.closest('.pf-section') || inner;

    const layer = document.createElement('div');
    layer.className = 'pf-ledger-photo';
    layer.setAttribute('aria-hidden', 'true');
    const slots = [0, 1].map(() => {
      const slot = document.createElement('div');
      slot.className = 'pf-ledger-photo__img';
      layer.append(slot);
      return slot;
    });
    section.prepend(layer);
    section.classList.add('has-ledger-photos');
    if (guide) {
      const fit = () => {
        layer.style.left = (document.documentElement.getBoundingClientRect().left - guide.getBoundingClientRect().left) + 'px';
        layer.style.width = document.documentElement.clientWidth + 'px';
      };
      fit();
      new ResizeObserver(fit).observe(document.documentElement);
    }

    // The citations: from the ledger to the foot of the left column.
    const labelBox = groups.parentElement.querySelector('.pf-ledger-photo__labels');
    const labels = {};
    if (labelBox) {
      labelBox.querySelectorAll('[data-ledger-label]').forEach((label) => {
        labels[label.dataset.ledgerLabel] = label;
      });
      // In a Quick Start, under the panel's notes (absolute, so nothing moves).
      (guide && groups.closest('.pf-quick-guide__panel')?.querySelector(':scope > .pf-quick-guide__visit') || inner).append(labelBox);
      labelBox.hidden = false;
    }
    const cite = (url) => {
      Object.keys(labels).forEach((key) => labels[key].classList.toggle('is-on', key === url));
    };

    const ready = {};
    const load = (url) => {
      if (!ready[url]) {
        const img = new Image();
        img.src = url;
        ready[url] = img.decode().then(() => true, () => false);
      }
      return ready[url];
    };
    const warm = () => lines.forEach((line) => line.dataset.ledgerPhoto && load(line.dataset.ledgerPhoto));

    let wanted = null;
    let shown = null;
    let front = 0;
    let timer = 0;

    const apply = (url) => {
      if (url === shown) return;
      if (!url) {
        slots.forEach((slot) => slot.classList.remove('is-on', 'is-prev'));
        cite(null);
        section.classList.remove('is-ledger-photo');
        shown = null;
        return;
      }
      load(url).then((ok) => {
        if (!ok || wanted !== url || shown === url) return;
        const old = slots[front];
        front = 1 - front;
        const slot = slots[front];
        slot.style.setProperty('--pf-ledger-photo', 'url("' + url + '")');
        slot.classList.remove('is-prev');
        slot.classList.add('is-on');
        if (old.classList.contains('is-on')) {
          old.classList.remove('is-on');
          old.classList.add('is-prev');
        }
        section.classList.add('is-ledger-photo');
        cite(url);
        shown = url;
      });
    };

    const want = (url) => {
      let next = url || null;
      if (next && getComputedStyle(layer).display === 'none') next = null;
      if (next === wanted) return;
      wanted = next;
      clearTimeout(timer);
      timer = setTimeout(() => apply(next), next ? WAIT : LINGER);
    };

    groups.addEventListener('pointerenter', (event) => {
      if (event.pointerType !== 'touch' && getComputedStyle(layer).display !== 'none') warm();
    });
    // A row picks the photo; it stays while the pointer or focus is
    // anywhere in the section, so its citation's View record link can be
    // reached, and goes when both have left. A row without a cover clears it.
    lines.forEach((line) => {
      line.addEventListener('pointerenter', (event) => {
        if (event.pointerType !== 'touch') want(line.dataset.ledgerPhoto);
      });
      line.addEventListener('focus', () => want(line.dataset.ledgerPhoto));
    });
    section.addEventListener('pointerleave', (event) => {
      if (event.pointerType !== 'touch' && !section.contains(document.activeElement)) want(null);
    });
    section.addEventListener('focusout', (event) => {
      if (!section.contains(event.relatedTarget) && !section.matches(':hover')) want(null);
    });
  }

  Drupal.behaviors.palefireLedgerPhoto = {
    attach(context) {
      once('pf-ledger-photo', '[data-ledger-photos]', context).forEach(init);
    },
  };
})(Drupal, once);
