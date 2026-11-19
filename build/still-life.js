/**
 * Draws templates/includes/still-life.html.twig: the formats the Center's
 * interviews were recorded on, on a corner of a table: a 5-inch tape reel
 * at the back, a cassette before it, two clear MiniDiscs squared one on
 * the other and the digital recorders' memory cards (CompactFlash, Memory
 * Stick Pro Duo, SD, microSD) as one block nearest, each square to the
 * rest, with clear gaps between (PLACES; STILL_PLACES in the environment
 * tries others). A
 * hairline drawing in true perspective from the field recorder's camera
 * (build/recorder-mark.js: turned 50 degrees, looking down 44), so the two
 * drawings agree.
 *
 * Modeled in millimeters from the formats' published sizes: the reel 127
 * across (flanges 1.3 thick, 1/4-inch tape between them), the cassette
 * 100.4 x 63.8 x 8.7 with its 1.65 raised foot, the MiniDisc 72 x 68 x 5,
 * the cards at their own sizes. Every part is a prism: a plan outline (with
 * any holes) extruded between two heights. Drawn as a pen would: faces in
 * the page color, far before near, so nearer parts cover what is behind
 * them; lines only at outlines, creases and silhouettes (.e), the finer
 * marks lighter (.m). Through openings (the cassette's window and hubs)
 * what lies inside is drawn clipped to the opening.
 *
 * Paths are written short (relative moves in tenths, the lines of one kind
 * merged) to keep the page light.
 *
 * Usage: node build/still-life.js [out]
 */
const fs = require('fs');
const path = require('path');
const out = process.argv[2] || path.join(__dirname, '../templates/includes/still-life.html.twig');

// ---- Camera (the recorder's). ------------------------------------------------
const RAD = Math.PI / 180;
const TH = 50 * RAD, PH = 44 * RAD, D = 1100;
const raw = ([x, y, z]) => {
  const u = x * Math.cos(TH) - y * Math.sin(TH);
  const v = x * Math.sin(TH) + y * Math.cos(TH);
  const sy = v * Math.sin(PH) - z * Math.cos(PH);
  const dep = v * Math.cos(PH) + z * Math.sin(PH);
  const s = D / (D - dep);
  return [u * s, sy * s];
};
const CAM = [D * Math.cos(PH) * Math.sin(TH), D * Math.cos(PH) * Math.cos(TH), D * Math.sin(PH)];
// The table's line for an object placed without a depth (toward the
// viewer, mm).
const ROW = 0;
// A place on the table by screen across (u) and toward the viewer (v).
const at = (u, v) => [u * Math.cos(TH) + v * Math.sin(TH), -u * Math.sin(TH) + v * Math.cos(TH)];

// ---- Vectors and output. -----------------------------------------------------
const sub = (a, b) => a.map((v, i) => v - b[i]);
const dot = (a, b) => a.reduce((t, v, i) => t + v * b[i], 0);
const f1 = n => (Math.round(n * 10) / 10).toString();
// The drawing's extent.
const bbox = [Infinity, Infinity, -Infinity, -Infinity];
const grow = (b, x, y) => {
  b[0] = Math.min(b[0], x); b[1] = Math.min(b[1], y);
  b[2] = Math.max(b[2], x); b[3] = Math.max(b[3], y);
};
// A point on the screen in tenths (integers, so moves add up exactly).
const P = p => {
  const [x, y] = raw(p);
  grow(bbox, x, y);
  return [Math.round(x * 10), Math.round(y * 10)];
};
// Tenths as short as SVG reads them: no leading zero, no space before a
// minus or after a number with a point when the next starts with one.
const num = t => (t / 10).toString().replace(/^(-?)0\./, '$1.');
const nums = list => list.map(num).reduce((acc, n) => {
  if (acc === null) return n;
  const sep = n[0] === '-' || (n[0] === '.' && /\.\d*$/.test(acc)) ? '' : ' ';
  return acc + sep + n;
}, null);
// A first point, then each next one as a move from the last (repeats
// dropped).
const enc = (p3, close) => {
  const pts = p3.map(P);
  const out = [];
  for (let i = 1; i < pts.length; i++) {
    const dx = pts[i][0] - pts[i - 1][0], dy = pts[i][1] - pts[i - 1][1];
    if (dx || dy) out.push(dx, dy);
  }
  return 'M' + nums(pts[0]) + (out.length ? 'l' + nums(out) : '') + (close ? 'z' : '');
};
const ring = (p3) => enc(p3, true);
const run = (p3) => enc(p3, false);
let svg = [];
let clipN = 0;

