<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Events.php';

/**
 * CHEF-MODUS — DER ASSISTENT NUR FUER UWE
 * ===========================================================================
 *
 * Manuela (STRATO) fuehrt draussen die Kundengespraeche. Wenn UWE anruft und
 * das geheime Codewort sagt, schaltet sie in diesen Modus um: Sie steht dann
 * auf Uwes Seite — sagt, was heute dran ist, erklaert den Stand eines Kunden,
 * legt auf Zuruf einen Kunden an, nimmt eine Notiz auf. Vorschlaege, kein
 * Selbstlauf: Alles Wirksame (anlegen, notieren) passiert erst, wenn Uwe
 * ausdruecklich „ja“ sagt — Manuela ruft die Aktion dann ein zweites Mal mit
 * bestaetigt=ja.
 *
 * DIE SCHUTZTUER IST DAS CODEWORT
 * -------------------------------
 * Die Kundenseite (telefon.php) ist am STRATO-Schluessel erkennbar — der
 * beweist, dass STRATO anruft, nicht wer spricht. Fuer die Chef-Aktionen
 * kommt das gesprochene Codewort dazu: Nur wer es sagt, oeffnet den Modus.
 * Uwe setzt es selbst in der Verwaltung; es steht nirgends im Code und nicht
 * im Repository. Ist keins gesetzt, ist der Chef-Modus AUS — dann tut hier
 * nichts etwas, egal was gerufen wird.
 *
 * Gesprochenes wird tippfehler-tolerant verglichen: klein, ohne Leerzeichen
 * und Satzzeichen. „Sonnen-Blume.“ trifft „sonnenblume“.
 */
final class Chef
{
    /** Die Aktionen, die dieser Endpunkt kennt. */
    public const AKTIONEN = ['chef_lage', 'chef_kunde', 'chef_kunde_anlegen', 'chef_notiz'];

    /* ==================================================================== */
    /*  Die Schutztuer                                                      */
    /* ==================================================================== */

    /** Das gesetzte Codewort (roh, wie Uwe es eingetippt hat). */
    public static function codewort(): string
    {
        return (string) self::still(static fn() => Db::wert(
            "SELECT svalue FROM settings WHERE skey = 'chef_codewort'", [], ''), '');
    }

