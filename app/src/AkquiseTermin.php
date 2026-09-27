<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquiseGate.php';
require_once __DIR__ . '/Texte.php';

/**
 * Terminbuchung (27.09.2026, Uwe: „Terminbuchung“ mit eigenem Kalender).
 *
 * FREIE ZEITEN
 * Aus einem Wochenplan („Mo 10:00-12:00, 15:00-17:00“), der Gesprächsdauer,
 * einem Vorlauf (niemand bucht für in einer Stunde) und einem Horizont.
 * Gesperrte Tage (Urlaub, Feiertag) fallen heraus. Gebuchte Zeiten auch --
 * und das doppelte Buchen verhindert nicht diese Rechnung, sondern der
 * eindeutige Schlüssel in der Tabelle: Wer zwei Sekunden später dieselbe
 * Uhrzeit abschickt, bekommt „gerade vergeben“.
 *
 * WAS RAUSGEHT
 * Eine Bestätigung an den Buchenden (er hat sie gerade selbst angefordert),
 * eine Erinnerung am Vortag, bei einer Absage durch Vecom eine Nachricht.
 * Das sind keine Werbemails: Sie hängen an keinem Akquise-Schalter und an
 * keinem Testbetrieb. Uwe bekommt eine Meldung.
 */
final class AkquiseTermin
{
    public const THEMEN = ['neu', 'ueberarbeiten', 'analyse', 'preis', 'sonst'];
    public const JE_ADRESSE = 3;

    public static function an(): bool { return AkquiseGate::einstellung('akq_termin_an', '1') === '1'; }

    /** @return array{dauer:int, vorlauf:int, tage:int, gesperrt:list<string>} */
    public static function einstellungen(): array
    {
        $gesperrt = array_values(array_filter(array_map('trim', explode(',', AkquiseGate::einstellung('akq_termin_gesperrt', ''))),
            static fn($d) => (bool) preg_match('~^\d{4}-\d{2}-\d{2}$~', $d)));
        return [
            'dauer' => max(10, min(120, (int) AkquiseGate::einstellung('akq_termin_dauer', '30'))),
            'vorlauf' => max(0, min(168, (int) AkquiseGate::einstellung('akq_termin_vorlauf', '18'))),
            'tage' => max(1, min(60, (int) AkquiseGate::einstellung('akq_termin_tage', '21'))),
            'gesperrt' => $gesperrt,
        ];
    }

    /** Wochenplan als Text je Wochentag (1 = Montag), z. B. "10:00-12:00, 15:00-17:00". @return array<int,string> */
    public static function planText(): array
    {
        $roh = json_decode(AkquiseGate::einstellung('akq_termin_plan', ''), true);
        $out = [];
        for ($t = 1; $t <= 7; $t++) { $out[$t] = is_array($roh) ? (string) ($roh[(string) $t] ?? '') : ''; }
        return $out;
    }

    /** @return array<int, list<array{0:int,1:int}>> Wochentag => Zeitfenster in Minuten */
    public static function plan(): array
    {
        $out = [];
        foreach (self::planText() as $t => $text) { $out[$t] = self::fenster($text); }
        return $out;
    }

    /** "10:00-12:00, 15-17" → [[600,720],[900,1020]]; Unlesbares fällt heraus. @return list<array{0:int,1:int}> */
    public static function fenster(string $text): array
    {
        $out = [];
        foreach (preg_split('~[,;]+~', $text) ?: [] as $teil) {
            if (!preg_match('~^\s*(\d{1,2})(?:[:.](\d{2}))?\s*[-–]\s*(\d{1,2})(?:[:.](\d{2}))?\s*$~u', $teil, $m)) { continue; }
            $von = (int) $m[1] * 60 + (int) ($m[2] ?? 0); $bis = (int) $m[3] * 60 + (int) ($m[4] ?? 0);
            if ($von < $bis && $bis <= 24 * 60) { $out[] = [$von, $bis]; }
        }
        return $out;
    }

