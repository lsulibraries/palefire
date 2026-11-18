/**
 * Draws templates/includes/recorder-mark.html.twig: a Sony PCM-D10 field
 * recorder, the cassette mark's companion (cassette-mark.html.twig), as a
 * solid in perspective. It lies on its back with the XLR end toward the
 * viewer, lower left, and the microphones away, upper right, so the face,
 * the bottom end (two combo jacks) and the right side (line-in jack, input
 * dial, attenuator, card slot, USB) show.
 *
 * Modeled in millimeters from the published size (72.3 wide without the dial, 37.4 deep) and
 * the straight-on product photograph. The body is its plan outline (handle
 * with the microphone opening) extruded through its depth with 3 mm fillets
 * on the face and back edges; keys, bezel, dial, collars and latches are
 * extrusions and cylinders; switches and sockets are recesses. Every face is
 * a polygon on shared corners, drawn only when it faces the eye, far faces
 * first, toned by which way it faces (face 9%, right side 17%, end 25% of
 * the text color over the page, blended across the fillets). Outlines: the
 * silhouette, the fillets' edges and the parts' rims, as solid tones (sN).
 * Flat print (labels, the display's readout) is mapped onto its face by the
 * projection's local affine at that spot.
 *
 * Colors and motion are classes styled in scss/recorder.scss. Parts that
 * move while it records: the two level meters (a cover sliding off a
 * segmented bar), their peak ticks, the -20/-12 dB lamps, the REC lamp and
 * the counter's seconds (two digit columns stepping behind a clip).
 *
 * Usage: node build/recorder-mark.js [out]
 */
const fs = require('fs');
const path = require('path');
// --line draws the hairline version (recorder-line.html.twig): no tones,
// every face the page color, and only the edges a pen would draw: creases
// where two faces meet at an angle and outlines where a surface turns away.
const LINE = process.argv.includes('--line');
const args = process.argv.slice(2).filter(a => a !== '--line');
const out = args[0] || path.join(__dirname, '../templates/includes/' + (LINE ? 'recorder-line' : 'recorder-mark') + '.html.twig');

// ---- Camera. ---------------------------------------------------------------
const RAD = Math.PI / 180;
const TH = 50 * RAD, PH = 44 * RAD, D = 620;
const W = 72.3, L = 185.5, H = 37.4;
const CEN = [W / 2, L / 2, H / 2];
// The crown: the face and the back bow across the width (the face, the
// display's lens and its keys with it), falling away toward the long edges
// through the roll of the sides; the middle of each side stays flat, so the
// dial and jacks there keep their true shapes.
const SAG = 2.6, RZC = 12.5, UF = 0.55;
const crownW = z => z > H - RZC ? -(Math.min(1, (z - H + RZC) / RZC) ** 2) : z < RZC ? Math.min(1, (RZC - z) / RZC) ** 2 : 0;
// Flat across the middle (the screen's readout area), bowing beyond it.
const warp = ([x, y, z]) => { const u = Math.max(0, (Math.min(1, Math.abs(2 * x / W - 1)) - UF) / (1 - UF)); return [x, y, z + SAG * u * u * crownW(z)]; };
const raw = p => {
  const [x, y, z] = warp(p);
  const X = x - CEN[0], Y = y - CEN[1], Z = z - CEN[2];
  const u = X * Math.cos(TH) - Y * Math.sin(TH);
  const v = X * Math.sin(TH) + Y * Math.cos(TH);
  const sy = v * Math.sin(PH) - Z * Math.cos(PH);
  const dep = v * Math.cos(PH) + Z * Math.sin(PH);
  const s = D / (D - dep);
  return [u * s, sy * s];
};
const CAM = [CEN[0] + D * Math.cos(PH) * Math.sin(TH), CEN[1] + D * Math.cos(PH) * Math.cos(TH), CEN[2] + D * Math.sin(PH)];

// ---- Vectors. ---------------------------------------------------------------
const add = (a, b) => [a[0] + b[0], a[1] + b[1], a[2] + b[2]];
const sub = (a, b) => [a[0] - b[0], a[1] - b[1], a[2] - b[2]];
const mul = (a, k) => [a[0] * k, a[1] * k, a[2] * k];
const dot = (a, b) => a[0] * b[0] + a[1] * b[1] + a[2] * b[2];
const cross = (a, b) => [a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0]];
const norm = a => mul(a, 1 / (Math.hypot(...a) || 1));
const cen = p => mul(p.reduce((t, q) => add(t, q), [0, 0, 0]), 1 / p.length);
const newell = p => {
  const n = [0, 0, 0];
  p.forEach((a, i) => {
    const b = p[(i + 1) % p.length];
    n[0] += (a[1] - b[1]) * (a[2] + b[2]);
    n[1] += (a[2] - b[2]) * (a[0] + b[0]);
    n[2] += (a[0] - b[0]) * (a[1] + b[1]);
  });
  return norm(n);
};
const facing = (n, c) => dot(n, sub(CAM, c)) > 1e-6;
const dist = c => Math.hypot(...sub(CAM, c));

// ---- Output helpers (the viewBox is fitted at the end). --------------------
const f1 = n => (Math.round(n * 10) / 10).toString();
const f2 = n => (Math.round(n * 100) / 100).toString();
const P2 = q => f1(q[0]) + ',' + f1(q[1]);
const dense = (p3, closed = true) => {
  const r = [], n = p3.length;
  for (let i = 0; i < n; i++) {
    const a = p3[i];
    r.push(a);
    if (!closed && i === n - 1) break;
    const b = p3[(i + 1) % n], k = Math.ceil(Math.abs(b[0] - a[0]) / 2.5);
    for (let j = 1; j < k; j++) r.push(add(a, mul(sub(b, a), j / k)));
  }
  return r;
};
const pts = (p3, closed = true) => dense(p3, closed).map(p => P2(raw(p))).join(' ');
const bbox = [Infinity, Infinity, -Infinity, -Infinity];
const grow = p3 => p3.forEach(p => { const [x, y] = raw(p); bbox[0] = Math.min(bbox[0], x); bbox[1] = Math.min(bbox[1], y); bbox[2] = Math.max(bbox[2], x); bbox[3] = Math.max(bbox[3], y); });
const poly = (p3, cls) => `<polygon class="${cls}" points="${pts(p3)}"/>`;
const line = (p3, cls) => `<polyline class="${cls}" points="${pts(p3, false)}"/>`;

// Tones: the text color at N% over the page, by which way a face faces.
const tone = (n, extra = 0) => {
  if (LINE) return 'f';
  const [x, y, z] = n;
  let t = 9 * z * z * (z > 0 ? 1 : 3) + (x > 0 ? 17 : 6) * x * x + (y > 0 ? 25 : 7) * y * y + extra;
  t = Math.max(2, Math.min(56, Math.round(t / 2) * 2));
  return 't' + t;
};

