/**
 * @file
 * Glossary cards (src/GlossaryTerms.php, _glossary.scss).
 *
 * The cards are native popovers: the browser opens them from their term,
 * closes them on Escape or a tap elsewhere, and keeps one open at a time.
 * Where CSS anchor positioning works (Chrome, Edge, Safari 26), CSS puts
 * each card under its word and this script does nothing. Elsewhere it
 * places an open card under the word (above it when there's no room
 * below), inside the window's edges, and closes it if the page scrolls the
 * word away.
 */
(function (Drupal, once) {
  'use strict';

  const anchored = window.CSS && CSS.supports && CSS.supports('anchor-name: --a');
  const MARGIN = 16;
  const GAP = 8;

  function place(card, term) {
    const box = term.getBoundingClientRect();
    const width = Math.min(card.offsetWidth, window.innerWidth - 2 * MARGIN);
    let left = box.left + box.width / 2 - width / 2;
    left = Math.max(MARGIN, Math.min(left, window.innerWidth - MARGIN - width));
    let top = box.bottom + GAP;
    if (top + card.offsetHeight > window.innerHeight - MARGIN && box.top - GAP - card.offsetHeight > MARGIN) {
      top = box.top - GAP - card.offsetHeight;
    }
    card.style.margin = '0';
    card.style.left = left + 'px';
    card.style.top = top + 'px';
  }

  Drupal.behaviors.palefireGlossary = {
    attach(context) {
      if (anchored) {
        return;
      }
      once('pf-term', '.pf-term__card', context).forEach((card) => {
        const term = document.querySelector('[popovertarget="' + card.id + '"]');
        if (!term) {
          return;
        }
        const follow = () => {
          const box = term.getBoundingClientRect();
          if (box.bottom < 0 || box.top > window.innerHeight) {
            card.hidePopover();
            return;
          }
          place(card, term);
        };
        card.addEventListener('toggle', (event) => {
          if (event.newState === 'open') {
            place(card, term);
            window.addEventListener('scroll', follow, { passive: true });
            window.addEventListener('resize', follow);
          }
          else {
            window.removeEventListener('scroll', follow);
            window.removeEventListener('resize', follow);
          }
        });
      });
    },
  };
})(Drupal, once);
