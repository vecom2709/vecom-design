# Richtet Claude im Stil von vecom-design.it auf dem Windows-Rechner ein.
#   powershell -NoProfile -ExecutionPolicy Bypass -File tools\claude-stil\installieren-windows.ps1
#
# Als Skriptdatei, nie als -Command "…": Bei der Durchreichung würden die
# Variablen vorher ersetzt (CLAUDE.md, „Dateien auf den Windows-Rechner bringen“).
$ErrorActionPreference = 'Stop'

$hier = Split-Path -Parent $MyInvocation.MyCommand.Path
$cl   = Join-Path $env:USERPROFILE '.claude'
New-Item -ItemType Directory -Force (Join-Path $cl 'themes') | Out-Null

# Ohne BOM schreiben: PowerShell 5.1 setzt mit -Encoding UTF8 eines davor, und
# ein JSON mit BOM lesen weder Claude Code noch Windows Terminal zuverlässig.
$ohneBom = New-Object System.Text.UTF8Encoding $false
function Schreibe($pfad, $text) { [IO.File]::WriteAllText($pfad, $text, $ohneBom) }

Write-Host '◆ Farbthema für Claude Code'
Copy-Item (Join-Path $hier 'claude-code\vecom.json') (Join-Path $cl 'themes\vecom.json') -Force

Write-Host '◆ Statuszeile'
Copy-Item (Join-Path $hier 'claude-code\statusline.sh') (Join-Path $cl 'vecom-statusline.sh') -Force
$einst = Join-Path $cl 'settings.json'
if (Test-Path $einst) {
  Copy-Item $einst "$einst.vor-vecom" -Force
  $d = Get-Content $einst -Raw | ConvertFrom-Json
} else { $d = [pscustomobject]@{} }
# Claude Code führt Befehle unter Windows über Git Bash aus – daher das Bash-Skript.
$zeile = [pscustomobject]@{ type = 'command'; command = 'bash ~/.claude/vecom-statusline.sh'; padding = 0 }
$d | Add-Member -NotePropertyName statusLine -NotePropertyValue $zeile -Force
Schreibe $einst ($d | ConvertTo-Json -Depth 32)

Write-Host '◆ Mod (V-Zeichen, Gold, deutsche Wörter)'
try {
  & claude plugin marketplace add (Join-Path $hier 'claude-code') 2>$null | Out-Null
  & claude plugin install vecom-stil@vecom
} catch { Write-Host '  ! Mod nicht installiert – in Claude Code: /plugin install vecom-stil@vecom' }

Write-Host '◆ Windows Terminal: Farbschema, Profil und Logo'
# Ein Fragment statt settings.json umzuschreiben: Windows Terminal liest es
# zusätzlich ein, und die eigenen Einstellungen bleiben unberührt.
$frag = Join-Path $env:LOCALAPPDATA 'Microsoft\Windows Terminal\Fragments\VecomDesign'
New-Item -ItemType Directory -Force $frag | Out-Null
Copy-Item (Join-Path $hier 'vecom-zeichen.png') $frag -Force
Copy-Item (Join-Path $hier 'vecom-logo.png') $frag -Force
$schema = Get-Content (Join-Path $hier 'terminal\windows-terminal-schema.json') -Raw | ConvertFrom-Json
$profil = [ordered]@{
  guid                  = '{6e7c0d5a-2f4b-4d0e-9a51-7ec0e5d1a9f3}'
  name                  = 'Vecom · Claude Code'
  commandline           = 'powershell.exe -NoLogo -NoExit -Command claude'
  startingDirectory     = '%USERPROFILE%'
  icon                  = (Join-Path $frag 'vecom-zeichen.png')
  colorScheme           = 'Vecom Design'
  cursorColor           = '#f1d38b'
  cursorShape           = 'filledBox'
  font                  = @{ face = 'Cascadia Mono'; size = 12 }
  padding               = '14, 10, 14, 10'
  backgroundImage       = (Join-Path $frag 'vecom-logo.png')
  backgroundImageOpacity    = 0.05
  backgroundImageAlignment  = 'bottomRight'
  backgroundImageStretchMode = 'none'
  tabColor              = '#14110d'
}
$inhalt = [ordered]@{ schemes = @($schema); profiles = @($profil) }
Schreibe (Join-Path $frag 'vecom.json') ($inhalt | ConvertTo-Json -Depth 8)

Write-Host ''
Write-Host 'Fertig. Noch zwei Handgriffe, die kein Skript sicher erledigen kann:'
Write-Host '  1. Windows Terminal neu öffnen, Profil „Vecom · Claude Code“ wählen'
Write-Host '     (Einstellungen → Start → Standardprofil, wenn es immer so sein soll).'
Write-Host '  2. In Claude Code:  /theme  →  „Vecom Design“.'
Write-Host '  Für claude.ai im Browser: Stylus und claude-ai.user.css (siehe LIESMICH.md).'
Write-Host 'Rückweg: settings.json.vor-vecom zurückkopieren, Ordner Fragments\VecomDesign löschen,'
Write-Host '         claude plugin uninstall vecom-stil@vecom'
