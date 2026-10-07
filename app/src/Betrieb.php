<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Versionen.php';
require_once __DIR__ . '/BauPruefung.php';

/**
 * AutoBuild Phase 10: Betrieb (07.10.2026, Uwe: „mache mit den offenen Phasen weiter“ — Vorschläge 1–10).
 *
 *  1  Karte „Betrieb“ am Projekt                    stand()
 *  2  Übersicht aller Live-Seiten mit Ampel        uebersicht()
 *  3  Abweichungswächter (täglich)                 abweichung(), taeglich()
 *  4  Seitenprüfung (wöchentlich)                  seitencheck(), woechentlich()
 *  5  Kontingent der Betreuung + Zusatzangebot     kontingent(), einordnungPruefen(), zusatzangebot()
 *  6  Betriebsbericht als PDF am Monatsbericht     berichtPdf()
 *  9  Kostenwächter                                grenze(), bauLaeufe(), bauSperre(), grenzeErhoehen()
 * 10  Bewusst nicht: keine Seite wird wegen einer offenen Zahlung abgeschaltet — sie steht nur gelb in der Übersicht.
 *
 * Repariert wird nichts von selbst: Eine Abweichung meldet sich; wiederherstellen ist ein Klick
 * (dieselbe Fassung noch einmal veröffentlichen, mit Sicherung vorher — Veroeffentlichung).
 */
