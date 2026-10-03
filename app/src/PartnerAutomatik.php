<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';

/* ==========================================================================
   PartnerAutomatik.php — was ein Partner automatisch laufen lassen kann
   (03.10.2026, Uwe: „Gebe den Partnern auch Möglichkeiten, Automatisierungen
   zu setzen“ — Ja zu acht Vorschlägen).

   Jeder Schalter gilt nur für den Partner selbst und lässt nichts das Haus
   verlassen, was er nicht ohnehin von Hand täte: Hinweise auf sein eigenes
   Handy, ein Website-Check, den er sonst selbst anstieße, ein Kalender, den
   nur er abonniert. Kein Schalter schreibt in seinem Namen an Dritte.

   - medien:        neue freigegebene Videos/Bilder von Vecom sofort aufs Handy
   - wochenpaket:   montags das Werbepaket der Woche (PartnerPost::wochenImpuls)
   - autopilot:     morgens Betriebe zum Vorbeigehen — Anzahl und Uhrzeit wählbar
   - nachfass:      Erinnerung, wenn ein Kontakt nach 3 bzw. 7 Tagen nachgehakt werden will
   - check:         bei jeder Reservierung mit Website den Check von selbst anlegen
   - wochenbericht: freitags die Woche in einem Satz
   - kalender:      Rückrufe und Nachhaken als Kalender-Abo (.ics)
   - ruhe_bis:      Urlaubsmodus — bis zu diesem Tag kein Hinweis und keine Tagesliste
   ========================================================================== */

final class PartnerAutomatik
{
    /** Schalter und ihr Stand ab Werk. Der Kalender ist aus, bis der Partner ihn will. */
    public const SCHALTER = ['medien' => true, 'wochenpaket' => true, 'autopilot' => true, 'nachfass' => true,
                             'check' => true, 'wochenbericht' => true, 'heiss' => true, 'kalender' => false];
    public const ANZAHL = [3, 5, 10];
    public const STUNDEN = [7, 8, 9, 10];
    /** Höchstens so viele automatische Checks je Cronlauf — jeder ruft eine fremde Website ab. */
    public const CHECKS_JE_LAUF = 3;
    /** Längster Urlaub, den man auf einmal einstellen kann. */
    public const RUHE_MAX_TAGE = 60;

    /** @var array<int,array<string,mixed>> */
    private static array $cache = [];

    private static function still(callable $f, mixed $sonst): mixed
    {
        try { return $f(); } catch (Throwable $e) { return $sonst; }
    }

    /** @return array<string,mixed> Schalter + anzahl + stunde + ruhe_bis */
    public static function einstellungen(int $pid): array
    {
        if (isset(self::$cache[$pid])) { return self::$cache[$pid]; }
        $roh = self::still(static fn() => Db::wert('SELECT einstellungen FROM partner_automatik WHERE partner_id = ?', [$pid], null), null);
        $j = is_string($roh) ? (json_decode($roh, true) ?: []) : [];
        $e = self::SCHALTER + ['anzahl' => 5, 'stunde' => 8, 'ruhe_bis' => null];
        foreach (self::SCHALTER as $k => $_) { if (array_key_exists($k, $j)) { $e[$k] = (bool) $j[$k]; } }
        if (in_array((int) ($j['anzahl'] ?? 0), self::ANZAHL, true)) { $e['anzahl'] = (int) $j['anzahl']; }
        if (in_array((int) ($j['stunde'] ?? 0), self::STUNDEN, true)) { $e['stunde'] = (int) $j['stunde']; }
        if (is_string($j['ruhe_bis'] ?? null) && preg_match('~^\d{4}-\d{2}-\d{2}$~', $j['ruhe_bis'])) { $e['ruhe_bis'] = $j['ruhe_bis']; }
        return self::$cache[$pid] = $e;
    }

    public static function an(int $pid, string $schalter): bool
    {
        return (bool) (self::einstellungen($pid)[$schalter] ?? false);
    }

    /** Urlaubsmodus: bis einschließlich ruhe_bis still. */
    public static function ruhig(int $pid, ?string $heute = null): bool
    {
        $bis = self::einstellungen($pid)['ruhe_bis'];
        return $bis !== null && $bis >= ($heute ?? date('Y-m-d'));
    }

