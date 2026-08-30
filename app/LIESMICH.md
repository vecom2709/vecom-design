# Verwaltungsplattform — Einrichtung

Reines PHP 8 mit MariaDB, ohne Framework und ohne Composer. Dieselbe Bauweise
wie die Website: hochladen, fertig. Der Deploy bringt den Ordner `app/`
automatisch mit auf den Webspace.

## Einmalig einrichten

**1. Datenbank im KAS anlegen**
KAS → *Datenbanken* → neue Datenbank. Notiere Name, Benutzer und Passwort.

**2. Zugangsdaten hinterlegen**
`app/config.local.example.php` auf dem Server nach `app/config.local.php`
kopieren und ausfüllen. Diese Datei kommt nie ins Repository und wird vom
Deploy nie überschrieben — genau wie beim Kontaktformular.

**3. Tabellen anlegen**
Über SSH oder den KAS-Cronjob einmalig ausführen:

    php app/tools/migrate.php

**4. Eigenen Zugang anlegen**

    php app/tools/admin_anlegen.php "Uwe Vetter" kontakt@vecom-design.it

Das Passwort wird abgefragt und steht nicht in der Befehlszeile.

**5. Pakete übernehmen** (die drei von vecom-design.it)

    php app/tools/pakete_uebernehmen.php

Danach erreichbar unter `https://vecom-design.it/app/`.

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
