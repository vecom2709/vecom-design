<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Kas.php';
require_once __DIR__ . '/Domainpruefung.php';

/**
 * WUNSCHDOMAIN UND HOSTING — VOM FRAGEBOGEN BIS ZUM EIGENEN ACCOUNT
 * ===========================================================================
 *
 * Der Ablauf, entschieden am 07.09.2026:
 *
 * 1. ERKENNEN. Der Fragebogen sagt: keine bestehende Website, Domain wird
 *    neu gebraucht, Wunschnamen stehen drin. Die Wuensche laufen durch die
 *    Domainpruefung; die erste freie wird dem Kunden vorgeschlagen.
 *
 * 2. ZUSTIMMEN. Auf seiner Kundenseite steht ein Kasten mit Domain und
 *    Preis (9,90 EUR/Monat; bei Betreuung Plus/Premium inklusive) und einem
 *    echten Knopf. OHNE seinen Klick passiert nichts weiter — laufende
 *    Kosten aus einem blossen Vermerk waeren keine Vereinbarung, sondern
 *    eine Ueberraschung auf der Rechnung.
 *
 * 3. ANLEGEN — erst wenn der Bau fertig ist. Bei der finalen Freigabe
 *    (der Kunde hat die Vorschau abgenommen) legt die Verwaltung an:
 *    KAS-Account, Domain im Account, Postfach info@. Jeder Schritt einzeln
 *    fehlertolerant; was nicht klappt, steht woertlich in der Aufgabe fuer
 *    Uwe. Die REGISTRIERUNG der Domain bleibt sein Handgriff im
 *    Domainbestellsystem — dafuer gibt es keine Schnittstelle, und All-Inkl
 *    will genau diese Reihenfolge (erst im KAS anlegen, dann bestellen).
 *
 * 4. ZUGANGSDATEN. Die frisch erzeugten Passwoerter (KAS, FTP, Postfach)
 *    liegen verschluesselt in der Datenbank — der Schluessel dazu NICHT,
 *    er steht in app/config.local.php. Der Kunde ruft sie auf seiner Seite
 *    GENAU EINMAL ab; danach (oder nach 14 Tagen) werden sie geloescht.
 *    Verloren heisst: im KAS neu setzen. Kein Klartext in Mail oder Chat.
 *
 * 5. ABRECHNEN. Mit dem Anlegen entsteht der Monatsvertrag (Abo, Paket
 *    „hosting", 12 Monate Mindestlaufzeit) — ausser der Kunde hat
 *    Betreuung Plus oder Premium, dann ist es dort inklusive.
 */
final class Hosting
{
    /** Nach so vielen Tagen ohne Abruf werden die Zugangsdaten vernichtet. */
    public const ZUGANG_TAGE = 14;

    /** Betreuungspakete, in denen das Hosting schon drinsteckt. */
    public const INKLUSIVE_BEI = ['betreuung-plus', 'betreuung-premium'];

    /** Speicher je Kunden-Account, in Megabyte (Reseller-Pool: 200 GB auf
     *  25 Accounts — ohne Grenze koennte EIN Kunde alles belegen). */
    public const SPEICHER_MB = 10240;

    /* ==================================================================== */
    /*  1. Erkennen — nach dem Absenden des Fragebogens                     */
    /* ==================================================================== */

