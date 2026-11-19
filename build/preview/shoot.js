// Headless Edge over CDP (its own profile, port 9333): the proxied Oral
// History page (build/preview/proxy.js running) with sim.js applied, the
// Record tab open, scrolled to the band's foot, at each width. Prints
// measurements (scrollWidth vs clientWidth for sideways scroll, boxes,
// backdrop filter) and writes rec-<width>.png to outdir.
// node build/preview/shoot.js <absolute outdir> [widths, default 1440,1024,768,320]
// Edge is started through edge.js, which closes it however this script ends.
const fs = require('fs'), path = require('path');
const { launch } = require('./edge');
const out = process.argv[2];
const widths = (process.argv[3] || '1440,1024,768,320').split(',').map(Number);
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
let edge;
(async () => {
  edge = await launch({ port: 9333, maxMs: 10 * 60 * 1000, args: ['--disable-renderer-backgrounding', '--disable-background-timer-throttling', '--disable-backgrounding-occluded-windows', '--hide-scrollbars'] });
  const { ws, send } = await edge.page();
  const ev = async (expr) => (await send('Runtime.evaluate', { expression: expr, awaitPromise: true, returnByValue: true })).result?.result?.value;
  await send('Page.enable'); await send('Page.bringToFront');
  for (const w of widths) {
    await send('Emulation.setDeviceMetricsOverride', { width: w, height: 900, deviceScaleFactor: 1, mobile: w < 768 });
    await send('Page.navigate', { url: 'http://localhost:8766/v2/oralhistory' });
    await sleep(6000);
    await ev(`(async()=>{const t=await (await fetch('/__local/sim.js')).text();(0,eval)(t);await new Promise(r=>setTimeout(r,2500));document.querySelector('#record-tab, [aria-controls="record"]')?.click();await new Promise(r=>setTimeout(r,800));})()`);
    const info = await ev(`(async()=>{const g=document.querySelector('.pf-quick-guide');const b=g.getBoundingClientRect();window.scrollTo({top:b.bottom+scrollY-900,behavior:'instant'});await new Promise(r=>setTimeout(r,900));const s=document.querySelector('.pf-quick-guide__specimen');const p=document.querySelector('#record .pf-paths');const v=document.querySelector('#record .pf-quick-guide__visit');const R=e=>e?(r=>[Math.round(r.left),Math.round(r.top),Math.round(r.width),Math.round(r.height)])(e.getBoundingClientRect()):null;return JSON.stringify({sw:document.documentElement.scrollWidth,cw:document.documentElement.clientWidth,guide:R(g),spec:R(s),paths:R(p),visit:R(v),specDisp:s&&getComputedStyle(s).display,bf:(h=>h&&getComputedStyle(h).backdropFilter)(document.querySelector('#record .pf-quick-guide__link')),op:s&&s.offsetParent&&(s.offsetParent.className.slice(0,40)),gpad:getComputedStyle(g).paddingBottom,gbord:getComputedStyle(g).borderBottomWidth,before:getComputedStyle(g,'::before').inset,sec:(r=>[r.top,r.bottom])(g.closest('.pf-section').getBoundingClientRect()),secBg:getComputedStyle(g.closest('.pf-section')).backgroundColor,band:getComputedStyle(g,'::before').backgroundColor,stops:[...document.querySelectorAll('#record .pf-paths__stop')].map(e=>Math.round(e.getBoundingClientRect().bottom))})})()`);
    console.log(w, info);
    const shot = await send('Page.captureScreenshot', { format: 'png' });
    fs.writeFileSync(path.join(out, `rec-${w}.png`), Buffer.from(shot.result.data, 'base64'));
  }
  ws.close(); edge.close();
})().catch((e) => { console.error(e); process.exit(1); });
