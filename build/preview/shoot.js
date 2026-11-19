// Headless Edge over CDP (its own profile, port 9333): the proxied Oral
// History page (build/preview/proxy.js running) with sim.js applied, the
// Record tab open, scrolled to the band's foot, at each width. Prints
// measurements (scrollWidth vs clientWidth for sideways scroll, boxes,
// backdrop filter) and writes rec-<width>.png to outdir.
// node build/preview/shoot.js <absolute outdir> [widths, default 1440,1024,768,320]
const { spawn } = require('child_process'), fs = require('fs'), path = require('path');
const out = process.argv[2];
const widths = (process.argv[3] || '1440,1024,768,320').split(',').map(Number);
const edge = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const ud = path.resolve(out, 'ud-shoot-' + Date.now());
const port = 9333;
const proc = spawn(edge, ['--headless=new', '--disable-gpu', '--remote-debugging-port=' + port, '--user-data-dir=' + ud, '--disable-renderer-backgrounding', '--disable-background-timer-throttling', '--disable-backgrounding-occluded-windows', '--hide-scrollbars', '--no-first-run', '--no-default-browser-check', 'about:blank'], { stdio: 'ignore' });
proc.on('error', (e) => console.error('spawn', e));
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
(async () => {
  let ws;
  for (let i = 0; i < 80; i++) {
    try {
      const list = await (await fetch(`http://127.0.0.1:${port}/json`)).json();
      const pg = list.find((t) => t.type === 'page');
      if (pg) { ws = new WebSocket(pg.webSocketDebuggerUrl); break; }
    } catch (e) { if (i === 79) console.error(String(e)); }
    await sleep(400);
  }
  await new Promise((r) => ws.addEventListener('open', r));
  let id = 0; const wait = {};
  ws.addEventListener('message', (m) => { const d = JSON.parse(m.data); if (d.id && wait[d.id]) { wait[d.id](d); delete wait[d.id]; } });
  const send = (method, params = {}) => new Promise((r) => { const i = ++id; wait[i] = r; ws.send(JSON.stringify({ id: i, method, params })); });
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
  ws.close(); proc.kill();
})().catch((e) => { console.error(e); proc.kill(); process.exit(1); });
