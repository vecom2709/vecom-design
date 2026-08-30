<?php
declare(strict_types=1);
/* Spielt alle noch nicht angewandten Migrationen ein.
   Aufruf auf der Kommandozeile: php app/tools/migrate.php
   Jede Datei wird genau einmal ausgefuehrt und danach vermerkt.
   Dieselbe Logik nutzt auch app/einrichten.php. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Nur auf der Kommandozeile.'); }
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Db.php';
require __DIR__ . '/../src/Einrichtung.php';

$neu = Einrichtung::migrieren();
foreach ($neu as $n) { echo "eingespielt: $n\n"; }
echo $neu ? count($neu) . " Migration(en) eingespielt.\n" : "Nichts zu tun — die Datenbank ist aktuell.\n";
