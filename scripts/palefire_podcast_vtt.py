"""Caption What Endures, the Oral History podcast, for the docked player.

Writes one WebVTT file per episode, named by its SoundCloud track ID
(transcripts/podcast/2172212511.vtt), which js/podcast.js shows as a live
caption line over the player and as the episode's transcript. Two sources:

- The interview excerpts: the old Oral History blog published each
  episode's clips as "Name: words" paragraphs, with their citations
  (episodes 1 to 21, saved from the Wayback Machine). Those words are kept
  as written, each cue voiced <v Name>, and timed by Whisper as the
  exhibitions' transcripts are (palefire_oral_history_vtt.py).
- Everything else (the host, and every episode the blog never had):
  Whisper's own words, each cue wrapped <c.auto> so the page can say the
  text was transcribed automatically.

Dev-only, never deployed. Needs what palefire_oral_history_vtt.py needs
(Python 3.10+, ffmpeg on PATH, faster-whisper):

  venv/Scripts/python scripts/palefire_podcast_vtt.py \
    --feed sounds.rss --posts posts/ --audio audio/ --out transcripts/podcast/

--feed is SoundCloud's podcast feed, saved; --audio holds each episode's
MP3 as <track id>.mp3 (--download fetches the missing ones from the feed);
--posts holds the archived blog posts' HTML, any names. Whisper's words
are cached beside each MP3 (.words.json), so re-runs only re-align.
"""

import argparse
import difflib
import html
import json
import re
import subprocess
import sys
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from palefire_oral_history_vtt import norm, phrases, sentences, stamp  # noqa: E402

import palefire_oral_history_vtt as shared  # noqa: E402

# A live caption is read in a glance: shorter phrases than an exhibition's.
shared.MAX_WORDS = 16
# A Whisper phrase ends at a sentence's end, a pause this long, or this many words.
PAUSE = 0.7
MAX_AUTO = 14
# Episodes shorter than this are the staff introduction clips (PodcastFeed).
MIN_LENGTH = 240


# Whisper is told what the show is, so it hears "oral history", not "art
# history", and the Center's name; the episode's title adds its own names.
PROMPT = ('What Endures, a production of the T. Harry Williams Center for Oral History '
          'at Louisiana State University Libraries, Baton Rouge. Oral history interviews. {title}. {about}')
# What it still mishears, put right in its own text (never the blog's).
FIXES = [
    (r'\bCenter for Art History', 'Center for Oral History'),
    (r'\b([Aa])rt (histor(?:y|ies|ians?)) (interview|project|collection|program)', lambda m: ('O' if m.group(1) == 'A' else 'o') + 'ral ' + m.group(2) + ' ' + m.group(3)),
    (r'\bT\. Harry Williams\b|\bT Harry Williams\b|\bTee Harry Williams\b', 'T. Harry Williams'),
    # The Center's Director, Jennifer A. Cramer.
    (r'\b(Jen|Jennifer)( A\.)? Kramer\b', r'\1\2 Cramer'),
]
# Words that end in a period without ending a sentence.
NOT_ENDS = re.compile(r'^(?:[A-Z]\.|Mr\.|Mrs\.|Ms\.|Dr\.|St\.|Sr\.|Jr\.|Rev\.|Gov\.|Sen\.|Col\.|Gen\.|Lt\.|No\.)$')


def transcribe_episode(model, clip, title, about=''):
    """Whisper's words for an episode, cached beside it (.words.json).

    The prompt carries the episode's title and description, so the names
    in them come out spelled as SoundCloud has them."""
    cache = clip.with_suffix('.words.json')
    if cache.exists():
        return json.loads(cache.read_text(encoding='utf-8'))
    import numpy
    pcm = subprocess.run(['ffmpeg', '-v', 'error', '-i', str(clip), '-ac', '1', '-ar', '16000', '-f', 'f32le', '-'], capture_output=True, check=True).stdout
    segments, _ = model.transcribe(numpy.frombuffer(pcm, numpy.float32), language='en', word_timestamps=True, beam_size=5,
                                   condition_on_previous_text=False, initial_prompt=PROMPT.format(title=re.sub(r'^Episode\s+\d+\s*-\s*', '', title), about=about[:600]),
                                   vad_filter=True)
    words = [{'w': w.word.strip(), 's': round(w.start, 3), 'e': round(w.end, 3)} for seg in segments for w in seg.words]
    cache.write_text(json.dumps(words), encoding='utf-8')
    return words


def fixed(text):
    for pattern, repl in FIXES:
        text = re.sub(pattern, repl, text)
    return text


