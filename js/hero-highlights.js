/**
 * @file
 * The Special Collections first screen (.pf-landing__stage, split cover,
 * _landing-features.scss).
 *
 * The Highlights are on the film from the start. Once the film is playing it
 * has VIDEO ms, then the column comes to rest on the first Highlight's picture,
 * the featured one, with the film stopped. The VIDEO ms hold while the Pause
 * button is pressed, a row is in hand, or the stage is out of sight. With reduced
 * motion or "Pause background videos" it starts at the rest. .is-shown says the
 * featured picture is up; a row in hand wins over it, in the CSS.
 *
 * The Scroll cue glides to what follows the stage and moves focus there. It is
 * there only at the top of the page, and fades once the page has moved. Once
 * someone has reached what follows the stage or pressed it, it has done its job
 * and stays gone for this page view, keeping its room so nothing shifts. The
 * line's movement is the CSS's; the script says when, once, after IDLE ms with
 * nothing happening at the top of the page.
 *
 * Once the entrance is over, or as soon as someone clicks or presses a key, the
 * sections below are laid out in full (.is-laid, _landing-features.scss), so
 * that jumps to them land exactly.
 *
 * Without script: the film's poster, the rows and no cue. Phones have no stage,
 * so none of this runs there.
 */
(function (Drupal, once) {
  const VIDEO = 5000; // ms
  const TICK = 250; // ms
  const LOAD_LIMIT = 4000; // ms, how long the film may take to start playing
  const AT_TOP = 16; // px, so a trackpad's bounce doesn't flicker the cue
  const IDLE = 10000; // ms
  const LAY_AFTER = 5000; // ms, past the entrance's movement
  const html = document.documentElement;
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
  const still = () => reduce.matches || html.classList.contains('pf-a11y-motion') || html.classList.contains('pf-a11y-pausevideo');
  const IN_HAND = '.pf-hero-banner__link:hover, .pf-hero-banner__link:focus-visible';

  const sequence = (stage) => {
    const list = stage.querySelector('.pf-hero-banners');
    if (!list) {
      return;
    }
    const wrap = stage.querySelector('[data-landing-video]');
    const button = stage.querySelector('[data-landing-video-toggle]');

    let begun = false;
    let playing = false;
    let inView = true;
    let waited = 0;
    let elapsed = 0;
    let timer = 0;

    const rest = () => {
      window.clearInterval(timer);
      stage.classList.add('is-picturing');
      // A picture, or a unit homepage's map banner (its drawing).
      const featured = [...list.children].find((item) => item.querySelector('.pf-hero-banner__media img, .pf-hero-banner__map svg'));
      if (featured) {
        featured.classList.add('is-shown');
      }
      if (!wrap || !button) {
        return;
      }
      wrap.setAttribute('data-pf-film-rest', '');
      wrap.querySelectorAll('video').forEach((video) => video.pause());
      if (button === document.activeElement) {
        list.querySelector('a').focus({ preventScroll: true });
      }
      button.hidden = true;
    };

    // A unit homepage's faded film (.pf-landing--wash) is a ground, not the
    // column's spotlight: the column rests on its first item at once and the
    // film plays on behind, its button left as it is.
    if (stage.closest('.pf-landing--wash')) {
      stage.classList.add('is-picturing');
      const first = [...list.children].find((item) => item.querySelector('.pf-hero-banner__media img, .pf-hero-banner__map svg'));
      if (first) {
        first.classList.add('is-shown');
      }
      return;
    }

    const tick = () => {
      if (!stage.isConnected) {
        window.clearInterval(timer);
        return;
      }
      if (getComputedStyle(stage).display !== 'grid') {
        return;
      }
      if (!wrap || !button || still()) {
        rest();
        return;
      }
      if (!begun) {
        begun = true;
        wrap.setAttribute('data-pf-film-sequence', '');
      }
      if (button.dataset.state === 'paused' || document.hidden || !inView || list.querySelector(IN_HAND)) {
        return;
      }
      if (!playing) {
        waited += TICK;
        playing = waited >= LOAD_LIMIT;
        if (!playing) {
          return;
        }
      }
      stage.classList.add('is-picturing');
      elapsed += TICK;
      button.style.setProperty('--pf-landing-progress', Math.min(1, elapsed / VIDEO).toFixed(4));
      if (elapsed >= VIDEO) {
        rest();
      }
    };

    if (wrap) {
      wrap.addEventListener('playing', () => { playing = true; }, { capture: true, once: true });
    }
    new IntersectionObserver(([entry]) => { inView = entry.isIntersecting; }).observe(stage);
    timer = window.setInterval(tick, TICK);
    tick();
  };

  const cue = (stage) => {
    const button = stage.querySelector('[data-pf-hero-cue]');
    const next = stage.closest('.pf-landing__run').nextElementSibling;
    if (!button || !next) {
      return;
    }
    button.hidden = false;
    const stop = new AbortController();
    const calm = new AbortController();
    let idle = 0;
    const padding = () => parseFloat(getComputedStyle(html).scrollPaddingTop) || 0;
    const retire = () => {
      window.clearTimeout(idle);
      stop.abort();
      calm.abort();
      button.classList.add('is-away');
    };
    const away = () => {
      if (!button.isConnected) {
        retire();
        return;
      }
      const moved = window.scrollY > AT_TOP;
      // Runs on every scroll event: positions from palefire.js's cache, so
      // it never makes the browser lay out the page mid-scroll.
      if (moved && Drupal.palefireLayout.top(next) <= Drupal.palefireLayout.scrollPadding() + 2) {
        retire();
        return;
      }
      button.classList.toggle('is-away', moved);
    };
    window.addEventListener('scroll', away, { passive: true, signal: stop.signal });
    button.addEventListener('click', () => {
      retire();
      const top = Math.round(next.getBoundingClientRect().top + window.scrollY - padding());
      Drupal.palefireGlide(top, () => {
        if (Math.abs(window.scrollY - top) < 2) {
          const heading = next.querySelector('h2, h3') || next;
          heading.setAttribute('tabindex', '-1');
          heading.focus({ preventScroll: true });
        }
      }, { interruptible: true });
    });

    const replay = () => {
      if (!button.isConnected) {
        retire();
      }
      else if (document.hidden || window.scrollY > AT_TOP || getComputedStyle(button).display === 'none') {
        wait();
      }
      else {
        calm.abort();
        button.classList.add('is-replaying');
      }
    };
    const wait = () => {
      window.clearTimeout(idle);
      idle = window.setTimeout(replay, IDLE);
    };
    ['scroll', 'wheel', 'keydown', 'pointerdown'].forEach((type) => {
      window.addEventListener(type, wait, { passive: true, signal: calm.signal });
    });
    wait();
    away();
  };

  const layOut = (stage) => {
    const sections = stage.closest('.pf-sections');
    if (!sections) {
      return;
    }
    const stop = new AbortController();
    const lay = () => {
      stop.abort();
      sections.classList.add('is-laid');
    };
    if (window.location.hash) {
      lay();
      return;
    }
    const later = window.requestIdleCallback || window.setTimeout;
    window.setTimeout(() => later(lay, { timeout: 2000 }), LAY_AFTER);
    ['pointerdown', 'keydown'].forEach((type) => {
      window.addEventListener(type, lay, { passive: true, signal: stop.signal });
    });
  };

  // The map banner (a unit's homepage): the lit room as a location page
  // shows it (js/building-map.js, "Here" marker): the "here" arrow over its
  // name, and the name on a clear plate, a black rect in the room's hatch
  // mask (the stripes) and in a mask over the lines (any wall it crosses),
  // on the name's own plane (it copies the name's --x/--y). Measured from
  // the name's own box, before its transform.
  const SVG_NS = 'http://www.w3.org/2000/svg';
  const markHere = (map) => {
    const svg = map.querySelector('svg');
    const label = svg && svg.querySelector('.pf-map__label.is-active');
    const region = svg && svg.querySelector('.pf-map__region.is-active');
    const hatch = region && region.querySelector('.pf-map__hatch');
    const ref = hatch && /#([^)"']+)/.exec(hatch.getAttribute('mask') || '');
    const mask = ref && document.getElementById(ref[1]);
    if (!label || !mask) {
      return;
    }
    const knocks = [0, 1].map(() => {
      const knock = document.createElementNS(SVG_NS, 'rect');
      knock.setAttribute('class', 'pf-map__knock is-active');
      knock.setAttribute('fill', '#000');
      knock.setAttribute('style', label.getAttribute('style') || '');
      return knock;
    });
    mask.appendChild(knocks[0]);
    const walls = document.createElementNS(SVG_NS, 'mask');
    walls.id = ref[1] + '-here-walls';
    walls.setAttribute('maskUnits', 'userSpaceOnUse');
    const sheet = document.createElementNS(SVG_NS, 'rect');
    sheet.setAttribute('fill', '#fff');
    const view = svg.viewBox.baseVal;
    [walls, sheet].forEach((el) => {
      el.setAttribute('x', view.x);
      el.setAttribute('y', view.y);
      el.setAttribute('width', view.width);
      el.setAttribute('height', view.height);
    });
    walls.append(sheet, knocks[1]);
    mask.parentNode.insertBefore(walls, mask);
    svg.querySelectorAll('.pf-map__lines').forEach((el) => el.setAttribute('mask', 'url(#' + walls.id + ')'));

    const marker = document.createElementNS(SVG_NS, 'g');
    marker.setAttribute('class', 'pf-map__here');
    marker.setAttribute('aria-hidden', 'true');
    const arrow = document.createElementNS(SVG_NS, 'path');
    arrow.setAttribute('class', 'pf-map__here-arrow');
    arrow.setAttribute('d', 'M-46 -92 H46 L0 0 Z');
    arrow.setAttribute('stroke-linejoin', 'round');
    marker.appendChild(arrow);
    svg.appendChild(marker);

    const place = () => {
      const box = label.getBBox();
      if (!box.width) {
        return;
      }
      const pad = (parseFloat(getComputedStyle(label).fontSize) || 62) * 0.3;
      knocks.forEach((knock) => {
        knock.setAttribute('x', (box.x - pad).toFixed(1));
        knock.setAttribute('y', (box.y - pad).toFixed(1));
        knock.setAttribute('width', (box.width + pad * 2).toFixed(1));
        knock.setAttribute('height', (box.height + pad * 2).toFixed(1));
      });
      // Over the room's middle, clear of its name, as drawn.
      const m = svg.getScreenCTM();
      const r = hatch.getBoundingClientRect();
      const top = Math.min(r.top, label.getBoundingClientRect().top);
      if (!m || !r.width) {
        return;
      }
      const pt = new DOMPoint(r.left + r.width / 2, top).matrixTransform(m.inverse());
      marker.setAttribute('transform', 'translate(' + pt.x.toFixed(1) + ' ' + (pt.y - 40).toFixed(1) + ')');
    };
    place();
    if (document.fonts && document.fonts.ready) {
      document.fonts.ready.then(place);
    }
    if ('ResizeObserver' in window) {
      new ResizeObserver(place).observe(svg);
    }
  };

  Drupal.behaviors.palefireHeroHighlights = {
    attach(context) {
      once('hero-highlights', '.pf-landing--cover .pf-landing__stage', context).forEach((stage) => {
        sequence(stage);
        cue(stage);
        layOut(stage);
      });
      once('hero-map-here', '.pf-hero-banner__map', context).forEach(markHere);
    },
  };
})(Drupal, once);