    /**
     * Aus dem Formular. Unbekannte Felder werden ignoriert, Werte außerhalb
     * der Auswahl fallen auf den bisherigen Stand zurück.
     */
    public static function speichern(int $pid, array $post): void
    {
        $alt = self::einstellungen($pid);
        $neu = [];
        foreach (self::SCHALTER as $k => $_) { $neu[$k] = !empty($post[$k]); }
        $neu['anzahl'] = in_array((int) ($post['anzahl'] ?? 0), self::ANZAHL, true) ? (int) $post['anzahl'] : $alt['anzahl'];
        $neu['stunde'] = in_array((int) ($post['stunde'] ?? 0), self::STUNDEN, true) ? (int) $post['stunde'] : $alt['stunde'];
        $bis = (string) ($post['ruhe_bis'] ?? '');
        $neu['ruhe_bis'] = null;
        if (!empty($post['ruhe']) && preg_match('~^\d{4}-\d{2}-\d{2}$~', $bis) && $bis >= date('Y-m-d')) {
            $neu['ruhe_bis'] = min($bis, date('Y-m-d', strtotime('+' . self::RUHE_MAX_TAGE . ' days')));
        }
        // Was der Cronlauf sich merkt (check_versucht), bleibt erhalten.
        $roh = self::still(static fn() => Db::wert('SELECT einstellungen FROM partner_automatik WHERE partner_id = ?', [$pid], null), null);
        $j = is_string($roh) ? (json_decode($roh, true) ?: []) : [];
        Db::run('INSERT INTO partner_automatik (partner_id, einstellungen) VALUES (?, ?) ON DUPLICATE KEY UPDATE einstellungen = VALUES(einstellungen)',
            [$pid, json_encode(array_merge($j, $neu))]);
        unset(self::$cache[$pid]);
        if ($neu['kalender']) { self::icsToken($pid); }
        try { Events::pruefspur('partner_automatik', 'partner', $pid, $alt, $neu); } catch (Throwable $e) { }
    }

    /** Eigener Schlüssel fürs Kalender-Abo (nie der Login-Schlüssel). */
    public static function icsToken(int $pid): string
    {
        $t = (string) self::still(static fn() => Db::wert('SELECT ics_token FROM partner_automatik WHERE partner_id = ?', [$pid], ''), '');
        if (preg_match('~^[a-f0-9]{32}$~', $t)) { return $t; }
        $t = bin2hex(random_bytes(16));
        Db::run('INSERT INTO partner_automatik (partner_id, ics_token) VALUES (?, ?) ON DUPLICATE KEY UPDATE ics_token = VALUES(ics_token)', [$pid, $t]);
        return $t;
    }

