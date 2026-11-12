/**
 * @file
 * Combined Search: the motion and small touches around the results, apart
 * from index-search.js, which does the searching.
 *
 * - The term searched, over the results, rises in letter by letter.
 * - Results arrive in turn: each section's head, then its first rows.
 * - Counts count up to their number: each section's, and the Source filter's.
 * - Thumbnails fade up from a tint as they load.
 * - In Special Collections, the empty field types out searches to try.
 * - Ctrl+K (⌘K on a Mac) goes to the field from anywhere on the page, and the
 *   up and down arrows move between result titles.
 * - With nothing found, searches to try and a piece from the collections.
 *
 * Searches to try and the collection pieces are in data/search-showcase.json,
 * fetched only when needed. Nothing moves with reduced motion: rows are there
 * at once, counts show their number, and the field keeps no examples.
 */
((Drupal, drupalSettings, once) => {
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const still = () => reducedMotion.matches || document.documentElement.classList.contains('pf-a11y-motion');
  const base = (drupalSettings.path && drupalSettings.path.baseUrl) || '/';
  const SHOWCASE = `${base}themes/custom/palefire/data/search-showcase.json`;
  // Rows that arrive in turn: the rest are there with the last of them.
  const REVEAL_ROWS = 8;
  const COUNT_MS = 1000;
  // Every count that counts up: the sections' and the Source filter's.
  const COUNTS = '[data-pf-index-section-count], .pf-index__count';

  let showcase = null;
  const loadShowcase = () => {
    showcase = showcase || fetch(SHOWCASE).then((response) => (response.ok ? response.json() : {})).catch(() => ({}));
    return showcase;
  };

  const modeOf = (root) => root.querySelector('.pf-index__form input[name="mode"]')?.value || 'all';

  // Results arriving. ----------------------------------------------------------

  // A section's head, then its rows, 45ms apart. Rows index-search.js adds
  // with Show more already arrive in turn (.is-new), so they're left alone.
  const reveal = (scope) => {
    if (still()) {
      return;
    }
    const groups = scope.matches?.('.pf-index-section__late') ? [scope] : [...scope.querySelectorAll('.pf-index-section, [aria-labelledby="pf-index-results"]')];
    groups.forEach((group) => {
      const head = group.querySelector(':scope > .pf-index-section__head');
      const rows = [...group.querySelectorAll('.pf-index-row:not(.is-new, .is-rising)')].slice(0, REVEAL_ROWS);
      let order = 0;
      if (head && !head.classList.contains('is-rising') && !group.querySelector('[data-pf-index-late][aria-busy="true"]')) {
        head.classList.add('is-rising');
        head.style.setProperty('--pf-rise-order', order++);
      }
      rows.forEach((row) => {
        row.classList.add('is-rising');
        row.style.setProperty('--pf-rise-order', order++);
      });
      // The section's own fade would play under the rows' rise.
      if (rows.length) {
        group.querySelector('.pf-index-section__late.is-loaded')?.classList.add('has-rising');
      }
    });
  };

  // The term searched, letter by letter. -----------------------------------------

  // Each letter rises into place in turn, the whole term within about a second
  // however long it is. Words are kept whole on a line, unless one is wider
  // than the column. When the last letter lands, the plain text goes back, for
  // the font's own kerning. The echo is aria-hidden, so nothing is read twice.
  // The CSS holds the term back until then, and shows it after a pause if this
  // never runs.
  const ECHO_MS = 1100;
  const segment = (text) => (window.Intl?.Segmenter ? [...new Intl.Segmenter(undefined, { granularity: 'grapheme' }).segment(text)].map((part) => part.segment) : Array.from(text));
  const letterEcho = (term) => {
    const text = term.textContent.trim();
    if (still() || !text) {
      term.classList.add('is-lettered');
      return;
    }
    const letters = text.replace(/\s+/g, ' ').split(' ').map(segment);
    const count = letters.reduce((sum, word) => sum + word.length, 0);
    term.style.setProperty('--pf-letter-step', `${Math.min(55, Math.round(ECHO_MS / count))}ms`);
    let order = 0;
    let last = null;
    const words = letters.map((word) => {
      const wordSpan = document.createElement('span');
      wordSpan.className = 'pf-index__echo-word';
      word.forEach((letter) => {
        last = document.createElement('span');
        last.className = 'pf-index__echo-letter';
        last.style.setProperty('--pf-letter-order', order++);
        last.textContent = letter;
        wordSpan.append(last);
      });
      return wordSpan;
    });
    term.replaceChildren(...words.flatMap((word, i) => (i ? [' ', word] : [word])));
    term.classList.add('is-lettered');
    last.addEventListener('animationend', () => {
      term.textContent = text;
      term.style.removeProperty('--pf-letter-step');
    }, { once: true });
  };

  // Counting up. ---------------------------------------------------------------

  const format = new Intl.NumberFormat(document.documentElement.lang || 'en');

  // "13 results", "300+ results", "1,204 results": the number counts up and the
  // words stay. Screen readers get the final words only.
  // What each count was last set to, by the page or by a count in progress,
  // so a count's own steps aren't taken for a new number.
  const written = new WeakMap();

  // "13 results", "300+ results", "1,204": the number counts up from 0 and the
  // words stay. One text node throughout, so anything reading the count (the
  // results bar names the section in view with it) reads one number.
  const countUp = (element) => {
    const text = element.textContent.trim();
    const seen = written.get(element);
    if (seen && (seen.final === text || seen.step === text)) {
      return;
    }
    const match = text.match(/^([\d.,\s]*\d)(.*)$/);
    const target = match ? parseInt(match[1].replace(/\D/g, ''), 10) : NaN;
    const state = { final: text, step: '' };
    written.set(element, state);
    if (still() || !match || !(target > 1)) {
      return;
    }
    const rest = match[2];
    const start = performance.now();
    const step = (now) => {
      // Another number arrived meanwhile: that one counts instead.
      if (!element.isConnected || written.get(element) !== state) {
        return;
      }
      const t = Math.min(1, (now - start) / COUNT_MS);
      // Ease out: quick at first, settling on the number.
      const eased = 1 - (1 - t) ** 3;
      state.step = t < 1 ? `${format.format(Math.round(target * eased))}${rest}` : text;
      element.textContent = state.step;
      if (t < 1) {
        window.requestAnimationFrame(step);
      }
    };
    window.requestAnimationFrame(step);
  };

  // Thumbnails. ----------------------------------------------------------------

  const fadeUp = (image) => {
    if (image.complete && image.naturalWidth) {
      return;
    }
    image.classList.add('is-pending');
    const done = () => image.classList.remove('is-pending');
    image.addEventListener('load', done, { once: true });
    image.addEventListener('error', done, { once: true });
  };

  // Examples in the field. -----------------------------------------------------

  // Special Collections only for now: the other tabs keep a still field. Their
  // examples stay in the data file for the no-results suggestions.
  const TYPED_MODES = ['special'];

  const typeExamples = (input) => {
    const root = input.closest('[data-pf-index]');
    if (still() || input.value || !root || !TYPED_MODES.includes(modeOf(root))) {
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
    // Only once the page is idle: the field works without them.
    (window.requestIdleCallback || window.setTimeout)(() => {
      loadShowcase().then((data) => {
        examples = data.examples?.[modeOf(root)] || [];
        which = Math.floor(Math.random() * Math.max(1, examples.length));
        start();
      });
    });
  };

  // Keyboard. ------------------------------------------------------------------

  const isMac = /Mac|iPhone|iPad/.test(navigator.userAgentData?.platform || navigator.platform || '');

  const keyHint = (input) => {
    input.setAttribute('aria-keyshortcuts', 'Control+K Meta+K');
    const hint = document.createElement('kbd');
    hint.className = 'pf-index__key';
    hint.setAttribute('aria-hidden', 'true');
    hint.textContent = isMac ? '⌘K' : 'Ctrl K';
    input.after(hint);
  };

  const onKey = (event) => {
    const root = document.querySelector('[data-pf-index]');
    if (!root) {
      return;
    }
    // Ctrl+K or ⌘K: to the field, its words chosen to type over.
    if ((event.ctrlKey || event.metaKey) && !event.altKey && !event.shiftKey && event.key.toLowerCase() === 'k') {
      const input = root.querySelector('.pf-index__input');
      if (input) {
        event.preventDefault();
        input.focus();
        input.select();
      }
      return;
    }
    // Up and down between result titles, from a title.
    if ((event.key !== 'ArrowDown' && event.key !== 'ArrowUp') || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
      return;
    }
    const current = event.target.closest?.('.pf-index-row__title a');
    if (!current || !root.contains(current)) {
      return;
    }
    const titles = [...root.querySelectorAll('.pf-index-row__title a')].filter((link) => link.offsetParent !== null);
    const next = titles[titles.indexOf(current) + (event.key === 'ArrowDown' ? 1 : -1)];
    if (next) {
      event.preventDefault();
      next.focus();
      next.scrollIntoView({ block: 'nearest', behavior: still() ? 'auto' : 'smooth' });
    }
  };

  // Nothing found. -------------------------------------------------------------

  // Every list says 0 and none is still on its way. A section that couldn't
  // reach its service doesn't count as empty: it says so itself.
  const allEmpty = (root) => {
    const main = root.querySelector('[data-pf-index-main]');
    if (!main || main.querySelector('[data-pf-index-late][aria-busy="true"], .pf-index-answer')) {
      return false;
    }
    const lists = [...main.querySelectorAll('.pf-index-list')].filter((list) => !list.closest('[hidden]'));
    const late = [...main.querySelectorAll('[data-pf-index-late]')].filter((holder) => !holder.closest('[hidden]') && !holder.querySelector('.pf-index-list'));
    return !late.length && lists.every((list) => list.dataset.pfIndexTotal === '0' && !list.hasAttribute('data-pf-index-failed'));
  };

  const fillEmpty = (root, empty) => {
    if (empty.dataset.pfFilled) {
      return;
    }
    empty.dataset.pfFilled = '1';
    loadShowcase().then((data) => {
      const mode = modeOf(root);
      const query = (root.querySelector('.pf-index__input')?.value || '').trim().toLowerCase();
      const action = root.querySelector('.pf-index__form')?.getAttribute('action') || '';
      const tries = (data.examples?.[mode] || data.examples?.all || []).filter((word) => word.toLowerCase() !== query).slice(0, 4);
      const tryBox = empty.querySelector('[data-pf-index-try]');
      if (tryBox && tries.length) {
        const label = document.createElement('p');
        label.className = 'pf-index-empty__label';
        label.id = 'pf-index-try-label';
        label.textContent = Drupal.t('Try');
        const list = document.createElement('ul');
        list.setAttribute('role', 'list');
        list.setAttribute('aria-labelledby', label.id);
        tries.forEach((word) => {
          const params = new URLSearchParams({ q: word });
          if (mode !== 'all') {
            params.set('mode', mode);
          }
          const item = document.createElement('li');
          const link = document.createElement('a');
          link.href = `${action}?${params}`;
          link.textContent = word;
          item.append(link);
          list.append(item);
        });
        tryBox.replaceChildren(label, list);
        tryBox.hidden = false;
      }
      const pieces = data.vault || [];
      const vault = empty.querySelector('[data-pf-index-vault]');
      if (!vault || !pieces.length) {
        return;
      }
      const piece = pieces[Math.floor(Math.random() * pieces.length)];
      const figure = document.createElement('figure');
      figure.className = 'pf-index-vault';
      const frame = document.createElement('a');
      frame.className = 'pf-index-vault__frame';
      frame.href = piece.url;
      frame.tabIndex = -1;
      frame.setAttribute('aria-hidden', 'true');
      const image = document.createElement('img');
      Object.assign(image, { src: piece.image, alt: '', width: piece.width, height: piece.height, loading: 'lazy', decoding: 'async' });
      frame.append(image);
      const caption = document.createElement('figcaption');
      caption.className = 'pf-index-vault__caption';
      const kicker = document.createElement('p');
      kicker.className = 'pf-index-empty__label';
      kicker.textContent = Drupal.t('From the collections');
      const title = document.createElement('p');
      title.className = 'pf-index-vault__title';
      title.textContent = piece.title;
      caption.append(kicker, title);
      if (piece.credit) {
        const credit = document.createElement('p');
        credit.className = 'pf-index-vault__credit';
        credit.textContent = piece.credit;
        caption.append(credit);
      }
      const link = document.createElement('a');
      link.className = 'pf-index-vault__link';
      link.href = piece.url;
      link.innerHTML = `${Drupal.checkPlain(Drupal.t('See it in @exhibition', { '@exhibition': piece.exhibition }))}<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 12h15M13 6l6 6-6 6"/></svg>`;
      caption.append(link);
      figure.append(frame, caption);
      fadeUp(image);
      vault.replaceChildren(figure);
      vault.hidden = false;
    });
  };

  const checkEmpty = (root) => {
    const empty = root.querySelector('[data-pf-index-empty]');
    if (!empty) {
      return;
    }
    if (!empty.hidden || allEmpty(root)) {
      if (empty.hidden) {
        empty.hidden = false;
        Drupal.announce(Drupal.t('No results.'));
      }
      fillEmpty(root, empty);
    }
  };

  // Watching. ------------------------------------------------------------------

  // Late sections and counts come in without a behaviors pass (index-search.js
  // fills them in place), so one observer per page picks them up.
  const watch = (root) => {
    let frame = 0;
    const pending = new Set();
    const flush = () => {
      frame = 0;
      pending.forEach((node) => {
        if (!node.isConnected) {
          return;
        }
        if (node.matches('.pf-index-section__late')) {
          reveal(node);
        }
        node.querySelectorAll(COUNTS).forEach(countUp);
        if (node.matches(COUNTS)) {
          countUp(node);
        }
        node.querySelectorAll('.pf-index-row__thumb img').forEach(fadeUp);
      });
      pending.clear();
      checkEmpty(root);
    };
    new MutationObserver((records) => {
      records.forEach((record) => {
        const target = record.target.nodeType === Node.ELEMENT_NODE ? record.target : record.target.parentElement;
        const count = target?.closest(COUNTS);
        const late = target?.closest('.pf-index-section__late');
        if (count) {
          pending.add(count);
        }
        else if (late) {
          pending.add(late);
        }
        else if (target && record.type === 'childList') {
          record.addedNodes.forEach((node) => node.nodeType === Node.ELEMENT_NODE && pending.add(node));
        }
        if (record.type === 'attributes') {
          pending.add(root);
        }
      });
      if (!frame) {
        frame = window.requestAnimationFrame(flush);
      }
    }).observe(root, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['hidden', 'aria-busy'] });
  };

  // Not on the page's first load: its rows are already painted by then, and
  // would blink out to rise again. After a search in place, and for every
  // late section, they do.
  const setUpMain = (main, first) => {
    const root = main.closest('[data-pf-index]');
    if (!first) {
      reveal(main);
    }
    main.querySelectorAll(COUNTS).forEach(countUp);
    main.querySelectorAll('.pf-index-row__thumb img').forEach(fadeUp);
    if (root) {
      checkEmpty(root);
    }
  };

  Drupal.behaviors.palefireIndexFlair = {
    attach(context) {
      once('pf-index-flair-keys', 'html').forEach(() => document.addEventListener('keydown', onKey));
      once('pf-index-flair', '[data-pf-index]', context).forEach(watch);
      once('pf-index-flair-echo', '.pf-index__echo-term', context).forEach(letterEcho);
      once('pf-index-flair-main', '[data-pf-index-main]', context).forEach((main) => setUpMain(main, context === document));
      once('pf-index-flair-input', '.pf-index__input', context).forEach((input) => {
        keyHint(input);
        typeExamples(input);
      });
    },
  };
})(Drupal, drupalSettings, once);
