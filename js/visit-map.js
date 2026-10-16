/**
 * @file
 * Visit card mini map: a button stands in for the Google Maps embed until
 * someone asks for it, so the page makes no request to Google (and sets no
 * Google cookies) by default. Clicking swaps in the iframe and moves focus
 * to it; the "Open in Google Maps" link works with or without this script.
 *
 * Markup:
 *   <div class="pf-visit__map" data-pf-visit-map data-src="https://www.google.com/maps?q=...&output=embed"
 *        data-title="Map: Hill Memorial Library">
 *     <button type="button" data-pf-visit-map-load>Show map</button>
 *   </div>
 */
((Drupal, once) => {
  Drupal.behaviors.palefireVisitMap = {
    attach(context) {
      once('pf-visit-map', '[data-pf-visit-map][data-src]', context).forEach((root) => {
        const button = root.querySelector('[data-pf-visit-map-load]');
        if (!button) {
          return;
        }
        button.addEventListener('click', () => {
          const src = root.dataset.src;
          if (!/^https:\/\/www\.google\.com\/maps/.test(src)) {
            return;
          }
          const frame = document.createElement('iframe');
          frame.className = 'pf-visit__map-frame';
          frame.src = src;
          frame.title = root.dataset.title || Drupal.t('Map');
          frame.loading = 'lazy';
          frame.referrerPolicy = 'no-referrer-when-downgrade';
          frame.setAttribute('allowfullscreen', '');
          frame.tabIndex = 0;
          root.classList.add('is-loaded');
          button.replaceWith(frame);
          frame.focus({ preventScroll: true });
        });
      });
    },
  };
})(Drupal, once);
