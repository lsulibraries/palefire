/**
 * @file
 * "Next event" for the landing hero (includes/landing.html.twig with
 * hero): the first of the hero's banners (.pf-hero-banner), which it turns
 * into the next event, keeping the banner it was as the backup when there
 * is none; or a card on its own by the map when the hero has no lead
 * panel (.pf-hero-event).
 *
 * Reads the events carousel's own feed (drupalSettings.libcalGanttCarousel
 * .endpoint, the libcal_gantt_carousel block), so it only runs on pages
 * that have the carousel. It asks with the page's first carousel's own
 * Calendars and Libraries (its data-calendar and data-campus), as
 * day-carousel.js does, so the card is always one of the events the
 * carousel lists. It picks the first timed event that hasn't ended;
 * all-day items such as donation bins and displays are skipped, with the
 * carousel's own all-day test. The feed has no description, so the line
 * under the title is the first category and the place ("Online" when
 * there is no room).
 *
 * The banner keeps its size, so swapping it shifts nothing; the card's
 * space is held by CSS while the feed loads (no layout shift). With no
 * carousel, no upcoming event or a failed request the banner stays the
 * backup and the card is removed.
 * Times are the feed's own labels (the library's time zone).
 */
(function (Drupal, drupalSettings, once) {
  'use strict';

  const FALLBACK_ENDPOINT = '/libcal-gantt/carousel-events';
  let feed = null;

  // The same query day-carousel.js sends, from the carousel's own data-*
  // attributes, so both read the same calendars and libraries.
  function feedUrl(carousel) {
    const settings = drupalSettings.libcalGanttCarousel || {};
    const endpoint = settings.endpoint || FALLBACK_ENDPOINT;
    const params = new URLSearchParams({ offset: '0' });
    const data = carousel ? carousel.dataset : {};
    if (data.calendar) {
      params.set('calendar', data.calendar);
    }
    if (data.campus) {
      params.set('campus', data.campus);
    }
    return endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + params.toString();
  }

  function loadFeed(carousel) {
    if (!feed) {
      feed = fetch(feedUrl(carousel), { credentials: 'same-origin' })
        .then((response) => (response.ok ? response.json() : null))
        .catch(() => null);
    }
    return feed;
  }

  // "2026-10-21" + 16.5 -> that day at 4:30 PM, local time.
  function at(day, hour) {
    const [y, m, d] = day.split('-').map(Number);
    return new Date(y, m - 1, d, 0, Math.round(hour * 60));
  }

  // The upcoming timed events, soonest first, each once (a multi-day event
  // at its next day).
  function nextEvents(data) {
    const now = Date.now();
    const upcoming = [];
    ((data && data.events) || []).forEach((event) => {
      let next = null;
      Object.entries(event.segments || {}).forEach(([day, segment]) => {
        // Same test as day-carousel.js ("All day" on its slides).
        const allDay = segment.startHour <= 0.01 && segment.endHour >= 23.9;
        const start = at(day, segment.startHour);
        if (!allDay && at(day, segment.endHour).getTime() > now && (!next || start < next.start)) {
          next = { event, start };
        }
      });
      if (next) {
        upcoming.push(next);
      }
    });
    return upcoming.sort((a, b) => a.start - b.start);
  }

  function dayLabel(start) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const days = Math.round((new Date(start).setHours(0, 0, 0, 0) - today) / 864e5);
    if (days === 0) {
      return Drupal.t('Today');
    }
    if (days === 1) {
      return Drupal.t('Tomorrow');
    }
    return start.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
  }

  function el(tag, className, text) {
    const node = document.createElement(tag);
    node.className = className;
    if (text) {
      node.textContent = text;
    }
    return node;
  }

  // "Tomorrow · 4:00 PM–5:30 PM", as a <time>.
  function timeOf(event, start) {
    const time = document.createElement('time');
    const pad = (n) => String(n).padStart(2, '0');
    time.dateTime = `${start.getFullYear()}-${pad(start.getMonth() + 1)}-${pad(start.getDate())}T${pad(start.getHours())}:${pad(start.getMinutes())}`;
    time.textContent = [dayLabel(start), [event.startLabel, event.endLabel].filter(Boolean).join('–')].filter(Boolean).join(' · ');
    return time;
  }

  function render(box, { event, start }) {
    // The whole card is the link.
    const card = el('a', 'pf-hero-event__card');
    card.href = event.url;

    // Square thumbnail, or the hatched placeholder (CSS) when there is none.
    // Decorative: the title says what the event is.
    const thumb = el('span', 'pf-hero-event__thumb');
    if (/^https:\/\//.test(event.image || '')) {
      const img = document.createElement('img');
      img.src = event.image;
      img.alt = '';
      img.width = 96;
      img.height = 96;
      img.decoding = 'async';
      thumb.append(img);
    }

    const text = el('div', 'pf-hero-event__text');
    const when = el('p', 'pf-hero-event__when');
    when.append(timeOf(event, start));

    const title = el('span', 'pf-hero-event__title', event.title);

    const place = event.location || (event.isOnline ? Drupal.t('Online') : '');
    const meta = [(event.categories || [])[0], place].filter(Boolean).join(' · ');

    text.append(when, title);
    if (meta) {
      text.append(el('p', 'pf-hero-event__meta', meta));
    }
    card.append(thumb, text);
    box.append(card);
    box.classList.add('is-ready');
  }

  // The first banner as the next event: the event's picture (cropped from
  // the top: they are often flyers), else its date set large on the
  // banner's dark ground; "Next event · Tue, Oct 27" over the title, the
  // time and place under it. The whole banner is the event's link.
  function renderBanner(box, { event, start }) {
    const link = box.querySelector('.pf-hero-banner__link');
    if (!link) {
      return;
    }
    link.href = event.url;
    const media = el('span', 'pf-hero-banner__media');
    media.setAttribute('aria-hidden', 'true');
    if (/^https:\/\//.test(event.image || '')) {
      const img = document.createElement('img');
      img.src = event.image;
      img.alt = '';
      img.width = 600;
      img.height = 900;
      img.decoding = 'async';
      media.classList.add('pf-hero-banner__media--event');
      media.append(img);
    }
    else {
      const lang = document.documentElement.lang || undefined;
      media.classList.add('pf-hero-banner__media--date');
      media.append(
        el('span', 'pf-hero-banner__day', String(start.getDate())),
        el('span', 'pf-hero-banner__month', start.toLocaleDateString(lang, { month: 'short', year: 'numeric' })),
      );
    }
    const text = el('span', 'pf-hero-banner__text');
    text.append(el('span', 'pf-hero-banner__label', Drupal.t('Next event') + ' · ' + dayLabel(start)));
    text.append(el('span', 'pf-hero-banner__title', event.title));
    const place = event.location && !/^\d+$/.test(event.location) ? event.location : (event.row || (event.isOnline ? Drupal.t('Online') : ''));
    const time = timeOf(event, start);
    time.textContent = [event.startLabel, event.endLabel].filter(Boolean).join('–');
    const meta = el('span', 'pf-hero-banner__meta');
    meta.append(time);
    if (place) {
      meta.append(' · ' + place);
    }
    text.append(meta);
    link.replaceChildren(media, text);
    box.classList.add('is-event');
  }

  // No upcoming event (or the feed failed): a banner keeps the banner it
  // was (the backup); the card on its own is removed.
  function renderEmpty(box) {
    if (!box.classList.contains('pf-hero-banner')) {
      box.remove();
    }
  }

  Drupal.behaviors.palefireHeroEvent = {
    attach(context) {
      once('pf-hero-event', '[data-pf-hero-event]', context).forEach((box) => {
        // Only alongside the events carousel or From the stacks' events
        // list (CSS holds the space then). Either carries the calendar and
        // campus as data-*: the carousel block's container, or the list.
        const carousel = document.querySelector('.pf-events-carousel .libcal-day-carousel, [data-pf-upcoming][data-calendar]');
        if (!carousel && !document.querySelector('.pf-events-carousel')) {
          renderEmpty(box);
          return;
        }
        // On its own (no lead panel): under the building map's heading,
        // above the drawing (grid row "event", _landing-hero.scss). Moved
        // before the feed loads, so its space is held there.
        const heading = box.classList.contains('pf-hero-event--solo')
          && box.closest('.pf-landing') && box.closest('.pf-landing').querySelector('.pf-map > .pf-map__heading');
        if (heading) {
          heading.after(box);
          box.classList.add('is-in-map');
        }
        loadFeed(carousel).then((data) => {
          // Links and images only from http(s) addresses.
          const next = nextEvents(data).find(({ event }) => /^https?:\/\//.test(event.url || '') && event.title);
          if (next) {
            if (box.classList.contains('pf-hero-banner')) {
              renderBanner(box, next);
            }
            else {
              render(box, next);
            }
          }
          else {
            renderEmpty(box);
          }
        });
      });
    },
  };
})(Drupal, drupalSettings, once);
