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
 * - Ask Us: each tab's LibAnswers iframe gets its src the first time the
 *   tab is shown, so nothing loads from LibAnswers until the drawer opens.
 * - Settings: saves the visitor's choices (localStorage "pf-a11y") and
 *   applies them to <html> at once. html.html.twig applies the saved
 *   copy before the first paint on every page load. Sends
 *   palefire:a11ychange (bento-video.js pauses on it).
 * - Utility icon tooltips: Escape hides the one showing (WCAG 1.4.13).
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

  /* ---------------- Ask Us ---------------- */

  function loadFrame(pane) {
    const frame = pane && pane.querySelector('iframe[data-src]');
    if (frame) {
      frame.src = frame.dataset.src;
      frame.removeAttribute('data-src');
    }
  }

  function initAsk(panel) {
    panel.addEventListener('show.bs.offcanvas', () => {
      loadFrame(panel.querySelector('.pf-ask__pane.active'));
    });
    panel.querySelectorAll('[data-bs-toggle="tab"]').forEach((tab) => {
      tab.addEventListener('show.bs.tab', () => {
        loadFrame(panel.querySelector(tab.dataset.bsTarget));
      });
    });
  }

  /* ---------------- Settings ---------------- */

  const STORE = 'pf-a11y';
  const SIZES = ['100', '115', '130', '150'];
  const SWITCHES = ['font', 'spacing', 'underline', 'contrast', 'solid', 'focus', 'motion', 'pausevideo'];
  const FONT_ID = 'pf-font-atkinson';
  const FONT_URL = 'https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible+Next:ital,wght@0,200..800;1,200..800&display=swap';

  function readSettings() {
    try {
      return JSON.parse(window.localStorage.getItem(STORE)) || {};
    }
    catch (e) {
      return {};
    }
  }

  function saveSettings(settings) {
    try {
      if (Object.keys(settings).length) {
        window.localStorage.setItem(STORE, JSON.stringify(settings));
      }
      else {
        window.localStorage.removeItem(STORE);
      }
      return true;
    }
    catch (e) {
      return false; // Private mode or storage blocked: still applied to this page.
    }
  }

  // The same job as the inline script in html.html.twig, for live changes.
  function applySettings(settings) {
    if (SIZES.includes(settings.size) && settings.size !== '100') {
      ROOT.setAttribute('data-pf-text-size', settings.size);
    }
    else {
      ROOT.removeAttribute('data-pf-text-size');
    }
    SWITCHES.forEach((key) => ROOT.classList.toggle(`pf-a11y-${key}`, !!settings[key]));
    // The font file is only requested once someone turns it on.
    if (settings.font && !document.getElementById(FONT_ID)) {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.id = FONT_ID;
      link.href = FONT_URL;
      document.head.appendChild(link);
    }
    document.dispatchEvent(new CustomEvent('palefire:a11ychange', { detail: { settings } }));
  }

  function initSettings(panel) {
    const form = panel.querySelector('[data-pf-settings]');
    const status = panel.querySelector('[data-pf-settings-status]');
    const reset = panel.querySelector('[data-pf-settings-reset]');
    if (!form) {
      return;
    }
    let statusTimer = null;

    // Show the saved state each time the drawer opens (another tab may
    // have changed it).
    const fill = () => {
      const settings = readSettings();
      const size = SIZES.includes(settings.size) ? settings.size : '100';
      form.querySelectorAll('input[name="size"]').forEach((input) => {
        input.checked = input.value === size;
      });
      SWITCHES.forEach((key) => {
        const input = form.elements[key];
        if (input) {
          input.checked = !!settings[key];
        }
      });
    };

    const announce = (text) => {
      // Clear, then set on the next frame, so the same words are read again.
      window.clearTimeout(statusTimer);
      status.textContent = '';
      window.requestAnimationFrame(() => {
        status.textContent = text;
        statusTimer = window.setTimeout(() => { status.textContent = ''; }, 4000);
      });
    };

    const collect = () => {
      const settings = {};
      const size = form.querySelector('input[name="size"]:checked');
      if (size && size.value !== '100') {
        settings.size = size.value;
      }
      SWITCHES.forEach((key) => {
        const input = form.elements[key];
        if (input && input.checked) {
          settings[key] = 1;
        }
      });
      return settings;
    };

    form.addEventListener('change', () => {
      const settings = collect();
      applySettings(settings);
      announce(saveSettings(settings)
        ? Drupal.t('Saved on this device.')
        : Drupal.t('Applied to this page. This browser is not saving settings.'));
    });
    form.addEventListener('submit', (event) => event.preventDefault());

    if (reset) {
      reset.addEventListener('click', () => {
        saveSettings({});
        applySettings({});
        fill();
        announce(Drupal.t('Settings reset to default.'));
      });
    }

    panel.addEventListener('show.bs.offcanvas', fill);
    fill();

    // Another tab changed the settings: follow along.
    window.addEventListener('storage', (event) => {
      if (event.key === STORE) {
        applySettings(readSettings());
        fill();
      }
    });
  }

  /* ---------------- Utility icon tooltips ---------------- */

  // The label shows on hover/focus (CSS). Escape hides it until the pointer
  // or focus leaves that icon.
  function initTooltips() {
    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') {
        return;
      }
      document.querySelectorAll('.pf-utility-nav__link:is(:hover, :focus-visible)').forEach((link) => {
        link.classList.add('is-tip-hidden');
      });
    });
    const clear = (event) => {
      const link = event.target.closest && event.target.closest('.pf-utility-nav__link');
      if (link && !link.contains(event.relatedTarget)) {
        link.classList.remove('is-tip-hidden');
      }
    };
    document.addEventListener('pointerout', clear);
    document.addEventListener('focusout', clear);
  }

  Drupal.behaviors.palefireUtilityPanels = {
    attach(context) {
      once('pf-utility-tips', 'html').forEach(initTooltips);
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
          else if (key === 'ask') {
            initAsk(panel);
          }
          else if (key === 'settings') {
            initSettings(panel);
          }
        });
      });
    },
  };
})(Drupal, once);
