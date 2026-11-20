// Builds scripts/palefire_guide_exhibition_migration.php (the Exhibition
// Migration Review guide) from docs/exhibition-corrections: _purpose.html,
// _process.html and one <folder>.md per oral history exhibition, wrapped
// in palefire_guide_palefire_redesign.php's setup and build code. Run from the repo root after changing any of them:
//   node scripts/palefire_guide_exhibition_migration_build.js
// With "special" it builds scripts/palefire_guide_special_exhibitions.php
// (Special Collections Exhibitions) from docs/special-exhibition-corrections
// instead: the same files, plus _biographies.html.
//   node scripts/palefire_guide_exhibition_migration_build.js special
const fs = require('fs');
const REPO = process.cwd();
const src = fs.readFileSync('scripts/palefire_guide_palefire_redesign.php', 'utf8').replace(/\r/g, '');
const SPECIAL = process.argv[2] === 'special';
const dir = SPECIAL ? 'docs/special-exhibition-corrections' : 'docs/exhibition-corrections';
const OUT = SPECIAL ? 'scripts/palefire_guide_special_exhibitions.php' : 'scripts/palefire_guide_exhibition_migration.php';
const SCRIPT = OUT.replace('scripts/', '');
const TITLE = SPECIAL ? 'Special Collections Exhibitions' : 'Exhibition Migration Review';
const PATH = SPECIAL ? '/v2/guides/special-collections-exhibitions' : '/v2/guides/exhibition-migration-review';

