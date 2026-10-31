/**
 * Draws images/exhibit-deck.svg: the tape deck an oral history clip with no
 * picture plays on in the exhibition object view (js/exhibition.js
 * stageDeck(), styled in _exhibition.scss .pf-deck). Line art after a Tascam
 * Model 32, seen from 35° to the right: a 5" supply reel and a 7" take-up,
 * threaded; the head block traced from a photograph (photo pixels mapped
 * onto the panel by a homography from the photo's four panel corners).
 *
 * Colors are classes the theme styles, so the deck follows the reading
 * colors: tN = the text color at N% over the page, bg, hole, tape, gold,
 * tan, led-*. Parts that move: .spin (reel flanges and spokes, drawn about
 * 0,0 in a matrix() that linearises the projection at that layer), .needle
 * (the same, about its pivot), .pinch, .key-play, .key-pause, and the
 * .count, .kind and .year text. The tape stays where it is a third of the
 * way through the clip.
 *
 * Usage: node build/exhibit-deck.js [out]   (default images/exhibit-deck.svg)
 * Prints the aspect ratios _exhibition.scss needs (--pf-deck-r), the tones
 * used ($pf-deck-tones) and the pinch roller's travel.
 */
const fs = require('fs');
const out = process.argv[2] || require('path').join(__dirname, '../images/exhibit-deck.svg');
const RAD = Math.PI / 180, TH = -35 * RAD, C = Math.cos(TH), S = Math.sin(TH), D = 2400;
let OY = 0;
const pr = ([x, y, z]) => { const X = x - 410, Y = y + OY - 450, x1 = X * C + z * S, z1 = -X * S + z * C, s = D / (D - z1); return [x1 * s, Y * s]; };
const f2 = n => (Math.round(n * 10) / 10).toString(), P2 = p => f2(p[0]) + ',' + f2(p[1]), ps = pts => pts.map(p => P2(pr(p))).join(' ');
const tones = new Set();
const T = o => { const n = Math.max(1, Math.round(o * 100)); tones.add(n); return `class="t${n}"`; };
const circB = (cx, cy, r, n = 40) => Array.from({ length: n }, (_, i) => { const a = i / n * 2 * Math.PI; return [cx + r * Math.cos(a), cy + r * Math.sin(a)]; });
const arcB = (cx, cy, r, a1, a2, n = 14) => Array.from({ length: n + 1 }, (_, i) => { const a = (a1 + (a2 - a1) * i / n) * RAD; return [cx + r * Math.cos(a), cy + r * Math.sin(a)]; });
const rectB = (x, y, w, h) => [[x, y], [x + w, y], [x + w, y + h], [x, y + h]];
const Z = (b, z) => b.map(([x, y]) => [x, y, z]);
const so = o => o === undefined ? '' : ` stroke-opacity="${o}"`;
const G = (pts, a, open) => `<${open ? 'polyline' : 'polygon'} points="${ps(pts)}"${a ? ' ' + a : ''}/>`;
const L = (a, b, op, extra) => G([a, b], `stroke-opacity="${op}"${extra ? ' ' + extra : ''}`, 1);
function hull(P) { P = P.slice().sort((a, b) => a[0] - b[0] || a[1] - b[1]); const cr = (o, a, b) => (a[0] - o[0]) * (b[1] - o[1]) - (a[1] - o[1]) * (b[0] - o[0]); const lo = [], up = []; for (const p of P) { while (lo.length > 1 && cr(lo[lo.length - 2], lo[lo.length - 1], p) <= 0) lo.pop(); lo.push(p); } for (const p of P.slice().reverse()) { while (up.length > 1 && cr(up[up.length - 2], up[up.length - 1], p) <= 0) up.pop(); up.push(p); } lo.pop(); up.pop(); return lo.concat(up); }
const silh = (pts3, cls) => `<polygon points="${hull(pts3.map(pr)).map(P2).join(' ')}" ${cls} stroke-opacity=".45"/>`;
function prism(b, z0, z1, fo, sop, extra) { return silh([...Z(b, z0), ...Z(b, z1)], T(fo * .55)) + G(Z(b, z1), `${T(fo)}${extra ? ' ' + extra : ''} stroke-opacity="${sop || .65}"`); }
const cyl = (cx, cy, r, z0, z1, fo, sop) => prism(circB(cx, cy, r), z0, z1, fo, sop);
const ring = (cx, cy, r, z, a) => G(Z(circB(cx, cy, r), z), a);
function txt(x, y, z, s, sz, an, op, cls) { const a = pr([x, y, z]), b = pr([x + 1, y, z]), c = pr([x, y + 1, z]); return `<text${cls ? ` class="${cls}"` : ''} transform="matrix(${(b[0] - a[0]).toFixed(3)} ${(b[1] - a[1]).toFixed(3)} ${(c[0] - a[0]).toFixed(3)} ${(c[1] - a[1]).toFixed(3)} ${f2(a[0])} ${f2(a[1])})" fill-opacity="${op || .6}" font-size="${sz || 7}"${an && an !== 'middle' ? ` text-anchor="${an}"` : ''}>${s}</text>`; }
const scr = (x, y, z = 0) => ring(x, y, 4.5, z, 'stroke-opacity=".35"') + L([x - 3, y, z], [x + 3, y, z], .3);
// The projection at layer z, linearised about (cx, cy): a matrix() that
// takes a part drawn about 0,0 to the screen.
const aff = (cx, cy, z, r = 100) => { const o = pr([cx, cy, z]), xa = pr([cx + r, cy, z]), xb = pr([cx - r, cy, z]), ya = pr([cx, cy + r, z]), yb = pr([cx, cy - r, z]); return [(xa[0] - xb[0]) / (2 * r), (xa[1] - xb[1]) / (2 * r), (ya[0] - yb[0]) / (2 * r), (ya[1] - yb[1]) / (2 * r), o[0], o[1]].map(v => +v.toFixed(4)).join(' '); };
const lp = pts => pts.map(p => f2(p[0]) + ',' + f2(p[1])).join(' ');

