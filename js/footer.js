/**
 * @file
 * Site footer hours (includes/footer.html.twig).
 *
 * The footer is at the end of every page, so its LibCal hours are not
 * requested on load. When a [data-pf-footer-lid] comes within a screen of
 * the viewport, it gets visit-hours.js's data-pf-hours and data-lid
 * attributes, and that behavior fills it in (one request per location,
 * shared with the Hours drawer and the Visit card).
 *
 * Ask (Special Collections) goes to the question form (#ask-question). On
 * the page that has it, a click glides there (Drupal.palefireGlide, as the
 * "On this page" links do): the browser's own jump to a #hash already in
 * the address sat for seconds after a wheel scroll, or never moved. It
 * replaces the address, and on arrival focuses the panel for keyboard and
 * screen reader users and restarts its gold frame (_ask.scss), which
 * otherwise plays only once. Elsewhere the link goes there as usual.
 */
((Drupal, once) => {
  const start = (el) => {
    el.dataset.pfHours = '';
    el.dataset.lid = el.dataset.pfFooterLid;
    Drupal.behaviors.palefireVisitHours.attach(el.parentNode);
  };

  const observer = 'IntersectionObserver' in window
    ? new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          observer.unobserve(entry.target);
          start(entry.target);
        }
      });
    }, { rootMargin: '0px 0px 100% 0px' })
    : null;

  Drupal.behaviors.palefireFooterAsk = {
    attach(context) {
      once('pf-footer-ask', '.pf-footer a[href$="#ask-question"]', context).forEach((link) => {
        link.addEventListener('click', (event) => {
          const panel = document.getElementById(link.hash.slice(1));
          if (
            !panel
            || link.pathname !== window.location.pathname
            || event.button !== 0
            || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
          ) {
            return;
          }
          event.preventDefault();
          window.history.replaceState(window.history.state, '', link.hash);
          const arrived = () => {
            if (!panel.hasAttribute('tabindex')) {
              panel.setAttribute('tabindex', '-1');
              panel.addEventListener('blur', () => panel.removeAttribute('tabindex'), { once: true });
            }
            panel.focus({ preventScroll: true });
            // Off, a reflow, then back on: the browser starts it afresh.
            panel.style.animation = 'none';
            panel.getBoundingClientRect();
            panel.style.removeProperty('animation');
          };
          if (!Drupal.palefireGlide) {
            panel.scrollIntoView({ block: 'start' });
            arrived();
            return;
          }
          // Under the header, at the panel's scroll-margin (its frame).
          const pad = parseFloat(getComputedStyle(document.documentElement).scrollPaddingTop) || 0;
          const margin = parseFloat(getComputedStyle(panel).scrollMarginTop) || 0;
          Drupal.palefireGlide(window.scrollY + panel.getBoundingClientRect().top - pad - margin, arrived, { interruptible: true });
        });
      });
    },
  };

  Drupal.behaviors.palefireFooter = {
    attach(context) {
      once('pf-footer', '[data-pf-footer-lid]', context).forEach((el) => {
        if (observer) {
          observer.observe(el);
        }
        else {
          start(el);
        }
      });
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once.remove('pf-footer', '[data-pf-footer-lid]', context).forEach((el) => {
        if (observer) {
          observer.unobserve(el);
        }
      });
    },
  };
})(Drupal, once);
