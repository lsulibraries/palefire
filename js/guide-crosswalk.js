/**
 * @file
 * Guide: Field crosswalk. Markup: paragraph--guide-crosswalk.html.twig.
 *
 * Reads the mapping spreadsheet uploaded to the block (CSV, the master
 * mapping's own columns) and, for the chosen institution, draws each MODS
 * element on the left and the CSV column it becomes on the right, joined by a
 * line. Rows count when needed or att_needed is "yes" and their
 * institution_list names the institution. Element text draws a solid line;
 * an attribute (att_field_name, from att_flag) a dashed one.
 *
 * Choosing a box lights its lines and fills the panel with the full XPaths,
 * display labels and Notes rules behind it. Section chips hide parts of the
 * record; the filter searches paths, labels and rules. The URL keeps the
 * institution (#crosswalk-<name>), replaced, never pushed.
 *
 * The drawing is a picture: every box is also a button, and the table of
 * mapping rows under it says the same in text.
 */
(function (Drupal, once) {
  'use strict';

  const NS = 'http://www.w3.org/2000/svg';
  const ROW_H = 26;
  const SEC_H = 30;
  const PAD = 12;
  const SECTION_TONES = 7; // _guide-crosswalk.scss: --pf-cw-0 to --pf-cw-7 (7 = the rest).
  const SUFFIXES = ['_number', '_end', '_start', '_label', '_qualifier'];
  const REQUIRED = ['xpaths', 'needed', 'att_needed', 'field_name', 'att_field_name', 'institution_list'];

  const isHelper = (f) => /^(temp_|nonsort_|att_)/.test(f);
  const isTbd = (f) => /^TBD/i.test(f);
  const section = (src) => src.split('/')[0].replace(/^@/, '') || 'mods';
  const slug = (s) => s.toLowerCase().replace(/&/g, 'and').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

  // CSV (RFC 4180: quoted fields may hold commas, quotes and line breaks). --
  function parseCsv(text) {
    const rows = [];
    let row = [];
    let cell = '';
    let quoted = false;
    for (let i = 0; i < text.length; i++) {
      const c = text[i];
      if (quoted) {
        if (c === '"') {
          if (text[i + 1] === '"') { cell += '"'; i++; }
          else { quoted = false; }
        }
        else { cell += c; }
      }
      else if (c === '"') { quoted = true; }
      else if (c === ',') { row.push(cell); cell = ''; }
      else if (c === '\n' || c === '\r') {
        if (c === '\r' && text[i + 1] === '\n') { i++; }
        row.push(cell); rows.push(row); row = []; cell = '';
      }
      else { cell += c; }
    }
    if (cell !== '' || row.length) { row.push(cell); rows.push(row); }
    return rows;
  }

  // Fields a helper column is combined into, from a Notes cell: the base
  // field_* token of each step that mentions it (as extraction.py chooses
  // a step's destination).
  function targetsOf(notes, helper) {
    const out = [];
    notes.split(/;(?![^()]*\))/).forEach((step) => {
      const toks = [...step.matchAll(/\[([A-Za-z_]\w*)\]/g)].map((m) => m[1]);
      if (!toks.includes(helper)) { return; }
      const prepend = step.match(/Prepend to \[?(field_\w+)/);
      if (prepend) { out.push(prepend[1]); return; }
      const fields = toks.filter((t) => t.startsWith('field_'));
      const base = fields.filter((t) => !SUFFIXES.some((s) => t.endsWith(s)));
      const pick = (base.length ? base : fields)[0];
      if (pick) { out.push(pick); }
    });
    return out;
  }

  function readMapping(text) {
    const table = parseCsv(text.replace(/^﻿/, ''));
    const head = (table.shift() || []).map((h) => h.trim());
    const col = {};
    head.forEach((h, i) => { col[h.toLowerCase()] = i; });
    const missing = REQUIRED.filter((h) => !(h in col));
    if (missing.length) {
      throw new Error(Drupal.t('The spreadsheet has no @cols column. Upload the master mapping saved as "CSV UTF-8".', { '@cols': missing.join(', ') }));
    }
    const get = (r, name) => (name in col ? (r[col[name]] || '').trim() : '');

    const insts = new Map();
    const rows = [];
    const helperVotes = {};
    table.forEach((r) => {
      const needed = get(r, 'needed').toLowerCase() === 'yes';
      const attNeeded = get(r, 'att_needed').toLowerCase() === 'yes';
      const notes = get(r, 'notes');
      // Global fallback for helpers: every Notes cell, used or not.
      if (notes) {
        [...notes.matchAll(/\[((?:temp|nonsort|att)_\w+)\]/g)].forEach((m) => {
          targetsOf(notes, m[1]).forEach((t) => {
            (helperVotes[m[1]] ||= {})[t] = (helperVotes[m[1]][t] || 0) + 1;
          });
        });
      }
      if (!needed && !attNeeded) { return; }
      const xp = get(r, 'xpaths');
      if (!xp) { return; }
      const field = needed ? get(r, 'field_name') : '';
      const att = attNeeded ? get(r, 'att_field_name') : '';
      if (!field && !att) { return; }
      const into = {};
      [field, att].forEach((h) => {
        if (h && isHelper(h)) {
          const t = targetsOf(notes, h);
          if (t.length) { into[h] = t[0]; }
        }
      });
      const i = get(r, 'institution_list').split(';').map((s) => s.trim()).filter(Boolean).map((name) => {
        if (!insts.has(name)) { insts.set(name, insts.size); }
        return insts.get(name);
      });
      rows.push({
        x: xp,
        s: xp.replace(/\s*\[[^\]]*\]/g, '').replace(/^mods\/?/, '') || 'mods',
        l: get(r, '@displaylabel'),
        f: field,
        a: att,
        af: get(r, 'att_flag'),
        n: notes,
        t: get(r, 'field_type'),
        d1: get(r, 'delimiter_1'),
        i,
        into,
      });
    });
    const helperInto = {};
    Object.entries(helperVotes).forEach(([h, votes]) => {
      helperInto[h] = Object.keys(votes).sort((a, b) => votes[b] - votes[a])[0];
    });
    rows.forEach((r) => {
      [r.f, r.a].forEach((h) => {
        if (h && isHelper(h) && !r.into[h] && helperInto[h]) { r.into[h] = helperInto[h]; }
      });
    });
    return { insts: [...insts.keys()], rows };
  }

  // The block. ------------------------------------------------------------
  function init(root) {
    const app = root.querySelector('[data-crosswalk-app]');
    const status = root.querySelector('[data-crosswalk-status]');
    const src = root.dataset.crosswalkSrc;
    const uid = root.dataset.crosswalkUid || 'pf-crosswalk';
    if (!src || !app) { return; }

    status.textContent = Drupal.t('Loading the mapping…');
    fetch(src, { credentials: 'same-origin' })
      .then((res) => {
        if (!res.ok) { throw new Error(Drupal.t('The mapping spreadsheet could not be loaded (@code).', { '@code': res.status })); }
        return res.text();
      })
      .then((text) => {
        const data = readMapping(text);
        if (root.dataset.crosswalkTbd === '0') {
          data.rows.forEach((r) => {
            if (isTbd(r.f)) { r.f = ''; }
            if (isTbd(r.a)) { r.a = ''; }
          });
          data.rows = data.rows.filter((r) => r.f || r.a);
        }
        if (!data.rows.length) { throw new Error(Drupal.t('The spreadsheet has no rows marked needed.')); }
        build(root, app, data, uid);
      })
      .catch((err) => {
        status.textContent = err.message;
        status.classList.add('pf-crosswalk__status--error');
      });
  }

  function build(root, app, data, uid) {
    const { insts, rows: ALL } = data;
    const counts = insts.map(() => 0);
    ALL.forEach((r) => r.i.forEach((i) => counts[i]++));
    const order = insts.map((n, i) => i).sort((a, b) => insts[a].localeCompare(insts[b]));
    const slugs = insts.map((n) => 'crosswalk-' + slug(n));

    // Section tones: the seven most-used top-level elements get their own.
    const secCount = {};
    ALL.forEach((r) => { const s = section(r.s); secCount[s] = (secCount[s] || 0) + r.i.length; });
    const topSecs = Object.keys(secCount).sort((a, b) => secCount[b] - secCount[a]).slice(0, SECTION_TONES);
    const tone = (s) => (topSecs.includes(s) ? topSecs.indexOf(s) : SECTION_TONES);

    app.innerHTML = `
      <div class="pf-crosswalk__controls">
        <div class="pf-crosswalk__control">
          <label class="pf-crosswalk__label" for="${uid}-inst">${Drupal.t('Institution')}</label>
          <select class="form-select" id="${uid}-inst" data-cw-inst></select>
        </div>
        <div class="pf-crosswalk__control">
          <label class="pf-crosswalk__label" for="${uid}-q">${Drupal.t('Filter')}</label>
          <input class="form-control" type="search" id="${uid}-q" data-cw-q placeholder="${esc(Drupal.t('date, subject, Photographer…'))}">
        </div>
      </div>
      <p class="pf-crosswalk__stats" data-cw-stats aria-live="polite"></p>
      <div class="pf-crosswalk__chips" role="group" aria-label="${esc(Drupal.t('Parts of the MODS record'))}" data-cw-chips></div>
      <div class="pf-crosswalk__layout">
        <div class="pf-crosswalk__chart" data-cw-chart>
          <div class="pf-crosswalk__heads" aria-hidden="true"><span>${Drupal.t('MODS element (source XML)')}</span><span>${Drupal.t('Column in the ingest CSV')}</span></div>
          <div data-cw-svg></div>
        </div>
        <aside class="pf-crosswalk__panel" data-cw-panel aria-live="polite"></aside>
      </div>
      <details class="pf-crosswalk__rows" data-cw-rows hidden>
        <summary>${Drupal.t('Every mapping row for this institution')}</summary>
        <div class="pf-crosswalk__table" tabindex="0" role="region" aria-label="${esc(Drupal.t('Mapping rows'))}">
          <table class="table table-sm"><thead><tr><th scope="col">${Drupal.t('MODS XPath')}</th><th scope="col">${Drupal.t('CSV column')}</th><th scope="col">${Drupal.t('Rule')}</th></tr></thead><tbody data-cw-tbody></tbody></table>
        </div>
      </details>`;

    const $ = (sel) => app.querySelector(sel);
    const select = $('[data-cw-inst]');
    order.forEach((i) => {
      const o = document.createElement('option');
      o.value = i;
      o.textContent = Drupal.t('@name (@n rows)', { '@name': insts[i], '@n': counts[i] });
      select.append(o);
    });
    const showTable = root.dataset.crosswalkTable !== '0';
    if (showTable) { $('[data-cw-rows]').hidden = false; }

    const fallback = () => {
      const want = (root.dataset.crosswalkDefault || '').toLowerCase();
      const named = insts.findIndex((n) => n.toLowerCase() === want);
      if (named >= 0) { return named; }
      return counts.indexOf(Math.max(...counts));
    };
    const fromHash = () => {
      const k = slugs.indexOf(decodeURIComponent(window.location.hash.slice(1)));
      return k >= 0 ? k : null;
    };

    const state = { inst: fromHash() ?? fallback(), q: '', off: new Set(), pick: null, links: [] };
    select.value = state.inst;

    function linksFor() {
      const rows = ALL.filter((r) => r.i.includes(state.inst));
      const map = new Map();
      const add = (src, field, r, kind) => {
        const k = src + '\u0000' + field;
        if (!map.has(k)) { map.set(k, { src, field, rows: [], kind }); }
        map.get(k).rows.push(r);
      };
      rows.forEach((r) => {
        if (r.f) { add(r.s, r.f, r, 'el'); }
        if (r.a) { add(r.s + '/@' + (r.af || 'attr'), r.a, r, 'att'); }
      });
      const all = [...map.values()];
      const secs = {};
      all.forEach((l) => { const s = section(l.src); secs[s] = (secs[s] || 0) + 1; });
      const q = state.q.trim().toLowerCase();
      let shown = all.filter((l) => !state.off.has(section(l.src)));
      if (q) {
        shown = shown.filter((l) => (l.src + ' ' + l.field).toLowerCase().includes(q)
          || l.rows.some((r) => (r.x + ' ' + r.l + ' ' + r.n).toLowerCase().includes(q)));
      }
      return { rows, all, shown, secs };
    }

    function render() {
      const { rows, all, shown, secs } = linksFor();
      state.links = shown;
      const srcN = new Set(all.map((l) => l.src)).size;
      const fldN = new Set(all.map((l) => l.field)).size;
      const ruleN = new Set(rows.filter((r) => r.n).map((r) => r.f || r.a)).size;
      $('[data-cw-stats]').innerHTML = [
        [rows.length, Drupal.t('mapping rows')],
        [srcN, Drupal.t('MODS elements')],
        [fldN, Drupal.t('CSV columns')],
        [ruleN, Drupal.t('columns with combining rules')],
      ].map(([n, label]) => `<span><strong>${n}</strong> ${label}</span>`).join('');

      const chips = $('[data-cw-chips]');
      chips.innerHTML = '';
      Object.keys(secs).sort((a, b) => secs[b] - secs[a]).forEach((s) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'pf-crosswalk__chip';
        b.dataset.tone = tone(s);
        b.setAttribute('aria-pressed', String(!state.off.has(s)));
        b.innerHTML = `<span class="pf-crosswalk__dot" aria-hidden="true"></span>${esc(s)} <span class="pf-crosswalk__n">${secs[s]}</span>`;
        b.addEventListener('click', () => {
          if (state.off.has(s)) { state.off.delete(s); } else { state.off.add(s); }
          state.pick = null;
          render();
        });
        chips.append(b);
      });

      draw(shown, Object.keys(secs).sort((a, b) => secs[b] - secs[a]));
      panel();
      if (showTable) { table(rows); }
    }

    function draw(links, secOrder) {
      const holder = $('[data-cw-svg]');
      holder.innerHTML = '';
      if (!links.length) {
        holder.innerHTML = `<p class="pf-crosswalk__empty">${Drupal.t('Nothing matches this filter.')}</p>`;
        return;
      }
      const W = Math.max(720, Math.floor($('[data-cw-chart]').clientWidth) - 2);
      const colW = Math.round(W * 0.36);
      const chars = Math.floor((colW - 22) / 7.4);
      const x1 = PAD + colW;
      const x2 = W - PAD - colW;

      const srcs = [...new Set(links.map((l) => l.src))];
      const bySec = {};
      srcs.forEach((s) => (bySec[section(s)] ||= []).push(s));
      const left = [];
      let y = PAD;
      secOrder.filter((s) => bySec[s]).forEach((s) => {
        left.push({ sec: s, y });
        y += SEC_H;
        bySec[s].sort().forEach((id) => { left.push({ id, sec: s, y }); y += ROW_H; });
      });
      const srcY = {};
      left.forEach((n) => { if (n.id) { srcY[n.id] = n.y + ROW_H / 2 - 2; } });

      // Right column in the order of its sources, so lines stay short.
      const fields = [...new Set(links.map((l) => l.field))];
      const bary = {};
      fields.forEach((f) => {
        const ys = links.filter((l) => l.field === f).map((l) => srcY[l.src]);
        bary[f] = ys.reduce((a, b) => a + b, 0) / ys.length;
      });
      fields.sort((a, b) => bary[a] - bary[b]);
      const span = Math.max(y, PAD + SEC_H + fields.length * ROW_H) + PAD;
      const step = fields.length > 1
        ? Math.max(ROW_H, Math.min(ROW_H * 1.8, (span - PAD * 2 - SEC_H - ROW_H) / (fields.length - 1)))
        : ROW_H;
      const fldY = {};
      fields.forEach((f, i) => { fldY[f] = PAD + SEC_H + i * step + ROW_H / 2 - 2; });
      const H = Math.max(span, PAD + SEC_H + (fields.length - 1) * step + ROW_H + PAD);

      const info = {};
      links.forEach((l) => {
        const fi = (info[l.field] ||= { rule: false, into: null });
        l.rows.forEach((r) => {
          if (r.n) { fi.rule = true; }
          if (r.into[l.field]) { fi.into = r.into[l.field]; }
        });
      });

      const svg = document.createElementNS(NS, 'svg');
      svg.setAttribute('viewBox', `0 0 ${W} ${H}`);
      svg.setAttribute('width', W);
      svg.setAttribute('height', H);
      svg.setAttribute('class', 'pf-crosswalk__svg');
      svg.setAttribute('role', 'group');
      svg.setAttribute('aria-label', Drupal.t('@a MODS elements and the @b CSV columns they become', { '@a': srcs.length, '@b': fields.length }));
      const gLines = document.createElementNS(NS, 'g');
      gLines.setAttribute('aria-hidden', 'true');
      const gNodes = document.createElementNS(NS, 'g');
      svg.append(gLines, gNodes);

      const mid = (x1 + x2) / 2;
      links.forEach((l) => {
        const p = document.createElementNS(NS, 'path');
        const ya = srcY[l.src];
        const yb = fldY[l.field];
        p.setAttribute('d', `M${x1},${ya} C${mid},${ya} ${mid},${yb} ${x2},${yb}`);
        p.setAttribute('class', 'pf-crosswalk__line' + (l.kind === 'att' ? ' pf-crosswalk__line--att' : ''));
        p.dataset.tone = tone(section(l.src));
        p.dataset.src = l.src;
        p.dataset.field = l.field;
        gLines.append(p);
      });

      const text = (x, ty, s, cls) => {
        const t = document.createElementNS(NS, 'text');
        t.setAttribute('x', x);
        t.setAttribute('y', ty);
        if (cls) { t.setAttribute('class', cls); }
        t.textContent = s;
        return t;
      };
      const clipStart = (s, n) => (s.length > n ? '…' + s.slice(s.length - n + 1) : s);
      const clipEnd = (s, n) => (s.length > n ? s.slice(0, n - 1) + '…' : s);

      const node = (kind, id, x, ny, extra, label) => {
        const g = document.createElementNS(NS, 'g');
        g.setAttribute('class', 'pf-crosswalk__node' + (extra ? ' ' + extra : ''));
        g.setAttribute('tabindex', '0');
        g.setAttribute('role', 'button');
        g.setAttribute('aria-label', label);
        g.dataset.kind = kind;
        g.dataset.id = id;
        const r = document.createElementNS(NS, 'rect');
        r.setAttribute('x', x);
        r.setAttribute('y', ny);
        r.setAttribute('width', colW);
        r.setAttribute('height', ROW_H - 4);
        r.setAttribute('rx', 3);
        g.append(r);
        const go = () => {
          const same = state.pick && state.pick.kind === kind && state.pick.id === id;
          state.pick = same ? null : { kind, id };
          light();
          panel();
        };
        g.addEventListener('click', go);
        g.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); }
        });
        gNodes.append(g);
        return g;
      };

      left.forEach((n) => {
        if (!n.id) {
          const t = text(PAD, n.y + 19, n.sec, 'pf-crosswalk__sec');
          t.dataset.tone = tone(n.sec);
          gNodes.append(t);
          return;
        }
        const g = node('src', n.id, PAD, n.y, '', Drupal.t('MODS element @id', { '@id': n.id }));
        const bar = document.createElementNS(NS, 'rect');
        bar.setAttribute('x', PAD);
        bar.setAttribute('y', n.y);
        bar.setAttribute('width', 4);
        bar.setAttribute('height', ROW_H - 4);
        bar.setAttribute('class', 'pf-crosswalk__bar');
        bar.dataset.tone = tone(n.sec);
        g.append(bar, text(PAD + 12, n.y + 15, clipStart(n.id, chars)));
      });

      gNodes.append(text(x2, PAD + 19, Drupal.t('@n columns', { '@n': fields.length }), 'pf-crosswalk__sec'));
      fields.forEach((f) => {
        const ny = fldY[f] - ROW_H / 2 + 2;
        const fi = info[f];
        const extra = isTbd(f) ? 'pf-crosswalk__node--tbd' : (isHelper(f) ? 'pf-crosswalk__node--helper' : '');
        let label = Drupal.t('CSV column @id', { '@id': f });
        if (fi.into) { label += ', ' + Drupal.t('merged into @to', { '@to': fi.into }); }
        const g = node('field', f, x2, ny, extra, label);
        const room = chars - (fi.rule ? 6 : 0);
        const name = clipEnd(f, fi.into ? Math.floor(room * 0.55) : room);
        g.append(text(x2 + 10, ny + 15, name));
        if (fi.into) {
          g.append(text(x2 + 16 + name.length * 7.4, ny + 15, '→ ' + clipEnd(fi.into, room - name.length - 3), 'pf-crosswalk__sub'));
        }
        if (fi.rule) {
          g.append(text(x2 + colW - 36, ny + 15, Drupal.t('RULE'), 'pf-crosswalk__badge'));
        }
      });

      holder.append(svg);
      light();
    }

    function light() {
      const svg = app.querySelector('.pf-crosswalk__svg');
      if (!svg) { return; }
      const p = state.pick;
      svg.classList.toggle('is-picking', !!p);
      const onSrc = new Set();
      const onFld = new Set();
      svg.querySelectorAll('.pf-crosswalk__line').forEach((l) => {
        const on = !!p && (p.kind === 'src' ? l.dataset.src === p.id : l.dataset.field === p.id);
        l.classList.toggle('is-on', on);
        if (on) { onSrc.add(l.dataset.src); onFld.add(l.dataset.field); }
      });
      svg.querySelectorAll('.pf-crosswalk__node').forEach((n) => {
        n.classList.toggle('is-on', n.dataset.kind === 'src' ? onSrc.has(n.dataset.id) : onFld.has(n.dataset.id));
        const picked = !!p && n.dataset.kind === p.kind && n.dataset.id === p.id;
        n.classList.toggle('is-picked', picked);
        n.setAttribute('aria-pressed', String(picked));
      });
    }

    const xpath = (r) => `<div class="pf-crosswalk__xpath">${r.l ? `<span class="pf-crosswalk__display">“${esc(r.l)}”</span>` : ''}${esc(r.x)}</div>`;
    const rules = (rs) => [...new Set(rs.map((r) => r.n).filter(Boolean))].map((n) => `<div class="pf-crosswalk__rule">${esc(n)}</div>`).join('');

    function panel() {
      const el = $('[data-cw-panel]');
      const p = state.pick;
      const links = state.links;
      if (!p || !links.some((l) => (p.kind === 'src' ? l.src : l.field) === p.id)) {
        state.pick = null;
        el.innerHTML = `
          <p class="pf-crosswalk__eyebrow">${Drupal.t('How to read this')}</p>
          <p>${Drupal.t('Choose a box on either side to trace its lines and list the full XPaths behind it.')}</p>
          <ul class="pf-crosswalk__legend">
            <li><span class="pf-crosswalk__swatch"></span>${Drupal.t('A column in the ingest CSV')}</li>
            <li><span class="pf-crosswalk__swatch pf-crosswalk__swatch--helper"></span>${Drupal.t('A helper value, merged into the column after the arrow')}</li>
            <li><span class="pf-crosswalk__swatch pf-crosswalk__swatch--tbd"></span>${Drupal.t('Not decided yet (TBD in the mapping)')}</li>
            <li><span class="pf-crosswalk__swatch pf-crosswalk__swatch--rule">${Drupal.t('RULE')}</span>${Drupal.t('Has a concat or hyperlink rule')}</li>
            <li><span class="pf-crosswalk__key"></span>${Drupal.t('Element text')}</li>
            <li><span class="pf-crosswalk__key pf-crosswalk__key--att"></span>${Drupal.t('An attribute value, such as @qualifier')}</li>
          </ul>`;
        return;
      }
      const clear = `<button type="button" class="pf-crosswalk__clear" data-cw-clear>${Drupal.t('Clear the selection')}</button>`;
      if (p.kind === 'src') {
        const ls = links.filter((l) => l.src === p.id);
        const n = ls.reduce((a, l) => a + l.rows.length, 0);
        el.innerHTML = `
          <p class="pf-crosswalk__eyebrow">${Drupal.t('MODS element')} · ${esc(section(p.id))}</p>
          <p class="pf-crosswalk__name">${esc(p.id)}</p>
          <p class="pf-crosswalk__meta">${Drupal.formatPlural(ls.length, '1 CSV column', '@count CSV columns')} · ${Drupal.formatPlural(n, '1 mapping row', '@count mapping rows')}</p>
          ${ls.map((l) => `<div class="pf-crosswalk__group"><p class="pf-crosswalk__to">→ ${esc(l.field)}${l.kind === 'att' ? ` <span class="pf-crosswalk__tag">${Drupal.t('attribute')}</span>` : ''}${isTbd(l.field) ? ` <span class="pf-crosswalk__tag pf-crosswalk__tag--tbd">TBD</span>` : ''}</p>${l.rows.map(xpath).join('')}${rules(l.rows)}</div>`).join('')}
          ${clear}`;
      }
      else {
        const ls = links.filter((l) => l.field === p.id);
        const rs = ls.flatMap((l) => l.rows);
        const into = rs.map((r) => r.into[p.id]).find(Boolean);
        const types = [...new Set(rs.map((r) => r.t).filter(Boolean))];
        const joiner = rs[0].d1 ? ' · ' + Drupal.t('values joined with “@d”', { '@d': rs[0].d1 }) : '';
        const allRules = rules(rs);
        el.innerHTML = `
          <p class="pf-crosswalk__eyebrow">${Drupal.t('CSV column')}${types.length ? ' · ' + esc(types.join(', ')) : ''}</p>
          <p class="pf-crosswalk__name">${esc(p.id)}</p>
          ${into ? `<div class="pf-crosswalk__rule">${Drupal.t('A helper value: merged into <strong>@to</strong>, and not a column of its own in the final CSV.', { '@to': into })}</div>` : ''}
          ${isTbd(p.id) ? `<div class="pf-crosswalk__rule pf-crosswalk__rule--tbd">${Drupal.t('Its destination isn’t decided yet in the mapping.')}</div>` : ''}
          <p class="pf-crosswalk__meta">${Drupal.formatPlural(ls.length, 'From 1 MODS element', 'From @count MODS elements')} · ${Drupal.formatPlural(rs.length, '1 mapping row', '@count mapping rows')}${joiner}</p>
          ${ls.map((l) => `<div class="pf-crosswalk__group"><p class="pf-crosswalk__to">${esc(l.src)} →</p>${l.rows.map(xpath).join('')}</div>`).join('')}
          ${allRules ? `<div class="pf-crosswalk__group"><p class="pf-crosswalk__to">${Drupal.t('Combining rules')}</p>${allRules}</div>` : ''}
          ${clear}`;
      }
      el.querySelector('[data-cw-clear]').addEventListener('click', () => {
        const back = app.querySelector('.pf-crosswalk__node.is-picked');
        state.pick = null;
        light();
        panel();
        if (back) { back.focus(); }
      });
      // Stacked layout: bring the panel into view if it sits above the chart.
      const r = el.getBoundingClientRect();
      if (r.bottom < 0) { el.scrollIntoView({ block: 'nearest' }); }
    }

    function table(rows) {
      $('[data-cw-tbody]').innerHTML = rows.map((r) => {
        const dest = [r.f ? esc(r.f) : '', r.a ? `${esc(r.a)} <span class="pf-crosswalk__n">(@${esc(r.af || 'attr')})</span>` : ''].filter(Boolean).join('<br>');
        return `<tr><td class="pf-crosswalk__td-x">${r.l ? `<span class="pf-crosswalk__display">“${esc(r.l)}”</span>` : ''}${esc(r.x)}</td><td class="pf-crosswalk__td-f">${dest}</td><td class="pf-crosswalk__td-n">${esc(r.n)}</td></tr>`;
      }).join('');
    }

    const choose = (k, fromUser) => {
      state.inst = k;
      state.pick = null;
      state.off.clear();
      select.value = k;
      if (fromUser) { window.history.replaceState(null, '', '#' + slugs[k]); }
      render();
    };
    select.addEventListener('change', () => choose(+select.value, true));
    let timer;
    $('[data-cw-q]').addEventListener('input', (e) => {
      clearTimeout(timer);
      timer = setTimeout(() => { state.q = e.target.value; state.pick = null; render(); }, 150);
    });
    window.addEventListener('hashchange', () => {
      const k = fromHash();
      if (k !== null && k !== state.inst) { choose(k, false); }
    });
    let width = $('[data-cw-chart]').clientWidth;
    window.addEventListener('resize', Drupal.debounce(() => {
      const w = $('[data-cw-chart]').clientWidth;
      if (Math.abs(w - width) > 24) { width = w; render(); }
    }, 200));

    render();
  }

  Drupal.behaviors.palefireGuideCrosswalk = {
    attach(context) {
      once('pf-crosswalk', '[data-crosswalk]', context).forEach(init);
    },
  };
})(Drupal, once);
