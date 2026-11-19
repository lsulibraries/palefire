/**
 * @file
 * Builds data/mossville-interviews.json, the Mossville History Project's
 * full interviews (ExhibitInterviews, "[interviews:mossville]"), from the
 * 2017 directory page (_reference/Oralhistory Exhibitions/presentations/
 * mossvilledirectory, online at /sites/all/files/oralhistory/presentations/
 * mossvilledirectory).
 *
 * The directory's links were copied card to card, so many point at another
 * narrator's file. Each tape here takes its files from the folders by its
 * own tape number instead, and the card's details are corrected:
 * - Ronnie Banks's and John Bernard's cards showed another tape's number
 *   (T4426, T4673); their files are T4403 and T4427.
 * - Vera Payne's second tape was Larry Payne's (T4421); hers is T4483.
 * - "Bennet" is Bennett; names and interviewers as the exhibition gives
 *   them (Doug Mungin, Jennifer A. Cramer, Edward “Butch” Lemelle Jr.).
 * - Three interviews in the folders had no card: Theresa Lee (T4623,
 *   T4674), Judy Montgomery-Gauthia and Elaine Robinson (T4569) and Barbara
 *   Payne (T4529).
 * A tape with no file for a part simply has no link for it.
 *
 * Each tape's length comes from its MP3 (the Xing frame count, else the
 * size at the first frame's bit rate). Each portrait is named as its web copy
 * (lowercase, "_" for other characters, .jpg): build/preview/
 * resize-portraits.js makes those copies from the card's photo, and
 * scripts/palefire_exhibit_mossville_interviews.php imports them as media.
 *
 * Usage: node scripts/palefire_exhibit_mossville_interviews_build.js
 */
const fs = require('fs');
const path = require('path');

const REPO = path.resolve(__dirname, '..');
const DIR = path.join(REPO, '_reference/Oralhistory Exhibitions/presentations/mossvilledirectory');
const OUT = path.join(REPO, 'data/mossville-interviews.json');

const html = fs.readFileSync(path.join(DIR, 'index.html'), 'utf8');
const audioFiles = fs.readdirSync(path.join(DIR, 'audio')).filter((f) => /\.mp3$/i.test(f) && !/edited/i.test(f));
const transcriptFiles = fs.readdirSync(path.join(DIR, 'transcript')).filter((f) => /\.pdf$/i.test(f));
const portraits = fs.readdirSync(path.join(DIR, 'thumbnails'));

const field = (p, cls) => ((p.match(new RegExp('class="' + cls + '">([^<]*)<')) || [])[1] || '').trim();
const tapeOf = (file) => ((file || '').match(/_(T\d{4})(?:_|\.)/) || [])[1];

// The file for a tape, the one named like the transcript when there are
// two spellings (Andrus_Shirley_and_Marshall_Carolyn / ..._Carolyn_Marshall).
function fileFor(list, tape, like) {
  const hits = list.filter((f) => new RegExp('_' + tape + '\\.').test(f)).sort();
  if (!hits.length) {
    return null;
  }
  const stem = like ? like.replace(/\.[^.]+$/, '') : '';
  return hits.find((f) => f.replace(/\.[^.]+$/, '') === stem) || hits.sort((a, b) => b.length - a.length)[0];
}

// An MP3's length in seconds: the Xing/Info frame count when there is one,
// else the bytes after the tags at the first frame's bit rate.
function mp3Seconds(file) {
  const b = fs.readFileSync(file);
  let i = 0;
  if (b.toString('latin1', 0, 3) === 'ID3') {
    i = 10 + ((b[6] << 21) | (b[7] << 14) | (b[8] << 7) | b[9]);
  }
  while (i < b.length - 4 && !(b[i] === 0xff && (b[i + 1] & 0xe0) === 0xe0)) {
    i++;
  }
  const version = (b[i + 1] >> 3) & 3; // 3: MPEG 1, 2: MPEG 2, 0: MPEG 2.5.
  const rates = version === 3
    ? [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320]
    : [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160];
  const kbps = rates[b[i + 2] >> 4];
  const sampleRate = [[11025, 12000, 8000], null, [22050, 24000, 16000], [44100, 48000, 32000]][version][(b[i + 2] >> 2) & 3];
  const samples = version === 3 ? 1152 : 576;
  const xing = b.indexOf('Xing', i);
  const info = b.indexOf('Info', i);
  const tag = [xing, info].filter((x) => x > 0 && x < i + 200)[0];
  if (tag && (b.readUInt32BE(tag + 4) & 1)) {
    return Math.round(b.readUInt32BE(tag + 8) * samples / sampleRate);
  }
  return Math.round((b.length - i) * 8 / (kbps * 1000));
}

const NAMES = {
  'Delma and Christine Bennet': 'Delma and Christine Bennett',
  'Daren Dotson, Sr.': 'Daren Dotson Sr.',
  'Kevin Fondel': 'Kevin Fondel Sr.',
  'Edward Butch Lemelle Jr': 'Edward “Butch” Lemelle Jr.',
  'Huber Smith': 'Huber “Mickey” Smith Sr.',
};
const PEOPLE = { 'Douglas Mungin': 'Doug Mungin', 'Jennifer Abraham Cramer': 'Jennifer A. Cramer' };
const person = (s) => s.split(/,\s*/).map((n) => PEOPLE[n] || n);

