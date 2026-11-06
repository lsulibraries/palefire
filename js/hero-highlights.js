/**
 * @file
 * The Special Collections first screen (.pf-landing__stage, split cover,
 * _landing-features.scss).
 *
 * The Highlights are on the film from the start. Once the film is playing it
 * has VIDEO ms, then the column shows each Highlight's picture in turn for DWELL
 * ms and comes to rest on the first, the featured one, with the film stopped.
 * The sequence runs once, and holds while the Pause button is pressed, a row is
 * in hand, or the stage is out of sight. With reduced motion or "Pause
 * background videos" it starts at the rest. .is-shown on a row says whose
 * picture is up; a row in hand wins over it, in the CSS.
 *
 * The Scroll cue glides to what follows the stage and moves focus there. It is
 * there only at the top of the page, and fades once the page has moved. Its
 * arrow dips once, if nothing has happened for four seconds after the entrance.
 *
 * Without script: the film's poster, the rows and no cue. Phones have no stage,
 * so none of this runs there.
 */
(function (Drupal, once) {
  const VIDEO = 5000; // ms
  const DWELL = 3500; // ms
  const TICK = 250; // ms
  const LOAD_LIMIT = 4000; // ms, how long the film may take to start playing
  // ms: the entrance's last animation (the film's column, _entrance.scss, ends
  // at 2s), the rows' after it, then four quiet seconds.
  const NUDGE_AFTER = 6500;
  const AT_TOP = 16; // px, so a trackpad's bounce doesn't flicker the cue
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
    const pictures = () => [...list.children].filter((item) => item.querySelector('.pf-hero-banner__media img'));
    const show = (item) => {
      [...list.children].forEach((other) => other.classList.toggle('is-shown', other === item));
    };
    const stopFilm = () => {
      wrap.setAttribute('data-pf-film-rest', '');
      wrap.querySelectorAll('video').forEach((video) => video.pause());
    };

    let begun = false;
    let playing = false;
    let inView = true;
    let waited = 0;
    let elapsed = 0;
    let timer = 0;

    const rest = () => {
      window.clearInterval(timer);
      stage.classList.add('is-picturing');
      show(pictures()[0] || null);
      if (!wrap || !button) {
        return;
      }
      stopFilm();
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
        button.querySelector('[data-landing-video-label]').textContent = Drupal.t('Pause the video and pictures');
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
      const items = pictures();
      const total = VIDEO + DWELL * items.length;
      button.style.setProperty('--pf-landing-progress', Math.min(1, elapsed / total).toFixed(4));
      if (elapsed >= total) {
        rest();
      }
      else if (elapsed >= VIDEO) {
        stopFilm();
        show(items[Math.floor((elapsed - VIDEO) / DWELL)]);
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
    const away = () => {
      if (!button.isConnected) {
        window.removeEventListener('scroll', away);
        return;
      }
      button.classList.toggle('is-away', window.scrollY > AT_TOP);
    };
    window.addEventListener('scroll', away, { passive: true });
    away();
    button.addEventListener('click', () => {
      const pad = parseFloat(getComputedStyle(html).scrollPaddingTop) || 0;
      const top = Math.round(next.getBoundingClientRect().top + window.scrollY - pad);
      Drupal.palefireGlide(top, () => {
        if (Math.abs(window.scrollY - top) < 2) {
          const heading = next.querySelector('h2, h3') || next;
          heading.setAttribute('tabindex', '-1');
          heading.focus({ preventScroll: true });
        }
      }, { interruptible: true });
    });

    const stop = new AbortController();
    const cancel = () => {
      window.clearTimeout(nudge);
      stop.abort();
    };
    const nudge = window.setTimeout(() => {
      const shown = button.isConnected && getComputedStyle(button).display !== 'none';
      cancel();
      if (shown && window.scrollY < 1) {
        button.classList.add('is-nudging');
      }
    }, NUDGE_AFTER);
    ['scroll', 'wheel', 'keydown', 'pointerdown'].forEach((type) => {
      window.addEventListener(type, cancel, { passive: true, signal: stop.signal });
    });
    button.addEventListener('animationend', (event) => {
      if (event.animationName === 'pf-hero-cue-nudge') {
        button.classList.remove('is-nudging');
      }
    });
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
