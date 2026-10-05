<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Texte.php';

/* ==========================================================================
   PartnerCommand.php — das Command Center des Partners (Etappe 1b, 05.10.2026,
   Uwe: „Ja“ zu „das Command Center ist eine eigene schnelle Seite“).

   Der Partnerbereich ist über Jahre gewachsen: fünf Reiter, rund 40 Blöcke.
   Wer morgens hineinschaut, braucht davon drei Dinge — was heute dran ist,
   wie es läuft und wohin mit einem Tipp. Diese Seite zeigt genau das und
   lädt nichts von dem übrigen Bereich.

   NUR ECHTE ZAHLEN (Uwe: „keine Demo-Umsätze, keine Fake-Leads, keine
   erfundenen Conversions“). Jede Kennzahl kommt aus einer Tabelle, die es
   schon gibt; was nicht da ist, ist 0 und steht als 0 da. Die Empfehlung
   kommt aus denselben Daten wie „Heute zu tun“ (PartnerHeute) — keine zweite
   Wahrheit. Reicht es dafür nicht, sagt die Seite das.

   NUR DIE EIGENEN DATEN: Alles läuft über $p['id'] aus dem eigenen Schlüssel,
   nie über eine ID aus der Anfrage.

   Die Seite darf beim Ansehen nichts verändern (wie PartnerHeute) — gespeichert
   wird nur das Marketingprofil, und nur auf Klick.
   ========================================================================== */
final class PartnerCommand
{
    /** Schnellwege: Ziel → Anker im Partnerbereich (partner-reiter.js öffnet den Reiter). */
    public const SCHNELLWEGE = ['kunden' => 'recherche', 'anfragen' => 'werbung', 'lokal' => 'mc-start', 'social' => 'kalender', 'check' => 'schnellcheck'];
    /** Wohin die Empfehlung führt. */
    public const ANKER = ['kontakte' => 'besuche', 'heiss' => 'heiss', 'nachhaken' => 'nachhaken', 'anrufen' => 'anrufliste',
        'nachrichten' => 'nachrichten', 'zahlen' => 'mc-bestellungen', 'freigeben' => 'mc-designs', 'material' => 'mc-start',
        'anlass' => 'kalender', 'posten' => 'kalender', 'kampagne' => 'cc:kampagne-neu'];
    /** Was in PartnerHeute so dringend ist, dass es vor allem anderen kommt (Reihenfolge von dort). */
    public const DRINGEND = ['kontakte', 'heiss', 'nachhaken', 'anrufen', 'nachrichten'];
    public const WEGE = ['persoenlich', 'whatsapp', 'social', 'telefon', 'druck', 'email'];
    /** Profilziele: die ersten fünf Kampagnenziele — dieselben Schlüssel wie im Kampagnen-Assistenten. */
    public const PROFIL_ZIELE = ['neue_kunden', 'anfragen', 'bekanntheit', 'lokal', 'social'];
    public const HOECHSTENS_BRANCHEN = 3;
    /** Parameter eines schlichten Aufrufs: Link, App-Start, Sprachwahl, Werbe-Anhängsel (utm_*). */
    public const STARTSEITE_PARAMETER = ['t', 'lang', 'app', 'fbclid', 'gclid'];

    /**
     * Öffnet dieser Aufruf das Command Center? (Startseite live, 05.10.2026)
     * Ja bei „cc“ und bei einem schlichten Aufruf. Alles mit eigenem Parameter —
     * Downloads (beleg, druck, karte …), Stripe-Rückweg, Bestellung, Manifest und
     * „voll“ — bleibt im vollen Partnerbereich, ebenso jedes POST.
     */
    public static function startseite(string $methode, array $get): bool
    {
        if (isset($get['cc'])) { return true; }
        if (strtoupper($methode) !== 'GET' || isset($get['voll'])) { return false; }
        foreach (array_keys($get) as $k) {
            $k = (string) $k;
            if (!in_array($k, self::STARTSEITE_PARAMETER, true) && !str_starts_with($k, 'utm_')) { return false; }
        }
        return true;
    }