// ---- Shapes in a part's own plan (millimeters, counterclockwise). ----------
// Sides enough that a side strays under an eighth of a unit from the
// true circle (at most n).
const sides = (r, n) => Math.min(n, Math.max(10, Math.ceil(Math.PI * Math.sqrt(r / 0.25))));
const circle = (r, cx = 0, cy = 0, n = 96, a0 = 0) => (n = sides(r, n), Array.from({ length: n }, (_, i) => {
  const a = a0 + (i / n) * 2 * Math.PI;
  return [cx + r * Math.cos(a), cy + r * Math.sin(a)];
}));
const arc = (r, a0, a1, cx = 0, cy = 0, n = 24) => Array.from({ length: n + 1 }, (_, i) => {
  const a = a0 + (a1 - a0) * (i / n);
  return [cx + r * Math.cos(a), cy + r * Math.sin(a)];
});
// A rounded rectangle, x0..x1 by y0..y1, corner radius r.
const rrect = (x0, y0, x1, y1, r, n = 6) => {
  if (r <= 0) return [[x0, y0], [x1, y0], [x1, y1], [x0, y1]];
  const q = (cx, cy, a) => arc(r, a, a + Math.PI / 2, cx, cy, n);
  return [
    ...q(x1 - r, y0 + r, -Math.PI / 2),
    ...q(x1 - r, y1 - r, 0),
    ...q(x0 + r, y1 - r, Math.PI / 2),
    ...q(x0 + r, y0 + r, Math.PI),
  ];
};
// An annular sector (a reel's window), r0..r1 between angles, corners
// rounded a little.
const sector = (r0, r1, a0, a1) => [...arc(r1, a0, a1, 0, 0, 20), ...arc(r0, a1, a0, 0, 0, 12)];
const area = q => q.reduce((t, p, i) => { const r = q[(i + 1) % q.length]; return t + p[0] * r[1] - r[0] * p[1]; }, 0) / 2;
const ccw = q => (area(q) < 0 ? q.slice().reverse() : q);

// A placed object: its plan points to the table (rotation a, then position).
const frame = (u, v, deg, z = 0) => {
  const [ox, oy] = at(u, v);
  const c = Math.cos(deg * RAD), s = Math.sin(deg * RAD);
  const W = ([x, y], h) => [ox + x * c - y * s, oy + x * s + y * c, z + h];
  // A point given on the screen's terms (across, toward the viewer) from
  // the object's middle, in its own plan.
  const L = (du, dv) => {
    const [wx, wy] = at(du, dv);
    return [wx * c + wy * s, -wx * s + wy * c];
  };
  return { W, L, deg, z, ox, oy };
};

