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
#   2. Einen kurzen Film in zwei Fassungen. VP9 ist bei gleicher Bildguete
#      rund ein Viertel kleiner als H.264, kann aber nicht jeder abspielen;
#      darum beide, und der Browser nimmt, was er kann. AV1 waere noch
#      kleiner, aber Haswell-Grafik dekodiert weder AV1 noch VP9 in
#      Hardware -- und genau die soll hier nicht ins Schwitzen kommen.
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
# 24 Bilder je Sekunde: 240 Frames sind damit genau zehn Sekunden.
#
# Der Film laeuft NICHT in der Schleife, und das ist keine Nachlaessigkeit,
# sondern das, was die Kamerafahrt selbst sagt. Am 14.09.2026 nachgemessen,
# mittlerer Bildunterschied je Paar:
#
#   Bild 239 -> 240    0,91   die Kamera kommt zur Ruhe
#   Bild   1 ->   2    0,44   und sie startet aus der Ruhe
#   Bild 240 ->   1   48,38   aber an einer voellig anderen Stelle
#
# Die Fahrt hat eine weiche Ein- und Ausblende an beiden Enden und endet
# woanders, als sie beginnt. In der Schleife gaebe das einen harten Schnitt
# alle zehn Sekunden. Also: einmal spielen, auf dem letzten Bild stehen
# bleiben. Im Markup heisst das autoplay muted playsinline -- ohne loop.
#
# Die Stufen wurden gemessen, nicht geraten. SSIM gegen die PNG-Quelle,
# Bild fuer Bild nach dem Dekodieren verglichen (die Filterkette direkt auf
# den Videostrom zu setzen misst sonst um ein Bild versetzt und liefert fuer
# jede Einstellung dieselbe Zahl -- darauf bin ich einmal hereingefallen):
#
#   VP9  crf 36   2,28 MB   0,9612      H.264 crf 24   2,71 MB   0,9605
#   VP9  crf 40   1,54 MB   0,9578      H.264 crf 26   2,04 MB   0,9571
#   VP9  crf 44   1,09 MB   0,9541      H.264 crf 28   1,59 MB   0,9533
#
# Im 1:1-Ausschnitt ist zwischen allen dreien nichts zu sehen -- auch die
# Schrift auf der Tafel bleibt bei crf 44 lesbar. Die Zahlen bleiben so eng
# beieinander, weil das Bildrauschen aus 256 Samples selbst Bitrate frisst;
# noch mehr Bitrate ginge in das Rauschen, nicht ins Bild. Darum VP9 bei 40.
# H.264 bleibt bei 26: Er ist der Rueckfall fuer die schwachen Rechner, und
# dort ist die halbe Megabyte mehr besser angelegt als bei denen, die
# ohnehin VP9 bekommen.
#
# -g 48 haelt alle zwei Sekunden ein Schluesselbild bereit -- nicht wegen
# der Schleife, sondern damit ein Sprung in der Zeitleiste sofort steht.
ffmpeg -y -loglevel error -framerate 24 -pattern_type glob -i "$QUELLE/*.png" \
       -c:v libvpx-vp9 -crf 40 -b:v 0 -row-mt 1 -g 48 -an \
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
