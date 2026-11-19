/**
 * @file
 * Web copies of photos for an import script's images=<folder>, without
 * libvips: headless Edge (edge.js) draws each photo on a canvas at most
 * <px> on the long side (never enlarged), turned upright by its EXIF
 * orientation, and saves it as a JPEG at quality 0.85 with no metadata.
 *
 * Usage:
 *   node build/preview/resize-portraits.js <list.json> <source folder> <out folder> [px]
 *
 * <list.json> maps each output name to its source file name, as
 * scripts/palefire_exhibit_mossville_interviews_build.js writes it.
 * Existing outputs are skipped, so a run can be resumed.
 */

'use strict';

const fs = require('fs');
const path = require('path');
const { launch } = require('./edge');

const [list, src, out, px = '1600'] = process.argv.slice(2);
if (!list || !src || !out) {
  console.error('Usage: node build/preview/resize-portraits.js <list.json> <source folder> <out folder> [px]');
  process.exit(1);
}

const DRAW = `async (b64, max) => {
  const blob = await (await fetch('data:image/jpeg;base64,' + b64)).blob();
  const bitmap = await createImageBitmap(blob, { imageOrientation: 'from-image' });
  const scale = Math.min(1, max / Math.max(bitmap.width, bitmap.height));
  const canvas = new OffscreenCanvas(Math.round(bitmap.width * scale), Math.round(bitmap.height * scale));
  const ctx = canvas.getContext('2d');
  ctx.imageSmoothingQuality = 'high';
  ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
  const jpeg = await canvas.convertToBlob({ type: 'image/jpeg', quality: 0.85 });
  const bytes = new Uint8Array(await jpeg.arrayBuffer());
  let s = '';
  for (let i = 0; i < bytes.length; i += 0x8000) {
    s += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
  }
  return canvas.width + 'x' + canvas.height + ' ' + btoa(s);
}`;

(async () => {
  const names = JSON.parse(fs.readFileSync(list, 'utf8'));
  fs.mkdirSync(out, { recursive: true });
  const edge = await launch({ port: 9341, maxMs: 15 * 60 * 1000 });
  try {
    const page = await edge.page();
    await page.send('Page.navigate', { url: 'about:blank' });
    for (const [name, source] of Object.entries(names)) {
      const dest = path.join(out, name);
      if (fs.existsSync(dest)) {
        continue;
      }
      const b64 = fs.readFileSync(path.join(src, source)).toString('base64');
      const res = await page.send('Runtime.evaluate', {
        expression: `(${DRAW})(${JSON.stringify(b64)}, ${Number(px)})`,
        awaitPromise: true,
        returnByValue: true,
      });
      const value = res.result && res.result.result && res.result.result.value;
      if (!value) {
        console.error('failed:', source, JSON.stringify(res.result && res.result.exceptionDetails || res).slice(0, 300));
        continue;
      }
      const [size, data] = value.split(' ');
      fs.writeFileSync(dest, Buffer.from(data, 'base64'));
      console.log(name.padEnd(52), size, Math.round(fs.statSync(dest).size / 1024) + ' KB');
    }
  }
  finally {
    edge.close();
  }
})();