// ---- Drawing parts. ----------------------------------------------------------
const CREASE = Math.cos(28 * RAD);
const faces = (fr, q, h0, h1, inward) => {
  // Walls of an outline q between heights h0 and h1; inward: the walls of a
  // hole or pocket (seen from inside it).
  const B = q.map(p => fr.W(p, h0)), T = q.map(p => fr.W(p, h1));
  const n = q.length;
  const vis = [], nrm = [];
  for (let i = 0; i < n; i++) {
    const p = q[i], r = q[(i + 1) % n];
    let ox = r[1] - p[1], oy = -(r[0] - p[0]);
    if (inward) { ox = -ox; oy = -oy; }
    const l = Math.hypot(ox, oy) || 1;
    const mid = [(p[0] + r[0]) / 2, (p[1] + r[1]) / 2], hm = (h0 + h1) / 2;
    const c = fr.W(mid, hm);
    const w = sub(fr.W([mid[0] + ox / l, mid[1] + oy / l], hm), c);
    const wl = Math.hypot(...w) || 1;
    nrm.push(w.map(v => v / wl));
    vis.push(dot(nrm[i], sub(CAM, c)) > 0);
  }
  return { B, T, n, vis, nrm };
};
const wallsOut = ({ B, T, n, vis, nrm }, fillCls = 'f') => {
  // Each band of walls in a row that face the eye as one shape: its foot,
  // then its top back (they all wind one way on the screen, so the band
  // fills as its walls would), and its foot as one line.
  let fill = '', edge = '';
  const start = vis.every(Boolean) ? 0 : vis.findIndex((v, i) => v && !vis[(i - 1 + n) % n]);
  if (start >= 0) {
    for (let k = 0; k < n; k++) {
      const i = (start + k) % n;
      if (!vis[i] || (k && vis[(i - 1 + n) % n])) continue;
      let len = 0;
      while (len < n && vis[(i + len) % n]) len++;
      const idx = Array.from({ length: len + 1 }, (_, m) => (i + m) % n);
      fill += ring([...idx.map(m => B[m]), ...idx.slice().reverse().map(m => T[m])]);
      edge += run(idx.map(m => B[m]));
    }
  }
  for (let i = 0; i < n; i++) {
    const p = (i - 1 + n) % n;
    const sharp = dot(nrm[p], nrm[i]) < CREASE;
    if (vis[p] !== vis[i] || (vis[p] && vis[i] && sharp)) edge += run([B[i], T[i]]);
  }
  if (fill) svg.push(`<path class="${fillCls}" d="${fill}"/>`);
  if (edge) svg.push(`<path class="e" d="${edge}"/>`);
};

// A solid: outline (and holes through it) from h0 up to h1.
const prism = (fr, outline, h0, h1, { holes = [], cls = 'e', top = true, fill = 'f' } = {}) => {
  const q = ccw(outline);
  const outer = faces(fr, q, h0, h1, false);
  wallsOut(outer, fill);
  // Hole walls show on the hole's far side, below the top.
  holes.forEach(hq => wallsOut(faces(fr, ccw(hq), h0, h1, true), fill));
  if (top) {
    // The cap that faces the eye: the top, or on a tilted part the other.
    const c = fr.W(q[0], h1), up = sub(fr.W(q[0], h1 + 1), c);
    const hc = dot(up, sub(CAM, c)) > 0 ? h1 : h0;
    // Seen from its other face (a tilted part), the far rim of each wall
    // that shows is an outline too.
    if (hc === h0) {
      let rim = '';
      for (let i = 0; i < outer.n; i++) if (outer.vis[i]) rim += run([outer.T[i], outer.T[(i + 1) % outer.n]]);
      if (rim) svg.push(`<path class="e" d="${rim}"/>`);
    }
    const d = ring(q.map(p => fr.W(p, hc))) + holes.map(hq => ring(hq.map(p => fr.W(p, hc)))).join('');
    // One path filled and outlined (.f with a line class).
    svg.push(`<path class="${fill} ${cls}" fill-rule="evenodd" d="${d}"/>`);
  }
};

// Does the face at plan point p, height h, with plan outward normal (nx, ny)
// (a wall) face the eye?
const wallFaces = (fr, p, h, nx, ny) => {
  const c = fr.W(p, h);
  return dot(sub(fr.W([p[0] + nx, p[1] + ny], h), c), sub(CAM, c)) > 0;
};

// A line or mark drawn on a surface at height h.
const mark = (fr, pts, h, cls = 'm', closed = true) => {
  const w = pts.map(p => fr.W(p, h));
  svg.push(`<path class="${cls}" d="${closed ? ring(w) : run(w)}"/>`);
};

