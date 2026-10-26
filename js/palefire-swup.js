/**
 * @file
 * Palefire page transitions with Swup 4 (https://swup.js.org).
 *
 * Swup fetches the next page, cross-fades the #swup container, and swaps it
 * in without a full reload. Drupal was not built for this, so this file keeps
 * Drupal consistent:
 *
 * - Behaviors: detached from the outgoing containers ('unload') and attached
 *   to the incoming ones, with drupalSettings replaced by the new page's.
 * - Assets: libraries the next page needs that this one hasn't loaded are
 *   added before the swap, as an aggregate of just those (see "Missing
 *   assets"; 2026-10-26, was a full page load). The visit falls back to a
 *   normal full page load if that fails, or if the next page's content
 *   contains executable <script> tags (LibCal/other embeds).
 * - Head/body: <title> (Swup), meta tags, canonical/shortlink, JSON-LD,
 *   <html lang/dir>, and Drupal's path-* body classes are synced.
 * - Accessibility: focus moves to the new page's <h1> (or the #anchor
 *   target, or <main>), and the page title is announced via Drupal.announce.
 *   Transitions are skipped when the user prefers reduced motion.
 * - Exhibitions (/…/exhibitions/<name>) always load normally: they need
 *   their own scripts, so a visit there faded the page out, fetched it, found
 *   it incompatible and loaded it a second time (2026-10-26). Any other page
 *   that falls back is remembered for the session and loaded normally from
 *   the start next time.
 * - Prefetch: a link's page is fetched into the cache on hover, focus or
 *   touch, so most visits swap without waiting (see "Prefetch").
 * - Loading line: a thin line under the header (.pf-loading, _header.scss)
 *   while the next page loads, a Swup visit or a normal one (a link Swup
 *   skips). It shows only after 300ms, so a quick visit never flashes it;
 *   after a second screen readers hear "Loading…". Cleared when a page is
 *   shown again from the back/forward cache.
 *
 * Swup is only enabled for anonymous visitors (page.html.twig attaches this
 * library when not logged in); editors get normal page loads so the toolbar,
 * contextual links, and editing UI are never swapped. Add data-no-swup to any
 * link (or an ancestor) to force a normal page load.
 */
