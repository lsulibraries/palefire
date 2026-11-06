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
 * Without script: the film's poster, the rows and no cue. Phones have no stage,
 * so none of this runs there.
 */
(function (Drupal, once) {
  const VIDEO = 5000; // ms
  const TICK = 250; // ms
  const LOAD_LIMIT = 4000; // ms, how long the film may take to start playing
  const AT_TOP = 16; // px, so a trackpad's bounce doesn't flicker the cue
  const IDLE = 10000; // ms
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
      const featured = [...list.children].find((item) => item.querySelector('.pf-hero-banner__media img'));
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
      if (moved && next.getBoundingClientRect().top <= padding() + 2) {
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

  Drupal.behaviors.palefireHeroHighlights = {
    attach(context) {
      once('hero-highlights', '.pf-landing--cover .pf-landing__stage', context).forEach((stage) => {
        sequence(stage);
        cue(stage);
      });
    },
  };
})(Drupal, once);
