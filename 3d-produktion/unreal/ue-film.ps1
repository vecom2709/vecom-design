# Werbefilm „Sichtbar werden" -- Vergleich in Unreal (28.09.2026).
# 1. Blender exportiert die Gasse (GLB + JSON) aus film-sichtbar.blend
# 2. Unreal baut die Karte im Hintergrund (Commandlet, Projekt VecomArbeiten)
# 3. Unreal rechnet die Kameras mit Path Tracer und Lumen (-game, Render Queue)
# Stand in film-sichtbar\unreal\stand.txt. Der offene Editor wird nicht angefasst.
param(
  [double]$Ev = 1.3, [int]$Breite = 1920, [int]$Hoehe = 1080, [int]$Spp = 64, [int]$Raumproben = 16,
  [string]$Verfahren = 'pt,lumen', [string]$Bilder = '358,420,640', [int]$Export = 1,
  [double]$LampeFaktor = 1.0, [double]$HimmelNits = 180.0, [double]$MondFaktor = 1.0, [double]$EmissionFaktor = 1.0,
  [int]$NurKarte = 0, [int]$NurRender = 0
)
$ErrorActionPreference = 'Continue'
$exe   = 'C:\Program Files\Epic Games\UE_5.8\Engine\Binaries\Win64\UnrealEditor-Cmd.exe'
$bl    = 'C:\Program Files\Blender Foundation\Blender 5.2\blender.exe'
$proj  = 'C:\Users\manue\Documents\Unreal Projects\VecomArbeiten'
$uproj = Join-Path $proj 'VecomArbeiten.uproject'
$werk  = 'C:\Users\manue\Desktop\Vecom Design\werkzeug'
$film  = 'C:\Users\manue\Desktop\Vecom Design\3d-produktion\film-sichtbar'
$u     = Join-Path $film 'unreal'
$stand = Join-Path $u 'stand.txt'
function Stand($t) { ('{0} {1}' -f (Get-Date -Format 'HH:mm:ss'), $t) | Out-File -Append -Encoding utf8 $stand }

if ($Export -eq 1 -and $NurRender -ne 1) {
  Stand 'Blender-Export startet'
  & $bl -b (Join-Path $film 'film-sichtbar.blend') -P (Join-Path $film 'scripts\film_unreal_export.py') -- 420 $Bilder *> (Join-Path $u 'export.log')
  Stand ('Blender-Export Ende, GLB {0:N1} MB' -f ((Get-Item (Join-Path $u 'gasse.glb')).Length / 1MB))
}
New-Item -ItemType Directory -Force -Path (Join-Path $proj 'Import'), (Join-Path $proj 'Content\Python') | Out-Null
Copy-Item (Join-Path $u 'gasse.glb'), (Join-Path $u 'gasse.json') (Join-Path $proj 'Import') -Force
Copy-Item (Join-Path $u 'ue-f01_gasse.py') (Join-Path $proj 'Content\Python\f01_gasse.py') -Force

$editor = @(Get-CimInstance Win32_Process -Filter "Name='UnrealEditor.exe'" -ErrorAction SilentlyContinue |
            Where-Object { $_.CommandLine -and $_.CommandLine -match 'VecomArbeiten' })
if ($editor) { Stand 'Editor auf VecomArbeiten offen - kein Lauf'; exit 4 }

$ordner = Join-Path $u 'render'
$auftrag = [ordered]@{ ev = $Ev; breite = $Breite; hoehe = $Hoehe; spp = $Spp; raumproben = $Raumproben; verfahren = $Verfahren;
                       lampe_faktor = $LampeFaktor; himmel_nits = $HimmelNits; mond_faktor = $MondFaktor; himmel = 1.0;
                       emission_faktor = $EmissionFaktor; ordner = $ordner
                       emission = [ordered]@{ 'Anzeige' = 2.2; 'Fensterlicht' = 2.2; 'Birne' = 80.0; 'Rosette' = 0.22 } }
[System.IO.File]::WriteAllText((Join-Path $werk 'ue-film-auftrag.json'), ($auftrag | ConvertTo-Json -Depth 5), (New-Object System.Text.UTF8Encoding($false)))
$zeiger = Join-Path $werk 'ue-film-manifest.txt'
if ($NurRender -ne 1) { Remove-Item $zeiger -Force -ErrorAction SilentlyContinue }

if ($NurRender -eq 1) { $manifest = (Get-Content $zeiger -Raw).Trim().Replace('\', '/') } else {
Stand 'Unreal: Karte bauen'
$a = @(('"' + $uproj + '"'), '-run=pythonscript', ('-script="' + (Join-Path $proj 'Content\Python\f01_gasse.py') + '"'),
       '-unattended', '-nosplash', '-nopause', '-stdout', '-FullStdOutLogOutput', ('-abslog="' + (Join-Path $u 'ue-szene.log') + '"'))
$pr = Start-Process -FilePath $exe -ArgumentList $a -PassThru -WindowStyle Hidden
$pr.WaitForExit(3600 * 1000) | Out-Null
Stand ("Karte Ende {0}" -f $pr.ExitCode)
if (-not (Test-Path $zeiger)) { Stand 'kein Manifest - Abbruch (siehe ue-szene.log)'; exit 5 }
$manifest = (Get-Content $zeiger -Raw).Trim().Replace('\', '/')
}
if ($NurKarte -eq 1) { Stand 'nur Karte - fertig'; exit 0 }

Stand 'Unreal: rendern'
$b = @(('"' + $uproj + '"'), '-game', ('-MoviePipelineConfig="' + $manifest + '"'), '-dx12', '-sm6',
       '-RenderOffscreen', '-unattended', '-nosplash', '-nopause', '-stdout', '-FullStdOutLogOutput', ('-abslog="' + (Join-Path $u 'ue-render.log') + '"'))
$pr = Start-Process -FilePath $exe -ArgumentList $b -PassThru -WindowStyle Hidden
$pr.WaitForExit(4 * 3600 * 1000) | Out-Null
Stand ("Render Ende {0}" -f $pr.ExitCode)
Get-ChildItem $ordner -Filter *.png -ErrorAction SilentlyContinue | ForEach-Object { Stand ('Bild ' + $_.Name) }
Stand 'fertig'
