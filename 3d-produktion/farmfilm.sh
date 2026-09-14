#!/usr/bin/env bash
# Aus dem SheepIt-Archiv wird die Buehne der Webseite.
#
# Die Farm liefert 240 Einzelbilder in 1920x1080 und dazu ein MP4. Fuer die
# Seite braucht es daraus drei Dinge, und jedes hat seinen eigenen Grund:
#
#   1. Ein Standbild. Es traegt die Buehne fuer jeden, dessen Grafik die
#      Echtzeitwelt nicht bauen kann -- und das ist ein Bild, kein Film:
#      Es kostet keine Dekodierung, keinen Akku, und es steht sofort.
#      AVIF zuerst, WebP als Rueckfall.
#
#   2. Einen kurzen Film in zwei Fassungen. AV1 ist rund ein Drittel
#      kleiner als H.264, kann aber noch nicht jeder abspielen; darum
#      beide, und der Browser nimmt, was er kann.
#
#   3. Ein Plakatbild fuer den Film, damit im ersten Moment nicht
#      Schwarz steht.
#
# Aufruf:  ./farmfilm.sh <ordner-mit-den-frames> [frame-fuer-standbild]
set -euo pipefail

QUELLE="${1:?Ordner mit den gerenderten Frames angeben}"
STAND="${2:-0122}"
ZIEL="$(cd "$(dirname "$0")/.." && pwd)/assets"

mkdir -p "$ZIEL/img/3d" "$ZIEL/video"

# ---------------------------------------------------------------- Standbild
# Der Frame, an dem der Saal am besten steht -- nicht der erste, denn da
# faehrt die Kamera noch an.
EINZEL="$(ls "$QUELLE"/*"$STAND"*.png 2>/dev/null | head -1 || true)"
[ -n "$EINZEL" ] || EINZEL="$(ls "$QUELLE"/*.png | sed -n '122p')"
echo "Standbild aus: $EINZEL"
ffmpeg -y -loglevel error -i "$EINZEL" -frames:v 1 -c:v libaom-av1 -crf 34 -cpu-used 4 \
       "$ZIEL/img/3d/buehne-standbild.avif"
ffmpeg -y -loglevel error -i "$EINZEL" -frames:v 1 -c:v libwebp -quality 78 \
       "$ZIEL/img/3d/buehne-standbild.webp"

# ---------------------------------------------------------------- Film
# 24 Bilder je Sekunde: 240 Frames sind damit genau zehn Sekunden. Der Film
# laeuft in der Schleife, deshalb keine Tonspur und keine langen Keyframe-
# Abstaende -- ein Sprung am Ende faellt sonst auf.
ffmpeg -y -loglevel error -framerate 24 -pattern_type glob -i "$QUELLE/*.png" \
       -c:v libvpx-vp9 -crf 36 -b:v 0 -row-mt 1 -g 48 -an \
       -pix_fmt yuv420p "$ZIEL/video/buehne.webm"
ffmpeg -y -loglevel error -framerate 24 -pattern_type glob -i "$QUELLE/*.png" \
       -c:v libx264 -crf 26 -preset slow -profile:v high -g 48 -an \
       -movflags +faststart -pix_fmt yuv420p "$ZIEL/video/buehne.mp4"

echo
echo "Fertig:"
for f in "$ZIEL/img/3d/buehne-standbild.avif" "$ZIEL/img/3d/buehne-standbild.webp" \
         "$ZIEL/video/buehne.webm" "$ZIEL/video/buehne.mp4"; do
  [ -f "$f" ] && printf '  %-44s %8s\n' "${f#"$ZIEL/"}" "$(du -h "$f" | cut -f1)"
done
