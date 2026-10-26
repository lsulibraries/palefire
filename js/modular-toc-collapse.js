/**
 * @file
 * Hide / show the TOC sidebar on Modular pages (lg and up), on every page
 * type but guides, where the TOC is required (the button is hidden there
 * and the sidebar never collapses).
 *
 * The state is a class on <html> (pf-toc-collapsed), so it carries over
 * between Swup visits. The visitor's choice is saved in localStorage per
 * page type ('1' hidden, '0' shown): landing pages keep their own
 * (palefire.tocCollapsed.landing), and Landing hero pages theirs
 * (palefire.tocCollapsed.hero), so showing the TOC on one doesn't open it
 * on every page. With no saved choice, a page that opens with a
 * Landing hero (<html data-pf-hero>) starts hidden, so the hero runs full
 * width; every other page starts shown. It never opens or closes by
 * itself while the visitor scrolls: that would reflow the content column
 * under them (layout shift). This replaces the landing pages' old
 * scroll-driven collapse.
 *
 * The library loads in <head> (header: true in palefire.libraries.yml), so
 * the class is set before the page paints: a hidden sidebar never flashes
 * open and the column doesn't animate on load.
 *
 * Styles: "TOC sidebar toggle" in scss/components/_modular.scss.
 * Markup: the [data-pf-toc-collapse] button in page.html.twig. On a page
 * with the landing's sub-header bar this adds a copy at the bar's end
 * (.pf-landing__toc, beside the video button), which shows the TOC while
 * it is hidden (_landing-features.scss); both work the same and keep the
 * same name.
 * Restored 2026-10-18 from the version removed in 5865c10.
 */
(function (Drupal, once) {
  'use strict';

  const CLASS = 'pf-toc-collapsed';
  const root = document.documentElement;

  // Read each time: a Swup visit can move between page types
  // (palefire-swup.js syncs data-pf-mode before behaviors attach).
  const mode = () => root.getAttribute('data-pf-mode') || 'page';
  // Hero pages keep a choice of their own, so one made on another landing
  // (or saved before they started hidden) doesn't carry over.
  const key = () => {
    if (root.hasAttribute('data-pf-hero')) {
      return 'palefire.tocCollapsed.hero';
    }
    return mode() === 'landing' ? 'palefire.tocCollapsed.landing' : 'palefire.tocCollapsed';
  };

  // TRUE (hidden), FALSE (shown) or NULL (no choice saved).
  function stored() {
    try {
      const value = window.localStorage.getItem(key());
      return value === '1' ? true : (value === '0' ? false : null);
    }
    catch (e) {
      return null;
    }
  }

  function store(collapsed) {
    try {
      window.localStorage.setItem(key(), collapsed ? '1' : '0');
    }
    catch (e) {
      // Storage is blocked: the state lasts until the next full page load.
    }
  }

  // With no saved choice: hidden on a page that opens with a Landing hero
  // (<html data-pf-hero>, PalefireHooks::preprocessHtml()), so the hero
  // gets the full width; shown everywhere else.
  function wanted() {
    if (mode() === 'guide') {
      return false;
    }
    const choice = stored();
    return choice === null ? root.hasAttribute('data-pf-hero') : choice;
  }

  // Full page load: before the first paint.
  root.classList.toggle(CLASS, wanted());

  function sync(button) {
    const collapsed = root.classList.contains(CLASS);
    const label = collapsed ? Drupal.t('Show table of contents') : Drupal.t('Hide table of contents');
    button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    button.setAttribute('aria-label', label);
    button.title = label;
  }

  // Swup visit: <html> keeps the previous page's class, so apply the new
  // page's state without the slide, like a full load.
  function applyDefault() {
    const collapsed = wanted();
    if (root.classList.contains(CLASS) === collapsed) {
      return;
    }
    root.classList.add('pf-toc-instant');
    root.classList.toggle(CLASS, collapsed);
    requestAnimationFrame(() => requestAnimationFrame(() => root.classList.remove('pf-toc-instant')));
  }

  // The sidebar's button, copied to the end of the landing bar's row.
  function addBarCopy(context) {
    const button = document.querySelector('.pf-modular__toc-collapse[data-pf-toc-collapse]');
    once('pf-toc-bar', '.pf-landing__bar-inner', context).forEach((row) => {
      if (!button) {
        return;
      }
      const copy = button.cloneNode(true);
      copy.className = 'pf-landing__toc d-none d-lg-inline-flex';
      row.append(copy);
    });
  }

  Drupal.behaviors.palefireTocCollapse = {
    attach(context) {
      addBarCopy(context);
      once('pf-toc-collapse', '[data-pf-toc-collapse]', context).forEach((button) => {
        applyDefault();
        sync(button);
        button.addEventListener('click', () => {
          const collapsed = !root.classList.contains(CLASS);
          root.classList.toggle(CLASS, collapsed);
          store(collapsed);
          const buttons = document.querySelectorAll('[data-pf-toc-collapse]');
          buttons.forEach(sync);
          // On the landing bar one button shows at a time: when the one
          // pressed has gone, focus moves to the one that took its place.
          if (!button.getClientRects().length) {
            const other = Array.from(buttons).find((b) => b.getClientRects().length);
            if (other) {
              other.focus({ preventScroll: true });
            }
          }
          // Shown again: let the TOC re-measure its indicator in the
          // opened column.
          window.dispatchEvent(new Event('resize'));
        });
      });
    },
  };
})(Drupal, once);
