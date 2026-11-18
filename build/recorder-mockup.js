/**
 * Builds the recorder review sheet: the shaded and hairline drawings
 * (templates/includes/recorder-mark.html.twig and recorder-line.html.twig)
 * on light and dark plates, animated, with close-ups of the fins, lens and
 * dial, as one self-contained HTML page. It was published as a private
 * claude.ai artifact (https://claude.ai/artifact/STRR59jsjikucqT7mtCyGC); to
 * update it, regenerate both drawings (node build/recorder-mark.js, then
 * with --line), run this and republish the page to that same link.
 *
 * Usage: node build/recorder-mockup.js [out.html]
 * (default: recorder-mockup.html in the system temp folder; not for the
 * site, so it never lands in the theme).
 */
const fs = require('fs'), path = require('path'), os = require('os');
const repo = path.join(__dirname, '..');
const outFile = process.argv[2] || path.join(os.tmpdir(), 'recorder-mockup.html');
const tw = fs.readFileSync(repo + '/templates/includes/recorder-mark.html.twig', 'utf8')
  .replace(/\{#[\s\S]*?#\}\n/, '').replace(/\{\{ attach_library[^\n]*\n/, '').replace(/\{% set id[^\n]*\n/, '').trim();
const twl = fs.readFileSync(repo + '/templates/includes/recorder-line.html.twig', 'utf8')
  .replace(/\{#[\s\S]*?#\}\n/, '').replace(/\{\{ attach_library[^\n]*\n/, '').replace(/\{% set id[^\n]*\n/, '').trim();
const css = fs.readFileSync(repo + '/css/recorder.css', 'utf8');
const svg = (id, vb, src = tw) => {
  let s = src.split('{{ id }}').join(id).replace(/ width="\d+" height="\d+"/, '');
  if (vb) s = s.replace(/viewBox="[^"]*"/, () => `viewBox="${vb}"`);
  return s;
};
const head = `<title>PCM-D10 Recorder Mark</title>
<meta name="claude:viewport-fit-sides" content="cover">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:wght@700&display=swap">
<style>
/* Layout: a specimen sheet. One wide plate per theme, then three detail plates on a 3-up grid. */
:root {
  --bg: #f4f5f6; --fg: #1d2125; --muted: #5d656d; --plate: #ffffff; --hair: rgba(29,33,37,.2); --accent: #2f5d8a;
  --sans: "Inter", system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
}
@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) { --bg: #0e1012; --fg: #e4e7ea; --muted: #9aa3ab; --plate: #16191c; --hair: rgba(228,231,234,.2); --accent: #8db6dc; color-scheme: dark } }
:root[data-theme="dark"] { --bg: #0e1012; --fg: #e4e7ea; --muted: #9aa3ab; --plate: #16191c; --hair: rgba(228,231,234,.2); --accent: #8db6dc; color-scheme: dark }
body { background: var(--bg); color: var(--fg); font: 15px/1.55 var(--sans); }
.wrap { max-width: 1240px; margin: 0 auto; padding-block: 40px 64px; padding-left: max(16px, env(safe-area-inset-left, 0px)); padding-right: max(16px, env(safe-area-inset-right, 0px)); display: grid; gap: 40px; }
header { display: grid; gap: 10px; max-width: 68ch; }
.eyebrow { font-size: 12px; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); }
h1 { font: 600 clamp(26px, 4vw, 38px)/1.15 var(--sans); margin: 0; letter-spacing: -.01em; text-wrap: balance; }
h2 { font: 600 13px/1.3 var(--sans); letter-spacing: .1em; text-transform: uppercase; margin: 0; color: var(--muted); }
p { margin: 0; color: var(--muted); }
.bar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
button { font: 500 14px/1 var(--sans); color: var(--fg); background: transparent; border: 1px solid var(--hair); border-radius: 999px; padding: 10px 16px; cursor: pointer; transition: border-color .2s, background-color .2s; }
button:hover { border-color: var(--fg); }
button:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
button[aria-pressed="true"] { background: var(--fg); color: var(--bg); border-color: var(--fg); }
@media (prefers-reduced-motion: reduce) { button { transition: none; } }
.pair { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 520px), 1fr)); gap: 16px; }
.plate { margin: 0; border-top: 1px solid var(--hair); padding-top: 14px; display: grid; gap: 12px; min-width: 0; }
.stage { border-radius: 4px; padding: clamp(12px, 3vw, 28px); overflow: hidden; }
.stage svg.pf-recorder { display: block; width: 100%; height: auto; overflow: hidden; }
.light { --bs-body-bg: #ffffff; --bs-body-color: #212529; background: #ffffff; color: #212529; }
.dark { --bs-body-bg: #121212; --bs-body-color: #e6e6e6; background: #121212; color: #e6e6e6; }
.theme { --bs-body-bg: var(--plate); background: var(--plate); color: var(--fg); }
.pf-recorder { --bs-body-font-family: var(--sans); }
figcaption { display: grid; gap: 4px; }
figcaption strong { font-weight: 600; font-size: 15px; }
.details { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr)); gap: 16px; }
.specs { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr)); gap: 0 24px; margin: 0; }
.specs div { border-top: 1px solid var(--hair); padding-block: 10px; display: grid; gap: 2px; }
.specs dt { font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }
.specs dd { margin: 0; font-variant-numeric: tabular-nums; }
`;
const body = `</style>
<div class="wrap">
  <header>
    <span class="eyebrow">Palefire · drawing review</span>
    <h1>Sony PCM-D10 field recorder</h1>
    <p>The hairline mark that goes with the cassette and the tape deck, drawn as a solid in millimeters and recording in a loop. This round: a hairline version with no shading, drawn like the tape deck, shown beside the shaded one.</p>
    <div class="bar"><button type="button" id="motion" aria-pressed="false">Pause the loop</button></div>
  </header>
  <section class="pair" aria-label="Full drawing">
    <figure class="plate">
      <div class="stage light">${svg('rec-a')}</div>
      <figcaption><strong>Shaded, light page</strong><p>Faces are the text color at set strengths over the page, so the drawing takes on any background.</p></figcaption>
    </figure>
    <figure class="plate">
      <div class="stage dark">${svg('rec-b')}</div>
      <figcaption><strong>Shaded, dark page</strong><p>The same drawing with nothing swapped: only the page and text colors change.</p></figcaption>
    </figure>
  </section>
  <section class="plate" aria-labelledby="hair">
    <h2 id="hair">Hairline version</h2>
    <p>No shading, like the tape deck: every face takes the page color, and only the edges a pen would draw are drawn, 1px wide. The creases where faces meet at an angle and the outlines where a surface turns away are found from the model itself, so the rolled sides read by their silhouette alone.</p>
    <div class="pair">
      <figure class="plate"><div class="stage light">${svg('rec-h1', null, twl)}</div><figcaption><strong>Hairline, light page</strong></figcaption></figure>
      <figure class="plate"><div class="stage dark">${svg('rec-h2', null, twl)}</div><figcaption><strong>Hairline, dark page</strong></figcaption></figure>
    </div>
  </section>
  <section class="plate" aria-labelledby="det">
    <h2 id="det">Details</h2>
    <div class="details">
      <figure class="plate">
        <div class="stage theme">${svg('rec-c', '-108 8 76 57')}</div>
        <figcaption><strong>Guard fins</strong><p>The jack panel sits back inside the rim, and the rounded sides stand a little proud of it either side of the jacks, following the roll of the body.</p></figcaption>
      </figure>
      <figure class="plate">
        <div class="stage theme">${svg('rec-d', '-20 -58 88 66')}</div>
        <figcaption><strong>Flat lens, curved edges</strong><p>The middle of the glass is flat so the readout sits true. The face only bows beyond it, toward the long edges.</p></figcaption>
      </figure>
      <figure class="plate">
        <div class="stage theme">${svg('rec-e', '40 -28 56 42')}</div>
        <figcaption><strong>Input dial</strong><p>Dual-concentric L and R rings in a round pocket, set in the molded guard that swells out of the side.</p></figcaption>
      </figure>
    </div>
  </section>
  <section class="plate" aria-labelledby="spec">
    <h2 id="spec">Model</h2>
    <dl class="specs">
      <div><dt>Body</dt><dd>72.3 × 185.5 × 37.4 mm</dd></div>
      <div><dt>Flat zone</dt><dd>Middle 55% of the width</dd></div>
      <div><dt>Sides</dt><dd>Rolled nearly half round, 9 mm across</dd></div>
      <div><dt>Fins</dt><dd>Thin lips, 2 mm thick, 1.6 mm proud</dd></div>
      <div><dt>Camera</dt><dd>50° around, 44° up</dd></div>
      <div><dt>Files</dt><dd>Shaded ${(Buffer.byteLength(tw) / 1024).toFixed(0)} kB, hairline ${(Buffer.byteLength(twl) / 1024).toFixed(0)} kB</dd></div>
    </dl>
  </section>
</div>
<script>
(() => {
  const b = document.getElementById('motion');
  b.addEventListener('click', () => {
    const on = b.getAttribute('aria-pressed') !== 'true';
    b.setAttribute('aria-pressed', String(on));
    b.textContent = on ? 'Play the loop' : 'Pause the loop';
    document.documentElement.classList.toggle('pf-a11y-motion', on);
  });
})();
</script>
`;
const page = head + css + '\n' + body;
fs.writeFileSync(outFile, page);
console.log('wrote', outFile, (Buffer.byteLength(page) / 1024).toFixed(0) + ' kB');
