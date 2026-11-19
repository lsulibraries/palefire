/**
 * Builds the Record Tab Layouts review sheet: Oral History's Record your own
 * tab composed several ways (D1-D5, L1-L6, R1-R6) with the hairline recorder
 * (templates/includes/recorder-line.html.twig) and css/recorder.css. The
 * agreed layout is L6 with R2. Published review artifact:
 * https://claude.ai/artifact/2Zrr4qRvrmJrDXJ4UYsnhJ (private).
 *
 * Usage, from the repository root:
 *   node build/record-layouts-mockup.js . out.html [photo-dir]
 * photo-dir holds p1.webp, p2.webp and p3.webp (the services' hover photos,
 * kept locally in the ignored _reference/record-layouts/, not in the repo);
 * without it the photo slots are left empty.
 */
const fs = require('fs'), path = require('path');
const [repo, out, photoDir] = process.argv.slice(2);
const svg = fs.readFileSync(repo + '/templates/includes/recorder-line.html.twig', 'utf8')
  .replace(/\{#[\s\S]*?#\}\n/, '').replace(/\{\{ attach_library[^\n]*\n/, '').replace(/\{% set id[^\n]*\n/, '')
  .split('{{ id }}').join('RECID').trim();
const css = fs.readFileSync(repo + '/css/recorder.css', 'utf8');
const ph = [1, 2, 3].map((n) => {
  const f = photoDir && path.join(photoDir, 'p' + n + '.webp');
  return f && fs.existsSync(f) ? 'data:image/webp;base64,' + fs.readFileSync(f).toString('base64') : 'data:image/gif;base64,R0lGODlhAQABAAAAACw=';
});

const services = ['Create your own oral history', 'Collaborate with the Williams Center', 'Oral History Research Consultations', 'Oral History Workshops', 'Oral History Seminars'];
const arrow = '<svg class="ar" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
const list = (cls = '') => `<div class="svc ${cls}"><p class="eb">Service</p>${services.map((s, i) => `<a class="row" data-ph="${i % 3}">${s}${arrow}</a>`).join('')}</div>`;
const eyebrow = '<p class="eb gold">01.3 <span>The Williams Center’s services</span></p>';
const intro = '<p class="intro">Plan an oral history project, learn to interview and record, or work with the Williams Center on yours, so what you record is kept.</p>';
const hours = '<div class="hours"><span class="dot"></span>Hours<span class="chev">↓</span></div>';
const rec = (cls) => `<div class="rec ${cls}" data-rec></div>`;
const caption = (cls = '') => `<p class="cap ${cls}"><span>Sony PCM-D10</span> Linear PCM field recorder, 24-bit / 192 kHz. Shown recording.</p>`;

const plates = [
  {
    id: 'now', tag: 'Now', title: 'Pane under Hours',
    body: `<div class="a-copy">${eyebrow}<h2>Record your own</h2>${intro}${hours}<div class="pane">${rec('')}</div></div>${list('c35')}`,
    note: 'What is live: the drawing in a frosted box. It reads as a widget, a third card under the intro and Hours, and the box’s edge competes with the band’s hairlines.',
  },
  {
    id: 'margin', tag: 'A', title: 'Object in the margin',
    body: `<div class="a-copy">${eyebrow}<h2>Record your own</h2>${intro}${hours}</div>${rec('m-rec')}<div class="m-rule"></div>${caption('m-cap')}${list('c35')}`,
    note: 'No box. The recorder sits on a hairline at the foot of the first column, larger, its left end hanging into the page margin like an object on a plinth, with a museum label under it. Its faces are filled with the band color, so it stays legible over a photo without a pane.',
    pros: 'Least change to the grid; the label gives it a reason to be there.',
    mobile: 'Under 64rem it follows the services at full column width, label below.',
  },
  {
    id: 'hinge', tag: 'B', title: 'The hinge',
    body: `<div class="h-copy">${eyebrow}<h2>Record your own</h2>${intro}</div><div class="h-hours">${hours}</div>${rec('h-rec')}${list('h-svc')}${caption('h-cap')}`,
    note: 'The recorder straddles the gutter between the two halves, level with the services, and each service’s hairline runs in from under it, as if the list were the recorder’s input. Copy above, Hours at the foot of the first column.',
    pros: 'Ties the two columns together; the most “composed” option.',
    mobile: 'The leader lines drop; the recorder goes between intro and services, centered.',
  },
  {
    id: 'title', tag: 'C', title: 'Set in the headline',
    body: `<div class="t-head">${eyebrow}<h2 class="t-h2">Record your own${rec('t-rec')}</h2></div><div class="t-copy">${intro}${hours}</div>${list('c35 t-svc')}`,
    note: 'The headline goes full width at display size and the recorder takes the place of the next word, sitting on the baseline at about two cap heights. Intro and Hours under it in the first column, services to the right.',
    pros: 'Strongest typographic gesture; the drawing literally finishes the sentence.',
    mobile: 'The headline wraps to two lines and the recorder drops under it at the left, the same height.',
  },
  {
    id: 'crop', tag: 'D1', title: 'Corner crop',
    body: `<div class="a-copy">${eyebrow}<h2>Record your own</h2>${intro}${hours}</div>${rec('dv d1')}${list('c35 d-svc')}`,
    note: 'The first version: the recorder very large and quiet, lines at a third of their strength, cropped by the band’s right and bottom edges, behind the services. When a service photo shows it fades further.',
    pros: 'The calmest of the set; the whole object is still recognizable.',
    mobile: 'Smaller, cropped at the band’s foot under the list.',
  },
  {
    id: 'top', tag: 'D2', title: 'From the top edge',
    body: `<div class="a-copy">${eyebrow}<h2>Record your own</h2>${intro}${hours}</div>${rec('dv d2')}${list('c35 d-svc')}`,
    note: 'The recorder hangs into the band from above, cropped by its top edge, its microphones reaching down toward the headline. The type sits over its lower end.',
    pros: 'Ties the drawing to the heading rather than the list; the services stay on a clean ground.',
    mobile: 'Cropped at the top of the panel, over the heading, at full width.',
  },
  {
    id: 'macro', tag: 'D3', title: 'Macro on the dial',
    body: `<div class="a-copy">${eyebrow}<h2>Record your own</h2>${intro}${hours}</div>${rec('dv d3')}${list('c35 d-svc')}`,
    note: 'So close that only the level dial, the screen and the record button are in the band. It stops being a picture of a recorder and becomes a field of rings and ticks, and the meters moving are the only motion on the band.',
    pros: 'Most abstract; reads as texture first and the object second.',
    mobile: 'Same crop, the dial centered behind the list.',
  },
  {
    id: 'stand', tag: 'D4', title: 'Standing full height',
    body: `<div class="a-copy">${eyebrow}<h2>Record your own</h2>${intro}${hours}</div>${rec('dv d4')}${list('c35 d-svc')}`,
    note: 'Turned upright so its length runs the band’s full height down the right edge, cropped top and bottom, like a specimen pinned in a case. The services run over its left half.',
    pros: 'Uses the band’s height; a strong vertical against the horizontal rows.',
    mobile: 'Left out under 48rem; the column is too narrow to stand it.',
  },
  {
    id: 'fade', tag: 'D5', title: 'Fading under the list',
    body: `<div class="a-copy">${eyebrow}<h2>Record your own</h2>${intro}${hours}</div>${rec('dv d5')}${list('c35 d-svc')}`,
    note: 'Cropped on the right as in D1, but the lines fade out toward the left, so they are strongest in the margin and gone by the time they reach the services’ words.',
    pros: 'Best for legibility: no line ever crosses a letter.',
    mobile: 'The fade runs bottom to top instead, under the last rows.',
  },
  {
    id: 'under', tag: 'D6', title: 'Under the copy',
    body: `<div class="a-copy">${eyebrow}<h2>Record your own</h2>${intro}${hours}</div>${rec('dv d6')}${list('c35 d-svc')}`,
    note: 'Moved to the other side: very large behind the intro and Hours, cropped by the left and bottom edges, so the services keep a clean ground and the first column gets the texture.',
    pros: 'Balances the band: the heavy list on the right, the drawing on the left.',
    mobile: 'Behind the heading and intro, cropped at the left edge.',
  },
  {
    id: 'gold', tag: 'D7', title: 'Gold lines',
    body: `<div class="a-copy">${eyebrow}<h2>Record your own</h2>${intro}${hours}</div>${rec('dv d7')}${list('c35 d-svc')}`,
    note: 'D1’s crop drawn in the band’s gold at low strength instead of white, so it reads as a printed mark, like the gold section numbers, rather than a drawing.',
    pros: 'Ties it to the eyebrow and Service labels; warmer on the dark swatch.',
    mobile: 'As D1, in gold.',
  },
  {
    before: `<div class="part"><p class="kick">Part two</p><h2 class="part-h">The list and its organization</h2><p class="lede">All with D5’s fading recorder. The ledger can show a title, a one-line note (from each page’s intro) and Category groups, so every board below is built from what editors can already set; nothing needs new fields. Notes marked “sample” are placeholder wording.</p></div>`,
    id: 'l-group', tag: 'L1', title: 'Grouped by what people came to do',
    body: `<div class="a-copy">${eyebrow}<h2>Record your own</h2>${intro}${hours}</div>${rec('dv d5')}<div class="svc c35 l-groups">
      <div><p class="eb">Start a project</p><a class="row" data-ph="0">Create your own oral history${arrow}</a><a class="row" data-ph="1">Collaborate with the Williams Center${arrow}</a></div>
      <div><p class="eb">Get help</p><a class="row" data-ph="2">Research consultations${arrow}</a></div>
      <div><p class="eb">Learn</p><a class="row" data-ph="0">Workshops${arrow}</a><a class="row" data-ph="1">Seminars${arrow}</a></div></div>`,
    note: 'Three Category groups instead of one “Service” label: Start a project, Get help, Learn. The group names carry the “Oral History” that three titles repeat, so the rows can be shorter.',
    pros: 'Editors only: Category terms on the five tiles, and shorter link titles if the tile allows its own.',
    mobile: 'Same groups, stacked.',
  },
  {
    id: 'l-notes', tag: 'L2', title: 'A ledger with notes',
    body: `<div class="a-copy">${eyebrow}<h2>Record your own</h2>${intro}${hours}</div>${rec('dv d5 d5-low')}<div class="svc c35 l-notes"><p class="eb">Service</p>
      ${[['Create your own oral history','Plan, record and keep an interview of your own'],['Collaborate with the Williams Center','Work with us on a project, from planning to preservation'],['Research consultations','One-on-one help with your project or our collection'],['Workshops','Hands-on training in interviewing and recording'],['Seminars','Talks on the method, its ethics and its uses']].map(([t,n],i)=>`<a class="row" data-ph="${i%3}"><span class="t">${t}</span><span class="n">${n}</span>${arrow}</a>`).join('')}
      <p class="sample">Notes: sample wording</p></div>`,
    note: 'Each row gets its page’s one-line note in a second column, so the five services can be told apart before clicking, which matters most for Consultations, Workshops and Seminars.',
    pros: 'Editors only: the ledger already shows the note when the page has an intro. Titles shrink to make room.',
    mobile: 'The note goes under the title.',
  },
  {
    id: 'l-nest', tag: 'L3', title: 'Nested: one guide, one program',
    body: `<div class="a-copy">${eyebrow}<h2>Record your own</h2>${intro}${hours}</div>${rec('dv d5')}<div class="svc c35 l-nest"><p class="eb">Service</p>
      <a class="row" data-ph="0">Create your own oral history${arrow}</a><div class="subs"><a>Plan</a><a>Interview</a><a>Record</a><a>Deposit</a><span class="sample">sample steps</span></div>
      <a class="row" data-ph="1">Collaborate with the Williams Center${arrow}</a>
      <a class="row" data-ph="2">Research consultations${arrow}</a>
      <a class="row" data-ph="0">Workshops and seminars${arrow}</a><div class="subs"><a>Workshops</a><a>Seminars</a><a>Upcoming dates</a></div></div>`,
    note: 'Two changes in one: the how-to guide shows its own sections as small links under its row, straight to the step people need; and Workshops and Seminars, both teaching, become one entry with the two as sub-links. Four rows instead of five, each a different kind of thing.',
    pros: 'Sub-links are a theme change (the guide’s sections already exist as anchors); merging Workshops and Seminars is an editor change.',
    mobile: 'Sub-links wrap under their row in one line.',
  },
  {
    id: 'l-grid', tag: 'L4', title: 'A grid with the visit in it',
    body: `<div class="a-copy">${eyebrow}<h2>Record your own</h2>${intro}<a class="cta">Book a consultation${arrow}</a></div>${rec('dv d5 d5-low')}<div class="svc c35 l-grid"><p class="eb">Service</p><div class="cells">
      ${['Create your own oral history','Collaborate with the Williams Center','Research consultations','Workshops','Seminars'].map((t,i)=>`<a class="cell" data-ph="${i%3}"><span>${t}</span>${arrow}</a>`).join('')}
      <div class="cell info"><span class="dot"></span><span><b>Hours</b><br>Today’s hours and where to find the Center</span></div></div></div>`,
    note: 'Five services and the visit as six even cells on hairlines, a museum floor plan’s rhythm. Hours leaves the first column for the sixth cell, and the first column ends in the one action most people come for.',
    pros: 'Theme change (a grid ledger variant). The action’s link and wording are the Williams Center’s call.',
    mobile: 'Two columns of cells, then one under 30rem.',
  },
  {
    id: 'l-stack', tag: 'L5', title: 'Short titles, set large',
    body: `<div class="a-copy">${eyebrow}<h2>Record your own</h2>${intro}${hours}</div>${rec('dv d5')}<div class="svc c35 l-stack"><p class="eb">Oral history services</p>
      ${['Create your own','Collaborate','Consultations','Workshops','Seminars'].map((t,i)=>`<a class="row" data-ph="${i%3}">${t}${arrow}</a>`).join('')}</div>`,
    note: 'With “Oral History” moved into the label, every title fits on one line at display size, light weight, tight rows: the list becomes the band’s typography, and the recorder’s lines fade in behind the short words’ empty right half.',
    pros: 'Editors: short titles. The full titles stay on the pages themselves and in search.',
    mobile: 'Same, at the section heading’s size.',
  },
  {
    id: 'l-quick', tag: 'L6', title: 'L1 as a Quick Start Guide',
    body: `<div class="a-copy q-copy">${eyebrow}<h2>Record your own</h2><p class="intro">Record an oral history yourself or with the Williams Center, so what you record is kept. Choose one of <strong>three ways</strong> to take part: start a project (A), get help (B), or learn how (C).</p>
      <p class="eb q-eb">Before you record</p><ul class="q-notes"><li>Research consultations are by appointment, in Room 201 at Hill.</li><li>The Center keeps and catalogs the interviews it collaborates on.</li><li>Workshop and seminar dates are on the events calendar.</li></ul><p class="sample">Notes: sample wording</p></div>
      ${rec('dv d5 q-rec')}
      <div class="q-paths">
      ${[['A','Start a','project',[['Create your own','A guide, step by step',0],['Collaborate','With the Center’s staff',1]]],['B','Get','help',[['Consultations','By appointment',2]]],['C','Learn','how',[['Workshops','Hands-on, scheduled',0],['Seminars','Talks and discussion',1]]]].map(([l,a,b,stops])=>`<div class="q-line"><p class="q-name"><span class="q-l">${l}</span><span>${a}<br>${b}</span></p><ol>${stops.map(([t,n,p])=>`<li><span class="q-ic"><i></i></span><a class="row q-stop" data-ph="${p}"><b>${t}</b><span>${n}</span></a></li>`).join('')}</ol></div>`).join('')}
      </div>`,
    note: 'The Research tab’s template, reused: the intro names the three ways in bold, each way is a lettered path (A, B, C) with the services as its stops (diamond, title, short note), and the first column gets its own notes list where Hours was. The recorder fades in under the paths, whose short columns leave the band’s lower right empty.',
    pros: 'Every Quick Start tab reads the same way, so a visitor who learned Research already knows Record. The first column carries real guidance instead of one Hours row.',
    mobile: 'Paths stack A, B, C as on Research; the recorder follows at the foot, fading upward.',
  },
  {
    before: `<div class="part"><p class="kick">Part four</p><h2 class="part-h">The recorder under L6</h2><p class="lede">With no hover photos the band’s lower half is free, so the drawing can be placed for the composition alone. Each board keeps every line clear of the text.</p></div>`,
    id: 'r-corner', tag: 'R1', title: 'Corner, fading up and left',
    body: `<div class="a-copy q-copy">${eyebrow}<h2>Record your own</h2><p class="intro">Record an oral history yourself or with the Williams Center, so what you record is kept. Choose one of <strong>three ways</strong> to take part: start a project (A), get help (B), or learn how (C).</p>
      <p class="eb q-eb">Before you record</p><ul class="q-notes"><li>Research consultations are by appointment, in Room 201 at Hill.</li><li>The Center keeps and catalogs the interviews it collaborates on.</li><li>Workshop and seminar dates are on the events calendar.</li></ul><p class="sample">Notes: sample wording</p></div>
      ${rec('dv r1')}
      <div class="q-paths">
      ${[['A','Start a','project',[['Create your own','A guide, step by step',0],['Collaborate','With the Center’s staff',1]]],['B','Get','help',[['Consultations','By appointment',2]]],['C','Learn','how',[['Workshops','Hands-on, scheduled',0],['Seminars','Talks and discussion',1]]]].map(([l,a,b,stops])=>`<div class="q-line"><p class="q-name"><span class="q-l">${l}</span><span>${a}<br>${b}</span></p><ol>${stops.map(([t,n,p])=>`<li><span class="q-ic"><i></i></span><a class="row q-stop" data-ph="${p}"><b>${t}</b><span>${n}</span></a></li>`).join('')}</ol></div>`).join('')}
      </div>`,
    note: 'L6 as drawn: cropped by the right and bottom edges, the fade running from the lower right toward the paths, so the lines are strongest where nothing else is.',
    pros: 'Safe and quiet; the same move as D5.',
  },
  {
    id: 'r-floor', tag: 'R2', title: 'Rising from the floor',
    body: `<div class="a-copy q-copy">${eyebrow}<h2>Record your own</h2><p class="intro">Record an oral history yourself or with the Williams Center, so what you record is kept. Choose one of <strong>three ways</strong> to take part: start a project (A), get help (B), or learn how (C).</p>
      <p class="eb q-eb">Before you record</p><ul class="q-notes"><li>Research consultations are by appointment, in Room 201 at Hill.</li><li>The Center keeps and catalogs the interviews it collaborates on.</li><li>Workshop and seminar dates are on the events calendar.</li></ul><p class="sample">Notes: sample wording</p></div>
      ${rec('dv r2')}
      <div class="q-paths">
      ${[['A','Start a','project',[['Create your own','A guide, step by step',0],['Collaborate','With the Center’s staff',1]]],['B','Get','help',[['Consultations','By appointment',2]]],['C','Learn','how',[['Workshops','Hands-on, scheduled',0],['Seminars','Talks and discussion',1]]]].map(([l,a,b,stops])=>`<div class="q-line"><p class="q-name"><span class="q-l">${l}</span><span>${a}<br>${b}</span></p><ol>${stops.map(([t,n,p])=>`<li><span class="q-ic"><i></i></span><a class="row q-stop" data-ph="${p}"><b>${t}</b><span>${n}</span></a></li>`).join('')}</ol></div>`).join('')}
      </div>`,
    note: 'Wide and low, cropped only by the band’s foot, fading upward instead of sideways: the lines are strongest along the bottom edge and gone before the last stops, as if the recorder lay on the floor of the band.',
    pros: 'Reads as a horizon under the three paths; uses the full width.',
  },
  {
    id: 'r-whole', tag: 'R3', title: 'The whole object, uncropped',
    body: `<div class="a-copy q-copy">${eyebrow}<h2>Record your own</h2><p class="intro">Record an oral history yourself or with the Williams Center, so what you record is kept. Choose one of <strong>three ways</strong> to take part: start a project (A), get help (B), or learn how (C).</p>
      <p class="eb q-eb">Before you record</p><ul class="q-notes"><li>Research consultations are by appointment, in Room 201 at Hill.</li><li>The Center keeps and catalogs the interviews it collaborates on.</li><li>Workshop and seminar dates are on the events calendar.</li></ul><p class="sample">Notes: sample wording</p></div>
      ${rec('dv r3')}
      <div class="q-paths">
      ${[['A','Start a','project',[['Create your own','A guide, step by step',0],['Collaborate','With the Center’s staff',1]]],['B','Get','help',[['Consultations','By appointment',2]]],['C','Learn','how',[['Workshops','Hands-on, scheduled',0],['Seminars','Talks and discussion',1]]]].map(([l,a,b,stops])=>`<div class="q-line"><p class="q-name"><span class="q-l">${l}</span><span>${a}<br>${b}</span></p><ol>${stops.map(([t,n,p])=>`<li><span class="q-ic"><i></i></span><a class="row q-stop" data-ph="${p}"><b>${t}</b><span>${n}</span></a></li>`).join('')}</ol></div>`).join('')}
      </div>`,
    note: 'Smaller and complete, at full line strength, set in the empty quadrant under paths B and C on the paths’ own left edge, with a small museum label. A specimen in a case rather than a texture.',
    pros: 'Most legible as an object; the label explains why it’s there.',
  },
  {
    id: 'r-dial', tag: 'R4', title: 'A spotlight on the dial',
    body: `<div class="a-copy q-copy">${eyebrow}<h2>Record your own</h2><p class="intro">Record an oral history yourself or with the Williams Center, so what you record is kept. Choose one of <strong>three ways</strong> to take part: start a project (A), get help (B), or learn how (C).</p>
      <p class="eb q-eb">Before you record</p><ul class="q-notes"><li>Research consultations are by appointment, in Room 201 at Hill.</li><li>The Center keeps and catalogs the interviews it collaborates on.</li><li>Workshop and seminar dates are on the events calendar.</li></ul><p class="sample">Notes: sample wording</p></div>
      ${rec('dv r4')}
      <div class="q-paths">
      ${[['A','Start a','project',[['Create your own','A guide, step by step',0],['Collaborate','With the Center’s staff',1]]],['B','Get','help',[['Consultations','By appointment',2]]],['C','Learn','how',[['Workshops','Hands-on, scheduled',0],['Seminars','Talks and discussion',1]]]].map(([l,a,b,stops])=>`<div class="q-line"><p class="q-name"><span class="q-l">${l}</span><span>${a}<br>${b}</span></p><ol>${stops.map(([t,n,p])=>`<li><span class="q-ic"><i></i></span><a class="row q-stop" data-ph="${p}"><b>${t}</b><span>${n}</span></a></li>`).join('')}</ol></div>`).join('')}
      </div>`,
    note: 'Large, but a soft round mask keeps only the dial, the screen and the record button, dissolving everything else. The moving meters sit inside the light.',
    pros: 'Draws the eye to the one moving part; the least literal.',
  },
  {
    id: 'r-gutter', tag: 'R5', title: 'Hung from path C',
    body: `<div class="a-copy q-copy">${eyebrow}<h2>Record your own</h2><p class="intro">Record an oral history yourself or with the Williams Center, so what you record is kept. Choose one of <strong>three ways</strong> to take part: start a project (A), get help (B), or learn how (C).</p>
      <p class="eb q-eb">Before you record</p><ul class="q-notes"><li>Research consultations are by appointment, in Room 201 at Hill.</li><li>The Center keeps and catalogs the interviews it collaborates on.</li><li>Workshop and seminar dates are on the events calendar.</li></ul><p class="sample">Notes: sample wording</p></div>
      ${rec('dv r5')}
      <div class="q-paths">
      ${[['A','Start a','project',[['Create your own','A guide, step by step',0],['Collaborate','With the Center’s staff',1]]],['B','Get','help',[['Consultations','By appointment',2]]],['C','Learn','how',[['Workshops','Hands-on, scheduled',0],['Seminars','Talks and discussion',1]]]].map(([l,a,b,stops])=>`<div class="q-line"><p class="q-name"><span class="q-l">${l}</span><span>${a}<br>${b}</span></p><ol>${stops.map(([t,n,p])=>`<li><span class="q-ic"><i></i></span><a class="row q-stop" data-ph="${p}"><b>${t}</b><span>${n}</span></a></li>`).join('')}</ol></div>`).join('')}
      </div>`,
    note: 'Turned upright and hung under path C, on its column, cropped by the band’s foot, fading upward into the last stop: the recorder becomes path C’s long tail, a vertical against the three short columns.',
    pros: 'Ties the drawing to the grid, not to a corner.',
  },
  {
    id: 'r-cross', tag: 'R6', title: 'Across the column line',
    body: `<div class="a-copy q-copy">${eyebrow}<h2>Record your own</h2><p class="intro">Record an oral history yourself or with the Williams Center, so what you record is kept. Choose one of <strong>three ways</strong> to take part: start a project (A), get help (B), or learn how (C).</p>
      <p class="eb q-eb">Before you record</p><ul class="q-notes"><li>Research consultations are by appointment, in Room 201 at Hill.</li><li>The Center keeps and catalogs the interviews it collaborates on.</li><li>Workshop and seminar dates are on the events calendar.</li></ul><p class="sample">Notes: sample wording</p></div>
      ${rec('dv r6')}
      <div class="q-paths">
      ${[['A','Start a','project',[['Create your own','A guide, step by step',0],['Collaborate','With the Center’s staff',1]]],['B','Get','help',[['Consultations','By appointment',2]]],['C','Learn','how',[['Workshops','Hands-on, scheduled',0],['Seminars','Talks and discussion',1]]]].map(([l,a,b,stops])=>`<div class="q-line"><p class="q-name"><span class="q-l">${l}</span><span>${a}<br>${b}</span></p><ol>${stops.map(([t,n,p])=>`<li><span class="q-ic"><i></i></span><a class="row q-stop" data-ph="${p}"><b>${t}</b><span>${n}</span></a></li>`).join('')}</ol></div>`).join('')}
      </div>`,
    note: 'Large, centered on the line between the first column and the paths, cropped by the band’s foot, fading out to both sides: the one element that crosses from the notes to the paths, under both.',
    pros: 'Bridges the two halves, like the hinge (B), but quietly.',
  },
];

const page = `<title>Record Tab Layouts</title>
<meta name="claude:viewport-fit-sides" content="cover">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600&family=Roboto+Mono:wght@400;500&display=swap">
<style>
/* Layout: a review sheet of artboards, each one the Record tab's band at 1280px, scaled to fit. */
:root{--bg:#f4f4f2;--fg:#1d1c20;--muted:#5b5960;--line:rgba(29,28,32,.16);--chip:#fff;--accent:#7a5c00;
--sans:Roboto,"Helvetica Neue",Arial,sans-serif;--mono:"Roboto Mono",ui-monospace,Consolas,monospace}
@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){--bg:#141316;--fg:#ececec;--muted:#a9a7ad;--line:rgba(236,236,236,.16);--chip:#1f1e22;--accent:#d6b25e;color-scheme:dark}}
:root[data-theme="dark"]{--bg:#141316;--fg:#ececec;--muted:#a9a7ad;--line:rgba(236,236,236,.16);--chip:#1f1e22;--accent:#d6b25e;color-scheme:dark}
*{box-sizing:border-box}
body{background:var(--bg);color:var(--fg);font:16px/1.55 var(--sans)}
.wrap{max-width:1180px;margin:0 auto;padding-block:48px 96px;padding-left:max(16px,env(safe-area-inset-left,0px));padding-right:max(16px,env(safe-area-inset-right,0px))}
header.top h1{font-weight:300;font-size:clamp(2rem,5vw,3.4rem);line-height:1.05;margin:.2em 0 .3em;letter-spacing:-.01em;text-wrap:balance}
.kick{font:500 .75rem/1 var(--mono);letter-spacing:.14em;text-transform:uppercase;color:var(--accent);margin:0}
.lede{max-width:62ch;color:var(--muted);margin:0}
.bar{position:sticky;top:env(safe-area-inset-top,0px);z-index:5;display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center;margin:28px 0 40px;padding:12px 0;background:var(--bg);border-bottom:1px solid var(--line)}
body::before{content:"";position:fixed;inset:0 0 auto;height:env(safe-area-inset-top,0px);z-index:10;background:var(--bg)}
.bar span{font:500 .72rem/1 var(--mono);letter-spacing:.12em;text-transform:uppercase;color:var(--muted)}
.bar button{font:inherit;font-size:.9rem;color:var(--fg);background:var(--chip);border:1px solid var(--line);padding:6px 12px;cursor:pointer}
.bar button[aria-pressed="true"]{border-color:var(--fg);box-shadow:inset 0 0 0 1px var(--fg)}
.bar button:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
.opt{display:grid;gap:18px;margin-bottom:72px}
.opt h2{display:flex;gap:14px;align-items:baseline;margin:0;font-weight:400;font-size:clamp(1.4rem,3vw,2rem);letter-spacing:-.005em}
.opt h2 b{font:500 .8rem/1 var(--mono);letter-spacing:.1em;color:var(--accent);min-width:2.6em}
.meta{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr);gap:8px 40px;max-width:100%}
.meta p{margin:0;min-width:0}.meta .side{color:var(--muted);font-size:.92rem}
.meta .side strong{display:block;font:500 .7rem/1.6 var(--mono);letter-spacing:.12em;text-transform:uppercase;color:var(--fg)}
@media (max-width:720px){.meta{grid-template-columns:1fr}}
.fit{width:100%;max-width:100%;overflow:hidden}
.fit-in{width:1280px;height:760px;transform-origin:0 0;transform:scale(var(--k,.5))}
.fit{aspect-ratio:1280/760}

/* The band: Palefire's dark swatch, 5-column grid at 1280 (64px margins, 32px gutter, 204.8px columns). */
.band{--bs-body-bg:#211f24;--bs-body-color:#ececec;--pf-section-border:#ececec;--pf-glass-filter:blur(26px) saturate(1.4);--gold:#d6b25e;--mut:#c4c2c8;
position:relative;width:1280px;height:760px;overflow:hidden;background:var(--bs-body-bg);color:var(--bs-body-color);font-family:var(--sans);isolation:isolate}
.band .photo{position:absolute;inset:0;z-index:-1;opacity:0;background:linear-gradient(var(--veil),var(--veil)),var(--img) center/cover;transition:opacity .7s cubic-bezier(.4,0,.2,1)}
.band.on .photo{opacity:1}
/* The site's Faded filter (--pf-photo-veil on dark swatches): a gray veil at 87%, so nothing is lighter than gray 64; gray text moves 40% toward white (--pf-photo-muted). */
.band{--veil:rgb(35 35 35 / .87)}
.band.on{--mut:color-mix(in srgb,#fff 40%,#c4c2c8)}
.band .eb,.band .intro,.band .cap,.band .chev{transition:color .7s}
.band .eb{margin:0 0 18px;font:500 11px/1 var(--mono);letter-spacing:.16em;text-transform:uppercase;color:var(--mut)}
.band .eb.gold{color:var(--gold)}.band .eb.gold span{color:var(--mut);margin-left:14px}
.band h2{margin:0 0 16px;font-weight:400;font-size:38px;line-height:1.1;letter-spacing:-.01em}
.band .intro{margin:0;max-width:400px;font-size:16px;line-height:1.55;color:var(--mut)}
.band .hours{display:flex;align-items:center;gap:12px;height:48px;padding:0 18px;margin-top:32px;font-size:14px;font-weight:500;background:rgba(255,255,255,.07);border:1px solid rgba(236,236,236,.12)}
.band .dot{width:8px;height:8px;border-radius:50%;background:#7bc47f}.band .chev{margin-left:auto;color:var(--mut)}
.band .svc{position:absolute;top:96px}
.band .svc .eb{color:var(--gold);margin-bottom:8px}
.band .row{display:flex;justify-content:space-between;align-items:center;gap:24px;padding:22px 0;border-bottom:1px solid rgba(236,236,236,.2);font-size:24px;font-weight:500;line-height:1.2;color:inherit;cursor:pointer}
.band .row .ar{width:22px;height:22px;flex:none;opacity:.6;transition:transform .3s ease,opacity .3s}
.band .row:hover .ar{transform:translateX(4px);opacity:1}
.c35{left:500px;width:716px}
.a-copy{position:absolute;left:64px;top:96px;width:410px}
.rec{position:absolute}
.rec .pf-recorder{display:block;width:100%;height:auto}
.pane{margin-top:28px;padding:16px;border:1px solid rgba(236,236,236,.2);background:color-mix(in srgb,var(--bs-body-bg) 72%,transparent);-webkit-backdrop-filter:var(--pf-glass-filter);backdrop-filter:var(--pf-glass-filter)}
.pane .rec{position:static}
.band .cap{position:absolute;margin:0;font-size:12px;line-height:1.5;color:var(--mut);max-width:300px}
.band .cap span{display:block;font:500 10.5px/1.6 var(--mono);letter-spacing:.14em;text-transform:uppercase;color:var(--bs-body-color)}

/* A: object in the margin */
.m-rec{left:22px;top:392px;width:470px}
.m-rule{position:absolute;left:64px;top:690px;width:410px;height:1px;background:rgba(236,236,236,.2)}
.m-cap{left:64px;top:700px}
.band .m-cap{max-width:410px}

/* B: the hinge */
.h-copy{position:absolute;left:64px;top:96px;width:410px}
.h-hours{position:absolute;left:64px;top:622px;width:300px}.h-hours .hours{margin:0}
.h-rec{left:330px;top:300px;width:330px;z-index:2}
.h-svc{left:390px;width:826px}
.h-svc .eb{margin-left:262px}
.h-svc .row{padding-left:262px;position:relative}
.h-svc .row:nth-child(-n+3){border-bottom-color:rgba(236,236,236,.2)}
.h-cap{left:64px;top:420px;width:250px}

/* C: set in the headline */
.t-head{position:absolute;left:64px;top:80px;width:1152px}
.band .t-h2{display:flex;align-items:flex-end;font-size:112px;font-weight:300;line-height:1;letter-spacing:-.03em;margin:0;white-space:nowrap}
.band .t-rec{position:relative;flex:none;width:230px;margin:0 0 -26px 28px}
.t-copy{position:absolute;left:64px;top:300px;width:410px}
.t-copy .intro{font-size:17px}
.band .t-svc{top:290px}

/* D: cropped specimen, variations */
.band .dv{opacity:var(--dv-o,.34);transition:opacity .7s cubic-bezier(.4,0,.2,1)}
.band.on .dv{opacity:calc(var(--dv-o,.34) * .35)}
.d-svc{z-index:1}
.band .d5-low{top:400px}
.part{display:grid;gap:10px;margin:24px 0 40px;padding-top:40px;border-top:1px solid var(--line)}
.part-h{margin:0;font-weight:300;font-size:clamp(1.8rem,4vw,2.8rem);letter-spacing:-.01em;text-wrap:balance}
.band .svc{z-index:1}
.band .sample{margin:14px 0 0;font:500 10px/1 var(--mono);letter-spacing:.14em;text-transform:uppercase;color:var(--mut);opacity:.7}
.l-groups{display:grid;grid-template-columns:1fr 1fr;gap:36px 32px}
.l-groups>div:first-child{grid-column:1/-1}
.l-groups .row{font-size:22px}
.l-notes .row{display:grid;grid-template-columns:300px minmax(0,1fr) 22px;gap:28px;font-size:21px}
.l-notes .n{font-size:15px;font-weight:400;line-height:1.45;color:var(--mut)}
.l-nest .subs{display:flex;flex-wrap:wrap;gap:6px 22px;align-items:baseline;padding:12px 0 18px;border-bottom:1px solid rgba(236,236,236,.2);font-size:15px;color:var(--mut)}
.l-nest .subs a{color:var(--bs-body-color);border-bottom:1px solid rgba(236,236,236,.35);padding-bottom:1px}
.l-nest .subs .sample{margin:0 0 0 auto}
.l-nest .row:has(+ .subs){border-bottom:0;padding-bottom:8px}
.l-grid .cells{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));border-top:1px solid rgba(236,236,236,.2);border-left:1px solid rgba(236,236,236,.2)}
.l-grid .cell{display:flex;flex-direction:column;justify-content:space-between;gap:24px;min-height:178px;padding:20px 22px;border-right:1px solid rgba(236,236,236,.2);border-bottom:1px solid rgba(236,236,236,.2);font-size:21px;font-weight:500;line-height:1.2;color:inherit;cursor:pointer}
.l-grid .cell .ar{width:22px;height:22px;align-self:flex-end;opacity:.6}
.l-grid .info{flex-direction:row;justify-content:flex-start;align-items:flex-start;gap:12px;font-size:14px;font-weight:400;line-height:1.5;color:var(--mut);cursor:default}
.l-grid .info b{font-weight:500;color:var(--bs-body-color)}
.l-grid .info .dot{margin-top:6px;flex:none}
.band .cta{display:inline-flex;align-items:center;gap:14px;margin-top:32px;padding:14px 20px;border:1px solid rgba(236,236,236,.6);font-size:15px;font-weight:500;color:inherit;cursor:pointer}
.band .cta .ar{width:20px;height:20px}
.q-copy .intro strong{color:var(--bs-body-color);font-weight:600}
.band .q-eb{margin:34px 0 0;padding-bottom:12px;border-bottom:1px solid rgba(236,236,236,.2);color:var(--mut)}
.q-notes{list-style:none;margin:0;padding:0;font-size:14px;line-height:1.5;color:var(--mut)}
.q-notes li{position:relative;padding:10px 0 10px 18px}
.q-notes li::before{content:"";position:absolute;left:2px;top:17px;width:6px;height:6px;background:var(--gold);transform:rotate(45deg)}
.q-paths{position:absolute;left:560px;top:96px;width:656px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:32px;z-index:1}
.q-name{display:flex;align-items:center;gap:22px;margin:0 0 30px;font-size:18px;font-weight:600;line-height:1.2}
.q-l{font-size:38px;font-weight:400;color:var(--gold);width:30px}
.q-line ol{list-style:none;margin:0;padding:0;display:grid;gap:30px}
.q-line li{display:flex;gap:20px;align-items:center}
.q-ic{flex:none;display:grid;place-items:center;width:34px;height:34px;margin-left:-2px}
.q-ic::before{content:"";position:absolute;width:24px;height:24px;border:1px solid rgba(214,178,94,.7);transform:rotate(45deg)}
.q-ic{position:relative}.q-ic i{width:9px;height:9px;border-radius:50%;border:1.5px solid var(--bs-body-color)}
.band .q-stop{display:grid;gap:2px;padding:0;border:0;font-size:16px}
.q-stop b{font-weight:600}.q-stop span{font-size:13px;font-weight:400;color:var(--mut)}
.band .q-rec{left:450px;top:330px;width:1250px}
.band .r1{left:450px;top:330px;width:1250px;--dv-o:.55;-webkit-mask-image:linear-gradient(120deg,transparent 22%,#000 60%);mask-image:linear-gradient(120deg,transparent 22%,#000 60%)}
.band .r2{left:120px;top:360px;width:1400px;--dv-o:.6;-webkit-mask-image:linear-gradient(180deg,transparent 40px,#000 330px);mask-image:linear-gradient(180deg,transparent 40px,#000 330px)}
.band .r3{left:600px;top:400px;width:430px;--dv-o:.9}
.band .r3::after{content:"Sony PCM-D10 · shown recording";position:absolute;left:100%;bottom:44px;width:180px;margin-left:24px;padding-top:8px;border-top:1px solid rgba(236,236,236,.2);font:500 10px/1.6 var(--mono);letter-spacing:.14em;text-transform:uppercase;color:var(--mut)}
.band .r4{left:-420px;top:-100px;width:2100px;--dv-o:.6;-webkit-mask-image:radial-gradient(circle 380px at 66% 52%,#000 35%,transparent 100%);mask-image:radial-gradient(circle 380px at 66% 52%,#000 35%,transparent 100%)}
.band .r5{left:720px;top:330px;width:860px;transform:rotate(90deg);--dv-o:.6;-webkit-mask-image:linear-gradient(90deg,transparent 4%,#000 34%);mask-image:linear-gradient(90deg,transparent 4%,#000 34%)}
.band .r6{left:-80px;top:330px;width:1200px;--dv-o:.62;-webkit-mask-image:linear-gradient(90deg,transparent 8%,#000 38%,#000 62%,transparent 92%),linear-gradient(0deg,#000 45%,transparent 78%);-webkit-mask-composite:source-in;mask-image:linear-gradient(90deg,transparent 8%,#000 38%,#000 62%,transparent 92%),linear-gradient(0deg,#000 45%,transparent 78%);mask-composite:intersect}
.l-stack .row{font-size:46px;font-weight:300;letter-spacing:-.015em;padding:12px 0}
.d1{left:430px;top:200px;width:1150px}
.d2{left:-60px;top:-400px;width:1300px;--dv-o:.3}
.d3{left:-1304px;top:-384px;width:2600px;--dv-o:.28}
.d4{left:620px;top:5px;width:1000px;transform:rotate(90deg);--dv-o:.3}
.d5{left:380px;top:170px;width:1150px;--dv-o:.55;-webkit-mask-image:linear-gradient(90deg,transparent 18%,#000 62%);mask-image:linear-gradient(90deg,transparent 18%,#000 62%)}
.d6{left:-300px;top:180px;width:1100px;--dv-o:.3}
.d7{left:430px;top:200px;width:1150px;--dv-o:.7;color:var(--gold)}

${css}
@media (prefers-reduced-motion:reduce){.band .photo,.d-rec,.band .row .ar{transition:none}}
.rlist{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 40px;margin:24px 0 0}
.rlist>div{padding:18px 0;border-top:1px solid var(--line);min-width:0}
.rlist dt{font-weight:500;font-size:1.05rem;display:flex;flex-wrap:wrap;gap:6px 12px;align-items:baseline}
.rlist dt em{font:500 .68rem/1 var(--mono);font-style:normal;letter-spacing:.12em;text-transform:uppercase;color:var(--accent)}
.rlist dd{margin:6px 0 0;color:var(--muted);max-width:60ch}
@media (max-width:720px){.rlist{grid-template-columns:1fr}}
.recs{margin-bottom:56px}
footer{color:var(--muted);font-size:.9rem;border-top:1px solid var(--line);padding-top:20px}
</style>
<div class="wrap">
<header class="top">
<p class="kick">Oral History · Record your own</p>
<h1>The recorder, out of its box</h1>
<p class="lede">Four ways, and seven takes on the cropped one, to make the hairline PCM-D10 part of the Record tab’s composition instead of a card under Hours. Each board is the band at 1280px with the real drawing, animating. Turn on a service photo to see each one over the hover state, or hover a service. Photos are under the site’s Faded filter, as on the live page.</p>
</header>
<div class="bar" role="group" aria-label="Hover photo">
<span>Hover photo</span>
<button type="button" data-p="-1" aria-pressed="true">Off</button>
<button type="button" data-p="0" aria-pressed="false">Create your own</button>
<button type="button" data-p="1" aria-pressed="false">Collaborate</button>
<button type="button" data-p="2" aria-pressed="false">Consultations</button>
</div>
${plates.map((p) => `${p.before || ''}<section class="opt" aria-labelledby="h-${p.id}">
<h2 id="h-${p.id}"><b>${p.tag}</b>${p.title}</h2>
<div class="fit"><div class="fit-in"><div class="band" id="b-${p.id}"><div class="photo" aria-hidden="true"></div>${p.body}</div></div></div>
<div class="meta"><p>${p.note}</p><div class="side">${p.pros ? `<p><strong>Why</strong>${p.pros}</p>` : ''}${p.mobile ? `<p style="margin-top:10px"><strong>Phone</strong>${p.mobile}</p>` : ''}</div></div>
</section>`).join('\n')}
<section class="part recs" aria-labelledby="recs-h">
<p class="kick">Part three</p>
<h2 class="part-h" id="recs-h">Navigation and organization</h2>
<p class="lede">Recommendations for the Record tab and how it connects to the rest of Oral History, each marked by who makes the change.</p>
<dl class="rlist">
<div><dt>Group the services by intent <em>Editors</em></dt><dd>Start a project, Get help, Learn (L1). Five rows under one “Service” label read as a flat list of program names; three short groups tell a first-time visitor where to begin.</dd></div>
<div><dt>Drop the repeated “Oral History” <em>Editors</em></dt><dd>The page, the tab and the eyebrow already say it. Three of five titles start with it, so the words that differ come last. Keep the full titles on the pages and in search.</dd></div>
<div><dt>Merge Workshops and Seminars <em>Editors</em></dt><dd>Both are teaching, on a schedule. One entry with its dates is easier to scan, and leaves four distinct kinds of service (L3). If they stay apart, give both a note so the difference is visible (L2).</dd></div>
<div><dt>Surface the guide’s steps <em>Theme</em></dt><dd>Create your own oral history is a long how-to. Listing its sections under its row (L3) lets people jump to the step they are on, and is the one place nesting earns its depth: one level only.</dd></div>
<div><dt>Give the tab one action <em>Editors + theme</em></dt><dd>The first column ends in Hours, which is about visiting, not recording. A single “Book a consultation” (or “Contact the Williams Center”) is what most of these services lead to; Hours can move into the list (L4) or stay in the page’s header bar.</dd></div>
<div><dt>Rename the tab to match its contents <em>Editors</em></dt><dd>“Record your own” fits two of the five services; Collaborate and Consultations are working with the Center. The eyebrow, The Williams Center’s services, is the more accurate name; “Record with us” covers both.</dd></div>
<div><dt>Order by the visitor’s path <em>Editors</em></dt><dd>Learn, plan, get help, collaborate: the order someone new moves in. The current order puts the do-it-yourself guide first and teaching last.</dd></div>
<div><dt>Keep it to one level in the tab <em>Theme</em></dt><dd>Anything deeper belongs on the service’s own page. The tab is a doorway: titles, at most a note, at most one row of sub-links.</dd></div>
</dl>
</section>
<footer>All four keep what the live version has: the drawing is decorative (aria-hidden), its motion stops for reduced motion, and its faces are solid band color, so over a photo it reads without a pane. The frosted pane can stay as an option under any of them.</footer>
</div>
<template id="rec-src">${svg}</template>
<script>
(function(){
  var src=document.getElementById('rec-src').innerHTML, n=0;
  document.querySelectorAll('[data-rec]').forEach(function(el){el.innerHTML=src.split('RECID').join('rec'+(++n));});
  var fits=[].slice.call(document.querySelectorAll('.fit'));
  function size(){fits.forEach(function(f){f.style.setProperty('--k',f.clientWidth/1280);});}
  size(); new ResizeObserver(size).observe(document.body);
  var photos=${JSON.stringify(ph)};
  var bands=[].slice.call(document.querySelectorAll('.band'));
  var btns=[].slice.call(document.querySelectorAll('.bar button'));
  var pinned=-1;
  function show(i){bands.forEach(function(b){if(i>=0){b.style.setProperty('--img','url('+photos[i]+')');b.classList.add('on');}else b.classList.remove('on');});}
  btns.forEach(function(b){b.addEventListener('click',function(){pinned=+b.dataset.p;btns.forEach(function(x){x.setAttribute('aria-pressed',x===b?'true':'false');});show(pinned);});});
  bands.forEach(function(band){
    band.querySelectorAll('.row').forEach(function(r){r.addEventListener('pointerenter',function(){var i=+r.dataset.ph;band.style.setProperty('--img','url('+photos[i]+')');band.classList.add('on');});});
    band.addEventListener('pointerleave',function(){if(pinned<0)band.classList.remove('on');else show(pinned);});
  });
})();
</script>
`;
fs.writeFileSync(out, page);
console.log((Buffer.byteLength(page) / 1024).toFixed(0) + ' kB');