// An opening in a top at height h: what is inside drawn clipped to it.
// inside(fr): draws the contents. depth: the opening's walls down to h-depth.
const opening = (fr, q, h, depth, inside) => {
  const id = `${'{{ id }}'}-c${++clipN}`;
  const rim = ring(ccw(q).map(p => fr.W(p, h)));
  svg.push(`<clipPath id="${id}"><path d="${rim}"/></clipPath><g clip-path="url(#${id})">`);
  // The floor, then the walls seen from inside.
  const floor = ring(ccw(q).map(p => fr.W(p, h - depth)));
  svg.push(`<path class="f" d="${floor}"/>`);
  if (inside) inside(fr);
  wallsOut(faces(fr, ccw(q), h - depth, h, true));
  svg.push(`</g><path class="e" d="${rim}"/>`);
};

// Flat print on a surface: text mapped by the local affine of the plan.
const text = (fr, [x, y], h, size, str, cls = 'm-text', anchor = 'start', rot = 0) => {
  // rot 'screen': along the screen's across, whatever way the object lies.
  if (rot === 'screen') { const [px, py] = fr.L(1, 0); rot = Math.atan2(py, px) / RAD; }
  const c = Math.cos(rot * RAD), s = Math.sin(rot * RAD);
  const o = raw(fr.W([x, y], h));
  const ex = raw(fr.W([x + c, y + s], h)), ey = raw(fr.W([x - s, y + c], h));
  // Text's +y is down the line; the plan's "down" for reading is -y.
  const m = [ex[0] - o[0], ex[1] - o[1], ey[0] - o[0], ey[1] - o[1], o[0], o[1]].map(n => (Math.round(n * 1000) / 1000).toString());
  svg.push(`<text class="${cls}" text-anchor="${anchor}" font-size="${size}" transform="matrix(${m.join(' ')})">${str}</text>`);
};

// Print round a circle (a reel's flange): each letter placed on the arc at
// radius r, centered on angle mid (degrees), its foot to the edge.
const arcText = (fr, str, mid, r, h, size, dir = ARC_DIR) => {
  const adv = size * 0.68;
  const n = str.length, span = (adv * (n - 1)) / r;
  [...str].forEach((ch, i) => {
    if (ch === ' ') return;
    const a = mid * RAD + dir * (span / 2 - (i * adv) / r);
    text(fr, [r * Math.cos(a), r * Math.sin(a)], h, size, ch, 'm-text', 'middle', a / RAD + dir * 90 + 180);
  });
};
const ARC_DIR = 1;

// ---- 1. The reel: 5 inches, 1/4-inch tape, its leader wound in. ----------
const reel = (u, v = ROW) => {
  const fr = frame(u, v, 0);
  const R = 63.5, T1 = 1.3, GAP = 6.6;
  const windows = [0, 1, 2].map(k => sector(24, 55, (90 + 120 * k - 36) * RAD, (90 + 120 * k + 36) * RAD));
  // Bottom flange, the pack, the top flange.
  prism(fr, circle(R), 0, T1, { holes: windows });
  prism(fr, circle(46), T1, T1 + GAP, { top: true });
  [30, 38].forEach(r => mark(fr, circle(r), T1 + GAP));
  prism(fr, circle(R), T1 + GAP, T1 * 2 + GAP, { holes: [...windows, circle(4)] });
  // The hub: its ring and the three keyways round the spindle hole, the
  // windows' molded rims.
  const top = T1 * 2 + GAP;
  mark(fr, circle(13), top);
  [0, 1, 2].forEach(k => {
    const a = (30 + 120 * k) * RAD;
    mark(fr, rrect(-1, 3.6, 1, 6.2, 0.4).map(([x, y]) => [x * Math.cos(a) - y * Math.sin(a), x * Math.sin(a) + y * Math.cos(a)]), top);
  });
  mark(fr, circle(59.5), top);
  // 600 FT round the flange's edge, letter by letter, in the solid gap
  // between the two windows nearest the viewer.
  const [fx, fy] = fr.L(0, 1);
  const toward = Math.atan2(fy, fx) / RAD;
  const gap = [30, 150, 270].reduce((b, g) => (Math.abs(((g - toward + 540) % 360) - 180) < Math.abs(((b - toward + 540) % 360) - 180) ? g : b));
  arcText(fr, '600 FT', gap, 57.6, top, 3.4);
};

