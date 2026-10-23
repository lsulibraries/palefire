/**
 * Draws the exhibition cover's wash as PNGs for the Focal Point preview:
 * images/focal-preview/wash-desktop.png (900 x 1200) and wash-phone.png
 * (900 x 1600). In the "Cover preview: desktop" and "Cover preview: phone"
 * image styles, an Image Effects "Watermark" lays them over the focal point
 * crop, so editors see where the title and text will cover the image.
 *
 * The same wash as the title page (.pf-exhibit-head::after,
 * _exhibition.scss): pf-eased-wash(), the default reading swatch's page
 * colour (#272429), solid up to $from and gone by $to along a cosine curve
 * in 12 straight steps.
 * - Desktop (lg): only the image's part of the head, which starts at 44%
 *   of its width, in its shape on a 1440 x 900 screen (638 x 851, here
 *   scaled to 900 x 1200). To the right, 46% to 84% of the head; and the
 *   foot's fade, to the top over clamp(4rem, 14vh, 8rem) (126px of 851).
 * - Phone: to the top, 46% to 98%.
 *
 * Run after changing the wash: node build/focal-preview-washes.js
 */
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const out = path.resolve(__dirname, '..', 'images', 'focal-preview');
const PAGE = [0x27, 0x24, 0x29];

// pf-eased-wash()'s stops: [position 0-1, alpha 0-1].
function stops(from, to, steps = 12) {
  const list = [];
  for (let i = 0; i <= steps; i++) {
    const t = i / steps;
    const alpha = Math.round((1 + Math.cos(Math.PI * t)) / 2 * 1000) / 1000;
    list.push([(from + (to - from) * t) / 100, alpha]);
  }
  return list;
}

// The alpha at p (0 at the gradient's start, 1 at its end).
function wash(list, p) {
  if (p <= list[0][0]) {
    return list[0][1];
  }
  for (let i = 1; i < list.length; i++) {
    if (p <= list[i][0]) {
      const [p0, a0] = list[i - 1];
      const [p1, a1] = list[i];
      return a0 + (a1 - a0) * (p - p0) / (p1 - p0);
    }
  }
  return list[list.length - 1][1];
}

// Two washes over each other: "a over b".
const over = (a, b) => a + b * (1 - a);

function png(width, height, alphaAt) {
  const row = width * 4 + 1;
  const raw = Buffer.alloc(row * height);
  for (let y = 0; y < height; y++) {
    raw[y * row] = 0;
    for (let x = 0; x < width; x++) {
      const i = y * row + 1 + x * 4;
      raw[i] = PAGE[0];
      raw[i + 1] = PAGE[1];
      raw[i + 2] = PAGE[2];
      raw[i + 3] = Math.round(alphaAt(x, y) * 255);
    }
  }
  const chunk = (type, data) => {
    const len = Buffer.alloc(4);
    len.writeUInt32BE(data.length);
    const body = Buffer.concat([Buffer.from(type), data]);
    const crc = Buffer.alloc(4);
    crc.writeUInt32BE(zlib.crc32(body) >>> 0);
    return Buffer.concat([len, body, crc]);
  };
  const header = Buffer.alloc(13);
  header.writeUInt32BE(width, 0);
  header.writeUInt32BE(height, 4);
  header.set([8, 6, 0, 0, 0], 8);
  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk('IHDR', header),
    chunk('IDAT', zlib.deflateSync(raw, { level: 9 })),
    chunk('IEND', Buffer.alloc(0)),
  ]);
}

fs.mkdirSync(out, { recursive: true });

// Where the image starts in the head (.pf-exhibit-cover__media's left).
const mediaLeft = 0.44;
const side = stops(46, 84);
const foot = stops(0, 100);
fs.writeFileSync(path.join(out, 'wash-desktop.png'), png(900, 1200, (x, y) => {
  const fromFoot = (1200 - 1 - y) / (1200 * 126 / 851);
  return over(fromFoot <= 1 ? wash(foot, fromFoot) : 0, wash(side, mediaLeft + (1 - mediaLeft) * x / 899));
}));

const up = stops(46, 98);
fs.writeFileSync(path.join(out, 'wash-phone.png'), png(900, 1600, (x, y) => wash(up, (1600 - 1 - y) / 1599)));

console.log('Wrote', path.relative(process.cwd(), out));