let s = '';
const foot = (x0, x1, zf, zb) => prism([[x0, 900], [x1, 900], [x1 - 5, 934], [x0 + 5, 934]], zb, zf, .08, .45);
s += foot(748, 816, -292, -376) + foot(4, 72, -6, -96) + foot(748, 816, -6, -96);
s += G([[820, 0, 0], [820, 0, -380], [820, 900, -380], [820, 900, 0]], `${T(.07)} stroke-opacity=".5"`);
[[-26, .3], [-34, .15], [-362, .25]].forEach(([z, o]) => { s += L([820, 0, z], [820, 900, z], o); });
[176, 476, 786].forEach(y => { const c = r => Array.from({ length: 28 }, (_, i) => { const a = i / 28 * 2 * Math.PI; return [820, y + r * Math.sin(a), -148 + r * Math.cos(a)]; }); s += G(c(20), `${T(.16)} stroke-opacity=".55"`) + G(c(8), 'class="hole" stroke-opacity=".45"'); });
s += G(Z(rectB(0, 0, 820, 900), 0), 'class="bg" stroke-opacity=".6"');
[[14, 40], [806, 40], [14, 330], [806, 330], [14, 676], [806, 676], [14, 884], [806, 884]].forEach(p => { s += scr(...p); });
[130, 690].forEach(x => { s += ring(x, 140, 110, 0, `${T(.03)} stroke-opacity=".45"`) + ring(x, 140, 98, 0, 'stroke-opacity=".2"'); });
s += G(Z([[4, 426], [508, 426], [508, 399], [546, 426], [814, 426], [814, 577], [4, 577]], 0), `${T(.025)} stroke-opacity=".4"`);
const slot = (cx, cy, a1, a2) => Z([...arcB(cx, cy, 97, a1, a2), ...arcB(cx, cy, 83, a2, a1)], 0);
s += G(slot(155, 522, 186, 240), 'class="hole" stroke-opacity=".35"') + G(slot(663, 531, 296, 348), 'class="hole" stroke-opacity=".35"');
s += cyl(617, 473, 16, 0, 4, .16, .6);
s += G(Z([[4, 578], [808, 578], [812, 584], [812, 660], [808, 666], [4, 666]], 0), `${T(.04)} stroke-opacity=".45"`) + G(Z(rectB(36, 593, 752, 59), 0), `${T(.02)} stroke-opacity=".55"`) + L([0, 670, 0], [820, 670, 0], .25);
OY = 7;
s += txt(70, 596, 0, 'POWER', 6.5) + prism(rectB(55, 604, 30, 30), 0, 8, .14) + txt(97, 612, 0, 'ON', 5.5, 'start', .45) + txt(97, 626, 0, 'OFF', 5.5, 'start', .45);
[['REEL', 122], ['SPEED', 160], ['EDIT', 200]].forEach(([t, x]) => { s += txt(x, 596, 0, t, 6.5) + prism(rectB(x - 5, 606, 10, 24), 0, 10, .2); });
s += txt(275, 596, 0, 'PITCH CONT', 6.5) + cyl(262, 621, 13, 0, 16, .16, .65) + txt(290, 616, 0, 'PULL ON', 5, 'start', .45);
s += txt(358, 596, 0, '▲CUE', 6.5) + prism(rectB(352, 610, 10, 22), 0, 10, .18) + G(Z(rectB(384, 600, 26, 26), 0), 'class="hole" stroke-opacity=".45"');
const tri = (pts, cx, cy) => G(pts.map(([x, y]) => [cx + x, cy + y, 0]), `${T(.6)} stroke-opacity=".5"`);
for (let i = 0; i < 4; i++) {
  const x = 450 + i * 52, cx = x + 25;
  if (i === 0) s += tri([[-6, 0], [0, -3], [0, 3]], cx - 1, 595) + tri([[0, 0], [6, -3], [6, 3]], cx - 7, 595);
  if (i === 1) s += tri([[0, -3], [6, 0], [0, 3]], cx - 5, 595) + tri([[0, -3], [6, 0], [0, 3]], cx + 1, 595);
  if (i === 2) s += txt(cx, 598, 0, 'STOP', 6.5);
  if (i === 3) s += tri([[-3, -3], [4, 0], [-3, 3]], cx, 595);
  s += i === 3 ? prism(rectB(x, 605, 50, 30), 0, 8, .13, .65).replace(/(<polygon[^>]*)class="t13"/g, '$1class="key-play"') : prism(rectB(x, 605, 50, 30), 0, 8, .13, .65);
}
s += txt(696, 596, 0, 'PAUSE', 6.5) + prism(rectB(672, 605, 48, 30), 0, 8, .08, .65).replace(/class="t8"/, 'class="key-pause"') + txt(750, 596, 0, 'RECORD', 6.5) + prism(rectB(726, 605, 48, 30), 0, 8, .08) + ring(750, 620, 3, 8, 'stroke-opacity=".5"');
OY = 0;
function scale(cx, cy, r, lab) { let h = ''; for (let i = 0; i <= 10; i++) { const a = (-225 + i * 27) * RAD; h += L([cx + r * Math.cos(a), cy + r * Math.sin(a), 0], [cx + (r + 5) * Math.cos(a), cy + (r + 5) * Math.sin(a), 0], .5); if (lab && i % 2 === 0) h += txt(cx + (r + 13) * Math.cos(a), cy + (r + 13) * Math.sin(a) + 3, 0, i, 7, 'middle', .5); } return h; }
function bigKnob(cx, cy) {
  const t = (-225 + 3.8 * 27) * RAD;
  return scale(cx, cy, 37, true) + cyl(cx, cy, 32, 0, 26, .13, .65)
    + L([cx + 27 * Math.cos(t), cy + 27 * Math.sin(t), 26], [cx + 32 * Math.cos(t), cy + 32 * Math.sin(t), 26], .9, 'stroke-width="2"')
    + cyl(cx, cy, 24, 26, 48, .2, .75);
}
s += txt(65, 692, 0, 'PHONES', 6.5) + scale(65, 722, 15, false) + cyl(65, 722, 11, 0, 20, .17, .7);
[775, 815, 855].forEach((y, i) => { s += ring(65, y, 10, 0, `${T(.12)} stroke-opacity=".6"`) + ring(65, y, 4.5, 0, 'class="hole" stroke-opacity=".4"') + txt(84, y + 3, 0, ['', 'L', 'R'][i], 6, 'start', .45); });
s += txt(65, 880, 0, 'MIC', 6.5);
const brk = (x1, x2, t) => txt((x1 + x2) / 2, 681, 0, t, 7) + G([[x1 - 4, 692, 0], [x1 - 4, 686, 0], [x2 + 4, 686, 0], [x2 + 4, 692, 0]], 'stroke-opacity=".45"', 1);
s += brk(165, 410, 'INPUT SELECT') + brk(510, 550, 'FUNCTION') + brk(635, 715, 'OUTPUT SELECT');
const lever = (x, t, led) => {
  const b = rectB(x - 6, 716, 12, 28);
  return txt(x, 700, 0, t, 6) + G(Z(rectB(x - 4, 703, 8, 4), 0), `class="led-${led}" stroke-opacity=".3"`) + L([x, 707, 0], [x, 716, 0], .45)
    + silh([...Z(b, 0), ...Z(b, 14)], 'class="tan"') + G(Z(b, 14), `${T(.34)} stroke-opacity=".65"`);
};
[[165, 'ATT', 'dim'], [205, 'MIC', 'dim'], [245, 'LINE', 'dim'], [330, 'ATT', 'dim'], [370, 'MIC', 'dim'], [410, 'LINE', 'dim'], [510, 'L', 'red'], [550, 'R', 'red'], [635, 'INPUT', 'red'], [675, 'SYNC', 'amb'], [715, 'REPRO', 'grn']].forEach(a => { s += lever(...a); });
s += txt(205, 760, 0, 'LEFT', 6.5) + txt(370, 760, 0, 'RIGHT', 6.5);
function vu(x0) {
  const cx = x0 + 74, py = 852, z = 3;
  let h = prism(rectB(x0, 768, 148, 92), 0, z, .04, .6) + G(Z(rectB(x0 + 9, 775, 130, 68), z), `${T(.09)} stroke-opacity=".4"`);
  h += G(Z(arcB(cx, py, 60, -128, -74), z), 'stroke-opacity=".5"', 1) + G(Z(arcB(cx, py, 60, -74, -52, 6), z), 'class="gold" stroke-opacity=".8" stroke-width="2"', 1);
  [['20', -126], ['10', -115], ['7', -107], ['5', -100], ['3', -93], ['0', -74], ['3', -56]].forEach(([t, a]) => { const r = a * RAD; h += txt(cx + 69 * Math.cos(r), py + 69 * Math.sin(r) + 2, z, t, 6.5, 'middle', .55) + L([cx + 60 * Math.cos(r), py + 60 * Math.sin(r), z], [cx + 64 * Math.cos(r), py + 64 * Math.sin(r), z], .5); });
  h += txt(cx, 826, z, 'VU', 10, 'middle', .6) + G(Z(arcB(cx, 843, 13, 180, 360, 10), z), `${T(.15)} stroke-opacity=".5"`);
  return h;
}
s += vu(130) + vu(295);
s += txt(478, 776, 0, 'INPUT', 7) + bigKnob(530, 822) + txt(624, 776, 0, 'OUTPUT', 7) + bigKnob(678, 822);

