<?php
declare(strict_types=1);

/**
 * Marketing-Ausbau der Partnerseite (28.09.2026, Uwe: Ja zu Heißer Kontakt,
 * Nachfass-Erinnerung, Zentrale Aktion, Branchen-Paketen, Mini-Kurs und
 * Meilensteinen; „Meine Kontakte“, Gutschein und Kundenstimmen-Bilder laufen
 * im Browser des Partners, partner-plus.js / partner-medien.js).
 *
 * DATEN: Hier entsteht nichts, was ein Betrieb nicht schon selbst ausgelöst
 * hat. Ein „heißer Kontakt“ ist ein Aufruf des Schnellchecks, den der Partner
 * ihm geschickt hat -- mehr wissen wir nicht (keine IP, kein Gerät). Die
 * Nachfass-Liste kennt nur Schnellchecks und Betriebe, die der Partner selbst
 * angeschrieben hat.
 *
 * ZEIT: Alle Vergleiche laufen in der Datenbank (NOW(), CURDATE()) -- PHP
 * und Datenbank können in verschiedenen Zeitzonen laufen (Kette 27.09.2026).
 */
final class PartnerMarketing
{
    /** Nach so vielen Tagen erinnert die Seite ans Nachhaken (Stufe 1, 2). */
    public const NACHFASS_TAGE = [1 => 3, 2 => 7];
    /** Älteres fällt aus der Nachfass-Liste (dann ist es kein Nachhaken mehr, sondern neu anschreiben). */
    public const NACHFASS_BIS_TAGE = 21;
    /** Höchstens ein „heißer“ Hinweis je Bericht in diesem Abstand. */
    public const HEISS_STUNDEN = 6;
    /** So lange steht ein Aufruf unter „Heiße Kontakte“. */
    public const HEISS_ZEIGEN_STUNDEN = 48;
    /** Kanal der Links aus den Branchen-Paketen. */
    public const KANAL_BRANCHE = 'branche';
    public const BRANCHEN = ['gastro', 'unterkunft', 'handwerk', 'laden', 'praxis'];
    public const KURS_TAGE = 7;
    /** Tage, die nur von Hand abgehakt werden (4: Kontakte liegen nur im Browser; 7: Nachhaken ist ein Gespräch). */
    public const KURS_HAND = [4, 7];
    public const MEILENSTEINE = ['profil', 'klick1', 'klick10', 'klick100', 'check5', 'anfrage1', 'kunde1', 'kunde5', 'geld1', 'kurs'];

    private static function still(callable $f, mixed $sonst): mixed
    {
        try { return $f(); } catch (Throwable $e) { error_log('PartnerMarketing: ' . $e->getMessage()); return $sonst; }
    }

    private static function t(string $k, string $sp): string
    {
        return Texte::h(Texte::PARTNER_PLUS[$k] ?? [], $sp);
    }

    private static function sprache(array $p): string
    {
        return in_array((string) ($p['sprache'] ?? ''), ['it', 'de', 'en'], true) ? (string) $p['sprache'] : 'it';
    }

    /* ==================================================================== */
    /*  Heißer Kontakt                                                      */
    /* ==================================================================== */

    /**
     * Aus check.php: ein Fremder hat den Bericht geöffnet (der Partner selbst
     * und Programme zählen nicht -- das entscheidet der Aufrufer). Zählt den
     * Aufruf, merkt die Zeit und schickt höchstens alle HEISS_STUNDEN einen
     * Hinweis aufs Handy.
     * @return bool ob ein Hinweis rausging
     */
    public static function checkAufruf(array $z): bool
    {
        $id = (int) ($z['id'] ?? 0);
        if ($id <= 0) { return false; }
        Db::run('UPDATE partner_checks SET aufrufe = aufrufe + 1, zuletzt_am = NOW() WHERE id = ?', [$id]);
        $neu = Db::run('UPDATE partner_checks SET heiss_am = NOW() WHERE id = ? AND (heiss_am IS NULL OR heiss_am < NOW() - INTERVAL '
            . self::HEISS_STUNDEN . ' HOUR)', [$id])->rowCount() > 0;
        if (!$neu) { return false; }
        $p = Db::one("SELECT * FROM partner WHERE id = ? AND status = 'aktiv'", [(int) $z['partner_id']]);
        if (!$p) { return false; }
        require_once __DIR__ . '/PartnerPost.php';
        $sp = self::sprache($p);
        self::still(static fn() => PartnerPost::push((int) $p['id'], strtr(self::t('hk_push_t', $sp), ['{host}' => (string) $z['host']]),
            self::t('hk_push_x', $sp), Partner::portalLink($p) . '#heiss'), 0);
        return true;
    }

