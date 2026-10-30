/**
 * @file
 * An image group in a guide (.pf-exhibit-group--guide): a picture opens in
 * the full-screen viewer (js/viewer.js) with its caption beside it, and the
 * arrows step through the rest of its group. Exhibitions do this in
 * js/exhibition.js, which a guide doesn't load.
 *
 * Without the script, or with a modifier key, the link opens the picture's
 * largest version.
 */
(function (Drupal, once) {
  'use strict';

  const viewer = Drupal.palefireViewer;
  let state = null;

  // The viewer's copy of a thumbnail: the size already loaded first, then
  // the largest the srcset offers for the stage. data-full is the original,
  // swapped in on zoom.
  function stageImg(link) {
    const thumb = link.querySelector('img');
    const img = document.createElement('img');
    img.className = 'pf-viewer__img pf-viewer__zoomable';
    img.alt = thumb ? thumb.alt : '';
    if (link.dataset.width) {
      img.width = parseInt(link.dataset.width, 10);
      img.height = parseInt(link.dataset.height, 10);
    }
    img.src = thumb && thumb.currentSrc ? thumb.currentSrc : link.href;
    img.decoding = 'async';
    img.dataset.full = link.href;
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

  function fill(index) {
    const view = viewer.get();
    const n = state.links.length;
    state.index = (index + n) % n;
    const link = state.links[state.index];
    const frame = document.createElement('div');
    frame.className = 'pf-viewer__frame';
    frame.append(stageImg(link));
    view.media.replaceChildren(frame);

    const figure = link.closest('figure');
    const caption = figure ? figure.querySelector(':scope > figcaption') : null;
    view.dialog.removeAttribute('aria-labelledby');
    view.dialog.setAttribute('aria-label', Drupal.t('Image viewer'));
    view.title.hidden = true;
    view.label.replaceChildren(...(caption ? Array.from(caption.cloneNode(true).childNodes) : []));
    view.label.hidden = !caption;
    view.body.replaceChildren();
    view.count.textContent = n > 1 ? Drupal.t('Image @n of @total', { '@n': state.index + 1, '@total': n }) : '';
    viewer.sync({ steps: n, bare: !caption && n < 2 });
  }

  const handler = {
    step: (delta) => fill(state.index + delta),
    close: () => viewer.close(state.links[state.index].querySelector('img')),
    closed: () => {
      const link = state && state.links[state.index];
      state = null;
      if (link && link.isConnected) {
        link.focus({ preventScroll: true });
      }
    },
  };

  function open(link) {
    const group = link.closest('.pf-exhibit-group--guide');
    state = {
      links: Array.from(group.querySelectorAll('a[data-pf-lightbox]')),
      index: 0,
    };
    viewer.show(handler, () => fill(state.links.indexOf(link)), { from: link.querySelector('img') });
  }

  Drupal.behaviors.palefireGuideGallery = {
    attach(context) {
      once('pf-guide-gallery', '.pf-exhibit-group--guide a[data-pf-lightbox]', context).forEach((link) => {
        link.addEventListener('click', (event) => {
          if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || !window.HTMLDialogElement) {
            return;
          }
          event.preventDefault();
          open(link);
        });
      });
    },
  };
})(Drupal, once);
