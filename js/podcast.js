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
 *
 * The section's head shows one episode: the one pointed at or focused in
 * the list, else the one playing. Its photo (the feed's, 500px, set only
 * then) fades in as the section's backdrop, and the cassette's tape is its
 * length, the longest on the page a full reel; while it plays the reels
 * turn and the tape winds across.
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

  // The episode the section's head shows (includes/podcast.html.twig rows):
  // the one pointed at or focused, else the one playing, else the newest
  // (its tape only, no photo).
  const view = { rows: [], longest: 1, shown: null, page: 0, turn: null };
  const PER_PAGE = 3;
  const OFF = 'pf-podcast__row--off';
  const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('pf-a11y-motion');

  const rowFor = (src) => view.rows.find((row) => {
    const button = row.querySelector('[data-pf-podcast-src]');
    return button && button.dataset.pfPodcastSrc === src;
  }) || null;

  const loaded = (row) => !!row && !!state.audio && rowFor(state.audio.dataset.src || '') === row;

  // A tape pack's size: its area grows with the tape on it, so the radius
  // is sqrt(hub² + (full² − hub²) × share), as a scale of the full pack
  // (46 on the drawing's grid, the bare spool 26: a real cassette's 21.6 mm
  // spool on a 100 mm shell). The longest episode on the
  // page fills the reel; the others are their share of it.
  const pack = (share) => {
    const s = Math.max(0, Math.min(1, share));
    return (Math.sqrt(676 + 1440 * s) / 46).toFixed(3);
  };

  // The tape between a guide roller at the foot's corner and its pack
  // (scale: the pack's, 1 full), on the face's grid: the run comes off the
  // roller's outer side and leaves the pack along their common tangent, so
  // it meets the pack at any size, and its width runs back along the
  // thickness (3, −2). The left run; the right is its mirror.
  function tapeGeometry(scale, right) {
    const r = 46 * scale;
    const len = Math.hypot(42, 74);
    const ux = 42 / len;
    const uy = -74 / len;
    const cos = (3.5 - r) / len;
    const sin = Math.sqrt(1 - cos * cos);
    const nx = cos * ux + sin * uy;
    const ny = cos * uy - sin * ux;
    const x = (v) => +(right ? 240 - v : v).toFixed(2);
    const y = (v) => +v.toFixed(2);
    const a = [x(28 + 3.5 * nx), y(142 + 3.5 * ny)];
    const b = [x(70 + r * nx), y(68 + r * ny)];
    return {
      edge: `M${x(28)} 145.5A3.5 3.5 0 0 ${right ? 0 : 1} ${a[0]} ${a[1]}L${b[0]} ${b[1]}`,
      ribbon: `M${a[0]} ${a[1]}L${b[0]} ${b[1]}L${y(b[0] + 3)} ${y(b[1] - 2)}L${y(a[0] + 3)} ${y(a[1] - 2)}Z`,
    };
  }

  // Redraws a mark's tape runs: as attributes, and as the d property where a
  // browser has it, so they ease with the packs (scss/podcast.scss).
  function drawTape(mark, side, scale) {
    const geometry = tapeGeometry(Number(scale), side === 'right');
    mark.querySelectorAll(`.pf-cassette__tape--${side}, .pf-cassette__ribbon--${side}`).forEach((path) => {
      const d = path.classList.contains('pf-cassette__ribbon') ? geometry.ribbon : geometry.edge;
      path.setAttribute('d', d);
      path.style.setProperty('d', `path("${d}")`);
    });
  }

  // The cassette mark (includes/cassette-mark.html.twig): its tape is the
  // shown episode's length, and while that episode plays the reels turn and
  // the tape winds from the left pack to the right.
  function syncCassettes() {
    const audio = state.audio;
    // While an episode plays, the cassette stays on it: pointing at
    // another row changes the head's text, not the tape.
    const current = audio && !audio.paused ? rowFor(audio.dataset.src || '') : null;
    const row = current || view.shown;
    const mine = loaded(row);
    const playing = mine && !audio.paused;
    const progress = mine && audio.duration ? Math.min(1, audio.currentTime / audio.duration) : 0;
    const share = row ? Number(row.dataset.pfPodcastLength || 0) / view.longest : 1;
    const time = row ? row.querySelector('time') : null;
    const length = time ? time.textContent.trim() : '';
    document.querySelectorAll('.pf-cassette').forEach((mark) => {
      mark.classList.toggle('is-playing', playing);
      const left = pack(share * (1 - progress));
      const right = pack(share * progress);
      mark.style.setProperty('--pf-pack-left', left);
      mark.style.setProperty('--pf-pack-right', right);
      drawTape(mark, 'left', left);
      drawTape(mark, 'right', right);
      // Pointed at, the tape winds on or off the left pack: the reels turn
      // by the length that moved (the pack's area, two turns for a full
      // reel), the way a rewind turns them. Playing, they spin instead.
      const before = Number(mark.dataset.pfPackLeft || left);
      if (!playing) {
        const turn = Number(mark.dataset.pfReelTurn || 0) + (left * left - before * before) * 720;
        mark.dataset.pfReelTurn = turn.toFixed(1);
        mark.style.setProperty('--pf-reel-turn', `${turn.toFixed(1)}deg`);
      }
      mark.dataset.pfPackLeft = left;
      const type = mark.querySelector('.pf-cassette__type--end');
      if (type && length) {
        type.textContent = Drupal.t('ORAL HISTORY · @time', { '@time': length });
      }
    });
    document.querySelectorAll('.pf-podcast-head__read').forEach((read) => {
      read.textContent = mine ? Drupal.t('Playing @now / @total', { '@now': clock(audio.currentTime), '@total': length }) : length;
      read.classList.toggle('is-playing', playing);
    });
  }

  // The section's backdrop: two pictures, the new one fading in over the
  // old once it has loaded. Nothing loads until an episode is shown.
  function backdrop(src) {
    document.querySelectorAll('.pf-podcast-backdrop').forEach((box) => {
      if ((box.dataset.src || '') === src) {
        return;
      }
      box.dataset.src = src;
      const pictures = Array.from(box.querySelectorAll('img'));
      if (!src) {
        pictures.forEach((picture) => picture.classList.remove('is-on'));
        return;
      }
      const next = pictures.find((picture) => !picture.classList.contains('is-on')) || pictures[0];
      const reveal = () => {
        if (box.dataset.src === src) {
          pictures.forEach((picture) => picture.classList.toggle('is-on', picture === next));
        }
      };
      next.onload = reveal;
      next.src = src;
      if (next.complete && next.naturalWidth) {
        reveal();
      }
    });
  }

  // The head tells the episode pointed at, focused or playing: its number
  // and date, first paragraph and credits in place of the Lede, and its
  // photo's credit under the cassette. Back to the Lede at rest.
  let told = null;
  function tell(row) {
    if (row === told) {
      return;
    }
    told = row;
    const data = row ? row.dataset : {};
    document.querySelectorAll('.pf-podcast-head').forEach((head) => {
      const episode = head.querySelector('.pf-podcast-head__episode');
      const lede = head.querySelector('.pf-podcast-head__lede');
      const credit = head.querySelector('.pf-podcast-head__credit');
      if (episode) {
        if (row) {
          episode.querySelector('.pf-podcast-head__kicker').textContent = data.pfPodcastKicker || '';
          episode.querySelector('.pf-podcast-head__summary').textContent = data.pfPodcastAbout || '';
          const credits = episode.querySelector('.pf-podcast-head__credits');
          credits.textContent = data.pfPodcastCredits || '';
          credits.hidden = !data.pfPodcastCredits;
          episode.classList.remove('is-swapped');
          // Restart the fade-in for each episode.
          void episode.offsetWidth;
          episode.classList.add('is-swapped');
        }
        episode.hidden = !row;
        if (lede) {
          lede.hidden = !!row;
        }
      }
      if (credit) {
        // The text stays while the line fades out.
        if (row && data.pfPodcastCredit) {
          credit.textContent = data.pfPodcastCredit;
        }
        credit.classList.toggle('is-on', !!(row && data.pfPodcastCredit));
      }
    });
  }

  function show(row, photo) {
    view.shown = row;
    view.rows.forEach((other) => other.classList.toggle('is-current', other === row && photo));
    backdrop(row && photo ? row.dataset.pfPodcastPhoto || '' : '');
    tell(row && photo ? row : null);
    syncCassettes();
  }

  // Pointer and focus gone from the list: the playing episode, or the
  // newest's tape on a bare section.
  function rest() {
    const playing = state.audio ? rowFor(state.audio.dataset.src || '') : null;
    show(playing || view.rows[0] || null, !!playing);
  }

  // Every Play button for the episode that is loaded shows its state.
  function syncButtons() {
    syncCassettes();
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
    rest();
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
      else {
        syncCassettes();
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
      const row = rowFor(audio.dataset.src || '');
      if (row && view.turn) {
        view.turn(Math.floor(view.rows.indexOf(row) / PER_PAGE), false, true);
      }
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
    // The head shows it: its photo, its tape.
    const row = rowFor(src);
    if (row) {
      show(row, true);
    }
    const playing = state.audio.play();
    if (playing && playing.catch) {
      playing.catch(() => syncButtons());
    }
  }

  // Three episodes to a page (includes/podcast.html.twig), so the head that
  // tells the one pointed at stays in view beside the list. The arrows page
  // newer and older; the count says the page (read out politely). Focus
  // stays on the arrow pressed, so it can be pressed again; at an end it
  // goes to the other. The playing episode keeps the head wherever its page
  // is. The box holds the tallest page's height, so nothing under it moves.
  function pages(list) {
    const pager = list.querySelector('[data-pf-podcast-pager]');
    const box = list.querySelector('.pf-podcast__episodes');
    const count = Math.ceil(view.rows.length / PER_PAGE);
    if (!pager || !box || count < 2) {
      return;
    }
    const buttons = Array.from(pager.querySelectorAll('[data-pf-podcast-page]'));
    const number = pager.querySelector('[data-pf-podcast-n]');
    const said = pager.querySelector('[data-pf-podcast-said]');
    const pad = (n) => String(n).padStart(2, '0');
    const on = (page) => view.rows.filter((row, i) => Math.floor(i / PER_PAGE) === page);
    let moving = null;
    let target = null;

    // A row off its page: a class, not the hidden attribute. [hidden] is
    // tested inside :has() elsewhere in the stylesheet, so toggling it made
    // Chrome re-check every :has() up the page and restyle the whole page
    // (about 2,000 elements) on each pass of hold(): 9 passes, 0.9s at 4x
    // CPU on load (2026-11-19). No :has() names this class.
    const off = (row, away) => row.classList.toggle(OFF, away);

    // Every page laid out in turn, the box held at the tallest.
    const hold = () => {
      box.style.minHeight = '';
      let tallest = 0;
      for (let page = 0; page < count; page++) {
        view.rows.forEach((row, i) => off(row, Math.floor(i / PER_PAGE) !== page));
        tallest = Math.max(tallest, box.offsetHeight);
      }
      view.rows.forEach((row, i) => off(row, Math.floor(i / PER_PAGE) !== view.page));
      box.style.minHeight = tallest + 'px';
    };

    const set = (page, announce) => {
      view.page = page;
      view.rows.forEach((row, i) => off(row, Math.floor(i / PER_PAGE) !== page));
      number.textContent = pad(page + 1);
      if (announce) {
        const rows = on(page);
        const numbers = {
          '@page': page + 1,
          '@count': count,
          '@first': rows[0].querySelector('.pf-podcast__num').textContent,
          '@last': rows[rows.length - 1].querySelector('.pf-podcast__num').textContent,
        };
        // A last page of one: "episode 01", not "episodes 01 to 01".
        said.textContent = rows.length > 1 ? Drupal.t('Page @page of @count, episodes @first to @last', numbers) : Drupal.t('Page @page of @count, episode @first', numbers);
      }
      buttons.forEach((button) => {
        const step = Number(button.dataset.pfPodcastPage);
        const end = step < 0 ? page === 0 : page === count - 1;
        if (end && document.activeElement === button) {
          buttons.find((other) => other !== button).focus();
        }
        button.disabled = end;
      });
      // The head let go of a row now out of sight (none playing).
      if (view.shown && view.shown.classList.contains(OFF) && !(state.audio && !state.audio.paused)) {
        rest();
      }
    };

    // Out to one side, in from the other, a row at a time (at once without
    // motion, or when the bar asks for the playing episode's page).
    view.turn = (page, announce, now) => {
      page = Math.max(0, Math.min(count - 1, page));
      if (moving) {
        moving.cancel();
        moving = null;
      }
      target = null;
      if (page === view.page) {
        return;
      }
      const toward = page > view.page ? 1 : -1;
      if (now || reduceMotion() || !box.animate) {
        set(page, announce);
        return;
      }
      target = page;
      const ease = 'cubic-bezier(.2, .7, .2, 1)';
      const list = box.firstElementChild;
      moving = list.animate([{ opacity: 1, transform: 'none' }, { opacity: 0, transform: 'translateX(' + (-toward * 12) + 'px)' }], { duration: 160, easing: 'ease-in', fill: 'forwards' });
      moving.onfinish = () => {
        moving.cancel();
        moving = null;
        target = null;
        set(page, announce);
        on(page).forEach((row, i) => {
          row.animate([{ opacity: 0, transform: 'translateX(' + (toward * 16) + 'px)' }, { opacity: 1, transform: 'none' }], { duration: 420, delay: i * 40, easing: ease, fill: 'backwards' });
        });
      };
    };

    buttons.forEach((button) => {
      button.addEventListener('click', () => view.turn((target === null ? view.page : target) + Number(button.dataset.pfPodcastPage), true));
    });
    pager.hidden = false;
    set(0, false);
    hold();
    // Again once the type has loaded (the rows' heights change with it).
    if (document.fonts) {
      document.fonts.ready.then(hold);
    }
    let wait = 0;
    window.addEventListener('resize', () => {
      cancelAnimationFrame(wait);
      wait = requestAnimationFrame(hold);
    }, { passive: true });
  }

  Drupal.behaviors.palefirePodcast = {
    attach(context) {
      once('pf-podcast', '[data-pf-podcast-src]', context).forEach((button) => {
        button.addEventListener('click', () => start(button));
      });
      // The cassette draws itself once, when it comes into view.
      once('pf-cassette', '.pf-cassette', context).forEach((mark) => {
        if (!('IntersectionObserver' in window)) {
          return;
        }
        // Undrawn until then (only where motion is allowed: podcast.scss).
        mark.classList.add('is-waiting');
        const observer = new IntersectionObserver((entries) => {
          if (entries.some((entry) => entry.isIntersecting)) {
            mark.classList.replace('is-waiting', 'is-in');
            observer.disconnect();
          }
        }, { threshold: 0.6 });
        observer.observe(mark);
      });
      // The list takes focus from the bar's title.
      once('pf-podcast-list', '#podcast', context).forEach((list) => {
        list.setAttribute('tabindex', '-1');
        // Each row, pointed at or focused, shows its episode in the head.
        view.rows = Array.from(list.querySelectorAll('[data-pf-podcast-length]'));
        view.longest = Math.max(1, ...view.rows.map((row) => Number(row.dataset.pfPodcastLength) || 0));
        view.rows.forEach((row) => {
          row.addEventListener('mouseenter', () => show(row, true));
          row.addEventListener('focusin', () => show(row, true));
        });
        pages(list);
        list.addEventListener('mouseleave', () => {
          if (!list.contains(document.activeElement)) {
            rest();
          }
        });
        list.addEventListener('focusout', (event) => {
          if (!list.contains(event.relatedTarget) && !list.matches(':hover')) {
            rest();
          }
        });
        rest();
      });
    },
  };
})(Drupal, once);
