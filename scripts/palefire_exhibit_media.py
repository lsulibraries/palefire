"""Prepare an oral history exhibition's audio and photos for Palefire.

The treatment the Mossville History Project's files had
(_reference/.../mossvilleexhibit-web, palefire_exhibit_mossville.php), so
every exhibition sounds and looks alike. Loudness raised from -18 to -16
LUFS on 2026-11-16, near the -16 podcasts use, so the clips aren't quiet
next to other sites (browsers don't level audio between sites):

- Audio: mono, measured and normalized in two passes to -16 LUFS
  integrated, -1.5 dBTP ceiling, 11 LU range (ffmpeg loudnorm, linear), then
  AAC 64 kbps at 44.1 kHz in .m4a. A stereo file with one dead or much
  quieter channel (20 dB or more) keeps the live channel instead of halving
  it in the downmix.
- Photos: 2560 px on the long side (never enlarged), sRGB JPEG, quality 85,
  metadata stripped (libvips), for the drush script's images=<folder>.

Output names are the source's, lowercased, with runs of other characters as
"_" (01_Helen_Bradley.mp3 -> 01_helen_bradley.m4a). A manifest.csv beside
them lists each file with its sizes, dimensions and loudness before and
after. Existing outputs are skipped, so a run can be resumed.

Usage (ffmpeg and vips on PATH):
  py scripts/palefire_exhibit_media.py --out <folder> \
      --audio <files or folders> --images <files or folders>

Writes <out>/audio, <out>/masters and <out>/manifest.csv.
"""

import argparse
import csv
import json
import re
import subprocess
import sys
from pathlib import Path

AUDIO_EXT = {'.mp3', '.m4a', '.wav', '.aif', '.aiff', '.flac', '.ogg'}
IMAGE_EXT = {'.jpg', '.jpeg', '.png', '.tif', '.tiff', '.webp'}
LN = 'loudnorm=I=-16:TP=-1.5:LRA=11'
MASTER_PX = 2560


def slug(path: Path) -> str:
    return re.sub(r'[^a-z0-9]+', '_', path.stem.lower()).strip('_')


def files(sources, exts):
    found = []
    for source in sources or []:
        p = Path(source)
        if p.is_dir():
            found += sorted(f for f in p.rglob('*') if f.suffix.lower() in exts)
        elif p.suffix.lower() in exts:
            found.append(p)
        else:
            print(f'skipped (not found or wrong type): {p}', file=sys.stderr)
    return found


def run(args):
    return subprocess.run(args, capture_output=True, text=True, encoding='utf-8', errors='replace')


def last_json(text):
    start = text.rfind('{')
    return json.loads(text[start:text.rfind('}') + 1])


def channel_map(src: Path) -> str:
    """The mono mix: both channels, or the live one when the other is dead."""
    probe = run(['ffprobe', '-v', 'error', '-select_streams', 'a:0', '-show_entries', 'stream=channels', '-of', 'csv=p=0', str(src)])
    if probe.stdout.strip() != '2':
        return 'pan=mono|c0=c0' if probe.stdout.strip() == '1' else 'aresample=async=0'
    stats = run(['ffmpeg', '-hide_banner', '-i', str(src), '-af', 'astats=measure_overall=none:measure_perchannel=RMS_level', '-f', 'null', '-'])
    levels = [float(v) for v in re.findall(r'RMS level dB:\s*(-?[\d.]+|-inf)', stats.stderr.replace('-inf', '-200'))][:2]
    if len(levels) == 2 and abs(levels[0] - levels[1]) >= 20:
        return 'pan=mono|c0=c0' if levels[0] > levels[1] else 'pan=mono|c0=c1'
    return 'pan=mono|c0=0.5*c0+0.5*c1'


def audio(src: Path, out: Path):
    mix = channel_map(src)
    first = run(['ffmpeg', '-hide_banner', '-i', str(src), '-af', f'{mix},{LN}:print_format=json', '-f', 'null', '-'])
    m = last_json(first.stderr)
    second = (f"{mix},{LN}:linear=true:measured_I={m['input_i']}:measured_TP={m['input_tp']}"
              f":measured_LRA={m['input_lra']}:measured_thresh={m['input_thresh']}"
              f":offset={m['target_offset']}:print_format=json")
    done = run(['ffmpeg', '-hide_banner', '-y', '-i', str(src), '-af', second, '-ac', '1', '-ar', '44100',
                '-c:a', 'aac', '-b:a', '64k', '-movflags', '+faststart', str(out)])
    if done.returncode:
        raise RuntimeError(done.stderr[-800:])
    after = last_json(done.stderr)
    return m['input_i'], after['output_i'], after['output_tp']


def image(src: Path, out: Path):
    head = run(['vipsheader', str(src)]).stdout
    size = re.search(r'(\d+)x(\d+)', head)
    done = run(['vipsthumbnail', str(src), '--size', f'{MASTER_PX}x{MASTER_PX}>', '--export-profile', 'srgb',
                '-o', f'{out}[Q=85,strip,optimize_coding]'])
    if done.returncode:
        raise RuntimeError(done.stderr[-800:])
    new = re.search(r'(\d+)x(\d+)', run(['vipsheader', str(out)]).stdout)
    return f'{size.group(0) if size else "?"} -> {new.group(0) if new else "?"}'


def main():
    parser = argparse.ArgumentParser(description=__doc__.split('\n\n')[0])
    parser.add_argument('--out', required=True)
    parser.add_argument('--audio', nargs='*')
    parser.add_argument('--images', nargs='*')
    a = parser.parse_args()
    out = Path(a.out)
    (out / 'audio').mkdir(parents=True, exist_ok=True)
    (out / 'masters').mkdir(parents=True, exist_ok=True)
    rows, failed, seen = [], [], set()
    for kind, sources, exts in (('audio', a.audio, AUDIO_EXT), ('image', a.images, IMAGE_EXT)):
        for src in files(sources, exts):
            target = out / ('audio' if kind == 'audio' else 'masters') / (slug(src) + ('.m4a' if kind == 'audio' else '.jpg'))
            if target in seen:
                failed.append(f'{src}: same output name as another file ({target.name})')
                continue
            seen.add(target)
            if target.exists():
                print(f'kept   {target.name}')
                continue
            try:
                if kind == 'audio':
                    lin, lout, peak = audio(src, target)
                    rows.append([kind, src, target.name, src.stat().st_size // 1024, target.stat().st_size // 1024, '', lin, lout, peak])
                else:
                    dims = image(src, target)
                    rows.append([kind, src, target.name, src.stat().st_size // 1024, target.stat().st_size // 1024, dims, '', '', ''])
                print(f'made   {target.name}')
            except Exception as e:  # noqa: BLE001 - report and carry on
                target.unlink(missing_ok=True)
                failed.append(f'{src}: {e}')
    manifest = out / 'manifest.csv'
    new = not manifest.exists()
    with manifest.open('a', newline='', encoding='utf-8') as f:
        w = csv.writer(f, lineterminator='\n')
        if new:
            w.writerow(['type', 'source', 'output', 'source_kb', 'output_kb', 'dimensions', 'lufs_in', 'lufs_out', 'peak_out_dbfs'])
        w.writerows(rows)
    print(f'{len(rows)} made, {len(failed)} failed')
    for line in failed:
        print('FAILED ' + line, file=sys.stderr)
    return 1 if failed else 0


if __name__ == '__main__':
    sys.exit(main())
