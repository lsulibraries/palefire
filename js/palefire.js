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
  // Compact bar height for a given header width (see compactBarHeight).
  let compactBar = { width: -1, height: 0 };
  let compactAfterFrame = 0;

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
          // Unrounded, so the sidebar's top edge lands exactly on the bar's
          // bottom edge mid-transition, not up to a pixel off. (The mobile
          // menu is a fixed drawer, so it never adds to the bar's height.)
          const height = bar.getBoundingClientRect().height;
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
   * (an IntersectionObserver, so there is no scroll listener). On pages with
   * the sticky TOC sidebar, palefireCompactAfter makes the sentinel longer.
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
   * Height of the header's bar when compact. Measured on a hidden copy, so
   * the real header never changes size or starts a transition. Cached per
   * header width, since the bar can wrap differently at other widths.
   */
  const compactBarHeight = (header) => {
    const width = header.getBoundingClientRect().width;
    if (width !== compactBar.width) {
      const copy = header.cloneNode(true);
      copy.classList.add(COMPACT);
      copy.classList.remove('sticky-top');
      copy.querySelectorAll('[id]').forEach((el) => el.removeAttribute('id'));
      copy.setAttribute('aria-hidden', 'true');
      copy.inert = true;
      copy.style.cssText = `position:absolute;top:0;left:0;width:${width}px;visibility:hidden;pointer-events:none`;
      header.after(copy);
      // A new element has no previous style to transition from, so this
      // is already the final compact height.
      const bar = copy.querySelector('.navbar') || copy;
      compactBar = { width, height: bar.getBoundingClientRect().height };
      copy.remove();
    }
    return compactBar.height;
  };

  // For scripts that scroll past the compact point and need to know where
  // the header will end (landing-snap.js), before it has shrunk.
  Drupal.palefireCompactHeaderHeight = () => {
    const header = document.querySelector('.pf-header');
    return header ? compactBarHeight(header) : 0;
  };

  const updateCompactAfter = () => {
    compactAfterFrame = 0;
    const header = document.querySelector('.pf-header');
    const sentinel = document.querySelector('.pf-header-sentinel');
    if (!header || !sentinel) {
      return;
    }
    const sidebar = document.querySelector('.pf-modular__sidebar');
    if (!sidebar || window.getComputedStyle(sidebar).position !== 'sticky') {
      sentinel.style.removeProperty('--pf-compact-after');
      return;
    }
    // The sidebar's place in the page is the top of its grid. Measured on
    // the grid because a stuck sidebar reports where it is drawn, not where
    // it sits in the page.
    const place = sidebar.parentElement.getBoundingClientRect().top - sentinel.getBoundingClientRect().top;
    const distance = Math.max(Math.ceil(place - compactBarHeight(header)), 0);
    sentinel.style.setProperty('--pf-compact-after', `${distance}px`);
  };

  const scheduleCompactAfter = () => {
    if (!compactAfterFrame) {
      compactAfterFrame = window.requestAnimationFrame(updateCompactAfter);
    }
  };

  /**
   * Keeps the header expanded until the modular TOC sidebar can follow it
   * up to the compact bar.
   *
   * A sticky element never moves above its own place in the page. The
   * sidebar's place is below the expanded header and the breadcrumb, so its
   * top edge only reaches the compact bar's bottom edge once the page has
   * scrolled that far. If the header shrank sooner, the sidebar stayed where
   * it was, leaving a gap under the bar until the page scrolled further.
   * This sets --pf-compact-after on .pf-header-sentinel to that scroll
   * distance. $pf-header-compact-after is still the minimum. Runs again on
   * every Swup visit, because the sidebar and breadcrumb are part of each
   * page.
   */
  Drupal.behaviors.palefireCompactAfter = {
    attach(context) {
      once('pf-compact-after', 'html').forEach(() => {
        window.addEventListener('resize', scheduleCompactAfter, { passive: true });
        if (document.fonts) {
          document.fonts.ready.then(() => {
            compactBar.width = -1;
            scheduleCompactAfter();
          });
        }
      });
      once('pf-compact-after', '.pf-body--modular', context).forEach((body) => {
        if ('ResizeObserver' in window) {
          // The breadcrumb, and anything else above the sidebar, moves its
          // place in the page.
          const observer = new ResizeObserver(scheduleCompactAfter);
          observer.observe(body);
          const crumb = body.parentElement.querySelector(':scope > .pf-breadcrumb');
          if (crumb) {
            observer.observe(crumb);
          }
        }
      });
      scheduleCompactAfter();
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
   * The mobile menu drawer (#pf-primary-menu, a Bootstrap offcanvas below
   * lg). Bootstrap handles Escape, the focus trap and returning focus to
   * the Menu button. This adds:
   * - <html data-pf-panel="menu"> while it is open, so the backdrop is the
   *   same wash of the page colour as the utility drawers'
   *   (_utility-panels.scss).
   * - The sections as an accordion: each section's heading button
   *   (.pf-primary-nav__heading, menu--main.html.twig) shows or hides its
   *   pages underneath it. Several can be open at once.
   * The header sits outside Swup's container, so this runs once.
   */
  Drupal.behaviors.palefireMenuDrawer = {
    attach(context) {
      once('pf-menu-drawer', '#pf-primary-menu', context).forEach((drawer) => {
        drawer.addEventListener('show.bs.offcanvas', () => {
          root.dataset.pfPanel = 'menu';
        });
        drawer.addEventListener('hidden.bs.offcanvas', () => {
          if (root.dataset.pfPanel === 'menu') {
            delete root.dataset.pfPanel;
          }
        });
        drawer.addEventListener('click', (event) => {
          const heading = event.target.closest('.pf-primary-nav__heading');
          if (heading && drawer.contains(heading)) {
            heading.setAttribute('aria-expanded', heading.getAttribute('aria-expanded') === 'true' ? 'false' : 'true');
          }
        });
      });
    },
  };

  /**
   * Guide accordions: numbers each panel's blocks (the text's paragraphs,
   * lists and tables, then each resource) in page order, so they fade in
   * one after another when the accordion opens (.pf-seq / --pf-seq,
   * _guide.scss). Capped at 5: anything later arrives with the sixth.
   */
  Drupal.behaviors.palefireAccordionSequence = {
    attach(context) {
      once('pf-accordion-seq', '.pf-accordion__panel', context).forEach((panel) => {
        const blocks = panel.querySelectorAll(':scope > .pf-guide-prose > *, :scope > .pf-resources > li, :scope > :not(.pf-guide-prose, .pf-resources)');
        blocks.forEach((block, i) => {
          block.classList.add('pf-seq');
          block.style.setProperty('--pf-seq', String(Math.min(i, 5)));
        });
      });
    },
  };

  /**
   * Opens a utility drawer over the open menu drawer, keeping the menu
   * underneath (Bootstrap would close it: one offcanvas at a time). While
   * it is up, <html class="pf-over-menu"> lifts the drawer and its backdrop
   * above the menu (_utility-panels.scss). Closing it (its × button, Escape
   * or a tap on the backdrop) goes back to the menu: focus returns to the
   * tile that opened it, and the menu's focus trap (Bootstrap keeps one
   * active at a time) is restored.
   */
  function openOverMenu(panel, tile) {
    const bs = window.bootstrap;
    const menu = document.getElementById('pf-primary-menu');

    const onHidden = (event) => {
      if (event.target !== panel) {
        return;
      }
      document.removeEventListener('hidden.bs.offcanvas', onHidden);
      root.classList.remove('pf-over-menu');
      const menuDrawer = menu && bs.Offcanvas.getInstance(menu);
      if (!menuDrawer || !menu.classList.contains('show')) {
        return;
      }
      // Back on the menu: its backdrop wash, its focus trap, the tile.
      root.dataset.pfPanel = 'menu';
      const trap = menuDrawer._focustrap;
      if (trap && trap.activate) {
        trap.deactivate();
        trap.activate();
      }
      tile.focus();
    };

    // Pause the menu's focus trap first: the drawer's own trap focuses
    // the drawer before it takes over, and the menu's would pull focus
    // back into the menu.
    const menuDrawer = menu && bs.Offcanvas.getInstance(menu);
    if (menuDrawer && menuDrawer._focustrap) {
      menuDrawer._focustrap.deactivate();
    }

    root.classList.add('pf-over-menu');
    // On document, so it runs after the drawer's own hidden handlers
    // (utility-panels.js clears data-pf-panel there).
    document.addEventListener('hidden.bs.offcanvas', onHidden);
    bs.Offcanvas.getOrCreateInstance(panel).show(tile);
    // Its backdrop was just added (Bootstrap appends it synchronously):
    // lift it over the menu, so the menu dims under the new layer.
    const backdrops = document.querySelectorAll('.offcanvas-backdrop');
    if (backdrops.length) {
      backdrops[backdrops.length - 1].classList.add('pf-backdrop--over-menu');
    }
  }

  /**
   * Mobile menu quick links. Below lg the header bar keeps only Search and
   * the Menu button; the other utility icons (Hours, Ask Us, My Accounts,
   * Settings) are copied into the menu drawer as labelled tiles. A tile
   * opens its drawer over the menu (openOverMenu), not in its place, so
   * people can go back to the menu. The bar icons are hidden only once the
   * copies exist (.pf-header--actions-in-menu), so without JavaScript
   * nothing goes missing (and a tile is still a link to its page). The
   * header sits outside Swup's container, so this runs once per page load.
   */
  Drupal.behaviors.palefireMenuActions = {
    attach(context) {
      once('pf-menu-actions', '[data-pf-menu-actions]', context).forEach((nav) => {
        const header = nav.closest('.pf-header');
        const list = nav.querySelector('.pf-menu-actions__list');
        if (!header || !list) {
          return;
        }
        header.querySelectorAll('.pf-utility-nav .pf-utility-nav__link').forEach((link) => {
          if (link.dataset.pfPanelTrigger === 'search') {
            return;
          }
          const copy = link.cloneNode(true);
          copy.className = 'pf-menu-actions__link';
          copy.removeAttribute('id');
          // Opened by openOverMenu, not Bootstrap's data API (which would
          // close the menu first).
          if (copy.hasAttribute('data-bs-toggle')) {
            copy.dataset.pfOverMenu = copy.getAttribute('data-bs-target') || '';
            copy.removeAttribute('data-bs-toggle');
            copy.removeAttribute('data-bs-target');
          }
          const label = copy.querySelector('.pf-utility-nav__label');
          if (label) {
            label.className = 'pf-menu-actions__label';
          }
          const item = document.createElement('li');
          item.className = 'pf-menu-actions__item';
          item.appendChild(copy);
          list.appendChild(item);
        });
        if (list.children.length) {
          nav.hidden = false;
          header.classList.add('pf-header--actions-in-menu');
        }
        list.addEventListener('click', (event) => {
          const tile = event.target.closest('[data-pf-over-menu]');
          const panel = tile && tile.dataset.pfOverMenu ? document.querySelector(tile.dataset.pfOverMenu) : null;
          if (!panel || !window.bootstrap) {
            return;
          }
          event.preventDefault();
          openOverMenu(panel, tile);
        });
      });
    },
  };
})(Drupal, once);
