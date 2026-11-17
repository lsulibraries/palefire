/**
 * @file
 * The ledger's hover photos (paragraph--tile-showcase.html.twig): while a
 * row with a cover is hovered or focused, its cover fills the section's
 * left column, behind the heading and lede, under the swatch's photo veil
 * (_tile-showcase.scss), as the map's room photos fill #map
 * (building-map.js). The photos are fetched once a mouse first enters the
 * ledger (or a row takes focus) and shown once decoded. A short wait
 * before the swap keeps a sweep across the rows from flashing photos, and
 * the photo lingers a moment after the pointer leaves. Two slots crossfade.
 * Nothing happens where CSS hides the layer (under lg, more contrast,
 * forced colors) or on touch, where a tap simply follows the link.
 */
(function (Drupal, once) {
  'use strict';

  const WAIT = 120; // ms a row is hovered before its photo is asked for
  const LINGER = 400; // ms the photo stays after the last row goes out

  function init(groups) {
    const inner = groups.closest('.pf-section__inner');
    const lines = Array.from(groups.querySelectorAll('.pf-tile-line'));
    if (!inner || !lines.length) return;

    const layer = document.createElement('div');
    layer.className = 'pf-ledger-photo';
    layer.setAttribute('aria-hidden', 'true');
    const slots = [0, 1].map(() => {
      const slot = document.createElement('div');
      slot.className = 'pf-ledger-photo__img';
      layer.append(slot);
      return slot;
    });
    inner.prepend(layer);
    inner.classList.add('has-ledger-photos');

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
        inner.classList.remove('is-ledger-photo');
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
        inner.classList.add('is-ledger-photo');
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
    lines.forEach((line) => {
      line.addEventListener('pointerenter', (event) => {
        if (event.pointerType !== 'touch') want(line.dataset.ledgerPhoto);
      });
      line.addEventListener('pointerleave', (event) => {
        if (event.pointerType !== 'touch' && document.activeElement !== line) want(null);
      });
      line.addEventListener('focus', () => want(line.dataset.ledgerPhoto));
      line.addEventListener('blur', () => want(null));
    });
  }

  Drupal.behaviors.palefireLedgerPhoto = {
    attach(context) {
      once('pf-ledger-photo', '[data-ledger-photos]', context).forEach(init);
    },
  };
})(Drupal, once);
