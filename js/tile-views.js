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
    },
  };
})(Drupal, once);
