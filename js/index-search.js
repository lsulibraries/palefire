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
 *   that stays fresher than results can. A late section's mark waits up to
 *   HOLDINGS_GRACE_MS for them, so quick ones arrive with the rows; slower ones
 *   fill a placeholder of the same height. If they fail, the row has no line.
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
 * - A paged list gets a Show more button, which adds the next page under the
 *   rows it has, over a rule that fills as more are shown. It's only ever on
 *   request: a list that grew by itself would keep the footer out of reach.
 *   The pager folds away behind Go to page, for jumping ahead.
 * - On a phone the chosen filters leave the held results bar for a line of
 *   their own under it, which scrolls away with the page.
 */
(function (Drupal, drupalSettings, once) {
  'use strict';

  const TIMEOUT_MS = 20000;
  const HOLDINGS_TIMEOUT_MS = 10000;
  // How long a late section's mark waits for its Catalog copies, at most.
  const HOLDINGS_GRACE_MS = 500;
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

  // One answer for a list's holdings, as a template: empty if it failed, and
  // the rows are still whole, with the record's link. owner: the element whose
  // leaving the page cancels it.
  const fetchHoldings = async (url, owner) => {
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), HOLDINGS_TIMEOUT_MS);
    controllers.set(controller, owner);
    const template = document.createElement('template');
    try {
      const response = await fetch(url, { signal: controller.signal });
      if (response.ok) {
        template.innerHTML = await response.text();
      }
    }
    catch (error) {
      // Left empty.
    }
    finally {
      window.clearTimeout(timer);
      controllers.delete(controller);
    }
    return template;
  };

  // An oral history's row shows its Catalog record's Summary, not its series'
  // description. Cut as the module cuts a row's summary; when it's whole, the
  // panel's About leaves it out rather than say it twice.
  const SUMMARY_LENGTH = 240;
  const fillSummary = (place, text) => {
    const summary = place.closest('.pf-index-row')?.querySelector('[data-pf-index-summary]');
    if (!summary || !text) {
      return;
    }
    if (text.length > SUMMARY_LENGTH) {
      const cut = text.slice(0, SUMMARY_LENGTH);
      summary.textContent = `${cut.slice(0, cut.lastIndexOf(' ')).replace(/[\s,;:.]+$/, '')}…`;
    }
    else {
      summary.textContent = text;
      const group = place.querySelector('[data-pf-index-detail="summary"]');
      const about = group?.closest('.pf-index-row__about');
      group?.remove();
      if (about && !about.querySelector('[data-pf-index-detail]')) {
        about.remove();
      }
    }
    summary.hidden = false;
  };

  // Fills each row's place from one answer, and removes the places left empty.
  // pending: an answer already asked for (load() starts it early).
  const loadHoldings = async (rows, pending = null) => {
    // Now: a list that's being added to hands these rows on while this waits.
    const places = [...rows.querySelectorAll('[data-pf-index-holdings]')];
    rows.setAttribute('aria-busy', 'true');
    const template = await (pending || fetchHoldings(rows.dataset.pfIndexHoldingsUrl, rows));
    rows.removeAttribute('aria-busy');
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
        fillSummary(place, found.dataset.pfIndexSummary);
      }
      else {
        place.remove();
      }
    });
  };

  // An outside source's count in the Source filter, from its section's heading
  // once that arrives ("13 results" gives 13, "300+ results" 300+). Nothing
  // known, nothing shown: never a 0 that only means it hasn't answered.
  const showSourceCount = (holder, text) => {
    const key = holder.closest('[data-pf-index-source]')?.dataset.pfIndexSource;
    const scope = holder.closest('[data-pf-index]') || document;
    const label = key && [...scope.querySelectorAll('[data-pf-index-form] input[name="source[]"]')].find((input) => input.value === key)?.closest('.pf-index__option');
    if (!label) {
      return;
    }
    const number = (text || '').match(/^[\d.,\s]*\d\+?/);
    let count = label.querySelector(':scope > .pf-index__count');
    if (!number) {
      count?.remove();
      return;
    }
    if (!count) {
      count = document.createElement('span');
      count.className = 'pf-index__count is-late';
      label.querySelector(':scope > .pf-index__option-label')?.after(count);
    }
    count.textContent = number[0].trim();
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
        showSourceCount(holder, '');
        return;
      }
      const count = holder.closest('.pf-index-section')?.querySelector('[data-pf-index-section-count]');
      if (count && list && list.dataset.pfIndexCount) {
        count.textContent = list.dataset.pfIndexCount;
      }
      showSourceCount(holder, list && list.dataset.pfIndexCount);
      // Catalog rows: their copies are asked for now, and the mark stays a
      // moment for them, so a quick answer arrives with the rows instead of
      // after them. A slow one never holds the rows up: they show with the
      // line's placeholder (index-search.scss) and it fills in when it comes.
      const holdingsRows = template.content.querySelector('[data-pf-index-holdings-url]');
      const pending = holdingsRows && fetchHoldings(holdingsRows.dataset.pfIndexHoldingsUrl, holder);
      if (pending) {
        await Promise.race([pending, new Promise((resolve) => { window.setTimeout(resolve, HOLDINGS_GRACE_MS); })]);
      }
      holder.replaceChildren(template.content);
      holder.classList.add('is-loaded');
      once('pf-index-holdings', '[data-pf-index-holdings-url]', holder).forEach((rows) => loadHoldings(rows, rows === holdingsRows ? pending : null));
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
    const size = rows.children.length;
    const first = pageNumber * size + 1;
    const total = Number(list.dataset.pfIndexTotal);
    const last = () => first + rows.children.length - 1;

    // A rule that fills as more are shown, then the count, Go to page and the
    // button on one line (on a phone the button takes a line of its own).
    const box = document.createElement('div');
    box.className = 'pf-index-list__grow';
    const progress = document.createElement('div');
    progress.className = 'pf-index-list__progress';
    progress.setAttribute('aria-hidden', 'true');
    const line = document.createElement('div');
    line.className = 'pf-index-list__grow-row';
    const count = document.createElement('p');
    count.className = 'pf-index-list__grow-count';
    const jump = document.createElement('button');
    jump.type = 'button';
    jump.className = 'pf-index-list__jump';
    jump.textContent = Drupal.t('Go to page');
    jump.setAttribute('aria-expanded', 'false');
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'pf-index-list__grow-button';
    const label = document.createElement('span');
    button.append(label);
    button.insertAdjacentHTML('beforeend', icon('M12 5v14M6 13l6 6 6-6'));
    line.append(count, jump, button);
    box.append(progress, line);
    rows.after(box);

    // The pager folds away behind Go to page. Without this file it shows.
    const pagerId = 'pf-index-pager-' + Math.random().toString(36).slice(2, 8);
    const fold = (element) => {
      element.id = pagerId;
      element.hidden = jump.getAttribute('aria-expanded') !== 'true';
    };
    jump.setAttribute('aria-controls', pagerId);
    fold(pager);
    jump.addEventListener('click', () => {
      const open = jump.getAttribute('aria-expanded') !== 'true';
      jump.setAttribute('aria-expanded', String(open));
      const current = list.querySelector(':scope > .pf-index-list__pager');
      if (current) {
        fold(current);
        // Into the pager, on its first link that shows (First and Last are
        // hidden on a small phone).
        if (open) {
          [...current.querySelectorAll('a')].find((link) => link.getClientRects().length)?.focus({ preventScroll: true });
        }
      }
    });

    const shown = () => (first === 1
      ? Drupal.t('@shown of @total', { '@shown': last().toLocaleString(), '@total': total.toLocaleString() })
      : Drupal.t('@first to @last of @total', { '@first': first.toLocaleString(), '@last': last().toLocaleString(), '@total': total.toLocaleString() }));
    const rest = () => {
      const more = Math.min(size, total - last());
      label.textContent = more > 0 ? Drupal.t('Show @count more', { '@count': more.toLocaleString() }) : Drupal.t('Show more');
      const numbers = document.createElement('span');
      numbers.className = 'pf-index-list__grow-numbers';
      numbers.textContent = shown();
      const words = document.createElement('span');
      words.className = 'pf-index-list__grow-words';
      words.textContent = Drupal.t('results shown');
      count.replaceChildren(numbers, ' ', words);
      box.style.setProperty('--pf-index-shown', Math.min(100, (last() / total) * 100).toFixed(2) + '%');
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
      label.textContent = Drupal.t('Loading…');
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
      const nextPager = more.querySelector(':scope > .pf-index-list__pager');
      fold(nextPager);
      list.querySelector(':scope > .pf-index-list__pager').replaceWith(nextPager);
      // The note that this is as far as the service reads, on its last page.
      more.querySelectorAll(':scope > .pf-index-list__rows ~ .pf-index-list__status').forEach((note) => box.before(note));

      const message = Drupal.t('@count more results added. @shown.', { '@count': arriving.length, '@shown': shown() });
      rest();
      // All of them: the rule full, the count and Go to page stay.
      if (!list.querySelector('.pf-index-list__pager a[rel="next"]')) {
        button.remove();
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

  // The search field's clear button, hidden while the field is empty.
  const syncErase = (root) => {
    const input = root.querySelector('#pf-index-q');
    const erase = root.querySelector('[data-pf-index-erase]');
    if (input && erase) {
      erase.hidden = !input.value;
    }
  };

  // From 75rem the held results bar moves up into the breadcrumb bar's row,
  // so there is one band of glass under the header, not two (styles:
  // index-search.scss, "Docked"). Classes on <html>, as the breadcrumb bar
  // is outside the Index: pf-index-dock while the window is wide enough,
  // pf-index-docked while the bar is held. Before it docks, its height is
  // kept (--pf-index-toolbar-h), so the results under it don't move when it
  // slims to the row; and the room left of its words is measured
  // (--pf-index-crumbs-room), so the breadcrumb gives way (ellipsis) before
  // it reaches them.
  // pf-index-dock-moved once the bar has docked or undocked, so the words'
  // undocking motion plays only after a move, not on arrival.
  const DOCK_CLASSES = ['pf-index-dock', 'pf-index-docked', 'pf-index-dock-moved'];
  const still = () => reducedMotion.matches || document.documentElement.classList.contains('pf-a11y-motion');
  const undock = () => {
    document.documentElement.classList.remove(...DOCK_CLASSES);
  };

  const dockToolbar = (toolbar) => {
    const root = document.documentElement;
    const wide = window.matchMedia('(min-width: 75rem) and (min-height: 30rem)');
    const total = toolbar.querySelector('.pf-index__total');
    if (!document.querySelector('.pf-page-bar .breadcrumb') || !total) {
      return;
    }
    let frame = 0;
    let measured = false;
    const update = () => {
      frame = 0;
      if (!toolbar.isConnected) {
        window.removeEventListener('scroll', schedule);
        window.removeEventListener('resize', resize);
        undock();
        return;
      }
      if (!wide.matches) {
        undock();
        return;
      }
      root.classList.add('pf-index-dock');
      const docked = root.classList.contains('pf-index-docked');
      if (!docked) {
        root.style.setProperty('--pf-index-toolbar-h', `${toolbar.offsetHeight}px`);
      }
      const stick = parseFloat(window.getComputedStyle(toolbar).top) || 0;
      const held = toolbar.getBoundingClientRect().top <= stick + 0.5;
      if (held && (!docked || !measured)) {
        const crumbs = document.querySelector('.pf-page-bar .breadcrumb');
        // 1.5rem between the crumbs and the bar's words.
        const gap = 1.5 * parseFloat(window.getComputedStyle(root).fontSize);
        const room = total.getBoundingClientRect().left - crumbs.getBoundingClientRect().left - gap;
        root.style.setProperty('--pf-index-crumbs-room', `${Math.max(0, Math.floor(room))}px`);
        measured = true;
      }
      if (held !== docked) {
        root.classList.add('pf-index-dock-moved');
      }
      root.classList.toggle('pf-index-docked', held);
    };
    const schedule = () => {
      if (!frame) {
        frame = window.requestAnimationFrame(update);
      }
    };
    const resize = () => {
      measured = false;
      schedule();
    };
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', resize);
    schedule();
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

    // The Source filter marks the source being read, as a table of contents
    // marks its section, at the same point the bar above names it: the first
    // source until a heading goes under the bar, and the last at the page's
    // foot. Only a look: the row's checkbox still says what's filtered, and
    // screen readers have the headings.
    const sources = [...scope.querySelectorAll('[data-pf-index-form] input[name="source[]"]')];
    let marked = null;
    const mark = (passed) => {
      const sections = [...scope.querySelectorAll('.pf-index-section[data-pf-index-source]')].filter((section) => !section.hidden);
      const doc = document.documentElement;
      const atFoot = window.innerHeight + window.scrollY >= doc.scrollHeight - 2;
      const reading = atFoot ? sections[sections.length - 1] : passed || sections[0];
      const input = sections.length > 1 && reading ? sources.find((source) => source.value === reading.dataset.pfIndexSource) : null;
      const option = input && input.closest('.pf-index__option');
      const row = option && (option.closest('.pf-index__source') || option);
      if (row !== marked) {
        marked?.classList.remove('is-in-view');
        row?.classList.add('is-in-view');
        marked = row;
      }
    };

    // A change to the name before the words moves them; they ease over from
    // where they were (index-search.scss, .is-shifting), measured either side.
    const LEAVE_MS = 200;
    let leaving = 0;
    const shift = (change) => {
      const before = text.getBoundingClientRect().left;
      change();
      const by = before - text.getBoundingClientRect().left;
      if (Math.abs(by) < 1 || still()) {
        return;
      }
      text.style.setProperty('--pf-index-shift', `${Math.round(by)}px`);
      text.classList.remove('is-shifting');
      void text.offsetWidth;
      text.classList.add('is-shifting');
    };
    text.addEventListener('animationend', (event) => {
      if (event.target === text) {
        text.classList.remove('is-shifting');
      }
    });

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
      if (sources.length) {
        mark(next);
      }
      if (!next) {
        // Above the first heading again: the name fades, then makes way.
        if (current) {
          current = null;
          where.classList.add('is-leaving');
          window.clearTimeout(leaving);
          leaving = window.setTimeout(() => {
            shift(() => {
              where.hidden = true;
              where.classList.remove('is-leaving');
            });
          }, still() ? 0 : LEAVE_MS);
        }
        return;
      }
      window.clearTimeout(leaving);
      const head = next.querySelector(':scope > .pf-index-section__head');
      const label = head.querySelector('.pf-index-section__title').textContent.trim();
      // A late section's count arrives after its heading.
      const number = head.querySelector('[data-pf-index-section-count]');
      const counted = number ? number.textContent.trim().replace(/\s*results?$/i, '') : '';
      if (next === current && !where.hidden && name.textContent === label && count.textContent === counted) {
        return;
      }
      const fresh = next !== current;
      shift(() => {
        name.textContent = label;
        count.textContent = counted;
        where.classList.remove('is-leaving');
        where.hidden = false;
      });
      if (fresh) {
        current = next;
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
      date.textContent = Drupal.t('Today, @date', { '@date': new Date().toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }).replace(',', '') });
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

  // Analytics: a "pf_search" event in Google Tag Manager's dataLayer for each
  // new search: its words (search_term) and mode (search_mode). Only when GTM
  // is on the page. A filter, sort, page or mode tab on the same words is not
  // a new search, nor is Back, Forward or a reload: the history entry keeps
  // pfSearchSent. Email addresses are left out (Google's terms forbid personal
  // data), and the words are cut to GA4's 100-character limit.
  const EMAIL = /[^\s@]+@[^\s@]+\.[^\s@]+/g;
  const sendSearch = (url) => {
    if (!Array.isArray(window.dataLayer)) {
      return;
    }
    const params = new URL(url, window.location.href).searchParams;
    const term = (params.get('q') || '').replace(EMAIL, '[email]').trim().slice(0, 100);
    if (term) {
      window.dataLayer.push({
        event: 'pf_search',
        search_term: term,
        search_mode: params.get('mode') || 'all',
      });
    }
  };

  // A search reached by loading its address (the menu's or a landing's search
  // box, a shared link), once per history entry.
  const sendFirstSearch = () => {
    const state = window.history.state || {};
    if (!state.pfSearchSent) {
      sendSearch(window.location.href);
      window.history.replaceState({ ...state, pfSearchSent: true }, '');
    }
  };

  // Moves to another search in place. Anything unexpected falls back to loading
  // the address normally, which always works. "search" marks a new search
  // (the search box), for sendSearch().
  const visit = async (root, url, { focus = null, history = true, search = false } = {}) => {
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
      window.history.pushState({ pfIndex: true, pfSearchSent: true }, '', url);
    }
    if (search && history) {
      sendSearch(url);
    }
    document.title = page.title;
    // The analytics section label follows the mode (Special Collections or
    // not, PalefireHooks::preprocessHtml()), so the next search carries it.
    const section = page.documentElement.getAttribute('data-pf-section');
    if (section) {
      document.documentElement.setAttribute('data-pf-section', section);
    }
    Drupal.detachBehaviors(main, drupalSettings, 'unload');
    swap(root, page, '.pf-index__form');
    syncErase(root);
    swap(root, page, '.pf-index__modes');
    // The trail is outside the Index, and Special Collections mode adds a step.
    const crumbs = document.querySelectorAll('.pf-breadcrumb__nav');
    const nextCrumbs = page.querySelectorAll('.pf-breadcrumb__nav');
    if (crumbs.length === nextCrumbs.length) {
      crumbs.forEach((crumb, index) => crumb.replaceWith(nextCrumbs[index]));
    }
    // So does the footer: Special Collections mode has Hill's, the others
    // the Main Library's. Swapped only when it changes, so its hours aren't
    // fetched again on every search.
    const footer = document.getElementById('pf-footer');
    const nextFooter = page.getElementById('pf-footer');
    if (footer && nextFooter && footer.classList.contains('pf-footer--special') !== nextFooter.classList.contains('pf-footer--special')) {
      Drupal.detachBehaviors(footer, drupalSettings, 'unload');
      footer.replaceWith(nextFooter);
      Drupal.attachBehaviors(nextFooter, drupalSettings);
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
    // The search field's clear button (×): shown while the field has words.
    // It empties the field and puts the cursor back in it; the results stay
    // until the next search.
    syncErase(root);
    root.addEventListener('input', (event) => {
      if (event.target.matches('#pf-index-q')) {
        syncErase(root);
      }
    });
    root.addEventListener('click', (event) => {
      const erase = event.target instanceof Element ? event.target.closest('[data-pf-index-erase]') : null;
      const input = root.querySelector('#pf-index-q');
      if (erase && input) {
        input.value = '';
        syncErase(root);
        input.focus();
      }
    });

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
      const isFilters = form.matches('[data-pf-index-form]');
      visit(root, addressOf(form), { focus: isFilters ? '[data-pf-index-open]' : '#pf-index-q', search: !isFilters });
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
      const isFilter = wide.matches && (control.matches('[data-pf-index-form] :is(input[type="checkbox"], input[type="radio"], select)') || isYear);
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

  // On a phone the held bar keeps only the count and the sort, one line, so it
  // costs little of the screen. The chosen filters take a line of their own
  // under it, which scrolls away with the page; the Filters button above says
  // how many are chosen. From 36rem they go back into the bar.
  const phone = window.matchMedia('(max-width: 35.98rem)');
  const placeChips = (toolbar) => {
    const chips = toolbar.querySelector(':scope > .pf-index__chips');
    if (!chips) {
      return;
    }
    const place = () => {
      if (!toolbar.isConnected) {
        phone.removeEventListener('change', place);
        return;
      }
      if (phone.matches) {
        toolbar.after(chips);
      }
      else {
        const sort = toolbar.querySelector(':scope > .pf-index__sort');
        if (sort) {
          sort.before(chips);
        }
        else {
          toolbar.append(chips);
        }
      }
      chips.classList.toggle('is-apart', phone.matches);
    };
    place();
    phone.addEventListener('change', place);
  };

  // About this item, in a Catalog record's panel: a text longer than its
  // four lines gets Read more, measured when the panel opens, as the page's
  // width decides what is long. One already given a button keeps it.
  let clampCount = 0;
  const clampDetails = (event) => {
    const details = event.target;
    if (!(details instanceof HTMLDetailsElement) || !details.open) {
      return;
    }
    details.querySelectorAll('[data-pf-index-clamp]:not([id])').forEach((text) => {
      if (text.scrollHeight <= text.clientHeight + 1) {
        return;
      }
      clampCount += 1;
      text.id = `pf-index-clamp-${clampCount}`;
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'pf-index-row__about-more';
      button.setAttribute('aria-expanded', 'false');
      button.setAttribute('aria-controls', text.id);
      button.dataset.pfIndexUnclamp = '';
      button.textContent = Drupal.t('Read more');
      text.after(button);
    });
  };

  // A copies panel taller than the screen gets a second Hide at its foot.
  // Closing there brings its toggle back into view and gives it focus, so
  // the visitor stays where the row is.
  const offerClose = (event) => {
    const details = event.target;
    if (!(details instanceof HTMLDetailsElement) || !details.open || !details.dataset.pfIndexCopies) {
      return;
    }
    const close = details.querySelector('[data-pf-index-close-copies]');
    const panel = details.querySelector('.pf-index-row__copies-table');
    if (close && panel) {
      close.hidden = panel.scrollHeight <= window.innerHeight;
    }
  };

  // What are these? opens what each source searches inside the filter, above
  // its options, and closes it again.
  const toggleAbout = (event) => {
    const button = event.target.closest('[data-pf-index-about]');
    const panel = button && document.getElementById(button.getAttribute('aria-controls'));
    if (!panel) {
      return;
    }
    const open = button.getAttribute('aria-expanded') !== 'true';
    button.setAttribute('aria-expanded', String(open));
    panel.hidden = !open;
  };

  const closeCopies = (event) => {
    const button = event.target.closest('[data-pf-index-close-copies]');
    const details = button && button.closest('details');
    if (!details) {
      return;
    }
    details.open = false;
    const summary = details.querySelector(':scope > summary');
    summary.focus({ preventScroll: true });
    if (summary.getBoundingClientRect().top < 0) {
      summary.scrollIntoView({ behavior: reducedMotion.matches ? 'auto' : 'smooth', block: 'center' });
    }
  };

  const unclamp = (event) => {
    const button = event.target.closest('[data-pf-index-unclamp]');
    const text = button && document.getElementById(button.getAttribute('aria-controls'));
    if (!text) {
      return;
    }
    const whole = button.getAttribute('aria-expanded') !== 'true';
    text.classList.toggle('is-whole', whole);
    button.setAttribute('aria-expanded', String(whole));
    button.textContent = whole ? Drupal.t('Show less') : Drupal.t('Read more');
  };

  Drupal.behaviors.palefireIndexSearch = {
    attach(context) {
      once('pf-index-states', 'html').forEach(pageStates);
      once('pf-index-sort', 'html').forEach(() => {
        document.addEventListener('click', sortHoldings);
        // toggle doesn't bubble, so it's caught on the way down.
        document.addEventListener('toggle', loadCopies, true);
        document.addEventListener('toggle', clampDetails, true);
        document.addEventListener('click', unclamp);
        document.addEventListener('toggle', offerClose, true);
        document.addEventListener('click', closeCopies);
        document.addEventListener('click', toggleAbout);
      });
      once('pf-index', '[data-pf-index]', context).forEach(setUp);
      once('pf-index-sent', '[data-pf-index]', context).forEach(sendFirstSearch);
      once('pf-index-sign-in', '[data-pf-index]', context).forEach(checkSignIn);
      once('pf-index-body', '[data-pf-index-body]', context).forEach(enhance);
      once('pf-index-sources', '[data-pf-index-body]', context).forEach(setUpSources);
      once('pf-index-late', '[data-pf-index-late]', context).forEach(load);
      once('pf-index-where', '.pf-index__toolbar', context).forEach(followSections);
      once('pf-index-dock', '.pf-index__toolbar', context).forEach(dockToolbar);
      once('pf-index-answer', '[data-pf-index-answer]', context).forEach(fillAnswer);
      once('pf-index-holdings', '[data-pf-index-holdings-url]', context).forEach((rows) => loadHoldings(rows));
      once('pf-index-thumb', '.pf-index-row__thumb img', context).forEach(checkCover);
      once('pf-index-more', '.pf-index-list__pager', context).forEach(setUpMore);
      once('pf-index-chips', '.pf-index__toolbar', context).forEach(placeChips);
    },
    detach(context, settings, trigger) {
      // Only the requests for what is going away. Signed in, BigPipe detaches
      // each piece it fills in (toolbar, messages) while the sections load.
      if (trigger === 'unload') {
        // Leaving the Index (swup): the breadcrumb bar is the next page's.
        if (context === document || context.querySelector?.('.pf-index__toolbar')) {
          undock();
        }
        controllers.forEach((element, controller) => {
          if (context === document || context.contains(element)) {
            controller.abort();
          }
        });
      }
    },
  };
}(Drupal, drupalSettings, once));
