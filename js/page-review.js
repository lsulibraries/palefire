/**
 * @file
 * Review mode on a Palefire page (includes/page-review.html.twig,
 * Drupal\palefire\PageReview): any /v2 page opened with ?review.
 *
 * - A pin beside each section heading (the visible h2s in the main content)
 *   with its count of notes and thumbs; choosing it opens the panel with
 *   Section filled in.
 * - The panel: beside the page from 64rem, a sheet from the foot of the
 *   screen below it; Esc or the close button shuts it.
 * - On this page lists every section in the page's order (one with nothing
 *   yet is drawn from a template), then any section whose heading has since
 *   changed.
 * - The form is sent in the background (a plain post: the form sits outside
 *   the page's main content, which Drupal's own Ajax redraws) and redrawn
 *   from the reply, keeping the name and section.
 * - Links to other /v2 pages keep ?review.
 */
(function (Drupal, once) {
  'use strict';

  const VOTES_KEY = 'pf-pn-votes';

  function clean(text) {
    return (text || '').replace(/\s+/g, ' ').trim().slice(0, 255);
  }

  function readVotes() {
    try {
      return JSON.parse(window.localStorage.getItem(VOTES_KEY) || '{}') || {};
    }
    catch (e) {
      return {};
    }
  }

  // The sections: visible h2s in the main content, by their words.
  function headings(root) {
    const main = document.querySelector('main#main-content') || document.querySelector('main');
    if (!main) {
      return [];
    }
    return Array.from(main.querySelectorAll('h2')).filter((h) => {
      // Headings for screen readers only aren't sections. One in a closed
      // tab or a part not drawn yet is: its pin waits until it shows.
      return !root.contains(h) && !h.closest('.visually-hidden, .sr-only, [aria-hidden="true"]') && clean(h.textContent) !== '';
    });
  }

  // Keeps ?review on a link to another Palefire page.
  function keep(link) {
    const raw = link.getAttribute('href');
    if (!raw || raw.charAt(0) === '#' || link.hasAttribute('download') || link.matches('[data-pf-rv-exit]')) {
      return;
    }
    let url;
    try {
      url = new URL(raw, window.location.href);
    }
    catch (e) {
      return;
    }
    if (url.origin !== window.location.origin || !/^\/v2(\/|$)/i.test(url.pathname) || url.searchParams.has('review')) {
      return;
    }
    url.search += (url.search ? '&' : '?') + 'review';
    link.setAttribute('href', url.pathname + url.search + url.hash);
  }

  Drupal.behaviors.pfPageReview = {
    attach(context) {
      once('pf-rv-width', '[data-pf-rv-form] [name="width"]', context).forEach((input) => {
        input.value = String(window.innerWidth);
      });

      once('pf-rv', '[data-pf-rv]', context).forEach((root) => {
        // A reviewer from now on: js/palefire.js shows Review this page on
        // this browser's other Palefire pages.
        try {
          window.localStorage.setItem('pf-reviewer', '1');
        }
        catch (e) {
          // Not remembered; review links still work.
        }
        const panel = root.querySelector('[data-pf-rv-panel]');
        const opener = root.querySelector('[data-pf-rv-open]');
        const closer = root.querySelector('[data-pf-rv-close]');
        const status = root.querySelector('[data-pf-rv-status]');
        const wrap = root.querySelector('[data-pf-rv-form]');
        const list = root.querySelector('[data-pf-rv-groups]');
        const template = root.querySelector('[data-pf-rv-group-template]');
        const goneText = root.querySelector('[data-pf-rv-gone]');
        let counts = {};
        try {
          counts = JSON.parse(root.dataset.counts || '{}') || {};
        }
        catch (e) {
          counts = {};
        }
        if (!panel || !opener) {
          return;
        }

        const forget = root.querySelector('[data-pf-rv-forget]');
        if (forget) {
          forget.hidden = false;
          forget.addEventListener('click', () => {
            try {
              window.localStorage.removeItem('pf-reviewer');
            }
            catch (e) {
              // Nothing kept.
            }
            forget.hidden = true;
            Drupal.announce(Drupal.t('The Review this page button won’t show outside review mode. A review link brings it back.'));
            if (status) {
              status.textContent = Drupal.t('The Review this page button won’t show outside review mode. A review link brings it back.');
            }
          });
        }
        root.classList.add('is-ready');
        opener.hidden = false;
        if (closer) {
          closer.hidden = false;
        }
        panel.hidden = true;

        let back = null;
        const open = (from) => {
          back = from || opener;
          panel.hidden = false;
          opener.setAttribute('aria-expanded', 'true');
          root.classList.add('is-open');
        };
        const close = () => {
          panel.hidden = true;
          opener.setAttribute('aria-expanded', 'false');
          root.classList.remove('is-open');
          document.querySelectorAll('.pf-rv-target').forEach((h) => h.classList.remove('pf-rv-target'));
          if (back && document.contains(back)) {
            back.focus();
          }
        };
        opener.addEventListener('click', () => {
          if (panel.hidden) {
            open(opener);
            panel.focus();
          }
          else {
            close();
          }
        });
        if (closer) {
          closer.addEventListener('click', close);
        }
        document.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && !panel.hidden) {
            close();
          }
        });

        // Ties the note to a section (or the page as a whole) and opens the
        // panel at the note.
        const choose = (about, from, heading) => {
          const form = wrap && wrap.querySelector('form');
          const field = form && form.querySelector('[name="section"]');
          if (field) {
            field.value = about;
          }
          document.querySelectorAll('.pf-rv-target').forEach((h) => h.classList.remove('pf-rv-target'));
          if (heading) {
            heading.classList.add('pf-rv-target');
          }
          open(from);
          const text = form && form.querySelector('textarea');
          (text || panel).focus();
          Drupal.announce(about ? Drupal.t('Your note is about @section.', { '@section': about }) : Drupal.t('Your note is about the page as a whole.'));
        };

        // Pins. -------------------------------------------------------------
        const found = headings(root);
        const layer = document.createElement('div');
        layer.className = 'pf-rv-pins';
        layer.setAttribute('role', 'group');
        layer.setAttribute('aria-label', Drupal.t('Sections to comment on'));
        root.appendChild(layer);
        const icon = opener.querySelector('.pf-fa');
        const pins = found.map((heading) => {
          const about = clean(heading.textContent);
          const tally = counts[about] || { notes: 0, thumbs: 0 };
          const pin = document.createElement('button');
          pin.type = 'button';
          pin.className = 'pf-rv-pin';
          if (icon) {
            pin.appendChild(icon.cloneNode(true));
          }
          const n = tally.notes + tally.thumbs;
          if (n) {
            const count = document.createElement('span');
            count.className = 'pf-rv-pin__count';
            count.setAttribute('aria-hidden', 'true');
            count.textContent = String(n);
            pin.appendChild(count);
          }
          pin.setAttribute('aria-label', Drupal.t('Comment on @section (@notes notes, @thumbs thumbs)', { '@section': about, '@notes': tally.notes, '@thumbs': tally.thumbs }));
          pin.addEventListener('click', () => choose(about, pin, heading));
          layer.appendChild(pin);
          return [heading, pin];
        });

        // Beside the heading's last line of words, or under it when there's
        // no room to its right.
        const place = () => {
          const origin = layer.getBoundingClientRect();
          const room = document.documentElement.clientWidth;
          pins.forEach(([heading, pin]) => {
            const range = document.createRange();
            range.selectNodeContents(heading);
            const lines = Array.from(range.getClientRects()).filter((r) => r.width > 0);
            const box = heading.getBoundingClientRect();
            const shown = box.width > 1 && box.height > 1;
            pin.hidden = !shown;
            if (!shown) {
              return;
            }
            const last = lines.length ? lines[lines.length - 1] : box;
            const size = pin.offsetWidth || 32;
            let x = last.right + 8;
            let y = last.top + (last.height - size) / 2;
            if (x + size > room - 8) {
              x = (lines[0] || box).left;
              y = last.bottom + 4;
            }
            pin.style.transform = 'translate(' + Math.round(x - origin.left) + 'px, ' + Math.round(y - origin.top) + 'px)';
          });
        };
        let frame = 0;
        const later = () => {
          if (!frame) {
            frame = window.requestAnimationFrame(() => {
              frame = 0;
              place();
            });
          }
        };
        place();
        window.addEventListener('resize', later);
        window.addEventListener('load', later);
        if (document.fonts && document.fonts.ready) {
          document.fonts.ready.then(later);
        }
        if ('ResizeObserver' in window) {
          new ResizeObserver(later).observe(document.body);
        }

        // On this page: the page as a whole, the sections in order, then
        // any no longer on the page. -----------------------------------------
        if (list && template) {
          const groups = new Map();
          list.querySelectorAll('[data-pf-rv-group]').forEach((group) => groups.set(group.dataset.about || '', group));
          const given = readVotes();
          const page = window.location.pathname;
          const ordered = [groups.get('')].filter(Boolean);
          groups.delete('');
          found.forEach((heading) => {
            const about = clean(heading.textContent);
            if (ordered.some((g) => (g.dataset.about || '') === about)) {
              return;
            }
            let group = groups.get(about);
            groups.delete(about);
            if (!group) {
              group = template.content.firstElementChild.cloneNode(true);
              group.dataset.about = about;
              group.querySelectorAll('[data-about]').forEach((el) => {
                el.dataset.about = about;
              });
              const walker = document.createTreeWalker(group, NodeFilter.SHOW_TEXT);
              while (walker.nextNode()) {
                walker.currentNode.nodeValue = walker.currentNode.nodeValue.split('{label}').join(about);
              }
              const mine = given[page + '|' + about];
              group.querySelectorAll('[data-pf-pn-vote]').forEach((button) => {
                button.setAttribute('aria-pressed', String(button.dataset.pfPnVote === mine));
              });
            }
            group.dataset.heading = String(found.indexOf(heading));
            ordered.push(group);
          });
          groups.forEach((group) => {
            if (goneText && !group.querySelector('.pf-rv__gone')) {
              const note = document.createElement('p');
              note.className = 'pf-rv__gone';
              note.textContent = goneText.textContent;
              group.querySelector('.pf-rv__group-title').after(note);
            }
            ordered.push(group);
          });
          ordered.forEach((group) => list.appendChild(group));

          list.addEventListener('click', (event) => {
            const button = event.target.closest('[data-pf-rv-comment]');
            if (!button) {
              return;
            }
            const group = button.closest('[data-pf-rv-group]');
            const heading = group && group.dataset.heading ? found[parseInt(group.dataset.heading, 10)] : null;
            choose(button.dataset.about || '', button, heading);
          });
        }

        // Sending. -----------------------------------------------------------
        let queue = Promise.resolve();
        const say = (text) => {
          if (status) {
            status.textContent = text;
          }
        };
        if (wrap) {
          wrap.addEventListener('submit', (event) => {
            const form = event.target.closest('form');
            if (!form || !window.fetch || !window.FormData || !window.DOMParser) {
              return;
            }
            event.preventDefault();
            const width = form.querySelector('[name="width"]');
            if (width) {
              width.value = String(window.innerWidth);
            }
            let data;
            try {
              data = event.submitter ? new FormData(form, event.submitter) : new FormData(form);
            }
            catch (e) {
              data = new FormData(form);
            }
            const sentNote = clean(String(data.get('comment') || '')) !== '';
            const action = form.getAttribute('action') || window.location.href;
            queue = queue.then(() => {
              const now = wrap.querySelector('form') || form;
              now.setAttribute('aria-busy', 'true');
              return window.fetch(action, { method: 'POST', body: data, credentials: 'same-origin' })
                .then((response) => response.text().then((html) => [response, html]))
                .then(([response, html]) => {
                  const doc = new DOMParser().parseFromString(html, 'text/html');
                  const fresh = doc.querySelector('[data-pf-rv-form]');
                  const messages = doc.querySelector('[data-drupal-messages]');
                  const said = messages ? clean(messages.textContent.replace(/^\s*(Status|Error|Warning) message\s*/i, '')) : '';
                  const current = wrap.querySelector('form');
                  const keepValues = {};
                  ['name', 'section'].concat(sentNote ? [] : ['comment']).forEach((key) => {
                    const el = current && current.querySelector('[name="' + key + '"]');
                    if (el) {
                      keepValues[key] = el.value;
                    }
                  });
                  if (!response.ok) {
                    throw new Error(String(response.status));
                  }
                  if (fresh && fresh.querySelector('form')) {
                    if (Drupal.detachBehaviors) {
                      Drupal.detachBehaviors(wrap);
                    }
                    wrap.innerHTML = fresh.innerHTML;
                    Object.keys(keepValues).forEach((key) => {
                      const el = wrap.querySelector('[name="' + key + '"]');
                      if (el && el.value === '') {
                        el.value = keepValues[key];
                      }
                    });
                    Drupal.attachBehaviors(wrap, window.drupalSettings);
                  }
                  else if (current) {
                    // The thank-you page wasn't in review mode: the note
                    // went; start a fresh one.
                    current.removeAttribute('aria-busy');
                    if (sentNote) {
                      const text = current.querySelector('textarea');
                      if (text) {
                        text.value = '';
                      }
                      current.querySelectorAll('[name="verdict"]').forEach((input) => {
                        input.checked = false;
                      });
                    }
                  }
                  if (sentNote) {
                    say(said || Drupal.t('Thank you, it’s in. Your note shows here once staff approve it.'));
                  }
                  else if (said && /error|required|invalid/i.test(said)) {
                    say(said);
                  }
                });
            }).catch(() => {
              const now = wrap.querySelector('form');
              if (now) {
                now.removeAttribute('aria-busy');
              }
              say(Drupal.t('That didn’t send. Check your connection and try again.'));
            });
          });
        }

        // Links keep ?review. --------------------------------------------------
        document.querySelectorAll('a[href]').forEach(keep);
        document.addEventListener('click', (event) => {
          const link = event.target.closest && event.target.closest('a[href]');
          if (link) {
            keep(link);
          }
        }, true);
      });
    },
  };
})(Drupal, once);
