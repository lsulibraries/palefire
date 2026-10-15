/**
 * @file
 * Hill Memorial building map: draw-on outline, hover sync, tooltips and the
 * bento background dim.
 *
 * Markup: paragraph--building-map.html.twig (map, legend, space list,
 * hidden tooltip sources) inside paragraph--bento-search.html.twig.
 * Hovering or focusing a room, its name on the map, or its entry in the list
 * lights the room, turns its name gold and shows a tooltip built from that
 * room's .pf-map__tip-src. Rooms with a link follow it on click.
 */
(function (Drupal, once) {
  'use strict';

  const SETTINGS = {
    duration: 3200,      // trace window, ms (codepen: 5000)
    stagger: 900,        // spread of path start times, ms
    strokeWidthPt: 0.5,  // outline weight as displayed
    easing: 'cubic-bezier(0.45, 0, 0.2, 1)',
    hideDelay: 140,      // ms; lets the pointer cross onto the tooltip (WCAG 1.4.13)
    tipGap: 14,          // px between the room name and the tooltip
  };
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');

  function initMap(root) {
    const svg = root.querySelector('[data-map-svg]');
    if (!svg) return;
    const stage = root.querySelector('.pf-map__stage') || root;
    const lines = Array.from(svg.querySelectorAll('.pf-map__lines:not(.pf-map__lines--hidden) path'));
    const regions = Array.from(root.querySelectorAll('.pf-map__region[data-map-region]'));
    const items = Array.from(root.querySelectorAll('.pf-map__item[data-map-region]'));
    const labels = Array.from(root.querySelectorAll('[data-map-label]'));
    const live = new Set(regions.map((r) => r.dataset.mapRegion));

    // Keep the outline at 0.5pt at any size without non-scaling-stroke, so
    // dash lengths and getTotalLength() share the same (user) units.
    const fitStroke = () => {
      const m = svg.getScreenCTM();
      const scale = m ? Math.hypot(m.a, m.b) : 0;
      if (scale > 0) root.style.setProperty('--pf-map-line', String((SETTINGS.strokeWidthPt * 96 / 72) / scale));
    };
    if ('ResizeObserver' in window) new ResizeObserver(fitStroke).observe(svg);
    else window.addEventListener('resize', fitStroke, { passive: true });
    fitStroke();

    // Tooltip ---------------------------------------------------------------
    // Screen readers get the same text through aria-describedby on each
    // region, so the visual tooltip itself is aria-hidden.
    const tip = document.createElement('div');
    tip.className = 'pf-map__tip';
    tip.setAttribute('aria-hidden', 'true');
    stage.appendChild(tip);
    let current = null;
    let hideTimer = 0;

    function setActive(key, on) {
      regions.concat(items).forEach((el) => {
        if (el.dataset.mapRegion === key) el.classList.toggle('is-active', on);
      });
      labels.forEach((el) => {
        if (el.dataset.mapLabel === key) el.classList.toggle('is-active', on);
      });
    }

    function place(key) {
      const anchorEl = labels.find((l) => l.dataset.mapLabel === key)
        || regions.find((r) => r.dataset.mapRegion === key);
      if (!anchorEl) return;
      const box = stage.getBoundingClientRect();
      const a = anchorEl.getBoundingClientRect();
      const w = tip.offsetWidth;
      const h = tip.offsetHeight;
      const cx = a.left + a.width / 2 - box.left;
      let x = Math.round(cx - w / 2);
      x = Math.max(8, Math.min(x, box.width - w - 8));
      // The name lifts on hover; aim above where it will be.
      let y = Math.round(a.top - box.top - h - SETTINGS.tipGap - 6);
      const below = y < 0 && (a.bottom - box.top + SETTINGS.tipGap + h) <= box.height + 40;
      if (below) y = Math.round(a.bottom - box.top + SETTINGS.tipGap);
      tip.classList.toggle('is-below', below);
      tip.style.setProperty('--tip-x', x + 'px');
      tip.style.setProperty('--tip-y', y + 'px');
      tip.style.setProperty('--tip-arrow', Math.max(18, Math.min(w - 18, cx - x)) + 'px');
    }

    function show(key) {
      clearTimeout(hideTimer);
      if (!live.has(key)) return;
      if (current !== key) {
        if (current) setActive(current, false);
        const src = root.querySelector('.pf-map__tip-src[data-map-tip="' + key + '"]');
        if (!src) return;
        tip.innerHTML = src.innerHTML;
        current = key;
      }
      setActive(key, true);
      place(key);
      tip.classList.add('is-open');
    }

    function hide(now) {
      clearTimeout(hideTimer);
      const done = () => {
        if (current) setActive(current, false);
        current = null;
        tip.classList.remove('is-open');
      };
      if (now) done();
      else hideTimer = setTimeout(done, SETTINGS.hideDelay);
    }

    tip.addEventListener('pointerenter', () => clearTimeout(hideTimer));
    tip.addEventListener('pointerleave', () => hide());
    root.addEventListener('keydown', (e) => { if (e.key === 'Escape' && current) hide(true); });
    window.addEventListener('resize', () => { if (current) place(current); }, { passive: true });
    const scroller = root.querySelector('.pf-map__scroller');
    if (scroller) scroller.addEventListener('scroll', () => { if (current) place(current); }, { passive: true });

    const focusVisible = (el) => { try { return el.matches(':focus-visible'); } catch (e) { return true; } };
    const hoverable = (el, key) => {
      el.addEventListener('pointerenter', (e) => { if (e.pointerType !== 'touch') show(key); });
      el.addEventListener('pointerleave', (e) => { if (e.pointerType !== 'touch') hide(); });
      el.addEventListener('focus', () => { if (focusVisible(el)) show(key); });
      el.addEventListener('blur', () => hide());
    };

    regions.forEach((region) => {
      const key = region.dataset.mapRegion;
      hoverable(region, key);
      // Touch has no hover: the first tap shows the tooltip, a second tap on
      // the same room follows its link.
      let touch = false;
      region.addEventListener('pointerdown', (e) => { touch = e.pointerType === 'touch'; });
      region.addEventListener('click', (e) => {
        if (touch && current !== key) { e.preventDefault(); show(key); }
        touch = false;
      });
    });
    // Room names are selectable text that sit above the hit area, so they
    // carry the hover themselves. Clicking a name only selects text.
    labels.forEach((label) => {
      const key = label.dataset.mapLabel;
      if (!live.has(key)) return;
      label.addEventListener('pointerenter', (e) => { if (e.pointerType !== 'touch') show(key); });
      label.addEventListener('pointerleave', (e) => { if (e.pointerType !== 'touch') hide(); });
    });
    items.forEach((item) => hoverable(item, item.dataset.mapRegion));
    document.addEventListener('pointerdown', (e) => {
      if (current && !e.target.closest('.pf-map__region, .pf-map__tip, [data-map-label]')) hide(true);
    });

    // Legend: collapsible below the map on narrow screens, always open
    // otherwise. Only acts when that state flips, so a reader's own toggle is
    // never overridden. Without JS it is simply open.
    const key = root.querySelector('[data-map-key]');
    if (key) {
      const narrow = window.matchMedia('(max-width: 991.98px)');
      let collapsible = null;
      const syncKey = () => {
        if (narrow.matches === collapsible) return;
        collapsible = narrow.matches;
        root.classList.toggle('has-collapsible-key', collapsible);
        key.open = !collapsible;
      };
      narrow.addEventListener('change', syncKey);
      syncKey();
    }

    // Bento: dim + blur the background video while the map is in view.
    const bento = root.closest('.pf-bento');
    if (bento && 'IntersectionObserver' in window) {
      const steps = [0, 0.1, 0.2, 0.3, 0.4, 0.5, 0.6];
      const dim = new IntersectionObserver(([entry]) => {
        const seen = entry.intersectionRect.height;
        const inView = entry.isIntersecting
          && (entry.intersectionRatio >= 0.3 || seen >= window.innerHeight * 0.45);
        bento.classList.toggle('is-map-in-view', inView);
      }, { threshold: steps });
      dim.observe(root);
      root.pfMapDim = dim;
    }

    // Draw-on trace (from the codepen), once, when the map scrolls into view.
    if (motion.matches || !lines.length || typeof lines[0].animate !== 'function') return;
    root.classList.add('is-tracing');
    const records = lines.map((path) => ({ path, length: path.getTotalLength(), top: path.getBBox().y }))
      .sort((a, b) => a.top - b.top);
    const longest = Math.max(1, ...records.map((r) => r.length));
    records.forEach(({ path, length }) => {
      path.style.strokeDasharray = `${length} ${length}`;
      path.style.strokeDashoffset = String(length);
    });
    const draw = () => {
      fitStroke();
      const spread = Math.min(SETTINGS.stagger, SETTINGS.duration * 0.5);
      const windowMs = SETTINGS.duration - spread;
      const animations = records.map(({ path, length }, i) => path.animate(
        [{ strokeDashoffset: length }, { strokeDashoffset: 0 }],
        {
          duration: Math.max(350, windowMs * Math.pow(length / longest, 0.55)),
          delay: 120 + (i / Math.max(1, records.length - 1)) * spread,
          easing: SETTINGS.easing,
          fill: 'both',
        },
      ));
      // Reveal hatch, labels and icons as the last lines land.
      setTimeout(() => root.classList.add('is-drawn'), SETTINGS.duration * 0.7);
      Promise.all(animations.map((a) => a.finished.catch(() => {}))).then(() => {
        animations.forEach((a) => a.cancel());
        records.forEach(({ path }) => { path.style.strokeDasharray = ''; path.style.strokeDashoffset = ''; });
        root.classList.remove('is-tracing');
      });
    };
    if (!('IntersectionObserver' in window)) { draw(); return; }
    const io = new IntersectionObserver(([entry]) => {
      if (!entry.isIntersecting) return;
      io.disconnect();
      draw();
    }, { threshold: 0.25 });
    io.observe(svg);
  }

  Drupal.behaviors.palefireBuildingMap = {
    attach(context) {
      once('pf-building-map', '[data-pf-map]', context).forEach(initMap);
    },
    detach(context, settings, trigger) {
      // Swup swaps #swup: stop the dim observer and clear the bento state.
      if (trigger !== 'unload') return;
      context.querySelectorAll('[data-pf-map]').forEach((root) => {
        if (root.pfMapDim) root.pfMapDim.disconnect();
        const bento = root.closest('.pf-bento');
        if (bento) bento.classList.remove('is-map-in-view');
      });
    },
  };
})(Drupal, once);
