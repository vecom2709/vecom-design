<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';

/* ==========================================================================
   PartnerBesuche.php — wer auf der Seite eines Partners war (03.10.2026,
   Uwe: Ja zu K1 „Besucherliste je Klick“, K2 „Kontakt mit Einwilligung“,
   K3 „Link je Beitrag“, N1 „Sofort-Hinweis bei heißem Besuch“, N2
   „Rückruf-Termin wählen“).

   Was ein Partner sieht und was nicht:
   - Je Besuch: wann, über welche Plattform, über welchen Beitrag, Land und
     Region (lokal aus der IP bestimmt, die IP wird nie gespeichert), Gerät,
     Seiten, Dauer, was er getan hat — und daraus die Chance.
   - Kein Name, keine Nummer, kein Profil. Wer geklickt, geliked oder
     kommentiert hat, verraten die Plattformen nicht, und die DSGVO erlaubt
     es ohne Einwilligung auch nicht.
   - Kontaktdaten nur, wenn der Besucher sie selbst eingetragen UND mit einem
     eigenen Häkchen für den Partner freigegeben hat. Dann mit fertigem Text.
     Nach 90 Tagen weg.
   ========================================================================== */

final class PartnerBesuche
{
    public const AUFBEWAHREN_TAGE = 90;
    /** Ab so vielen Sekunden auf der Seite gilt ein Besuch als heiß (auch ohne Klick). */
    public const HEISS_SEKUNDEN = 120;
    /** Höchstens so viele Sofort-Hinweise je Partner und Tag. */
    public const HEISS_JE_TAG = 5;
    /** Ereignisse, die zeigen, dass jemand etwas will. */
    public const STARK = ['partner_weg', 'callback_requested', 'website_check_completed', 'appointment_requested', 'lead_created',
                          'price_calculator_started', 'price_calculator_completed', 'questionnaire_started', 'customer_created'];

    /** Plattform aus dem Kanal des Links, wenn die Quelle nichts sagt. */
    public const KANAL_NAMEN = ['weiter' => 'weiter', 'kontakte' => 'kontakte', 'check' => 'check', 'mappe' => 'mappe', 'brief' => 'brief',
                                'signatur' => 'signatur', 'website' => 'website', 'antwort' => 'antwort', 'anschreiben' => 'anschreiben'];

    private static function still(callable $f, mixed $sonst): mixed
    {
        try { return $f(); } catch (Throwable $e) { return $sonst; }
    }

    private static function w(string $k, string $sp): string
    {
        return Texte::h(Texte::PARTNER_BESUCHE[$k] ?? [], $sp);
    }

