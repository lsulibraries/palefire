/**
 * @file
 * Hill Memorial building map: draw-on outline, hover sync, tooltips and the
 * bento background dim.
 *
 * Markup: paragraph--building-map.html.twig (map, legend, space list,
 * hidden tooltip sources) inside paragraph--bento-search.html.twig.
 * Hovering or focusing a room, its name on the map, or its entry in the list
 * lights the room, turns its name gold and shows a tooltip built from that
 * room's .pf-map__tip-src. Rooms with a link follow it on click; rooms
 * without one are buttons that pin their tooltip. A page about one room
 * (data-map-here) gets a "here" arrow above that room's name.
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
    hatchPeriod: 20,     // map units: the hatch <pattern> height in the drawing
    hereGap: 36,         // map units between the "here" arrow and the room name
  };
  const SVG_NS = 'http://www.w3.org/2000/svg';
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
    const regionFor = (key) => regions.find((r) => r.dataset.mapRegion === key);
    labels.forEach((label) => label.classList.toggle('is-live', live.has(label.dataset.mapLabel)));

    // Keep the outline at 0.5pt at any size without non-scaling-stroke, so
    // dash lengths and getTotalLength() share the same (user) units.
    const fitStroke = () => {
      const m = svg.getScreenCTM();
      const scale = m ? Math.hypot(m.a, m.b) : 0;
      if (scale > 0) {
        root.style.setProperty('--pf-map-line', String((SETTINGS.strokeWidthPt * 96 / 72) / scale));
        // Legend swatches draw the map's hatch at the map's current size,
        // snapped to whole device pixels: with a fractional period every line
        // lands on a different sub-pixel offset and is antialiased
        // differently (the first one looked thinner). Snapped, all four lines
        // render identically.
        const dpr = window.devicePixelRatio || 1;
        const period = Math.max(4, Math.round(SETTINGS.hatchPeriod * scale * dpr));
        const line = Math.max(1, Math.round(period * 0.25));
        const offset = Math.round((period - line) / 2);
        root.style.setProperty('--pf-map-hatch-period', (period / dpr) + 'px');
        root.style.setProperty('--pf-map-hatch-line', (line / dpr) + 'px');
        root.style.setProperty('--pf-map-hatch-offset', (offset / dpr) + 'px');
      }
    };
    if ('ResizeObserver' in window) new ResizeObserver(fitStroke).observe(svg);
    else window.addEventListener('resize', fitStroke, { passive: true });
    fitStroke();

    // Entrance arrow: centre it over its word once the web font has loaded
    // (the markup carries a close estimate for no-JS). Measured in the
    // label's own (pre-skew) units; half the trailing letter-spacing is
    // dropped so it centres on the visible letters.
    const centreArrows = () => svg.querySelectorAll('[data-map-center-over]').forEach((arrow) => {
      const text = arrow.parentNode.querySelector('text');
      if (!text || typeof text.getBBox !== 'function') return;
      const box = text.getBBox();
      if (!box.width) return;
      const tracking = parseFloat(getComputedStyle(text).letterSpacing) || 0;
      arrow.setAttribute('transform', `translate(${(box.x + (box.width - tracking) / 2).toFixed(1)} 0)`);
    });
    centreArrows();
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(centreArrows);

    // Tooltip ---------------------------------------------------------------
    // Screen readers get the same text through aria-describedby on each
    // region, so the visual tooltip itself is aria-hidden.
    const tip = document.createElement('div');
    tip.className = 'pf-map__tip';
    tip.setAttribute('aria-hidden', 'true');
    stage.appendChild(tip);
    let current = null;
    let hideTimer = 0;
    let showTimer = 0;
    // Hover wait before a tooltip shows: --pf-map-tip-delay (s or ms).
    const tipDelay = () => {
      const v = getComputedStyle(root).getPropertyValue('--pf-map-tip-delay').trim();
      const n = parseFloat(v);
      return Number.isFinite(n) ? (v.endsWith('ms') ? n : n * 1000) : 250;
    };

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
      clearTimeout(showTimer);
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

    // Pointer hover: wait first, unless a tooltip is already open (moving
    // from room to room then feels instant).
    function showSoon(key) {
      clearTimeout(hideTimer);
      clearTimeout(showTimer);
      if (current && tip.classList.contains('is-open')) { show(key); return; }
      showTimer = setTimeout(() => show(key), tipDelay());
    }

    function hide(now) {
      clearTimeout(showTimer);
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
      el.addEventListener('pointerenter', (e) => { if (e.pointerType !== 'touch') showSoon(key); });
      el.addEventListener('pointerleave', (e) => { if (e.pointerType !== 'touch') hide(); });
      el.addEventListener('focus', () => { if (focusVisible(el)) show(key); });
      el.addEventListener('blur', () => hide());
    };
    // Rooms without a link are role="button" (map) or <button> (list):
    // activating one shows its tooltip, as hover does. Space/Enter on the
    // SVG <g> need handling by hand; a real <button> fires click itself.
    // A pointer click toggles; a keyboard press (detail 0) only opens, since
    // focus has usually opened it already. Escape closes.
    const pressable = (el, key) => {
      el.addEventListener('click', (e) => {
        if (el.matches('a, [href]')) return;
        e.preventDefault();
        if (e.detail > 0 && current === key && tip.classList.contains('is-open')) hide(true);
        else show(key);
      });
      if (el.tagName.toLowerCase() === 'button') return;
      el.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
          e.preventDefault();
          el.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        }
      });
    };

    regions.forEach((region) => {
      const key = region.dataset.mapRegion;
      hoverable(region, key);
      // Touch has no hover: the first tap shows the tooltip, a second tap on
      // the same room follows its link.
      let touch = false;
      region.addEventListener('pointerdown', (e) => { touch = e.pointerType === 'touch'; });
      region.addEventListener('click', (e) => {
        if (touch && current !== key && region.matches('a')) { e.preventDefault(); show(key); }
        touch = false;
      });
      pressable(region, key);
    });
    // Room names are selectable text that sit above the hit area, so they
    // carry the hover themselves. A click on a name (not a text selection)
    // does what a click on the room does.
    labels.forEach((label) => {
      const key = label.dataset.mapLabel;
      if (!live.has(key)) return;
      const region = regionFor(key);
      let touch = false;
      label.addEventListener('pointerenter', (e) => { if (e.pointerType !== 'touch') showSoon(key); });
      label.addEventListener('pointerleave', (e) => { if (e.pointerType !== 'touch') hide(); });
      label.addEventListener('pointerdown', (e) => { touch = e.pointerType === 'touch'; });
      label.addEventListener('click', (e) => {
        const wasTouch = touch;
        touch = false;
        const selection = window.getSelection && window.getSelection();
        if (selection && !selection.isCollapsed && String(selection).trim()) return;
        const href = region && region.getAttribute('href');
        if (!href) { region.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, detail: 1 })); return; }
        if (wasTouch && current !== key) { show(key); return; }
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) window.open(href, '_blank', 'noopener');
        else window.location.assign(href);
      });
    });
    items.forEach((item) => {
      hoverable(item, item.dataset.mapRegion);
      pressable(item, item.dataset.mapRegion);
    });
    document.addEventListener('pointerdown', (e) => {
      if (current && !e.target.closest('.pf-map__region, .pf-map__item, .pf-map__tip, [data-map-label]')) hide(true);
    });

    // "Here" marker -----------------------------------------------------------
    // data-map-here names the room this page is about (field_map_here). The
    // arrow is drawn in map units over that room's name, so it scales with
    // the drawing and only needs placing once the name has its final font.
    const hereKey = root.dataset.mapHere;
    if (hereKey && live.has(hereKey)) {
      const hereRegion = regionFor(hereKey);
      hereRegion.setAttribute('aria-current', 'location');
      const hereLabel = labels.find((l) => l.dataset.mapLabel === hereKey);
      const marker = document.createElementNS(SVG_NS, 'g');
      marker.setAttribute('class', 'pf-map__here');
      marker.setAttribute('aria-hidden', 'true');
      const arrow = document.createElementNS(SVG_NS, 'path');
      arrow.setAttribute('class', 'pf-map__here-arrow');
      arrow.setAttribute('d', 'M-46 -92 H46 L0 0 Z');
      arrow.setAttribute('stroke-linejoin', 'round');
      marker.appendChild(arrow);
      svg.appendChild(marker);
      const placeHere = () => {
        const target = hereLabel || hereRegion;
        const m = svg.getScreenCTM();
        const r = target.getBoundingClientRect();
        if (!m || !r.width) return;
        // Measure without the hover lift.
        const lifted = target.classList.contains('is-active');
        if (lifted) return;
        const pt = new DOMPoint(r.left + r.width / 2, r.top).matrixTransform(m.inverse());
        marker.setAttribute('transform', 'translate(' + pt.x.toFixed(1) + ' ' + (pt.y - SETTINGS.hereGap).toFixed(1) + ')');
      };
      placeHere();
      if (document.fonts && document.fonts.ready) document.fonts.ready.then(placeHere);
      if ('ResizeObserver' in window) new ResizeObserver(placeHere).observe(svg);
    }

    // Legend: open while it sits beside the map, collapsed when it moves
    // under it. The layout comes from the stylesheet (--pf-map-layout, set by
    // a container query in _building-map.scss), so this follows the space the
    // map really has, TOC sidebar or not. Only acts when the layout flips, so
    // a reader's own toggle is never overridden. Without JS it is open.
    const key = root.querySelector('[data-map-key]');
    if (key) {
      let stacked = null;
      const syncKey = () => {
        const now = getComputedStyle(root).getPropertyValue('--pf-map-layout').trim() === 'stacked';
        if (now === stacked) return;
        stacked = now;
        key.open = !stacked;
      };
      if ('ResizeObserver' in window) new ResizeObserver(syncKey).observe(root);
      else window.addEventListener('resize', syncKey, { passive: true });
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
    // Reveal order: rooms top to bottom (then left to right) through the
    // drawing; each name shares its room's place in the order.
    const centre = (el) => { const b = el.getBoundingClientRect(); return { x: b.left + b.width / 2, y: b.top + b.height / 2 }; };
    const ordered = regions.map((el) => ({ el, c: centre(el) }))
      .sort((a, b) => (Math.abs(a.c.y - b.c.y) > 8 ? a.c.y - b.c.y : a.c.x - b.c.x))
      .map(({ el }) => el);
    const seqOf = new Map(ordered.map((el, i) => [el.dataset.mapRegion, i]));
    ordered.forEach((el, i) => el.style.setProperty('--pf-map-seq', String(i)));
    const unlisted = Array.from(svg.querySelectorAll('.pf-map__label')).filter((l) => !seqOf.has(l.dataset.mapLabel));
    labels.forEach((label) => {
      if (seqOf.has(label.dataset.mapLabel)) label.style.setProperty('--pf-map-seq', String(seqOf.get(label.dataset.mapLabel)));
    });
    unlisted.forEach((label, i) => label.style.setProperty('--pf-map-seq', String(Math.round(ordered.length * 0.5) + i)));
    const seqEnd = Math.max(ordered.length, Math.round(ordered.length * 0.5) + unlisted.length);
    root.style.setProperty('--pf-map-seq-end', String(seqEnd));
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
      // Rooms start fading in as the last lines land (see the stylesheet for
      // the order); .is-revealing carries the staggered delays and is
      // dropped afterwards so hovers and focus never inherit them.
      const revealMs = (seqEnd * 70) + 220 + 650;
      setTimeout(() => {
        root.classList.add('is-revealing', 'is-drawn');
        setTimeout(() => root.classList.remove('is-revealing'), revealMs);
      }, SETTINGS.duration * 0.72);
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