    /** Gruß nach Tageszeit: „morgen“ bis 11 Uhr, „abend“ ab 18 Uhr, sonst „tag“ (Zeitzone der Seite). */
    public static function gruss(?int $jetzt = null): string
    {
        $stunde = (int) date('G', $jetzt ?? time());
        return $stunde >= 4 && $stunde < 11 ? 'morgen' : ($stunde >= 18 || $stunde < 4 ? 'abend' : 'tag');
    }

    private static function still(callable $f, mixed $sonst): mixed
    {
        try { return $f(); } catch (Throwable $e) { return $sonst; }
    }

    /**
     * Die sechs Kennzahlen. Alles ganze Zahlen, Geld in Cent.
     * @return array{kampagnen:int, scans:int, leads:int, kunden:int, provision:int, provision_wartet:int,
     *               bestellungen:int, bestellungen_zahlung:int, klicks:int, designs:int, freigegeben:int}
     */
    public static function zahlen(array $p): array
    {
        require_once __DIR__ . '/PartnerHeute.php';
        $pid = (int) $p['id'];
        $w = static fn(string $sql, array $a = []): int => (int) self::still(static fn() => Db::wert($sql, $a ?: [$pid], 0), 0);
        $f = self::still(static fn() => PartnerHeute::fortschritt($p), ['verdient' => 0, 'wartet' => 0]);
        return [
            'kampagnen'    => $w("SELECT COUNT(*) FROM mk_kampagnen WHERE partner_id = ? AND status = 'aktiv'"),
            'scans'        => $w('SELECT COALESCE(SUM(scans), 0) FROM wm_entwuerfe WHERE partner_id = ?'),
            // Leads: wer über den Link bei Vecom ankam (Zuordnung) und wer seinen Kontakt für den Partner freigab.
            'leads'        => $w('SELECT (SELECT COUNT(*) FROM partner_zuordnungen WHERE partner_id = ?) + (SELECT COUNT(*) FROM partner_kontaktfreigaben WHERE partner_id = ?)', [$pid, $pid]),
            'kunden'       => $w("SELECT COUNT(DISTINCT customer_id) FROM partner_provisionen WHERE partner_id = ? AND status NOT IN ('storniert','abgelehnt','zurueckgeholt','rueckforderung')"),
            'provision'    => (int) $f['verdient'],
            'provision_wartet' => (int) $f['wartet'],
            'bestellungen' => $w("SELECT COUNT(*) FROM wm_bestellungen WHERE partner_id = ? AND status IN ('angefragt','offen','bezahlt','beim_drucker')"),
            'bestellungen_zahlung' => $w("SELECT COUNT(*) FROM wm_bestellungen WHERE partner_id = ? AND status IN ('angefragt','offen')"),
            'klicks'       => (int) self::still(static fn() => Partner::klicksImmer($pid), 0),
            'designs'      => $w("SELECT COUNT(*) FROM wm_entwuerfe WHERE partner_id = ? AND status = 'entwurf'"),
            'freigegeben'  => $w("SELECT COUNT(*) FROM wm_entwuerfe WHERE partner_id = ? AND status IN ('freigegeben','ersetzt')"),
        ];
    }

