/**
 * @file
 * Hide / show the "On this page" sidebar on modular pages (lg and up).
 *
 * The state is a class on <html> (pf-toc-collapsed), so it carries over
 * between Swup visits, and it is saved in localStorage. The library loads
 * in <head> (header: true in palefire.libraries.yml), so the class is set
 * before the page paints: a hidden sidebar never flashes open and the
 * column doesn't animate on load.
 *
 * Styles: "TOC sidebar toggle" in scss/components/_modular.scss.
 * Markup: the [data-pf-toc-collapse] button in page.html.twig.
 */
(function (Drupal, once) {
  const KEY = 'palefire.tocCollapsed';
  const CLASS = 'pf-toc-collapsed';
  const root = document.documentElement;

  function stored() {
    try {
      return window.localStorage.getItem(KEY) === '1';
    }
    catch (e) {
      return false;
    }
  }

  function store(collapsed) {
    try {
      if (collapsed) {
        window.localStorage.setItem(KEY, '1');
      }
      else {
        window.localStorage.removeItem(KEY);
      }
    }
    catch (e) {
      // Storage is blocked: the state lasts until the next full page load.
    }
  }

  root.classList.toggle(CLASS, stored());

  function sync(button) {
    const collapsed = root.classList.contains(CLASS);
    button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    button.title = collapsed ? Drupal.t('Show table of contents') : Drupal.t('Hide sidebar');
  }

  Drupal.behaviors.palefireTocCollapse = {
    attach(context) {
      once('pf-toc-collapse', '[data-pf-toc-collapse]', context).forEach((button) => {
        sync(button);
        button.addEventListener('click', () => {
          const collapsed = !root.classList.contains(CLASS);
          root.classList.toggle(CLASS, collapsed);
          store(collapsed);
          sync(button);
        });
      });
    },
  };
})(Drupal, once);
