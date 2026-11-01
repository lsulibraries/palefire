/**
 * @file
 * Exhibitions showcase (includes/exhibit-showcase.html.twig).
 *
 * The carousel: --pf-d on each cover is its place in the row (0 = front);
 * CSS moves it there (_exhibit-showcase.scss). The row moves one way:
 * - Next: the front cover steps off (.is-leaving), then jumps to the back
 *   unseen (.is-jump) and fades in there.
 * - Previous: the back cover jumps out in front (.is-jump + .is-leaving)
 *   and steps in.
 * A dot jumps several places, the same way. The arrows, dots, ← → (while
 * focus is in the carousel) and a swipe move it; a click on the front
 * cover opens its exhibition, one behind comes forward. Beside the
 * arrows, the title that Next brings (decorative; the change itself is
 * announced politely). Nothing moves by itself.
 *
 * The table's switch (All, Digital, Physical) hides the other rows and
 * says how many are shown; the rows shown fade in, one after another.
 *
 * Reduced motion (the OS setting or the Settings drawer's
 * html.pf-a11y-motion): covers take their places at once and fade, and
 * rows appear without fading.
 */
(function (Drupal, once) {
  'use strict';

  const LEAVE = 500; // .is-leaving's transition, a little less
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const reduceMotion = () => motion.matches || document.documentElement.classList.contains('pf-a11y-motion');
  const pad = (n) => String(n).padStart(2, '0');

  function carousel(root) {
    const region = root.querySelector('[data-pf-showcase-carousel]');
    const scene = root.querySelector('[data-pf-showcase-scene]');
    const cards = Array.from(root.querySelectorAll('[data-pf-showcase-card]'));
    const summaries = Array.from(root.querySelectorAll('[data-pf-showcase-summary]'));
    const dots = Array.from(root.querySelectorAll('[data-pf-showcase-go]'));
    const number = root.querySelector('[data-pf-showcase-n]');
    const live = root.querySelector('[data-pf-showcase-live]');
    const upnext = root.querySelector('[data-pf-showcase-upnext]');
    const total = cards.length;
    if (total < 2 || !region || !scene) {
      return;
    }

    let current = 0;
    const timers = new Map();
    const place = (k) => (k - current + total) % total;
    const setPlace = (card, d) => {
      card.style.setProperty('--pf-d', d);
      card.classList.toggle('is-active', d === 0);
    };
    // Off with the transition: the cover is moved where it can't be seen.
    const jump = (card, d, leaving) => {
      card.classList.add('is-jump');
      card.classList.toggle('is-leaving', leaving);
      setPlace(card, d);
      void card.offsetWidth;
      card.classList.remove('is-jump', 'is-leaving');
    };
    // A cover still on its way out when the next change comes: finish now.
    const settle = () => {
      timers.forEach((timer, card) => {
        clearTimeout(timer);
        jump(card, place(cards.indexOf(card)), false);
      });
      timers.clear();
    };

    const go = (target, forward) => {
      target = (target + total) % total;
      if (target === current) {
        return;
      }
      settle();
      const before = cards.map((card, k) => place(k));
      current = target;
      const quick = reduceMotion();

      cards.forEach((card, k) => {
        const d = place(k);
        if (quick) {
          setPlace(card, d);
        }
        else if (forward && d > before[k]) {
          // Was in front of the new front cover: off, then to the back.
          card.classList.remove('is-active');
          card.classList.add('is-leaving');
          timers.set(card, setTimeout(() => {
            timers.delete(card);
            jump(card, place(k), false);
          }, LEAVE));
        }
        else if (!forward && d < before[k]) {
          // Comes round from the back: in at the front, then steps in.
          card.classList.add('is-jump', 'is-leaving');
          setPlace(card, d);
          void card.offsetWidth;
          card.classList.remove('is-jump', 'is-leaving');
        }
        else {
          setPlace(card, d);
        }
      });

      summaries.forEach((summary, k) => summary.classList.toggle('is-active', k === current));
      dots.forEach((dot, k) => dot.setAttribute('aria-current', String(k === current)));
      if (number) {
        number.textContent = pad(current + 1);
      }
      if (upnext) {
        upnext.textContent = summaries[(current + 1) % total].dataset.title;
      }
      if (live) {
        live.textContent = Drupal.t('Exhibition @n of @total: @title', {
          '@n': current + 1,
          '@total': total,
          '@title': summaries[current].dataset.title,
        });
      }
    };
    const next = () => go(current + 1, true);
    const prev = () => go(current - 1, false);

    const nextButton = root.querySelector('[data-pf-showcase-next]');
    const prevButton = root.querySelector('[data-pf-showcase-prev]');
    if (nextButton && prevButton) {
      nextButton.addEventListener('click', next);
      prevButton.addEventListener('click', prev);
    }
    dots.forEach((dot, k) => dot.addEventListener('click', () => go(k, k > current)));

    region.addEventListener('keydown', (event) => {
      if (event.altKey || event.ctrlKey || event.metaKey) {
        return;
      }
      if (event.key === 'ArrowRight') {
        event.preventDefault();
        next();
      }
      else if (event.key === 'ArrowLeft') {
        event.preventDefault();
        prev();
      }
    });

    // Swipe (the arrows are the single-pointer way, WCAG 2.5.7), and a
    // click: the front cover opens its exhibition, one behind comes forward.
    let startX = null;
    let swiped = false;
    scene.addEventListener('pointerdown', (event) => {
      startX = event.clientX;
      swiped = false;
    });
    scene.addEventListener('pointerup', (event) => {
      if (startX === null) {
        return;
      }
      const dx = event.clientX - startX;
      startX = null;
      if (Math.abs(dx) > 40) {
        swiped = true;
        if (dx < 0) {
          next();
        }
        else {
          prev();
        }
      }
    });
    scene.addEventListener('click', (event) => {
      const card = event.target.closest('[data-pf-showcase-card]');
      if (swiped || !card) {
        return;
      }
      const k = cards.indexOf(card);
      if (k === current) {
        window.location.href = card.dataset.url;
      }
      else {
        go(k, true);
      }
    });
  }

  function filter(root) {
    const group = root.querySelector('[data-pf-showcase-filter]');
    if (!group) {
      return;
    }
    const buttons = Array.from(group.querySelectorAll('[data-pf-showcase-kind]'));
    const rows = Array.from(root.querySelectorAll('.pf-showcase-list__row'));
    const live = root.querySelector('[data-pf-showcase-filter-live]');
    const words = {
      all: (n) => Drupal.formatPlural(n, '1 exhibition shown', '@count exhibitions shown'),
      digital: (n) => Drupal.formatPlural(n, '1 digital exhibition shown', '@count digital exhibitions shown'),
      physical: (n) => Drupal.formatPlural(n, '1 physical exhibition shown', '@count physical exhibitions shown'),
    };

    group.hidden = false;
    group.addEventListener('click', (event) => {
      const button = event.target.closest('[data-pf-showcase-kind]');
      if (!button || button.getAttribute('aria-pressed') === 'true') {
        return;
      }
      const kind = button.dataset.pfShowcaseKind;
      buttons.forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
      const shown = rows.filter((row) => {
        row.hidden = kind !== 'all' && row.dataset.kind !== kind;
        return !row.hidden;
      });
      if (live) {
        live.textContent = words[kind](shown.length);
      }
      if (!reduceMotion() && Element.prototype.animate) {
        shown.slice(0, 12).forEach((row, i) => {
          row.animate([
            { opacity: 0, transform: 'translateY(.5rem)' },
            { opacity: 1, transform: 'none' },
          ], { duration: 450, delay: i * 40, easing: 'cubic-bezier(.22, 1, .36, 1)', fill: 'backwards' });
        });
      }
    });
  }

  Drupal.behaviors.palefireExhibitShowcase = {
    attach(context) {
      once('pf-showcase', '[data-pf-showcase]', context).forEach((root) => {
        carousel(root);
        filter(root);
        root.classList.add('is-ready');
      });
    },
  };
})(Drupal, once);
