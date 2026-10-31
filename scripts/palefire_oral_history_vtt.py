"""Time an exhibition's oral history transcripts to their audio.

Writes one WebVTT file per clip, one cue per phrase, named after the clip
(01-helen-bradley.m4a -> 01-helen-bradley.vtt). Each cue's text is the
transcript's own wording, so js/exhibition.js can find it in the page and
light it while that phrase is heard. Whisper only supplies the timing:
its words are lined up with the transcript's, and the transcript wins.

Dev-only, never deployed. Needs Python 3.10+, ffmpeg on PATH and
faster-whisper:

  py -m venv venv
  venv/Scripts/python -m pip install faster-whisper
  venv/Scripts/python scripts/palefire_oral_history_vtt.py \
    --page mossville.html --audio audio/ --out scripts/data/mossville-vtt/

--page is the exhibition page's saved HTML (the transcripts as published);
--audio holds the clips under their published file names. Whisper's words
are cached next to each clip (.words.json), so re-runs only re-align.
"""

import argparse
import difflib
import html
import json
import re
import subprocess
import sys
from pathlib import Path

# A phrase longer than this is split at the comma nearest its middle.
MAX_WORDS = 26
ABBREVIATIONS = {'mr', 'mrs', 'ms', 'dr', 'st', 'sr', 'jr', 'mt', 'ft', 'vs', 'no'}


def clips_from_page(page):
    """Yield (audio file name, [paragraph text]) for each clip on the page."""
    for block in re.split(r'<div class="pf-audio" data-pf-audio>', page)[1:]:
        src = re.search(r'<source src="([^"]+)"', block)
        body = re.search(r'field--name-field-audio-transcript[^>]*>(.*?)\n', block, re.S)
        if not src or not body:
            continue
        paragraphs = []
        for p in re.findall(r'<p[^>]*>(.*?)</p>', body.group(1), re.S):
            text = html.unescape(re.sub(r'<[^>]+>', '', p))
            text = re.sub(r'\s+', ' ', text).strip()
            if text:
                paragraphs.append(text)
        yield Path(src.group(1)).name, paragraphs


def spoken(paragraph):
    """The paragraph without its speaker label ("CHELSEA ARSENEAULT: ",
    "HUBER “MICKEY” SMITH Sr.: "), which nobody says. A paragraph that is
    only a name (no lowercase) is all label."""
    if not re.search(r'[a-z]', paragraph.replace('Sr.', '').replace('Jr.', '')):
        return ''
    return re.sub(r'^[A-Z][A-Z .“”"\'’\-]*(?:[SJ]r\.?)?:\s+', '', paragraph)


def sentences(paragraph):
    """Split a paragraph into sentences, keeping ". . ." and "Mr." whole."""
    out, start = [], 0
    for m in re.finditer(r'[.!?]["”’)]*\s+(?=["“‘(]?[A-Z0-9])', paragraph):
        before = paragraph[:m.start()]
        word = re.search(r'(\S+)$', before)
        word = word.group(1).lower().strip('"“(') if word else ''
        # ". . ." (a spaced ellipsis) and abbreviations don't end a sentence.
        if paragraph[m.start()] == '.' and (before.endswith(' ') or before.endswith('.') or word in ABBREVIATIONS or re.fullmatch(r'[a-z]', word)):
            continue
        out.append(paragraph[start:m.end()].strip())
        start = m.end()
    out.append(paragraph[start:].strip())
    return [s for s in out if s]


def phrases(sentence):
    """Split an overlong sentence at commas, as evenly as it allows."""
    words = sentence.split(' ')
    if len(words) <= MAX_WORDS:
        return [sentence]
    commas = [i for i, w in enumerate(words[:-3]) if i >= 3 and re.search(r'[,;:]["”’]?$', w)]
    if not commas:
        return [sentence]
    middle = len(words) / 2
    cut = min(commas, key=lambda i: abs(i + 1 - middle)) + 1
    return phrases(' '.join(words[:cut])) + phrases(' '.join(words[cut:]))


def norm(word):
    return re.sub(r"[^a-z0-9']", '', word.lower().replace('’', "'")).strip("'")