const cards = html.split('<div class="fullPlayer">').slice(1).map((p) => {
  const name = field(p, 'EEname');
  const thumb = (p.match(/thumbnails\/([^"]+)"/) || [])[1];
  // The card's thumbnail by its name in any case (the page asks for .jpg,
  // the folder has .JPG); the stand-in "generic.jpg" is no portrait.
  const portrait = thumb && !/^generic\./i.test(thumb) ? portraits.find((f) => f.toLowerCase() === thumb.toLowerCase()) || null : null;
  const tapes = p.split('<div class="tapeNumber">').slice(1).map((t) => {
    const abs = (t.match(/abstracts\/mossville\/([^"]+)"/) || [])[1] || null;
    const shown = t.slice(0, t.indexOf('<')).trim();
    // The summary names the tapes it covers; a card number it doesn't name
    // was copied from another card.
    const tape = abs && !abs.includes(shown.slice(1)) ? tapeOf(abs) || shown : shown;
    return { tape, summary: abs };
  });
  return {
    name: NAMES[name] || name,
    interviewers: person(field(p, 'ERname')),
    accessions: field(p, 'collNumber').split(/,\s*/).filter(Boolean),
    portrait,
    tapes,
  };
});

// Vera Payne's second tape: hers, not Larry's.
const vera = cards.find((c) => c.name === 'Vera Payne');
vera.tapes = vera.tapes.map((t) => (t.tape === 'T4421' ? { tape: 'T4483', summary: 'Payne_Vera_T4483_abs.pdf' } : t));

// Interviews in the folders with no card, with the details from their
// summaries (on the server, though the page never linked them).
cards.push(
  { name: 'Theresa Lee', interviewers: ['Chelsea Arseneault'], accessions: ['4700.2666'], portrait: 'Lee_Theresa_5.jpg', tapes: [{ tape: 'T4623', summary: 'Lee_Theresa_T4623_T4674_abs.pdf', with: 'With Dawn Holman' }, { tape: 'T4674', summary: 'Lee_Theresa_T4623_T4674_abs.pdf' }] },
  { name: 'Judy Montgomery-Gauthia and Elaine Robinson', interviewers: ['Chelsea Arseneault'], accessions: ['4700.2615'], portrait: 'Montgomery-Gauthia_left_and_Robinson_right.JPG', tapes: [{ tape: 'T4569', summary: 'Montgomery-Gauthia_Judy_and_Robinson_Elaine_T4569_abs.pdf' }] },
  { name: 'Barbara Payne', interviewers: ['Jamie Digilormo'], accessions: ['4700.2582'], portrait: null, tapes: [{ tape: 'T4529', summary: 'Payne_Barbara_T4529_abs.pdf' }] },
);

let seconds = 0;
const narrators = cards.map((c) => {
  const tapes = c.tapes.map((t) => {
    const transcript = fileFor(transcriptFiles, t.tape);
    const audio = fileFor(audioFiles, t.tape, transcript);
    const length = audio ? mp3Seconds(path.join(DIR, 'audio', audio)) : null;
    seconds += length || 0;
    const out = { tape: t.tape, seconds: length, audio, transcript, summary: t.summary };
    if (t.with) {
      out.note = t.with;
    }
    return out;
  });
  // Filed by the surname the files use (LaTour-Pitre, Montgomery-Gauthia).
  const sort = (tapes.find((t) => t.transcript || t.audio) || {}).transcript || (tapes[0].audio || c.name);
  return { name: c.name, sort: sort.split('_')[0] + ' ' + c.name, interviewers: c.interviewers, accessions: c.accessions, portrait: c.portrait, tapes };
});
narrators.sort((a, b) => a.sort.localeCompare(b.sort));
const webName = (f) => f.replace(/\.[^.]+$/, '').toLowerCase().replace(/[^a-z0-9-]+/g, '_') + '.jpg';
const sources = {};
narrators.forEach((n) => {
  if (n.portrait) {
    sources[webName(n.portrait)] = n.portrait;
    n.portrait = webName(n.portrait);
  }
});
narrators.forEach((n) => delete n.sort);

const data = {
  _about: 'Built by scripts/palefire_exhibit_mossville_interviews_build.js from the 2017 Mossville directory page; do not edit by hand.',
  files: 'https://lib.lsu.edu/sites/all/files/oralhistory/presentations/mossvilledirectory',
  summaries: 'https://lib.lsu.edu/sites/all/files/oralhistory/abstracts/mossville',
  narrators,
};
fs.writeFileSync(OUT, JSON.stringify(data, null, 2) + '\n');

const tapes = narrators.flatMap((n) => n.tapes);
console.log(`${narrators.length} narrators, ${tapes.length} tapes, ${Math.round(seconds / 3600)} hours`);
console.log('no audio:', tapes.filter((t) => !t.audio).map((t) => t.tape).join(' ') || 'none');
console.log('no transcript:', tapes.filter((t) => !t.transcript).map((t) => t.tape).join(' ') || 'none');
console.log('no summary:', tapes.filter((t) => !t.summary).map((t) => t.tape).join(' ') || 'none');
console.log('no portrait:', narrators.filter((n) => !n.portrait).map((n) => n.name).join(', '));
// The photos to resize, web name: source name (build/preview/resize-portraits.js).
fs.writeFileSync(path.join(DIR, 'portraits.json'), JSON.stringify(sources, null, 2) + '\n');
console.log(Object.keys(sources).length, 'portraits listed in', path.join(DIR, 'portraits.json'));
