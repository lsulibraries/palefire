/**
 * @file
 * Exhibitions showcase (includes/exhibit-showcase.html.twig) and the
 * exhibitions band it shares with Oral History's Showcase
 * (includes/exhibit-carousel.html.twig).
 *
 * The band: the canvases sit on a rail that scrolls sideways and snaps to
 * each one; the canvas at the rail's start is the one shown. Swipe and
 * trackpad scroll it natively; a mouse grabs and flings it
 * (Drupal.palefireDragGlide, js/drag-glide.js), and Previous / Next, the
 * dots, ← → (while focus is in the band) and a click on a canvas glide it
 * to a canvas. The shown one's summary, count, dot, the title Next brings
 * and the plate (its picture behind the band) follow the scroll as it goes,
 * the plate a moment behind so a fling past six canvases fetches one
 * picture; the polite announcement waits until the rail comes to rest. A
 * click on the shown canvas opens its exhibition. Nothing moves by itself.
 *
 * The table's switch (All, Digital, Physical) hides the other rows and
 * says how many are shown; the rows shown fade in, one after another.
 *
 * Reduced motion (the OS setting or the Settings drawer's
 * html.pf-a11y-motion): the rail jumps to a canvas instead of gliding, and
 * rows appear without fading.
 */
(function (Drupal, once) {
  'use strict';

  const SETTLE = 160; // ms without a scroll: the rail is at rest
  const PLATE_DELAY = 100; // ms after the shown exhibition last changed
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const reduceMotion = () => motion.matches || document.documentElement.classList.contains('pf-a11y-motion');
  const pad = (n) => String(n).padStart(2, '0');

  function carousel(root) {
    const region = root.querySelector('[data-pf-showcase-carousel]');
    const rail = root.querySelector('[data-pf-showcase-scene]');
    const cards = Array.from(root.querySelectorAll('[data-pf-showcase-card]'));
    const summaries = Array.from(root.querySelectorAll('[data-pf-showcase-summary]'));
    const dots = Array.from(root.querySelectorAll('[data-pf-showcase-go]'));
    const plates = Array.from(root.querySelectorAll('[data-pf-showcase-plate]'));
    const number = root.querySelector('[data-pf-showcase-n]');
    const live = root.querySelector('[data-pf-showcase-live]');
    const upnext = root.querySelector('[data-pf-showcase-upnext]');
    const nextButton = root.querySelector('[data-pf-showcase-next]');
    const prevButton = root.querySelector('[data-pf-showcase-prev]');
    const total = cards.length;
    if (total < 2 || !region || !rail || !Drupal.palefireDragGlide) {
      return;
    }

    let current = 0;
    let plateOn = 1;
    let plateShown = '';
    let near = false;

    // Each canvas's place on the rail, from the first one's (the rail's
    // end padding lets the last one reach the start too). Measured once
    // and again only when the rail changes size, not on every scroll.
    let offsets = null;
    const offset = (k) => {
      if (!offsets) {
        offsets = cards.map((card) => Math.abs(card.offsetLeft - cards[0].offsetLeft));
      }
      return offsets[k];
    };
    if ('ResizeObserver' in window) {
      new ResizeObserver(() => {
        offsets = null;
      }).observe(rail);
    }
    const maxScroll = () => rail.scrollWidth - rail.clientWidth;
    const rtl = () => getComputedStyle(rail).direction === 'rtl';
    const indexAt = () => {
      const x = Math.abs(rail.scrollLeft);
      let best = 0;
      cards.forEach((card, k) => {
        if (Math.abs(offset(k) - x) < Math.abs(offset(best) - x)) {
          best = k;
        }
      });
      return best;
    };

    // The plate: the shown exhibition's picture, changing with its summary,
    // a moment after the last change (so a fling past six canvases fetches
    // one picture). It waits, too, until the band nears the screen, so a
    // page that never scrolls down to it never fetches one.
    let plateTimer = 0;
    const plate = () => {
      const url = cards[current].dataset.plate || '';
      if (!near || plates.length !== 2 || url === plateShown) {
        return;
      }
      plateShown = url;
      const next = plates[1 - plateOn];
      next.style.backgroundImage = url ? `url("${url}")` : '';
      next.classList.add('is-on');
      plates[plateOn].classList.remove('is-on');
      plateOn = 1 - plateOn;
    };

    // The summary and its companions: at once, as the rail moves.
    const show = (k) => {
      clearTimeout(plateTimer);
      plateTimer = setTimeout(plate, PLATE_DELAY);
      current = k;
      cards.forEach((card, i) => card.classList.toggle('is-active', i === k));
      summaries.forEach((summary, i) => summary.classList.toggle('is-active', i === k));
      dots.forEach((dot, i) => dot.setAttribute('aria-current', String(i === k)));
      if (number) {
        number.textContent = pad(k + 1);
      }
      if (upnext) {
        upnext.parentElement.classList.toggle('is-last', k === total - 1);
        upnext.textContent = k < total - 1 ? summaries[k + 1].dataset.title : '';
      }
      if (prevButton && nextButton) {
        prevButton.disabled = k === 0;
        nextButton.disabled = k === total - 1;
      }
    };

    // The announcement: once the rail is at rest, so a fling says one thing.
    let said = 0;
    const rest = () => {
      if (live && said !== current) {
        said = current;
        live.textContent = Drupal.t('Exhibition @n of @total: @title', {
          '@n': current + 1,
          '@total': total,
          '@title': summaries[current].dataset.title,
        });
      }
    };

    const glider = Drupal.palefireDragGlide(rail, {
      item: '[data-pf-showcase-card]',
      // While it moves the canvases ignore the pointer (no hover lift
      // flickering across them).
      onMoving: (on) => rail.classList.toggle('is-moving', on),
      onDrag: (on) => rail.classList.toggle('is-dragging', on),
    });
    const go = (k) => {
      k = Math.max(0, Math.min(total - 1, k));
      const x = Math.min(maxScroll(), offset(k));
      glider.glideTo(rtl() ? -x : x);
    };

    let frame = 0;
    let timer = 0;
    rail.addEventListener('scroll', () => {
      cancelAnimationFrame(frame);
      frame = requestAnimationFrame(() => {
        const k = indexAt();
        if (k !== current) {
          show(k);
        }
      });
      clearTimeout(timer);
      timer = setTimeout(rest, SETTLE);
    }, { passive: true });

    if (nextButton && prevButton) {
      nextButton.addEventListener('click', () => go(current + 1));
      prevButton.addEventListener('click', () => go(current - 1));
    }
    dots.forEach((dot, k) => dot.addEventListener('click', () => go(k)));

    region.addEventListener('keydown', (event) => {
      if (event.altKey || event.ctrlKey || event.metaKey || event.target.closest('input, textarea, select')) {
        return;
      }
      const back = rtl() ? 'ArrowRight' : 'ArrowLeft';
      const ahead = rtl() ? 'ArrowLeft' : 'ArrowRight';
      if (event.key === ahead || event.key === back) {
        event.preventDefault();
        go(current + (event.key === ahead ? 1 : -1));
      }
    });

    // A click (not the end of a drag, which drag-glide.js swallows): the
    // shown canvas opens its exhibition, another glides to the start.
    rail.addEventListener('click', (event) => {
      const card = event.target.closest('[data-pf-showcase-card]');
      if (!card) {
        return;
      }
      const k = cards.indexOf(card);
      if (k === current) {
        window.location.href = card.dataset.url;
      }
      else {
        go(k);
      }
    });

    show(indexAt());
    if ('IntersectionObserver' in window) {
      const watch = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
          watch.disconnect();
          near = true;
          plate();
        }
      }, { rootMargin: '400px 0px' });
      watch.observe(region);
    }
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
