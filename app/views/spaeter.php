<?php
$titel = ['nachrichten'=>'Nachrichten','onboarding'=>'Kunden-Fragebögen','dateien'=>'Dateien','zahlungen'=>'Zahlungen',
'rechnungen'=>'Rechnungen','statistiken'=>'Statistiken','integrationen'=>'Integrationen','monitoring'=>'Website-Monitoring',
'einstellungen'=>'Einstellungen','unbekannt'=>'Nicht gefunden'][$bereich] ?? 'Bereich';
$erklaerung = [
 'nachrichten'=>'Nachrichten zwischen dir und deinen Kunden, je Projekt.',
 'onboarding'=>'Der Fragebogen, den der Kunde nach der Zahlung ausfüllt.',
 'dateien'=>'Logos, Bilder, Texte und Dokumente je Kunde und Projekt.',
 'zahlungen'=>'Alle Zahlungen quer über alle Bestellungen, mit Anbieter und Beleg.',
 'rechnungen'=>'Rechnungsnummern, Beträge, Steuer, Fälligkeit.',
 'statistiken'=>'Auswertungen über längere Zeiträume.',
 'integrationen'=>'Zahlungsanbieter, E-Mail, Kalender, Speicher — mit Status und Fehlern.',
 'monitoring'=>'Erreichbarkeit, HTTP-Status, SSL-Laufzeit und Antwortzeit je Website.',
 'einstellungen'=>'Firmendaten, Steuersatz, Benutzer und Rollen.',
][$bereich] ?? null;
?>
<div class="kopf"><h1><?= Fmt::h($titel) ?></h1></div>
<div class="block">
  <?php if ($erklaerung): ?>
    <p style="color:var(--dim);margin-bottom:10px"><?= Fmt::h($erklaerung) ?></p>
    <p style="color:var(--leise);font-size:13px">Die Tabellen dafür stehen bereits in der Datenbank — dieser Bereich
    kommt in der nächsten Ausbaustufe. Bis dahin bleibt die Navigation vollständig, damit sich nichts verschiebt.</p>
  <?php else: ?>
    <div class="leer">Diese Seite gibt es nicht.</div>
  <?php endif; ?>
</div>
