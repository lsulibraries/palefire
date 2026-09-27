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
 * - Assets: if the next page needs any script or stylesheet URL that is not
 *   already on this page (different libraries, or an aggregate that differs),
 *   or its content contains executable <script> tags (LibCal/other embeds),
 *   the visit falls back to a normal full page load.
 * - Head/body: <title> (Swup), meta tags, canonical/shortlink, JSON-LD,
 *   <html lang/dir>, and Drupal's path-* body classes are synced.
 * - Accessibility: focus moves to the new page's <h1> (or the #anchor
 *   target, or <main>), and the page title is announced via Drupal.announce.
 *   Transitions are skipped when the user prefers reduced motion.
 *
 * Swup is only enabled for anonymous visitors (page.html.twig attaches this
 * library when not logged in); editors get normal page loads so the toolbar,
 * contextual links, and editing UI are never swapped. Add data-no-swup to any
 * link (or an ancestor) to force a normal page load.
 */
((Drupal, drupalSettings, once) => {
  const CONTAINER = '#swup';
  // Also swapped when present, so menu active-trail classes stay correct.
  const OPTIONAL_CONTAINERS = ['#pf-primary-menu'];

  // Paths that must always get a full page load.
  const IGNORE_PATHS = [
    /^\/(admin|user|batch|system|core|modules|themes|profiles|libraries|sites)(\/|$)/,
    /^\/node\/add(\/|$)/,
    /^\/node\/\d+\/(edit|delete|revisions|layout)(\/|$)/,
    /^\/(media|taxonomy\/term)\/\d+\/(edit|delete)(\/|$)/,
    /\.(?!html?$)[a-z0-9]{2,5}$/i, // file downloads (pdf, docx, jpg, ...)
  ];

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

  const BODY_CLASS = /^(path-|page-node-type-)/;

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
    const libraries = drupalSettings.ajaxPageState && drupalSettings.ajaxPageState.libraries;
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

    const body = document.body.classList;
    Array.from(body).filter((name) => BODY_CLASS.test(name)).forEach((name) => body.remove(name));
    Array.from(doc.body.classList).filter((name) => BODY_CLASS.test(name)).forEach((name) => body.add(name));
  };

  /**
   * Closes the mobile menu before leaving, and resets its toggle afterwards
   * (the swapped-in menu always starts collapsed).
   */
  const closeMenu = () => {
    const panel = document.querySelector('#pf-primary-menu.show');
    if (panel && window.bootstrap) {
      window.bootstrap.Collapse.getOrCreateInstance(panel, { toggle: false }).hide();
    }
  };
  const resetMenuToggle = () => {
    document.querySelectorAll('[aria-controls="pf-primary-menu"]').forEach((toggle) => {
      toggle.setAttribute('aria-expanded', 'false');
      toggle.classList.add('collapsed');
    });
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

  const shouldIgnore = (url, { el } = {}) => {
    if (el) {
      if (el.closest('[data-no-swup], .use-ajax, #toolbar-administration, .contextual')) {
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
    return IGNORE_PATHS.some((pattern) => pattern.test(`/${path}`));
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
    });

    swup.hooks.on('visit:start', (visit) => {
      hardNavigation = false;
      incoming = null;
      if (reducedMotion.matches) {
        visit.animation.animate = false;
      }
      closeMenu();
    });

    // Decide whether the fetched page can be swapped in safely.
    swup.hooks.on('page:load', (visit, args) => {
      const html = args && args.page && args.page.html;
      const doc = html ? new DOMParser().parseFromString(html, 'text/html') : null;
      const settings = doc && readSettings(doc);
      const compatible = doc
        && settings
        && containers.every((sel) => doc.querySelector(sel))
        && OPTIONAL_CONTAINERS.every((sel) => containers.includes(sel) || !doc.querySelector(sel))
        && assetUrls(doc).every((url) => loadedAssets.has(url))
        && !containers.some((sel) => Array.from(doc.querySelector(sel).querySelectorAll('script')).some(isExecutable))
        && !doc.querySelector('[data-big-pipe-placeholder-id]');

      if (!compatible) {
        hardNavigation = true;
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
      resetMenuToggle();
      containers.forEach((sel) => {
        document.querySelectorAll(sel).forEach((el) => Drupal.attachBehaviors(el, drupalSettings));
      });
    });

    swup.hooks.on('page:view', (visit) => {
      if (hardNavigation) {
        return;
      }
      focusTarget(visit.to.hash || window.location.hash);
      Drupal.announce(Drupal.t('Page loaded: @title', { '@title': document.title }));
    });

    Drupal.palefireSwup = swup;
  };

  Drupal.behaviors.palefireSwup = {
    attach() {
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
