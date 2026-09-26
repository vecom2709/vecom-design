<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Fmt.php';
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
    /** Das erste Postfach eines Hosting-Kunden. */
    /** Das eine Postfach, das beim Einrichten entsteht. Bis 26.09.2026
     *  "kontakt" -- die Website versprach aber info@, und fuer italienische
     *  Kunden ist "kontakt" ein fremdes Wort. Seitdem info@, wie versprochen. */
    public const POSTFACH = 'info';

    /** Fassung des Kastentextes -- wird mit jeder Zustimmung gespeichert. */
    public const FASSUNG = '2026-09-25';

    /** Nach so vielen Tagen ohne Abruf werden die Zugangsdaten vernichtet. */
    public const ZUGANG_TAGE = 14;

    /** Betreuungspakete, in denen das Hosting schon drinsteckt. */
    public const INKLUSIVE_BEI = ['betreuung-plus', 'betreuung-premium'];

    /** Speicher je Kunden-Account, in Megabyte (Reseller-Pool: 200 GB auf
     *  25 Accounts — ohne Grenze koennte EIN Kunde alles belegen).
     *
     *  Seit 26.09.2026 nur noch der VORGABEWERT fuer neue Auftraege: Was mit
     *  einem Kunden vereinbart ist, steht in hosting_auftraege.speicher_mb
     *  und gilt, auch wenn diese Zahl sich einmal aendert. Eine geaenderte
     *  Vorgabe darf keinem bestehenden Kunden still seinen Speicher nehmen. */
    public const SPEICHER_MB = 10240;

    /** Obergrenze fuer eine einzelne Vereinbarung: der ganze Reseller-Pool. */
    public const SPEICHER_MAX_MB = 204800;

    /* ------------------------------------------------------------------ */
    /*  Gerecht geteilt (26.09.2026, Uwe: "gerecht aufgeteilt anhand der  */
    /*  gesamten Kunden -- auch E-Mails, Subdomains usw.")                */
    /* ------------------------------------------------------------------ */

    /** Plaetze fuer Kunden, wenn der Vertrag keine Zahl nennt (WEB-L-Reseller, PROJEKT.md 07.09.2026). */
    public const PLAETZE_ERSATZ = 25;

    /** Was der Reseller "unbegrenzt" hat, bekommt jeder Kunde so oft -- genug fuer
     *  eine Firma, zu wenig, als dass einer den Server mit Tausenden fuellt. */
    public const UNBEGRENZT_JE_KUNDE = 10;

    /** Darunter geht es nicht: sonst scheitert das Einrichten selbst (eine Domain,
     *  ein Postfach, die Weiterleitungen aus dem Fragebogen, Datenbank/FTP auf Wunsch). */
    public const KONTINGENT_MINDEST = [
        'max_domain' => 1, 'max_subdomain' => 1, 'max_mail_account' => 1, 'max_mail_forward' => 10,
        'max_database' => 1, 'max_ftpuser' => 1, 'max_cronjobs' => 1,
    ];

    /** Was geteilt wird. Unter-Accounts, Netzlaufwerke und Baukaesten nicht: Die braucht ein Kunde nicht. */
    public const KONTINGENT_TEILEN = ['max_domain', 'max_subdomain', 'max_mail_account', 'max_mail_forward',
        'max_mailinglist', 'max_database', 'max_ftpuser', 'max_cronjobs'];

    /**
     * Den Reseller-Vertrag gerecht auf die Kunden-Plaetze teilen.
     *
     * GERECHT HEISST: jeder Platz gleich viel, abgerundet. Geteilt wird durch
     * die Plaetze des Vertrags (max_account), NICHT durch die Kunden von
     * heute -- sonst schrumpfte jedem Kunden sein Speicher, sobald ein neuer
     * dazukommt, und aus einer Vereinbarung wuerde eine Schaetzung. So passt
     * auch der letzte Platz noch hinein, und niemand ist ueberbucht.
     *
     * @param array<string,array{max?:mixed}> $ressourcen wie get_accountresources
     * @return array{je_kunde:array<string,int>, plaetze:int, quelle:string, knapp:list<string>}
     */
    public static function kontingentAus(array $ressourcen): array
    {
        $max = static fn(string $k): ?int => isset($ressourcen[$k]['max']) && is_numeric($ressourcen[$k]['max']) ? (int) $ressourcen[$k]['max'] : null;
        $plaetze = ($max('max_account') ?? 0) > 0 ? (int) $max('max_account') : self::PLAETZE_ERSATZ;
        $je = []; $knapp = [];
        $ws = $max('max_webspace');
        if ($ws === null || $ws < 0) {
            $je['max_webspace'] = self::SPEICHER_MB;
        } else {
            $mb = intdiv($ws, $plaetze);
            $je['max_webspace'] = $mb >= 1024 ? intdiv($mb, 1024) * 1024 : $mb;   // ganze GB, wo es geht
        }
        foreach (self::KONTINGENT_TEILEN as $k) {
            $m = $max($k);
            $wert = $m === null ? (self::KONTINGENT_MINDEST[$k] ?? 0)
                : ($m < 0 ? self::UNBEGRENZT_JE_KUNDE : intdiv($m, $plaetze));
            $mindest = self::KONTINGENT_MINDEST[$k] ?? 0;
            if ($wert < $mindest) { $knapp[] = $k; $wert = $mindest; }
            if ($wert > 0) { $je[$k] = $wert; }
        }
        return ['je_kunde' => $je, 'plaetze' => $plaetze, 'quelle' => $ressourcen ? 'vertrag' : 'ersatz', 'knapp' => $knapp];
    }

    /** Die Vorgabe fuer neue Auftraege -- aus dem zuletzt ausgelesenen Reseller-Stand. */
    public static function vorgabe(): array
    {
        $stand = json_decode((string) self::still(static fn() => Db::wert("SELECT svalue FROM settings WHERE skey = 'kas_reseller_stand'", [], ''), ''), true);
        $res = is_array($stand) && is_array($stand['ressourcen'] ?? null) ? $stand['ressourcen'] : [];
        return self::kontingentAus($res);
    }

    public static function speicherVorgabe(): int
    {
        return (int) self::vorgabe()['je_kunde']['max_webspace'];
    }

    /** Was ein neuer Auftrag beim Anlegen festhaelt: Speicher und die uebrigen Kontingente. */
    public static function vorgabeFelder(): array
    {
        $je = self::vorgabe()['je_kunde'];
        $mb = (int) $je['max_webspace']; unset($je['max_webspace']);
        return ['speicher_mb' => $mb, 'kontingente' => json_encode($je)];
    }

    /**
     * Die Grenzen fuer add_account: das Festgehaltene, sonst die Vorgabe von
     * heute -- nie unter dem, was das Einrichten selbst braucht.
     * @return array<string,int>
     */
    public static function grenzenVon(array $a): array
    {
        $k = json_decode((string) ($a['kontingente'] ?? ''), true);
        if (!is_array($k) || !$k) { $k = self::vorgabe()['je_kunde']; unset($k['max_webspace']); }
        $g = ['max_webspace' => self::speicherVon($a)];
        foreach (self::KONTINGENT_TEILEN as $n) {
            $w = max((int) ($k[$n] ?? 0), (int) (self::KONTINGENT_MINDEST[$n] ?? 0));
            if ($w > 0) { $g[$n] = $w; }
        }
        $g['max_mail_forward'] = max($g['max_mail_forward'] ?? 0, count(self::weiterleitungen((string) ($a['weiterleitungen'] ?? ''))));
        return $g;
    }

    /**
     * Nach dem Auslesen: Auftraege, denen der Kunde noch NICHT zugestimmt hat,
     * bekommen die neue Vorgabe. Zugestimmte behalten, was im Zustimmungstext
     * stand -- eine Vereinbarung wird nicht nachtraeglich kleiner.
     */
    public static function vorgabeAnwenden(): int
    {
        $f = self::vorgabeFelder();
        return Db::run("UPDATE hosting_auftraege SET speicher_mb = ?, kontingente = ? WHERE status = 'vorgeschlagen'",
            [$f['speicher_mb'], $f['kontingente']])->rowCount();
    }

    /**
     * Den Reseller auslesen, den Stand merken und offene Angebote auf die
     * Aufteilung bringen -- fuer den Knopf und fuer den taeglichen Lauf.
     * Nur lesende KAS-Aufrufe (Kas::resellerLesen).
     *
     * @param callable():array|null $lesen austauschbar fuer die Pruefkette
     * @param bool $nurWennAlt der Cron liest hoechstens einmal in 20 Stunden
     * @return array{gelesen:bool, fehler:list<string>, angepasst:int}
     */
    public static function resellerAktualisieren(?callable $lesen = null, bool $nurWennAlt = false): array
    {
        if ($nurWennAlt) {
            $alt = json_decode((string) Db::wert("SELECT svalue FROM settings WHERE skey = 'kas_reseller_stand'", [], ''), true);
            if (is_array($alt) && (string) ($alt['am'] ?? '') > date('Y-m-d H:i:s', strtotime('-20 hours'))) {
                return ['gelesen' => false, 'fehler' => [], 'angepasst' => 0];
            }
            if ($lesen === null && !Kas::bereit()) { return ['gelesen' => false, 'fehler' => [], 'angepasst' => 0]; }
        }
        $rs = $lesen !== null ? $lesen() : Kas::resellerLesen();
        /* Einen guten Stand nicht durch einen kaputten ersetzen: Kamen die
           Kontingente nicht, bleibt der alte -- sonst fiele die Aufteilung
           still auf den Ersatzwert zurueck. */
        if (empty($rs['ressourcen'])) {
            $alt = json_decode((string) Db::wert("SELECT svalue FROM settings WHERE skey = 'kas_reseller_stand'", [], ''), true);
            if (is_array($alt) && !empty($alt['ressourcen'])) {
                return ['gelesen' => false, 'fehler' => (array) ($rs['fehler'] ?? ['Keine Kontingente gelesen.']), 'angepasst' => 0];
            }
        }
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('kas_reseller_stand', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
            [json_encode($rs, JSON_UNESCAPED_UNICODE)]);
        $n = !empty($rs['ressourcen']) ? self::vorgabeAnwenden() : 0;
        if ($n > 0) { Events::protokoll('hosting_vorgabe', $n . ' offene(s) Angebot(e) auf die neue Aufteilung gesetzt: ' . self::grenzenText(self::vorgabe()['je_kunde'])); }
        return ['gelesen' => true, 'fehler' => (array) ($rs['fehler'] ?? []), 'angepasst' => $n];
    }

    /** Der vereinbarte Speicher eines Auftrags -- die Quelle, nach der sich der KAS richtet. */
    public static function speicherVon(array $a): int
    {
        $mb = (int) ($a['speicher_mb'] ?? 0);
        return $mb > 0 ? $mb : self::SPEICHER_MB;
    }

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

            Db::insert('hosting_auftraege', self::vorgabeFelder() + [
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

        Db::insert('hosting_auftraege', self::vorgabeFelder() + [
            'customer_id' => $kundeId, 'project_id' => $projektId ?: null,
            'domain' => $domain, 'domain_aktion' => $aktion, 'mail' => $mail,
            'weiterleitungen' => $mail === 'vecom' ? (implode(',', self::weiterleitungen((string) ($antworten['mail_weiter'] ?? ''))) ?: null) : null,
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
            '{gb}'     => self::gb(self::speicherVon($a)),
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

        $auftragId = (int) Db::insert('hosting_auftraege', self::vorgabeFelder() + [
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
        'weiterleitung' => 'Weiterleitungen',
        'datenbank' => 'Datenbank',
        'ftp'      => 'FTP-Zugang für Vecom',
        'dns'      => 'DNS vom alten Anbieter',
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
        /* PROBELAUF (26.09.2026): Mit dem echten KAS und eingeschaltetem
           Probelauf wird nichts beansprucht und nichts angelegt -- nur
           aufgeschrieben, was geschehen wuerde. Der Auftrag bleibt
           "zugestimmt" und laeuft von selbst an, sobald der Probelauf aus
           ist (fortsetzen). Einen halb angelegten Auftrag mit erfundenem
           Login darf es nicht geben. */
        if ($kas === null && Kas::probelauf()) { return self::probelaufMerken($a); }
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
            public function dnsLesen(string $d, ?array $als = null): array { return Kas::dnsLesen($d, $als); }
            public function dnsHinzufuegen(string $d, string $t, string $n, string $w, int $aux = 0, ?array $als = null): array { return Kas::dnsHinzufuegen($d, $t, $n, $w, $aux, $als); }
            public function weiterleitungAnlegen(string $l, string $d, string $z, ?array $als = null): array { return Kas::weiterleitungAnlegen($l, $d, $z, $als); }
            public function dnsAendern(string $id, string $w, int $aux = 0, ?array $als = null): array { return Kas::dnsAendern($id, $w, $aux, $als); }
            public function bestand(string $d): array { require_once __DIR__ . '/Domainumzug.php'; return Domainumzug::bestandsaufnahme($d); }
            public function kommentare(string $aktion, ?array $als = null): array { return Kas::kommentare($aktion, $als); }
            public function datenbankAnlegen(string $k, string $pw, ?array $als = null): array { return Kas::datenbankAnlegen($k, $pw, $als); }
            public function ftpAnlegen(string $k, string $pw, ?array $als = null): array { return Kas::ftpAnlegen($k, $pw, $als); }
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
        if ($kas === null && Kas::probelauf()) {
            return ['ok' => false, 'text' => 'Probelauf ist an — beim KAS wird nichts angelegt. Unter Einstellungen → Server ausschalten.'];
        }
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
        if (!isset($st['account'])) { return ['ok' => false, 'text' => 'Wird gerade eingerichtet.']; }
        /* Ein Schritt, den es beim Anlegen noch nicht gab (DNS, 25.09.2026):
           nachtragen, statt den Auftrag daran haengen zu lassen. */
        foreach (array_keys(self::SCHRITTE) as $s) {
            if (!isset($st[$s])) {
                Db::run('INSERT IGNORE INTO hosting_schritte (auftrag_id, schritt, status) VALUES (?, ?, ?)', [$auftragId, $s, 'offen']);
            }
        }
        $st = self::schritte($auftragId);

        /* Domain und Postfach, die mitten im Aufruf abbrachen, duerfen einfach
           noch einmal: Ein zweites Anlegen meldet "gibt es schon", und das
           zaehlt als Erfolg (siehe unten). Beim Account gilt das nicht. */
        foreach (['domain', 'postfach', 'weiterleitung', 'datenbank', 'ftp'] as $s) {
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
            /* Alle Grenzen, nicht nur der Speicher: add_account setzt jede
               fehlende auf 0 (Doku) -- ohne sie haette der Account keine
               Domain und kein Postfach anlegen duerfen. */
            $grenzen = self::grenzenVon($a);
            if (empty($a['kontingente'])) {
                $festhalten = $grenzen; unset($festhalten['max_webspace']);
                Db::run('UPDATE hosting_auftraege SET kontingente = ? WHERE id = ?', [json_encode($festhalten), $auftragId]);
            }
            $acc = $kas->accountAnlegen($wer . ' — ' . $domain, $grenzen);
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
            self::schritt($auftragId, 'account', 'fertig', ($acc['login'] !== '' ? 'Account ' . $acc['login'] : 'Angelegt — Login siehe Accountliste')
                . ' · Speicher ' . self::speicherVon($a) . ' MB');
            Events::protokoll('hosting_speicher_gesetzt', 'KAS-Account für ' . $domain . ' angelegt mit ' . self::grenzenText($grenzen)
                . ' (wie vereinbart)', $kundeId);
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

        /* 2a. WEITERLEITUNGEN -- kontakt@, buchung@ ... auf info@, wie im
           Fragebogen gewuenscht. Nur mit Postfach, nur im Unter-Account. */
        if (self::dran($st['weiterleitung'])) {
            $wl = self::weiterleitungen((string) ($a['weiterleitungen'] ?? ''));
            if (!$wl || (string) $st['postfach']['status'] !== 'fertig') {
                self::schritt($auftragId, 'weiterleitung', 'entfaellt', $wl ? 'Ohne Postfach bei uns keine Weiterleitung.' : 'Keine gewünscht.');
            } elseif ($als === null) {
                self::schritt($auftragId, 'weiterleitung', 'hand', 'Ohne Login des Unter-Accounts nicht automatisch: ' . implode(', ', $wl));
            } else {
                self::schritt($auftragId, 'weiterleitung', 'laeuft', null, true);
                $nicht = [];
                foreach ($wl as $lokal) {
                    $r = $kas->weiterleitungAnlegen($lokal, $domain, self::POSTFACH . '@' . $domain, $als);
                    if (!$r['ok']) { $nicht[] = $lokal . ' (' . $r['text'] . ')'; }
                }
                $versuche = (int) $st['weiterleitung']['versuche'] + 1;
                self::schritt($auftragId, 'weiterleitung', $nicht ? ($versuche >= self::VERSUCHE ? 'hand' : 'fehler') : 'fertig',
                    $nicht ? 'Nicht angelegt: ' . implode(' · ', $nicht)
                           : implode(', ', array_map(static fn($l) => $l . '@', $wl)) . ' → ' . self::POSTFACH . '@' . $domain);
            }
            $st = self::schritte($auftragId);
        }

        /* 2a'. DATENBANK UND FTP -- nur, wenn Uwe sie beim Auftrag angekreuzt
           hat (eine statische Seite braucht beides nicht; ein Postfach auf
           Vorrat ist genau das, was der Masterprompt verbietet). Idempotent
           ueber den Kommentar: Steht "vecom-<Auftrag>-db" schon im Account,
           entsteht keine zweite. Die Passwoerter landen verschluesselt in
           technik_blob -- fuer Uwe, nie auf der Kundenseite. */
        foreach (['datenbank' => 'mit_datenbank', 'ftp' => 'mit_ftp'] as $s => $feld) {
            if (!self::dran($st[$s])) { continue; }
            if (empty($a[$feld])) { self::schritt($auftragId, $s, 'entfaellt', 'Nicht gewünscht.'); continue; }
            if ($als === null) { self::schritt($auftragId, $s, 'hand', 'Ohne Login des Unter-Accounts nicht automatisch — im KAS anlegen.'); continue; }
            $kommentar = 'vecom-' . $auftragId . '-' . ($s === 'datenbank' ? 'db' : 'ftp');
            self::schritt($auftragId, $s, 'laeuft', null, true);
            $da = $kas->kommentare($s === 'datenbank' ? 'get_databases' : 'get_ftpusers', $als);
            if (!$da['ok']) {
                $versuche = (int) $st[$s]['versuche'] + 1;
                self::schritt($auftragId, $s, $versuche >= self::VERSUCHE ? 'hand' : 'fehler', (string) $da['text']);
                continue;
            }
            if (in_array($kommentar, $da['kommentare'], true)) {
                self::schritt($auftragId, $s, 'fertig', 'War schon da (' . $kommentar . ').');
                continue;
            }
            $pw = $kas->passwortNeu();
            $r = $s === 'datenbank' ? $kas->datenbankAnlegen($kommentar, $pw, $als) : $kas->ftpAnlegen($kommentar, $pw, $als);
            if (!$r['ok']) {
                $versuche = (int) $st[$s]['versuche'] + 1;
                self::schritt($auftragId, $s, $versuche >= self::VERSUCHE ? 'hand' : 'fehler', (string) $r['text']);
                continue;
            }
            $t = !empty($a['technik_blob']) ? (self::entschluesseln((string) $a['technik_blob']) ?? []) : [];
            $t[$s] = $s === 'datenbank'
                ? ['name' => (string) ($r['name'] ?? ''), 'passwort' => $pw, 'kommentar' => $kommentar]
                : ['login' => (string) ($r['login'] ?? ''), 'passwort' => $pw, 'kommentar' => $kommentar];
            $blobT = self::verschluesseln($t);
            if ($blobT !== null) { Db::update('hosting_auftraege', $auftragId, ['technik_blob' => $blobT]); $a['technik_blob'] = $blobT; }
            self::schritt($auftragId, $s, 'fertig', ($s === 'datenbank'
                ? 'Datenbank ' . ((string) ($r['name'] ?? '') ?: $kommentar) : 'FTP-Nutzer ' . ((string) ($r['login'] ?? '') ?: $kommentar))
                . ($blobT === null ? ' — Passwort nicht ablegbar (Schlüssel fehlt), im KAS neu setzen.' : ''));
            Events::protokoll('hosting_' . $s, ($s === 'datenbank' ? 'Datenbank' : 'FTP-Zugang') . ' für ' . $domain . ' angelegt', $kundeId);
        }
        $st = self::schritte($auftragId);

        /* 2b. DNS VOM ALTEN ANBIETER -- nur beim Umzug, und nur wenn die
           Domain im KAS steht. Was heute beim alten Anbieter eingetragen
           ist, kommt in die KAS-Zone, BEVOR die Nameserver umziehen: Sonst
           kommen danach keine Mails mehr an, und Bestaetigungen (Google,
           Microsoft) sind weg. */
        if (self::dran($st['dns'])) {
            if ((string) ($a['domain_aktion'] ?? '') !== 'transfer') {
                self::schritt($auftragId, 'dns', 'entfaellt', (string) ($a['domain_aktion'] ?? '') === 'behalten'
                    ? 'Die Domain bleibt beim bisherigen Anbieter — dort nur den Web-Eintrag ändern.'
                    : 'Neue Domain — es gibt nichts zu übernehmen.');
            } elseif ($als === null || (string) $st['domain']['status'] !== 'fertig') {
                self::schritt($auftragId, 'dns', 'hand', 'Ohne Domain im KAS oder ohne Login nicht automatisch — Einträge aus der Kundenakte im KAS eintragen.');
            } else {
                self::schritt($auftragId, 'dns', 'laeuft', null, true);
                try {
                    $r = self::dnsUebernehmen($domain, (string) ($a['mail'] ?? 'vecom'), $als, $kas);
                    self::schritt($auftragId, 'dns', !empty($r['hand']) ? 'hand'
                        : ($r['ok'] ? 'fertig' : ((int) $st['dns']['versuche'] + 1 >= self::VERSUCHE ? 'hand' : 'fehler')), $r['text']);
                } catch (Throwable $e) {
                    self::schritt($auftragId, 'dns', (int) $st['dns']['versuche'] + 1 >= self::VERSUCHE ? 'hand' : 'fehler', $e->getMessage());
                }
            }
            $st = self::schritte($auftragId);
        }

        if (!self::erledigt($st['domain']) || !self::erledigt($st['postfach']) || !self::erledigt($st['dns']) || !self::erledigt($st['weiterleitung'])) {
            return ['ok' => false, 'text' => 'Domain, Postfach, Weiterleitung oder DNS wird noch einmal versucht.'];
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
                'neu'      => 'Jetzt ' . $domain . ' im Domainbestellsystem auf den Kunden bestellen — alles zum Kopieren '
                            . '(Inhaber, Nameserver) steht in der Kundenakte; dass sie da ist, merkt das System selbst. ',
                'transfer' => 'Umzug (KK) von ' . $domain . ': Der Kunde gibt den Auth-Code auf seiner Seite ein (du bekommst '
                            . 'Bescheid). In der Kundenakte steht die DNS-Bestandsaufnahme — MX, SPF, DKIM, DMARC und TXT '
                            . 'VOR dem Antrag im KAS-DNS eintragen, dann den KK-Antrag im Domainbestellsystem stellen und '
                            . '„KK-Antrag gestellt“ klicken. Inhaber bleibt der Kunde. ',
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
            /* Phase 5: Beim Umzug gleich die Begleitung anlegen -- DNS-
               Bestandsaufnahme und Sperre lesen, damit der Kunde auf seiner
               Seite sieht, was er tun muss (Sperre loesen, Code eingeben). */
            if ($aktion === 'transfer') {
                try {
                    require_once __DIR__ . '/Domainumzug.php';
                    Domainumzug::anlegen($auftragId);
                } catch (Throwable $e) { /* die Aufgabe fuer Uwe steht trotzdem */ }
            }
            Events::protokoll('hosting_angelegt', 'Hosting angelegt: ' . $domain
                . ((string) ($a['kas_login'] ?? '') !== '' ? ' (' . $a['kas_login'] . ')' : ''), $kundeId, null,
                $a['project_id'] !== null ? (int) $a['project_id'] : null);
        }

        return ['ok' => true, 'text' => $hand ? 'Angelegt, mit Handarbeit: ' . implode(' · ', $hand) : 'Angelegt.'];
    }

    /**
     * Welche Eintraege vom alten Anbieter in die KAS-Zone gehoeren.
     *
     * DIE REGEL
     *   - Web (A/AAAA/CNAME fuer @ und www) und NS nie: Genau die sollen ja
     *     kuenftig auf den KAS zeigen -- das stellt der KAS selbst ein.
     *   - Bleibt die E-Mail beim alten Anbieter (Microsoft 365, Google ...):
     *     MX, SPF, DKIM und DMARC mitnehmen -- und die MX des KAS ersetzen.
     *   - Laeuft die E-Mail kuenftig ueber Vecom: nichts davon, der KAS hat
     *     seine eigenen. Mitgenommen werden nur fremde TXT-Eintraege
     *     (Bestaetigungen von Google, Microsoft, Facebook ...).
     *
     * @param list<array{name:string,typ:string,wert:string}> $bestand
     * @return array{eintraege:list<array{typ:string,name:string,daten:string,aux:int}>, mx_ersetzen:bool}
     */
    public static function dnsAuswahl(array $bestand, string $mail): array
    {
        $mailBleibt = $mail !== 'vecom';
        $aus = [];
        foreach ($bestand as $e) {
            $name = $e['name'] === '@' ? '' : (string) $e['name'];
            $typ = strtoupper((string) $e['typ']);
            $wert = trim((string) $e['wert']);
            if (in_array($typ, ['NS', 'A', 'AAAA'], true) || ($typ === 'CNAME' && in_array($name, ['', 'www'], true))) { continue; }
            $istSpf = $typ === 'TXT' && stripos($wert, 'v=spf1') === 0;
            $istMail = $typ === 'MX' || $istSpf || str_starts_with($name, '_dmarc') || str_contains($name, '._domainkey');
            if ($istMail && !$mailBleibt) { continue; }
            $aux = 0;
            if ($typ === 'MX' && preg_match('~^(\d+)\s+(\S+)$~', $wert, $m)) { $aux = (int) $m[1]; $wert = rtrim($m[2], '.'); }
            $aus[] = ['typ' => $typ, 'name' => $name, 'daten' => $wert, 'aux' => $aux];
        }
        $mxDa = (bool) array_filter($aus, static fn($x) => $x['typ'] === 'MX');
        return ['eintraege' => $aus, 'mx_ersetzen' => $mailBleibt && $mxDa];
    }

    /** Die Auswahl in die KAS-Zone schreiben. */
    private static function dnsUebernehmen(string $domain, string $mail, array $als, object $kas): array
    {
        $bestand = $kas->bestand($domain);
        $plan = self::dnsAuswahl($bestand, $mail);
        if (!$plan['eintraege']) { return ['ok' => true, 'text' => 'Beim alten Anbieter stand nichts, was mit muss.']; }
        $umgeschrieben = 0;
        $uebrigKas = 0;
        $eintraege = $plan['eintraege'];
        if ($plan['mx_ersetzen']) {
            /* Die MX des KAS duerfen nicht neben denen des alten Anbieters
               stehen -- sonst landet ein Teil der Post in einem KAS-Postfach,
               das es fuer diesen Kunden gar nicht gibt. GELOESCHT wird nicht
               (Kas hat mit Absicht keine loeschende Methode): Die KAS-MX
               werden auf die alten Ziele UMGESCHRIEBEN. Nur ausdruecklich
               aenderbare mit Nummer; bleibt einer uebrig, entscheidet Uwe. */
            $zone = $kas->dnsLesen($domain, $als);
            if (!$zone['ok']) { return ['ok' => false, 'text' => 'KAS-Zone nicht lesbar: ' . $zone['text']]; }
            $alteMx = array_values(array_filter($eintraege, static fn($e) => $e['typ'] === 'MX'));
            $sonst = array_values(array_filter($eintraege, static fn($e) => $e['typ'] !== 'MX'));
            $kasMx = array_values(array_filter($zone['eintraege'], static fn($z) => $z['typ'] === 'MX'));
            foreach ($kasMx as $z) {
                if (!$z['aenderbar'] || $z['id'] === '' || !$alteMx) { $uebrigKas++; continue; }
                $ziel = array_shift($alteMx);
                $r = $kas->dnsAendern($z['id'], $ziel['daten'], $ziel['aux'], $als);
                if ($r['ok']) { $umgeschrieben++; } else { array_unshift($alteMx, $ziel); $uebrigKas++; }
            }
            $eintraege = array_merge($alteMx, $sonst);
        }
        $fehler = [];
        $gut = $umgeschrieben;
        foreach ($eintraege as $e) {
            $r = $kas->dnsHinzufuegen($domain, $e['typ'], $e['name'], $e['daten'], $e['aux'], $als);
            if ($r['ok']) { $gut++; } else { $fehler[] = $e['typ'] . ' ' . ($e['name'] ?: '@') . ': ' . $r['text']; }
        }
        if ($fehler) { return ['ok' => false, 'text' => $gut . ' übernommen, nicht: ' . implode(' · ', $fehler)]; }
        if ($uebrigKas > 0) {
            return ['ok' => true, 'hand' => true, 'text' => $gut . ' Einträge übernommen. Im KAS steht noch ' . $uebrigKas
                . ' eigener MX-Eintrag — bitte dort löschen, sonst geht ein Teil der Post ins Leere.'];
        }
        return ['ok' => true, 'text' => $gut . ' Einträge übernommen' . ($umgeschrieben ? ', davon ' . $umgeschrieben . ' KAS-MX umgeschrieben' : '') . ' — einmal im KAS ansehen.'];
    }

    /**
     * "info, Buchung; office@firma.it" -> ['info', 'buchung', 'office'].
     * Nur gueltige lokale Teile, ohne info (das ist das Postfach selbst),
     * hoechstens zehn.
     * @return list<string>
     */
    public static function weiterleitungen(string $roh): array
    {
        $aus = [];
        foreach (preg_split('~[\s,;]+~', mb_strtolower(trim($roh))) ?: [] as $t) {
            $t = trim((string) preg_replace('~@.*$~', '', $t), '.-_ ');
            if ($t === '' || $t === self::POSTFACH || !preg_match('~^[a-z0-9][a-z0-9._-]{0,39}$~', $t)) { continue; }
            $aus[$t] = true;
        }
        return array_slice(array_keys($aus), 0, 10);
    }

    /* ---------- Nach Vertragsende: sperren, nicht loeschen ---------- */

    /**
     * Hosting-Auftraege, deren Vertrag vorbei ist, deren KAS-Zugang aber noch
     * offen steht. Eigener Vertrag beendet -- oder, wenn das Hosting in der
     * Betreuung steckte, die Betreuung beendet. Solange irgendein passender
     * Vertrag laeuft, steht hier nichts.
     * @return list<array<string,mixed>>
     */
    public static function zumSperren(): array
    {
        return Db::all("SELECT h.*, COALESCE(NULLIF(c.company,''), NULLIF(c.name,''), c.email) AS wer
              FROM hosting_auftraege h JOIN customers c ON c.id = h.customer_id
             WHERE h.status IN ('angelegt','aktiv') AND h.kas_login IS NOT NULL AND h.gesperrt_am IS NULL
               AND EXISTS (SELECT 1 FROM abos a WHERE a.customer_id = h.customer_id AND a.status = 'beendet'
                            AND (a.paket_slug = 'hosting' OR h.inklusive = 1))
               AND NOT EXISTS (SELECT 1 FROM abos a JOIN packages p ON p.id = a.package_id
                                WHERE a.customer_id = h.customer_id AND a.status IN ('angelegt','aktiv','gekuendigt')
                                  AND (a.paket_slug = 'hosting' OR (h.inklusive = 1 AND p.art = 'betreuung')))");
    }

    /** Sperren (oder wieder oeffnen) -- auf Uwes Klick, mit Rueckfrage. */
    public static function zugangSperren(int $auftragId, bool $sperren = true, ?callable $kas = null): array
    {
        $a = Db::one('SELECT * FROM hosting_auftraege WHERE id = ? AND kas_login IS NOT NULL', [$auftragId]);
        if (!$a) { return ['ok' => false, 'text' => 'Kein KAS-Account an diesem Auftrag.']; }
        $r = $kas !== null ? $kas((string) $a['kas_login'], $sperren) : Kas::zugangSperren((string) $a['kas_login'], $sperren);
        if (!$r['ok']) { return $r; }
        Db::run('UPDATE hosting_auftraege SET gesperrt_am = ' . ($sperren ? 'NOW()' : 'NULL') . ' WHERE id = ?', [$auftragId]);
        Events::protokoll($sperren ? 'hosting_gesperrt' : 'hosting_entsperrt',
            'KAS-Zugang ' . $a['kas_login'] . ' (' . $a['domain'] . ') ' . ($sperren ? 'gesperrt' : 'wieder geöffnet'), (int) $a['customer_id']);
        return ['ok' => true, 'text' => $sperren ? 'Gesperrt. Account, Dateien und Domain bleiben — gelöscht wird nur von Hand im KAS.' : 'Wieder geöffnet.'];
    }

    /** Ab diesem Anteil am Speicher meldet sich die Verwaltung. */
    public const SPEICHER_WARNUNG = 0.9;

    /**
     * Der Speicher aller Kunden-Accounts -- einmal am Tag, ein Aufruf fuer alle
     * (get_space mit show_subaccounts). Ab 90 % gibt es EINE Meldung je Account
     * und Monat, nicht jeden Tag eine.
     *
     * @param callable():array|null $lesen austauschbar fuer die Pruefkette (Form wie Kas::speicherUnterkonten)
     * @return array{gelesen:int, gewarnt:int}
     */
    public static function speicherPruefen(?callable $lesen = null, ?callable $grenzen = null, ?callable $senden = null): array
    {
        $zuletzt = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'kas_speicher_am'", [], '');
        if ($lesen === null && $zuletzt !== '' && $zuletzt > date('Y-m-d H:i:s', strtotime('-20 hours'))) { return ['gelesen' => 0, 'gewarnt' => 0, 'abweichend' => 0]; }
        $r = $lesen !== null ? $lesen() : Kas::speicherUnterkonten();
        if (!$r['ok']) { return ['gelesen' => 0, 'gewarnt' => 0, 'abweichend' => 0]; }
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('kas_speicher', ?), ('kas_speicher_am', ?)
                  ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [json_encode($r['belegt']), date('Y-m-d H:i:s')]);
        /* Was im KAS eingerichtet ist (max_webspace je Account). Scheitert das
           Lesen, bleibt der letzte bekannte Stand -- geraten wird nichts. */
        $g = $grenzen !== null ? $grenzen() : Kas::accountGrenzen();
        $gewarnt = 0; $abweichend = 0;
        foreach (Db::all("SELECT h.*, COALESCE(NULLIF(c.company,''), NULLIF(c.name,''), c.email) AS wer
                            FROM hosting_auftraege h JOIN customers c ON c.id = h.customer_id
                           WHERE h.kas_login IS NOT NULL AND h.status IN ('angelegt','aktiv')") as $h) {
            $login = (string) $h['kas_login'];
            $soll = self::speicherVon($h);
            if ($g['ok'] && isset($g['grenzen'][$login])) {
                $ist = (int) $g['grenzen'][$login];
                Db::run('UPDATE hosting_auftraege SET kas_speicher_mb = ?, kas_gelesen_am = NOW() WHERE id = ?', [$ist, (int) $h['id']]);
                /* RESOURCE_MISMATCH: Vecom bleibt massgeblich. Gemeldet wird,
                   geschrieben wird nur auf Uwes Klick (speicherAufKas) -- und
                   nie andersherum: Ein KAS-Wert ueberschreibt keine Vereinbarung. */
                if ($ist !== $soll) {
                    $abweichend++;
                    $schl = 'speicher_abweichung_' . $login . '_' . $soll . '_' . $ist;
                    if ((string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$schl], '') === '') {
                        Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?)', [$schl, date('Y-m-d H:i:s')]);
                        Events::melden('hosting_abweichung', 'Speicher weicht ab: ' . $h['domain'], 'warnung',
                            $h['wer'] . ': vereinbart ' . self::gb($soll) . ', im KAS eingerichtet ' . self::gb($ist)
                            . '. Vecom ist maßgeblich — in der Kundenakte „KAS auf Vecom-Wert setzen“.', '/kunden/' . (int) $h['customer_id']);
                    }
                }
            }
            $mb = $r['belegt'][$login] ?? null;
            if ($mb === null || $mb < $soll * self::SPEICHER_WARNUNG) { continue; }
            $schluessel = 'speicher_warnung_' . $login . '_' . date('Y-m');
            if ((string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$schluessel], '') !== '') { continue; }
            Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?)', [$schluessel, (string) $mb]);
            Events::melden('hosting_speicher', 'Speicher fast voll: ' . $h['domain'], 'warnung',
                $h['wer'] . ' belegt ' . self::gb((int) $mb) . ' von ' . self::gb($soll)
                . '. Aufräumen (alte Mails, Sicherungen) oder mehr Speicher vereinbaren.', '/kunden/' . (int) $h['customer_id']);
            /* Und der Kunde selbst (26.09.2026, Uwe: ja) -- er ist es, der
               aufraeumen kann. Dieselbe Sperre: einmal im Monat. */
            self::still(static fn() => self::kundeSchreiben((int) $h['customer_id'], 'hosting_speicher_voll', [
                'domain' => (string) $h['domain'], 'belegt' => self::gb((int) $mb), 'gebucht' => self::gb($soll)], $senden));
            $gewarnt++;
        }
        return ['gelesen' => count($r['belegt']), 'gewarnt' => $gewarnt, 'abweichend' => $abweichend];
    }

    /** "10 GB Speicher, 4 Domains, 20 Subdomains …" -- fuers Protokoll und die Verwaltung. */
    public static function grenzenText(array $g): string
    {
        $namen = ['max_webspace' => null, 'max_domain' => 'Domains', 'max_subdomain' => 'Subdomains', 'max_mail_account' => 'Postfächer',
            'max_mail_forward' => 'Weiterleitungen', 'max_mailinglist' => 'Mailinglisten', 'max_database' => 'Datenbanken',
            'max_ftpuser' => 'FTP-Nutzer', 'max_cronjobs' => 'Cronjobs'];
        $aus = [];
        foreach ($namen as $k => $n) {
            if (!isset($g[$k])) { continue; }
            $aus[] = $n === null ? self::gb((int) $g[$k]) . ' Speicher' : (int) $g[$k] . ' ' . $n;
        }
        return implode(', ', $aus);
    }

    /**
     * Eine Mail an den Kunden eines Hosting-Auftrags, in seiner Sprache.
     * @param callable|null $senden wie Mail::senden -- austauschbar fuer die Pruefkette
     */
    private static function kundeSchreiben(int $kundeId, string $anlass, array $werte, ?callable $senden = null): bool
    {
        require_once __DIR__ . '/Texte.php';
        require_once __DIR__ . '/Mail.php';
        require_once __DIR__ . '/Kundenzugang.php';
        $k = Db::one('SELECT * FROM customers WHERE id = ?', [$kundeId]);
        if (!$k || trim((string) $k['email']) === '' || !empty($k['anonym_am'])) { return false; }
        $sp = in_array((string) $k['sprache'], ['it', 'de', 'en'], true) ? (string) $k['sprache'] : 'it';
        $werte += ['name' => (string) $k['name'], 'seite' => (string) Kundenzugang::linkFuer($kundeId)];
        if (isset($werte['zeilen']) && is_array($werte['zeilen'])) {
            $werte['zeilen'] = implode("\n", array_map(static fn(array $z): string => strtr((string) (Texte::BERICHT[$z[0]][$sp] ?? ''), $z[1]), $werte['zeilen']));
        }
        if (isset($werte['monat']) && preg_match('/^\d{4}-\d{2}$/', (string) $werte['monat'])) {
            require_once __DIR__ . '/Abo.php';
            $werte['monat'] = Abo::monatswort((string) $werte['monat'], $sp);
        }
        [$betreff, $text] = Texte::mail($anlass, $sp, $werte);
        $senden ??= [Mail::class, 'senden'];
        return (bool) $senden($anlass, (string) $k['email'], $betreff, $text, ['customer_id' => $kundeId, 'antwortAn' => Mail::eigeneAdresse()]);
    }

    /* ------------------------------------------------------------------ */
    /*  Monatsbericht (26.09.2026, Uwe: ja)                               */
    /* ------------------------------------------------------------------ */

    /**
     * Die gemessenen Zeilen eines Auftrags -- nur, was wirklich geprueft ist.
     * @return list<array{0:string,1:array<string,string>}>
     */
    public static function berichtZeilen(array $a): array
    {
        $z = [];
        $w = self::still(static fn() => Db::one("SELECT * FROM websites WHERE customer_id = ? AND monitoring = 1
                  ORDER BY (domain = ?) DESC, id DESC LIMIT 1", [(int) $a['customer_id'], (string) $a['domain']]), null);
        if ($w && !empty($w['last_ok_at']) && (string) $w['status'] === 'online'
            && strtotime((string) $w['last_ok_at']) > time() - 3 * 86400) {
            $z[] = ['online', ['{datum}' => Fmt::datum((string) $w['last_ok_at'])]];
        } elseif ($w && in_array((string) $w['status'], ['offline', 'fehler', 'ssl_problem', 'domain_problem'], true)) {
            $z[] = ['stoerung', []];
        }
        if ((string) ($a['ssl_status'] ?? '') === 'ok') {
            $bis = $w && !empty($w['ssl_expires_at']) ? (string) $w['ssl_expires_at']
                 : (preg_match('/bis (\d{4}-\d{2}-\d{2})/', (string) ($a['ssl_text'] ?? ''), $m) ? $m[1] : '');
            $z[] = $bis !== '' ? ['https_bis', ['{datum}' => Fmt::datum($bis)]]
                               : ['https', ['{datum}' => Fmt::datum((string) $a['ssl_geprueft_am'])]];
        }
        $mb = !empty($a['kas_login']) ? (self::speicher()[(string) $a['kas_login']] ?? null) : null;
        if ($mb !== null) { $z[] = ['speicher', ['{belegt}' => self::gb((int) $mb), '{gebucht}' => self::gb(self::speicherVon($a))]]; }
        return $z;
    }

    /**
     * Einmal im Monat an jeden laufenden Hosting-Kunden -- ab dem Ersten, fruehestens
     * 20 Tage nach dem Einrichten, nie an Gesperrte, und nur mit mindestens einer
     * gemessenen Zeile. Abschaltbar (Einstellung hosting_bericht = 0).
     * @param callable|null $senden wie Mail::senden
     */
    public static function berichteSenden(?callable $senden = null, ?string $monat = null): int
    {
        if ((string) self::still(static fn() => Db::wert("SELECT svalue FROM settings WHERE skey = 'hosting_bericht'", [], '1'), '1') === '0') { return 0; }
        $monat ??= date('Y-m');
        $n = 0;
        foreach (Db::all("SELECT * FROM hosting_auftraege WHERE status IN ('angelegt','aktiv') AND gesperrt_am IS NULL
                           AND (angelegt_am IS NULL OR angelegt_am < NOW() - INTERVAL 20 DAY) ORDER BY id") as $a) {
            if ($n >= 10) { break; }   // je Lauf hoechstens zehn -- der naechste Lauf macht weiter
            $schl = 'hosting_bericht_' . (int) $a['id'] . '_' . $monat;
            if ((string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$schl], '') !== '') { continue; }
            $zeilen = self::berichtZeilen($a);
            if (!$zeilen) { continue; }
            $ok = self::still(static fn() => self::kundeSchreiben((int) $a['customer_id'], 'hosting_bericht',
                ['domain' => (string) $a['domain'], 'monat' => $monat, 'zeilen' => $zeilen], $senden), false);
            if ($ok) {
                Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$schl, date('Y-m-d H:i:s')]);
                $n++;
            }
        }
        return $n;
    }

    /* ------------------------------------------------------------------ */
    /*  Neue Domain: Uwe bestellt, das System erkennt (26.09.2026, Weg B) */
    /* ------------------------------------------------------------------ */

    /** Die Nameserver von All-Inkl -- gemessen an vecom-design.it am 26.09.2026. */
    public const NAMESERVER = ['ns5.kasserver.com', 'ns6.kasserver.com'];

    /** Wartet dieser Auftrag darauf, dass Uwe die Domain bestellt? */
    public static function wartetAufBestellung(array $a): bool
    {
        return (string) ($a['domain_aktion'] ?? 'neu') === 'neu' && in_array((string) $a['status'], ['angelegt', 'aktiv'], true)
            && empty($a['domain_registriert_am']) && empty($a['gesperrt_am']);
    }

    /**
     * Ist die neue Domain registriert und zeigt auf All-Inkl? Dann: eintragen,
     * Uwe melden, HTTPS gleich pruefen, den Kunden benachrichtigen -- genau
     * einmal (der Eintrag wird beansprucht, bevor irgendetwas hinausgeht).
     *
     * Erkannt wird an den Nameservern, wie beim Umzug (Domainumzug::nachsehenAlle):
     * Solange die Domain frei ist, gibt es keine; zeigen alle auf kasserver.com,
     * ist sie registriert UND richtig eingerichtet.
     *
     * @param callable(string,int):array|null $dns wie dns_get_record
     * @param callable|null $senden wie Mail::senden
     * @param callable(string):array|null $https wie in httpsPruefen
     */
    public static function registrierungNachsehen(?callable $dns = null, ?callable $senden = null, ?callable $https = null, ?int $nur = null): int
    {
        $dns ??= static fn(string $h, int $t): array => (array) (@dns_get_record($h, $t) ?: []);
        $n = 0;
        $zeilen = $nur !== null ? Db::all('SELECT * FROM hosting_auftraege WHERE id = ?', [$nur])
            : Db::all("SELECT * FROM hosting_auftraege WHERE domain_aktion = 'neu' AND status IN ('angelegt','aktiv')
                        AND domain_registriert_am IS NULL AND gesperrt_am IS NULL ORDER BY id LIMIT 20");
        foreach ($zeilen as $a) {
            if (!self::wartetAufBestellung($a)) { continue; }
            $ns = array_map(static fn($r) => mb_strtolower(rtrim((string) ($r['target'] ?? ''), '.')), $dns((string) $a['domain'], DNS_NS));
            if (!$ns || array_filter($ns, static fn($x) => !str_ends_with($x, 'kasserver.com'))) { continue; }
            $meins = Db::run('UPDATE hosting_auftraege SET domain_registriert_am = NOW() WHERE id = ? AND domain_registriert_am IS NULL',
                [(int) $a['id']])->rowCount();
            if ($meins === 0) { continue; }
            $n++;
            Events::protokoll('domain_registriert', 'Domain ' . $a['domain'] . ' registriert — Nameserver zeigen auf All-Inkl', (int) $a['customer_id']);
            $h = self::still(static fn() => self::httpsPruefen((int) $a['id'], $https), ['status' => 'fehler', 'text' => '']);
            Events::melden('domain_fertig', 'Registriert: ' . $a['domain'], 'gut',
                'Die Domain ist da und zeigt auf All-Inkl. '
                . ((string) ($h['status'] ?? '') === 'ok' ? 'HTTPS steht bereits.'
                    : 'Jetzt im KAS den SSL-Schutz (Let\'s Encrypt) einschalten — die HTTPS-Prüfung läuft von selbst weiter und gibt „Online“ frei, sobald er greift.'),
                '/kunden/' . (int) $a['customer_id']);
            self::still(static fn() => self::kundeSchreiben((int) $a['customer_id'], 'domain_aktiv', ['domain' => (string) $a['domain']], $senden));
        }
        return $n;
    }

    /** MB als "7,4 GB" -- eine Nachkommastelle, ausser bei glatten Werten. */
    public static function gb(int $mb): string
    {
        $g = $mb / 1024;
        return (abs($g - round($g)) < 0.05 ? number_format($g, 0, ',', '.') : number_format($g, 1, ',', '.')) . ' GB';
    }

    /**
     * Uwe aendert, was mit einem Kunden vereinbart ist. Nur hier -- nie aus
     * einem KAS-Wert, nie aus einer neuen Vorgabe. Protokolliert alt -> neu.
     */
    public static function speicherAendern(int $auftragId, int $mb): array
    {
        $a = Db::one('SELECT * FROM hosting_auftraege WHERE id = ?', [$auftragId]);
        if (!$a) { return ['ok' => false, 'text' => 'Auftrag nicht gefunden.']; }
        if ($mb < 1024 || $mb > self::SPEICHER_MAX_MB) {
            return ['ok' => false, 'text' => 'Zwischen 1 und ' . (self::SPEICHER_MAX_MB / 1024) . ' GB.'];
        }
        $alt = self::speicherVon($a);
        if ($alt === $mb) { return ['ok' => true, 'text' => 'Unverändert.']; }
        Db::run('UPDATE hosting_auftraege SET speicher_mb = ? WHERE id = ?', [$mb, $auftragId]);
        Events::protokoll('hosting_speicher_vereinbart', 'Speicher für ' . $a['domain'] . ': ' . self::gb($alt) . ' → ' . self::gb($mb),
            (int) $a['customer_id']);
        return ['ok' => true, 'text' => 'Vereinbart: ' . self::gb($mb) . '.'
            . (!empty($a['kas_login']) ? ' Im KAS gilt das erst nach „KAS auf Vecom-Wert setzen“.' : '')];
    }

    /**
     * Den KAS auf den vereinbarten Wert bringen (update_account max_webspace).
     * @param callable(string,int):array|null $setzen austauschbar fuer die Pruefkette
     */
    public static function speicherAufKas(int $auftragId, ?callable $setzen = null): array
    {
        $a = Db::one('SELECT * FROM hosting_auftraege WHERE id = ? AND kas_login IS NOT NULL', [$auftragId]);
        if (!$a) { return ['ok' => false, 'text' => 'Kein KAS-Account an diesem Auftrag.']; }
        $soll = self::speicherVon($a);
        $r = $setzen !== null ? $setzen((string) $a['kas_login'], $soll) : Kas::speicherSetzen((string) $a['kas_login'], $soll);
        if (!$r['ok']) { return $r; }
        Db::run('UPDATE hosting_auftraege SET kas_speicher_mb = ?, kas_gelesen_am = NOW() WHERE id = ?', [$soll, $auftragId]);
        Events::protokoll('hosting_speicher_gesetzt', 'KAS ' . $a['kas_login'] . ' (' . $a['domain'] . '): Speicher auf ' . self::gb($soll)
            . ' gesetzt (wie vereinbart)', (int) $a['customer_id']);
        return ['ok' => true, 'text' => 'Im KAS stehen jetzt ' . self::gb($soll) . '.'];
    }

    /* ------------------------------------------------------------------ */
    /*  HTTPS -- keine Seite gilt als veroeffentlicht, bevor es steht     */
    /* ------------------------------------------------------------------ */

    /**
     * HTTPS der Domain pruefen: gueltiges Zertifikat (ueber den Abruf des
     * Monitorings, nicht noch einmal gebaut) und Umleitung von http auf https.
     *
     * @param callable(string):array|null $abrufen austauschbar: liefert
     *        ['https' => Monitoring::abrufen-Ergebnis, 'umleitung' => ?string]
     * @return array{status:string,text:string}
     */
    public static function httpsPruefen(int $auftragId, ?callable $abrufen = null): array
    {
        $a = Db::one('SELECT * FROM hosting_auftraege WHERE id = ?', [$auftragId]);
        if (!$a) { return ['status' => 'fehler', 'text' => 'Auftrag nicht gefunden.']; }
        $domain = strtolower(trim((string) $a['domain']));
        $e = $abrufen !== null ? $abrufen($domain) : self::httpsAbrufen($domain);
        $h = $e['https'];
        if (($h['ssl_gueltig'] ?? null) !== 1) {
            $st = 'fehler'; $txt = (string) ($h['fehler'] ?? 'Kein gültiges Zertifikat.');
        } elseif (!$h['ok']) {
            $st = 'warnung'; $txt = 'Zertifikat gültig, aber die Seite antwortet nicht sauber: ' . (string) ($h['fehler'] ?? '');
        } elseif (!str_starts_with(strtolower((string) ($e['umleitung'] ?? '')), 'https://')) {
            $st = 'warnung'; $txt = 'Zertifikat gültig' . (!empty($h['ssl_bis']) ? ' bis ' . $h['ssl_bis'] : '')
                . ', aber http:// leitet nicht auf https:// um (im KAS: SSL-Schutz → „HTTPS erzwingen“).';
        } else {
            $st = 'ok'; $txt = 'Gültig' . (!empty($h['ssl_bis']) ? ' bis ' . $h['ssl_bis'] : '') . ', http leitet auf https um.';
        }
        $vorher = (string) ($a['ssl_status'] ?? '');
        Db::run('UPDATE hosting_auftraege SET ssl_status = ?, ssl_text = ?, ssl_geprueft_am = NOW() WHERE id = ?',
            [$st, mb_substr($txt, 0, 255), $auftragId]);
        if ($st !== $vorher) {
            Events::protokoll('hosting_https', 'HTTPS ' . $domain . ': ' . $st . ' — ' . mb_substr($txt, 0, 200), (int) $a['customer_id']);
        }
        return ['status' => $st, 'text' => $txt];
    }

    /** Warum ein Projekt noch nicht "online" sein darf -- oder null. */
    public static function httpsSperre(int $projektId): ?string
    {
        $p = Db::one('SELECT customer_id FROM projects WHERE id = ?', [$projektId]);
        if (!$p) { return null; }
        $h = Db::one("SELECT domain, ssl_status, ssl_text FROM hosting_auftraege WHERE customer_id = ? AND status IN ('angelegt','aktiv')
                       ORDER BY id DESC LIMIT 1", [(int) $p['customer_id']]);
        if (!$h || (string) ($h['ssl_status'] ?? '') === 'ok') { return null; }
        return 'Noch nicht „Online“: HTTPS von ' . $h['domain'] . ' ist nicht bestätigt'
            . (!empty($h['ssl_text']) ? ' (' . $h['ssl_text'] . ')' : ' (noch nicht geprüft)')
            . '. In der Kundenakte „HTTPS prüfen“.';
    }

    /** Der echte Abruf: https:// mit Zertifikatspruefung, http:// ohne Folgen der Umleitung. */
    private static function httpsAbrufen(string $domain): array
    {
        require_once __DIR__ . '/Monitoring.php';
        if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
            return ['https' => ['ok' => false, 'ssl_gueltig' => null, 'ssl_bis' => null, 'fehler' => 'Keine gültige Domain.'], 'umleitung' => null];
        }
        $https = Monitoring::abrufen('https://' . $domain . '/');
        $ort = null;
        $ch = curl_init('http://' . $domain . '/');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_NOBODY => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_HEADER => true]);
        $kopf = (string) curl_exec($ch);
        curl_close($ch);
        if (preg_match('~^Location:\s*(\S+)~mi', $kopf, $m)) { $ort = $m[1]; }
        return ['https' => $https, 'umleitung' => $ort];
    }

    /**
     * Der Cron: alle angelegten Domains. Solange HTTPS nicht steht alle 6 h,
     * danach einmal am Tag (Zertifikate laufen ab).
     */
    public static function httpsPruefenAlle(?callable $abrufen = null): int
    {
        $ids = array_column(Db::all("SELECT id FROM hosting_auftraege
              WHERE status IN ('angelegt','aktiv') AND gesperrt_am IS NULL
                AND (ssl_geprueft_am IS NULL
                     OR (COALESCE(ssl_status,'') <> 'ok' AND ssl_geprueft_am < NOW() - INTERVAL 6 HOUR)
                     OR ssl_geprueft_am < NOW() - INTERVAL 1 DAY)
              LIMIT 10"), 'id');
        foreach ($ids as $id) { self::still(static fn() => self::httpsPruefen((int) $id, $abrufen)); }
        return count($ids);
    }

    /* ------------------------------------------------------------------ */
    /*  Probelauf                                                         */
    /* ------------------------------------------------------------------ */

    /** Was das Einrichten tun WUERDE -- ohne einen einzigen schreibenden Aufruf. */
    public static function plan(array $a): array
    {
        $d = (string) $a['domain'];
        $p = ['add_account (' . self::grenzenText(self::grenzenVon($a)) . ', wie vereinbart; max_webspace ' . self::speicherVon($a) . ')',
              'add_domain ' . $d];
        if ((string) ($a['mail'] ?? 'vecom') === 'vecom') {
            $p[] = 'add_mailaccount ' . self::POSTFACH . '@' . $d;
            foreach (self::weiterleitungen((string) ($a['weiterleitungen'] ?? '')) as $w) { $p[] = 'add_mailforward ' . $w . '@' . $d . ' → ' . self::POSTFACH . '@' . $d; }
        }
        if (!empty($a['mit_datenbank'])) { $p[] = 'add_database (vecom-' . (int) $a['id'] . '-db)'; }
        if (!empty($a['mit_ftp'])) { $p[] = 'add_ftpusers (vecom-' . (int) $a['id'] . '-ftp)'; }
        if ((string) ($a['domain_aktion'] ?? '') === 'transfer') { $p[] = 'add_dns_settings (Einträge vom alten Anbieter übernehmen)'; }
        return $p;
    }

    private static function probelaufMerken(array $a): array
    {
        if (!in_array((string) $a['status'], ['zugestimmt', 'in_arbeit'], true)) {
            return ['ok' => false, 'text' => 'Nur ein zugestimmter Auftrag wird angelegt (Stand: ' . $a['status'] . ').'];
        }
        if ((string) $a['status'] === 'zugestimmt') {
            Db::run('UPDATE hosting_auftraege SET probelauf_am = COALESCE(probelauf_am, NOW()) WHERE id = ?', [(int) $a['id']]);
        }
        Events::protokoll('kas_probelauf', 'Probelauf ' . $a['domain'] . ' — würde: ' . implode('; ', self::plan($a)), (int) $a['customer_id']);
        return ['ok' => false, 'probelauf' => true,
                'text' => 'Probelauf: nichts angelegt. Würde: ' . implode('; ', self::plan($a)) . '.'];
    }

    /** Belegter Speicher in MB je KAS-Login, wie zuletzt gelesen. */
    public static function speicher(): array
    {
        return json_decode((string) Db::wert("SELECT svalue FROM settings WHERE skey = 'kas_speicher'", [], ''), true) ?: [];
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
        /* Vom Probelauf angehaltene Auftraege: Sie waren fertig zum Anlegen,
           nur der Schalter stand. Ist er aus, laufen sie jetzt an. */
        if ($kas !== null || !Kas::probelauf()) {
            foreach (array_column(Db::all("SELECT id FROM hosting_auftraege WHERE status = 'zugestimmt' AND probelauf_am IS NOT NULL LIMIT 5"), 'id') as $pid) {
                Db::run('UPDATE hosting_auftraege SET probelauf_am = NULL WHERE id = ?', [(int) $pid]);
                self::still(static fn() => self::anlegen((int) $pid, $kas));
            }
        }
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
     * Datenbank- und FTP-Zugang fuer Uwe (Verwaltung, nie Kundenseite).
     * Jeder Blick wird protokolliert -- wer, wann, welcher Auftrag.
     * @return array<string,mixed>|null
     */
    public static function technikAbrufen(int $auftragId): ?array
    {
        $a = Db::one('SELECT * FROM hosting_auftraege WHERE id = ?', [$auftragId]);
        if (!$a || empty($a['technik_blob'])) { return null; }
        $t = self::entschluesseln((string) $a['technik_blob']);
        if ($t !== null) { Events::protokoll('hosting_technik_angesehen', 'DB/FTP-Zugang von ' . $a['domain'] . ' angesehen', (int) $a['customer_id']); }
        return $t;
    }

    /** DB/FTP an- oder abwaehlen -- wirkt beim naechsten Einrichten oder "Offene Schritte wiederholen". */
    public static function technikWunsch(int $auftragId, bool $db, bool $ftp): array
    {
        $a = Db::one('SELECT * FROM hosting_auftraege WHERE id = ?', [$auftragId]);
        if (!$a) { return ['ok' => false, 'text' => 'Auftrag nicht gefunden.']; }
        Db::run('UPDATE hosting_auftraege SET mit_datenbank = ?, mit_ftp = ? WHERE id = ?', [$db ? 1 : 0, $ftp ? 1 : 0, $auftragId]);
        /* Ein Schritt, der schon "entfaellt" war, bekommt eine neue Chance. */
        foreach (['datenbank' => $db, 'ftp' => $ftp] as $s => $ja) {
            if ($ja) { Db::run("UPDATE hosting_schritte SET status = 'fehler', versuche = 0, text = 'Neu gewünscht.'
                                 WHERE auftrag_id = ? AND schritt = ? AND status = 'entfaellt'", [$auftragId, $s]); }
        }
        return ['ok' => true, 'text' => 'Gemerkt' . (in_array((string) $a['status'], ['angelegt', 'aktiv', 'in_arbeit'], true)
            ? ' — angelegt wird mit „Offene Schritte wiederholen“.' : ' — wird beim Einrichten mit angelegt.')];
    }

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

    /** Phase 5: derselbe Tresor fuer den Auth-Code eines Domain-Umzugs. */
    public static function versiegeln(array $daten): ?string { return self::verschluesseln($daten); }

    /** @return array<string,string>|null */
    public static function entsiegeln(string $blob): ?array { return self::entschluesseln($blob); }

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