    /**
     * Prueft die Fragebogen-Antworten und legt bei Bedarf den Vorschlag an.
     *
     * Still: Ein Fehler hier darf das Absenden des Fragebogens nie
     * beruehren — der ist das Wichtigere.
     */
    public static function nachFragebogen(int $projektId, int $kundeId, array $antworten): void
    {
        self::still(static function () use ($projektId, $kundeId, $antworten) {
            if (!self::brauchtDomain($antworten)) { return; }
            if ($kundeId <= 0) { return; }

            /* Nur einmal: Wer den Fragebogen nachreicht oder aendert,
               bekommt keinen zweiten Vorschlag neben den ersten. */
            $schon = Db::one('SELECT id FROM hosting_auftraege
                               WHERE customer_id = ? AND status <> ?', [$kundeId, 'abgelehnt']);
            if ($schon) { return; }

            $frei = self::ersteFreie($antworten);
            if ($frei === null) {
                /* Alle Wuensche vergeben oder nicht pruefbar: Das ist keine
                   Sackgasse, sondern ein Gespraechsanlass — Meldung an Uwe
                   statt stiller Verzicht. */
                Events::melden('hosting_domain', 'Wunschdomains nicht frei oder nicht prüfbar', 'hinweis',
                    'Kunde braucht eine neue Domain, aber keiner der Wünsche ließ sich als frei bestätigen. '
                    . 'Alternativen mit ihm klären.', '/kunden/' . $kundeId);
                return;
            }

            Db::insert('hosting_auftraege', [
                'customer_id' => $kundeId,
                'project_id'  => $projektId ?: null,
                'domain'      => $frei,
                'status'      => 'vorgeschlagen',
                'preis_cents' => self::preisCents(),
            ]);
            Events::protokoll('hosting_vorschlag', 'Wunschdomain frei: ' . $frei, $kundeId, null, $projektId ?: null);
        });
    }

    /** Sagt der Fragebogen: keine Website, Domain neu, Wuensche vorhanden? */
    public static function brauchtDomain(array $antworten): bool
    {
        $alt = (string) ($antworten['altseite'] ?? '');
        if (!in_array($alt, ['nein', 'social', ''], true)) { return false; }
        if ((string) ($antworten['domain'] ?? '') !== 'neu') { return false; }
        return trim((string) ($antworten['wunsch1'] ?? '')) !== ''
            || trim((string) ($antworten['wunsch2'] ?? '')) !== ''
            || trim((string) ($antworten['wunsch3'] ?? '')) !== '';
    }

    /** Der erste Wunsch, der normalisierbar UND frei ist — oder null. */
    public static function ersteFreie(array $antworten): ?string
    {
        foreach (['wunsch1', 'wunsch2', 'wunsch3'] as $feld) {
            $roh = trim((string) ($antworten[$feld] ?? ''));
            if ($roh === '') { continue; }
            $domain = Domainpruefung::normalisieren($roh);
            if ($domain === null) { continue; }
            $erg = self::still(static fn() => Domainpruefung::pruefen($domain), null);
            if (is_array($erg) && (string) ($erg['stand'] ?? '') === 'frei') { return $domain; }
        }
        return null;
    }

    /** Der Monatspreis aus dem Paket — eine Zahl, ein Ort. */
    public static function preisCents(): int
    {
        return (int) self::still(static fn() => Db::wert(
            "SELECT monthly_cents FROM packages WHERE slug = 'hosting'", [], 990), 990);
    }

    /* ==================================================================== */
    /*  2. Zustimmen — der Knopf auf der Kundenseite                        */
    /* ==================================================================== */

    /** Der Auftrag eines Kunden, falls es einen gibt. @return array<string,mixed>|null */
    public static function fuerKunde(int $kundeId): ?array
    {
        if ($kundeId <= 0) { return null; }
        return self::still(static fn() => Db::one(
            "SELECT * FROM hosting_auftraege WHERE customer_id = ? AND status <> 'abgelehnt'
              ORDER BY id DESC LIMIT 1", [$kundeId]), null);
    }

    /**
     * Der Kunde stimmt zu — oder lehnt ab. Beides ist eine Antwort.
     *
     * Der Preis wird im Moment der Zustimmung eingefroren, wie er im
     * Kasten stand. Eine spaetere Preisaenderung am Paket aendert keinen
     * Vertrag, dem schon zugestimmt wurde.
     */
    public static function antwort(int $auftragId, int $kundeId, bool $ja): bool
    {
        $a = Db::one('SELECT * FROM hosting_auftraege WHERE id = ? AND customer_id = ?',
            [$auftragId, $kundeId]);
        if (!$a || (string) $a['status'] !== 'vorgeschlagen') { return false; }

        Db::update('hosting_auftraege', $auftragId, $ja
            ? ['status' => 'zugestimmt', 'zugestimmt_am' => date('Y-m-d H:i:s')]
            : ['status' => 'abgelehnt']);
        Events::protokoll($ja ? 'hosting_zugestimmt' : 'hosting_abgelehnt',
            ($ja ? 'Domain & Hosting zugestimmt: ' : 'Domain & Hosting abgelehnt: ') . $a['domain'],
            $kundeId, null, $a['project_id'] !== null ? (int) $a['project_id'] : null);

        /* SOLO — OHNE WEBSITE-PROJEKT — STARTET DIE ZUSTIMMUNG DEN VERTRAG
           ----------------------------------------------------------------
           Beim Website-Kunden wartet alles auf die finale Freigabe: Er hat
           laengst angezahlt, und die Domain soll erst mit der fertigen
           Seite kommen. Der Solo-Kunde hat weder das eine noch das andere.
           Also entsteht mit seinem Ja der Monatsvertrag samt erster Rate
           und Zahlungsaufforderung — ANGELEGT (Domain, Account, Postfach)
           wird aber erst, wenn diese erste Rate bezahlt ist: Eine Domain
           zu registrieren kostet Geld, und das gibt es nicht auf Verdacht.
           Den Anschluss macht Events::zahlungBestaetigen -> nachZahlung(). */
        if ($ja && $a['project_id'] === null) {
            self::still(static function () use ($a, $kundeId) {
                require_once __DIR__ . '/Abo.php';
                $schonVertrag = Db::one(
                    "SELECT id FROM abos WHERE customer_id = ? AND paket_slug = 'hosting'
                       AND status IN ('angelegt','aktiv','gekuendigt')", [$kundeId]);
                if (!$schonVertrag) {
                    $aboId = Abo::anlegen($kundeId, ['paket_slug' => 'hosting',
                        'zahlart' => 'manuell', 'betrag_cents' => (int) $a['preis_cents']]);
                    $rate = Abo::abrechnen($aboId);
                    if ($rate !== null) { Abo::anfordern($rate); }
                }
            });
            Events::melden('hosting_zugestimmt', 'Solo-Hosting zugestimmt: ' . $a['domain'], 'gut',
                'Vertrag und erste Rate stehen. Angelegt wird, sobald die Zahlung da ist.',
                '/kunden/' . $kundeId);
        }
        return true;
    }

    /**
     * Wird von Events::zahlungBestaetigen gerufen, wenn eine Abo-Rate bezahlt
     * wurde. Gehoert sie zu einem Hosting-Vertrag und wartet ein zugestimmter
     * Solo-Auftrag, wird jetzt angelegt. Still — eine Zahlung darf an einem
     * Hoster-Schluckauf nie scheitern.
     */
    public static function nachZahlung(int $aboId): void
    {
        self::still(static function () use ($aboId) {
            $abo = Db::one("SELECT * FROM abos WHERE id = ? AND paket_slug = 'hosting'", [$aboId]);
            if (!$abo) { return; }
            $a = Db::one("SELECT * FROM hosting_auftraege
                           WHERE customer_id = ? AND status = 'zugestimmt' AND project_id IS NULL",
                [(int) $abo['customer_id']]);
            if ($a) { self::anlegen((int) $a['id']); }
        });
    }

    /* ==================================================================== */
    /*  3. Anlegen — bei der finalen Freigabe                               */
    /* ==================================================================== */

    /** Wird von Events::projektStatus gerufen. Still — nie den Statuswechsel gefaehrden. */
    public static function beiStatuswechsel(int $projektId, string $neu): void
    {
        if ($neu !== 'finale_freigabe') { return; }
        self::still(static function () use ($projektId) {
            $a = Db::one("SELECT * FROM hosting_auftraege
                           WHERE project_id = ? AND status = 'zugestimmt'", [$projektId]);
            if ($a) { self::anlegen((int) $a['id']); }
        });
    }

    /**
     * Account, Domain und Postfach anlegen — jeder Schritt einzeln.
     *
     * Was klappt, klappt; was scheitert, steht woertlich in der Aufgabe.
     * Ein halber Erfolg mit ehrlicher Restliste ist mehr wert als ein
     * Alles-oder-nichts, das beim ersten Schluckauf gar nichts anlegt.
     *
     * @return array{ok:bool,text:string}
     */
    public static function anlegen(int $auftragId): array
    {
        $a = Db::one('SELECT * FROM hosting_auftraege WHERE id = ?', [$auftragId]);
        if (!$a) { return ['ok' => false, 'text' => 'Auftrag nicht gefunden.']; }
        if ((string) $a['status'] !== 'zugestimmt') {
            return ['ok' => false, 'text' => 'Nur ein zugestimmter Auftrag wird angelegt (Stand: ' . $a['status'] . ').'];
        }

        $kundeId = (int) $a['customer_id'];
        $domain  = (string) $a['domain'];
        $k = Db::one('SELECT * FROM customers WHERE id = ?', [$kundeId]);
        $wer = $k ? (string) ($k['company'] ?: $k['name']) : ('Kunde ' . $kundeId);

        $schritte = [];

        /* 1. Der Account. Ohne ihn geht nichts weiter. */
        $acc = Kas::accountAnlegen($wer . ' — ' . $domain, ['max_webspace' => self::SPEICHER_MB]);
        if (!$acc['ok']) {
            Events::melden('hosting_fehler', 'KAS-Account konnte nicht angelegt werden', 'schlecht',
                $wer . ' / ' . $domain . ' — ' . $acc['text'] . ' Im KAS von Hand anlegen.',
                '/kunden/' . $kundeId);
            return ['ok' => false, 'text' => $acc['text']];
        }
        $schritte[] = 'Account ' . ($acc['login'] !== '' ? $acc['login'] : '(Login siehe Accountliste)');

        /* 2. Domain und Postfach — im Unter-Account, mit dessen frischem
           Passwort. Nur jetzt kennen wir es. */
        $als = $acc['login'] !== '' ? ['login' => $acc['login'], 'passwort' => $acc['kas_passwort']] : null;
        $offen = [];
        if ($als !== null) {
            $d = Kas::domainAnlegen($domain, $als);
            $d['ok'] ? $schritte[] = 'Domain im KAS' : $offen[] = 'Domain im KAS anlegen (' . $d['text'] . ')';
        } else {
            $offen[] = 'Domain im KAS anlegen (Login war aus der Antwort nicht zu lesen)';
        }

        $mailPw = Kas::passwortNeu();
        if ($als !== null) {
            $m = Kas::postfachAnlegen('info', $domain, $mailPw, $als);
            $m['ok'] ? $schritte[] = 'Postfach info@' . $domain : $offen[] = 'Postfach info@ anlegen (' . $m['text'] . ')';
        }

        /* 3. Zugangsdaten verschluesselt ablegen — einmaliger Abruf. */
        $blob = self::verschluesseln([
            'kas_login' => $acc['login'], 'kas_passwort' => $acc['kas_passwort'],
            'ftp_passwort' => $acc['ftp_passwort'],
            'postfach' => 'info@' . $domain, 'postfach_passwort' => $mailPw,
            'server' => ($acc['login'] !== '' ? $acc['login'] : 'w…') . '.kasserver.com',
        ]);

        /* 4. Der Monatsvertrag — ausser er steckt in der Betreuung. */
        $inklusive = self::still(static fn() => (bool) Db::one(
            "SELECT a.id FROM abos a JOIN packages p ON p.id = a.package_id
              WHERE a.customer_id = ? AND a.status IN ('angelegt','aktiv','gekuendigt')
                AND p.slug IN ('" . implode("','", self::INKLUSIVE_BEI) . "')", [$kundeId]), false);
        if (!$inklusive) {
            self::still(static function () use ($kundeId, $a) {
                require_once __DIR__ . '/Abo.php';
                // Beim Solo-Kunden entstand der Vertrag schon mit der
                // Zustimmung — dann steht er hier bereits und bleibt, wie
                // er ist. Nur wenn keiner da ist, kommt jetzt einer.
                $schon = Db::one(
                    "SELECT id FROM abos WHERE customer_id = ? AND paket_slug = 'hosting'
                       AND status IN ('angelegt','aktiv','gekuendigt')", [$kundeId]);
                if ($schon) { return; }
                Abo::anlegen($kundeId, ['paket_slug' => 'hosting',
                    'projekt_id' => $a['project_id'] !== null ? (int) $a['project_id'] : null,
                    'zahlart' => 'manuell', 'betrag_cents' => (int) $a['preis_cents']]);
            });
        }

        Db::update('hosting_auftraege', $auftragId, [
            'status' => 'angelegt', 'angelegt_am' => date('Y-m-d H:i:s'),
            'kas_login' => $acc['login'] ?: null, 'inklusive' => $inklusive ? 1 : 0,
            'zugang_blob' => $blob,
            'zugang_bis' => date('Y-m-d H:i:s', strtotime('+' . self::ZUGANG_TAGE . ' days')),
            'notiz' => $offen ? ('Offen: ' . implode(' · ', $offen)) : null,
        ]);

        /* 5. Die Aufgabe fuer Uwe: bestellen — und was liegen blieb. */
        Events::melden('hosting_bestellen', 'Domain bestellen: ' . $domain, 'hinweis',
            'Erledigt: ' . implode(' · ', $schritte) . '. '
            . 'Jetzt im Domainbestellsystem (domain-bestellsystem.de) die Domain ' . $domain
            . ' bestellen — Nameserver ns5.kasserver.com. '
            . 'Danach im KAS den SSL-Schutz (Let\'s Encrypt, kostenlos) für die Domain aktivieren. '
            . ($offen ? 'Außerdem von Hand: ' . implode(' · ', $offen) . '. ' : '')
            . ($inklusive ? 'Abrechnung: in der Betreuung enthalten.'
                : 'Monatsvertrag ' . number_format(((int) $a['preis_cents']) / 100, 2, ',', '.') . ' € ist angelegt.'),
            '/kunden/' . $kundeId);
        Events::protokoll('hosting_angelegt', 'Hosting angelegt: ' . $domain
            . ($acc['login'] !== '' ? ' (' . $acc['login'] . ')' : ''), $kundeId, null,
            $a['project_id'] !== null ? (int) $a['project_id'] : null);

        return ['ok' => true, 'text' => implode(' · ', $schritte)];
    }

    /* ==================================================================== */
    /*  4. Zugangsdaten — einmal zeigen, dann vergessen                     */
    /* ==================================================================== */

    /**
     * Der einmalige Abruf durch den Kunden. Danach ist der Blob weg.
     *
     * @return array<string,string>|null
     */
    public static function zugangAbrufen(int $auftragId, int $kundeId): ?array
    {
        $a = Db::one('SELECT * FROM hosting_auftraege WHERE id = ? AND customer_id = ?',
            [$auftragId, $kundeId]);
        if (!$a || $a['zugang_blob'] === null) { return null; }
        if ($a['zugang_bis'] !== null && strtotime((string) $a['zugang_bis']) < time()) {
            Db::update('hosting_auftraege', $auftragId, ['zugang_blob' => null]);
            return null;
        }
        $daten = self::entschluesseln((string) $a['zugang_blob']);
        if ($daten === null) { return null; }
        Db::update('hosting_auftraege', $auftragId, ['zugang_blob' => null]);
        Events::protokoll('hosting_zugang', 'Zugangsdaten einmalig abgerufen: ' . $a['domain'],
            $kundeId, null, $a['project_id'] !== null ? (int) $a['project_id'] : null);
        return $daten;
    }

    /** Raeumt abgelaufene Blobs weg — laeuft im Cron mit. */
    public static function aufraeumen(): int
    {
        // Verglichen wird gegen die PHP-Uhr, nicht gegen NOW(): Alle
        // Zeitstempel hier entstehen mit date() in der Anwendungszeitzone —
        // die Datenbank kann in einer anderen laufen.
        return (int) self::still(static fn() => Db::run(
            'UPDATE hosting_auftraege SET zugang_blob = NULL
              WHERE zugang_blob IS NOT NULL AND zugang_bis < ?',
            [date('Y-m-d H:i:s')])->rowCount(), 0);
    }

    /* ---------- Verschluesselung ---------------------------------------- */

    /**
     * Der Schluessel steht in app/config.local.php — nicht in der
     * Datenbank, in der auch die Blobs liegen. Beim ersten Gebrauch wird
     * er erzeugt und in die Datei geschrieben.
     */
    private static function schluessel(): ?string
    {
        $k = (string) Config::get('hosting_geheim', '');
        if ($k !== '') { return $k; }

        $neu = bin2hex(random_bytes(32));
        $pfad = dirname(__DIR__) . '/config.local.php';
        if (!is_file($pfad)) { return null; }
        $alt = (array) (include $pfad);
        $alt['hosting_geheim'] = $neu;
        require_once __DIR__ . '/Einrichtung.php';
        if (!Einrichtung::konfigSchreiben($pfad, $alt)) { return null; }
        return $neu;
    }

    private static function verschluesseln(array $daten): ?string
    {
        $k = self::schluessel();
        if ($k === null) { return null; }
        $iv = random_bytes(12);
        $tag = '';
        $chiffre = openssl_encrypt(json_encode($daten, JSON_UNESCAPED_UNICODE), 'aes-256-gcm',
            hex2bin($k), OPENSSL_RAW_DATA, $iv, $tag);
        if ($chiffre === false) { return null; }
        return base64_encode($iv . $tag . $chiffre);
    }

    /** @return array<string,string>|null */
    private static function entschluesseln(string $blob): ?array
    {
        $k = (string) Config::get('hosting_geheim', '');
        if ($k === '') { return null; }
        $roh = base64_decode($blob, true);
        if ($roh === false || strlen($roh) < 29) { return null; }
        $klar = openssl_decrypt(substr($roh, 28), 'aes-256-gcm', hex2bin($k),
            OPENSSL_RAW_DATA, substr($roh, 0, 12), substr($roh, 12, 16));
        if ($klar === false) { return null; }
        $d = json_decode($klar, true);
        return is_array($d) ? $d : null;
    }

    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }
}
