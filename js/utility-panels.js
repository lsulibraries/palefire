/**
 * @file
 * Utility menu panels: Bootstrap offcanvas drawers from the left
 * (templates/includes/utility-panels.html.twig).
 *
 * Bootstrap does the opening, closing, focus trap, Escape and backdrop.
 * This file only adds:
 * - <html data-pf-panel="hours"> while a panel is open, so the backdrop
 *   can dim more on light pages than on dark ones (_utility-panels.scss).
 * - "See full hours" goes to the URL of the menu link that opened the
 *   panel (falls back to the LibCal hours page in the markup).
 * - Focus moves to the search box when the Search panel opens.
 * - Links inside a panel close it first, so a Swup visit never leaves an
 *   open drawer (the panels are outside #swup and are kept between pages).
 * - Hours panel: today's date, a clock that ticks on the minute, and an
 *   open / closed row per LibCal location. LibCal is only asked the first
 *   time the panel opens, and through Drupal.palefireHours
 *   (visit-hours.js), so a location the Visit card already loaded is not
 *   requested again.
 */
((Drupal, once) => {
  const ROOT = document.documentElement;

  function clockText(date) {
    const h = date.getHours() % 12 || 12;
    const m = String(date.getMinutes()).padStart(2, '0');
    return `${h}:${m} ${date.getHours() >= 12 ? 'PM' : 'AM'}`;
  }

  function dateText(date) {
    return date
      .toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })
      .replace(',', '');
  }

  // "Opens tomorrow 9AM" -> <span class="pf-hours__when-lead">Opens</span>
  // tomorrow 9AM (Origami's light first word, bold rest).
  function setWhen(el, text) {
    const [lead, ...rest] = String(text).split(' ');
    if (!rest.length || !/^(Opens|Closes)$/.test(lead)) {
      el.textContent = text;
      return;
    }
    const span = document.createElement('span');
    span.className = 'pf-hours__when-lead';
    span.textContent = lead;
    el.replaceChildren(span, ` ${rest.join(' ')}`);
  }

  function renderLocation(li, data) {
    const H = Drupal.palefireHours;
    const specs = (data && data.openingHoursSpecification) || [];
    const now = new Date();
    const s = H.status(specs, now);
    const today = H.today(specs, now);
    const open = !!today && now >= today.open && now < today.close;
    const badge = li.querySelector('[data-pf-hours-badge]');
    const when = li.querySelector('[data-pf-hours-when]');
    const name = li.querySelector('[data-pf-hours-name]');

    li.dataset.hoursState = open ? (s.state === 'soon' ? 'closing' : 'open') : (s.state === 'soon' ? 'opening' : 'closed');
    if (badge) {
      badge.textContent = open ? Drupal.t('Open') : Drupal.t('Closed');
    }
    if (when) {
      // Open: today's range ("7AM – 12AM"); closing soon: "Closes at …";
      // closed: when it opens next.
      setWhen(when, open && s.state !== 'soon' ? H.range(today) : (s.detail || s.label));
    }
    if (name && !name.hasAttribute('data-fixed')) {
      name.textContent = (data && data.name) || Drupal.t('LibCal location @lid', { '@lid': li.dataset.lid });
    }
    li.classList.remove('is-loading', 'is-failed');
  }

  function initHours(panel) {
    const dateEl = panel.querySelector('[data-pf-clock-date]');
    const clockEl = panel.querySelector('[data-pf-clock]');
    const rows = Array.from(panel.querySelectorAll('[data-pf-hours-loc][data-lid]'));
    const loaded = new Map();
    let timer = null;
    let started = false;

    const tick = () => {
      const now = new Date();
      if (dateEl) {
        dateEl.textContent = dateText(now);
      }
      if (clockEl) {
        clockEl.textContent = clockText(now);
        clockEl.dateTime = now.toISOString();
      }
      rows.forEach((li) => {
        if (loaded.has(li)) {
          renderLocation(li, loaded.get(li));
        }
      });
    };

    const start = () => {
      tick();
      if (!started && Drupal.palefireHours) {
        started = true;
        rows.forEach((li) => {
          Drupal.palefireHours.load(li.dataset.lid).then((data) => {
            loaded.set(li, data);
            renderLocation(li, data);
          }).catch(() => {
            li.classList.remove('is-loading');
            li.classList.add('is-failed');
            const when = li.querySelector('[data-pf-hours-when]');
            if (when) {
              when.textContent = Drupal.t('Hours unavailable');
            }
          });
        });
      }
      // Line the updates up with the minute, then once a minute.
      window.clearTimeout(timer);
      const next = () => {
        timer = window.setTimeout(() => {
          tick();
          next();
        }, 60000 - (Date.now() % 60000) + 50);
      };
      next();
    };

    const stop = () => {
      window.clearTimeout(timer);
      timer = null;
    };

    panel.addEventListener('show.bs.offcanvas', start);
    panel.addEventListener('hidden.bs.offcanvas', stop);
  }

  Drupal.behaviors.palefireUtilityPanels = {
    attach(context) {
      once('pf-utility-panels', '[data-pf-panels]', context).forEach((wrap) => {
        wrap.querySelectorAll('[data-pf-panel]').forEach((panel) => {
          const key = panel.dataset.pfPanel;

          panel.addEventListener('show.bs.offcanvas', (event) => {
            ROOT.dataset.pfPanel = key;
            const trigger = event.relatedTarget;
            const cta = panel.querySelector('[data-pf-panel-href]');
            const href = trigger && trigger.getAttribute ? trigger.getAttribute('href') : null;
            if (cta && href && href !== '#' && !href.startsWith('#')) {
              cta.setAttribute('href', href);
            }
          });

          panel.addEventListener('shown.bs.offcanvas', () => {
            const field = panel.querySelector('[data-pf-panel-autofocus]');
            if (field) {
              field.focus();
            }
          });

          panel.addEventListener('hidden.bs.offcanvas', () => {
            if (ROOT.dataset.pfPanel === key) {
              delete ROOT.dataset.pfPanel;
            }
          });

          // Close before following a link, so Swup swaps the page under a
          // closed drawer and focus isn't returned to a stale trigger.
          panel.addEventListener('click', (event) => {
            const link = event.target.closest('a[href]');
            if (link && window.bootstrap) {
              window.bootstrap.Offcanvas.getOrCreateInstance(panel).hide();
            }
          });

          if (key === 'hours') {
            initHours(panel);
          }
        });
      });
    },
  };
})(Drupal, once);
