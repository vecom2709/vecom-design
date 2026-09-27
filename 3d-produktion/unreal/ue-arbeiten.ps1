# Fallstudien in Unreal: Projekt anlegen (einmal), Szene bauen, Path Tracer rechnen.
# Laeuft im Hintergrund; Stand in 3d-produktion\branchen\ue-arbeiten.txt.
#
# Aufruf:  ue-arbeiten.ps1 -Projekte cavaleri[,jonika,...] [-Ev 0] [-Breite 1200] [-Hoehe 675]
#                          [-Raumproben 16] [-Spp 32] [-Marke probe] [-SonneLux 110000]
param(
  [string]$Projekte = 'cavaleri',
  [double]$Ev = 0.0,
  [int]$Breite = 1200, [int]$Hoehe = 675,
  [int]$Raumproben = 16, [int]$Spp = 32,
  [string]$Marke = 'probe',
  [double]$SonneLux = 110000.0,
  [double]$Himmel = 1.0,
  [double]$LampeFaktor = 1.0,
  [double]$DofBlende = 0.0,
  # 27.09.2026 (Kueche): mehrere Kameras, Weissabgleich, sichtbare Leuchtstreifen
  [string]$Kameras = '',
  [double]$Weiss = 6500.0,
  [int]$Sichtbar = 0,
  [double]$StreifenFaktor = 1.0,
  [double]$SonneKelvin = 5400.0,
  [double]$Saettigung = 1.0,
  [string]$Farbgewinn = ''
)
$exe   = 'C:\Program Files\Epic Games\UE_5.8\Engine\Binaries\Win64\UnrealEditor-Cmd.exe'
$ue    = 'C:\Users\manue\Documents\Unreal Projects'
$proj  = Join-Path $ue 'VecomArbeiten'
$uproj = Join-Path $proj 'VecomArbeiten.uproject'
$werk  = 'C:\Users\manue\Desktop\Vecom Design\werkzeug'
$br    = 'C:\Users\manue\Desktop\Vecom Design\3d-produktion\branchen'
$stand = Join-Path $br 'ue-arbeiten.txt'
function Stand($t) { ('{0} {1}' -f (Get-Date -Format 'HH:mm:ss'), $t) | Out-File -Append -Encoding utf8 $stand }

# Ein eigenes Projekt, damit Villa, Tisch und Showroom unberuehrt bleiben.
# Konfiguration (DX12, SM6, Raytracing, Path Tracer) von der Villa -- dort
# erprobt und gemessen.
if (-not (Test-Path $uproj)) {
  New-Item -ItemType Directory -Force -Path $proj, (Join-Path $proj 'Content\Python'), (Join-Path $proj 'Import') | Out-Null
  Copy-Item (Join-Path $ue 'VecomVilla\Config') -Destination $proj -Recurse -Force
  $v = Get-Content (Join-Path $ue 'VecomVilla\VecomVilla.uproject') -Raw | ConvertFrom-Json
  $v.Description = 'VECOM Fallstudien - Arbeitsplatz-Szenen aus Blender, Path Tracer'
  $v.Plugins = @($v.Plugins | Where-Object { $_.Name -ne 'ProceduralVegetationEditor' })
  $json = $v | ConvertTo-Json -Depth 8
  [System.IO.File]::WriteAllText($uproj, $json, (New-Object System.Text.UTF8Encoding($false)))
  Stand 'Projekt VecomArbeiten angelegt'
}
Copy-Item (Join-Path $werk 'ue-a01_szene.py') (Join-Path $proj 'Content\Python\a01_szene.py') -Force

$editor = @(Get-CimInstance Win32_Process -Filter "Name='UnrealEditor.exe'" -ErrorAction SilentlyContinue |
            Where-Object { $_.CommandLine -and $_.CommandLine -match 'VecomArbeiten' })
if ($editor) { Stand 'Editor auf VecomArbeiten offen - kein Lauf'; exit 4 }

foreach ($p in ($Projekte -split '[,;]' | Where-Object { $_ })) {
  $q = Join-Path $br ("render\arbeiten\{0}\unreal" -f $p)
  Copy-Item (Join-Path $q "$p.glb"), (Join-Path $q "$p.json") (Join-Path $proj 'Import') -Force
  $auftrag = [ordered]@{ projekt = $p; ev = $Ev; breite = $Breite; hoehe = $Hoehe; raumproben = $Raumproben;
                         spp = $Spp; marke = $Marke; sonne_lux = $SonneLux; himmel = $Himmel; lampe_faktor = $LampeFaktor; dof_blende = $DofBlende;
                         kameras = $Kameras; weiss = $Weiss; sichtbare_lichter = $Sichtbar; streifen_faktor = $StreifenFaktor; sonne_kelvin = $SonneKelvin; saettigung = $Saettigung; farbgewinn = $Farbgewinn;
                         ordner = $q }
  [System.IO.File]::WriteAllText((Join-Path $werk 'ue-arbeiten-auftrag.json'), ($auftrag | ConvertTo-Json),
                                 (New-Object System.Text.UTF8Encoding($false)))
  $log1 = Join-Path $br ("ue-arbeiten-{0}-szene.log" -f $p)
  Stand "$p Szene startet"
  $a = @(('"' + $uproj + '"'), '-run=pythonscript', ('-script="' + (Join-Path $proj 'Content\Python\a01_szene.py') + '"'),
         '-unattended', '-nosplash', '-nopause', '-stdout', '-FullStdOutLogOutput', ('-abslog="' + $log1 + '"'))
  $pr = Start-Process -FilePath $exe -ArgumentList $a -PassThru -WindowStyle Hidden
  $pr.WaitForExit(1800 * 1000) | Out-Null
  Stand ("$p Szene Ende {0}" -f $pr.ExitCode)
  Start-Sleep 5
  $zeiger = Join-Path $werk 'ue-arbeiten-manifest.txt'
  if (-not (Test-Path $zeiger)) { Stand "$p kein Manifest"; continue }
  $manifest = (Get-Content $zeiger -Raw).Trim().Replace('\', '/')
  $log2 = Join-Path $br ("ue-arbeiten-{0}-render.log" -f $p)
  Stand "$p Render startet"
  $b = @(('"' + $uproj + '"'), '-game', ('-MoviePipelineConfig="' + $manifest + '"'), '-dx12', '-sm6',
         '-RenderOffscreen', '-unattended', '-nosplash', '-nopause', '-stdout', '-FullStdOutLogOutput', ('-abslog="' + $log2 + '"'))
  $pr = Start-Process -FilePath $exe -ArgumentList $b -PassThru -WindowStyle Hidden
  $pr.WaitForExit(4 * 3600 * 1000) | Out-Null
  Stand ("$p Render Ende {0}" -f $pr.ExitCode)
  Remove-Item $zeiger -Force -ErrorAction SilentlyContinue
}
Stand 'fertig'
