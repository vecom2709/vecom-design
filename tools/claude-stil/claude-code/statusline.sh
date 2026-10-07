#!/usr/bin/env bash
# Statuszeile von Claude Code im Stil von vecom-design.it.
#
# Ohne jq: Auf Windows (Git Bash) ist es nicht da, und ein fehlendes Werkzeug
# ließe die Zeile still leer – man hielte den Stil für kaputt. Die drei Felder
# sind flach genug für sed.
eingabe=$(cat)
feld() { printf '%s' "$eingabe" | sed -n "s/.*\"$1\"[[:space:]]*:[[:space:]]*\"\([^\"]*\)\".*/\1/p" | head -n1; }
zahl() { printf '%s' "$eingabe" | sed -n "s/.*\"$1\"[[:space:]]*:[[:space:]]*\([0-9.]*\).*/\1/p" | head -n1; }

modell=$(feld display_name)
ordner=$(feld current_dir); [ -z "$ordner" ] && ordner=$(feld cwd)
voll=$(zahl used_percentage)

gold=$'\e[38;2;241;211;139m'; elfenbein=$'\e[38;2;247;243;234m'
leise=$'\e[38;2;139;132;122m'; rot=$'\e[38;2;239;107;91m'; aus=$'\e[0m'

zweig=""
if [ -n "$ordner" ] && git -C "$ordner" rev-parse --git-dir >/dev/null 2>&1; then
  zweig=$(git -C "$ordner" branch --show-current 2>/dev/null)
  # Ungesichertes in Gold: das Eine, was gerade Aufmerksamkeit verdient.
  [ -n "$(git -C "$ordner" status --porcelain 2>/dev/null | head -n1)" ] && zweig="$zweig ${gold}●${leise}"
fi

zeile="${gold}◆${aus} ${elfenbein}VECOM${aus} ${leise}design${aus}"
[ -n "$ordner" ] && zeile="$zeile ${leise}·${aus} ${elfenbein}${ordner##*/}${aus}"
[ -n "$zweig" ]  && zeile="$zeile ${leise}⎇ ${zweig}${aus}"
[ -n "$modell" ] && zeile="$zeile ${leise}· ${modell}${aus}"
if [ -n "$voll" ]; then
  p=${voll%.*}; farbe=$leise; [ "${p:-0}" -ge 80 ] && farbe=$rot
  zeile="$zeile ${leise}·${aus} ${farbe}${p}%${aus}"
fi
printf '%s\n' "$zeile"
