/**
 * @file
 * A landing hero's search field, as Combined Search's (js/index-flair.js):
 *
 * - The empty field types out searches to try ([data-pf-examples], the mode
 *   whose examples it takes from data/search-showcase.json), fetched once
 *   the page is idle. It stops while the field is focused or has words, and
 *   with reduced motion the field keeps its own placeholder.
 * - Ctrl+K (⌘K on a Mac) goes to the field ([data-pf-search-key]) from
 *   anywhere on the page, its words chosen to type over, with a key hint at
 *   the field's end for a mouse on a wide screen (_landing-features.scss).
 *   Not while a drawer or dialog is open, and not on Combined Search, which
 *   has its own.
 */
((Drupal, drupalSettings, once) => {
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const still = () => reducedMotion.matches || document.documentElement.classList.contains('pf-a11y-motion');
  const base = (drupalSettings.path && drupalSettings.path.baseUrl) || '/';
  const SHOWCASE = `${base}themes/custom/palefire/data/search-showcase.json`;
  const isMac = /Mac|iPhone|iPad/.test(navigator.userAgentData?.platform || navigator.platform || '');

  // Searches to try. -------------------------------------------------------------

  const typeExamples = (input) => {
    if (still() || input.value) {
      return;
    }
    const original = input.getAttribute('placeholder') || '';
    let timer = 0;
    let running = false;
    let examples = [];
    let which = 0;
    const wait = (ms, next) => {
      timer = window.setTimeout(next, ms);
    };
    const stop = () => {
      running = false;
      window.clearTimeout(timer);
      input.setAttribute('placeholder', original);
    };
    const type = (word, length) => {
      if (!running) {
        return;
      }
      input.setAttribute('placeholder', word.slice(0, length));
      if (length < word.length) {
        wait(70, () => type(word, length + 1));
      }
      else {
        wait(2000, () => erase(word, length));
      }
    };
    const erase = (word, length) => {
      if (!running) {
        return;
      }
      input.setAttribute('placeholder', word.slice(0, length));
      if (length > 0) {
        wait(28, () => erase(word, length - 1));
      }
      else {
        which = (which + 1) % examples.length;
        wait(450, () => type(examples[which], 1));
      }
    };
    const start = () => {
      if (!input.isConnected || running || input.value || document.activeElement === input || document.hidden || !examples.length || still()) {
        return;
      }
      running = true;
      wait(600, () => type(examples[which], 1));
    };
    input.addEventListener('focus', stop);
    input.addEventListener('input', stop);
    input.addEventListener('blur', start);
    document.addEventListener('visibilitychange', () => (document.hidden ? stop() : start()));
    reducedMotion.addEventListener?.('change', () => (still() ? stop() : start()));
    (window.requestIdleCallback || window.setTimeout)(() => {
      fetch(SHOWCASE)
        .then((response) => (response.ok ? response.json() : {}))
        .catch(() => ({}))
        .then((data) => {
          examples = data.examples?.[input.dataset.pfExamples] || [];
          which = Math.floor(Math.random() * Math.max(1, examples.length));
          start();
        });
    });
  };

  // Ctrl+K. -----------------------------------------------------------------------

  const fields = [];

  const keyHint = (input) => {
    input.setAttribute('aria-keyshortcuts', 'Control+K Meta+K');
    const hint = document.createElement('span');
    hint.className = 'pf-landing__key';
    hint.setAttribute('aria-hidden', 'true');
    const key = document.createElement('kbd');
    key.textContent = isMac ? '⌘K' : 'Ctrl K';
    hint.append(key);
    input.after(hint);
    fields.push(input);
  };

  const onKey = (event) => {
    if (!(event.ctrlKey || event.metaKey) || event.altKey || event.shiftKey || event.key.toLowerCase() !== 'k') {
      return;
    }
    // Combined Search's own (index-flair.js), or a drawer or dialog open.
    if (document.querySelector('[data-pf-index], dialog[open], .offcanvas.show, [aria-modal="true"]:not([hidden])')) {
      return;
    }
    const input = fields.find((field) => field.isConnected && !field.disabled && field.offsetParent !== null);
    if (input) {
      event.preventDefault();
      input.focus();
      input.select();
    }
  };

  Drupal.behaviors.palefireSearchField = {
    attach(context) {
      once('pf-search-field-examples', '[data-pf-examples]', context).forEach(typeExamples);
      once('pf-search-field-key', '[data-pf-search-key]', context).forEach(keyHint);
      once('pf-search-field-keys', 'html').forEach(() => document.addEventListener('keydown', onKey));
    },
  };
})(Drupal, drupalSettings, once);
