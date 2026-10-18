/**
 * @file
 * Exhibition tiles in the landing hero's lead (includes/landing.html.twig,
 * [data-pf-exhibit-rotate]): a tile with several images crossfades through
 * them, one every INTERVAL ms. The fade itself is CSS (.is-current,
 * _landing-features.scss).
 *
 * - A pause/play button (WCAG 2.2.2): it is in the markup, hidden, and
 *   shown here, so without JS there is no button and nothing moves.
 * - It only runs while the tile is on screen and the tab is visible.
 * - Off for prefers-reduced-motion and the Settings drawer's "Reduce
 *   motion" (html.pf-a11y-motion): the first image stays, no button.
 * - Hover or focus inside the tile holds the current image.
 */
(function (Drupal, once) {
  'use strict';

  const INTERVAL = 7000;
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const reduceMotion = () => motion.matches || document.documentElement.classList.contains('pf-a11y-motion');

  Drupal.behaviors.palefireExhibitRotate = {
    attach(context) {
      once('pf-exhibit-rotate', '[data-pf-exhibit-rotate]', context).forEach((tile) => {
        const frames = Array.from(tile.querySelectorAll('.pf-exhibit__frame'));
        const button = tile.querySelector('[data-pf-exhibit-pause]');
        const label = button && button.querySelector('[data-pf-exhibit-pause-label]');
        if (frames.length < 2 || reduceMotion()) {
          return;
        }

        let current = Math.max(0, frames.findIndex((frame) => frame.classList.contains('is-current')));
        let timer = 0;
        let paused = false;
        let visible = false;
        let held = false;

        const show = (index) => {
          frames[current].classList.remove('is-current');
          current = index % frames.length;
          frames[current].classList.add('is-current');
        };
        const stop = () => {
          window.clearInterval(timer);
          timer = 0;
        };
        const sync = () => {
          const run = !paused && visible && !held && !document.hidden && !reduceMotion();
          if (run && !timer) {
            timer = window.setInterval(() => show(current + 1), INTERVAL);
          }
          else if (!run && timer) {
            stop();
          }
        };

        if (button) {
          button.hidden = false;
          button.setAttribute('data-state', 'playing');
          button.addEventListener('click', () => {
            paused = !paused;
            button.setAttribute('data-state', paused ? 'paused' : 'playing');
            if (label) {
              label.textContent = paused ? Drupal.t('Play slideshow') : Drupal.t('Pause slideshow');
            }
            sync();
          });
        }

        // Hold while the visitor is looking at or working in the tile.
        tile.addEventListener('pointerenter', () => { held = true; sync(); });
        tile.addEventListener('pointerleave', () => { held = false; sync(); });
        tile.addEventListener('focusin', () => { held = true; sync(); });
        tile.addEventListener('focusout', () => { held = false; sync(); });

        if ('IntersectionObserver' in window) {
          new IntersectionObserver(([entry]) => {
            visible = entry.isIntersecting;
            sync();
          }).observe(tile);
        }
        else {
          visible = true;
        }
        document.addEventListener('visibilitychange', sync);
        motion.addEventListener('change', sync);
        sync();
      });
    },
  };
})(Drupal, once);