    /** Regionen aus der Geo-Datei (englisch) in der Sprache des Partners. Englisch bleibt, wie es ist. */
    public const REGIONEN = [
        'Sicily' => ['it' => 'Sicilia', 'de' => 'Sizilien'],
        'Lombardy' => ['it' => 'Lombardia', 'de' => 'Lombardei'],
        'Lazio' => ['it' => 'Lazio', 'de' => 'Latium'],
        'Calabria' => ['it' => 'Calabria', 'de' => 'Kalabrien'],
        'Tuscany' => ['it' => 'Toscana', 'de' => 'Toskana'],
        'Sardinia' => ['it' => 'Sardegna', 'de' => 'Sardinien'],
        'The Marches' => ['it' => 'Marche', 'de' => 'Marken'],
        'Abruzzo' => ['it' => 'Abruzzo', 'de' => 'Abruzzen'],
        'Basilicate' => ['it' => 'Basilicata', 'de' => 'Basilikata'],
        'Campania' => ['it' => 'Campania', 'de' => 'Kampanien'],
        'Piedmont' => ['it' => 'Piemonte', 'de' => 'Piemont'],
        'Apulia' => ['it' => 'Puglia', 'de' => 'Apulien'],
        'Liguria' => ['it' => 'Liguria', 'de' => 'Ligurien'],
        'Veneto' => ['it' => 'Veneto', 'de' => 'Venetien'],
        'Emilia-Romagna' => ['it' => 'Emilia-Romagna', 'de' => 'Emilia-Romagna'],
        'Trentino-Alto Adige' => ['it' => 'Trentino-Alto Adige', 'de' => 'Trentino-Südtirol'],
        'Umbria' => ['it' => 'Umbria', 'de' => 'Umbrien'],
        'Molise' => ['it' => 'Molise', 'de' => 'Molise'],
        'Friuli Venezia Giulia' => ['it' => 'Friuli Venezia Giulia', 'de' => 'Friaul-Julisch Venetien'],
        'Aosta Valley' => ['it' => 'Valle d\'Aosta', 'de' => 'Aostatal'],
        'Hesse' => ['it' => 'Assia', 'de' => 'Hessen'],
        'Hamburg' => ['it' => 'Amburgo', 'de' => 'Hamburg'],
        'Free and Hanseatic City of Hamburg' => ['it' => 'Amburgo', 'de' => 'Hamburg'],
        'Bavaria' => ['it' => 'Baviera', 'de' => 'Bayern'],
        'North Rhine-Westphalia' => ['it' => 'Renania Settentrionale-Vestfalia', 'de' => 'Nordrhein-Westfalen'],
        'State of Berlin' => ['it' => 'Berlino', 'de' => 'Berlin'],
        'Berlin' => ['it' => 'Berlino', 'de' => 'Berlin'],
        'Baden-Wurttemberg' => ['it' => 'Baden-Württemberg', 'de' => 'Baden-Württemberg'],
        'Baden-Württemberg' => ['it' => 'Baden-Württemberg', 'de' => 'Baden-Württemberg'],
        'Saxony' => ['it' => 'Sassonia', 'de' => 'Sachsen'],
        'Schleswig-Holstein' => ['it' => 'Schleswig-Holstein', 'de' => 'Schleswig-Holstein'],
        'Lower Saxony' => ['it' => 'Bassa Sassonia', 'de' => 'Niedersachsen'],
        'Bremen' => ['it' => 'Brema', 'de' => 'Bremen'],
        'Free Hanseatic City of Bremen' => ['it' => 'Brema', 'de' => 'Bremen'],
        'City state Bremen' => ['it' => 'Brema', 'de' => 'Bremen'],
        'Saarland' => ['it' => 'Saarland', 'de' => 'Saarland'],
        'Saxony-Anhalt' => ['it' => 'Sassonia-Anhalt', 'de' => 'Sachsen-Anhalt'],
        'Brandenburg' => ['it' => 'Brandeburgo', 'de' => 'Brandenburg'],
        'Thuringia' => ['it' => 'Turingia', 'de' => 'Thüringen'],
        'Rheinland-Pfalz' => ['it' => 'Renania-Palatinato', 'de' => 'Rheinland-Pfalz'],
        'Rhineland-Palatinate' => ['it' => 'Renania-Palatinato', 'de' => 'Rheinland-Pfalz'],
        'Mecklenburg-Vorpommern' => ['it' => 'Meclemburgo-Pomerania', 'de' => 'Mecklenburg-Vorpommern'],
    ];
    public const LAENDER = ['IT' => ['it' => 'Italia', 'de' => 'Italien', 'en' => 'Italy'], 'DE' => ['it' => 'Germania', 'de' => 'Deutschland', 'en' => 'Germany'],
                            'AT' => ['it' => 'Austria', 'de' => 'Österreich', 'en' => 'Austria'], 'CH' => ['it' => 'Svizzera', 'de' => 'Schweiz', 'en' => 'Switzerland'],
                            'FR' => ['it' => 'Francia', 'de' => 'Frankreich', 'en' => 'France'], 'GB' => ['it' => 'Regno Unito', 'de' => 'Großbritannien', 'en' => 'United Kingdom'],
                            'US' => ['it' => 'Stati Uniti', 'de' => 'USA', 'en' => 'USA'], 'NL' => ['it' => 'Paesi Bassi', 'de' => 'Niederlande', 'en' => 'Netherlands']];

    /** „Sizilien · Italien“ — die Stadt kennt die lokale Geo-Datei nicht (DB-IP Lite, nur Region). */
    public static function ort(string $land, string $region, string $sp): string
    {
        $r = $region !== '' ? (self::REGIONEN[$region][$sp] ?? $region) : '';
        $l = $land !== '' ? (self::LAENDER[$land][$sp] ?? $land) : '';
        return trim(implode(' · ', array_filter([$r, $l])));
    }

