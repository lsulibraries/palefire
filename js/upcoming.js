/**
 * @file
 * The next events for From the stacks (paragraph--landing-stacks), an
 * ordered list [data-pf-upcoming] with the events carousel's settings as
 * data-*: calendar, campus, endpoint (the carousel's feed), count (how many)
 * and empty (the words when there are none).
 *
 * The feed (libcal_gantt) answers ten weekdays at a time from `offset`
 * weekdays ahead. It filters by campus only for offset 0; later windows
 * come back for every library, so events are kept here only when their
 * campusId is the list's campus. Up to three windows (about six weeks) are
 * read, one after another, until there are `count` events. All-day items
 * (donation bins, displays) are skipped with the carousel's own test, and
 * an event that runs several days is listed once, at its next day.
 *
 * It starts when the list is near the window (IntersectionObserver), so
 * the homepage's first screen doesn't wait on it. The list's height is
 * held by CSS while it loads (aria-busy); a failed request shows the empty
 * words. Links only to http(s) addresses. Styles: _stacks.scss.
 */
(function (Drupal, once) {
  'use strict';

  const WINDOW = 10; // weekdays per feed request
  const WINDOWS = 3;

  function feedUrl(list, offset) {
    const data = list.dataset;
    const params = new URLSearchParams({ offset: String(offset), calendar: data.calendar });
    if (data.campus) {
      params.set('campus', data.campus);
    }
    const endpoint = data.endpoint || '/libcal-gantt/carousel-events';
    return endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + params.toString();
  }

  // "2026-10-21" + 16.5 -> that day at 4:30 PM, local time.
  function at(day, hour) {
    const [y, m, d] = day.split('-').map(Number);
    return new Date(y, m - 1, d, 0, Math.round(hour * 60));
  }

  // The window's upcoming timed events at the list's campus, each at its
  // next day: { event, start }.
  function upcoming(data, campus) {
    const now = Date.now();
    const found = [];
    ((data && data.events) || []).forEach((event) => {
      if (campus && event.campusId !== undefined && String(event.campusId) !== campus) {
        return;
      }
      if (!/^https?:\/\//.test(event.url || '') || !event.title) {
        return;
      }
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
        found.push(next);
      }
    });
    return found;
  }

  function el(tag, className, text) {
    const node = document.createElement(tag);
    node.className = className;
    if (text) {
      node.textContent = text;
    }
    return node;
  }

  // A date block (month over day) and the title, then "Tue · 6:30 PM–8:30
  // PM · Hill Memorial Library". The whole row is the link.
  function item({ event, start }) {
    const li = el('li', 'pf-upcoming__item');
    const link = el('a', 'pf-upcoming__link');
    link.href = event.url;

    const pad = (n) => String(n).padStart(2, '0');
    const date = document.createElement('time');
    date.className = 'pf-upcoming__date';
    date.dateTime = `${start.getFullYear()}-${pad(start.getMonth() + 1)}-${pad(start.getDate())}T${pad(start.getHours())}:${pad(start.getMinutes())}`;
    date.append(
      el('span', 'pf-upcoming__month', start.toLocaleDateString(undefined, { month: 'short' })),
      el('span', 'pf-upcoming__day', String(start.getDate())),
    );

    const text = el('span', 'pf-upcoming__text');
    // A room number alone ("109") says little: the building instead.
    const place = event.location && !/^\d+$/.test(event.location) ? event.location : (event.row || (event.isOnline ? Drupal.t('Online') : ''));
    const when = [start.toLocaleDateString(undefined, { weekday: 'short' }), [event.startLabel, event.endLabel].filter(Boolean).join('–'), place].filter(Boolean).join(' · ');
    text.append(el('span', 'pf-upcoming__title', event.title), el('span', 'pf-upcoming__meta', when));

    const arrow = el('span', 'pf-upcoming__arrow', '→');
    arrow.setAttribute('aria-hidden', 'true');
    link.append(date, text, arrow);
    li.append(link);
    return li;
  }

  async function load(list) {
    const count = parseInt(list.dataset.count, 10) || 3;
    const byId = new Map();
    for (let i = 0; i < WINDOWS && byId.size < count; i++) {
      let data = null;
      try {
        const response = await fetch(feedUrl(list, i * WINDOW), { credentials: 'same-origin' });
        data = response.ok ? await response.json() : null;
      }
      catch (error) {
        data = null;
      }
      if (!data) {
        break;
      }
      upcoming(data, list.dataset.campus || '').forEach((found) => {
        const key = found.event.id || found.event.url;
        const seen = byId.get(key);
        if (!seen || found.start < seen.start) {
          byId.set(key, found);
        }
      });
    }
    const events = Array.from(byId.values()).sort((a, b) => a.start - b.start).slice(0, count);
    if (events.length) {
      list.append(...events.map(item));
    }
    else {
      list.append(el('li', 'pf-upcoming__empty', list.dataset.empty || Drupal.t('No upcoming events')));
    }
    list.setAttribute('aria-busy', 'false');
    list.classList.add('is-ready');
  }

  Drupal.behaviors.palefireUpcoming = {
    attach(context) {
      once('pf-upcoming', '[data-pf-upcoming][data-calendar]', context).forEach((list) => {
        if (!('IntersectionObserver' in window)) {
          load(list);
          return;
        }
        const observer = new IntersectionObserver((entries) => {
          if (entries.some((entry) => entry.isIntersecting)) {
            observer.disconnect();
            load(list);
          }
        }, { rootMargin: '600px 0px' });
        observer.observe(list);
      });
    },
  };
})(Drupal, once);