// Head block, traced from the angled photo.
function homog(src, dst) {
  const A = [], b = []; src.forEach(([x, y], i) => { const [u, v] = dst[i]; A.push([x, y, 1, 0, 0, 0, -u * x, -u * y]); b.push(u); A.push([0, 0, 0, x, y, 1, -v * x, -v * y]); b.push(v); });
  for (let i = 0; i < 8; i++) { let m = i; for (let r = i + 1; r < 8; r++) if (Math.abs(A[r][i]) > Math.abs(A[m][i])) m = r; [A[i], A[m]] = [A[m], A[i]]; [b[i], b[m]] = [b[m], b[i]]; for (let r = 0; r < 8; r++) if (r !== i) { const f = A[r][i] / A[i][i]; for (let c = i; c < 8; c++) A[r][c] -= f * A[i][c]; b[r] -= f * b[i]; } }
  return [...b.map((v, i) => v / A[i][i]), 1];
}
const HP = homog([[115, 101], [1001, -57], [998, 1408], [108, 1257]], [[0, 0], [820, 0], [820, 900], [0, 900]]);
const ph = ([x, y]) => { const w = HP[6] * x + HP[7] * y + HP[8]; return [(HP[0] * x + HP[1] * y + HP[2]) / w, (HP[3] * x + HP[4] * y + HP[5]) / w, 0]; };
const PH = pts => pts.map(ph);
const hP = (pts, a) => G(PH(pts), a), hL = (a, b, op) => G(PH([a, b]), `stroke-opacity="${op}"`, 1);
const hT = (p, t, sz, op, cls) => { const q = ph(p); return txt(q[0], q[1], 0, t, sz, 'middle', op, cls); };
const lx = y => 392.9 - (y - 493.6) * .4643, rx = y => 519.2 + (y - 492.3) * .3826;
const HB = {
  top: [[392.9, 493.6], [431.4, 487.2], [564.1, 480.8], [576.9, 482.7], [519.2, 492.3]],
  side: [[519.2, 492.3], [576.9, 482.7], [669.9, 646.1], [669.9, 690], [600, 712], [582, 710.2], [589.7, 675], [578.8, 649.4], [560.9, 601.3]],
  front: [[392.9, 493.6], [519.2, 492.3], [560.9, 601.3], [342.9, 601.3]],
  roof: [[342.9, 601.3], [560.9, 601.3], [578.8, 649.4], [291.7, 649.4]],
  band: [[291.7, 649.4], [578.8, 649.4], [589.7, 675], [582, 710.2], [253.2, 710.2], [253.2, 667.2]]
};
let f = '';
f += hP(HB.side, `${T(.045)} stroke-opacity=".5"`) + hP(HB.top, `${T(.12)} stroke-opacity=".55"`);
f += hP(HB.front, `${T(.075)} stroke-opacity=".7"`) + hL([lx(525), 525], [rx(525), 525], .35) + hL([lx(563), 563], [rx(563), 563], .35);
f += hP(rectB(408, 527, 77, 34), 'class="hole" stroke-opacity=".5"') + hT([446.5, 550], '0:00', 12, .85, 'count');
f += hP(rectB(375, 540, 17, 23), `${T(.3)} stroke-opacity=".6"`) + hP(rectB(507, 537, 16, 23), `${T(.3)} stroke-opacity=".6"`);
f += hT([384, 578], 'ZERO RETURN', 6.5) + hT([515, 575], 'RESET', 6.5);
f += hP(HB.roof, `${T(.12)} stroke-opacity=".6"`);
f += hP(HB.band, `${T(.085)} stroke-opacity=".7"`);
[[262.8, 676.9], [560.9, 678.2]].forEach(p => { const q = ph(p); f += scr(q[0], q[1]); });
f += hP(rectB(309, 660, 111, 31), `${T(.03)} stroke-opacity=".6"`) + hP(rectB(420, 660, 58, 31), `${T(.18)} stroke-opacity=".6"`) + G(PH([[309, 698], [478, 698]]), 'class="gold" stroke-opacity=".85" stroke-width="2"', 1);
f += hT([364.5, 680], 'AUDIO ONLY', 9.5, .6, 'kind') + hT([449, 681], '', 10.5, .85, 'year');

