/**
 * @file
 * The mobile dock (templates/includes/dock.html.twig): below md, on Special
 * Collections pages, a frosted sheet at the bottom of the screen with the
 * library's hours, the page's or section's action, and Contents.
 *
 * - The dock lives outside #swup and survives Swup visits. Each page brings
 *   its settings in <script data-pf-dock-page> (PalefireHooks::dock()):
 *   { lid, action, sections: { <TOC hash>: action } }. The behavior re-reads
 *   them after each visit; a page without them turns the dock off.
 * - Hours come from Drupal.palefireHours (js/visit-hours.js), one LibCal
 *   request per location, re-checked every minute.
 * - The status and Contents buttons are disclosures: each grows the dock
 *   upward into a panel (the week, the page's TOC). One open at a time.
 * - Contents shows the section's printed number ("03", .pf-index-rule__num)
 *   in a ring that fills as the reader moves through <main>.
 * - Actions follow the section in view (palefire:sectionchange from
 *   js/modular-toc.js) after scrolling settles, and fold away while the page
 *   shows a link to the same place.
 * - The grabber (or a swipe down) hides the dock until the tab brings it
 *   back, for the rest of the visit (sessionStorage).
 */
(function (Drupal, once) {
  'use strict';

  const NAMES = {
    236: [Drupal.t('Hill Memorial Library'), Drupal.t('Hill')],
    241: [Drupal.t('Main Library'), Drupal.t('Main')],
  };
  const MD = window.matchMedia('(min-width: 768px)');
  const SETTLE = 150;
  const TAP = 10;
  const SWIPE = 40;
  const STORE = 'pf-dock-hidden';
  const FIELDS = 'input:not([type=checkbox]):not([type=radio]):not([type=button]):not([type=submit]):not([type=reset]), textarea, select';

  const root = document.documentElement;
  const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches
    || root.classList.contains('pf-a11y-motion');
  const pad = (n) => String(Math.min(n, 99)).padStart(2, '0');
  const minutes = (d) => d.getHours() * 60 + d.getMinutes();
  const addDays = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
  const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  const store = (value) => {
    try {
      if (value) {
        window.sessionStorage.setItem(STORE, '1');
      }
      else {
        window.sessionStorage.removeItem(STORE);
      }
    }
    catch (e) {}
  };
  const stored = () => {
    try {
      return window.sessionStorage.getItem(STORE) === '1';
    }
    catch (e) {
      return false;
    }
  };
  // Origin, path, query and hash: how two links are "the same place".
  const place = (href) => {
    try {
      const url = new URL(href, window.location.href);
      return url.origin + url.pathname + url.search + url.hash;
    }
    catch (e) {
      return href;
    }
  };

  function initDock(dock) {
    const H = Drupal.palefireHours;
    const tab = document.querySelector('[data-pf-dock-show]');
    const grab = dock.querySelector('[data-pf-dock-hide]');
    const row = dock.querySelector('.pf-dock__row');
    const status = dock.querySelector('[data-pf-dock-status]');
    const stateEl = dock.querySelector('[data-pf-dock-state]');
    const nextEl = dock.querySelector('[data-pf-dock-next]');
    const action = dock.querySelector('[data-pf-dock-action]');
    const actionIcon = dock.querySelector('[data-pf-dock-action-icon]');
    const actionLabel = dock.querySelector('[data-pf-dock-action-label]');
    const tocButton = dock.querySelector('[data-pf-dock-toc-button]');
    const ringFill = dock.querySelector('.pf-dock__ring-fill');
    const ringNum = dock.querySelector('[data-pf-dock-ring-num]');
    const tocName = dock.querySelector('[data-pf-dock-toc-name]');
    const tocList = dock.querySelector('[data-pf-dock-toc]');
    const icons = dock.querySelector('template[data-pf-dock-icons]');
    const panels = {
      hours: dock.querySelector('[data-pf-dock-panel="hours"]'),
      contents: dock.querySelector('[data-pf-dock-panel="contents"]'),
    };
    const openers = { hours: status, contents: tocButton };
    const ask = dock.querySelector('[data-pf-dock-ask]');
    const askPanel = document.getElementById('pf-panel-ask');
    // A panel to open once the menu has closed, and whether closing the Ask
    // drawer comes back to the week.
    let pending = null;
    let askBack = false;

    let config = null;
    let configText = '';
    let lid = '';
    let specs = null;
    let failed = false;
    let open = null;
    let returnTo = null;
    let hidden = false;
    let covered = false;
    let aside = false;
    let current = null;
    let settleTimer = 0;
    let dupTimer = 0;
    let observer = null;
    const visible = new Set();
    let frame = 0;

    /* ---------------- On and off ---------------- */

    function syncInert() {
      dock.inert = !config || hidden || covered || aside;
      if (tab) {
        tab.inert = covered;
      }
    }

    function readConfig() {
      const el = document.querySelector('#swup [data-pf-dock-page]');
      const text = el && root.hasAttribute('data-pf-dock') ? el.textContent : '';
      if (text === configText) {
        return;
      }
      configText = text;
      try {
        config = text ? JSON.parse(text) : null;
      }
      catch (e) {
        config = null;
      }
      root.classList.toggle('pf-dock-on', !!config);
      syncInert();
      if (!config) {
        if (tab) {
          tab.hidden = true;
        }
        return;
      }
      if (tab) {
        tab.hidden = !hidden;
      }
      if (String(config.lid) !== lid) {
        lid = String(config.lid);
        specs = null;
        failed = false;
        renderHours();
        H.load(lid).then((data) => {
          specs = (data && data.openingHoursSpecification) || [];
          renderHours();
        }, () => {
          failed = true;
          renderHours();
        });
      }
      current = null;
      readToc();
      queueFrame();
      settle();
    }

    /* ---------------- Hours ---------------- */

    function names() {
      return NAMES[lid] || [Drupal.t('Library'), Drupal.t('Library')];
    }

    function dayWindow(day) {
      return specs ? H.window(H.specFor(specs, day), day) : null;
    }

    // Monday to Sunday, as visit-hours.js shows the week (the next one on
    // a Sunday).
    function weekDays(now) {
      const monday = addDays(now, now.getDay() === 0 ? 1 : 1 - now.getDay());
      return Array.from({ length: 7 }, (v, i) => addDays(monday, i));
    }

    // "Open late": today closes later than the week usually does (the most
    // common closing time among its open days).
    function openLate(now, today) {
      const counts = {};
      weekDays(now).forEach((day) => {
        const w = dayWindow(day);
        if (w) {
          const m = minutes(w.close) || 1440;
          counts[m] = (counts[m] || 0) + 1;
        }
      });
      const usual = Object.keys(counts).sort((a, b) => counts[b] - counts[a])[0];
      return usual !== undefined && (minutes(today.close) || 1440) > Number(usual);
    }

    // { state, label, next }: the row's two lines, shorter than the
    // hours panels' ("Open" over "until 5 PM").
    function describe(now) {
      const today = dayWindow(now);
      const s = H.status(specs, now);
      if (today && now >= today.open && now < today.close) {
        if (s.state === 'soon') {
          return { state: 'soon', label: Drupal.t('Closes soon'), next: Drupal.t('at @time', { '@time': H.formatTime(today.close) }) };
        }
        return {
          state: 'open',
          label: openLate(now, today) ? Drupal.t('Open late') : Drupal.t('Open'),
          next: Drupal.t('until @time', { '@time': H.formatTime(today.close) }),
        };
      }
      if (today && now < today.open) {
        return {
          state: s.state,
          label: s.state === 'soon' ? Drupal.t('Opens soon') : Drupal.t('Closed'),
          next: s.state === 'soon'
            ? Drupal.t('at @time', { '@time': H.formatTime(today.open) })
            : Drupal.t('opens today @time', { '@time': H.formatTime(today.open) }),
        };
      }
      for (let i = 1; i <= 7; i++) {
        const day = addDays(now, i);
        const w = dayWindow(day);
        if (w) {
          const when = i === 1 ? Drupal.t('tomorrow') : day.toLocaleDateString('en-US', { weekday: 'short' });
          return { state: 'closed', label: Drupal.t('Closed'), next: Drupal.t('opens @when @time', { '@when': when, '@time': H.formatTime(w.open) }) };
        }
      }
      return { state: 'closed', label: Drupal.t('Closed'), next: '' };
    }

    function setBar(now) {
      const today = specs ? dayWindow(now) : null;
      const share = today ? Math.max(0, Math.min(1, (now - today.open) / (today.close - today.open))) : 0;
      dock.querySelectorAll('.pf-dock__bar').forEach((bar) => bar.style.setProperty('--pf-dock-bar', share));
    }

    // Line 2: the library's full name when it fits on one line, else its
    // short name (wrapping if even that doesn't fit). Never truncated.
    function fitName() {
      const name = nextEl.querySelector('.pf-dock__name');
      if (!name) {
        return;
      }
      const [full, short] = names();
      name.textContent = full;
      nextEl.classList.add('is-measuring');
      const over = nextEl.scrollWidth > nextEl.clientWidth + 1;
      nextEl.classList.remove('is-measuring');
      if (over) {
        name.textContent = short;
      }
    }

    function renderHours() {
      const now = new Date();
      const [full] = names();
      dock.querySelector('[data-pf-dock-fullname]').textContent = full;
      const d = specs ? describe(now) : { state: failed ? 'unknown' : 'loading', label: Drupal.t('Hours'), next: '' };
      dock.dataset.hoursState = d.state;
      stateEl.textContent = d.label;
      const name = document.createElement('span');
      name.className = 'pf-dock__name';
      nextEl.replaceChildren(name);
      if (d.next) {
        const sep = document.createElement('span');
        sep.className = 'pf-dock__sep';
        sep.setAttribute('aria-hidden', 'true');
        sep.textContent = ' · ';
        nextEl.append(sep, d.next);
      }
      fitName();
      status.setAttribute('aria-label', [d.label, full, d.next].filter(Boolean).join(', ') + '. ' + Drupal.t("Show this week's hours."));
      setBar(now);

      // The panel's own lines: the state large, then the hours panels'
      // sentence ('Opens tomorrow 9 AM', visit-hours.js).
      dock.querySelector('[data-pf-dock-now]').textContent = d.label;
      dock.querySelector('[data-pf-dock-now-detail]').textContent = specs
        ? H.status(specs, now).detail
        : (failed ? Drupal.t('Hours are unavailable right now.') : Drupal.t('Loading hours…'));
      renderWeek(now);
    }

    function renderWeek(now) {
      const week = dock.querySelector('[data-pf-dock-week]');
      const closure = dock.querySelector('[data-pf-dock-closure]');
      if (!specs) {
        week.replaceChildren();
        closure.hidden = true;
        return;
      }
      const today = ymd(now);
      week.replaceChildren(...weekDays(now).map((day) => {
        const key = ymd(day);
        const spec = H.specFor(specs, day);
        const w = H.window(spec, day);
        const li = document.createElement('li');
        li.className = 'pf-dock__day' + (key === today ? ' is-today' : '') + (key < today ? ' is-past' : '') + (w ? '' : ' is-closed');
        if (key === today) {
          li.setAttribute('aria-current', 'date');
        }
        const name = document.createElement('span');
        name.className = 'pf-dock__day-name';
        name.textContent = key === today ? Drupal.t('Today') : day.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
        const time = document.createElement('span');
        time.className = 'pf-dock__day-hours';
        time.textContent = w ? H.range(w) : (spec ? Drupal.t('Closed') : Drupal.t('Not posted'));
        li.append(name, time);
        return li;
      }));
      // A weekday closure in the next 7 days (a holiday, a break).
      const closed = [];
      for (let i = 1; i <= 7; i++) {
        const day = addDays(now, i);
        const spec = H.specFor(specs, day);
        if (day.getDay() > 0 && day.getDay() < 6 && spec && !H.window(spec, day)) {
          closed.push(day.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' }));
        }
      }
      closure.hidden = !closed.length;
      closure.textContent = closed.length ? Drupal.t('Closed @days', { '@days': closed.join('; ') }) : '';
    }

    /* ---------------- Contents ---------------- */

    let entries = [];
    let total = 0;
    let nums = [];

    function tocNav() {
      return document.querySelector('#pf-modular-sidebar .pf-toc');
    }

    // The TOC's top-level entries, each with its first printed number
    // (between its target and the next entry's), if the page prints any.
    function readToc() {
      const nav = tocNav();
      const links = nav ? Array.from(nav.querySelectorAll('.pf-toc__link[href^="#"]')).filter((a) => !a.closest('li').hidden) : [];
      nums = Array.from(document.querySelectorAll('#swup .pf-index-rule__num'));
      entries = links.map((link) => ({ link, target: document.getElementById(decodeURIComponent(link.hash.slice(1))) })).filter((e) => e.target);
      entries.forEach((entry, i) => {
        const next = entries[i + 1] && entries[i + 1].target;
        const num = nums.find((n) => {
          const after = entry.target.compareDocumentPosition(n) & (Node.DOCUMENT_POSITION_FOLLOWING | Node.DOCUMENT_POSITION_CONTAINED_BY);
          const before = !next || (next.compareDocumentPosition(n) & Node.DOCUMENT_POSITION_PRECEDING && !next.contains(n));
          return after && before;
        });
        entry.num = nums.length ? (num ? num.textContent.trim() : '') : pad(i + 1);
      });
      const printed = nums.map((n) => parseInt(n.textContent, 10)).filter((n) => !isNaN(n));
      total = printed.length ? Math.max(...printed) : entries.length;
      tocButton.hidden = entries.length < 2;
      dock.classList.toggle('has-toc', !tocButton.hidden);
      // The page's name as the tab shows it (the h1 can carry hidden
      // context, such as the landing's eyebrow).
      dock.querySelector('[data-pf-dock-page-title]').textContent = document.title.split(' | ')[0];
    }

    function activeLink() {
      const nav = tocNav();
      return nav && nav.querySelector('.pf-toc__link[aria-current]');
    }

    function headerLine() {
      const head = parseFloat(getComputedStyle(root).getPropertyValue('--pf-header-h')) || 0;
      return head + (window.innerHeight - head) * 0.75;
    }

    function updateRing() {
      frame = 0;
      if (!config || tocButton.hidden) {
        return;
      }
      const main = document.querySelector('#swup main') || document.querySelector('main');
      let share = 0;
      if (main) {
        const box = main.getBoundingClientRect();
        const run = box.height - window.innerHeight;
        share = run > 0 ? Math.max(0, Math.min(1, -box.top / run)) : 1;
      }
      ringFill.style.strokeDasharray = `${Math.round(share * 100)} 100`;
      // No round-capped dot at 0%.
      ringFill.style.opacity = share < 0.01 ? '0' : '';

      let n = '';
      if (nums.length) {
        const line = headerLine();
        const passed = nums.filter((el) => el.getBoundingClientRect().top < line && el.getClientRects().length);
        n = (passed.length ? passed[passed.length - 1] : nums[0]).textContent.trim();
      }
      else {
        const link = activeLink();
        const i = entries.findIndex((e) => e.link === link);
        n = String((i < 0 ? 0 : i) + 1);
      }
      // The ring drops the leading zero the page's numbers and the list keep.
      n = n.replace(/^0+(?=\d)/, '');
      if (ringNum.textContent !== n) {
        ringNum.textContent = n;
        tocName.textContent = Drupal.t('Contents, section @n of @total', { '@n': parseInt(n, 10) || 1, '@total': total });
      }
    }

    function queueFrame() {
      if (!frame) {
        frame = window.requestAnimationFrame(updateRing);
      }
    }

    function buildList() {
      readToc();
      const now = activeLink();
      // Sub-items for guides (depth 2), as their sidebar lists them; the
      // landing's opt-in ones would make the list too long for a phone.
      const nav = tocNav();
      const deep = !!nav && parseInt(nav.dataset.depth || '1', 10) > 1;
      tocList.replaceChildren(...entries.map((entry) => {
        const li = document.createElement('li');
        li.append(tocRow(entry.link, entry.num, 'pf-dock__toc-link', now));
        const subs = deep ? entry.link.closest('li').querySelectorAll('.pf-toc__sublink') : [];
        if (subs.length) {
          const ol = document.createElement('ol');
          ol.className = 'pf-dock__toc-sub';
          subs.forEach((sub) => {
            const subLi = document.createElement('li');
            subLi.append(tocRow(sub, '', 'pf-dock__toc-link pf-dock__toc-link--sub', null));
            ol.append(subLi);
          });
          li.append(ol);
        }
        return li;
      }));
    }

    // A row that hands its tap to the sidebar's own link, which glides to
    // the section and focuses its heading (modular-toc.js).
    function tocRow(source, num, className, now) {
      const a = document.createElement('a');
      a.className = className;
      a.href = source.getAttribute('href');
      if (source === now) {
        a.setAttribute('aria-current', 'location');
      }
      if (num) {
        const n = document.createElement('span');
        n.className = 'pf-dock__toc-num';
        n.setAttribute('aria-hidden', 'true');
        n.textContent = num;
        a.append(n);
      }
      const label = document.createElement('span');
      label.className = 'pf-dock__toc-label';
      label.textContent = source.textContent.replace(/\s+/g, ' ').trim();
      a.append(label);
      a.addEventListener('click', (event) => {
        event.preventDefault();
        closePanel(false);
        source.click();
      });
      return a;
    }

    /* ---------------- Panels ---------------- */

    function animate(panel, opening, done) {
      if (reduceMotion()) {
        done();
        return;
      }
      const from = opening ? 0 : panel.offsetHeight;
      const to = opening ? panel.scrollHeight : 0;
      panel.style.height = from + 'px';
      panel.getBoundingClientRect();
      panel.classList.add('is-moving', opening ? 'is-opening' : 'is-closing');
      panel.style.height = to + 'px';
      let finished = false;
      const end = () => {
        if (!finished) {
          finished = true;
          panel.classList.remove('is-moving', 'is-opening', 'is-closing');
          panel.style.height = '';
          done();
        }
      };
      // Only the panel's own height ends it, not its contents' fades.
      const onEnd = (event) => {
        if (event.target === panel && event.propertyName === 'height') {
          panel.removeEventListener('transitionend', onEnd);
          end();
        }
      };
      panel.addEventListener('transitionend', onEnd);
      window.setTimeout(end, 700);
    }

    function openPanel(name, opener) {
      if (open === name) {
        return;
      }
      if (hidden) {
        show(false);
      }
      closePanel(false, true);
      if (name === 'contents') {
        buildList();
      }
      open = name;
      returnTo = opener || openers[name];
      const panel = panels[name];
      panel.hidden = false;
      dock.dataset.open = name;
      openers[name].setAttribute('aria-expanded', 'true');
      animate(panel, true, () => {});
      if (name === 'contents') {
        const cur = tocList.querySelector('[aria-current]') || tocList.querySelector('a');
        if (cur) {
          cur.scrollIntoView({ block: 'nearest' });
          cur.focus({ preventScroll: true });
        }
      }
      else {
        panel.querySelector('[data-pf-dock-close]').focus({ preventScroll: true });
      }
    }

    function closePanel(focus, instant) {
      if (!open) {
        return;
      }
      const name = open;
      const panel = panels[name];
      open = null;
      delete dock.dataset.open;
      openers[name].setAttribute('aria-expanded', 'false');
      const finish = () => {
        if (open !== name) {
          panel.hidden = true;
        }
      };
      if (instant) {
        finish();
      }
      else {
        animate(panel, false, finish);
      }
      if (focus && returnTo && returnTo.isConnected) {
        returnTo.focus({ preventScroll: true });
      }
      returnTo = null;
    }

    status.addEventListener('click', () => (open === 'hours' ? closePanel(true) : openPanel('hours', status)));
    tocButton.addEventListener('click', () => (open === 'contents' ? closePanel(true) : openPanel('contents', tocButton)));
    dock.querySelectorAll('[data-pf-dock-close]').forEach((button) => button.addEventListener('click', () => closePanel(true)));
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && open) {
        closePanel(true);
      }
    });

    // A tap outside closes the open panel, and doesn't follow what it hit.
    // A scroll that starts outside doesn't count.
    let down = null;
    let swallow = false;
    document.addEventListener('pointerdown', (event) => {
      down = open && !dock.contains(event.target) ? { x: event.clientX, y: event.clientY } : null;
    }, true);
    document.addEventListener('pointerup', (event) => {
      if (down && open && Math.hypot(event.clientX - down.x, event.clientY - down.y) < TAP) {
        closePanel(false);
        swallow = true;
        window.setTimeout(() => {
          swallow = false;
        }, 400);
      }
      down = null;
    }, true);
    document.addEventListener('click', (event) => {
      if (swallow && !dock.contains(event.target)) {
        swallow = false;
        event.preventDefault();
        event.stopPropagation();
      }
    }, true);

    /* ---------------- Actions ---------------- */

    function wanted() {
      if (!config) {
        return null;
      }
      const link = activeLink();
      const key = link ? decodeURIComponent(link.hash.slice(1)) : '';
      return (config.sections && config.sections[key]) || config.action || null;
    }

    function settle() {
      window.clearTimeout(settleTimer);
      settleTimer = window.setTimeout(applyAction, SETTLE);
    }

    // The page's own links to the action's destination: their words name
    // the action, so one place has one name on the page (a short label
    // only; long link text keeps the dock's).
    function pageLinks(act) {
      const target = place(act.href);
      return Array.from(document.querySelectorAll('#swup a[href]')).filter((a) => place(a.href) === target);
    }

    function setAction(act) {
      const links = pageLinks(act);
      const words = links.length ? links[0].textContent.replace(/\s+/g, ' ').trim() : '';
      actionLabel.textContent = words && words.length <= 24 ? words : act.label;
      const src = icons && icons.content.querySelector(`[data-icon="${act.icon}"] svg`);
      actionIcon.replaceChildren(...(src ? [src.cloneNode(true)] : []));
      action.href = act.href;
      action.classList.toggle('pf-dock__action--secondary', act.style === 'secondary');
      if (act.external) {
        action.target = '_blank';
        action.rel = 'noopener';
        const note = document.createElement('span');
        note.className = 'visually-hidden';
        note.textContent = ' ' + Drupal.t('(opens in a new tab)');
        actionLabel.append(note);
      }
      else {
        action.removeAttribute('target');
        action.removeAttribute('rel');
      }
    }

    function applyAction() {
      const act = wanted();
      if (act === current) {
        return;
      }
      const had = !!current;
      current = act;
      if (!act) {
        watchDuplicates();
        dock.classList.remove('has-action');
        action.inert = true;
        window.setTimeout(() => {
          if (!current) {
            action.hidden = true;
          }
        }, reduceMotion() ? 0 : 520);
        return;
      }
      if (had && !reduceMotion()) {
        action.classList.add('is-swapping');
        window.setTimeout(() => {
          setAction(current);
          action.classList.remove('is-swapping');
        }, 200);
      }
      else {
        setAction(act);
      }
      action.hidden = false;
      action.inert = false;
      action.getBoundingClientRect();
      dock.classList.add('has-action');
      watchDuplicates();
    }

    // The same place linked from the page: the action folds away while it
    // shows.
    function watchDuplicates() {
      if (observer) {
        observer.disconnect();
        observer = null;
      }
      visible.clear();
      dock.removeAttribute('data-pf-dock-dup');
      if (!current) {
        return;
      }
      const links = pageLinks(current);
      if (!links.length || !('IntersectionObserver' in window)) {
        return;
      }
      const dockH = parseFloat(getComputedStyle(root).getPropertyValue('--pf-dock-h')) || 96;
      observer = new IntersectionObserver((records) => {
        records.forEach((r) => (r.isIntersecting ? visible.add(r.target) : visible.delete(r.target)));
        window.clearTimeout(dupTimer);
        dupTimer = window.setTimeout(() => {
          const dup = visible.size > 0;
          dock.toggleAttribute('data-pf-dock-dup', dup);
          action.inert = dup || !current;
        }, SETTLE);
      }, { rootMargin: `0px 0px -${Math.round(dockH)}px 0px`, threshold: 0.6 });
      links.forEach((a) => observer.observe(a));
    }

    // In-page actions ("Find a room"): glide there and focus it.
    action.addEventListener('click', (event) => {
      const href = action.getAttribute('href');
      if (!href || href.charAt(0) !== '#') {
        return;
      }
      const target = document.getElementById(decodeURIComponent(href.slice(1)));
      if (!target) {
        return;
      }
      event.preventDefault();
      if (target.tagName === 'DETAILS') {
        target.open = true;
      }
      target.scrollIntoView({ behavior: reduceMotion() ? 'auto' : 'smooth', block: 'start' });
      if (!target.hasAttribute('tabindex')) {
        target.setAttribute('tabindex', '-1');
      }
      target.focus({ preventScroll: true });
      window.history.replaceState(window.history.state, '', href);
    });

    /* ---------------- Hide and show ---------------- */

    function hide(focus) {
      closePanel(false, true);
      hidden = true;
      dock.classList.add('is-hidden');
      syncInert();
      store(true);
      if (tab) {
        tab.hidden = false;
        if (focus) {
          tab.focus({ preventScroll: true });
        }
      }
    }

    function show(focus) {
      hidden = false;
      dock.classList.remove('is-hidden');
      syncInert();
      store(false);
      if (tab) {
        tab.hidden = true;
      }
      if (focus) {
        grab.focus({ preventScroll: true });
      }
    }

    grab.addEventListener('click', () => hide(true));
    if (tab) {
      tab.addEventListener('click', () => show(true));
    }

    // Swipe down on the grabber or the row to hide; up on the tab to show.
    function swipe(el, down, act) {
      let start = null;
      el.addEventListener('pointerdown', (event) => {
        start = event.pointerType === 'mouse' || open ? null : { x: event.clientX, y: event.clientY };
      });
      el.addEventListener('pointerup', (event) => {
        if (!start) {
          return;
        }
        const dy = (event.clientY - start.y) * (down ? 1 : -1);
        if (dy > SWIPE && Math.abs(event.clientX - start.x) < dy) {
          act();
        }
        start = null;
      });
      el.addEventListener('pointercancel', () => {
        start = null;
      });
    }
    swipe(grab, true, () => hide(true));
    swipe(row, true, () => hide(true));
    if (tab) {
      swipe(tab, false, () => show(true));
    }

    /* ---------------- Living with the page ---------------- */

    // Focus inside the page never hides under the dock (WCAG 2.4.11), and
    // the dock steps aside while the on-screen keyboard is up.
    document.addEventListener('focusin', (event) => {
      const el = event.target;
      if (!config || dock.contains(el) || !el.closest || !el.closest('#swup')) {
        return;
      }
      if (el.matches(FIELDS)) {
        aside = true;
        dock.classList.add('is-aside');
        closePanel(false, true);
        syncInert();
        return;
      }
      if (hidden || MD.matches) {
        return;
      }
      const top = dock.getBoundingClientRect().top;
      const bottom = el.getBoundingClientRect().bottom;
      if (bottom > top - 8) {
        window.scrollBy({ top: bottom - top + 16, behavior: 'auto' });
      }
    });
    document.addEventListener('focusout', () => {
      window.setTimeout(() => {
        const el = document.activeElement;
        if (aside && !(el && el.matches && el.matches(FIELDS) && el.closest('#swup'))) {
          aside = false;
          dock.classList.remove('is-aside');
          syncInert();
        }
      }, 0);
    });

    // The menu and the drawers cover the dock: out of reach while open.
    document.addEventListener('show.bs.offcanvas', (event) => {
      // Not when the Hours drawer handed its opening to the dock.
      if (event.defaultPrevented) {
        return;
      }
      covered = true;
      closePanel(false, true);
      syncInert();
    });
    document.addEventListener('hidden.bs.offcanvas', (event) => {
      covered = !!document.querySelector('.offcanvas.show, .offcanvas.showing');
      syncInert();
      if (covered) {
        return;
      }
      // The menu's Hours tile closed the menu to open the week here.
      if (pending) {
        const name = pending;
        pending = null;
        openPanel(name, openers[name]);
      }
      // Back from the Ask drawer opened from the week: the week again,
      // focus on Ask Us (as the menu takes its tile back, palefire.js).
      else if (askBack && event.target === askPanel) {
        askBack = false;
        openPanel('hours', status);
        ask.focus({ preventScroll: true });
      }
    });

    // Ask Us in the week: the utility menu's Ask drawer (LibAnswers).
    if (ask && askPanel && window.bootstrap) {
      ask.hidden = false;
      ask.addEventListener('click', () => {
        closePanel(false, true);
        askBack = true;
        window.bootstrap.Offcanvas.getOrCreateInstance(askPanel).show(ask);
      });
      // Leaving for a link inside the drawer: don't reopen the week.
      askPanel.addEventListener('click', (event) => {
        if (event.target.closest('a[href]')) {
          askBack = false;
        }
      });
    }

    document.addEventListener('palefire:sectionchange', () => {
      queueFrame();
      settle();
    });
    window.addEventListener('scroll', () => {
      queueFrame();
      if (current !== wanted()) {
        settle();
      }
    }, { passive: true });

    // The row's and the closed dock's heights, for the page's reserved
    // space and scroll padding (_dock.scss); and the name's fit, which the
    // status's width decides (it narrows as an action unfolds).
    if ('ResizeObserver' in window) {
      const sizes = new ResizeObserver(() => {
        root.style.setProperty('--pf-dock-row', row.offsetHeight + 'px');
        if (!open) {
          root.style.setProperty('--pf-dock-h', dock.offsetHeight + 'px');
        }
        fitName();
      });
      sizes.observe(row);
      sizes.observe(status);
    }

    window.setInterval(() => {
      if (specs) {
        renderHours();
      }
    }, 60000);

    if (stored()) {
      hidden = true;
      dock.classList.add('is-hidden');
    }

    return {
      readConfig,
      close: () => closePanel(false, true),
      openContents(opener) {
        if (!config || MD.matches || tocButton.hidden) {
          return false;
        }
        openPanel('contents', opener);
        return true;
      },
      // The utility menu's Hours (palefire.js, utility-panels.js): the
      // week here instead of the Hours drawer. Under the open menu, it
      // opens once the menu has closed; focus lands on its close button
      // and returns to the status.
      openHours() {
        if (!config || MD.matches) {
          return false;
        }
        if (covered) {
          pending = 'hours';
        }
        else {
          openPanel('hours', status);
        }
        return true;
      },
    };
  }

  let api = null;

  Drupal.palefireDock = {
    openContents: (opener) => !!api && api.openContents(opener),
    openHours: () => !!api && api.openHours(),
  };

  Drupal.behaviors.palefireDock = {
    attach() {
      once('pf-dock', '#pf-dock').forEach((dock) => {
        api = initDock(dock);
      });
      // Runs again after each Swup visit (palefire-swup.js re-attaches the
      // new #swup): the new page's settings.
      if (api) {
        api.readConfig();
      }
    },
    detach(context, settings, trigger) {
      if (api && trigger === 'unload' && context.id === 'swup') {
        api.close();
      }
    },
  };
})(Drupal, once);
