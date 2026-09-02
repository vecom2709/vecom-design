#!/usr/bin/env bash
#
# tools/ton-kie.sh — erzeugt die Sprachaufnahmen fuer das Erklaervideo ueber kie.ai.
#
# WARUM DU DAS AUSFUEHRST UND NICHT ICH
# Der Schluessel gehoert dir. Er steht in deiner Umgebung, nicht in diesem
# Repository, nicht in einer Chatnachricht und nirgends sonst.
#
#   export KIE_API_KEY="dein-schluessel"
#   bash tools/ton-kie.sh de
#   bash tools/ton-kie.sh it
#   bash tools/ton-kie.sh en
#
# Ergebnis: video/ton/<sprache>/szene-01.mp3 … szene-11.mp3
# Danach:   node tools/ton-einbauen.mjs <sprache>
#
# Das Skript fragt IMMER zuerst das Guthaben ab und rechnet vor, was der Lauf
# kostet — abgebrochen wird, bevor Geld fliesst, nicht danach.
set -euo pipefail

SPRACHE="${1:-de}"
: "${KIE_API_KEY:?Bitte zuerst KIE_API_KEY setzen: export KIE_API_KEY=\"…\"}"

BASIS="https://api.kie.ai"
# turbo-2-5 ist das Modell, das eine Sprache erzwingen kann. Ohne das spricht
# ein mehrsprachiges Modell den deutschen Text gern mit englischem Einschlag.
MODELL="elevenlabs/text-to-speech-turbo-2-5"
STIMME="LruHrtVF6PSyGItzMNHS"   # Benjamin — tief, warm, ruhig
ORDNER="video/ton/$SPRACHE"

command -v jq  >/dev/null || { echo "jq fehlt. Auf dem Mac: brew install jq"; exit 1; }
command -v curl >/dev/null || { echo "curl fehlt."; exit 1; }

# ---------------------------------------------------------------- Guthaben
echo "Frage das Guthaben ab …"
ANTWORT=$(curl -sS -H "Authorization: Bearer $KIE_API_KEY" "$BASIS/api/v1/chat/credit")
CODE=$(printf '%s' "$ANTWORT" | jq -r '.code // "?"')
if [ "$CODE" != "200" ]; then
  echo "kie.ai antwortet nicht wie erwartet: $ANTWORT" >&2
  echo "Bei 401 stimmt der Schluessel nicht." >&2
  exit 1
fi
GUTHABEN=$(printf '%s' "$ANTWORT" | jq -r '.data')
echo "Guthaben: $GUTHABEN"

# Die Texte kommen aus den Sprachdaten — eine Quelle, nicht zwei.
mapfile -t TEXTE < <(node tools/sprechertext.mjs "$SPRACHE")
ANZAHL=${#TEXTE[@]}
ZEICHEN=$(printf '%s' "${TEXTE[@]}" | wc -c | tr -d ' ')
echo "Vorhaben: $ANZAHL Aufnahmen, zusammen rund $ZEICHEN Zeichen."
echo
echo "Was eine Aufnahme kostet, sagt kie.ai vorher nicht — der Verbrauch steht"
echo "erst hinterher je Aufgabe fest. Nach der ersten Szene halte ich an und"
echo "zeige dir den tatsaechlichen Verbrauch, bevor der Rest laeuft."
echo

mkdir -p "$ORDNER"

erzeugen() {
  local nr="$1" text="$2" ziel="$3"
  local anfrage antwort taskid zustand daten url
  anfrage=$(jq -n --arg m "$MODELL" --arg t "$text" --arg v "$STIMME" --arg l "$SPRACHE" '{
    model: $m,
    input: { text: $t, voice: $v, stability: 0.55, similarity_boost: 0.75,
             style: 0, speed: 0.96, language_code: $l }
  }')
  antwort=$(curl -sS -X POST "$BASIS/api/v1/jobs/createTask" \
    -H "Authorization: Bearer $KIE_API_KEY" -H "Content-Type: application/json" -d "$anfrage")
  if [ "$(printf '%s' "$antwort" | jq -r '.code')" != "200" ]; then
    echo "  Szene $nr abgelehnt: $(printf '%s' "$antwort" | jq -r '.msg')" >&2
    return 1
  fi
  taskid=$(printf '%s' "$antwort" | jq -r '.data.taskId')
  for _ in $(seq 1 100); do
    sleep 3
    daten=$(curl -sS -H "Authorization: Bearer $KIE_API_KEY" "$BASIS/api/v1/jobs/recordInfo?taskId=$taskid")
    zustand=$(printf '%s' "$daten" | jq -r '.data.state // "unbekannt"')
    case "$zustand" in
      success)
        # resultJson ist eine Zeichenkette, die noch einmal JSON enthaelt.
        url=$(printf '%s' "$daten" | jq -r '.data.resultJson | fromjson | .resultUrls[0]')
        curl -sS -L -o "$ziel" "$url"
        VERBRAUCH=$(printf '%s' "$daten" | jq -r '.data.creditsConsumed // "?"')
        echo "  Szene $nr fertig ($VERBRAUCH Credits) → $ziel"
        return 0 ;;
      fail)
        echo "  Szene $nr fehlgeschlagen: $(printf '%s' "$daten" | jq -r '.data.failMsg')" >&2
        return 1 ;;
    esac
  done
  echo "  Szene $nr: Zeitueberschreitung" >&2
  return 1
}

for i in "${!TEXTE[@]}"; do
  NR=$(printf '%02d' $((i + 1)))
  ZIEL="$ORDNER/szene-$NR.mp3"
  if [ -s "$ZIEL" ]; then echo "  Szene $NR liegt schon vor, uebersprungen"; continue; fi
  erzeugen "$NR" "${TEXTE[$i]}" "$ZIEL"
  if [ "$i" -eq 0 ]; then
    REST=$(curl -sS -H "Authorization: Bearer $KIE_API_KEY" "$BASIS/api/v1/chat/credit" | jq -r '.data')
    echo
    echo "Guthaben vorher: $GUTHABEN — jetzt: $REST"
    echo "Hochgerechnet auf alle $ANZAHL Szenen und drei Sprachen entsprechend mehr."
    read -r -p "Weitermachen? [j/N] " WEITER
    [[ "$WEITER" =~ ^[jJyY]$ ]] || { echo "Abgebrochen. Die erste Aufnahme bleibt liegen."; exit 0; }
  fi
done

echo
echo "Fertig. Weiter mit:  node tools/ton-einbauen.mjs $SPRACHE"