    /** Uwe setzt oder loescht das Codewort in der Verwaltung. Leer = Modus aus. */
    public static function codewortSetzen(string $wort): void
    {
        $wort = trim($wort);
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('chef_codewort', ?)
                  ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$wort]);
        Events::protokoll('chef_codewort', $wort === ''
            ? 'Chef-Modus-Codewort entfernt (Modus aus)'
            : 'Chef-Modus-Codewort gesetzt');
    }

    /** Ist der Chef-Modus ueberhaupt eingerichtet? */
    public static function eingerichtet(): bool
    {
        return self::codewort() !== '';
    }

    /** Nur Buchstaben und Ziffern, klein — damit Gesprochenes vergleichbar wird. */
    private static function kern(string $s): string
    {
        $s = mb_strtolower(trim($s));
        return (string) preg_replace('/[^a-z0-9äöüß]/u', '', $s);
    }

    /**
     * Stimmt das gesagte Codewort? Zeitkonstant und nur, wenn ueberhaupt eins
     * gesetzt ist (ein leeres Codewort oeffnet nie).
     */
    public static function frei(string $gesagt): bool
    {
        $soll = self::kern(self::codewort());
        if ($soll === '') { return false; }
        $ist = self::kern($gesagt);
        if ($ist === '') { return false; }
        return hash_equals($soll, $ist);
    }

    /* ==================================================================== */
    /*  Lesen — was ist heute dran                                          */
    /* ==================================================================== */

    /**
     * Der Tagesüberblick für Uwe: was offen ist und wo es klemmt. Reine
     * Auskunft, keine Aktion. Alles still — ein fehlendes Tabellchen darf den
     * Ueberblick nicht sprengen.
     *
     * @return array<string,mixed>
     */
    public static function lage(): array
    {
        $neueAnfragen = (int) self::still(static fn() => Db::wert(
            "SELECT COUNT(*) FROM anfragen WHERE status = 'neu'", [], 0), 0);
        $offeneMeldungen = (int) self::still(static fn() => Db::wert(
            'SELECT COUNT(*) FROM notifications WHERE read_at IS NULL', [], 0), 0);
        $offeneFragebogen = (int) self::still(static fn() => Db::wert(
            "SELECT COUNT(*) FROM questionnaires WHERE status = 'offen'", [], 0), 0);
        $faelligeRaten = (int) self::still(static fn() => Db::wert(
            "SELECT COUNT(*) FROM payments
              WHERE status <> 'bezahlt' AND faellig_am IS NOT NULL AND faellig_am <= CURDATE()", [], 0), 0);
        $mailFehler = (int) self::still(static fn() => Db::wert(
            "SELECT COUNT(*) FROM mails WHERE status = 'fehler'
               AND created_at > DATE_SUB(NOW(), INTERVAL 3 DAY)", [], 0), 0);
        $neueKunden = (int) self::still(static fn() => Db::wert(
            'SELECT COUNT(*) FROM customers WHERE created_at >= CURDATE()', [], 0), 0);

        $teile = [];
        $teile[] = $neueAnfragen === 0 ? 'keine neuen Anfragen'
            : ($neueAnfragen . ' neue ' . ($neueAnfragen === 1 ? 'Anfrage' : 'Anfragen'));
        if ($offeneFragebogen > 0) { $teile[] = $offeneFragebogen . ' offene Fragebögen'; }
        if ($faelligeRaten > 0)    { $teile[] = $faelligeRaten . ' fällige Raten'; }
        if ($mailFehler > 0)       { $teile[] = $mailFehler . ' fehlgeschlagene E-Mails'; }
        if ($offeneMeldungen > 0)  { $teile[] = $offeneMeldungen . ' offene Meldungen'; }

        $satz = 'Stand jetzt: ' . self::aufzaehlen($teile) . '.';
        if ($mailFehler > 0) {
            $satz .= ' Bei den E-Mails klemmt etwas — schau in den Postausgang.';
        }

        return [
            'ok'               => true,
            'neue_anfragen'    => $neueAnfragen,
            'offene_meldungen' => $offeneMeldungen,
            'offene_fragebogen'=> $offeneFragebogen,
            'faellige_raten'   => $faelligeRaten,
            'mail_fehler'      => $mailFehler,
            'neue_kunden_heute'=> $neueKunden,
            'hinweis'          => $satz,
        ];
    }

    /* ==================================================================== */
    /*  Lesen — der Stand eines Kunden                                      */
    /* ==================================================================== */

    /**
     * Der Stand eines Kunden für Uwe (mehr als die Kundenseite zeigt, weil er
     * es ist): Projektlage, Verträge, offene Zahlungen, letzte Aktivität.
     *
     * @return array<string,mixed>
     */
    public static function kunde(array $d): array
    {
        $suche = trim((string) ($d['name'] ?? $d['suche'] ?? $d['email'] ?? ''));
        if ($suche === '') {
            return ['ok' => false, 'hinweis' => 'Sag mir Namen oder E-Mail des Kunden.'];
        }

        $k = self::kundeSuchen($suche);
        if ($k === null) {
            return ['ok' => false, 'treffer' => 0,
                    'hinweis' => 'Zu „' . $suche . '“ finde ich keinen Kunden. Anders geschrieben versuchen?'];
        }
        if (isset($k['mehrere'])) {
            return ['ok' => false, 'treffer' => (int) $k['mehrere'],
                    'hinweis' => 'Dazu passen mehrere Kunden. Nenn mir die E-Mail oder den Ort.'];
        }

        $kid = (int) $k['id'];
        require_once __DIR__ . '/Status.php';
        require_once __DIR__ . '/Fmt.php';

        $projekt = self::still(static fn() => Db::one(
            'SELECT status FROM projects WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$kid]), null);
        $projLage = $projekt ? (Status::PROJEKT[(string) $projekt['status']] ?? (string) $projekt['status']) : null;

        $abo = self::still(static fn() => Db::one(
            "SELECT paket_name FROM abos WHERE customer_id = ?
               AND status IN ('angelegt','aktiv','gekuendigt') ORDER BY id DESC LIMIT 1", [$kid]), null);

        $offen = (int) self::still(static fn() => Db::wert(
            "SELECT COUNT(*) FROM payments p
               LEFT JOIN orders o ON o.id = p.order_id
              WHERE (o.customer_id = ? OR p.abo_id IN (SELECT id FROM abos WHERE customer_id = ?))
                AND p.status <> 'bezahlt'", [$kid, $kid], 0), 0);

        $teile = ['Kunde ' . (string) $k['name']];
        if ((string) ($k['company'] ?? '') !== '') { $teile[0] .= ' (' . $k['company'] . ')'; }
        if ($projLage) { $teile[] = 'Projekt: ' . $projLage; }
        if ($abo)      { $teile[] = 'Vertrag: ' . $abo['paket_name']; }
        $teile[] = $offen > 0 ? ($offen . ' offene Zahlung' . ($offen === 1 ? '' : 'en')) : 'keine offene Zahlung';

        return [
            'ok'          => true,
            'kunde_id'    => $kid,
            'name'        => (string) $k['name'],
            'email'       => (string) $k['email'],
            'projekt'     => $projLage,
            'vertrag'     => $abo['paket_name'] ?? null,
            'offene_zahlungen' => $offen,
            'hinweis'     => self::aufzaehlen($teile) . '.',
        ];
    }

    /* ==================================================================== */
    /*  Handeln — mit Bestätigung                                           */
    /* ==================================================================== */

    /**
     * Einen Kunden anlegen — auf Zuruf, aber nur nach ausdrücklichem „ja“.
     * Erster Aufruf (ohne bestaetigt): Manuela liest den Vorschlag vor.
     * Zweiter Aufruf (bestaetigt=ja): angelegt. Gibt es die E-Mail schon,
     * wird nichts doppelt angelegt — der bestehende Kunde wird genannt.
     *
     * @return array<string,mixed>
     */
    public static function kundeAnlegen(array $d): array
    {
        $name  = trim((string) ($d['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
        $firma = trim((string) ($d['firma'] ?? $d['company'] ?? ''));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'hinweis' => 'Dafür brauche ich Name und eine gültige E-Mail-Adresse.'];
        }

        $schon = self::still(static fn() => Db::one('SELECT id, name FROM customers WHERE email = ?', [$email]), null);
        if ($schon) {
            return ['ok' => true, 'schon' => true, 'kunde_id' => (int) $schon['id'],
                    'hinweis' => (string) $schon['name'] . ' steht schon in der Kartei — ich habe nichts doppelt angelegt.'];
        }

        if (!self::jaGesagt($d)) {
            return ['ok' => true, 'bestaetigung_noetig' => true,
                    'hinweis' => 'Soll ich ' . $name . ($firma !== '' ? ' von ' . $firma : '')
                               . ' mit der E-Mail ' . $email . ' anlegen? Sag ja, dann mache ich es.'];
        }

        $kid = (int) Events::kundeFinden([
            'name' => mb_substr($name, 0, 120), 'email' => $email,
            'company' => $firma !== '' ? mb_substr($firma, 0, 120) : null,
            'notes' => 'Von Uwe telefonisch über den Chef-Modus angelegt.',
        ]);
        Events::melden('chef_kunde', 'Kunde im Chef-Modus angelegt', 'gut',
            $name . ($firma !== '' ? ' (' . $firma . ')' : '') . ' — ' . $email, '/kunden/' . $kid);

        return ['ok' => true, 'angelegt' => true, 'kunde_id' => $kid,
                'hinweis' => $name . ' ist angelegt. Du findest ihn in der Verwaltung unter Kunden.'];
    }

    /**
     * Eine Notiz / ein To-do für Uwe in der Verwaltung hinterlassen. Deckt
     * „schreib mir das auf“ und „erinnere mich“ ab — sie geht NICHT an einen
     * Kunden raus, sondern landet in Uwes Meldungen. Auch das erst nach „ja“,
     * damit kein Halbsatz zur Aufgabe wird.
     *
     * @return array<string,mixed>
     */
    public static function notiz(array $d): array
    {
        $text = trim((string) ($d['text'] ?? $d['notiz'] ?? ''));
        if ($text === '') {
            return ['ok' => false, 'hinweis' => 'Was soll ich notieren?'];
        }
        $text = mb_substr($text, 0, 800);

        if (!self::jaGesagt($d)) {
            return ['ok' => true, 'bestaetigung_noetig' => true,
                    'hinweis' => 'Ich notiere: „' . $text . '“. Richtig so? Sag ja, dann lege ich es ab.'];
        }

        Events::melden('chef_notiz', 'Notiz aus dem Chef-Modus', 'hinweis', $text, '/heute');
        return ['ok' => true, 'notiert' => true,
                'hinweis' => 'Notiert. Es steht in deinen Meldungen.'];
    }

    /* ==================================================================== */
    /*  Kleinkram                                                           */
    /* ==================================================================== */

    /** Erkennt ein „ja“ tippfehler-tolerant (ja, jawohl, ok, mach, bestätigt …). */
    private static function jaGesagt(array $d): bool
    {
        $roh = self::kern((string) ($d['bestaetigt'] ?? $d['ja'] ?? ''));
        if ($roh === '') { return false; }
        foreach (['ja', 'jawohl', 'jaklar', 'ok', 'okay', 'mach', 'machen', 'bestaetigt',
                  'bestätigt', 'passt', 'stimmt', 'true', '1', 'si', 'yes'] as $w) {
            if ($roh === self::kern($w) || str_starts_with($roh, self::kern($w))) { return true; }
        }
        return false;
    }

    /**
     * Sucht einen Kunden nach Name oder E-Mail. Genau ein Treffer -> die Akte;
     * mehrere -> Hinweis; keiner -> null.
     *
     * @return array<string,mixed>|null
     */
    private static function kundeSuchen(string $suche): ?array
    {
        if (filter_var($suche, FILTER_VALIDATE_EMAIL)) {
            $k = self::still(static fn() => Db::one(
                'SELECT * FROM customers WHERE email = ?', [mb_strtolower($suche)]), null);
            return $k ?: null;
        }
        $wie = '%' . $suche . '%';
        $treffer = (array) self::still(static fn() => Db::all(
            'SELECT * FROM customers WHERE name LIKE ? OR company LIKE ? ORDER BY id DESC LIMIT 5',
            [$wie, $wie]), []);
        if (!$treffer) { return null; }
        if (count($treffer) > 1) { return ['mehrere' => count($treffer)]; }
        return $treffer[0];
    }

    /** „a, b und c“ — für gesprochene Sätze. */
    private static function aufzaehlen(array $teile): string
    {
        $teile = array_values(array_filter($teile, static fn($t) => trim((string) $t) !== ''));
        if (!$teile) { return 'nichts Besonderes'; }
        if (count($teile) === 1) { return (string) $teile[0]; }
        $letzt = array_pop($teile);
        return implode(', ', $teile) . ' und ' . $letzt;
    }

    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }
}
