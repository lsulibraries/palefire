/**
 * @file
 * Visit card hours: "Open now / Closed now" and today's and this week's
 * hours from LibCal, for [data-pf-hours] elements (includes/landing.html.twig
 * Visit card, field_hours_location on the Modular page).
 *
 * Ported from Origami's bento.js (LibCal JSON-LD hours widget, same status
 * rules) without jQuery, ScrollReveal or the 50 ms polling loop:
 * - Each LibCal location is requested once (JSONP, the only form LibCal's
 *   public hours widget offers), however many cards use it.
 * - The status is re-worked out every minute, so "Closes soon" and
 *   "Opens soon" change on their own without a reload.
 * - If LibCal doesn't answer, the card keeps its "All hours" link and hides
 *   the status line.
 *
 * Markup (all parts optional except the root):
 *   <div data-pf-hours data-lid="241">
 *     <span data-pf-hours-status></span>   Open now | Closes soon | ...
 *     <span data-pf-hours-today></span>    9 AM–5 PM today | Opens tomorrow 9 AM
 *     <ol data-pf-hours-week></ol>         seven rows, today first
 *   </div>
 */
((Drupal, once) => {
  const ENDPOINT = 'https://lsu.libcal.com/widget/hours/grid';
  const SOON = 30 * 60 * 1000;
  const LOOKAHEAD_DAYS = 14;
  const requests = {};

  /* ---------------- LibCal (JSONP, one request per location) ---------------- */

  function load(lid) {
    if (!requests[lid]) {
      requests[lid] = new Promise((resolve, reject) => {
        const cb = 'pfLibcalHours' + lid.replace(/\W/g, '');
        const script = document.createElement('script');
        const timer = window.setTimeout(() => reject(new Error('LibCal timeout')), 10000);
        window[cb] = (data) => {
          window.clearTimeout(timer);
          resolve(data || {});
        };
        script.async = true;
        script.src = `${ENDPOINT}?format=jsonld&lid=${encodeURIComponent(lid)}&org=2&callback=${cb}`;
        script.onerror = () => {
          window.clearTimeout(timer);
          reject(new Error('LibCal unavailable'));
        };
        document.head.appendChild(script);
      });
    }
    return requests[lid];
  }

  /* ---------------- Dates and times (same rules as bento.js) ---------------- */

  // "9 AM", "6:30 PM": a no-break space before AM/PM every time (it was
  // "9AM" on the hour), so the two never part at a line end.
  function formatTime(date) {
    let h = date.getHours();
    const m = date.getMinutes();
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    const nbsp = '\u00a0';
    return m === 0 ? `${h}${nbsp}${ampm}` : `${h}:${String(m).padStart(2, '0')}${nbsp}${ampm}`;
  }

  function ymd(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  }

  function addDays(d, n) {
    const out = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    out.setDate(out.getDate() + n);
    return out;
  }

  function specFor(specs, day) {
    const key = ymd(day);
    return specs.find((spec) => {
      const from = spec.validFrom;
      const thru = spec.validThrough;
      return from === thru ? key === from : key >= from && key < thru;
    }) || null;
  }

  function parse(hhmm) {
    return String(hhmm).split(':').map(Number);
  }

  // { open: Date, close: Date } for that day, or null when closed all day.
  function window_(spec, day) {
    if (!spec || !spec.opens || !spec.closes) {
      return null;
    }
    const [oh, om] = parse(spec.opens);
    const [ch, cm] = parse(spec.closes);
    if (oh === 0 && om === 0 && ch === 0 && cm === 0) {
      return null;
    }
    const open = new Date(day.getFullYear(), day.getMonth(), day.getDate(), oh, om);
    const close = (ch === 0 && cm === 0)
      ? addDays(day, 1)
      : new Date(day.getFullYear(), day.getMonth(), day.getDate(), ch, cm);
    return { open, close };
  }

  // "9 AM–5 PM": a closed-up en dash for a range, as the events show times.
  function range(w) {
    return `${formatTime(w.open)}\u2013${formatTime(w.close)}`;
  }

  function nextOpen(specs, now) {
    for (let i = 1; i <= LOOKAHEAD_DAYS; i++) {
      const day = addDays(now, i);
      const w = window_(specFor(specs, day), day);
      if (w) {
        const label = i === 1
          ? Drupal.t('tomorrow')
          : day.toLocaleDateString('en-US', { weekday: 'long' });
        return `${label} ${formatTime(w.open)}`;
      }
    }
    return null;
  }

  // The bento.js states: open, closes soon, opens soon, opens later today,
  // closed until the next open day (tomorrow or later), closed all day.
  function status(specs, now) {
    const today = window_(specFor(specs, now), now);
    if (today && now >= today.open && now < today.close) {
      const soon = today.close - now <= SOON;
      return {
        state: soon ? 'soon' : 'open',
        label: soon ? Drupal.t('Closes soon') : Drupal.t('Open now'),
        detail: soon
          ? Drupal.t('Closes at @time', { '@time': formatTime(today.close) })
          : Drupal.t('@hours today', { '@hours': range(today) }),
      };
    }
    if (today && now < today.open) {
      const soon = today.open - now <= SOON;
      return {
        state: soon ? 'soon' : 'closed',
        label: soon ? Drupal.t('Opens soon') : Drupal.t('Closed now'),
        detail: Drupal.t('Opens today @time', { '@time': formatTime(today.open) }),
      };
    }
    const next = nextOpen(specs, now);
    return {
      state: 'closed',
      label: Drupal.t('Closed now'),
      detail: next ? Drupal.t('Opens @when', { '@when': next }) : '',
    };
  }

  /* ---------------- Rendering ---------------- */

  function renderWeek(list, specs, now) {
    const rows = [];
    for (let i = 0; i < 7; i++) {
      const day = addDays(now, i);
      const w = window_(specFor(specs, day), day);
      const li = document.createElement('li');
      li.className = 'pf-visit__day' + (i === 0 ? ' is-today' : '') + (w ? '' : ' is-closed');
      const name = document.createElement('span');
      name.className = 'pf-visit__day-name';
      name.textContent = day.toLocaleDateString('en-US', { weekday: 'short' });
      const time = document.createElement('span');
      time.className = 'pf-visit__day-hours';
      time.textContent = w ? range(w) : Drupal.t('Closed');
      li.append(name, time);
      rows.push(li);
    }
    list.replaceChildren(...rows);
  }

  function render(el, data) {
    const specs = (data && data.openingHoursSpecification) || [];
    const now = new Date();
    const s = status(specs, now);
    const statusEl = el.querySelector('[data-pf-hours-status]');
    const todayEl = el.querySelector('[data-pf-hours-today]');
    const weekEl = el.querySelector('[data-pf-hours-week]');
    el.dataset.hoursState = s.state;
    if (statusEl) {
      statusEl.textContent = s.label;
    }
    if (todayEl) {
      todayEl.textContent = s.detail;
    }
    if (weekEl) {
      renderWeek(weekEl, specs, now);
    }
    el.classList.add('is-ready');
    el.classList.remove('is-loading', 'is-failed');
  }

  // Shared with the utility menu's Hours panel (utility-panels.js), so both
  // use one LibCal request per location and the same open/closed rules.
  Drupal.palefireHours = {
    load,
    status,
    today: (specs, now) => window_(specFor(specs, now), now),
    range,
    formatTime,
  };

  // Bootstrap lg: the Visit strip (_visit.scss) has room for the open list.
  const LG = window.matchMedia('(min-width: 992px)');

  Drupal.behaviors.palefireVisitHours = {
    attach(context) {
      once('pf-visit-hours', '[data-pf-hours][data-lid]', context).forEach((el) => {
        // "This week" starts open on lg screens (992px up, the Visit strip's
        // hours column) and closed below. Crossing the breakpoint resets it;
        // otherwise visitors can open and close it as they like.
        const week = el.querySelector('.pf-visit__week');
        if (week) {
          const sync = () => { week.open = LG.matches; };
          sync();
          LG.addEventListener('change', sync);
          el.pfHoursSync = sync;
        }
        el.classList.add('is-loading');
        load(el.dataset.lid).then((data) => {
          render(el, data);
          // Every minute, lined up with the clock, so "soon" states flip on time.
          const tick = () => {
            if (!el.isConnected) {
              window.clearInterval(el.pfHoursTimer);
              return;
            }
            render(el, data);
          };
          window.setTimeout(() => {
            tick();
            el.pfHoursTimer = window.setInterval(tick, 60000);
          }, 60000 - (Date.now() % 60000));
        }).catch(() => {
          el.classList.remove('is-loading');
          el.classList.add('is-failed');
        });
      });
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once.remove('pf-visit-hours', '[data-pf-hours][data-lid]', context).forEach((el) => {
        window.clearInterval(el.pfHoursTimer);
        if (el.pfHoursSync) {
          LG.removeEventListener('change', el.pfHoursSync);
        }
      });
    },
  };
})(Drupal, once);
