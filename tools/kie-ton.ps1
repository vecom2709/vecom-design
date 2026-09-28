# tools/kie-ton.ps1 -- Musik (Suno) und Sprecherstimme (ElevenLabs) ueber kie.ai
# fuer die Werbefilme der Partnerseiten (28.09.2026).
#
# Der Schluessel gehoert Uwe und steht NUR in seiner Umgebung:
#   Windows -> "Umgebungsvariablen fuer dieses Konto bearbeiten" -> Neu:
#   Name KIE_API_KEY, Wert = Schluessel. Er wird hier gelesen, nie ausgegeben,
#   nie gespeichert, nie in eine Datei geschrieben.
#
# Aufruf:
#   powershell -ExecutionPolicy Bypass -File kie-ton.ps1 guthaben
#   powershell -ExecutionPolicy Bypass -File kie-ton.ps1 musik  <auftrag.json> <zielordner>
#   powershell -ExecutionPolicy Bypass -File kie-ton.ps1 sprache <auftrag.json> <zielordner>
# Jeder Auftrag fragt ZUERST das Guthaben ab und bricht ab, wenn es unter der
# im Auftrag genannten Mindestgrenze liegt. Danach wird der Verbrauch gemeldet.
param([string]$Modus = 'guthaben', [string]$Auftrag = '', [string]$Ziel = '')
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$Basis = 'https://api.kie.ai'

$schluessel = [Environment]::GetEnvironmentVariable('KIE_API_KEY', 'User')
if (-not $schluessel) { $schluessel = [Environment]::GetEnvironmentVariable('KIE_API_KEY', 'Machine') }
if (-not $schluessel) { $schluessel = $env:KIE_API_KEY }
if (-not $schluessel) { 'KEIN SCHLUESSEL: Bitte KIE_API_KEY als Benutzer-Umgebungsvariable anlegen.'; exit 2 }
$kopf = @{ Authorization = "Bearer $schluessel"; 'Content-Type' = 'application/json' }

function Guthaben {
    $r = Invoke-RestMethod -Uri "$Basis/api/v1/chat/credit" -Headers $kopf -TimeoutSec 30
    if ($r.code -ne 200) { throw "kie.ai antwortet nicht wie erwartet (code $($r.code)): $($r.msg)" }
    return [double]$r.data
}

$vorher = Guthaben
"GUTHABEN $vorher"
if ($Modus -eq 'guthaben') { exit 0 }

$a = Get-Content -LiteralPath $Auftrag -Raw -Encoding UTF8 | ConvertFrom-Json
if ($a.mindestens -and $vorher -lt [double]$a.mindestens) { "ABBRUCH: Guthaben $vorher unter $($a.mindestens)."; exit 3 }
New-Item -ItemType Directory -Force -Path $Ziel | Out-Null