// A solid's faces, visible ones only, far first; runs of one tone share a
// path. Faces too thin to see are dropped.
const area2 = q => { let A = 0; q.forEach((p, i) => { const r = q[(i + 1) % q.length]; A += p[0] * r[1] - r[0] * p[1]; }); return Math.abs(A) / 2; };
const CREASE = Math.cos(28 * RAD);
const drawLine = faces => {
  const key = p => p.map(v => Math.round(v * 1000)).join(',');
  const all = faces.map(f => {
    const q = f.p.map(warp);
    let n = newell(q);
    if (!Number.isFinite(n[0]) || Math.hypot(...n) < 0.5) n = f.n; else if (dot(n, f.n) < 0) n = mul(n, -1);
    const c = cen(q);
    return { ...f, n, c, vis: facing(n, c) };
  });
  // Edges by their two corners, with the faces that share them.
  const edges = new Map();
  all.forEach((f, i) => f.p.forEach((a, k) => {
    const b = f.p[(k + 1) % f.p.length], ka = key(a), kb = key(b);
    if (ka === kb) return;
    const id = ka < kb ? ka + '|' + kb : kb + '|' + ka;
    if (!edges.has(id)) edges.set(id, { a, b, fs: [] });
    const e = edges.get(id);
    if (!e.fs.includes(i)) e.fs.push(i);
  }));
  const order = all.map((f, i) => i).filter(i => all[i].vis).sort((x, y) => dist(all[y].c) - dist(all[x].c));
  const rank = new Map(order.map((i, r) => [i, r]));
  // An edge is drawn by the nearer of its visible faces, right after it, so
  // anything nearer still covers it.
  const owned = new Map();
  for (const e of edges.values()) {
    if (e.fs.length < 2) continue;
    const V = e.fs.filter(i => all[i].vis);
    if (!V.length) continue;
    const draw = V.length < e.fs.length || dot(all[e.fs[0]].n, all[e.fs[1]].n) < CREASE;
    if (!draw) continue;
    const owner = V.reduce((m, i) => (rank.get(i) > rank.get(m) ? i : m));
    if (!owned.has(owner)) owned.set(owner, []);
    owned.get(owner).push(e);
  }
  const runs = [];
  const add = (cls, d) => { if (runs.length && runs[runs.length - 1].cls === cls) runs[runs.length - 1].d += d; else runs.push({ cls, d }); };
  for (const i of order) {
    const f = all[i];
    if (area2(f.p.map(raw)) > 0.004) add(f.cls || 'f', 'M' + dense(f.p).map(p => P2(raw(p))).join('L') + 'Z');
    for (const e of owned.get(i) || []) add('e', 'M' + dense([e.a, e.b], false).map(p => P2(raw(p))).join('L'));
  }
  return runs.map(r => `<path class="${r.cls}" d="${r.d}"/>`).join('');
};
const draw = (faces, extra = 0) => {
  if (LINE) return drawLine(faces);
  const runs = [];
  faces
    .map(f => {
      const q = f.p.map(warp);
      let n = newell(q);
      if (!Number.isFinite(n[0]) || Math.hypot(...n) < 0.5) n = f.n; else if (dot(n, f.n) < 0) n = mul(n, -1);
      return { ...f, n, c: cen(q), c0: cen(f.p) };
    })
    .filter(f => facing(f.n, f.c) && area2(f.p.map(raw)) > 0.004)
    .sort((a, b) => dist(b.c) - dist(a.c))
    .forEach(f => {
      const cls = f.cls || tone(f.n, f.extra ?? extra), d = 'M' + dense(f.p).map(p => P2(raw(p))).join('L') + 'Z';
      if (runs.length && runs[runs.length - 1].cls === cls) runs[runs.length - 1].d += d; else runs.push({ cls, d });
    });
  return runs.map(r => `<path class="${r.cls}" d="${r.d}"/>`).join('');
};

// 2D outlines.
const arc = (cx, cy, r, a0, a1, n) => Array.from({ length: n + 1 }, (_, i) => { const a = (a0 + (a1 - a0) * i / n) * RAD; return [cx + r * Math.cos(a), cy + r * Math.sin(a)]; });
// A rounded rectangle (y down): radii top-left, top-right, bottom-right,
// bottom-left.
const rr = (x0, y0, x1, y1, r, n = 6) => {
  const [a, b, c, d] = Array.isArray(r) ? r : [r, r, r, r];
  return [
    ...arc(x0 + a, y0 + a, a, 180, 270, n),
    ...arc(x1 - b, y0 + b, b, 270, 360, n),
    ...arc(x1 - c, y1 - c, c, 0, 90, n),
    ...arc(x0 + d, y1 - d, d, 90, 180, n),
  ];
};
const circ = (cx, cy, r, n = 28) => Array.from({ length: n }, (_, i) => { const a = i / n * 2 * Math.PI; return [cx + r * Math.cos(a), cy + r * Math.sin(a)]; });

// Frames: a plane with an origin, two in-plane axes and its normal.
const frame = (o, u, v) => ({ o, u, v, n: norm(cross(u, v)) });
const at = (F, a, b, h = 0) => add(add(add(F.o, mul(F.u, a)), mul(F.v, b)), mul(F.n, h));
const on = (F, p2, h = 0) => p2.map(([a, b]) => at(F, a, b, h));
const FACE = frame([0, 0, H], [1, 0, 0], [0, 1, 0]);
const END = frame([0, L, H], [1, 0, 0], [0, 0, -1]);
const SIDE = frame([W, 0, 0], [0, 1, 0], [0, 0, 1]);

