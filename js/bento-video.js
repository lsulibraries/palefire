(function (Drupal, once) {
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
  Drupal.behaviors.palefireBentoVideo = {
    attach(context) {
      once('bento-video', '[data-bento-video]', context).forEach((wrap) => {
        const video = wrap.querySelector('video');
        const btn = wrap.querySelector('[data-bento-video-toggle]');
        const label = btn.querySelector('[data-bento-video-label]') || btn;
        const sources = JSON.parse(wrap.dataset.sources || '[]');
        if (!sources.length) return;
        let i = 0;
        let paused = reduce.matches;
        const play = () => { if (!paused) video.play().catch(() => {}); };
        const load = (n) => { i = n % sources.length; video.src = sources[i]; };
        video.loop = sources.length === 1;
        // Progress bar (the gold line along the button's bottom edge): how
        // far through the current clip. A drop (next clip, loop) jumps back
        // without animating the bar backwards.
        let shown = 0;
        const progress = () => {
          const p = video.duration ? Math.min(1, video.currentTime / video.duration) : 0;
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
        ['timeupdate', 'loadedmetadata', 'seeked'].forEach((type) => video.addEventListener(type, progress));
        const setState = () => {
          btn.dataset.state = paused ? 'paused' : 'playing';
          label.textContent = paused ? Drupal.t('Play background video') : Drupal.t('Pause background video');
        };
        video.addEventListener('ended', () => { load(i + 1); play(); });
        btn.addEventListener('click', () => {
          paused = !paused;
          setState();
          paused ? video.pause() : play();
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
        load(0);
        new IntersectionObserver(([e]) => (e.isIntersecting ? play() : video.pause()),
          { threshold: 0.25 }).observe(wrap);
      });
    },
  };
})(Drupal, once);
