/**
 * @file
 * The tape deck beside Oral History's series (landing.html.twig,
 * .pf-series__deck; styled in _landing-features.scss).
 *
 * On wide screens (64rem and up) the section becomes two columns: the
 * heading, its intro and the deck held on the left, the series on the
 * right (.has-deck). The deck's drawing (images/exhibit-deck.svg, the one
 * the exhibitions' object view plays clips on) is fetched once, as the
 * section comes near. Pointing at or focusing a series puts it on the
 * deck: the counter shows its interview count, the caption its name,
 * description and a link to them in search ("View 60 interviews"), and
 * the tape winds to it: the series is the tape loaded, so the supply reel
 * (left) holds as much as its card shows (its share of the largest series,
 * by area); the take-up reel (right) is the deck's own, a few turns
 * threaded, and stays. The reels are turned here, not by CSS, so they spin
 * up and down like a motor's: at an easy fast wind while the supply
 * empties (longer the more it moves), or held still while it fills, as if
 * a fuller reel were loaded (no rewind: it would break the page's calm),
 * then at playing speed, PLAY down and the pinch roller in, for as long as the
 * pointer is on the series or the focus in them, slowing to a stop after.
 * At rest it shows the largest series. Reduced motion (or the Settings
 * drawer's "Reduce motion"): the caption and the tape change, nothing runs.
 *
 * Decorative throughout: the deck and its caption are aria-hidden, and each
 * card already says its name and count and is described by its
 * description. Without this script, or on narrow screens, the section is
 * the heading over the series, as before.
 */
