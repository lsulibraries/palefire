/**
 * @file
 * Site footer hours (includes/footer.html.twig).
 *
 * The footer is at the end of every page, so its LibCal hours are not
 * requested on load. When a [data-pf-footer-lid] comes within a screen of
 * the viewport, it gets visit-hours.js's data-pf-hours and data-lid
 * attributes, and that behavior fills it in (one request per location,
 * shared with the Hours drawer and the Visit card).
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