// An outline pushed out of its plane from h0 to h1: sides and cap, normals
// turned away from its axis.
const extrude = (F, p2, h0, h1, opt = {}) => {
  const A = on(F, p2, h0), B = on(F, p2, h1), mid = mul(add(cen(A), cen(B)), 0.5);
  const faces = [];
  for (let i = 0; i < p2.length; i++) {
    const j = (i + 1) % p2.length, p = [A[i], A[j], B[j], B[i]];
    let n = newell(p);
    if (dot(n, sub(cen(p), mid)) < 0) n = mul(n, -1);
    faces.push({ p, n, extra: opt.sideExtra ?? opt.extra ?? 0 });
  }
  faces.push({ p: B, n: F.n, extra: opt.extra ?? 0, cls: opt.capCls });
  return faces;
};
// A recess: its walls (seen from inside), floor and mouth.
const recess = (F, p2, depth, extra = 10) => {
  const A = on(F, p2, 0), B = on(F, p2, -depth), ax = cen(B);
  const walls = [];
  for (let i = 0; i < p2.length; i++) {
    const j = (i + 1) % p2.length, p = [A[i], A[j], B[j], B[i]];
    let n = newell(p);
    const c = cen(p), toAxis = sub(add(ax, mul(F.n, dot(sub(c, ax), F.n))), c);
    if (dot(n, toAxis) < 0) n = mul(n, -1);
    walls.push({ p, n, extra });
  }
  return { walls, floor: B, mouth: A };
};
// A solid of revolution along an axis: profile [[u, r], ...], with caps.
const lathe = (o, ax, profile, seg = 28, opt = {}) => {
  ax = norm(ax);
  const ref = Math.abs(ax[2]) < 0.9 ? [0, 0, 1] : [1, 0, 0];
  const e1 = norm(cross(ax, ref)), e2 = cross(ax, e1);
  const ring = (u, r) => Array.from({ length: seg }, (_, i) => { const a = (i + 0.5) / seg * 2 * Math.PI; return add(add(o, mul(ax, u)), add(mul(e1, r * Math.cos(a)), mul(e2, r * Math.sin(a)))); });
  const rings = profile.map(([u, r]) => ring(u, r));
  const faces = [];
  for (let k = 0; k < profile.length - 1; k++) {
    const [u0, r0] = profile[k], [u1, r1] = profile[k + 1];
    if (r0 < 1e-3 && r1 < 1e-3) continue;
    for (let i = 0; i < seg; i++) {
      const j = (i + 1) % seg;
      const p = [rings[k][i], rings[k][j], rings[k + 1][j], rings[k + 1][i]];
      const am = (i + 1) / seg * 2 * Math.PI;
      const radial = add(mul(e1, Math.cos(am)), mul(e2, Math.sin(am)));
      const step = Math.abs(u1 - u0) < 1e-6;
      const n = step ? mul(ax, r1 < r0 ? 1 : -1) : norm(add(radial, mul(ax, (r0 - r1) / (u1 - u0))));
      faces.push({ p, n, ...(opt.parts ? opt.parts(k, i) : {}) });
    }
  }
  const first = profile[0], last = profile[profile.length - 1];
  if (first[1] > 1e-3) faces.push({ p: rings[0].slice().reverse(), n: mul(ax, -1), ...(opt.cap0 || {}) });
  if (last[1] > 1e-3) faces.push({ p: rings[rings.length - 1], n: ax, ...(opt.cap1 || {}) });
  return { faces, rings, e1, e2, ax };
};
const hull2 = P => {
  P = P.slice().sort((a, b) => a[0] - b[0] || a[1] - b[1]);
  const cr = (o, a, b) => (a[0] - o[0]) * (b[1] - o[1]) - (a[1] - o[1]) * (b[0] - o[0]);
  const lo = [], up = [];
  for (const p of P) { while (lo.length > 1 && cr(lo[lo.length - 2], lo[lo.length - 1], p) <= 0) lo.pop(); lo.push(p); }
  for (const p of P.slice().reverse()) { while (up.length > 1 && cr(up[up.length - 2], up[up.length - 1], p) <= 0) up.pop(); up.push(p); }
  lo.pop(); up.pop();
  return lo.concat(up);
};
const outline = (p3, cls) => `<polygon class="${cls}" points="${hull2(p3.map(raw)).map(P2).join(' ')}"/>`;
// Convex polygon clipping (Sutherland-Hodgman) on the screen.
const clip2 = (subj, clipP) => {
  let A = 0;
  clipP.forEach((p, i) => { const q = clipP[(i + 1) % clipP.length]; A += p[0] * q[1] - q[0] * p[1]; });
  const C = A > 0 ? clipP : clipP.slice().reverse();
  const inside = (p, a, b) => (b[0] - a[0]) * (p[1] - a[1]) - (b[1] - a[1]) * (p[0] - a[0]) >= 0;
  let res = subj;
  for (let i = 0; i < C.length && res.length; i++) {
    const a = C[i], b = C[(i + 1) % C.length], inp = res;
    res = [];
    for (let k = 0; k < inp.length; k++) {
      const p = inp[k], q = inp[(k + 1) % inp.length], pi = inside(p, a, b), qi = inside(q, a, b);
      const X = () => { const d1 = [q[0] - p[0], q[1] - p[1]], d2 = [b[0] - a[0], b[1] - a[1]]; const t = ((a[0] - p[0]) * d2[1] - (a[1] - p[1]) * d2[0]) / (d1[0] * d2[1] - d1[1] * d2[0]); return [p[0] + t * d1[0], p[1] + t * d1[1]]; };
      if (pi) { res.push(p); if (!qi) res.push(X()); } else if (qi) res.push(X());
    }
  }
  return res;
};
// The projection's affine at a point of a frame: maps the frame's (a, b)
// onto the screen exactly there.
const affine = (F, a, b, h = 0) => {
  const e = 0.5, p = raw(at(F, a, b, h));
  const pa = raw(at(F, a + e, b, h)), pa0 = raw(at(F, a - e, b, h));
  const pb = raw(at(F, a, b + e, h)), pb0 = raw(at(F, a, b - e, h));
  const A = [(pa[0] - pa0[0]) / (2 * e), (pa[1] - pa0[1]) / (2 * e)];
  const B = [(pb[0] - pb0[0]) / (2 * e), (pb[1] - pb0[1]) / (2 * e)];
  return [A[0], A[1], B[0], B[1], p[0] - A[0] * a - B[0] * b, p[1] - A[1] * a - B[1] * b];
};
const mat = m => `matrix(${m.map(v => (Math.round(v * 1000) / 1000).toString()).join(' ')})`;
const flat = (F, a, b, h, inner) => `<g transform="${mat(affine(F, a, b, h))}">${inner}</g>`;
const T = (cls, x, y, s, anchor) => `<text class="pf-recorder__${cls}" x="${f2(x)}" y="${f2(y)}"${anchor === 'end' ? ' text-anchor="end"' : anchor === 'start' ? '' : ' text-anchor="middle"'}>${s}</text>`;
const label = (F, a, b, h, s, cls = 'micro', anchor) => flat(F, a, b, h, T(cls, a, b, s, anchor));

const o = [];
const id = '{{ id }}';

// ---- Shadow on the table: the footprint, softened in three steps. ----------
for (const g of [4.5, 2.6, 1]) {
  if (!LINE) o.push(poly(rr(-g, -g, W + g, L + g, [12 + g, 12 + g, 5 + g, 5 + g], 6).map(([x, y]) => [x + 1.5, y + 1, 0]), 'pf-recorder__shadow'));
}

// ---- The body. -------------------------------------------------------------
// Plan: x across the face, y down its length, z out of the face. Outer
// outline with the handle's rounded top; the microphone opening through it.
// Layers [z, inset] through the back fillet, the flat side, the face fillet.
// The sides roll from face to back in one curve, nearly half round as in
// Sony's end-on photograph: a quarter ellipse 9 across and half the depth
// deep at each edge, so the side is flat only along its middle line.
const RZ = H / 2, RX = 9, RH = 1.4, FS = 9;
const fillet = (rz, rx, n) => {
  const l = [];
  for (let i = 0; i <= n; i++) { const t = (i / n) * 90 * RAD; l.push([rz - rz * Math.cos(t), rx - rx * Math.sin(t)]); }
  for (let i = n; i >= 0; i--) { const t = (i / n) * 90 * RAD; l.push([H - rz + rz * Math.cos(t), rx - rx * Math.sin(t)]); }
  return l;
};
const lay = fillet(RZ, RX, FS), hlay = fillet(RH, RH, 3);
// The ends roll less: a quarter of the sides' inset.
const OUT = d => { const e = d / 4; return rr(d, e, W - d, L - e, [12 - e, 12 - e, 2.2 - e, 2.2 - e], 7); };
const HX0 = 6, HX1 = W - 6, HY0 = 3.6, HY1 = 22.6;
const HOLE = d => rr(HX0 - d, HY0 - d, HX1 + d, HY1 + d, [7 + d, 7 + d, 3 + d, 3 + d], 6);