(function (Drupal, once) {
  'use strict';

  const wide = window.matchMedia('(min-width: 64rem)');
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const still = () => motion.matches || document.documentElement.classList.contains('pf-a11y-motion');
  let markup = null;

  const fetchDeck = (url) => {
    markup = markup || fetch(url).then((response) => {
      if (!response.ok) {
        throw new Error(response.statusText);
      }
      return response.text();
    });
    return markup;
  };

  // A card's series: its name, count, description and link.
  const seriesOf = (link) => {
    const text = link.getAttribute('aria-describedby');
    const desc = text ? document.getElementById(text) : null;
    return {
      name: (link.querySelector('.pf-series-reel__name') || {}).textContent || '',
      count: (link.querySelector('.pf-series-reel__count') || {}).textContent || '',
      text: desc ? desc.textContent.trim() : '',
      url: link.href,
    };
  };

  // The tape, redrawn as build/exhibit-deck.js draws it (keep the two in
  // step): the same projection, reels and tape path, for a share of a full
  // supply reel. The drawing's parts carry data-tape.
  const RAD = Math.PI / 180;
  const COS = Math.cos(-35 * RAD);
  const SIN = Math.sin(-35 * RAD);
  const EYE = 2400;
  const pr = (x, y, z) => {
    const X = x - 410;
    const x1 = X * COS + z * SIN;
    const s = EYE / (EYE - (-X * SIN + z * COS));
    return [x1 * s, (y - 450) * s];
  };
  const fmt = (n) => String(Math.round(n * 10) / 10);
  const pointsOf = (pts, z) => pts.map((p) => pr(p[0], p[1], z).map(fmt).join(',')).join(' ');
  const circle = (cx, cy, r) => Array.from({ length: 40 }, (_, i) => [cx + r * Math.cos(i / 20 * Math.PI), cy + r * Math.sin(i / 20 * Math.PI)]);
  const REELS = [{ cx: 130, k: 5 / 7, hub: 44 }, { cx: 690, k: 1, hub: 50 }];
  const AREA = 104 * 104 - 44 * 44;

  const tangent = (A, B) => {
    const dx = B.c[0] - A.c[0];
    const dy = B.c[1] - A.c[1];
    const len = Math.hypot(dx, dy);
    const ux = dx / len;
    const uy = dy / len;
    for (const s1 of [1, -1]) {
      for (const s2 of [1, -1]) {
        for (const sg of [1, -1]) {
          const r1 = s1 * A.r;
          const r2 = s2 * B.r;
          const cs = (r1 - r2) / len;
          if (Math.abs(cs) <= 1) {
            const sn = sg * Math.sqrt(1 - cs * cs);
            const nx = ux * cs - uy * sn;
            const ny = uy * cs + ux * sn;
            const p1 = [A.c[0] + r1 * nx, A.c[1] + r1 * ny];
            const p2 = [B.c[0] + r2 * nx, B.c[1] + r2 * ny];
            const tx = p2[0] - p1[0];
            const ty = p2[1] - p1[1];
            const ok = (u, w) => tx * (w * -u[1]) + ty * (w * u[0]) > 0;
            if (ok([s1 * nx, s1 * ny], A.w) && ok([s2 * nx, s2 * ny], B.w)) {
              return [p1, p2];
            }
          }
        }
      }
    }
    return [A.c, B.c];
  };
  const segment = (a, b) => {
    const n = Math.max(1, Math.ceil(Math.hypot(b[0] - a[0], b[1] - a[1]) / 6));
    return Array.from({ length: n + 1 }, (_, i) => [a[0] + (b[0] - a[0]) * i / n, a[1] + (b[1] - a[1]) * i / n]);
  };
  const arc = (K, a, d) => {
    const a0 = Math.atan2(a[1] - K.c[1], a[0] - K.c[0]);
    let dl = Math.atan2(d[1] - K.c[1], d[0] - K.c[0]) - a0;
    while (K.w > 0 && dl < 0) {
      dl += 2 * Math.PI;
    }
    while (K.w < 0 && dl > 0) {
      dl -= 2 * Math.PI;
    }
    const n = Math.max(2, Math.ceil(Math.abs(dl) * K.r / 5));
    return Array.from({ length: n + 1 }, (_, i) => [K.c[0] + K.r * Math.cos(a0 + dl * i / n), K.c[1] + K.r * Math.sin(a0 + dl * i / n)]);
  };
  const tapePath = (rs, rt) => {
    const cs = [{ c: [130, 140], r: rs, w: -1 }, { c: [67, 522], r: 22, w: -1 }, { c: [164, 483], r: 43, w: 1 }, { c: [617, 473], r: 7.5, w: -1 }, { c: [752, 520], r: 22, w: -1 }, { c: [690, 140], r: rt, w: -1 }];
    const tg = [];
    for (let i = 0; i < 5; i++) {
      tg.push(tangent(cs[i], cs[i + 1]));
    }
    const pts = [];
    let gA = 0;
    let cD = 0;
    for (let i = 0; i < 5; i++) {
      pts.push(...segment(tg[i][0], tg[i][1]));
      if (i < 4) {
        if (i === 1) {
          gA = pts.length - 1;
        }
        pts.push(...arc(cs[i + 1], tg[i][1], tg[i + 1][0]));
        if (i === 2) {
          cD = pts.length - 1;
        }
      }
    }
    const dist = (p, c) => Math.hypot(p[0] - c[0], p[1] - c[1]);
    let j = 0;
    while (j < gA && dist(pts[j], [130, 140]) < 165 * 5 / 7) {
      j++;
    }
    let k = pts.length - 1;
    while (k > cD && dist(pts[k], [690, 140]) < 165) {
      k--;
    }
    return { aIn: pts.slice(0, j + 1), aOut: pts.slice(Math.max(0, j - 1), gA + 1), b: pts.slice(gA, cD + 1), cOut: pts.slice(cD, k + 2), cIn: pts.slice(k) };
  };

  // The take-up reel: a few turns of tape threaded on, whatever is loaded.
  const TAKE_UP = Math.sqrt(50 * 50 + AREA * 0.04);

  const wind = (svg, share) => {
    const parts = {};
    svg.querySelectorAll('[data-tape]').forEach((el) => {
      parts[el.dataset.tape] = el;
    });
    const radii = [Math.sqrt(44 * 44 + AREA * share), TAKE_UP];
    REELS.forEach((reel, i) => {
      if (parts['pack' + i]) {
        parts['pack' + i].setAttribute('points', pointsOf(circle(reel.cx, 140, radii[i]), 21));
      }
      if (parts['edge' + i]) {
        parts['edge' + i].setAttribute('points', pointsOf(circle(reel.cx, 140, Math.max(reel.hub, radii[i] - 16 * reel.k)), 21));
      }
    });
    const path = tapePath(radii[0], radii[1]);
    Object.keys(path).forEach((key) => {
      const pts = path[key];
      if (parts[key] && pts.length > 1) {
        parts[key].setAttribute('points', pointsOf(pts, 15) + ' ' + pointsOf(pts.slice().reverse(), 27));
      }
    });
  };

  const number = (count) => parseInt(count.replace(/\D/g, ''), 10) || 0;
  const digits = (count) => String(number(count)).padStart(4, '0').slice(-4);

  function start(section) {
    const deck = section.querySelector('[data-pf-series-deck]');
    const links = Array.from(section.querySelectorAll('.pf-series__link'));
    if (!deck || !links.length || !window.fetch || !window.DOMParser) {
      return;
    }
    const machine = deck.querySelector('.pf-series__machine');
    const now = deck.querySelector('.pf-series__now');
    const name = now.querySelector('.pf-series__now-name');
    const text = now.querySelector('.pf-series__now-text');
    const open = now.querySelector('.pf-series__now-open');
    const view = now.querySelector('.pf-series__now-view');
    let svg = null;
    let current = null;
    let swap = 0;
    const most = Math.max(1, ...links.map((link) => number(seriesOf(link).count)));
    // How full the supply reel is: the largest series fills it, the
    // smallest still shows a few turns.
    const shareOf = (series) => 0.06 + 0.94 * Math.min(1, number(series.count) / most);
    // As drawn (build/exhibit-deck.js: 65% left on the supply reel).
    let share = 0.65;

    // The motor. Playing speed is the exhibitions' deck (_exhibition.scss
    // .spin: a turn in 5s, the take-up in 7s), counterclockwise; fast wind
    // is WIND times that. Speeds ease toward their target (EASE seconds),
    // so a change of speed is a spin-up or a run-down, never a jump.
    const PLAY = [-360 / 5, -360 / 7];
    const WIND = 4;
    const EASE = 0.3;
    let reels = [[], []];
    const angle = [0, 0];
    const speed = [0, 0];
    let winding = null;
    let held = false;
    let pointerIn = false;
    let motor = 0;
    let last = 0;

    const playing = () => {
      deck.classList.toggle('is-playing', held && !winding);
    };

    const turn = (time) => {
      const dt = last ? Math.min(0.05, (time - last) / 1000) : 0;
      last = time;
      let gear = held ? 1 : 0;
      if (winding) {
        const p = Math.min(1, (time - winding.begin) / winding.length);
        share = winding.from + (winding.to - winding.from) * (p < 0.5 ? 2 * p * p : 1 - Math.pow(-2 * p + 2, 2) / 2);
        wind(svg, share);
        gear = winding.to < winding.from ? WIND : 0;
        if (p >= 1) {
          winding = null;
          playing();
        }
      }
      const k = 1 - Math.exp(-dt / EASE);
      // Runs on while a reel fills with the reels held still.
      let moving = gear !== 0 || winding !== null;
      [0, 1].forEach((i) => {
        speed[i] += (PLAY[i] * gear - speed[i]) * k;
        angle[i] = (angle[i] + speed[i] * dt) % 360;
        reels[i].forEach((el) => {
          el.style.rotate = angle[i].toFixed(2) + 'deg';
        });
        moving = moving || Math.abs(speed[i]) > 0.5;
      });
      if (moving) {
        motor = window.requestAnimationFrame(turn);
      }
      else {
        motor = 0;
        last = 0;
        speed[0] = 0;
        speed[1] = 0;
      }
    };

    const run = () => {
      if (svg && !motor && !still()) {
        last = 0;
        motor = window.requestAnimationFrame(turn);
      }
    };

    const hold = (on) => {
      if (held !== on) {
        held = on;
        playing();
        run();
      }
    };

    const tape = (quiet) => {
      if (!svg || !current) {
        return;
      }
      const to = shareOf(current);
      if (quiet || still()) {
        winding = null;
        share = to;
        wind(svg, share);
        return;
      }
      // Winding forward takes longer the more tape moves (a whole reel in
      // about two seconds); a fuller reel settles in over a second.
      winding = { from: share, to: to, begin: window.performance.now(), length: to < share ? 500 + 1500 * (share - to) : 1000 };
      playing();
      run();
    };

    const counter = () => {
      if (svg && current) {
        const shown = svg.querySelector('.count');
        if (shown) {
          shown.textContent = digits(current.count);
        }
      }
    };

    const put = (link, quiet) => {
      const series = seriesOf(link);
      if (current && current.url === series.url) {
        return;
      }
      current = series;
      const fill = () => {
        name.textContent = series.name;
        text.textContent = series.text;
        // It opens the series' interviews in search: say how many.
        if (view) {
          view.textContent = Drupal.formatPlural(number(series.count), 'View the interview', 'View @count interviews');
        }
        open.href = series.url;
        now.classList.remove('is-swapping');
      };
      counter();
      tape(quiet);
      window.clearTimeout(swap);
      if (quiet || still()) {
        fill();
        return;
      }
      now.classList.add('is-swapping');
      swap = window.setTimeout(fill, 160);
      hold(true);
    };

    const load = () => {
      fetchDeck(deck.dataset.pfSeriesDeck).then((source) => {
        const drawing = new DOMParser().parseFromString(source, 'image/svg+xml').documentElement;
        if (drawing.localName !== 'svg' || svg) {
          return;
        }
        svg = document.importNode(drawing, true);
        const kind = svg.querySelector('.kind');
        const year = svg.querySelector('.year');
        if (kind) {
          kind.textContent = Drupal.t('Interviews').toLocaleUpperCase();
        }
        if (year) {
          year.textContent = '';
        }
        // Fit the column's height, held to its bottom (centered) by the caption.
        svg.setAttribute('preserveAspectRatio', 'xMidYMax meet');
        machine.append(svg);
        reels = [Array.from(svg.querySelectorAll('.spin--0')), Array.from(svg.querySelectorAll('.spin--1'))];
        counter();
        tape(true);
      }).catch(() => {
        // No drawing: the caption still follows the series.
      });
    };

    const largest = links.reduce((best, link) => (number(seriesOf(link).count) > number(seriesOf(best).count) ? link : best), links[0]);
    put(largest, true);

    let loaded = false;
    const layout = () => {
      const on = wide.matches;
      section.classList.toggle('has-deck', on);
      deck.hidden = !on;
      if (on && !loaded) {
        loaded = true;
        if ('IntersectionObserver' in window) {
          const near = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
              near.disconnect();
              load();
            }
          }, { rootMargin: '600px 0px' });
          near.observe(section);
        }
        else {
          load();
        }
      }
    };
    layout();
    wide.addEventListener('change', layout);

    const hand = (event) => {
      const link = event.target.closest('.pf-series__link');
      if (link && section.classList.contains('has-deck')) {
        put(link, false);
      }
    };
    section.addEventListener('mouseover', hand);
    section.addEventListener('focusin', hand);
    // Plays while the pointer is on the series or the focus in them.
    section.addEventListener('pointerenter', () => {
      pointerIn = true;
    });
    section.addEventListener('pointerleave', () => {
      pointerIn = false;
      if (!section.contains(document.activeElement)) {
        hold(false);
      }
    });
    section.addEventListener('focusout', (event) => {
      if (!section.contains(event.relatedTarget) && !pointerIn) {
        hold(false);
      }
    });
  }

  Drupal.behaviors.palefireSeriesDeck = {
    attach(context) {
      once('pf-series-deck', '.pf-series', context).forEach(start);
    },
  };
})(Drupal, once);
