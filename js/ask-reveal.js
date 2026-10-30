/**
 * @file
 * Ask Special Collections (includes/ask-special.html.twig): as the section
 * scrolls into view, each column's parts rise 16px and fade in one after
 * another, 60ms apart, as the Collections rows do (js/tile-reveal.js).
 * - FAQs: the column heading, each question, then the two buttons;
 * - the form: its heading, the intro, each field, Required, Send question.
 * Both columns count from their own first part, so on wide screens they
 * come in side by side. Once per page load: a part that has come in stays.
 *
 * Parts that enter together are staggered in source order. Off entirely
 * (everything simply shown) for prefers-reduced-motion, the Settings
 * drawer's "Reduce motion" (html.pf-a11y-motion) and without
 * IntersectionObserver; without JS nothing is hidden: the hidden state
 * needs .pf-inquiry--reveal, which only this script adds. Focus anywhere in
 * the section (a Tab, or the footer's Ask link, js/footer.js) shows it all
 * at once. Styles: _ask.scss.
 */
(function (Drupal, once) {
  'use strict';

  const INTERVAL = 60;
  const PARTS = [
    '.pf-inquiry__faqs > .pf-inquiry__heading',
    '.pf-inquiry__accordion > .pf-accordion__item',
    '.pf-inquiry__links > li',
    '.pf-inquiry__panel > .pf-inquiry__heading',
    '.pf-inquiry__intro',
    '.pf-inquiry__field',
    '.pf-inquiry__required',
    '.pf-inquiry__actions',
    '.pf-inquiry__panel > .pf-inquiry__link',
  ].join(', ');
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const reduceMotion = () => motion.matches || document.documentElement.classList.contains('pf-a11y-motion');

  Drupal.behaviors.palefireAskReveal = {
    attach(context) {
      if (!('IntersectionObserver' in window) || reduceMotion()) {
        return;
      }
      once('pf-ask-reveal', '.pf-inquiry', context).forEach((root) => {
        const parts = Array.from(root.querySelectorAll(PARTS));
        if (!parts.length) {
          return;
        }
        const column = (part) => part.closest('.pf-inquiry__faqs, .pf-inquiry__panel');
        const show = (part, delay) => {
          part.style.setProperty('--pf-reveal-delay', delay + 'ms');
          part.classList.add('is-revealed');
        };

        const observer = new IntersectionObserver((entries) => {
          const counts = new Map();
          entries
            .filter((entry) => entry.isIntersecting)
            .sort((a, b) => parts.indexOf(a.target) - parts.indexOf(b.target))
            .forEach((entry) => {
              const key = column(entry.target);
              const n = counts.get(key) || 0;
              counts.set(key, n + 1);
              show(entry.target, INTERVAL * n);
              observer.unobserve(entry.target);
            });
        }, { threshold: 0 });

        parts.forEach((part) => {
          part.classList.add('pf-inquiry__part');
          observer.observe(part);
        });
        root.addEventListener('focusin', () => {
          parts.forEach((part) => show(part, 0));
          observer.disconnect();
        }, { once: true });
        root.classList.add('pf-inquiry--reveal');
      });
    },
  };
})(Drupal, once);
