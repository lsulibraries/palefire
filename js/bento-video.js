(function (Drupal, once) {
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
  // Crossfade between clips; keep in step with --pf-bento-fade
  // (_bento-search.scss).
  const FADE_MS = 1000;
  Drupal.behaviors.palefireBentoVideo = {
    attach(context) {
      once('bento-video', '[data-bento-video]', context).forEach((wrap) => {
        const first = wrap.querySelector('video');
        const btn = wrap.querySelector('[data-bento-video-toggle]');
        const label = btn.querySelector('[data-bento-video-label]') || btn;
        const sources = JSON.parse(wrap.dataset.sources || '[]');
        if (!sources.length) return;
        let paused = reduce.matches;
        let inView = false;
        const setState = () => {
          btn.dataset.state = paused ? 'paused' : 'playing';
          label.textContent = paused ? Drupal.t('Play background video') : Drupal.t('Pause background video');
        };

        // Two <video> layers, so a playlist never cuts to black: while one
        // plays, the other has already loaded the next clip. Just before the
        // current clip ends the next one starts under it and fades in
        // (opacity, _bento-search.scss), then the old layer loads the clip
        // after that. One clip: a single layer with the native loop.
        const videos = [first];
        if (sources.length > 1) {
          const second = first.cloneNode(false);
          second.removeAttribute('poster');
          second.setAttribute('aria-hidden', 'true');
          first.after(second);
          videos.push(second);
          wrap.classList.add('is-buffered');
        }
        videos.forEach((v) => { v.muted = true; v.defaultMuted = true; });
        first.loop = sources.length === 1;
        let cur = 0; // index into videos
        let clip = 0; // index into sources
        let swapping = false;
        const video = () => videos[cur];
        const spare = () => videos[1 - cur];
        const nextClip = () => (clip + 1) % sources.length;
        const preload = () => {
          if (videos.length < 2) return;
          const v = spare();
          v.preload = 'auto';
          v.src = sources[nextClip()];
          v.load();
        };
        const play = (v = video()) => { if (!paused && inView) v.play().catch(() => {}); };
        const pauseAll = () => videos.forEach((v) => v.pause());

        // Progress bar (the gold line along the button's bottom edge): how
        // far through the current clip. A drop (next clip, loop) jumps back
        // without animating the bar backwards.
        let shown = 0;
        const progress = (event) => {
          if (event && event.target !== video()) return;
          const v = video();
          const p = v.duration ? Math.min(1, v.currentTime / v.duration) : 0;
          if (p < shown) {
            btn.classList.add('is-rewinding');
            btn.style.setProperty('--pf-bento-progress', p.toFixed(4));
            // eslint-disable-next-line no-unused-expressions
            btn.offsetWidth;
            btn.classList.remove('is-rewinding');
          } else {
            btn.style.setProperty('--pf-bento-progress', p.toFixed(4));
          }
          shown = p;
        };

        // Hand over to the spare layer. It only becomes the visible layer
        // once it is really playing, so a slow network shows the last frame
        // of the old clip (an ended video keeps it), never black.
        const swap = () => {
          if (swapping || videos.length < 2) return;
          swapping = true;
          const from = video();
          const to = spare();
          to.currentTime = 0;
          const show = () => {
            to.removeEventListener('playing', show);
            cur = 1 - cur;
            clip = nextClip();
            to.classList.add('is-current');
            from.classList.remove('is-current');
            progress();
            window.setTimeout(() => {
              from.pause();
              swapping = false;
              preload();
            }, FADE_MS);
          };
          to.addEventListener('playing', show);
          if (paused || !inView) {
            // Paused mid-handover: finish it now, on the next clip's first frame.
            to.removeEventListener('playing', show);
            show();
            return;
          }
          to.play().catch(() => { to.removeEventListener('playing', show); swapping = false; });
        };
        videos.forEach((v) => {
          ['timeupdate', 'loadedmetadata', 'seeked'].forEach((type) => v.addEventListener(type, progress));
          v.addEventListener('timeupdate', () => {
            if (v !== video() || !v.duration || paused) return;
            if (v.duration - v.currentTime <= (reduce.matches ? 0.15 : FADE_MS / 1000)) swap();
          });
          v.addEventListener('ended', () => { if (v === video()) swap(); });
        });

        btn.addEventListener('click', () => {
          paused = !paused;
          setState();
          if (paused) pauseAll();
          else play();
        });
        // In the first section, centre the button on the breadcrumb's line
        // (.pf-modular__head, page.html.twig) instead of 1rem below the
        // section's top. Measured against the button's positioned parent,
        // so it holds at any scroll position; re-run when either resizes.
        const align = () => {
          const crumb = document.querySelector('.pf-modular__head .breadcrumb-item');
          const host = btn.offsetParent;
          let top = null;
          if (crumb && host && wrap.closest('.pf-sections > .pf-section:first-child')) {
            const c = crumb.getBoundingClientRect();
            top = Math.round(c.top + c.height / 2 - host.getBoundingClientRect().top - btn.offsetHeight / 2);
          }
          if (top !== null && top >= 0) {
            btn.style.setProperty('--pf-bento-toggle-top', `${top}px`);
          }
          else {
            btn.style.removeProperty('--pf-bento-toggle-top');
          }
        };
        requestAnimationFrame(align);
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(align);
        if ('ResizeObserver' in window) {
          const ro = new ResizeObserver(() => requestAnimationFrame(align));
          [btn.offsetParent, document.querySelector('.pf-modular__head')].forEach((el) => el && ro.observe(el));
        }
        else {
          window.addEventListener('resize', align, { passive: true });
        }
        setState();
        first.classList.add('is-current');
        first.src = sources[0];
        preload();
        new IntersectionObserver(([e]) => {
          inView = e.isIntersecting;
          if (inView) play();
          else pauseAll();
        }, { threshold: 0.25 }).observe(wrap);
      });
    },
  };
})(Drupal, once);
