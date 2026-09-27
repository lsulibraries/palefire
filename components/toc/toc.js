/**
 * @file
 * Palefire TOC: highlights the section in view and closes the mobile panel
 * after a link is chosen.
 */
((Drupal, once) => {
  const observers = new WeakMap();

  Drupal.behaviors.palefireToc = {
    attach(context) {
      once('pf-toc', '[data-pf-toc]', context).forEach((toc) => {
        const links = Array.from(toc.querySelectorAll('a[href^="#"]'));
        const sections = new Map();

        links.forEach((link) => {
          const target = document.getElementById(decodeURIComponent(link.hash.slice(1)));
          if (target) {
            sections.set(target, link);
          }
        });

        // Collapse the mobile panel after navigating to a section.
        const panel = toc.querySelector('.pf-toc__panel');
        toc.addEventListener('click', (event) => {
          if (!event.target.closest('a[href^="#"]') || !panel) {
            return;
          }
          if (panel.classList.contains('show') && window.bootstrap) {
            window.bootstrap.Collapse.getOrCreateInstance(panel, { toggle: false }).hide();
          }
        });

        if (!sections.size || !('IntersectionObserver' in window)) {
          return;
        }

        const setCurrent = (active) => {
          links.forEach((link) => {
            if (link === active) {
              link.setAttribute('aria-current', 'location');
            } else {
              link.removeAttribute('aria-current');
            }
          });
        };

        // A section is "current" when it crosses a band 20-40% down the
        // viewport; the first such section in document order wins.
        const inBand = new Set();
        const observer = new IntersectionObserver((entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              inBand.add(entry.target);
            } else {
              inBand.delete(entry.target);
            }
          });
          const current = Array.from(sections.keys()).find((section) => inBand.has(section));
          if (current) {
            setCurrent(sections.get(current));
          }
        }, { rootMargin: '-20% 0px -60% 0px' });

        sections.forEach((link, section) => observer.observe(section));
        observers.set(toc, observer);
      });
    },
    // Swup swaps pages without a reload; stop observing the old sections.
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once.remove('pf-toc', '[data-pf-toc]', context).forEach((toc) => {
        const observer = observers.get(toc);
        if (observer) {
          observer.disconnect();
          observers.delete(toc);
        }
      });
    },
  };
})(Drupal, once);
