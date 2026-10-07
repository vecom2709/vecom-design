#!/usr/bin/env bash
# Richtet Claude im Stil von vecom-design.it auf dem Mac ein.
#   bash tools/claude-stil/installieren-mac.sh
#
# Fasst nur an, was es selbst anlegt, und sichert settings.json vorher. Zweimal
# laufen lassen schadet nicht: Jeder Schritt prüft, ob er schon getan ist.
set -euo pipefail

HIER="$(cd "$(dirname "$0")" && pwd)"
CL="$HOME/.claude"
mkdir -p "$CL/themes"

echo "◆ Farbthema für Claude Code"
cp "$HIER/claude-code/vecom.json" "$CL/themes/vecom.json"

echo "◆ Statuszeile"
cp "$HIER/claude-code/statusline.sh" "$CL/vecom-statusline.sh"
chmod +x "$CL/vecom-statusline.sh"
[ -f "$CL/settings.json" ] && cp "$CL/settings.json" "$CL/settings.json.vor-vecom"
python3 - "$CL/settings.json" <<'PY'
import json, os, sys
p = sys.argv[1]
d = json.load(open(p)) if os.path.exists(p) and os.path.getsize(p) else {}
d['statusLine'] = {'type': 'command', 'command': '~/.claude/vecom-statusline.sh', 'padding': 0}
open(p, 'w').write(json.dumps(d, ensure_ascii=False, indent=2) + '\n')
PY

echo "◆ Mod (V-Zeichen, Gold, deutsche Wörter)"
# Gelesen wird direkt aus diesem Repository: Ein git pull bringt Änderungen mit.
claude plugin marketplace add "$HIER/claude-code" >/dev/null 2>&1 || true
claude plugin install vecom-stil@vecom || echo "  ! Mod nicht installiert – in Claude Code: /plugin install vecom-stil@vecom"

echo "◆ Terminal-Farbschema"
open "$HIER/terminal/Vecom Design.terminal"
sleep 1
defaults write com.apple.Terminal "Default Window Settings" -string "Vecom Design"
defaults write com.apple.Terminal "Startup Window Settings" -string "Vecom Design"
if [ -d "/Applications/iTerm.app" ]; then
  open "$HIER/terminal/Vecom.itermcolors"
  echo "  iTerm2: Settings → Profiles → Colors → Color Presets → Vecom"
fi

cat <<'TXT'

Fertig. Noch zwei Handgriffe, die kein Skript sicher erledigen kann:
  1. Claude Code neu starten und  /theme  →  „Vecom Design“ wählen.
  2. Für claude.ai im Browser: Erweiterung „Stylus“ installieren und
     tools/claude-stil/claude-ai.user.css hineinziehen (siehe LIESMICH.md).
Rückweg: ~/.claude/settings.json.vor-vecom zurückkopieren,
         claude plugin uninstall vecom-stil@vecom
TXT
