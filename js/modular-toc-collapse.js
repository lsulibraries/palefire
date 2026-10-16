/**
 * @file
 * Hide / show the "On this page" sidebar on modular pages (lg and up).
 *
 * The state is a class on <html> (pf-toc-collapsed), so it carries over
 * between Swup visits, and the visitor's choice is saved in localStorage:
 * '1' hidden, '0' shown. With no saved choice the page decides: a page that
 * opens with a bento hero (<html data-pf-toc-default="hidden">, set by
 * PalefireHooks::preprocessHtml()) starts hidden, so the hero is full
 * width; every other page starts shown. It never opens or closes by itself
 * while the visitor scrolls: that would reflow the content column under
 * them (and count as layout shift).
 *
 * The library loads in <head> (header: true in palefire.libraries.yml), so
 * the class is set before the page paints: a hidden sidebar never flashes
 * open and the column doesn't animate on load.
 *
 * Styles: "TOC sidebar toggle" in scss/components/_modular.scss.
 * Markup: the [data-pf-toc-collapse] button in page.html.twig.
 */
(function (Drupal, once) {
  const KEY = 'palefire.tocCollapsed';
  const CLASS = 'pf-toc-collapsed';
  const root = document.documentElement;

  // TRUE (hidden), FALSE (shown) or NULL (no choice saved).
  function stored() {
    try {
      const value = window.localStorage.getItem(KEY);
      return value === '1' ? true : (value === '0' ? false : null);
    }
    catch (e) {
      return null;
    }
  }

  function store(collapsed) {
    try {
      window.localStorage.setItem(KEY, collapsed ? '1' : '0');
    }
    catch (e) {
      // Storage is blocked: the state lasts until the next full page load.
    }
  }

  // Full page load: the server marks hero pages on <html>.
  const saved = stored();
  root.classList.toggle(CLASS, saved === null ? root.getAttribute('data-pf-toc-default') === 'hidden' : saved);

  function sync(button) {
    const collapsed = root.classList.contains(CLASS);
    button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    button.title = collapsed ? Drupal.t('Show table of contents') : Drupal.t('Hide sidebar');
  }

  // Swup visit: <html> keeps the previous page's attributes, so the default
  // comes from the new page itself (its first section is a bento). Applied
  // without the slide, like a full load.
  function applyDefault(context) {
    if (stored() !== null) {
      return;
    }
    const hero = !!context.querySelector('.pf-sections > .pf-section:first-child .pf-bento');
    if (root.classList.contains(CLASS) === hero) {
      return;
    }
    root.classList.add('pf-toc-instant');
    root.classList.toggle(CLASS, hero);
    requestAnimationFrame(() => requestAnimationFrame(() => root.classList.remove('pf-toc-instant')));
  }

  Drupal.behaviors.palefireTocCollapse = {
    attach(context) {
      once('pf-toc-collapse', '[data-pf-toc-collapse]', context).forEach((button) => {
        applyDefault(document);
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
