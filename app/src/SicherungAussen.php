<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Sicherung.php';
require_once __DIR__ . '/Ablage.php';

/**
 * Sicherung außer Haus (AI Office Stufe 0, 06.10.2026, Uwe: „Auf Ihren PC“).
 *
 * WARUM
 *
 * Der tägliche Auszug lag nur auf demselben Webspace wie die Datenbank, 14
 * Tage, ohne Wiederherstellung und ohne Probe. Fällt der Webspace aus, sind
 * Daten und Sicherung zusammen weg. Jetzt holt Uwes Windows-Rechner jede
 * Nacht den Auszug und alle Kundendateien ab und spielt die Sicherung einmal
 * die Woche probeweise in eine eigene MariaDB ein.
 *
 * WARUM KEIN GEMEINSAMES GEHEIMNIS
 *
 * Der Rechner hat ein RSA-Schlüsselpaar. Den öffentlichen Teil trägt Uwe
 * einmal in der Verwaltung ein. Damit
 *   1. verschlüsselt der Server jede Datei (nur der Rechner kann sie lesen —
 *      auch wenn der Ordner in einer Cloud liegt oder die Leitung mitgelesen
 *      wird), und
 *   2. prüft der Server jede Anfrage auf die Unterschrift des Rechners.
 * Es gibt kein Passwort, das irgendwo im Klartext stehen könnte, und keins,
 * das Claude je zu sehen bekommt. Der private Schlüssel verlässt den Rechner nie.
 *
 * FORMAT (VCS1), Abschnitt für Abschnitt mit AES-256-GCM, damit nie eine ganze
 * Datei im Speicher liegt:
 *   "VCS1" | uint16 Länge | RSA-OAEP(AES-Schlüssel) | 8 Byte Nonce-Anfang |
 *   je Abschnitt: uint32 Länge (oberstes Bit = letzter) | Geheimtext | 16 Byte Tag
 *   Nonce = Anfang ‖ uint32 Nummer; AAD = "VCS1" ‖ uint32 Nummer ‖ letzter (1 Byte).
 * Wer am Ende abschneidet, fällt auf: Der letzte Abschnitt ist markiert und signiert.
 */
final class SicherungAussen
{
    public const ABSCHNITT = 1 << 20;   // 1 MiB je Abschnitt
    public const FENSTER_SEKUNDEN = 300;
    public const MIN_BITS = 3072;

    /* ------------------------------------------------------------ Schlüssel */

