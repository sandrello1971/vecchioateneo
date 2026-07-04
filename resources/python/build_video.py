#!/usr/bin/env python
"""V3 — compone il video narrato: per ogni slide un segmento (immagine PNG fissa per
la durata del suo MP3), poi concatena i segmenti in un unico MP4.

Manifest JSON da stdin:
  {"out": "/abs/out.mp4", "slides": [{"image": "/abs/1.png", "audio": "/abs/1.mp3"}, ...]}

Stampa la durata totale (s) su stdout. Esce != 0 con messaggio su stderr in errore.
ffmpeg è invocato dal processo chiamante sotto nice/ionice (eredita la priorità).
"""
import json
import subprocess
import sys
import tempfile
from pathlib import Path

FFMPEG = "/usr/bin/ffmpeg"
FFPROBE = "/usr/bin/ffprobe"

# Parametri di encoding (da config services.video, con default coerenti con V3).
DEFAULTS = {"width": 1280, "height": 720, "fps": 25, "crf": 23, "preset": "medium"}


def run(cmd):
    p = subprocess.run(cmd, capture_output=True, text=True)
    if p.returncode != 0:
        raise RuntimeError(f"{cmd[0]} fallito: {p.stderr[-800:]}")
    return p


def duration(path):
    p = run([FFPROBE, "-v", "error", "-show_entries", "format=duration",
             "-of", "default=nw=1:nk=1", path])
    try:
        return float(p.stdout.strip())
    except ValueError:
        return 0.0


def main():
    manifest = json.load(sys.stdin)
    slides = manifest["slides"]
    out = manifest["out"]
    cfg = {**DEFAULTS, **(manifest.get("config") or {})}
    w, h, fps = int(cfg["width"]), int(cfg["height"]), int(cfg["fps"])
    vf = (f"scale={w}:{h}:force_original_aspect_ratio=decrease,"
          f"pad={w}:{h}:(ow-iw)/2:(oh-ih)/2,setsar=1")
    if not slides:
        sys.stderr.write("nessuna slide nel manifest\n")
        return 2

    total = 0.0
    durations = []
    with tempfile.TemporaryDirectory() as tmp:
        tmp = Path(tmp)
        segments = []
        for i, s in enumerate(slides, 1):
            seg = str(tmp / f"seg_{i:03d}.mp4")
            run([
                FFMPEG, "-y", "-loop", "1", "-i", s["image"], "-i", s["audio"],
                "-c:v", "libx264", "-tune", "stillimage", "-pix_fmt", "yuv420p",
                "-preset", str(cfg["preset"]), "-crf", str(cfg["crf"]),
                "-r", str(fps), "-vf", vf,
                "-c:a", "aac", "-b:a", "128k", "-ac", "2", "-ar", "44100",
                "-shortest", "-movflags", "+faststart", seg,
            ])
            segments.append(seg)
            d = duration(s["audio"])
            durations.append(round(d, 2))
            total += d

        # Concat dei segmenti (stessi parametri → copy stream).
        listfile = tmp / "concat.txt"
        listfile.write_text("".join(f"file '{seg}'\n" for seg in segments))
        Path(out).parent.mkdir(parents=True, exist_ok=True)
        run([FFMPEG, "-y", "-f", "concat", "-safe", "0", "-i", str(listfile),
             "-c", "copy", "-movflags", "+faststart", out])

    # JSON su stdout: durata totale + durata per-segmento (in ordine slide) → la
    # piattaforma costruisce slide_timings (timestamp per-slide, gratis).
    print(json.dumps({"total": round(total, 2), "durations": durations}))
    return 0


if __name__ == "__main__":
    sys.exit(main())
