/**
 * @file
 * Style guide plates (templates/includes/styleguide-plate.html.twig):
 * prints what the browser actually draws, so the plates can't drift from
 * the Sass.
 *
 * - Color: each swatch's hex code and its contrast with the page color
 *   (brand colors: with the ground named in data-sg-on, from --sg-on),
 *   read from the computed color (a canvas pixel, so color-mix() and
 *   translucent tokens resolve too; translucent ones are measured over the
 *   page). Redrawn when a visitor picks another reading color or turns on
 *   High contrast.
 * - Motion: each card's curve and timing from its --sg-ease and --sg-dur
 *   (scss/styleguide.scss), drawn as the card's SVG. Pressing a card sends
 *   its dot along the curve and back; the CSS keeps it still under reduced
 *   motion.
 *
 * Without the script the plates show their names and tokens.
 */
(function (Drupal, once) {
  'use strict';

  const EASES = {
    ease: [0.25, 0.1, 0.25, 1],
    linear: [0, 0, 1, 1],
    'ease-in': [0.42, 0, 1, 1],
    'ease-out': [0, 0, 0.58, 1],
    'ease-in-out': [0.42, 0, 0.58, 1],
  };

  const canvas = document.createElement('canvas');
  canvas.width = 1;
  canvas.height = 1;
  const ctx = canvas.getContext('2d', { willReadFrequently: true });

  // [r, g, b, a] of a CSS color, painted over `under` when given.
  const pixel = (color, under) => {
    ctx.clearRect(0, 0, 1, 1);
    if (under) {
      ctx.fillStyle = under;
      ctx.fillRect(0, 0, 1, 1);
    }
    ctx.fillStyle = color;
    ctx.fillRect(0, 0, 1, 1);
    return Array.from(ctx.getImageData(0, 0, 1, 1).data);
  };

  const hex = (rgb) => '#' + rgb.slice(0, 3).map((v) => v.toString(16).padStart(2, '0')).join('').toUpperCase();

  const luminance = (rgb) => {
    const [r, g, b] = rgb.slice(0, 3).map((v) => {
      const c = v / 255;
      return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
  };

  const ratio = (a, b) => {
    const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
    return (hi + 0.05) / (lo + 0.05);
  };

  const grade = (r) => {
    if (r >= 7) {
      return Drupal.t('AAA');
    }
    if (r >= 4.5) {
      return Drupal.t('AA');
    }
    if (r >= 3) {
      return Drupal.t('Large text and UI only');
    }
    return Drupal.t('Not for text');
  };

  const paintColors = (plate) => {
    const page = getComputedStyle(plate).getPropertyValue('--bs-body-bg').trim() || '#fff';
    const pageRgb = pixel(page);
    plate.querySelectorAll('[data-sg-swatch]').forEach((swatch) => {
      const chip = swatch.querySelector('.pf-sg-swatch__chip');
      const color = getComputedStyle(chip).backgroundColor;
      const own = pixel(color);
      const seen = own[3] < 255 ? pixel(color, page) : own;
      const hexOut = swatch.querySelector('[data-sg-hex]');
      const ratioOut = swatch.querySelector('[data-sg-ratio]');
      hexOut.textContent = own[3] < 255
        ? Drupal.t('@pct% · @hex on this page', { '@pct': Math.round(own[3] / 2.55), '@hex': hex(seen) })
        : hex(own);
      if (swatch.hasAttribute('data-sg-page')) {
        ratioOut.textContent = '';
      }
      else if (swatch.hasAttribute('data-sg-decorative')) {
        ratioOut.textContent = Drupal.t('Decorative');
      }
      else if (swatch.hasAttribute('data-sg-ground')) {
        ratioOut.textContent = Drupal.t('Ground for text');
      }
      else {
        // Brand colors are measured on the ground they are used on
        // (--sg-on), the reading colors on this page.
        const on = swatch.getAttribute('data-sg-on');
        const ground = on ? pixel(getComputedStyle(swatch).getPropertyValue('--sg-on').trim()) : pageRgb;
        const r = ratio(seen, ground);
        ratioOut.textContent = (Math.floor(r * 10) / 10).toFixed(1) + ':1 ' + grade(r)
          + (on ? ' ' + Drupal.t('on @ground', { '@ground': on }) : '');
      }
    });
  };

  const paintMotion = (card) => {
    const style = getComputedStyle(card);
    const ease = style.getPropertyValue('--sg-ease').trim();
    const dur = parseFloat(style.getPropertyValue('--sg-dur'));
    const match = ease.match(/cubic-bezier\(([^)]+)\)/);
    const points = match ? match[1].split(',').map(Number) : EASES[ease];
    if (!points || points.some(Number.isNaN)) {
      return;
    }
    const [x1, y1, x2, y2] = points;
    const y = (v) => +(50 - v * 50).toFixed(2);
    card.querySelector('[data-sg-curve]').setAttribute('d', `M0 50C${x1 * 100} ${y(y1)} ${x2 * 100} ${y(y2)} 100 0`);
    card.querySelector('[data-sg-timing]').textContent = `${ease} · ${dur}s`;
  };

  Drupal.behaviors.palefireStyleguide = {
    attach(context) {
      const plates = once('pf-styleguide', '[data-pf-styleguide]', context);
      if (!plates.length) {
        return;
      }
      const colorPlates = plates.filter((plate) => plate.querySelector('[data-sg-swatch]'));
      colorPlates.forEach(paintColors);
      if (colorPlates.length) {
        // A new reading color or High contrast: the tokens change on <html>.
        new MutationObserver(() => colorPlates.forEach(paintColors))
          .observe(document.documentElement, { attributes: true, attributeFilter: ['data-pf-theme', 'data-bs-theme', 'class'] });
      }
      plates.forEach((plate) => {
        plate.querySelectorAll('[data-sg-motion]').forEach((card) => {
          paintMotion(card);
          card.addEventListener('click', () => {
            const on = card.getAttribute('aria-pressed') !== 'true';
            card.setAttribute('aria-pressed', String(on));
            card.classList.toggle('is-playing', on);
          });
        });
      });
    },
  };
})(Drupal, once);