const ringsOf = (lays, fn) => lays.map(([z, d]) => fn(d).map(([x, y]) => [x, y, z]));
const bandFaces = (rings, inward, ref) => {
  const faces = [];
  for (let k = 0; k < rings.length - 1; k++) {
    const A = rings[k], B = rings[k + 1];
    for (let i = 0; i < A.length; i++) {
      const j = (i + 1) % A.length, p = [A[i], A[j], B[j], B[i]];
      let n = newell(p);
      const c = cen(p), r = inward ? [ref[0], ref[1], c[2]] : ref;
      if ((dot(n, sub(c, r)) < 0) !== !!inward) n = mul(n, -1);
      faces.push({ p, n, k, i });
    }
  }
  return faces;
};
const outerR = ringsOf(lay, OUT), holeR = ringsOf(hlay, HOLE);
const outerF = bandFaces(outerR, false, CEN);
const holeF = bandFaces(holeR, true, [W / 2, (HY0 + HY1) / 2, 0]);
grow(outerR.flat());

// Through the opening: where the page shows (the front and back mouths
// overlapping), then the opening's walls that face the eye.
const mouthF = dense(HOLE(0).map(([x, y]) => [x, y, H])).map(raw), mouthB = dense(HOLE(0).map(([x, y]) => [x, y, 0])).map(raw);
const through = clip2(mouthF, mouthB).map(P2).join(' ');
o.push(`<polygon class="pf-recorder__through" points="${through}"/>`);
o.push(draw(holeF, 8));

// The microphones: two short capsules, X-Y, side by side, each angled 45°
// up and in so their ends meet over the middle. The whole side is
// perforated; the end is a gold rim round a dark mesh disc. Their pivots sit
// inside the body below the opening.
const capsule = (base, dir, z) => {
  const o3 = [base[0], base[1], z];
  const prof = [[0, 4.4], [2, 4.4], [2, 7], [14.4, 7], [14.4, 7.15], [16, 7.15], [16, 5.5], [16, 0]];
  const C3 = lathe(o3, dir, prof, 24, { parts: k => (k >= 3 && k <= 5 ? { gold: true } : k === 6 || k < 2 ? { cls: 'pf-recorder__well' } : { extra: 4 }) });
  C3.faces.forEach(fc => { if (fc.gold) fc.cls = 'pf-recorder__gold g' + Math.min(3, 1 + Math.round(2 * (1 - Math.max(0, dot(norm(sub(CAM, cen(fc.p))), fc.n))))); });
  let s = draw(C3.faces);
  // Perforations: rows of holes round the side, and across the end disc.
  const d = [];
  const dotAt = (p, n) => { const v = sub(CAM, p); if (dot(n, v) > 0.3 * Math.hypot(...v)) { const q = raw(p); d.push(`M${f1(q[0])} ${f1(q[1])}h0`); } };
  for (let u = 3, row = 0; u <= 13.6; u += 0.95, row++) {
    for (let i = 0; i < 30; i++) {
      const a = (i + (row % 2) * 0.5) / 30 * 2 * Math.PI;
      const rad = add(mul(C3.e1, Math.cos(a)), mul(C3.e2, Math.sin(a)));
      dotAt(add(add(o3, mul(C3.ax, u)), mul(rad, 7.02)), rad);
    }
  }
  for (let x = -4.4; x <= 4.4; x += 1.1) {
    for (let y = -4.4; y <= 4.4; y += 1.1) {
      if (x * x + y * y < 4.6 * 4.6) dotAt(add(add(o3, mul(C3.ax, 16.02)), add(mul(C3.e1, x), mul(C3.e2, y))), C3.ax);
    }
  }
  s += `<path class="pf-recorder__mesh" d="${d.join('')}"/>`;
  return s + outline(C3.faces.flatMap(fc => fc.p), 's55');
};
o.push(capsule([16.4, 21.4], norm([0.707, -0.707, 0]), 27.6), capsule([W - 16.4, 21.4], norm([-0.707, -0.707, 0]), 28));

// The outside: walls that face the eye, then the face with its opening.
o.push(draw(outerF, 0));
const capOuter = OUT(RX).map(([x, y]) => [x, y, H]), capHole = HOLE(RH).map(([x, y]) => [x, y, H]);
o.push(`<path class="${tone([0, 0, 1])}" fill-rule="evenodd" d="M${pts(capOuter).replace(/ /g, 'L')}ZM${pts(capHole).replace(/ /g, 'L')}Z"/>`);
// Edges: where the face fillet meets the side, the face's edge, the
// opening's rims, the silhouette.
const visRing = (rings, faces, k, cls) => {
  const R = rings[k], segs = [];
  let cur = [];
  for (let i = 0; i < R.length; i++) {
    const vis = faces.some(fc => (fc.k === k || fc.k === k - 1) && fc.i === i && facing(fc.n, cen(fc.p)));
    if (vis) { if (!cur.length) cur.push(R[i]); cur.push(R[(i + 1) % R.length]); } else if (cur.length) { segs.push(cur); cur = []; }
  }
  if (cur.length) segs.push(cur);
  return segs.map(s => line(s, cls)).join('');
};
o.push(visRing(outerR, outerF, FS + 1, 's22'));
o.push(`<polygon class="s30" points="${pts(capOuter)}"/>`);
o.push(`<polygon class="s40" points="${pts(capHole)}"/>`);
o.push(visRing(holeR, holeF, 4, 's30'));
o.push(outline(outerR.flatMap(r => dense(r)), 's70'));
o.push(`<polygon class="s45" points="${through}"/>`);

