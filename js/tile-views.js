/**
 * @file
 * The masonry Tile showcase's view switch: Grid (the default), Masonry or List
 * (paragraph--tile-showcase.html.twig, styled in _tile-showcase.scss).
 *
 * Sets data-pf-view on the wrapper and aria-pressed on the buttons, and
 * remembers the visitor's pick in this browser (localStorage, shared by
 * every such section), so the next page opens the same way. The switch is
 * hidden until this runs: without it the grid is simply there. A pick
 * saved as "default" (the masonry's old name, when it was the default) is
 * no longer a view, so those visitors start on Grid too.
 *
 * Masonry packs the cards: each takes the shortest column (--pf-col,
 * --pf-row), in list order, its height a run of rows (--pf-span, set in
 * CSS). Packed again only when the column count changes (ResizeObserver).
 *
 * The change morphs where the browser can (View Transitions): each card
 * gets a name for the length of the change only, so it never meets the
 * page transitions' own. None with prefers-reduced-motion or the Settings
 * drawer's "Reduce motion" (html.pf-a11y-motion): the view just changes.
 */
(function (Drupal, once) {
  'use strict';

  const KEY = 'pf-tile-view';
  const VIEWS = ['grid', 'masonry', 'list'];
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const reduceMotion = () => motion.matches || document.documentElement.classList.contains('pf-a11y-motion');

  const stored = () => {
    try {
      const view = window.localStorage.getItem(KEY);
      return VIEWS.includes(view) ? view : null;
    }
    catch (e) {
      return null;
    }
  };

  const store = (view) => {
    try {
      window.localStorage.setItem(KEY, view);
    }
    catch (e) {
      // Private windows and blocked storage: the switch still works here.
    }
  };

  let uid = 0;

  Drupal.behaviors.palefireTileViews = {
    attach(context) {
      once('pf-tile-views', '.pf-tiles--masonry', context).forEach((wrapper) => {
        const group = wrapper.querySelector('.pf-tiles__views');
        if (!group) {
          return;
        }
        const buttons = Array.from(group.querySelectorAll('[data-pf-view]'));
        const list = wrapper.querySelector('.pf-tiles__list');
        const items = Array.from(wrapper.querySelectorAll('.pf-tiles__item'));
        const id = ++uid;
        let packed = 0;

        const pack = () => {
          const cols = parseInt(getComputedStyle(list).getPropertyValue('--pf-cols'), 10) || 1;
          if (wrapper.dataset.pfView !== 'masonry' || cols === packed) {
            return;
          }
          packed = cols;
          const heights = new Array(cols).fill(1);
          items.forEach((item) => {
            const col = heights.indexOf(Math.min(...heights));
            item.style.setProperty('--pf-col', col + 1);
            item.style.setProperty('--pf-row', heights[col]);
            heights[col] += parseInt(getComputedStyle(item).getPropertyValue('--pf-span'), 10) || 1;
          });
        };

        const set = (view) => {
          wrapper.dataset.pfView = view;
          buttons.forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.pfView === view));
          });
          packed = 0;
          pack();
        };

        const change = (view) => {
          if (view === wrapper.dataset.pfView) {
            return;
          }
          store(view);
          if (!document.startViewTransition || reduceMotion()) {
            set(view);
            return;
          }
          items.forEach((item, i) => {
            item.style.viewTransitionName = `pf-tile-${id}-${i}`;
            item.style.viewTransitionClass = 'pf-tile';
          });
          const transition = document.startViewTransition(() => set(view));
          transition.finished.finally(() => {
            items.forEach((item) => {
              item.style.viewTransitionName = '';
              item.style.viewTransitionClass = '';
            });
          });
        };

        set(stored() || 'grid');
        group.hidden = false;
        if ('ResizeObserver' in window) {
          new ResizeObserver(pack).observe(list);
        }
        buttons.forEach((button) => {
          button.addEventListener('click', () => change(button.dataset.pfView));
        });
      });
    },
  };
})(Drupal, once);