// ---- 2. The cassette, label side up, head edge away. ------------------------
const cassette = (u, v = ROW) => {
  const fr = frame(u, v, 0);
  const L = 100.4 / 2, Wd = 63.8 / 2, H = 8.7;
  // Head edge at +y (away); the foot (trapezoid) raised on it.
  prism(fr, rrect(-L, -Wd, L, Wd, 2.6), 0, H);
  prism(fr, [[-36, Wd], [-28, Wd - 11.5], [28, Wd - 11.5], [36, Wd]], H, H + 1.65);
  // The foot's holes: capstan, guide pins, the pressure pad's opening.
  [-1, 1].forEach(sx => {
    mark(fr, circle(1.4, sx * 17, Wd - 5, 20), H + 1.65, 'e');
    mark(fr, rrect(sx * 25 - 1.5, Wd - 4, sx * 25 + 1.5, Wd - 1.5, 0.4), H + 1.65, 'e');
  });
  mark(fr, rrect(-5, Wd - 4.2, 5, Wd - 1.2, 0.6), H + 1.65, 'e');
  // The label: its edge, a ruled line for the title, the side letter.
  mark(fr, rrect(-L + 5, -Wd + 4, L - 5, Wd - 13.5, 1.5), H);
  [-1, 1].forEach(k => mark(fr, [[-L + 12, -Wd + 11 + k * 0], [L - 12, -Wd + 11 + k * 0]], H, 'm', false));
  text(fr, [-L + 8, -Wd + 7.4], H, 4.5, 'A', 'm-text');
  text(fr, [L - 8, -Wd + 7.4], H, 3.4, 'C60', 'm-text', 'end');
  // The window: the two packs and the tape between, behind clear plastic.
  const hubs = [-21.1, 21.1];
  opening(fr, rrect(-13.5, -6.5, 13.5, 6.5, 2.4), H, 3.6, (f) => {
    mark(f, circle(19, hubs[0], 0), H - 3.6, 'm');
    mark(f, circle(13, hubs[1], 0), H - 3.6, 'm');
  });
  // The hub holes: each hub's ring and six teeth, down inside.
  hubs.forEach(hx => {
    opening(fr, circle(6.6, hx, 0, 48), H, 2.2, (f) => {
      mark(f, circle(4.2, hx, 0, 36), H - 2.2, 'm');
      for (let k = 0; k < 6; k++) {
        const a = (k * 60) * RAD;
        mark(f, [[hx + 2.2 * Math.cos(a), 2.2 * Math.sin(a)], [hx + 4.2 * Math.cos(a), 4.2 * Math.sin(a)]], H - 2.2, 'm', false);
      }
    });
  });
  // Screws, and the write-protect notches at the back edge.
  [[-L + 4, -Wd + 3.6], [L - 4, -Wd + 3.6], [-L + 4, Wd - 3.6], [L - 4, Wd - 3.6], [0, -Wd + 3.4]].forEach(([x, y]) => {
    mark(fr, circle(1.25, x, y, 18), H, 'e');
    mark(fr, [[x - 0.8, y], [x + 0.8, y]], H, 'm', false);
  });
  [-1, 1].forEach(sx => opening(fr, rrect(sx * 41 - 3, -Wd, sx * 41 + 3, -Wd + 3.4, 0), H, 3));
};

