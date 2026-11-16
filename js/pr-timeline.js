/**
 * @file
 * The public relations timeline (includes/pr-timeline.html.twig).
 *
 * Show notes: approved notes are in the page for everyone; this hides them
 * until the reader turns them on, and remembers the choice in this browser.
 * Comment: fills the form's Item with the item's date and title, marks the
 * item, and brings the form to the reader (it follows the timeline on
 * narrow screens; wider, it is already beside it).
 */
(function (Drupal, once) {
  'use strict';

  const KEY = 'pf-prt-notes';

  function stillMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches ||
      document.documentElement.classList.contains('pf-a11y-motion');
  }

  Drupal.behaviors.pfPrTimeline = {
    attach(context) {
      once('pf-prt', '[data-pf-prt]', context).forEach((root) => {
        const toggle = root.querySelector('[data-pf-prt-toggle]');
        const panel = root.querySelector('[data-pf-prt-panel]');
        const notes = root.querySelectorAll('[data-pf-prt-notes]');

        if (toggle && notes.length) {
          let on = false;
          try {
            on = window.localStorage.getItem(KEY) === '1';
          }
          catch (e) {
            // Storage may be blocked; notes start hidden.
          }
          const set = (value, remember) => {
            on = value;
            toggle.setAttribute('aria-pressed', String(on));
            notes.forEach((el) => {
              el.hidden = !on;
            });
            if (remember) {
              try {
                window.localStorage.setItem(KEY, on ? '1' : '0');
              }
              catch (e) {
                // Not remembered; fine.
              }
            }
          };
          set(on, false);
          toggle.hidden = false;
          toggle.addEventListener('click', () => set(!on, true));
        }

        if (!panel) {
          return;
        }
        root.addEventListener('click', (event) => {
          const link = event.target.closest('[data-pf-prt-comment]');
          if (!link || !root.contains(link)) {
            return;
          }
          // The form is redrawn after each note (Ajax), so look it up now.
          const field = panel.querySelector('[name="item"]');
          const text = panel.querySelector('textarea');
          if (!field) {
            // No form yet: let the link go to the panel.
            return;
          }
          event.preventDefault();
          field.value = link.dataset.item || '';
          root.querySelectorAll('[data-pf-prt-item].is-active').forEach((el) => el.classList.remove('is-active'));
          const item = link.closest('[data-pf-prt-item]');
          if (item) {
            item.classList.add('is-active');
          }
          if (window.getComputedStyle(panel).position !== 'sticky') {
            panel.scrollIntoView({ behavior: stillMotion() ? 'auto' : 'smooth', block: 'start' });
          }
          (text || panel).focus({ preventScroll: true });
          Drupal.announce(Drupal.t('Your note is about @item.', { '@item': field.value }));
        });

        // Clearing Item by hand writes about the timeline as a whole.
        panel.addEventListener('input', (event) => {
          if (event.target.name === 'item' && event.target.value.trim() === '') {
            root.querySelectorAll('[data-pf-prt-item].is-active').forEach((el) => el.classList.remove('is-active'));
          }
        });
      });
    },
  };
})(Drupal, once);
