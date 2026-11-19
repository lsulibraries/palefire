/**
 * @file
 * The Record tab's recorder, drawn when it's about to be seen.
 *
 * The hairline recorder (images/recorder-line.svg, from
 * build/recorder-mark.js --line) is about 190 kB and a thousand elements:
 * too much to carry in every load of a page where most visitors never open
 * the Record tab. Its box (.pf-quick-guide__specimen, includes/landing.html.twig)
 * names the file in data-pf-recorder-src; once the box comes within 400px of
 * the window (its panel shown, by tab, link or Find in page), the file is
 * fetched and the drawing inserted inline, so the page's styles color and
 * move it (scss/recorder.scss) as when it was in the HTML. The box is
 * absolutely placed at its own size, so nothing moves when it arrives.
 *
 * Decorative (the box is aria-hidden). Without JS, or if the fetch fails,
 * the box stays empty.
 */
(function (Drupal, once) {
  'use strict';

  const draw = (box) => {
    fetch(box.dataset.pfRecorderSrc)
      .then((response) => (response.ok ? response.text() : ''))
      .then((text) => {
        const svg = new DOMParser().parseFromString(text, 'image/svg+xml').documentElement;
        if (svg && svg.namespaceURI === 'http://www.w3.org/2000/svg' && svg.localName === 'svg') {
          box.appendChild(document.importNode(svg, true));
        }
      })
      .catch(() => {});
  };

  Drupal.behaviors.palefireRecorderDefer = {
    attach(context) {
      const boxes = once('pf-recorder-defer', '[data-pf-recorder-src]', context);
      if (!boxes.length) {
        return;
      }
      if (!('IntersectionObserver' in window)) {
        boxes.forEach(draw);
        return;
      }
      const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            observer.unobserve(entry.target);
            draw(entry.target);
          }
        });
      }, { rootMargin: '400px 0px' });
      boxes.forEach((box) => observer.observe(box));
    },
  };
})(Drupal, once);
