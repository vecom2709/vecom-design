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

        /* Ein Postfach nur, wenn der Kunde E-Mail ueber Vecom gewaehlt hat
           (25.09.2026). Bleibt seine E-Mail bei Microsoft 365 oder beim alten
           Anbieter, waere ein Postfach hier nicht nur ueberfluessig -- es
           koennte ihm Mails wegfangen, sobald jemand die MX-Eintraege umstellt. */
        $mitPostfach = (string) ($a['mail'] ?? 'vecom') === 'vecom';
        $mailPw = $mitPostfach ? Kas::passwortNeu() : '';
        if ($als !== null && $mitPostfach) {
            /* kontakt@ statt info@ (Uwe, 25.09.2026): dieselbe Adresse, die
               Vecom selbst benutzt -- kontakt@vecom-design.it. */
            $m = Kas::postfachAnlegen(self::POSTFACH, $domain, $mailPw, $als);
            $m['ok'] ? $schritte[] = 'Postfach ' . self::POSTFACH . '@' . $domain
                     : $offen[] = 'Postfach ' . self::POSTFACH . '@ anlegen (' . $m['text'] . ')';
        }

        /* 3. Zugangsdaten verschluesselt ablegen — einmaliger Abruf. */
        $blob = self::verschluesseln([
            'kas_login' => $acc['login'], 'kas_passwort' => $acc['kas_passwort'],
            'ftp_passwort' => $acc['ftp_passwort'],
            'postfach' => $mitPostfach ? self::POSTFACH . '@' . $domain : '',
            'postfach_passwort' => $mailPw,
            'server' => ($acc['login'] !== '' ? $acc['login'] : 'w…') . '.kasserver.com',
        ]);

        /* 4. Der Monatsvertrag — ausser er steckt in der Betreuung. */
        $inklusive = self::inklusive($kundeId);
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

        /* 5. Der Kunde erfaehrt es — OHNE Passwoerter in der Mail. Die Mail
           zeigt nur den Weg zur einmaligen Anzeige und sagt ihm, die
           Passwoerter danach im KAS zu aendern. In eigenem Netz: Ein
           stummer Mailserver macht das Angelegte nicht ungeschehen. */
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
                Mail::senden('hosting_fertig', (string) $k['email'], $betreff, $text,
                    ['customer_id' => $kundeId, 'antwortAn' => Mail::eigeneAdresse()]);
            }
        } catch (Throwable $e) { /* die Anzeige auf der Kundenseite steht trotzdem bereit */ }

        /* 6. Die Aufgabe fuer Uwe -- je nachdem, was mit der Domain geschehen
           soll. Registrieren und Umziehen gehen nur im Domainbestellsystem
           (keine API bei All-Inkl), SSL per Let's Encrypt nur im KAS. Bei einer
           Domain, die beim alten Anbieter bleibt, darf NUR der Web-Eintrag
           geaendert werden: MX, SPF, DKIM, DMARC und TXT sind Sache des Kunden. */
        $aktion = (string) ($a['domain_aktion'] ?? 'neu');
        $schritt = [
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
        Events::melden('hosting_bestellen', ($aktion === 'neu' ? 'Domain bestellen: ' : 'Domain einrichten: ') . $domain, 'hinweis',
            'Erledigt: ' . implode(' · ', $schritte) . '. '
            . $schritt
            . 'Danach im KAS den SSL-Schutz (Let\'s Encrypt, kostenlos) für die Domain aktivieren. '
            . ($mitPostfach ? '' : 'Kein Postfach angelegt — der Kunde behält seine E-Mail, wo sie ist. ')
            . ($offen ? 'Außerdem von Hand: ' . implode(' · ', $offen) . '. ' : '')
            . ($inklusive ? 'Abrechnung: in der Betreuung enthalten.'
                : 'Monatsvertrag ' . number_format(((int) $a['preis_cents']) / 100, 2, ',', '.') . ' € läuft.'),
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
