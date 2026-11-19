/**
 * @file
 * News and events (paragraph--news-events.html.twig).
 *
 * - Selection: the row under a mouse pointer or with keyboard focus selects
 *   its event; on a narrow section (touch, no hover) the row in the middle
 *   of the screen does. The selected event's feature shows, the others go
 *   inert, and its photo crossfades in behind the section.
 * - Photos: only the first is in the markup's src; the others load when the
 *   section comes near the screen.
 * - Freshness: the page is cached for a day, so any event that has ended is
 *   removed here, and the first feature's "Next" gets its countdown ("today",
 *   "tomorrow", "in 6 days", "now"). With every event ended, the events part
 *   is removed and the blog and Instagram cards stay, until the next hourly
 *   cache clear brings the server's fallback (a recent post or the
 *   exhibition on view).
 * - A post or exhibition feature (pf-news--feature) needs none of this.
 */
(function (Drupal, once) {
  'use strict';

  const DAY = 86400000;

  /**
   * Whole calendar days from today to the date, in the visitor's clock.
   */
  const daysUntil = (date, now) => {
    const a = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const b = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    return Math.round((b - a) / DAY);
  };

  const countdown = (start, end, now) => {
    if (start <= now && now < end) {
      return Drupal.t('now');
    }
    const days = daysUntil(start, now);
    if (days <= 0) {
      return Drupal.t('today');
    }
    if (days === 1) {
      return Drupal.t('tomorrow');
    }
    return Drupal.t('in @count days', { '@count': days });
  };

  Drupal.behaviors.palefireNewsEvents = {
    attach(context) {
      once('pf-news', '[data-pf-news].pf-news--events', context).forEach((news) => {
        const now = new Date();
        const part = (i) => news.querySelectorAll(`[data-i="${i}"]`);

        // Drop what has ended since the page was cached.
        news.querySelectorAll('.pf-news__item').forEach((item) => {
          const end = new Date(item.dataset.end);
          if (!Number.isNaN(end.getTime()) && end <= now) {
            part(item.dataset.i).forEach((el) => el.remove());
          }
        });
        const rows = Array.from(news.querySelectorAll('.pf-news__row'));
        if (!rows.length) {
          news.querySelectorAll('.pf-news__backdrop, .pf-news__main').forEach((el) => el.remove());
          news.classList.remove('pf-news--events');
          news.classList.add('pf-news--quiet');
          return;
        }

        const features = Array.from(news.querySelectorAll('.pf-news__feature'));
        const layers = Array.from(news.querySelectorAll('.pf-news__layer'));

        // The first remaining event is the next one.
        const first = features[0];
        features.forEach((feature) => feature.classList.toggle('is-next', feature === first));
        if (first) {
          const when = first.querySelector('[data-pf-news-when]');
          const start = new Date(first.dataset.start);
          const end = new Date(first.dataset.end);
          if (when && !Number.isNaN(start.getTime())) {
            when.textContent = ` · ${countdown(start, end, now)}`;
          }
        }

        let current = null;
        const select = (i) => {
          if (i === current) {
            return;
          }
          current = i;
          rows.forEach((row) => row.classList.toggle('is-on', row.dataset.i === i));
          layers.forEach((layer) => layer.classList.toggle('is-on', layer.dataset.i === i));
          features.forEach((feature) => {
            const on = feature.dataset.i === i;
            feature.classList.toggle('is-on', on);
            feature.inert = !on;
          });
        };
        select(rows[0].dataset.i);

        rows.forEach((row) => {
          row.addEventListener('pointerenter', (event) => {
            if (event.pointerType === 'mouse') {
              select(row.dataset.i);
            }
          });
          row.addEventListener('focus', () => select(row.dataset.i));
        });

        // Load the other photos once the section is close.
        const lazy = Array.from(news.querySelectorAll('.pf-news__layer img[data-src]'));
        const load = () => lazy.forEach((img) => {
          img.src = img.dataset.src;
          img.removeAttribute('data-src');
        });

        if (!('IntersectionObserver' in window)) {
          load();
          return;
        }
        if (lazy.length) {
          const near = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
              near.disconnect();
              load();
            }
          }, { rootMargin: '50% 0px' });
          near.observe(news);
        }

        // On a phone the feature pins under the header and, once it is
        // pinned, the page's breadcrumb bar (.pf-landing__bar); its height
        // goes to --pf-news-bar (news-events.scss).
        const bar = document.querySelector('.pf-landing__bar');
        if (bar && 'ResizeObserver' in window) {
          new ResizeObserver(() => {
            news.style.setProperty('--pf-news-bar', bar.offsetHeight + 'px');
          }).observe(bar);
        }

        // No hover on a narrow section: the row in the middle selects.
        const narrow = window.matchMedia('(hover: none)');
        let middle = null;
        const watch = () => {
          if (middle) {
            middle.disconnect();
            middle = null;
          }
          if (!narrow.matches) {
            return;
          }
          middle = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
              if (entry.isIntersecting) {
                select(entry.target.dataset.i);
              }
            });
          }, { rootMargin: '-45% 0px -45% 0px' });
          rows.forEach((row) => middle.observe(row));
        };
        watch();
        narrow.addEventListener('change', watch);
      });
    },
  };
})(Drupal, once);
