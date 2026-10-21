/**
 * @file
 * Louisiana Newspapers guide: the parish map.
 *
 * Markup: paragraph--parish-map.html.twig. Choosing a parish (clicking it on
 * the map, picking it from the Parish menu, or from the list in the panel)
 * lights it on the map and fills the panel from its <template>. "All
 * parishes" brings the list back. The URL keeps the choice (#parish-orleans,
 * replaced, never pushed, as the TOC does), so a link opens on that parish.
 *
 * The drawing is aria-hidden; the menu and the list are the keyboard and
 * screen reader way in. Choosing from the list moves focus to the parish's
 * name; the menu keeps focus (arrow keys change it) and a status line says
 * which parish is showing.
 *
 * Draw-on: when the map first scrolls into view the outlines trace from
 * north to south, then the hatch fades in (_parish-map.scss). Skipped with
 * reduced motion, or when the map is already on screen as the page loads.
 */
(function (Drupal, once) {
  'use strict';

  const SETTINGS = {
    linePx: 0.75,       // outline weight on screen, px
    revealEnd: 2600,    // ms; the last parish has traced and filled by then
  };
  const HASH = /^#(parish-[a-z0-9-]+)$/;
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  // OS setting, or the Settings drawer's "Reduce motion" (html.pf-a11y-motion).
  const reduceMotion = () => motion.matches || document.documentElement.classList.contains('pf-a11y-motion');

  function headerHeight() {
    return parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--pf-header-h')) || 50;
  }

  function initMap(root) {
    const svg = root.querySelector('[data-parish-svg]');
    const caption = root.querySelector('[data-parish-caption]');
    const select = root.querySelector('[data-parish-select]');
    const view = root.querySelector('[data-parish-view]');
    const panel = root.querySelector('.pf-parishes__panel');
    if (!svg || !select || !view) return;
    const index = view.firstElementChild;
    const group = svg.querySelector('.pf-parishes__shapes');
    const shapes = new Map();
    svg.querySelectorAll('[data-parish]').forEach((shape) => shapes.set(shape.dataset.parish, shape));
    const entries = new Map();
    const byId = new Map();
    root.querySelectorAll('template[data-parish-entry]').forEach((tpl) => {
      entries.set(tpl.dataset.parishEntry, tpl);
      byId.set(tpl.dataset.parishId, tpl.dataset.parishEntry);
    });
    const name = (key) => (entries.get(key) ? entries.get(key).dataset.parishName : '');
    const resting = caption.textContent;
    let current = '';

    // Screen readers hear which parish the menu chose.
    const status = document.createElement('p');
    status.className = 'visually-hidden';
    status.setAttribute('role', 'status');
    panel.prepend(status);

    // Outline weight in map units, so it stays SETTINGS.linePx at any size.
    const unit = () => {
      const width = svg.getBoundingClientRect().width;
      if (width) {
        svg.style.setProperty('--pf-parishes-line', String((svg.viewBox.baseVal.width / width) * SETTINGS.linePx));
      }
    };
    unit();
    if ('ResizeObserver' in window) {
      new ResizeObserver(unit).observe(svg);
    }

    function bringIntoView(focusTarget) {
      const top = panel.getBoundingClientRect().top;
      const gap = headerHeight() + 16;
      // Stacked (the panel under the map) or scrolled past: glide to it.
      if (top < gap || top > window.innerHeight * 0.6) {
        window.scrollTo({ top: window.scrollY + top - gap, behavior: reduceMotion() ? 'auto' : 'smooth' });
      }
      if (focusTarget) focusTarget.focus({ preventScroll: true });
    }

    function show(key, how = {}) {
      if (key && !entries.has(key)) return;
      if (key === current) {
        if (how.reveal) bringIntoView(how.focus ? view.querySelector('.pf-parishes__name') : null);
        return;
      }
      const previous = current;
      current = key;
      // The chosen parish takes over from any hover.
      light('');
      if (shapes.get(previous)) shapes.get(previous).classList.remove('is-selected');
      const shape = shapes.get(key);
      if (shape) {
        shape.classList.add('is-selected');
        // Last in the drawing, so its outline sits over its neighbours'.
        group.append(shape);
      }
      select.value = key;
      caption.textContent = key ? name(key) : resting;
      root.classList.toggle('has-parish', !!key);

      let focusTarget = null;
      if (key) {
        view.replaceChildren(entries.get(key).content.cloneNode(true));
        Drupal.attachBehaviors(view);
        focusTarget = how.focus ? view.querySelector('.pf-parishes__name') : null;
        status.textContent = Drupal.t('Showing @parish', { '@parish': name(key) });
      }
      else {
        view.replaceChildren(index);
        // Back to the list: focus the parish we came from.
        focusTarget = how.focus ? index.querySelector('[data-parish="' + previous + '"]') : null;
        status.textContent = '';
      }
      const id = key ? entries.get(key).dataset.parishId : '';
      if (how.hash !== false) {
        history.replaceState(history.state, '', id ? '#' + id : location.pathname + location.search);
      }
      if (how.reveal) {
        bringIntoView(focusTarget);
      }
      else if (focusTarget) {
        focusTarget.focus();
      }
    }

    // The map: pointer only (aria-hidden).
    svg.addEventListener('click', (event) => {
      const shape = event.target.closest('[data-parish]');
      if (shape) show(shape.dataset.parish, { reveal: true });
    });
    // Hover, in both directions: a parish under the pointer on the map lights
    // its name in the list, and a name pointed at or focused in the list
    // lights its parish on the map (.is-hover, the map's own hover look).
    // The caption names it either way.
    let lit = '';
    const light = (key) => {
      if (key === lit) return;
      if (lit) {
        if (shapes.get(lit)) shapes.get(lit).classList.remove('is-hover');
        const was = index.querySelector('[data-parish="' + lit + '"]');
        if (was) was.classList.remove('is-hover');
      }
      lit = key;
      if (key) {
        if (shapes.get(key)) shapes.get(key).classList.add('is-hover');
        const pick = index.querySelector('[data-parish="' + key + '"]');
        if (pick) pick.classList.add('is-hover');
      }
      caption.textContent = key ? name(key) : (current ? name(current) : resting);
    };
    svg.addEventListener('pointerover', (event) => {
      const shape = event.target.closest('[data-parish]');
      if (shape) light(shape.dataset.parish);
    });
    svg.addEventListener('pointerleave', () => light(''));
    const pickOf = (event) => {
      const pick = event.target.closest && event.target.closest('.pf-parishes__pick');
      return pick && index.contains(pick) ? pick : null;
    };
    ['pointerover', 'focusin'].forEach((type) => view.addEventListener(type, (event) => {
      const pick = pickOf(event);
      if (pick) light(pick.dataset.parish);
    }));
    ['pointerout', 'focusout'].forEach((type) => view.addEventListener(type, (event) => {
      const pick = pickOf(event);
      // Still on the same name (moving between its text and its padding).
      if (pick && !(event.relatedTarget && pick.contains(event.relatedTarget))) light('');
    }));

    // The list and the "All parishes" button (buttons with data-parish).
    view.addEventListener('click', (event) => {
      const button = event.target.closest('button[data-parish]');
      if (button) show(button.dataset.parish, { focus: true, reveal: true });
    });
    select.addEventListener('change', () => show(select.value));

    const fromHash = (how) => {
      const match = HASH.exec(location.hash);
      if (match && byId.has(match[1])) show(byId.get(match[1]), how);
    };
    const onHash = () => {
      // Swup swapped this map out: stop listening.
      if (!root.isConnected) {
        window.removeEventListener('hashchange', onHash);
        return;
      }
      fromHash({ reveal: true, hash: false });
    };
    window.addEventListener('hashchange', onHash);
    fromHash({ reveal: true, hash: false });

    // Draw-on, once, the first time the map comes into view.
    const rect = svg.getBoundingClientRect();
    const onScreen = rect.top < window.innerHeight && rect.bottom > 0;
    if (reduceMotion() || onScreen || !('IntersectionObserver' in window)) return;
    root.classList.add('is-tracing');
    const observer = new IntersectionObserver((records) => {
      if (!records.some((record) => record.isIntersecting)) return;
      observer.disconnect();
      root.classList.add('is-revealing');
      // Read layout so the hidden state is applied before it's taken off,
      // and the trace animates from it.
      void root.offsetWidth;
      root.classList.remove('is-tracing');
      setTimeout(() => root.classList.remove('is-revealing'), SETTINGS.revealEnd);
    }, { threshold: 0.25 });
    observer.observe(svg);
  }

  Drupal.behaviors.palefireParishMap = {
    attach(context) {
      once('pf-parish-map', '[data-parish-map]', context).forEach(initMap);
    },
  };
})(Drupal, once);
