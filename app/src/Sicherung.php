<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';

/**
 * Naechtlicher Auszug der Datenbank.
 *
 * Der Hoster sichert den Webspace, die Datenbank aber nicht zuverlaessig.
 * In der Datenbank stehen Kunden, Bestellungen, Zahlungen und Belegnummern
 * — genau das, was sich nicht wiederbeschaffen laesst.
 *
 * Der Auszug landet deshalb ALS DATEI auf dem Webspace. Das ist Absicht:
 * die Webspace-Sicherung des Hosters nimmt ihn dann mit, und aus zwei
 * halben Sicherungen wird eine ganze.
 *
 * Kein mysqldump, kein exec — auf dem Webspace gibt es keine Shell. Der
 * Auszug entsteht in PHP ueber PDO und wird gleich beim Schreiben
 * komprimiert, damit nie die ganze Datenbank im Speicher liegt.
 */
final class Sicherung
{
    /** So viele Tage werden aufgehoben. */
    public const AUFHEBEN_TAGE = 14;

    /** Zeilen je INSERT. Gross genug fuer Tempo, klein genug fuer max_allowed_packet. */
    private const ZEILEN_JE_INSERT = 200;

    /** Der Ablageordner, samt seiner Sperre. */
    public static function ordner(): string
    {
        $pfad = dirname(__DIR__) . '/sicherungen';
        if (!is_dir($pfad)) {
            if (!@mkdir($pfad, 0755, true) && !is_dir($pfad)) {
                throw new RuntimeException('Der Ordner für Sicherungen lässt sich nicht anlegen.');
            }
        }
        // Dieselbe Sperre wie bei den Uploads: nicht abrufbar, kein
        // Verzeichnislisting, kein PHP. Ein Datenbankauszug ist das
        // Letzte, was ueber das Web erreichbar sein darf.
        $sperre = $pfad . '/.htaccess';
        if (!is_file($sperre)) {
            @file_put_contents($sperre, "Require all denied\nOptions -Indexes -ExecCGI\nphp_flag engine off\n");
        }
        return $pfad;
    }

    /** Heute schon gesichert? Eine Sicherung je Tag genuegt. */
    public static function heuteSchon(): bool
    {
        return is_file(self::ordner() . '/' . self::name(date('Y-m-d')));
    }

    private static function name(string $tag): string
    {
        return "vecom-$tag.sql.gz";
    }

    /**
     * Ein Durchlauf. Gibt zurueck, was passiert ist — die Zeile landet in
     * der Bilanz des Cronlaufs.
     *
     * @return array{datei?:string,bytes?:int,tabellen?:int,geloescht?:int,uebersprungen?:string}
     */
    public static function laufen(bool $erzwingen = false): array
    {
        if (!$erzwingen && self::heuteSchon()) {
            return ['uebersprungen' => 'heute bereits gesichert'];
        }

        $ordner = self::ordner();
        $ziel   = $ordner . '/' . self::name(date('Y-m-d'));
        // Erst unter einem Zwischennamen schreiben. Bricht der Lauf ab,
        // bleibt keine halbe Datei liegen, die wie eine gute aussieht.
        $roh = $ziel . '.teil';

        $strom = @gzopen($roh, 'wb6');
        if ($strom === false) {
            throw new RuntimeException('Sicherungsdatei lässt sich nicht anlegen.');
        }

        try {
            $tabellen = self::schreiben($strom);
        } catch (Throwable $e) {
            @gzclose($strom);
            @unlink($roh);
            throw $e;
        }
        gzclose($strom);

        if (!@rename($roh, $ziel)) {
            @unlink($roh);
            throw new RuntimeException('Sicherung lässt sich nicht ablegen.');
        }

        return [
            'datei'     => basename($ziel),
            'bytes'     => (int) filesize($ziel),
            'tabellen'  => $tabellen,
            'geloescht' => self::aufraeumen(),
        ];
    }

