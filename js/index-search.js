/**
 * @file
 * The Index (/v2/search): moving around a search, and late sections.
 *
 * All of it is an enhancement: without this file every control is a plain link
 * or form, the filters sit in the page with an Apply button, and a late section
 * offers a link to its source's list.
 *
 * - Everything that changes the search (mode tabs, chips, pager, the search
 *   box, filters, sort) replaces only the results, not the page. This isn't
 *   Swup's job: Swup fades the whole page, moves focus to the <h1>, prefetches
 *   on hover (here, a whole search each time) and only runs for logged-out
 *   visitors. Swup still handles result links into the rest of the site.
 * - From 62rem, a filter or sort applies as it's chosen, and focus stays on the
 *   control, so choosing one isn't a change of context (WCAG 3.2.2). Under
 *   62rem, filters open in a drawer and apply with Show results.
 * - The address keeps up, so a search can be shared and Back works.
 * - Late sections: each outside source loads after the page, so a slow or
 *   failing service can't hold it up. One with no results is removed.
 */
(function (Drupal, drupalSettings, once) {
  'use strict';

  const TIMEOUT_MS = 20000;
  const wide = window.matchMedia('(min-width: 62rem)');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const controllers = new Set();
  let visiting = null;

  const load = async (holder) => {
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), TIMEOUT_MS);
    controllers.add(controller);
    try {
      const response = await fetch(holder.dataset.pfIndexLate, { signal: controller.signal });
      if (!response.ok) {
        throw new Error(String(response.status));
      }
      const template = document.createElement('template');
      template.innerHTML = await response.text();
      const list = template.content.querySelector('.pf-index-list');
      if (list && list.dataset.pfIndexTotal === '0') {
        holder.closest('.pf-index-section').hidden = true;
        return;
      }
      holder.replaceChildren(template.content);
      holder.classList.add('is-loaded');
    }
    catch (error) {
      // The server's link is still there, so the visitor has a way on.
    }
    finally {
      window.clearTimeout(timer);
      controllers.delete(controller);
      holder.setAttribute('aria-busy', 'false');
    }
  };

  // A form's address, leaving out the default sort.
  const addressOf = (form) => {
    const params = new URLSearchParams(new FormData(form));
    if (params.get('sort') === 'relevance') {
      params.delete('sort');
    }
    return `${form.getAttribute('action')}?${params}`;
  };

  // Swaps one part of the page for the same part of the next page.
  const swap = (root, page, selector) => {
    const current = root.querySelector(selector);
    const next = page.querySelector(`[data-pf-index] ${selector}`);
    if (current && next) {
      current.replaceWith(next);
    }
    return next;
  };

  // Moves to another search in place. Anything unexpected falls back to loading
  // the address normally, which always works.
  const visit = async (root, url, { focus = null, history = true } = {}) => {
    const main = root.querySelector('[data-pf-index-main]');
    const rail = main.querySelector('[data-pf-index-form]');
    const railScroll = rail ? rail.scrollTop : 0;

    if (visiting) {
      visiting.abort();
    }
    visiting = new AbortController();
    const { signal } = visiting;
    main.setAttribute('aria-busy', 'true');

    let page;
    try {
      const response = await fetch(url, { signal });
      if (!response.ok) {
        throw new Error(String(response.status));
      }
      page = new DOMParser().parseFromString(await response.text(), 'text/html');
      if (!page.querySelector('[data-pf-index] [data-pf-index-main]') || page.querySelector('[data-big-pipe-placeholder-id]')) {
        throw new Error('Not a page that can be swapped in.');
      }
    }
    catch (error) {
      if (error.name !== 'AbortError') {
        window.location.assign(url);
      }
      return;
    }

    // Before the swap: the new pager's links are relative to this address.
    if (history) {
      window.history.pushState({ pfIndex: true }, '', url);
    }
    document.title = page.title;
    Drupal.detachBehaviors(main, drupalSettings, 'unload');
    swap(root, page, '.pf-index__form');
    swap(root, page, '.pf-index__modes');
    const next = swap(root, page, '[data-pf-index-main]');
    next.classList.add('is-arriving');
    Drupal.attachBehaviors(next, drupalSettings);

    const nextRail = next.querySelector('[data-pf-index-form]');
    if (nextRail) {
      nextRail.scrollTop = railScroll;
    }

    // Focus stays on the control where it still exists. Otherwise it goes to
    // the results, so a keyboard user doesn't drop back to the top of the page.
    const target = (focus && root.querySelector(focus)) || next;
    target.focus({ preventScroll: true });

    // Only when the top of the results has gone above the screen.
    if (next.getBoundingClientRect().top < 0) {
      next.scrollIntoView({ behavior: reducedMotion.matches ? 'auto' : 'smooth', block: 'start' });
    }

    const count = next.querySelector('[data-pf-index-count]');
    Drupal.announce(count ? count.textContent : Drupal.t('Results updated.'));
  };

  // The selector that finds a filter or sort again in the new page.
  const focusFor = (control) => (control.type === 'checkbox'
    ? `input[name="${CSS.escape(control.name)}"][value="${CSS.escape(control.value)}"]`
    : `#${CSS.escape(control.id)}`);

  // True for a plain click on a link to another search on this page.
  const isSearchLink = (event, link) => link
    && !event.defaultPrevented
    && event.button === 0
    && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey
    && !link.hasAttribute('download')
    && (!link.target || link.target === '_self')
    && link.origin === window.location.origin
    && link.pathname === window.location.pathname
    && !link.hash;

  // The drawer's buttons. Runs again each time the results are replaced.
  const enhance = (body) => {
    const dialog = body.querySelector('[data-pf-index-filters]');
    const open = body.querySelector('[data-pf-index-open]');
    const close = body.querySelector('[data-pf-index-close]');
    if (!dialog || !open || !close) {
      return;
    }
    open.addEventListener('click', () => dialog.showModal());
    close.addEventListener('click', () => dialog.close());
    // A click on the backdrop lands on the dialog itself.
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) {
        dialog.close();
      }
    });
  };

  const setUp = (root) => {
    root.addEventListener('click', (event) => {
      const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
      if (isSearchLink(event, link)) {
        event.preventDefault();
        visit(root, link.pathname + link.search);
      }
    });

    root.addEventListener('submit', (event) => {
      const form = event.target;
      event.preventDefault();
      const dialog = form.closest('[data-pf-index-filters]');
      if (dialog && dialog.open) {
        dialog.close();
      }
      const focus = form.matches('[data-pf-index-form]') ? '[data-pf-index-open]' : '#pf-index-q';
      visit(root, addressOf(form), { focus });
    });

    root.addEventListener('change', (event) => {
      const control = event.target;
      const isSort = control.matches('select[form="pf-index-filter-form"]');
      const isFilter = wide.matches && control.matches('[data-pf-index-form] input[type="checkbox"]');
      if ((isSort || isFilter) && control.form) {
        visit(root, addressOf(control.form), { focus: focusFor(control) });
      }
    });
  };

  // Once for the whole visit: the page may be gone when Back brings it up.
  const pageStates = () => {
    // A drawer left open has no place in the wider layout.
    wide.addEventListener('change', () => {
      const dialog = document.querySelector('[data-pf-index-filters]');
      if (wide.matches && dialog && dialog.open) {
        dialog.close();
      }
    });

    window.addEventListener('popstate', (event) => {
      // Swup's own history entries are Swup's to restore.
      if (event.state && event.state.source === 'swup') {
        return;
      }
      const root = document.querySelector('[data-pf-index]');
      if (root) {
        visit(root, window.location.pathname + window.location.search, { history: false });
      }
      else if (event.state && event.state.pfIndex) {
        window.location.reload();
      }
    });
  };

  Drupal.behaviors.palefireIndexSearch = {
    attach(context) {
      once('pf-index-states', 'html').forEach(pageStates);
      once('pf-index', '[data-pf-index]', context).forEach(setUp);
      once('pf-index-body', '[data-pf-index-body]', context).forEach(enhance);
      once('pf-index-late', '[data-pf-index-late]', context).forEach(load);
    },
    detach(context, settings, trigger) {
      if (trigger === 'unload') {
        controllers.forEach((controller) => controller.abort());
      }
    },
  };
}(Drupal, drupalSettings, once));
