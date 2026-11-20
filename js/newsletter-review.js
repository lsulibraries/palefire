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
 * Below 78.25rem the form is a popover sheet, opened from a bar at the foot of
 * the screen or by a Comment button.
 *
 * A section with approved notes or thumbs gets a chip under its Comment
 * button ("2 notes · 3 thumbs"); the chip opens a card right below it with
 * that section's thumbs and notes, moved there from Notes and thumbs under
 * the draft (which keeps the whole issue and any section no longer in the
 * email). A note finds its story by headline whatever the case, quotes,
 * dashes or end punctuation, or by the story's kicker ("Oral history") when
 * only one story has it; notes under a second name for a story join its
 * card, and notes that name no story stay in the list. A card shows its first two notes whole, the rest behind Show
 * more (n). In the margin beside the email the cards start open, and a long
 * one moves the Comment buttons below it down so none overlap; with no
 * margin they open over the email only when asked.
 */
(function (Drupal, once, debounce) {
  'use strict';

  // The form beside the email: an 18rem form and 1.5rem gap leave the stage
  // its 58.75rem, the full email with the notes cards open in the margin
  // (newsletter-review.scss). Narrower, the form is the sheet. It was 56rem,
  // where the cards had no margin and covered half the email.
  const NARROW_REM = 78.25;

  // Drupal.t() escapes @ placeholders for HTML; as text or an attribute,
  // "Research &amp; Collections" would be read out with its "amp".
  const plain = (html) => new DOMParser().parseFromString(html, 'text/html').documentElement.textContent;

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
      // Walk back over the photo and kicker that lead into the headline,
      // keeping the kicker's words: a note may name the story by them.
      let start = head;
      const kickers = [];
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
        if (el.tagName !== 'IMG') {
          kickers.push(el.textContent.replace(/\s+/g, ' ').trim());
        }
      }
      list.push({ label: head.textContent.replace(/\s+/g, ' ').trim(), top: Math.min(top(start), top(head)), head, kickers });
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
        const feedbackMark = opener ? opener.querySelector('.pf-fa') : null;
        const words = forWords();
        let list = [];
        let active = null;
        let own = null;
        let narrow = false;
        // Whether the cards had the margin beside the email last time.
        let margin = null;
        let returnTo = null;
        let scrolled = false;
        let returned = false;
        let following = true;

        const field = (name) => panel.querySelector('[name="' + name + '"]');

        // Notes and thumbs by section, as cards for the Comment buttons. ------
        const cards = new Map();
        root.querySelectorAll('.pf-nlr__public .pf-nlr__group').forEach((group, i) => {
          const votes = group.querySelector('[data-pf-pn-votes]');
          const about = votes ? votes.dataset.about || '' : '';
          if (!about) {
            return;
          }
          const card = document.createElement('div');
          card.className = 'pf-nlr__card';
          card.id = (root.id || 'pf-nlr') + '-card-' + i;
          card.hidden = true;
          const notes = group.querySelector('[data-pf-pn-notes]');
          // Where the thumbs and notes go back to if no section takes them
          // (unplaced()).
          const marks = [votes, notes].filter(Boolean).map((el) => {
            const mark = document.createComment('');
            el.before(mark);
            return [mark, el];
          });
          card.append(votes);
          const body = document.createElement('div');
          body.className = 'pf-nlr__card-notes';
          if (notes) {
            body.append(notes);
          }
          card.append(body);
          const more = document.createElement('button');
          more.type = 'button';
          more.className = 'pf-nlr__card-more';
          more.hidden = true;
          more.setAttribute('aria-expanded', 'false');
          more.addEventListener('click', () => {
            const full = !card.classList.contains('is-full');
            card.classList.toggle('is-full', full);
            fit(card);
            if (!full) {
              more.focus();
            }
          });
          card.append(more);
          // Resolved (n) opened or closed: cut again (toggle doesn't bubble).
          card.addEventListener('toggle', () => fit(card), true);
          group.hidden = true;
          cards.set(about, { card, notes, group, marks });
        });

        // A note's section, as written, to the story it's about: the same
        // words whatever the case, quotes, dashes, spacing or end
        // punctuation ("Oral history." is "Oral history"), and failing that
        // the story whose kicker it names ("EVENT RECAP"), when only one
        // story has that kicker. null when none.
        const key = (s) => fold(s)
          .replace(/[‘’‚‛ʼ`´]/g, '\'')
          .replace(/[“”„‟]/g, '"')
          .replace(/[‐‑‒–—―−]/g, '-')
          .replace(/^[\s'"]+|[\s.,;:!?…'"-]+$/g, '');
        function sectionFor(about) {
          const k = key(about);
          const named = list.find((s) => key(s.label) === k);
          if (named) {
            return named;
          }
          const kicked = list.filter((s) => (s.kickers || []).some((kicker) => key(kicker) === k));
          return kicked.length === 1 ? kicked[0] : null;
        }

        // A card no section took: its thumbs and notes go back under its
        // heading in Notes and thumbs below the draft, for good.
        function unplaced(about, entry) {
          entry.marks.forEach(([mark, el]) => mark.replaceWith(el));
          entry.group.hidden = false;
          entry.card.remove();
          cards.delete(about);
        }

        // A second name for a story that already has a card ("Oral history"
        // beside its headline): the notes join that card; the thumbs, given
        // to the other name, stay below, and only if there are any.
        function merged(about, entry, into) {
          if (entry.notes) {
            into.card.querySelector('.pf-nlr__card-notes').append(entry.notes);
          }
          entry.marks.forEach(([mark, el]) => {
            if (el === entry.notes) {
              mark.remove();
            }
            else {
              mark.replaceWith(el);
            }
          });
          const thumbs = Array.from(entry.group.querySelectorAll('[data-pf-pn-count]')).some((c) => (parseInt(c.textContent, 10) || 0) > 0);
          entry.group.hidden = !thumbs;
          entry.card.remove();
          cards.delete(about);
        }

        // "2 notes · 3 thumbs", from the card's own counts, so a thumb just
        // given shows at once.
        function tally(entry) {
          // All the card's notes, merged ones too (merged()).
          const n = entry.card.querySelectorAll('.pf-pn__n').length;
          let thumbs = 0;
          entry.card.querySelectorAll('[data-pf-pn-count]').forEach((c) => {
            thumbs += parseInt(c.textContent, 10) || 0;
          });
          const parts = [];
          if (n) {
            parts.push(n === 1 ? Drupal.t('1 note') : Drupal.t('@count notes', { '@count': n }));
          }
          if (thumbs || !n) {
            parts.push(thumbs === 1 ? Drupal.t('1 thumb') : Drupal.t('@count thumbs', { '@count': thumbs }));
          }
          return parts.join(' · ');
        }

        // The first notes shown whole, the rest behind Show more (n).
        const SHOWN = 2;
        function fit(card) {
          const li = card.closest('.pf-nlr__pin');
          const body = card.querySelector('.pf-nlr__card-notes');
          const more = card.querySelector('.pf-nlr__card-more');
          body.style.maxHeight = '';
          body.classList.remove('is-cut');
          if (!li || card.hidden) {
            stack();
            return;
          }
          // Only notes showing count: not those in a folded Resolved.
          const items = Array.from(body.querySelectorAll('.pf-pn__n')).filter((item) => item.offsetHeight);
          const over = items.length > SHOWN;
          const full = card.classList.contains('is-full');
          more.hidden = !over;
          if (!over || full) {
            more.textContent = Drupal.t('Show less');
            more.setAttribute('aria-expanded', 'true');
            stack();
            return;
          }
          const last = items[SHOWN - 1].getBoundingClientRect();
          body.style.maxHeight = Math.ceil(last.bottom - body.getBoundingClientRect().top + 8) + 'px';
          body.classList.add('is-cut');
          more.textContent = Drupal.t('Show more (@count)', { '@count': items.length - SHOWN });
          more.setAttribute('aria-expanded', 'false');
          stack();
        }

        // In the margin, where cards start open: each Comment button at its
        // section, or just below the card above it when that runs longer,
        // so open cards never overlap. Over the email, cards open on top.
        function stack() {
          let below = 0;
          pins.querySelectorAll('.pf-nlr__pin').forEach((li) => {
            const own = parseFloat(li.dataset.top) || 0;
            const top = margin ? Math.max(own, below) : own;
            li.style.top = top + 'px';
            below = top + li.offsetHeight + 12;
          });
        }

        function toggleCard(chip, card, open) {
          card.hidden = !open;
          chip.setAttribute('aria-expanded', String(open));
          card.closest('.pf-nlr__pin').classList.toggle('is-open', open);
          fit(card);
        }

        root.addEventListener('keydown', (e) => {
          const card = e.key === 'Escape' && e.target.closest && e.target.closest('.pf-nlr__card:not([hidden])');
          if (card) {
            const chip = root.querySelector('[aria-controls="' + card.id + '"]');
            toggleCard(chip, card, false);
            chip.focus();
          }
        });
        // After js/public-notes.js has counted a thumb (its listener is on
        // the same root, added first), or Show notes has hidden the notes.
        root.addEventListener('click', (e) => {
          if (!e.target.closest('[data-pf-pn-vote], [data-pf-pn-toggle]')) {
            return;
          }
          cards.forEach((entry) => {
            const chip = root.querySelector('[aria-controls="' + entry.card.id + '"] .pf-nlr__chip-words');
            if (chip) {
              chip.textContent = tally(entry);
            }
            fit(entry.card);
          });
        });

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
          // Cards start open in the margin beside the email (a 56.75rem stage,
          // newsletter-review.scss); with no margin they'd cover the email,
          // so they open only when asked. Set again only when that changes,
          // so a card the reader closed stays closed.
          // The stylesheet sets the card's width only where there's a margin.
          const wide = getComputedStyle(pins).getPropertyValue('--pf-nlr-card-w').trim() !== '';
          if (wide !== margin) {
            margin = wide;
            cards.forEach((entry) => {
              entry.card.hidden = !wide;
            });
          }
          // Each card to its section, by name first, then by kicker: one card
          // a section, which takes the notes of any other name for it; cards
          // no section takes go back to the list below.
          const bySection = new Map();
          const byKicker = [];
          const place = (about, entry, section) => {
            if (bySection.has(section.label)) {
              merged(about, entry, bySection.get(section.label));
            }
            else {
              bySection.set(section.label, entry);
            }
          };
          cards.forEach((entry, about) => {
            const section = sectionFor(about);
            if (!section) {
              unplaced(about, entry);
            }
            else if (key(section.label) === key(about)) {
              place(about, entry, section);
            }
            else {
              byKicker.push([about, entry, section]);
            }
          });
          byKicker.forEach(([about, entry, section]) => place(about, entry, section));
          list.forEach((section, i) => {
            const li = document.createElement('li');
            li.className = 'pf-nlr__pin';
            li.dataset.top = String(frame.offsetTop + section.top + 8);
            li.style.top = li.dataset.top + 'px';
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'pf-nlr__pin-button';
            button.setAttribute('aria-pressed', String(active && active.label === section.label));
            // The feedback icon, copied from the Leave feedback button
            // (includes/icon-fa.html.twig).
            button.innerHTML = '<span class="pf-nlr__pin-words"></span>';
            if (feedbackMark) {
              button.prepend(feedbackMark.cloneNode(true));
            }
            button.lastChild.textContent = Drupal.t('Comment');
            button.setAttribute('aria-label', plain(Drupal.t('Comment on section @n: @label', { '@n': i + 1, '@label': section.label })));
            button.addEventListener('click', () => choose(section, button));
            li.append(button);
            const entry = bySection.get(section.label);
            if (entry) {
              const chip = document.createElement('button');
              chip.type = 'button';
              chip.className = 'pf-nlr__chip';
              chip.setAttribute('aria-controls', entry.card.id);
              chip.setAttribute('aria-expanded', String(!entry.card.hidden));
              chip.innerHTML = '<span class="pf-nlr__chip-words"></span><span class="visually-hidden"></span>';
              chip.firstChild.textContent = tally(entry);
              chip.lastChild.textContent = ' ' + plain(Drupal.t('on @label', { '@label': section.label }));
              chip.addEventListener('click', () => toggleCard(chip, entry.card, entry.card.hidden));
              li.append(chip, entry.card);
              li.classList.toggle('is-open', !entry.card.hidden);
            }
            pins.append(li);
          });
          cards.forEach((entry) => fit(entry.card));
          // Back from answering a note on its Edit screen (#pf-note-<id>):
          // the note sits in a closed card, and maybe under Resolved, so
          // both are opened and the note brought into view, once.
          if (!returned && /^#pf-note-\d+$/.test(window.location.hash)) {
            returned = true;
            const note = root.querySelector(window.location.hash);
            const card = note && note.closest('.pf-nlr__card');
            const fold = note && note.closest('details');
            if (fold) {
              fold.open = true;
            }
            if (card && card.hidden) {
              const chip = root.querySelector('[aria-controls="' + card.id + '"]');
              if (chip) {
                card.classList.add('is-full');
                toggleCard(chip, card, true);
              }
            }
            if (note) {
              // Again once the email's photos have loaded and moved it,
              // unless the reader has scrolled by then.
              let moved = false;
              const bring = () => {
                if (!moved) {
                  note.scrollIntoView({ block: 'center', behavior: 'instant' });
                }
              };
              ['wheel', 'touchstart', 'keydown', 'mousedown'].forEach((type) => window.addEventListener(type, () => {
                moved = true;
              }, { once: true, passive: true }));
              bring();
              window.addEventListener('load', () => window.setTimeout(bring, 300), { once: true });
              window.setTimeout(bring, 1500);
              const link = note.querySelector('.pf-pn__reply-link');
              if (link) {
                link.focus({ preventScroll: true });
              }
            }
          }
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
                forNote.textContent = plain(Drupal.t('Your part is marked in the email: “@label”.', { '@label': own.section.label }));
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
   * in a dialog on the page. The issue comes from this page with
   * ?read=<id> (NewsletterReview::reading(): fetched once a day, links made
   * plain text), read in the background; if that fails, the card's own link
   * shows it above the grid.
   */
  Drupal.behaviors.pfNewsletterArchive = {
    attach(context) {
      once('pf-nla', '[data-pf-nla]', context).forEach((root) => {
        const dialog = root.querySelector('[data-pf-nla-dialog]');
        if (!dialog || typeof dialog.showModal !== 'function' || !window.fetch || !window.DOMParser) {
          return;
        }
        const frame = dialog.querySelector('[data-pf-nla-frame]');
        const title = dialog.querySelector('[data-pf-nla-title]');
        const issues = new Map();
        let opener = null;
        let asked = '';
        root.querySelectorAll('[data-pf-nla-open]').forEach((card) => {
          card.addEventListener('click', (e) => {
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.button > 0) {
              return;
            }
            e.preventDefault();
            opener = card;
            asked = card.href;
            title.textContent = card.dataset.name;
            frame.title = Drupal.t('Newsletter: @name', { '@name': card.dataset.name });
            dialog.classList.add('is-loading');
            dialog.setAttribute('aria-busy', 'true');
            frame.srcdoc = '';
            dialog.showModal();
            dialog.querySelector('[data-pf-nla-close]').focus();
            const show = (html) => {
              if (asked !== card.href) {
                return;
              }
              frame.srcdoc = html;
              dialog.classList.remove('is-loading');
              dialog.removeAttribute('aria-busy');
            };
            if (issues.has(card.href)) {
              show(issues.get(card.href));
              return;
            }
            window.fetch(card.href, { credentials: 'same-origin' })
              .then((response) => (response.ok ? response.text() : Promise.reject(new Error(String(response.status)))))
              .then((page) => {
                const found = new DOMParser().parseFromString(page, 'text/html').querySelector('[data-pf-nla-reading] iframe');
                const html = found ? found.getAttribute('srcdoc') : '';
                if (!html) {
                  throw new Error('missing');
                }
                issues.set(card.href, html);
                show(html);
              })
              .catch(() => {
                // The page itself shows it.
                window.location.href = card.href;
              });
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

  /**
   * The newsletters page on wide screens: the intro is a sticky column
   * (newsletter-review.scss, .pf-main:has(.pf-nla)), and the list of years
   * moves into it, under the intro, so the column holds a way through the
   * past issues instead of empty page. It moves back above the issues when
   * the screen narrows. The year in view is marked in the list.
   */
  Drupal.behaviors.pfNewsletterYears = {
    attach(context) {
      once('pf-nl-years', '.pf-nla__years', context).forEach((years) => {
        const main = years.closest('.pf-main');
        const head = main && main.querySelector(':scope > .pf-modular__head');
        // A review page keeps its intro above (no intro column there: the
        // email and its form need the width), so the years stay with the
        // issues.
        if (!head || main.querySelector('[data-pf-nlr]')) {
          return;
        }
        const home = document.createComment('pf-nla-years');
        years.before(home);
        const wide = window.matchMedia('(min-width: 80rem)');
        let placed = null;
        const place = () => {
          if (placed === wide.matches) {
            return;
          }
          placed = wide.matches;
          if (placed) {
            head.appendChild(years);
          }
          else {
            home.after(years);
          }
          years.classList.toggle('is-in-head', placed);
        };
        wide.addEventListener('change', place);
        window.addEventListener('resize', debounce(place, 150));
        place();

        if (!('IntersectionObserver' in window)) {
          return;
        }
        const links = new Map(Array.from(years.querySelectorAll('a[href^="#"]')).map((a) => [a.getAttribute('href').slice(1), a]));
        const mark = new IntersectionObserver((entries) => {
          entries.forEach((entry) => {
            const id = entry.target.getAttribute('aria-labelledby');
            if (entry.isIntersecting && links.has(id)) {
              links.forEach((a) => a.removeAttribute('aria-current'));
              links.get(id).setAttribute('aria-current', 'location');
            }
          });
        }, { rootMargin: '-40% 0px -55% 0px' });
        main.querySelectorAll('.pf-nla__year').forEach((section) => mark.observe(section));
      });
    },
  };
})(Drupal, once, Drupal.debounce);
