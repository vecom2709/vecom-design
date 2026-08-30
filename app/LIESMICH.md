# Verwaltungsplattform — Einrichtung

Reines PHP 8 mit MariaDB, ohne Framework und ohne Composer. Dieselbe Bauweise
wie die Website: hochladen, fertig. Der Deploy bringt den Ordner `app/`
automatisch mit auf den Webspace.

## Einmalig einrichten

**1. Datenbank im KAS anlegen**
KAS → *Datenbanken* → neue Datenbank. Notiere Name, Benutzer und Passwort.

**2. Einrichter aufrufen**

    https://vecom-design.it/app/einrichten.php

Dort Einrichtungsschlüssel, die Datenbankdaten und deinen gewünschten Zugang
eintragen. Der Einrichter legt die Tabellen an, richtet den Zugang ein und
übernimmt die drei Pakete von vecom-design.it — alles in einem Schritt.

Danach riegelt er sich selbst ab: Sobald `app/config.local.php` existiert,
nimmt er keine Eingaben mehr an.

### Der Weg über die Kommandozeile

Wer SSH hat, kann es auch von Hand machen:

    php app/tools/migrate.php
    php app/tools/admin_anlegen.php "Uwe Vetter" kontakt@vecom-design.it
    php app/tools/pakete_uebernehmen.php

Beide Wege benutzen dieselbe Logik in `app/src/Einrichtung.php`.

## Wie das System zusammenhängt

Alle Vorgänge laufen über `app/src/Events.php` — dort steht an einer Stelle,
was ein Ereignis auslöst. Keine Ansicht schreibt selbst quer in andere
Bereiche. Deshalb bleiben Bestellung, Zahlung, Projekt, Aktivität und
Benachrichtigung zwangsläufig synchron.

    Kunde → Bestellung → Zahlung → Projekt → Website
                                      ↓
                     Aufgaben · Nachrichten · Dateien · Fragebogen

Das Dashboard speichert nichts. Jede Zahl in `app/src/Kennzahlen.php` wird bei
jedem Aufruf aus den Tabellen gerechnet.

**Projektstatus und Website-Status sind getrennt.** Ein Projekt kann
abgeschlossen sein, während die Website offline ist. Nur das Monitoring setzt
den technischen Status.

## Was schon läuft

Anmeldung mit Rollen, Kunden, Pakete, Bestellungen, Projekte, Aktivitäten,
Benachrichtigungen, globale Suche, Dashboard aus echten Daten.

## Was als Nächstes kommt

Nachrichten, Fragebogen, Dateien, Zahlungsanbieter über die Schnittstelle,
Rechnungen, Website-Monitoring per Cronjob, Kundenbereich. Die Tabellen dafür
stehen bereits — die Bereiche sind in der Navigation als solche gekennzeichnet.