def episodes(feed):
    """[(track id, number or None, title, seconds, mp3 url, description)], one per episode."""
    out, seen = [], set()
    for item in feed.split('<item>')[1:]:
        title = html.unescape(re.sub(r'<[^>]+>', '', (re.search(r'<title>(.*?)</title>', item, re.S) or [None, ''])[1])).strip()
        mp3 = (re.search(r'<enclosure[^>]*url="([^"]+)"', item) or [None, ''])[1]
        track = re.search(r'/stream/(\d+)', mp3)
        clock = (re.search(r'<itunes:duration>([^<]+)', item) or [None, '0'])[1]
        seconds = 0
        for part in clock.split(':'):
            seconds = seconds * 60 + int(part or 0)
        key = (title.lower(), seconds)
        if not track or seconds < MIN_LENGTH or key in seen:
            continue
        seen.add(key)
        number = re.match(r'Episode\s+(\d+)', title, re.I)
        about = (re.search(r'<description>(.*?)</description>', item, re.S) or [None, ''])[1]
        about = html.unescape(re.sub(r'<!\[CDATA\[|\]\]>', '', about))
        about = re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', ' ', about)).strip()
        out.append((track.group(1), int(number.group(1)) if number else None, title, seconds, mp3, about))
    return out


def excerpts(post):
    """The post's episode number, title and [(speaker, text)] excerpts."""
    body = post.split('class="entry-content"', 1)[-1].split('id="comments"', 1)[0]
    body = re.sub(r'<(script|style)[^>]*>.*?</\1>', '', body, flags=re.S)
    title = re.search(r'<h1[^>]*class="entry-title"[^>]*>(.*?)</h1>', post, re.S) or re.search(r'<title>(.*?)</title>', post, re.S)
    title = html.unescape(re.sub(r'<[^>]+>', '', title.group(1))).split('|')[0].strip() if title else ''
    number = re.search(r'EPISODE\s+(\d+)', re.sub(r'<[^>]+>', ' ', body))
    out = []
    for p in re.findall(r'<p[^>]*>(.*?)</p>', body, re.S):
        text = re.sub(r'\s+', ' ', html.unescape(re.sub(r'<[^>]+>', '', p))).replace('\xa0', ' ').strip()
        # "Jesse Coates: They didn't have any colors..." Citations ("Coates,
        # Jesse, interview by ...") and the episode notes don't match.
        m = re.match(r'^([A-Z][\w.“”"\'’\- ]{1,48}?):\s+(\S.*)$', text)
        if m and not re.search(r'interview(ed)? by|recording,', text[:200], re.I) and len(m.group(1).split()) <= 6 and m.group(1).strip().upper() not in {'WARNING', 'NOTE', 'NOTES', 'CITATION', 'SOURCE', 'LISTEN'}:
            name = m.group(1).strip()
            # "WINNIE BYRD" as "Winnie Byrd"; mixed case stays as written.
            out.append((name.title() if name.isupper() else name, m.group(2).strip()))
    return (int(number.group(1)) if number else None), title, out


def match_post(posts, track_number, track_title):
    """The archived post for an episode: its number, then the closest title."""
    best, score = None, 0.0
    for number, title, items in posts:
        if not items:
            continue
        ratio = difflib.SequenceMatcher(None, title.lower(), re.sub(r'^Episode\s+\d+\s*-\s*', '', track_title, flags=re.I).lower()).ratio()
        if track_number is not None and number == track_number:
            ratio += 1
        if ratio > score:
            best, score = items, ratio
    return best if score >= 0.6 else None


def place(items, words):
    """Where each excerpt is heard: [(first word, last word + 1)] or None.

    Each excerpt's words are matched against the episode's, in order, so a
    clip the blog left out (or one Whisper missed) just goes unplaced."""
    heard = [norm(w['w']) for w in words]
    spans, floor = [], 0
    for _, text in items:
        tokens = [norm(w) for w in text.split() if norm(w)]
        if len(tokens) < 4:
            spans.append(None)
            continue
        window = heard[floor:]
        blocks = difflib.SequenceMatcher(None, tokens, window, autojunk=False).get_matching_blocks()
        hits = [(b, size) for a, b, size in blocks if size >= 2]
        found = sum(size for _, size in hits)
        if found < max(3, len(tokens) * 0.45):
            spans.append(None)
            continue
        # The run of hits that holds most of them, so a stray match far off
        # doesn't stretch the span.
        start = floor + hits[0][0]
        end = floor + hits[-1][0] + hits[-1][1]
        if end - start > len(tokens) * 2.5:
            middle = sorted(hits, key=lambda h: -h[1])[0]
            start = floor + max(hits[0][0], middle[0] - len(tokens))
            end = floor + min(hits[-1][0] + hits[-1][1], middle[0] + middle[1] + len(tokens))
        # Short lines ("Whose job was it to gather eggs?") can hit stray
        # common words far off: the span must read like the excerpt.
        if difflib.SequenceMatcher(None, tokens, heard[start:end], autojunk=False).ratio() < 0.5:
            spans.append(None)
            continue
        spans.append((start, end))
        floor = end
    return spans


