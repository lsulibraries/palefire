/**
 * @file
 * What Endures, the Oral History podcast: Play in the docked listening bar.
 *
 * Every [data-pf-podcast-src] button (includes/podcast.html.twig, the hero's
 * episode banner in landing.html.twig) plays its episode in one bar at the
 * foot of the screen: the exhibitions' own (.pf-listen, the same markup as
 * js/exhibition.js builds, styled in _exhibition.scss), with the custom
 * player's round Play, seek and time. Its title goes to the episode list;
 * its close button, or Escape, stops it and returns focus to the button
 * that played it. One clip at a time on the page: an exhibition clip
 * playing pauses the episode, and the other way round.
 *
 * The MP3 is requested only on Play (preload none).
 */
(function (Drupal, once) {
  'use strict';

  const state = { bar: null, audio: null, trigger: null };

  const clock = (seconds) => {
    if (!isFinite(seconds) || seconds < 0) {
      return '0:00';
    }
    const total = Math.floor(seconds);
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = String(total % 60).padStart(2, '0');
    return h ? h + ':' + String(m).padStart(2, '0') + ':' + s : m + ':' + s;
  };

  // Every Play button for the episode that is loaded shows its state.
  function syncButtons() {
    const audio = state.audio;
    const src = audio ? audio.dataset.src : '';
    document.querySelectorAll('[data-pf-podcast-src]').forEach((button) => {
      const playing = !!audio && !audio.paused && button.dataset.pfPodcastSrc === src;
      button.classList.toggle('is-playing', playing);
      const text = button.querySelector('[data-pf-podcast-state]');
      if (text) {
        text.textContent = (playing ? Drupal.t('Pause @title', { '@title': button.dataset.pfPodcastTitle }) : Drupal.t('Play @title', { '@title': button.dataset.pfPodcastTitle }));
      }
    });
  }

  function close() {
    const { bar, audio, trigger } = state;
    if (!bar) {
      return;
    }
    audio.pause();
    audio.removeAttribute('src');
    audio.load();
    state.audio = null;
    state.bar = null;
    bar.classList.remove('is-shown');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('pf-a11y-motion');
    setTimeout(() => bar.remove(), reduced ? 0 : 600);
    syncButtons();
    if (trigger && trigger.isConnected) {
      trigger.focus({ preventScroll: true });
    }
  }

  // The bar, as js/exhibition.js builds it, with the player inside.
  function build() {
    const bar = document.createElement('section');
    bar.className = 'pf-listen pf-listen--podcast';
    bar.setAttribute('aria-label', Drupal.t('Now playing'));
    bar.innerHTML = '<div class="pf-listen__inner">'
      + '<button type="button" class="pf-listen__expand">'
      + '<span class="pf-listen__kicker"></span>'
      + '<span class="pf-listen__title"></span>'
      + '<span class="visually-hidden">' + Drupal.t(', go to the episodes') + '</span>'
      + '<svg class="pf-listen__icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="square" aria-hidden="true" focusable="false"><path d="M8 2.5v10M3.5 8.5 8 13l4.5-4.5"/></svg>'
      + '</button>'
      + '<div class="pf-listen__player"><div class="pf-audio is-custom" data-pf-audio>'
      + '<button type="button" class="pf-audio__play"><svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path class="pf-audio__icon-play" d="M5 3.2v9.6L12.6 8z"/><path class="pf-audio__icon-pause" d="M4.5 3h2.5v10H4.5zM9 3h2.5v10H9z"/></svg><span class="visually-hidden"></span></button>'
      + '<input type="range" class="pf-audio__seek" min="0" max="1000" step="1" value="0">'
      + '<span class="pf-audio__time" aria-hidden="true">0:00 / 0:00</span>'
      + '<audio preload="none"></audio>'
      + '</div></div>'
      + '<button type="button" class="pf-listen__close">'
      + '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="square" aria-hidden="true" focusable="false"><path d="M3.5 3.5l9 9m0-9-9 9"/></svg>'
      + '<span class="visually-hidden">' + Drupal.t('Stop and close the player') + '</span>'
      + '</button>'
      + '</div>';

    const wrap = bar.querySelector('.pf-audio');
    const audio = bar.querySelector('audio');
    const play = bar.querySelector('.pf-audio__play');
    const playText = play.querySelector('.visually-hidden');
    const seek = bar.querySelector('.pf-audio__seek');
    const time = bar.querySelector('.pf-audio__time');
    seek.setAttribute('aria-label', Drupal.t('Seek'));

    const sync = () => {
      const paused = audio.paused;
      wrap.classList.toggle('is-playing', !paused);
      playText.textContent = paused ? Drupal.t('Play') : Drupal.t('Pause');
      const duration = audio.duration || 0;
      if (!seek.matches(':active')) {
        seek.value = duration ? String(Math.round(audio.currentTime / duration * 1000)) : '0';
      }
      seek.style.setProperty('--pf-audio-progress', (Number(seek.value) / 10) + '%');
      time.textContent = clock(audio.currentTime) + ' / ' + clock(duration);
      seek.setAttribute('aria-valuetext', Drupal.t('@now of @total', { '@now': clock(audio.currentTime), '@total': clock(duration) }));
    };
    ['play', 'pause', 'timeupdate', 'durationchange', 'ended'].forEach((type) => audio.addEventListener(type, () => {
      sync();
      if (type !== 'timeupdate') {
        syncButtons();
      }
    }));
    // One clip at a time: this one pauses the page's others.
    audio.addEventListener('play', () => {
      document.querySelectorAll('[data-pf-audio] audio').forEach((other) => {
        if (other !== audio) {
          other.pause();
        }
      });
    });
    play.addEventListener('click', () => (audio.paused ? audio.play() : audio.pause()));
    seek.addEventListener('input', () => {
      if (audio.duration) {
        audio.currentTime = Number(seek.value) / 1000 * audio.duration;
      }
      sync();
    });

    bar.querySelector('.pf-listen__expand').addEventListener('click', () => {
      const list = document.getElementById('podcast');
      if (!list) {
        return;
      }
      const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      list.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
      const current = list.querySelector('[data-pf-podcast-src="' + CSS.escape(audio.dataset.src || '') + '"]');
      (current || list).focus({ preventScroll: true });
    });
    bar.querySelector('.pf-listen__close').addEventListener('click', close);
    bar.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        close();
      }
    });

    sync();
    return { bar, audio };
  }

  function start(button) {
    const src = button.dataset.pfPodcastSrc;
    // The same episode: Play and Pause.
    if (state.audio && state.audio.dataset.src === src) {
      state.trigger = button;
      if (state.audio.paused) {
        state.audio.play();
      }
      else {
        state.audio.pause();
      }
      return;
    }
    if (!state.bar) {
      Object.assign(state, build());
      document.body.appendChild(state.bar);
      requestAnimationFrame(() => requestAnimationFrame(() => state.bar && state.bar.classList.add('is-shown')));
    }
    state.trigger = button;
    state.bar.querySelector('.pf-listen__kicker').textContent = button.dataset.pfPodcastKicker || Drupal.t('Now playing');
    state.bar.querySelector('.pf-listen__title').textContent = button.dataset.pfPodcastTitle || '';
    state.audio.dataset.src = src;
    state.audio.src = src;
    const playing = state.audio.play();
    if (playing && playing.catch) {
      playing.catch(() => syncButtons());
    }
  }

  Drupal.behaviors.palefirePodcast = {
    attach(context) {
      once('pf-podcast', '[data-pf-podcast-src]', context).forEach((button) => {
        button.addEventListener('click', () => start(button));
      });
      // The list takes focus from the bar's title.
      once('pf-podcast-list', '#podcast', context).forEach((list) => list.setAttribute('tabindex', '-1'));
    },
  };
})(Drupal, once);