// ---- The right side. --------------------------------------------------------
// SIDE frame: a = y down the length, b = z from the back, h out of it.
// Line-in jack in a raised collar.
o.push(draw(extrude(SIDE, circ(23.5, H / 2, 4.2, 20), 0, 0.9)), outline(on(SIDE, circ(23.5, H / 2, 4.2, 20), 0.9), 's50'));
o.push(poly(on(SIDE, circ(23.5, H / 2, 2.4, 18), 0.9), 'pf-recorder__hole'));
// The input level dial: two numbered rings (L inner, R outer) between
// knurled bands, a knurled cap with a plain face. It sits in a round pocket
// of a housing molded out of the side: a heightfield over the side, a hood
// above the dial and a guard below joined by a thin rim, its cross-section
// a rounded bump that runs back into the body's curve, tapering long toward
// the bottom end. The red index is on the face's edge beside it.
const DA = 47, DB = H / 2, PR = 11.5;
const sm = t => { t = Math.max(0, Math.min(1, t)); return t * t * (3 - 2 * t); };
// The body's own side, as a height off the side plane (negative on its
// curves), so the housing rises straight off it and meets it exactly.
const bodyH = b => {
  const z = Math.min(b, H - b);
  if (z >= RZ) return 0;
  const c = 1 - Math.max(0, z) / RZ;
  return -RX * (1 - Math.sqrt(Math.max(0, 1 - c * c)));
};
// The housing as rings around the dial's pocket, so its facets follow
// the molding's contours: a long tail toward the jacks, a short hood
// toward the mics, rolling off into the body's curved side.
const HT = 7.2, HRB = 13;
const hR = t => { const c = Math.cos(t), sn = Math.sin(t), ra = c > 0 ? 20.5 : 17; return 1 / Math.sqrt((c / ra) ** 2 + (sn / HRB) ** 2); };
const hS = (s, b) => { const base = bodyH(b); return base + (HT - base) * sm((1 - s) / 0.85); };
const hNo = (a, b) => {
  const d = Math.hypot(a - DA, b - DB), t = Math.atan2(b - DB, a - DA), R = hR(t);
  if (d >= R) return bodyH(b);
  return hS(Math.max(0, (d - PR) / (R - PR)), b);
};
const housingF = [];
{
  const NS = 12, NT = 72;
  const node = (i, k) => {
    const t = k / NT * 2 * Math.PI, sv = i / NS, r = PR + (hR(t) - PR) * sv;
    const a = DA + r * Math.cos(t), b = DB + r * Math.sin(t);
    return at(SIDE, a, b, i === NS ? bodyH(b) : hS(sv, b));
  };
  for (let i = 0; i < NS; i++) {
    for (let k = 0; k < NT; k++) {
      const p = [node(i, k), node(i + 1, k), node(i + 1, k + 1), node(i, k + 1)];
      let n = newell(p);
      if (dot(n, SIDE.n) < 0) n = mul(n, -1);
      housingF.push({ p, n, extra: 4 });
    }
  }
  // The pocket's wall, from the rim down to its floor.
  const M = 48;
  const rim = k => { const t = k / M * 2 * Math.PI, a = DA + PR * Math.cos(t), b = DB + PR * Math.sin(t); return [at(SIDE, a, b, hS(0, b)), at(SIDE, a, b, 0.6)]; };
  for (let k = 0; k < M; k++) {
    const [t0, b0] = rim(k), [t1, b1] = rim(k + 1), p = [t0, t1, b1, b0];
    let n = newell(p);
    const c = cen(p), toC = sub(at(SIDE, DA, DB, dot(sub(c, SIDE.o), SIDE.n)), c);
    if (dot(n, toC) < 0) n = mul(n, -1);
    housingF.push({ p, n, extra: 12 });
  }
}
const dialO = at(SIDE, DA, DB, 0.6);
const dprof = [[0, 10.1], [1.4, 10.1], [1.4, 10.8], [3.6, 10.8], [3.6, 10.2], [4, 10.2], [4, 10.8], [6.2, 10.8], [6.2, 10.3], [7.2, 10.3], [7.2, 9.7], [7.8, 9.4]];
const knurl = k => k === 0 || k === 8;
const dial = lathe(dialO, SIDE.n, dprof, 48, { parts: (k, i) => ({ extra: knurl(k) ? (i % 2 ? 16 : 4) : 6 }), cap1: { extra: 2 } });
o.push(poly(on(SIDE, circ(DA, DB, PR, 36), 0.6), 'pf-recorder__hole'));
o.push(draw([...housingF, ...dial.faces]));
o.push(outline(dial.faces.flatMap(fc => fc.p), 's60'));
for (const k of [3, 6]) o.push(`<polygon class="s30" points="${pts(dial.rings[k])}"/>`);
o.push(`<polygon class="s25" points="${pts(on(frame(add(dialO, mul(SIDE.n, 7.8)), dial.e1, dial.e2), circ(0, 0, 6.6, 40)))}"/>`);
grow(dial.faces.flatMap(fc => fc.p));
// The scales: 0-10 round each ring, upright along the rim, the R ring two
// steps ahead of the L.
{
  const nums = [];
  for (const [u, off] of [[2.5, 0], [5.1, 2]]) {
    for (let k = 0; k <= 10; k++) {
      const th = (-128 - (k - 5 - off) * 27) * RAD;
      const rad = add(mul(dial.e1, Math.cos(th)), mul(dial.e2, Math.sin(th)));
      const tan = add(mul(dial.e1, -Math.sin(th)), mul(dial.e2, Math.cos(th)));
      const p = add(add(dialO, mul(dial.ax, u)), mul(rad, 10.82));
      const v = sub(CAM, p);
      if (dot(rad, v) < 0.25 * Math.hypot(...v)) continue;
      const pa = p[1];
      if (pa > DA + 7 && dot(sub(p, SIDE.o), SIDE.n) < hNo(pa, p[2])) continue;
      nums.push(flat({ o: p, u: dial.ax, v: mul(tan, -1), n: rad }, 0, 0, 0, T('dialnum', 0, 0.62, String(k))));
    }
  }
  o.push(nums.join(''));
}
o.push(poly(on(FACE, rr(W - 4.6, DA - 0.5, W - 2.6, DA + 0.5, 0.4, 2), 0.02), 'pf-recorder__rec'));
// Attenuator switch, card slot cover, USB.
const att = recess(SIDE, rr(68, H / 2 - 4.6, 78, H / 2 + 4.6, 1.2, 2), 1);
o.push(poly(att.floor, 'pf-recorder__hole'), draw(att.walls), draw(extrude(SIDE, rr(69.2, H / 2 - 3.4, 73.4, H / 2 + 3.4, 0.8, 2), -1, 0.4)));
o.push(flat(SIDE, 73, 13.4, 0, `<g transform="rotate(180 73 13)">${T('micro', 73, 13.4, 'MIC ATT')}</g>`));
const sd = rr(92, 12.8, 128, H - 12.8, 2.4, 4);
o.push(draw(extrude(SIDE, sd, 0, 0.5)), `<polygon class="s40" points="${pts(on(SIDE, sd, 0.5))}"/>`);
o.push(line(on(SIDE, [[95.4, 14.6], [95.4, H - 14.6]], 0.5), 's25'), flat(SIDE, 110, 21, 0.5, `<g transform="rotate(180 110 20.6)">${T('micro', 110, 21, 'SD')}</g>`));
const usb = recess(SIDE, rr(142, H / 2 - 2.2, 150, H / 2 + 2.2, 1.2, 2), 2.2);
o.push(poly(usb.floor, 'pf-recorder__hole'), draw(usb.walls, 14));

