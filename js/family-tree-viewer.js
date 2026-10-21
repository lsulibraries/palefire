/**
 * @file
 * Louisiana Newspapers guide: a newspaper family tree in a full-screen
 * viewer, drawn in the Palefire style.
 *
 * Family-tree links (a[data-family-tree], in the parish panel and the
 * Family trees list) open a <dialog> instead of the tree's page. The tree
 * pages are Diagram nodes (jointjs_field): the drawing is JointJS JSON in
 * their [data-diagram] attribute (one tree stores it base64-encoded). This
 * fetches the page once, reads that JSON and draws it as SVG here, without
 * JointJS: boxes (bordered, borderless notes, grey-filled), solid, dashed
 * and grey lines, arrowheads, bends, anchored and free line ends. The
 * title block becomes the viewer's heading; the Key and any notes go under
 * the drawing, with line samples beside "Solid line" and "Dashed line".
 *
 * "Fit" scales a wide tree to the window; "Actual size" draws it at the
 * size its editor made it, to scroll. A link opened with a modifier key,
 * one to another site, or a page with no diagram falls back to the page.
 *
 * Linkable: an open tree is in the address as ?tree=<its page's name>
 * (e.g. ?tree=Napoleonville_Assumption_pioneer), beside any #parish-…, so
 * the address can be shared and opens the guide with that tree up. "Copy
 * link" copies it. Opening a tree adds one history entry, so the browser's
 * Back button (or a phone's back gesture) closes the viewer rather than
 * leaving the guide; closing it any other way takes that entry back off.
 */