def transcribe(model, clip):
    cache = clip.with_suffix('.words.json')
    if cache.exists():
        return json.loads(cache.read_text(encoding='utf-8'))
    # ffmpeg decodes (16 kHz mono float), sidestepping PyAV version clashes.
    import numpy
    pcm = subprocess.run(['ffmpeg', '-v', 'error', '-i', str(clip), '-ac', '1', '-ar', '16000', '-f', 'f32le', '-'], capture_output=True, check=True).stdout
    segments, _ = model.transcribe(numpy.frombuffer(pcm, numpy.float32), language='en', word_timestamps=True, beam_size=5, condition_on_previous_text=False)
    words = [{'w': w.word.strip(), 's': round(w.start, 3), 'e': round(w.end, 3)} for seg in segments for w in seg.words]
    cache.write_text(json.dumps(words), encoding='utf-8')
    return words


def align(cue_texts, words):
    """Return [(start, end)] per cue, and the share of words matched."""
    tokens, owner = [], []
    for i, text in enumerate(cue_texts):
        for w in text.split(' '):
            if norm(w):
                tokens.append(norm(w))
                owner.append(i)
    heard = [norm(w['w']) for w in words]
    times = [None] * len(tokens)
    matcher = difflib.SequenceMatcher(None, tokens, heard, autojunk=False)
    matched = 0
    for a, b, size in matcher.get_matching_blocks():
        for k in range(size):
            times[a + k] = (words[b + k]['s'], words[b + k]['e'])
            matched += 1
    # Unheard words take times spread evenly between their heard neighbours.
    end_of_audio = words[-1]['e'] if words else 0.0
    known = [i for i, t in enumerate(times) if t]
    for i, t in enumerate(times):
        if t:
            continue
        prev = max((k for k in known if k < i), default=None)
        nxt = min((k for k in known if k > i), default=None)
        lo = times[prev][1] if prev is not None else 0.0
        hi = times[nxt][0] if nxt is not None else end_of_audio
        span = (nxt if nxt is not None else len(times)) - (prev if prev is not None else -1)
        step = (hi - lo) / span
        at = lo + step * (i - (prev if prev is not None else -1))
        times[i] = (at - step, at)
    starts, ends = {}, {}
    for t, i in zip(times, owner):
        starts.setdefault(i, t[0])
        ends[i] = t[1]
    out, floor = [], 0.0
    for i in range(len(cue_texts)):
        start = max(starts.get(i, floor), floor)
        out.append([start, max(ends.get(i, start), start + 0.2)])
        floor = start
    # A phrase holds until the next one starts, so the light never blinks off mid-pause.
    for i in range(len(out) - 1):
        out[i][1] = max(out[i + 1][0], out[i][0] + 0.2)
    return out, matched / max(len(tokens), 1)


def stamp(seconds):
    ms = int(round(seconds * 1000))
    return f'{ms // 3600000:02d}:{ms // 60000 % 60:02d}:{ms // 1000 % 60:02d}.{ms % 1000:03d}'


def vtt(cue_texts, timings):
    lines = ['WEBVTT', '']
    for n, (text, (start, end)) in enumerate(zip(cue_texts, timings), 1):
        text = text.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;').replace('-->', '--&gt;')
        lines += [str(n), f'{stamp(start)} --> {stamp(end)}', text, '']
    return '\n'.join(lines)


def main():
    parser = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    parser.add_argument('--page', required=True, type=Path)
    parser.add_argument('--audio', required=True, type=Path)
    parser.add_argument('--out', required=True, type=Path)
    parser.add_argument('--model', default='small.en')
    parser.add_argument('--only', help='Only this clip (file name).')
    args = parser.parse_args()

    from faster_whisper import WhisperModel
    model = WhisperModel(args.model, device='cpu', compute_type='int8')
    args.out.mkdir(parents=True, exist_ok=True)
    page = args.page.read_text(encoding='utf-8')
    for name, paragraphs in clips_from_page(page):
        if args.only and name != args.only:
            continue
        clip = args.audio / name
        if not clip.exists() or not paragraphs:
            print(f'skip  {name}: {"no audio" if paragraphs else "no transcript"}', file=sys.stderr)
            continue
        cue_texts = [p for para in paragraphs for s in sentences(spoken(para)) for p in phrases(s)]
        timings, share = align(cue_texts, transcribe(model, clip))
        (args.out / clip.with_suffix('.vtt').name).write_text(vtt(cue_texts, timings), encoding='utf-8', newline='\n')
        flag = '' if share >= 0.6 else '  <- check by ear'
        print(f'{share:5.0%}  {len(cue_texts):3d} phrases  {name}{flag}', flush=True)


if __name__ == '__main__':
    main()
