# Registriert die Windows-Aufgabe "VECOM Akquise": taeglich 02:30 "npm run alles".
# Nachts, weil Lighthouse und Playwright den Rechner beschaeftigen; laeuft der
# Rechner da nicht, holt Windows den Lauf beim naechsten Start nach.
# Aufruf: powershell -NoProfile -ExecutionPolicy Bypass -File planen.ps1
$ordner = Split-Path -Parent $MyInvocation.MyCommand.Path
$npm = (Get-Command npm.cmd -ErrorAction Stop).Source
$log = Join-Path $ordner 'daten\logs\aufgabe.log'
New-Item -ItemType Directory -Force -Path (Split-Path $log) | Out-Null
$aktion = New-ScheduledTaskAction -Execute 'cmd.exe' -Argument "/c cd /d `"$ordner`" && `"$npm`" run alles >> `"$log`" 2>&1"
$ausloeser = New-ScheduledTaskTrigger -Daily -At 2:30am
$einst = New-ScheduledTaskSettingsSet -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Hours 5) -MultipleInstances IgnoreNew
Register-ScheduledTask -TaskName 'VECOM Akquise' -Action $aktion -Trigger $ausloeser -Settings $einst -Description 'Recherche, Website-Audit und Textvorschlaege fuer vecom-design.it/app/akquise. Versendet nie selbst.' -Force | Out-Null
Write-Output "Aufgabe 'VECOM Akquise' eingerichtet (taeglich 02:30). Protokoll: $log"

# Auf Abruf (27.09.2026): stündlich nur die wartenden Suchaufträge -- der
# Partner-Finder legt für unbekannte Orte einen Overture-Auftrag für die
# ganze Provinz an. Ohne Auftrag endet der Lauf nach einer Anfrage. Die
# Sperrdatei verhindert, dass er dem Nachtlauf in die Quere kommt.
$aktion2 = New-ScheduledTaskAction -Execute 'cmd.exe' -Argument "/c cd /d `"$ordner`" && `"$npm`" run recherche >> `"$log`" 2>&1"
$ausloeser2 = New-ScheduledTaskTrigger -Once -At (Get-Date).Date.AddMinutes(5) -RepetitionInterval (New-TimeSpan -Hours 1)
$einst2 = New-ScheduledTaskSettingsSet -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Hours 2) -MultipleInstances IgnoreNew
Register-ScheduledTask -TaskName 'VECOM Akquise Abruf' -Action $aktion2 -Trigger $ausloeser2 -Settings $einst2 -Description 'Stuendlich: wartende Suchauftraege (Overture auf Abruf) fuer vecom-design.it. Versendet nie selbst.' -Force | Out-Null
Write-Output "Aufgabe 'VECOM Akquise Abruf' eingerichtet (stuendlich). Protokoll: $log"