// ---- Markdown subset to HTML ---------------------------------------------
const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const inline = (s) => {
  const codes = [];
  s = s.replace(/`([^`]+)`/g, (m, c) => { codes.push(c); return '\u0000' + (codes.length - 1) + '\u0000'; });
  s = esc(s);
  s = s.replace(/(^|[\s(])(https?:\/\/[^\s<)]*[^\s<).,;:])/g, '$1<a href="$2">$2</a>');
  s = s.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (m, t, u) => '<a href="' + u.replace(/"/g, '%22') + '">' + t + '</a>');
  s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
  s = s.replace(/(^|[^\w*])\*([^*\s][^*]*?)\*(?!\w)/g, '$1<em>$2</em>');
  s = s.replace(/(^|[^\w])_([^_\s][^_]*?)_(?!\w)/g, '$1<em>$2</em>');
  s = s.replace(/\u0000(\d+)\u0000/g, (m, i) => '<code>' + esc(codes[+i]) + '</code>');
  return s;
};
const cells = (line) => line.trim().replace(/^\||\|$/g, '').split(/(?<!\\)\|/).map((c) => c.trim().replace(/\\\|/g, '|'));

function toHtml(md, shift) {
  const lines = md.replace(/\r/g, '').split('\n');
  const out = [];
  let para = [];
  const lists = []; // stack of {type, indent}
  // A line that starts "Label: " in a paragraph starts its own line.
  const label = /^[A-Z][A-Za-z ]{0,24}:\s/;
  const flushPara = () => {
    if (para.length) {
      out.push('<p>' + para.map((l, k) => (k ? (label.test(l) ? '<br>' : ' ') : '') + inline(l)).join('') + '</p>');
      para = [];
    }
  };
  const closeLists = (to = 0) => { while (lists.length > to) { const l = lists.pop(); out.push('</li></' + l.type + '>'); } };
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    if (/^\s*$/.test(line)) { flushPara(); continue; }
    if (/^\s*(-{3,}|\*{3,})\s*$/.test(line)) { flushPara(); closeLists(); continue; }
    const h = line.match(/^(#{1,6})\s+(.*)$/);
    if (h) {
      flushPara(); closeLists();
      const level = Math.min(6, h[1].length + shift);
      out.push(`<h${level}>` + inline(h[2].replace(/^\d+\.\s+/, '')) + `</h${level}>`);
      continue;
    }
    if (/^\s*\|/.test(line) && i + 1 < lines.length && /^\s*\|?\s*:?-{2,}/.test(lines[i + 1])) {
      flushPara(); closeLists();
      const head = cells(line);
      i += 2;
      const rows = [];
      while (i < lines.length && /^\s*\|/.test(lines[i])) { rows.push(cells(lines[i])); i++; }
      i--;
      const oldNew = head.length === 3 && /old|before|was/i.test(head[1]) && /new|after|now/i.test(head[2]);
      out.push('<ul>');
      for (const r of rows) {
        let item;
        if (oldNew) item = '<strong>' + inline(r[0]) + '</strong>: ' + inline(r[1] || '') + ' → ' + inline(r[2] || '');
        else if (r.length === 2) item = '<strong>' + inline(r[0]) + '</strong>: ' + inline(r[1]);
        else if (r.length >= 3) item = '<strong>' + inline(r[0]) + '</strong>: ' + r.slice(1).map((c, k) => (head[k + 1] ? inline(head[k + 1]) + ' ' : '') + inline(c)).join('; ');
        else item = inline(r.join(' '));
        out.push('<li>' + item + '</li>');
      }
      out.push('</ul>');
      continue;
    }
    const li = line.match(/^(\s*)([-*]|\d+\.)\s+(.*)$/);
    if (li) {
      flushPara();
      const indent = li[1].length;
      const type = /\d/.test(li[2]) ? 'ol' : 'ul';
      const top = lists[lists.length - 1];
      if (!top || indent > top.indent) {
        out.push('<' + type + '><li>' + inline(li[3]));
        lists.push({ type, indent });
      }
      else {
        while (lists.length && indent < lists[lists.length - 1].indent) { const l = lists.pop(); out.push('</li></' + l.type + '>'); }
        out.push('</li><li>' + inline(li[3]));
      }
      continue;
    }
    if (lists.length && /^\s+\S/.test(line)) { out[out.length - 1] += ' ' + inline(line.trim()); continue; }
    if (/^>\s?/.test(line)) { flushPara(); closeLists(); out.push('<blockquote><p>' + inline(line.replace(/^>\s?/, '')) + '</p></blockquote>'); continue; }
    closeLists();
    para.push(line.trim());
  }
  flushPara(); closeLists();
  return out.join('\n');
}

// ---- Corrections as tables -----------------------------------------------
// A list item "Where: “old” → “new” (note)" becomes a table row: the place
// as the row header, the old text in <del>, the new in <ins>, a closing
// note in <em> after it. Runs of such items in a list become one table
// (Where | Before | After; no Where column when no row has one), and other
// items stay list items around it. Text formats drop classes, so <del> and
// <ins> mark the table for _guide-corrections.scss; if the format strips
// them the text is kept and the table still reads.
const QUOTE = /^[“"]([\s\S]*)[”"]$/;
const unquote = (s) => {
  s = s.trim();
  const m = s.match(QUOTE);
  // Leave “a” / “b” and “a”, “b” as they are.
  return m && !/[”"]\s*(?:[,/;]|and|or)\s*[“"]/.test(m[1]) ? m[1] : s;
};
// Several pairs in one item (“a” → “b”; “c” → “d”) give a row each, with
// the same place; an item that doesn't split cleanly stays a list item.
const rowsOf = (item) => {
  let text = item.trim();
  let where = '';
  const w = text.match(/^((?:(?![“"”])[\s\S])*?):\s+([\s\S]*)$/);
  if (w && w[1].length <= 160 && !w[1].includes('→') && !/<(?!\/?strong>)/.test(w[1])) {
    where = w[1].replace(/<\/?strong>/g, '').trim();
    text = w[2];
  }
  const pairs = text.split(/(?<=[”"](?:\s*\([^()]*\))?)\s*;\s*(?=[“"])/);
  const rows = pairs.map((p) => rowOf(p, where));
  return rows.every(Boolean) ? rows : null;
};
const rowOf = (item, where) => {
  const sides = item.trim().split(' → ');
  if (sides.length !== 2) return null;
  let [left, right] = sides;
  // A closing parenthesis (with any nested pairs) on either side is a note:
  // where the old text was (“old menu”), or how often, or what to check.
  const split = (text) => {
    text = text.trim().replace(/[.;]$/, '');
    if (text.endsWith(')')) {
      let depth = 0;
      for (let i = text.length - 1; i >= 0; i--) {
        if (text[i] === ')') depth++;
        else if (text[i] === '(' && --depth === 0) {
          if (i > 0) return [text.slice(0, i).trim().replace(/([”"])[.;]$/, '$1'), text.slice(i + 1, -1).trim()];
          break;
        }
      }
    }
    return [text.replace(/([”"])[.;]$/, '$1'), ''];
  };
  const [before, beforeNote] = split(left);
  const [after, note] = split(right);
  if (!before || !after) return null;
  return { where, before: unquote(before), beforeNote, after: unquote(after), note };
};
const table = (rows) => {
  const withWhere = rows.some((r) => r.where);
  const head = (withWhere ? '<th scope="col">Where</th>' : '') + '<th scope="col">Before</th><th scope="col">After</th>';
  const body = rows.map((r) => '<tr>'
    + (withWhere ? `<th scope="row">${r.where}</th>` : '')
    + `<td><del>${r.before}</del>${r.beforeNote ? ` <em>${r.beforeNote}</em>` : ''}</td>`
    // "—" in a draft means the text was taken out: the cell says so.
    + (r.after === '—' ? '<td><em>Removed</em>' : `<td><ins>${r.after}</ins>`)
    + `${r.note ? ` <em>${r.note}</em>` : ''}</td></tr>`).join('\n');
  return `<table>\n<thead><tr>${head}</tr></thead>\n<tbody>\n${body}\n</tbody>\n</table>`;
};
let tableRows = 0;
const corrections = (html) => html.replace(/<ul>((?:(?!<\/?ul>)[\s\S])*?)<\/ul>/g, (all, inner) => {
  const items = inner.trim().replace(/^<li>/, '').replace(/<\/li>$/, '').split(/<\/li>\s*<li>/);
  const parts = [];
  for (const item of items) {
    const rows = rowsOf(item);
    const last = parts[parts.length - 1];
    if (rows && last && last.rows) last.rows.push(...rows);
    else if (rows) parts.push({ rows });
    else if (last && last.items) last.items.push(item);
    else parts.push({ items: [item] });
  }
  if (!parts.some((p) => p.rows)) return all;
  return parts.map((p) => {
    if (p.items) return '<ul><li>' + p.items.join('</li>\n<li>') + '</li></ul>';
    tableRows += p.rows.length;
    return table(p.rows);
  }).join('\n');
});

// ---- Exhibitions ------------------------------------------------------------
const OH = 'https://lib.lsu.edu/sites/all/files/oralhistory/presentations/';
const oral = SPECIAL
  ? [['aves', 'aves'], ['audubon', 'audubon'], ['occult', 'occult'], ['unionartgallery', 'unionartgallery', 'unpublished'], ['fpoc', 'fpoc', 'unpublished']]
  : [
    ['mossville', 'mossvilleexhibit'], ['gu272', 'gu272'], ['graphicdesign', 'graphicdesign'], ['klsu', 'klsu'],
    ['storm', 'storm'], ['jf', 'jf'], ['lsuag', 'lsuag'], ['lady', 'lady'], ['rpoe', 'rpoe'], ['dntume', 'dntume'], ['football', 'football'],
  ];
const base = SPECIAL ? '/v2/special/exhibitions/' : '/v2/oralhistory/exhibitions/';
const sections = [];
for (const [file, folder, state] of oral) {
  const path = `${dir}/${file}.md`;
  if (!fs.existsSync(path)) { console.log('MISSING', path); continue; }
  let md = fs.readFileSync(path, 'utf8').replace(/\r/g, '');
  const title = md.match(/^#\s+(.*)$/m)[1].replace(/:\s*(migration corrections|corrections for review)\s*$/i, '').replace(/\s*\((jf|LSUAG)\)\s*/i, '');
  md = md.replace(/^#\s+.*\n/m, '');
  // Drop the drafts' own "New page" lines (several give an old address,
  // and some run on to a second line); the live address goes first instead.
  md = md.replace(/^New page:.*(\n(?!\s*\n|[A-Z#|*-]).*)*\n?/m, '');
  // A page built but not published yet says so (its link works for editors).
  const head = state === 'unpublished'
    ? `<p>Built at <a href="${base}${folder}">${base}${folder}</a>, not published yet.</p>\n`
    : `<p>Now at <a href="${base}${folder}">${base}${folder}</a>.</p>\n`;
  sections.push({ title, html: head + corrections(toHtml(md, 1)), family: SPECIAL ? 'special' : 'oral' });
}
sections.sort((a, b) => a.title.replace(/^(The|A) /, '').localeCompare(b.title.replace(/^(The|A) /, '')));

// ---- PHP ---------------------------------------------------------------------
const phpString = (s) => "'" + s.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
const nowdoc = (html) => {
  if (/^HTML$/m.test(html)) throw new Error('HTML line in content');
  return "<<<'HTML'\n" + html + '\nHTML';
};

const headEnd = src.indexOf('// Sections. ----');
const tailStart = src.indexOf('// End of sections. ----');
let head = src.slice(0, headEnd);
let tail = src.slice(tailStart);

const what = SPECIAL
  ? `the text,
 * layout and images of the Special Collections online exhibitions (AVES,
 * Audubon, Occult, Union Art Gallery, Free People of Color) when they moved to Palefire, and the
 * open proposals for AVES's biographies,`
  : `the text,
 * audio and images of the eleven oral history exhibitions when they moved
 * to Palefire,`;