    /** @param array<int,string> $text */
    public static function einstellungenSetzen(array $text, int $dauer, int $vorlauf, int $tage, string $gesperrt, bool $an): void
    {
        $plan = [];
        for ($t = 1; $t <= 7; $t++) {
            $f = self::fenster((string) ($text[$t] ?? ''));
            $plan[(string) $t] = implode(', ', array_map(static fn($x) => sprintf('%02d:%02d-%02d:%02d', intdiv($x[0], 60), $x[0] % 60, intdiv($x[1], 60), $x[1] % 60), $f));
        }
        AkquiseGate::setzen('akq_termin_plan', (string) json_encode($plan));
        AkquiseGate::setzen('akq_termin_dauer', (string) max(10, min(120, $dauer)));
        AkquiseGate::setzen('akq_termin_vorlauf', (string) max(0, min(168, $vorlauf)));
        AkquiseGate::setzen('akq_termin_tage', (string) max(1, min(60, $tage)));
        $tageListe = [];
        foreach (preg_split('~[\s,;]+~', $gesperrt) ?: [] as $d) {
            $d = trim($d);
            if (preg_match('~^(\d{1,2})\.(\d{1,2})\.(\d{4})$~', $d, $m)) { $d = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]); }
            if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $d) && strtotime($d) !== false) { $tageListe[] = $d; }
        }
        AkquiseGate::setzen('akq_termin_gesperrt', implode(',', array_unique($tageListe)));
        AkquiseGate::setzen('akq_termin_an', $an ? '1' : '0');
    }

    /**
     * Freie Anfangszeiten ab jetzt. @return array<string, list<string>> 'Y-m-d' => ['10:00', …]
     * @param int|null $jetzt für die Kette
     */
    public static function freie(?int $jetzt = null): array
    {
        if (!self::an()) { return []; }
        $jetzt ??= time();
        $e = self::einstellungen();
        $plan = self::plan();
        $frueh = $jetzt + $e['vorlauf'] * 3600;
        $belegt = array_flip(array_column(Db::all("SELECT beginn FROM akq_termine WHERE belegt = 1 AND beginn >= ?", [date('Y-m-d H:i:s', $jetzt)]), 'beginn'));
        $out = [];
        for ($i = 0; $i <= $e['tage']; $i++) {
            $tag = strtotime(date('Y-m-d', $jetzt) . ' +' . $i . ' days');
            $datum = date('Y-m-d', $tag);
            if (in_array($datum, $e['gesperrt'], true)) { continue; }
            foreach ($plan[(int) date('N', $tag)] ?? [] as [$von, $bis]) {
                for ($m = $von; $m + $e['dauer'] <= $bis; $m += $e['dauer']) {
                    $beginn = strtotime($datum . ' 00:00') + $m * 60;
                    if ($beginn < $frueh) { continue; }
                    if (isset($belegt[date('Y-m-d H:i:s', $beginn)])) { continue; }
                    $out[$datum][] = date('H:i', $beginn);
                }
            }
        }
        return $out;
    }

    /**
     * Buchen. @return array{ok:bool, grund?:string, token?:string}
     * grund: aus | zeit | belegt | angaben | zuviel
     */
    public static function buchen(array $e, string $ip = ''): array
    {
        if (!self::an()) { return ['ok' => false, 'grund' => 'aus']; }
        $slot = (string) ($e['slot'] ?? '');
        if (!preg_match('~^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})$~', $slot, $m)) { return ['ok' => false, 'grund' => 'zeit']; }
        $name = trim((string) ($e['name'] ?? '')); $email = Akquise::normEmail($e['email'] ?? null);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || $email === null) { return ['ok' => false, 'grund' => 'angaben']; }
        $thema = in_array($e['thema'] ?? '', self::THEMEN, true) ? (string) $e['thema'] : 'sonst';
        $sprache = in_array($e['sprache'] ?? '', ['de', 'it', 'en'], true) ? (string) $e['sprache'] : 'it';
        $frei = self::freie();
        if (!in_array($m[2], $frei[$m[1]] ?? [], true)) {
            $gibt = Db::wert('SELECT id FROM akq_termine WHERE beginn = ? AND belegt = 1', [$m[1] . ' ' . $m[2] . ':00'], null);
            return ['ok' => false, 'grund' => $gibt !== null ? 'belegt' : 'zeit'];
        }
        $ipHash = $ip !== '' ? hash('sha256', $ip . '|' . Config::get('app_geheim', 'vecom')) : null;
        $jetzt = date('Y-m-d H:i:s');
        if ((int) Db::wert('SELECT COUNT(*) FROM akq_termine WHERE belegt = 1 AND email = ? AND beginn >= ?', [$email, $jetzt], 0) >= 1
            || ($ipHash !== null && (int) Db::wert('SELECT COUNT(*) FROM akq_termine WHERE ip_hash = ? AND created_at >= CURDATE()', [$ipHash], 0) >= self::JE_ADRESSE)) {
            return ['ok' => false, 'grund' => 'zuviel'];
        }
        $beginn = strtotime($slot);
        $firmaId = Db::wert('SELECT id FROM akq_firmen WHERE email = ? ORDER BY id LIMIT 1', [$email], null)
            ?? Db::wert('SELECT firma_id FROM akq_checks WHERE email = ? AND firma_id IS NOT NULL ORDER BY id DESC LIMIT 1', [$email], null);
        $token = bin2hex(random_bytes(16));
        $telefon = trim((string) ($e['telefon'] ?? ''));
        try {
            $id = (int) Db::insert('akq_termine', [
                'token' => $token, 'beginn' => date('Y-m-d H:i:s', $beginn), 'ende' => date('Y-m-d H:i:s', $beginn + self::einstellungen()['dauer'] * 60),
                'belegt' => 1, 'status' => 'gebucht', 'name' => mb_substr($name, 0, 120), 'firma' => mb_substr(trim((string) ($e['firma'] ?? '')), 0, 190) ?: null,
                'email' => $email, 'telefon' => $telefon !== '' ? mb_substr($telefon, 0, 40) : null, 'sprache' => $sprache, 'thema' => $thema,
                'art' => ($e['art'] ?? '') === 'video' ? 'video' : 'telefon', 'nachricht' => mb_substr(trim((string) ($e['nachricht'] ?? '')), 0, 500) ?: null,
                'firma_id' => $firmaId !== null ? (int) $firmaId : null, 'ip_hash' => $ipHash, 'created_at' => $jetzt,
            ]);
        } catch (Throwable $x) {
            if (Db::doppelt($x, 'uq_akq_termin_slot')) { return ['ok' => false, 'grund' => 'belegt']; }
            throw $x;
        }
        $t = Db::one('SELECT * FROM akq_termine WHERE id = ?', [$id]) ?? [];
        self::mail($t, 'mail_betreff', 'mail_text', 'termin_bestaetigung');
        if ($firmaId !== null) { Akquise::protokoll((int) $firmaId, 'termin', 'Termin gebucht: ' . date('d.m.Y H:i', $beginn) . ' (' . $t['art'] . ', ' . $thema . ')'); }
        try {
            Events::melden('termin', 'Neuer Termin: ' . date('d.m. H:i', $beginn) . ' — ' . $name . ($t['firma'] ? ' (' . $t['firma'] . ')' : ''), 'gut',
                ($t['art'] === 'video' ? 'Video' : 'Telefon') . ' · ' . strtoupper($sprache) . ' · ' . $email . ($telefon !== '' ? ' · ' . $telefon : ''), 'akquise/termine');
        } catch (Throwable $x) { }
        return ['ok' => true, 'token' => $token];
    }

    public static function laden(string $token): ?array
    {
        if (!preg_match('~^[a-f0-9]{32}$~', $token)) { return null; }
        return Db::one('SELECT * FROM akq_termine WHERE token = ?', [$token]);
    }

    public static function link(string $token = '', string $sprache = 'it'): string
    {
        $b = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/termin.php';
        return $token !== '' ? $b . '?t=' . $token : $b . ($sprache !== 'it' ? '?lang=' . $sprache : '');
    }

    /** Absagen -- vom Kunden über seinen Link oder von Uwe in der Verwaltung (dann mit Nachricht an den Kunden). */
    public static function absagen(int $id, string $von = 'kunde'): bool
    {
        $t = Db::one('SELECT * FROM akq_termine WHERE id = ?', [$id]);
        if (!$t || $t['status'] !== 'gebucht') { return false; }
        Db::update('akq_termine', $id, ['status' => 'abgesagt', 'belegt' => null, 'abgesagt_von' => $von === 'vecom' ? 'vecom' : 'kunde']);
        if ($von === 'vecom') { self::mail($t, 'absage_betreff', 'absage_text', 'termin_absage'); }
        if ($t['firma_id']) { Akquise::protokoll((int) $t['firma_id'], 'termin', 'Termin ' . date('d.m.Y H:i', strtotime((string) $t['beginn'])) . ' abgesagt (' . ($von === 'vecom' ? 'von Vecom' : 'vom Kunden') . ')'); }
        try { if ($von !== 'vecom') { Events::melden('termin', 'Termin abgesagt: ' . date('d.m. H:i', strtotime((string) $t['beginn'])) . ' — ' . $t['name'], 'warnung', null, 'akquise/termine'); } } catch (Throwable $x) { }
        return true;
    }

    public static function erledigt(int $id): void
    {
        Db::run("UPDATE akq_termine SET status = 'erledigt' WHERE id = ? AND status = 'gebucht'", [$id]);
    }

    /** Cron: Erinnerung am Vortag (zwischen 30 und 12 Stunden vorher), genau einmal. */
    public static function erinnern(?int $jetzt = null): int
    {
        $jetzt ??= time();
        $n = 0;
        foreach (Db::all("SELECT * FROM akq_termine WHERE status = 'gebucht' AND erinnert_am IS NULL AND beginn BETWEEN ? AND ?",
                     [date('Y-m-d H:i:s', $jetzt + 12 * 3600), date('Y-m-d H:i:s', $jetzt + 30 * 3600)]) as $t) {
            Db::update('akq_termine', (int) $t['id'], ['erinnert_am' => date('Y-m-d H:i:s', $jetzt)]);
            self::mail($t, 'erinnerung_betreff', 'erinnerung_text', 'termin_erinnerung');
            $n++;
        }
        return $n;
    }

    private static function mail(array $t, string $betreffK, string $textK, string $anlass): void
    {
        if (empty($t['email'])) { return; }
        require_once __DIR__ . '/Mail.php';
        require_once __DIR__ . '/AkquiseText.php';
        $sp = (string) $t['sprache'];
        $T = static fn(string $k) => Texte::h(Texte::AKQ_TERMIN[$k] ?? [], $sp);
        $abs = AkquiseText::absender();
        $b = strtotime((string) $t['beginn']);
        $ersatz = ['{name}' => (string) $t['name'], '{zeit}' => self::zeitText($b, $sp), '{uhr}' => date('H:i', $b),
                   '{art}' => $T('art_' . $t['art']), '{thema}' => $T('thema_' . $t['thema']), '{link}' => self::link((string) $t['token']),
                   '{buchen}' => self::link('', $sp), '{absender}' => (string) $abs['firma'], '{inhaber}' => (string) $abs['inhaber']];
        try { Mail::senden($anlass, (string) $t['email'], strtr($T($betreffK), $ersatz), strtr($T($textK), $ersatz), ['nurText' => true, 'sprache' => $sp]); }
        catch (Throwable $x) { }
    }

    /** „Di 30.09. · 10:00“ in der Sprache des Buchenden. */
    public static function zeitText(int $ts, string $sprache): string
    {
        $tage = explode(',', Texte::h(Texte::AKQ_TERMIN['tage'], $sprache));
        return ($tage[(int) date('N', $ts) - 1] ?? '') . ' ' . date('d.m.Y', $ts) . ' · ' . date('H:i', $ts);
    }

    /** Kalenderdatei für den eigenen Kalender (RFC 5545). */
    public static function ics(array $t): string
    {
        $abs = ['firma' => 'Vecom Design'];
        try { require_once __DIR__ . '/AkquiseText.php'; $abs = AkquiseText::absender(); } catch (Throwable $x) { }
        $utc = static fn(string $d) => gmdate('Ymd\THis\Z', strtotime($d));
        $esc = static fn(string $s) => str_replace(["\\", ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $s);
        $T = static fn(string $k) => Texte::h(Texte::AKQ_TERMIN[$k] ?? [], (string) $t['sprache']);
        return implode("\r\n", [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Vecom Design//Termin//DE', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'BEGIN:VEVENT',
            'UID:' . $t['token'] . '@vecom-design.it', 'DTSTAMP:' . gmdate('Ymd\THis\Z'), 'DTSTART:' . $utc((string) $t['beginn']), 'DTEND:' . $utc((string) $t['ende']),
            'SUMMARY:' . $esc((string) $abs['firma'] . ' · ' . $T('art_' . $t['art'])),
            'DESCRIPTION:' . $esc($T('thema_' . $t['thema']) . "\n" . self::link((string) $t['token'])),
            'END:VEVENT', 'END:VCALENDAR', '',
        ]);
    }

    /** Satz für die Folge-Mail „Einladung zum Gespräch“ -- leer, solange es keine freie Zeit gibt. */
    public static function satzFuerMail(string $sprache): string
    {
        try { if (self::freie() === []) { return ''; } } catch (Throwable $x) { return ''; }
        return strtr(Texte::h(Texte::AKQ_TERMIN['mail_satz'], $sprache), ['{link}' => self::link('', $sprache)]);
    }

    /** @return list<array> für die Verwaltung */
    public static function liste(bool $kommend = true, int $n = 50): array
    {
        return $kommend
            ? Db::all("SELECT * FROM akq_termine WHERE status = 'gebucht' AND ende >= ? ORDER BY beginn LIMIT " . max(1, $n), [date('Y-m-d H:i:s')])
            : Db::all("SELECT * FROM akq_termine WHERE status <> 'gebucht' OR ende < ? ORDER BY beginn DESC LIMIT " . max(1, $n), [date('Y-m-d H:i:s')]);
    }
}
