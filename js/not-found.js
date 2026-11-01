/**
 * @file
 * The 404 page (includes/not-found.html.twig): the empty display case.
 *
 * - The case turns with the pointer (idle: a slow sway), and its glare
 *   moves with it.
 * - The wall text behind it is cut into strips. The base copy has a hole
 *   where the glass is; strips clipped to that hole shift like a lens, a
 *   little at rest and more while the case turns, so only the letters seen
 *   through the glass bend.
 * - Dust drifts in the spotlight (a canvas).
 * - The <h1> glitches when the pointer, the page or (on a phone) the
 *   device moves fast: two tinted copies tear into bands that jump
 *   sideways, a few times a second, and fade within half a second. Slow
 *   movement stays under the threshold, so it never twitches while read.
 *   iPhone asks before sharing motion, so a "Tilt to glitch" button asks
 *   once; Android sends it without asking.
 *
 * Frames run only while the case is on screen. With prefers-reduced-motion
 * or Settings > Reduce motion (.pf-a11y-motion) the case stands still at
 * its resting angle, with no dust or glitch. Swup visits detach it.
 */
(function (Drupal, once) {
  'use strict';

  const root = document.documentElement;
  const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches
    || root.classList.contains('pf-a11y-motion');

  // Wall text strips; resting angle and swing of the case (degrees).
  const STRIPS = 12;
  const REST = -24;
  const SWING = 80;
  const MOTES = 60;

  const teardown = new WeakMap();

  /**
   * Splits the wall text into a hidden sizer, a base copy and strips.
   */
  function cutWall(wall) {
    const text = wall.textContent.trim();
    wall.textContent = '';
    const make = (className) => {
      const span = document.createElement('span');
      span.className = className;
      span.textContent = text;
      wall.appendChild(span);
      return span;
    };
    make('pf-404__wall-sizer');
    const base = make('pf-404__wall-base');
    const strips = [];
    for (let k = 0; k < STRIPS; k++) {
      strips.push(make('pf-404__wall-strip'));
    }
    return { base, strips };
  }

  /**
   * Adds the two tinted glitch copies to the <h1>.
   */
  function glitchLayers(title) {
    const text = title.textContent.trim();
    return ['a', 'b'].map((key) => {
      const layer = document.createElement('span');
      layer.className = `pf-404__glitch pf-404__glitch--${key}`;
      layer.setAttribute('aria-hidden', 'true');
      layer.textContent = text;
      title.appendChild(layer);
      return layer;
    });
  }

  /**
   * Shows one of the editors' quips at random over the <h1>.
   *
   * The page is cached, so the server prints the first and the pool
   * (PalefireHooks::notFoundQuips()); each visit draws its own here.
   */
  function pickQuip(el) {
    const eyebrow = el.querySelector('[data-pf-404-quips]');
    let pool = [];
    try {
      pool = JSON.parse(eyebrow ? eyebrow.getAttribute('data-pf-404-quips') : '[]');
    }
    catch (e) {
      return;
    }
    if (Array.isArray(pool) && pool.length > 1) {
      eyebrow.textContent = String(pool[Math.floor(Math.random() * pool.length)]);
    }
  }

  function setup(el) {
    const stage = el.querySelector('[data-pf-404-stage]');
    const box = el.querySelector('[data-pf-404-case]');
    const wall = el.querySelector('[data-pf-404-wall]');
    const title = el.querySelector('[data-pf-404-title]');
    if (!stage || !box || !wall || !title) {
      return () => {};
    }
    const glass = box.querySelectorAll('.pf-404__glass');
    const { base, strips } = cutWall(wall);
    const layers = glitchLayers(title);
    const label = title.firstChild;

    const cleanup = [];
    const listen = (target, type, fn, options) => {
      target.addEventListener(type, fn, options);
      cleanup.push(() => target.removeEventListener(type, fn, options));
    };

    // Dust.
    const canvas = document.createElement('canvas');
    canvas.className = 'pf-404__dust';
    canvas.setAttribute('aria-hidden', 'true');
    stage.appendChild(canvas);
    const ctx = canvas.getContext('2d');
    const motes = Array.from({ length: MOTES }, () => ({
      x: Math.random(),
      y: Math.random(),
      r: Math.random() * 1.4 + 0.4,
      v: Math.random() * 0.00025 + 0.00008,
      ph: Math.random() * 6.28,
    }));
    let dpr = 1;
    const size = () => {
      const r = stage.getBoundingClientRect();
      dpr = window.devicePixelRatio || 1;
      canvas.width = Math.round(r.width * dpr);
      canvas.height = Math.round(r.height * dpr);
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    };
    size();
    listen(window, 'resize', size, { passive: true });

    // Pointer, smoothed.
    const s = { tx: 0, ty: 0, x: 0, y: 0, vx: 0, inside: false };
    listen(el, 'pointermove', (e) => {
      const r = el.getBoundingClientRect();
      s.tx = (e.clientX - r.left) / r.width - 0.5;
      s.ty = (e.clientY - r.top) / r.height - 0.5;
      s.inside = true;
      wake();
    }, { passive: true });
    listen(el, 'pointerleave', () => { s.inside = false; });

    let visible = false;
    let raf = 0;
    let bend = 0;
    let lastRy = REST;

    function pose(ry, rx, sheen) {
      box.style.setProperty('--pf-404-ry', `${ry.toFixed(2)}deg`);
      box.style.setProperty('--pf-404-rx', `${rx.toFixed(2)}deg`);
      box.style.setProperty('--pf-404-sheen', `${sheen.toFixed(1)}%`);
    }

    // Cuts the hole in the base copy and places the strips over it.
    function lens(ry) {
      const hr = wall.getBoundingClientRect();
      let L = Infinity; let R = -Infinity; let T = Infinity; let B = -Infinity;
      glass.forEach((g) => {
        const q = g.getBoundingClientRect();
        L = Math.min(L, q.left); R = Math.max(R, q.right);
        T = Math.min(T, q.top); B = Math.max(B, q.bottom);
      });
      const W = hr.width; const H = hr.height;
      const x0 = L - hr.left; const x1 = R - hr.left; const y0 = T - hr.top; const y1 = B - hr.top;
      base.style.clipPath = `path(evenodd, "M-200 -200 H${W + 200} V${H + 200} H-200 Z M${x0.toFixed(1)} ${y0.toFixed(1)} H${x1.toFixed(1)} V${y1.toFixed(1)} H${x0.toFixed(1)} Z")`;
      const amp = 2.5 + Math.abs(ry - REST) * 0.05 + Math.abs(bend) * 0.7;
      const phase = ry / 12;
      const sign = bend < 0 ? -1 : 1;
      strips.forEach((strip, k) => {
        const top = Math.max(y0, (k / STRIPS) * H);
        const bottom = Math.min(y1, ((k + 1) / STRIPS) * H);
        if (bottom <= top) {
          strip.style.visibility = 'hidden';
          return;
        }
        strip.style.visibility = '';
        const dx = amp * Math.sin(((k + 0.5) / STRIPS) * Math.PI * 2 + phase) * sign;
        // Clipped in the strip's own, shifted frame, so the window stays on the glass.
        strip.style.clipPath = `inset(${top.toFixed(1)}px ${(W - x1 + dx).toFixed(1)}px ${(H - bottom).toFixed(1)}px ${(x0 - dx).toFixed(1)}px)`;
        strip.style.transform = `translateX(${dx.toFixed(2)}px)`;
      });
    }

    function dust(t) {
      const W = canvas.width / dpr; const H = canvas.height / dpr;
      ctx.clearRect(0, 0, W, H);
      const rgb = getComputedStyle(el).getPropertyValue('--pf-404-mote').trim() || '70, 60, 40';
      motes.forEach((o) => {
        o.y -= o.v;
        o.x += Math.sin(t / 2000 + o.ph) * 0.00012 + s.vx * 0.02;
        if (o.y < 0) {
          o.y = 1;
          o.x = Math.random();
        }
        const spread = 0.1 + o.y * 0.32;
        const dx = Math.abs(o.x - 0.5) / spread;
        if (dx > 1) {
          return;
        }
        const a = (1 - dx) * (1 - o.y * 0.6) * (0.35 + 0.25 * Math.sin(t / 900 + o.ph));
        ctx.fillStyle = `rgba(${rgb}, ${a.toFixed(3)})`;
        ctx.beginPath();
        ctx.arc(o.x * W, o.y * H, o.r, 0, 6.29);
        ctx.fill();
      });
    }

    function frame(t) {
      raf = 0;
      if (!visible || !el.isConnected) {
        return;
      }
      if (reduced()) {
        pose(REST, -8, 50);
        bend = 0;
        lens(REST);
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        return;
      }
      if (!s.inside) {
        s.tx = Math.sin(t / 2400) * 0.3;
        s.ty = -0.05;
      }
      const lx = s.x;
      s.x += (s.tx - s.x) * 0.08;
      s.y += (s.ty - s.y) * 0.08;
      s.vx = s.x - lx;
      const ry = REST + s.x * SWING;
      pose(ry, -8 - s.y * 12, 50 + s.x * 80);
      bend += ((ry - lastRy) * 10 - bend) * 0.1;
      lastRy = ry;
      lens(ry);
      dust(t);
      raf = requestAnimationFrame(frame);
    }
    function wake() {
      if (!raf && visible) {
        raf = requestAnimationFrame(frame);
      }
    }

    const io = new IntersectionObserver(([entry]) => {
      visible = entry.isIntersecting;
      wake();
    });
    io.observe(stage);
    cleanup.push(() => io.disconnect());

    // Glitch. Fast movement charges an energy level that decays each frame.
    let energy = 0;
    let graf = 0;
    let nextAt = 0;
    function glitch(t) {
      graf = 0;
      energy *= 0.9;
      if (energy < 0.03 || reduced()) {
        energy = 0;
        layers.forEach((layer) => { layer.style.opacity = ''; });
        title.style.removeProperty('--pf-404-jolt');
        return;
      }
      if (t > nextAt) {
        // A few tears a second, never a strobe.
        nextAt = t + 45 + Math.random() * 80;
        layers.forEach((layer) => {
          const top = Math.random() * 85;
          const band = 6 + Math.random() * 28 * energy;
          layer.style.clipPath = `inset(${top.toFixed(1)}% -5% ${Math.max(0, 100 - top - band).toFixed(1)}% -5%)`;
          layer.style.transform = `translateX(${((Math.random() - 0.5) * 2 * (2 + energy * 14)).toFixed(1)}px)`;
          layer.style.opacity = Math.min(1, energy * 1.8).toFixed(2);
        });
        title.style.setProperty('--pf-404-jolt', Math.random() < energy * 0.45 ? `${((Math.random() - 0.5) * energy * 6).toFixed(1)}px` : '0px');
      }
      graf = requestAnimationFrame(glitch);
    }
    const charge = (amount) => {
      if (amount <= 0 || reduced()) {
        return;
      }
      energy = Math.min(1, energy + amount);
      if (!graf) {
        graf = requestAnimationFrame(glitch);
      }
    };

    let px = null; let py = null;
    listen(window, 'pointermove', (e) => {
      if (px !== null) {
        charge((Math.hypot(e.clientX - px, e.clientY - py) - 14) * 0.006);
      }
      px = e.clientX;
      py = e.clientY;
    }, { passive: true });
    let lastScroll = window.scrollY;
    listen(window, 'scroll', () => {
      charge((Math.abs(window.scrollY - lastScroll) - 25) * 0.004);
      lastScroll = window.scrollY;
    }, { passive: true });

    let lastTilt = null;
    const orient = (e) => {
      if (e.beta === null) {
        return;
      }
      if (lastTilt) {
        charge((Math.abs(e.beta - lastTilt[0]) + Math.abs(e.gamma - lastTilt[1]) - 1.5) * 0.05);
      }
      lastTilt = [e.beta, e.gamma];
    };
    const Orientation = window.DeviceOrientationEvent;
    if (Orientation && window.matchMedia('(pointer: coarse)').matches) {
      if (typeof Orientation.requestPermission === 'function') {
        const tilt = el.querySelector('[data-pf-404-tilt]');
        if (tilt) {
          tilt.hidden = false;
          listen(tilt, 'click', () => {
            Orientation.requestPermission().then((answer) => {
              if (answer === 'granted') {
                listen(window, 'deviceorientation', orient);
              }
              tilt.hidden = true;
            }).catch(() => { tilt.hidden = true; });
          });
        }
      }
      else {
        listen(window, 'deviceorientation', orient);
      }
    }

    // Static first pose, before any frame.
    pose(REST, -8, 50);
    requestAnimationFrame(() => lens(REST));

    return () => {
      cleanup.forEach((fn) => fn());
      cancelAnimationFrame(raf);
      cancelAnimationFrame(graf);
      raf = 0;
      graf = 0;
      if (label) {
        title.textContent = label.textContent;
      }
    };
  }

  Drupal.behaviors.palefireNotFound = {
    attach(context) {
      once('pf-404', '[data-pf-404]', context).forEach((el) => {
        pickQuip(el);
        teardown.set(el, setup(el));
      });
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once.remove('pf-404', '[data-pf-404]', context).forEach((el) => {
        const stop = teardown.get(el);
        if (stop) {
          stop();
        }
        teardown.delete(el);
      });
    },
  };
})(Drupal, once);
