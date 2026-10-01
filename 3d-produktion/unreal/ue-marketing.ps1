# Marketing-Film in Unreal (01.10.2026, Uwe B3): Blender baut, Unreal rendert.
# 1. Szene liegt schon als GLB + JSON vor (branchen_ort.py, Modus marketing_unreal)
# 2. Unreal baut Karte, Kamerafahrt und Renderliste (Commandlet, Projekt VecomMarketing)
# 3. Unreal rechnet die Bilder mit dem Path Tracer (-game, Render Queue)
# 4. Blender schneidet Titel und Abspann dazu und misst gegen das Referenzbild
# Stand in <Ordner der Szene>\ue-stand.txt. Ein offener Editor wird nicht angefasst.
#
# Aufruf: ue-marketing.ps1 -Szene <json> -Aus <mp4> [-Blenden 0] [-Spp 32] [-Raumproben 8] [-NurBilder 0] [-HdriYaw '']
param(
  [Parameter(Mandatory = $true)][string]$Szene,
  [Parameter(Mandatory = $true)][string]$Aus,
  [double]$Blenden = 0.0, [int]$Spp = 32, [int]$Raumproben = 8, [int]$NurBilder = 0,
  [string]$HdriYaw = '', [double]$HdriFaktor = 1.0, [int]$NurKarte = 0
)
$ErrorActionPreference = 'Continue'
$exe   = 'C:\Program Files\Epic Games\UE_5.8\Engine\Binaries\Win64\UnrealEditor-Cmd.exe'
$bl    = 'C:\Program Files\Blender Foundation\Blender 5.2\blender.exe'
$ue    = 'C:\Users\manue\Documents\Unreal Projects'
$proj  = Join-Path $ue 'VecomMarketing'
$uproj = Join-Path $proj 'VecomMarketing.uproject'
$werk  = 'C:\Users\manue\Desktop\Vecom Design\werkzeug'
$hier  = Split-Path -Parent $MyInvocation.MyCommand.Path
$skripte = Join-Path (Split-Path -Parent $hier) 'scripts'
$ordner = Split-Path -Parent $Szene
$stamm = [IO.Path]::GetFileNameWithoutExtension($Aus)
$bilder = Join-Path $ordner ("ue-bilder-$stamm")
$stand = Join-Path $ordner 'ue-stand.txt'
# Der Schnitt schreibt seinen Bericht nach <Aus ohne Endung>.json -- das darf
# nicht die Szenen-JSON sein (Probelauf 01.10.2026: Szene ueberschrieben).
if ([IO.Path]::ChangeExtension($Aus, '.json') -ieq $Szene) { Write-Error 'Szene und Bericht haetten denselben Namen'; exit 3 }
function Stand($t) { ('{0} {1}' -f (Get-Date -Format 'HH:mm:ss'), $t) | Out-File -Append -Encoding utf8 $stand }
$utf8 = New-Object System.Text.UTF8Encoding($false)

# Eigenes Projekt, damit Villa, Fallstudien und Showroom unberuehrt bleiben.
# Konfiguration (DX12, SM6, Raytracing, Path Tracer) von VecomArbeiten, dazu
# das Plugin HDRIBackdrop (Aufnahme als Licht, Hintergrund und Boden).
if (-not (Test-Path $uproj)) {
  New-Item -ItemType Directory -Force -Path $proj, (Join-Path $proj 'Content\Python') | Out-Null
  Copy-Item (Join-Path $ue 'VecomArbeiten\Config') -Destination $proj -Recurse -Force
  $v = Get-Content (Join-Path $ue 'VecomArbeiten\VecomArbeiten.uproject') -Raw | ConvertFrom-Json
  $v.Description = 'VECOM Marketing - Szenen aus Blender, Path Tracer'
  $liste = @($v.Plugins | Where-Object { $_.Name -ne 'HDRIBackdrop' })
  $liste += [pscustomobject]@{ Name = 'HDRIBackdrop'; Enabled = $true }
  $v.Plugins = $liste
  [IO.File]::WriteAllText($uproj, ($v | ConvertTo-Json -Depth 8), $utf8)
  Stand 'Projekt VecomMarketing angelegt'
}
New-Item -ItemType Directory -Force -Path (Join-Path $proj 'Content\Python'), $bilder | Out-Null
Copy-Item (Join-Path $hier 'ue-m01_marketing.py') (Join-Path $proj 'Content\Python\m01_marketing.py') -Force

