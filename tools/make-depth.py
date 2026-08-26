"""Erzeugt Tiefenkarten aus Bildern — mit Depth Anything V2 (Small).

Aufruf:
    pip install torch --index-url https://download.pytorch.org/whl/cpu
    pip install transformers
    python tools/make-depth.py assets/img/work/terraviva.webp

Ergebnis: <name>-depth.webp neben dem Original. Die Karte wird auf 720 px
Breite verkleinert — für die Verschiebung im Shader reicht das, und sie wiegt
dann nur drei bis vier Kilobyte.

Auf einem normalen Rechner dauert ein Bild wenige Sekunden. Das Modell lädt
beim ersten Aufruf etwa 100 MB herunter und liegt danach im Zwischenspeicher.
"""
import sys, os
from PIL import Image
from transformers import pipeline

if len(sys.argv) < 2:
    sys.exit("Aufruf: python tools/make-depth.py <bild> [<bild> ...]")

pipe = pipeline(task="depth-estimation",
                model="depth-anything/Depth-Anything-V2-Small-hf", device=-1)

for src in sys.argv[1:]:
    im = Image.open(src).convert("RGB")
    depth = pipe(im)["depth"]
    w = 720
    depth = depth.resize((w, round(w * depth.size[1] / depth.size[0])), Image.LANCZOS).convert("L")
    base, _ = os.path.splitext(src)
    out = f"{base}-depth.webp"
    depth.save(out, quality=80, method=6)
    print(f"{out}  {depth.size}  {os.path.getsize(out)//1024} KB")
