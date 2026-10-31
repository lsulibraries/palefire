/**
 * @file
 * Staff directory (/v2/staff, views-view--palefire-staff--directory.html.twig):
 * search and a Department filter over the list on the page.
 *
 * The list is all there without this; this shows the controls and hides
 * the rows that don't match. Search matches every word typed, in any order,
 * against each row's name, job, departments and subjects
 * (data-pf-staff-text, lower case, accents removed). The filter and the
 * words are kept in the address (?department=, ?q=) with replaceState, so a
 * link can open the list filtered and Back still leaves the page.
 *
 * The count is announced (aria-live) once typing pauses, not per key. An
 * old /v2/staff#<slug> link (the old directory's modals) goes on to that
 * person's profile.
 */
(function (Drupal, once) {
  'use strict';

  const ANNOUNCE_MS = 400;

  // Lower case without accents, as the server writes data-pf-staff-text.
  const fold = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

  Drupal.behaviors.palefireStaffDirectory = {
    attach(context) {
      once('pf-staff', '[data-pf-staff]', context).forEach((root) => {
        const list = root.querySelector('[data-pf-staff-list]');
        const controls = root.querySelector('[data-pf-staff-controls]');
        if (!list || !controls) {
          return;
        }

        // Old modal links: /v2/staff#lilibedoya.
        const hash = decodeURIComponent(window.location.hash.slice(1));
        const target = hash && Array.from(list.children).find((row) => row.dataset.pfStaffSlug === hash);
        if (target) {
          window.location.replace(target.querySelector('.pf-staff__name').href);
          return;
        }

        const rows = Array.from(list.children);
        const input = controls.querySelector('#pf-staff-q');
        const select = controls.querySelector('#pf-staff-department');
        const count = controls.querySelector('[data-pf-staff-count]');
        const empty = root.querySelector('[data-pf-staff-empty]');
        let timer = 0;

        const params = new URLSearchParams(window.location.search);
        input.value = params.get('q') || '';
        const department = params.get('department') || '';
        if (Array.from(select.options).some((option) => option.value === department)) {
          select.value = department;
        }

        const announce = (shown) => {
          const label = select.value ? select.options[select.selectedIndex].text.replace(/\s*\(\d+\)$/, '') : '';
          count.textContent = label
            ? Drupal.t('Showing @shown of @total, @department', { '@shown': shown, '@total': rows.length, '@department': label })
            : Drupal.t('Showing @shown of @total', { '@shown': shown, '@total': rows.length });
        };

        const apply = (now) => {
          const words = fold(input.value).split(/\s+/).filter(Boolean);
          const dept = select.value;
          let shown = 0;
          rows.forEach((row) => {
            const text = row.dataset.pfStaffText || '';
            const match = (!dept || (row.dataset.pfStaffDept || '').split(' ').includes(dept))
              && words.every((word) => text.includes(word));
            row.hidden = !match;
            shown += match ? 1 : 0;
          });
          empty.hidden = shown > 0;

          const url = new URL(window.location.href);
          url.searchParams.delete('q');
          url.searchParams.delete('department');
          if (dept) {
            url.searchParams.set('department', dept);
          }
          if (input.value.trim()) {
            url.searchParams.set('q', input.value.trim());
          }
          window.history.replaceState(window.history.state, '', url);

          window.clearTimeout(timer);
          if (now) {
            announce(shown);
          }
          else {
            timer = window.setTimeout(() => announce(shown), ANNOUNCE_MS);
          }
        };

        input.addEventListener('input', () => apply(false));
        select.addEventListener('change', () => apply(true));
        controls.addEventListener('submit', (event) => event.preventDefault());

        controls.hidden = false;
        apply(true);
      });
    },
  };
})(Drupal, once);
