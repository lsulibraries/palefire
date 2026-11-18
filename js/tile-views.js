/**
 * @file
 * The Grid / List switch: on the masonry-layout Tile showcase
 * (paragraph--tile-showcase.html.twig, styled in _tile-showcase.scss) and on
 * an exhibition's reading lists (paragraph--exhibit-gallery.html.twig,
 * _exhibition.scss).
 *
 * Sets data-pf-view on the wrapper and aria-pressed on the buttons, and
 * remembers the visitor's pick in this browser (localStorage), so the next
 * page opens the same way. Tile showcases share one pick, reading lists
 * another (the wrapper's data-pf-views names its key); a pick changes every
 * list of its kind on the page at once. The switch is hidden until this
 * runs: without it the grid is simply there. A pick saved as "masonry" or
 * "default" (views that are gone) starts on Grid.
 *
 * The change morphs where the browser can (View Transitions): each card
 * gets a name for the length of the change only, so it never meets the
 * page transitions' own. None with prefers-reduced-motion or the Settings
 * drawer's "Reduce motion" (html.pf-a11y-motion): the view just changes.
 *
 * Oral History's series (.pf-series__views, landing.html.twig) open
 * folded: two rows of the grid (four beside the tape deck, the stylesheet's
 * --pf-fold-rows), or five of the list, at the columns of the
 * moment, then a "Show more" button that opens as many again in place, the
 * first of them taking focus. Beside it, "Showing 12 of 39"
 * (.pf-series__shown, the button's description). Once all show, it says "Show less" and folds
 * them again, the page held so the button stays where it was under the
 * pointer. The fold follows the view and the width. Without this script
 * every series shows and there is no button.
 */
