/**
 * @file
 * Oral History's Quick Start band: the reel-to-reel loop behind it and its
 * Pause / Play button (includes/landing.html.twig, scss/band-reel.scss).
 *
 * The video has no source in the markup, so the page loads only its poster.
 * The loop (WebM, else MP4) is added the first time the band comes on
 * screen, and it plays only while the band is on screen in the visible tab:
 * scrolled away, on another tab or hidden (prefers-contrast, forced colors,
 * the Settings drawer's contrast) it pauses. Reduced motion, or the
 * Settings drawer's Reduce motion or Pause background videos, starts it
 * paused; the button plays it. Turning those settings on later pauses it.
 * Without script the poster stays and the button stays hidden.
 */
(function (Drupal, once) {
  'use strict';

  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
  const html = document.documentElement;
  const startPaused = () => reduce.matches || html.classList.contains('pf-a11y-motion') || html.classList.contains('pf-a11y-pausevideo');
  const TYPES = { webm: 'video/webm', mp4: 'video/mp4' };

  Drupal.behaviors.palefireBandReel = {
    attach(context) {
      once('pf-band-reel', '[data-pf-band-reel]', context).forEach((box) => {
        const video = box.querySelector('video');
        const btn = box.parentElement.querySelector('[data-pf-band-reel-toggle]');
        if (!video || !btn) {
          return;
        }
        const label = btn.querySelector('[data-pf-band-reel-label]') || btn;
        const sources = JSON.parse(video.dataset.sources || '{}');
        video.muted = true;
        video.defaultMuted = true;

        let paused = startPaused();
        let onScreen = false;
        let loaded = false;

        const setState = () => {
          btn.dataset.state = paused ? 'paused' : 'playing';
          label.textContent = paused ? Drupal.t('Play background video') : Drupal.t('Pause background video');
        };
        const load = () => {
          if (loaded) {
            return;
          }
          loaded = true;
          Object.keys(TYPES).forEach((key) => {
            if (sources[key]) {
              const source = document.createElement('source');
              source.src = sources[key];
              source.type = TYPES[key];
              video.append(source);
            }
          });
          video.load();
        };
        const sync = () => {
          if (onScreen && !document.hidden && !paused) {
            load();
            video.play().catch(() => {});
          }
          else {
            video.pause();
          }
        };

        btn.addEventListener('click', () => {
          paused = !paused;
          setState();
          sync();
        });
        document.addEventListener('palefire:a11ychange', () => {
          if (box.isConnected && !paused && startPaused()) {
            paused = true;
            setState();
            sync();
          }
        });
        document.addEventListener('visibilitychange', () => {
          if (box.isConnected) {
            sync();
          }
        });
        // A hidden box (display: none) never intersects, so it never plays.
        new IntersectionObserver(([entry]) => {
          onScreen = entry.isIntersecting;
          sync();
        }).observe(box);

        setState();
        btn.hidden = false;
      });
    },
  };
})(Drupal, once);
