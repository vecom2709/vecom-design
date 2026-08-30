<?php
declare(strict_types=1);
/** Die Pakete von vecom-design.it — mit den Texten aller drei Sprachen, genau
    so, wie sie heute auf der Seite stehen. Die Daten liegen daneben in
    standardpakete.json, damit sie ohne PHP-Escaping gepflegt werden koennen.
    Eine Quelle fuer Einrichter, Kommandozeile und Website. */
$datei = __DIR__ . '/standardpakete.json';
$daten = is_readable($datei) ? json_decode((string) file_get_contents($datei), true) : null;
return is_array($daten) ? $daten : [];