// Tape path at a fixed point in the clip (the packs do not grow).
const reels = [{ cx: 130, k: 5 / 7, hub: 44, max: 104 }, { cx: 690, k: 1, hub: 50, max: 136 }];
function tan2(A, B) {
  const [x1, y1] = A.c, [x2, y2] = B.c, dx = x2 - x1, dy = y2 - y1, Ln = Math.hypot(dx, dy), ux = dx / Ln, uy = dy / Ln;
  for (const s1 of [1, -1]) for (const s2 of [1, -1]) for (const sg of [1, -1]) {
    const r1 = s1 * A.r, r2 = s2 * B.r, cs = (r1 - r2) / Ln; if (Math.abs(cs) > 1) continue; const sn = sg * Math.sqrt(1 - cs * cs), nx = ux * cs - uy * sn, ny = uy * cs + ux * sn;
    const p1 = [x1 + r1 * nx, y1 + r1 * ny], p2 = [x2 + r2 * nx, y2 + r2 * ny], tx = p2[0] - p1[0], ty = p2[1] - p1[1];
    const ok = (u, w) => tx * (w * -u[1]) + ty * (w * u[0]) > 0;
    if (ok([s1 * nx, s1 * ny], A.w) && ok([s2 * nx, s2 * ny], B.w)) return [p1, p2];
  }
  return [A.c, B.c];
}
const segP = (a, b) => { const n = Math.max(1, Math.ceil(Math.hypot(b[0] - a[0], b[1] - a[1]) / 6)); return Array.from({ length: n + 1 }, (_, i) => [a[0] + (b[0] - a[0]) * i / n, a[1] + (b[1] - a[1]) * i / n]); };
function arcP(K, a, d) { const a0 = Math.atan2(a[1] - K.c[1], a[0] - K.c[0]), a1 = Math.atan2(d[1] - K.c[1], d[0] - K.c[0]); let dl = a1 - a0; if (K.w > 0) { while (dl < 0) dl += 2 * Math.PI; } else { while (dl > 0) dl -= 2 * Math.PI; } const n = Math.max(2, Math.ceil(Math.abs(dl) * K.r / 5)); return Array.from({ length: n + 1 }, (_, i) => { const t = a0 + dl * i / n; return [K.c[0] + K.r * Math.cos(t), K.c[1] + K.r * Math.sin(t)]; }); }
function tapePath(rs, rt) {
  const cs = [{ c: [130, 140], r: rs, w: -1 }, { c: [67, 522], r: 22, w: -1 }, { c: [164, 483], r: 43, w: 1 }, { c: [617, 473], r: 7.5, w: -1 }, { c: [752, 520], r: 22, w: -1 }, { c: [690, 140], r: rt, w: -1 }];
  const tg = []; for (let i = 0; i < 5; i++) tg.push(tan2(cs[i], cs[i + 1]));
  let pts = [], gA = 0, cD = 0;
  for (let i = 0; i < 5; i++) { pts.push(...segP(tg[i][0], tg[i][1])); if (i < 4) { const k = i + 1; if (k === 2) gA = pts.length - 1; pts.push(...arcP(cs[k], tg[i][1], tg[k][0])); if (k === 3) cD = pts.length - 1; } }
  const R0 = 165 * 5 / 7, R1 = 165, dist = (p, c) => Math.hypot(p[0] - c[0], p[1] - c[1]);
  let j = 0; while (j < gA && dist(pts[j], [130, 140]) < R0) j++;
  let k = pts.length - 1; while (k > cD && dist(pts[k], [690, 140]) < R1) k--;
  return { aIn: pts.slice(0, j + 1), aOut: pts.slice(Math.max(0, j - 1), gA + 1), b: pts.slice(gA, cD + 1), cOut: pts.slice(cD, k + 2), cIn: pts.slice(k) };
}
const rib = pts => pts.length < 2 ? '' : G([...pts.map(p => [p[0], p[1], 15]), ...pts.slice().reverse().map(p => [p[0], p[1], 27])], 'class="tape" stroke="none"');
const P = .35, A2 = reels[0].max ** 2 - reels[0].hub ** 2;
const rr = [Math.sqrt(reels[0].hub ** 2 + A2 * (1 - P)), Math.sqrt(reels[1].hub ** 2 + A2 * P)];
const TP = tapePath(rr[0], rr[1]);
const ROL = [[67, 522, 21, 0, 34, .17], [164, 483, 42, 0, 33, .15], [617, 473, 6.5, 4, 32, .28], [752, 520, 21, 0, 34, .17]];
const RL = ROL.map(([cx, cy, r, z0, z1, fo]) => { const c0 = Z(circB(cx, cy, r), z0), c1 = Z(circB(cx, cy, r), z1); return [silh([...c0, ...c1], T(fo * .55)), G(c1, `${T(fo)} stroke-opacity=".7"`)]; });
const m = RL.map(r => r[0]).join('') + rib(TP.aOut) + rib(TP.b) + rib(TP.cOut) + RL.map(r => r[1]).join('');