// ---- The bottom end. -------------------------------------------------------
// END frame: a = x across, b = depth from the face, h out of the end.
// The guards: the jack panel sits back inside the body's rim, and the two
// rounded sides stand a little proud of it, either side of the jacks: low
// fins the full depth of the side, rounded off at face and back, each one
// following the roll (and crown) of its side.
const FIN_T = 2, FIN_O = 1.6, FIN_Y = L - 4, FN = 24;
const fin = right => {
  const arc = [];
  for (let i = 0; i <= FN; i++) {
    const z = 1.2 + (H - 2.4) * i / FN, t = 2 * (z - 1.2) / (H - 2.4) - 1;
    const x0 = right ? W + bodyH(z) : -bodyH(z), x1 = right ? x0 - FIN_T : x0 + FIN_T;
    const y = L + FIN_O * Math.sqrt(Math.max(0, 1 - t ** 8));
    arc.push({ z, x0, x1, y: Math.max(FIN_Y, y) });
  }
  const faces = [], ctr = [right ? W - FIN_T / 2 : FIN_T / 2, L + 2, H / 2];
  const push = p => { let n = newell(p); const c = cen(p); if (dot(n, sub(c, ctr)) < 0) n = mul(n, -1); faces.push({ p, n }); };
  for (let i = 0; i < FN; i++) {
    const P = arc[i], Q = arc[i + 1];
    push([[P.x0, FIN_Y, P.z], [P.x0, P.y, P.z], [Q.x0, Q.y, Q.z], [Q.x0, FIN_Y, Q.z]]);
    push([[P.x1, FIN_Y, P.z], [P.x1, P.y, P.z], [Q.x1, Q.y, Q.z], [Q.x1, FIN_Y, Q.z]]);
    push([[P.x0, P.y, P.z], [P.x1, P.y, P.z], [Q.x1, Q.y, Q.z], [Q.x0, Q.y, Q.z]]);
  }
  const outer = arc.map(p => [p.x0, p.y, p.z]), inner = arc.map(p => [p.x1, p.y, p.z]);
  grow(outer);
  return draw(faces, 0) + line(right ? outer : inner, 's55') + line(right ? inner : outer, 's30');
};
o.push(fin(false));
for (const cx of [18.6, W - 18.6]) {
  const flange = lathe(at(END, cx, H / 2, 0), END.n, [[0, 10.4], [0.8, 10.4]], 36);
  o.push(draw(flange.faces, -2), outline(flange.faces.flatMap(fc => fc.p), 's55'));
  // The socket: its mouth, its walls, the insert at the bottom (the jack's
  // hole and the three XLR pins' holes), seen through the mouth.
  const c0 = at(END, cx, H / 2, 0.8);
  const mouth = flange.rings[1].map(p => add(p, mul(sub(p, c0), -0.19)));
  const mouth2 = mouth.map(raw);
  o.push(`<polygon class="pf-recorder__hole" points="${mouth2.map(P2).join(" ")}"/>`);
  o.push(`<polygon class="${LINE ? "pf-recorder__hole" : "t34"}" points="${clip2(mouth.map(p => raw(add(p, mul(END.n, -1.8)))), mouth2).map(P2).join(' ')}"/>`);
  for (const [a, b, r] of [[cx, H / 2, 3.3], [cx - 3.8, H / 2 - 5.2, 0.8], [cx + 3.8, H / 2 - 5.2, 0.8], [cx, H / 2 + 6.2, 0.8]]) {
    const q = clip2(circ(a, b, r, 18).map(([x, y]) => raw(at(END, x, y, -1))), mouth2);
    if (q.length) o.push(`<polygon class="pf-recorder__hole" points="${q.map(P2).join(' ')}"/>`);
  }
  o.push(`<polygon class="s55" points="${mouth2.map(P2).join(' ')}"/>`);
  // The latch: PUSH, a tab above the socket.
  o.push(draw(extrude(END, rr(cx - 2.6, 6.6, cx + 2.6, 9.4, 0.8, 2), 0.8, 1.6)), label(END, cx, 5.6, 0.8, 'PUSH'));
}
const slot = recess(END, rr(W / 2 - 3, 25, W / 2 + 3, 30.4, 1.2, 2), 1.6);
o.push(poly(slot.floor, 'pf-recorder__hole'), draw(slot.walls));
o.push(fin(true));


// ---- The face. -------------------------------------------------------------
// FACE frame: a = x, b = y, h above the face.
const lamps = [[13, 'grn pf-recorder__lamp--l'], [17.6, 'amb pf-recorder__lamp--l'], [22.2, ''], [W - 22.2, 'grn pf-recorder__lamp--r'], [W - 17.6, 'amb pf-recorder__lamp--r'], [W - 13, '']];
for (const [a, c] of lamps) o.push(flat(FACE, a, 27, 0, `<circle class="pf-recorder__lamp${c ? ' pf-recorder__lamp--' + c : ''}" cx="${f2(a)}" cy="27" r=".85"/>`));
o.push(label(FACE, 9.6, 27.5, 0, 'L', 'tiny'), label(FACE, W - 9.6, 27.5, 0, 'R', 'tiny'));
o.push(label(FACE, 17.6, 30, 0, '-20 -12 OVER'), label(FACE, W - 17.6, 30, 0, '-20 -12 OVER'));
// The maker's mark: a wide, even-stroked bold serif, stretched to its
// proportions.
o.push(flat(FACE, W / 2, 29.3, 0, `<g transform="translate(${f2(W / 2)} 29.3) scale(1.22 1) translate(${f2(-W / 2)} -29.3)">${T('maker', W / 2, 29.3, 'SONY')}</g>`));

