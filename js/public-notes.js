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
 *   note is being written; otherwise it goes with that note. This browser
 *   remembers each thumb so it counts once from here (not proof against
 *   someone set on voting twice).
 * - The form's Your take choices get the thumbs icons.
 */
(function (Drupal, once) {
  'use strict';

  const NOTES_KEY = 'pf-pn-notes';
  const VOTES_KEY = 'pf-pn-votes';

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
          const verdict = button.dataset.pfPnVote;
          const votes = read(VOTES_KEY, {});
          if (votes[page + '|' + about]) {
            Drupal.announce(Drupal.t('You already gave a thumb here.'));
            return;
          }
          const panel = root.querySelector('[data-pf-pn-form]');
          const form = panel ? panel.querySelector('form') : null;
          const field = form ? form.querySelector('[name="' + key + '"]') : null;
          const radio = form ? form.querySelector('[name="verdict"][value="' + verdict + '"]') : null;
          if (!field || !radio) {
            return;
          }
          field.value = about;
          radio.checked = true;
          radio.dispatchEvent(new Event('change', { bubbles: true }));
          const text = form.querySelector('textarea');
          const word = verdict === 'good' ? Drupal.t('Thumbs up') : Drupal.t('Thumbs down');
          if (text && text.value.trim() !== '') {
            // A note is being written: the thumb goes with it.
            Drupal.announce(Drupal.t('@thumb added to your note. Send it when you’re ready.', { '@thumb': word }));
            const submit = form.querySelector('.webform-button--submit, [type="submit"]');
            if (submit) {
              submit.focus();
            }
            return;
          }
          if (!send(form)) {
            return;
          }
          votes[page + '|' + about] = verdict;
          write(VOTES_KEY, votes);
          button.setAttribute('aria-pressed', 'true');
          const count = button.querySelector('[data-pf-pn-count]');
          if (count) {
            count.textContent = String((parseInt(count.textContent, 10) || 0) + 1);
          }
          Drupal.announce(Drupal.t('@thumb sent.', { '@thumb': word }));
        });
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