// ---- 3. The MiniDiscs, clear shells: two, squared one on the other. -----
// The Center's were clear: the disc shows through the shell (its edge, the
// recorded band, the metal hub plate), with the compartment's round wall
// and the corner posts; the shutter is metal. flip: the cartridge shows its
// other face (as it would in a player's lid, facing out).
const minidisc = (fr, h0, { open = false, flip = false } = {}) => {
  const X = 36, Y = 34, H = 5;
  prism(fr, rrect(-X, -Y, X, Y, 2.4), h0, h0 + H, { fill: 'pf-still__clear' });
  const face = flip ? h0 : h0 + H, out = flip ? -1 : 1, mid = h0 + H / 2;
  // Inside, through the plastic.
  mark(fr, circle(33.6, 0, 1, 120), mid, 'm');
  mark(fr, circle(32, 0, 1, 120), mid, 'pf-still__disc');
  mark(fr, circle(29, 0, 1, 120), mid, 'm');
  mark(fr, circle(12, 0, 1, 60), mid, 'm');
  mark(fr, circle(5.6, 0, 1, 40), mid, 'pf-still__disc');
  mark(fr, circle(1.8, 0, 1, 20), mid, 'm');
  [[-1, -1], [1, -1], [-1, 1], [1, 1]].forEach(([sx, sy]) => mark(fr, circle(1.3, sx * 32.4, sy * 30.4, 16), mid, 'm'));
  // On the face: the label strip at the back, the insertion arrow, the
  // write-protect tab.
  mark(fr, rrect(-31, Y - 9.5, 31, Y - 3.5, 1), face);
  text(fr, flip ? [-22, Y - 7.6] : [22, Y - 7.6], face, 3.2, 'MD 74', 'm-text', 'start', flip ? 0 : 180);
  mark(fr, [[-27, -24], [-23, -24], [-25, -28]], face);
  if (!flip) prism(fr, rrect(X - 8.6, Y - 13, X - 5.4, Y - 10.6, 0.4), face, face + 0.4);
  // The slot in the shell (a hole: the disc itself shows in it), and the
  // shutter over it, or slid aside when open.
  mark(fr, rrect(-6.5, -Y, 6.5, -5, 1.2), face, 'e');
  const wall = wallFaces(fr, [0, -Y], mid, 0, -1);
  const band = (x0, x1) => {
    if (!wall) return;
    svg.push(`<path class="e" d="${run([fr.W([x0, -Y], face), fr.W([x0, -Y], face - out * 3.8), fr.W([x1, -Y], face - out * 3.8), fr.W([x1, -Y], face)])}"/>`);
  };
  const sx = open ? 22 : 0;
  band(sx - 12, sx + 12);
  const h1 = face, h2 = face + out * 0.35;
  prism(fr, rrect(sx - 12, -Y, sx + 12, -6, 1.6), Math.min(h1, h2), Math.max(h1, h2));
  mark(fr, [[sx - 8, -Y + 4], [sx + 8, -Y + 4]], h2, 'm', false);
};
// Two, squared one on the other.
const stack = (u, v = ROW) => {
  minidisc(frame(u, v, 180), 0);
  minidisc(frame(u, v, 180), 5);
};

// ---- 4. The memory cards of the digital recorders, oldest to newest. -------
// CompactFlash (43 x 36 x 3.3), label up: the label's recess, the guide
// grooves along both sides, and the socket's two rows of pin holes in the
// end toward the viewer.
const compactflash = (u, v = ROW) => {
  const fr = frame(u, v, 0);
  const X = 21.4, Y = 18.2, H = 3.3;
  prism(fr, rrect(-X, -Y, X, Y, 1), 0, H);
  mark(fr, rrect(-X + 2.6, -Y + 4.5, X - 2.6, Y - 2.4, 0.8), H);
  [-1, 1].forEach(k => mark(fr, [[k * (X - 1.2), -Y + 1], [k * (X - 1.2), Y - 1]], H, 'm', false));
  text(fr, [-X + 4.4, Y - 6.4], H, 3.6, 'CF', 'm-text');
  if (wallFaces(fr, [0, -Y], H / 2, 0, -1)) {
    let pins = '';
    for (let i = 0; i < 25; i++) {
      const x = -X + 3.2 + i * ((2 * X - 6.4) / 24);
      [0.95, 2.35].forEach(h => { pins += run([fr.W([x - 0.25, -Y], h), fr.W([x + 0.25, -Y], h)]); });
    }
    svg.push(`<path class="m" d="${pins}"/>`);
  }
};