    /**
     * Lead-Center (Etappe 3): der Weg vom Kontakt zur Provision — nur Anzahlen, nie Namen (Vereinbarung:
     * der Partner sieht, DASS jemand kam und kaufte, nicht WER). Gezählt werden die über den Partner
     * zugeordneten Kunden je erreichter Stufe; Beispieldaten (demo) zählen nicht.
     * @return array{leads:int, gespraech:int, angebot:int, kunden:int, provision:int}
     */
    public static function trichter(array $p): array
    {
        $pid = (int) $p['id'];
        $w = static fn(string $sql): int => (int) self::still(static fn() => Db::wert($sql, [$pid], 0), 0);
        return [
            'leads'     => $w('SELECT COUNT(*) FROM partner_zuordnungen WHERE partner_id = ?'),
            'gespraech' => $w("SELECT COUNT(DISTINCT z.customer_id) FROM partner_zuordnungen z WHERE z.partner_id = ? AND (
                                 EXISTS (SELECT 1 FROM bedarf b WHERE b.customer_id = z.customer_id AND b.abgesendet_am IS NOT NULL AND COALESCE(b.demo, 0) = 0)
                              OR EXISTS (SELECT 1 FROM angebote a WHERE a.customer_id = z.customer_id AND COALESCE(a.demo, 0) = 0))"),
            'angebot'   => $w("SELECT COUNT(DISTINCT z.customer_id) FROM partner_zuordnungen z WHERE z.partner_id = ?
                                 AND EXISTS (SELECT 1 FROM angebote a WHERE a.customer_id = z.customer_id AND a.gesendet_am IS NOT NULL AND COALESCE(a.demo, 0) = 0)"),
            'kunden'    => $w("SELECT COUNT(DISTINCT customer_id) FROM partner_provisionen WHERE partner_id = ? AND status NOT IN ('storniert','abgelehnt','zurueckgeholt','rueckforderung')"),
            'provision' => (int) (self::still(static fn() => PartnerHeute::fortschritt($p), ['verdient' => 0])['verdient'] ?? 0),
        ];
    }

    /** Gibt es schon genug, um aus Zahlen etwas zu empfehlen? Ohne Besuch, Scan, Lead oder Material: nein. */
    public static function wenigDaten(array $z): bool
    {
        return $z['klicks'] === 0 && $z['scans'] === 0 && $z['leads'] === 0 && $z['freigegeben'] === 0 && $z['provision'] === 0;
    }

    /**
     * Genau eine Empfehlung für heute.
     * @param bool $mc Gibt es das Marketing Center für diesen Partner (Katalog nicht leer)?
     * @return array{k:string, n:int, titel:string, warum:string, knopf:string, anker:string, wenig:bool}
     */
    public static function empfehlung(array $p, string $sprache, array $z, bool $mc, ?int $jetzt = null): array
    {
        require_once __DIR__ . '/PartnerHeute.php';
        require_once __DIR__ . '/PartnerStart.php';
        require_once __DIR__ . '/PartnerKalender.php';
        $jetzt ??= time();
        $E = Texte::PARTNER_CC['e'];
        $t = static fn(array $x): string => Texte::h($x, $sprache);
        $aus = static function (string $k, int $n = 1, array $ersatz = [], bool $wenig = false) use ($E, $t): array {
            $titel = isset($E[$k]['titel'][0]) ? $E[$k]['titel'][$n === 1 ? 0 : 1] : $E[$k]['titel'];
            return ['k' => $k, 'n' => $n, 'titel' => strtr($t($titel), $ersatz + ['{n}' => (string) $n]),
                    'warum' => $t($E[$k]['warum']), 'knopf' => $t($E[$k]['knopf']), 'anker' => self::ANKER[$k], 'wenig' => $wenig];
        };

        // 1. Was Menschen gerade von dir wollen — dieselbe Reihenfolge wie „Heute zu tun“.
        $punkte = self::still(static fn() => PartnerHeute::punkte($p, $sprache), []);
        foreach ($punkte as $hp) {
            if (in_array($hp['k'], self::DRINGEND, true)) { return $aus($hp['k'], (int) $hp['n']); }
        }
        // 2. Geld, das schon unterwegs ist, und Material, das nur noch ein Ja braucht.
        if ($mc && $z['bestellungen_zahlung'] > 0) { return $aus('zahlen', $z['bestellungen_zahlung']); }
        if ($mc && $z['designs'] > 0) { return $aus('freigeben', $z['designs']); }

        // 3. Ganz am Anfang: der nächste der ersten Schritte — und die Seite sagt, warum.
        $wenig = self::wenigDaten($z);
        $st = self::still(static fn() => PartnerStart::schritte($p), ['naechster' => null]);
        if ($wenig && $st['naechster'] !== null && $st['naechster'] !== 'vereinbarung') {
            $sd = Texte::PARTNER_START['schritte'][$st['naechster']];
            return ['k' => 'start_' . $st['naechster'], 'n' => 1, 'titel' => $t($sd[0]), 'warum' => $t(Texte::PARTNER_CC['start_warum']),
                    'knopf' => $t(Texte::PARTNER_CC['los']), 'anker' => PartnerStart::ANKER[$st['naechster']], 'wenig' => true];
        }
        // 4. Noch nie eine Kampagne: der Assistent bündelt Link, Werbemittel und Texte (Etappe 2).
        if (!$wenig && (int) self::still(static fn() => Db::wert('SELECT COUNT(*) FROM mk_kampagnen WHERE partner_id = ?', [(int) $p['id']], 0), 0) === 0) {
            return $aus('kampagne');
        }
        // 5. Noch kein Werbemittel freigegeben: die erste Karte.
        if ($mc && $z['freigegeben'] === 0) { return $aus('material', 1, [], $wenig); }
        // 6. Ein Anlass in den nächsten sieben Tagen (Saison).
        $bald = self::still(static fn() => PartnerKalender::bald($jetzt, 7, PartnerKalender::region($p)), null);
        $name = is_array($bald) ? (Texte::PARTNER_KALENDER['anlaesse'][$bald['schluessel']]['titel'] ?? null) : null;
        if (is_array($bald) && $name) {
            return $aus('anlass', 1, ['{tage}' => (string) $bald['in'], '{anlass}' => $t($name)], $wenig);
        }
        // 7. Sonst: der Beitrag des Tages — den gibt es immer.
        $posten = null;
        foreach ($punkte as $hp) { if ($hp['k'] === 'posten') { $posten = $hp; } }
        return $aus('posten', 1, ['{titel}' => (string) ($posten['titel'] ?? '')], $wenig);
    }

    /* ---------------------------------------------------------------------
       Marketingprofil: Branchen (bis 3), Ort (= heimatort), Wege, Ziel.
       --------------------------------------------------------------------- */

    /** @return array{branchen:list<string>, wege:list<string>, ziel:string, ort:string, fertig:bool} */
    public static function profil(array $p): array
    {
        $j = json_decode((string) ($p['mk_profil'] ?? ''), true);
        $j = is_array($j) ? $j : [];
        require_once __DIR__ . '/PartnerKampagne.php';
        $b = array_values(array_intersect(array_map('strval', (array) ($j['branchen'] ?? [])), PartnerKampagne::BRANCHEN));
        $w = array_values(array_intersect(array_map('strval', (array) ($j['wege'] ?? [])), self::WEGE));
        $z = in_array((string) ($j['ziel'] ?? ''), self::PROFIL_ZIELE, true) ? (string) $j['ziel'] : '';
        $o = trim((string) ($p['heimatort'] ?? ''));
        return ['branchen' => $b, 'wege' => $w, 'ziel' => $z, 'ort' => $o, 'fertig' => $b && $w && $z !== '' && $o !== ''];
    }

    /**
     * Speichern aus dem Formular. Nur bekannte Schlüssel, höchstens drei Branchen.
     * @return string 'ok' | 'fehler'
     */
    public static function profilSpeichern(int $partnerId, array $d): string
    {
        require_once __DIR__ . '/PartnerKampagne.php';
        $b = array_slice(array_values(array_unique(array_intersect(array_map('strval', (array) ($d['branchen'] ?? [])), PartnerKampagne::BRANCHEN))), 0, self::HOECHSTENS_BRANCHEN);
        $w = array_values(array_unique(array_intersect(array_map('strval', (array) ($d['wege'] ?? [])), self::WEGE)));
        $z = in_array((string) ($d['ziel'] ?? ''), self::PROFIL_ZIELE, true) ? (string) $d['ziel'] : '';
        $o = trim(mb_substr(strip_tags((string) ($d['ort'] ?? '')), 0, 80));
        if (!$b || !$w || $z === '' || mb_strlen($o) < 2) { return 'fehler'; }
        Db::run('UPDATE partner SET mk_profil = ?, heimatort = ? WHERE id = ?',
            [json_encode(['branchen' => $b, 'wege' => $w, 'ziel' => $z, 'am' => date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE), $o, $partnerId]);
        return 'ok';
    }
}