def auto_cues(words):
    """Whisper's words as phrases: [(start, end, text)].

    A phrase ends at a sentence's end (not "T." or "Dr."), at a pause, or
    when it runs long: then at its last comma past the sixth word, if any,
    the rest starting the next phrase."""
    cues, run = [], []

    def flush(upto):
        cues.append((run[0]['s'], run[upto - 1]['e'], fixed(' '.join(x['w'] for x in run[:upto]).strip())))
        del run[:upto]

    for i, w in enumerate(words):
        run.append(w)
        nxt = words[i + 1] if i + 1 < len(words) else None
        ends = re.search(r'[.!?]["”]?$', w['w']) and not NOT_ENDS.match(w['w'])
        if not nxt or ends or nxt['s'] - w['e'] >= PAUSE:
            flush(len(run))
        elif len(run) >= MAX_AUTO:
            commas = [k for k in range(6, len(run)) if re.search(r'[,;:]["”]?$', run[k - 1]['w'])]
            flush(commas[-1] if commas else len(run))
    return cues


def written_cues(speaker, text, words):
    """An excerpt's own words, phrase by phrase, timed on the words heard."""
    texts = [p for s in sentences(text) for p in phrases(s)]
    timings, _ = shared.align(texts, words) if words else ([], 0)
    # align() times unheard leading words from 0:00; keep them in the span.
    lo, hi = (words[0]['s'], words[-1]['e']) if words else (0, 0)
    clamp = lambda t: min(max(t, lo), hi)
    return [(clamp(start), clamp(end), phrase, speaker) for phrase, (start, end) in zip(texts, timings)]


def build(words, items):
    """Every cue of the episode: [(start, end, text, speaker or None)]."""
    cues, at = [], 0
    spans = place(items, words) if items else []
    placed = [(span, item) for span, item in zip(spans, items or []) if span]
    for (start, end), (speaker, text) in placed:
        cues += [(s, e, t, None) for s, e, t in auto_cues(words[at:start])]
        cues += written_cues(speaker, text, words[start:end])
        at = end
    cues += [(s, e, t, None) for s, e, t in auto_cues(words[at:])]
    # In order, never overlapping; each holds until the next unless a pause
    # follows, so the line clears while no one speaks.
    cues.sort(key=lambda c: c[0])
    out = []
    for i, (s, e, t, who) in enumerate(cues):
        nxt = cues[i + 1][0] if i + 1 < len(cues) else e + 1.5
        if out and s < out[-1][1]:
            s = out[-1][1]
        e = max(min(e + 0.6, nxt), s + 0.4) if nxt - e < 1.2 else e + 0.6
        out.append((s, e, t, who))
    return out, len(placed), len(items or [])


def vtt(cues, title, source):
    esc = lambda t: t.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')
    # Each NOTE a block of its own; js/podcast.js shows the second.
    lines = ['WEBVTT', '', f'NOTE {esc(title)}', '', f'NOTE {source}', '']
    for n, (s, e, text, who) in enumerate(cues, 1):
        body = f'<v {esc(who)}>{esc(text)}' if who else f'<c.auto>{esc(text)}</c>'
        lines += [str(n), f'{stamp(s)} --> {stamp(e)}', body, '']
    return '\n'.join(lines)


def main():
    parser = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    parser.add_argument('--feed', required=True, type=Path)
    parser.add_argument('--posts', type=Path)
    parser.add_argument('--audio', required=True, type=Path)
    parser.add_argument('--out', type=Path)
    parser.add_argument('--model', default='small.en')
    parser.add_argument('--download', action='store_true', help='Fetch missing MP3s from the feed.')
    parser.add_argument('--transcribe-only', action='store_true', help='Only cache Whisper\'s words.')
    parser.add_argument('--only', help='Only this track ID.')
    args = parser.parse_args()

    feed = args.feed.read_text(encoding='utf-8')
    posts = [excerpts(p.read_text(encoding='utf-8', errors='replace')) for p in sorted(args.posts.glob('*.htm*'))] if args.posts else []
    args.audio.mkdir(parents=True, exist_ok=True)
    model = None
    for track, number, title, seconds, mp3, about in episodes(feed):
        if args.only and track != args.only:
            continue
        clip = args.audio / f'{track}.mp3'
        if not clip.exists():
            if not args.download:
                print(f'skip  {track}: no audio', file=sys.stderr)
                continue
            urllib.request.urlretrieve(mp3, clip)
        if not clip.with_suffix('.words.json').exists() and model is None:
            from faster_whisper import WhisperModel
            model = WhisperModel(args.model, device='cpu', compute_type='int8')
        words = transcribe_episode(model, clip, title, about)
        if args.transcribe_only or not args.out:
            print(f'words {track}  {len(words):5d}  {title}', flush=True)
            continue
        items = match_post(posts, number, title)
        cues, placed, total = build(words, items)
        source = f'Interview excerpts as published on the Oral History blog ({placed} of {total}); the rest transcribed automatically.' if items else 'Transcribed automatically.'
        args.out.mkdir(parents=True, exist_ok=True)
        (args.out / f'{track}.vtt').write_text(vtt(cues, title, source), encoding='utf-8', newline='\n')
        flag = '  <- check by ear' if items and placed < total * 0.6 else ''
        print(f'{track}  {len(cues):4d} cues  {placed:2d}/{total:<2d} excerpts  {title}{flag}', flush=True)


if __name__ == '__main__':
    main()