    /**
     * Berichte, die in den letzten HEISS_ZEIGEN_STUNDEN von einem Fremden geöffnet wurden.
     * @return list<array{token:string, host:string, minuten:int, aufrufe:int}>
     */
    public static function heisse(int $partnerId): array
    {
        return array_map(static fn(array $z): array => ['token' => (string) $z['token'], 'host' => (string) $z['host'],
            'minuten' => max(0, (int) $z['minuten']), 'aufrufe' => (int) $z['aufrufe']],
            self::still(static fn() => Db::all('SELECT token, host, aufrufe, TIMESTAMPDIFF(MINUTE, zuletzt_am, NOW()) AS minuten FROM partner_checks
                 WHERE partner_id = ? AND zuletzt_am >= NOW() - INTERVAL ' . self::HEISS_ZEIGEN_STUNDEN . ' HOUR ORDER BY zuletzt_am DESC LIMIT 5', [$partnerId]), []));
    }

    /* ==================================================================== */
    /*  Nachfassen                                                          */
    /* ==================================================================== */

    /** Der Partner hat einen reservierten Betrieb angeschrieben (Klick in seiner Liste). Nur eigene, gültige Reservierungen. */
    public static function angeschrieben(int $partnerId, int $firmaId): bool
    {
        return Db::run('UPDATE partner_reservierungen SET angeschrieben_am = NOW(), nachfass = 0
                         WHERE partner_id = ? AND firma_id = ? AND bis >= CURDATE()', [$partnerId, $firmaId])->rowCount() > 0;
    }

    /**
     * Alles, was jetzt ein Nachhaken verdient: eigene Schnellchecks und
     * angeschriebene Betriebe, 3 bis 21 Tage alt, nicht als erledigt markiert.
     * @return list<array{art:string, id:int, titel:string, tage:int, stufe:int, gemeldet:int, aufrufe:int, telefon:string, land:string, token:string}>
     */
    public static function faellig(int $partnerId): array
    {
        [$t1, $t2] = [self::NACHFASS_TAGE[1], self::NACHFASS_TAGE[2]];
        $bis = self::NACHFASS_BIS_TAGE;
        $checks = self::still(static fn() => Db::all("SELECT id, token, host, aufrufe, nachfass, DATEDIFF(NOW(), created_at) AS tage FROM partner_checks
             WHERE partner_id = ? AND nachfass < 9 AND created_at <= NOW() - INTERVAL $t1 DAY AND created_at >= NOW() - INTERVAL $bis DAY", [$partnerId]), []);
        $firmen = self::still(static fn() => Db::all("SELECT f.id, f.name, f.telefon, f.land, r.nachfass, DATEDIFF(NOW(), r.angeschrieben_am) AS tage
             FROM partner_reservierungen r JOIN akq_firmen f ON f.id = r.firma_id
             WHERE r.partner_id = ? AND r.bis >= CURDATE() AND r.nachfass < 9 AND r.angeschrieben_am IS NOT NULL
               AND r.angeschrieben_am <= NOW() - INTERVAL $t1 DAY AND r.angeschrieben_am >= NOW() - INTERVAL $bis DAY", [$partnerId]), []);
        $stufe = static fn(int $tage): int => $tage >= $t2 ? 2 : 1;
        $aus = [];
        foreach ($checks as $c) {
            $aus[] = ['art' => 'check', 'id' => (int) $c['id'], 'titel' => (string) $c['host'], 'tage' => (int) $c['tage'], 'stufe' => $stufe((int) $c['tage']),
                      'gemeldet' => (int) $c['nachfass'], 'aufrufe' => (int) $c['aufrufe'], 'telefon' => '', 'land' => 'IT', 'token' => (string) $c['token']];
        }
        foreach ($firmen as $f) {
            $aus[] = ['art' => 'firma', 'id' => (int) $f['id'], 'titel' => (string) $f['name'], 'tage' => (int) $f['tage'], 'stufe' => $stufe((int) $f['tage']),
                      'gemeldet' => (int) $f['nachfass'], 'aufrufe' => 0, 'telefon' => trim((string) $f['telefon']), 'land' => (string) ($f['land'] ?: 'IT'), 'token' => ''];
        }
        usort($aus, static fn($a, $b) => [$b['aufrufe'] > 0, $a['tage']] <=> [$a['aufrufe'] > 0, $b['tage']]);
        return $aus;
    }

    /** Der fertige Nachfass-Text, in der Sprache des Betriebs (Italien → it) bzw. der gewählten. */
    public static function nachfassText(array $p, array $e, string $sprache): string
    {
        require_once __DIR__ . '/PartnerWerbung.php';
        require_once __DIR__ . '/PartnerCheck.php';
        $w = ['{name}' => Partner::anzeigeName($p), '{link}' => PartnerWerbung::link($p, $e['art'] === 'check' ? 'check' : 'anschreiben'),
              '{host}' => $e['titel'], '{firma}' => $e['titel'], '{check}' => $e['token'] !== '' ? PartnerCheck::link($e['token']) : ''];
        return strtr(self::t($e['art'] === 'check' ? 'nf_msg_check' : 'nf_msg_firma', $sprache), $w);
    }

    /** Sprache für den Text an einen Betrieb: Italien → Italienisch, deutschsprachige Länder → Deutsch, sonst Englisch. */
    public static function betriebSprache(string $land): string
    {
        $land = strtoupper($land ?: 'IT');
        return $land === 'IT' ? 'it' : (in_array($land, ['DE', 'AT', 'CH', 'LI'], true) ? 'de' : 'en');
    }

    /** Vom Partner als erledigt markiert. Nur Eigenes. */
    public static function erledigt(int $partnerId, string $art, int $id): bool
    {
        return match ($art) {
            'check' => Db::run('UPDATE partner_checks SET nachfass = 9 WHERE id = ? AND partner_id = ?', [$id, $partnerId])->rowCount() > 0,
            'firma' => Db::run('UPDATE partner_reservierungen SET nachfass = 9 WHERE firma_id = ? AND partner_id = ?', [$id, $partnerId])->rowCount() > 0,
            default => false,
        };
    }

    /**
     * Aus dem Lauf: je Partner höchstens EIN Hinweis, wenn eine neue Stufe
     * fällig ist. Nur tagsüber (9–20 Uhr) -- nachts klingelt kein Handy.
     * Erst vermerken, dann schicken: Ein hängender Push-Dienst löst keine Serie aus.
     * @return int verschickte Hinweise
     */
    public static function nachfassErinnern(?int $jetzt = null): int
    {
        $jetzt ??= time();
        $stunde = (int) date('G', $jetzt);
        if ($stunde < 9 || $stunde >= 20) { return 0; }
        require_once __DIR__ . '/PartnerPost.php';
        $n = 0;
        foreach (Db::all("SELECT p.* FROM partner p WHERE p.status = 'aktiv' AND EXISTS (SELECT 1 FROM partner_push pp WHERE pp.partner_id = p.id)") as $p) {
            $neu = array_values(array_filter(self::faellig((int) $p['id']), static fn($e) => $e['stufe'] > $e['gemeldet']));
            if (!$neu) { continue; }
            foreach ($neu as $e) {
                if ($e['art'] === 'check') { Db::run('UPDATE partner_checks SET nachfass = ? WHERE id = ? AND nachfass < 9', [$e['stufe'], $e['id']]); }
                else { Db::run('UPDATE partner_reservierungen SET nachfass = ? WHERE firma_id = ? AND partner_id = ? AND nachfass < 9', [$e['stufe'], $e['id'], (int) $p['id']]); }
            }
            $sp = self::sprache($p);
            if (self::still(static fn() => PartnerPost::push((int) $p['id'], strtr(self::t('nf_push_t', $sp), ['{n}' => (string) count($neu)]),
                self::t('nf_push_x', $sp), Partner::portalLink($p) . '#nachhaken'), 0) > 0) { $n++; }
        }
        return $n;
    }

    /* ==================================================================== */
    /*  Zentrale Aktion                                                     */
    /* ==================================================================== */

    /**
     * Die laufende Aktion, wenn es eine gibt: an, Text in mindestens einer
     * Sprache, Enddatum heute oder später (Datum in der Zeitzone der Seite --
     * ein Tagesdatum, keine Uhrzeit).
     * @return ?array{texte:array<string,string>, bis:string, tage:int}
     */
    public static function aktion(?int $jetzt = null): ?array
    {
        $roh = json_decode(Partner::einstellung('partner_aktion'), true);
        if (!is_array($roh) || empty($roh['an'])) { return null; }
        $bis = (string) ($roh['bis'] ?? '');
        if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $bis)) { return null; }
        $heute = date('Y-m-d', $jetzt ?? time());
        if ($bis < $heute) { return null; }
        $texte = array_filter(array_map(static fn($t) => trim((string) $t), (array) ($roh['texte'] ?? [])), static fn($t) => $t !== '');
        if (!$texte) { return null; }
        $tage = (int) round((strtotime($bis . ' 12:00') - strtotime($heute . ' 12:00')) / 86400) + 1;
        return ['texte' => $texte, 'bis' => $bis, 'tage' => $tage];
    }

    /** Text in dieser Sprache (sonst Italienisch, sonst irgendeine). */
    public static function aktionText(array $a, string $sprache): string
    {
        return (string) ($a['texte'][$sprache] ?? $a['texte']['it'] ?? reset($a['texte']));
    }

    /** „Noch 5 Tage (bis 31.10.)“ bzw. „Letzter Tag!“ */
    public static function aktionRest(array $a, string $sprache): string
    {
        if ($a['tage'] <= 1) { return self::t('ak_rest1', $sprache); }
        return strtr(self::t('ak_rest', $sprache), ['{n}' => (string) $a['tage'], '{datum}' => date('d.m.', strtotime($a['bis']))]);
    }

    /** Fertiger Beitrag zur Aktion mit dem Link des Partners (Kanal „aktion“). */
    public static function aktionBeitrag(array $p, array $a, string $sprache): string
    {
        require_once __DIR__ . '/PartnerWerbung.php';
        return self::aktionText($a, $sprache) . ' — ' . self::aktionRest($a, $sprache) . "\n\n" . PartnerWerbung::link($p, 'aktion');
    }

    /**
     * Verwaltung: Aktion speichern. Leerer Text in allen Sprachen oder „aus“ beendet sie.
     * @return string ok | datum | text
     */
    public static function aktionSpeichern(bool $an, string $bis, array $texte): string
    {
        $t = [];
        foreach (['it', 'de', 'en'] as $l) {
            $x = trim(mb_substr((string) ($texte[$l] ?? ''), 0, 240));
            if ($x !== '') { $t[$l] = $x; }
        }
        if ($an && !preg_match('~^\d{4}-\d{2}-\d{2}$~', $bis)) { return 'datum'; }
        if ($an && !$t) { return 'text'; }
        Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)',
            ['partner_aktion', json_encode(['an' => $an, 'bis' => $bis, 'texte' => $t], JSON_UNESCAPED_UNICODE)]);
        return 'ok';
    }

    /* ==================================================================== */
    /*  Branchen-Pakete                                                     */
    /* ==================================================================== */

    /**
     * Ein Paket mit eingesetztem Namen und Link.
     * @return array{name:string, warum:string, args:list<string>, satz:string, wa:string, post:string}
     */
    public static function branche(array $p, string $b, string $sprache): array
    {
        require_once __DIR__ . '/PartnerWerbung.php';
        $d = Texte::PARTNER_BRANCHEN[$b] ?? Texte::PARTNER_BRANCHEN['gastro'];
        $w = ['{name}' => Partner::anzeigeName($p), '{link}' => PartnerWerbung::link($p, self::KANAL_BRANCHE)];
        return ['name' => Texte::h(Texte::PARTNER_MEDIEN['motive'][$b]['name'] ?? [], $sprache, $b), 'warum' => Texte::h($d['warum'], $sprache),
                'args' => array_map(static fn(array $a) => Texte::h($a, $sprache), $d['args']), 'satz' => Texte::h($d['satz'], $sprache),
                'wa' => strtr(Texte::h($d['wa'], $sprache), $w), 'post' => strtr(Texte::h($d['post'], $sprache), $w)];
    }

    /* ==================================================================== */
    /*  Mini-Kurs „Ihr erster Kunde in 7 Tagen“                             */
    /* ==================================================================== */

    /**
     * Stand des Kurses. Beginnt beim ersten Aufruf (kurs_start wird gesetzt);
     * je Tag wird ein weiterer Schritt freigeschaltet. Erledigt ist, was die
     * Datenbank belegt -- oder von Hand abgehakt wurde (Tage 4 und 7).
     * @return array{tag:int, erledigt:array<int,bool>, n:int, fertig:bool}
     */
    public static function kurs(array $p, bool $starten = true): array
    {
        $id = (int) $p['id'];
        if ($starten && empty($p['kurs_start'])) {
            Db::run('UPDATE partner SET kurs_start = CURDATE() WHERE id = ? AND kurs_start IS NULL', [$id]);
        }
        $tag = (int) self::still(static fn() => Db::wert('SELECT DATEDIFF(CURDATE(), COALESCE(kurs_start, CURDATE())) + 1 FROM partner WHERE id = ?', [$id], 1), 1);
        $tag = max(1, min(self::KURS_TAGE, $tag));
        $hand = array_map('intval', array_filter(explode(',', (string) ($p['kurs_erledigt'] ?? ''))));
        $zahl = static fn(string $sql) => (int) self::still(static fn() => Db::wert($sql, [$id], 0), 0);
        $auto = [
            1 => !empty($p['foto_am']) && trim((string) ($p['profil_satz'] ?? '')) !== '',
            2 => !empty($p['seite_am']),
            3 => $zahl('SELECT COALESCE(SUM(anzahl),0) FROM partner_klicks WHERE partner_id = ?') > 0
                 || $zahl('SELECT COUNT(*) FROM partner_kanal_klicks WHERE partner_id = ?') > 0,
            4 => false,
            5 => $zahl('SELECT COUNT(*) FROM partner_checks WHERE partner_id = ?') > 0,
            6 => $zahl('SELECT COUNT(*) FROM partner_reservierungen WHERE partner_id = ?') > 0,
            7 => false,
        ];
        $e = [];
        for ($i = 1; $i <= self::KURS_TAGE; $i++) { $e[$i] = $auto[$i] || in_array($i, $hand, true); }
        $n = count(array_filter($e));
        return ['tag' => $tag, 'erledigt' => $e, 'n' => $n, 'fertig' => $n === self::KURS_TAGE];
    }

    /** Einen Tag von Hand abhaken (nur freigeschaltete). */
    public static function kursAbhaken(array $p, int $nr): bool
    {
        if ($nr < 1 || $nr > self::KURS_TAGE) { return false; }
        if ($nr > self::kurs($p)['tag']) { return false; }
        $hand = array_map('intval', array_filter(explode(',', (string) ($p['kurs_erledigt'] ?? ''))));
        $hand[] = $nr;
        $hand = array_values(array_unique($hand)); sort($hand);
        Db::run('UPDATE partner SET kurs_erledigt = ? WHERE id = ?', [implode(',', $hand), (int) $p['id']]);
        return true;
    }

    /**
     * Aus dem Lauf: einmal am Tag ab 9 Uhr ein Hinweis zum heutigen Kurstag,
     * wenn er noch offen ist. Nur in den ersten 7 Tagen.
     */
    public static function kursErinnern(?int $jetzt = null): int
    {
        $jetzt ??= time();
        if ((int) date('G', $jetzt) < 9 || (int) date('G', $jetzt) >= 20) { return 0; }
        require_once __DIR__ . '/PartnerPost.php';
        $n = 0;
        foreach (Db::all("SELECT p.* FROM partner p WHERE p.status = 'aktiv' AND p.kurs_start IS NOT NULL
                            AND p.kurs_start >= CURDATE() - INTERVAL " . (self::KURS_TAGE - 1) . " DAY
                            AND (p.kurs_push_am IS NULL OR p.kurs_push_am < CURDATE())
                            AND EXISTS (SELECT 1 FROM partner_push pp WHERE pp.partner_id = p.id)") as $p) {
            $k = self::kurs($p, false);
            Db::run('UPDATE partner SET kurs_push_am = CURDATE() WHERE id = ?', [(int) $p['id']]);
            if ($k['erledigt'][$k['tag']] || $k['fertig']) { continue; }
            $sp = self::sprache($p);
            $titel = Texte::h(Texte::PARTNER_PLUS['kurs'][$k['tag']]['titel'], $sp);
            if (self::still(static fn() => PartnerPost::push((int) $p['id'], strtr(self::t('ku_push', $sp), ['{n}' => (string) $k['tag'], '{titel}' => $titel]),
                self::t('ku_push_x', $sp), Partner::portalLink($p) . '#kurs'), 0) > 0) { $n++; }
        }
        return $n;
    }

    /* ==================================================================== */
    /*  Meilensteine                                                        */
    /* ==================================================================== */

    /** @return array<string,bool> alle Meilensteine, erreicht oder nicht */
    public static function meilensteine(array $p): array
    {
        $id = (int) $p['id'];
        $zahl = static fn(string $sql) => (int) self::still(static fn() => Db::wert($sql, [$id], 0), 0);
        $klicks = $zahl('SELECT COALESCE(SUM(anzahl),0) FROM partner_klicks WHERE partner_id = ?');
        $kunden = $zahl("SELECT COUNT(DISTINCT customer_id) FROM partner_provisionen WHERE partner_id = ? AND status NOT IN ('storniert','zurueckgeholt','rueckforderung')");
        return [
            'profil'   => !empty($p['foto_am']) && trim((string) ($p['profil_satz'] ?? '')) !== '' && !empty($p['seite_am']),
            'klick1'   => $klicks >= 1,
            'klick10'  => $klicks >= 10,
            'klick100' => $klicks >= 100,
            'check5'   => $zahl('SELECT COUNT(*) FROM partner_checks WHERE partner_id = ?') >= 5,
            'anfrage1' => $zahl('SELECT COUNT(*) FROM partner_zuordnungen WHERE partner_id = ?') >= 1,
            'kunde1'   => $kunden >= 1,
            'kunde5'   => $kunden >= 5,
            'geld1'    => $zahl("SELECT COUNT(*) FROM partner_auszahlungen WHERE partner_id = ? AND status IN ('erledigt','ausgezahlt')") >= 1,
            'kurs'     => self::kurs($p, false)['fertig'],
        ];
    }

    /**
     * Aus dem Lauf: neu erreichte Meilensteine melden. Beim allerersten
     * Durchlauf je Partner wird nur vermerkt -- sonst bekäme ein alter
     * Partner auf einen Schlag Hinweise für alles, was längst war.
     */
    public static function meilensteineMelden(): int
    {
        require_once __DIR__ . '/PartnerPost.php';
        $n = 0;
        foreach (Db::all("SELECT * FROM partner WHERE status = 'aktiv'") as $p) {
            $jetzt = array_keys(array_filter(self::meilensteine($p)));
            $vorher = $p['meilensteine'] === null ? null : array_filter(explode(',', (string) $p['meilensteine']));
            if ($vorher !== null && !array_diff($jetzt, $vorher)) { continue; }
            Db::run('UPDATE partner SET meilensteine = ? WHERE id = ?', [implode(',', $jetzt), (int) $p['id']]);
            if ($vorher === null) { continue; }
            $neu = array_values(array_diff($jetzt, $vorher));
            $bester = end($neu);                 // der höchste (Reihenfolge wie MEILENSTEINE)
            $sp = self::sprache($p);
            $titel = Texte::h(Texte::PARTNER_PLUS['meilensteine'][$bester] ?? [], $sp, $bester);
            if (self::still(static fn() => PartnerPost::push((int) $p['id'], strtr(self::t('ms_push_t', $sp), ['{titel}' => $titel]),
                self::t('ms_push_x', $sp), Partner::portalLink($p) . '#meilensteine'), 0) > 0) { $n++; }
        }
        return $n;
    }

    /** Alles aus dem Cronlauf (Partner::lauf). Jede Aufgabe fällt für sich, keine reißt die andere mit. */
    public static function lauf(): array
    {
        return ['nachfassen' => self::still(static fn() => self::nachfassErinnern(), 0),
                'kurs' => self::still(static fn() => self::kursErinnern(), 0),
                'meilensteine' => self::still(static fn() => self::meilensteineMelden(), 0)];
    }
}