const doc = `/**
 * @file
 * Guide page: "${TITLE}", every change made to ${what}
 * for curators to check: what was corrected (old → new), what to verify,
 * and what was written new. As a Modular page in guide mode. Run
 * palefire_guide_setup.php and palefire_guide_blocks_setup.php first.
 *
 *   drush php:script scripts/${SCRIPT}
 *   drush php:script scripts/${SCRIPT} -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. Built as palefire_guide_palefire_redesign.php:
 * this script is the page's source and can be run again. A new page is
 * created UNPUBLISHED; after that each build replaces the intro and
 * sections in a new revision, and stops if the page was edited in Drupal or
 * has a newer draft (force=1 overrides the first).
 *
 * Optional arguments after "--": apply=1, force=1, format=<text format>,
 * path=<alias> (default ${PATH}).
 *
 * The exhibitions' sections come from ${dir}/*.md,
 * written by the sessions that built each exhibition. Each old → new
 * correction becomes a table row (Where | Before | After), the old text in
 * <del> and the new in <ins>, styled by _guide-corrections.scss.
 */`;
head = head.replace(/\/\*\*[\s\S]*?\*\//, doc);
head = head.replace("$title = 'Palefire Redesign Project';", () => `$title = '${TITLE}';`);
const INTRO = SPECIAL
  ? '<p>How the online exhibitions of LSU Libraries Special Collections moved to the new site: what changed on each page, corrections to the old text, things to check, wording written new, and what is proposed next for biographies. For curators and editors to review, one exhibition at a time.</p>'
  : '<p>Every change made to the oral history exhibitions of the T. Harry Williams Center when they moved to the new site: corrections to the old text, things to check, wording written new, and how the audio and images were prepared. For curators and editors to review, one exhibition at a time.</p>';
head = head.replace(/^\$intro = .*$/m, () => "$intro = '" + INTRO + "';");
if (!head.includes("$title = '" + TITLE) || !head.includes("$intro = '" + INTRO)) throw new Error('head replace');
tail = tail.replace(/palefire_guide_palefire_redesign\.php build/g, () => SCRIPT + ' build');
tail = tail.replace("'/v2/guides/palefire-redesign-project'", () => `'${PATH}'`);
tail = tail.replace('Subjects are left empty on purpose: this\n// is a project record, not a research guide.', 'Subjects are left empty on purpose: this\n// is a review list, not a research guide.');
// The corrections tables need <table>, <del>, <ins> and <em>: warn in the
// dry run if the format strips them (the text stays, unstyled).
const tagCheck = `$html_filter = $formats[$format]->filters('filter_html');
if ($html_filter && $html_filter->status) {
  $allowed = (string) ($html_filter->settings['allowed_html'] ?? '');
  foreach (['table', 'thead', 'del', 'ins', 'em'] as $tag) {
    if (!preg_match('/<' . $tag . '[\\s>]/', $allowed)) {
      echo "Warning: the $format format strips <$tag>, so the corrections tables lose their styling. Allow it in the format." . PHP_EOL;
    }
  }
}
`;
const formatEnd = "  throw new \\RuntimeException(\"Text format '$format' not found.\");\n}\n";
if (!head.includes(formatEnd)) throw new Error('format check not found');
head = head.replace(formatEnd, () => formatEnd + tagCheck);
if (!tail.includes(PATH) || !tail.includes(SCRIPT + ' build')) throw new Error('tail replace');

const process_html = fs.readFileSync(dir + '/_process.html', 'utf8').replace(/\r/g, '').trim();
let body = '// Sections. ----------------------------------------------------------------\n\n';
body += `$section('How to use this page', [\n  $text(NULL, ${nowdoc(fs.readFileSync(dir + '/_purpose.html', 'utf8').replace(/\r/g, '').trim())}),\n]);\n\n`;
body += `$section('How the exhibitions were moved', [\n  $text(NULL, ${nowdoc(process_html)}),\n]);\n\n`;
for (const s of sections) {
  body += `$section(${phpString(s.title)}, [\n  $text(NULL, ${nowdoc(s.html)}),\n]);\n\n`;
}
if (fs.existsSync(dir + '/_biographies.html')) {
  body += `$section('Biographies', [\n  $text(NULL, ${nowdoc(fs.readFileSync(dir + '/_biographies.html', 'utf8').replace(/\r/g, '').trim())}),\n]);\n\n`;
}
fs.writeFileSync(OUT, head + body + tail);
console.log('table rows:', tableRows); console.log('sections:', sections.map((s) => s.title).join(' | '));
