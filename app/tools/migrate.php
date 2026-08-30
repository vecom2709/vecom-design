<?php
declare(strict_types=1);
/* Spielt alle noch nicht angewandten Migrationen ein. Aufruf auf der
   Kommandozeile: php app/tools/migrate.php
   Jede Datei wird genau einmal ausgefuehrt und danach vermerkt. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Nur auf der Kommandozeile.'); }
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Db.php';

Db::run('CREATE TABLE IF NOT EXISTS migrations (
  datei VARCHAR(190) NOT NULL PRIMARY KEY,
  angewandt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$erledigt = array_column(Db::all('SELECT datei FROM migrations'), 'datei');
$dateien = glob(__DIR__ . '/../migrations/*.sql') ?: [];
sort($dateien);
$neu = 0;
foreach ($dateien as $pfad) {
    $name = basename($pfad);
    if (in_array($name, $erledigt, true)) { continue; }
    $sql = (string) file_get_contents($pfad);
    // Kommentare entfernen, dann Anweisung fuer Anweisung ausfuehren.
    $sql = preg_replace('~^\s*--.*$~m', '', $sql) ?? $sql;
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $anweisung) {
        Db::pdo()->exec($anweisung);
    }
    Db::insert('migrations', ['datei' => $name]);
    echo "eingespielt: $name\n";
    $neu++;
}
echo $neu === 0 ? "Nichts zu tun — die Datenbank ist aktuell.\n" : "$neu Migration(en) eingespielt.\n";