// Memory Stick Pro Duo (20 x 31 x 1.6), contacts up: ten gold pads between
// ribs at the connector end, its corner cut, the grip notch in one side.
const memorystick = (u, v = ROW) => {
  const fr = frame(u, v, 0);
  const X = 10, Y = 15.5, H = 1.6;
  const notch = arc(1.4, Math.PI / 2, -Math.PI / 2, -X, -6, 8).map(([x, y]) => [x + 0.0001, y]).reverse();
  prism(fr, [[-X, -Y], [X, -Y], [X, Y - 2.6], [X - 2.6, Y], [-X, Y], ...notch.map(([x, y]) => [-X + (x + X), y]).reverse()].filter((p, i, a) => i === 0 || p[0] !== a[i - 1][0] || p[1] !== a[i - 1][1]), 0, H);
  for (let i = 0; i < 10; i++) {
    const x = -X + 1.6 + i * 1.72;
    mark(fr, rrect(x, Y - 5.6, x + 1.05, Y - 1.2, 0.2), H, 'pf-still__gold');
  }
  mark(fr, [[-X + 1, Y - 6.6], [X - 3.2, Y - 6.6]], H, 'm', false);
};

// microSD (11 x 15 x 1), contacts up: eight gold pads at the wide end, the
// step and notch along one side.
const microsd = (u, v = ROW) => {
  const fr = frame(u, v, 0);
  const H = 1;
  prism(fr, [[-5.5, -7.5], [4.2, -7.5], [4.2, -1.4], [4.85, -0.8], [4.85, 4.6], [5.5, 5.3], [5.5, 7.5], [-5.5, 7.5]], 0, H);
  for (let i = 0; i < 8; i++) {
    const x = -4.9 + i * 1.18;
    mark(fr, rrect(x, 7.5 - 4.2 + (i % 2 ? 0.6 : 0), x + 0.82, 7.5 - 0.9, 0.15), H, 'pf-still__gold');
  }
};

// The SD card, contacts up, its lock at the left.
const sdcard = (u, v = ROW) => {
  const fr = frame(u, v, 0);
  const X = 12, Y = 16, H = 2.1;
  // Corner cut at the top right; the lock's recess on the left edge.
  const outline = [[-X, -Y], [X, -Y], [X, Y - 4], [X - 4, Y], [-X, Y], [-X, 4], [-X + 0.7, 4], [-X + 0.7, -4], [-X, -4]];
  prism(fr, outline, 0, H);
  prism(fr, [[-X + 0.7, -3], [-X - 0.2, -3], [-X - 0.2, 1], [-X + 0.7, 1]], 0.4, 1.7);
  // Nine contacts along the cut edge, each a gold-plated pad.
  const xs = [-9.2, -6.7, -4.2, -1.7, 0.8, 3.3, 5.8, 8.3];
  xs.forEach(x => mark(fr, rrect(x - 0.9, Y - 6.2, x + 0.9, Y - 1.4, 0.3), H, 'pf-still__gold'));
  mark(fr, rrect(-11.4, Y - 5.4, -10.4, Y - 1.4, 0.3), H, 'pf-still__gold');
};