if ($Modus -eq 'musik') {
    # Suno ueber kie.ai: eigener Stil, Instrumental. Suno liefert zwei Fassungen.
    $body = @{ prompt = $a.prompt; style = $a.style; title = $a.titel; customMode = $true; instrumental = $true
               model = $a.modell; callBackUrl = 'https://vecom-design.it/kie-rueckruf' } | ConvertTo-Json -Depth 5
    $r = Invoke-RestMethod -Method Post -Uri "$Basis/api/v1/generate" -Headers $kopf -Body ([Text.Encoding]::UTF8.GetBytes($body)) -TimeoutSec 60
    if ($r.code -ne 200) { "ABGELEHNT: $($r.msg)"; exit 4 }
    $id = $r.data.taskId; "AUFTRAG $id"
    for ($i = 0; $i -lt 120; $i++) {
        Start-Sleep -Seconds 10
        $s = Invoke-RestMethod -Uri "$Basis/api/v1/generate/record-info?taskId=$id" -Headers $kopf -TimeoutSec 30
        $st = $s.data.status
        if ($st -eq 'SUCCESS') {
            $n = 0
            foreach ($t in $s.data.response.sunoData) {
                $n++; $f = Join-Path $Ziel ("{0}-{1}.mp3" -f $a.name, $n)
                Invoke-WebRequest -Uri $t.audioUrl -OutFile $f -UseBasicParsing -TimeoutSec 300
                "DATEI $f DAUER $($t.duration)"
            }
            break
        }
        if ($st -match 'FAIL|ERROR') { "FEHLGESCHLAGEN: $st $($s.data.errorMessage)"; exit 5 }
    }
}
elseif ($Modus -eq 'sprache') {
    # ElevenLabs Turbo 2.5 ueber kie.ai (kann die Sprache erzwingen): ein Satz je Datei,
    # damit der Schnitt jeden Satz genau auf sein Bild legen kann.
    $nr = 0
    foreach ($satz in $a.saetze) {
        $modell = if ($a.modell) { $a.modell } else { 'elevenlabs/text-to-speech-turbo-2-5' }
        $gemini = $modell -like 'google/*'
        $nr++; $f = Join-Path $Ziel ("{0}-{1:D2}.{2}" -f $a.name, $nr, $(if ($gemini) { 'wav' } else { 'mp3' }))
        if ((Test-Path $f) -and (Get-Item $f).Length -gt 1000) { "VORHANDEN $f"; continue }
        if ($gemini) {
            # Google Gemini TTS (28.09.2026: ElevenLabs bei kie.ai liefert nur "500 Internal Error").
            # Szene und Sprecherprofil bleiben fuer jeden Satz gleich -> gleiche Stimme ueber alle Saetze.
            $body = @{ model = $modell; callBackUrl = 'https://vecom-design.it/kie-rueckruf'
                       input = @{ temperature = $(if ($a.temperatur) { [double]$a.temperatur } else { 0.6 }); scene = $a.szene
                                  speakers = @(@{ speaker_id = 'Speaker 1'; voice_name = $a.stimme; audio_profile = $a.profil; accent = 'Neutral'; style = $a.stil; pace = $a.gangart })
                                  dialogue_turns = @(@{ speaker_id = 'Speaker 1'; text = $satz }) } } | ConvertTo-Json -Depth 8
        } else {
        $body = @{ model = $modell; callBackUrl = 'https://vecom-design.it/kie-rueckruf'
                   input = @{ text = $satz; voice = $a.stimme; stability = 0.55; similarity_boost = 0.75; style = 0.1; speed = $a.tempo; language_code = $a.sprache } } | ConvertTo-Json -Depth 5
        }
        $r = Invoke-RestMethod -Method Post -Uri "$Basis/api/v1/jobs/createTask" -Headers $kopf -Body ([Text.Encoding]::UTF8.GetBytes($body)) -TimeoutSec 60
        if ($r.code -ne 200) { "ABGELEHNT Satz ${nr}: $($r.msg)"; exit 4 }
        $id = $r.data.taskId
        for ($i = 0; $i -lt 100; $i++) {
            Start-Sleep -Seconds 3
            $s = Invoke-RestMethod -Uri "$Basis/api/v1/jobs/recordInfo?taskId=$id" -Headers $kopf -TimeoutSec 30
            if ($s.data.state -eq 'success') {
                $url = ($s.data.resultJson | ConvertFrom-Json).resultUrls[0]
                Invoke-WebRequest -Uri $url -OutFile $f -UseBasicParsing -TimeoutSec 120
                "DATEI $f CREDITS $($s.data.creditsConsumed)"; break
            }
            if ($s.data.state -eq 'fail') { "FEHLGESCHLAGEN Satz ${nr}: $($s.data.failMsg)"; exit 5 }
        }
    }
}
$nachher = Guthaben
"GUTHABEN_NACHHER $nachher VERBRAUCH $([math]::Round($vorher - $nachher, 1))"