// Pinch roller (out; CSS presses it in while the clip plays), needles at
// rest, the reels.
const FZ = 30, BZ = 12, TZ = 21;
let d = '';
const pin = pr([524, 525, 16]), pout = pr([530, 531, 16]);
d += `<g class="pinch">` + cyl(530, 531, 38, 0, 32, .03, .7) + ring(530, 531, 29, 32, `${T(.22)} stroke-opacity=".6"`) + '</g>';
[204, 369].forEach(cx => { d += `<g transform="matrix(${aff(cx, 852, 4, 40)})"><line class="needle gold" x1="0" y1="0" x2="0" y2="-72"/></g>`; });
function reel(R, tin, rp, i) {
  const k = R.k, cx = R.cx;
  const spin = (z, inner) => `<g transform="matrix(${aff(cx, 140, z)})"><g class="spin spin--${i}">${inner}</g></g>`;
  const flange = () => { let p = 'M' + lp(circB(0, 0, 165 * k, 64)) + 'Z'; [-90, 30, 150].forEach(a => { p += 'M' + lp([...arcB(0, 0, 142 * k, a - 30, a + 30, 12), ...arcB(0, 0, Math.max(R.hub + 12, 64 * k), a + 17, a - 17, 8)]) + 'Z'; }); return p.replace(/ /g, 'L'); };
  let h = spin(BZ, `<path d="${flange()}" class="bg flange" fill-opacity=".9" stroke-opacity=".45"/>`);
  h += cyl(cx, 140, R.hub, BZ, FZ, .04, .4);
  h += ring(cx, 140, rp, TZ, 'class="tape" stroke-opacity="0"') + ring(cx, 140, Math.max(R.hub, rp - 16 * k), TZ, 'stroke-opacity=".22"');
  h += rib(tin);
  h += spin(FZ, `<path d="${flange()}" class="bg flange" fill-opacity=".86" stroke-opacity=".9"/>`);
  h += G(Z(circB(cx, 140, R.hub), FZ), 'stroke-opacity=".5"');
  h += cyl(cx, 140, 34, FZ, FZ + 16, .15, .75);
  let sp = ''; for (let j = 0; j < 12; j++) { const a = j * 30 * RAD; sp += `<line x1="${f2(22 * Math.cos(a))}" y1="${f2(22 * Math.sin(a))}" x2="${f2(31 * Math.cos(a))}" y2="${f2(31 * Math.sin(a))}" stroke-opacity=".45"/>`; }
  h += spin(FZ + 16, sp);
  h += cyl(cx, 140, 6, FZ + 16, FZ + 26, .3, .7);
  return h;
}
d += reel(reels[0], TP.aIn, rr[0], 0) + reel(reels[1], TP.cIn, rr[1], 1);

