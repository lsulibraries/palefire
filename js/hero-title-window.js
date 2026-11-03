/**
 * @file
 * The title as a window (split cover, _landing-features.scss): on the
 * first load the cover's clip plays inside the letters of the page title,
 * then, once the film's column has faded in beside them (_entrance.scss,
 * pf-split-open), the letters turn gold: the picture passes from the
 * letters to the column, and only then do they take their colour.
 *
 * A canvas laid exactly over the <h1> draws the clip's current frame,
 * a faint layer of the title's own gold over it (so the letters never sink
 * into the page on a dark frame), then keeps only the title's glyphs:
 * each word filled at its own place on the page (Range rects), in the
 * title's font, size and tracking. The <h1> stays real text under it,
 * gold, and shows again as the canvas fades out. Decorative: the canvas
 * is aria-hidden and takes no pointer.
 *
 * Runs only where the column's opening animation runs (so from 64rem of
 * landing, motion allowed, first paint of the page) and only if the clip
 * is ready in time; otherwise nothing happens and the title stays gold.
 * More contrast and forced colours skip it too.
 */
(function (Drupal, once) {
  const OPEN = 'pf-split-open'; // the column's keyframes (_entrance.scss)
  const FADE_IN = 300; // ms
  const FADE_OUT = 1200; // ms, the letters' gold (_entrance.scss, pf-title-fill)
  const VEIL = 0.22; // the gold under the clip's frame

  const skip = () => {
    const html = document.documentElement;
    return window.matchMedia('(prefers-reduced-motion: reduce), (prefers-contrast: more), (forced-colors: active)').matches
      || html.classList.contains('pf-a11y-motion')
      || html.classList.contains('pf-a11y-contrast');
  };

  // The playing layer (a playlist has two, landing-video.js).
  const currentVideo = (media) => media.querySelector('video.is-current') || media.querySelector('video');

  // Each word of the title with its box, relative to the title's.
  const words = (title) => {
    const box = title.getBoundingClientRect();
    const out = [];
    const walker = document.createTreeWalker(title, NodeFilter.SHOW_TEXT);
    for (let node = walker.nextNode(); node; node = walker.nextNode()) {
      const re = /\S+/g;
      let m;
      while ((m = re.exec(node.data))) {
        const range = document.createRange();
        range.setStart(node, m.index);
        range.setEnd(node, m.index + m[0].length);
        const r = range.getClientRects()[0];
        if (r) out.push({ text: m[0], x: r.left - box.left, y: r.top - box.top });
      }
    }
    return out;
  };

  // The frame drawn to cover the canvas, as object-fit: cover would.
  const drawCover = (ctx, video, w, h) => {
    const vw = video.videoWidth;
    const vh = video.videoHeight;
    if (!vw || !vh) return;
    const scale = Math.max(w / vw, h / vh);
    const sw = w / scale;
    const sh = h / scale;
    ctx.drawImage(video, (vw - sw) * 0.58, (vh - sh) * 0.5, sw, sh, 0, 0, w, h);
  };

  Drupal.behaviors.palefireHeroTitleWindow = {
    attach(context) {
      once('hero-title-window', '.pf-landing--cover .pf-landing__lead--hero', context).forEach((lead) => {
        if (skip()) return;
        const media = lead.querySelector(':scope > .pf-landing__media');
        const title = lead.querySelector('.pf-landing__title');
        if (!media || !title || !media.getAnimations) return;
        const open = media.getAnimations().find((a) => a.animationName === OPEN);
        if (!open || !open.effect) return;
        const delay = open.effect.getTiming().delay || 0;
        const span = Number(open.effect.getTiming().duration) || 0;
        // Time left before the column opens; too little and the effect would
        // only flash.
        const until = () => delay - (open.currentTime || 0);
        if (until() < FADE_IN + 800) return;

        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const style = getComputedStyle(title);
        // Canvas tracking must match the title's, or the letters drift.
        if (!ctx || (style.letterSpacing !== 'normal' && !('letterSpacing' in ctx))) return;

        const start = (video) => {
          const left = until();
          if (left < FADE_IN + 500) return;
          const dpr = Math.min(window.devicePixelRatio || 1, 2);
          const w = title.offsetWidth;
          const h = title.offsetHeight;
          canvas.width = Math.round(w * dpr);
          canvas.height = Math.round(h * dpr);
          canvas.setAttribute('aria-hidden', 'true');
          canvas.className = 'pf-landing__title-window';
          title.append(canvas);

          // The letters, drawn once on a canvas of their own: each frame
          // keeps only what lies under them in one step (destination-in
          // per word would clear every word but the last).
          const mask = document.createElement('canvas');
          mask.width = canvas.width;
          mask.height = canvas.height;
          const mctx = mask.getContext('2d');
          mctx.scale(dpr, dpr);
          mctx.font = `${style.fontStyle} ${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
          if ('letterSpacing' in mctx) mctx.letterSpacing = style.letterSpacing;
          mctx.textBaseline = 'alphabetic';
          const ascent = mctx.measureText('Hg').fontBoundingBoxAscent;
          if (!ascent) {
            canvas.remove();
            return;
          }
          words(title).forEach((word) => mctx.fillText(word.text, word.x, word.y + ascent));
          ctx.scale(dpr, dpr);
          const gold = style.color;
          let frame = 0;

          const draw = () => {
            ctx.globalCompositeOperation = 'source-over';
            ctx.clearRect(0, 0, w, h);
            drawCover(ctx, video, w, h);
            ctx.globalAlpha = VEIL;
            ctx.fillStyle = gold;
            ctx.fillRect(0, 0, w, h);
            ctx.globalAlpha = 1;
            ctx.globalCompositeOperation = 'destination-in';
            ctx.drawImage(mask, 0, 0, w, h);
            frame = requestAnimationFrame(draw);
          };
          draw();

          const done = () => {
            cancelAnimationFrame(frame);
            canvas.remove();
          };
          canvas.animate([{ opacity: 0 }, { opacity: 1 }], { duration: FADE_IN, easing: 'ease-out' });
          canvas.animate([{ opacity: 1 }, { opacity: 0 }], {
            // Held until the column has faded in.
            duration: FADE_OUT, delay: left + span, easing: 'ease-in-out', fill: 'forwards',
          }).finished.then(done, done);
        };

        // Wait for the clip's first frame, but not past the point where the
        // effect would be too short to read.
        const wait = (tries) => {
          const video = currentVideo(media);
          if (video && video.readyState >= 2 && !video.paused) {
            if (document.fonts && document.fonts.status !== 'loaded') {
              document.fonts.ready.then(() => start(video));
            }
            else {
              start(video);
            }
          }
          else if (tries > 0 && until() > FADE_IN + 500) {
            setTimeout(() => wait(tries - 1), 100);
          }
        };
        wait(25);
      });
    },
  };
})(Drupal, once);
