/**
 * @file
 * Palefire global behaviors.
 */
((Drupal, once) => {
  const root = document.documentElement;
  const COMPACT = 'pf-header--compact';
  // Matches $pf-header-transition (.2s) with headroom for slow frames.
  const RESIZE_MS = 400;
  // Set by palefireHeaderOffset; re-measures the header.
  let measureHeader = () => {};

  /**
   * Publishes the sticky header's height as --pf-header-h. Used for
   * scroll-padding (anchor/TOC jumps and scroll-snap land below the header,
   * and focused elements are not obscured: WCAG 2.2 SC 2.4.11), and as the
   * sticky top of the modular TOC sidebar, which has to track the header
   * frame by frame while it shrinks or grows.
   */
  Drupal.behaviors.palefireHeaderOffset = {
    attach(context) {
      once('pf-header-offset', '.pf-header', context).forEach((header) => {
        const bar = header.querySelector('.navbar') || header;
        const update = () => {
          // Measure the collapsed bar only, so opening the mobile menu does
          // not shift scroll offsets.
          const menu = header.querySelector('.navbar-collapse.show');
          const menuHeight = menu && window.getComputedStyle(menu).position !== 'absolute' ? menu.getBoundingClientRect().height : 0;
          // Unrounded, so the sidebar's top edge lands exactly on the bar's
          // bottom edge mid-transition, not up to a pixel off.
          const height = Math.max(bar.getBoundingClientRect().height - menuHeight, 0);
          root.style.setProperty('--pf-header-h', `${height}px`);
          // Expanded height, remembered while the header is compact or
          // changing size, so its bottom margin can hold the page in place.
          if (!header.classList.contains(COMPACT) && !header.hasAttribute('data-pf-resizing')) {
            root.style.setProperty('--pf-header-full', `${height}px`);
          }
        };
        measureHeader = update;
        update();
        if ('ResizeObserver' in window) {
          // border-box: the compact transition animates the bar's padding,
          // which the default content-box observation never reports. The
          // sidebar used to lag by that padding until the final re-measure.
          new ResizeObserver(update).observe(bar, { box: 'border-box' });
        } else {
          window.addEventListener('resize', update, { passive: true });
        }
      });
    },
  };

  /**
   * Shrinks the header once the page is scrolled past .pf-header-sentinel
   * (an IntersectionObserver, so there is no scroll listener).
   */
  Drupal.behaviors.palefireHeaderCompact = {
    attach(context) {
      once('pf-header-compact', '.pf-header', context).forEach((header) => {
        const sentinel = document.querySelector('.pf-header-sentinel');
        if (!sentinel || !('IntersectionObserver' in window)) {
          return;
        }
        let timer;
        let frame;
        // Re-measure on every frame of the shrink/grow transition, so
        // --pf-header-h (the sidebar's sticky top and the header's bottom
        // margin) moves in lockstep with the bar. rAF callbacks run after
        // this frame's transition progress is applied and before layout and
        // paint, so the header and sidebar are drawn from the same value.
        // This also covers browsers without border-box ResizeObserver.
        const follow = (until) => {
          window.cancelAnimationFrame(frame);
          const tick = (now) => {
            measureHeader();
            if (now < until) {
              frame = window.requestAnimationFrame(tick);
            }
          };
          frame = window.requestAnimationFrame(tick);
        };
        new IntersectionObserver(([entry]) => {
          const compact = !entry.isIntersecting;
          if (compact === header.classList.contains(COMPACT)) {
            return;
          }
          // Hold the remembered expanded height until the CSS transition
          // ($pf-header-transition) has finished.
          header.setAttribute('data-pf-resizing', '');
          header.classList.toggle(COMPACT, compact);
          follow(window.performance.now() + RESIZE_MS);
          window.clearTimeout(timer);
          timer = window.setTimeout(() => {
            header.removeAttribute('data-pf-resizing');
            measureHeader();
          }, RESIZE_MS);
        }).observe(sentinel);
      });
    },
  };

  /**
   * Full-page backgrounds: the first section's background image or video
   * reaches up under the top bar (--pf-bleed-block-start in _modular.scss).
   * Measures the distance from the top of the page to that section, so the
   * background also covers a breadcrumb, page title or tabs printed above
   * it. Both points scroll with the page, and the compact header keeps the
   * page from moving, so the value only changes when the layout does.
   * .pf-body is replaced on every Swup visit, so this re-runs per page.
   */
  Drupal.behaviors.palefireBleedOffset = {
    attach(context) {
      once('pf-bleed-offset', '.pf-body--modular', context).forEach((body) => {
        const section = body.querySelector('.pf-sections > .pf-section:first-child');
        const pageTop = document.querySelector('.pf-header-sentinel');
        if (!section || !pageTop) {
          return;
        }
        const update = () => {
          const offset = section.getBoundingClientRect().top - pageTop.getBoundingClientRect().top;
          section.style.setProperty('--pf-bleed-block-start', `${Math.max(Math.round(offset), 0)}px`);
        };
        update();
        if ('ResizeObserver' in window) {
          // .pf-body resizes when the window does, and when anything above
          // the section (title, tabs, messages) changes height. The
          // breadcrumb sits outside it, so it is watched separately.
          const observer = new ResizeObserver(update);
          observer.observe(body);
          const crumb = body.parentElement.querySelector(':scope > .pf-breadcrumb');
          if (crumb) {
            observer.observe(crumb);
          }
        }
      });
    },
  };

  /**
   * Escape closes the open mobile menu and returns focus to its toggle.
   */
  Drupal.behaviors.palefireMenuEscape = {
    attach(context) {
      once('pf-menu-escape', '.pf-header .navbar-collapse', context).forEach((panel) => {
        panel.addEventListener('keydown', (event) => {
          if (event.key !== 'Escape' || !panel.classList.contains('show')) {
            return;
          }
          // Let Bootstrap close an open dropdown first.
          if (panel.querySelector('.dropdown-menu.show')) {
            return;
          }
          const toggle = document.querySelector(`[aria-controls="${panel.id}"]`);
          if (window.bootstrap) {
            window.bootstrap.Collapse.getOrCreateInstance(panel, { toggle: false }).hide();
          }
          if (toggle) {
            toggle.focus();
          }
        });
      });
    },
  };
})(Drupal, once);
