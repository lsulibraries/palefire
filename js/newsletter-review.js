/**
 * @file
 * Newsletter review. Markup: includes/newsletter-review.html.twig.
 *
 * The email is in a sandboxed frame from the same origin (srcdoc), so this
 * script can read it: it sizes the frame to the email, so the page scrolls
 * and the frame never does, and splits the email into sections at its
 * headlines (h1, h2). Each section gets a Comment button beside it; the
 * photo and kicker above a headline belong to its section. Choosing one
 * fills the form's Section field and frames the section.
 *
 * ?for=<words> (e.g. ?for=lipsey, ?for=paolo-chirumbolo) scrolls to the
 * story whose headline has all those words, or else the smallest block of
 * the email that does, and marks it "Your story", so someone featured lands
 * on their own part. Unmatched words do nothing.
 *
 * Below 63rem the form is a popover sheet, opened from a bar at the foot of
 * the screen or by a Comment button.
 */
(function (Drupal, once, debounce) {
  'use strict';

  // The form beside a full 600px email: 40rem frame, 1rem plate edges, 2rem
  // gap, 21rem form (newsletter-review.scss).
  const NARROW_REM = 63;

  const fold = (s) => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();

  function forWords() {
    const raw = new URLSearchParams(window.location.search).get('for');
    if (!raw) {
      return [];
    }
    return fold(raw).split(/[^a-z0-9]+/).filter((w) => w.length > 1).slice(0, 6);
  }

  // The email's own height: the bottom of its last block, not the document's
  // scrollHeight, which never drops below the frame's current height.
  function height(doc) {
    const bottoms = Array.from(doc.body.children).map((el) => el.getBoundingClientRect().bottom);
    const margin = parseFloat(doc.defaultView.getComputedStyle(doc.body).marginBottom) || 0;
    return Math.ceil(Math.max(0, ...bottoms) + doc.defaultView.scrollY + margin);
  }

  // Sections: [{label, top, bottom}] in the frame's own pixels.
  function sections(doc, end) {
    const items = Array.from(doc.querySelectorAll('img, h1, h2, h3, p, li, td > div')).filter((el) => el.getClientRects().length);
    const heads = items.filter((el) => /^H[12]$/.test(el.tagName) && fold(el.textContent).length > 1);
    const top = (el) => el.getBoundingClientRect().top + doc.defaultView.scrollY;
    const list = [{ label: Drupal.t('Opening'), top: 0, head: null }];
    heads.forEach((head) => {
      // Walk back over the photo and kicker that lead into the headline.
      let start = head;
      for (let i = items.indexOf(head) - 1; i >= 0; i--) {
        const el = items[i];
        if (el.contains(head) || head.contains(el)) {
          continue;
        }
        const text = fold(el.textContent);
        // A photo, or a short kicker ("EVENT RECAP"); never a link, such as
        // the story above's "Read more".
        const lead = el.tagName === 'IMG' || (text.length > 0 && text.length < 40 && !/^H[12]$/.test(el.tagName) && !el.querySelector('a') && !el.closest('a'));
        if (!lead || (list.length > 1 && top(el) < list[list.length - 1].top)) {
          break;
        }
        start = el;
      }
      list.push({ label: head.textContent.replace(/\s+/g, ' ').trim(), top: Math.min(top(start), top(head)), head });
    });
    list.forEach((s, i) => {
      s.bottom = i + 1 < list.length ? list[i + 1].top : end;
    });
    // A section header with nothing under it (a heading row straight before
    // the next) joins the section after it.
    return list.filter((s, i) => s.bottom - s.top >= 60 || i === list.length - 1);
  }

  // What ?for= names: a section by its headline, else the smallest block.
  function findStory(doc, list, words) {
    const has = (text) => words.every((w) => fold(text).includes(w));
    const section = list.find((s) => s.head && has(s.label));
    if (section) {
      return { section, top: section.top, bottom: section.bottom };
    }
    const blocks = Array.from(doc.querySelectorAll('table.layout, td')).filter((el) => has(el.textContent) && el.getClientRects().length);
    if (!blocks.length) {
      return null;
    }
    blocks.sort((a, b) => a.textContent.length - b.textContent.length);
    const rect = blocks[0].getBoundingClientRect();
    const y = rect.top + doc.defaultView.scrollY;
    const owner = list.filter((s) => s.top <= y + 1).pop() || list[0];
    return { section: owner, top: y, bottom: y + rect.height };
  }

  Drupal.behaviors.pfNewsletterReview = {
    attach(context) {
      once('pf-nlr', '[data-pf-nlr]', context).forEach((root) => {
        const frame = root.querySelector('[data-pf-nlr-frame]');
        const pins = root.querySelector('[data-pf-nlr-pins]');
        const panel = root.querySelector('[data-pf-nlr-panel]');
        const opener = root.querySelector('[data-pf-nlr-open]');
        const closer = root.querySelector('[data-pf-nlr-close]');
        const forNote = root.querySelector('[data-pf-nlr-for]');
        const words = forWords();
        let list = [];
        let active = null;
        let own = null;
        let narrow = false;
        let returnTo = null;
        let scrolled = false;
        let following = true;

        const field = (name) => panel.querySelector('[name="' + name + '"]');

        // The form: beside the email, or a sheet. ------------------------------
        function setMode() {
          const rem = parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
          const next = root.clientWidth < NARROW_REM * rem;
          if (next === narrow && root.classList.contains('is-set')) {
            return;
          }
          narrow = next;
          root.classList.add('is-set');
          root.classList.toggle('is-narrow', narrow);
          if (narrow && 'showPopover' in panel) {
            panel.setAttribute('popover', 'auto');
          }
          else {
            if (panel.matches(':popover-open')) {
              panel.hidePopover();
            }
            panel.removeAttribute('popover');
          }
        }

        function openPanel(from, focusField) {
          if (narrow && panel.hasAttribute('popover')) {
            returnTo = from || opener;
            if (!panel.matches(':popover-open')) {
              panel.showPopover();
            }
          }
          const target = (focusField && field('comment')) || panel;
          target.focus({ preventScroll: !narrow });
        }

        if (panel) {
          panel.addEventListener('toggle', (e) => {
            const open = e.newState === 'open';
            opener.setAttribute('aria-expanded', String(open));
            if (!open && returnTo && document.activeElement && (panel.contains(document.activeElement) || document.activeElement === document.body)) {
              returnTo.focus({ preventScroll: true });
            }
          });
          opener.addEventListener('click', () => openPanel(opener, false));
          closer.addEventListener('click', () => panel.hidePopover && panel.matches(':popover-open') && panel.hidePopover());
          setMode();
          root.classList.add('is-ready');
        }

        if (!frame || !pins) {
          return;
        }

        // Frames drawn over the email. ------------------------------------------
        function mark(kind) {
          let el = pins.querySelector('.pf-nlr__mark--' + kind);
          if (!el) {
            el = document.createElement('li');
            el.className = 'pf-nlr__mark pf-nlr__mark--' + kind;
            el.setAttribute('aria-hidden', 'true');
            if (kind === 'own') {
              const label = document.createElement('span');
              label.className = 'pf-nlr__mark-label';
              label.textContent = Drupal.t('Your story');
              el.append(label);
            }
            pins.append(el);
          }
          return el;
        }

        function place(el, top, bottom) {
          el.style.top = (frame.offsetTop + top) + 'px';
          el.style.height = Math.max(0, bottom - top) + 'px';
        }

        function choose(section, button) {
          active = section;
          pins.querySelectorAll('.pf-nlr__pin-button').forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
          const input = field('section');
          if (input) {
            input.value = section.label;
            input.dispatchEvent(new Event('change', { bubbles: true }));
          }
          const el = mark('active');
          place(el, section.top, section.bottom);
          el.classList.add('is-on');
          openPanel(button, true);
        }

        // Comment buttons, one per section. -------------------------------------
        function draw() {
          const doc = frame.contentDocument;
          if (!doc || !doc.body) {
            return;
          }
          const h = height(doc);
          frame.style.height = h + 'px';
          list = sections(doc, h);
          pins.querySelectorAll('.pf-nlr__pin').forEach((li) => li.remove());
          list.forEach((section, i) => {
            const li = document.createElement('li');
            li.className = 'pf-nlr__pin';
            li.style.top = (frame.offsetTop + section.top + 8) + 'px';
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'pf-nlr__pin-button';
            button.setAttribute('aria-pressed', String(active && active.label === section.label));
            button.innerHTML = '<i class="far fa-comment" aria-hidden="true"></i><span class="pf-nlr__pin-words"></span>';
            button.lastChild.textContent = Drupal.t('Comment');
            button.setAttribute('aria-label', Drupal.t('Comment on section @n: @label', { '@n': i + 1, '@label': section.label }));
            button.addEventListener('click', () => choose(section, button));
            li.append(button);
            pins.append(li);
          });
          if (active) {
            const same = list.find((s) => s.label === active.label);
            if (same) {
              active = same;
              place(mark('active'), same.top, same.bottom);
            }
          }
          if (words.length) {
            own = findStory(doc, list, words);
            if (own) {
              const el = mark('own');
              place(el, own.top, own.bottom);
              el.classList.add('is-on');
              if (forNote && forNote.hidden) {
                forNote.textContent = Drupal.t('Your part is marked in the email: “@label”.', { '@label': own.section.label });
                forNote.hidden = false;
                const input = field('section');
                if (input && !input.value) {
                  input.value = own.section.label;
                }
              }
              if (!scrolled) {
                scrolled = true;
                // A jump, not a glide: photos above the story are still
                // arriving and push it down, so it is kept in place until
                // they settle or the reader scrolls. After the page has
                // loaded, or the browser's scroll restoration undoes it.
                const stop = () => {
                  following = false;
                };
                ['wheel', 'touchstart', 'keydown', 'mousedown'].forEach((type) => window.addEventListener(type, stop, { once: true, passive: true }));
                window.setTimeout(stop, 6000);
                if (document.readyState === 'complete') {
                  window.setTimeout(goToOwn, 0);
                }
                else {
                  window.addEventListener('load', () => window.setTimeout(goToOwn, 0), { once: true });
                }
              }
              else if (following) {
                goToOwn();
              }
            }
          }
        }

        function goToOwn() {
          if (!own || !following) {
            return;
          }
          const header = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--pf-header-h')) || 50;
          const y = frame.getBoundingClientRect().top + window.scrollY + own.top - header - 48;
          window.scrollTo({ top: Math.max(0, y), behavior: 'instant' });
        }

        const redraw = debounce(draw, 120);

        function ready() {
          const doc = frame.contentDocument;
          if (!doc || !doc.body) {
            return;
          }
          draw();
          // Photos arrive after the text: follow the email's height.
          const Observer = frame.contentWindow.ResizeObserver || window.ResizeObserver;
          if (Observer) {
            const watch = new Observer(redraw);
            Array.from(doc.body.children).forEach((el) => watch.observe(el));
          }
        }

        frame.addEventListener('load', ready);
        if (frame.contentDocument && frame.contentDocument.readyState === 'complete' && frame.contentDocument.body && frame.contentDocument.body.childElementCount) {
          ready();
        }
        if ('ResizeObserver' in window) {
          new ResizeObserver(debounce(() => {
            setMode();
            draw();
          }, 120)).observe(root);
        }
      });
    },
  };

  /**
   * Archive (includes/newsletter-archive.html.twig): a card opens its issue
   * in a dialog on the page, framed from Constant Contact's preview with no
   * scripts, rather than in a new tab. The frame loads only when opened.
   */
  Drupal.behaviors.pfNewsletterArchive = {
    attach(context) {
      once('pf-nla', '[data-pf-nla]', context).forEach((root) => {
        const dialog = root.querySelector('[data-pf-nla-dialog]');
        if (!dialog || typeof dialog.showModal !== 'function') {
          return;
        }
        const frame = dialog.querySelector('[data-pf-nla-frame]');
        const title = dialog.querySelector('[data-pf-nla-title]');
        const own = dialog.querySelector('[data-pf-nla-own]');
        let opener = null;
        root.querySelectorAll('[data-pf-nla-open]').forEach((card) => {
          // It opens here now, not in a new tab.
          card.querySelectorAll('[data-pf-nla-tab]').forEach((note) => note.remove());
          card.addEventListener('click', (e) => {
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.button > 0) {
              return;
            }
            e.preventDefault();
            opener = card;
            title.textContent = card.dataset.name;
            frame.title = Drupal.t('Newsletter: @name', { '@name': card.dataset.name });
            own.href = card.href;
            if (frame.getAttribute('src') !== card.href) {
              frame.src = card.href;
            }
            dialog.showModal();
            dialog.querySelector('[data-pf-nla-close]').focus();
          });
        });
        // Back to the issue it was opened from. Also straight after close():
        // the close event comes a task later, and the shelf may have moved.
        const back = () => {
          if (opener && !dialog.open) {
            opener.focus({ preventScroll: true });
          }
        };
        const shut = () => {
          dialog.close();
          back();
        };
        dialog.querySelector('[data-pf-nla-close]').addEventListener('click', shut);
        // A click on the dimmed page closes it.
        dialog.addEventListener('click', (e) => {
          if (e.target === dialog) {
            shut();
          }
        });
        // Escape.
        dialog.addEventListener('close', back);
      });
    },
  };

  /**
   * Before and after (includes/newsletter-compare.html.twig): both emails at
   * their full height, the later one shown right of the cut. The range input
   * and a drag across the emails move the cut.
   */
  Drupal.behaviors.pfNewsletterCompare = {
    attach(context) {
      once('pf-nlc', '[data-pf-nlc]', context).forEach((root) => {
        const stage = root.querySelector('[data-pf-nlc-stage]');
        const range = root.querySelector('[data-pf-nlc-range]');
        const frames = Array.from(root.querySelectorAll('[data-pf-nlc-frame]'));
        const set = (value) => {
          const v = Math.max(0, Math.min(100, value));
          root.style.setProperty('--pf-nlc-cut', v + '%');
          range.value = String(Math.round(v));
        };
        range.addEventListener('input', () => set(Number(range.value)));

        // Drag, or tap, across the emails.
        let dragging = false;
        const at = (e) => {
          const rect = stage.getBoundingClientRect();
          set(((e.clientX - rect.left) / rect.width) * 100);
        };
        stage.addEventListener('pointerdown', (e) => {
          dragging = true;
          stage.setPointerCapture(e.pointerId);
          at(e);
        });
        stage.addEventListener('pointermove', (e) => {
          if (dragging) {
            at(e);
          }
        });
        ['pointerup', 'pointercancel'].forEach((type) => stage.addEventListener(type, () => {
          dragging = false;
        }));

        // Each frame as tall as its email; the stage as tall as the longer.
        const size = () => {
          let tallest = 0;
          frames.forEach((frame) => {
            const doc = frame.contentDocument;
            if (doc && doc.body && doc.body.childElementCount) {
              const h = height(doc);
              frame.style.height = h + 'px';
              tallest = Math.max(tallest, h);
            }
          });
          if (tallest) {
            stage.style.height = tallest + 'px';
          }
        };
        const resize = debounce(size, 120);
        frames.forEach((frame) => {
          const watch = () => {
            size();
            const Observer = frame.contentWindow.ResizeObserver || window.ResizeObserver;
            if (Observer) {
              const observer = new Observer(resize);
              Array.from(frame.contentDocument.body.children).forEach((el) => observer.observe(el));
            }
          };
          frame.addEventListener('load', watch);
          if (frame.contentDocument && frame.contentDocument.readyState === 'complete' && frame.contentDocument.body && frame.contentDocument.body.childElementCount) {
            watch();
          }
        });
        if ('ResizeObserver' in window) {
          new ResizeObserver(resize).observe(stage);
        }
      });
    },
  };
})(Drupal, once, Drupal.debounce);