    /** Schreibt den vollstaendigen Auszug in den offenen Strom. */
    private static function schreiben($strom): int
    {
        $pdo = Db::pdo();
        $schreib = static function (string $text) use ($strom): void {
            if (gzwrite($strom, $text) === false) {
                throw new RuntimeException('Schreiben der Sicherung fehlgeschlagen.');
            }
        };

        $schreib("-- Vecom Design — Datenbankauszug\n");
        $schreib('-- ' . date('d.m.Y H:i:s') . "\n");
        $schreib("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        // SHOW FULL TABLES liefert je Zeile [Name, Art]. Bewusst als Liste
        // von Paaren und nicht als Name=>Art-Karte: gaebe es die Art als
        // Schluessel, fielen alle Tabellen bis auf die letzte weg.
        $eintraege = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
        $anzahl = 0;
        $sichten = [];

        foreach ($eintraege as [$tabelle, $art]) {
            // Sichten kommen ganz zum Schluss: sie greifen auf Tabellen zu,
            // die es beim Einspielen erst geben muss.
            if (strtoupper((string) $art) !== 'BASE TABLE') {
                $sichten[] = (string) $tabelle;
                continue;
            }

            $q = self::q((string) $tabelle);
            $erzeugen = $pdo->query("SHOW CREATE TABLE $q")->fetch(PDO::FETCH_NUM);
            $schreib("DROP TABLE IF EXISTS $q;\n" . ($erzeugen[1] ?? '') . ";\n\n");
            $anzahl++;

            // Ungepuffert lesen: sonst haelt PDO die ganze Tabelle im
            // Speicher, und bei den Dateien und Nachrichten wird das eng.
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            try {
                $zeilen  = $pdo->query("SELECT * FROM $q");
                $stapel  = [];
                $spalten = null;

                foreach ($zeilen as $zeile) {
                    if ($spalten === null) {
                        $spalten = implode(', ', array_map(
                            static fn($s) => self::q((string) $s),
                            array_keys($zeile)
                        ));
                    }
                    $werte = array_map(
                        static fn($w) => $w === null ? 'NULL' : $pdo->quote((string) $w),
                        array_values($zeile)
                    );
                    $stapel[] = '(' . implode(',', $werte) . ')';

                    if (count($stapel) >= self::ZEILEN_JE_INSERT) {
                        $schreib("INSERT INTO $q ($spalten) VALUES\n" . implode(",\n", $stapel) . ";\n");
                        $stapel = [];
                    }
                }
                if ($stapel) {
                    $schreib("INSERT INTO $q ($spalten) VALUES\n" . implode(",\n", $stapel) . ";\n");
                }
                $schreib("\n");
            } finally {
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            }
        }

        foreach ($sichten as $sicht) {
            $q = self::q($sicht);
            $erzeugen = (string) ($pdo->query("SHOW CREATE VIEW $q")->fetch(PDO::FETCH_NUM)[1] ?? '');
            // Ohne DEFINER: beim Einspielen gibt es den urspruenglichen
            // Benutzer meist nicht mehr, und die Zeile bricht den Import ab.
            $erzeugen = preg_replace('~DEFINER=`[^`]*`@`[^`]*`\s*~', '', $erzeugen) ?? $erzeugen;
            // Eine Sicht wird mit DROP VIEW abgeraeumt, nicht mit DROP TABLE.
            $schreib("DROP VIEW IF EXISTS $q;\n" . $erzeugen . ";\n\n");
            $anzahl++;
        }

        $schreib("SET FOREIGN_KEY_CHECKS=1;\n");
        return $anzahl;
    }

    /** Ein Bezeichner in Rueckwaerts-Anfuehrungszeichen, sicher gegen eigene. */
    private static function q(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    /** Loescht, was aelter ist als AUFHEBEN_TAGE. Gibt die Anzahl zurueck. */
    public static function aufraeumen(): int
    {
        $grenze = time() - self::AUFHEBEN_TAGE * 86400;
        $weg = 0;
        foreach (glob(self::ordner() . '/vecom-*.sql.gz') ?: [] as $datei) {
            if (@filemtime($datei) < $grenze && @unlink($datei)) { $weg++; }
        }
        // Angebrochene Dateien eines abgestuerzten Laufs raeumen wir auch weg.
        foreach (glob(self::ordner() . '/*.teil') ?: [] as $rest) {
            if (@filemtime($rest) < time() - 86400) { @unlink($rest); }
        }
        return $weg;
    }

    /** Was gerade da ist — fuer die Anzeige in der Verwaltung. */
    public static function vorhandene(): array
    {
        $liste = [];
        foreach (glob(self::ordner() . '/vecom-*.sql.gz') ?: [] as $datei) {
            $liste[] = [
                'name'  => basename($datei),
                'bytes' => (int) filesize($datei),
                'zeit'  => date('Y-m-d H:i:s', (int) filemtime($datei)),
            ];
        }
        usort($liste, static fn($a, $b) => strcmp($b['name'], $a['name']));
        return $liste;
    }
}