    /** Hoch, mittel oder niedrig — einfach genug, dass man es versteht. */
    public static function chance(int $sekunden, int $seiten, array $typen, bool $kontakt): string
    {
        if ($kontakt || array_intersect($typen, self::STARK) || ($sekunden >= self::HEISS_SEKUNDEN && $seiten >= 2)) { return 'hoch'; }
        if ($sekunden >= 45 || $seiten >= 2 || in_array('price_calculator_opened', $typen, true) || in_array('contact_form_opened', $typen, true)) { return 'mittel'; }
        return 'niedrig';
    }

    /** Welcher Beitrag den Besucher brachte — aus der Kennung hinter dem Kanal (K3). */
    public static function beitrag(?string $kanal, string $sp): ?string
    {
        if ($kanal === null || !preg_match('/^(kalender|beitrag|bild3d|video3d)-([a-z0-9]{1,8})$/', $kanal, $m)) { return null; }
        if ($m[1] === 'kalender' && preg_match('/^(\d{2})(\d{2})$/', $m[2], $d)) {
            return strtr(self::w('b_kalender', $sp), ['{datum}' => $d[2] . '.' . $d[1] . '.']);
        }
        if ($m[1] === 'beitrag' && ctype_digit($m[2])) {
            $t = (string) self::still(static fn() => Db::wert('SELECT titel FROM mk_inhalte WHERE id = ?', [(int) $m[2]], ''), '');
            return strtr(self::w('b_beitrag', $sp), ['{titel}' => $t !== '' ? '„' . mb_strimwidth($t, 0, 50, '…') . '“' : '#' . $m[2]]);
        }
        if (!ctype_digit($m[2]) || $m[1] === 'kalender' || $m[1] === 'beitrag') { return null; }   // Kennung passt nicht zum Kanal
        return self::w($m[1] === 'video3d' ? 'b_video3d' : 'b_bild3d', $sp);
    }

    /** Plattform in Worten: zuerst die Quelle des Besuchs, sonst der Kanal des Links, sonst „ohne Angabe“. */
    public static function plattform(string $quelle, ?string $kanal, string $sp): string
    {
        require_once __DIR__ . '/Spur.php';
        if ($quelle !== '' && !in_array($quelle, ['direkt', 'andere'], true)) { return Spur::quelleName($quelle); }
        $basis = $kanal !== null ? (Partner::kanalBasis($kanal) ?? $kanal) : '';
        if ($basis !== '' && isset(self::KANAL_NAMEN[$basis])) { return self::w('k_' . self::KANAL_NAMEN[$basis], $sp); }
        return self::w($quelle === 'andere' ? 'k_andere' : 'k_direkt', $sp);
    }

