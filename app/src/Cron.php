<?php
declare(strict_types=1);

require_once __DIR__ . '/Monitoring.php';
require_once __DIR__ . '/Onboarding.php';
require_once __DIR__ . '/Cockpit.php';
require_once __DIR__ . '/Sicherung.php';

/**
 * Der regelmaessige Lauf. Auf dem Webspace gibt es kein SSH und keinen
 * eigenen Dienst — der Anstoss kommt vom Cronjob im KAS, der einfach eine
 * Adresse aufruft.
 *
 * Damit diese Adresse nicht jeder aufrufen kann, traegt sie einen
 * Schluessel. Er entsteht beim ersten Blick in die Verwaltung von selbst
 * und steht dort zum Kopieren.
 */
final class Cron
{
    /** Kuerzester Abstand zwischen zwei Laeufen — schuetzt vor versehentlichem Dauerfeuer. */
    public const MINDESTABSTAND_SEKUNDEN = 60;

    public static function schluessel(): string
    {
        $vorhanden = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'cron_schluessel'", [], '');
        if ($vorhanden !== '') { return $vorhanden; }
        $neu = bin2hex(random_bytes(16));
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('cron_schluessel', ?)
                 ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$neu]);
        return $neu;
    }

    /** Die vollstaendige Adresse, die im KAS eingetragen wird. */
    public static function adresse(): string
    {
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        return $basis . '/cron.php?schluessel=' . self::schluessel();
    }

    public static function schluesselStimmt(string $eingabe): bool
    {
        $soll = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'cron_schluessel'", [], '');
        // Ohne hinterlegten Schluessel laeuft gar nichts — sonst waere die
        // Adresse offen, solange die Verwaltung noch nie aufgerufen wurde.
        if ($soll === '' || $eingabe === '') { return false; }
        return hash_equals($soll, $eingabe);
    }

    public static function zuletzt(): ?string
    {
        $w = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'cron_zuletzt'", [], '');
        return $w !== '' ? $w : null;
    }

    public static function letzteBilanz(): ?array
    {
        $w = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'cron_bilanz'", [], '');
        $d = $w !== '' ? json_decode($w, true) : null;
        return is_array($d) ? $d : null;
    }

    private static function merken(string $schluessel, string $wert): void
    {
        Db::run("INSERT INTO settings (skey, svalue) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$schluessel, $wert]);
    }

    /**
     * Ein Durchlauf. Jede Aufgabe steht fuer sich: Faellt eine aus, laufen
     * die anderen trotzdem — und der Fehler steht in der Bilanz.
     *
     * @param bool $erzwingen Mindestabstand ueberspringen (Knopf in der Verwaltung)
     */
    public static function laufen(bool $erzwingen = false): array
    {
        $zuletzt = self::zuletzt();
        if (!$erzwingen && $zuletzt !== null && (time() - strtotime($zuletzt)) < self::MINDESTABSTAND_SEKUNDEN) {
            return ['uebersprungen' => true, 'grund' => 'Der letzte Lauf ist keine Minute her.'];
        }

        $anfang = microtime(true);
        $bilanz = ['zeit' => date('c')];

        $aufgaben = [
            'websites'    => static fn() => Monitoring::alle(),
            'ssl'         => static fn() => Monitoring::sslWarnungen(),
            'erinnerungen'=> static fn() => Onboarding::erinnerungen(),
            'zahllinks'   => static fn() => self::abgelaufeneZahlungslinks(),
            // Damit die Verwaltung auf jeder Seite warnen kann, ohne bei
            // jedem Aufruf eine HTTP-Anfrage zu stellen.
            'cockpit'     => static fn() => self::cockpitPruefen(),
        ];
        // Einmal am Tag genuegt: alte Pruefungen wegraeumen.
        if (self::heuteNochNicht('cron_aufraeumen')) {
            $aufgaben['aufgeraeumt'] = static fn() => Monitoring::aufraeumen();
        }
        // Ebenfalls einmal taeglich: der Auszug der Datenbank. Er steht
        // bewusst am Ende der Liste — er dauert am laengsten, und wenn er
        // scheitert, sollen die schnellen Aufgaben trotzdem gelaufen sein.
        if (self::heuteNochNicht('cron_sicherung')) {
            $aufgaben['sicherung'] = static fn() => Sicherung::laufen();
        }

        foreach ($aufgaben as $name => $tun) {
            try { $bilanz[$name] = $tun(); }
            catch (Throwable $e) { $bilanz[$name] = ['fehler' => mb_substr($e->getMessage(), 0, 200)]; }
        }

        $bilanz['dauer_ms'] = (int) round((microtime(true) - $anfang) * 1000);
        self::merken('cron_zuletzt', date('Y-m-d H:i:s'));
        self::merken('cron_bilanz', json_encode($bilanz, JSON_UNESCAPED_UNICODE));
        return $bilanz;
    }

    /** Merkt sich, ob /cockpit/ geschuetzt ist. Meldet nur den Wechsel. */
    private static function cockpitPruefen(): string
    {
        $jetzt = Cockpit::geschuetzt();
        if ($jetzt === null) { return 'nicht erreichbar'; }
        $wert = $jetzt ? 'ja' : 'nein';
        $vorher = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'cockpit_geschuetzt'", [], '');
        self::merken('cockpit_geschuetzt', $wert);

        if ($vorher === 'ja' && $wert === 'nein') {
            Events::melden('cockpit_offen', 'Das Cockpit ist nicht mehr geschützt', 'schlecht',
                'Vorher war es geschützt, jetzt antwortet es ohne Passwort. In den Einstellungen wieder einrichten.',
                '/einstellungen');
        }
        return $wert;
    }

    private static function heuteNochNicht(string $schluessel): bool
    {
        $w = (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$schluessel], '');
        if ($w === date('Y-m-d')) { return false; }
        self::merken($schluessel, date('Y-m-d'));
        return true;
    }

    /**
     * Ein Zahlungslink von Stripe gilt nur eine begrenzte Zeit. Ist er
     * abgelaufen und nichts eingegangen, faellt die Rate zurueck auf
     * "ausstehend" — sonst stuende sie fuer immer auf "in Bearbeitung".
     */
    public static function abgelaufeneZahlungslinks(): int
    {
        return Db::run(
            "UPDATE payments SET status = 'ausstehend', link_url = NULL, link_bis = NULL
             WHERE status = 'in_bearbeitung' AND link_bis IS NOT NULL AND link_bis < NOW()"
        )->rowCount();
    }
}
