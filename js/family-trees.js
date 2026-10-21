/**
 * @file
 * Louisiana Newspapers guide: search and filter the newspaper family trees.
 *
 * Markup: paragraph--family-trees.html.twig. Every row is in a <template>;
 * the list shows the rows that match the search box (every word, in the
 * title, city or parish, accents ignored) and the parish menu, a step (40)
 * at a time, with "Show more" for the next step. The status line under the
 * filters gives the count. The first step is already in the page, so
 * nothing moves when this runs.
 */
(function (Drupal, once) {
  'use strict';

  const fold = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

  function initTrees(root) {
    const q = root.querySelector('[data-trees-q]');
    const parish = root.querySelector('[data-trees-parish]');
    const count = root.querySelector('[data-trees-count]');
    const list = root.querySelector('[data-trees-list]');
    const more = root.querySelector('[data-trees-more]');
    const tpl = root.querySelector('template[data-trees-rows]');
    if (!q || !parish || !list || !more || !tpl) return;
    const step = parseInt(root.dataset.treesStep, 10) || 40;
    const rows = Array.from(tpl.content.children);
    const search = rows.map((row) => fold(row.dataset.search || ''));
    let matches = rows;
    let shown = list.children.length;
    let timer = 0;

    function update() {
      const left = matches.length - shown;
      more.hidden = left <= 0;
      more.textContent = Drupal.t('Show @count more', { '@count': Math.min(step, left) });
    }

    function addStep() {
      const next = matches.slice(shown, shown + step).map((row) => row.cloneNode(true));
      list.append(...next);
      shown += next.length;
      update();
      return next[0];
    }

    function filter() {
      const words = fold(q.value).split(/\s+/).filter(Boolean);
      const where = parish.value;
      matches = rows.filter((row, i) => (!where || row.dataset.parish === where) && words.every((word) => search[i].includes(word)));
      list.replaceChildren();
      shown = 0;
      addStep();
      count.textContent = !words.length && !where
        ? Drupal.formatPlural(rows.length, '1 family tree', '@count family trees')
        : Drupal.formatPlural(matches.length, '1 family tree matches', '@count family trees match');
    }

    q.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(filter, 200);
    });
    parish.addEventListener('change', filter);
    // Show more: focus the first new tree, so keyboard users carry on there.
    more.addEventListener('click', () => {
      const first = addStep();
      const link = first && first.querySelector('a');
      if (link) link.focus();
    });
    update();
  }

  Drupal.behaviors.palefireFamilyTrees = {
    attach(context) {
      once('pf-family-trees', '[data-family-trees]', context).forEach(initTrees);
    },
  };
})(Drupal, once);
