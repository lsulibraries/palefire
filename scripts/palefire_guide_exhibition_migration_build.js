// Builds scripts/palefire_guide_exhibition_migration.php (the Exhibition
// Migration Review guide) from docs/exhibition-corrections: _purpose.html,
// _process.html, one <folder>.md per oral history exhibition and
// _special.md, wrapped in palefire_guide_palefire_redesign.php's setup and
// build code. Run from the repo root after changing any of them:
//   node scripts/palefire_guide_exhibition_migration_build.js
const fs = require('fs');
const REPO = process.cwd();
const src = fs.readFileSync('scripts/palefire_guide_palefire_redesign.php', 'utf8').replace(/\r/g, '');
const dir = 'docs/exhibition-corrections';

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

// ---- Exhibitions ------------------------------------------------------------
const OH = 'https://lib.lsu.edu/sites/all/files/oralhistory/presentations/';
const oral = [
  ['mossville', 'mossvilleexhibit'], ['gu272', 'gu272'], ['graphicdesign', 'graphicdesign'], ['klsu', 'klsu'],
  ['storm', 'storm'], ['jf', 'jf'], ['lsuag', 'lsuag'], ['lady', 'lady'], ['rpoe', 'rpoe'], ['dntume', 'dntume'], ['football', 'football'],
];
const sections = [];
for (const [file, folder] of oral) {
  const path = `${dir}/${file}.md`;
  if (!fs.existsSync(path)) { console.log('MISSING', path); continue; }
  let md = fs.readFileSync(path, 'utf8').replace(/\r/g, '');
  const title = md.match(/^#\s+(.*)$/m)[1].replace(/:\s*(migration corrections|corrections for review)\s*$/i, '').replace(/\s*\((jf|LSUAG)\)\s*/i, '');
  md = md.replace(/^#\s+.*\n/m, '');
  // Drop the drafts' own "New page" lines (several give an old address,
  // and some run on to a second line); the live address goes first instead.
  md = md.replace(/^New page:.*(\n(?!\s*\n|[A-Z#|*-]).*)*\n?/m, '');
  const head = `<p>Now at <a href="/v2/oralhistory/exhibitions/${folder}">/v2/oralhistory/exhibitions/${folder}</a>.</p>\n`;
  sections.push({ title, html: head + toHtml(md, 1), family: 'oral' });
}
sections.sort((a, b) => a.title.replace(/^(The|A) /, '').localeCompare(b.title.replace(/^(The|A) /, '')));
const special = fs.readFileSync(`${dir}/_special.md`, 'utf8').replace(/\r/g, '').split(/^### /m).slice(1);
for (const part of special) {
  const title = part.split('\n')[0].trim();
  sections.push({ title, html: toHtml(part.split('\n').slice(1).join('\n'), -1), family: 'special' });
}

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

const doc = `/**
 * @file
 * Guide page: "Exhibition Migration Review", every change made to the text,
 * audio and images of the online exhibitions when they moved to Palefire,
 * for curators to check: what was corrected (old → new), what to verify,
 * and what was written new. As a Modular page in guide mode. Run
 * palefire_guide_setup.php and palefire_guide_blocks_setup.php first.
 *
 *   drush php:script scripts/palefire_guide_exhibition_migration.php
 *   drush php:script scripts/palefire_guide_exhibition_migration.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1. Built as palefire_guide_palefire_redesign.php:
 * this script is the page's source and can be run again. A new page is
 * created UNPUBLISHED; after that each build replaces the intro and
 * sections in a new revision, and stops if the page was edited in Drupal or
 * has a newer draft (force=1 overrides the first).
 *
 * Optional arguments after "--": apply=1, force=1, format=<text format>,
 * path=<alias> (default /v2/guides/exhibition-migration-review).
 *
 * The exhibitions' sections come from docs/exhibition-corrections/*.md,
 * written by the sessions that built each exhibition (tables there are
 * lists here, so no text format has to allow <table>).
 */`;
head = head.replace(/\/\*\*[\s\S]*?\*\//, doc);
head = head.replace("$title = 'Palefire Redesign Project';", "$title = 'Exhibition Migration Review';");
head = head.replace(/^\$intro = .*$/m, "$intro = '<p>Every change made to the online exhibitions when they moved to the new site: corrections to the old text, things to check, wording written new, and how the audio and images were prepared. For curators and editors to review, one exhibition at a time.</p>';");
if (!head.includes('Exhibition Migration Review') || !head.includes("$intro = '<p>Every change")) throw new Error('head replace');
tail = tail.replace(/palefire_guide_palefire_redesign\.php build/g, 'palefire_guide_exhibition_migration.php build');
tail = tail.replace("'/v2/guides/palefire-redesign-project'", "'/v2/guides/exhibition-migration-review'");
tail = tail.replace('Subjects are left empty on purpose: this\n// is a project record, not a research guide.', 'Subjects are left empty on purpose: this\n// is a review list, not a research guide.');
if (!tail.includes('exhibition-migration-review') || !tail.includes('palefire_guide_exhibition_migration.php build')) throw new Error('tail replace');

const process_html = fs.readFileSync(dir + '/_process.html', 'utf8').replace(/\r/g, '').trim();
let body = '// Sections. ----------------------------------------------------------------\n\n';
body += `$section('How to use this page', [\n  $text(NULL, ${nowdoc(fs.readFileSync(dir + '/_purpose.html', 'utf8').replace(/\r/g, '').trim())}),\n]);\n\n`;
body += `$section('How the exhibitions were moved', [\n  $text(NULL, ${nowdoc(process_html)}),\n]);\n\n`;
for (const s of sections) {
  body += `$section(${phpString(s.title)}, [\n  $text(NULL, ${nowdoc(s.html)}),\n]);\n\n`;
}
fs.writeFileSync('scripts/palefire_guide_exhibition_migration.php', head + body + tail);
console.log('sections:', sections.map((s) => s.title).join(' | '));