$editor = @(Get-CimInstance Win32_Process -Filter "Name='UnrealEditor.exe'" -ErrorAction SilentlyContinue |
            Where-Object { $_.CommandLine -and $_.CommandLine -match 'VecomMarketing' })
if ($editor) { Stand 'Editor auf VecomMarketing offen - kein Lauf'; exit 4 }

$auftrag = [ordered]@{ szene = $Szene; ordner = $bilder; ev = $Blenden; spp = $Spp; raumproben = $Raumproben; nur_bilder = $NurBilder;
                       hdri_yaw = $HdriYaw; hdri_faktor = $HdriFaktor }
[IO.File]::WriteAllText((Join-Path $werk 'ue-marketing-auftrag.json'), ($auftrag | ConvertTo-Json), $utf8)
$zeiger = Join-Path $werk 'ue-marketing-manifest.txt'
Remove-Item $zeiger, (Join-Path $werk 'ue-marketing-bericht.json') -Force -ErrorAction SilentlyContinue

Stand 'Unreal: Karte bauen'
$a = @(('"' + $uproj + '"'), '-run=pythonscript', ('-script="' + (Join-Path $proj 'Content\Python\m01_marketing.py') + '"'),
       '-unattended', '-nosplash', '-nopause', '-stdout', '-FullStdOutLogOutput', ('-abslog="' + (Join-Path $ordner "ue-$stamm-szene.log") + '"'))
$pr = Start-Process -FilePath $exe -ArgumentList $a -PassThru -WindowStyle Hidden
if (-not $pr.WaitForExit(40 * 60 * 1000)) { $pr.Kill(); Stand 'Karte: nach 40 Minuten abgebrochen'; exit 6 }
Stand ("Karte Ende {0}" -f $pr.ExitCode)
if (-not (Test-Path $zeiger)) { Stand 'kein Manifest - Abbruch (siehe Szenen-Log)'; exit 5 }
Copy-Item (Join-Path $werk 'ue-marketing-bericht.json') (Join-Path $ordner "ue-$stamm-bericht.json") -Force
if ($NurKarte -eq 1) { Stand 'nur Karte - fertig'; exit 0 }
$manifest = (Get-Content $zeiger -Raw).Trim().Replace('\', '/')

Stand 'Unreal: rendern'
Get-ChildItem $bilder -Filter 'bild-*.png' -ErrorAction SilentlyContinue | Remove-Item -Force
$t0 = Get-Date
$b = @(('"' + $uproj + '"'), '-game', ('-MoviePipelineConfig="' + $manifest + '"'), '-dx12', '-sm6',
       '-RenderOffscreen', '-unattended', '-nosplash', '-nopause', '-stdout', '-FullStdOutLogOutput', ('-abslog="' + (Join-Path $ordner "ue-$stamm-render.log") + '"'))
$pr = Start-Process -FilePath $exe -ArgumentList $b -PassThru -WindowStyle Hidden
if (-not $pr.WaitForExit(6 * 3600 * 1000)) { $pr.Kill(); Stand 'Render: nach 6 Stunden abgebrochen'; exit 7 }
$n = @(Get-ChildItem $bilder -Filter 'bild-*.png').Count
$sek = [int]((Get-Date) - $t0).TotalSeconds
Stand ("Render Ende {0}: {1} Bilder in {2} s" -f $pr.ExitCode, $n, $sek)
if ($n -eq 0) { Stand 'keine Bilder - Abbruch (siehe Render-Log)'; exit 8 }

# Schnitt in Blender: Titel, Abspann, Messung gegen das Cycles-Referenzbild.
$sz = Get-Content $Szene -Raw | ConvertFrom-Json
$schnitt = [ordered]@{ ordner = $bilder; aus = $Aus; px = @($sz.px); fps = $sz.fps; titel = $sz.titel; abspann = $sz.abspann;
                       referenz = $sz.referenz; render_sekunden = $sek }
$sj = Join-Path $ordner "ue-$stamm-schnitt.json"
[IO.File]::WriteAllText($sj, ($schnitt | ConvertTo-Json), $utf8)
& $bl -b -P (Join-Path $skripte 'marketing_schnitt.py') -- ("auftrag=" + $sj) *> (Join-Path $ordner "ue-$stamm-schnitt.log")
if (Test-Path $Aus) { Stand ('Film fertig {0:N1} MB' -f ((Get-Item $Aus).Length / 1MB)) } else { Stand 'Schnitt ohne Ergebnis' ; exit 9 }
Stand 'fertig'
