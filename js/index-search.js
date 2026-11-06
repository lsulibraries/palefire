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
 * - A Catalog row's holdings load after the list, on an address of their own
 *   that stays fresher than results can. If they fail, the row just has no line.
 * - Source options can be put in the visitor's own order, by dragging or with
 *   an up button, and the sections follow. It's kept in this browser, and only
 *   a preference: without storage or this file, the page's own order stands.
 * - A cover that fails, or a host's 1px "no cover" stand-in, gives its width
 *   back to the row's text.
 */
(function (Drupal, drupalSettings, once) {
  'use strict';

  const TIMEOUT_MS = 20000;
  const HOLDINGS_TIMEOUT_MS = 10000;
  const ORDER_KEY = 'pf-index-source-order';
  const wide = window.matchMedia('(min-width: 62rem)');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const controllers = new Set();
  let visiting = null;

  // Whether the library's proxy already knows this browser. The proxy serves
  // this image only with a session, and sends its login page otherwise, which
  // an image can't load. It can't see a saved Microsoft sign-in, so someone may
  // get straight through while the tag still says LSU login: the tag only ever
  // moves to Signed in on proof. If the image goes away, it stays at LSU login.
  const SIGN_IN_IMAGE = 'https://libezp.lib.lsu.edu/login?url=https://www.jstor.org/favicon.ico';
  const checking = new WeakSet();

  const checkSignIn = (root) => {
    if (!root || checking.has(root) || root.hasAttribute('data-pf-signed-in') || !root.querySelector('.pf-index-row__login, [data-pf-index-late]')) {
      return;
    }
    checking.add(root);
    const image = new Image();
    image.referrerPolicy = 'no-referrer';
    image.onload = () => {
      checking.delete(root);
      if (image.naturalWidth > 0) {
        root.setAttribute('data-pf-signed-in', '');
      }
    };
    image.onerror = () => checking.delete(root);
    // A fresh address each time: a stored copy would outlive the session.
    image.src = `${SIGN_IN_IMAGE}?${Date.now()}`;
  };

  // Fills each row's place from one answer, and removes the places left empty.
  const loadHoldings = async (rows) => {
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), HOLDINGS_TIMEOUT_MS);
    controllers.add(controller);
    const template = document.createElement('template');
    try {
      const response = await fetch(rows.dataset.pfIndexHoldingsUrl, { signal: controller.signal });
      if (response.ok) {
        template.innerHTML = await response.text();
      }
    }
    catch (error) {
      // The rows are still whole, with the record's link.
    }
    finally {
      window.clearTimeout(timer);
      controllers.delete(controller);
    }
    rows.querySelectorAll('[data-pf-index-holdings]').forEach((place) => {
      const found = template.content.querySelector(`[data-pf-index-holdings-for="${CSS.escape(place.dataset.pfIndexHoldings)}"]`);
      if (found) {
        place.replaceChildren(...found.childNodes);
        place.classList.add('is-loaded');
      }
      else {
        place.remove();
      }
    });
  };

  const load = async (holder) => {
    // The site's mark while this section's service answers.
    const mark = holder.closest('[data-pf-index]')?.querySelector('[data-pf-index-loading] .pf-index__mark');
    if (mark) {
      holder.prepend(mark.cloneNode(true));
      holder.classList.add('is-searching');
    }
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
      once('pf-index-holdings', '[data-pf-index-holdings-url]', holder).forEach(loadHoldings);
    }
    catch (error) {
      // The server's link is still there, so the visitor has a way on.
    }
    finally {
      window.clearTimeout(timer);
      controllers.delete(controller);
      holder.classList.remove('is-searching');
      holder.querySelector(':scope > .pf-index__mark')?.remove();
      holder.setAttribute('aria-busy', 'false');
    }
  };

  // A form's address, leaving out the default sort and empty years.
  const addressOf = (form) => {
    const params = new URLSearchParams(new FormData(form));
    if (params.get('sort') === 'relevance') {
      params.delete('sort');
    }
    ['from', 'to'].forEach((name) => {
      if (params.get(name) === '') {
        params.delete(name);
      }
    });
    // A round button's Any has no value, so nothing to send.
    [...params.entries()].forEach(([name, value]) => {
      if (name.startsWith('refine[') && value === '') {
        params.delete(name);
      }
    });
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
    root.classList.add('is-searching');
    Drupal.announce(Drupal.t('Searching'));

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
    root.classList.remove('is-searching');
    checkSignIn(root);
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
  const focusFor = (control) => (control.type === 'checkbox' || control.type === 'radio'
    ? `input[name="${CSS.escape(control.name)}"][value="${CSS.escape(control.value)}"]:not(:disabled)`
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

  // Load and error don't bubble, so these listen on the way down.
  const dropEmptyCover = (event) => {
    const image = event.target;
    if (image instanceof HTMLImageElement
      && image.parentElement.matches('.pf-index-row__thumb, .pf-index-row__label-cover')
      && (event.type === 'error' || image.naturalWidth <= 1)) {
      const row = image.closest('.pf-index-row');
      image.parentElement.remove();
      if (!row.querySelector('.pf-index-row__thumb')) {
        row.classList.remove('pf-index-row--image');
      }
    }
  };

  // One that finished before this script ran has no event left to hear. An
  // image still waiting, lazily or on the network, isn't complete.
  const checkCover = (image) => {
    if (image.complete && image.naturalWidth <= 1) {
      dropEmptyCover({ target: image, type: 'error' });
    }
  };

  const readOrder = () => {
    try {
      const order = JSON.parse(window.localStorage.getItem(ORDER_KEY));
      return Array.isArray(order) ? order : [];
    }
    catch (error) {
      return [];
    }
  };

  const writeOrder = (order) => {
    try {
      if (order) {
        window.localStorage.setItem(ORDER_KEY, JSON.stringify(order));
      }
      else {
        window.localStorage.removeItem(ORDER_KEY);
      }
    }
    catch (error) {
      // It still applies until the next page.
    }
  };

  // Slides rows from where they were to where a change puts them.
  const slide = (rows, change) => {
    if (reducedMotion.matches) {
      change();
      return;
    }
    const before = new Map(rows.map((row) => [row, row.getBoundingClientRect().top]));
    change();
    rows.forEach((row) => {
      const distance = before.get(row) - row.getBoundingClientRect().top;
      if (distance) {
        row.animate([{ transform: `translateY(${distance}px)` }, { transform: 'none' }], { duration: 200, easing: 'cubic-bezier(.2, 0, 0, 1)' });
      }
    });
  };

  const icon = (path) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="${path}"/></svg>`;

  // Source rows the visitor can reorder. Runs again each time results are replaced.
  const setUpSources = (body) => {
    const labels = [...body.querySelectorAll('.pf-index__option input[name="source[]"]')].map((input) => input.closest('.pf-index__option'));
    if (labels.length < 2) {
      return;
    }
    const scope = body.closest('[data-pf-index-main]') || body;
    const sections = [...scope.querySelectorAll('[data-pf-index-source]')];
    const list = labels[0].parentElement;
    const rowsNow = () => [...list.querySelectorAll(':scope > .pf-index__source')];
    const keysNow = () => rowsNow().map((row) => row.dataset.source);

    const reset = document.createElement('button');
    reset.type = 'button';
    reset.className = 'pf-index__source-reset';
    reset.textContent = Drupal.t('Reset order');

    // The move button and handle sit beside the label, because a label can't hold
    // a second control.
    const rows = labels.map((label) => {
      const name = label.querySelector('.pf-index__option-label').textContent.trim();
      const row = document.createElement('div');
      row.className = 'pf-index__source';
      row.dataset.source = label.querySelector('input').value;
      label.before(row);
      row.append(label);

      const up = document.createElement('button');
      up.type = 'button';
      up.className = 'pf-index__source-up';
      up.setAttribute('aria-label', Drupal.t('Move @source up', { '@source': name }));
      up.innerHTML = icon('M12 19V5M6 11l6-6 6 6');
      row.append(up);

      // For a mouse or finger: the up button does the same with one press or key.
      const grip = document.createElement('span');
      grip.className = 'pf-index__source-grip';
      grip.setAttribute('aria-hidden', 'true');
      grip.title = Drupal.t('Drag to reorder');
      grip.innerHTML = icon('M9 6h.01M15 6h.01M9 12h.01M15 12h.01M9 18h.01M15 18h.01');
      row.append(grip);
      return row;
    });
    const firstOrder = rows.map((row) => row.dataset.source);
    const firstSections = [...sections];

    const placeSections = (rank) => {
      if (!sections.length) {
        return;
      }
      const after = sections[sections.length - 1].nextSibling;
      const parent = sections[0].parentElement;
      [...firstSections].sort((a, b) => rank(a.dataset.pfIndexSource) - rank(b.dataset.pfIndexSource)).forEach((section) => parent.insertBefore(section, after));
      sections.sort((a, b) => rank(a.dataset.pfIndexSource) - rank(b.dataset.pfIndexSource));
    };

    const refresh = () => {
      rowsNow().forEach((row, index) => {
        row.querySelector('.pf-index__source-up').setAttribute('aria-disabled', index === 0 ? 'true' : 'false');
      });
      reset.hidden = keysNow().join() === firstOrder.join();
    };

    const rankIn = (order) => (key) => {
      const place = order.indexOf(key);
      return place === -1 ? Infinity : place;
    };

    const apply = (order) => {
      const rank = rankIn(order);
      list.append(...[...rows].sort((a, b) => rank(a.dataset.source) - rank(b.dataset.source)), reset);
      placeSections(rank);
      refresh();
    };

    // Sources this mode doesn't show keep their places for the modes that do.
    const save = () => {
      const keys = keysNow();
      const order = [...keys, ...readOrder().filter((key) => !keys.includes(key))];
      writeOrder(order);
      placeSections(rankIn(order));
      refresh();
    };

    const announce = (row) => {
      const name = row.querySelector('.pf-index__option-label').textContent.trim();
      const keys = keysNow();
      Drupal.announce(Drupal.t('@source moved to position @place of @total.', { '@source': name, '@place': keys.indexOf(row.dataset.source) + 1, '@total': keys.length }));
    };

    list.addEventListener('click', (event) => {
      const up = event.target instanceof Element ? event.target.closest('.pf-index__source-up') : null;
      if (!up || up.getAttribute('aria-disabled') === 'true') {
        return;
      }
      const row = up.closest('.pf-index__source');
      // Moves its neighbor: an element that's moved itself loses focus.
      slide(rowsNow(), () => row.after(row.previousElementSibling));
      save();
      announce(row);
    });

    list.addEventListener('pointerdown', (event) => {
      const grip = event.target instanceof Element ? event.target.closest('.pf-index__source-grip') : null;
      if (!grip || event.button !== 0) {
        return;
      }
      event.preventDefault();
      const row = grip.closest('.pf-index__source');
      grip.setPointerCapture(event.pointerId);
      row.classList.add('is-dragging');
      const start = keysNow().join();

      const move = (moved) => {
        const others = rowsNow().filter((other) => other !== row);
        const next = others.find((other) => {
          const box = other.getBoundingClientRect();
          return moved.clientY < box.top + box.height / 2;
        }) || reset;
        // Moves the rows around it: an element that's moved itself loses the
        // pointer, and the drag would end there.
        const all = rowsNow();
        const from = all.indexOf(row);
        const to = next === reset ? all.length : all.indexOf(next);
        if (to < from) {
          slide(others, () => row.after(...all.slice(to, from)));
        }
        else if (to > from + 1) {
          slide(others, () => row.before(...all.slice(from + 1, to)));
        }
      };
      const end = () => {
        grip.removeEventListener('pointermove', move);
        grip.removeEventListener('pointerup', end);
        grip.removeEventListener('pointercancel', end);
        row.classList.remove('is-dragging');
        if (keysNow().join() !== start) {
          save();
          announce(row);
        }
      };
      grip.addEventListener('pointermove', move);
      grip.addEventListener('pointerup', end);
      grip.addEventListener('pointercancel', end);
    });

    reset.addEventListener('click', () => {
      writeOrder(null);
      apply(firstOrder);
      rowsNow()[0].querySelector('.pf-index__source-up').focus();
      Drupal.announce(Drupal.t('Sources are back in their usual order.'));
    });

    apply(readOrder().length ? readOrder() : firstOrder);
  };

  const setUp = (root) => {
    root.addEventListener('load', dropEmptyCover, true);
    root.addEventListener('error', dropEmptyCover, true);

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
      const isYear = control.matches('[data-pf-index-year]');
      const isFilter = wide.matches && (control.matches('[data-pf-index-form] input:is([type="checkbox"], [type="radio"])') || isYear);
      // A wrong year stays put and says why, rather than running a search.
      if (isFilter && isYear && !control.reportValidity()) {
        return;
      }
      if ((isSort || isFilter) && control.form) {
        visit(root, addressOf(control.form), { focus: focusFor(control) });
      }
    });
  };

  // Once for the whole visit: the page may be gone when Back brings it up.
  const pageStates = () => {
    // Back from signing in, in another tab or by the Back button.
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') {
        checkSignIn(document.querySelector('[data-pf-index]'));
      }
    });
    window.addEventListener('pageshow', (event) => {
      if (event.persisted) {
        checkSignIn(document.querySelector('[data-pf-index]'));
      }
    });

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
      once('pf-index-sign-in', '[data-pf-index]', context).forEach(checkSignIn);
      once('pf-index-body', '[data-pf-index-body]', context).forEach(enhance);
      once('pf-index-sources', '[data-pf-index-body]', context).forEach(setUpSources);
      once('pf-index-late', '[data-pf-index-late]', context).forEach(load);
      once('pf-index-holdings', '[data-pf-index-holdings-url]', context).forEach(loadHoldings);
      once('pf-index-thumb', '.pf-index-row__thumb img', context).forEach(checkCover);
    },
    detach(context, settings, trigger) {
      if (trigger === 'unload') {
        controllers.forEach((controller) => controller.abort());
      }
    },
  };
}(Drupal, drupalSettings, once));