((Drupal, drupalSettings, once) => {
  const CONTAINER = '#swup';
  // Also swapped when present, so menu active-trail classes stay correct
  // and the footer follows the section (includes/footer.html.twig).
  const OPTIONAL_CONTAINERS = ['#pf-primary-menu', '#pf-footer'];

  // Paths that must always get a full page load.
  const IGNORE_PATHS = [
    /^\/(admin|user|batch|system|core|modules|themes|profiles|libraries|sites)(\/|$)/,
    /^\/node\/add(\/|$)/,
    /^\/node\/\d+\/(edit|delete|revisions|layout)(\/|$)/,
    /^\/(media|taxonomy\/term)\/\d+\/(edit|delete)(\/|$)/,
    /\.(?!html?$)[a-z0-9]{2,5}$/i, // file downloads (pdf, docx, jpg, ...)
    /\/exhibitions\/[^/]+/, // an exhibition (not the list): scripts of its own
  ];

  // Pages that fell back to a full load this session (page:load, below).
  const HARD_KEY = 'palefire.swupHard';
  const hardPaths = () => {
    try {
      return JSON.parse(window.sessionStorage.getItem(HARD_KEY)) || [];
    }
    catch (e) {
      return [];
    }
  };
  const rememberHard = (path) => {
    try {
      const paths = hardPaths();
      if (!paths.includes(path)) {
        window.sessionStorage.setItem(HARD_KEY, JSON.stringify(paths.concat(path).slice(-50)));
      }
    }
    catch (e) {
      // Storage blocked: the fallback still works, just later.
    }
  };

  /* ---------------------------------------------------------------------
   * Loading line
   * ------------------------------------------------------------------ */

  const LOADING = 'pf-is-loading';
  let announceTimer = 0;

  const startLoading = () => {
    const root = document.documentElement;
    if (root.classList.contains(LOADING)) {
      return;
    }
    root.classList.add(LOADING);
    window.clearTimeout(announceTimer);
    announceTimer = window.setTimeout(() => Drupal.announce(Drupal.t('Loading…')), 1000);
  };

  const stopLoading = () => {
    document.documentElement.classList.remove(LOADING);
    window.clearTimeout(announceTimer);
  };

  // The line itself, once, at the foot of the header (outside #swup, so it
  // survives every visit).
  const addLine = () => {
    const header = document.querySelector('.pf-header');
    if (!header || header.querySelector('.pf-loading')) {
      return;
    }
    const line = document.createElement('div');
    line.className = 'pf-loading';
    line.setAttribute('aria-hidden', 'true');
    line.append(document.createElement('span'));
    header.append(line);
  };

  // Head elements replaced on every visit (the title is handled by Swup).
  const HEAD_SELECTOR = [
    'meta[name]:not([name="viewport"])',
    'meta[property]',
    'link[rel="canonical"]',
    'link[rel="shortlink"]',
    'link[rel="alternate"]',
    'link[rel="image_src"]',
    'script[type="application/ld+json"]',
  ].join(',');

  const BODY_CLASS = /^(path-|page-node-type-|modular--)/;

  const reducedMotion = window.matchMedia
    ? window.matchMedia('(prefers-reduced-motion: reduce)')
    : { matches: false };

  const assetUrls = (doc) => {
    const urls = [];
    doc.querySelectorAll('script[src], link[rel="stylesheet"][href]').forEach((el) => {
      urls.push(new URL(el.getAttribute('src') || el.getAttribute('href'), window.location.href).href);
    });
    return urls;
  };

  /* ---------------------------------------------------------------------
   * Missing assets
   *
   * A page that needs libraries this one hasn't loaded used to fall back to
   * a full load, after Swup had already faded the page out and fetched it
   * (so it was fetched twice). Its own aggregates can't simply be added:
   * each one bundles its whole group, Drupal core included, and running
   * drupal.js again would wipe every behavior. So this asks Drupal's asset
   * route (/sites/…/files/js/js_*.js) for an aggregate of only the missing
   * libraries, the way Drupal's own AJAX adds assets: include = the next
   * page's libraries less this one's, exclude = this one's (dependencies
   * resolved by Drupal, so core and anything loaded stays out). Drupal builds
   * any include/exclude set on request; with a name that isn't its own hash
   * it serves the file without saving it. CSS first, then header and footer
   * scripts, each group (delta) in order until one past the last answers
   * 400. Files the next page links outside aggregates are added as they
   * are. Then ajaxPageState.libraries becomes the union, so Drupal AJAX and
   * the next visit know what is loaded.
   * ------------------------------------------------------------------ */

  // ajaxPageState.libraries once more libraries have been added; null while
  // the page has what it loaded with.
  let pageLibraries = null;

  // Drupal's UrlHelper::compressQueryParameter(): gzcompress (zlib, which
  // is the streams' "deflate"), base64 with - and _, no padding.
  const toBase64 = (bytes) => btoa(String.fromCharCode(...bytes)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  const fromBase64 = (text) => {
    let s = text.replace(/-/g, '+').replace(/_/g, '/');
    while (s.length % 4) {
      s += '=';
    }
    return Uint8Array.from(atob(s), (c) => c.charCodeAt(0));
  };
  const pipe = (bytes, stream) => new Response(new Blob([bytes]).stream().pipeThrough(stream));
  const compress = async (text) => toBase64(new Uint8Array(await pipe(new TextEncoder().encode(text), new CompressionStream('deflate')).arrayBuffer()));
  const uncompress = async (text) => (await pipe(fromBase64(text), new DecompressionStream('deflate')).text()).split(',').filter(Boolean);

  // A short stable name for an aggregate, so the browser can cache it.
  const nameFor = (text) => {
    let h = 0;
    for (let i = 0; i < text.length; i++) {
      h = (h * 31 + text.charCodeAt(i)) | 0;
    }
    return (h >>> 0).toString(36);
  };

  const AGGREGATE = /\/(css|js)\/(css|js)_[^/]+\.(css|js)$/;

  // One group of the missing libraries' aggregate: fetched first, since a
  // group past the last answers 400 "Invalid filename." and a <script src>
  // ran that text as a script; then its code goes in as a <style> or an
  // inline <script> (which runs as it is added, so groups keep their order).
  // Drupal rewrites url()s in aggregated CSS to root-relative ones, so they
  // hold in a <style>.
  const addGroup = async (url, type) => {
    const response = await fetch(url, { credentials: 'same-origin' });
    if (!response.ok) {
      throw new Error(url);
    }
    const code = await response.text();
    // An empty group (the theme's CSS is one global file): nothing to add.
    if (!code.trim()) {
      return;
    }
    const el = document.createElement(type === 'css' ? 'style' : 'script');
    el.dataset.pfAsset = url;
    el.textContent = code;
    document.head.append(el);
  };

  // Adds a stylesheet or script the next page links outside aggregates, and
  // waits for it; rejects (and removes it) on an error.
  const addAsset = (url) => new Promise((resolve, reject) => {
    const css = /\.css$/.test(new URL(url, window.location.href).pathname);
    const el = document.createElement(css ? 'link' : 'script');
    if (css) {
      el.rel = 'stylesheet';
      el.href = url;
    }
    else {
      el.src = url;
      el.async = false;
    }
    el.addEventListener('load', () => resolve(el), { once: true });
    el.addEventListener('error', () => {
      el.remove();
      reject(new Error(url));
    }, { once: true });
    document.head.append(el);
  });

  /**
   * Loads what the next page needs that this one hasn't.
   *
   * @return {Promise<boolean>}
   *   FALSE when it can't be done here (no compression streams, no library
   *   lists, or Drupal sent nothing for libraries that are missing): the
   *   visit then falls back to a full load.
   */
  const loadMissing = async (doc, next, loadedAssets) => {
    const now = drupalSettings.ajaxPageState;
    const then = next.ajaxPageState;
    if (!('CompressionStream' in window) || !now || !then || !now.libraries || !then.libraries) {
      return false;
    }
    const have = await uncompress(pageLibraries || now.libraries);
    const missing = (await uncompress(then.libraries)).filter((library) => !have.includes(library));
    const sample = document.querySelector('script[src*="/js/js_"]');
    if (missing.length && sample) {
      const src = new URL(sample.src);
      const jsDir = src.pathname.replace(/[^/]+$/, '');
      const include = await compress(missing.join(','));
      const exclude = pageLibraries || now.libraries;
      const name = nameFor(include + exclude);
      const base = { language: src.searchParams.get('language') || document.documentElement.lang || 'en', theme: now.theme || src.searchParams.get('theme') || '', include, exclude };
      let loaded = 0;
      for (const [type, scope] of [['css', null], ['js', 'header'], ['js', 'footer']]) {
        const dir = type === 'css' ? jsDir.replace(/\/js\/$/, '/css/') : jsDir;
        for (let delta = 0; delta < 8; delta++) {
          const params = new URLSearchParams(Object.assign(scope ? { scope } : {}, { delta: String(delta) }, base));
          try {
            await addGroup(`${dir}${type}_pf${name}${delta}.${type}?${params}`, type);
            loaded++;
          }
          catch (e) {
            break;
          }
        }
      }
      if (!loaded) {
        return false;
      }
    }
    // Files the next page links outside aggregates (or every file, where
    // aggregation is off).
    const loose = assetUrls(doc).filter((url) => !loadedAssets.has(url) && (!sample || !AGGREGATE.test(new URL(url).pathname)));
    for (const url of loose) {
      try {
        await addAsset(url);
      }
      catch (e) {
        return false;
      }
    }
    assetUrls(doc).forEach((url) => loadedAssets.add(url));
    if (missing.length) {
      pageLibraries = await compress(have.concat(missing).join(','));
    }
    return true;
  };

  const isExecutable = (script) => {
    const type = (script.getAttribute('type') || '').trim().toLowerCase();
    return !type || type === 'module' || /(java|ecma)script/.test(type);
  };

  const readSettings = (doc) => {
    const el = doc.querySelector('script[type="application/json"][data-drupal-selector="drupal-settings-json"]');
    try {
      return el ? JSON.parse(el.textContent) : null;
    } catch (e) {
      return null;
    }
  };

  /**
   * Replaces drupalSettings in place: core and contrib scripts hold a
   * reference to the same object.
   */
  const replaceSettings = (next) => {
    const libraries = pageLibraries || (drupalSettings.ajaxPageState && drupalSettings.ajaxPageState.libraries);
    Object.keys(drupalSettings).forEach((key) => delete drupalSettings[key]);
    Object.assign(drupalSettings, next);
    // Keep the list of libraries actually loaded in this document, so Drupal
    // AJAX requests keep sending the correct ajax_page_state.
    if (libraries && drupalSettings.ajaxPageState) {
      drupalSettings.ajaxPageState.libraries = libraries;
    }
  };

  const syncDocument = (doc) => {
    document.head.querySelectorAll(HEAD_SELECTOR).forEach((el) => el.remove());
    doc.head.querySelectorAll(HEAD_SELECTOR).forEach((el) => {
      document.head.appendChild(document.importNode(el, true));
    });

    ['lang', 'dir'].forEach((name) => {
      const value = doc.documentElement.getAttribute(name);
      if (value) {
        document.documentElement.setAttribute(name, value);
      }
    });
    // Page mode and the Landing hero flag (PalefireHooks::preprocessHtml()):
    // present on some pages only, so copy or remove them. data-pf-hero has
    // no value, so it is copied when present at all.
    ['data-pf-mode', 'data-pf-hero'].forEach((name) => {
      if (doc.documentElement.hasAttribute(name)) {
        document.documentElement.setAttribute(name, doc.documentElement.getAttribute(name));
      }
      else {
        document.documentElement.removeAttribute(name);
      }
    });
    document.dispatchEvent(new CustomEvent('palefire:pagemode'));

    const body = document.body.classList;
    Array.from(body).filter((name) => BODY_CLASS.test(name)).forEach((name) => body.remove(name));
    Array.from(doc.body.classList).filter((name) => BODY_CLASS.test(name)).forEach((name) => body.add(name));
  };

  /**
   * Closes the mobile menu drawer (a Bootstrap offcanvas, page.html.twig)
   * before leaving, so the new page doesn't open under it.
   */
  const closeMenu = () => {
    const panel = document.querySelector('#pf-primary-menu.show');
    if (panel && window.bootstrap) {
      window.bootstrap.Offcanvas.getOrCreateInstance(panel).hide();
    }
  };
  const focusTarget = (hash) => {
    let target = null;
    if (hash && hash.length > 1) {
      try {
        target = document.getElementById(decodeURIComponent(hash.slice(1)));
      } catch (e) {
        target = null;
      }
    }
    target = target || document.querySelector('#main-content h1, main h1') || document.getElementById('main-content');
    if (!target) {
      return;
    }
    if (!target.matches('a[href], button, input, select, textarea, [tabindex]')) {
      target.setAttribute('tabindex', '-1');
      target.addEventListener('blur', () => target.removeAttribute('tabindex'), { once: true });
    }
    target.focus({ preventScroll: true });
  };

  const shouldIgnore = (url, { el, event } = {}) => {
    // A script already handled this click (called preventDefault). Swup
    // doesn't check that itself, so it would follow the link too.
    if (event && event.defaultPrevented) {
      return true;
    }
    if (el) {
      // [data-bs-toggle]: links that open a Bootstrap component (the utility
      // menu's offcanvas drawers). [data-pf-over-menu]: the mobile menu's
      // quick-link tiles, which open their drawer over the menu
      // (openOverMenu, palefire.js) instead of through Bootstrap's data API.
      if (el.closest('[data-no-swup], [data-bs-toggle], [data-pf-over-menu], .use-ajax, #toolbar-administration, .contextual')) {
        return true;
      }
      if (el.hasAttribute('download') || (el.target && el.target !== '_self')) {
        return true;
      }
    }
    const next = new URL(url, window.location.href);
    if (next.origin !== window.location.origin) {
      return true;
    }
    // Same-page #anchor links (e.g. the TOC): let the browser scroll, so
    // scroll-padding and scroll-snap behave exactly as without Swup.
    if (next.hash && next.pathname === window.location.pathname && next.search === window.location.search) {
      return true;
    }
    // Compare Drupal paths without the base path or language prefix.
    const base = (drupalSettings.path && drupalSettings.path.baseUrl) || '/';
    const prefix = (drupalSettings.path && drupalSettings.path.pathPrefix) || '';
    let path = next.pathname.startsWith(base) ? next.pathname.slice(base.length) : next.pathname.replace(/^\//, '');
    if (prefix && path.startsWith(prefix)) {
      path = path.slice(prefix.length);
    }
    return IGNORE_PATHS.some((pattern) => pattern.test(`/${path}`)) || hardPaths().includes(next.pathname);
  };

  // A click that leaves the page without Swup (an ignored link): the line
  // until the next page replaces this one. Bubble phase, after Swup and
  // every other handler, so a link they took over is left alone.
  const onLeave = (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }
    const el = event.target instanceof Element ? event.target.closest('a[href]') : null;
    if (!el || el.hasAttribute('download') || (el.target && el.target !== '_self')) {
      return;
    }
    const next = new URL(el.href, window.location.href);
    if (next.origin !== window.location.origin || !/^https?:$/.test(next.protocol)) {
      return;
    }
    if (next.pathname === window.location.pathname && next.search === window.location.search) {
      return;
    }
    // A file download leaves the page in place: no line.
    if (/\.(?!html?$)[a-z0-9]{2,5}$/i.test(next.pathname)) {
      return;
    }
    startLoading();
  };

  /* ---------------------------------------------------------------------
   * Prefetch
   *
   * A link's page is fetched into Swup's cache when the pointer rests on it
   * (80ms, so sweeping across a menu fetches nothing), when it gets focus,
   * or when a finger touches it, so the visit usually swaps with no wait.
   * Only links Swup would follow, each page once, two at a time, and never
   * with Data Saver or on a 2G connection.
   * ------------------------------------------------------------------ */

  const PREFETCH_DELAY = 80;
  const PREFETCH_MAX = 2;
  // Swup URL (path + query) => the request in flight.
  const prefetching = new Map();

  const prefetchOn = (swup) => {
    const connection = navigator.connection;
    if (connection && (connection.saveData || /2g/.test(connection.effectiveType || ''))) {
      return;
    }
    let hoverTimer = 0;
    let hoverLink = null;

    const prefetch = (el) => {
      if (swup.shouldIgnoreVisit(el.href, { el })) {
        return;
      }
      const next = new URL(el.href, window.location.href);
      const url = next.pathname + next.search;
      if (url === window.location.pathname + window.location.search || prefetching.has(url) || prefetching.size >= PREFETCH_MAX || swup.cache.has(url)) {
        return;
      }
      const request = swup.fetchPage(url)
        .catch(() => null)
        .finally(() => prefetching.delete(url));
      prefetching.set(url, request);
    };

    const linkFor = (event) => (event.target instanceof Element ? event.target.closest('a[href]') : null);

    document.addEventListener('mouseover', (event) => {
      const el = linkFor(event);
      // Moving between a link's own children: the timer keeps running.
      if (el === hoverLink) {
        return;
      }
      hoverLink = el;
      window.clearTimeout(hoverTimer);
      if (el) {
        hoverTimer = window.setTimeout(() => prefetch(el), PREFETCH_DELAY);
      }
    });
    document.addEventListener('focusin', (event) => {
      const el = linkFor(event);
      if (el) {
        prefetch(el);
      }
    });
    document.addEventListener('touchstart', (event) => {
      const el = linkFor(event);
      if (el) {
        prefetch(el);
      }
    }, { passive: true });
  };

  const init = () => {
    const containers = [CONTAINER].concat(OPTIONAL_CONTAINERS.filter((sel) => document.querySelector(sel)));
    const loadedAssets = new Set(assetUrls(document));
    let incoming = null;
    let hardNavigation = false;

    const swup = new window.Swup({
      containers,
      cache: true,
      ignoreVisit: shouldIgnore,
      // The content fades, not #swup: it's the curtain over the footer
      // (base/_swup.scss).
      animationSelector: `${CONTAINER} > *`,
    });

    // A visit to a page still being prefetched waits for that request
    // instead of sending a second one; the page is then in the cache.
    swup.hooks.replace('page:load', async (visit, args, defaultHandler) => {
      const pending = prefetching.get(visit.to.url);
      if (pending) {
        await pending;
      }
      return defaultHandler(visit, args);
    });
    prefetchOn(swup);

    // The new page opens at its top (or #anchor) in one jump, while its
    // content is still faded out. Swup scrolls with behavior 'auto', which
    // follows the CSS scroll-behavior: smooth (_modular.scss), so a visit
    // from far down the page showed the new one gliding up as it faded in.
    ['scroll:top', 'scroll:anchor'].forEach((hook) => {
      swup.hooks.before(hook, (visit, args) => {
        args.options.behavior = 'instant';
      });
    });

    swup.hooks.on('visit:start', (visit) => {
      hardNavigation = false;
      incoming = null;
      startLoading();
      if (reducedMotion.matches || document.documentElement.classList.contains('pf-a11y-motion')) {
        visit.animation.animate = false;
      }
      closeMenu();
    });

    // Decide whether the fetched page can be swapped in safely, loading the
    // libraries it needs that this page hasn't (loadMissing, above). Swup
    // waits for the promise before it swaps.
    swup.hooks.on('page:load', async (visit, args) => {
      const html = args && args.page && args.page.html;
      const doc = html ? new DOMParser().parseFromString(html, 'text/html') : null;
      const settings = doc && readSettings(doc);
      let compatible = doc
        && settings
        && containers.every((sel) => doc.querySelector(sel))
        && OPTIONAL_CONTAINERS.every((sel) => containers.includes(sel) || !doc.querySelector(sel))
        && !containers.some((sel) => Array.from(doc.querySelector(sel).querySelectorAll('script')).some(isExecutable))
        && !doc.querySelector('[data-big-pipe-placeholder-id]');
      if (compatible && !assetUrls(doc).every((url) => loadedAssets.has(url))) {
        try {
          compatible = await loadMissing(doc, settings, loadedAssets);
        }
        catch (e) {
          compatible = false;
        }
      }

      if (!compatible) {
        hardNavigation = true;
        rememberHard(new URL(visit.to.url, window.location.href).pathname);
        if (typeof visit.abort === 'function') {
          visit.abort();
        }
        window.location.assign(`${visit.to.url}${visit.to.hash || ''}`);
        return;
      }
      incoming = { doc, settings };
    });

    swup.hooks.before('content:replace', () => {
      if (hardNavigation) {
        return;
      }
      containers.forEach((sel) => {
        document.querySelectorAll(sel).forEach((el) => Drupal.detachBehaviors(el, drupalSettings, 'unload'));
      });
    });

    // Never swap in a page we've decided to load normally.
    swup.hooks.replace('content:replace', (visit, args, defaultHandler) => {
      if (!hardNavigation) {
        return defaultHandler(visit, args);
      }
      return undefined;
    });

    swup.hooks.on('content:replace', () => {
      if (hardNavigation || !incoming) {
        return;
      }
      replaceSettings(incoming.settings);
      syncDocument(incoming.doc);
      containers.forEach((sel) => {
        document.querySelectorAll(sel).forEach((el) => Drupal.attachBehaviors(el, drupalSettings));
      });
    });

    swup.hooks.on('page:view', (visit) => {
      if (hardNavigation) {
        return;
      }
      stopLoading();
      focusTarget(visit.to.hash || window.location.hash);
      Drupal.announce(Drupal.t('Page loaded: @title', { '@title': document.title }));
    });

    Drupal.palefireSwup = swup;
  };

  Drupal.behaviors.palefireSwup = {
    attach() {
      once('pf-loading', 'html').forEach(() => {
        addLine();
        document.addEventListener('click', onLeave);
        // Back/forward cache: the page comes back as it was left, line on.
        window.addEventListener('pageshow', (event) => {
          if (event.persisted) {
            stopLoading();
          }
        });
      });
      once('pf-swup', 'html').forEach(() => {
        if (
          typeof window.Swup !== 'function'
          || !document.querySelector(CONTAINER)
          || document.body.classList.contains('user-logged-in')
          || document.documentElement.hasAttribute('data-no-swup')
          || !window.history.pushState
        ) {
          return;
        }
        init();
      });
    },
  };
})(Drupal, drupalSettings, once);
