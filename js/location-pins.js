/**
 * @file
 * Location pages: the policy marks' tooltips (paragraph--location-space).
 *
 * CSS shows a tooltip on hover and keyboard focus. This adds a tap, which
 * opens a mark's tooltip and closes the others (phones have no hover), and
 * Escape, which hides whatever tooltip is showing until the pointer or focus
 * leaves that mark, so it can be dismissed without moving (WCAG 1.4.13).
 */
(function (Drupal, once) {
  Drupal.behaviors.palefireLocationPins = {
    attach(context) {
      once('pf-location-pins', '[data-pf-pins]', context).forEach((list) => {
        const pins = () => list.querySelectorAll('.pf-space-pin');
        const close = () => pins().forEach((pin) => pin.classList.remove('is-open'));

        list.addEventListener('click', (event) => {
          const button = event.target.closest('.pf-space-pin__button');
          if (!button) {
            return;
          }
          const pin = button.closest('.pf-space-pin');
          const open = !pin.classList.contains('is-open');
          close();
          pin.classList.remove('is-dismissed');
          pin.classList.toggle('is-open', open);
        });

        // Dismissed until the pointer or focus moves on.
        pins().forEach((pin) => {
          const reset = () => pin.classList.remove('is-dismissed');
          pin.addEventListener('pointerleave', reset);
          pin.addEventListener('focusout', reset);
        });

        document.addEventListener('keydown', (event) => {
          if (event.key !== 'Escape' || !list.isConnected) {
            return;
          }
          pins().forEach((pin) => {
            if (pin.matches(':hover, :focus-within, .is-open')) {
              pin.classList.add('is-dismissed');
            }
            pin.classList.remove('is-open');
          });
        });

        document.addEventListener('pointerdown', (event) => {
          if (!list.contains(event.target)) {
            close();
          }
        });
      });
    },
  };
})(Drupal, once);
