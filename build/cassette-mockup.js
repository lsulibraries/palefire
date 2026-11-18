/**
 * Builds a standalone review page for the What Endures cassette mark
 * (templates/includes/cassette-mark.html.twig), for editing it later away
 * from the site: the drawing as the template has it, styled by the compiled
 * css/podcast.css, its tape wound by the same pack() and tapeGeometry() as
 * js/podcast.js (read from that file, so the page always matches it). A
 * slider winds the tape, a button plays it, and plates show it on a dark
 * and a light page, tilted as on Oral History, over a striped ground so the
 * see-through holes and smoked plastic read.
 *
 * Usage: node build/cassette-mockup.js [out.html]
 */
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const out = process.argv[2] || path.join(root, 'cassette-mockup.html');

const svg = read('templates/includes/cassette-mark.html.twig').replace(/\{#[\s\S]*?#\}\s*/, '').trim()
  .replace('class="pf-cassette"', 'class="pf-cassette is-in"');
const css = read('css/podcast.css');
const js = read('js/podcast.js');

// The geometry, verbatim from js/podcast.js.
const grab = (start, end) => {
  const a = js.indexOf(start);
  const b = js.indexOf(end, a);
  if (a < 0 || b < 0) {
    throw new Error('Not found in js/podcast.js: ' + start);
  }
  return js.slice(a, b + end.length);
};
const pack = grab('const pack = (share) => {', '\n  };');
const tape = grab('function tapeGeometry(scale, right) {', '\n  }\n');

const page = `<title>What Endures Cassette</title>
<meta name="claude:viewport-fit-sides" content="cover">
<style>
/* Layout: a drawing review sheet. Controls, two large plates (dark, light), notes. */
:root {
  --bg: #f3f2f1; --fg: #1f1b19; --muted: #5f5853; --hair: rgba(31,27,25,.2); --accent: #8a6a12;
  --sans: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
}
@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) { --bg: #141210; --fg: #ece8e4; --muted: #a59e98; --hair: rgba(236,232,228,.2); --accent: #e3c76a; color-scheme: dark } }
:root[data-theme="dark"] { --bg: #141210; --fg: #ece8e4; --muted: #a59e98; --hair: rgba(236,232,228,.2); --accent: #e3c76a; color-scheme: dark }
body { background: var(--bg); color: var(--fg); font: 15px/1.55 var(--sans); }
.wrap { max-width: 1200px; margin: 0 auto; padding-block: 40px 64px; padding-left: max(16px, env(safe-area-inset-left, 0px)); padding-right: max(16px, env(safe-area-inset-right, 0px)); display: grid; gap: 32px; }
header { display: grid; gap: 10px; max-width: 68ch; }
.eyebrow { font-size: 12px; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); }
h1 { font: 600 clamp(26px, 4vw, 36px)/1.15 var(--sans); margin: 0; text-wrap: balance; }
p { margin: 0; color: var(--muted); }
.bar { display: flex; flex-wrap: wrap; gap: 16px 24px; align-items: center; }
.bar label { display: flex; gap: 12px; align-items: center; font-size: 14px; }
.bar output { min-width: 4ch; font-variant-numeric: tabular-nums; }
input[type="range"] { width: min(260px, 60vw); accent-color: var(--accent); }
button { font: 500 14px/1 var(--sans); color: var(--fg); background: transparent; border: 1px solid var(--hair); border-radius: 999px; padding: 10px 16px; cursor: pointer; }
button:hover { border-color: var(--fg); }
button:focus-visible, input:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
button[aria-pressed="true"] { background: var(--fg); color: var(--bg); border-color: var(--fg); }
.pair { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 480px), 1fr)); gap: 16px; }
figure { margin: 0; border-top: 1px solid var(--hair); padding-top: 14px; display: grid; gap: 12px; min-width: 0; }
figcaption { font-size: 14px; color: var(--muted); }
/* A stand-in for the section's photo: bold stripes and soft light, so the
   frosted plastic, the clear window and the holes read as they do there. */
/* The ground is its own layer under the mark, as the photo is on the site
   (.pf-podcast-backdrop): the clipped blur only reads a backdrop there. */
/* No overflow clip or rounded corners here: either stops Chrome blurring
   through the clipped backdrop. */
.stage { position: relative; padding: clamp(24px, 6vw, 56px) clamp(16px, 5vw, 48px); }
.stage::before { content: ""; position: absolute; inset: 0; z-index: 0; background-image: repeating-linear-gradient(100deg, var(--stripe) 0 4px, transparent 4px 16px), radial-gradient(60% 70% at 70% 35%, var(--glow), transparent 70%), radial-gradient(50% 60% at 20% 80%, var(--glow2), transparent 70%); }
.stage > .pf-podcast-head__tape { z-index: 1; }
.stage.dark { --bs-body-bg: #1f1b26; --bs-body-color: #ece8e4; --stripe: rgba(236,232,228,.28); --glow: rgba(196,150,90,.55); --glow2: rgba(90,120,170,.45); background-color: #1f1b26; color: #ece8e4; }
.stage.light { --bs-body-bg: #ffffff; --bs-body-color: #212529; --stripe: rgba(33,37,41,.22); --glow: rgba(196,150,90,.45); --glow2: rgba(90,120,170,.35); background-color: #ffffff; color: #212529; }
.stage .pf-podcast-head__tape { width: 100%; max-width: 520px; margin: 0 auto; transform: rotate(-8deg); --bs-body-font-family: var(--sans); }
.notes { display: grid; gap: 8px; max-width: 72ch; }
.notes code { font-size: 13px; }
${css}
</style>
<div class="wrap">
  <header>
    <span class="eyebrow">Palefire · drawing reference</span>
    <h1>What Endures cassette</h1>
    <p>The podcast mark from Oral History as the theme draws it, saved to edit later: one tape running reel to reel past the felt pad, spools and packs in a real cassette's proportions behind smoked plastic, and four holes through the foot.</p>
    <div class="bar">
      <label for="wound">Tape on the right reel <input type="range" id="wound" min="0" max="100" value="35"><output id="wound-out" for="wound">35%</output></label>
      <button type="button" id="play" aria-pressed="false">Play</button>
    </div>
  </header>
  <section class="pair" aria-label="The mark on a dark and a light page">
    <figure><div class="stage dark"><div class="pf-podcast-head__tape">${svg}</div></div><figcaption>Dark page, as on Oral History. The ground stands in for the section's photo: frosted through the plastic, sharp through the window and the holes.</figcaption></figure>
    <figure><div class="stage light"><div class="pf-podcast-head__tape">${svg}</div></div><figcaption>Light page, for reference: the plastic follows the page; the tape packs stay dark brown.</figcaption></figure>
  </section>
  <section class="notes" aria-label="Where it lives">
    <p>Drawing: <code>templates/includes/cassette-mark.html.twig</code>. Styles and motion: <code>scss/podcast.scss</code>. Pack sizes, tape runs and the reel turn: <code>js/podcast.js</code> (<code>pack()</code>, <code>tapeGeometry()</code>, <code>syncCassettes()</code>). Rebuild this page with <code>node build/cassette-mockup.js</code>.</p>
  </section>
</div>
<script>
(() => {
  ${pack}
  ${tape}
  const marks = document.querySelectorAll('.pf-cassette');
  const range = document.getElementById('wound');
  const out = document.getElementById('wound-out');
  const play = document.getElementById('play');
  let turn = 0;
  let last = null;
  function draw() {
    const progress = Number(range.value) / 100;
    const left = pack(1 - progress);
    const right = pack(progress);
    if (last !== null) {
      turn += (left * left - last * last) * 720;
    }
    last = Number(left);
    out.textContent = range.value + '%';
    marks.forEach((mark) => {
      mark.style.setProperty('--pf-pack-left', left);
      mark.style.setProperty('--pf-pack-right', right);
      mark.style.setProperty('--pf-reel-turn', turn.toFixed(1) + 'deg');
      ['left', 'right'].forEach((side) => {
        const g = tapeGeometry(Number(side === 'left' ? left : right), side === 'right');
        mark.querySelectorAll('.pf-cassette__tape--' + side + ', .pf-cassette__ribbon--' + side).forEach((p) => {
          const d = p.classList.contains('pf-cassette__ribbon') ? g.ribbon : g.edge;
          p.setAttribute('d', d);
          p.style.setProperty('d', 'path("' + d + '")');
        });
      });
    });
  }
  range.addEventListener('input', draw);
  let timer = null;
  play.addEventListener('click', () => {
    const on = play.getAttribute('aria-pressed') !== 'true';
    play.setAttribute('aria-pressed', String(on));
    play.textContent = on ? 'Pause' : 'Play';
    marks.forEach((mark) => mark.classList.toggle('is-playing', on));
    clearInterval(timer);
    if (on) {
      timer = setInterval(() => {
        range.value = Number(range.value) >= 100 ? 0 : Number(range.value) + 1;
        if (Number(range.value) === 0) {
          last = null;
        }
        draw();
      }, 300);
    }
  });
  draw();
})();
</script>
`;
fs.writeFileSync(out, page);
console.log('Wrote ' + out);