(function (Drupal, once) {
  'use strict';

  const SVG_NS = 'http://www.w3.org/2000/svg';
  const PAD = 24;           // map units around the drawing
  const FONT = 13;          // label size, map units; shrinks to fit a box
  const FONT_MIN = 10;
  const LINE_HEIGHT = 1.25;
  const MARKER = [9, 4];    // arrowhead length and half-width, map units
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  // OS setting, or the Settings drawer's "Reduce motion" (html.pf-a11y-motion).
  const reduceMotion = () => motion.matches || document.documentElement.classList.contains('pf-a11y-motion');
  const PARAM = 'tree';
  const cache = new Map();
  let viewer = null;

  // A tree's name in the address: its page's file name without ".html".
  const nameOf = (href) => decodeURIComponent(new URL(href, location.href).pathname.split('/').pop() || '').replace(/\.html?$/i, '');
  // This page's address with ?tree= set to name, or taken off (null).
  function addressWith(name) {
    const url = new URL(location.href);
    if (name) {
      url.searchParams.set(PARAM, name);
    }
    else {
      url.searchParams.delete(PARAM);
    }
    return url.pathname + url.search + url.hash;
  }

  const el = (name, attrs = {}, ns = null) => {
    const node = ns ? document.createElementNS(ns, name) : document.createElement(name);
    Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, value));
    return node;
  };
  // Text of the title, Key and note blocks (HTML in the diagram): read with
  // a parser, never inserted as HTML.
  const blockText = (html) => Array.from(new DOMParser().parseFromString(html, 'text/html').body.children)
    .map((child) => ({ tag: child.tagName.toLowerCase(), text: child.textContent.replace(/ /g, ' ').trim() }))
    .filter((line) => line.text);

  // Page -> JointJS cells, or null when the page has no diagram.
  async function load(url) {
    if (cache.has(url)) return cache.get(url);
    const response = await fetch(url, { credentials: 'same-origin' });
    if (!response.ok) throw new Error(response.status);
    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
    const holder = page.querySelector('[data-diagram]');
    let raw = holder ? holder.getAttribute('data-diagram').trim() : '';
    if (raw && raw[0] !== '{') {
      raw = new TextDecoder().decode(Uint8Array.from(atob(raw), (c) => c.charCodeAt(0)));
    }
    const cells = raw ? JSON.parse(raw).cells : null;
    cache.set(url, cells);
    return cells;
  }

  // Where the segment from p (inside or on rect r) towards q leaves r.
  function exitPoint(p, q, r) {
    const dx = q.x - p.x;
    const dy = q.y - p.y;
    let t = Infinity;
    if (dx) [r.x, r.x + r.w].forEach((x) => { const s = (x - p.x) / dx; if (s > 0 && s < t) t = s; });
    if (dy) [r.y, r.y + r.h].forEach((y) => { const s = (y - p.y) / dy; if (s > 0 && s < t) t = s; });
    if (!Number.isFinite(t) || t >= 1) return p;
    return { x: p.x + dx * t, y: p.y + dy * t };
  }

  // A line end: the box's anchor (centre, or JointJS's topLeft + dx/dy
  // percentages), or a free point.
  function endPoint(end, boxes) {
    const box = end.id && boxes.get(end.id);
    if (!box) return end.x !== undefined ? { x: end.x, y: end.y, box: null } : null;
    const args = (end.anchor && end.anchor.args) || {};
    const pct = (value, size) => (typeof value === 'string' && value.endsWith('%') ? (parseFloat(value) / 100) * size : Number(value) || 0);
    const p = end.anchor && end.anchor.name === 'topLeft'
      ? { x: box.x + pct(args.dx, box.w), y: box.y + pct(args.dy, box.h) }
      : { x: box.x + box.w / 2, y: box.y + box.h / 2 };
    return { ...p, box };
  }

  // Words onto lines no wider than width, at size px, in ctx's family.
  function wrap(text, width, size, ctx) {
    ctx.font = size + 'px ' + ctx.pfFamily;
    const lines = [];
    text.split('\n').forEach((para) => {
      let line = '';
      para.split(/\s+/).filter(Boolean).forEach((word) => {
        const next = line ? line + ' ' + word : word;
        if (line && ctx.measureText(next).width > width) {
          lines.push(line);
          line = word;
        }
        else {
          line = next;
        }
      });
      lines.push(line);
    });
    return lines;
  }

  function draw(cells, family) {
    const boxes = new Map();
    const blocks = [];
    cells.forEach((c) => {
      if (!c.position || !c.size) return;
      const r = { x: c.position.x, y: c.position.y, w: c.size.width, h: c.size.height, cell: c };
      if (c.type === 'Node') {
        const lines = blockText((c.attrs && c.attrs.htmlContent && c.attrs.htmlContent.html) || '');
        const kind = lines.some((line) => /^key\b/i.test(line.text)) ? 'key' : /^h\d$/.test((lines[0] || {}).tag) ? 'title' : 'note';
        blocks.push({ kind, lines, y: r.y });
      }
      else {
        boxes.set(c.id, r);
      }
    });

    const svg = el('svg', { class: 'pf-tree-viewer__svg', 'aria-hidden': 'true', focusable: 'false' }, SVG_NS);
    const lineGroup = el('g', { class: 'pf-tree-viewer__links' }, SVG_NS);
    const boxGroup = el('g', { class: 'pf-tree-viewer__boxes' }, SVG_NS);
    svg.append(lineGroup, boxGroup);
    const extent = { x0: Infinity, y0: Infinity, x1: -Infinity, y1: -Infinity };
    const grow = (x, y) => {
      extent.x0 = Math.min(extent.x0, x);
      extent.y0 = Math.min(extent.y0, y);
      extent.x1 = Math.max(extent.x1, x);
      extent.y1 = Math.max(extent.y1, y);
    };

    // Boxes, top to bottom (the order they fade in, and the order of the
    // text alternative).
    const ctx = document.createElement('canvas').getContext('2d');
    ctx.pfFamily = family;
    const ordered = Array.from(boxes.values()).sort((a, b) => a.y - b.y || a.x - b.x);
    ordered.forEach((r, i) => {
      const body = (r.cell.attrs && r.cell.attrs.body) || {};
      const framed = body.stroke !== 'transparent';
      const filled = body.fill && body.fill !== 'transparent';
      const g = el('g', { class: 'pf-tree-viewer__box' + (framed ? '' : ' is-note') + (filled ? ' is-filled' : ''), style: '--i:' + Math.min(i, 24) }, SVG_NS);
      if (framed || filled) {
        g.append(el('rect', { x: r.x, y: r.y, width: r.w, height: r.h, rx: 2 }, SVG_NS));
      }
      const text = String((r.cell.attrs && r.cell.attrs.label && r.cell.attrs.label.text) || '');
      r.text = text.replace(/\s*\n\s*/g, ' ').trim();
      let size = FONT;
      let lines = wrap(text, r.w - 10, size, ctx);
      while (size > FONT_MIN && lines.length * size * LINE_HEIGHT > r.h - 6) {
        size -= 0.5;
        lines = wrap(text, r.w - 10, size, ctx);
      }
      const label = el('text', { x: r.x + r.w / 2, 'font-size': size, 'text-anchor': 'middle' }, SVG_NS);
      const top = r.y + r.h / 2 - ((lines.length - 1) * size * LINE_HEIGHT) / 2;
      lines.forEach((line, n) => {
        const span = el('tspan', { x: r.x + r.w / 2, y: top + n * size * LINE_HEIGHT, 'dominant-baseline': 'central' }, SVG_NS);
        span.textContent = line;
        label.append(span);
      });
      g.append(label);
      boxGroup.append(g);
      grow(r.x, r.y);
      grow(r.x + r.w, r.y + r.h);
    });

    // Lines: anchor -> bends -> anchor, each end clipped to its box's edge.
    cells.filter((c) => c.source && c.target).forEach((c) => {
      const from = endPoint(c.source, boxes);
      const to = endPoint(c.target, boxes);
      if (!from || !to) return;
      const points = [from, ...(c.vertices || []).map((v) => ({ x: v.x, y: v.y })), to];
      if (from.box) points[0] = exitPoint(from, points[1], from.box);
      const last = points.length - 1;
      if (to.box) points[last] = exitPoint(to, points[last - 1], to.box);
      const line = (c.attrs && c.attrs.line) || {};
      const path = el('path', {
        d: 'M' + points.map((p) => p.x.toFixed(1) + ' ' + p.y.toFixed(1)).join('L'),
        pathLength: 1,
        class: 'pf-tree-viewer__link' + (line.strokeDasharray ? ' is-dashed' : '') + (line.stroke === 'gray' ? ' is-struck' : ''),
      }, SVG_NS);
      lineGroup.append(path);
      // Arrowheads (JointJS markers): the tip on the end, pointing out.
      [[line.targetMarker, points[last], points[last - 1]], [line.sourceMarker, points[0], points[1]]].forEach(([marker, tip, back]) => {
        if (!marker || marker.type === 'none' || !marker.d) return;
        const len = Math.hypot(back.x - tip.x, back.y - tip.y) || 1;
        const ux = (back.x - tip.x) / len;
        const uy = (back.y - tip.y) / len;
        const bx = tip.x + ux * MARKER[0];
        const by = tip.y + uy * MARKER[0];
        lineGroup.append(el('path', {
          class: 'pf-tree-viewer__arrow',
          d: `M${tip.x} ${tip.y}L${bx - uy * MARKER[1]} ${by + ux * MARKER[1]}L${bx + uy * MARKER[1]} ${by - ux * MARKER[1]}Z`,
        }, SVG_NS));
      });
      points.forEach((p) => grow(p.x, p.y));
    });

    const width = extent.x1 - extent.x0 + PAD * 2;
    const height = extent.y1 - extent.y0 + PAD * 2;
    svg.setAttribute('viewBox', [extent.x0 - PAD, extent.y0 - PAD, width, height].map((n) => n.toFixed(1)).join(' '));
    blocks.sort((a, b) => a.y - b.y);
    return { svg, width, height, blocks, boxes: ordered };
  }

  function build() {
    const dialog = el('dialog', { class: 'pf-tree-viewer', 'aria-labelledby': 'pf-tree-viewer-title' });
    dialog.innerHTML = `
      <div class="pf-tree-viewer__bar">
        <div class="pf-tree-viewer__heading">
          <p class="pf-tree-viewer__eyebrow">${Drupal.t('Family tree')}</p>
          <h2 class="pf-tree-viewer__title" id="pf-tree-viewer-title"></h2>
          <p class="pf-tree-viewer__dates"></p>
        </div>
        <div class="pf-tree-viewer__actions">
          <button type="button" class="pf-tree-viewer__action" data-tree-zoom aria-pressed="false" hidden>${Drupal.t('Actual size')}</button>
          <button type="button" class="pf-tree-viewer__action" data-tree-copy hidden>${Drupal.t('Copy link')}</button>
          <a class="pf-tree-viewer__action" data-tree-page data-no-swup href="#">${Drupal.t('Original page')}</a>
          <button type="button" class="pf-tree-viewer__close" data-tree-close aria-label="${Drupal.t('Close')}" autofocus>
            <svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/></svg>
          </button>
        </div>
      </div>
      <div class="pf-tree-viewer__stage" tabindex="0" role="region" aria-label="${Drupal.t('Diagram')}"></div>
      <aside class="pf-tree-viewer__key" aria-label="${Drupal.t('Key and notes')}"></aside>`;
    document.body.append(dialog);
    const parts = {
      dialog,
      title: dialog.querySelector('.pf-tree-viewer__title'),
      dates: dialog.querySelector('.pf-tree-viewer__dates'),
      zoom: dialog.querySelector('[data-tree-zoom]'),
      page: dialog.querySelector('[data-tree-page]'),
      stage: dialog.querySelector('.pf-tree-viewer__stage'),
      key: dialog.querySelector('.pf-tree-viewer__key'),
      copy: dialog.querySelector('[data-tree-copy]'),
      opener: null,
      href: '',
      tree: null,
      actual: false,
      // TRUE while the history entry this viewer added is the current one.
      pushed: false,
    };
    dialog.querySelector('[data-tree-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
      document.documentElement.classList.remove('pf-tree-viewer-open');
      // Closed by ×, Escape or a link: take ?tree= back off the address.
      if (parts.pushed) {
        parts.pushed = false;
        history.back();
      }
      else if (new URLSearchParams(location.search).has(PARAM)) {
        history.replaceState(history.state, '', addressWith(null));
      }
      if (parts.opener && parts.opener.isConnected) parts.opener.focus();
    });
    // Copy link: the address, which has ?tree= while a tree is open.
    if (navigator.clipboard && window.isSecureContext) {
      parts.copy.hidden = false;
      let timer = 0;
      parts.copy.addEventListener('click', () => {
        navigator.clipboard.writeText(location.href).then(() => {
          parts.copy.textContent = Drupal.t('Link copied');
          Drupal.announce(Drupal.t('Link copied'));
          clearTimeout(timer);
          timer = setTimeout(() => { parts.copy.textContent = Drupal.t('Copy link'); }, 2000);
        });
      });
    }
    parts.zoom.addEventListener('click', () => {
      parts.actual = !parts.actual;
      fit(parts);
    });
    new ResizeObserver(() => fit(parts)).observe(parts.stage);
    return parts;
  }

  // Fit: scale a wide tree down to the stage; never above its actual size.
  function fit(parts) {
    const tree = parts.tree;
    if (!tree) return;
    const style = getComputedStyle(parts.stage);
    const room = parts.stage.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
    const wide = tree.width > room;
    parts.zoom.hidden = !wide;
    parts.zoom.setAttribute('aria-pressed', String(parts.actual));
    parts.zoom.textContent = parts.actual ? Drupal.t('Fit') : Drupal.t('Actual size');
    const scale = parts.actual || !wide ? 1 : room / tree.width;
    tree.svg.setAttribute('width', (tree.width * scale).toFixed(1));
    tree.svg.setAttribute('height', (tree.height * scale).toFixed(1));
    parts.stage.classList.toggle('is-actual', parts.actual && wide);
  }

  function keyLine(text) {
    const li = el('li');
    const sample = /^solid line\b/i.test(text) ? 'solid' : /^dashed line\b/i.test(text) ? 'dashed' : '';
    if (sample) {
      li.append(el('span', { class: 'pf-tree-viewer__sample is-' + sample, 'aria-hidden': 'true' }));
    }
    li.append(document.createTextNode(text));
    return li;
  }

  // Opens the tree at href. title: the link's text, until the diagram's own
  // title has loaded. opener: the link to focus again on close, or null.
  // push: add a history entry with ?tree= (FALSE when the address already
  // has it: a shared link, or Forward).
  async function open(href, title, opener, push) {
    viewer = viewer || build();
    const parts = viewer;
    const link = opener;
    parts.opener = opener;
    parts.href = href;
    parts.actual = false;
    parts.tree = null;
    if (push) {
      history.pushState({ pfTree: nameOf(href) }, '', addressWith(nameOf(href)));
      parts.pushed = true;
    }
    parts.page.href = href;
    parts.title.textContent = title;
    parts.dates.textContent = '';
    parts.key.replaceChildren();
    parts.zoom.hidden = true;
    parts.stage.replaceChildren(el('p', { class: 'pf-tree-viewer__status', role: 'status' }));
    parts.stage.firstChild.textContent = Drupal.t('Loading the family tree…');
    parts.dialog.classList.toggle('is-still', reduceMotion());
    document.documentElement.classList.add('pf-tree-viewer-open');
    if (!parts.dialog.open) parts.dialog.showModal();

    let cells;
    try {
      cells = await load(href);
    }
    catch (e) {
      cells = null;
    }
    if (parts.href !== href || parts.opener !== link || !parts.dialog.open) return;
    if (!cells || !cells.length) {
      const status = parts.stage.firstChild;
      status.textContent = Drupal.t("This family tree can't be drawn here. ");
      const more = el('a', { href, 'data-no-swup': '' });
      more.textContent = Drupal.t('Open its page');
      status.append(more);
      return;
    }

    const tree = draw(cells, getComputedStyle(parts.stage).fontFamily);
    const heads = tree.blocks.filter((b) => b.kind === 'title');
    if (heads.length) {
      const lines = heads.flatMap((b) => b.lines);
      parts.title.textContent = lines.filter((l) => /^h\d$/.test(l.tag)).map((l) => l.text).join(' ') || parts.title.textContent;
      parts.dates.textContent = lines.filter((l) => !/^h\d$/.test(l.tag)).map((l) => l.text).join(' ');
    }
    tree.blocks.filter((b) => b.kind !== 'title').forEach((block) => {
      if (block.kind === 'key') {
        const [head, ...rest] = block.lines;
        const h = el('h3', { class: 'pf-tree-viewer__key-title' });
        h.textContent = head.text;
        const list = el('ul', { class: 'pf-tree-viewer__key-list' });
        rest.forEach((line) => list.append(keyLine(line.text)));
        parts.key.prepend(h, list);
      }
      else {
        block.lines.forEach((line) => {
          const p = el('p', { class: 'pf-tree-viewer__note' });
          p.textContent = line.text;
          parts.key.append(p);
        });
      }
    });
    // The publications as text, top to bottom, for screen readers.
    const alt = el('ul', { class: 'visually-hidden' });
    tree.boxes.filter((r) => r.text).forEach((r) => {
      const li = el('li');
      li.textContent = r.text;
      alt.append(li);
    });
    // A title of a whole family of papers can run to five lines: set it
    // smaller, so the drawing keeps the screen.
    parts.title.classList.toggle('is-long', parts.title.textContent.length > 60);
    parts.tree = tree;
    parts.stage.replaceChildren(tree.svg, alt);
    parts.stage.setAttribute('aria-label', Drupal.t('Diagram: @title', { '@title': parts.title.textContent }));
    fit(parts);
    // Narrow screens scroll the whole viewer; wide ones the drawing's pane.
    parts.dialog.scrollTo(0, 0);
    parts.stage.scrollTo(0, 0);
  }

  // One listener for every family-tree link on the page, now and after a
  // parish panel is filled in.
  function onClick(event) {
    const link = event.target.closest('a[data-family-tree]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    if (new URL(link.href, location.href).origin !== location.origin || typeof HTMLDialogElement !== 'function') return;
    event.preventDefault();
    open(link.href, link.textContent.trim(), link, true);
  }

  // The tree called name, from the family-tree links on the page, those in
  // the parish panels' <template>s included. Returns { href, title, link }
  // (link: one in the page, to focus on close, or null), or null. The links
  // come live from the newspaper records, so a name none of them has is a
  // mistyped or removed tree: it's ignored and the guide opens as usual.
  function findTree(name) {
    if (!/^[\w.-]+$/.test(name)) return null;
    const links = Array.from(document.querySelectorAll('a[data-family-tree]'));
    document.querySelectorAll('template').forEach((tpl) => links.push(...tpl.content.querySelectorAll('a[data-family-tree]')));
    // Template content resolves no URLs: resolve each href against the page.
    const href = (a) => new URL(a.getAttribute('href'), location.href).href;
    const match = links.find((a) => nameOf(href(a)) === name);
    if (!match) return null;
    return { href: href(match), title: match.textContent.trim(), link: match.isConnected && !match.closest('template') ? match : null };
  }

  // The address's ?tree=, opened without adding history (it's already
  // there). pushed: whether that entry is one this viewer added.
  function fromAddress(pushed) {
    const name = new URLSearchParams(location.search).get(PARAM);
    const tree = name && findTree(name);
    if (!tree || typeof HTMLDialogElement !== 'function') return;
    open(tree.href, tree.title, tree.link, false);
    viewer.pushed = pushed;
  }

  // Back closes the viewer; Forward opens the tree again.
  function onPopState(event) {
    const state = event.state || {};
    if (viewer && viewer.dialog.open && !state.pfTree) {
      viewer.pushed = false;
      viewer.dialog.close();
    }
    else if (state.pfTree && !(viewer && viewer.dialog.open)) {
      fromAddress(true);
    }
  }

  Drupal.behaviors.palefireFamilyTreeViewer = {
    attach() {
      once('pf-tree-viewer', 'html').forEach(() => {
        document.addEventListener('click', onClick);
        window.addEventListener('popstate', onPopState);
        fromAddress(false);
      });
    },
  };
})(Drupal, once);
