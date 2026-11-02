/**
 * @file
 * Location pages: the map chip and the frosted floors
 * (paragraph--location-space).
 *
 * Draws a miniature of the page's building map (its outlines, and the
 * room marked "here" filled) into the chip, shows it, and marks the page
 * .is-past-map while the map is above the window and the room's sections
 * still fill the foot of the screen, where the chip sits; CSS then shows
 * the chip there (_location.scss).
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

  // The frosted floors: a layer under the drawing, masked to the floors'
  // shapes (Floor 1's outline and every room's hit area, in the drawing's
  // own viewBox), so CSS can blur and dim the photo behind exactly those
  // and leave it clear around them (_location.scss). Only over a photo.
  function frost(map, svg) {
    if (!map.closest('.has-backdrop')) {
      return;
    }
    const shapes = svg.querySelectorAll('.pf-map__floors path, .pf-map__hit');
    if (!shapes.length) {
      return;
    }
    const paths = Array.from(shapes, (path) => `<path d="${path.getAttribute('d')}"/>`).join('');
    const mask = `<svg xmlns="${NS}" viewBox="${svg.getAttribute('viewBox')}" preserveAspectRatio="none">${paths}</svg>`;
    const layer = document.createElement('div');
    layer.className = 'pf-location__frost';
    layer.setAttribute('aria-hidden', 'true');
    layer.style.setProperty('--pf-location-frost', `url("data:image/svg+xml,${encodeURIComponent(mask)}")`);
    svg.before(layer);
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
        frost(map, svg);
        plate.querySelector('.pf-location__plate-drawing').append(miniature(svg, plate.dataset.pfPlate));
        plate.hidden = false;

        let past = false;
        let inView = true;
        const update = () => page.classList.toggle('is-past-map', past && inView);

        // The window, run on far below its foot, so the only edge the map
        // crosses is the top: out of it is above the window, however the
        // page got there (a phone starts with the map below the fold).
        new IntersectionObserver(([entry]) => {
          past = !entry.isIntersecting;
          update();
        }, { rootMargin: '0px 0px 100000px 0px' }).observe(map);
        // The page's sections (the room's parts follow its Location section
        // as sections of their own), watched across the screen's lowest
        // eighth only: hidden once the footer rises into it. The footer
        // is a sticky curtain, always within the window behind the page,
        // so it can't be watched itself. (Watched across the whole window,
        // the chip sat on the footer's copyright line.)
        new IntersectionObserver(([entry]) => {
          inView = entry.isIntersecting;
          update();
        }, { rootMargin: '-88% 0px 0px 0px' }).observe(page.closest('.pf-sections') || page);
      });
    },
  };
})(Drupal, once);
