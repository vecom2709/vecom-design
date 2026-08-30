<?php
declare(strict_types=1);
/* Uebernimmt die drei Pakete von vecom-design.it in die Verwaltung.
   Laeuft mehrfach ohne Schaden: vorhandene Pakete werden am Kuerzel erkannt
   und aktualisiert statt doppelt angelegt.
   Aufruf: php app/tools/pakete_uebernehmen.php */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Nur auf der Kommandozeile.'); }
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Db.php';
require __DIR__ . '/../src/Einrichtung.php';

foreach (Einrichtung::pakete() as $zeile) { echo $zeile, "\n"; }