// The display: a raised bezel, the screen, the readout (a flat 104 x 82
// layout scaled onto the screen).
const bez = rr(7, 33.4, W - 7, 91.4, 2.2, 4);
o.push(draw(extrude(FACE, bez, 0, 0.7, { capCls: 'pf-recorder__bezel', sideExtra: 6 })), `<polygon class="s50" points="${pts(on(FACE, bez, 0.7))}"/>`);
const SX = 8.2, SY = 36.2, SK = 0.537;
o.push(poly(on(FACE, rr(SX, SY, SX + 104 * SK, SY + 82 * SK, 0.8, 2), 0.7), 'pf-recorder__screen'));
o.push(label(FACE, W / 2, 89.4, 0.7, 'LINEAR PCM RECORDER  PCM-D10'));
// The readout, laid out flat (x 23-127, y 76-168) and set onto the screen,
// which is flat in the middle and curves at the sides. Three strips: the
// flat middle and each curved side, each with its own affine (the tangent
// plane at its middle). Text and the moving meters are drawn once, on the
// strip they mostly sit in (an item's x picks it), so nothing is cut at a
// seam. Lines that cross a seam (the rules, the meter tracks, the REC
// badge) are projected point by point instead (exact), so they run
// unbroken over the curve.
{
  const S = [SK, 0, 0, SK, SX - 23 * SK, SY - 81 * SK];
  const XF0 = W / 2 * (1 - UF), XF1 = W / 2 * (1 + UF), toL = x => 23 + (x - SX) / SK;
  const edges = [23, toL(XF0), toL(XF1), 127];
  const mats = [0, 1, 2].map((k) => {
    const x0 = edges[k], x1 = edges[k + 1];
    const m = affine(FACE, SX + ((x0 + x1) / 2 - 23) * SK, SY + 41 * SK, 0.7);
    return [m[0] * S[0] + m[2] * S[1], m[1] * S[0] + m[3] * S[1], m[0] * S[2] + m[2] * S[3], m[1] * S[2] + m[3] * S[3], m[0] * S[4] + m[2] * S[5] + m[4], m[1] * S[4] + m[3] * S[5] + m[5]];
  });
  const strip = x => (x < edges[1] ? 0 : x < edges[2] ? 1 : 2);
  const groups = [[], [], []];
  const put = (x, html) => groups[strip(x)].push(html);
  // Exact: a layout point onto the curved screen, 0.7 above the face.
  const px = ([x, y]) => raw(at(FACE, SX + (x - 23) * SK, SY + (y - 81) * SK, 0.7));
  const seg = (p, q, n) => Array.from({ length: n + 1 }, (_, i) => [p[0] + (q[0] - p[0]) * i / n, p[1] + (q[1] - p[1]) * i / n]);
  const rrect = (x, y, w, h, r) => {
    const out = [], arc = (cx, cy, a0) => { for (let i = 0; i <= 4; i++) { const t = a0 + i * Math.PI / 8; out.push([cx + r * Math.cos(t), cy + r * Math.sin(t)]); } };
    out.push(...seg([x + r, y], [x + w - r, y], Math.ceil(w)));
    arc(x + w - r, y + r, -Math.PI / 2);
    out.push(...seg([x + w, y + r], [x + w, y + h - r], 2));
    arc(x + w - r, y + h - r, 0);
    out.push(...seg([x + w - r, y + h], [x + r, y + h], Math.ceil(w)));
    arc(x + r, y + h - r, Math.PI / 2);
    out.push(...seg([x, y + h - r], [x, y + r], 2));
    arc(x + r, y + r, Math.PI);
    return out;
  };
  const k = Math.sqrt(Math.abs(mats[1][0] * mats[1][3] - mats[1][1] * mats[1][2]));
  const sw = ` style="stroke-width:${f2(0.5 * k)}"`;
  const exact = [];
  const xline = (cls, p) => exact.push(`<polyline class="pf-recorder__${cls}"${sw} points="${p.map(px).map(P2).join(' ')}"/>`);
  const xshape = (cls, p, stroke = true) => exact.push(`<polygon class="pf-recorder__${cls}"${stroke ? sw : ''} points="${p.map(px).map(P2).join(' ')}"/>`);

  const TL = (cls, x, y, t, anchor = 'start') => T(cls, x, y, t, anchor);
  // Clip paths for the meters and the seconds, in the layout's units: each
  // used inside one strip's transform.
  const defs = `<defs><clipPath id="${id}-sec"><rect x="108.4" y="114" width="11" height="10.4"/></clipPath>`
    + `<clipPath id="${id}-ml"><rect x="31" y="85.6" width="61" height="3"/></clipPath>`
    + `<clipPath id="${id}-mr"><rect x="31" y="97.6" width="61" height="3"/></clipPath></defs>`;
  put(26, TL('lcd', 26, 89, 'L'));
  put(26, TL('lcd', 26, 101, 'R'));
  for (const [ch, y] of [['l', 85.6], ['r', 97.6]]) {
    put(61, `<g clip-path="url(#${id}-m${ch})"><path class="pf-recorder__seg" d="M31 ${f2(y + 1.5)}H92"/><rect class="pf-recorder__cover pf-recorder__cover--${ch}" x="31" y="${f2(y - 0.5)}" width="62" height="4"/><path class="pf-recorder__peak pf-recorder__peak--${ch}" d="M31.6 ${f2(y)}v3"/></g>`);
    xshape('track', rrect(31, y, 61, 3, 0.3));
  }
  put(32, TL('scale', 32, 95.2, '-60'));
  for (const [x, t] of [[46, '-40'], [60, '-20'], [70, '-12'], [80, '-6']]) put(x, T('scale', x, 95.2, t));
  put(90, TL('scale', 91.4, 95.2, '0', 'end'));
  put(118, TL('lcd', 124, 89, '-06dB', 'end'));
  put(118, TL('lcd', 124, 101, '-12dB', 'end'));
  for (const y of [105, 135, 148]) xline('rule', seg([25, y], [125, y], 50));
  xshape('badge', rrect(26, 115.2, 14, 8, 1), false);
  put(33, T('badge-text', 33, 121.4, 'REC'));
  // The counter (hours and minutes, the rolling seconds, "s") as one, so
  // its digits keep their spacing.
  const col = (cls, x, n) => `<g class="pf-recorder__digits pf-recorder__digits--${cls}">` + Array.from({ length: n }, (_, i) => T('count', x, 122.6 + 10.4 * i, String(i))).join('') + '</g>';
  put(100, TL('count', 108.6, 122.6, '0h12m', 'end') + `<g clip-path="url(#${id}-sec)">${col('tens', 111.3, 6)}${col('units', 116.5, 10)}</g>` + TL('lcd-s', 119.6, 122.6, 's'));
  put(118, TL('lcd', 124, 131, '-3h30m', 'end'));
  put(28, '<path class="pf-recorder__lcd-line" d="M26.4 139.4h2.6l1.2 1.2v3.8h-3.8z"/>');
  put(50, TL('lcd', 32, 144.2, '261118_0001.wav'));
  put(36, TL('lcd', 26, 154.4, 'LPCM 192/24'));
  put(28, TL('lcd', 26, 160.6, 'S/N'));
  put(117, '<rect class="pf-recorder__lcd-line" x="112" y="155.4" width="10" height="4.4" rx=".6"/><path class="pf-recorder__lcd-line" d="M122 156.6v2"/><path class="pf-recorder__lcd-fill" d="M113.4 156.6h4l-1.2 2h-2.8z"/>');
  o.push(`<g class="pf-recorder__lcd">${defs}${exact.join('')}${groups.map((g, i) => (g.length ? `<g transform="${mat(mats[i])}">${g.join('')}</g>` : '')).join('')}</g>`);
}
// The glass: a faint sheen across its upper left.
o.push(poly(on(FACE, [[SX + 1, SY + 1], [SX + 30, SY + 1], [SX + 12, SY + 30], [SX + 1, SY + 30]], 0.72), 'pf-recorder__sheen'));

