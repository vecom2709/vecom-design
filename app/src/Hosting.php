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
 *    KAS-Account, Domain im Account, Postfach kontakt@. Jeder Schritt einzeln
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
    /** Das erste Postfach eines Hosting-Kunden. */
    public const POSTFACH = 'kontakt';

    /** Fassung des Kastentextes -- wird mit jeder Zustimmung gespeichert. */
    public const FASSUNG = '2026-09-25';

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
            if ($kundeId <= 0) { return; }

            /* SEIT 25.09.2026: DER KUNDE ENTSCHEIDET
               Beantwortet der Fragebogen "Wo soll die Website laufen?",
               gilt nur das. Hosting entsteht NIE, weil eine Website gebaut
               wird -- nur, wenn "bei Vecom Design" gewaehlt ist. Aeltere
               Frageboegen ohne diese Frage behalten ihren alten Weg. */
            if (array_key_exists('hosting_wahl', $antworten)) {
                self::nachWahl($projektId, $kundeId, $antworten);
                return;
            }
            if (!self::brauchtDomain($antworten)) { return; }

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

    /**
     * Der Weg mit den drei Entscheidungen (Domain, Hosting, E-Mail).
     *
     * Hosting nur bei "vecom". Die Domain folgt dem, was der Kunde gewaehlt
     * hat -- ein Umzug zu Vecom nur bei "uebertragen", sonst bleibt sie, wo
     * sie ist. Ein Postfach nur bei "vecom". Nichts davon ist schon bestellt:
     * Das geschieht erst mit dem Knopf auf der Kundenseite, und dort steht
     * genau diese Zusammenstellung.
     */
    private static function nachWahl(int $projektId, int $kundeId, array $antworten): void
    {
        if ((string) ($antworten['hosting_wahl'] ?? '') !== 'vecom') { return; }

        $schon = Db::one('SELECT id FROM hosting_auftraege WHERE customer_id = ? AND status <> ?',
            [$kundeId, 'abgelehnt']);
        if ($schon) { return; }

        $stand = (string) ($antworten['domain'] ?? '');
        $domain = null;
        $aktion = 'offen';
        if ($stand === 'neu') {
            $domain = self::ersteFreie($antworten);
            $aktion = 'neu';
        } elseif (in_array($stand, ['uns', 'fremd'], true)) {
            $domain = Domainpruefung::normalisieren((string) ($antworten['domain_name'] ?? ''));
            $aktion = ['behalten' => 'behalten', 'uebertragen' => 'transfer'][(string) ($antworten['domain_wahl'] ?? '')] ?? 'offen';
        }
        $mailWahl = (string) ($antworten['mail_wahl'] ?? '');
        $mail = in_array($mailWahl, ['vecom', 'bisher', 'keine'], true) ? $mailWahl : 'offen';

        if ($domain === null) {
            /* Hosting gewuenscht, aber keine Domain, mit der es laufen
               koennte: kein Sackgassen-Vorschlag, sondern ein Anruf. */
            Events::melden('hosting_domain', 'Hosting gewünscht, Domain unklar', 'hinweis',
                'Der Kunde möchte Hosting bei Vecom Design, aber aus dem Fragebogen ergibt sich keine '
                . 'Domain (keine freie Wunschdomain oder kein Name angegeben). Mit ihm klären.',
                '/kunden/' . $kundeId);
            return;
        }

        Db::insert('hosting_auftraege', [
            'customer_id' => $kundeId, 'project_id' => $projektId ?: null,
            'domain' => $domain, 'domain_aktion' => $aktion, 'mail' => $mail,
            'status' => 'vorgeschlagen', 'preis_cents' => self::preisCents(),
        ]);
        Events::protokoll('hosting_vorschlag', 'Hosting bei Vecom gewählt: ' . $domain
            . ' (Domain ' . $aktion . ', E-Mail ' . $mail . ')', $kundeId, null, $projektId ?: null);
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
     * Der Text im Kasten auf der Kundenseite -- und, bei "Ja", der Wortlaut
     * der Zustimmung. Eine Quelle fuer beides: Was gespeichert wird, ist
     * genau das, was dastand.
     */
    public static function angebotText(array $a, string $sprache): string
    {
        require_once __DIR__ . '/Texte.php';
        $t = static fn(string $k): string => (string) (Texte::SEITE[$k][$sprache] ?? Texte::SEITE[$k]['it'] ?? '');
        $werte = [
            '{domain}' => (string) $a['domain'],
            '{preis}'  => Fmt::geld((int) $a['preis_cents'], 'EUR'),
            '{monate}' => (string) self::mindestMonate(),
        ];
        // Der Solo-Kauf (ohne Website) hat seinen eigenen, laengst
        // abgenommenen Text: neue Domain samt Postfach.
        if ($a['project_id'] === null) {
            return strtr($t('hostingAngebotSolo'), $werte);
        }
        $aktion = in_array((string) ($a['domain_aktion'] ?? 'neu'), ['neu', 'transfer', 'behalten', 'offen'], true)
            ? (string) $a['domain_aktion'] : 'offen';
        $mail = (string) ($a['mail'] ?? 'vecom') === 'vecom' ? $t('hostingUmfangMail') : '';
        $saetze = [
            $t('hostingWahlEinleitung'),
            $t('hostingDomain_' . $aktion),
            strtr($t('hostingUmfang'), ['{mail}' => $mail]),
            $t('hostingPreisSatz'),
            $t('hostingWann'),
        ];
        return strtr(implode(' ', array_filter($saetze, static fn(string $x): bool => $x !== '')), $werte);
    }

    /** Die Mindestlaufzeit des Hosting-Produkts (Migration 052). */
    private static function mindestMonate(): int
    {
        require_once __DIR__ . '/Abo.php';
        $p = (array) self::still(static fn() => Db::one("SELECT * FROM packages WHERE slug = 'hosting'"), []);
        return Abo::mindestMonate($p);
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

        /* Der Wortlaut, dem zugestimmt wurde -- Kastentext und Knopf, in der
           Sprache des Kunden. Ein Domain-Umzug bekommt eine eigene Zeile:
           Er ist ein eigener Auftrag und darf nie nur "mitgemeint" sein. */
        if ($ja) {
            self::still(static function () use ($a, $auftragId, $kundeId) {
                require_once __DIR__ . '/Zustimmung.php';
                require_once __DIR__ . '/Texte.php';
                $sprache = strtolower((string) Db::wert('SELECT sprache FROM customers WHERE id = ?', [$kundeId], 'it'));
                if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }
                $knopf = strtr((string) (Texte::SEITE['hostingJa'][$sprache] ?? ''),
                    ['{preis}' => Fmt::geld((int) $a['preis_cents'], 'EUR')]);
                $text = self::angebotText($a, $sprache) . "\n\n[" . $knopf . ']';
                $projekt = $a['project_id'] !== null ? (int) $a['project_id'] : null;
                Zustimmung::festhalten('hosting', $kundeId, $text, $sprache, self::FASSUNG, $projekt, $auftragId);
                if ((string) ($a['domain_aktion'] ?? '') === 'transfer') {
                    Zustimmung::festhalten('domain_transfer', $kundeId, $text, $sprache, self::FASSUNG, $projekt, $auftragId);
                }
            });
        }
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
            self::still(static fn() => self::vertragUndErsteRate($a, $kundeId));
            Events::melden('hosting_zugestimmt', 'Solo-Hosting zugestimmt: ' . $a['domain'], 'gut',
                'Vertrag und erste Rate stehen. Angelegt wird, sobald die Zahlung da ist.',
                '/kunden/' . $kundeId);
        }
        return true;
    }

    /** Steckt das Hosting in einer laufenden Betreuung (Plus/Premium)? */
    public static function inklusive(int $kundeId): bool
    {
        return (bool) self::still(static fn() => (bool) Db::one(
            "SELECT a.id FROM abos a JOIN packages p ON p.id = a.package_id
              WHERE a.customer_id = ? AND a.status IN ('angelegt','aktiv','gekuendigt')
                AND p.slug IN ('" . implode("','", self::INKLUSIVE_BEI) . "')", [$kundeId]), false);
    }

    /**
     * Monatsvertrag und erste Rate samt Zahlungsaufforderung -- einmal je
     * Kunde. Angelegt wird erst, wenn diese Rate bezahlt ist (nachZahlung).
     * Der Zahlungslink fuehrt danach auf die persoenliche Kundenseite.
     */
    private static function vertragUndErsteRate(array $a, int $kundeId): void
    {
        require_once __DIR__ . '/Abo.php';
        require_once __DIR__ . '/Kundenzugang.php';
        $schonVertrag = Db::one(
            "SELECT id FROM abos WHERE customer_id = ? AND paket_slug = 'hosting'
               AND status IN ('angelegt','aktiv','gekuendigt')", [$kundeId]);
        if ($schonVertrag) { return; }
        $aboId = Abo::anlegen($kundeId, ['paket_slug' => 'hosting',
            'projekt_id' => $a['project_id'] !== null ? (int) $a['project_id'] : null,
            'zahlart' => 'manuell', 'betrag_cents' => (int) $a['preis_cents']]);
        $rate = Abo::abrechnen($aboId);
        if ($rate !== null) {
            $ziel = self::still(static fn() => Kundenzugang::linkFuer($kundeId), '');
            Abo::anfordern($rate, $ziel !== '' ? $ziel : null);
        }
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
            /* Solo-Auftrag sofort; ein Auftrag zu einer Website erst, wenn
               die Website freigegeben ist (sonst stuende das Hosting vor der
               Seite da, fuer die es gedacht ist). */
            $a = Db::one("SELECT h.* FROM hosting_auftraege h
                            LEFT JOIN projects p ON p.id = h.project_id
                           WHERE h.customer_id = ? AND h.status = 'zugestimmt'
                             AND (h.project_id IS NULL
                                  OR p.status IN ('finale_freigabe','veroeffentlichung','online','abgeschlossen'))
                           ORDER BY h.id LIMIT 1",
                [(int) $abo['customer_id']]);
            if ($a) { self::anlegen((int) $a['id']); }
        });
    }

    /**
     * Der DIREKTKAUF von der oeffentlichen Seite (hosting.php).
     *
     * Kein Angebot, kein Handgriff von Uwe: Der Kunde hat die Domain als
     * frei bestaetigt gesehen und verbindlich gekauft. Diese Methode prueft
     * die Domain ein letztes Mal (gegen manipulierte Formulare), legt Kunde
     * und Auftrag an und schliesst mit derselben Zustimmung ab wie der
     * Ja-Knopf auf der Kundenseite — Vertrag, erste Rate, Zahlungsaufforderung.
     *
     * NICHT still: Der oeffentliche Aufrufer WILL wissen, ob es geklappt hat,
     * und zeigt dem Kunden je nach Grund etwas anderes.
     *
     * @return array{ok:bool, grund?:string, domain?:string, kunde_id?:int, auftrag_id?:int}
     */
    public static function direktKauf(string $name, string $email, string $domainRoh, string $sprache): array
    {
        $domain = Domainpruefung::normalisieren($domainRoh);
        if ($domain === null) { return ['ok' => false, 'grund' => 'ungueltig']; }

        $erg   = self::still(static fn() => Domainpruefung::pruefen($domain), null);
        $stand = is_array($erg) ? (string) ($erg['stand'] ?? '') : '';
        if ($stand !== 'frei') {
            return ['ok' => false, 'domain' => $domain,
                    'grund' => $stand === 'vergeben' ? 'vergeben' : 'unklar'];
        }

        $name  = trim($name);
        $email = mb_strtolower(trim($email));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'grund' => 'daten', 'domain' => $domain];
        }

        $kundeId = Events::kundeFinden([
            'name'  => mb_substr($name, 0, 120),
            'email' => $email,
            'notes' => 'Domain & Hosting direkt auf der Website gekauft.',
        ]);
        require_once __DIR__ . '/Onboarding.php';
        self::still(static fn() => Onboarding::spracheMerken($kundeId, $sprache, true));

        // Kein zweiter Auftrag neben einem bestehenden.
        $schon = self::fuerKunde($kundeId);
        if ($schon !== null) {
            return ['ok' => true, 'domain' => $domain, 'kunde_id' => $kundeId,
                    'auftrag_id' => (int) $schon['id'], 'grund' => 'schon',
                    'zahl_url' => self::offeneRateLink($kundeId)];
        }

        $auftragId = (int) Db::insert('hosting_auftraege', [
            'customer_id' => $kundeId, 'project_id' => null, 'domain' => $domain,
            'status' => 'vorgeschlagen', 'preis_cents' => self::preisCents(),
        ]);
        // Derselbe verbindliche Abschluss wie der Ja-Knopf: zugestimmt,
        // Vertrag, erste Rate, Zahlungsaufforderung, Vertragsblatt.
        self::antwort($auftragId, $kundeId, true);
        Events::protokoll('hosting_kauf', 'Domain & Hosting direkt gekauft: ' . $domain,
            $kundeId, null, null);
        // Die Bezahlseite der ersten Rate — dorthin schickt hosting.php den
        // Kunden gleich weiter. Steht kein Stripe-Link (nur Ueberweisung),
        // bleibt sie leer und die Seite zeigt die Danke-/Ueberweisungsansicht.
        return ['ok' => true, 'domain' => $domain, 'kunde_id' => $kundeId,
                'auftrag_id' => $auftragId, 'zahl_url' => self::offeneRateLink($kundeId)];
    }

    /**
     * Der Stripe-Zahlungslink der ersten offenen Hosting-Rate eines Kunden,
     * frisch aus der Datenbank (dort hat Abo::anfordern ihn eben abgelegt).
     * Leer, wenn keiner existiert — etwa weil nur die Ueberweisung offensteht.
     */
    private static function offeneRateLink(int $kundeId): string
    {
        return (string) self::still(static fn() => Db::wert(
            "SELECT z.link_url FROM payments z
               JOIN abos a ON a.id = z.abo_id
              WHERE a.customer_id = ? AND a.paket_slug = 'hosting'
                AND z.status <> 'bezahlt' AND z.link_url IS NOT NULL AND z.link_url <> ''
              ORDER BY z.id ASC LIMIT 1", [$kundeId], ''), '');
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
            if (!$a) { return; }
            $kundeId = (int) $a['customer_id'];

            /* ERST DIE ZAHLUNG, DANN DAS ANLEGEN (25.09.2026)
               Bis heute wurde hier sofort angelegt und der Monatsvertrag erst
               dabei geschlossen -- Account und Domain entstanden, bevor ein
               Cent fuer das Hosting da war. Jetzt wie beim Solo-Kauf: Vertrag
               und erste Rate, angelegt wird in nachZahlung(). Steckt das
               Hosting in der Betreuung (Plus/Premium), gibt es keine eigene
               Rate -- dann gleich. */
            if (self::inklusive($kundeId)) { self::anlegen((int) $a['id']); return; }
            self::vertragUndErsteRate($a, $kundeId);
        });
    }

    /* ==================================================================== */
    /*  Einrichten in Schritten (Phase 3, 25.09.2026)                       */
    /* ==================================================================== */

    /** Die Schritte in ihrer Reihenfolge -- und wie sie in der Verwaltung heissen. */
    public const SCHRITTE = [
        'account'  => 'KAS-Account',
        'domain'   => 'Domain im KAS',
        'postfach' => 'Postfach',
        'vertrag'  => 'Monatsvertrag',
        'kunde'    => 'Mail an den Kunden',
        'aufgabe'  => 'Aufgabe für Uwe',
    ];

    /** So oft versucht der Cron einen gescheiterten Schritt, dann ist er Handarbeit. */
    public const VERSUCHE = 3;

    /** Erst nach dieser Pause versucht es der Cron noch einmal (die KAS-API bremst gern). */
    public const PAUSE_MINUTEN = 20;

    /**
     * Einrichten anstossen.
     *
     * WARUM ERST "BEANSPRUCHEN"
     *
     * Bis zum 25.09.2026 stand der Auftrag nach einem Abbruch mitten im
     * Anlegen weiter auf "zugestimmt" -- und die naechste bezahlte Rate legte
     * einen zweiten KAS-Account an. Jetzt wechselt er als Erstes, in einem
     * Befehl, auf "in_arbeit". Wer den Wechsel nicht selbst vollzieht, legt
     * nichts an: kein zweiter Account, auch wenn Webhook, Abgleich und Knopf
     * gleichzeitig kommen.
     *
     * @param object|null $kas austauschbar fuer die Pruefkette (accountAnlegen, domainAnlegen, postfachAnlegen, passwortNeu)
     * @return array{ok:bool,text:string}
     */
    public static function anlegen(int $auftragId, ?object $kas = null): array
    {
        $a = Db::one('SELECT * FROM hosting_auftraege WHERE id = ?', [$auftragId]);
        if (!$a) { return ['ok' => false, 'text' => 'Auftrag nicht gefunden.']; }
        if ((string) $a['status'] === 'in_arbeit') { return self::weiter($auftragId, $kas); }
        if ((string) $a['status'] !== 'zugestimmt') {
            return ['ok' => false, 'text' => 'Nur ein zugestimmter Auftrag wird angelegt (Stand: ' . $a['status'] . ').'];
        }
        $meins = Db::run("UPDATE hosting_auftraege SET status = 'in_arbeit' WHERE id = ? AND status = 'zugestimmt'",
                         [$auftragId])->rowCount();
        if ($meins === 0) { return ['ok' => false, 'text' => 'Wird schon eingerichtet.']; }

        $mitPostfach = (string) ($a['mail'] ?? 'vecom') === 'vecom';
        foreach (array_keys(self::SCHRITTE) as $s) {
            Db::run('INSERT IGNORE INTO hosting_schritte (auftrag_id, schritt, status, text) VALUES (?, ?, ?, ?)', [
                $auftragId, $s,
                ($s === 'postfach' && !$mitPostfach) ? 'entfaellt' : 'offen',
                /* Ein Postfach nur bei E-Mail ueber Vecom: Bleibt sie bei
                   Microsoft 365 oder beim alten Anbieter, koennte ein Postfach
                   hier Mails wegfangen, sobald jemand die MX-Eintraege umstellt. */
                ($s === 'postfach' && !$mitPostfach) ? 'Der Kunde behält seine E-Mail, wo sie ist.' : null]);
        }
        return self::weiter($auftragId, $kas);
    }

    /** Die Schritte eines Auftrags, nach Namen. */
    public static function schritte(int $auftragId): array
    {
        $aus = [];
        foreach (Db::all('SELECT * FROM hosting_schritte WHERE auftrag_id = ?', [$auftragId]) as $z) {
            $aus[(string) $z['schritt']] = $z;
        }
        return $aus;
    }

    private static function schritt(int $auftragId, string $s, string $status, ?string $text = null, bool $versuch = false): void
    {
        Db::run('UPDATE hosting_schritte SET status = ?, text = ?' . ($versuch ? ', versuche = versuche + 1' : '')
              . ' WHERE auftrag_id = ? AND schritt = ?',
            [$status, $text !== null ? mb_substr($text, 0, 500) : null, $auftragId, $s]);
    }

    /** Ist ein Schritt durch -- so oder so? */
    private static function erledigt(array $z): bool
    {
        return in_array((string) $z['status'], ['fertig', 'hand', 'entfaellt'], true);
    }

    /** Darf der Schritt (noch einmal) laufen? */
    private static function dran(array $z): bool
    {
        return (string) $z['status'] === 'offen'
            || ((string) $z['status'] === 'fehler' && (int) $z['versuche'] < self::VERSUCHE);
    }

    private static function kas(?object $k): object
    {
        return $k ?? new class {
            public function accountAnlegen(string $kommentar, array $grenzen = []): array { return Kas::accountAnlegen($kommentar, $grenzen); }
            public function domainAnlegen(string $d, ?array $als = null): array { return Kas::domainAnlegen($d, $als); }
            public function postfachAnlegen(string $l, string $d, string $pw, ?array $als = null): array { return Kas::postfachAnlegen($l, $d, $pw, $als); }
            public function passwortNeu(): string { return Kas::passwortNeu(); }
        };
    }

    /**
     * Die Schritte abarbeiten, die dran sind. Laeuft beim ersten Mal, im
     * Cron (fortsetzen) und auf Uwes Knopf -- jedes Mal nur, was noch fehlt.
     *
     * @return array{ok:bool,text:string}
     */
    public static function weiter(int $auftragId, ?object $kas = null): array
    {
        $kas = self::kas($kas);
        $a = Db::one('SELECT * FROM hosting_auftraege WHERE id = ?', [$auftragId]);
        if (!$a || (string) $a['status'] !== 'in_arbeit') { return ['ok' => false, 'text' => 'Nichts in Arbeit.']; }
        $kundeId = (int) $a['customer_id'];
        $domain  = (string) $a['domain'];
        $k = Db::one('SELECT * FROM customers WHERE id = ?', [$kundeId]);
        $wer = $k ? (string) ($k['company'] ?: $k['name']) : ('Kunde ' . $kundeId);
        $st = self::schritte($auftragId);
        /* Beansprucht, aber die Schritte stehen noch nicht: Ein anderer Aufruf
           ist genau jetzt dabei. Nicht dazwischenfunken. */
        if (count($st) < count(self::SCHRITTE)) { return ['ok' => false, 'text' => 'Wird gerade eingerichtet.']; }

        /* Domain und Postfach, die mitten im Aufruf abbrachen, duerfen einfach
           noch einmal: Ein zweites Anlegen meldet "gibt es schon", und das
           zaehlt als Erfolg (siehe unten). Beim Account gilt das nicht. */
        foreach (['domain', 'postfach'] as $s) {
            if ((string) ($st[$s]['status'] ?? '') === 'laeuft') {
                self::schritt($auftragId, $s, 'fehler', 'Abgebrochen mitten im Aufruf — wird wiederholt.');
            }
        }
        $st = self::schritte($auftragId);

        /* 1. DER ACCOUNT
           "laeuft" beim Hereinkommen heisst: Der letzte Lauf brach mitten im
           Aufruf ab. Ob der Account entstand, weiss nur der KAS -- ein
           zweiter Versuch koennte einen zweiten anlegen. Also Uwe. */
        if ((string) ($st['account']['status'] ?? '') === 'laeuft') {
            self::schritt($auftragId, 'account', 'hand',
                'Abgebrochen mitten im Anlegen. In der KAS-Accountliste nach „' . $wer . ' — ' . $domain
                . '“ sehen: Gibt es ihn, dort weitermachen; sonst „Offene Schritte wiederholen“.');
            self::melden($a, $wer, 'KAS-Account: unklar, ob er entstand');
            return ['ok' => false, 'text' => 'Account unklar — von Hand prüfen.'];
        }
        if (self::dran($st['account'])) {
            self::schritt($auftragId, 'account', 'laeuft', null, true);
            $acc = $kas->accountAnlegen($wer . ' — ' . $domain, ['max_webspace' => self::SPEICHER_MB]);
            if (!$acc['ok']) {
                $versuche = (int) $st['account']['versuche'] + 1;
                self::schritt($auftragId, 'account', $versuche >= self::VERSUCHE ? 'hand' : 'fehler', (string) $acc['text']);
                /* Schon beim ersten Mal melden: Ohne Account passiert gar nichts,
                   und oft ist es ein Zugang, den nur Uwe richten kann. */
                self::melden($a, $wer, 'KAS-Account konnte nicht angelegt werden: ' . $acc['text']
                    . ($versuche >= self::VERSUCHE ? ' Jetzt von Hand.' : ' Wird in ' . self::PAUSE_MINUTEN . ' Minuten noch einmal versucht.'));
                return ['ok' => false, 'text' => (string) $acc['text']];
            }
            /* Die Zugangsdaten sofort verschluesselt ablegen -- sie sind das
               Einzige, womit Domain und Postfach spaeter noch nachgeholt
               werden koennen (gespeichert wird das Passwort nirgends sonst). */
            $mitPostfach = (string) ($st['postfach']['status'] ?? '') !== 'entfaellt';
            $blob = self::verschluesseln([
                'kas_login' => $acc['login'], 'kas_passwort' => $acc['kas_passwort'],
                'ftp_passwort' => $acc['ftp_passwort'],
                'postfach' => $mitPostfach ? self::POSTFACH . '@' . $domain : '',
                'postfach_passwort' => $mitPostfach ? $kas->passwortNeu() : '',
                'server' => ($acc['login'] !== '' ? $acc['login'] : 'w…') . '.kasserver.com',
            ]);
            Db::update('hosting_auftraege', $auftragId, [
                'kas_login' => $acc['login'] ?: null, 'zugang_blob' => $blob,
                'zugang_bis' => date('Y-m-d H:i:s', strtotime('+' . self::ZUGANG_TAGE . ' days')),
            ]);
            self::schritt($auftragId, 'account', 'fertig', $acc['login'] !== '' ? 'Account ' . $acc['login'] : 'Angelegt — Login siehe Accountliste');
            $st = self::schritte($auftragId);
            $a = Db::one('SELECT * FROM hosting_auftraege WHERE id = ?', [$auftragId]);
        }
        if ((string) $st['account']['status'] !== 'fertig') {
            return ['ok' => false, 'text' => 'Der Account steht noch nicht.'];
        }

        /* 2. DOMAIN UND POSTFACH -- im Unter-Account, mit dessen Passwort aus
           der verschluesselten Ablage. Ist die weg (abgerufen, abgelaufen)
           oder fehlt der Login, geht es nur noch von Hand. */
        $z = $a['zugang_blob'] !== null ? self::entschluesseln((string) $a['zugang_blob']) : null;
        $als = ($z !== null && (string) ($z['kas_login'] ?? '') !== '')
            ? ['login' => (string) $z['kas_login'], 'passwort' => (string) $z['kas_passwort']] : null;

        foreach (['domain', 'postfach'] as $s) {
            if (!self::dran($st[$s])) { continue; }
            if ($als === null) {
                self::schritt($auftragId, $s, 'hand', 'Ohne Login des Unter-Accounts nicht automatisch — im KAS anlegen.');
                continue;
            }
            self::schritt($auftragId, $s, 'laeuft', null, true);
            $r = $s === 'domain'
                ? $kas->domainAnlegen($domain, $als)
                : $kas->postfachAnlegen(self::POSTFACH, $domain, (string) ($z['postfach_passwort'] ?? ''), $als);
            /* Kam die erste Antwort nie an, meldet der zweite Versuch "gibt
               es schon" -- das ist dann ein Erfolg, kein Fehler. */
            $schonDa = !$r['ok'] && preg_match('~already.?exist|exists|schon vorhanden~i', (string) $r['text']);
            if ($r['ok'] || $schonDa) {
                self::schritt($auftragId, $s, 'fertig', $schonDa ? 'War schon da.'
                    : ($s === 'domain' ? 'Domain im KAS' : 'Postfach ' . self::POSTFACH . '@' . $domain));
            } else {
                $versuche = (int) $st[$s]['versuche'] + 1;
                self::schritt($auftragId, $s, $versuche >= self::VERSUCHE ? 'hand' : 'fehler', (string) $r['text']);
            }
        }
        $st = self::schritte($auftragId);
        if (!self::erledigt($st['domain']) || !self::erledigt($st['postfach'])) {
            return ['ok' => false, 'text' => 'Domain oder Postfach wird noch einmal versucht.'];
        }

        /* 3. DER MONATSVERTRAG -- ausser er steckt in der Betreuung. Beim
           Solo-Kunden entstand er schon mit der Zustimmung; dann bleibt er. */
        $inklusive = self::inklusive($kundeId);
        if (self::dran($st['vertrag'])) {
            if (!$inklusive) {
                require_once __DIR__ . '/Abo.php';
                $schon = Db::one("SELECT id FROM abos WHERE customer_id = ? AND paket_slug = 'hosting'
                                    AND status IN ('angelegt','aktiv','gekuendigt')", [$kundeId]);
                if (!$schon) {
                    try {
                        Abo::anlegen($kundeId, ['paket_slug' => 'hosting',
                            'projekt_id' => $a['project_id'] !== null ? (int) $a['project_id'] : null,
                            'zahlart' => 'manuell', 'betrag_cents' => (int) $a['preis_cents']]);
                    } catch (Throwable $e) {
                        self::schritt($auftragId, 'vertrag', 'hand', 'Vertrag nicht angelegt: ' . $e->getMessage());
                    }
                }
            }
            $st = self::schritte($auftragId);
            if ((string) $st['vertrag']['status'] !== 'hand') {
                self::schritt($auftragId, 'vertrag', 'fertig', $inklusive ? 'In der Betreuung enthalten.'
                    : 'Monatsvertrag ' . number_format(((int) $a['preis_cents']) / 100, 2, ',', '.') . ' €');
            }
        }

        /* Ab hier ist das Automatische durch: Der Auftrag gilt als angelegt,
           und erst jetzt darf der Kunde die Zugangsdaten abrufen (mit dem
           Abruf verschwinden sie -- vorher wuerden sie fuer Wiederholungen
           noch gebraucht). */
        $st = self::schritte($auftragId);
        $hand = [];
        foreach ($st as $s => $zeile) {
            if ((string) $zeile['status'] === 'hand') { $hand[] = (self::SCHRITTE[$s] ?? $s) . ': ' . $zeile['text']; }
        }
        Db::update('hosting_auftraege', $auftragId, [
            'status' => 'angelegt', 'angelegt_am' => date('Y-m-d H:i:s'), 'inklusive' => $inklusive ? 1 : 0,
            'notiz' => $hand ? ('Von Hand: ' . implode(' · ', $hand)) : null,
        ]);

        /* 4. Der Kunde erfaehrt es -- OHNE Passwoerter in der Mail. Die Mail
           zeigt nur den Weg zur einmaligen Anzeige. */
        $mitPostfach = (string) $st['postfach']['status'] === 'fertig';
        if (self::dran($st['kunde'])) {
            $gesendet = false;
            try {
                if ($k && trim((string) $k['email']) !== '') {
                    require_once __DIR__ . '/Mail.php';
                    require_once __DIR__ . '/Texte.php';
                    require_once __DIR__ . '/Kundenzugang.php';
                    $sprache = strtolower((string) ($k['sprache'] ?: 'it'));
                    if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }
                    [$betreff, $text] = Texte::mail('hosting_fertig', $sprache, [
                        'name'   => (string) $k['name'],
                        'domain' => $domain,
                        'link'   => Kundenzugang::linkFuer($kundeId),
                        'tage'   => (string) self::ZUGANG_TAGE,
                        'umfang' => (string) (Texte::SEITE[$mitPostfach ? 'hostingUmfangMailFertig' : 'hostingUmfangFertig'][$sprache] ?? ''),
                    ]);
                    $gesendet = Mail::senden('hosting_fertig', (string) $k['email'], $betreff, $text,
                        ['customer_id' => $kundeId, 'antwortAn' => Mail::eigeneAdresse()]);
                }
            } catch (Throwable $e) { $gesendet = false; }
            /* Eine Mail, die nicht rausging, haelt nichts auf: Die Anzeige
               steht auf der Kundenseite bereit, und der Postausgang zeigt es. */
            self::schritt($auftragId, 'kunde', 'fertig', $gesendet ? 'Mail ist raus.' : 'Mail nicht zugestellt — steht im Postausgang.');
        }

        /* 5. Die Aufgabe fuer Uwe -- je nachdem, was mit der Domain geschehen
           soll. Registrieren und Umziehen gehen nur im Domainbestellsystem
           (keine API bei All-Inkl), SSL per Let's Encrypt nur im KAS. Bei einer
           Domain, die beim alten Anbieter bleibt, darf NUR der Web-Eintrag
           geaendert werden: MX, SPF, DKIM, DMARC und TXT sind Sache des Kunden. */
        if (self::dran($st['aufgabe'])) {
            $aktion = (string) ($a['domain_aktion'] ?? 'neu');
            $was = [
                'neu'      => 'Jetzt im Domainbestellsystem (domain-bestellsystem.de) die Domain ' . $domain
                            . ' auf den Kunden als Inhaber bestellen — Nameserver ns5.kasserver.com. ',
                'transfer' => 'Umzug (KK) von ' . $domain . ': Auth-Code beim Kunden anfordern (nicht per Mail im Klartext '
                            . 'aufbewahren), VORHER die DNS-Einträge beim alten Anbieter ablesen und MX, SPF, DKIM, DMARC '
                            . 'und TXT im KAS-DNS eintragen, dann den KK-Antrag im Domainbestellsystem stellen. Inhaber bleibt der Kunde. ',
                'behalten' => 'Die Domain ' . $domain . ' bleibt beim bisherigen Anbieter des Kunden: dort nur A/AAAA für '
                            . $domain . ' und www auf den KAS-Server zeigen lassen — MX, SPF, DKIM, DMARC und TXT NICHT anfassen. ',
                'offen'    => 'Mit dem Kunden klären, ob ' . $domain . ' beim alten Anbieter bleibt oder umzieht — '
                            . 'ohne sein ausdrückliches Ja wird nichts übertragen. ',
            ][$aktion] ?? '';
            $fertig = [];
            foreach ($st as $s => $zeile) {
                if ((string) $zeile['status'] === 'fertig' && in_array($s, ['account', 'domain', 'postfach'], true)) {
                    $fertig[] = (string) $zeile['text'];
                }
            }
            Events::melden('hosting_bestellen', ($aktion === 'neu' ? 'Domain bestellen: ' : 'Domain einrichten: ') . $domain, 'hinweis',
                'Erledigt: ' . implode(' · ', $fertig) . '. '
                . $was
                . 'Danach im KAS den SSL-Schutz (Let\'s Encrypt, kostenlos) für die Domain aktivieren. '
                . ((string) $st['postfach']['status'] === 'entfaellt' ? 'Kein Postfach angelegt — der Kunde behält seine E-Mail, wo sie ist. ' : '')
                . ($hand ? 'Außerdem von Hand: ' . implode(' · ', $hand) . '. ' : '')
                . ($inklusive ? 'Abrechnung: in der Betreuung enthalten.'
                    : 'Monatsvertrag ' . number_format(((int) $a['preis_cents']) / 100, 2, ',', '.') . ' € läuft.'),
                '/kunden/' . $kundeId);
            self::schritt($auftragId, 'aufgabe', 'fertig', 'Auf „Heute“.');
            Events::protokoll('hosting_angelegt', 'Hosting angelegt: ' . $domain
                . ((string) ($a['kas_login'] ?? '') !== '' ? ' (' . $a['kas_login'] . ')' : ''), $kundeId, null,
                $a['project_id'] !== null ? (int) $a['project_id'] : null);
        }

        return ['ok' => true, 'text' => $hand ? 'Angelegt, mit Handarbeit: ' . implode(' · ', $hand) : 'Angelegt.'];
    }

    /** Eine Meldung fuer Uwe, wenn ein Schritt Handarbeit geworden ist. */
    private static function melden(array $a, string $wer, string $was): void
    {
        Events::melden('hosting_fehler', 'Hosting: ' . $was, 'schlecht',
            $wer . ' / ' . $a['domain'] . ' — ' . $was . ' Die Schritte stehen beim Kunden in der Verwaltung.',
            '/kunden/' . (int) $a['customer_id']);
    }

    /**
     * Der Cron: gescheiterte oder liegengebliebene Schritte nach einer Pause
     * noch einmal. "laeuft" beim Account wird dabei zu Handarbeit (weiter()),
     * "hand" wird nie von selbst angefasst.
     */
    public static function fortsetzen(?object $kas = null): int
    {
        $ids = array_column(Db::all(
            "SELECT DISTINCT h.id FROM hosting_auftraege h
               JOIN hosting_schritte s ON s.auftrag_id = h.id
              WHERE h.status = 'in_arbeit' AND s.status IN ('fehler', 'offen', 'laeuft') AND s.versuche < ?
                AND s.updated_at < NOW() - INTERVAL " . (int) self::PAUSE_MINUTEN . " MINUTE
              LIMIT 5", [self::VERSUCHE]), 'id');
        foreach ($ids as $id) { self::still(static fn() => self::weiter((int) $id, $kas)); }
        return count($ids);
    }

    /**
     * Uwes Knopf "Offene Schritte wiederholen": gibt gescheiterten und von
     * Hand markierten Schritten neue Versuche -- ausser dem Account, wenn
     * unklar ist, ob er entstand (dann hat Uwe im KAS nachgesehen und
     * drueckt den Knopf bewusst).
     */
    public static function wiederholen(int $auftragId, ?object $kas = null): array
    {
        Db::run("UPDATE hosting_schritte SET status = 'fehler', versuche = 0
                  WHERE auftrag_id = ? AND status IN ('fehler', 'hand')", [$auftragId]);
        Db::run("UPDATE hosting_auftraege SET status = 'in_arbeit' WHERE id = ? AND status = 'angelegt'
                  AND EXISTS (SELECT 1 FROM hosting_schritte s WHERE s.auftrag_id = hosting_auftraege.id AND s.status = 'fehler')",
                [$auftragId]);
        return self::weiter($auftragId, $kas);
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
        /* Erst wenn das Automatische durch ist: Mit dem Abruf verschwindet
           der Blob -- vorher braucht die Einrichtung ihn noch fuer Domain
           und Postfach (Phase 3). */
        if (!in_array((string) $a['status'], ['angelegt', 'aktiv'], true)) { return null; }
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
