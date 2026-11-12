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
 * - Only /v2 paths use Swup for now; any other page loads normally
 *   (2026-11-12: a Swup visit there broke Back, see the fallback below).
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
 *   after a second screen readers hear "Loading…". During a Swup visit the
 *   LSU mark is also traced in the middle of the faded-out page
 *   (.pf-loading-mark, _swup.scss). Cleared when a page is
 *   shown again from the back/forward cache.
 *
 * - View transitions (2026-11-03): where the browser has them (Chrome,
 *   Edge, Safari 18+) and the next page is already in the cache (a
 *   prefetch), the visit runs as a native view transition (Swup's native
 *   option): the old page fades out and the new one rises in as one
 *   picture, and a card's picture and title glide into the new page's
 *   lead picture and <h1> (see "Morph"). Otherwise, and in every other
 *   browser, the CSS fade runs as before. <html class="pf-vt"> marks a
 *   native visit, so base/_swup.scss leaves its fade alone.
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

  // The LSU mark, traced in the middle of the window while a Swup visit's
  // content is faded out (base/_swup.scss). Once, outside #swup. Decorative:
  // the line above and the "Loading…" announcement carry the meaning.
  // pathLength="1" lets the CSS draw each letter without measuring it.
  const MARK_SHAPES = [
    '<path d="M144.51 69.17L144.51 46.93L101.95 46.93L101.95 40.65C101.95 37.87 105.03 35.6 108.75 35.6L144.51 35.6L144.51 23.68L99.18 23.68C89.52 23.68 81.63 29.53 81.63 36.7L81.63 58.85L124.18 58.85L124.18 65.22C124.18 68 121.11 70.26 117.31 70.26L81.63 70.26L81.63 82.18L126.89 82.18C136.62 82.18 144.51 76.33 144.51 69.17"/>',
    '<path d="M76.79 70.34L44.32 70.34L44.32 23.68L24.14 23.68L24.14 82.18L76.79 82.18Z"/>',
    '<path d="M212.06 69.17L212.06 23.68L191.8 23.68L191.8 65.22C191.8 68.07 188.73 70.33 184.93 70.33L169.43 70.33L169.43 23.83L149.32 23.83L149.32 82.18L194.51 82.18C204.16 82.18 212.06 76.33 212.06 69.17"/>',
    '<path d="M43.79 128.68L49.32 128.68L49.32 149.82L60.32 149.82L60.32 154.7L43.79 154.7Z"/>',
    '<path d="M64.07 135.86L69.02 135.86L69.02 154.7L64.07 154.7ZM63.56 130.48C63.56 128.84 64.92 127.51 66.52 127.51C68.16 127.51 69.49 128.84 69.49 130.48C69.49 132.11 68.16 133.44 66.52 133.44C64.92 133.44 63.56 132.11 63.56 130.48"/>',
    '<path d="M83.14 150.76C86.02 150.76 87.93 148.57 87.93 145.3C87.93 142.02 86.02 139.8 83.14 139.8C81.54 139.8 79.7 140.73 78.89 141.98L78.89 148.65C79.7 149.86 81.54 150.76 83.14 150.76M73.93 128.68L78.89 128.68L78.89 138.28C80.37 136.36 82.47 135.39 84.7 135.39C89.49 135.39 93.04 139.13 93.04 145.3C93.04 151.57 89.45 155.16 84.7 155.16C82.43 155.16 80.37 154.15 78.89 152.28L78.89 154.69L73.93 154.69Z"/>',
    '<path d="M96.79 135.86L101.74 135.86L101.74 138.43C103.07 136.75 105.41 135.43 107.75 135.43L107.75 140.26C107.39 140.19 106.93 140.11 106.38 140.11C104.74 140.11 102.56 141.04 101.74 142.25L101.74 154.7L96.79 154.7Z"/>',
    '<path d="M122.14 147.83C121.32 146.74 119.76 146.19 118.16 146.19C116.21 146.19 114.61 147.21 114.61 149.04C114.61 150.79 116.21 151.81 118.16 151.81C119.76 151.81 121.32 151.26 122.14 150.17ZM122.14 152.74C120.85 154.27 118.63 155.16 116.17 155.16C113.17 155.16 109.62 153.14 109.62 148.92C109.62 144.52 113.17 142.92 116.17 142.92C118.67 142.92 120.89 143.7 122.14 145.22L122.14 142.6C122.14 140.69 120.5 139.44 118 139.44C115.97 139.44 114.1 140.19 112.5 141.67L110.63 138.35C112.93 136.32 115.9 135.39 118.86 135.39C123.15 135.39 127.09 137.1 127.09 142.53L127.09 154.69L122.14 154.69Z"/>',
    '<path d="M132 135.86L136.96 135.86L136.96 138.43C138.28 136.75 140.62 135.43 142.96 135.43L142.96 140.26C142.61 140.19 142.14 140.11 141.6 140.11C139.96 140.11 137.78 141.04 136.96 142.25L136.96 154.7L132 154.7Z"/>',
    '<path d="M146.04 135.86L151 135.86L151 154.7L146.04 154.7ZM145.54 130.48C145.54 128.84 146.9 127.51 148.5 127.51C150.14 127.51 151.46 128.84 151.46 130.48C151.46 132.11 150.14 133.44 148.5 133.44C146.9 133.44 145.54 132.11 145.54 130.48"/>',
    '<path d="M159.81 143.5L169.09 143.5C168.97 141.71 167.73 139.33 164.45 139.33C161.37 139.33 160.04 141.63 159.81 143.5M164.45 135.39C170.07 135.39 173.89 139.6 173.89 145.76L173.89 146.86L159.89 146.86C160.2 149.23 162.11 151.22 165.31 151.22C167.06 151.22 169.13 150.52 170.34 149.35L172.52 152.55C170.65 154.27 167.69 155.16 164.76 155.16C159.03 155.16 154.7 151.3 154.7 145.26C154.7 139.8 158.72 135.39 164.45 135.39"/>',
    '<path d="M177.98 148.77C179.35 150.05 182.16 151.34 184.5 151.34C186.64 151.34 187.65 150.6 187.65 149.43C187.65 148.1 186.02 147.64 183.87 147.21C180.63 146.58 176.46 145.8 176.46 141.32C176.46 138.16 179.23 135.39 184.18 135.39C187.38 135.39 189.92 136.48 191.79 137.96L189.8 141.36C188.67 140.15 186.52 139.17 184.22 139.17C182.43 139.17 181.26 139.84 181.26 140.93C181.26 142.1 182.74 142.53 184.85 142.96C188.08 143.58 192.41 144.44 192.41 149.12C192.41 152.55 189.49 155.16 184.26 155.16C180.98 155.16 177.83 154.07 175.84 152.24Z"/>',
  ];
  const MARK_RULE = '<line class="pf-mark-track" x1="24.14" y1="114.43" x2="212.06" y2="114.43" stroke-width="1.51"/><line class="pf-mark-worm" pathLength="1" x1="24.14" y1="114.43" x2="212.06" y2="114.43" stroke-width="1.51"/>';

  const addMark = () => {
    if (document.querySelector('.pf-loading-mark')) {
      return;
    }
    const mark = document.createElement('div');
    mark.className = 'pf-loading-mark';
    mark.setAttribute('aria-hidden', 'true');
    mark.innerHTML = `<svg viewBox="23.14 22.68 189.92 133.48" width="188" height="132" focusable="false">${MARK_SHAPES.map((shape) => shape.replace('/>', ' pathLength="1"/>')).join('')}${MARK_RULE}</svg>`;
    document.body.append(mark);
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
    // Page mode, kind, the Landing hero flag and the mobile dock flag
    // (PalefireHooks::preprocessHtml()): present on some pages only, so copy
    // or remove them. data-pf-hero and data-pf-dock have no value, so they
    // are copied when present at all.
    ['data-pf-mode', 'data-pf-page', 'data-pf-hero', 'data-pf-dock', 'data-pf-section', 'data-pf-type'].forEach((name) => {
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
    // Only the redesign (/v2/…) for now: any other page is a normal load, so
    // Swup never pushes a history entry for a page it can't swap in.
    if (!/^\/v2(\/|$)/.test(`/${path}`)) {
      return true;
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

  // A landing's search is a plain GET form, so the next page loads in full and
  // the wait is the server's. The line, and within #swup the same fade and mark
  // as a visit, until that page replaces this one. pageshow undoes it if Back
  // brings this page back as it was left.
  const onSearch = (event) => {
    const form = event.target;
    if (event.defaultPrevented || !form.matches('.pf-landing__form') || (form.target && form.target !== '_self')) {
      return;
    }
    startLoading();
    const root = document.documentElement;
    if (form.closest(CONTAINER) && !reducedMotion.matches && !root.classList.contains('pf-a11y-motion')) {
      root.classList.add('is-changing', 'is-animating');
    }
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

  /* ---------------------------------------------------------------------
   * Morph (native visits only)
   * ------------------------------------------------------------------ */

  // A card's picture and title (the link that started the visit) glide
  // into the new page's lead picture and <h1>: each pair shares a
  // view-transition-name for the one snapshot, then the names come off.
  const MORPH_IMAGE = 'pf-morph-image';
  const MORPH_TITLE = 'pf-morph-title';
  const morphed = [];

  const nameMorph = (el, name) => {
    if (el) {
      el.style.viewTransitionName = name;
      morphed.push(el);
    }
  };

  const clearMorph = () => {
    morphed.splice(0).forEach((el) => {
      el.style.viewTransitionName = '';
    });
  };

  // Visible, and at least this share of the window wide: a lead picture,
  // not an icon or a thumbnail. fromTop: measured as it will be once the
  // new page opens at its top (Swup scrolls after the swap).
  const inView = (el, share, fromTop = false) => {
    const box = el.getBoundingClientRect();
    const top = fromTop ? box.top + window.scrollY : box.top;
    return box.width >= window.innerWidth * share && top + box.height > 0 && top < window.innerHeight;
  };

  const morphFrom = (link) => {
    if (!link || !link.closest(CONTAINER)) {
      return;
    }
    // The card the link belongs to: the link itself, or a covering link's
    // card (tiles, banners, showcase cards).
    const card = link.closest('li, article, .pf-tile, .pf-hero-banner') || link;
    const image = Array.from(card.querySelectorAll('img')).find((img) => inView(img, 0.08));
    const title = card.querySelector('h2, h3, h4, [class*="__title"]');
    nameMorph(image, MORPH_IMAGE);
    nameMorph(title && inView(title, 0) ? title : null, MORPH_TITLE);
  };

  const morphTo = () => {
    const main = document.querySelector(`${CONTAINER} main`) || document.querySelector(CONTAINER);
    if (!main) {
      return;
    }
    if (morphed.some((el) => el.style.viewTransitionName === MORPH_IMAGE)) {
      nameMorph(Array.from(main.querySelectorAll('img')).find((img) => inView(img, 0.4, true)), MORPH_IMAGE);
    }
    if (morphed.some((el) => el.style.viewTransitionName === MORPH_TITLE)) {
      const h1 = main.querySelector('h1');
      nameMorph(h1 && inView(h1, 0, true) ? h1 : null, MORPH_TITLE);
    }
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
      // View transitions where the browser has them (per visit, below).
      native: true,
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
      // Native only for a page already fetched: a view transition freezes
      // the screen until the new page is in, so a page still on its way
      // fades out with the loading line instead.
      if (visit.animation.native && !swup.cache.has(visit.to.url)) {
        visit.animation.native = false;
      }
      const native = visit.animation.animate && visit.animation.native;
      document.documentElement.classList.toggle('pf-vt', native);
      clearMorph();
      if (native && !visit.history.popstate) {
        morphFrom(visit.trigger.el);
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
        && !doc.querySelector('[data-big-pipe-placeholder-id]')
        // The mobile dock (dock.html.twig) is printed outside #swup, so a
        // page without it can't swap one in: load the dock page in full.
        && (!doc.getElementById('pf-dock') || document.getElementById('pf-dock'));
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
        // Swup has already pushed a history entry for this URL on this
        // document. assign() left two entries; replace() kept Swup's state,
        // and Chrome then treated Back as a same-document step (the URL
        // changed, the page didn't). Step back off the pushed entry first,
        // then load the page normally, as Swup's own fallback does.
        const target = `${visit.to.url}${visit.to.hash || ''}`;
        window.removeEventListener('popstate', swup.handlePopState);
        window.addEventListener('popstate', () => window.location.assign(target), { once: true });
        window.history.back();
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
      if (morphed.length) {
        morphTo();
      }
    });

    // A native visit has no CSS fade to wait for (the view transition is
    // the animation): skip Swup's wait, which would only warn.
    ['animation:out:await', 'animation:in:await'].forEach((hook) => {
      swup.hooks.before(hook, (visit, args) => {
        if (document.documentElement.classList.contains('pf-vt')) {
          args.skip = true;
        }
      });
    });

    // The names come off once the transition is over, so the next visit
    // starts clean.
    swup.hooks.on('visit:end', () => {
      clearMorph();
      document.documentElement.classList.remove('pf-vt');
    });

    swup.hooks.on('page:view', (visit) => {
      if (hardNavigation) {
        return;
      }
      stopLoading();
      focusTarget(visit.to.hash || window.location.hash);
      Drupal.announce(Drupal.t('Page loaded: @title', { '@title': document.title }));
      // Analytics: a Swup visit is a page view Google Tag Manager can't see
      // (no page load). Sent once the title and <html> labels are the new
      // page's (syncDocument()), so the view isn't filed under the last one.
      if (Array.isArray(window.dataLayer)) {
        window.dataLayer.push({
          event: 'pf_page_view',
          page_location: window.location.href,
          page_referrer: new URL(visit.from.url, window.location.href).href,
          page_title: document.title,
        });
      }
    });

    Drupal.palefireSwup = swup;
  };

  Drupal.behaviors.palefireSwup = {
    attach() {
      once('pf-loading', 'html').forEach(() => {
        addLine();
        addMark();
        document.addEventListener('click', onLeave);
        document.addEventListener('submit', onSearch);
        // Back/forward cache: the page comes back as it was left, line on.
        window.addEventListener('pageshow', (event) => {
          if (event.persisted) {
            stopLoading();
            document.documentElement.classList.remove('is-changing', 'is-animating');
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
