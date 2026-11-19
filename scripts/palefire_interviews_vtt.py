"""Time the Mossville full interviews' transcripts to their tapes.

Writes one WebVTT file per tape (transcripts/mossville-interviews/T4402.vtt)
for the Full Interviews lightbox. Each cue is one phrase of the typed
transcript, in the transcript's own words and order: Whisper only supplies
the timing, lined up as palefire_oral_history_vtt.py does for the clips,
and the transcript wins wherever the two disagree. A new speaker opens a
cue voiced <v NAME>, so the page can show the transcript's speaker labels
and paragraphs.

Dev-only, never deployed. Needs Python 3.10+, ffmpeg on PATH,
faster-whisper and pypdf:

  venv/Scripts/python scripts/palefire_interviews_vtt.py \
    --dir "_reference/Oralhistory Exhibitions/presentations/mossvilledirectory"

--dir is the 2017 directory as saved (audio/ and transcript/ inside); the
tapes come from data/mossville-interviews.json. Whisper's words are cached
beside each MP3 (.words.json), so re-runs only re-align. --transcribe-only
caches the words and writes nothing else; --text-only writes each tape's
extracted text (scripts/data/mossville-interviews-text/T4402.txt) to check
before timing.
"""

import argparse
import json
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import palefire_oral_history_vtt as shared  # noqa: E402

REPO = Path(__file__).resolve().parent.parent
DATA = REPO / 'data' / 'mossville-interviews.json'
OUT = REPO / 'transcripts' / 'mossville-interviews'
TEXT = REPO / 'scripts' / 'data' / 'mossville-interviews-text'


def tapes():
    """Yield (tape number, audio file, transcript file, seconds) in the gallery's order."""
    for person in json.loads(DATA.read_text(encoding='utf-8'))['narrators']:
        for t in person['tapes']:
            yield t['tape'], t.get('audio'), t.get('transcript'), t.get('seconds') or 0


LABEL = re.compile(r'^((?:[A-Z][A-Z.,\'’“”"\-]*\s)*[A-Z][A-Z.,\'’“”"\-]*(?:\s[SJ]r\.)?):\s*')
MARK = re.compile(r'\s*\[(\d{1,2}(?::\d\d){1,2})\]\s*')


def page_lines(page, first):
    """The page's own lines: no running head (name, accession, tape, page
    number), no copyright notice, and on the first page no cover block
    (interviewee, interviewer, transcriber, auditor, editor)."""
    lines = page.extract_text().replace('\r', '').split('\n')
    out, skip = [], first
    notice = False
    for i, line in enumerate(lines):
        bare = line.strip()
        if bare.startswith('Copyright is retained'):
            notice = True
        if notice:
            # The notice runs to "... as well." (page one) or ".../williams".
            if re.search(r'(as well\.|special/williams)\s*$', bare):
                notice = False
            continue
        if not first and i < 2 and re.search(r'\bTape\s*\d+\s+\d+\s*$', bare):
            continue
        if skip:
            # The cover block ends at the first speaker line or bracketed note.
            if LABEL.match(bare) or bare.startswith('['):
                skip = False
            else:
                continue
        out.append(line.rstrip())
    return out


def paragraphs(pdf):
    """The transcript as paragraphs, in its own words and order. Lines are
    joined with a space (or none after a word broken by a hyphen), and a
    paragraph broken by a page runs on into the next page."""
    from pypdf import PdfReader
    paras, current = [], []
    for n, page in enumerate(PdfReader(str(pdf)).pages):
        lines = page_lines(page, n == 0)
        # A page that opens with a speaker label or a note starts a paragraph.
        head = next((l.strip() for l in lines if l.strip()), '')
        if current and (LABEL.match(head) or head.startswith('[')):
            paras.append(current)
            current = []
        for line in lines:
            bare = line.strip()
            if not bare:
                if current:
                    paras.append(current)
                    current = []
                continue
            current.append(bare)
    if current:
        paras.append(current)
    out = []
    for p in paras:
        text = p[0]
        for line in p[1:]:
            text += line if re.search(r'[a-z]-$', text) and re.match(r'[a-z]', line) else ' ' + line
        out.append(re.sub(r'\s+', ' ', text).strip())
    return out