(function (Drupal, once) {
  'use strict';

  const VIEWS = ['grid', 'list'];
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const reduceMotion = () => motion.matches || document.documentElement.classList.contains('pf-a11y-motion');

  const keyOf = (wrapper) => wrapper.dataset.pfViews || 'pf-tile-view';

  const stored = (key) => {
    try {
      const view = window.localStorage.getItem(key);
      return VIEWS.includes(view) ? view : null;
    }
    catch (e) {
      return null;
    }
  };

  const store = (key, view) => {
    try {
      window.localStorage.setItem(key, view);
    }
    catch (e) {
      // Private windows and blocked storage: the switch still works here.
    }
  };

  // The cards of a wrapper: a showcase's tiles, a reading list's covers.
  const itemsOf = (wrapper) => Array.from(wrapper.querySelectorAll('.pf-tiles__item, .pf-exhibit-group__grid > *'));

  const set = (wrapper, view) => {
    wrapper.dataset.pfView = view;
    wrapper.querySelectorAll('.pf-tiles__views [data-pf-view]').forEach((button) => {
      button.setAttribute('aria-pressed', String(button.dataset.pfView === view));
    });
  };

  let uid = 0;

  const change = (wrapper, view) => {
    if (view === wrapper.dataset.pfView) {
      return;
    }
    const key = keyOf(wrapper);
    store(key, view);
    const wrappers = Array.from(document.querySelectorAll('[data-pf-view-ready]')).filter((other) => keyOf(other) === key);
    const apply = () => wrappers.forEach((other) => set(other, view));
    if (!document.startViewTransition || reduceMotion()) {
      apply();
      return;
    }
    // Only the cards on screen morph; the rest just change.
    const items = itemsOf(wrapper).filter((item) => {
      const rect = item.getBoundingClientRect();
      return rect.bottom > 0 && rect.top < window.innerHeight;
    });
    const id = ++uid;
    items.forEach((item, i) => {
      item.style.viewTransitionName = `pf-tile-${id}-${i}`;
      item.style.viewTransitionClass = 'pf-tile';
    });
    const transition = document.startViewTransition(apply);
    transition.finished.finally(() => {
      items.forEach((item) => {
        item.style.viewTransitionName = '';
        item.style.viewTransitionClass = '';
      });
    });
  };

  // Rows shown while folded, by view.
  const FOLD_ROWS = { grid: 2, list: 5 };

  const fold = (wrapper) => {
    const list = wrapper.querySelector('.pf-series__list');
    const items = list ? Array.from(list.children) : [];
    if (!list || items.length < 2) {
      return;
    }
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'pf-series__more';
    button.setAttribute('aria-expanded', 'false');
    if (!list.id) {
      list.id = 'pf-series-list-' + (++uid);
    }
    button.setAttribute('aria-controls', list.id);
    // A slab: the words, then the arrow at the end (the tally is laid over
    // it before the arrow).
    button.innerHTML = '<span></span><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M8 3v10M4 9l4 4 4-4"/></svg>';
    const label = button.querySelector('span');
    // How many show of how many, beside the button and read with it.
    const tally = document.createElement('span');
    tally.className = 'pf-series__shown';
    tally.id = list.id + '-shown';
    button.setAttribute('aria-describedby', tally.id);
    const bar = document.createElement('div');
    bar.className = 'pf-series__fold';
    bar.append(button, tally);
    list.after(bar);

    // Pages of the fold shown: 1 is folded; each Show more adds one.
    let pages = 1;
    let open = false;
    const name = () => {
      button.setAttribute('aria-expanded', String(open));
      button.classList.toggle('is-open', open);
      label.textContent = open ? Drupal.t('Show less') : Drupal.t('Show more');
    };
    const page = () => {
      const cols = getComputedStyle(list).gridTemplateColumns.split(' ').filter(Boolean).length || 1;
      // The stylesheet may ask for more rows (--pf-fold-rows: the grid
      // beside the tape deck, js/series-deck.js).
      const rows = parseInt(getComputedStyle(list).getPropertyValue('--pf-fold-rows'), 10) || FOLD_ROWS[wrapper.dataset.pfView] || FOLD_ROWS.grid;
      return cols * rows;
    };
    const apply = () => {
      const size = page();
      const shown = size * pages;
      items.forEach((item, i) => {
        item.hidden = i >= shown;
      });
      open = shown >= items.length;
      bar.hidden = size >= items.length;
      tally.textContent = Drupal.t('Showing @shown of @total', { '@shown': Math.min(shown, items.length), '@total': items.length });
      name();
    };

    button.addEventListener('click', () => {
      if (open) {
        // Fold again, holding the button where it was on screen.
        const before = button.getBoundingClientRect().top;
        pages = 1;
        items.forEach((item) => item.classList.remove('is-unfolding'));
        apply();
        window.scrollBy({ top: button.getBoundingClientRect().top - before, behavior: 'instant' });
        return;
      }
      const hidden = items.filter((item) => item.hidden);
      pages++;
      apply();
      const shown = hidden.filter((item) => !item.hidden);
      shown.forEach((item) => item.classList.add('is-unfolding'));
      const link = shown[0] && shown[0].querySelector('a');
      if (link) {
        link.focus({ preventScroll: true });
      }
    });

    apply();
    if ('ResizeObserver' in window) {
      new ResizeObserver(apply).observe(list);
    }
    new MutationObserver(apply).observe(wrapper, { attributes: true, attributeFilter: ['data-pf-view'] });
  };

  Drupal.behaviors.palefireTileViews = {
    attach(context) {
      once('pf-tile-views', '.pf-tiles--masonry, [data-pf-views]', context).forEach((wrapper) => {
        const group = wrapper.querySelector('.pf-tiles__views');
        if (!group) {
          return;
        }
        wrapper.dataset.pfViewReady = '';
        set(wrapper, stored(keyOf(wrapper)) || 'grid');
        group.hidden = false;
        group.querySelectorAll('[data-pf-view]').forEach((button) => {
          button.addEventListener('click', () => change(wrapper, button.dataset.pfView));
        });
      });
      once('pf-series-fold', '.pf-series__views', context).forEach(fold);
    },
  };
})(Drupal, once);
