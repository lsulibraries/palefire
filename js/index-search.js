/**
 * @file
 * The Index (/v2/search): loads each outside source's section after the page,
 * so a slow or failing service can't hold the page up.
 *
 * Each section starts with a link to the source's own list, which the server
 * renders directly. That's what stays if JavaScript is off or the request
 * fails. A section that comes back with no results is removed.
 */
(function (Drupal, once) {
  'use strict';

  const TIMEOUT_MS = 20000;
  const controllers = new Set();

  const load = async (holder) => {
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), TIMEOUT_MS);
    controllers.add(controller);
    try {
      const response = await fetch(holder.dataset.pfIndexLate, { signal: controller.signal });
      if (!response.ok) {
        throw new Error(String(response.status));
      }
      const template = document.createElement('template');
      template.innerHTML = await response.text();
      const list = template.content.querySelector('.pf-index-list');
      if (list && list.dataset.pfIndexTotal === '0') {
        holder.closest('.pf-index-section').hidden = true;
        return;
      }
      holder.replaceChildren(template.content);
      holder.classList.add('is-loaded');
    }
    catch (error) {
      // The server's link is still there, so the visitor has a way on.
    }
    finally {
      window.clearTimeout(timer);
      controllers.delete(controller);
      holder.setAttribute('aria-busy', 'false');
    }
  };

  Drupal.behaviors.palefireIndexSearch = {
    attach(context) {
      once('pf-index-late', '[data-pf-index-late]', context).forEach(load);
    },
    detach(context, settings, trigger) {
      if (trigger === 'unload') {
        controllers.forEach((controller) => controller.abort());
      }
    },
  };
}(Drupal, once));