def transcribe(model, clip):
    cache = clip.with_suffix('.words.json')
    if cache.exists():
        return json.loads(cache.read_text(encoding='utf-8'))
    import subprocess
    import numpy
    pcm = subprocess.run(['ffmpeg', '-v', 'error', '-i', str(clip), '-ac', '1', '-ar', '16000', '-f', 'f32le', '-'], capture_output=True, check=True).stdout
    # Long tapes: no prompt carried between windows, so one mishearing can't
    # run on; voice detection skips the silences Whisper would invent words in.
    segments, _ = model.transcribe(numpy.frombuffer(pcm, numpy.float32), language='en', word_timestamps=True, beam_size=5,
                                   condition_on_previous_text=False, vad_filter=True)
    words = [{'w': w.word.strip(), 's': round(w.start, 3), 'e': round(w.end, 3)} for seg in segments for w in seg.words]
    cache.write_text(json.dumps(words), encoding='utf-8')
    return words


def structure(paras):
    """Split the paragraphs into speaker, cue texts and anchors.

    Returns ([{'who': label or None, 'cues': [text]}], [(token index,
    seconds)]). Cue texts are the paragraph's words in order with the
    transcriber's [mm:ss] marks taken out; each mark becomes an anchor at
    the word it stood before. A paragraph with no speaker label continues
    the turn (or is an editor's note like [End Tape 4403.])."""
    blocks, anchors, count = [], [], 0
    for para in paras:
        m = LABEL.match(para)
        who = m.group(1) if m else None
        body = para[m.end():] if m else para
        pieces = MARK.split(body)
        # pieces alternate text, time, text, time, ...
        clean = []
        for k, piece in enumerate(pieces):
            if k % 2:
                parts = [int(x) for x in piece.split(':')]
                seconds = parts[-1] + 60 * parts[-2] + (3600 * parts[-3] if len(parts) == 3 else 0)
                before = ' '.join(clean)
                anchors.append((count + sum(1 for w in before.split(' ') if shared.norm(w)), float(seconds)))
            elif piece.strip():
                clean.append(piece.strip())
        text = re.sub(r'\s+', ' ', ' '.join(clean)).strip()
        if not text:
            continue
        cues = [p for s in shared.sentences(text) for p in shared.phrases(s)]
        blocks.append({'who': who, 'cues': cues})
        count += sum(1 for w in text.split(' ') if shared.norm(w))
    return blocks, anchors


