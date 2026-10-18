/**
 * @file
 * "Next event" card in the landing hero's lead panel
 * (paragraph--bento-search.html.twig with hero, .pf-hero-event).
 *
 * Reads the events carousel's own feed (drupalSettings.libcalGanttCarousel
 * .endpoint, the libcal_gantt_carousel block), so it only runs on pages
 * that have the carousel, and the card shows the same events. It picks the
 * first timed event that hasn't ended; all-day items such as donation bins
 * and displays are skipped. The feed has no description, so the line under
 * the title is the first category and the place ("Online" when there is no
 * room).
 *
 * The card's space is held by CSS while the feed loads (no layout shift);
 * with no carousel, no upcoming event or a failed request it is removed.
 * Times are the feed's own labels (the library's time zone).
 */
(function (Drupal, drupalSettings, once) {
  'use strict';

  const FALLBACK_ENDPOINT = '/libcal-gantt/carousel-events';
  let feed = null;

  function loadFeed() {
    if (!feed) {
      const settings = drupalSettings.libcalGanttCarousel || {};
      feed = fetch(settings.endpoint || FALLBACK_ENDPOINT, { credentials: 'same-origin' })
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

  function nextEvent(data) {
    const now = Date.now();
    const upcoming = [];
    ((data && data.events) || []).forEach((event) => {
      Object.entries(event.segments || {}).forEach(([day, segment]) => {
        const allDay = segment.startHour <= 0 && segment.endHour >= 23.9;
        const start = at(day, segment.startHour);
        if (!allDay && at(day, segment.endHour).getTime() > now) {
          upcoming.push({ event, start });
        }
      });
    });
    upcoming.sort((a, b) => a.start - b.start);
    return upcoming[0] || null;
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
    const time = document.createElement('time');
    const pad = (n) => String(n).padStart(2, '0');
    time.dateTime = `${start.getFullYear()}-${pad(start.getMonth() + 1)}-${pad(start.getDate())}T${pad(start.getHours())}:${pad(start.getMinutes())}`;
    time.textContent = [dayLabel(start), [event.startLabel, event.endLabel].filter(Boolean).join('–')].filter(Boolean).join(' · ');
    when.append(time);

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

  Drupal.behaviors.palefireHeroEvent = {
    attach(context) {
      once('pf-hero-event', '[data-pf-hero-event]', context).forEach((box) => {
        // Only alongside the events carousel (CSS holds the space then).
        if (!document.querySelector('.pf-events-carousel')) {
          box.remove();
          return;
        }
        // On its own (no lead panel): under the building map's heading,
        // above the drawing (grid row "event", _landing-hero.scss). Moved
        // before the feed loads, so its space is held there.
        const heading = box.classList.contains('pf-hero-event--solo')
          && box.closest('.pf-bento') && box.closest('.pf-bento').querySelector('.pf-map > .pf-map__heading');
        if (heading) {
          heading.after(box);
          box.classList.add('is-in-map');
        }
        loadFeed().then((data) => {
          const next = nextEvent(data);
          // Links and images only from http(s) addresses.
          if (next && /^https?:\/\//.test(next.event.url || '') && next.event.title) {
            render(box, next);
          }
          else {
            box.remove();
          }
        });
      });
    },
  };
})(Drupal, drupalSettings, once);