// Keys: raised cylinders, a top and its side.
const key = (a, b, r, h = 1.3, glyph = '', lab = '') => {
  const K = lathe(at(FACE, a, b, 0), FACE.n, [[0, r], [h, r]], 22, { cap1: { extra: -2 } });
  let s = draw(K.faces, 4) + outline(K.faces.flatMap(fc => fc.p), 's60') + `<polygon class="s35" points="${pts(K.rings[1])}"/>`;
  if (glyph) s += flat(FACE, a, b, h, glyph);
  if (lab) s += label(FACE, a, b - r - 1.1, 0, lab);
  return s;
};
o.push(key(17.4, 99.6, 5.3, 1.3, '', 'T-MARK'));
o.push(key(28.6, 99.6, 5.3, 1.3, '<rect class="pf-recorder__glyph" x="27.4" y="98.4" width="2.4" height="2.4" rx=".3"/>', 'STOP'));
o.push(key(43, 99.6, 5.3, 1.3, '<path class="pf-recorder__glyph" d="M41.9 98.2h.9v2.8h-.9zM43.2 98.2h.9v2.8h-.9z"/>', 'REC PAUSE'));
o.push(key(54.6, 99.6, 5.3, 1.3, '<circle class="pf-recorder__rec" cx="54.6" cy="99.6" r="2"/><circle class="pf-recorder__rec-glow" cx="54.6" cy="99.6" r="2"/>'));
o.push(label(FACE, 54.6, 93.2, 0, 'REC', 'micro pf-recorder__micro--rec'));
o.push(key(15.6, 112.6, 4.4, 1.2, '', 'C1'), key(W - 15.6, 112.6, 4.4, 1.2, '', 'C2'));
// The cross keys: a ring with its arrows, the play key in its well.
const ring = lathe(at(FACE, W / 2, 122, 0), FACE.n, [[0, 12.4], [0.9, 12.4]], 40, { cap1: { extra: -2 } });
o.push(draw(ring.faces, 4), outline(ring.faces.flatMap(fc => fc.p), 's60'), `<polygon class="s35" points="${pts(ring.rings[1])}"/>`);
o.push(poly(on(FACE, circ(W / 2, 122, 6, 28), 0.9), 'pf-recorder__well'));
const cx2 = W / 2;
o.push(flat(FACE, cx2, 122, 0.9, `<path class="pf-recorder__glyph" d="M${f2(cx2)} 111.4l1.1 1.5h-2.2zM${f2(cx2)} 132.6l1.1 -1.5h-2.2zM${f2(cx2 - 10.6)} 122l1.5 -1.1v2.2zM${f2(cx2 - 8.8)} 122l1.5 -1.1v2.2zM${f2(cx2 + 10.6)} 122l-1.5 -1.1v2.2zM${f2(cx2 + 8.8)} 122l-1.5 -1.1v2.2z"/>`));
o.push(key(cx2, 122, 4.6, 1.9, `<path class="pf-recorder__glyph" d="M${f2(cx2 - 0.9)} 120.4L${f2(cx2 + 1.7)} 122L${f2(cx2 - 0.9)} 123.6Z"/>`));
o.push(label(FACE, cx2, 108.9, 0, 'DPC'), label(FACE, cx2, 136.6, 0, 'A-B'));
o.push(key(15.6, 133.4, 4.4, 1.2, '', '· BACK'), key(W - 15.6, 133.4, 4.4, 1.2, '', 'OPTION'), label(FACE, 15.6, 140, 0, '− HOME'));

// Input switches: recessed slots with raised knobs.
const slide = (a, b, up) => {
  const R = recess(FACE, rr(a - 2.1, b, a + 2.1, b + 7.6, 0.9, 2), 1.1);
  const ky = up ? b + 0.7 : b + 3.5, knob = rr(a - 1.5, ky, a + 1.5, ky + 3.4, 0.6, 2);
  return poly(R.floor, 'pf-recorder__hole') + draw(R.walls) + `<polygon class="s40" points="${pts(R.mouth)}"/>`
    + draw(extrude(FACE, knob, -1.1, 0.5)) + outline(on(FACE, knob, 0.5), 's55');
};
o.push(line(on(FACE, [[RX + 0.6, 143.2], [W - RX - 0.6, 143.2]]), 's22'));
for (const a of [15.6, W - 15.6]) {
  o.push(label(FACE, a, 146, 0, 'INPUT LEVEL'), slide(a, 148, true), slide(a, 159, false));
  o.push(label(FACE, a + 3.4, 150.4, 0, 'MIC', 'micro', 'start'), label(FACE, a + 3.4, 154.6, 0, 'LINE', 'micro', 'start'));
  o.push(label(FACE, a + 3.4, 161.4, 0, 'ON', 'micro', 'start'), label(FACE, a + 3.4, 165.6, 0, 'OFF', 'micro', 'start'));
  o.push(flat(FACE, a - 1.6, 169.4, 0, `<circle class="pf-recorder__lamp" cx="${f2(a - 1.6)}" cy="169.4" r=".6"/>`), label(FACE, a + 1.6, 170, 0, '+48V'));
}
o.push(label(FACE, cx2, 146, 0, 'INPUT'), label(FACE, cx2, 148.6, 0, 'MIC / LINE'), slide(cx2, 150, true), label(FACE, cx2, 161, 0, 'XLR / TRS'));
o.push(label(FACE, 15.6, 177, 0, 'L/MONO', 'label'), label(FACE, W - 15.6, 177, 0, 'R', 'label'));

// ---- Fit and write. --------------------------------------------------------
grow(rr(-4.5, -4.5, W + 4.5, L + 4.5, 16, 4).map(([x, y]) => [x + 1.5, y + 1, 0]));
const m = 2;
const vb = [bbox[0] - m, bbox[1] - m, bbox[2] - bbox[0] + 2 * m, bbox[3] - bbox[1] + 2 * m].map(f1);
const head = `{#
/**
 * @file
 * A Sony PCM-D10 field recorder, recording: the cassette mark's companion
 * (cassette-mark.html.twig), drawn as a solid in perspective, lying on its
 * back with the XLR end toward the viewer.${LINE ? `
 *
 * The hairline version, like the tape deck (images/exhibit-deck.svg): no
 * tones, 1px lines at the creases and outlines only (recorder-mark.html.twig
 * is the shaded one).` : ''}
 *
 * Generated by build/recorder-mark.js (edit that, then run it); styles and
 * motion in scss/recorder.scss (library palefire/recorder, attached here).
 * While motion is allowed it records in an endless loop: the level meters
 * move, the -20 and -12 dB lamps answer them, the REC lamp sends out a ring
 * and the counter's seconds tick. Reduced motion: the still drawing at
 * 0h12m34s.
 *
 * Decorative (aria-hidden): whatever it stands beside carries the words.
 *
 * Variables:
 * - id: a prefix for its clip paths, when more than one is on a page
 *   (default "pf-recorder").
 */
#}
{{ attach_library('palefire/recorder') }}
{% set id = id|default('pf-recorder') %}
<svg class="pf-recorder${LINE ? ' pf-recorder--line' : ''}" viewBox="${vb.join(' ')}" width="${Math.round(vb[2] * 2)}" height="${Math.round(vb[3] * 2)}" aria-hidden="true" focusable="false">
`;
fs.writeFileSync(out, head + o.join('\n') + '\n</svg>\n');
const used = new Set((o.join('').match(/class="[^"]*"/g) || []).join(' ').match(/\b[tsg]\d+\b/g));
console.log('wrote', (fs.statSync(out).size / 1024).toFixed(1) + ' kB', 'viewBox', vb.join(' '));
console.log('classes', [...used].sort((a, b) => a[0].localeCompare(b[0]) || a.slice(1) - b.slice(1)).join(' '));