    /**
     * Die Besuche der letzten Tage, neueste zuerst, mit allem, was der Partner wissen darf.
     * @return list<array<string,mixed>>
     */
    public static function liste(array $p, string $sp, int $tage = 14, int $max = 60): array
    {
        $pid = (int) $p['id'];
        $zeilen = self::still(static fn() => Db::all("SELECT id, kanal, quelle, land, region, geraet, seiten, start_am, zuletzt_am,
                                                              TIMESTAMPDIFF(SECOND, start_am, zuletzt_am) AS sekunden
                                                         FROM spur_besuche WHERE partner_id = ? AND verdacht = 0 AND start_am >= NOW() - INTERVAL " . max(1, min(90, $tage)) . " DAY
                                                     ORDER BY start_am DESC LIMIT " . max(1, min(200, $max)), [$pid]), []);
        if (!$zeilen) { return []; }
        $ids = array_map(static fn($z) => (int) $z['id'], $zeilen);
        $typen = [];
        foreach (self::still(static fn() => Db::all('SELECT besuch_id, event_type, meta FROM spur_ereignisse WHERE besuch_id IN (' . implode(',', $ids) . ')'), []) as $e) {
            $t = (string) $e['event_type'];
            if ($t === 'partner_weg') { $m = json_decode((string) $e['meta'], true) ?: []; $t = 'weg_' . preg_replace('/[^a-z]/', '', (string) ($m['weg'] ?? '')); $typen[(int) $e['besuch_id']][] = 'partner_weg'; }
            $typen[(int) $e['besuch_id']][] = $t;
        }
        $kontakte = [];
        foreach (self::still(static fn() => Db::all('SELECT * FROM partner_kontaktfreigaben WHERE partner_id = ? AND besuch_id IN (' . implode(',', $ids) . ')', [$pid]), []) as $k) {
            $kontakte[(int) $k['besuch_id']] = $k;
        }
        $aus = [];
        foreach ($zeilen as $z) {
            $bid = (int) $z['id']; $ty = array_values(array_unique($typen[$bid] ?? []));
            $sek = max(0, (int) $z['sekunden']);
            $aus[] = ['id' => $bid, 'zeit' => (string) $z['start_am'], 'plattform' => self::plattform((string) $z['quelle'], $z['kanal'] !== null ? (string) $z['kanal'] : null, $sp),
                      'beitrag' => self::beitrag($z['kanal'] !== null ? (string) $z['kanal'] : null, $sp), 'land' => (string) $z['land'], 'region' => (string) $z['region'],
                      'ort' => self::ort((string) $z['land'], (string) $z['region'], $sp),
                      'geraet' => (string) $z['geraet'], 'seiten' => max(1, (int) $z['seiten']), 'sekunden' => $sek, 'typen' => $ty,
                      'taten' => self::taten($ty, $sp), 'kontakt' => $kontakte[$bid] ?? null,
                      'chance' => self::chance($sek, (int) $z['seiten'], $ty, isset($kontakte[$bid]))];
        }
        return $aus;
    }

    /** Was der Besucher getan hat, in Worten. @return list<string> */
    public static function taten(array $typen, string $sp): array
    {
        $aus = [];
        foreach (['weg_preis' => 't_preis', 'price_calculator_opened' => 't_preis', 'price_calculator_completed' => 't_rechner', 'weg_check' => 't_check', 'weg_analisi' => 't_check',
                  'website_check_completed' => 't_check_fertig', 'weg_termin' => 't_termin', 'appointment_requested' => 't_termin_fertig', 'weg_wa' => 't_wa',
                  'callback_requested' => 't_rueckruf', 'contact_form_opened' => 't_formular', 'lead_created' => 't_anfrage', 'customer_created' => 't_kunde'] as $t => $k) {
            if (in_array($t, $typen, true)) { $aus[self::w($k, $sp)] = true; }
        }
        return array_keys($aus);
    }

    /* ==================================================================== */
    /*  Kontakt mit Einwilligung (K2) und Rückruf-Termin (N2)               */
    /* ==================================================================== */

    /** @return int Kennung der Freigabe */
    public static function kontaktAnlegen(array $p, ?int $besuchId, string $name, ?string $telefon, ?string $email, ?string $von, ?string $bis, string $sprache, string $einwilligung): int
    {
        $id = (int) Db::insert('partner_kontaktfreigaben', ['partner_id' => (int) $p['id'], 'besuch_id' => $besuchId, 'name' => mb_substr(trim($name), 0, 80),
            'telefon' => $telefon !== null && $telefon !== '' ? mb_substr($telefon, 0, 24) : null, 'email' => $email !== null && $email !== '' ? mb_substr($email, 0, 190) : null,
            'wann_von' => $von, 'wann_bis' => $bis, 'sprache' => in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'it', 'einwilligung' => mb_substr($einwilligung, 0, 400)]);
        if ($besuchId !== null) {
            require_once __DIR__ . '/Spur.php';
            self::still(static fn() => Spur::ereignis('callback_requested', ['besuch' => Db::one('SELECT * FROM spur_besuche WHERE id = ?', [$besuchId]), 'meta' => ['freigabe' => 1]]), null);
        }
        return $id;
    }

    /** Offene Freigaben eines Partners, älteste Termine zuerst. @return list<array<string,mixed>> */
    public static function kontakte(int $pid): array
    {
        return self::still(static fn() => Db::all('SELECT * FROM partner_kontaktfreigaben WHERE partner_id = ? AND erledigt_am IS NULL
                                                   ORDER BY COALESCE(wann_von, created_at) LIMIT 50', [$pid]), []);
    }

    public static function erledigt(int $pid, int $id): bool
    {
        return Db::run('UPDATE partner_kontaktfreigaben SET erledigt_am = NOW() WHERE id = ? AND partner_id = ? AND erledigt_am IS NULL', [$id, $pid])->rowCount() === 1;
    }

    /** Der fertige Text an den Besucher — in SEINER Sprache, mit der Seite des Partners. */
    public static function text(array $p, array $k, string $wie = 'wa'): string
    {
        require_once __DIR__ . '/PartnerWerbung.php';
        $sp = in_array((string) $k['sprache'], ['it', 'de', 'en'], true) ? (string) $k['sprache'] : 'it';
        $vorname = trim((string) (preg_split('/\s+/u', trim((string) $k['name']))[0] ?? ''));
        return strtr(self::w($wie === 'mail' ? 'msg_mail' : 'msg_wa', $sp), ['{vorname}' => $vorname, '{name}' => Partner::anzeigeName($p),
            '{link}' => PartnerWerbung::link($p, 'antwort')]);
    }

    /** WhatsApp-Adresse mit fertigem Text, wenn die Nummer eine ist. */
    public static function waLink(array $p, array $k): ?string
    {
        $n = preg_replace('~\D~', '', (string) ($k['telefon'] ?? '')) ?? '';
        if (strlen($n) < 8) { return null; }
        return 'https://wa.me/' . $n . '?text=' . rawurlencode(self::text($p, $k, 'wa'));
    }

    /* ==================================================================== */
    /*  Sofort-Hinweis bei heißem Besuch (N1)                               */
    /* ==================================================================== */

    /**
     * Ist der Besuch heiß, bekommt der Partner einen Hinweis aufs Handy — einmal je Besuch,
     * höchstens HEISS_JE_TAG am Tag, nur mit eingeschaltetem Schalter, nie im Urlaub (PartnerPost::push).
     */
    public static function heissMelden(int $besuchId): bool
    {
        $b = self::still(static fn() => Db::one("SELECT b.*, TIMESTAMPDIFF(SECOND, b.start_am, b.zuletzt_am) AS sekunden FROM spur_besuche b
                                                 WHERE b.id = ? AND b.partner_id IS NOT NULL AND b.verdacht = 0 AND b.heiss_am IS NULL", [$besuchId]), null);
        if (!$b) { return false; }
        $typen = array_column(self::still(static fn() => Db::all('SELECT DISTINCT event_type FROM spur_ereignisse WHERE besuch_id = ?', [$besuchId]), []), 'event_type');
        $kontakt = (int) self::still(static fn() => Db::wert('SELECT COUNT(*) FROM partner_kontaktfreigaben WHERE besuch_id = ?', [$besuchId], 0), 0) > 0;
        if (self::chance((int) $b['sekunden'], (int) $b['seiten'], $typen, $kontakt) !== 'hoch') { return false; }
        $pid = (int) $b['partner_id'];
        // Erst vermerken, dann schicken — zwei gleichzeitige Pings melden nicht doppelt.
        if (Db::run('UPDATE spur_besuche SET heiss_am = NOW() WHERE id = ? AND heiss_am IS NULL', [$besuchId])->rowCount() !== 1) { return false; }
        require_once __DIR__ . '/PartnerAutomatik.php';
        if (!PartnerAutomatik::an($pid, 'heiss')) { return false; }
        if ((int) Db::wert('SELECT COUNT(*) FROM spur_besuche WHERE partner_id = ? AND heiss_am >= CURDATE()', [$pid], 0) > self::HEISS_JE_TAG) { return false; }
        $p = Partner::laden($pid);
        if (!$p) { return false; }
        require_once __DIR__ . '/PartnerPost.php';
        $sp = in_array((string) $p['sprache'], ['it', 'de', 'en'], true) ? (string) $p['sprache'] : 'it';
        $wo = self::ort((string) $b['land'], (string) $b['region'], $sp);
        $text = trim(implode(' · ', array_filter([$wo, self::beitrag($b['kanal'] !== null ? (string) $b['kanal'] : null, $sp), $kontakt ? self::w('push_kontakt', $sp) : ''])));
        return self::still(static fn() => PartnerPost::push($pid, strtr(self::w('push_titel', $sp), ['{plattform}' => self::plattform((string) $b['quelle'], $b['kanal'] !== null ? (string) $b['kanal'] : null, $sp)]),
            $text !== '' ? $text : self::w('push_text', $sp), Partner::portalLink($p) . '#besuche'), 0) > 0;
    }

    /** Kontaktfreigaben nach 90 Tagen löschen. */
    public static function aufraeumen(): int
    {
        return (int) self::still(static fn() => Db::run('DELETE FROM partner_kontaktfreigaben WHERE created_at < NOW() - INTERVAL ' . self::AUFBEWAHREN_TAGE . ' DAY')->rowCount(), 0);
    }
}