    public static function icsLink(int $pid): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/partner-kalender.php?k=' . self::icsToken($pid);
    }

    /* ==================================================================== */
    /*  Kalender-Abo                                                        */
    /* ==================================================================== */

    private static function icsText(string $t): string
    {
        return str_replace(["\\", ";", ",", "\r", "\n"], ["\\\\", "\\;", "\\,", '', "\\n"], $t);
    }

    /**
     * Rückrufe aus der Anrufliste und fällige Nachhaken als Ganztagstermine.
     * Nur Betriebsdaten, die der Partner im Dashboard ohnehin sieht — keine
     * Kundendaten, kein Link mit seinem Zugang.
     * Null, wenn der Schlüssel nicht passt oder der Kalender aus ist.
     */
    public static function ics(string $token): ?string
    {
        if (!preg_match('~^[a-f0-9]{32}$~', $token)) { return null; }
        $z = self::still(static fn() => Db::one("SELECT a.partner_id, p.sprache FROM partner_automatik a JOIN partner p ON p.id = a.partner_id
                                                  WHERE a.ics_token = ? AND p.status = 'aktiv'", [$token]), null);
        if (!$z || !self::an((int) $z['partner_id'], 'kalender')) { return null; }
        $pid = (int) $z['partner_id'];
        $sp = in_array((string) $z['sprache'], ['it', 'de', 'en'], true) ? (string) $z['sprache'] : 'it';
        $W = static fn(string $k): string => Texte::h(Texte::PARTNER_AUTOMATIK['ics'][$k], $sp);
        $termine = [];
        foreach (Db::all("SELECT f.id, f.name, f.telefon, r.naechster_versuch AS tag FROM partner_reservierungen r JOIN akq_firmen f ON f.id = r.firma_id
                           WHERE r.partner_id = ? AND r.herkunft = 'vecom' AND r.anruf_status = 'nicht_erreicht' AND r.naechster_versuch IS NOT NULL
                             AND r.bis >= CURDATE() AND f.gesperrt = 0", [$pid]) as $f) {
            $termine[] = ['uid' => 'rueckruf-' . (int) $f['id'] . '-' . $f['tag'], 'tag' => (string) $f['tag'],
                          'titel' => strtr($W('rueckruf'), ['{name}' => (string) $f['name']]), 'text' => trim((string) $f['telefon'])];
        }
        require_once __DIR__ . '/PartnerMarketing.php';
        [$t1, $t2] = [PartnerMarketing::NACHFASS_TAGE[1], PartnerMarketing::NACHFASS_TAGE[2]];
        foreach (Db::all("SELECT f.id, f.name, f.telefon, r.nachfass, DATE(r.angeschrieben_am) AS am FROM partner_reservierungen r JOIN akq_firmen f ON f.id = r.firma_id
                           WHERE r.partner_id = ? AND r.bis >= CURDATE() AND r.angeschrieben_am IS NOT NULL AND r.nachfass < 2 AND f.gesperrt = 0", [$pid]) as $f) {
            $tage = (int) $f['nachfass'] >= 1 ? $t2 : $t1;
            $termine[] = ['uid' => 'nachhaken-' . (int) $f['id'] . '-' . $tage, 'tag' => date('Y-m-d', strtotime((string) $f['am'] . " +$tage days")),
                          'titel' => strtr($W('nachhaken'), ['{name}' => (string) $f['name']]), 'text' => trim((string) $f['telefon'])];
        }
        /* Rückruf-Termin mit Freigabe (03.10.2026, N2): zur gewählten Zeit, mit Nummer — nur was der Besucher selbst freigegeben hat. */
        foreach (Db::all('SELECT id, name, telefon, email, wann_von, wann_bis FROM partner_kontaktfreigaben
                           WHERE partner_id = ? AND erledigt_am IS NULL AND wann_von IS NOT NULL AND wann_von >= CURDATE() - INTERVAL 1 DAY', [$pid]) as $k) {
            $termine[] = ['uid' => 'freigabe-' . (int) $k['id'], 'tag' => substr((string) $k['wann_von'], 0, 10), 'von' => (string) $k['wann_von'], 'bis' => (string) ($k['wann_bis'] ?: $k['wann_von']),
                          'titel' => strtr($W('rueckruf'), ['{name}' => (string) $k['name']]), 'text' => trim((string) ($k['telefon'] ?? '') . ' ' . (string) ($k['email'] ?? ''))];
        }
        $host = (string) parse_url((string) Config::get('website', 'https://vecom-design.it'), PHP_URL_HOST);
        $z = ["BEGIN:VCALENDAR", "VERSION:2.0", "PRODID:-//Vecom Design//Partner//" . strtoupper($sp), "CALSCALE:GREGORIAN", "METHOD:PUBLISH",
              "X-WR-CALNAME:" . self::icsText($W('name')), "REFRESH-INTERVAL;VALUE=DURATION:PT6H"];
        $jetzt = gmdate('Ymd\THis\Z');
        foreach ($termine as $t) {
            $tag = max($t['tag'], date('Y-m-d'));   // Überfälliges steht heute, nicht in der Vergangenheit
            $z[] = 'BEGIN:VEVENT';
            $z[] = 'UID:' . $t['uid'] . '@' . ($host ?: 'vecom-design.it');
            $z[] = 'DTSTAMP:' . $jetzt;
            if (!empty($t['von'])) {   // mit Uhrzeit (Ortszeit des Partners, „schwebend“ — der Kalender nimmt seine Zone)
                $z[] = 'DTSTART:' . date('Ymd\THis', strtotime($t['von']));
                $z[] = 'DTEND:' . date('Ymd\THis', max(strtotime($t['bis']), strtotime($t['von']) + 1800));
            } else {
                $z[] = 'DTSTART;VALUE=DATE:' . str_replace('-', '', $tag);
                $z[] = 'DTEND;VALUE=DATE:' . date('Ymd', strtotime($tag . ' +1 day'));
            }
            $z[] = 'SUMMARY:' . self::icsText($t['titel']);
            if ($t['text'] !== '') { $z[] = 'DESCRIPTION:' . self::icsText($t['text']); }
            $z[] = 'END:VEVENT';
        }
        $z[] = 'END:VCALENDAR';
        // RFC 5545: Zeilen über 75 Byte werden gefaltet.
        // Nie mitten in einem Umlaut trennen: mb_strcut schneidet an Zeichengrenzen.
        $falten = static function (string $l): string {
            $aus = '';
            while (strlen($l) > 75) { $t = mb_strcut($l, 0, 74, 'UTF-8'); $aus .= $t . "\r\n "; $l = substr($l, strlen($t)); }
            return $aus . $l;
        };
        return implode("\r\n", array_map($falten, $z)) . "\r\n";
    }

    /* ==================================================================== */
    /*  Hinweise                                                            */
    /* ==================================================================== */

    /**
     * Ein Medium wurde für Partner freigegeben (Verwaltung › Freigeben):
     * Galerie → alle aktiven Partner mit Schalter „medien“, eigener Wunsch →
     * nur der Partner, der ihn bestellt hat.
     */
    public static function medienMelden(int $medienId): int
    {
        $m = self::still(static fn() => Db::one("SELECT * FROM mk_medien WHERE id = ? AND inhalt_id = 0 AND status = 'gewaehlt'", [$medienId]), null);
        if (!$m) { return 0; }
        require_once __DIR__ . '/PartnerPost.php';
        require_once __DIR__ . '/PartnerSchutz.php';
        $wer = !empty($m['partner_id'])
            ? Db::all("SELECT p.* FROM partner p WHERE p.id = ? AND p.status = 'aktiv'", [(int) $m['partner_id']])
            : Db::all("SELECT p.* FROM partner p WHERE p.status = 'aktiv' AND " . PartnerSchutz::sqlFrei('p') . "
                         AND EXISTS (SELECT 1 FROM partner_push pp WHERE pp.partner_id = p.id) LIMIT 500");
        $n = 0;
        foreach ($wer as $p) {
            if (!self::an((int) $p['id'], 'medien')) { continue; }
            $sp = in_array((string) $p['sprache'], ['it', 'de', 'en'], true) ? (string) $p['sprache'] : 'it';
            $A = Texte::PARTNER_AUTOMATIK['push'];
            $titel = Texte::h($A[(string) $m['art'] === 'video' ? 'medien_video' : 'medien_bild'], $sp);
            if (self::still(static fn() => PartnerPost::push((int) $p['id'], $titel, Texte::h($A['medien_text'], $sp), Partner::portalLink($p) . '#galerie3d'), 0) > 0) { $n++; }
        }
        return $n;
    }

    /** Freitags ab 17 Uhr die Woche in einem Satz — höchstens einmal je Woche. */
    public static function wochenbericht(?int $jetzt = null): int
    {
        $jetzt ??= time();
        if ((int) date('N', $jetzt) !== 5 || (int) date('G', $jetzt) < 17 || (int) date('G', $jetzt) >= 21) { return 0; }
        require_once __DIR__ . '/PartnerPost.php';
        require_once __DIR__ . '/PartnerSchutz.php';
        $von = date('Y-m-d', strtotime('-6 days', $jetzt));
        $n = 0;
        foreach (Db::all("SELECT p.* FROM partner p LEFT JOIN partner_automatik a ON a.partner_id = p.id
                           WHERE p.status = 'aktiv' AND " . PartnerSchutz::sqlFrei('p') . "
                             AND EXISTS (SELECT 1 FROM partner_push pp WHERE pp.partner_id = p.id)
                             AND (a.bericht_am IS NULL OR a.bericht_am < ?) LIMIT 500", [date('Y-m-d H:i:s', $jetzt - 6 * 86400)]) as $p) {
            $pid = (int) $p['id'];
            if (!self::an($pid, 'wochenbericht')) { continue; }
            // Erst vermerken, dann schicken: ein hängender Push-Dienst darf keine Serie auslösen.
            Db::run('INSERT INTO partner_automatik (partner_id, bericht_am) VALUES (?, ?) ON DUPLICATE KEY UPDATE bericht_am = VALUES(bericht_am)', [$pid, date('Y-m-d H:i:s', $jetzt)]);
            $k = Partner::kennzahlen($pid, $von, date('Y-m-d', $jetzt));
            $sp = in_array((string) $p['sprache'], ['it', 'de', 'en'], true) ? (string) $p['sprache'] : 'it';
            $A = Texte::PARTNER_AUTOMATIK['push'];
            $text = strtr(Texte::h($A['bericht_text'], $sp), ['{klicks}' => (string) $k['klicks'], '{kunden}' => (string) $k['kunden'],
                                                               '{verkaeufe}' => (string) $k['verkaeufe'], '{betrag}' => Fmt::geld((int) $k['provision'])]);
            if (self::still(static fn() => PartnerPost::push($pid, Texte::h($A['bericht_titel'], $sp), $text, Partner::portalLink($p) . '#zahlen'), 0) > 0) { $n++; }
        }
        return $n;
    }

    /**
     * Check + Mappe von selbst: Für frische Reservierungen mit Website, zu
     * denen es noch keinen Check gibt, legt der Cronlauf ihn an — genau den
     * Check, den der Partner sonst von Hand anstieße (zählt auch gegen sein
     * Tageslimit). Danach ein Hinweis mit Sprung zur Mappe.
     */
    public static function checksNachholen(?int $nurPartner = null): int
    {
        require_once __DIR__ . '/PartnerCheck.php';
        require_once __DIR__ . '/PartnerPost.php';
        $n = 0;
        $kandidaten = Db::all("SELECT r.partner_id, f.id AS firma_id, f.name, COALESCE(NULLIF(f.url, ''), f.domain) AS web, p.sprache, p.token
                                 FROM partner_reservierungen r JOIN akq_firmen f ON f.id = r.firma_id JOIN partner p ON p.id = r.partner_id
                                WHERE p.status = 'aktiv' AND r.bis >= CURDATE() AND r.created_at >= NOW() - INTERVAL 2 DAY
                                  AND COALESCE(NULLIF(f.url, ''), f.domain, '') <> '' AND f.gesperrt = 0" . ($nurPartner !== null ? ' AND r.partner_id = ' . (int) $nurPartner : '') . "
                             ORDER BY r.created_at LIMIT 50");
        foreach ($kandidaten as $c) {
            if ($n >= self::CHECKS_JE_LAUF) { break; }
            $pid = (int) $c['partner_id'];
            if (!self::an($pid, 'check')) { continue; }
            if (in_array((int) $c['firma_id'], self::versucht($pid), true)) { continue; }
            $host = preg_replace('~^www\.~', '', strtolower((string) (parse_url(str_contains((string) $c['web'], '://') ? (string) $c['web'] : 'https://' . $c['web'], PHP_URL_HOST) ?: $c['web'])));
            if ($host === '' || (int) Db::wert("SELECT COUNT(*) FROM partner_checks WHERE partner_id = ? AND (host = ? OR host = ?)", [$pid, $host, 'www.' . $host], 0) > 0) { continue; }
            self::versuchtMerken($pid, (int) $c['firma_id']);   // auch ein Fehlschlag wird nicht jede halbe Stunde wiederholt
            $r = self::still(static fn() => PartnerCheck::anlegen($pid, (string) $c['web']), ['ok' => false]);
            if (!$r['ok']) { continue; }
            $n++;
            $sp = in_array((string) $c['sprache'], ['it', 'de', 'en'], true) ? (string) $c['sprache'] : 'it';
            $A = Texte::PARTNER_AUTOMATIK['push'];
            self::still(static fn() => PartnerPost::push($pid, strtr(Texte::h($A['check_titel'], $sp), ['{name}' => (string) $c['name']]), Texte::h($A['check_text'], $sp),
                Partner::portalLink(['token' => $c['token']]) . '#recherche'), 0);
        }
        return $n;
    }

    /** @return list<int> */
    private static function versucht(int $pid): array
    {
        $roh = self::still(static fn() => Db::wert('SELECT einstellungen FROM partner_automatik WHERE partner_id = ?', [$pid], null), null);
        return array_map('intval', (array) ((is_string($roh) ? json_decode($roh, true) : [])['check_versucht'] ?? []));
    }

    private static function versuchtMerken(int $pid, int $firma): void
    {
        $roh = self::still(static fn() => Db::wert('SELECT einstellungen FROM partner_automatik WHERE partner_id = ?', [$pid], null), null);
        $j = is_string($roh) ? (json_decode($roh, true) ?: []) : [];
        $j['check_versucht'] = array_slice(array_values(array_unique(array_merge((array) ($j['check_versucht'] ?? []), [$firma]))), -50);
        Db::run('INSERT INTO partner_automatik (partner_id, einstellungen) VALUES (?, ?) ON DUPLICATE KEY UPDATE einstellungen = VALUES(einstellungen)', [$pid, json_encode($j)]);
        unset(self::$cache[$pid]);
    }

    /** Alles aus dem Cronlauf; jede Aufgabe fällt für sich. */
    public static function lauf(): array
    {
        require_once __DIR__ . '/PartnerBesuche.php';
        return ['wochenbericht' => self::still(static fn() => self::wochenbericht(), -1), 'checks' => self::still(static fn() => self::checksNachholen(), -1),
                'kontakte_weg' => self::still(static fn() => PartnerBesuche::aufraeumen(), -1),
                'uebersetzen' => self::still(static function (): int { require_once __DIR__ . '/PartnerSeite.php'; return (int) PartnerSeite::uebersetzungAnstossen(); }, -1)];
    }
}