final class Betrieb
{
    /** So viele Dateien einer Fassung vergleicht der Abweichungswächter höchstens. */
    public const MAX_DATEIEN = 40;
    /** So viele Verweise prüft die Seitenprüfung höchstens. */
    public const MAX_VERWEISE = 60;
    /** Tage Nachbesserung nach dem Livegang (wie im Angebot). */
    public const NACHBESSERUNG_TAGE = 30;
    /** Vorgabe für den Kostenwächter: Bauläufe je Projekt, danach wartet jeder neue Auftrag auf Freigabe. */
    public const GRENZE_VORGABE = 30;
    /** So viel erhöht „freigeben“. */
    public const GRENZE_SCHRITT = 10;

    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }

    private static function einstellung(string $k, string $vorgabe = ''): string
    {
        return (string) self::still(static fn() => Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$k], $vorgabe), $vorgabe);
    }

    private static function setzen(string $k, string $v): void
    {
        Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, $v]);
    }

    /** @return callable(string):array{0:int,1:string} */
    public static function http(): callable
    {
        return static function (string $u): array {
            if (!function_exists('curl_init')) { return [0, '']; }
            $c = curl_init($u);
            curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_TIMEOUT => 15,
                CURLOPT_USERAGENT => 'Vecom-Betrieb', CURLOPT_ENCODING => '']);
            $b = (string) curl_exec($c); $s = (int) curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
            return [$s, $b];
        };
    }

    /* ------------------------------------------------------------------ */
    /*  Zustand                                                           */
    /* ------------------------------------------------------------------ */

    public static function imBetrieb(array $p): bool
    {
        return !empty($p['veroeffentlicht_am']) && (int) ($p['live_version_id'] ?? 0) > 0 && (string) ($p['veroeffentlicht_domain'] ?? '') !== '';
    }

    public static function seit(array $p): ?string
    {
        $s = (string) (($p['betrieb_seit'] ?? '') ?: ($p['veroeffentlicht_am'] ?? ''));
        return $s !== '' ? $s : null;
    }

    /** Ab der Freigabe der Übergabe gilt das Projekt als „im Betrieb“ (einmal gesetzt, bleibt es). */
    public static function beginnen(int $pid): void
    {
        self::still(static fn() => Db::run('UPDATE projects SET betrieb_seit = COALESCE(betrieb_seit, NOW()) WHERE id = ? AND veroeffentlicht_am IS NOT NULL', [$pid]), null);
    }

    /** Der Betreuungsvertrag zu diesem Projekt (oder ein projektloser des Kunden). */
    public static function abo(int $pid): ?array
    {
        return self::still(static fn() => Db::one("SELECT a.*, pk.inklusiv_minuten, pk.art AS paket_art FROM projects p
              JOIN abos a ON a.customer_id = p.customer_id AND (a.project_id = p.id OR a.project_id IS NULL)
              LEFT JOIN packages pk ON pk.id = a.package_id
             WHERE p.id = ? AND a.status IN ('aktiv','gekuendigt') AND (pk.art = 'betreuung' OR a.paket_slug LIKE 'betreuung%')
             ORDER BY (a.project_id = p.id) DESC, a.id DESC LIMIT 1", [$pid]), null);
    }

    /**
     * Kontingent des Monats: enthaltene Minuten (aus dem Paket) und was davon schon eingeordnet ist.
     * Kleine Inhaltsänderungen (Texte, Fotos, Öffnungszeiten, Kontaktdaten) stehen in jedem Betreuungspaket
     * als „inklusive“ — sie zählen mit 0 Minuten.
     * @return array{vertrag:?string, minuten:int, verbraucht:int, rest:int, nachbesserung_bis:?string, in_nachbesserung:bool, monat:string}
     */
    public static function kontingent(int $pid, ?string $monat = null): array
    {
        $monat ??= date('Y-m');
        $p = Db::one('SELECT * FROM projects WHERE id = ?', [$pid]) ?: [];
        $abo = self::abo($pid);
        $min = $abo ? (int) ($abo['inklusiv_minuten'] ?? 0) : 0;
        $verb = (int) self::still(static fn() => Db::wert("SELECT COALESCE(SUM(aufwand_min),0) FROM projekt_wuensche WHERE project_id = ? AND kontingent_monat = ?
              AND status IN ('im_umfang','in_arbeit','umgesetzt')", [$pid, $monat], 0), 0);
        $seit = self::seit($p);
        $bis = $seit ? date('Y-m-d', strtotime($seit . ' +' . self::NACHBESSERUNG_TAGE . ' days')) : null;
        return ['vertrag' => $abo ? (string) $abo['paket_name'] : null, 'minuten' => $min, 'verbraucht' => $verb, 'rest' => max(0, $min - $verb),
                'nachbesserung_bis' => $bis, 'in_nachbesserung' => $bis !== null && date('Y-m-d') <= $bis, 'monat' => $monat];
    }

    /** Aufwand aus Claudes Schätzung („ca. 30 Min.“, „1 Stunde“) — nur als Vorschlag im Formular. */
    public static function minutenAus(string $aufwand): int
    {
        $a = mb_strtolower($aufwand);
        if (preg_match('~(\d+(?:[.,]\d+)?)\s*(std|stunde|h\b)~u', $a, $m)) { return (int) round((float) str_replace(',', '.', $m[1]) * 60); }
        if (preg_match('~(\d+)\s*min~u', $a, $m)) { return (int) $m[1]; }
        return 0;
    }

    /**
     * Darf ein Wunsch nach dem Livegang „im Umfang“ werden? null = ja. Sonst der Grund — dann ist „Zusatz“ richtig.
     * Vor dem Livegang gilt das Angebot (Phase 8) und nichts hiervon.
     */
    public static function einordnungPruefen(int $pid, int $minuten): ?string
    {
        $p = Db::one('SELECT * FROM projects WHERE id = ?', [$pid]);
        if (!$p || !self::imBetrieb($p)) { return null; }
        $k = self::kontingent($pid);
        if ($k['in_nachbesserung']) { return null; }
        if ($k['vertrag'] === null) {
            return 'Die Seite ist live, die ' . self::NACHBESSERUNG_TAGE . ' Tage Nachbesserung sind vorbei und es läuft kein Betreuungsvertrag — bitte als Zusatz einordnen (Angebot vorher).';
        }
        if ($minuten > 0 && $minuten > $k['rest']) {
            return 'Kontingent ' . $k['vertrag'] . ' im ' . $k['monat'] . ': ' . $k['minuten'] . ' Min. enthalten, ' . $k['verbraucht'] . ' schon verplant — für ' . $minuten
                . ' Min. reicht es nicht. Bitte als Zusatz einordnen oder den Aufwand auf eine kleine Inhaltsänderung (0 Min.) beschränken.';
        }
        return null;
    }

    /** @return array<string,mixed> alles für die Karte „Betrieb“ */
    public static function stand(int $pid): array
    {
        $p = Db::one('SELECT * FROM projects WHERE id = ?', [$pid]) ?: [];
        $w = self::still(static fn() => Db::one('SELECT * FROM websites WHERE project_id = ? OR (domain <> \'\' AND domain = ?) ORDER BY (project_id = ?) DESC, id DESC LIMIT 1',
            [$pid, (string) ($p['veroeffentlicht_domain'] ?? ''), $pid]), null);
        $sicherung = self::still(static fn() => Db::one("SELECT id, orig_name, created_at FROM files WHERE project_id = ? AND (rolle = 'sicherung' OR orig_name LIKE 'sicherung-%') ORDER BY id DESC LIMIT 1", [$pid]), null);
        $live = (int) ($p['live_version_id'] ?? 0) > 0 ? Versionen::laden((int) $p['live_version_id']) : null;
        $offen = (int) self::still(static fn() => Db::wert("SELECT COUNT(*) FROM projekt_wuensche WHERE project_id = ? AND status IN ('neu','im_umfang','zusatz','zusatz_angenommen','in_arbeit')", [$pid], 0), 0);
        return [
            'im_betrieb' => self::imBetrieb($p), 'seit' => self::seit($p), 'domain' => (string) ($p['veroeffentlicht_domain'] ?? ''),
            'live' => $live, 'livecheck' => json_decode((string) ($p['livecheck'] ?? ''), true) ?: null, 'livecheck_am' => $p['livecheck_am'] ?? null,
            'website' => $w, 'ssl_tage' => $w && !empty($w['ssl_expires_at']) ? (int) floor((strtotime((string) $w['ssl_expires_at']) - time()) / 86400) : null,
            'sicherung' => $sicherung, 'abweichung' => json_decode((string) ($p['abweichung'] ?? ''), true) ?: null, 'abweichung_am' => $p['abweichung_am'] ?? null,
            'seitencheck' => json_decode((string) ($p['seitencheck'] ?? ''), true) ?: null, 'seitencheck_am' => $p['seitencheck_am'] ?? null,
            'offene_wuensche' => $offen, 'kontingent' => self::kontingent($pid), 'bau_laeufe' => self::bauLaeufe($pid), 'grenze' => self::grenze($pid),
            'zahlung_offen' => self::zahlungOffen($pid),
        ];
    }

    /** Überfällige Raten zu diesem Projekt (Bestellung oder Betreuung) — nur zur Anzeige, nie ein Abschaltgrund. */
    public static function zahlungOffen(int $pid): int
    {
        return (int) self::still(static fn() => Db::wert("SELECT COUNT(*) FROM payments z
              LEFT JOIN orders o ON o.id = z.order_id LEFT JOIN abos a ON a.id = z.abo_id
              JOIN projects p ON p.id = ?
             WHERE z.status = 'ausstehend' AND z.faellig_am IS NOT NULL AND z.faellig_am < CURDATE()
               AND (o.id = p.order_id OR (a.customer_id = p.customer_id AND (a.project_id = p.id OR a.project_id IS NULL)))", [$pid], 0), 0);
    }

    /**
     * Alle Live-Seiten mit Ampel. Rot: nicht erreichbar, Abweichung, Zertifikat < 7 Tage, Live-Prüfung gescheitert.
     * Gelb: Seitenprüfung mit Befund, offene Wünsche, Zertifikat < 30 Tage, überfällige Zahlung, Kostengrenze erreicht.
     * @return list<array<string,mixed>>
     */
    public static function uebersicht(): array
    {
        $aus = [];
        foreach ((array) self::still(static fn() => Db::all("SELECT p.id, p.name, c.name AS kunde, c.company AS firma FROM projects p JOIN customers c ON c.id = p.customer_id
              WHERE p.veroeffentlicht_am IS NOT NULL AND p.live_version_id IS NOT NULL ORDER BY p.veroeffentlicht_am DESC LIMIT 300"), []) as $r) {
            $s = self::stand((int) $r['id']);
            $rot = []; $gelb = [];
            $ws = (string) ($s['website']['status'] ?? '');
            if (in_array($ws, ['offline', 'fehler', 'ssl_problem', 'domain_problem'], true)) { $rot[] = 'nicht erreichbar (' . $ws . ')'; }
            if ($s['livecheck'] && empty($s['livecheck']['ok'])) { $rot[] = 'Live-Prüfung gescheitert'; }
            if ($s['abweichung'] && empty($s['abweichung']['ok'])) { $rot[] = 'weicht von V' . (int) ($s['live']['nummer'] ?? 0) . ' ab'; }
            if ($s['ssl_tage'] !== null && $s['ssl_tage'] < 7) { $rot[] = 'Zertifikat ' . ($s['ssl_tage'] < 0 ? 'abgelaufen' : 'noch ' . $s['ssl_tage'] . ' Tage'); }
            elseif ($s['ssl_tage'] !== null && $s['ssl_tage'] < 30) { $gelb[] = 'Zertifikat noch ' . $s['ssl_tage'] . ' Tage'; }
            if ($s['seitencheck'] && empty($s['seitencheck']['ok'])) { $gelb[] = 'Seitenprüfung: ' . (int) ($s['seitencheck']['befunde'] ?? 0) . ' Befund(e)'; }
            if ($s['offene_wuensche'] > 0) { $gelb[] = $s['offene_wuensche'] . ' offene(r) Wunsch/Wünsche'; }
            if ($s['zahlung_offen'] > 0) { $gelb[] = $s['zahlung_offen'] . ' überfällige Rate(n)'; }
            if ($s['bau_laeufe'] >= $s['grenze']) { $gelb[] = 'Kostengrenze erreicht (' . $s['bau_laeufe'] . '/' . $s['grenze'] . ')'; }
            $aus[] = $r + ['stand' => $s, 'ampel' => $rot ? 'rot' : ($gelb ? 'gelb' : 'gruen'), 'gruende' => array_merge($rot, $gelb)];
        }
        $rang = ['rot' => 0, 'gelb' => 1, 'gruen' => 2];
        usort($aus, static fn($a, $b) => $rang[$a['ampel']] <=> $rang[$b['ampel']] ?: strcmp((string) $a['name'], (string) $b['name']));
        return $aus;
    }

    /* ------------------------------------------------------------------ */
    /*  3  Abweichungswächter                                             */
    /* ------------------------------------------------------------------ */

    private static function gleich(string $a, string $b): bool
    {
        $n = static fn(string $s): string => rtrim(str_replace(["\r\n", "\r"], "\n", $s));
        return hash('sha256', $n($a)) === hash('sha256', $n($b));
    }

    /**
     * Liegt online noch genau die freigegebene Fassung? Vergleicht die Textdateien (HTML, CSS, JS …) über HTTPS.
     * Meldet sich nur beim Wechsel von „passt“ auf „weicht ab“ — nicht jeden Tag neu.
     * @param null|callable(string):array{0:int,1:string} $http
     * @return array{ok:bool, geprueft:int, geaendert:list<string>, fehlt:list<string>, text:string}
     */
    public static function abweichung(int $pid, ?callable $http = null): array
    {
        $p = Db::one('SELECT * FROM projects WHERE id = ?', [$pid]);
        if (!$p || !self::imBetrieb($p)) { return ['ok' => true, 'geprueft' => 0, 'geaendert' => [], 'fehlt' => [], 'text' => 'Nicht im Betrieb.']; }
        $http ??= self::http();
        $basis = 'https://' . $p['veroeffentlicht_domain'] . '/';
        $dateien = array_slice(Versionen::dateien((int) $p['live_version_id'], true), 0, self::MAX_DATEIEN, true);
        $geaendert = []; $fehlt = []; $n = 0; $netz = 0;
        foreach ($dateien as $pfad => $inhalt) {
            [$st, $body] = $http($basis . ($pfad === 'index.html' ? '' : implode('/', array_map('rawurlencode', explode('/', $pfad)))));
            $n++;
            /* Antwortet die Domain einmal nicht, nicht noch 39 Mal je 15 s warten (Prüfung 07.10.2026, Punkt 39). */
            if ($st === 0) { $netz++; break; }
            if ($st === 404 || $st === 410) { $fehlt[] = $pfad; continue; }
            if ($st !== 200 || !self::gleich($inhalt, $body)) { $geaendert[] = $pfad . ($st !== 200 ? ' (' . $st . ')' : ''); }
        }
        if ($netz > 0) {
            /* Trotzdem vermerken, wann versucht wurde — sonst stand dasselbe Projekt beim nächsten Lauf
               wieder ganz vorn und blockierte die anderen. Das letzte Ergebnis bleibt stehen. */
            Db::update('projects', $pid, ['abweichung_am' => date('Y-m-d H:i:s')]);
            return ['ok' => true, 'geprueft' => 0, 'geaendert' => [], 'fehlt' => [], 'text' => 'Keine Verbindung — dieser Lauf zählt nicht.'];
        }
        $ok = !$geaendert && !$fehlt;
        $v = (int) Db::wert('SELECT nummer FROM projekt_versionen WHERE id = ?', [(int) $p['live_version_id']], 0);
        $text = $ok ? 'Online liegt genau V' . $v . ' (' . ($n - $netz) . ' Dateien verglichen).'
            : 'Online weicht von V' . $v . ' ab: ' . ($geaendert ? count($geaendert) . ' geändert (' . implode(', ', array_slice($geaendert, 0, 5)) . ')' : '')
              . ($geaendert && $fehlt ? ', ' : '') . ($fehlt ? count($fehlt) . ' fehlen (' . implode(', ', array_slice($fehlt, 0, 5)) . ')' : '') . '.';
        $vorher = json_decode((string) ($p['abweichung'] ?? ''), true);
        $erg = ['ok' => $ok, 'geprueft' => $n - $netz, 'geaendert' => $geaendert, 'fehlt' => $fehlt, 'text' => $text];
        Db::update('projects', $pid, ['abweichung' => json_encode($erg, JSON_UNESCAPED_UNICODE), 'abweichung_am' => date('Y-m-d H:i:s')]);
        if (!$ok && (!is_array($vorher) || !empty($vorher['ok']))) {
            self::still(static fn() => Events::melden('betrieb_abweichung', 'Live-Seite verändert: ' . $p['veroeffentlicht_domain'], 'schlecht',
                $text . ' Jemand hat per FTP etwas geändert — oder die Seite wurde angegriffen. Repariert wird nichts von selbst: Karte „Betrieb“ → „V' . $v . ' wiederherstellen“.',
                '/projekte/' . $pid . '#betrieb'), null);
        }
        return $erg;
    }

    /* ------------------------------------------------------------------ */
    /*  4  Seitenprüfung                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Wöchentlich: kaputte Links, fehlende Bilder, Kontaktwege, Impressum/Datenschutz, Jahreszahl im Fuß, Formulare.
     * Grundlage sind die Seiten der Live-Fassung; geprüft wird, was online antwortet.
     * @return array{ok:bool, befunde:int, punkte:list<array{name:string,ok:bool,schwer:bool,detail:string}>}
     */
    public static function seitencheck(int $pid, ?callable $http = null): array
    {
        $p = Db::one('SELECT * FROM projects WHERE id = ?', [$pid]);
        if (!$p || !self::imBetrieb($p)) { return ['ok' => true, 'befunde' => 0, 'punkte' => []]; }
        $http ??= self::http();
        $basis = 'https://' . $p['veroeffentlicht_domain'] . '/';
        $dateien = Versionen::dateien((int) $p['live_version_id'], true);
        $html = array_filter($dateien, static fn($v, $k) => (bool) preg_match('~\.html?$~i', (string) $k), ARRAY_FILTER_USE_BOTH);
        $punkte = [];
        $add = static function (string $name, bool $ok, bool $schwer, string $detail = '') use (&$punkte): void {
            $punkte[] = ['name' => $name, 'ok' => $ok, 'schwer' => $schwer, 'detail' => mb_substr($detail, 0, 400)];
        };
        [$st] = $http($basis);
        $add('Startseite antwortet', $st === 200, true, $st === 200 ? '' : 'Antwort ' . ($st ?: 'keine'));

        $intern = []; $bilderExtern = []; $alles = '';
        foreach ($html as $pfad => $inhalt) {
            $alles .= ' ' . $inhalt;
            if (preg_match_all('~\b(href|src)=["\']([^"\'#?]+)~i', $inhalt, $m, PREG_SET_ORDER)) {
                foreach ($m as [, $attr, $ziel]) {
                    $ziel = trim($ziel);
                    if ($ziel === '') { continue; }
                    if (preg_match('~^https?://~i', $ziel)) { if (strtolower($attr) === 'src' && count($bilderExtern) < 10) { $bilderExtern[$ziel] = $pfad; } continue; }
                    if (preg_match('~^([a-z][a-z0-9+.-]*:|//)~i', $ziel)) { continue; }
                    $voll = BauPruefung::aufloesen($pfad, $ziel);
                    if ($voll !== null && !isset($intern[$voll])) { $intern[$voll] = $pfad; }
                }
            }
        }
        $tot = []; $i = 0;
        foreach ($intern as $ziel => $von) {
            if (++$i > self::MAX_VERWEISE || $st === 0) { break; }   // Startseite stumm: keine 60 Abrufe à 15 s
            [$s] = $http($basis . implode('/', array_map('rawurlencode', explode('/', $ziel))));
            if ($s === 0) { break; }
            if ($s !== 200) { $tot[] = $von . ' → ' . $ziel . ' (' . $s . ')'; }
        }
        $add('Keine kaputten internen Links oder fehlenden Dateien', !$tot, true, implode(', ', array_slice($tot, 0, 8)));
        $weg = [];
        foreach ($bilderExtern as $u => $von) { [$s] = $http($u); if ($s !== 200 && $s !== 0) { $weg[] = $u . ' (' . $s . ')'; } }
        $add('Eingebundene Bilder von außen erreichbar', !$weg, false, implode(', ', array_slice($weg, 0, 5)));
        $add('Kontaktweg vorhanden (tel:, mailto:, WhatsApp)', (bool) preg_match('~href=["\'](tel:|mailto:|https://wa\.me/)~i', $alles), true);
        $add('Impressum / Datenschutz verlinkt', (bool) preg_match('~privacy|datenschutz|impressum|note-legali|note legali|informativa~i', $alles . ' ' . implode(' ', array_keys($dateien))), false);
        $jahr = (int) date('Y');
        $ok = true; $detail = '';
        if (preg_match_all('~(?:©|&copy;|\(c\))\s*(?:(\d{4})\s*[–-]\s*)?(\d{4})~iu', $alles, $jm)) {
            $max = max(array_map('intval', $jm[2]));
            if ($max < $jahr) { $ok = false; $detail = 'Im Fuß steht © ' . $max . ', wir haben ' . $jahr . '.'; }
        }
        $add('Jahreszahl im Fuß aktuell', $ok, false, $detail);
        $leer = preg_match_all('~<form\b(?![^>]*\baction=["\'][^"\']+)[^>]*>~i', $alles);
        $add('Kein Formular, das ins Leere sendet', $leer === 0, true, $leer ? $leer . ' Formular(e) ohne Ziel' : '');
        $befunde = count(array_filter($punkte, static fn($x) => !$x['ok']));
        $erg = ['ok' => !array_filter($punkte, static fn($x) => !$x['ok'] && $x['schwer']) && $befunde === 0, 'befunde' => $befunde, 'punkte' => $punkte];
        Db::update('projects', $pid, ['seitencheck' => json_encode($erg, JSON_UNESCAPED_UNICODE), 'seitencheck_am' => date('Y-m-d H:i:s')]);
        return $erg;
    }

    /** Cron, täglich: Abweichung für alle Live-Seiten (höchstens 20 je Lauf, jede einmal am Tag). */
    public static function taeglich(?callable $http = null): int
    {
        $n = 0;
        $start = microtime(true);
        foreach ((array) self::still(static fn() => Db::all("SELECT id FROM projects WHERE veroeffentlicht_am IS NOT NULL AND live_version_id IS NOT NULL
              AND (abweichung_am IS NULL OR abweichung_am < CURDATE()) ORDER BY abweichung_am IS NULL DESC, abweichung_am LIMIT 20"), []) as $r) {
            if (microtime(true) - $start > self::ZEITBUDGET) { break; }   // der nächste Lauf macht weiter
            self::still(static fn() => self::abweichung((int) $r['id'], $http), null);
            // Auch bei einem Absturz mittendrin: heute ist dieses Projekt dran gewesen.
            self::still(static fn() => Db::run('UPDATE projects SET abweichung_am = NOW() WHERE id = ? AND (abweichung_am IS NULL OR abweichung_am < CURDATE())', [(int) $r['id']]), null);
            $n++;
        }
        return $n;
    }

    /** Höchstens so viele Sekunden je Cron-Aufgabe (Abweichung, Seitenprüfung). */
    public const ZEITBUDGET = 90;

    /** Cron, wöchentlich je Seite: Seitenprüfung (höchstens 5 je Lauf). */
    public static function woechentlich(?callable $http = null): int
    {
        $n = 0;
        $start = microtime(true);
        foreach ((array) self::still(static fn() => Db::all("SELECT id FROM projects WHERE veroeffentlicht_am IS NOT NULL AND live_version_id IS NOT NULL
              AND (seitencheck_am IS NULL OR seitencheck_am < NOW() - INTERVAL 7 DAY) ORDER BY seitencheck_am IS NULL DESC, seitencheck_am LIMIT 5"), []) as $r) {
            if (microtime(true) - $start > self::ZEITBUDGET) { break; }
            self::still(static fn() => self::seitencheck((int) $r['id'], $http), null);
            self::still(static fn() => Db::run('UPDATE projects SET seitencheck_am = NOW() WHERE id = ? AND (seitencheck_am IS NULL OR seitencheck_am < NOW() - INTERVAL 7 DAY)', [(int) $r['id']]), null);
            $n++;
        }
        return $n;
    }

    /* ------------------------------------------------------------------ */
    /*  5  Zusatzangebot                                                  */
    /* ------------------------------------------------------------------ */

    /** Stundensatz für Zusatzangebote in Cent (0 = nicht eingestellt). */
    public static function stundensatz(): int
    {
        return max(0, (int) self::einstellung('betrieb_stundensatz', '0'));
    }

    /**
     * Ein Zusatzangebot (Entwurf) für einen Wunsch, der als „Zusatz“ eingeordnet ist. Geht NICHT raus —
     * Uwe sieht es an, ändert es und schickt es selbst.
     * @return int|string Angebots-ID oder Hinweis
     */
    public static function zusatzangebot(int $wunschId, int $minuten, string $wer): int|string
    {
        $w = Db::one('SELECT * FROM projekt_wuensche WHERE id = ?', [$wunschId]);
        if (!$w || (string) $w['status'] !== 'zusatz') { return 'Ein Zusatzangebot gibt es nur zu einem Wunsch, der als „Zusatz“ eingeordnet ist.'; }
        $satz = self::stundensatz();
        if ($satz <= 0) { return 'Erst einen Stundensatz eintragen (Übersicht „Betrieb“ → Einstellungen).'; }
        $minuten = max(15, $minuten);
        $cents = (int) (ceil($minuten / 15) * 15 / 60 * $satz);
        $p = Db::one('SELECT customer_id, name FROM projects WHERE id = ?', [(int) $w['project_id']]);
        if (!$p) { return 'Projekt nicht gefunden.'; }
        require_once __DIR__ . '/Angebot.php';
        $a = Angebot::festpreisNeu((int) $p['customer_id'], max(100, $cents));
        if (!is_int($a)) { return $a; }
        $kurz = mb_substr(trim((string) preg_replace('~\s+~u', ' ', (string) $w['text'])), 0, 120);
        Db::update('angebote', $a, ['titel' => mb_substr('Änderung – ' . $kurz, 0, 200),
            'einleitung' => 'Ihr Wunsch: „' . mb_substr((string) $w['text'], 0, 600) . '“' . "\n" . 'Geschätzter Aufwand: ' . $minuten . ' Minuten.']);
        Db::insert('angebot_positionen', ['angebot_id' => $a, 'bezeichnung' => mb_substr('Änderung: ' . $kurz, 0, 190), 'beschreibung' => 'Umsetzung auf einer Testfassung, Ihre Freigabe, Veröffentlichung mit Sicherung vorher.',
            'menge' => 1, 'einzel_cents' => max(100, $cents), 'summe_cents' => max(100, $cents), 'monatlich' => 0, 'sortierung' => 10]);
        Angebot::summenNeu($a);
        Events::pruefspur('zusatzangebot', 'angebote', $a, [], ['wunsch' => $wunschId, 'minuten' => $minuten, 'von' => $wer]);
        return $a;
    }

    /* ------------------------------------------------------------------ */
    /*  9  Kostenwächter                                                  */
    /* ------------------------------------------------------------------ */

    public static function grenzeVorgabe(): int
    {
        return max(1, (int) self::einstellung('bau_grenze_projekt', (string) self::GRENZE_VORGABE));
    }

    public static function grenze(int $pid): int
    {
        $eigen = (int) self::still(static fn() => Db::wert('SELECT bau_grenze FROM projects WHERE id = ?', [$pid], 0), 0);
        return $eigen > 0 ? $eigen : self::grenzeVorgabe();
    }

    /** Bauläufe eines Projekts (Analyse, Pflichtenheft, Bauen, Review, Wünsche) — abgebrochene zählen nicht. */
    public static function bauLaeufe(int $pid): int
    {
        return (int) self::still(static fn() => Db::wert("SELECT COUNT(*) FROM bau_auftraege WHERE project_id = ? AND status <> 'abgebrochen'", [$pid], 0), 0);
    }

    /** null = darf; sonst der Grund. Meldet sich einmal, wenn die Grenze erreicht ist. */
    public static function bauSperre(int $pid): ?string
    {
        $n = self::bauLaeufe($pid); $g = self::grenze($pid);
        if ($n < $g) { return null; }
        $schl = 'bau_grenze_gemeldet_' . $pid . '_' . $g;
        if (self::einstellung($schl) === '') {
            self::setzen($schl, date('Y-m-d H:i:s'));
            $name = (string) Db::wert('SELECT name FROM projects WHERE id = ?', [$pid], '');
            self::still(static fn() => Events::melden('bau_grenze', 'Kostengrenze erreicht: ' . $name, 'info',
                "$n Bauläufe — neue Aufträge für dieses Projekt warten, bis Sie freigeben (Karte „Betrieb“ bzw. „Bauen“).", '/projekte/' . $pid . '#betrieb'), null);
        }
        return 'Kostenwächter: ' . $n . ' von ' . $g . ' Bauläufen verbraucht — neue Aufträge warten, bis ein Admin freigibt (Karte „Betrieb“).';
    }

    public static function grenzeErhoehen(int $pid, string $wer): int
    {
        $neu = max(self::grenze($pid), self::bauLaeufe($pid)) + self::GRENZE_SCHRITT;
        Db::update('projects', $pid, ['bau_grenze' => $neu]);
        Events::pruefspur('bau_grenze', 'projects', $pid, [], ['grenze' => $neu, 'von' => $wer]);
        return $neu;
    }

    public static function einstellungenSpeichern(int $stundensatzCent, int $grenze, string $wer): void
    {
        self::setzen('betrieb_stundensatz', (string) max(0, $stundensatzCent));
        self::setzen('bau_grenze_projekt', (string) max(1, min(500, $grenze)));
        Events::pruefspur('betrieb_einstellungen', 'settings', 0, [], ['stundensatz' => $stundensatzCent, 'grenze' => $grenze, 'von' => $wer]);
    }

    /* ------------------------------------------------------------------ */
    /*  6  Betriebsbericht                                                */
    /* ------------------------------------------------------------------ */

    /** Der Monatsbericht einer Live-Seite als PDF im Stil „Vecom Gold“. Leer, wenn nicht im Betrieb. */
    public static function berichtPdf(int $pid, ?string $monat = null): string
    {
        require_once __DIR__ . '/Dokument.php';
        $p = Db::one('SELECT p.*, c.sprache, c.name AS kunde, c.company AS firma FROM projects p JOIN customers c ON c.id = p.customer_id WHERE p.id = ?', [$pid]);
        if (!$p || !self::imBetrieb($p) || !Dokument::bereit()) { return ''; }
        $monat ??= date('Y-m', strtotime('first day of last month'));
        $s = in_array((string) $p['sprache'], ['it', 'de', 'en'], true) ? (string) $p['sprache'] : 'it';
        $L = static fn(array $t): string => (string) ($t[$s] ?? $t['de']);
        require_once __DIR__ . '/Abo.php';
        $monatWort = (string) self::still(static fn() => Abo::monatswort($monat, $s), $monat);
        $titel = $L(['it' => 'Rapporto mensile', 'de' => 'Betriebsbericht', 'en' => 'Monthly report']);
        $d = new Dokument($titel, $p['veroeffentlicht_domain'] . ' · ' . $monatWort, $titel . ' · ' . $p['veroeffentlicht_domain'] . ' · ' . $monatWort);
        $st = self::stand($pid);
        $von = $monat . '-01'; $bis = date('Y-m-t', strtotime($von));
        $d->adresseUndMeta(Dokument::absenderzeile(), array_values(array_filter([(string) ($p['firma'] ?? ''), (string) $p['kunde']])),
            [[$L(['it' => 'Mese', 'de' => 'Monat', 'en' => 'Month']), $monatWort],
             [$L(['it' => 'Versione online', 'de' => 'Live-Fassung', 'en' => 'Live version']), 'V' . (int) ($st['live']['nummer'] ?? 0)]]);
        $zeilen = [];
        $w = $st['website'];
        if ($w) {
            require_once __DIR__ . '/Monitoring.php';
            $proz = self::still(static fn() => Monitoring::verfuegbarkeit((int) $w['id'], 30), null);
            $zeilen[] = [$L(['it' => 'Raggiungibilità (30 giorni)', 'de' => 'Erreichbarkeit (30 Tage)', 'en' => 'Availability (30 days)']), $proz !== null ? number_format((float) $proz, 1, ',', '.') . ' %' : '—'];
        }
        if ($st['ssl_tage'] !== null && !empty($w['ssl_expires_at'])) {
            $zeilen[] = [$L(['it' => 'Certificato HTTPS valido fino al', 'de' => 'HTTPS-Zertifikat gültig bis', 'en' => 'HTTPS certificate valid until']), Dokument::datum((string) $w['ssl_expires_at'])];
        }
        $sich = (int) self::still(static fn() => Db::wert("SELECT COUNT(*) FROM files WHERE project_id = ? AND (rolle = 'sicherung' OR orig_name LIKE 'sicherung-%') AND created_at BETWEEN ? AND ?",
            [$pid, $von . ' 00:00:00', $bis . ' 23:59:59'], 0), 0);
        $zeilen[] = [$L(['it' => 'Copie di sicurezza nel mese', 'de' => 'Sicherungen im Monat', 'en' => 'Backups this month']), (string) $sich];
        if ($st['abweichung']) {
            $zeilen[] = [$L(['it' => 'Controllo integrità', 'de' => 'Unverändert gegenüber der Freigabe', 'en' => 'Integrity check']),
                !empty($st['abweichung']['ok']) ? $L(['it' => 'sì', 'de' => 'ja', 'en' => 'yes']) : $L(['it' => 'differenze trovate — le sistemo', 'de' => 'Abweichung gefunden — ich kümmere mich', 'en' => 'differences found — I’m on it'])];
        }
        if ($st['seitencheck']) {
            $zeilen[] = [$L(['it' => 'Controllo pagine (link, contatti, note legali)', 'de' => 'Seitenprüfung (Links, Kontakt, Rechtliches)', 'en' => 'Page check (links, contact, legal)']),
                (int) $st['seitencheck']['befunde'] === 0 ? $L(['it' => 'tutto a posto', 'de' => 'alles in Ordnung', 'en' => 'all good']) : (int) $st['seitencheck']['befunde'] . ' ' . $L(['it' => 'punti da sistemare', 'de' => 'Punkte offen', 'en' => 'open points'])];
        }
        $d->ueberschrift('', $L(['it' => 'Stato del suo sito', 'de' => 'So steht Ihre Website', 'en' => 'Your website at a glance']));
        $d->positionen(array_map(static fn($z, $i) => ['pos' => (string) ($i + 1), 'titel' => $z[0], 'text' => '', 'menge' => '', 'einzel' => '', 'gesamt' => $z[1]], $zeilen, array_keys($zeilen)),
            ['pos' => '', 'bez' => $L(['it' => 'Controllo', 'de' => 'Prüfung', 'en' => 'Check']), 'menge' => '', 'einzel' => '', 'gesamt' => $L(['it' => 'Risultato', 'de' => 'Ergebnis', 'en' => 'Result']), 'uebertrag' => '', 'weiter' => '%d']);
        $aend = (array) self::still(static fn() => Db::all("SELECT text, umgesetzt_am FROM projekt_wuensche WHERE project_id = ? AND status = 'umgesetzt' AND umgesetzt_am BETWEEN ? AND ? ORDER BY umgesetzt_am",
            [$pid, $von . ' 00:00:00', $bis . ' 23:59:59']), []);
        $d->kasten($L(['it' => 'Modifiche nel mese', 'de' => 'Änderungen im Monat', 'en' => 'Changes this month']),
            array_map(static fn($a) => [mb_substr((string) $a['text'], 0, 160), '', Dokument::datum((string) $a['umgesetzt_am'])], array_slice($aend, 0, 12)),
            $aend ? '' : $L(['it' => 'Nessuna modifica richiesta questo mese.', 'de' => 'In diesem Monat wurde keine Änderung gewünscht.', 'en' => 'No changes requested this month.']));
        $k = $st['kontingent'];
        if ($k['vertrag'] !== null && $k['minuten'] > 0) {
            $km = self::kontingent($pid, $monat);
            $d->absatz($L(['it' => 'Assistenza ', 'de' => 'Betreuung ', 'en' => 'Care plan ']) . $km['vertrag'] . ': ' . $km['verbraucht'] . ' / ' . $km['minuten'] . ' '
                . $L(['it' => 'minuti di modifiche utilizzati.', 'de' => 'Minuten Änderungen genutzt.', 'en' => 'minutes of changes used.']), 9.5, Dokument::TINTE);
        }
        $d->hinweis($L(['it' => 'Questo rapporto è generato dai controlli automatici del sito. Per qualsiasi domanda mi scriva dalla sua pagina cliente.',
            'de' => 'Dieser Bericht entsteht aus den automatischen Prüfungen Ihrer Website. Bei Fragen schreiben Sie mir über Ihre Kundenseite.',
            'en' => 'This report is generated from the automatic checks of your website. Any questions — write to me from your customer page.']));
        return $d->fertig(['Title' => $titel . ' ' . $p['veroeffentlicht_domain'] . ' ' . $monat]);
    }
}
