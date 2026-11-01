/**
 * @file
 * Location pages: the small map plate (paragraph--location-space).
 *
 * Draws a miniature of the page's building map (its outlines, and the
 * room marked "here" filled) into the plate, shows the plate, and marks
 * the page .is-past-map while the map is above the window and the room's
 * section is still in view. CSS shows the plate then: at the foot of the
 * details column, or as a chip at the bottom of the screen where there is
 * no column (_location.scss). It is the chip too (.is-plate-chip) when the
 * plate would not be in the window at the column's foot: the column runs
 * past the bottom of the window, or (a short window) does not stick.
 */
(function (Drupal, once) {
  const NS = 'http://www.w3.org/2000/svg';

  // The map's outlines and the room's floor, as plain paths: no ids, masks
  // or controls, so nothing in the copy is read out or focusable.
  function miniature(svg, here) {
    const mini = document.createElementNS(NS, 'svg');
    mini.setAttribute('class', 'pf-location__plate-svg');
    mini.setAttribute('viewBox', svg.getAttribute('viewBox'));
    mini.setAttribute('aria-hidden', 'true');
    mini.setAttribute('focusable', 'false');
    const add = (source, className) => {
      const path = document.createElementNS(NS, 'path');
      path.setAttribute('d', source.getAttribute('d'));
      if (className) {
        path.setAttribute('class', className);
      }
      mini.append(path);
    };
    if (here) {
      svg.querySelectorAll(`.pf-map__region[data-map-region="${CSS.escape(here)}"] .pf-map__hit`).forEach((path) => add(path, 'is-here'));
    }
    svg.querySelectorAll('.pf-map__lines:not(.pf-map__lines--hidden) path').forEach((path) => add(path));
    return mini;
  }

  Drupal.behaviors.palefireLocationPlate = {
    attach(context) {
      once('pf-location-plate', '[data-pf-plate]', context).forEach((plate) => {
        const page = plate.closest('.pf-location-page');
        const map = page && page.querySelector('.pf-location__map');
        const svg = map && map.querySelector('.pf-map__svg');
        if (!svg || !('IntersectionObserver' in window)) {
          return;
        }
        plate.querySelector('.pf-location__plate-drawing').append(miniature(svg, plate.dataset.pfPlate));
        plate.hidden = false;

        let past = false;
        let inView = true;
        const update = () => {
          page.classList.remove('is-plate-chip');
          page.classList.toggle('is-past-map', past && inView);
          if (!past || !inView || getComputedStyle(plate).position === 'fixed') {
            return;
          }
          // Measured with the plate in the column, before it is painted.
          const box = plate.getBoundingClientRect();
          if (box.top < 0 || box.bottom > window.innerHeight) {
            page.classList.add('is-plate-chip');
          }
        };

        // The window, run on far below its foot, so the only edge the map
        // crosses is the top: out of it is above the window, however the
        // page got there (a phone starts with the map below the fold).
        new IntersectionObserver(([entry]) => {
          past = !entry.isIntersecting;
          update();
        }, { rootMargin: '0px 0px 100000px 0px' }).observe(map);
        new IntersectionObserver(([entry]) => {
          inView = entry.isIntersecting;
          update();
        }).observe(page.querySelector('.pf-location'));
        window.addEventListener('resize', Drupal.debounce(update, 150));
      });
    },
  };
})(Drupal, once);
