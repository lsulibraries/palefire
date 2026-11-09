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
 * - A holdings table's copy numbers load when it's first opened, from an
 *   address of their own: they come from the catalog's record page, too slow
 *   to ask for every result. If they fail, the column stays empty.
 * - Each library's table in a holdings panel sorts by Call number or Status:
 *   ascending, then descending.
 * - A paged list gets a Show more results button, which adds the next page under
 *   the rows it has. It's only ever on request: a list that grew by itself would
 *   keep the footer out of reach. The pager stays, for jumping ahead.
 */
(function (Drupal, drupalSettings, once) {
  'use strict';

  const TIMEOUT_MS = 20000;
  const HOLDINGS_TIMEOUT_MS = 10000;
  const ORDER_KEY = 'pf-index-source-order';
  const wide = window.matchMedia('(min-width: 62rem)');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  // Each request in flight, with the element it fills.
  const controllers = new Map();
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
    // Now: a list that's being added to hands these rows on while this waits.
    const places = [...rows.querySelectorAll('[data-pf-index-holdings]')];
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), HOLDINGS_TIMEOUT_MS);
    controllers.set(controller, rows);
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
    places.forEach((place) => {
      const found = template.content.querySelector(`[data-pf-index-holdings-for="${CSS.escape(place.dataset.pfIndexHoldings)}"]`);
      if (found) {
        place.replaceChildren(...found.childNodes);
        place.classList.add('is-loaded');
        // The list only knows the first box of a run. Its call number comes last.
        const meta = place.closest('.pf-index-row')?.querySelector('.pf-index-row__meta');
        const { pfIndexCall: call, pfIndexCallFirst: first } = found.dataset;
        if (meta && call && first && meta.textContent.endsWith(first)) {
          meta.textContent = meta.textContent.slice(0, -first.length) + call;
        }
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
    controllers.set(controller, holder);
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
      const count = holder.closest('.pf-index-section')?.querySelector('[data-pf-index-section-count]');
      if (count && list && list.dataset.pfIndexCount) {
        count.textContent = list.dataset.pfIndexCount;
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

  // Adds the next page of a list to the rows it has, on request. The address
  // follows, so a reload or a shared link lands on the page reached. Anything
  // unexpected falls back to loading that page normally, which always works.
  const setUpMore = (pager) => {
    const list = pager.closest('.pf-index-list');
    const rows = list && list.querySelector(':scope > .pf-index-list__rows');
    if (!rows || !pager.querySelector('a[rel="next"]')) {
      return;
    }
    const pageNumber = Number(new URLSearchParams(window.location.search).get('page')) || 0;
    const first = pageNumber * rows.children.length + 1;
    const total = Number(list.dataset.pfIndexTotal);

    const box = document.createElement('div');
    box.className = 'pf-index-list__grow';
    const button = document.createElement('button');
    button.type = 'button';
    const label = document.createElement('span');
    label.className = 'pf-index-list__grow-label';
    const count = document.createElement('span');
    count.className = 'pf-index-list__grow-count';
    button.append(label, count);
    button.insertAdjacentHTML('beforeend', icon('M12 5v14M5 12h14'));
    box.append(button);
    rows.after(box);

    const shown = () => (first === 1
      ? Drupal.t('@shown of @total shown', { '@shown': rows.children.length.toLocaleString(), '@total': total.toLocaleString() })
      : Drupal.t('@first to @last of @total shown', { '@first': first.toLocaleString(), '@last': (first + rows.children.length - 1).toLocaleString(), '@total': total.toLocaleString() }));
    const rest = () => {
      label.textContent = Drupal.t('Show more results');
      count.textContent = shown();
      button.removeAttribute('aria-busy');
    };
    rest();

    button.addEventListener('click', async () => {
      const next = list.querySelector('.pf-index-list__pager a[rel="next"]');
      if (!next || button.hasAttribute('aria-busy')) {
        return;
      }
      const url = next.href;
      button.setAttribute('aria-busy', 'true');
      label.textContent = Drupal.t('Loading more results');
      Drupal.announce(Drupal.t('Loading more results'));

      const controller = new AbortController();
      controllers.set(controller, button);
      let more;
      let added;
      try {
        const response = await fetch(url, { signal: controller.signal });
        if (!response.ok) {
          throw new Error(String(response.status));
        }
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        more = page.querySelector('[data-pf-index] .pf-index-list__pager')?.closest('.pf-index-list');
        added = more ? more.querySelector(':scope > .pf-index-list__rows') : null;
        if (!added || !added.children.length || page.querySelector('[data-big-pipe-placeholder-id]')) {
          throw new Error('Not a page that can be added.');
        }
      }
      catch (error) {
        if (error.name !== 'AbortError') {
          window.location.assign(url);
        }
        return;
      }
      finally {
        controllers.delete(controller);
      }

      if (added.dataset.pfIndexHoldingsUrl) {
        loadHoldings(added);
      }
      const arriving = [...added.children];
      arriving.forEach((row, index) => {
        row.classList.add('is-new');
        row.style.setProperty('--pf-row-order', Math.min(index, 5));
      });
      rows.append(...arriving);
      arriving.forEach((row) => once('pf-index-thumb', '.pf-index-row__thumb img', row).forEach(checkCover));

      // The pager's links are relative, so the address goes first.
      window.history.replaceState({ pfIndex: true }, '', url);
      list.querySelector(':scope > .pf-index-list__pager').replaceWith(more.querySelector(':scope > .pf-index-list__pager'));
      // The note that this is as far as the service reads, on its last page.
      more.querySelectorAll(':scope > .pf-index-list__rows ~ .pf-index-list__status').forEach((note) => box.before(note));

      const message = Drupal.t('@count more results added. @shown.', { '@count': arriving.length, '@shown': shown() });
      if (list.querySelector('.pf-index-list__pager a[rel="next"]')) {
        rest();
      }
      else {
        box.remove();
      }
      // The new rows open where the button was, so this rarely needs to scroll.
      const firstLink = arriving[0].querySelector('.pf-index-row__title a');
      firstLink?.focus({ preventScroll: true });
      firstLink?.scrollIntoView({ block: 'nearest' });
      Drupal.announce(message);
    });
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
    // All sources is what no source means, not a filter.
    params.delete('all');
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

  // The results bar names the source in view once its heading has gone under
  // the bar: on a long page of sections it's easy to lose track. Hidden from
  // screen readers, which have the headings.
  const followSections = (toolbar) => {
    const total = toolbar.querySelector('.pf-index__total');
    const scope = toolbar.closest('[data-pf-index-main]');
    if (!total || !scope || scope.querySelectorAll('.pf-index-section__head').length < 2) {
      return;
    }
    // "Results for “…”" in its own span, and "Results for" in one of its own, so
    // a phone can show the source in place of those words.
    const text = document.createElement('span');
    text.className = 'pf-index__total-text';
    text.append(...total.childNodes);
    const lead = text.firstChild;
    if (lead && lead.nodeType === Node.TEXT_NODE) {
      const words = document.createElement('span');
      words.className = 'pf-index__total-words';
      lead.replaceWith(words);
      words.append(lead);
    }
    const where = document.createElement('span');
    where.className = 'pf-index__where';
    where.setAttribute('aria-hidden', 'true');
    where.hidden = true;
    const name = document.createElement('span');
    const count = document.createElement('span');
    count.className = 'pf-index__where-count';
    where.append(name, count);
    total.replaceChildren(where, text);

    let current = null;
    let frame = 0;
    const update = () => {
      frame = 0;
      if (!toolbar.isConnected) {
        window.removeEventListener('scroll', schedule);
        window.removeEventListener('resize', schedule);
        return;
      }
      const line = toolbar.getBoundingClientRect().bottom;
      let next = null;
      scope.querySelectorAll('.pf-index-section').forEach((section) => {
        const head = section.querySelector(':scope > .pf-index-section__head');
        if (head && !section.hidden && head.getBoundingClientRect().bottom <= line) {
          next = section;
        }
      });
      if (!next) {
        where.hidden = true;
        current = null;
        return;
      }
      const head = next.querySelector(':scope > .pf-index-section__head');
      name.textContent = head.querySelector('.pf-index-section__title').textContent.trim();
      // A late section's count arrives after its heading.
      const number = head.querySelector('[data-pf-index-section-count]');
      count.textContent = number ? number.textContent.trim().replace(/\s*results?$/i, '') : '';
      if (next !== current) {
        current = next;
        where.hidden = false;
        // Again for each source, so the change is seen.
        where.style.animation = 'none';
        void where.offsetWidth;
        where.style.animation = '';
      }
    };
    function schedule() {
      if (!frame) {
        frame = window.requestAnimationFrame(update);
      }
    }
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule, { passive: true });
    update();
  };

  // Today's hours in a search's hours answer, from the same LibCal requests as
  // the Hours drawer (utility-panels.js, visit-hours.js). Without them, the
  // answer keeps its link to the full hours.
  const fillAnswer = (answer) => {
    const list = answer.querySelector('.pf-index-answer__hours');
    if (!list || !Drupal.palefireHours || !Drupal.palefireHoursRow) {
      return;
    }
    const date = answer.querySelector('[data-pf-index-answer-date]');
    if (date) {
      date.textContent = new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' }).replace(',', '');
    }
    list.hidden = false;
    list.querySelectorAll('[data-pf-hours-loc][data-lid]').forEach((row) => {
      Drupal.palefireHours.load(row.dataset.lid).then((data) => {
        Drupal.palefireHoursRow(row, data);
      }).catch(() => {
        row.classList.remove('is-loading');
        row.classList.add('is-failed');
        row.querySelector('[data-pf-hours-when]').textContent = Drupal.t('Hours unavailable');
      });
    });
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
      // A search that moved to another mode (Special Collections without its
      // filters is a Research one) keeps the address it ended at.
      if (response.redirected) {
        url = response.url;
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
    // The trail is outside the Index, and Special Collections mode adds a step.
    const crumbs = document.querySelectorAll('.pf-breadcrumb__nav');
    const nextCrumbs = page.querySelectorAll('.pf-breadcrumb__nav');
    if (crumbs.length === nextCrumbs.length) {
      crumbs.forEach((crumb, index) => crumb.replaceWith(nextCrumbs[index]));
    }
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
    const words = count && (count.querySelector('.pf-index__total-text') || count);
    Drupal.announce(words ? words.textContent : Drupal.t('Results updated.'));
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
      // A source's own narrower choices move with it, under the controls.
      const subs = [];
      for (let next = label.nextElementSibling; next && next.matches('.pf-index__option--sub'); next = next.nextElementSibling) {
        subs.push(next);
      }
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
      row.append(grip, ...subs);
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

    // All sources and the sources under it agree: choosing All clears them,
    // clearing them all chooses All. All can't be cleared by itself: choose a
    // source instead. Before the change below, which runs the search.
    root.addEventListener('click', (event) => {
      // The click has already cleared it: put it back, with no change.
      if (event.target.matches('[data-pf-index-all]') && !event.target.checked) {
        event.preventDefault();
      }
    });
    root.addEventListener('change', (event) => {
      const group = event.target.closest('.pf-index__group');
      const all = group && group.querySelector('[data-pf-index-all]');
      if (!all) {
        return;
      }
      const sources = [...group.querySelectorAll('input[type="checkbox"]:not([data-pf-index-all])')];
      if (event.target === all) {
        sources.forEach((input) => { input.checked = false; });
        all.checked = true;
      }
      else {
        all.checked = !sources.some((input) => input.checked);
      }
    }, true);

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

  // Holdings: the items under each library, in the order of the pressed
  // column. Ties keep the catalog's order.
  const collator = new Intl.Collator(document.documentElement.lang || undefined, { numeric: true, sensitivity: 'base' });
  const sortHoldings = (event) => {
    const button = event.target.closest('[data-pf-index-sort]');
    if (!button) {
      return;
    }
    const heading = button.closest('th');
    const table = heading.closest('table');
    const direction = heading.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending';
    table.querySelectorAll('th[aria-sort]').forEach((cell) => cell.removeAttribute('aria-sort'));
    heading.setAttribute('aria-sort', direction);
    const sign = direction === 'ascending' ? 1 : -1;
    const text = (row, selector) => row.querySelector(selector)?.textContent.trim() || '';
    const compare = button.dataset.pfIndexSort === 'status'
      ? (a, b) => (b.dataset.pfIndexAvailable - a.dataset.pfIndexAvailable) || collator.compare(text(a, '.pf-index-row__item-status'), text(b, '.pf-index-row__item-status'))
      : (a, b) => collator.compare(text(a, '.pf-index-row__item-number'), text(b, '.pf-index-row__item-number'))
        || collator.compare(text(a, '.pf-index-row__item-copy'), text(b, '.pf-index-row__item-copy'));
    table.querySelectorAll('tbody').forEach((body) => {
      let run = [];
      const flush = () => {
        if (run.length > 1) {
          const after = run[run.length - 1].nextElementSibling;
          run.sort((a, b) => sign * compare(a, b) || a.dataset.pfIndexOrder - b.dataset.pfIndexOrder)
            .forEach((row) => (after ? after.before(row) : body.append(row)));
        }
        run = [];
      };
      [...body.children].forEach((row) => {
        if (row.classList.contains('pf-index-row__copy-item')) {
          row.dataset.pfIndexOrder ??= String(run.length);
          run.push(row);
        }
        else {
          flush();
        }
      });
      flush();
    });
    Drupal.announce(Drupal.t('Sorted by @column, @direction.', {
      '@column': text(heading, '.pf-index-row__th-text'),
      '@direction': direction === 'ascending' ? Drupal.t('ascending') : Drupal.t('descending'),
    }));
  };

  // Holdings: the catalog's copy numbers, the first time a table opens.
  const loadCopies = async (event) => {
    const details = event.target;
    if (!(details instanceof HTMLDetailsElement) || !details.open || !details.dataset.pfIndexCopies || details.dataset.pfIndexCopiesAsked) {
      return;
    }
    const cells = details.querySelectorAll('[data-pf-index-copy-of]');
    if (!cells.length) {
      return;
    }
    details.dataset.pfIndexCopiesAsked = 'true';
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), HOLDINGS_TIMEOUT_MS);
    try {
      const response = await fetch(details.dataset.pfIndexCopies, { signal: controller.signal });
      const copies = response.ok ? await response.json() : {};
      cells.forEach((cell) => {
        const copy = copies[cell.dataset.pfIndexCopyOf];
        if (copy) {
          cell.textContent = String(copy);
        }
      });
    }
    catch (error) {
      // The column stays empty.
    }
    finally {
      window.clearTimeout(timer);
    }
  };

  Drupal.behaviors.palefireIndexSearch = {
    attach(context) {
      once('pf-index-states', 'html').forEach(pageStates);
      once('pf-index-sort', 'html').forEach(() => {
        document.addEventListener('click', sortHoldings);
        // toggle doesn't bubble, so it's caught on the way down.
        document.addEventListener('toggle', loadCopies, true);
      });
      once('pf-index', '[data-pf-index]', context).forEach(setUp);
      once('pf-index-sign-in', '[data-pf-index]', context).forEach(checkSignIn);
      once('pf-index-body', '[data-pf-index-body]', context).forEach(enhance);
      once('pf-index-sources', '[data-pf-index-body]', context).forEach(setUpSources);
      once('pf-index-late', '[data-pf-index-late]', context).forEach(load);
      once('pf-index-where', '.pf-index__toolbar', context).forEach(followSections);
      once('pf-index-answer', '[data-pf-index-answer]', context).forEach(fillAnswer);
      once('pf-index-holdings', '[data-pf-index-holdings-url]', context).forEach(loadHoldings);
      once('pf-index-thumb', '.pf-index-row__thumb img', context).forEach(checkCover);
      once('pf-index-more', '.pf-index-list__pager', context).forEach(setUpMore);
    },
    detach(context, settings, trigger) {
      // Only the requests for what is going away. Signed in, BigPipe detaches
      // each piece it fills in (toolbar, messages) while the sections load.
      if (trigger === 'unload') {
        controllers.forEach((element, controller) => {
          if (context === document || context.contains(element)) {
            controller.abort();
          }
        });
      }
    },
  };
}(Drupal, drupalSettings, once));