// The whole drawing, and the top plate alone (to the transport's edge).
const all = [[0, 0, 0], [820, 0, 0], [820, 900, 0], [0, 900, 0], [820, 0, -380], [820, 934, -380], [4, 934, -6], [816, 934, -6]];
reels.forEach(r => circB(r.cx, 140, 165 * r.k, 24).forEach(([a, b]) => { all.push([a, b, 30]); all.push([a, b, 12]); }));
const pp = all.map(pr), xs = pp.map(p => p[0]), ys = pp.map(p => p[1]), pad = 14;
const vx = Math.min(...xs) - pad, vy = Math.min(...ys) - pad, vw = Math.max(...xs) - Math.min(...xs) + pad * 2, vh = Math.max(...ys) - Math.min(...ys) + pad * 2;
const topY = Math.max(...[0, 820].map(x => pr([x, 674, 0])[1])) + 4;
const svg = `<svg xmlns="http://www.w3.org/2000/svg" class="pf-deck__svg" viewBox="${f2(vx)} ${f2(vy)} ${f2(vw)} ${f2(vh)}" aria-hidden="true" focusable="false">\n<g fill="none" stroke="currentColor" text-anchor="middle">\n${(s + m + f + d).replace(/\/>/g, '/>\n').replace(/<g /g, '\n<g ')}</g>\n</svg>\n`;
fs.writeFileSync(out, svg.replace(/\n+/g, '\n'));
const sorted = [...tones].sort((a, b) => a - b);
console.log(JSON.stringify({ bytes: svg.length, tones: sorted, aspect: +(vw / vh).toFixed(4), topAspect: +(vw / (topY - vy)).toFixed(4), topFrac: +((topY - vy) / vh).toFixed(4), pinch: [+(pin[0] - pout[0]).toFixed(2), +(pin[1] - pout[1]).toFixed(2)] }));