    public static function oeffentlich(): string
    {
        try { return (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'sicherung_oeffentlich'", [], ''); }
        catch (Throwable $e) { return ''; }
    }

    public static function eingerichtet(): bool { return self::oeffentlich() !== ''; }

    /** Kurzer Fingerabdruck zum Vergleichen mit dem, was der Rechner anzeigt. */
    public static function fingerabdruck(?string $pem = null): string
    {
        $pem ??= self::oeffentlich();
        if ($pem === '') { return ''; }
        $roh = base64_decode((string) preg_replace('~-----[^-]+-----|\s+~', '', $pem), true);
        return $roh === false ? '' : implode(':', str_split(substr(hash('sha256', $roh), 0, 16), 4));
    }

    /** @return array{ok:bool, text:string} */
    public static function schluesselSetzen(string $pem, string $wer): array
    {
        $pem = trim($pem);
        $k = $pem !== '' ? @openssl_pkey_get_public($pem) : false;
        if ($k === false) { return ['ok' => false, 'text' => 'Das ist kein öffentlicher Schlüssel (PEM, beginnt mit -----BEGIN PUBLIC KEY-----).']; }
        $d = openssl_pkey_get_details($k);
        if (($d['type'] ?? -1) !== OPENSSL_KEYTYPE_RSA || (int) ($d['bits'] ?? 0) < self::MIN_BITS) {
            return ['ok' => false, 'text' => 'Gebraucht wird ein RSA-Schlüssel mit mindestens ' . self::MIN_BITS . ' Bit.'];
        }
        $sauber = (string) $d['key'];
        $vorher = self::fingerabdruck();
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('sicherung_oeffentlich', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$sauber]);
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('sicherung_zeit_zuletzt', '0') ON DUPLICATE KEY UPDATE svalue = '0'");
        self::spur('sicherung_schluessel_gesetzt', ['fingerabdruck' => $vorher], ['fingerabdruck' => self::fingerabdruck($sauber), 'von' => $wer]);
        return ['ok' => true, 'text' => 'Eingetragen. Fingerabdruck ' . self::fingerabdruck($sauber) . ' — er muss mit dem auf dem Rechner übereinstimmen.'];
    }

    public static function schluesselEntfernen(string $wer): void
    {
        $vorher = self::fingerabdruck();
        Db::run("DELETE FROM settings WHERE skey IN ('sicherung_oeffentlich', 'sicherung_zeit_zuletzt')");
        self::spur('sicherung_schluessel_entfernt', ['fingerabdruck' => $vorher], ['von' => $wer]);
    }

    /**
     * Stimmt die Unterschrift des Rechners? Nachricht: aktion \n zeit_ms \n name \n sha256(rumpf).
     * Die Zeit muss im Fenster liegen und größer sein als jede zuvor — eine mitgeschnittene
     * Anfrage lässt sich so nicht wiederholen.
     */
    public static function anfrageStimmt(string $aktion, string $zeit, string $name, string $rumpf, string $signaturB64): bool
    {
        $pem = self::oeffentlich();
        if ($pem === '' || !ctype_digit($zeit) || strlen($zeit) > 15) { return false; }
        $ms = (int) $zeit;
        if (abs(intdiv($ms, 1000) - time()) > self::FENSTER_SEKUNDEN) { return false; }
        $sig = base64_decode($signaturB64, true);
        if ($sig === false || $sig === '') { return false; }
        $nachricht = $aktion . "\n" . $zeit . "\n" . $name . "\n" . hash('sha256', $rumpf);
        if (openssl_verify($nachricht, $sig, $pem, OPENSSL_ALGO_SHA256) !== 1) { return false; }
        // Nur weiter, wenn diese Zeit neuer ist als die letzte — atomar.
        Db::run("INSERT IGNORE INTO settings (skey, svalue) VALUES ('sicherung_zeit_zuletzt', '0')");
        return Db::run("UPDATE settings SET svalue = ? WHERE skey = 'sicherung_zeit_zuletzt' AND CAST(svalue AS UNSIGNED) < ?",
            [(string) $ms, $ms])->rowCount() === 1;
    }

    /* ---------------------------------------------------------------- Liste */

    public const NAME_AUSZUG = '~^vecom-\d{4}-\d{2}-\d{2}\.sql\.gz$~';
    /* Bis zu vier Ebenen tief (uploads/akquise/…, uploads/marketing/…); jede Ebene beginnt mit
       Buchstabe oder Ziffer — damit gibt es weder „..“ noch versteckte Dateien wie .htaccess. */
    public const NAME_DATEI = '~^[A-Za-z0-9][A-Za-z0-9._-]{0,119}(?:/[A-Za-z0-9][A-Za-z0-9._-]{0,119}){0,3}$~';

    /** @return array{auszuege:list<array>, dateien:list<array>} */
    public static function liste(): array
    {
        $auszuege = [];
        foreach (glob(Sicherung::ordner() . '/vecom-*.sql.gz') ?: [] as $p) {
            $n = basename($p);
            if (preg_match(self::NAME_AUSZUG, $n)) { $auszuege[] = ['name' => $n, 'bytes' => (int) filesize($p), 'zeit' => (int) filemtime($p)]; }
        }
        $dateien = [];
        $ordner = Ablage::ordner();
        $gehen = static function (string $rel, int $tiefe) use (&$gehen, &$dateien, $ordner): void {
            foreach (scandir($ordner . ($rel !== '' ? '/' . $rel : '')) ?: [] as $n) {
                $name = $rel !== '' ? $rel . '/' . $n : $n;
                if (!preg_match(self::NAME_DATEI, $name)) { continue; }
                $p = $ordner . '/' . $name;
                if (is_dir($p) && !is_link($p) && $tiefe < 3) { $gehen($name, $tiefe + 1); continue; }
                if (is_file($p) && !is_link($p)) { $dateien[] = ['name' => $name, 'bytes' => (int) filesize($p), 'zeit' => (int) filemtime($p)]; }
            }
        };
        $gehen('', 0);
        return ['auszuege' => $auszuege, 'dateien' => $dateien];
    }

    /** Pfad zu einem Namen — nur, was die Liste auch zeigt. */
    public static function pfad(string $art, string $name): ?string
    {
        if ($art === 'holen' && preg_match(self::NAME_AUSZUG, $name)) { $p = Sicherung::ordner() . '/' . $name; }
        elseif ($art === 'datei' && preg_match(self::NAME_DATEI, $name)) { $p = Ablage::ordner() . '/' . $name; }
        else { return null; }
        if (!is_file($p) || is_link($p)) { return null; }
        // Doppelt hält besser: Der echte Pfad muss im Ordner liegen.
        $basis = realpath($art === 'holen' ? Sicherung::ordner() : Ablage::ordner());
        $echt = realpath($p);
        return $basis !== false && $echt !== false && str_starts_with($echt, $basis . DIRECTORY_SEPARATOR) ? $echt : null;
    }

    /* -------------------------------------------------------- Verschlüsseln */

    /** Schreibt die Datei verschlüsselt in $aus (callable(string)). */
    public static function verschluesseln(string $pfad, callable $aus, ?string $pem = null): void
    {
        $pem ??= self::oeffentlich();
        $schluessel = random_bytes(32);
        $verpackt = '';
        if (!openssl_public_encrypt($schluessel, $verpackt, $pem, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new RuntimeException('Verschlüsseln nicht möglich.');
        }
        $anfang = random_bytes(8);
        $aus('VCS1' . pack('n', strlen($verpackt)) . $verpackt . $anfang);

        $h = fopen($pfad, 'rb');
        if ($h === false) { throw new RuntimeException('Datei nicht lesbar.'); }
        try {
            $nr = 0;
            $stueck = (string) fread($h, self::ABSCHNITT);
            while (true) {
                $naechstes = feof($h) ? '' : (string) fread($h, self::ABSCHNITT);
                $letzter = $naechstes === '' && feof($h);
                $tag = '';
                $geheim = openssl_encrypt($stueck, 'aes-256-gcm', $schluessel, OPENSSL_RAW_DATA,
                    $anfang . pack('N', $nr), $tag, 'VCS1' . pack('N', $nr) . ($letzter ? "\x01" : "\x00"), 16);
                if ($geheim === false) { throw new RuntimeException('Verschlüsseln fehlgeschlagen.'); }
                $aus(pack('N', strlen($geheim) | ($letzter ? 0x80000000 : 0)) . $geheim . $tag);
                if ($letzter) { break; }
                $stueck = $naechstes;
                $nr++;
            }
        } finally { fclose($h); }
    }

    /* ---------------------------------------------------------------- Probe */

    /** Die Wiederherstellungsprobe vom Rechner. @return array{ok:bool, text:string} */
    public static function probeMelden(array $d): array
    {
        $live = (int) Db::wert('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()', [], 0);
        $tabellen = (int) ($d['tabellen'] ?? 0);
        $zeilen = array_map('intval', array_slice((array) ($d['zeilen'] ?? []), 0, 20, true));
        $gut = !empty($d['ok']) && $tabellen > 0 && $tabellen >= $live - 3 && ($zeilen['customers'] ?? -1) >= 0;
        $stand = ['am' => date('Y-m-d H:i:s'), 'ok' => $gut, 'datei' => mb_substr((string) ($d['datei'] ?? ''), 0, 60),
                  'tabellen' => $tabellen, 'live' => $live, 'zeilen' => $zeilen, 'dauer_s' => (int) ($d['dauer_s'] ?? 0),
                  'fehler' => mb_substr((string) ($d['fehler'] ?? ''), 0, 300)];
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('sicherung_probe', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
            [json_encode($stand, JSON_UNESCAPED_UNICODE)]);
        require_once __DIR__ . '/Events.php';
        if (!$gut) {
            Events::melden('sicherung_probe', 'Wiederherstellungsprobe gescheitert', 'schlecht',
                'Datei ' . $stand['datei'] . ': ' . ($stand['fehler'] !== '' ? $stand['fehler'] : $tabellen . ' von ' . $live . ' Tabellen.'), '/einstellungen?b=ueberwachung#sicherung');
        }
        return ['ok' => $gut, 'text' => $gut ? 'Probe festgehalten.' : 'Probe festgehalten — sie zeigt ein Problem.'];
    }

    public static function abgeholt(string $name): void
    {
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('sicherung_abgeholt', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
            [json_encode(['am' => date('Y-m-d H:i:s'), 'name' => $name], JSON_UNESCAPED_UNICODE)]);
    }

    /** @return array{eingerichtet:bool, fingerabdruck:string, abgeholt:?array, probe:?array} */
    public static function stand(): array
    {
        $j = static fn(string $k) => json_decode((string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$k], ''), true) ?: null;
        return ['eingerichtet' => self::eingerichtet(), 'fingerabdruck' => self::fingerabdruck(),
                'abgeholt' => $j('sicherung_abgeholt'), 'probe' => $j('sicherung_probe')];
    }

    /** Täglich im Cron: Meldung, wenn der Rechner zu lange nichts abgeholt oder nicht geprobt hat. */
    public static function taeglich(): array
    {
        if (!self::eingerichtet()) { return ['uebersprungen' => 'nicht eingerichtet']; }
        $s = self::stand();
        require_once __DIR__ . '/Events.php';
        $abgeholt = isset($s['abgeholt']['am']) ? strtotime((string) $s['abgeholt']['am']) : 0;
        if ($abgeholt < time() - 2 * 86400) {
            Events::melden('sicherung_aussen', 'Sicherung seit über 2 Tagen nicht abgeholt', 'warnung',
                'Der Rechner hat die Sicherung nicht geholt. Ist er aus oder die Aufgabe „VECOM Sicherung holen“ gestoppt?', '/einstellungen?b=ueberwachung#sicherung');
        }
        $probe = isset($s['probe']['am']) ? strtotime((string) $s['probe']['am']) : 0;
        if ($probe < time() - 9 * 86400) {
            Events::melden('sicherung_probe', 'Wiederherstellungsprobe seit über 9 Tagen nicht gelaufen', 'warnung',
                'Die wöchentliche Probe auf dem Rechner hat sich nicht gemeldet.', '/einstellungen?b=ueberwachung#sicherung');
        }
        return ['abgeholt' => $s['abgeholt']['am'] ?? null, 'probe' => $s['probe']['am'] ?? null];
    }

    private static function spur(string $aktion, array $vorher, array $nachher): void
    {
        try {
            require_once __DIR__ . '/Events.php';
            Events::pruefspur($aktion, 'settings', null, $vorher, $nachher);
        } catch (Throwable $e) { }
    }
}
