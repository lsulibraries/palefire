/**
 * @file
 * The index under the search on the Special Collections stage (.pf-hero-index,
 * includes/landing.html.twig, _landing-features.scss): says the page goes on,
 * and what comes next, with or without a mouse wheel.
 *
 * - Highlights glides to where the stage shows every Highlight, and moves
 *   focus to the first of them.
 * - The links are the page's first numbered sections, read from the page. Those
 *   that don't fit on the one line are dropped from the end.
 * - The arrow dips once, if the visitor has neither scrolled nor touched
 *   anything for four seconds after the entrance ends. Never repeated.
 *
 * Stays hidden without script or Highlights. The CSS shows it only on the
 * stage, so reduced motion and short windows get neither index nor nudge.
 */
(function (Drupal, once) {
  // ms: the entrance's last animation (pf-title-fill, _entrance.scss, ends at
  // 3.2s), then four quiet seconds.
  const WAIT = 7200;
  const LINKS = 3;
  const STOP = 0.8; // of the stage's run, where every Highlight has come in

  const addLinks = (list) => {
    document.querySelectorAll('.pf-sections > .pf-section--indexed[id]').forEach((section, index) => {
      const title = section.querySelector('.pf-section__title');
      if (index >= LINKS || !title) {
        return;
      }
      const item = document.createElement('li');
      const link = document.createElement('a');
      link.href = `#${section.id}`;
      link.textContent = title.textContent.trim();
      item.append(link);
      list.append(item);
    });
  };

  const fit = (list) => {
    const links = [...list.children].slice(1);
    links.forEach((item) => { item.hidden = false; });
    while (links.length && list.scrollWidth > list.clientWidth) {
      links.pop().hidden = true;
    }
  };

  const nudge = (index, button) => {
    const stop = new AbortController();
    const cancel = () => {
      clearTimeout(timer);
      stop.abort();
    };
    const timer = setTimeout(() => {
      const shown = index.isConnected && getComputedStyle(index).display !== 'none';
      cancel();
      if (shown && window.scrollY < 1) {
        button.classList.add('is-nudging');
      }
    }, WAIT);
    ['scroll', 'wheel', 'keydown', 'pointerdown'].forEach((type) => {
      window.addEventListener(type, cancel, { passive: true, signal: stop.signal });
    });
    button.addEventListener('animationend', (event) => {
      if (event.animationName === 'pf-hero-index-nudge') {
        button.classList.remove('is-nudging');
      }
    });
  };

  Drupal.behaviors.palefireHeroIndex = {
    attach(context) {
      once('hero-index', '[data-pf-hero-index]', context).forEach((index) => {
        const lead = index.closest('.pf-landing__lead');
        const run = lead.querySelector('.pf-landing__run');
        const stage = lead.querySelector('.pf-landing__stage');
        const first = lead.querySelector('.pf-hero-banners a');
        const list = index.querySelector('ul');
        const button = index.querySelector('button');
        if (!first) {
          return;
        }
        addLinks(list);
        index.hidden = false;
        fit(list);
        new ResizeObserver(() => fit(list)).observe(list);

        button.addEventListener('click', () => {
          const top = Math.round((run.offsetHeight - stage.offsetHeight) * STOP);
          Drupal.palefireGlide(top, () => {
            if (Math.abs(window.scrollY - top) < 2) {
              first.focus({ preventScroll: true });
            }
          }, { interruptible: true });
        });
        nudge(index, button);
      });
    },
  };
})(Drupal, once);