def align(blocks, anchors, words, length=0.0):
    """Time every cue. The transcript is cut at its own [mm:ss] marks, and
    each stretch is lined up only with what Whisper heard around that
    stretch's time, so one long gap can't pull the rest out of step.
    Returns (timings per cue in order, share of words matched, median
    seconds between a mark and the word Whisper heard there)."""
    import difflib
    tokens, owner = [], []
    n = 0
    for b in blocks:
        for text in b['cues']:
            for w in text.split(' '):
                if shared.norm(w):
                    tokens.append(shared.norm(w))
                    owner.append(n)
            n += 1
    end_audio = max(words[-1]['e'] if words else 0.0, length or 0.0)
    # Marks in order only; a mark earlier than the one before is a typo.
    bounds, last = [(0, 0.0)], 0.0
    for at, sec in anchors:
        if sec >= last and at >= bounds[-1][0] and sec <= end_audio + 5:
            bounds.append((at, sec))
            last = sec
    bounds.append((len(tokens), max(end_audio, last)))
    times = [None] * len(tokens)
    heard_at = set()
    matched = 0
    for (a, ta), (b, tb) in zip(bounds, bounds[1:]):
        if b <= a:
            continue
        window = [i for i, w in enumerate(words) if ta - 8 <= w['s'] <= tb + 8]
        heard = [shared.norm(words[i]['w']) for i in window]
        sm = difflib.SequenceMatcher(None, tokens[a:b], heard, autojunk=False)
        for x, y, size in sm.get_matching_blocks():
            for k in range(size):
                w = words[window[y + k]]
                times[a + x + k] = (w['s'], w['e'])
                heard_at.add(a + x + k)
                matched += 1
        # Unheard words spread evenly between their heard neighbours, or the marks.
        known = [i for i in range(a, b) if times[i]]
        for i in range(a, b):
            if times[i]:
                continue
            prev = max((k for k in known if k < i), default=None)
            nxt = min((k for k in known if k > i), default=None)
            lo = times[prev][1] if prev is not None else ta
            hi = times[nxt][0] if nxt is not None else tb
            first = prev if prev is not None else a - 1
            span = (nxt if nxt is not None else b) - first
            step = max(hi - lo, 0) / span
            times[i] = (lo + step * (i - first - 1), lo + step * (i - first))
    drift = sorted(abs(times[at][0] - sec) for at, sec in bounds[1:-1] if at in heard_at)
    starts, ends = {}, {}
    for t, i in zip(times, owner):
        starts.setdefault(i, t[0])
        ends[i] = t[1]
    out, floor = [], 0.0
    for i in range(n):
        start = max(starts.get(i, floor), floor)
        out.append([start, max(ends.get(i, start), start + 0.2)])
        floor = start
    for i in range(len(out) - 1):
        out[i][1] = max(out[i + 1][0], out[i][0] + 0.2)
    return out, matched / max(len(tokens), 1), (drift[len(drift) // 2] if drift else None)


def main():
    parser = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    parser.add_argument('--dir', required=True, type=Path)
    parser.add_argument('--model', default='small.en')
    parser.add_argument('--threads', type=int, default=6, help='CPU threads for Whisper.')
    parser.add_argument('--only', help='Only this tape (T4402).')
    parser.add_argument('--transcribe-only', action='store_true')
    parser.add_argument('--text-only', action='store_true')
    args = parser.parse_args()

    model = None
    if not args.text_only:
        from faster_whisper import WhisperModel
        model = WhisperModel(args.model, device='cpu', compute_type='int8', cpu_threads=args.threads)
    for tape, audio, transcript, seconds in tapes():
        if args.only and tape != args.only:
            continue
        if args.transcribe_only:
            if audio:
                import time
                began = time.time()
                cached = (args.dir / 'audio' / audio).with_suffix('.words.json').exists()
                words = transcribe(model, args.dir / 'audio' / audio)
                print(f'{tape}  {len(words):6d} words  {"cached" if cached else f"{time.time() - began:5.0f} s"}', flush=True)
            continue
        if not transcript:
            print(f'{tape}  no transcript', flush=True)
            continue
        paras = paragraphs(args.dir / 'transcript' / transcript)
        if args.text_only:
            TEXT.mkdir(parents=True, exist_ok=True)
            (TEXT / f'{tape}.txt').write_text('\n\n'.join(paras) + '\n', encoding='utf-8', newline='\n')
            print(f'{tape}  {len(paras):4d} paragraphs', flush=True)
            continue
        cache = (args.dir / 'audio' / audio).with_suffix('.words.json') if audio else None
        heard = cache is not None and cache.exists()
        words = json.loads(cache.read_text(encoding='utf-8')) if heard else []
        blocks, anchors = structure(paras)
        timings, share, drift = align(blocks, anchors, words, float(seconds))
        OUT.mkdir(parents=True, exist_ok=True)
        (OUT / f'{tape}.vtt').write_text(vtt(blocks, timings), encoding='utf-8', newline='\n')
        if not heard:
            print(f'{tape}  timed by its marks only (no Whisper words yet)', flush=True)
            continue
        flag = '' if share >= 0.6 else '  <- check by ear'
        print(f'{tape}  {share:4.0%} matched  marks off by {drift if drift is None else round(drift, 1)} s{flag}', flush=True)


def vtt(blocks, timings):
    """One cue per phrase. A paragraph's first cue opens with its speaker
    (<v NAME>), or <c.p> where the transcript has no label, so the page can
    rebuild the paragraphs; the words themselves are the transcript's."""
    esc = lambda t: t.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')
    lines = ['WEBVTT', '']
    n = 0
    for b in blocks:
        for k, text in enumerate(b['cues']):
            start, end = timings[n]
            n += 1
            head = ('<v ' + esc(b['who']) + '>' if b['who'] else '<c.p>') if k == 0 else ''
            lines += [str(n), f'{shared.stamp(start)} --> {shared.stamp(end)}', head + esc(text).replace('-->', '--&gt;'), '']
    return '\n'.join(lines)


if __name__ == '__main__':
    main()
