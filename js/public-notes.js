/**
 * @file
 * Public notes and thumbs (includes/public-notes.html.twig,
 * public-votes.html.twig, public-notes-toggle.html.twig), on the PR timeline
 * and the newsletter review pages.
 *
 * Inside a [data-pf-pn-root] (data-pf-pn-key names the form's "about"
 * element, item or section):
 * - Show notes: approved notes are in the page for everyone; this hides them
 *   until the reader turns them on, and remembers the choice here.
 * - Thumbs: a thumb sends itself through the page's note form
 *   ([data-pf-pn-form]) with the thing it's about filled in, at once when no
 *   note is being written; otherwise it goes with that note. Choosing the
 *   other thumb changes it, and choosing the same one again takes it back.
 *   Each browser sends a random token of its own (voter), and only its
 *   latest thumb on a thing counts (PublicNotes::votes()); not proof against
 *   someone set on voting twice.
 * - The form's Your take choices get the thumbs icons.
 */
(function (Drupal, once) {
  'use strict';

  const NOTES_KEY = 'pf-pn-notes';
  const VOTES_KEY = 'pf-pn-votes';
  const VOTER_KEY = 'pf-pn-voter';

  // This browser's token: random, saying nothing about the person.
  function voter() {
    let token = read(VOTER_KEY, '');
    if (typeof token !== 'string' || !/^[a-z0-9-]{16,64}$/.test(token)) {
      token = window.crypto && window.crypto.randomUUID ? window.crypto.randomUUID() : Array.from({ length: 4 }, () => Math.random().toString(36).slice(2, 10)).join('-');
      write(VOTER_KEY, token);
    }
    return token;
  }

  function bump(button, by) {
    const count = button && button.querySelector('[data-pf-pn-count]');
    if (count) {
      count.textContent = String(Math.max(0, (parseInt(count.textContent, 10) || 0) + by));
    }
  }

  function read(key, fallback) {
    try {
      const value = window.localStorage.getItem(key);
      return value === null ? fallback : JSON.parse(value);
    }
    catch (e) {
      return fallback;
    }
  }

  function write(key, value) {
    try {
      window.localStorage.setItem(key, JSON.stringify(value));
    }
    catch (e) {
      // Not remembered; fine.
    }
  }

  // Submits a Webform the way its own button would: through Drupal's Ajax
  // when the form uses it (bound to mousedown), else a plain submit.
  // Nothing is sent (or counted) if the browser would refuse the form, for
  // one still requiring a note (made before thumbs: its script's replace=1).
  function send(form) {
    const button = form.querySelector('.webform-button--submit, [type="submit"]');
    if (!button) {
      return false;
    }
    if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
      form.reportValidity();
      return false;
    }
    const ajax = (Drupal.ajax && Drupal.ajax.instances || []).find((instance) => instance && instance.element === button);
    if (ajax && window.jQuery) {
      window.jQuery(button).trigger(ajax.elementSettings && ajax.elementSettings.event || 'mousedown');
    }
    else {
      button.click();
    }
    return true;
  }

  Drupal.behaviors.pfPublicNotes = {
    attach(context) {
      once('pf-pn', '[data-pf-pn-root]', context).forEach((root) => {
        const key = root.dataset.pfPnKey || 'item';
        const page = window.location.pathname;
        const toggle = root.querySelector('[data-pf-pn-toggle]');
        const notes = root.querySelectorAll('[data-pf-pn-notes]');

        if (toggle && notes.length) {
          let on = read(NOTES_KEY, false) === true;
          const set = (value) => {
            on = value;
            toggle.setAttribute('aria-pressed', String(on));
            notes.forEach((el) => {
              el.hidden = !on;
            });
          };
          set(on);
          toggle.hidden = false;
          toggle.addEventListener('click', () => {
            set(!on);
            write(NOTES_KEY, on);
          });
        }

        // Thumbs already given from this browser.
        const given = read(VOTES_KEY, {});
        root.querySelectorAll('[data-pf-pn-votes]').forEach((group) => {
          const mine = given[page + '|' + group.dataset.about];
          group.querySelectorAll('[data-pf-pn-vote]').forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.pfPnVote === mine));
          });
        });

        root.addEventListener('click', (event) => {
          const button = event.target.closest('[data-pf-pn-vote]');
          if (!button || !root.contains(button)) {
            return;
          }
          const group = button.closest('[data-pf-pn-votes]');
          const about = group.dataset.about || '';
          const chosen = button.dataset.pfPnVote;
          const votes = read(VOTES_KEY, {});
          const before = votes[page + '|' + about] || '';
          // The same thumb again takes it back.
          const after = before === chosen ? 'none' : chosen;
          const panel = root.querySelector('[data-pf-pn-form]');
          const form = panel ? panel.querySelector('form') : null;
          const field = form ? form.querySelector('[name="' + key + '"]') : null;
          const vote = form ? form.querySelector('[name="vote"]') : null;
          const radio = form ? form.querySelector('[name="verdict"][value="' + chosen + '"]') : null;
          if (!field || !radio) {
            return;
          }
          const word = chosen === 'good' ? Drupal.t('Thumbs up') : Drupal.t('Thumbs down');
          const text = form.querySelector('textarea');
          field.value = about;
          if (text && text.value.trim() !== '' && after !== 'none') {
            // A note is being written: the thumb goes with it as its take.
            radio.checked = true;
            radio.dispatchEvent(new Event('change', { bubbles: true }));
            Drupal.announce(Drupal.t('@thumb added to your note. Send it when you’re ready.', { '@thumb': word }));
            const submit = form.querySelector('.webform-button--submit, [type="submit"]');
            if (submit) {
              submit.focus();
            }
            return;
          }
          // A thumb on its own: sent as the vote, with no take or note, so
          // the form needs neither. A form made before thumbs could be
          // changed has no vote field: the thumb then goes as the take, and
          // can't be taken back.
          if (!vote && after === 'none') {
            return;
          }
          form.querySelectorAll('[name="verdict"]').forEach((input) => {
            input.checked = !vote && input === radio;
          });
          if (vote) {
            vote.value = after;
          }
          const sent = send(form);
          if (vote) {
            vote.value = '';
          }
          if (!sent) {
            return;
          }
          votes[page + '|' + about] = after === 'none' ? '' : after;
          write(VOTES_KEY, votes);
          group.querySelectorAll('[data-pf-pn-vote]').forEach((other) => {
            if (other.dataset.pfPnVote === before) {
              bump(other, -1);
            }
            if (other.dataset.pfPnVote === after) {
              bump(other, 1);
            }
            other.setAttribute('aria-pressed', String(other.dataset.pfPnVote === after));
          });
          Drupal.announce(after === 'none' ? Drupal.t('@thumb taken back.', { '@thumb': word }) : Drupal.t('@thumb sent.', { '@thumb': word }));
        });
      });

      // Every note form carries this browser's token, so a note's take
      // replaces an earlier thumb from here (the form is redrawn after each
      // note, so this runs on every attach).
      once('pf-pn-voter', '[data-pf-pn-form] [name="voter"]', context).forEach((input) => {
        input.value = voter();
      });

      // Your take: the thumbs icons beside Looks good / Needs changes (or
      // Like / Dislike), from the page's own thumbs (the form is redrawn
      // after each note, so this runs on every attach).
      once('pf-pn-take', 'input[name="verdict"]', context).forEach((input) => {
        const root = input.closest('[data-pf-pn-root]');
        const label = input.id ? root && root.querySelector('label[for="' + input.id + '"]') : null;
        const icon = root && root.querySelector('[data-pf-pn-icon="' + input.value + '"]');
        if (label && icon) {
          label.prepend(icon.content ? icon.content.cloneNode(true) : icon.cloneNode(true));
        }
      });
    },
  };
})(Drupal, once);