// ---- Scene: a corner of the table. -------------------------------------------
// The cards as one block about a MiniDisc's size (some 70 x 74 mm): two rows
// in the cards' own plan, CompactFlash and Memory Stick, then SD and
// microSD, each square to the rest with even gaps; drawn far to near.
const cards = (u, v = ROW) => {
  const place = [[compactflash, -12.5, -17], [memorystick, 25, -17], [sdcard, -12, 23], [microsd, 12, 27]]
    .map(([draw, x, y]) => [draw, u + x * Math.cos(TH) - y * Math.sin(TH), v + x * Math.sin(TH) + y * Math.cos(TH)])
    .sort((p, q) => p[2] - q[2]);
  place.forEach(([draw, pu, pv]) => draw(pu, pv));
};
// A corner of the table, on a grid square to the objects (their own plan,
// mm) with one gap (GRID) throughout: the cassette and the MiniDiscs side
// by side on one row, the reel on the row behind with its right edge on
// the MiniDiscs', the cards on the row in front with their left edge on
// the cassette's. Drawn far to near, so nearer ones cover farther.
// STILL_PLACES in the environment tries other places ([x, y] each).
const GRID = 12;
const CAS = [100.4 / 2, 63.8 / 2], MD = [36, 34], REEL = 63.5;
// The cards' block (cards() offsets and sizes): x -34 to 35, y -35 to 39.
const CARDS = { x0: -34, y0: -35 };
const PLACES = JSON.parse(process.env.STILL_PLACES || 'null') || [
  [CAS[0] + GRID + MD[0] * 2 - REEL, -CAS[1] - GRID - REEL],
  [0, 0],
  [CAS[0] + GRID + MD[0], 0],
  [-CAS[0] - CARDS.x0, CAS[1] + GRID - CARDS.y0],
];
// A place in the plan, on the screen's terms (across, toward the viewer).
const onScreen = ([x, y]) => [x * Math.cos(TH) - y * Math.sin(TH), x * Math.sin(TH) + y * Math.cos(TH)];
const items = [reel, cassette, stack, cards];
items.map((draw, i) => [draw, ...onScreen(PLACES[i])]).sort((p, q) => p[2] - q[2]).forEach(([draw, u, v]) => draw(u, v));

// ---- Output. ------------------------------------------------------------------
// Lines of one kind in a row merge into one path: one color, drawn in the
// same place in the order, so nothing changes but the size.
const MERGE = /^<path class="(e|m|pf-still__gold|pf-still__disc)" d="([^"]*)"\/>$/;
const body = svg.reduce((acc, el) => {
  const m = el.match(MERGE), last = acc.length && acc[acc.length - 1].match(MERGE);
  if (m && last && m[1] === last[1]) acc[acc.length - 1] = `<path class="${m[1]}" d="${last[2]}${m[2]}"/>`;
  else acc.push(el);
  return acc;
}, []).join('\n');
const pad = 6;
const vb = [bbox[0] - pad, bbox[1] - pad, bbox[2] - bbox[0] + pad * 2, bbox[3] - bbox[1] + pad * 2];
const head = `{#
/**
 * @file
 * The formats the Center's interviews were recorded on, on a corner of a
 * table, each square to the rest: a 5-inch tape reel at the back, a
 * cassette before it, two clear MiniDiscs squared one on the other and the
 * digital recorders' memory cards (CompactFlash, Memory Stick Pro Duo, SD,
 * microSD) nearest. A hairline drawing in true perspective from the field
 * recorder's camera (recorder-line.html.twig), drawn behind Oral History's
 * Quick Start as the recorder is behind its Record tab.
 *
 * Generated by build/still-life.js (edit that, then run it); styles in
 * scss/still-life.scss (library palefire/still-life, attached here).
 *
 * Decorative (aria-hidden): the collection is digitized, so the drawing
 * names nothing a visitor can ask for.
 *
 * Variables:
 * - id: a prefix for its clip paths, when more than one is on a page
 *   (default "pf-still").
 */
#}
{{ attach_library('palefire/still-life') }}
{% set id = id|default('pf-still') %}
`;
fs.writeFileSync(out, `${head}<svg class="pf-still" viewBox="${vb.map(f1).join(' ')}" width="${Math.round(vb[2] * 2)}" height="${Math.round(vb[3] * 2)}" aria-hidden="true" focusable="false">\n${body}\n</svg>\n`);
console.log('wrote', out, 'viewBox', vb.map(f1).join(' '), 'bytes', fs.statSync(out).size);
